<?php

defined( 'ABSPATH' ) || exit;

class NRFM_Schedule_Windows {
	public function __construct() {
		add_action( 'nrfm_form_settings_access_availability', array( $this, 'render_setting' ) );
		add_action( 'nrfm_save_form', array( $this, 'save_setting' ), 10, 2 );
		add_filter( 'nrfm_validate_submission', array( $this, 'validate_window' ), 10, 3 );
		add_filter( 'nrfm_form_render', array( $this, 'maybe_block_form' ), 10, 2 );
		add_filter( 'nrfm_form_default_messages', array( $this, 'add_default_message' ) );
	}

	public function render_setting( $form ) {
		if ( ! ( $form instanceof NRFM_Form ) ) {
			return;
		}
		$form_id = $form->get_id();
		$enabled = (int) get_post_meta( $form_id, '_nrfm_schedule_enabled', true );
		$open_ts = (int) get_post_meta( $form_id, '_nrfm_schedule_open_ts', true );
		$close_ts = (int) get_post_meta( $form_id, '_nrfm_schedule_close_ts', true );
		$message_open = (string) get_post_meta( $form_id, '_nrfm_schedule_message_open', true );
		$message_close = (string) get_post_meta( $form_id, '_nrfm_schedule_message_close', true );
		$tz = wp_timezone();
		$tz_label = wp_timezone_string();
		if ( $tz_label === '' ) {
			$tz_label = (string) get_option( 'timezone_string' );
		}
		if ( $tz_label === '' ) {
			$offset = (float) get_option( 'gmt_offset', 0 );
			$tz_label = 'UTC' . ( $offset >= 0 ? '+' : '' ) . $offset;
		}
		$open_val = $open_ts ? wp_date( 'Y-m-d\TH:i', $open_ts, $tz ) : '';
		$close_val = $close_ts ? wp_date( 'Y-m-d\TH:i', $close_ts, $tz ) : '';
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Schedule Window', 'narrative-forms' ); ?></th>
			<td>
				<label>
					<input type="checkbox" id="nrfm-schedule-enabled" name="nrfm_schedule_enabled" value="1" <?php checked( $enabled, 1 ); ?> />
					<?php esc_html_e( 'Enable open/close dates for this form', 'narrative-forms' ); ?>
				</label>
				<p class="description"><?php echo esc_html( sprintf( /* translators: %s: the site timezone name. */ __( 'Times use your site timezone: %s.', 'narrative-forms' ), $tz_label ) ); ?></p>
				<div class="nrfm-setting-children" data-parent-input="#nrfm-schedule-enabled">
					<p>
						<label for="nrfm-open-date"><?php esc_html_e( 'Open Date', 'narrative-forms' ); ?></label><br>
						<input type="datetime-local" id="nrfm-open-date" name="nrfm_schedule_open" value="<?php echo esc_attr( $open_val ); ?>">
					</p>
					<p>
						<label for="nrfm-close-date"><?php esc_html_e( 'Close Date', 'narrative-forms' ); ?></label><br>
						<input type="datetime-local" id="nrfm-close-date" name="nrfm_schedule_close" value="<?php echo esc_attr( $close_val ); ?>">
					</p>
					<p>
						<label for="nrfm-schedule-message-open"><?php esc_html_e( 'Not Open Yet Message (optional)', 'narrative-forms' ); ?></label><br>
						<input type="text" id="nrfm-schedule-message-open" name="nrfm_schedule_message_open" class="large-text" value="<?php echo esc_attr( $message_open ); ?>" placeholder="<?php echo esc_attr__( 'This form is not open yet.', 'narrative-forms' ); ?>">
					</p>
					<p>
						<label for="nrfm-schedule-message-close"><?php esc_html_e( 'Closed Message (optional)', 'narrative-forms' ); ?></label><br>
						<input type="text" id="nrfm-schedule-message-close" name="nrfm_schedule_message_close" class="large-text" value="<?php echo esc_attr( $message_close ); ?>" placeholder="<?php echo esc_attr__( 'This form is now closed.', 'narrative-forms' ); ?>">
					</p>
				</div>
			</td>
		</tr>
		<?php
	}

