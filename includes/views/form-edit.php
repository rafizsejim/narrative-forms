<?php
// Prevent direct access
defined('ABSPATH') || exit;

$nrfm_form_id = $form->get_id();
$nrfm_settings = $form->get_settings();
$nrfm_messages = $form->get_messages();
$nrfm_actions = get_post_meta($nrfm_form_id, '_nrfm_actions', true);
if (!is_array($nrfm_actions)) {
    $nrfm_actions = array();
}
$nrfm_submissions = isset($submissions) && is_array($submissions) ? $submissions : array();

// Build dynamic variable list
$nrfm_variables = array();
$nrfm_content_for_vars = $form->get_html();
if (!empty($nrfm_content_for_vars)) {
    if (preg_match_all('/name=["\']([^"\']+)/i', $nrfm_content_for_vars, $m)) {
        $nrfm_names = array();
        foreach ($m[1] as $nrfm_nm) {
            $nrfm_nm = preg_replace('/\[\]$/', '', $nrfm_nm);
            if ($nrfm_nm === '') continue;
            $nrfm_names[$nrfm_nm] = true;
        }
        foreach (array_keys($nrfm_names) as $nrfm_nm) {
            $nrfm_variables[] = '[' . strtoupper($nrfm_nm) . ']';
        }
    }
}
// Built-in variables
$nrfm_variables = array_values(array_unique(array_merge($nrfm_variables, array(
    '[NRFM_TIMESTAMP]', '[NRFM_USER_AGENT]', '[NRFM_IP_ADDRESS]', '[NRFM_REFERRER_URL]'
))));
?>

<div class="wrap nrfm-wrap">
    <p class="nrfm-breadcrumb">
        <?php esc_html_e('You are here:', 'narrative-forms'); ?> 
        <a href="<?php echo esc_url( admin_url('admin.php?page=nrfm-forms') ); ?>"><?php esc_html_e('narrative-forms', 'narrative-forms'); ?></a> 
        &rsaquo; <?php esc_html_e('Edit Form', 'narrative-forms'); ?>
    </p>
    
    <h1><?php esc_html_e('Edit Form', 'narrative-forms'); ?></h1>
    
