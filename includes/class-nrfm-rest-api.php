<?php
defined( 'ABSPATH' ) || exit;

class NRFM_REST_API {
	const NAMESPACE = 'nrfm/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'nrfm_settings_api_integrations', array( $this, 'render_api_key_setting' ) );
		add_filter( 'nrfm_save_global_settings', array( $this, 'save_api_key_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function enqueue_admin_assets( $hook ) {
		if ( nrfm_admin_request( 'page', '' ) !== 'nrfm-forms-settings' ) {
			return;
		}
		wp_enqueue_script( 'nrfm-api-key', NRFM_PLUGIN_URL . 'assets/js/api-key-admin.js', array(), NRFM_VERSION, true );
		wp_localize_script(
			'nrfm-api-key',
			'nrfmApiKey',
			array(
				'showText'   => __( 'Show', 'narrative-forms' ),
				'hideText'   => __( 'Hide', 'narrative-forms' ),
				'copyText'   => __( 'Copy', 'narrative-forms' ),
				'copiedText' => __( 'Copied', 'narrative-forms' ),
			)
		);
	}

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/forms',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_forms' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'per_page' => array(
						'default'           => 50,
						'sanitize_callback' => array( $this, 'sanitize_per_page' ),
					),
					'page' => array(
						'default'           => 1,
						'sanitize_callback' => array( $this, 'sanitize_page' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/forms/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_form' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/forms/(?P<id>\d+)/submissions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_submissions' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
					'per_page' => array(
						'default'           => 20,
						'sanitize_callback' => array( $this, 'sanitize_per_page' ),
					),
					'page' => array(
						'default'           => 1,
						'sanitize_callback' => array( $this, 'sanitize_page' ),
					),
					'order' => array(
						'default'           => 'DESC',
						'sanitize_callback' => array( $this, 'sanitize_order' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/submissions/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_submission' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/submissions/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_submission' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/forms/(?P<id>\d+)/submit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_submission' ),
				'permission_callback' => array( $this, 'check_submission_permission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);
	}

	public function check_permission( $request ) {
		$cap = apply_filters( 'nrfm_rest_api_capability', 'manage_options' );
		if ( current_user_can( $cap ) ) {
			return true;
		}
		if ( $this->has_valid_api_key( $request ) ) {
			return true;
		}
		return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'narrative-forms' ), array( 'status' => 401 ) );
	}

	public function check_submission_permission( $request ) {
		if ( $this->has_valid_api_key( $request ) ) {
			return true;
		}
		$cap = apply_filters( 'nrfm_rest_api_capability', 'manage_options' );
		if ( current_user_can( $cap ) ) {
			return true;
		}
		return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'narrative-forms' ), array( 'status' => 401 ) );
	}

	public function sanitize_per_page( $value ) {
		return min( 100, max( 1, absint( $value ) ) );
	}

	public function sanitize_page( $value ) {
		return max( 1, absint( $value ) );
	}

	public function sanitize_order( $value ) {
		return strtoupper( (string) $value ) === 'ASC' ? 'ASC' : 'DESC';
	}

	private function extract_request_api_key( $request ) {
		$key = trim( (string) $request->get_header( 'X-NRFM-API-Key' ) );
		if ( $key !== '' ) {
			return $key;
		}
		$auth = trim( (string) $request->get_header( 'Authorization' ) );
		if ( stripos( $auth, 'Bearer ' ) === 0 ) {
			return trim( substr( $auth, 7 ) );
		}
		return '';
	}

	private function get_api_key() {
		$key = get_option( 'nrfm_pro_api_key', '' );
		$key = is_string( $key ) ? trim( $key ) : '';
		return apply_filters( 'nrfm_rest_api_key', $key );
	}

	private function has_valid_api_key( $request ) {
		$stored_key = $this->get_api_key();
		if ( $stored_key === '' ) {
			return false;
		}
		$key = $this->extract_request_api_key( $request );
		return $key !== '' && hash_equals( $stored_key, $key );
	}

	public function render_api_key_setting() {
		$key = $this->get_api_key();
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'REST API Key', 'narrative-forms' ); ?></th>
			<td>
				<input type="password" id="nrfm-api-key" name="nrfm_pro_api_key" class="regular-text code" value="<?php echo esc_attr( $key ); ?>" autocomplete="off">
				<button type="button" class="button button-small nrfm-api-key-toggle" data-target="nrfm-api-key" aria-pressed="false" style="margin-left:4px;"><?php esc_html_e( 'Show', 'narrative-forms' ); ?></button>
				<button type="button" class="button button-small nrfm-api-key-copy" data-target="nrfm-api-key"><?php esc_html_e( 'Copy', 'narrative-forms' ); ?></button>
				<p class="description"><?php esc_html_e( 'Used for Narrative Forms REST API access via header X-NRFM-API-Key or Authorization: Bearer <key>.', 'narrative-forms' ); ?></p>
				<p class="description"><strong><?php esc_html_e( 'Keep this secret. Anyone with this key can read and delete every submission on this site.', 'narrative-forms' ); ?></strong></p>
				<p class="description"><?php esc_html_e( 'Leave empty to disable API key auth (administrator capability still works).', 'narrative-forms' ); ?></p>
			</td>
		</tr>
		<?php
	}

