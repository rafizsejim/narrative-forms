<?php
// Prevent direct access
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Submissions table (WP_List_Table) for a single form
 */
class NRFM_Submissions_Table extends WP_List_Table {
	private $form_id;
	private $field_keys   = array();
	private $field_labels = array();
	private $question_map = array();
	private $choice_map   = array();
	private $raw_fields   = array();

	public function __construct( $form_id ) {
		parent::__construct(
			array(
				'singular' => 'submission',
				'plural'   => 'submissions',
				'ajax'     => false,
			)
		);
		$this->form_id = intval( $form_id );
	}

	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'narrative-forms' ),
		);
	}

	public function process_bulk_action() {
		if ( $this->current_action() !== 'delete' ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'bulk-submissions' );

		$ids = isset( $_REQUEST['submission_ids'] )
			? array_map( 'absint', (array) wp_unslash( $_REQUEST['submission_ids'] ) )
			: array();
		if ( empty( $ids ) ) {
			return;
		}

		global $wpdb;
		$table = nrfm_get_valid_table( 'submissions' );
		if ( $table === '' ) {
			return;
		}
		// Use individual deletes to avoid placeholder count mismatches in prepare()
		foreach ( $ids as $id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
			/** Fires after a submission row is deleted, so add-ons can clean up related data. */
			do_action( 'nrfm_submission_deleted', (int) $id );
		}
		// Clear submission count cache for this form
		nrfm_clear_submission_cache( $this->form_id );
		return;
	}

	public function no_items() {
		esc_html_e( 'No submissions found.', 'narrative-forms' );
	}

	public function column_cb( $item ) {
		return '<label class="screen-reader-text" for="cb-select-' . intval( $item['id'] ) . '">' . esc_html__( 'Select submission', 'narrative-forms' ) . '</label>'
			. '<input id="cb-select-' . intval( $item['id'] ) . '" type="checkbox" name="submission_ids[]" value="' . intval( $item['id'] ) . '" />';
	}

	public function column_default( $item, $column_name ) {
		if ( in_array( $column_name, $this->field_keys, true ) ) {
			// Decode lazily from raw JSON
			static $decoded_cache = array();
			$cacheKey             = $item['id'];
			if ( ! isset( $decoded_cache[ $cacheKey ] ) ) {
				$decoded                    = json_decode( $item['fields_raw'], true );
				$decoded_cache[ $cacheKey ] = is_array( $decoded ) ? $decoded : array();
			}
			if ( isset( $decoded_cache[ $cacheKey ][ $column_name ] ) ) {
				$val = $decoded_cache[ $cacheKey ][ $column_name ];
				$is_raw = isset( $this->raw_fields[ $column_name ] ) && $this->raw_fields[ $column_name ];
				$choice_map = ( ! $is_raw && isset( $this->choice_map[ $column_name ] ) && is_array( $this->choice_map[ $column_name ] ) )
					? $this->choice_map[ $column_name ]
					: array();
				if ( is_array( $val ) ) {
					// files or checkbox array
					if ( isset( $val[0] ) && is_array( $val[0] ) && ( isset( $val[0]['name'] ) || isset( $val[0]['url'] ) ) ) {
						// Render file names as links when URLs available, one per line
						$parts = array();
						foreach ( $val as $f ) {
							if ( is_array( $f ) ) {
								$name = isset( $f['name'] ) ? $f['name'] : '';
								$url  = isset( $f['url'] ) ? $f['url'] : '';
								if ( $url ) {
									$parts[] = '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $name ) . '</a>'; } else {
									$parts[] = esc_html( $name ); }
							}
						}
						return implode( '<br>', $parts );
					} elseif ( isset( $val['url'] ) || isset( $val['name'] ) ) {
						// Single file entry shape (associative array)
						$name = isset( $val['name'] ) ? $val['name'] : '';
						$url  = isset( $val['url'] ) ? $val['url'] : '';
						if ( $url ) {
							return '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $name ) . '</a>'; }
						return esc_html( $name !== '' ? $name : ( isset( $val['path'] ) ? basename( (string) $val['path'] ) : '' ) );
					}
					// Generic arrays (map values to labels when possible)
					$parts = array();
					foreach ( $val as $v ) {
						$vv = is_scalar( $v ) ? (string) $v : '';
						if ( $vv !== '' && isset( $choice_map[ $vv ] ) ) {
							$parts[] = $choice_map[ $vv ];
						} else {
							$parts[] = $vv;
						}
					}
					return esc_html( implode( ', ', $parts ) );
				}
				$str = (string) $val;
				if ( $str !== '' && isset( $choice_map[ $str ] ) ) {
					$str = (string) $choice_map[ $str ];
				}
				return esc_html( mb_substr( $str, 0, 80 ) );
			}
			return '-';
		}

		switch ( $column_name ) {
			case 'submitted_on':
				return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item['submitted_at'] ) ) );
			case 'actions':
				$view_url = add_query_arg(
					array(
						'page'          => 'nrfm-forms',
						'action'        => 'edit',
						'form'          => $this->form_id,
						'tab'           => 'submissions',
						'nrfm_view'     => intval( $item['id'] ),
						'_wpnonce_view' => wp_create_nonce( 'nrfm_view_' . intval( $item['id'] ) ),
						'_wpnonce'      => wp_create_nonce( 'edit_form' ),
					),
					admin_url( 'admin.php' )
				);
				$del_url  = add_query_arg(
					array(
						'page'       => 'nrfm-forms',
						'action'     => 'delete_submission',
						'submission' => intval( $item['id'] ),
						'form'       => $this->form_id,
						'tab'        => 'submissions',
						'_wpnonce'   => wp_create_nonce( 'delete_submission' ),
					),
					admin_url( 'admin.php' )
				);
				return '<a class="button button-small" href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'narrative-forms' ) . '</a> '
					. '<a class="button button-small" onclick="return confirm(\'' . esc_attr__( 'Are you sure?', 'narrative-forms' ) . '\');" href="' . esc_url( $del_url ) . '">' . esc_html__( 'Delete', 'narrative-forms' ) . '</a>';
		}
		return '';
	}

	public function get_columns() {
		// Ensure field keys are prepared
		if ( empty( $this->field_keys ) ) {
			$this->discover_field_columns();
		}
		// Strip trailing asterisks from labels (legacy data)
		foreach ( $this->field_labels as $k => $v ) {
			$this->field_labels[ $k ] = preg_replace( '/\s*\*+\s*$/', '', (string) $v );
		}
		// Use WP expected id to enable select-all behavior
		$cols = array( 'cb' => '<input id="cb-select-all-1" type="checkbox" />' );
		foreach ( $this->field_keys as $k ) {
			$cols[ $k ] = esc_html( $this->sanitize_label_for_column( $k, $this->field_labels[ $k ] ) );
		}
		$cols['submitted_on'] = __( 'Submitted On', 'narrative-forms' );
		$cols['actions']      = __( 'Actions', 'narrative-forms' );
		return $cols;
	}

	public function get_sortable_columns() {
		return array(
			'submitted_on' => array( 'submitted_on', true ),
		);
	}

	private function discover_field_columns() {
		if ( empty( $this->question_map ) ) {
			$question_map = get_post_meta( $this->form_id, '_nrfm_fields_question_map', true );
			$this->question_map = is_array( $question_map ) ? $question_map : array();
		}
		if ( empty( $this->choice_map ) ) {
			$choice_map = get_post_meta( $this->form_id, '_nrfm_fields_choice_map', true );
			$this->choice_map = is_array( $choice_map ) ? $choice_map : array();
		}
		if ( empty( $this->raw_fields ) ) {
			$raw_fields = get_post_meta( $this->form_id, '_nrfm_fields_raw_display', true );
			$this->raw_fields = is_array( $raw_fields ) ? array_flip( $raw_fields ) : array();
		}

		// Cache columns to avoid repeated JSON scans
		$cache_key = 'nrfm_cols_' . $this->form_id;
		$labels    = get_transient( $cache_key );
		if ( $labels === false || ! is_array( $labels ) ) {
			// Prefer schema stored on form meta if available
			$schema        = get_post_meta( $this->form_id, '_nrfm_fields_schema', true );
			$schema_labels = get_post_meta( $this->form_id, '_nrfm_fields_labels', true );
			$question_map  = $this->question_map;
			$raw_fields    = $this->raw_fields;
			if ( is_array( $schema ) && ! empty( $schema ) ) {
				$labels = array();
				foreach ( $schema as $k ) {
					if ( count( $labels ) >= 12 ) {
						break;
					}
					$labels[ $k ] = ( empty( $raw_fields[ $k ] ) && isset( $question_map[ $k ] ) && $question_map[ $k ] !== '' )
						? $question_map[ $k ]
						: ( isset( $schema_labels[ $k ] ) && $schema_labels[ $k ] !== ''
							? $schema_labels[ $k ]
							: ucfirst( str_replace( '_', ' ', $k ) ) );
				}
			}
		}
		if ( $labels === false || ! is_array( $labels ) || empty( $labels ) ) {
			global $wpdb;
			$table_name = nrfm_get_valid_table( 'submissions' );
			if ( $table_name === '' ) {
				$this->field_keys   = array();
				$this->field_labels = array();
				return;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT data FROM %i WHERE form_id = %d ORDER BY submitted_at DESC LIMIT 50',
					$table_name,
					$this->form_id
				)
			);
				$labels = array();
				foreach ( $rows as $row ) {
					$data = json_decode( $row->data, true );
					if ( is_array( $data ) ) {
						foreach ( $data as $k => $v ) {
							if ( strpos( $k, 'nrfm_' ) === 0 || strpos( $k, '_' ) === 0 ) {
								continue;
							}
							if ( ! isset( $labels[ $k ] ) ) {
								$labels[ $k ] = ucfirst( str_replace( '_', ' ', $k ) ); }
							if ( count( $labels ) >= 12 ) {
								break 2;
							}
						}
					}
				}
				set_transient( $cache_key, $labels, DAY_IN_SECONDS );
		}
		$this->field_keys   = array_slice( array_keys( $labels ), 0, 12 );
		// Final guard: sanitize labels for columns to avoid concatenated option phrases
		foreach ( $labels as $k => $v ) {
			$labels[ $k ] = $this->sanitize_label_for_column( $k, $v );
		}
		$this->field_labels = $labels;
	}

	private function sanitize_label_for_column( $key, $label ) {
		$label = (string) $label;
		$label = trim( preg_replace( '/\s+/', ' ', $label ) );
		// If label is empty or looks like option text, fall back to key-derived label
		$wordCount = ( $label === '' ) ? 0 : count( explode( ' ', $label ) );
		if ( $label === '' || preg_match( '/\b(yes|no|male|female|option|select)\b/i', $label ) || $wordCount > 16 || strlen( $label ) > 140 ) {
			return ucfirst( str_replace( '_', ' ', $key ) );
		}
		return $label;
	}

	public function prepare_items() {
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$search       = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin search; page nonce is verified upstream
		$search_id    = ( $search !== '' && ctype_digit( $search ) ) ? (int) $search : 0;
		$orderby      = 'submitted_at';
		$order        = 'DESC';

		// Discover columns
		$this->discover_field_columns();
		// Set up column headers so WP_List_Table renders the table
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Fetch items
		global $wpdb;
		$table_name = nrfm_get_valid_table( 'submissions' );
		if ( $table_name === '' ) {
			$this->items = array();
			return;
		}

		// Count query using %i for table identifier (WP 6.2+)
		if ( $search === '' ) {
			$cache_key = 'nrfm_subs_total_' . $this->form_id;
			$cached_total = wp_cache_get( $cache_key, 'nrfm' );
			if ( $cached_total !== false ) {
				$total_items = (int) $cached_total;
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$total_items = (int) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM %i WHERE form_id = %d',
						$table_name,
						$this->form_id
					)
				);
				wp_cache_set( $cache_key, $total_items, 'nrfm', 60 );
			}
		} else {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$count_sql  = 'SELECT COUNT(*) FROM %i WHERE form_id = %d AND (data LIKE %s OR ip_address LIKE %s';
			$count_args = array( $table_name, $this->form_id, $like, $like );
			if ( $search_id > 0 ) {
				$count_sql  .= ' OR id = %d';
				$count_args[] = $search_id;
			}
			$count_sql .= ')';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total_items = (int) $wpdb->get_var(
				$wpdb->prepare( $count_sql, $count_args )
			);
		}

		$current_page = max( 1, (int) $current_page );
		$offset       = max( 0, ( $current_page - 1 ) * $per_page );

		// Whitelist order column
		$orderby   = in_array( $orderby, array( 'submitted_at', 'id' ), true ) ? $orderby : 'submitted_at';
		$order     = in_array( strtoupper( $order ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $order ) : 'DESC';
		$order_col = ( $orderby === 'id' ) ? 'id' : 'submitted_at';

		// Branch queries for ASC/DESC and search to avoid any string interpolation
		if ( $search === '' ) {
			if ( $order === 'ASC' ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT id, submitted_at, data FROM %i WHERE form_id = %d ORDER BY %i ASC LIMIT %d OFFSET %d',
						$table_name,
						$this->form_id,
						$order_col,
						$per_page,
						$offset
					)
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT id, submitted_at, data FROM %i WHERE form_id = %d ORDER BY %i DESC LIMIT %d OFFSET %d',
						$table_name,
						$this->form_id,
						$order_col,
						$per_page,
						$offset
					)
				);
			}
		} else {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$base_sql  = 'SELECT id, submitted_at, data FROM %i WHERE form_id = %d AND (data LIKE %s OR ip_address LIKE %s';
			$base_args = array( $table_name, $this->form_id, $like, $like );
			if ( $search_id > 0 ) {
				$base_sql  .= ' OR id = %d';
				$base_args[] = $search_id;
			}
			$base_sql .= ') ORDER BY %i ' . ( $order === 'ASC' ? 'ASC' : 'DESC' ) . ' LIMIT %d OFFSET %d';
			$base_args[] = $order_col;
			$base_args[] = $per_page;
			$base_args[] = $offset;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare( $base_sql, $base_args )
			);
		}

		$items = array();
		foreach ( $rows as $row ) {
			// Lazy decode: only decode when printing in column_default
			$items[] = array(
				'id'           => (int) $row->id,
				'fields_raw'   => $row->data,
				'submitted_at' => $row->submitted_at,
			);
		}
		$this->items = $items;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	// Render without duplicate footer/header and bottom bulk actions
	public function display() {
		$this->display_tablenav( 'top' );
		// Render using standard WP list table structure
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead>';
		$this->print_column_headers( 'head' );
		echo '</thead>';
		echo '<tbody id="the-list"' . ( $this->_args['singular'] ? ' data-wp-lists="list:' . esc_attr( $this->_args['singular'] ) . '"' : '' ) . '>';
		$this->display_rows_or_placeholder();
		echo '</tbody>';
		// No footer to avoid duplicated controls
		echo '</table>';
		// Intentionally skip bottom tablenav to avoid duplication
	}
}
