<?php
/**
 * Plugin Name: Narrative Forms
 * Plugin URI: https://narrative-forms.com
 * Description: Lightweight, developer-friendly WordPress form plugin. Pure HTML forms with no complexity.
 * Version: 1.2.1
 * Author: NarrativeCode
 * Text Domain: narrative-forms
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.2
 * License: GPL v2 or later
 */

defined('ABSPATH') || exit;

define('NRFM_VERSION', '1.2.1');
define('NRFM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NRFM_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Appsero usage analytics (opt-in only).
 *
 * The Appsero SDK collects nothing until the site administrator explicitly
 * allows it through the admin notice. See the "Privacy" section in readme.txt.
 */
function appsero_init_tracker_narrative_forms() {
    if ( ! class_exists( 'Appsero\Client' ) ) {
        require_once __DIR__ . '/appsero/src/Client.php';
    }

    $client = new Appsero\Client( '9d8a0265-2205-42ba-8609-80a007354733', 'HTML Forms & Contact Form for WordPress – Narrative Forms', __FILE__ );

    // Opt-in usage insights only (no licensing, no Appsero updater; wp.org handles updates).
    $client->insights()->init();
}
appsero_init_tracker_narrative_forms();

/**
 * Submissions table schema. Single source of truth used by both activation and
 * the schema-upgrade path (DRY).
 */
function nrfm_submissions_table_sql() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'nrfm_submissions';
    $charset_collate = $wpdb->get_charset_collate();
    return "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        form_id bigint(20) unsigned NOT NULL,
        data longtext NOT NULL,
        ip_address varchar(45) DEFAULT NULL,
        user_agent text,
        referer_url text,
        submitted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY form_id (form_id),
        KEY submitted_at (submitted_at),
        KEY form_id_submitted (form_id, submitted_at),
        KEY form_id_id (form_id, id),
        KEY form_id_ip (form_id, ip_address)
    ) $charset_collate;";
}

register_activation_hook(__FILE__, 'nrfm_activate');
function nrfm_activate() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta( nrfm_submissions_table_sql() );

    // Tables/columns/cron for the built-in feature modules folded in from the
    // former Pro add-on (Save & Resume, Submission Notifications, Webhooks).
    nrfm_install_feature_schema();

    nrfm_register_post_type();
    flush_rewrite_rules();
    update_option( 'nrfm_schema_version', 4 );
}

// Clean up feature crons on deactivation.
register_deactivation_hook(__FILE__, 'nrfm_deactivate');
function nrfm_deactivate() {
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-save-resume.php';
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-webhooks.php';
    NRFM_Save_Resume::unschedule_cleanup();
    NRFM_Webhooks::unschedule_cleanup();
}

/**
 * Create the schema owned by the folded-in features and schedule their cron.
 * Idempotent, so it is safe to call on activation and on schema upgrade.
 */
function nrfm_install_feature_schema() {
    require_once NRFM_PLUGIN_DIR . 'includes/functions.php';
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-save-resume.php';
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-submission-notifications.php';
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-webhooks.php';
    NRFM_Save_Resume::create_table();
    NRFM_Save_Resume::schedule_cleanup();
    NRFM_Submission_Notifications::maybe_add_seen_column();
    NRFM_Webhooks::create_table();
    NRFM_Webhooks::schedule_cleanup();

    // One-time cleanup of artifacts from the former Pro add-on (its schema is now
    // owned by core's nrfm_schema_version). All no-ops on a fresh install or a
    // site that never ran the old Pro add-on.
    // TODO: remove this block in a later release. It only matters for sites
    // upgrading past these schema versions that had run the old Pro add-on; once
    // those have updated, it is pure dead code.
    wp_clear_scheduled_hook( 'nrfm_pro_resume_cleanup' );
    wp_clear_scheduled_hook( 'nrfm_pro_webhook_retry' );
    wp_clear_scheduled_hook( 'nrfm_pro_webhook_logs_cleanup' );
    delete_option( 'nrfm_pro_notifications_schema' );
    delete_option( 'nrfm_pro_resume_schema' );
    delete_option( 'nrfm_pro_webhooks_schema' );
}

// Schema upgrades (add indexes safely)
add_action( 'admin_init', 'nrfm_maybe_upgrade_schema' );
function nrfm_maybe_upgrade_schema() {
    $current = (int) get_option( 'nrfm_schema_version', 1 );
    if ( $current >= 4 ) {
        return;
    }
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta( nrfm_submissions_table_sql() );

    // Save & Resume table + cron, the Submission Notifications `seen` column, and
    // the Webhooks logs table (folded in from the former Pro add-on). Idempotent.
    nrfm_install_feature_schema();

    update_option( 'nrfm_schema_version', 4 );
}

