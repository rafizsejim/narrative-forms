<?php

defined( 'ABSPATH' ) || exit;

/**
 * Submission editing: a signed front-end "manage your listing" link (for submitters,
 * no login required) and an admin edit screen (for site managers). Both reuse the same
 * generated field editor and NRFM_Submission::update_submission() write path. Core owns
 * the write; add-ons only react via the nrfm_submission_updated action.
 */
class NRFM_Edit {

	const ADMIN_SLUG = 'nrfm-forms-edit';

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_render_frontend_edit' ), 0 );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ), 20 );
		add_action( 'admin_head', array( $this, 'hide_admin_menu_link' ) );
	}

	/* ---------------------------------------------------------------------
	 * Front-end: signed edit link  (Model A)
	 * ------------------------------------------------------------------- */

	public function maybe_render_frontend_edit() {
		if ( is_admin() || ! isset( $_GET['nrfm_edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- auth is the signed HMAC token, verified below.
		$id  = isset( $_GET['nrfm_edit'] ) ? (int) $_GET['nrfm_edit'] : 0;
		$exp = isset( $_GET['exp'] ) ? (int) $_GET['exp'] : 0;
		$sig = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';
		$is_post = isset( $_SERVER['REQUEST_METHOD'] ) && strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) === 'POST';
		if ( $is_post ) {
			// On save the token travels in the POST body, not the query string.
			$exp = isset( $_POST['exp'] ) ? (int) $_POST['exp'] : 0;
			$sig = isset( $_POST['sig'] ) ? sanitize_text_field( wp_unslash( $_POST['sig'] ) ) : '';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! nrfm_verify_edit_signature( $id, $exp, $sig ) ) {
			$this->render_page( __( 'Link problem', 'narrative-forms' ), $this->message_html( __( 'This edit link is invalid or has expired. Please use the most recent link from your confirmation email.', 'narrative-forms' ) ) );
			exit;
		}

		$submission = $this->load_submission( $id );
		$form       = $submission ? new NRFM_Form( $submission['form_id'] ) : null;
		if ( ! $submission || ! $form || ! $form->exists() ) {
			$this->render_page( __( 'Not found', 'narrative-forms' ), $this->message_html( __( 'This listing could not be found. It may have been removed.', 'narrative-forms' ) ) );
			exit;
		}

		$notice = '';
		if ( $is_post ) {
			// The signed token (secret, per-submission, re-verified above) is the CSRF
			// guard: a cross-site POST cannot know it.
			$sub    = new NRFM_Submission();
			$result = $sub->update_submission( $id, $this->collect_input( $form ) );
			if ( $result !== false ) {
				$submission = $this->load_submission( $id ); // Reload the saved values.
				$notice     = $this->notice_html( __( 'Your listing has been updated.', 'narrative-forms' ), false );
			} else {
				$notice = $this->notice_html( __( 'Sorry, the update could not be saved. Please try again.', 'narrative-forms' ), true );
			}
		}

		$action = esc_url( add_query_arg( 'nrfm_edit', $id, home_url( '/' ) ) );
		$hidden = '<input type="hidden" name="exp" value="' . esc_attr( (string) $exp ) . '">'
			. '<input type="hidden" name="sig" value="' . esc_attr( $sig ) . '">';

		$body  = $notice;
		$body .= '<form class="nrfm-edit-form" method="post" enctype="multipart/form-data" action="' . $action . '">';
		$body .= $hidden;
		$body .= $this->render_editor_fields( $form, $submission['data'] );
		$body .= '<button type="submit" class="nrfm-edit-submit">' . esc_html__( 'Save changes', 'narrative-forms' ) . '</button>';
		$body .= '</form>';

		/* translators: %s: the form/listing title. */
		$heading = get_the_title( $form->get_id() );
		$heading = $heading !== '' ? $heading : __( 'your listing', 'narrative-forms' );
		$this->render_page(
			__( 'Manage your listing', 'narrative-forms' ),
			'<h1 class="nrfm-edit-title">' . esc_html__( 'Manage your listing', 'narrative-forms' ) . '</h1>'
			. '<p class="nrfm-edit-sub">' . esc_html( sprintf( /* translators: %s: listing title. */ __( 'Editing %s. Changes may need to be re-approved before they appear publicly.', 'narrative-forms' ), $heading ) ) . '</p>'
			. $body
		);
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Admin edit screen  (Model C)
	 * ------------------------------------------------------------------- */

	public function register_admin_page() {
		// Same capability as nrfm_can_manage() so WordPress's own page-access check aligns
		// with the gate inside render_admin_page().
		$cap = apply_filters( 'nrfm_manage_capability', 'manage_options' );
		add_submenu_page(
			'nrfm-forms',
			__( 'Edit Submission', 'narrative-forms' ),
			__( 'Edit Submission', 'narrative-forms' ),
			$cap,
			self::ADMIN_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Hide the edit screen from the submenu without breaking access to it. Removing it
	 * during admin_menu would strip the parent mapping before WordPress runs its
	 * capability check (which then denies the page); admin_head fires after that check but
	 * before the sidebar is rendered, so the link is hidden and the page still loads.
	 */
	public function hide_admin_menu_link() {
		remove_submenu_page( 'nrfm-forms', self::ADMIN_SLUG );
	}

	public function render_admin_page() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to edit submissions.', 'narrative-forms' ) );
		}

		$id         = isset( $_GET['submission'] ) ? (int) $_GET['submission'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$submission = $this->load_submission( $id );
		$form       = $submission ? new NRFM_Form( $submission['form_id'] ) : null;

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Edit Submission', 'narrative-forms' ) . '</h1>';
		echo '<style>
			.nrfm-admin-edit-form{margin-top:14px}
			.nrfm-admin-edit-form .nrfm-edit-field{margin:0 0 16px;max-width:520px}
			.nrfm-admin-edit-form .nrfm-edit-label{display:block;font-weight:600;font-size:13px;margin:0 0 5px;color:#1d2327}
			.nrfm-admin-edit-form .nrfm-edit-input{width:100%;padding:6px 10px;border:1px solid #8c8f94;border-radius:4px;font-size:14px;line-height:1.5;box-shadow:none}
			.nrfm-admin-edit-form .nrfm-edit-input:focus{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;outline:none}
			.nrfm-admin-edit-form textarea.nrfm-edit-input{min-height:80px}
			.nrfm-admin-edit-form .nrfm-edit-current{margin:0 0 6px;font-size:13px}
			.nrfm-admin-edit-form .nrfm-edit-file{margin-top:2px}
			.nrfm-admin-edit-form .nrfm-edit-hint{margin:4px 0 0;color:#646970;font-size:12px}
			.nrfm-admin-edit-form .nrfm-edit-readonly{margin:0;padding:6px 10px;background:#f0f0f1;border-radius:4px;font-size:13px;color:#3c434a}
		</style>';

		if ( ! $submission || ! $form || ! $form->exists() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Submission not found.', 'narrative-forms' ) . '</p></div></div>';
			return;
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) === 'POST' ) {
			$nonce = isset( $_POST['_nrfm_edit_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nrfm_edit_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'nrfm_edit_submission_' . $id ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Security check failed. Please try again.', 'narrative-forms' ) . '</p></div>';
			} else {
				$sub    = new NRFM_Submission();
				$result = $sub->update_submission( $id, $this->collect_input( $form ) );
				if ( $result !== false ) {
					$submission = $this->load_submission( $id );
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Submission updated.', 'narrative-forms' ) . '</p></div>';
				} else {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'The update could not be saved.', 'narrative-forms' ) . '</p></div>';
				}
			}
		}

		$back = add_query_arg(
			array(
				'page'      => 'nrfm-forms',
				'action'    => 'edit',
				'form'      => $submission['form_id'],
				'tab'       => 'submissions',
				'_wpnonce'  => wp_create_nonce( 'edit_form' ),
			),
			admin_url( 'admin.php' )
		);
		echo '<p><a href="' . esc_url( $back ) . '">&larr; ' . esc_html__( 'Back to submissions', 'narrative-forms' ) . '</a></p>';

		echo '<form method="post" enctype="multipart/form-data" class="nrfm-admin-edit-form" style="max-width:640px">';
		wp_nonce_field( 'nrfm_edit_submission_' . $id, '_nrfm_edit_nonce' );
		echo $this->render_editor_fields( $form, $submission['data'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with escaping inside.
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Update submission', 'narrative-forms' ) . '</button></p>';
		echo '</form>';
		echo '</div>';
	}

	/** URL of the admin edit screen for a submission (used by the submissions list). */
	public static function admin_edit_url( $submission_id ) {
		return add_query_arg(
			array(
				'page'       => self::ADMIN_SLUG,
				'submission' => (int) $submission_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/* ---------------------------------------------------------------------
	 * Shared helpers
	 * ------------------------------------------------------------------- */

	private function can_manage() {
		return function_exists( 'nrfm_can_manage' ) ? nrfm_can_manage() : current_user_can( 'manage_options' );
	}

	/** Load a submission's id/form_id/decoded data, or null. */
	private function load_submission( $id ) {
		global $wpdb;
		$id    = (int) $id;
		$table = function_exists( 'nrfm_get_valid_table' ) ? nrfm_get_valid_table( 'submissions' ) : '';
		if ( $id <= 0 || $table === '' ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, form_id, data FROM %i WHERE id = %d', $table, $id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$data = json_decode( (string) $row['data'], true );
		return array(
			'id'      => (int) $row['id'],
			'form_id' => (int) $row['form_id'],
			'data'    => is_array( $data ) ? $data : array(),
		);
	}

	/**
	 * Build the field map from POST, whitelisted to the form's own field names (mirrors
	 * the submit handler). Callers verify token/nonce before this runs.
	 */
	private function collect_input( $form ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- token/nonce verified by callers; sanitized in the helper.
		return nrfm_whitelist_field_input( $form, wp_unslash( $_POST ) );
	}

	/** Render labelled, pre-filled inputs for every field the form declares. */
	private function render_editor_fields( $form, $data ) {
		$names  = method_exists( $form, 'get_field_names' ) ? $form->get_field_names() : array();
		$labels = get_post_meta( $form->get_id(), '_nrfm_fields_labels', true );
		if ( ! is_array( $labels ) ) {
			$labels = array();
		}

		$out = '';
		foreach ( $names as $name ) {
			$label = ( isset( $labels[ $name ] ) && $labels[ $name ] !== '' )
				? $labels[ $name ]
				: ucwords( str_replace( array( '_', '-' ), ' ', $name ) );
			$value    = isset( $data[ $name ] ) ? $data[ $name ] : '';
			$field_id = 'nrfm-edit-' . preg_replace( '/[^a-z0-9_-]/i', '', $name );

			$out .= '<div class="nrfm-edit-field">';
			$out .= '<label class="nrfm-edit-label" for="' . esc_attr( $field_id ) . '">' . esc_html( $label ) . '</label>';

			if ( $this->is_file_value( $value ) ) {
				$out .= $this->render_file_field( $name, $field_id, $value );
			} elseif ( is_array( $value ) ) {
				// Multi-value fields are shown read-only in v1 and preserved unchanged on save.
				$out .= '<p class="nrfm-edit-readonly">' . esc_html( $this->flatten_scalar_list( $value ) ) . '</p>';
			} else {
				$sval = (string) $value;
				if ( strpos( $sval, "\n" ) !== false || strlen( $sval ) > 80 ) {
					$out .= '<textarea class="nrfm-edit-input" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" rows="4">' . esc_textarea( $sval ) . '</textarea>';
				} else {
					$out .= '<input class="nrfm-edit-input" type="text" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $sval ) . '">';
				}
			}
			$out .= '</div>';
		}
		return $out;
	}

	private function is_file_value( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}
		if ( isset( $value['url'] ) || isset( $value['path'] ) ) {
			return true;
		}
		return isset( $value[0] ) && is_array( $value[0] ) && ( isset( $value[0]['url'] ) || isset( $value[0]['path'] ) );
	}

	private function render_file_field( $name, $field_id, $value ) {
		$entries = ( isset( $value[0] ) && is_array( $value[0] ) ) ? $value : array( $value );
		$links   = array();
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['name'] ) ) {
				continue;
			}
			$url = isset( $entry['url'] ) ? $entry['url'] : '';
			$links[] = $url
				? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $entry['name'] ) . '</a>'
				: esc_html( $entry['name'] );
		}
		$current = $links ? implode( ', ', $links ) : esc_html__( 'No file', 'narrative-forms' );

		return '<p class="nrfm-edit-current">' . $current . '</p>'
			. '<input class="nrfm-edit-file" type="file" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '">'
			. '<p class="nrfm-edit-hint">' . esc_html__( 'Upload a new file to replace it, or leave empty to keep the current one.', 'narrative-forms' ) . '</p>';
	}

	private function flatten_scalar_list( $value ) {
		$parts = array();
		foreach ( (array) $value as $v ) {
			if ( is_scalar( $v ) ) {
				$parts[] = (string) $v;
			}
		}
		return implode( ', ', $parts );
	}

	private function notice_html( $text, $is_error ) {
		return '<p class="nrfm-edit-notice ' . ( $is_error ? 'is-error' : 'is-success' ) . '">' . esc_html( $text ) . '</p>';
	}

	private function message_html( $text ) {
		return '<h1 class="nrfm-edit-title">' . esc_html__( 'Manage your listing', 'narrative-forms' ) . '</h1><p class="nrfm-edit-sub">' . esc_html( $text ) . '</p>';
	}

	/** A minimal, self-contained page for the front-end edit link. */
	private function render_page( $title, $inner ) {
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title . ' — ' . get_bloginfo( 'name' ) ); ?></title>
<style>
:root{--nrfm-accent:#4f46e5}
*{box-sizing:border-box}
body{margin:0;padding:40px 16px;background:#f5f6f9;color:#1f2733;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased}
.nrfm-edit-wrap{max-width:560px;margin:0 auto;background:#fff;border:1px solid #e6e8ef;border-radius:18px;box-shadow:0 24px 60px -32px rgba(30,20,70,.4);padding:32px}
.nrfm-edit-title{margin:0 0 6px;font-size:24px;font-weight:700;letter-spacing:-.01em}
.nrfm-edit-sub{margin:0 0 22px;font-size:14px;line-height:1.5;color:#6b7180}
.nrfm-edit-field{margin:0 0 16px}
.nrfm-edit-label{display:block;margin:0 0 6px;font-size:13px;font-weight:600;color:#3a3f52}
.nrfm-edit-input{width:100%;padding:11px 13px;font-family:inherit;font-size:15px;color:#1f2733;background:#fff;border:1px solid #e2e4ee;border-radius:10px}
.nrfm-edit-input:focus{outline:none;border-color:var(--nrfm-accent);box-shadow:0 0 0 4px rgba(79,70,229,.14)}
textarea.nrfm-edit-input{min-height:96px;resize:vertical;line-height:1.5}
.nrfm-edit-file{width:100%;padding:9px 11px;font-size:14px;border:1px dashed #d6d4ee;border-radius:10px;background:#fff}
.nrfm-edit-current{margin:0 0 8px;font-size:14px}
.nrfm-edit-hint{margin:6px 0 0;font-size:12px;color:#8b90a0}
.nrfm-edit-readonly{margin:0;padding:11px 13px;font-size:14px;color:#4b5163;background:#f5f5fb;border-radius:10px}
.nrfm-edit-submit{width:100%;margin-top:6px;padding:14px 18px;border:0;border-radius:12px;cursor:pointer;background:var(--nrfm-accent);color:#fff;font-size:15px;font-weight:700;font-family:inherit}
.nrfm-edit-submit:hover{background:#4338ca}
.nrfm-edit-notice{margin:0 0 18px;padding:12px 14px;border-radius:10px;font-size:14px;font-weight:500}
.nrfm-edit-notice.is-success{background:#dcfce7;color:#166534}
.nrfm-edit-notice.is-error{background:#fee2e2;color:#991b1b}
</style>
</head>
<body>
<div class="nrfm-edit-wrap">
<?php echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped fragments above. ?>
</div>
</body>
</html>
		<?php
	}
}
