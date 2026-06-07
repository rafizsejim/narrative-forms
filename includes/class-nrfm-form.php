<?php
/**
 * Form class - handles form CRUD operations
 */
class NRFM_Form {
    
    private $id = 0;
    private $post = null;
    private $settings = array();
    private $messages = array();
    
    public function __construct($id = 0) {
        if ($id) {
            $this->id = $id;
            $this->load();
        }
    }
    
    private function load() {
        $this->post = get_post($this->id);
        if (!$this->post || $this->post->post_type !== 'nrfm_form') {
            $this->id = 0;
            $this->post = null;
            return;
        }
        
        // Load settings
        $default_settings = self::default_settings();
        $saved_settings = get_post_meta($this->id, '_nrfm_settings', true);
        $this->settings = wp_parse_args($saved_settings, $default_settings);
        
        // Load messages
        $default_messages = self::default_messages();
        $saved_messages = get_post_meta($this->id, '_nrfm_messages', true);
        $this->messages = wp_parse_args($saved_messages, $default_messages);
    }
    
    public function exists() {
        return $this->id > 0 && $this->post !== null;
    }
    
    public function get_id() {
        return $this->id;
    }
    
    public function get_title() {
        return $this->exists() ? $this->post->post_title : '';
    }
    
    public function get_slug() {
        return $this->exists() ? $this->post->post_name : '';
    }
    
    public function get_html() {
        return $this->exists() ? $this->post->post_content : '';
    }
    
    public function get_settings() {
        return wp_parse_args($this->settings, self::default_settings());
    }
    
    public function get_messages() {
        return wp_parse_args($this->messages, self::default_messages());
    }