// Flush rewrite rules whenever the share-link base changes (and once after this
// rule was introduced). The signature is keyed to the base slug, so changing the
// Direct Link Base self-heals on the next admin load instead of needing a manual
// Settings -> Permalinks flush. Runs on admin_init, after 'init' has registered
// the current rule, so the flush persists the correct pattern.
add_action( 'admin_init', 'nrfm_maybe_flush_rewrites' );
function nrfm_maybe_flush_rewrites() {
    $signature = '2:' . nrfm_get_share_base();
    if ( get_option( 'nrfm_rewrite_signature' ) !== $signature ) {
        flush_rewrite_rules();
        update_option( 'nrfm_rewrite_signature', $signature );
    }
}

// Cutover safety: if the retired Pro add-on is still active alongside this
// version (its features are now built in), tell the admin to remove it.
// TODO: removable in a later release once sites have completed the cutover.
add_action( 'admin_notices', 'nrfm_legacy_pro_notice' );
function nrfm_legacy_pro_notice() {
    if ( ! defined( 'NRFM_PRO_VERSION' ) || ! current_user_can( 'activate_plugins' ) ) {
        return;
    }
    echo '<div class="notice notice-warning"><p>'
        . esc_html__( 'Narrative Forms Pro is now built into Narrative Forms. You can safely deactivate and delete the Narrative Forms Pro plugin.', 'narrative-forms' )
        . '</p></div>';
}

// Initialize plugin
add_action('plugins_loaded', 'nrfm_init');
function nrfm_init() {
    // Load required files
    require_once NRFM_PLUGIN_DIR . 'includes/functions.php';
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-form.php';
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-submission.php';
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-ajax.php';
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-captcha.php';
    
    // Register AJAX handlers for frontend submissions
    $nrfm_ajax = new NRFM_Ajax();
    add_action('wp_ajax_nrfm_submit_form', array($nrfm_ajax, 'handle_submission'));
    add_action('wp_ajax_nopriv_nrfm_submit_form', array($nrfm_ajax, 'handle_submission'));

    // Load admin files
    if (is_admin()) {
        require_once NRFM_PLUGIN_DIR . 'includes/admin/class-nrfm-admin.php';
        new NRFM_Admin();

        require_once NRFM_PLUGIN_DIR . 'includes/admin/class-nrfm-ai-form-builder.php';
        new NRFM_AI_Form_Builder();
    }

    // Built-in feature modules (load in both admin and frontend contexts: each
    // module scopes its own hooks/assets to where they are needed).
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-conditional-logic.php';
    new NRFM_Conditional_Logic();
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-submission-notifications.php';
    new NRFM_Submission_Notifications();
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-save-resume.php';
    new NRFM_Save_Resume();
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-webhooks.php';
    new NRFM_Webhooks();
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-webhook-templates.php';
    new NRFM_Webhook_Templates();
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-rest-api.php';
    new NRFM_REST_API();
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-require-login.php';
    new NRFM_Require_Login();
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-schedule-windows.php';
    new NRFM_Schedule_Windows();
    require_once NRFM_PLUGIN_DIR . 'includes/class-nrfm-share-links.php';
    new NRFM_Share_Links();

    // Initialize free CAPTCHA
    new NRFM_Captcha();

    // Async actions processor
    add_action('nrfm_process_actions_async', 'nrfm_process_actions_async_job');
}

function nrfm_process_actions_async_job( $job ) {
    $form_id = isset( $job['form_id'] ) ? intval( $job['form_id'] ) : 0;
    $data    = isset( $job['data'] ) && is_array( $job['data'] ) ? $job['data'] : array();
    if ( $form_id <= 0 || empty( $data ) ) {
        return;
    }
    $form = new NRFM_Form( $form_id );
    if ( ! $form->exists() ) {
        return;
    }
    $submission = new NRFM_Submission();
    if ( method_exists( $submission, 'process_actions' ) ) {
        $submission->process_actions( $form, $data );
    }
}

// Listen for form preview requests (server-rendered like HTML Forms)
add_action( 'parse_request', 'nrfm_listen_for_preview' );
function nrfm_listen_for_preview() {
	if ( empty( $_GET['nrfm_preview_form'] ) || ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$form_id = isset( $_GET['nrfm_preview_form'] ) ? intval( wp_unslash( $_GET['nrfm_preview_form'] ) ) : 0;
	if ( $form_id <= 0 ) {
		return;
	}
	$nonce = isset( $_GET['nrfm_preview_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nrfm_preview_nonce'] ) ) : '';
	if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'nrfm_preview_' . $form_id ) ) {
		return;
	}
	show_admin_bar( false );
	add_filter( 'pre_handle_404', '__return_true' );
	remove_all_actions( 'template_redirect' );
	add_action(
		'template_redirect',
		function() {
			if ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
			status_header( 200 );
			require NRFM_PLUGIN_DIR . 'includes/views/form-preview.php';
			exit;
		}
	);
}