	public function save_setting( $form_id, $data ) {
		$enabled = ! empty( $_POST['nrfm_schedule_enabled'] ) ? 1 : 0;
		update_post_meta( $form_id, '_nrfm_schedule_enabled', $enabled );

		$open = isset( $_POST['nrfm_schedule_open'] ) ? sanitize_text_field( wp_unslash( $_POST['nrfm_schedule_open'] ) ) : '';
		$close = isset( $_POST['nrfm_schedule_close'] ) ? sanitize_text_field( wp_unslash( $_POST['nrfm_schedule_close'] ) ) : '';
		update_post_meta( $form_id, '_nrfm_schedule_open_ts', $this->parse_datetime_local( $open ) );
		update_post_meta( $form_id, '_nrfm_schedule_close_ts', $this->parse_datetime_local( $close ) );

		$message_open = isset( $_POST['nrfm_schedule_message_open'] ) ? sanitize_text_field( wp_unslash( $_POST['nrfm_schedule_message_open'] ) ) : '';
		if ( $message_open !== '' ) {
			update_post_meta( $form_id, '_nrfm_schedule_message_open', $message_open );
		} else {
			delete_post_meta( $form_id, '_nrfm_schedule_message_open' );
		}
		$message_close = isset( $_POST['nrfm_schedule_message_close'] ) ? sanitize_text_field( wp_unslash( $_POST['nrfm_schedule_message_close'] ) ) : '';
		if ( $message_close !== '' ) {
			update_post_meta( $form_id, '_nrfm_schedule_message_close', $message_close );
		} else {
			delete_post_meta( $form_id, '_nrfm_schedule_message_close' );
		}
	}

	private function parse_datetime_local( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( $value === '' ) {
			return 0;
		}
		$dt = date_create_from_format( 'Y-m-d\TH:i', $value, wp_timezone() );
		if ( ! $dt ) {
			return 0;
		}
		return (int) $dt->getTimestamp();
	}

	public function validate_window( $result, $data, $form ) {
		if ( ! ( $form instanceof NRFM_Form ) ) {
			return $result;
		}
		$form_id = $form->get_id();
		$enabled = (int) get_post_meta( $form_id, '_nrfm_schedule_enabled', true );
		if ( ! $enabled ) {
			return $result;
		}
		$status = $this->get_window_status( $form_id );
		if ( $status === 'not_open' ) {
			return array( 'valid' => false, 'error_code' => 'schedule_not_open' );
		}
		if ( $status === 'closed' ) {
			return array( 'valid' => false, 'error_code' => 'schedule_closed' );
		}
		return $result;
	}

	public function maybe_block_form( $html, $form ) {
		if ( ! ( $form instanceof NRFM_Form ) ) {
			return $html;
		}
		$form_id = $form->get_id();
		$enabled = (int) get_post_meta( $form_id, '_nrfm_schedule_enabled', true );
		if ( ! $enabled ) {
			return $html;
		}
		$status = $this->get_window_status( $form_id );
		if ( $status === 'open' ) {
			return $html;
		}
		if ( $status === 'not_open' ) {
			$message = (string) get_post_meta( $form_id, '_nrfm_schedule_message_open', true );
			if ( $message === '' ) {
				$message = __( 'This form is not open yet.', 'narrative-forms' );
			}
		} else {
			$message = (string) get_post_meta( $form_id, '_nrfm_schedule_message_close', true );
			if ( $message === '' ) {
				$message = __( 'This form is now closed.', 'narrative-forms' );
			}
		}
		return nrfm_error_message_html( $message );
	}

	private function get_window_status( $form_id ) {
		// Open/close timestamps are true Unix epochs (DateTime::getTimestamp in
		// parse_datetime_local), so compare against a real epoch — not the
		// GMT-offset-shifted current_time('timestamp'), which is wrong off-UTC.
		$now = time();
		$open_ts = (int) get_post_meta( $form_id, '_nrfm_schedule_open_ts', true );
		$close_ts = (int) get_post_meta( $form_id, '_nrfm_schedule_close_ts', true );
		if ( $open_ts > 0 && $now < $open_ts ) {
			return 'not_open';
		}
		if ( $close_ts > 0 && $now > $close_ts ) {
			return 'closed';
		}
		return 'open';
	}

	public function add_default_message( $messages ) {
		if ( ! isset( $messages['schedule_not_open'] ) ) {
			$messages['schedule_not_open'] = __( 'This form is not open yet.', 'narrative-forms' );
		}
		if ( ! isset( $messages['schedule_closed'] ) ) {
			$messages['schedule_closed'] = __( 'This form is now closed.', 'narrative-forms' );
		}
		return $messages;
	}
}
