<?php
defined( 'ABSPATH' ) || exit;

/**
 * Unread-submission badges for the Forms menu, forms list, and submissions tab.
 *
 * The `seen` column and its `form_seen` index are created by core's schema
 * versioning (see nrfm_activate / nrfm_maybe_upgrade_schema); this class only
 * reads/writes that column and renders the badges.
 */
class NRFM_Submission_Notifications {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_badge' ), 99 );
		add_action( 'admin_head', array( $this, 'inject_menu_dot_style' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
		add_action( 'nrfm_form_submitted', array( $this, 'bust_menu_unread_cache' ) );
		add_action( 'nrfm_save_form', array( $this, 'bust_menu_unread_cache' ) );
	}

	/**
	 * Idempotently add the `seen` column + `form_seen` index to the submissions
	 * table. Called by core's activation and schema-upgrade routines.
	 */
	public static function maybe_add_seen_column() {
		global $wpdb;
		$table = nrfm_get_valid_table( 'submissions' );
		if ( $table === '' ) {
			return;
		}
		$column = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table,
				'seen'
			)
		);
		if ( empty( $column ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN seen TINYINT(1) NOT NULL DEFAULT 0" );
		}
		$index = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW INDEX FROM %i WHERE Key_name = %s',
				$table,
				'form_seen'
			)
		);
		if ( empty( $index ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "ALTER TABLE {$table} ADD KEY form_seen (form_id, seen)" );
		}
	}

	public function enqueue_admin_assets( $hook ) {
		if ( strpos( (string) $hook, 'nrfm-forms' ) === false ) {
			return;
		}
		wp_enqueue_script(
			'nrfm-notifications-admin',
			NRFM_PLUGIN_URL . 'assets/js/submission-notifications-admin.js',
			array( 'jquery' ),
			NRFM_VERSION,
			true
		);

		$data = array(
			/* translators: %d: number of new (unseen) submissions. */
			'badgeLabel'       => __( '%d new', 'narrative-forms' ),
			'bulkActionValue'  => 'nrfm_mark_seen',
			'bulkActionLabel'  => __( 'Mark as seen', 'narrative-forms' ),
			'isFormsList'      => false,
			'isEditPage'       => false,
			'isSubmissionsTab' => false,
		);

		$page    = nrfm_admin_request( 'page', '' );
		$action  = nrfm_admin_request( 'action', '' );
		$form_id = nrfm_admin_request( 'form', 0 );

		if ( $page === 'nrfm-forms' && $action === 'edit' && $form_id > 0 ) {
			$data['isEditPage'] = true;
			if ( $this->notifications_enabled( $form_id ) ) {
				$data['tabUnreadCount'] = $this->get_unread_count_for_form( $form_id );
			}
			$tab = nrfm_admin_request( 'tab', '' );
			if ( $tab === 'submissions' && $this->notifications_enabled( $form_id ) ) {
				$data['isSubmissionsTab'] = true;
				$unread_ids = $this->get_unread_ids_for_form( $form_id );
				$data['unreadIds'] = $unread_ids;
				$data['markSeenUrls'] = $this->build_mark_seen_urls( $form_id, $unread_ids );
			}
		} elseif ( $page === 'nrfm-forms' ) {
			$data['isFormsList'] = true;
			$enabled_forms = $this->get_enabled_form_ids();
			$data['listUnreadCounts'] = $this->get_unread_counts_by_form( $enabled_forms );
		}

		wp_localize_script( 'nrfm-notifications-admin', 'nrfmNotifications', $data );

		wp_add_inline_style(
			'nrfm-admin',
			'.nrfm-unread-badge{display:inline-block;margin-left:8px;padding:2px 6px;border-radius:999px;background:#f0b849;color:#1d2327;font-size:11px;line-height:1.3;vertical-align:middle;}
			.nrfm-tab-badge{display:inline-block;margin-left:6px;padding:0 6px;border-radius:10px;background:#f0b849;color:#1d2327;font-size:11px;line-height:18px;vertical-align:middle;}
			.nrfm-submission-unread{background:#f8d7da !important;}'
		);
	}

	public function handle_admin_actions() {
		if ( ! is_admin() || ! nrfm_can_manage() ) {
			return;
		}
		$page    = nrfm_admin_request( 'page', '', 'request' );
		$form_id = nrfm_admin_request( 'form', 0, 'request' );

		if ( $page === 'nrfm-forms' && ! empty( $_GET['nrfm_view'] ) && $form_id > 0 ) {
			$submission_id = intval( wp_unslash( $_GET['nrfm_view'] ) );
			$nonce_view = isset( $_GET['_wpnonce_view'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce_view'] ) ) : '';
			if ( $submission_id > 0 && $nonce_view && wp_verify_nonce( $nonce_view, 'nrfm_view_' . $submission_id ) ) {
				if ( $this->notifications_enabled( $form_id ) ) {
					$this->mark_submission_seen( $submission_id, $form_id );
				}
			}
		}

		if ( ! empty( $_GET['nrfm_mark_seen'] ) && $page === 'nrfm-forms' ) {
			$submission_id = intval( wp_unslash( $_GET['nrfm_mark_seen'] ) );
			$nonce = isset( $_GET['_nrfm_mark_seen_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_nrfm_mark_seen_nonce'] ) ) : '';
			if ( $submission_id > 0 && $form_id > 0 && $nonce && wp_verify_nonce( $nonce, 'nrfm_mark_seen_' . $submission_id ) ) {
				if ( $this->notifications_enabled( $form_id ) ) {
					$this->mark_submission_seen( $submission_id, $form_id );
				}
			}
			$redirect = remove_query_arg( array( 'nrfm_mark_seen', '_nrfm_mark_seen_nonce' ) );
			wp_safe_redirect( $redirect );
			exit;
		}

		$action_1    = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		$action_2    = isset( $_POST['action2'] ) ? sanitize_text_field( wp_unslash( $_POST['action2'] ) ) : '';
		$bulk_action = ( ! empty( $action_1 ) && $action_1 !== '-1' ) ? $action_1 : $action_2;
		if ( $bulk_action === 'nrfm_mark_seen' && $page === 'nrfm-forms' && $form_id > 0 ) {
			$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'bulk-submissions' ) ) {
				return;
			}
			$ids = isset( $_POST['submission_ids'] ) && is_array( $_POST['submission_ids'] )
				? array_map( 'absint', (array) wp_unslash( $_POST['submission_ids'] ) )
				: array();
			if ( $this->notifications_enabled( $form_id ) && ! empty( $ids ) ) {
				$this->mark_submissions_seen( $ids, $form_id );
			}
			$redirect = add_query_arg(
				array(
					'page'     => 'nrfm-forms',
					'action'   => 'edit',
					'form'     => $form_id,
					'tab'      => 'submissions',
					'_wpnonce' => wp_create_nonce( 'edit_form' ),
				),
				admin_url( 'admin.php' )
			);
			wp_safe_redirect( $redirect );
			exit;
		}
	}

	public function add_menu_badge() {
		if ( ! nrfm_can_manage() ) {
			return;
		}
		$count = $this->get_menu_unread_count();
		global $menu;
		foreach ( $menu as $index => $item ) {
			if ( isset( $item[2] ) && $item[2] === 'nrfm-forms' ) {
				$dot = ( $count > 0 ) ? ' <span class="nrfm-menu-dot" aria-hidden="true"></span>' : '';
				// Append the dot to core's existing (translated) label; never replace it.
				$menu[ $index ][0] = $menu[ $index ][0] . $dot;
				break;
			}
		}
	}

	/**
	 * Cached total unread count for the admin menu dot. add_menu_badge runs on
	 * every admin page, so avoid recomputing across all forms each time. Busted
	 * on new submission, mark-seen, and form save; short TTL as a backstop.
	 */
	private function get_menu_unread_count() {
		$cached = get_transient( 'nrfm_menu_unread' );
		if ( $cached !== false ) {
			return (int) $cached;
		}
		$count = $this->get_unread_count( $this->get_enabled_form_ids() );
		set_transient( 'nrfm_menu_unread', $count, 5 * MINUTE_IN_SECONDS );
		return $count;
	}

	public function bust_menu_unread_cache() {
		delete_transient( 'nrfm_menu_unread' );
	}

	public function inject_menu_dot_style() {
		echo '<style>
			#adminmenu .nrfm-menu-dot{
				display:inline-block;
				width:10px;
				height:10px;
				border-radius:50%;
				background:#e65054;
				vertical-align:middle;
				transform:translateY(-1px);
			}
		</style>';
	}

	private function notifications_enabled( $form_id ) {
		if ( $form_id <= 0 ) {
			return false;
		}
		if ( class_exists( 'NRFM_Form' ) ) {
			$form = new NRFM_Form( $form_id );
			if ( ! $form->exists() ) {
				return false;
			}
			$settings = $form->get_settings();
		} else {
			$settings = get_post_meta( $form_id, '_nrfm_settings', true );
		}
		// Badges are always on for any form that stores submissions (no per-form toggle).
		$enabled = is_array( $settings ) && ! empty( $settings['save_submissions'] );
		/**
		 * Filter whether unread-submission badges show for a form. Lets developers turn
		 * badges off programmatically without a UI toggle.
		 *
		 * @param bool $enabled Whether badges are shown for this form.
		 * @param int  $form_id The form ID.
		 */
		return (bool) apply_filters( 'nrfm_notifications_enabled', $enabled, (int) $form_id );
	}

	private function get_enabled_form_ids() {
		$ids = get_posts(
			array(
				'post_type'      => 'nrfm_form',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		if ( empty( $ids ) ) {
			return array();
		}
		// Prime the post + meta caches in a bounded number of queries so the
		// per-form check below reads from cache instead of running a query per
		// form. This only runs on a cache-miss rebuild of the menu count.
		_prime_post_caches( $ids, false, true );
		$enabled = array();
		foreach ( $ids as $id ) {
			if ( get_post_status( $id ) === 'trash' ) {
				continue;
			}
			if ( $this->notifications_enabled( $id ) ) {
				$enabled[] = (int) $id;
			}
		}
		return $enabled;
	}

	private function get_table_name() {
		return nrfm_get_valid_table( 'submissions' );
	}

	private function get_unread_count( $form_ids ) {
		if ( empty( $form_ids ) ) {
			return 0;
		}
		$table = $this->get_table_name();
		if ( $table === '' ) {
			return 0;
		}
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE seen = 0 AND form_id IN ({$placeholders})",
				$form_ids
			)
		);
		return intval( $count );
	}

	private function get_unread_counts_by_form( $form_ids ) {
		$counts = array();
		if ( empty( $form_ids ) ) {
			return $counts;
		}
		$table = $this->get_table_name();
		if ( $table === '' ) {
			return $counts;
		}
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT form_id, COUNT(*) AS cnt FROM {$table} WHERE seen = 0 AND form_id IN ({$placeholders}) GROUP BY form_id",
				$form_ids
			)
		);
		foreach ( $rows as $row ) {
			$counts[ (int) $row->form_id ] = (int) $row->cnt;
		}
		return $counts;
	}

	private function get_unread_count_for_form( $form_id ) {
		$counts = $this->get_unread_counts_by_form( array( $form_id ) );
		return isset( $counts[ $form_id ] ) ? $counts[ $form_id ] : 0;
	}

	private function get_unread_ids_for_form( $form_id ) {
		$table = $this->get_table_name();
		if ( $table === '' ) {
			return array();
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE form_id = %d AND seen = 0 ORDER BY submitted_at DESC LIMIT 500",
				$form_id
			)
		);
		return array_map( 'intval', $rows );
	}

	private function build_mark_seen_urls( $form_id, $submission_ids ) {
		$urls = array();
		foreach ( $submission_ids as $id ) {
			$urls[ $id ] = add_query_arg(
				array(
					'page'                 => 'nrfm-forms',
					'action'               => 'edit',
					'form'                 => $form_id,
					'tab'                  => 'submissions',
					'nrfm_mark_seen'        => $id,
					'_nrfm_mark_seen_nonce' => wp_create_nonce( 'nrfm_mark_seen_' . $id ),
					'_wpnonce'             => wp_create_nonce( 'edit_form' ),
				),
				admin_url( 'admin.php' )
			);
		}
		return $urls;
	}

	private function mark_submission_seen( $submission_id, $form_id ) {
		$table = $this->get_table_name();
		if ( $table === '' ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array( 'seen' => 1 ),
			array( 'id' => $submission_id, 'form_id' => $form_id ),
			array( '%d' ),
			array( '%d', '%d' )
		);
		$this->bust_menu_unread_cache();
	}

	private function mark_submissions_seen( $submission_ids, $form_id ) {
		$table = $this->get_table_name();
		if ( $table === '' ) {
			return;
		}
		global $wpdb;
		$submission_ids = array_filter( array_map( 'intval', $submission_ids ) );
		if ( empty( $submission_ids ) ) {
			return;
		}
		$placeholders = implode( ',', array_fill( 0, count( $submission_ids ), '%d' ) );
		$params = array_merge( $submission_ids, array( $form_id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET seen = 1 WHERE id IN ({$placeholders}) AND form_id = %d",
				$params
			)
		);
		$this->bust_menu_unread_cache();
	}
}
