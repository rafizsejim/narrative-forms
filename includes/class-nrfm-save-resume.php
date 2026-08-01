<?php
defined( 'ABSPATH' ) || exit;

/**
 * Save & Resume: let visitors save form progress and continue later via a link.
 *
 * The `nrfm_resume_drafts` table is created by core's schema versioning (see
 * nrfm_activate / nrfm_maybe_upgrade_schema, which call self::create_table()).
 */
class NRFM_Save_Resume {
	const TOKEN_QUERY_KEY = 'nrfm_resume';
	const CLEANUP_HOOK = 'nrfm_resume_cleanup';
	const DEFAULT_EXPIRY_DAYS = 30;

	public function __construct() {
		add_filter( 'nrfm_form_default_settings', array( $this, 'add_default_settings' ) );
		add_filter( 'nrfm_save_form_settings', array( $this, 'save_form_settings' ) );
		add_action( 'nrfm_form_settings_data_notifications', array( $this, 'render_form_setting' ) );
		add_filter( 'nrfm_form_html', array( $this, 'inject_save_button' ), 10, 2 );
		add_filter( 'nrfm_form_render', array( $this, 'maybe_attach_resume_payload' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'wp_ajax_nrfm_save_resume', array( $this, 'handle_save_resume' ) );
		add_action( 'wp_ajax_nopriv_nrfm_save_resume', array( $this, 'handle_save_resume' ) );
		add_action( 'nrfm_form_submitted', array( $this, 'cleanup_after_submit' ), 10, 2 );
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup_expired_drafts' ) );
	}

	/**
	 * Create the drafts table. Idempotent (dbDelta), called by core's activation
	 * and schema-upgrade routines.
	 */
	public static function create_table() {
		$table = nrfm_get_valid_table( 'resume_drafts' );
		if ( $table === '' ) {
			return;
		}
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL,
			token_hash char(64) NOT NULL,
			data longtext NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			expires_at datetime DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY token_hash (token_hash),
			KEY form_id (form_id),
			KEY expires_at (expires_at)
		) {$charset_collate};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Schedule the daily expired-draft cleanup. Called on core activation and
	 * whenever the schema upgrades (safe: no-op if already scheduled).
	 */
	public static function schedule_cleanup() {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/** Clear the cleanup cron. Called on core deactivation. */
	public static function unschedule_cleanup() {
		$timestamp = wp_next_scheduled( self::CLEANUP_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CLEANUP_HOOK );
		}
	}

	public function add_default_settings( $settings ) {
		$settings['save_resume_enabled'] = 0;
		$settings['save_resume_button_label'] = __( 'Save & Resume Later', 'narrative-forms' );
		$settings['save_resume_saved_text'] = __( 'Draft saved. Use this link to resume:', 'narrative-forms' );
		return $settings;
	}

	public function save_form_settings( $settings ) {
		$raw = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
			? wp_unslash( $_POST['settings'] )
			: array();
		$settings['save_resume_enabled'] = ! empty( $raw['save_resume_enabled'] ) ? 1 : 0;
		$settings['save_resume_button_label'] = isset( $raw['save_resume_button_label'] )
			? sanitize_text_field( $raw['save_resume_button_label'] )
			: $settings['save_resume_button_label'];
		$settings['save_resume_saved_text'] = isset( $raw['save_resume_saved_text'] )
			? sanitize_text_field( $raw['save_resume_saved_text'] )
			: $settings['save_resume_saved_text'];
		return $settings;
	}

	public function render_form_setting( $form ) {
		if ( ! ( $form instanceof NRFM_Form ) ) {
			return;
		}
		$settings = $form->get_settings();
		$enabled  = ! empty( $settings['save_resume_enabled'] );
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Save & Resume', 'narrative-forms' ); ?></th>
			<td>
				<label>
					<input type="checkbox" id="nrfm-save-resume-enabled" name="settings[save_resume_enabled]" value="1" <?php checked( $enabled, true ); ?> />
					<?php esc_html_e( 'Allow visitors to save progress and continue later.', 'narrative-forms' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'A unique resume link is generated when users click the Save & Resume action on the form.', 'narrative-forms' ); ?></p>
				<div class="nrfm-setting-children<?php echo $enabled ? '' : ' nrfm-collapsed'; ?>" data-parent-input="#nrfm-save-resume-enabled">
					<p>
						<label for="nrfm-save-resume-label"><?php esc_html_e( 'Save Button Label', 'narrative-forms' ); ?></label><br>
						<input type="text" id="nrfm-save-resume-label" name="settings[save_resume_button_label]" class="regular-text" value="<?php echo esc_attr( $settings['save_resume_button_label'] ); ?>">
					</p>
					<p>
						<label for="nrfm-save-resume-message"><?php esc_html_e( 'Saved Message Text', 'narrative-forms' ); ?></label><br>
						<input type="text" id="nrfm-save-resume-message" name="settings[save_resume_saved_text]" class="large-text" value="<?php echo esc_attr( $settings['save_resume_saved_text'] ); ?>">
					</p>
				</div>
			</td>
		</tr>
		<?php
	}

	public function inject_save_button( $form_html, $form ) {
		if ( ! ( $form instanceof NRFM_Form ) ) {
			return $form_html;
		}
		$settings = $form->get_settings();
		if ( empty( $settings['save_resume_enabled'] ) ) {
			return $form_html;
		}
		if ( strpos( $form_html, 'nrfm-save-resume' ) !== false ) {
			return $form_html;
		}
		$label = isset( $settings['save_resume_button_label'] ) && $settings['save_resume_button_label'] !== ''
			? $settings['save_resume_button_label']
			: __( 'Save & Resume Later', 'narrative-forms' );
		$saved_text = isset( $settings['save_resume_saved_text'] ) && $settings['save_resume_saved_text'] !== ''
			? $settings['save_resume_saved_text']
			: __( 'Draft saved. Use this link to resume:', 'narrative-forms' );
		$insert = '<span class="nrfm-save-resume-actions">'
			. '<a href="#" role="button" class="nrfm-save-resume" data-nrfm-save-resume="1">'
			. esc_html( $label )
			. '</a>'
			. '</span>'
			. '<div class="nrfm-save-resume-message" data-saved-text="' . esc_attr( $saved_text ) . '" aria-live="polite"></div>'
			. '<input type="hidden" name="nrfm_resume_token" value="" />';
		$insert = apply_filters( 'nrfm_save_resume_markup', $insert, $form, $settings );
		// Inject before the LAST </form> only. A form whose template mistakenly includes
		// its own </form> would otherwise get the link added before every one of them.
		$pos = strrpos( $form_html, '</form>' );
		if ( $pos !== false ) {
			return substr( $form_html, 0, $pos ) . $insert . substr( $form_html, $pos );
		}
		return $form_html . $insert;
	}

	public function maybe_attach_resume_payload( $rendered, $form ) {
		if ( ! ( $form instanceof NRFM_Form ) ) {
			return $rendered;
		}
		if ( empty( $_GET[ self::TOKEN_QUERY_KEY ] ) ) {
			return $rendered;
		}
		$settings = $form->get_settings();
		if ( empty( $settings['save_resume_enabled'] ) ) {
			return $rendered;
		}
		$token = sanitize_text_field( wp_unslash( $_GET[ self::TOKEN_QUERY_KEY ] ) );
		if ( $token === '' ) {
			return $rendered;
		}
		$draft = $this->get_draft_by_token( $form->get_id(), $token );
		if ( ! $draft ) {
			return $rendered;
		}
		$payload = array(
			'formId' => $form->get_id(),
			'token'  => $token,
			'fields' => $draft['data'],
		);
		$script = 'window.nrfmResumeDrafts = window.nrfmResumeDrafts || {};'
			. 'window.nrfmResumeDrafts[' . intval( $form->get_id() ) . '] = '
			. wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';';
		// Attach to the registered handle (matches the conditional-logic pattern) rather than
		// echoing a raw <script>. The handle is enqueued in the footer when a form renders.
		wp_add_inline_script( 'nrfm-save-resume', $script, 'before' );
		return $rendered;
	}

	public function enqueue_frontend() {
		global $post;
		$is_preview = ! empty( $_GET['nrfm_preview_form'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $is_preview && ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'nrfm_form' ) ) ) {
			return;
		}
		$load_main_css = nrfm_get_settings( 'load_stylesheet', 0 );
		if ( $load_main_css ) {
			wp_enqueue_style(
				'nrfm-save-resume',
				NRFM_PLUGIN_URL . 'assets/css/save-resume.css',
				array(),
				NRFM_VERSION
			);
		}
		wp_register_script(
			'nrfm-save-resume',
			NRFM_PLUGIN_URL . 'assets/js/save-resume.js',
			array(),
			NRFM_VERSION,
			true
		);
		wp_localize_script(
			'nrfm-save-resume',
			'nrfmSaveResume',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'ajaxNonce'   => wp_create_nonce( 'nrfm_ajax_nonce' ),
				'queryKey'    => self::TOKEN_QUERY_KEY,
				'messageSaved' => __( 'Draft saved. Use this link to resume:', 'narrative-forms' ),
				'messageError' => __( 'Unable to save your progress. Please try again.', 'narrative-forms' ),
				'messageLoaded' => __( 'Draft loaded.', 'narrative-forms' ),
			)
		);
		add_action(
			'wp_footer',
			function() {
				if ( ! empty( $GLOBALS['nrfm_form_rendered'] ) ) {
					wp_enqueue_script( 'nrfm-save-resume' );
				}
			}
		);
	}

	public function handle_save_resume() {
		if ( ! check_ajax_referer( 'nrfm_ajax_nonce', 'nrfm_ajax_nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'narrative-forms' ) ) );
		}
		$form_id = isset( $_POST['nrfm_form_id'] ) ? intval( $_POST['nrfm_form_id'] ) : 0;
		if ( $form_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Form ID missing.', 'narrative-forms' ) ) );
		}
		$form = new NRFM_Form( $form_id );
		if ( ! $form->exists() ) {
			wp_send_json_error( array( 'message' => __( 'Form not found.', 'narrative-forms' ) ) );
		}
		$settings = $form->get_settings();
		if ( empty( $settings['save_resume_enabled'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Save & Resume is disabled for this form.', 'narrative-forms' ) ) );
		}

		$allowed_names = method_exists( $form, 'get_field_names' ) ? array_fill_keys( $form->get_field_names(), true ) : array();
		$data = array();
		foreach ( $allowed_names as $name => $_x ) {
			if ( ! isset( $_POST[ $name ] ) ) {
				continue;
			}
			$raw = wp_unslash( $_POST[ $name ] );
			$data[ $name ] = is_array( $raw ) ? map_deep( $raw, 'sanitize_text_field' ) : sanitize_text_field( $raw );
		}

		$token = isset( $_POST['nrfm_resume_token'] ) ? sanitize_text_field( wp_unslash( $_POST['nrfm_resume_token'] ) ) : '';
		$result = $this->save_draft( $form_id, $data, $token );
		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Unable to save draft.', 'narrative-forms' ) ) );
		}

		$resume_url = $this->build_resume_url( $result['token'] );
		wp_send_json_success(
			array(
				'resume_url' => $resume_url,
				'token'      => $result['token'],
			)
		);
	}

	public function cleanup_after_submit( $form_id, $data ) {
		$token = isset( $_POST['nrfm_resume_token'] ) ? sanitize_text_field( wp_unslash( $_POST['nrfm_resume_token'] ) ) : '';
		if ( $token === '' ) {
			return;
		}
		$this->delete_draft_by_token( $form_id, $token );
	}

	public function cleanup_expired_drafts() {
		$table = $this->get_table_name();
		if ( $table === '' ) {
			return;
		}
		global $wpdb;
		$now = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE expires_at IS NOT NULL AND expires_at < %s",
				$now
			)
		);
	}

	private function save_draft( $form_id, $data, $existing_token = '' ) {
		$table = $this->get_table_name();
		if ( $table === '' ) {
			return false;
		}
		global $wpdb;
		$now = current_time( 'mysql' );
		$expires = gmdate( 'Y-m-d H:i:s', time() + ( self::DEFAULT_EXPIRY_DAYS * DAY_IN_SECONDS ) );
		$token = $existing_token !== '' ? $existing_token : bin2hex( random_bytes( 16 ) );
		$token_hash = hash( 'sha256', $token );
		$payload = wp_json_encode( $data );
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( $existing_token !== '' ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$updated = $wpdb->update(
				$table,
				array(
					'data'       => $payload,
					'updated_at' => $now,
					'expires_at' => $expires,
					'ip_address' => $ip,
				),
				array(
					'form_id'    => $form_id,
					'token_hash' => $token_hash,
				),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d', '%s' )
			);
			if ( $updated !== false ) {
				return array( 'token' => $token );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$table,
			array(
				'form_id'    => $form_id,
				'token_hash' => $token_hash,
				'data'       => $payload,
				'created_at' => $now,
				'updated_at' => $now,
				'expires_at' => $expires,
				'ip_address' => $ip,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( $inserted ) {
			return array( 'token' => $token );
		}
		return false;
	}

	private function get_draft_by_token( $form_id, $token ) {
		$table = $this->get_table_name();
		if ( $table === '' ) {
			return false;
		}
		global $wpdb;
		$token_hash = hash( 'sha256', $token );
		$now = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT data, expires_at FROM {$table} WHERE form_id = %d AND token_hash = %s",
				$form_id,
				$token_hash
			),
			ARRAY_A
		);
		if ( ! $row ) {
			return false;
		}
		if ( ! empty( $row['expires_at'] ) && $row['expires_at'] < $now ) {
			$this->delete_draft_by_token( $form_id, $token );
			return false;
		}
		$decoded = json_decode( $row['data'], true );
		return array(
			'data' => is_array( $decoded ) ? $decoded : array(),
		);
	}

	private function delete_draft_by_token( $form_id, $token ) {
		$table = $this->get_table_name();
		if ( $table === '' ) {
			return;
		}
		global $wpdb;
		$token_hash = hash( 'sha256', $token );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$table,
			array(
				'form_id'    => $form_id,
				'token_hash' => $token_hash,
			),
			array( '%d', '%s' )
		);
	}

	private function build_resume_url( $token ) {
		$base = isset( $_POST['nrfm_resume_base'] ) ? esc_url_raw( wp_unslash( $_POST['nrfm_resume_base'] ) ) : '';
		if ( $base === '' ) {
			$base = wp_get_referer();
		}
		if ( $base === '' ) {
			$base = home_url( '/' );
		}
		$base_host = wp_parse_url( $base, PHP_URL_HOST );
		$site_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( $base_host && $site_host && ! hash_equals( $base_host, $site_host ) ) {
			$base = home_url( '/' );
		}
		$base = remove_query_arg( self::TOKEN_QUERY_KEY, $base );
		return add_query_arg( self::TOKEN_QUERY_KEY, rawurlencode( $token ), $base );
	}

	private function get_table_name() {
		return nrfm_get_valid_table( 'resume_drafts' );
	}
}