// Register post type
add_action('init', 'nrfm_register_post_type');
function nrfm_register_post_type() {
    register_post_type('nrfm_form', array(
        'labels' => array(
            'name' => __('Forms', 'narrative-forms'),
            'singular_name' => __('Form', 'narrative-forms'),
        ),
        'public' => false,
        'show_ui' => false, // We use custom admin pages
        'capability_type' => 'post',
        'supports' => array('title', 'editor'),
        'rewrite' => false,
    ));
}

// Register shortcode (unique tag)
add_shortcode('nrfm_form', 'nrfm_shortcode');
function nrfm_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => '',
        'slug' => '',
    ), $atts);
    
    // Get form by ID or slug
    $form_id = 0;
    if (!empty($atts['id'])) {
        $form_id = intval($atts['id']);
    } elseif (!empty($atts['slug'])) {
        $form = get_page_by_path($atts['slug'], OBJECT, 'nrfm_form');
        if ($form) {
            $form_id = $form->ID;
        }
    }
    
    if (!$form_id) {
        return '';
    }
    
    $form = new NRFM_Form($form_id);
    if (!$form->exists()) {
        return '';
    }
    
    $GLOBALS['nrfm_form_rendered'] = true;
    return $form->render();
}

// Template function for developers
function nrfm_form($id_or_slug) {
    if (is_numeric($id_or_slug)) {
        echo do_shortcode('[nrfm_form id="' . intval($id_or_slug) . '"]');
    } else {
        echo do_shortcode('[nrfm_form slug="' . esc_attr($id_or_slug) . '"]');
    }
}

// Frontend assets
add_action('wp_enqueue_scripts', 'nrfm_enqueue_frontend_assets');
function nrfm_enqueue_frontend_assets() {
    // Load on preview or pages with forms
    $is_preview = ! empty( $_GET['nrfm_preview_form'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    global $post;
    if ( ! $is_preview && ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'nrfm_form' ) ) ) {
        return;
    }
    
    // Load the frontend stylesheet only when enabled in settings (defaults to off).
    $settings = function_exists('nrfm_get_settings') ? nrfm_get_settings() : get_option('nrfm_settings', array());
    $load_main_css = !empty($settings['load_stylesheet']);
    if ($load_main_css) {
        // Version by file mtime so CSS edits bust the browser cache (falls back to NRFM_VERSION).
        $css_path = NRFM_PLUGIN_DIR . 'assets/css/frontend.css';
        $css_ver  = file_exists($css_path) ? (string) filemtime($css_path) : NRFM_VERSION;
        wp_enqueue_style('narrative-forms', NRFM_PLUGIN_URL . 'assets/css/frontend.css', array(), $css_ver);
    }

    // Register script; enqueue later in footer only if a form actually rendered
    $js_path = NRFM_PLUGIN_DIR . 'assets/js/frontend.js';
    $js_ver  = file_exists($js_path) ? (string) filemtime($js_path) : NRFM_VERSION;
    wp_register_script('narrative-forms', NRFM_PLUGIN_URL . 'assets/js/frontend.js', array(), $js_ver, true);
    wp_localize_script('narrative-forms', 'nrfm_ajax', array(
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('nrfm_ajax_nonce'),
    ));
    add_action('wp_footer', function(){ if (!empty($GLOBALS['nrfm_form_rendered'])) { wp_enqueue_script('narrative-forms'); } });
}

// Handle non-AJAX form submission
add_action('init', 'nrfm_handle_form_submission');
function nrfm_handle_form_submission() {
    // If this is an AJAX request, the dedicated AJAX handler will process it
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    if (empty($_POST['nrfm_form_id'])) {
        return;
    }
    
    $form_id = intval($_POST['nrfm_form_id']);
    // Verify nonce BEFORE further processing
    $nonce = isset($_POST['nrfm_nonce']) ? sanitize_text_field( wp_unslash($_POST['nrfm_nonce']) ) : '';
    if (! wp_verify_nonce($nonce, 'nrfm_form_' . $form_id)) {
        return;
    }
    $form = new NRFM_Form($form_id);
    if (!$form->exists()) { return; }

    // Verify honeypot
    $honeypot = 'nrfm_hp_' . $form_id;
    if (!empty($_POST[$honeypot])) {
        return; // Spam
    }
    
    // Process submission with only expected fields from the form markup
    $submission = new NRFM_Submission();
    $allowed_names = method_exists($form, 'get_field_names') ? array_fill_keys($form->get_field_names(), true) : array();
    $input = array();
    foreach ($allowed_names as $nm => $_) {
        if (isset($_POST[$nm])) {
            $raw = wp_unslash($_POST[$nm]); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $input[$nm] = is_array($raw)
                ? map_deep($raw, 'sanitize_text_field')
                : sanitize_text_field($raw);
        }
    }
    $result = $submission->process($form_id, $input);
    
    // Handle redirect using token-replaced URL from result (supports success or error)
    if (!empty($result['redirect_url'])) {
        wp_safe_redirect($result['redirect_url']);
        exit;
    }
    
    // Store result in transient for display
    set_transient('nrfm_submission_' . $form_id, $result, 60);
}