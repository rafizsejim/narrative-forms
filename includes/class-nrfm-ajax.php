<?php
/**
 * AJAX handler class
 */
class NRFM_Ajax {

	public function handle_submission() {
		// Verify nonce
		if ( ! check_ajax_referer( 'nrfm_ajax_nonce', 'nrfm_ajax_nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security check failed. Please refresh the page and try again.', 'narrative-forms' ),
				)
			);
		}

		// Check form ID
		if ( empty( $_POST['nrfm_form_id'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Form ID missing.', 'narrative-forms' ),
				)
			);
		}

		$form_id = intval( $_POST['nrfm_form_id'] );
		$form    = new NRFM_Form( $form_id );

		if ( ! $form->exists() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Form not found.', 'narrative-forms' ),
				)
			);
		}

		// Check honeypot
		$global_settings  = function_exists( 'nrfm_get_settings' ) ? nrfm_get_settings( null, array( 'honeypot_enabled' => 1 ) ) : get_option( 'nrfm_settings', array( 'honeypot_enabled' => 1 ) );
		$settings         = $form->get_settings();
		$honeypot_enabled = isset( $settings['honeypot_enabled'] ) ? $settings['honeypot_enabled'] : $global_settings['honeypot_enabled'];

		if ( $honeypot_enabled ) {
			$honeypot_field = 'nrfm_hp_' . $form_id;
			if ( ! empty( $_POST[ $honeypot_field ] ) ) {
				// It's spam, but return success to trick bots
				wp_send_json_success(
					array(
						'message'      => $form->get_message( 'success' ),
						'hide_form'    => true,
						'redirect_url' => '',
					)
				);
			}
		}

		// Process submission with only expected fields from the form markup (includes file handling)
		$submission    = new NRFM_Submission();
		$allowed_names = method_exists( $form, 'get_field_names' ) ? array_fill_keys( $form->get_field_names(), true ) : array();
		$input         = array();
		foreach ( $allowed_names as $nm => $_x ) {
			if ( isset( $_POST[ $nm ] ) ) {
				// Sanitize immediately to satisfy WPCS: sanitize input at read
				$input[ $nm ] = is_array( $_POST[ $nm ] )
					? map_deep( wp_unslash( $_POST[ $nm ] ), 'sanitize_text_field' )
					: sanitize_text_field( wp_unslash( $_POST[ $nm ] ) );
			}
		}
		$result = $submission->process( $form_id, $input );

		// Make sure we have all required response fields
		if ( $result['success'] ) {
			$response = array(
				'message'      => $result['message'],
				'hide_form'    => isset( $result['hide_form'] ) ? $result['hide_form'] : false,
				'redirect_url' => isset( $result['redirect_url'] ) ? $result['redirect_url'] : '',
			);
			wp_send_json_success( $response );
		} else {
			wp_send_json_error(
				array(
					'message' => $result['message'],
				)
			);
		}
	}
}
