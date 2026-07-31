<?php
defined( 'ABSPATH' ) || exit;

class NRFM_Webhooks {
	const RETRY_HOOK = 'nrfm_webhook_retry';
	const CLEANUP_HOOK = 'nrfm_webhook_logs_cleanup';
	const MAX_RETRIES = 3;
	const RETRY_DELAYS = array( 60, 300, 900 ); // 1min, 5min, 15min
	const LOG_RETENTION_DAYS = 30;


	public function __construct() {
		add_filter( 'nrfm_webhook_request_args', array( $this, 'inject_form_id' ), 10, 3 );
		add_action( 'nrfm_webhook_response', array( $this, 'log_success' ), 10, 4 );
		add_action( 'nrfm_webhook_error', array( $this, 'log_and_queue_retry' ), 10, 4 );
		add_action( 'nrfm_save_form', array( $this, 'store_form_id_in_actions' ), 15, 2 );
		add_action( self::RETRY_HOOK, array( $this, 'process_retry' ) );
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup_old_logs' ) );
		add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_footer', array( $this, 'render_inline_logs' ) );
	}

	/**
	 * Inject form_id into webhook args for logging
	 * Gets form_id from POST data since webhooks fire during form submission
	 */
	public function inject_form_id( $args, $payload, $webhook_config ) {
		// Get form_id from POST data (available during form submission)
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only, nonce verified by core
		$form_id = isset( $_POST['nrfm_form_id'] ) ? intval( wp_unslash( $_POST['nrfm_form_id'] ) ) : 0;
		
		$args['_nrfm_form_id'] = $form_id;
		return $args;
	}

	/**
	 * Persist form_id in webhook actions so logging works in async actions.
	 */
	public function store_form_id_in_actions( $form_id, $data ) {
		$actions = get_post_meta( $form_id, '_nrfm_actions', true );
		if ( empty( $actions['webhook'] ) || ! is_array( $actions['webhook'] ) ) {
			return;
		}
		$updated = false;
		foreach ( $actions['webhook'] as $index => $action ) {
			if ( ! isset( $action['form_id'] ) || (int) $action['form_id'] !== (int) $form_id ) {
				$actions['webhook'][ $index ]['form_id'] = (int) $form_id;
				$updated = true;
			}
		}
		if ( $updated ) {
			update_post_meta( $form_id, '_nrfm_actions', $actions );
		}
	}

	/**
	 * Create the webhook logs table. Idempotent (dbDelta); called by core's
	 * activation and schema-upgrade routines.
	 */
	public static function create_table() {
		global $wpdb;
		$table = nrfm_get_valid_table( 'webhook_logs' );
		if ( $table === '' ) {
			return;
		}
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL,
			webhook_url varchar(2048) NOT NULL,
			request_method varchar(10) NOT NULL DEFAULT 'POST',
			request_body longtext,
			response_code smallint(6) DEFAULT NULL,
			response_body longtext,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			next_retry_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY form_id (form_id),
			KEY status (status),
			KEY next_retry_at (next_retry_at),
			KEY created_at (created_at)
		) {$charset_collate};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/** Schedule the daily log-cleanup cron. Safe: no-op if already scheduled. */
	public static function schedule_cleanup() {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/** Clear the cleanup cron and any queued retries. Called on core deactivation. */
	public static function unschedule_cleanup() {
		$timestamp = wp_next_scheduled( self::CLEANUP_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CLEANUP_HOOK );
		}
		wp_clear_scheduled_hook( self::RETRY_HOOK );
	}

	public function log_success( $response, $url, $args, $webhook_config ) {
		$form_id = $this->extract_form_id( $webhook_config, $args );
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$this->insert_log( array(
			'form_id'        => $form_id,
			'webhook_url'    => $url,
			'request_method' => isset( $args['method'] ) ? $args['method'] : 'POST',
			'request_body'   => isset( $args['body'] ) ? ( is_array( $args['body'] ) ? wp_json_encode( $args['body'] ) : $args['body'] ) : '',
			'response_code'  => $code,
			'response_body'  => mb_substr( $body, 0, 10000 ),
			'status'         => ( $code >= 200 && $code < 300 ) ? 'success' : 'failed',
			'attempts'       => 1,
		) );
	}

	public function log_and_queue_retry( $error, $url, $args, $webhook_config ) {
		$form_id = $this->extract_form_id( $webhook_config, $args );
		$error_message = is_wp_error( $error ) ? $error->get_error_message() : 'Unknown error';
		$log_id = $this->insert_log( array(
			'form_id'        => $form_id,
			'webhook_url'    => $url,
			'request_method' => isset( $args['method'] ) ? $args['method'] : 'POST',
			'request_body'   => isset( $args['body'] ) ? ( is_array( $args['body'] ) ? wp_json_encode( $args['body'] ) : $args['body'] ) : '',
			'response_code'  => 0,
			'response_body'  => $error_message,
			'status'         => 'failed',
			'attempts'       => 1,
		) );
		if ( $log_id ) {
			$this->schedule_retry( $log_id, 1 );
		}
	}