<?php
$nrfm_saved = '';
$nrfm_saved_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
if ( isset( $_GET['saved'] ) && $nrfm_saved_nonce && wp_verify_nonce( $nrfm_saved_nonce, 'edit_form' ) ) {
	$nrfm_saved = sanitize_text_field( wp_unslash( $_GET['saved'] ) );
}
?>
<?php if ($nrfm_saved === '1'): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Form saved successfully.', 'narrative-forms'); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="post" id="nrfm-form-editor">
        <?php wp_nonce_field('nrfm_admin_action'); ?>
        <input type="hidden" name="nrfm_action" value="save_form">
        <input type="hidden" name="form_id" value="<?php echo esc_attr($nrfm_form_id); ?>">
        
        
        <div class="nrfm-form-meta">
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Form Title', 'narrative-forms'); ?></th>
                    <td>
                        <input type="text" name="form_title" id="form-title" class="large-text" 
                               value="<?php echo esc_attr($form->get_title()); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Slug', 'narrative-forms'); ?></th>
                    <td>
                        <input type="text" name="form_slug" id="form-slug" class="regular-text" 
                               value="<?php echo esc_attr($form->get_slug()); ?>" readonly>
                        <button type="button" class="button button-small" id="edit-slug"><?php esc_html_e('Edit', 'narrative-forms'); ?></button>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Shortcode', 'narrative-forms'); ?></th>
                    <td>
                        <input type="text" id="form-shortcode" class="regular-text code" 
                               value='[nrfm_form slug="<?php echo esc_attr($form->get_slug()); ?>"]' readonly onclick="this.select();">
                        <p class="description"><?php esc_html_e('Copy this shortcode and paste it into your post, page, or text widget content.', 'narrative-forms'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        
        <h2 class="nav-tab-wrapper nrfm-tabs" style="margin-bottom:0;">
            <?php
            $nrfm_tabs = array(
                'fields' => esc_html__('Fields', 'narrative-forms'),
                'messages' => esc_html__('Messages', 'narrative-forms'),
                'settings' => esc_html__('Settings', 'narrative-forms'),
                'actions' => esc_html__('Actions', 'narrative-forms'),
            );
            if ($nrfm_settings['save_submissions']) {
                $nrfm_tabs['submissions'] = esc_html__('Submissions', 'narrative-forms');
            }
            // PRO HOOK: Filter tabs
            $nrfm_tabs = apply_filters('nrfm_form_tabs', $nrfm_tabs, $nrfm_form_id);
            foreach ($nrfm_tabs as $nrfm_tab_key => $nrfm_tab_label): ?>
                <a href="#" class="nav-tab <?php echo $this->current_tab === $nrfm_tab_key ? 'nav-tab-active' : ''; ?>" 
                   data-tab="<?php echo esc_attr($nrfm_tab_key); ?>"><?php echo esc_html($nrfm_tab_label); ?></a>
            <?php endforeach; ?>
        </h2>
        
        
        <div class="nrfm-tab-content">
            
            
            <div class="nrfm-tab-panel <?php echo $this->current_tab === 'fields' ? 'active' : ''; ?>" id="tab-fields">
                <h3 class="nrfm-tab-title"><?php esc_html_e('Add Field', 'narrative-forms'); ?></h3>
                <div class="nrfm-field-buttons">
                    <button type="button" class="button nrfm-field-btn" data-field="text"><?php esc_html_e('Text', 'narrative-forms'); ?></button>
                    <button type="button" class="button nrfm-field-btn" data-field="email"><?php esc_html_e('Email', 'narrative-forms'); ?></button>
                    <button type="button" class="button nrfm-field-btn" data-field="url"><?php esc_html_e('URL', 'narrative-forms'); ?></button>
                    <button type="button" class="button nrfm-field-btn" data-field="number"><?php esc_html_e('Number', 'narrative-forms'); ?></button>
                    <button type="button" class="button nrfm-field-btn" data-field="date"><?php esc_html_e('Date', 'narrative-forms'); ?></button>
                    <button type="button" class="button nrfm-field-btn" data-field="textarea"><?php esc_html_e('Textarea', 'narrative-forms'); ?></button>
                    <button type="button" class="button nrfm-field-btn" data-field="dropdown"><?php esc_html_e('Dropdown', 'narrative-forms'); ?></button>
                    <button type="button" class="button nrfm-field-btn" data-field="checkboxes"><?php esc_html_e('Checkboxes', 'narrative-forms'); ?></button>
                    <button type="button" class="button nrfm-field-btn" data-field="radio"><?php esc_html_e('Radio Buttons', 'narrative-forms'); ?></button>
                    <button type="button" class="button nrfm-field-btn" data-field="file"><?php esc_html_e('File Upload', 'narrative-forms'); ?></button>
                    <button type="button" class="button nrfm-field-btn" data-field="submit"><?php esc_html_e('Submit Button', 'narrative-forms'); ?></button>
                </div>
                
                <p class="description" style="margin-bottom:20px;">
                    <?php echo esc_html__('Use the buttons above to generate your field HTML, or manually modify your form below.', 'narrative-forms'); ?>
                </p>
                
                
                <div id="nrfm-field-config" class="nrfm-field-config" style="display:none;">
                    <div class="nrfm-field-config-header">
                        <h4 id="nrfm-field-title"><?php esc_html_e('Configure Field', 'narrative-forms'); ?></h4>
                    </div>
                    <div class="nrfm-field-config-body">
                        <!-- Content will be dynamically inserted -->
                    </div>
                </div>
                
                
                <div class="nrfm-editor-section">
                <div class="nrfm-editor-wrapper nrfm-editor-split">
                    <div class="nrfm-editor-column">
                        <h4><?php esc_html_e('Form Code', 'narrative-forms'); ?>
                            <a href="#" id="nrfm-show-preview" style="display:none;"><?php esc_html_e('Show Preview', 'narrative-forms'); ?></a>
                        </h4>
                        <textarea name="form_content" id="nrfm-form-content" rows="20" class="large-text code"><?php 
                            echo esc_textarea($form->get_html()); 
                        ?></textarea>
                    </div>
                    
                    <div class="nrfm-preview-column" id="nrfm-preview-column">
                        <h4><?php esc_html_e('Form Preview', 'narrative-forms'); ?>
                            <a href="#" id="nrfm-hide-preview"><?php esc_html_e('Hide Preview', 'narrative-forms'); ?></a>
                        </h4>
                        <iframe id="nrfm-form-preview" src="about:blank"></iframe>
                    </div>
                </div>
                </div>
            </div>
            
            <!-- Messages Tab -->
            <div class="nrfm-tab-panel <?php echo $this->current_tab === 'messages' ? 'active' : ''; ?>" id="tab-messages">
                <h2 class="nrfm-tab-title"><?php esc_html_e('Form Messages', 'narrative-forms'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="message-success"><?php esc_html_e('Success', 'narrative-forms'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="messages[success]" id="message-success" class="large-text" 
                                   value="<?php echo esc_attr($nrfm_messages['success']); ?>">
                            <p class="description"><?php echo esc_html__('The text that shows after a successful form submission.', 'narrative-forms'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="message-invalid-email"><?php esc_html_e('Invalid Email Address', 'narrative-forms'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="messages[invalid_email]" id="message-invalid-email" class="large-text" 
                                   value="<?php echo esc_attr($nrfm_messages['invalid_email']); ?>">
                            <p class="description"><?php echo esc_html__('The text that shows when an invalid email address is given.', 'narrative-forms'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="message-required"><?php esc_html_e('Required Field Missing', 'narrative-forms'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="messages[required_field_missing]" id="message-required" class="large-text" 
                                   value="<?php echo esc_attr($nrfm_messages['required_field_missing']); ?>">
                            <p class="description"><?php echo esc_html__('The text that shows when a required field is missing.', 'narrative-forms'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="message-error"><?php esc_html_e('General Error', 'narrative-forms'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="messages[error]" id="message-error" class="large-text" 
                                   value="<?php echo esc_attr($nrfm_messages['error']); ?>">
                            <p class="description"><?php echo esc_html__('The text that shows when a general error occurred.', 'narrative-forms'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="message-file-too-large"><?php esc_html_e('File Too Large', 'narrative-forms'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="messages[file_too_large]" id="message-file-too-large" class="large-text" 
                                   value="<?php echo esc_attr($nrfm_messages['file_too_large'] ?? __('One or more files exceed the maximum allowed size.', 'narrative-forms')); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="message-max-files"><?php esc_html_e('Maximum Files Exceeded', 'narrative-forms'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="messages[max_files]" id="message-max-files" class="large-text" 
                                   value="<?php /* translators: %d is the maximum number of files allowed for a field. */ echo esc_attr( $nrfm_messages['max_files'] ?? sprintf( __( 'You can upload up to %d files.', 'narrative-forms' ), 1 ) ); ?>">
                            <p class="description"><?php echo esc_html__('Shown when the number of selected files exceeds the per-field limit.', 'narrative-forms'); ?></p>
                        </td>
                    </tr>
                    
                    <?php 
                    // PRO HOOK: Add additional form messages inside this table so layout matches
                    do_action('nrfm_form_messages_after_error', $form); 
                    ?>
                </table>
                <?php /* translators: %s is a small HTML snippet example, e.g., <strong>, <em>, <a> */ ?>
                <p><?php echo wp_kses_post( sprintf( esc_html__('HTML tags like %s are allowed in the message fields.', 'narrative-forms'), '<code>&lt;strong&gt;&lt;em&gt;&lt;a&gt;</code>' ) ); ?></p>
            </div>
            
            <!-- Settings Tab -->
            <div class="nrfm-tab-panel <?php echo $this->current_tab === 'settings' ? 'active' : ''; ?>" id="tab-settings">
                <h2 class="nrfm-tab-title"><?php esc_html_e('Form Settings', 'narrative-forms'); ?></h2>

                <h3 class="nrfm-section-title"><?php esc_html_e('Submission Behavior', 'narrative-forms'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Save Form Submissions', 'narrative-forms'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[save_submissions]" value="1" <?php checked($nrfm_settings['save_submissions'], 1); ?>>
                                <?php esc_html_e('Store successful form submissions.', 'narrative-forms'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Disable this if you only want to send data via actions and not keep records in WordPress.', 'narrative-forms'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Hide Form After Successful Submission', 'narrative-forms'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[hide_after_success]" value="1" <?php checked($nrfm_settings['hide_after_success'], 1); ?>>
                                <?php esc_html_e('Hide form fields after a successful submission.', 'narrative-forms'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="redirect-url"><?php esc_html_e('Redirect to URL After Successful Submission', 'narrative-forms'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="settings[redirect_url]" id="redirect-url" class="large-text"
                                   value="<?php echo esc_attr($nrfm_settings['redirect_url']); ?>"
                                   placeholder="<?php echo esc_attr('Example: ' . site_url('/thank-you/')); ?>">
                            <p class="description"><?php echo esc_html__('Leave empty for no redirect. Use full URLs including https://', 'narrative-forms'); ?></p>
                            <div class="nrfm-variable-buttons nrfm-dynamic-vars" data-target="redirect-url">
                                <?php foreach ($nrfm_variables as $nrfm_var): ?>
                                    <button type="button" class="button button-small nrfm-insert-var" data-target="redirect-url" data-var="<?php echo esc_attr($nrfm_var); ?>"><?php echo esc_html($nrfm_var); ?></button>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="redirect-error-url"><?php esc_html_e('Redirect to URL After Error (Optional)', 'narrative-forms'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="settings[redirect_error_url]" id="redirect-error-url" class="large-text"
                                   value="<?php echo esc_attr($nrfm_settings['redirect_error_url'] ?? ''); ?>"
                                   placeholder="https://example.com/oops?reason=invalid">
                            <p class="description"><?php echo esc_html__('Leave empty to show inline error. Tokens supported.', 'narrative-forms'); ?></p>
                            <?php
                            // Backward compatibility hook for existing integrations.
                            do_action('nrfm_form_settings_after_redirect', $form);
                            ?>
                        </td>
                    </tr>

                </table>

                <?php if ( has_action('nrfm_form_settings_access_availability') ) : ?>
                    <h3 class="nrfm-section-title"><?php esc_html_e('Access & Availability', 'narrative-forms'); ?></h3>
                    <table class="form-table"><?php do_action('nrfm_form_settings_access_availability', $form); ?></table>
                <?php endif; ?>

                <?php if ( has_action('nrfm_form_settings_data_notifications') ) : ?>
                    <?php // Hook name kept for back-compat; the label reflects the group's actual contents. ?>
                    <h3 class="nrfm-section-title"><?php esc_html_e('Data & Drafts', 'narrative-forms'); ?></h3>
                    <table class="form-table"><?php do_action('nrfm_form_settings_data_notifications', $form); ?></table>
                <?php endif; ?>

                <?php if ( has_action('nrfm_form_settings_sharing') ) : ?>
                    <h3 class="nrfm-section-title"><?php esc_html_e('Sharing', 'narrative-forms'); ?></h3>
                    <table class="form-table"><?php do_action('nrfm_form_settings_sharing', $form); ?></table>
                <?php endif; ?>
            </div>
            
            <!-- Actions Tab -->
            <div class="nrfm-tab-panel <?php echo $this->current_tab === 'actions' ? 'active' : ''; ?>" id="tab-actions">
                <h2 class="nrfm-tab-title"><?php esc_html_e('Form Actions', 'narrative-forms'); ?></h2>
                
                <p><?php echo esc_html__('Actions run after a valid submission. Add one or more actions below. Emails deliver content; Webhooks send the data to an external URL.', 'narrative-forms'); ?></p>
                
                <div id="nrfm-actions-container">
                    <?php 
                    // Email Actions
                    if (isset($nrfm_actions['email']) && is_array($nrfm_actions['email'])):
                        foreach ($nrfm_actions['email'] as $nrfm_index => $nrfm_email_action):
                            if (!is_array($nrfm_email_action)) continue;
                    ?>
                    <div class="nrfm-action-item" data-action-type="email" data-action-index="<?php echo esc_attr($nrfm_index); ?>">
                        <h3>
                            <span class="nrfm-action-title"><?php esc_html_e('Send Email', 'narrative-forms'); ?></span>
                            <span class="nrfm-action-summary"></span>
                            <button type="button" class="button-link nrfm-action-toggle" aria-expanded="true" aria-label="Toggle">▾</button>
                            <button type="button" class="button button-small nrfm-remove-action" style="float: right;"><?php esc_html_e('Remove', 'narrative-forms'); ?></button>
                        </h3>
                        <div class="nrfm-action-body" style="display:block;">
            <table class="form-table">
                            <tr>
                                <th><label><?php esc_html_e('From', 'narrative-forms'); ?> *</label></th>
                                <td><input type="text" name="actions[email][<?php echo esc_attr($nrfm_index); ?>][from]" class="regular-text" inputmode="email" placeholder="you@site.com or token e.g. [EMAIL]" value="<?php echo esc_attr($nrfm_email_action['from'] ?? get_option('admin_email')); ?>"></td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e('To', 'narrative-forms'); ?> *</label></th>
                                <td><input type="text" name="actions[email][<?php echo esc_attr($nrfm_index); ?>][to]" class="regular-text" inputmode="email" placeholder="you@site.com or token e.g. [EMAIL]" value="<?php echo esc_attr($nrfm_email_action['to'] ?? get_option('admin_email')); ?>"></td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e('Subject', 'narrative-forms'); ?></label></th>
                                <td><input type="text" name="actions[email][<?php echo esc_attr($nrfm_index); ?>][subject]" class="large-text" value="<?php echo esc_attr($nrfm_email_action['subject'] ?? __('New Form Submission', 'narrative-forms')); ?>"></td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e('Message', 'narrative-forms'); ?> *</label></th>
                                <td>
                                    <textarea name="actions[email][<?php echo esc_attr($nrfm_index); ?>][message]" rows="8" class="large-text"><?php echo esc_textarea($nrfm_email_action['message'] ?? ''); ?></textarea>
                                    <div class="nrfm-variable-buttons nrfm-dynamic-vars" data-target="actions_email_<?php echo esc_attr($nrfm_index); ?>_message">
                                        <?php foreach ($nrfm_variables as $nrfm_var): ?>
                                            <button type="button" class="button button-small nrfm-insert-var" data-target="actions_email_<?php echo esc_attr($nrfm_index); ?>_message" data-var="<?php echo esc_attr($nrfm_var); ?>"><?php echo esc_html($nrfm_var); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e('Content Type', 'narrative-forms'); ?></label></th>
                                <td>
                                    <select name="actions[email][<?php echo esc_attr($nrfm_index); ?>][content_type]">
                                        <option value="text/plain" <?php selected($nrfm_email_action['content_type'] ?? 'text/plain', 'text/plain'); ?>>text/plain</option>
                                        <option value="text/html" <?php selected($nrfm_email_action['content_type'] ?? 'text/plain', 'text/html'); ?>>text/html</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e('Additional Headers', 'narrative-forms'); ?></label></th>
                                <td>
                                    <textarea name="actions[email][<?php echo esc_attr($nrfm_index); ?>][headers]" rows="3" class="large-text" placeholder="Reply-To: John <john@example.com>\nCc: jane@example.com\nX-Custom: value"><?php echo esc_textarea($nrfm_email_action['headers'] ?? ''); ?></textarea>
                                    <p class="description"><?php echo esc_html__('Optional. One per line. Example: Reply-To: John <john@example.com>', 'narrative-forms'); ?></p>
                        </td>
                    </tr>
                </table>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    endif;
                    
                    // Webhook Actions
                    if (isset($nrfm_actions['webhook']) && is_array($nrfm_actions['webhook'])):
                        foreach ($nrfm_actions['webhook'] as $nrfm_index => $nrfm_webhook_action):
                            if (!is_array($nrfm_webhook_action)) continue;
                    ?>
                    <div class="nrfm-action-item" data-action-type="webhook" data-action-index="<?php echo esc_attr($nrfm_index); ?>">
                        <h3>
                            <span class="nrfm-action-title"><?php esc_html_e('Webhook', 'narrative-forms'); ?></span>
                            <span class="nrfm-action-summary"></span>
                            <button type="button" class="button-link nrfm-action-toggle" aria-expanded="true" aria-label="Toggle">▾</button>
                            <button type="button" class="button button-small nrfm-remove-action" style="float: right;"><?php esc_html_e('Remove', 'narrative-forms'); ?></button>
                        </h3>
                        <div class="nrfm-action-body" style="display:block;">
                        <table class="form-table">
                            <tr>
                                <th><label><?php esc_html_e('Webhook URL', 'narrative-forms'); ?> *</label></th>
                                <td><input type="url" name="actions[webhook][<?php echo esc_attr($nrfm_index); ?>][url]" class="large-text" value="<?php echo esc_attr($nrfm_webhook_action['url'] ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e('Method', 'narrative-forms'); ?></label></th>
                                <td>
                                    <select name="actions[webhook][<?php echo esc_attr($nrfm_index); ?>][method]">
                                        <option value="POST" <?php selected($nrfm_webhook_action['method'] ?? 'POST', 'POST'); ?>>POST</option>
                                        <option value="GET" <?php selected($nrfm_webhook_action['method'] ?? 'POST', 'GET'); ?>>GET</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e('Format', 'narrative-forms'); ?></label></th>
                                <td>
                                    <select name="actions[webhook][<?php echo esc_attr($nrfm_index); ?>][format]">
                                        <option value="form" <?php selected($nrfm_webhook_action['format'] ?? 'form', 'form'); ?>>Form Data</option>
                                        <option value="json" <?php selected($nrfm_webhook_action['format'] ?? 'form', 'json'); ?>>JSON</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    endif;
                    ?>
                </div>
                
                <h3><?php esc_html_e('Add New Action', 'narrative-forms'); ?></h3>
                <button type="button" class="button" id="nrfm-add-email-action"><?php esc_html_e('Add Email Notification', 'narrative-forms'); ?></button>
                <button type="button" class="button" id="nrfm-add-webhook-action"><?php esc_html_e('Add Webhook', 'narrative-forms'); ?></button>
                
                <script type="text/template" id="tmpl-email-action">
                    <div class="nrfm-action-item" data-action-type="email" data-action-index="{index}">
                        <h3>
                            <span class="nrfm-action-title"><?php esc_html_e('Send Email', 'narrative-forms'); ?></span>
                            <span class="nrfm-action-summary"></span>
                            <button type="button" class="button-link nrfm-action-toggle" aria-expanded="true" aria-label="Toggle">▾</button>
                            <button type="button" class="button button-small nrfm-remove-action" style="float: right;"><?php esc_html_e('Remove', 'narrative-forms'); ?></button>
                        </h3>
                        <div class="nrfm-action-body" style="display:block;">
                        <table class="form-table">
                            <tr>
                                <th><label><?php esc_html_e('From', 'narrative-forms'); ?> *</label></th>
                                <td><input type="text" name="actions[email][{index}][from]" class="regular-text" inputmode="email" placeholder="you@site.com or token e.g. [EMAIL]" value="<?php echo esc_attr(get_option('admin_email')); ?>"></td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e('To', 'narrative-forms'); ?> *</label></th>
                                <td><input type="text" name="actions[email][{index}][to]" class="regular-text" inputmode="email" placeholder="you@site.com or token e.g. [EMAIL]" value="<?php echo esc_attr(get_option('admin_email')); ?>"></td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e('Subject', 'narrative-forms'); ?></label></th>
                                <td><input type="text" name="actions[email][{index}][subject]" class="large-text" value="<?php echo esc_attr(__('New Form Submission', 'narrative-forms')); ?>"></td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e('Message', 'narrative-forms'); ?> *</label></th>
                                <td>
                                    <textarea id="actions_email_{index}_message" name="actions[email][{index}][message]" rows="8" class="large-text"></textarea>
                                    <div class="nrfm-variable-buttons nrfm-dynamic-vars" data-target="actions_email_{index}_message"></div>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e('Content Type', 'narrative-forms'); ?></label></th>
                                <td>
                                    <select name="actions[email][{index}][content_type]">
                                        <option value="text/plain">text/plain</option>
                                        <option value="text/html">text/html</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e('Additional Headers', 'narrative-forms'); ?></label></th>
                                <td>
                                    <textarea name="actions[email][{index}][headers]" rows="3" class="large-text" placeholder="Reply-To: John <john@example.com>\nCc: jane@example.com\nX-Custom: value"></textarea>
                                    <p class="description"><?php echo esc_html__('Optional. One per line. Example: Reply-To: John <john@example.com>', 'narrative-forms'); ?></p>
                                </td>
                            </tr>
                        </table>
                        </div>
                    </div>
                </script>
                
                <script type="text/template" id="tmpl-webhook-action">
                    <div class="nrfm-action-item" data-action-type="webhook" data-action-index="{index}">
                        <h3>
                            <span class="nrfm-action-title"><?php esc_html_e('Webhook', 'narrative-forms'); ?></span>
                            <span class="nrfm-action-summary"></span>
                            <button type="button" class="button-link nrfm-action-toggle" aria-expanded="true" aria-label="Toggle">▾</button>
                            <button type="button" class="button button-small nrfm-remove-action" style="float: right;"><?php esc_html_e('Remove', 'narrative-forms'); ?></button>
                        </h3>
                        <div class="nrfm-action-body" style="display:block;">
                        <table class="form-table">
                            <tr>
                                <th><label><?php esc_html_e('Webhook URL', 'narrative-forms'); ?> *</label></th>
                                <td><input type="url" name="actions[webhook][{index}][url]" class="large-text" placeholder="https://example.com/webhook"></td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e('Method', 'narrative-forms'); ?></label></th>
                                <td>
                                    <select name="actions[webhook][{index}][method]">
                                        <option value="POST">POST</option>
                                        <option value="GET">GET</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e('Format', 'narrative-forms'); ?></label></th>
                                <td>
                                    <select name="actions[webhook][{index}][format]">
                                        <option value="form">Form Data</option>
                                        <option value="json">JSON</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        </div>
                    </div>
                </script>
            </div>

            <?php
            // Render custom tab panels inside the form so their fields are submitted
            $nrfm_builtin_tabs = array('fields','messages','settings','actions');
            if ($nrfm_settings['save_submissions']) { $nrfm_builtin_tabs[] = 'submissions'; }
            foreach ($nrfm_tabs as $nrfm_tab_key => $nrfm_tab_label) {
                if (!in_array($nrfm_tab_key, $nrfm_builtin_tabs, true)) {
                    do_action('nrfm_form_tab_content', $nrfm_tab_key, $form);
                }
            }
            ?>
        </div>
        
        <p class="submit" id="nrfm-save-wrap" style="<?php echo ($this->current_tab === 'submissions') ? 'display:none;' : ''; ?>">
            <button type="submit" class="button button-primary"><?php esc_html_e('Save Changes', 'narrative-forms'); ?></button>
        </p>
    </form>

            <!-- Submissions Tab -->
            <?php if ($nrfm_settings['save_submissions'] && $this->current_tab === 'submissions'): ?>
            <div class="nrfm-tab-panel active" id="tab-submissions">
                <?php
                $nrfm_has_view = ( isset( $_GET['nrfm_view'], $_GET['_wpnonce_view'] ) );
                ?>
                <?php if ( ! $nrfm_has_view && ! empty( $nrfm_submissions ) ): ?>
                    <form method="get" style="margin: 10px 0;">
                        <input type="hidden" name="page" value="nrfm-forms">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="form" value="<?php echo esc_attr($nrfm_form_id); ?>">
                        <input type="hidden" name="tab" value="submissions">
                        <label for="nrfm-delim-select"><strong><?php esc_html_e('Export delimiter:', 'narrative-forms'); ?></strong></label>
                        <select id="nrfm-delim-select" name="delimiter">
                            <option value=",">Comma (,)</option>
                            <option value="semicolon">Semicolon (;)</option>
                            <option value="tab">Tab (\t)</option>
                        </select>
                        <?php $nrfm_export_url = add_query_arg(array('page' => 'nrfm-forms','action' => 'export_csv','form' => $nrfm_form_id,'_wpnonce' => wp_create_nonce('export_csv')), admin_url('admin.php')); ?>
                        <a class="button" href="#" onclick="(function(el){var v=document.getElementById('nrfm-delim-select').value; window.location='<?php echo esc_js($nrfm_export_url); ?>' + '&delimiter=' + encodeURIComponent(v);})(this); return false;"><?php esc_html_e('Export to CSV', 'narrative-forms'); ?></a>
                    </form>
                <?php endif; ?>
                
                <?php if ( $nrfm_has_view ):
                    // Show single submission view - validate nonce early
                    $nrfm_submission_id = isset($_GET['nrfm_view']) ? intval( wp_unslash( $_GET['nrfm_view'] ) ) : 0;
                    $nrfm_provided_nonce_view = isset($_GET['_wpnonce_view']) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce_view'] ) ) : '';
                    $nrfm_expected_nonce_action = 'nrfm_view_' . $nrfm_submission_id;
                    $nrfm_nonce_ok = ( $nrfm_submission_id > 0 && $nrfm_provided_nonce_view && wp_verify_nonce( $nrfm_provided_nonce_view, $nrfm_expected_nonce_action ) );

                    if (!$nrfm_nonce_ok) { echo '<div class="notice notice-error"><p>' . esc_html__('Security check failed.', 'narrative-forms') . '</p></div>'; } else {
                    $nrfm_submission = null;
                    foreach ($nrfm_submissions as $nrfm_sub) {
                        if ($nrfm_sub->id == $nrfm_submission_id) {
                            $nrfm_submission = $nrfm_sub;
                            break;
                        }
                    }
                    
                    if ($nrfm_submission):
                ?>
                    <h3><?php esc_html_e('Viewing Form Submission', 'narrative-forms'); ?></h3>

                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width:180px;"><?php esc_html_e('Meta', 'narrative-forms'); ?></th>
                                <th><?php esc_html_e('Value', 'narrative-forms'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php esc_html_e('Timestamp', 'narrative-forms'); ?></td>
                                <td><?php echo esc_html($nrfm_submission->submitted_at); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('User Agent', 'narrative-forms'); ?></td>
                                <td><?php echo esc_html($nrfm_submission->user_agent); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('IP Address', 'narrative-forms'); ?></td>
                                <td><?php echo esc_html($nrfm_submission->ip_address); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Referrer URL', 'narrative-forms'); ?></td>
                                <td><a href="<?php echo esc_url($nrfm_submission->referer_url); ?>" target="_blank"><?php echo esc_html($nrfm_submission->referer_url); ?></a></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Action', 'narrative-forms'); ?></td>
                                <td><code>nrfm_submit_form</code></td>
                            </tr>
                        </tbody>
                    </table>

                    <h3 style="margin-top:20px;"><?php esc_html_e('Fields', 'narrative-forms'); ?></h3>
                    <?php
                    $nrfm_schema_labels = get_post_meta($nrfm_form_id, '_nrfm_fields_labels', true);
                    if (!is_array($nrfm_schema_labels)) { $nrfm_schema_labels = array(); }
                    $nrfm_question_map = get_post_meta($nrfm_form_id, '_nrfm_fields_question_map', true);
                    if (!is_array($nrfm_question_map)) { $nrfm_question_map = array(); }
                    $nrfm_choice_map = get_post_meta($nrfm_form_id, '_nrfm_fields_choice_map', true);
                    if (!is_array($nrfm_choice_map)) { $nrfm_choice_map = array(); }
                    $nrfm_raw_fields = get_post_meta($nrfm_form_id, '_nrfm_fields_raw_display', true);
                    $nrfm_raw_fields = is_array($nrfm_raw_fields) ? array_flip($nrfm_raw_fields) : array();
                    ?>
                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width:280px;"><?php esc_html_e('Field', 'narrative-forms'); ?></th>
                                <th><?php esc_html_e('Value', 'narrative-forms'); ?></th>
                            </tr>
                        </thead>
                        <?php foreach ($nrfm_submission->data as $nrfm_field => $nrfm_value): 
                            // Skip internal keys including action; show action in Meta above
                            if (strpos($nrfm_field, 'nrfm_') !== 0 && strtolower($nrfm_field) !== 'action'):
                                $nrfm_label = (!isset($nrfm_raw_fields[$nrfm_field]) && isset($nrfm_question_map[$nrfm_field]) && $nrfm_question_map[$nrfm_field] !== '')
                                    ? $nrfm_question_map[$nrfm_field]
                                    : (isset($nrfm_schema_labels[$nrfm_field]) && $nrfm_schema_labels[$nrfm_field] !== '' ? $nrfm_schema_labels[$nrfm_field] : ucfirst(str_replace('_', ' ', $nrfm_field)));
                                // Strip trailing asterisks from labels for display and guard against concatenated option text
                                $nrfm_label = preg_replace('/\s*\*+\s*$/', '', $nrfm_label);
                                $nrfm_label_simple = preg_replace('/\s+/', ' ', trim($nrfm_label));
                                $nrfm_word_count = ($nrfm_label_simple === '') ? 0 : count(explode(' ', $nrfm_label_simple));
                                if ($nrfm_label_simple === '' || $nrfm_word_count > 16 || strlen($nrfm_label_simple) > 140 || preg_match('/\b(yes|no|male|female|option|select)\b/i', $nrfm_label_simple)) {
                                    $nrfm_label_simple = ucfirst(str_replace('_', ' ', $nrfm_field));
                                }
                                $nrfm_label = $nrfm_label_simple;
                                $nrfm_display = '';
                                if (is_array($nrfm_value)) {
                                    // Files or checkbox arrays
                                    if (isset($nrfm_value[0]) && is_array($nrfm_value[0]) && isset($nrfm_value[0]['url'])) {
                                        // Files array
                                        $nrfm_links = array();
                                        foreach ($nrfm_value as $nrfm_f) {
                                            $nrfm_links[] = '<a href="' . esc_url($nrfm_f['url']) . '" target="_blank">' . esc_html($nrfm_f['name']) . '</a>';
                                        }
                                        $nrfm_display = implode('<br>', $nrfm_links);
                                    } elseif (isset($nrfm_value['url'])) {
                                        // Single file entry shape
                                        $nrfm_display = '<a href="' . esc_url($nrfm_value['url']) . '" target="_blank">' . esc_html($nrfm_value['name']) . '</a>';
                                    } else {
                                        $nrfm_parts = array();
                                        foreach ($nrfm_value as $nrfm_v) {
                                            $nrfm_v = is_scalar($nrfm_v) ? (string) $nrfm_v : '';
                                            if ($nrfm_v !== '' && empty($nrfm_raw_fields[$nrfm_field]) && isset($nrfm_choice_map[$nrfm_field]) && isset($nrfm_choice_map[$nrfm_field][$nrfm_v])) {
                                                $nrfm_parts[] = $nrfm_choice_map[$nrfm_field][$nrfm_v];
                                            } else {
                                                $nrfm_parts[] = $nrfm_v;
                                            }
                                        }
                                        $nrfm_display = esc_html(implode(', ', $nrfm_parts));
                                    }
                                } else {
                                    $nrfm_display_val = (string) $nrfm_value;
                                    if ($nrfm_display_val !== '' && empty($nrfm_raw_fields[$nrfm_field]) && isset($nrfm_choice_map[$nrfm_field]) && isset($nrfm_choice_map[$nrfm_field][$nrfm_display_val])) {
                                        $nrfm_display_val = $nrfm_choice_map[$nrfm_field][$nrfm_display_val];
                                    }
                                    $nrfm_display = trim($nrfm_display_val) !== '' ? nl2br(esc_html($nrfm_display_val)) : '<em>' . esc_html__('Empty', 'narrative-forms') . '</em>';
                                }
                        ?>
                        <tr>
                            <td><?php echo esc_html($nrfm_label); ?></td>
                            <td><?php echo wp_kses_post( $nrfm_display ); ?></td>
                        </tr>
                        <?php endif; endforeach; ?>
                    </table>
                    
                    <h3 style="margin-top:20px;"><?php esc_html_e('Raw', 'narrative-forms'); ?></h3>
                    <pre style="background: #f0f0f0; padding: 15px; overflow: auto;"><?php 
                        echo esc_html(json_encode($nrfm_submission, JSON_PRETTY_PRINT));
                    ?></pre>
                    
                    <p>
                        <?php $nrfm_back_url = add_query_arg(array('page'=>'nrfm-forms','action'=>'edit','form'=>intval($nrfm_form_id),'tab'=>'submissions','_wpnonce'=>wp_create_nonce('edit_form')), admin_url('admin.php')); ?>
                        <a href="<?php echo esc_url( $nrfm_back_url ); ?>" class="button">
                            &laquo; <?php esc_html_e('Back to submissions list', 'narrative-forms'); ?>
                        </a>
                    </p>
                <?php 
                    endif; }
                else: ?>
                    <?php
                    require_once NRFM_PLUGIN_DIR . 'includes/admin/class-nrfm-submissions-table.php';
                    $nrfm_table_obj = new NRFM_Submissions_Table($nrfm_form_id);
                    $nrfm_table_obj->process_bulk_action();
                    ?>
                    <form method="get">
                        <input type="hidden" name="page" value="nrfm-forms">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="form" value="<?php echo esc_attr($nrfm_form_id); ?>">
                        <input type="hidden" name="tab" value="submissions">
                        <?php $nrfm_table_obj->search_box(esc_html__('Search submissions', 'narrative-forms'), 'nrfm-submissions'); ?>
                    </form>
                    <form method="post" id="nrfm-submissions-form">
                        <?php wp_nonce_field('bulk-submissions'); ?>
                        <?php $nrfm_table_obj->prepare_items(); ?>
                        <?php $nrfm_table_obj->display(); ?>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php /* custom tab panels are now rendered inside the form for submission */ ?>
</div>