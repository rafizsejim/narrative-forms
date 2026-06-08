<?php
/**
 * Admin class - handles admin interface
 */
defined( 'ABSPATH' ) || exit;
class NRFM_Admin {

	private $current_tab = '';
	private $default_settings = array(
		'load_stylesheet'  => 0,
		'wrapper_tag'      => 'p',
		'honeypot_enabled' => 1,
	);

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'menu_icon_style' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		if ( ! function_exists( 'nrfm_is_pro_active' ) || ! nrfm_is_pro_active() ) {
			require_once NRFM_PLUGIN_DIR . 'includes/admin/class-nrfm-pro-upsell.php';
			new NRFM_Pro_Upsell();
		}
	}

	public function add_menu_pages() {
		// Icon is 'none' here on purpose: passing a data:image/svg+xml icon makes WordPress's
		// svg-painter.js repaint every fill in the SVG to one scheme colour, which would flatten
		// our two-tone badge (the "<NF>" would vanish into the box). We paint the icon ourselves
		// in menu_icon_style() via CSS, which the painter never touches.
		add_menu_page(
			__( 'Narrative Forms', 'narrative-forms' ),
			// Menu label is intentionally single-word so it never wraps to a second line in the
			// admin sidebar; the page title above keeps the readable spaced name.
			__( 'NarrativeForms', 'narrative-forms' ),
			'manage_options',
			'nrfm-forms',
			array( $this, 'render_forms_page' ),
			'none',
			30
		);

		add_submenu_page(
			'nrfm-forms',
			__( 'All Forms', 'narrative-forms' ),
			__( 'All Forms', 'narrative-forms' ),
			'manage_options',
			'nrfm-forms',
			array( $this, 'render_forms_page' )
		);

		add_submenu_page(
			'nrfm-forms',
			__( 'Add New Form', 'narrative-forms' ),
			__( 'Add New', 'narrative-forms' ),
			'manage_options',
			'nrfm-forms-new',
			array( $this, 'render_new_form_page' )
		);

		add_submenu_page(
			'nrfm-forms',
			__( 'Settings', 'narrative-forms' ),
			__( 'Settings', 'narrative-forms' ),
			'manage_options',
			'nrfm-forms-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Paint the brand menu icon ourselves via CSS (a two-tone "<NF>" badge from
	 * assets/img/menu-icon.svg). We can't pass it through add_menu_page() because WordPress's
	 * svg-painter.js would flatten the badge to a single colour. Attached to core's global
	 * 'admin-menu' stylesheet so it loads on every screen where the sidebar appears.
	 */
	public function menu_icon_style() {
		$icon = esc_url( NRFM_PLUGIN_URL . 'assets/img/menu-icon.svg' );
		$css  = '#adminmenu #toplevel_page_nrfm-forms .wp-menu-image{background:url(' . $icon . ') no-repeat center !important;background-size:20px 20px !important;}'
			. '#adminmenu #toplevel_page_nrfm-forms .wp-menu-image:before{content:"" !important;}';
		wp_add_inline_style( 'admin-menu', $css );
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'nrfm-forms' ) === false ) {
			return;
		}

		wp_enqueue_style( 'nrfm-admin', NRFM_PLUGIN_URL . 'assets/css/admin.css', array(), NRFM_VERSION );
		$get_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $get_action === 'edit' ) {
			wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
		}
		$settings     = function_exists( 'nrfm_get_settings' )
			? nrfm_get_settings()
			: get_option( 'nrfm_settings', $this->default_settings );
		$admin_js_ver = @filemtime( NRFM_PLUGIN_DIR . 'assets/js/admin.js' );
		if ( ! $admin_js_ver ) {
			$admin_js_ver = NRFM_VERSION; }
		wp_enqueue_script( 'nrfm-admin', NRFM_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), $admin_js_ver, true );
		$form_id       = isset( $_GET['form'] ) ? intval( wp_unslash( $_GET['form'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$preview_nonce = $form_id > 0 ? wp_create_nonce( 'nrfm_preview_' . $form_id ) : '';
		$preview_url   = $form_id > 0 ? add_query_arg(
			array(
				'nrfm_preview_form'   => $form_id,
				'nrfm_preview_nonce'  => $preview_nonce,
			),
			home_url( '/' )
		) : '';
		wp_localize_script(
			'nrfm-admin',
			'nrfm_admin',
			array(
				'preview_url' => $preview_url,
			)
		);
		wp_localize_script( 'nrfm-admin', 'nrfm_settings', $settings );
	}

	public function handle_actions() {
		$get_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		$get_form   = isset( $_GET['form'] ) ? intval( wp_unslash( $_GET['form'] ) ) : 0;
		if ( ! empty( $get_action ) && $get_action === 'export_csv' && ! empty( $get_form ) ) {
			if ( ! nrfm_can_manage() ) {
				return; }
			if ( check_admin_referer( 'export_csv' ) ) {
				$this->export_csv( $get_form );
			}
			return;
		}

		if ( ! empty( $get_action ) && ! empty( $get_form ) ) {
			$fid = $get_form;
			if ( ! nrfm_can_manage() ) {
				return; }
			switch ( $get_action ) {
				case 'trash_form':
					if ( check_admin_referer( 'trash_form' ) ) {
						$this->trash_form( $fid ); }
					return;
				case 'restore_form':
					if ( check_admin_referer( 'restore_form' ) ) {
						$this->restore_form( $fid ); }
					return;
				case 'delete_form_perm':
					if ( check_admin_referer( 'delete_form_perm' ) ) {
						$this->delete_form_permanently( $fid ); }
					return;
			}
		}

		if ( ! empty( $get_action ) && $get_action === 'delete_submission' && ! empty( $_GET['submission'] ) ) {
			if ( ! nrfm_can_manage() ) {
				return; }
			if ( check_admin_referer( 'delete_submission' ) ) {
				$this->delete_submission( intval( wp_unslash( $_GET['submission'] ) ), intval( wp_unslash( $_GET['form'] ) ) );
			}
			return;
		}

		if ( ! empty( $_POST['action'] ) || ! empty( $_POST['action2'] ) ) {
			$action_1    = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
			$action_2    = isset( $_POST['action2'] ) ? sanitize_text_field( wp_unslash( $_POST['action2'] ) ) : '';
			$bulk_action = ! empty( $action_1 ) && $action_1 !== '-1' ? $action_1 : $action_2;
			if ( in_array( $bulk_action, array( 'trash', 'restore', 'delete' ), true ) ) {
				if ( ! nrfm_can_manage() ) {
					return; }
				$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
				if ( ! wp_verify_nonce( $nonce, 'nrfm_admin_action' ) ) {
					return;
				}
				if ( $bulk_action === 'trash' ) {
					$this->bulk_trash_forms();
					return; }
				if ( $bulk_action === 'restore' ) {
					$this->bulk_restore_forms();
					return; }
				if ( $bulk_action === 'delete' ) {
					$this->bulk_delete_forms();
					return; }
			}
		}

		if ( empty( $_POST['nrfm_action'] ) ) {
			return;
		}

		if ( ! nrfm_can_manage() ) {
			return;
		}
		if ( ! check_admin_referer( 'nrfm_admin_action' ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['nrfm_action'] ) );

		switch ( $action ) {
			case 'create_form':
				$this->create_form();
				break;
			case 'save_form':
				$this->save_form();
				break;
			case 'save_settings':
				$this->save_settings();
				break;
			case 'bulk_delete_submissions':
				$this->bulk_delete_submissions();
				break;
			case 'bulk_delete_forms':
				$this->bulk_delete_forms();
				break;
		}
	}

	private function export_csv( $form_id ) {
		$form = new NRFM_Form( $form_id );
		if ( ! $form->exists() ) {
			wp_die( esc_html__( 'Form not found', 'narrative-forms' ) );
		}

		global $wpdb;
		$table_name = nrfm_get_valid_table( 'submissions' );
		if ( $table_name === '' ) {
			wp_die( esc_html__( 'Submissions table is unavailable.', 'narrative-forms' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$first = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT data FROM %i WHERE form_id = %d ORDER BY submitted_at DESC LIMIT 1',
				$table_name,
				$form_id
			)
		);
		if ( ! $first ) {
			wp_die( esc_html__( 'No submissions to export', 'narrative-forms' ) );
		}
		$delimiter = isset( $_GET['delimiter'] ) ? sanitize_text_field( wp_unslash( $_GET['delimiter'] ) ) : ','; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $delimiter === 'tab' ) {
			$delimiter = "\t"; } elseif ( $delimiter === 'semicolon' ) {
			$delimiter = ';'; } else {
				$delimiter = ','; }

			$filename = 'form-' . $form->get_slug() . '-submissions-' . gmdate( 'Y-m-d' ) . '.csv';
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=' . $filename );

			$output = fopen( 'php://output', 'w' );

			fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

			$headers   = array( 'Timestamp', 'IP Address' );
			$firstData = json_decode( $first->data ?? '', true );
			if ( is_array( $firstData ) ) {
				foreach ( array_keys( $firstData ) as $field ) {
					if ( strpos( $field, 'nrfm_' ) !== 0 ) {
						$headers[] = ucfirst( str_replace( '_', ' ', $field ) );
					}
				}
			}

			fputcsv( $output, $headers, $delimiter );

			$limit  = 500;
			$offset = 0;
			do {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT id, submitted_at, ip_address, user_agent, referer_url, data FROM %i WHERE form_id = %d ORDER BY submitted_at DESC LIMIT %d OFFSET %d',
						$table_name,
						$form_id,
						$limit,
						$offset
					)
				);
				if ( empty( $rows ) ) {
					break;
				}
				foreach ( $rows as $submission ) {
					$data = json_decode( $submission->data, true );
					if ( ! is_array( $data ) ) {
						$data = array(); }
					$row = array( $submission->submitted_at, $submission->ip_address );
					foreach ( $data as $field => $value ) {
						if ( strpos( $field, 'nrfm_' ) !== 0 ) {
							$row[] = is_array( $value ) ? implode( ', ', $value ) : $value;
						}
					}
					fputcsv( $output, $row, $delimiter );
				}
				$offset += $limit;
				if ( function_exists( 'fastcgi_finish_request' ) ) {
					@fastcgi_finish_request(); }
			} while ( count( $rows ) === $limit );

			fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			exit;
	}

	private function delete_submission( $submission_id, $form_id ) {
		global $wpdb;
		$table = nrfm_get_valid_table( 'submissions' );
		if ( $table === '' ) {
			return;
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'id' => $submission_id ), array( '%d' ) );
		/** Fires after a submission row is deleted, so add-ons can clean up related data. */
		do_action( 'nrfm_submission_deleted', (int) $submission_id );
		// Clear cached counts for this form
		nrfm_clear_submission_cache( $form_id );
		delete_transient( 'nrfm_cols_' . $form_id );
		// Redirect to the submissions tab of the same form (consistent behavior)
		$target = add_query_arg(
			array(
				'page'     => 'nrfm-forms',
				'action'   => 'edit',
				'form'     => $form_id,
				'tab'      => 'submissions',
				'_wpnonce' => wp_create_nonce( 'edit_form' ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $target );
		exit;
	}

	private function bulk_delete_submissions() {
		if ( ! nrfm_can_manage() ) {
			return; }
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bulk-submissions' ) ) {
			return;
		}
		$form_id = isset( $_POST['form_id'] ) ? intval( wp_unslash( $_POST['form_id'] ) ) : 0;
		$ids     = isset( $_POST['submission_ids'] ) && is_array( $_POST['submission_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['submission_ids'] ) ) : array();
		if ( empty( $ids ) || $form_id <= 0 ) {
			$target = add_query_arg(
				array(
					'page'     => 'nrfm-forms',
					'action'   => 'edit',
					'form'     => $form_id,
					'tab'      => 'submissions',
					'_wpnonce' => wp_create_nonce( 'edit_form' ),
				),
				admin_url( 'admin.php' )
			);
			wp_safe_redirect( $target );
			exit;
		}
		global $wpdb;
		$table = nrfm_get_valid_table( 'submissions' );
		if ( $table === '' ) {
			return;
		}
		foreach ( $ids as $sid ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $table, array( 'id' => $sid ), array( '%d' ) );
			/** Fires after a submission row is deleted, so add-ons can clean up related data. */
			do_action( 'nrfm_submission_deleted', (int) $sid );
		}
		// Clear cached counts for this form
		nrfm_clear_submission_cache( $form_id );
		delete_transient( 'nrfm_cols_' . $form_id );
		$target = add_query_arg(
			array(
				'page'     => 'nrfm-forms',
				'action'   => 'edit',
				'form'     => $form_id,
				'tab'      => 'submissions',
				'_wpnonce' => wp_create_nonce( 'edit_form' ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $target );
		exit;
	}

	private function create_form() {
		if ( empty( $_POST['form_title'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
			return;
		}

		$global_settings = function_exists( 'nrfm_get_settings' ) ? nrfm_get_settings( null, array( 'honeypot_enabled' => 1 ) ) : get_option( 'nrfm_settings', array( 'honeypot_enabled' => 1 ) );
		$form            = new NRFM_Form();

		$settings = NRFM_Form::default_settings();
		$settings['honeypot_enabled'] = ! empty( $global_settings['honeypot_enabled'] ) ? 1 : 0;

		$messages = NRFM_Form::default_messages();

		$form_id = $form->save(
			array(
				'title' => sanitize_text_field( wp_unslash( $_POST['form_title'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
			'content'   => $this->get_default_form_html(),
			'settings'  => $settings,
			'messages'  => $messages,
			)
		);

		if ( $form_id ) {
			$this->update_form_schema( $form_id, $this->get_default_form_html() );
			$url = add_query_arg(
				array(
					'page'     => 'nrfm-forms',
					'action'   => 'edit',
					'form'     => $form_id,
					'_wpnonce' => wp_create_nonce( 'edit_form' ),
				),
				admin_url( 'admin.php' )
			);
			wp_safe_redirect( $url );
			exit;
		}
	}

	private function save_form() {
		if ( empty( $_POST['form_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
			return;
		}

		$form_id = intval( wp_unslash( $_POST['form_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
		$form    = new NRFM_Form( $form_id );

		if ( ! $form->exists() ) {
			return;
		}

		// Prepare data (nonce checked upstream in handle_actions)
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
		$title = isset( $_POST['form_title'] ) ? sanitize_text_field( wp_unslash( $_POST['form_title'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
		$slug = isset( $_POST['form_slug'] ) ? sanitize_title( wp_unslash( $_POST['form_slug'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in handle_actions(); sanitized via wp_kses in NRFM_Form::save
		$content = isset( $_POST['form_content'] ) ? (string) wp_unslash( $_POST['form_content'] ) : '';
		// Nonce and capability are verified in handle_actions(); sanitize here for users without unfiltered_html.
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$content = wp_kses( $content, $form->get_allowed_html() );
		}
		$data    = array(
			'title'   => $title,
			'slug'    => $slug,
			'content' => $content, // Sanitized via wp_kses in NRFM_Form::save
		);

		// Settings
		if ( isset( $_POST['settings'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in handle_actions()
			$raw_settings     = wp_unslash( $_POST['settings'] );
			$data['settings'] = array(
				'save_submissions'   => ! empty( $raw_settings['save_submissions'] ) ? 1 : 0,
				'hide_after_success' => ! empty( $raw_settings['hide_after_success'] ) ? 1 : 0,
				'redirect_url'       => isset( $raw_settings['redirect_url'] ) ? esc_url_raw( $raw_settings['redirect_url'] ) : '',
				'honeypot_enabled'   => ! empty( $raw_settings['honeypot_enabled'] ) ? 1 : 0,
				'async_actions'      => 1, // always on
			);
			// Allow PRO to persist extra settings (e.g., require_login, submission_limit)
			$data['settings'] = apply_filters( 'nrfm_save_form_settings', $data['settings'] );
		}

		// Messages
		if ( isset( $_POST['messages'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in handle_actions()
			$raw_messages     = wp_unslash( $_POST['messages'] );
			$data['messages'] = array(
				'success'                => isset( $raw_messages['success'] ) ? sanitize_textarea_field( $raw_messages['success'] ) : '',
				'error'                  => isset( $raw_messages['error'] ) ? sanitize_textarea_field( $raw_messages['error'] ) : '',
				'invalid_email'          => isset( $raw_messages['invalid_email'] ) ? sanitize_textarea_field( $raw_messages['invalid_email'] ) : '',
				'required_field_missing' => isset( $raw_messages['required_field_missing'] ) ? sanitize_textarea_field( $raw_messages['required_field_missing'] ) : '',
				'file_too_large'         => isset( $raw_messages['file_too_large'] ) ? sanitize_textarea_field( $raw_messages['file_too_large'] ) : '',
				'max_files'              => isset( $raw_messages['max_files'] ) ? sanitize_textarea_field( $raw_messages['max_files'] ) : '',
			);
			// Allow PRO to persist extra messages
			$data['messages'] = apply_filters( 'nrfm_save_form_messages', $data['messages'] );
		}

		// Actions - handle multiple actions
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in handle_actions()
		$raw_actions = isset( $_POST['actions'] ) ? wp_unslash( $_POST['actions'] ) : array();
		if ( ! empty( $raw_actions ) && is_array( $raw_actions ) ) {
			$actions = array();

			// Email actions
			if ( ! empty( $raw_actions['email'] ) && is_array( $raw_actions['email'] ) ) {
				$actions['email'] = array();
				foreach ( $raw_actions['email'] as $index => $email_action ) {
					if ( ! empty( $email_action['to'] ) ) { // Only save if has recipient
						$actions['email'][ $index ] = array(
							'from'         => sanitize_text_field( $email_action['from'] ),
							'to'           => sanitize_text_field( $email_action['to'] ),
							'subject'      => sanitize_text_field( $email_action['subject'] ),
							'message'      => sanitize_textarea_field( $email_action['message'] ),
							'content_type' => sanitize_text_field( $email_action['content_type'] ),
							'headers'      => sanitize_textarea_field( $email_action['headers'] ?? '' ),
						);
					}
				}
			}

			// Webhook actions
			if ( ! empty( $raw_actions['webhook'] ) && is_array( $raw_actions['webhook'] ) ) {
				$actions['webhook'] = array();
				foreach ( $raw_actions['webhook'] as $index => $webhook_action ) {
					if ( ! empty( $webhook_action['url'] ) ) { // Only save if has URL
						$actions['webhook'][ $index ] = array(
							'url'    => esc_url_raw( $webhook_action['url'] ),
							'method' => sanitize_text_field( $webhook_action['method'] ),
							'format' => sanitize_text_field( $webhook_action['format'] ),
						);
					}
				}
			}

			$data['actions'] = $actions;
		}

		// PRO HOOK: Filter form settings/messages before save handled above
		$form->save( $data );
		// Persist field schema for submissions table headers
		$this->update_form_schema( $form_id, $data['content'] ?? '' );

		// PRO HOOK: After form save
		do_action( 'nrfm_save_form', $form_id, $data );
		// Cache invalidation already handled inside update_form_schema()

		// Redirect to avoid resubmission; preserve current tab, include nonce
		$current_tab = ! empty( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'fields'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation param
		// Build clean URL without HTML-encoding ampersands
		$url = add_query_arg(
			array(
				'page'     => 'nrfm-forms',
				'action'   => 'edit',
				'form'     => $form_id,
				'tab'      => $current_tab,
				'saved'    => '1',
				'_wpnonce' => wp_create_nonce( 'edit_form' ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	// Parse current form HTML and store a list of field names as schema
	private function update_form_schema( $form_id, $html ) {
		if ( ! is_string( $html ) || $html === '' ) {
			return;
		}
		$names      = array();
		$labels_map = array();
		$question_map = array();
		$choice_map   = array();
		$raw_fields   = array();
		// Build label map via for/id (reliable) and map controls with both id and name (any order) in a single pass
		$id_labels = array();
		if ( preg_match_all( '/<label[^>]*for=["\']([^"\']+)["\'][^>]*>(.*?)<\/label>/is', $html, $lm, PREG_SET_ORDER ) ) {
			foreach ( $lm as $m ) {
				$id  = trim( $m[1] );
				$lab = trim( wp_strip_all_tags( $m[2] ) );
				if ( $id !== '' && $lab !== '' ) {
					$id_labels[ $id ] = preg_replace( '/\s*\*+\s*$/', '', $lab ); }
			}
		}
		$controlPattern = '/<(?:input|textarea|select)[^>]*(?:name=["\']([^"\']+)["\'][^>]*id=["\']([^"\']+)["\']|id=["\']([^"\']+)["\'][^>]*name=["\']([^"\']+)["\'])[ ^>]*>/is';
		if ( preg_match_all( $controlPattern, $html, $cm, PREG_SET_ORDER ) ) {
			foreach ( $cm as $m ) {
				$nm = ! empty( $m[1] ) ? $m[1] : $m[4];
				$id = ! empty( $m[2] ) ? $m[2] : $m[3];
				$nm = preg_replace( '/\[\]$/', '', $nm );
				if ( $nm === '' || strpos( $nm, 'nrfm_' ) === 0 || strpos( $nm, '_' ) === 0 ) {
					continue;
				}
				$names[ $nm ] = true;
				if ( $id !== '' && isset( $id_labels[ $id ] ) && $id_labels[ $id ] !== '' ) {
					$labels_map[ $nm ] = $id_labels[ $id ]; }
			}
		}
		// 1) Pair preceding label(s) chunk with the next input/select/textarea by name; choose the last label in that chunk
		$pattern = '/((?:<label[^>]*>.*?<\/label>\s*)*)(?:<input[^>]*name=["\']([^"\']+)["\'][^>]*>|<textarea[^>]*name=["\']([^"\']+)["\'][^>]*>.*?<\/textarea>|<select[^>]*name=["\']([^"\']+)["\'][^>]*>.*?<\/select>)/is';
		if ( preg_match_all( $pattern, $html, $mm, PREG_SET_ORDER ) ) {
			foreach ( $mm as $match ) {
				$label = '';
				if ( ! empty( $match[1] ) ) {
					if ( preg_match_all( '/<label[^>]*>(.*?)<\/label>/is', $match[1], $lm ) && ! empty( $lm[1] ) ) {
						$label = trim( wp_strip_all_tags( end( $lm[1] ) ) );
					}
				}
				// Remove trailing asterisk left from required marker
				$label = preg_replace( '/\s*\*+\s*$/', '', $label );
				$nm    = ! empty( $match[2] ) ? $match[2] : ( ! empty( $match[3] ) ? $match[3] : ( isset( $match[4] ) ? $match[4] : '' ) );
				$nm    = preg_replace( '/\[\]$/', '', $nm );
				if ( $nm === '' || strpos( $nm, 'nrfm_' ) === 0 || strpos( $nm, '_' ) === 0 ) {
					continue;
				}
				// Do not overwrite labels already set by for/id mapping
				$names[ $nm ] = true;
				if ( $label !== '' && ! isset( $labels_map[ $nm ] ) ) {
					// Guard: if label looks like it contains option text or is overly long/wordy, keep only the first sentence/word chunk
					$label_simple = preg_replace( '/\s+/', ' ', $label );
					if ( strlen( $label_simple ) > 40 || preg_match( '/\b(yes|no|male|female|option|select)\b/i', $label_simple ) ) {
						$parts        = preg_split( '/[\.\:\?]/', $label_simple );
						$label_simple = trim( $parts[0] );
					}
					$labels_map[ $nm ] = $label_simple;
				}
			}
		}
		// 2) Fallback: collect any remaining field names from real form controls only
		if ( preg_match_all( '/<(?:input|textarea|select|button)[^>]*name=["\']([^"\']+)/i', $html, $m ) ) {
			foreach ( $m[1] as $nm ) {
				$nm = preg_replace( '/\[\]$/', '', $nm );
				if ( $nm === '' || strpos( $nm, 'nrfm_' ) === 0 || strpos( $nm, '_' ) === 0 ) {
					continue;
				}
				$names[ $nm ] = true;
			}
		}

		// Build question/choice maps for friendly admin display (optional, HTML-first)
		if ( class_exists( 'DOMDocument' ) ) {
			libxml_use_internal_errors( true );
			$dom = new DOMDocument();
			$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
			$xpath = new DOMXPath( $dom );

			// Mark raw display fields within any data-nrfm-display="raw" container
			$raw_nodes = $xpath->query( '//*[@data-nrfm-display="raw"]' );
			if ( $raw_nodes ) {
				foreach ( $raw_nodes as $raw_node ) {
					$inputs = $xpath->query( './/input[@name]|.//select[@name]|.//textarea[@name]', $raw_node );
					if ( $inputs ) {
						foreach ( $inputs as $input ) {
							$nm = trim( $input->getAttribute( 'name' ) );
							$nm = preg_replace( '/\[\]$/', '', $nm );
							if ( $nm === '' || strpos( $nm, 'nrfm_' ) === 0 || strpos( $nm, '_' ) === 0 ) {
								continue;
							}
							$raw_fields[ $nm ] = true;
						}
					}
				}
			}

			// Fieldset/legend question mapping
			$fieldsets = $xpath->query( '//fieldset' );
			if ( $fieldsets ) {
				foreach ( $fieldsets as $fieldset ) {
					$legend_nodes = $xpath->query( './/legend', $fieldset );
					if ( ! $legend_nodes || ! $legend_nodes->length ) {
						continue;
					}
					$legend = trim( preg_replace( '/\s+/', ' ', $legend_nodes->item( 0 )->textContent ) );
					if ( $legend === '' ) {
						continue;
					}
					$inputs = $xpath->query( './/input[@name]|.//select[@name]|.//textarea[@name]', $fieldset );
					if ( $inputs ) {
						foreach ( $inputs as $input ) {
							$nm = trim( $input->getAttribute( 'name' ) );
							$nm = preg_replace( '/\[\]$/', '', $nm );
							if ( $nm === '' || strpos( $nm, 'nrfm_' ) === 0 || strpos( $nm, '_' ) === 0 ) {
								continue;
							}
							if ( empty( $question_map[ $nm ] ) ) {
								$question_map[ $nm ] = $legend;
							}
						}
					}
				}
			}

			// data-group + .qz-qtitle mapping for quiz-like markup
			$groups = $xpath->query( '//*[@data-group]' );
			if ( $groups ) {
				foreach ( $groups as $group ) {
					$nm = trim( $group->getAttribute( 'data-group' ) );
					$nm = preg_replace( '/\[\]$/', '', $nm );
					if ( $nm === '' || strpos( $nm, 'nrfm_' ) === 0 || strpos( $nm, '_' ) === 0 ) {
						continue;
					}
					$question = '';
					$node     = $group;
					while ( $node ) {
						$title_nodes = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " qz-qtitle ")]', $node );
						if ( $title_nodes && $title_nodes->length ) {
							$question = trim( preg_replace( '/\s+/', ' ', $title_nodes->item( 0 )->textContent ) );
							break;
						}
						$node = $node->parentNode;
					}
					if ( $question !== '' && empty( $question_map[ $nm ] ) ) {
						$question_map[ $nm ] = $question;
					}
				}
			}

			// Choice labels for radios/checkboxes (label-wrapped inputs)
			$label_nodes = $xpath->query( '//label[.//input[@name]]' );
			if ( $label_nodes ) {
				foreach ( $label_nodes as $label ) {
					$input_nodes = $xpath->query( './/input[@name]', $label );
					if ( ! $input_nodes || ! $input_nodes->length ) {
						continue;
					}
					$input = $input_nodes->item( 0 );
					$nm    = trim( $input->getAttribute( 'name' ) );
					$nm    = preg_replace( '/\[\]$/', '', $nm );
					if ( $nm === '' || strpos( $nm, 'nrfm_' ) === 0 || strpos( $nm, '_' ) === 0 ) {
						continue;
					}
					$val = (string) $input->getAttribute( 'value' );
					if ( $val === '' ) {
						continue;
					}
					$text_nodes = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " qz-choice-text ")]', $label );
					$label_text = '';
					if ( $text_nodes && $text_nodes->length ) {
						$label_text = $text_nodes->item( 0 )->textContent;
					} else {
						$label_text = $label->textContent;
					}
					$label_text = trim( preg_replace( '/\s+/', ' ', $label_text ) );
					if ( $label_text !== '' ) {
						if ( ! isset( $choice_map[ $nm ] ) ) {
							$choice_map[ $nm ] = array();
						}
						$choice_map[ $nm ][ $val ] = $label_text;
					}
				}
			}

			// Select option labels
			$selects = $xpath->query( '//select[@name]' );
			if ( $selects ) {
				foreach ( $selects as $select ) {
					$nm = trim( $select->getAttribute( 'name' ) );
					$nm = preg_replace( '/\[\]$/', '', $nm );
					if ( $nm === '' || strpos( $nm, 'nrfm_' ) === 0 || strpos( $nm, '_' ) === 0 ) {
						continue;
					}
					$options = $xpath->query( './/option', $select );
					if ( $options ) {
						foreach ( $options as $opt ) {
							$val  = (string) $opt->getAttribute( 'value' );
							$text = trim( preg_replace( '/\s+/', ' ', $opt->textContent ) );
							if ( $val === '' || $text === '' ) {
								continue;
							}
							if ( ! isset( $choice_map[ $nm ] ) ) {
								$choice_map[ $nm ] = array();
							}
							$choice_map[ $nm ][ $val ] = $text;
						}
					}
				}
			}

			libxml_clear_errors();
		}
		$schema = array_keys( $names );
		update_post_meta( $form_id, '_nrfm_fields_schema', $schema );
		update_post_meta( $form_id, '_nrfm_fields_labels', $labels_map );
		update_post_meta( $form_id, '_nrfm_fields_question_map', $question_map );
		update_post_meta( $form_id, '_nrfm_fields_choice_map', $choice_map );
		update_post_meta( $form_id, '_nrfm_fields_raw_display', array_keys( $raw_fields ) );
		delete_transient( 'nrfm_cols_' . $form_id );
	}

	private function trash_form( $form_id ) {
		wp_trash_post( $form_id );
		wp_safe_redirect( admin_url( 'admin.php?page=nrfm-forms' ) );
		exit;
	}

	private function restore_form( $form_id ) {
		wp_untrash_post( $form_id );
		// Ensure it returns to publish (fallback if original status meta missing)
		$status = get_post_status( $form_id );
		if ( $status && $status !== 'publish' ) {
			wp_update_post(
				array(
					'ID'          => $form_id,
					'post_status' => 'publish',
				)
			);
		}
		wp_safe_redirect( admin_url( 'admin.php?page=nrfm-forms&status=trash' ) );
		exit;
	}

	private function delete_form_permanently( $form_id ) {
		wp_delete_post( $form_id, true );
		$url = add_query_arg(
			array(
				'page'     => 'nrfm-forms',
				'status'   => 'trash',
				'deleted'  => '1',
				'_wpnonce' => wp_create_nonce( 'nrfm_deleted' ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	private function bulk_delete_forms() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return; }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
		$ids = isset( $_POST['form'] ) && is_array( $_POST['form'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['form'] ) ) : array();
		if ( ! empty( $ids ) ) {
			foreach ( $ids as $id ) {
				wp_delete_post( $id, true );
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=nrfm-forms&deleted=1' ) );
		exit;
	}

	private function bulk_trash_forms() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return; }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
		$ids = isset( $_POST['form'] ) && is_array( $_POST['form'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['form'] ) ) : array();
		if ( ! empty( $ids ) ) {
			foreach ( $ids as $id ) {
				wp_trash_post( $id ); }
		}
		wp_safe_redirect( admin_url( 'admin.php?page=nrfm-forms' ) );
		exit;
	}

	private function bulk_restore_forms() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return; }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
		$ids = isset( $_POST['form'] ) && is_array( $_POST['form'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['form'] ) ) : array();
		if ( ! empty( $ids ) ) {
			foreach ( $ids as $id ) {
				wp_untrash_post( $id );
				$status = get_post_status( $id );
				if ( $status && $status !== 'publish' ) {
					wp_update_post(
						array(
							'ID'          => $id,
							'post_status' => 'publish',
						)
					);
				}
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=nrfm-forms&status=trash' ) );
		exit;
	}

	private function save_settings() {
		// Persist all settings shown on the Settings page
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
		$settings = array( // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
			'load_stylesheet'            => ! empty( $_POST['load_stylesheet'] ) ? 1 : 0,
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
			'wrapper_tag'                => isset( $_POST['wrapper_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['wrapper_tag'] ) ) : 'p',
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
			'honeypot_enabled'           => ! empty( $_POST['honeypot_enabled'] ) ? 1 : 0,
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
			'purge_on_uninstall'         => ! empty( $_POST['purge_on_uninstall'] ) ? 1 : 0,
			// Anti-spam
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
			'spam_time_trap_seconds'     => isset( $_POST['spam_time_trap_seconds'] ) ? max( 0, intval( wp_unslash( $_POST['spam_time_trap_seconds'] ) ) ) : 3,
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
			'spam_same_origin'           => ! empty( $_POST['spam_same_origin'] ) ? 1 : 0,
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
			'spam_max_links'             => isset( $_POST['spam_max_links'] ) ? max( 0, intval( wp_unslash( $_POST['spam_max_links'] ) ) ) : 3,
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
			'spam_rate_limit_count'      => isset( $_POST['spam_rate_limit_count'] ) ? max( 0, intval( wp_unslash( $_POST['spam_rate_limit_count'] ) ) ) : 5,
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions()
			'spam_rate_limit_window_min' => isset( $_POST['spam_rate_limit_window_min'] ) ? max( 1, intval( wp_unslash( $_POST['spam_rate_limit_window_min'] ) ) ) : 10,
		);

		// PRO HOOK: Filter settings before saving
		$settings = apply_filters( 'nrfm_save_global_settings', $settings );

		update_option( 'nrfm_settings', $settings );

		$url = add_query_arg(
			array(
				'page'     => 'nrfm-forms-settings',
				'saved'    => '1',
				'_wpnonce' => wp_create_nonce( 'nrfm_saved' ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public function render_forms_page() {
		// Check if editing a form (be tolerant: presence of a valid form id implies edit view)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameters
		$nav_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameters
		$nav_form = isset( $_GET['form'] ) ? intval( wp_unslash( $_GET['form'] ) ) : 0;
		if ( ( $nav_action === 'edit' && $nav_form ) || $nav_form ) {
			$this->render_edit_form_page();
			return;
		}

		// List forms (paged for performance)
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameters
		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'any';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameters
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameters
		$paged = isset( $_GET['paged'] ) ? max( 1, intval( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$per_page = 20;

		$query_args = array(
			'post_type'      => 'nrfm_form',
			'post_status'    => ( $status === 'trash' ) ? 'trash' : 'any',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'title',
			'order'          => 'ASC',
			's'              => $search,
		);

		$nrfm_query = new WP_Query( $query_args );
		$nrfm_forms = $nrfm_query->posts;
		$nrfm_total = (int) $nrfm_query->found_posts;
		$nrfm_pages = (int) $nrfm_query->max_num_pages;

		include NRFM_PLUGIN_DIR . 'includes/views/form-list.php';
	}

	public function render_edit_form_page() {
		// Verify capability and nonce for edit screen navigation
		if ( ! nrfm_can_manage() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'narrative-forms' ) );
		}
		$nonce           = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		$is_view_request = ! empty( $_GET['nrfm_view'] );
		// Always require edit_form nonce for this page
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'edit_form' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'narrative-forms' ) );
		}
		$form_id = isset( $_GET['form'] ) ? intval( wp_unslash( $_GET['form'] ) ) : 0;
		$form    = new NRFM_Form( $form_id );

		if ( ! $form->exists() ) {
			wp_die( esc_html__( 'Form not found', 'narrative-forms' ) );
		}

		// Get current tab
		$this->current_tab = ! empty( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'fields';

		// Get submissions when needed
		$submissions = array();
		if ( $this->current_tab === 'submissions' ) {
			if ( ! empty( $_GET['nrfm_view'] ) ) {
				$view_submission_id = isset( $_GET['nrfm_view'] ) ? intval( wp_unslash( $_GET['nrfm_view'] ) ) : 0;
				$nonce_view         = isset( $_GET['_wpnonce_view'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce_view'] ) ) : '';
				if ( ! $nonce_view || ! wp_verify_nonce( $nonce_view, 'nrfm_view_' . $view_submission_id ) ) {
					wp_die( esc_html__( 'Security check failed.', 'narrative-forms' ) );
				}
				$one = $this->get_submission( $view_submission_id );
				if ( $one ) {
					$submissions = array( $one ); }
			} else {
				// Light fetch so UI elements (like CSV export) can detect whether there are any submissions
				$submissions = $this->get_form_submissions_light( $form_id );
			}
		}

		include NRFM_PLUGIN_DIR . 'includes/views/form-edit.php';
	}

	public function render_new_form_page() {
		include NRFM_PLUGIN_DIR . 'includes/views/form-new.php';
	}

	public function render_settings_page() {
		$settings = get_option( 'nrfm_settings', $this->default_settings );

		include NRFM_PLUGIN_DIR . 'includes/views/settings.php';
	}

	// Lightweight fetch for list views (id, submitted_at, data only)
	private function get_form_submissions_light( $form_id ) {
		global $wpdb;
		$table_name = nrfm_get_valid_table( 'submissions' );
		if ( $table_name === '' ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, submitted_at, data FROM %i WHERE form_id = %d ORDER BY submitted_at DESC LIMIT 100',
				$table_name,
				$form_id
			)
		);
		foreach ( $results as $r ) {
			$r->data = json_decode( $r->data, true );
		}
		return $results;
	}

	// Single submission with full meta
	private function get_submission( $submission_id ) {
		global $wpdb;
		$table_name = nrfm_get_valid_table( 'submissions' );
		if ( $table_name === '' ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, submitted_at, ip_address, user_agent, referer_url, data FROM %i WHERE id = %d',
				$table_name,
				$submission_id
			)
		);
		if ( $row ) {
			$row->data = json_decode( $row->data, true );
		}
		return $row;
	}

	private function get_default_form_html() {
		return '<p>
    <label for="name">Your Name <span class="nrfm-required" aria-hidden="true">*</span></label>
    <input type="text" id="name" name="name" required>
</p>

<p>
    <label for="email">Your Email <span class="nrfm-required" aria-hidden="true">*</span></label>
    <input type="email" id="email" name="email" required>
</p>

<p>
    <label for="message">Message <span class="nrfm-required" aria-hidden="true">*</span></label>
    <textarea id="message" name="message" rows="5" required></textarea>
</p>

<p>
    <button type="submit">Submit</button>
</p>';
	}
}
