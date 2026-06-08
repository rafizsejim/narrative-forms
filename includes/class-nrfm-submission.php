<?php
/** Submission handling */
class NRFM_Submission {
    /**
     * Safe accessor for $_SERVER values with unslash and scalar guard.
     */
    private function get_server_value($key) {
        // Use a sanitizing filter for server values to avoid raw input.
        $val = filter_input(INPUT_SERVER, (string) $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        return is_string($val) ? (string) $val : '';
    }
    private function get_sanitized_user_agent() {
        $raw = $this->get_server_value('HTTP_USER_AGENT');
        return $raw !== '' ? sanitize_text_field($raw) : '';
    }

    private function get_sanitized_referer() {
        $raw = $this->get_server_value('HTTP_REFERER');
        return $raw !== '' ? esc_url_raw($raw) : '';
    }

    private function get_mysql_now() {
        return current_time('mysql');
    }

    /**
     * Sanitize a $_FILES array entry immediately on access.
     * Returns a clean array with sanitized name/type and validated tmp_name/error/size.
     */
    private function sanitize_file_array($raw) {
        if (!is_array($raw)) {
            return array('name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0);
        }
        $is_multi = isset($raw['name']) && is_array($raw['name']);
        if ($is_multi) {
            $count = count($raw['name']);
            $clean = array('name' => array(), 'type' => array(), 'tmp_name' => array(), 'error' => array(), 'size' => array());
            for ($i = 0; $i < $count; $i++) {
                $clean['name'][$i]     = isset($raw['name'][$i]) && is_string($raw['name'][$i]) ? sanitize_file_name($raw['name'][$i]) : '';
                $clean['type'][$i]     = isset($raw['type'][$i]) && is_string($raw['type'][$i]) ? sanitize_text_field($raw['type'][$i]) : '';
                $clean['tmp_name'][$i] = isset($raw['tmp_name'][$i]) && is_string($raw['tmp_name'][$i]) ? (string) $raw['tmp_name'][$i] : '';
                $clean['error'][$i]    = isset($raw['error'][$i]) && is_numeric($raw['error'][$i]) ? (int) $raw['error'][$i] : UPLOAD_ERR_OK;
                $clean['size'][$i]     = isset($raw['size'][$i]) && is_numeric($raw['size'][$i]) ? (int) $raw['size'][$i] : 0;
            }
            return $clean;
        }
        return array(
            'name'     => isset($raw['name']) && is_string($raw['name']) ? sanitize_file_name($raw['name']) : '',
            'type'     => isset($raw['type']) && is_string($raw['type']) ? sanitize_text_field($raw['type']) : '',
            'tmp_name' => isset($raw['tmp_name']) && is_string($raw['tmp_name']) ? (string) $raw['tmp_name'] : '',
            'error'    => isset($raw['error']) && is_numeric($raw['error']) ? (int) $raw['error'] : UPLOAD_ERR_NO_FILE,
            'size'     => isset($raw['size']) && is_numeric($raw['size']) ? (int) $raw['size'] : 0,
        );
    }

    private $last_error_code = null;
    
    public function process($form_id, $data) {
        $this->last_error_code = null;
        $form = new NRFM_Form($form_id);
        
        if (!$form->exists()) {
            return array(
                'success' => false,
                'message' => __('Form not found', 'narrative-forms'),
            );
        }
        
        // File uploads
        $file_data = $this->handle_file_uploads($form);
        if ($file_data === false) {
            return array(
                'success' => false,
                'message' => $form->get_message($this->last_error_code ?: 'error'),
            );
        }
        if (is_array($file_data)) {
            $data = array_merge($data, $file_data);
        }
        
        // Convert inline base64 image data URLs (e.g. signature pads) into stored files so the
        // submission row holds a small URL instead of a huge blob. Content-based auto-detection.
        $data = $this->store_data_url_images($data);

        // Clean
        $cleaned_data = $this->clean_data($data);
        
        // Validate
        $validation = $this->validate($cleaned_data, $form);
        if (!$validation['valid']) {
            // Optional error redirect
            $err_settings = $form->get_settings();
            $err_redirect = isset($err_settings['redirect_error_url']) ? $err_settings['redirect_error_url'] : '';
            return array(
                'success' => false,
                'message' => $form->get_message($validation['error_code']),
                'redirect_url' => self::token_replace_redirect($err_redirect, $cleaned_data),
            );
        }
        
        // Save submission if enabled
        $settings = $form->get_settings();
        if ($settings['save_submissions']) {
            $this->save($form_id, $cleaned_data);
        }
        
        // Actions — only run the pipeline when the form actually has actions to process.
        // This mirrors the empty-actions guard inside process_actions(), hoisted up so that
        // action-less forms (the common "just store submissions" case) never enqueue a no-op
        // async job. At scale this avoids needless WP-Cron / Action Scheduler churn on every
        // submission. The nrfm_form_submitted hook below still fires for every submission.
        if (!empty(get_post_meta($form_id, '_nrfm_actions', true))) {
            if (!empty($settings['async_actions'])) {
                // Queue for async processing (Action Scheduler if available, else WP-Cron)
                $job = array(
                    'form_id' => $form_id,
                    'data' => $cleaned_data,
                    'when' => time(),
                );
                if ( function_exists( 'nrfm_enqueue_actions_job' ) ) {
                    nrfm_enqueue_actions_job( $job );
                } else {
                    wp_schedule_single_event(time() + 5, 'nrfm_process_actions_async', array($job));
                }
            } else {
                $this->process_actions($form, $cleaned_data);
            }
        }
        
        // Hook
        do_action('nrfm_form_submitted', $form_id, $cleaned_data);
        
        $result = array(
            'success' => true,
            'message' => $form->get_message('success'),
            'hide_form' => $settings['hide_after_success'],
            'redirect_url' => self::token_replace_redirect($settings['redirect_url'], $cleaned_data),
        );
        return $result;
    }

    /**
     * Replace tokens like [FIELD] and built-ins in a redirect URL template.
     * Values are URL-encoded. Unknown tokens are removed.
     */
    public static function token_replace_redirect($template, $data) {
        $url = trim((string)$template);
        if ($url === '') return '';
        $inst = new self();
        $ip_addr   = $inst->get_ip();
        $user_agent = $inst->get_sanitized_user_agent();
        $referrer  = $inst->get_sanitized_referer();
        $builtins = array(
            '[NRFM_TIMESTAMP]' => current_time('mysql'),
            '[NRFM_IP_ADDRESS]' => $ip_addr,
            '[NRFM_USER_AGENT]' => $user_agent,
            '[NRFM_REFERRER_URL]' => $referrer,
        );
        $url = strtr($url, $builtins);
        foreach ($data as $key => $value) {
            $tok = '[' . strtoupper($key) . ']';
            if (strpos($url, $tok) !== false) {
                if (is_array($value)) {
                    if (isset($value['name'])) { $value = $value['name']; }
                    else { $value = implode(', ', array_map('strval', $value)); }
                }
                $url = str_replace($tok, rawurlencode((string)$value), $url);
            }
        }
        $url = preg_replace('/\[[A-Z0-9_]+\]/', '', $url);
        return $url;
    }
    
    private function handle_file_uploads($form) {
        // Nonce is verified upstream in handlers; this method only runs after verification.
        if (empty($_FILES)) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in upstream handlers
            return array();
        }
        
        $upload_dir = wp_upload_dir();
        $file_data = array();
        $form_html = $form instanceof NRFM_Form ? $form->get_html() : '';
        
        // Restrict to file inputs that exist in the form markup
        $allowed_file_names = array();
        if ($form_html && preg_match_all('/<input[^>]*type=["\']file["\'][^>]*name=["\']([^"\']+)["\']/i', $form_html, $fm)) {
            foreach ($fm[1] as $nm) {
                $allowed_file_names[str_replace('[]', '', $nm)] = true;
            }
        }
        // If the form has no file inputs, do not iterate the global stack
        if (empty($allowed_file_names)) {
            return array();
        }

        $raw_files = is_array( $_FILES ) ? $_FILES : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in upstream handlers
        foreach (array_keys($allowed_file_names) as $field_name) {
            if (!isset($raw_files[$field_name])) { continue; }

            // Sanitize file data immediately into a clean structure
            $file = $this->sanitize_file_array($raw_files[$field_name]);

            // Skip if no file uploaded
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            
            // Check for upload errors
            if (!is_array($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
                $this->last_error_code = 'upload_error';
                return false;
            }
            
            // Validate file type
            $allowed_types = array(
                'jpg', 'jpeg', 'png', 'gif', 'pdf', 
                'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                'zip', 'txt', 'csv'
            );

            $is_multiple_upload = is_array($file['name']);
            $allows_multiple = false;
            if ($form_html) {
                if (preg_match('/<input[^>]*type=["\']file["\'][^>]*name=["\']' . preg_quote($field_name, '/') . '["\'][^>]*>/i', $form_html, $m)) {
                    $allows_multiple = (bool) preg_match('/\smultiple(\s|>|\z)/i', $m[0]);
                }
            }
            if ($is_multiple_upload && !$allows_multiple) {
                $this->last_error_code = 'max_files';
                return false;
            }

            // Build sanitized files array for processing
            $files_to_check = array();
            if ($is_multiple_upload) {
                $count = is_array($file['name']) ? count($file['name']) : 0;
                for ($i = 0; $i < $count; $i++) {
                    $files_to_check[] = array(
                        'name'     => isset($file['name'][$i]) ? $file['name'][$i] : '',
                        'type'     => isset($file['type'][$i]) ? $file['type'][$i] : '',
                        'tmp_name' => isset($file['tmp_name'][$i]) ? $file['tmp_name'][$i] : '',
                        'error'    => isset($file['error'][$i]) ? $file['error'][$i] : UPLOAD_ERR_OK,
                        'size'     => isset($file['size'][$i]) ? $file['size'][$i] : 0,
                    );
                }
            } else {
                $files_to_check[] = array(
                    'name'     => $file['name'],
                    'type'     => $file['type'],
                    'tmp_name' => $file['tmp_name'],
                    'error'    => $file['error'],
                    'size'     => $file['size'],
                );
            }

            // Enforce per-field max file count if provided
            $max_key = 'nrfm_max_files_' . $field_name;
            $max_files = isset($_POST[$max_key]) ? intval(wp_unslash($_POST[$max_key])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream
            if ($max_files > 0 && count($files_to_check) > $max_files) {
                // Trim to first N files; do not fail the request, just ignore extras
                $files_to_check = array_slice($files_to_check, 0, $max_files);
            }

            foreach ($files_to_check as $single) {
                $original_name = is_string($single['name']) ? $single['name'] : '';
                $safe_name = sanitize_file_name($original_name);
                $file_ext = strtolower(pathinfo($safe_name, PATHINFO_EXTENSION));
                if (!in_array($file_ext, $allowed_types, true)) {
                    $this->last_error_code = 'invalid_file_type';
                    return false;
                }
            
            // Determine per-field max size: request (hidden input) vs server cap
            $max_size_key = 'nrfm_max_' . $field_name;
            $requested_max = isset($_POST[$max_size_key]) ? intval(wp_unslash($_POST[$max_size_key])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream
            $server_cap   = function_exists('wp_max_upload_size') ? intval(wp_max_upload_size()) : 0;
            $effective_max = $requested_max > 0 ? $requested_max : (5 * 1024 * 1024);
            if ($server_cap > 0) {
                $effective_max = min($effective_max, $server_cap);
            }
            if ($effective_max > 0 && $single['size'] > $effective_max) {
                $this->last_error_code = 'file_too_large';
                return false;
            }
            
            // Verify file type and extension using WP core helper
            $check = function_exists('wp_check_filetype_and_ext') ? wp_check_filetype_and_ext($single['tmp_name'], $safe_name) : array('ext' => $file_ext, 'type' => $single['type']);
            if (empty($check['ext']) || empty($check['type'])) {
                $this->last_error_code = 'invalid_file_type';
                return false;
            }

            // Hand off to WordPress to move/sideload safely
            $sideload = array(
                'name'     => $safe_name,
                'type'     => isset($check['type']) ? $check['type'] : '',
                'tmp_name' => $single['tmp_name'],
                'error'    => 0,
                'size'     => (int) $single['size'],
            );
            // Ensure upload dir exists
            if (!function_exists('wp_handle_sideload')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            $overrides = array(
                'test_form' => false, // not from $_FILES
            );
            $result = wp_handle_sideload($sideload, $overrides);
            if (!is_wp_error($result) && !empty($result['file']) && !empty($result['url'])) {
                $entry = array(
                    'name' => $safe_name,
                    'path' => $result['file'],
                    'url'  => esc_url_raw($result['url']),
                    'type' => isset($result['type']) ? sanitize_text_field($result['type']) : sanitize_text_field($check['type']),
                    'size' => (int) $single['size']
                );
                if ($is_multiple_upload) {
                    if (!isset($file_data[$field_name]) || !is_array($file_data[$field_name])) {
                        $file_data[$field_name] = array();
                    }
                    $file_data[$field_name][] = $entry;
                } else {
                    $file_data[$field_name] = $entry;
                }
            } else {
                $this->last_error_code = 'upload_error';
                return false;
            }
            }
        }
        
        return $file_data;
    }
    
    /**
     * Auto-detect inline base64 image data URLs (e.g. signature-pad output written into a
     * hidden input) and store them as real files, replacing the value with the same file-entry
     * shape handle_file_uploads() produces. This keeps the submission row small and lets the
     * admin, CSV export and Views render a link/thumbnail automatically.
     *
     * Content-based detection — any image data URL in a normal field is converted; no field
     * configuration is needed. Toggle/limit with the nrfm_store_data_urls,
     * nrfm_data_url_mime_types and nrfm_data_url_max_bytes filters.
     *
     * @param array $data Submitted field values (after file uploads are merged in).
     * @return array
     */
    private function store_data_url_images($data) {
        if (!is_array($data) || !apply_filters('nrfm_store_data_urls', true)) {
            return $data;
        }

        // Image mime => file extension. Only these are ever decoded and written to disk.
        $allowed = apply_filters('nrfm_data_url_mime_types', array(
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ));

        // Max decoded byte size, bounded by the server's upload cap.
        $max_bytes  = (int) apply_filters('nrfm_data_url_max_bytes', 5 * 1024 * 1024);
        $server_cap = function_exists('wp_max_upload_size') ? (int) wp_max_upload_size() : 0;
        if ($server_cap > 0 && $max_bytes > 0) {
            $max_bytes = min($max_bytes, $server_cap);
        }

        foreach ($data as $key => $value) {
            // Only scalar strings that look like an image data URL are candidates.
            if (!is_string($value) || stripos($value, 'data:image/') !== 0) {
                continue;
            }
            // Mirror clean_data(): never touch internal/captcha fields.
            if (strpos($key, 'nrfm_') === 0 || strpos($key, '_') === 0) {
                continue;
            }
            if (!preg_match('#^data:(image/[a-z0-9.+-]+);base64,#i', $value, $m)) {
                continue;
            }
            $mime = strtolower($m[1]);
            if (!isset($allowed[$mime])) {
                continue; // Unknown image type: leave the value untouched.
            }

            $b64 = substr($value, strlen($m[0]));
            // Cheap size guard before decoding (base64 is ~4/3 of the binary size).
            if ($max_bytes > 0 && (int) (strlen($b64) * 0.75) > $max_bytes) {
                $data[$key] = '';
                continue;
            }
            $binary = base64_decode($b64, true);
            if ($binary === false || $binary === '' || ($max_bytes > 0 && strlen($binary) > $max_bytes)) {
                $data[$key] = '';
                continue;
            }
            // Confirm the bytes really are the image type claimed (defeats disguised payloads).
            $probe = function_exists('getimagesizefromstring') ? @getimagesizefromstring($binary) : false;
            if (!is_array($probe) || empty($probe['mime']) || strtolower($probe['mime']) !== $mime) {
                $data[$key] = '';
                continue;
            }

            // Hand the bytes to WordPress: it picks the uploads dir, makes the name unique, and
            // rejects any extension not in the site's allowed mime list.
            $name   = sanitize_file_name($key . '-' . substr(md5($binary), 0, 8) . '.' . $allowed[$mime]);
            $stored = wp_upload_bits($name, null, $binary);
            if (!empty($stored['error']) || empty($stored['file']) || empty($stored['url'])) {
                $data[$key] = ''; // Could not store: drop the blob rather than bloat the row.
                continue;
            }

            // Same shape as a file upload entry, so storage/admin/CSV/Views render it as a link.
            $data[$key] = array(
                'name' => $name,
                'path' => $stored['file'],
                'url'  => esc_url_raw($stored['url']),
                'type' => $mime,
                'size' => strlen($binary),
            );
        }

        return $data;
    }

    private function clean_data($data) {
        $cleaned = array();
        
        foreach ($data as $key => $value) {
            // Skip internal fields and captcha tokens
            if (strpos($key, 'nrfm_') === 0 || strpos($key, '_') === 0) {
                continue;
            }
            if (preg_match('/^(cf-?turnstile-response|g-recaptcha-response|h-captcha-response|recaptcha.*|captcha.*|.*token.*)$/i', $key)) {
                continue;
            }
            
            // Handle file upload data
            if (is_array($value) && isset($value['name']) && isset($value['path'])) {
                // Single file entry shape
                $cleaned[$key] = $value; // Keep file data as is
            } elseif (is_array($value) && !empty($value) && isset($value[0]) && is_array($value[0]) && (isset($value[0]['url']) || isset($value[0]['path']))) {
                // Multiple file entries shape: array of file entry arrays
                $cleaned[$key] = $value; // Preserve as-is so UI can render links for each file
            } elseif (is_array($value)) {
                // Generic arrays (checkboxes, multi-selects)
                $sanitized = array();
                foreach ($value as $v) {
                    if (is_scalar($v)) {
                        $v = wp_unslash((string) $v);
                        $v = trim($v);
                        $v = html_entity_decode($v, ENT_NOQUOTES, 'UTF-8');
                        $sanitized[] = $v;
                    }
                }
                $cleaned[$key] = $sanitized;
            } else {
                $v = wp_unslash((string) $value);
                $v = trim($v);
                $v = html_entity_decode($v, ENT_NOQUOTES, 'UTF-8');
                $cleaned[$key] = $v;
            }
        }
        
        return $cleaned;
    }
    
    private function validate($data, $form) {
        $html = $form->get_html();
        // Global anti-spam settings
        $g = get_option('nrfm_settings', array());
        $timeTrap = isset($g['spam_time_trap_seconds']) ? intval($g['spam_time_trap_seconds']) : 3;
        $sameOrigin = !empty($g['spam_same_origin']);
        $maxLinks = isset($g['spam_max_links']) ? intval($g['spam_max_links']) : 3;
        $rateCount = isset($g['spam_rate_limit_count']) ? intval($g['spam_rate_limit_count']) : 5;
        $rateWindowMin = isset($g['spam_rate_limit_window_min']) ? intval($g['spam_rate_limit_window_min']) : 10;

        // 1) Time trap: ensure form was open at least N seconds
        if ($timeTrap > 0) {
            // Nonce is verified upstream in handlers; read and unslash here
            $started = isset($_POST['nrfm_started_at']) ? intval( wp_unslash($_POST['nrfm_started_at']) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream
            if ($started > 0 && (time() - $started) < $timeTrap) {
                return array('valid' => false, 'error_code' => 'error');
            }
        }
        // 2) Same-origin referrer check
        if ($sameOrigin) {
            $ref = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw( wp_unslash($_SERVER['HTTP_REFERER']) ) : '';
            if ($ref) {
                $site = wp_parse_url(site_url(), PHP_URL_HOST);
                $rhost = wp_parse_url($ref, PHP_URL_HOST);
                if ($site && $rhost && !hash_equals($site, $rhost)) {
                    return array('valid' => false, 'error_code' => 'error');
                }
            }
        }
        
        // Check required fields. Build the list from the markup, then let extensions
        // (e.g. PRO conditional logic) drop fields that are hidden for this submission.
        preg_match_all('/name=["\']([^"\']+)["\'][^>]*required/i', $html, $required_matches);
        $required_fields = array();
        if (!empty($required_matches[1])) {
            foreach ($required_matches[1] as $field_name) {
                // Handle array names like checkboxes[]
                $required_fields[] = str_replace('[]', '', $field_name);
            }
            $required_fields = array_values(array_unique($required_fields));
        }
        /**
         * Filter the required field names enforced for this submission.
         *
         * Lets extensions remove fields that should not be required for this
         * particular submission — e.g. a field hidden by conditional logic.
         * Return a (possibly reduced) array of field names.
         *
         * @param string[]  $required_fields Field names parsed as required from the markup.
         * @param array     $data            Cleaned submission data.
         * @param NRFM_Form $form            The form being validated.
         */
        $required_fields = apply_filters('nrfm_required_fields', $required_fields, $data, $form);
        if (is_array($required_fields)) {
            foreach ($required_fields as $field_name) {
                if (empty($data[$field_name])) {
                    return array(
                        'valid' => false,
                        'error_code' => 'required_field_missing',
                    );
                }
            }
        }
        
        // Check email fields
        preg_match_all('/type=["\']email["\'][^>]*name=["\']([^"\']+)["\']/i', $html, $email_matches);
        if (!empty($email_matches[1])) {
            foreach ($email_matches[1] as $field_name) {
                if (!empty($data[$field_name]) && !is_email($data[$field_name])) {
                    return array(
                        'valid' => false,
                        'error_code' => 'invalid_email',
                    );
                }
            }
        }
        
        // Also check for name="email" fields that might not have type="email"
        foreach ($data as $key => $value) {
            if (strpos($key, 'email') !== false && !empty($value) && !is_email($value)) {
                return array(
                    'valid' => false,
                    'error_code' => 'invalid_email',
                );
            }
        }
        
        // Allow custom validation (expects array shape ['valid'=>bool,'error_code'=>string])
        $filter_result = apply_filters('nrfm_validate_submission', array('valid' => true), $data, $form);
        if (is_array($filter_result) && isset($filter_result['valid']) && $filter_result['valid'] === false) {
            return array('valid' => false, 'error_code' => isset($filter_result['error_code']) ? $filter_result['error_code'] : 'error');
        }
        $passes = true;
        if ($passes && $maxLinks > 0) {
            // 3) Link-count limit on a likely message field
            $message = '';
            foreach ($data as $k=>$v){ if (stripos($k,'message')!==false){ $message=is_array($v)? implode(' ',(array)$v): (string)$v; break; } }
            if ($message){
                $num = preg_match_all('/https?:\/\//i', $message);
                if ($num > $maxLinks) { $passes = false; }
            }
        }
        if ($passes && $rateCount > 0) {
            // 4) IP rate limit
            $ip = $this->get_ip();
            if ($ip) {
                $key = 'nrfm_ip_' . md5($ip);
                $window = max(1, $rateWindowMin) * MINUTE_IN_SECONDS;
                $list = get_transient($key);
                if (!is_array($list)) { $list = array(); }
                $now = time();
                // prune
                $list = array_filter($list, function($ts) use ($now,$window){ return ($now - $ts) < $window; });
                // persist pruned list so the window naturally expires
                set_transient($key, $list, $window);
                if (count($list) >= $rateCount) {
                    $passes = false;
                } else {
                    $list[] = $now;
                    set_transient($key, $list, $window);
                }
            }
        }
        if (!$passes) {
            return array(
                'valid' => false,
                'error_code' => 'error',
            );
        }
        
        return array('valid' => true);
    }
    
    private function save($form_id, $data) {
        global $wpdb;
        
        $table = nrfm_table('submissions');
        
        // Insert submission row (safe values + explicit formats)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $table,
            array(
                'form_id'      => $form_id,
                'data'         => wp_json_encode($data),
                'ip_address'   => $this->get_ip(),
                'user_agent'   => $this->get_sanitized_user_agent(),
                'referer_url'  => $this->get_sanitized_referer(),
                'submitted_at' => $this->get_mysql_now(),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        // Invalidate cached counts for this form
        nrfm_clear_submission_cache( $form_id );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Process configured actions. Public so the async worker can call it directly.
     */
    public function process_actions($form, $data) {
        $actions = get_post_meta($form->get_id(), '_nrfm_actions', true);
        
        if (empty($actions)) {
            return;
        }
        
        // Process email actions (multiple)
        if (!empty($actions['email']) && is_array($actions['email'])) {
            foreach ($actions['email'] as $email_action) {
                if (!empty($email_action['to'])) {
                    $this->send_email($email_action, $data);
                }
            }
        }
        
        // Process webhook actions (multiple)
        if (!empty($actions['webhook']) && is_array($actions['webhook'])) {
            foreach ($actions['webhook'] as $webhook_action) {
                if (!empty($webhook_action['url'])) {
                    $this->send_webhook($webhook_action, $data);
                }
            }
        }
        
        // Hook for custom actions
        do_action('nrfm_process_form_actions', $actions, $data, $form);
    }
    
    private function send_email($email_config, $data) {
        if (empty($email_config['to'])) {
            return;
        }
        
        $to = $email_config['to'];
        $from = !empty($email_config['from']) ? $email_config['from'] : get_option('admin_email');
        $subject = !empty($email_config['subject']) ? $email_config['subject'] : __('New Form Submission', 'narrative-forms');
        $message = !empty($email_config['message']) ? $email_config['message'] : '';
        $content_type = !empty($email_config['content_type']) ? $email_config['content_type'] : 'text/plain';
        
        // Replace field placeholders
        foreach ($data as $key => $value) {
            $placeholder = '[' . strtoupper($key) . ']';
            $replacement = is_array($value) ? implode(', ', $value) : $value;
            
            $to = str_replace($placeholder, $replacement, $to);
            $from = str_replace($placeholder, $replacement, $from);
            $subject = str_replace($placeholder, $replacement, $subject);
            $message = str_replace($placeholder, $replacement, $message);
        }
        
        // Add timestamp and meta placeholders
        $message = str_replace('[NRFM_TIMESTAMP]', $this->get_mysql_now(), $message);
        $message = str_replace('[NRFM_IP_ADDRESS]', $this->get_ip(), $message);
        $message = str_replace('[NRFM_USER_AGENT]', $this->get_sanitized_user_agent(), $message);
        $message = str_replace('[NRFM_REFERRER_URL]', $this->get_sanitized_referer(), $message);
        
        // If no custom message, create default using a clean, line-based template
        if (empty($message)) {
            $message = __('New form submission received:', 'narrative-forms') . "\n\n" . $this->format_submission_for_email($data);
        } else {
            // Support a placeholder to dump all fields line-by-line
            if (strpos($message, '[NRFM_ALL_FIELDS]') !== false) {
                $message = str_replace('[NRFM_ALL_FIELDS]', $this->format_submission_for_email($data), $message);
            }
        }
        
        // Set headers
        $headers = array(
            'Content-Type: ' . $content_type . '; charset=UTF-8',
            'From: ' . $from
        );
        // Auto add Reply-To from submitter email only when sending to admin email
        $maybe_reply_to = '';
        foreach ($data as $k => $v) {
            if (stripos($k, 'email') !== false && is_string($v)) {
                $candidate = sanitize_email($v);
                if (!empty($candidate)) { $maybe_reply_to = $candidate; break; }
            }
        }
        $custom_has_reply_to = !empty($email_config['headers']) && stripos($email_config['headers'], 'reply-to:') !== false;
        $admin_email = strtolower(sanitize_email(get_option('admin_email')));
        $to_emails = array();
        foreach (explode(',', $to) as $te) {
            $se = sanitize_email(trim($te));
            if ($se !== '') { $to_emails[] = strtolower($se); }
        }
        $to_includes_admin = in_array($admin_email, $to_emails, true);
        $reply_to_is_recipient = $maybe_reply_to !== '' && in_array(strtolower($maybe_reply_to), $to_emails, true);
        if ($maybe_reply_to && !$custom_has_reply_to && $to_includes_admin && !$reply_to_is_recipient) {
            $headers[] = 'Reply-To: ' . $maybe_reply_to;
        }
        
        // Add custom headers
        if (!empty($email_config['headers'])) {
            $custom_headers = $email_config['headers'];
            foreach ($data as $key => $value) {
                $placeholder = '[' . strtoupper($key) . ']';
                $replacement = is_array($value) ? implode(', ', $value) : $value;
                $custom_headers = str_replace($placeholder, $replacement, $custom_headers);
            }
            $headers[] = $custom_headers;
        }
        
        // Cleanup any unknown internal placeholders like [NRFM_MAX_UPLOAD]
        $cleanup_tokens = function($str) {
            $str = preg_replace('/\[NRFM_[A-Z0-9_]+\]/i', '', $str);
            // Tidy redundant spaces before newlines
            $str = preg_replace("/[\t ]+\n/", "\n", $str);
            return trim($str);
        };
        $to = $cleanup_tokens(trim($to));
        $from = $cleanup_tokens($from);
        $subject = $cleanup_tokens($subject);
        $message = $cleanup_tokens($message);

        // Preserve line breaks for HTML emails
        if (strtolower($content_type) === 'text/html') {
            $message = nl2br($message);
        }

        // Allow tokenized recipients like [EMAIL]
        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Build a readable, line-based list of submitted fields for email bodies.
     * - Puts each field on its own line
     * - For multiple files, prints one per line prefixed with "-"
     */
    private function format_submission_for_email($data) {
        $lines = array();
        foreach ($data as $key => $value) {
            // Skip internal keys defensively
            if (strpos($key, 'nrfm_') === 0 || strpos($key, '_') === 0 || strtolower($key) === 'action') {
                continue;
            }
            $label = ucfirst(str_replace('_', ' ', $key));
            if (is_array($value)) {
                if (isset($value['url'])) {
                    $lines[] = $label . ': ' . $value['name'] . ' (' . $value['url'] . ')';
                } elseif (isset($value[0]) && is_array($value[0]) && isset($value[0]['url'])) {
                    $file_lines = array();
                    foreach ($value as $file_entry) {
                        if (is_array($file_entry) && isset($file_entry['url'])) {
                            $file_lines[] = '- ' . $file_entry['name'] . ' (' . $file_entry['url'] . ')';
                        }
                    }
                    if (!empty($file_lines)) {
                        $lines[] = $label . ":\n" . implode("\n", $file_lines);
                    }
                } else {
                    $scalar_values = array();
                    foreach ($value as $v) {
                        if (is_scalar($v)) {
                            $scalar_values[] = (string) $v;
                        }
                    }
                    $lines[] = $label . ': ' . implode(', ', $scalar_values);
                }
            } else {
                $lines[] = $label . ': ' . (string) $value;
            }
        }
        return implode("\n", $lines);
    }
    
    private function send_webhook($webhook_config, $data) {
        $url = $webhook_config['url'];
        $method = !empty($webhook_config['method']) ? strtoupper($webhook_config['method']) : 'POST';
        $format = !empty($webhook_config['format']) ? strtolower($webhook_config['format']) : 'form';

        // Include useful meta alongside submitted fields
        $payload = $data;
        $payload['nrfm_timestamp']   = $this->get_mysql_now();
        $payload['nrfm_ip_address']  = $this->get_ip();
        $payload['nrfm_user_agent']  = $this->get_sanitized_user_agent();
        $payload['nrfm_referrer_url'] = $this->get_sanitized_referer();

        // Prepare request args (blocking so we can detect failures)
        $args = array(
            'method'      => $method,
            'timeout'     => 15,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => array(),
            'cookies'     => array(),
        );

        if ($format === 'json') {
            $args['headers']['Content-Type'] = 'application/json';
            if ($method === 'POST') {
                $args['body'] = wp_json_encode($payload);
            } else {
                $url = add_query_arg($payload, $url);
            }
        } else { // form
            if ($method === 'POST') {
                $args['body'] = $payload; // WP handles x-www-form-urlencoded
            } else {
                $url = add_query_arg($payload, $url);
            }
        }

        // Allow customization
        $url  = apply_filters('nrfm_webhook_request_url', $url, $payload, $webhook_config);
        $args = apply_filters('nrfm_webhook_request_args', $args, $payload, $webhook_config);

        $response = wp_remote_request($url, $args);

        // Notify listeners about result
        if (is_wp_error($response)) {
            do_action('nrfm_webhook_error', $response, $url, $args, $webhook_config);
        } else {
            do_action('nrfm_webhook_response', $response, $url, $args, $webhook_config);
        }
    }
    
    private function get_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                // Access header safely; treat arrays as empty; unslash for safety
                $raw = $this->get_server_value($key);
                // Now normalize for parsing (comma-separated list possible)
                foreach (explode(',', $raw) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return sanitize_text_field($ip);
                    }
                }
            }
        }
        
        $raw = $this->get_server_value('REMOTE_ADDR');
        return $raw !== '' ? sanitize_text_field($raw) : '';
    }
}