	public function save_api_key_setting( $settings ) {
		$key = isset( $_POST['nrfm_pro_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['nrfm_pro_api_key'] ) ) : '';
		$key = trim( $key );
		if ( $key === '' ) {
			delete_option( 'nrfm_pro_api_key' );
		} else {
			update_option( 'nrfm_pro_api_key', $key );
		}
		return $settings;
	}

	public function get_forms( $request ) {
		$per_page = $this->sanitize_per_page( $request->get_param( 'per_page' ) );
		$page     = $this->sanitize_page( $request->get_param( 'page' ) );
		$forms = get_posts( array(
			'post_type'      => 'nrfm_form',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'offset'         => ( $page - 1 ) * $per_page,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$data = array();
		foreach ( $forms as $post ) {
			$data[] = array(
				'id'               => $post->ID,
				'title'            => $post->post_title,
				'slug'             => $post->post_name,
				'submissions_count' => nrfm_get_submissions_count( $post->ID ),
			);
		}

		return rest_ensure_response( $data );
	}

	public function get_form( $request ) {
		$form_id = $request->get_param( 'id' );
		$form = new NRFM_Form( $form_id );

		if ( ! $form->exists() ) {
			return new WP_Error( 'not_found', __( 'Form not found.', 'narrative-forms' ), array( 'status' => 404 ) );
		}

		$post = get_post( $form_id );
		$settings = $form->get_settings();
		$messages = $form->get_messages();

		return rest_ensure_response( array(
			'id'               => $form_id,
			'title'            => $post->post_title,
			'slug'             => $post->post_name,
			'html'             => $form->get_html(),
			'settings'         => $settings,
			'messages'         => $messages,
			'submissions_count' => nrfm_get_submissions_count( $form_id ),
		) );
	}

	public function get_submissions( $request ) {
		$form_id = $request->get_param( 'id' );
		$form = new NRFM_Form( $form_id );

		if ( ! $form->exists() ) {
			return new WP_Error( 'not_found', __( 'Form not found.', 'narrative-forms' ), array( 'status' => 404 ) );
		}

		$per_page = $this->sanitize_per_page( $request->get_param( 'per_page' ) );
		$page = $this->sanitize_page( $request->get_param( 'page' ) );
		$order = $this->sanitize_order( $request->get_param( 'order' ) );
		$offset = ( $page - 1 ) * $per_page;

		global $wpdb;
		$table = nrfm_get_valid_table( 'submissions' );
		if ( $table === '' ) {
			return new WP_Error( 'table_error', __( 'Submissions table is unavailable.', 'narrative-forms' ), array( 'status' => 500 ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE form_id = %d', $table, $form_id )
		);

		if ( $order === 'ASC' ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id, submitted_at, ip_address, user_agent, referer_url, data FROM %i WHERE form_id = %d ORDER BY submitted_at ASC LIMIT %d OFFSET %d',
					$table,
					$form_id,
					$per_page,
					$offset
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id, submitted_at, ip_address, user_agent, referer_url, data FROM %i WHERE form_id = %d ORDER BY submitted_at DESC LIMIT %d OFFSET %d',
					$table,
					$form_id,
					$per_page,
					$offset
				),
				ARRAY_A
			);
		}

		$submissions = array();
		foreach ( $rows as $row ) {
			$submissions[] = array(
				'id'           => intval( $row['id'] ),
				'submitted_at' => $row['submitted_at'],
				'ip_address'   => $row['ip_address'],
				'user_agent'   => $row['user_agent'],
				'referer_url'  => $row['referer_url'],
				'fields'       => json_decode( $row['data'], true ),
			);
		}

		$response = rest_ensure_response( array(
			'form_id'     => $form_id,
			'total'       => intval( $total ),
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
			'submissions' => $submissions,
		) );

		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );

		return $response;
	}

	public function get_submission( $request ) {
		$submission_id = $request->get_param( 'id' );

		global $wpdb;
		$table = nrfm_get_valid_table( 'submissions' );
		if ( $table === '' ) {
			return new WP_Error( 'table_error', __( 'Submissions table is unavailable.', 'narrative-forms' ), array( 'status' => 500 ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $submission_id ),
			ARRAY_A
		);

		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Submission not found.', 'narrative-forms' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array(
			'id'           => intval( $row['id'] ),
			'form_id'      => intval( $row['form_id'] ),
			'submitted_at' => $row['submitted_at'],
			'ip_address'   => $row['ip_address'],
			'user_agent'   => $row['user_agent'],
			'referer_url'  => $row['referer_url'],
			'fields'       => json_decode( $row['data'], true ),
		) );
	}

	public function delete_submission( $request ) {
		$submission_id = $request->get_param( 'id' );

		global $wpdb;
		$table = nrfm_get_valid_table( 'submissions' );
		if ( $table === '' ) {
			return new WP_Error( 'table_error', __( 'Submissions table is unavailable.', 'narrative-forms' ), array( 'status' => 500 ) );
		}

		// Fetch the owning form id once; a null result means the row doesn't exist.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$form_id = $wpdb->get_var(
			$wpdb->prepare( 'SELECT form_id FROM %i WHERE id = %d', $table, $submission_id )
		);

		if ( null === $form_id ) {
			return new WP_Error( 'not_found', __( 'Submission not found.', 'narrative-forms' ), array( 'status' => 404 ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'id' => $submission_id ), array( '%d' ) );
		nrfm_clear_submission_cache( (int) $form_id );

		return rest_ensure_response( array(
			'deleted' => true,
			'id'      => intval( $submission_id ),
		) );
	}

	public function create_submission( $request ) {
		$form_id = $request->get_param( 'id' );
		$form = new NRFM_Form( $form_id );

		if ( ! $form->exists() ) {
			return new WP_Error( 'not_found', __( 'Form not found.', 'narrative-forms' ), array( 'status' => 404 ) );
		}

		$body = $request->get_json_params();
		if ( ! is_array( $body ) || empty( $body ) ) {
			$body = $request->get_body_params();
		}
		if ( empty( $body ) || ! is_array( $body ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid submission data.', 'narrative-forms' ), array( 'status' => 400 ) );
		}

		// REST body params are already unslashed by core; the helper whitelists + sanitizes.
		$input = nrfm_whitelist_field_input( $form, $body );

		$submission = new NRFM_Submission();
		$result = $submission->process( $form_id, $input );

		if ( ! is_array( $result ) ) {
			return new WP_Error( 'submission_failed', __( 'Submission failed.', 'narrative-forms' ), array( 'status' => 400 ) );
		}
		if ( ! empty( $result['success'] ) ) {
			return rest_ensure_response( array(
				'success' => true,
				'message' => $result['message'],
			) );
		}
		return new WP_Error( 'submission_failed', $result['message'] ?? __( 'Submission failed.', 'narrative-forms' ), array( 'status' => 400 ) );
	}
}