	private function schedule_retry( $log_id, $attempt ) {
		if ( $attempt >= self::MAX_RETRIES ) {
			return;
		}
		$delay = isset( self::RETRY_DELAYS[ $attempt ] ) ? self::RETRY_DELAYS[ $attempt ] : 900;
		$next_retry = time() + $delay;
		$this->update_log( $log_id, array(
			'status'        => 'pending_retry',
			'next_retry_at' => gmdate( 'Y-m-d H:i:s', $next_retry ),
		) );
		wp_schedule_single_event( $next_retry, self::RETRY_HOOK, array( $log_id ) );
	}

	public function process_retry( $log_id ) {
		$log = $this->get_log( $log_id );
		if ( ! $log || $log['status'] === 'success' ) {
			return;
		}
		if ( $log['attempts'] >= self::MAX_RETRIES ) {
			$this->update_log( $log_id, array( 'status' => 'failed' ) );
			return;
		}
		$url = $log['webhook_url'];
		$method = $log['request_method'];
		$body = $log['request_body'];
		$args = array(
			'method'      => $method,
			'timeout'     => 15,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking'    => true,
			'headers'     => array(),
			'cookies'     => array(),
		);
		$decoded = json_decode( $body, true );
		if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body'] = $body;
		} else {
			$args['body'] = $body;
		}
		$response = wp_remote_request( $url, $args );
		$new_attempts = $log['attempts'] + 1;
		if ( is_wp_error( $response ) ) {
			$this->update_log( $log_id, array(
				'attempts'      => $new_attempts,
				'response_body' => $response->get_error_message(),
				'updated_at'    => current_time( 'mysql' ),
			) );
			$this->schedule_retry( $log_id, $new_attempts );
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			$resp_body = wp_remote_retrieve_body( $response );
			$status = ( $code >= 200 && $code < 300 ) ? 'success' : 'failed';
			$this->update_log( $log_id, array(
				'attempts'       => $new_attempts,
				'response_code'  => $code,
				'response_body'  => mb_substr( $resp_body, 0, 10000 ),
				'status'         => $status,
				'next_retry_at'  => null,
				'updated_at'     => current_time( 'mysql' ),
			) );
			if ( $status === 'failed' && $new_attempts < self::MAX_RETRIES ) {
				$this->schedule_retry( $log_id, $new_attempts );
			}
		}
	}

	public function cleanup_old_logs() {
		global $wpdb;
		$table = nrfm_get_valid_table( 'webhook_logs' );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::LOG_RETENTION_DAYS * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE created_at < %s',
				$table,
				$cutoff
			)
		);
	}

	public function enqueue_admin_assets( $hook ) {
		if ( ! nrfm_is_form_edit_screen( $hook ) ) {
			return;
		}
		$form_id = nrfm_admin_request( 'form', 0 );
		if ( $form_id <= 0 ) {
			return;
		}
		$show_logs = nrfm_admin_request( 'nrfm_webhook_logs', '' ) === '1';
		$edit_nonce = wp_create_nonce( 'edit_form' );
		$logs_url = add_query_arg( array(
			'page'               => 'nrfm-forms',
			'action'             => 'edit',
			'form'               => $form_id,
			'tab'                => 'actions',
			'nrfm_webhook_logs'  => '1',
			'_wpnonce'           => $edit_nonce,
		), admin_url( 'admin.php' ) );
		$back_url = add_query_arg( array(
			'page'      => 'nrfm-forms',
			'action'    => 'edit',
			'form'      => $form_id,
			'tab'       => 'actions',
			'_wpnonce'  => $edit_nonce,
		), admin_url( 'admin.php' ) );
		$log_count = $this->get_log_count_for_form( $form_id );
		wp_enqueue_script(
			'nrfm-webhook-logs-link',
			NRFM_PLUGIN_URL . 'assets/js/webhook-logs-link.js',
			array(),
			NRFM_VERSION,
			true
		);
		wp_localize_script( 'nrfm-webhook-logs-link', 'nrfmWebhookLogs', array(
			'logsUrl'   => $logs_url,
			'backUrl'   => $back_url,
			'linkText'  => __( 'View Webhook Logs', 'narrative-forms' ),
			'backText'  => __( 'Back to Actions', 'narrative-forms' ),
			'logCount'  => $log_count,
			'showLogs'  => $show_logs,
		) );
	}

	public function handle_admin_actions() {
		$page   = nrfm_admin_request( 'page', '' );
		$action = nrfm_admin_request( 'action', '' );
		if ( $page !== 'nrfm-forms' || $action !== 'edit' ) {
			return;
		}
		$form_id = nrfm_admin_request( 'form', 0 );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['nrfm_retry_log'] ) && ! empty( $_GET['_nrfm_retry_nonce'] ) ) {
			$log_id = intval( wp_unslash( $_GET['nrfm_retry_log'] ) );
			if ( nrfm_can_manage() && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_nrfm_retry_nonce'] ) ), 'nrfm_retry_' . $log_id ) ) {
				$this->update_log( $log_id, array( 'attempts' => 0, 'status' => 'pending_retry' ) );
				$this->schedule_retry( $log_id, 0 );
			}
			wp_safe_redirect( remove_query_arg( array( 'nrfm_retry_log', '_nrfm_retry_nonce' ) ) );
			exit;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['nrfm_clear_logs'] ) && ! empty( $_GET['_nrfm_clear_logs_nonce'] ) ) {
			if ( $form_id > 0 && nrfm_can_manage() && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_nrfm_clear_logs_nonce'] ) ), 'nrfm_clear_logs_' . $form_id ) ) {
				$this->clear_logs_for_form( $form_id );
			}
			$redirect = remove_query_arg( array( 'nrfm_clear_logs', '_nrfm_clear_logs_nonce' ) );
			$redirect = add_query_arg( 'nrfm_logs_cleared', '1', $redirect );
			wp_safe_redirect( $redirect );
			exit;
		}
	}

	public function render_inline_logs() {
		$page      = nrfm_admin_request( 'page', '' );
		$action    = nrfm_admin_request( 'action', '' );
		$show_logs = nrfm_admin_request( 'nrfm_webhook_logs', '' ) === '1';
		if ( $page !== 'nrfm-forms' || $action !== 'edit' || ! $show_logs ) {
			return;
		}
		$form_id = nrfm_admin_request( 'form', 0 );
		if ( $form_id <= 0 ) {
			return;
		}
		// Verify user has permission to view
		if ( ! nrfm_can_manage() ) {
			return;
		}
		$logs = $this->get_logs_for_form( $form_id, 100 );
		$clear_url = add_query_arg( array(
			'nrfm_clear_logs'        => '1',
			'_nrfm_clear_logs_nonce' => wp_create_nonce( 'nrfm_clear_logs_' . $form_id ),
		) );
		ob_start();
		?>
		<div id="nrfm-webhook-logs-content" style="display:none;">
			<h2><?php esc_html_e( 'Webhook Logs', 'narrative-forms' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Recent webhook delivery attempts for this form. Logs are retained for 30 days.', 'narrative-forms' ); ?></p>
			<?php if ( nrfm_admin_request( 'nrfm_logs_cleared', '' ) === '1' ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Webhook logs cleared for this form. Pending retries were canceled.', 'narrative-forms' ); ?></p></div>
			<?php endif; ?>
			<p>
				<a
					href="<?php echo esc_url( $clear_url ); ?>"
					class="button button-secondary"
					onclick="return window.confirm('<?php echo esc_js( __( 'Clear all logs for this form? Pending retries for these logs will be canceled.', 'narrative-forms' ) ); ?>');"
				><?php esc_html_e( 'Clear Logs', 'narrative-forms' ); ?></a>
			</p>
			<table class="wp-list-table widefat fixed striped" style="margin-top:15px;">
				<thead>
					<tr>
						<th style="width:50px;"><?php esc_html_e( 'ID', 'narrative-forms' ); ?></th>
						<th><?php esc_html_e( 'URL', 'narrative-forms' ); ?></th>
						<th style="width:70px;"><?php esc_html_e( 'Method', 'narrative-forms' ); ?></th>
						<th style="width:100px;"><?php esc_html_e( 'Status', 'narrative-forms' ); ?></th>
						<th style="width:50px;"><?php esc_html_e( 'Code', 'narrative-forms' ); ?></th>
						<th style="width:70px;"><?php esc_html_e( 'Attempts', 'narrative-forms' ); ?></th>
						<th style="width:140px;"><?php esc_html_e( 'Created', 'narrative-forms' ); ?></th>
						<th style="width:80px;"><?php esc_html_e( 'Actions', 'narrative-forms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'No webhook logs found for this form.', 'narrative-forms' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo intval( $log['id'] ); ?></td>
								<td><code style="word-break:break-all;font-size:12px;"><?php echo esc_html( mb_substr( $log['webhook_url'], 0, 60 ) ); ?><?php echo strlen( $log['webhook_url'] ) > 60 ? '...' : ''; ?></code></td>
								<td><?php echo esc_html( $log['request_method'] ); ?></td>
								<td>
									<?php
									$status = $log['status'];
									$badge = 'background:#ddd;';
									if ( $status === 'success' ) {
										$badge = 'background:#d4edda;color:#155724;';
									} elseif ( $status === 'failed' ) {
										$badge = 'background:#f8d7da;color:#721c24;';
									} elseif ( $status === 'pending_retry' ) {
										$badge = 'background:#fff3cd;color:#856404;';
									}
									?>
									<span style="padding:2px 6px;border-radius:3px;font-size:11px;<?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $status ); ?></span>
								</td>
								<td><?php echo intval( $log['response_code'] ); ?></td>
								<td><?php echo intval( $log['attempts'] ); ?>/<?php echo intval( self::MAX_RETRIES ); ?></td>
								<td style="font-size:12px;"><?php echo esc_html( $log['created_at'] ); ?></td>
								<td>
									<?php if ( $log['status'] === 'failed' ) : ?>
										<?php
										$retry_url = add_query_arg( array(
											'nrfm_retry_log'    => $log['id'],
											'_nrfm_retry_nonce' => wp_create_nonce( 'nrfm_retry_' . $log['id'] ),
										) );
										?>
										<a href="<?php echo esc_url( $retry_url ); ?>" class="button button-small"><?php esc_html_e( 'Retry', 'narrative-forms' ); ?></a>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		$html = ob_get_clean();
		echo $html;
	}

	private function extract_form_id( $webhook_config, $args = array() ) {
		if ( isset( $args['_nrfm_form_id'] ) && $args['_nrfm_form_id'] > 0 ) {
			return intval( $args['_nrfm_form_id'] );
		}
		if ( isset( $webhook_config['form_id'] ) ) {
			return intval( $webhook_config['form_id'] );
		}
		return 0;
	}

	private function insert_log( $data ) {
		global $wpdb;
		$table = nrfm_get_valid_table( 'webhook_logs' );
		$now = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'form_id'        => isset( $data['form_id'] ) ? intval( $data['form_id'] ) : 0,
				'webhook_url'    => isset( $data['webhook_url'] ) ? $data['webhook_url'] : '',
				'request_method' => isset( $data['request_method'] ) ? $data['request_method'] : 'POST',
				'request_body'   => isset( $data['request_body'] ) ? $data['request_body'] : '',
				'response_code'  => isset( $data['response_code'] ) ? intval( $data['response_code'] ) : null,
				'response_body'  => isset( $data['response_body'] ) ? $data['response_body'] : '',
				'status'         => isset( $data['status'] ) ? $data['status'] : 'pending',
				'attempts'       => isset( $data['attempts'] ) ? intval( $data['attempts'] ) : 0,
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
		);
		return $wpdb->insert_id;
	}

	private function update_log( $log_id, $data ) {
		global $wpdb;
		$table = nrfm_get_valid_table( 'webhook_logs' );
		$data['updated_at'] = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table, $data, array( 'id' => $log_id ) );
	}

	private function get_log( $log_id ) {
		global $wpdb;
		$table = nrfm_get_valid_table( 'webhook_logs' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $log_id ),
			ARRAY_A
		);
	}

	private function get_logs_for_form( $form_id, $limit = 100 ) {
		global $wpdb;
		$table = nrfm_get_valid_table( 'webhook_logs' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE form_id = %d ORDER BY created_at DESC LIMIT %d', $table, $form_id, $limit ),
			ARRAY_A
		);
	}

	private function get_log_count_for_form( $form_id ) {
		global $wpdb;
		$table = nrfm_get_valid_table( 'webhook_logs' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE form_id = %d', $table, $form_id )
		);
	}

	private function clear_logs_for_form( $form_id ) {
		global $wpdb;
		$table = nrfm_get_valid_table( 'webhook_logs' );

		// Gather pending retry log IDs before delete so we can unschedule them.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$retry_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE form_id = %d AND (status = %s OR next_retry_at IS NOT NULL)',
				$table,
				$form_id,
				'pending_retry'
			)
		);

		if ( is_array( $retry_ids ) ) {
			foreach ( $retry_ids as $log_id ) {
				$timestamp = wp_next_scheduled( self::RETRY_HOOK, array( (int) $log_id ) );
				if ( $timestamp ) {
					wp_unschedule_event( $timestamp, self::RETRY_HOOK, array( (int) $log_id ) );
				}
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete( $table, array( 'form_id' => (int) $form_id ), array( '%d' ) );
	}
}
