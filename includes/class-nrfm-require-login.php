<?php

defined( 'ABSPATH' ) || exit;

class NRFM_Require_Login {
	public function __construct() {
		add_action( 'nrfm_form_settings_access_availability', array( $this, 'render_setting' ) );
		add_action( 'nrfm_save_form', array( $this, 'save_setting' ), 10, 2 );
		add_filter( 'nrfm_validate_submission', array( $this, 'validate_login' ), 10, 3 );
		add_filter( 'nrfm_form_render', array( $this, 'maybe_block_form' ), 10, 2 );
		add_filter( 'nrfm_form_default_messages', array( $this, 'add_default_message' ) );
	}

	public function render_setting( $form ) {
		if ( ! ( $form instanceof NRFM_Form ) ) {
			return;
		}
		$form_id = $form->get_id();
		$enabled = (int) get_post_meta( $form_id, '_nrfm_require_login', true );
		$message = (string) get_post_meta( $form_id, '_nrfm_login_message', true );
		$link    = (string) get_post_meta( $form_id, '_nrfm_login_link', true );
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Require Login', 'narrative-forms' ); ?></th>
			<td>
				<label>
					<input type="checkbox" id="nrfm-require-login-enabled" name="nrfm_require_login" value="1" <?php checked( $enabled, 1 ); ?> />
					<?php esc_html_e( 'Only allow logged-in users to submit this form.', 'narrative-forms' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'You can use {{user.email}}, {{user.name}}, etc. in your form HTML to prefill user data.', 'narrative-forms' ); ?>
				</p>
				<div class="nrfm-setting-children" data-parent-input="#nrfm-require-login-enabled">
					<p>
						<label for="nrfm-login-message"><?php esc_html_e( 'Message (optional)', 'narrative-forms' ); ?></label><br>
						<input type="text" id="nrfm-login-message" name="nrfm_login_message" class="large-text" value="<?php echo esc_attr( $message ); ?>" placeholder="<?php echo esc_attr__( 'Please log in to continue.', 'narrative-forms' ); ?>">
					</p>
					<p>
						<label for="nrfm-login-link"><?php esc_html_e( 'Login URL (optional)', 'narrative-forms' ); ?></label><br>
						<input type="url" id="nrfm-login-link" name="nrfm_login_link" class="large-text code" value="<?php echo esc_attr( $link ); ?>" placeholder="<?php echo esc_attr( wp_login_url() ); ?>">
					</p>
				</div>
			</td>
		</tr>
		<?php
	}

	public function save_setting( $form_id, $data ) {
		$enabled = ! empty( $_POST['nrfm_require_login'] ) ? 1 : 0;
		update_post_meta( $form_id, '_nrfm_require_login', $enabled );
		$message = isset( $_POST['nrfm_login_message'] ) ? sanitize_text_field( wp_unslash( $_POST['nrfm_login_message'] ) ) : '';
		if ( $message !== '' ) {
			update_post_meta( $form_id, '_nrfm_login_message', $message );
		} else {
			delete_post_meta( $form_id, '_nrfm_login_message' );
		}
		$link = isset( $_POST['nrfm_login_link'] ) ? esc_url_raw( wp_unslash( $_POST['nrfm_login_link'] ) ) : '';
		if ( $link !== '' ) {
			update_post_meta( $form_id, '_nrfm_login_link', $link );
		} else {
			delete_post_meta( $form_id, '_nrfm_login_link' );
		}
	}

	public function validate_login( $result, $data, $form ) {
		if ( ! ( $form instanceof NRFM_Form ) ) {
			return $result;
		}
		$form_id = $form->get_id();
		$enabled = (int) get_post_meta( $form_id, '_nrfm_require_login', true );
		if ( $enabled && ! is_user_logged_in() ) {
			return array( 'valid' => false, 'error_code' => 'login_required' );
		}
		return $result;
	}

	public function maybe_block_form( $html, $form ) {
		if ( ! ( $form instanceof NRFM_Form ) ) {
			return $html;
		}
		$form_id = $form->get_id();
		$enabled = (int) get_post_meta( $form_id, '_nrfm_require_login', true );
		if ( ! $enabled || is_user_logged_in() ) {
			return $html;
		}
		$message = (string) get_post_meta( $form_id, '_nrfm_login_message', true );
		if ( $message === '' ) {
			$message = __( 'Please log in to continue.', 'narrative-forms' );
		}
		$login_url = (string) get_post_meta( $form_id, '_nrfm_login_link', true );
		if ( $login_url === '' ) {
			$login_url = wp_login_url( get_permalink() );
		}
		return nrfm_error_message_html( $message, $login_url, __( 'Log in', 'narrative-forms' ) );
	}

	public function add_default_message( $messages ) {
		if ( ! isset( $messages['login_required'] ) ) {
			$messages['login_required'] = __( 'Please log in to continue.', 'narrative-forms' );
		}
		return $messages;
	}
}
