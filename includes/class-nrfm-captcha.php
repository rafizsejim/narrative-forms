<?php
// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Narrative Forms - Free CAPTCHA (Cloudflare Turnstile)
 * Lightweight global toggle + keys; server-side verification.
 */
class NRFM_Captcha {

	public function __construct() {
		// Admin settings UI and saving
		add_action('nrfm_settings_form_protection', array($this, 'render_settings')); // inside settings table
		add_filter('nrfm_save_global_settings', array($this, 'save_settings'));

		// Frontend
		add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend'));
		add_filter('nrfm_form_html', array($this, 'inject_widget'), 10, 2);
		add_filter('nrfm_form_default_messages', array($this, 'add_default_messages'));
		add_filter('nrfm_validate_submission', array($this, 'verify_token'), 10, 3);
	}

	private function get_options() {
		$settings = function_exists('nrfm_get_settings') ? nrfm_get_settings() : get_option('nrfm_settings', array());
		return array(
			'enabled'   => !empty($settings['turnstile_enabled']) ? 1 : 0,
			'site_key'  => isset($settings['turnstile_site_key']) ? trim($settings['turnstile_site_key']) : '',
			'secret_key'=> isset($settings['turnstile_secret_key']) ? trim($settings['turnstile_secret_key']) : '',
		);
	}

	public function render_settings() {
		$settings = $this->get_options();
		?>
		<tr>
			<th scope="row"><?php esc_html_e('Enable Turnstile (Captcha)', 'narrative-forms'); ?></th>
			<td>
				<label>
					<input type="checkbox" id="nrfm-turnstile-enabled" name="turnstile_enabled" value="1" <?php checked($settings['enabled'], 1); ?>>
					<?php esc_html_e('Protect forms using Cloudflare Turnstile (recommended)', 'narrative-forms'); ?>
				</label>
				<p class="description"><?php esc_html_e('Free, privacy‑friendly CAPTCHA by Cloudflare. Requires a site key and secret key.', 'narrative-forms'); ?></p>
				<div class="nrfm-setting-children" data-parent-input="#nrfm-turnstile-enabled">
					<p>
						<label for="turnstile-site-key"><?php esc_html_e('Site Key', 'narrative-forms'); ?></label><br>
						<input type="text" id="turnstile-site-key" name="turnstile_site_key" class="regular-text" value="<?php echo esc_attr($settings['site_key']); ?>">
					</p>
					<p>
						<label for="turnstile-secret-key"><?php esc_html_e('Secret Key', 'narrative-forms'); ?></label><br>
						<input type="password" id="turnstile-secret-key" name="turnstile_secret_key" class="regular-text" value="<?php echo esc_attr($settings['secret_key']); ?>">
					</p>
				</div>
			</td>
		</tr>
		<?php
	}

	public function save_settings($settings) {
		// Verify nonce from settings page
		$nonce = isset($_POST['_wpnonce']) ? sanitize_text_field( wp_unslash($_POST['_wpnonce']) ) : '';
		if (! wp_verify_nonce($nonce, 'nrfm_admin_action')) {
			return $settings;
		}
		$settings['turnstile_enabled']   = !empty($_POST['turnstile_enabled']) ? 1 : 0;
		$settings['turnstile_site_key']  = isset($_POST['turnstile_site_key']) ? sanitize_text_field(wp_unslash($_POST['turnstile_site_key'])) : '';
		$settings['turnstile_secret_key']= isset($_POST['turnstile_secret_key']) ? sanitize_text_field(wp_unslash($_POST['turnstile_secret_key'])) : '';
		return $settings;
	}

	public function enqueue_frontend() {
		$opt = $this->get_options();
		if (!$opt['enabled'] || empty($opt['site_key'])) return;
		global $post;
		if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'nrfm_form')) return;
		// Load a local bootstrap that injects the remote script at runtime to avoid direct offloaded enqueue
		wp_register_script('nrfm-turnstile-loader', NRFM_PLUGIN_URL . 'assets/js/turnstile-loader.js', array(), NRFM_VERSION, true);
		wp_enqueue_script('nrfm-turnstile-loader');
		wp_localize_script('nrfm-turnstile-loader', 'nrfm_turnstile', array(
			'src' => 'https://challenges.cloudflare.com/turnstile/v0/api.js'
		));
	}

	public function inject_widget($form_html, $form) {
		$opt = $this->get_options();
		if (!$opt['enabled'] || empty($opt['site_key'])) return $form_html;
		$widget = '<div class="nrfm-captcha"><div class="cf-turnstile" data-sitekey="' . esc_attr($opt['site_key']) . '"></div></div>';
		// append just before closing form tag
		if (preg_match('/<\/form>\s*$/i', $form_html)) {
			return preg_replace('/<\/form>\s*$/i', $widget . '</form>', $form_html, 1);
		}
		return $form_html . $widget; // fallback
	}

	public function add_default_messages($defaults) {
		if (!isset($defaults['captcha_failed'])) {
			$defaults['captcha_failed'] = __('Captcha verification failed. Please try again.', 'narrative-forms');
		}
		return $defaults;
	}

	public function verify_token($is_valid, $data, $form) {
		$opt = $this->get_options();
		if (!$opt['enabled'] || empty($opt['secret_key'])) return $is_valid;
		// Defensive: verify the primary form nonce against this form to prevent CSRF via direct calls to this filter.
		$posted_nonce = isset($_POST['nrfm_nonce']) ? sanitize_text_field( wp_unslash($_POST['nrfm_nonce']) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$form_id_for_nonce = is_object($form) && method_exists($form, 'get_id') ? (int) $form->get_id() : 0;
		if (empty($posted_nonce) || $form_id_for_nonce <= 0 || ! wp_verify_nonce($posted_nonce, 'nrfm_form_' . $form_id_for_nonce)) {
			return array('valid' => false, 'error_code' => 'captcha_failed');
		}
		// Token is only processed after the primary form nonce has been verified above
		$token = isset($_POST['cf-turnstile-response']) ? sanitize_text_field(wp_unslash($_POST['cf-turnstile-response'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Primary form nonce verified upstream before validation
		if ($token === '') {
			return array('valid' => false, 'error_code' => 'captcha_failed');
		}
		$body = array(
			'secret'   => $opt['secret_key'],
			'response' => $token,
			'remoteip' => (function(){
				$raw = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
				$raw = is_string($raw) ? $raw : '';
				$ip  = filter_var($raw, FILTER_VALIDATE_IP) ? $raw : '';
				return $ip !== '' ? sanitize_text_field($ip) : '';
			})(),
		);
		$response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
			'timeout' => 10,
			'body'    => $body,
		));
		if (is_wp_error($response)) {
			return array('valid' => false, 'error_code' => 'captcha_failed');
		}
		$json = json_decode(wp_remote_retrieve_body($response), true);
		if (empty($json['success'])) {
			return array('valid' => false, 'error_code' => 'captcha_failed');
		}
		return $is_valid; // allow other validators to continue
	}
}


