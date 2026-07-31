<?php

defined( 'ABSPATH' ) || exit;

/**
 * Direct / Share Links: a hosted URL for any form at /{base}/{slug}, plus the
 * per-form enable toggle and the global base-slug setting. The base slug is
 * stored as `pro_share_base` inside nrfm_settings (key kept for backward compat).
 */
class NRFM_Share_Links {

	public function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'nrfm_settings_form_urls', array( $this, 'render_share_base_setting' ), 5 );
		add_filter( 'nrfm_save_global_settings', array( $this, 'save_share_base_setting' ) );
		add_action( 'nrfm_form_settings_sharing', array( $this, 'render_share_link' ) );
		add_action( 'nrfm_save_form', array( $this, 'save_share_link_setting' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_share' ), 0 );
	}

	public function add_rewrite_rules() {
		$base = nrfm_get_share_base();
		add_rewrite_rule( '^' . preg_quote( $base, '/' ) . '/([^/]+)/?$', 'index.php?nrfm_share_slug=$matches[1]', 'top' );
	}

	public function register_query_vars( $vars ) {
		$vars[] = 'nrfm_share_slug';
		return $vars;
	}

	public function render_share_base_setting() {
		$settings = nrfm_get_settings();
		$share_base = isset( $settings['pro_share_base'] ) ? sanitize_text_field( $settings['pro_share_base'] ) : '';
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Direct Link Base', 'narrative-forms' ); ?></th>
			<td>
				<label>
					<input type="text" name="nrfm_share_base" class="regular-text" value="<?php echo esc_attr( $share_base ); ?>" placeholder="forms">
				</label>
				<p class="description"><?php esc_html_e( 'Base slug for hosted form links. Example: /forms/your-form-slug', 'narrative-forms' ); ?></p>
			</td>
		</tr>
		<?php
	}

	public function save_share_base_setting( $settings ) {
		$share_base = isset( $_POST['nrfm_share_base'] ) ? sanitize_text_field( wp_unslash( $_POST['nrfm_share_base'] ) ) : '';
		$settings['pro_share_base'] = $share_base;
		return $settings;
	}

	public function render_share_link( $form ) {
		if ( ! ( $form instanceof NRFM_Form ) ) {
			return;
		}
		$form_id = $form->get_id();
		$enabled = (int) get_post_meta( $form_id, '_nrfm_share_enabled', true );
		$slug = $form->get_slug();
		$slug = $slug ? sanitize_title( $slug ) : (string) $form_id;
		$base = nrfm_get_share_base();
		$url  = home_url( trailingslashit( $base ) . $slug );
		?>
		<tr>
			<th scope="row">
				<label for="nrfm-share-link"><?php esc_html_e( 'Direct Form Link', 'narrative-forms' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" id="nrfm-share-enabled" name="nrfm_share_enabled" value="1" <?php checked( $enabled, 1 ); ?> />
					<?php esc_html_e( 'Enable direct link for this form', 'narrative-forms' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Link is visible here, but will only work after you enable it and save.', 'narrative-forms' ); ?></p>
				<div class="nrfm-setting-children" data-parent-input="#nrfm-share-enabled">
					<input type="url" id="nrfm-share-link" class="large-text code" readonly value="<?php echo esc_url( $url ); ?>">
					<p class="description"><?php esc_html_e( 'Share this URL to open a hosted version of the form.', 'narrative-forms' ); ?></p>
					<p><button type="button" class="button" id="nrfm-copy-share-link" data-target="nrfm-share-link"><?php esc_html_e( 'Copy Link', 'narrative-forms' ); ?></button></p>
				</div>
			</td>
		</tr>
		<?php
	}

	public function save_share_link_setting( $form_id, $data ) {
		$enabled = ! empty( $_POST['nrfm_share_enabled'] ) ? 1 : 0;
		update_post_meta( $form_id, '_nrfm_share_enabled', $enabled );
	}

	public function enqueue_admin( $hook ) {
		if ( ! nrfm_is_form_edit_screen( $hook ) ) {
			return;
		}
		wp_enqueue_script( 'nrfm-share-link', NRFM_PLUGIN_URL . 'assets/js/share-link-admin.js', array(), NRFM_VERSION, true );
		wp_localize_script(
			'nrfm-share-link',
			'nrfmShareLink',
			array(
				'copyText'   => __( 'Copy Link', 'narrative-forms' ),
				'copiedText' => __( 'Copied', 'narrative-forms' ),
			)
		);
	}

	public function maybe_render_share() {
		if ( is_admin() ) {
			return;
		}
		$form_id = 0;
		$slug = get_query_var( 'nrfm_share_slug' );
		if ( $slug ) {
			$slug = sanitize_title( wp_unslash( $slug ) );
			if ( ctype_digit( $slug ) ) {
				$form_id = intval( $slug );
			} else {
				$post = get_page_by_path( $slug, OBJECT, 'nrfm_form' );
				if ( $post ) {
					$form_id = (int) $post->ID;
				}
			}
		} elseif ( isset( $_GET['nrfm_share'] ) ) {
			$form_id = intval( wp_unslash( $_GET['nrfm_share'] ) );
		}

		if ( $form_id <= 0 ) {
			return;
		}
		if ( (int) get_post_meta( $form_id, '_nrfm_share_enabled', true ) !== 1 ) {
			return;
		}
		require_once NRFM_PLUGIN_DIR . 'includes/views/form-share.php';
		exit;
	}
}