    /**
     * Get the list of field names defined in the form HTML.
     * Uses a transient cache keyed by form id and content hash for speed.
     */
    public function get_field_names() {
        if (!$this->exists()) { return array(); }
        $content = (string) $this->post->post_content;
        $hash = md5($content);
        $cache_key = 'nrfm_fieldnames_' . $this->id;
        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['h']) && $cached['h'] === $hash && isset($cached['n']) && is_array($cached['n'])) {
            return $cached['n'];
        }
        $names = array();
        if ($content !== '' && preg_match_all('/<(?:input|textarea|select)[^>]*name=["\']([^"\']+)["\']/i', $content, $m)) {
            foreach ($m[1] as $nm) {
                $nm = preg_replace('/\[\]$/', '', $nm);
                if ($nm !== '' && strpos($nm, 'nrfm_') !== 0) { $names[$nm] = true; }
            }
        }
        $names = array_keys($names);
        set_transient($cache_key, array('h' => $hash, 'n' => $names), DAY_IN_SECONDS);
        return $names;
    }

    /**
     * Centralized default settings for a form (single source of truth).
     * Filters are preserved for PRO/extension points.
     */
    public static function default_settings() {
        return apply_filters('nrfm_form_default_settings', array(
            'save_submissions' => 1,
            'hide_after_success' => 0,
            'redirect_url' => '',
            'redirect_error_url' => '',
            'honeypot_enabled' => 1,
            'async_actions' => 1,
        ));
    }

    /**
     * Centralized default messages for a form.
     */
    public static function default_messages() {
        return apply_filters('nrfm_form_default_messages', array(
            'success' => __('Thank you! We will be in touch soon.', 'narrative-forms'),
            'error' => __('Oops. An error occurred.', 'narrative-forms'),
            'invalid_email' => __('Sorry, that email address looks invalid.', 'narrative-forms'),
            'required_field_missing' => __('Please fill in the required fields.', 'narrative-forms'),
            'invalid_file_type' => __('One or more files have an unsupported file type.', 'narrative-forms'),
            'file_too_large' => __('One or more files exceed the maximum allowed size.', 'narrative-forms'),
            /* translators: %d is the maximum number of files allowed for a field. */
            'max_files' => __('You can upload up to %d files.', 'narrative-forms'),
            'upload_error' => __('There was an error uploading the file.', 'narrative-forms'),
        ));
    }
    
    public function get_message($key) {
        return isset($this->messages[$key]) ? $this->messages[$key] : '';
    }
    
    /**
     * Safe accessor for server values without directly using $_SERVER.
     */
    private function get_server_value($key) {
        $val = filter_input(INPUT_SERVER, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        return is_string($val) ? $val : '';
    }
    
    public function render() {
        if (!$this->exists()) {
            return '';
        }
        
        // Check if we have a submission result
        $result = get_transient('nrfm_submission_' . $this->id);
        delete_transient('nrfm_submission_' . $this->id);
        
        $html = '<div class="nrfm-form-wrapper">';
        
        // Show message if exists
        if ($result) {
            $class = $result['success'] ? 'nrfm-success' : 'nrfm-error';
            $html .= '<div class="nrfm-message ' . $class . '">' . esc_html($result['message']) . '</div>';
            
            // Hide form if successful and setting enabled
            if ($result['success'] && $this->settings['hide_after_success']) {
                $html .= '</div>';
                return $html;
            }
        }
        
        // Build form HTML
        $msgs = $this->get_messages();
        $class_attr = 'nrfm-form nrfm-form-' . $this->id;
        $form_html = '<form method="post" class="' . $class_attr . '" data-form-id="' . $this->id . '" data-msg-error="' . esc_attr($msgs['error']) . '" data-msg-file-too-large="' . esc_attr($msgs['file_too_large']) . '" data-msg-max-files="' . esc_attr($msgs['max_files']) . '" data-msg-required="' . esc_attr($msgs['required_field_missing']) . '" data-msg-invalid-email="' . esc_attr($msgs['invalid_email']) . '">';
        $form_html .= wp_nonce_field('nrfm_form_' . $this->id, 'nrfm_nonce', true, false);
        $form_html .= '<input type="hidden" name="nrfm_form_id" value="' . $this->id . '">';
        // Time trap start (hidden)
        $form_html .= '<input type="hidden" name="nrfm_started_at" value="' . time() . '">';
        
        // Honeypot field - check global settings first, then form settings
        $global_settings = function_exists('nrfm_get_settings') ? nrfm_get_settings(null, array('honeypot_enabled' => 1)) : get_option('nrfm_settings', array('honeypot_enabled' => 1));
        $honeypot_enabled = isset($this->settings['honeypot_enabled']) ? $this->settings['honeypot_enabled'] : $global_settings['honeypot_enabled'];
        
        if ($honeypot_enabled) {
            $form_html .= '<input type="text" name="nrfm_hp_' . $this->id . '" style="display:none !important" tabindex="-1" autocomplete="off">';
        }
        
        // Form content with wrapper tag processing
        $content = $this->post->post_content;
        
        // Apply wrapper tag if set globally and not 'none'
        if (!empty($global_settings['wrapper_tag']) && $global_settings['wrapper_tag'] !== 'none') {
            $wrapper = $global_settings['wrapper_tag'];
            // If content already uses wrappers, skip auto-wrapping to avoid empty wrappers on blank lines
            $has_wrappers_already = (bool) preg_match('/<\s*(p|div|section|article)\b/i', $content);
            if (!$has_wrappers_already) {
            $content = preg_replace_callback(
                '/^\s*(<(?:input|textarea|select|button|label)[^>]*>.*?)$/mi',
                function($matches) use ($wrapper) {
                        return '<' . $wrapper . '>' . trim($matches[1]) . '</' . $wrapper . '>';
                },
                $content
            );
            }
            // Remove empty wrappers only if they have no id/class/data attributes (preserve user elements)
            $content = preg_replace('/<\s*(p|div|section|article)\s*>\s*<\/\1>/i', '', $content);
        }
        
        // Process template tags if any
        $content = $this->process_template_tags($content);
        // Keep internal hidden fields (nrfm_max_*) so frontend JS can read limits
        $form_html .= '<div class="nrfm-fields-wrap">' . $content . '</div>';
        $form_html .= '</form>';
        
        // PRO HOOK: Filter form HTML
        $form_html = apply_filters('nrfm_form_html', $form_html, $this);
        
        $html .= $form_html;
        $html .= '</div>';
        
        // PRO HOOK: Filter complete rendered output
        $html = apply_filters('nrfm_form_render', $html, $this);
        
        return $html;
    }
    
    private function process_template_tags($content) {
        // Alias for familiarity: {{url_params.xyz}} -> {{get.xyz}}
        $content = preg_replace('/\{\{\s*url_params\./i', '{{get.', $content);

        // Providers
        $providers = array(
            'user' => function($key) {
                if (!is_user_logged_in()) { return ''; }
            $user = wp_get_current_user();
                switch ($key) {
                    case 'email': return (string) $user->user_email;
                    case 'name': return (string) $user->display_name;
                    case 'first_name': return (string) $user->first_name;
                    case 'last_name': return (string) $user->last_name;
                    default: return '';
                }
            },
            'get' => function($key) {
                // Disabled by default; must be explicitly allowed AND nonce-validated
                if (!apply_filters('nrfm_template_allow_get', false)) { return ''; }
                $tpl_nonce = isset($_GET['nrfm_tpl_nonce']) && !is_array($_GET['nrfm_tpl_nonce'])
                    ? sanitize_text_field( wp_unslash($_GET['nrfm_tpl_nonce']) )
                    : '';
                $nonce_ok = (!empty($tpl_nonce) && wp_verify_nonce($tpl_nonce, 'nrfm_tpl'));
                if (!$nonce_ok) { return ''; }
                if (!isset($_GET[$key]) || !is_string($_GET[$key])) { return ''; }
                return sanitize_text_field( (string) wp_unslash($_GET[$key]) );
            },
            'post' => function($key) {
                global $post;
                $p = is_a($post, 'WP_Post') ? $post : null;
                if (!$p) { return ''; }
                switch ($key) {
                    case 'ID': return (string) $p->ID;
                    case 'post_title': return (string) $p->post_title;
                    case 'post_name': return (string) $p->post_name;
                    case 'permalink': return (string) get_permalink($p);
                    default: return '';
                }
            },
            'site' => function($key) {
                switch ($key) {
                    case 'name': return (string) get_bloginfo('name');
                    case 'url': return (string) home_url('/');
                    case 'admin_email': return (string) get_option('admin_email');
                    default: return '';
                }
            },
            'date' => function($key) {
                return $key ? (string) $key : 'now';
            },
            'request' => function($key) {
                if (!apply_filters('nrfm_template_allow_request', false)) { return ''; }
                switch ($key) {
                    case 'referrer':
                        $raw = $this->get_server_value('HTTP_REFERER');
                        $raw = is_string($raw) ? (string) $raw : '';
                        return $raw !== '' ? esc_url_raw($raw) : '';
                    case 'ip':
                        $raw = $this->get_server_value('REMOTE_ADDR');
                        $raw = is_string($raw) ? (string) $raw : '';
                        $ip  = filter_var($raw, FILTER_VALIDATE_IP) ? $raw : '';
                        return $ip;
                    default: return '';
                }
            },
        );
        $providers = apply_filters('nrfm_template_providers', $providers);

        // Filters
        $filters = array(
            'default' => function($value, $fallback = '') { $value = (string)$value; return ($value === '') ? (string)$fallback : $value; },
            'lower' => function($value) { return function_exists('mb_strtolower') ? mb_strtolower((string)$value) : strtolower((string)$value); },
            'upper' => function($value) { return function_exists('mb_strtoupper') ? mb_strtoupper((string)$value) : strtoupper((string)$value); },
            'title' => function($value) { return function_exists('mb_convert_case') ? mb_convert_case((string)$value, MB_CASE_TITLE, 'UTF-8') : ucwords(strtolower((string)$value)); },
            'slug' => function($value) { return sanitize_title((string)$value); },
            'trim' => function($value) { return trim((string)$value); },
            'date' => function($value, $format = 'c') {
                $ts = null; $value = (string)$value;
                if ($value === '' || strtolower($value) === 'now') { $ts = current_time('timestamp'); }
                elseif (is_numeric($value)) { $ts = (int)$value; }
                else { $ts = strtotime($value); if ($ts === false) { $ts = current_time('timestamp'); } }
                return date_i18n((string)$format, $ts);
            },
            'replace' => function($value, $find = '', $with = '') { return str_replace((string)$find, (string)$with, (string)$value); },
            'truncate' => function($value, $len = 100) { $len=(int)$len; $str=(string)$value; if ($len<=0 || ((function_exists('mb_strlen')?mb_strlen($str):strlen($str)) <= $len)) return $str; return (function_exists('mb_substr')?mb_substr($str,0,$len):substr($str,0,$len)).'…'; },
        );
        $filters = apply_filters('nrfm_template_filters', $filters);

        $pattern = '/\{\{\s*([a-zA-Z_][\w]*)\.([a-zA-Z0-9_\-]+)\s*(\|[^}]*)?\}\}/';
        $content = preg_replace_callback($pattern, function($m) use ($providers, $filters) {
            $provider = strtolower($m[1]);
            $key = $m[2];
            $pipe = isset($m[3]) ? trim($m[3]) : '';
            $value = '';
            if (isset($providers[$provider]) && is_callable($providers[$provider])) {
                $value = call_user_func($providers[$provider], $key);
            }
            $value = is_scalar($value) ? (string)$value : '';
            if ($pipe !== '') {
                $segments = preg_split('/\|\s*/', ltrim($pipe, '| '));
                foreach ($segments as $seg) {
                    if ($seg === '') continue;
                    if (!preg_match('/^([a-zA-Z_][\w]*)\s*(?::\s*(.*))?$/', $seg, $mm)) { continue; }
                    $fname = strtolower($mm[1]);
                    $argsStr = isset($mm[2]) ? $mm[2] : '';
                    $args = array();
                    if ($argsStr !== '') {
                        if (preg_match_all('/\'([^\']*)\'|\"([^\"]*)\"|([^,]+)/', $argsStr, $am)) {
                            foreach ($am[0] as $i => $raw) {
                                $val = $am[1][$i] !== '' ? $am[1][$i] : ($am[2][$i] !== '' ? $am[2][$i] : trim($am[3][$i]));
                                $args[] = $val;
                            }
                        }
                    }
                    if (isset($filters[$fname]) && is_callable($filters[$fname])) {
                        $value = call_user_func_array($filters[$fname], array_merge(array($value), $args));
                    }
                }
            }
            return esc_attr((string)$value);
        }, $content);
        
        return $content;
    }
    
    public function save($data) {
        $post_data = array(
            'post_type' => 'nrfm_form',
            'post_status' => 'publish',
        );
        
        if (isset($data['title'])) {
            $post_data['post_title'] = sanitize_text_field($data['title']);
        }
        
        if (isset($data['slug'])) {
            $post_data['post_name'] = sanitize_title($data['slug']);
        }
        
        if (isset($data['content'])) {
            if ( current_user_can( 'unfiltered_html' ) ) {
                $post_data['post_content'] = $data['content'];
            } else {
                $post_data['post_content'] = wp_kses( $data['content'], $this->get_allowed_html() );
            }
        }
        
        // Update or insert
        if ($this->id) {
            $post_data['ID'] = $this->id;
            wp_update_post($post_data);
        } else {
            $this->id = wp_insert_post($post_data);
        }

        // Clear cached field names after save
        if ($this->id) {
            delete_transient('nrfm_fieldnames_' . $this->id);
        }
        
        // Save settings
        if (isset($data['settings'])) {
            update_post_meta($this->id, '_nrfm_settings', $data['settings']);
        }
        
        // Save messages
        if (isset($data['messages'])) {
            update_post_meta($this->id, '_nrfm_messages', $data['messages']);
        }
        
        // Save actions
        if (isset($data['actions'])) {
            update_post_meta($this->id, '_nrfm_actions', $data['actions']);
        }
        
        return $this->id;
    }
    
    public function get_allowed_html() {
        // Define allowed HTML for forms
        $allowed = wp_kses_allowed_html('post');
        
        // Add form elements
        $allowed['input'] = array(
            'type' => true,
            'name' => true,
            'value' => true,
            'placeholder' => true,
            'required' => true,
            'class' => true,
            'id' => true,
            'min' => true,
            'max' => true,
            'step' => true,
            'pattern' => true,
            'accept' => true,
            'multiple' => true,
            'checked' => true,
            'disabled' => true,
            'readonly' => true,
        );
        
        $allowed['textarea'] = array(
            'name' => true,
            'placeholder' => true,
            'required' => true,
            'class' => true,
            'id' => true,
            'rows' => true,
            'cols' => true,
        );
        
        $allowed['select'] = array(
            'name' => true,
            'required' => true,
            'class' => true,
            'id' => true,
            'multiple' => true,
        );
        
        $allowed['option'] = array(
            'value' => true,
            'selected' => true,
        );
        
        $allowed['button'] = array(
            'type' => true,
            'class' => true,
            'id' => true,
        );
        
        $allowed['label'] = array(
            'for' => true,
            'class' => true,
        );

        // Permit inline style/script so pasted snippets keep CSS/JS (sanitized by wp_kses)
        $allowed['style'] = array(
            'type'  => true,
            'media' => true,
        );
        $allowed['script'] = array(
            'type'  => true,
            'src'   => true,
            'defer' => true,
            'async' => true,
        );
        
        return $allowed;
    }
}