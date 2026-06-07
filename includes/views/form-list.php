<?php
// Prevent direct access
defined( 'ABSPATH' ) || exit;
?>

<div class="wrap nrfm-wrap">
	<?php
	$nrfm_trash_count = wp_count_posts( 'nrfm_form' );
	$nrfm_all_total  = 0;
	$nrfm_trash_total = 0;
	if ( $nrfm_trash_count ) {
		$nrfm_trash_total = isset( $nrfm_trash_count->trash ) ? intval( $nrfm_trash_count->trash ) : 0;
		foreach ( (array) $nrfm_trash_count as $status => $count ) {
			if ( $status === 'trash' ) {
				continue;
			}
			$nrfm_all_total += (int) $count;
		}
	}
	?>
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Forms', 'narrative-forms' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=nrfm-forms-new' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New Form', 'narrative-forms' ); ?>
	</a>
	
	<hr class="wp-header-end">
	
	<?php if ( ! empty( $_GET['deleted'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nrfm_deleted' ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Form deleted successfully.', 'narrative-forms' ); ?></p>
		</div>
	<?php endif; ?>
	
	<ul class="subsubsub">
		<li class="all"><a href="<?php echo esc_url( admin_url( 'admin.php?page=nrfm-forms' ) ); ?>" class="current">
			<?php esc_html_e( 'All', 'narrative-forms' ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( $nrfm_all_total ) ); ?>)</span></a> |
		</li>
		<?php
		$nrfm_trash_url   = admin_url( 'admin.php?page=nrfm-forms&status=trash' );
		?>
		<li class="trash"><a href="<?php echo esc_url( $nrfm_trash_url ); ?>"><?php esc_html_e( 'Trash', 'narrative-forms' ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( $nrfm_trash_total ) ); ?>)</span></a></li>
	</ul>

	<?php
		$nrfm_status   = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'any';
		$nrfm_is_trash = ( $nrfm_status === 'trash' );
	?>
	
	<form method="get">
		<input type="hidden" name="page" value="nrfm-forms">
		<?php if ( $nrfm_is_trash ) : ?>
			<input type="hidden" name="status" value="trash">
		<?php endif; ?>
		<p class="search-box">
			<label class="screen-reader-text" for="form-search-input"><?php esc_html_e( 'Search Forms:', 'narrative-forms' ); ?></label>
		<?php $nrfm_search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; ?>
			<input type="search" id="form-search-input" name="s" value="<?php echo esc_attr( $nrfm_search ); ?>">
			<input type="submit" id="search-submit" class="button" value="<?php echo esc_attr__( 'Search Forms', 'narrative-forms' ); ?>">
		</p>
	</form>
	
	<form method="post">
			<?php wp_nonce_field( 'nrfm_admin_action' ); ?>
			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'narrative-forms' ); ?></label>
					<select name="action" id="bulk-action-selector-top">
						<option value="-1"><?php esc_html_e( 'Bulk actions', 'narrative-forms' ); ?></option>
						<?php if ( $nrfm_is_trash ) : ?>
							<option value="restore"><?php esc_html_e( 'Restore', 'narrative-forms' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Delete Permanently', 'narrative-forms' ); ?></option>
						<?php else : ?>
							<option value="trash"><?php esc_html_e( 'Move to Trash', 'narrative-forms' ); ?></option>
						<?php endif; ?>
					</select>
					<input type="submit" id="doaction" class="button action" value="<?php echo esc_attr__( 'Apply', 'narrative-forms' ); ?>">
				</div>
				<div class="tablenav-pages">
					<?php /* translators: %s is the number of forms */ ?>
					<?php $nrfm_total_items = isset( $nrfm_total ) ? $nrfm_total : count( $nrfm_forms ); ?>
					<span class="displaying-num"><?php echo esc_html( sprintf( _n( '%s item', '%s items', $nrfm_total_items, 'narrative-forms' ), number_format_i18n( $nrfm_total_items ) ) ); ?></span>
					<?php if ( ! empty( $nrfm_pages ) && $nrfm_pages > 1 ) : ?>
						<?php
						$base_args = array(
							'page'   => 'nrfm-forms',
							'status' => $nrfm_is_trash ? 'trash' : null,
							's'      => $nrfm_search,
						);
						$base_args = array_filter( $base_args, 'strlen' );
						$base = add_query_arg( $base_args, admin_url( 'admin.php' ) );
						$pagination = paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%', $base ),
							'format'    => '',
							'current'   => isset( $paged ) ? (int) $paged : 1,
							'total'     => (int) $nrfm_pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'type'      => 'array',
						) );
						?>
						<?php if ( is_array( $pagination ) ) : ?>
							<span class="pagination-links">
								<?php foreach ( $pagination as $link ) : ?>
									<?php echo $link; ?>
								<?php endforeach; ?>
							</span>
						<?php endif; ?>
					<?php endif; ?>
				</div>
				<br class="clear">
			</div>
			
			<table class="wp-list-table widefat fixed striped table-view-list posts">
				<thead>
					<tr>
						<td id="cb" class="manage-column column-cb check-column">
							<label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e( 'Select All', 'narrative-forms' ); ?></label>
							<input id="cb-select-all-1" type="checkbox">
						</td>
						<th scope="col" class="manage-column column-title column-primary sortable desc">
							<a href="#"><span><?php esc_html_e( 'Form', 'narrative-forms' ); ?></span><span class="sorting-indicator"></span></a>
						</th>
						<th scope="col" class="manage-column column-shortcode"><?php esc_html_e( 'Shortcode', 'narrative-forms' ); ?></th>
						<th scope="col" class="manage-column column-submissions num"><?php esc_html_e( 'Submissions', 'narrative-forms' ); ?></th>
					</tr>
				</thead>
				<tbody id="the-list">
					<?php
					if ( empty( $nrfm_forms ) ) {
						if ( $nrfm_is_trash ) {
							echo '<tr class="no-items"><td class="colspanchange" colspan="4">' . esc_html__( 'No forms in Trash.', 'narrative-forms' ) . '</td></tr>';
						} else {
							$nrfm_new_url = admin_url( 'admin.php?page=nrfm-forms-new' );
							echo '<tr class="no-items"><td class="colspanchange" colspan="4">' .
								esc_html__( 'No forms found.', 'narrative-forms' ) . ' ' .
								'<a href="' . esc_url( $nrfm_new_url ) . '">' . esc_html__( 'Create your first form', 'narrative-forms' ) . '</a>' .
								'</td></tr>';
						}
					}
					foreach ( $nrfm_forms as $nrfm_form_post ) :
						$nrfm_form_obj          = new NRFM_Form( $nrfm_form_post->ID );
						$nrfm_submissions_count = function_exists( 'nrfm_get_submissions_count' ) ? nrfm_get_submissions_count( $nrfm_form_post->ID ) : 0;
						$nrfm_settings          = $nrfm_form_obj->get_settings();
						?>
						<tr class="iedit">
							<th scope="row" class="check-column">
								<input type="checkbox" name="form[]" value="<?php echo esc_attr( $nrfm_form_post->ID ); ?>">
							</th>
							<td class="title column-title has-row-actions column-primary" data-colname="Title">
								<strong>
								<?php
								$nrfm_edit_url = add_query_arg(
									array(
										'page'     => 'nrfm-forms',
										'action'   => 'edit',
										'form'     => intval( $nrfm_form_post->ID ),
										'_wpnonce' => wp_create_nonce( 'edit_form' ),
									),
									admin_url( 'admin.php' )
								);
								?>
									<a class="row-title" href="<?php echo esc_url( $nrfm_edit_url ); ?>">
									<?php echo esc_html( $nrfm_form_post->post_title ); ?>
									</a>
								</strong>
								<div class="row-actions">
								<?php if ( $nrfm_is_trash ) : ?>
										<span class="restore">
											<?php
											$nrfm_restore_url = add_query_arg(
												array(
													'page' => 'nrfm-forms',
													'action' => 'restore_form',
													'form' => intval( $nrfm_form_post->ID ),
													'status' => 'trash',
													'_wpnonce' => wp_create_nonce( 'restore_form' ),
												),
												admin_url( 'admin.php' )
											);
											?>
											<a href="<?php echo esc_url( $nrfm_restore_url ); ?>"><?php esc_html_e( 'Restore', 'narrative-forms' ); ?></a>
										</span> |
										<span class="delete">
											<?php
											$nrfm_del_url = add_query_arg(
												array(
													'page' => 'nrfm-forms',
													'action' => 'delete_form_perm',
													'form' => intval( $nrfm_form_post->ID ),
													'status' => 'trash',
													'_wpnonce' => wp_create_nonce( 'delete_form_perm' ),
												),
												admin_url( 'admin.php' )
											);
											?>
											<a href="<?php echo esc_url( $nrfm_del_url ); ?>" class="submitdelete"><?php esc_html_e( 'Delete Permanently', 'narrative-forms' ); ?></a>
										</span>
									<?php else : ?>
										<span class="fields">
											<?php
											$nrfm_fields_url = add_query_arg(
												array(
													'page' => 'nrfm-forms',
													'action' => 'edit',
													'form' => intval( $nrfm_form_post->ID ),
													'_wpnonce' => wp_create_nonce( 'edit_form' ),
												),
												admin_url( 'admin.php' )
											);
											?>
											<a href="<?php echo esc_url( $nrfm_fields_url ); ?>"><?php esc_html_e( 'Fields', 'narrative-forms' ); ?></a>
										</span> |
										<span class="messages">
											<?php
											$nrfm_messages_url = add_query_arg(
												array(
													'page' => 'nrfm-forms',
													'action' => 'edit',
													'form' => intval( $nrfm_form_post->ID ),
													'tab'  => 'messages',
													'_wpnonce' => wp_create_nonce( 'edit_form' ),
												),
												admin_url( 'admin.php' )
											);
											?>
											<a href="<?php echo esc_url( $nrfm_messages_url ); ?>"><?php esc_html_e( 'Messages', 'narrative-forms' ); ?></a>
										</span> |
										<span class="settings">
											<?php
											$nrfm_settings_url = add_query_arg(
												array(
													'page' => 'nrfm-forms',
													'action' => 'edit',
													'form' => intval( $nrfm_form_post->ID ),
													'tab'  => 'settings',
													'_wpnonce' => wp_create_nonce( 'edit_form' ),
												),
												admin_url( 'admin.php' )
											);
											?>
											<a href="<?php echo esc_url( $nrfm_settings_url ); ?>"><?php esc_html_e( 'Settings', 'narrative-forms' ); ?></a>
										</span> |
										<span class="actions">
											<?php
											$nrfm_actions_url = add_query_arg(
												array(
													'page' => 'nrfm-forms',
													'action' => 'edit',
													'form' => intval( $nrfm_form_post->ID ),
													'tab'  => 'actions',
													'_wpnonce' => wp_create_nonce( 'edit_form' ),
												),
												admin_url( 'admin.php' )
											);
											?>
											<a href="<?php echo esc_url( $nrfm_actions_url ); ?>"><?php esc_html_e( 'Actions', 'narrative-forms' ); ?></a>
										</span>
										<?php
										if ( $nrfm_settings['save_submissions'] ) :
											?>
											|
										<span class="submissions">
											<?php
											$nrfm_subs_url = add_query_arg(
												array(
													'page' => 'nrfm-forms',
													'action' => 'edit',
													'form' => intval( $nrfm_form_post->ID ),
													'tab'  => 'submissions',
													'_wpnonce' => wp_create_nonce( 'edit_form' ),
												),
												admin_url( 'admin.php' )
											);
											?>
											<a href="<?php echo esc_url( $nrfm_subs_url ); ?>"><?php esc_html_e( 'Submissions', 'narrative-forms' ); ?></a>
										</span>
										<?php endif; ?>
										<?php
										$nrfm_trash_action_url = add_query_arg(
											array(
												'page'     => 'nrfm-forms',
												'action'   => 'trash_form',
												'form'     => intval( $nrfm_form_post->ID ),
												'_wpnonce' => wp_create_nonce( 'trash_form' ),
											),
											admin_url( 'admin.php' )
										);
										?>
										| <span class="trash"><a href="<?php echo esc_url( $nrfm_trash_action_url ); ?>" class="submitdelete"><?php esc_html_e( 'Trash', 'narrative-forms' ); ?></a></span>
									<?php endif; ?>
								</div>
								<button type="button" class="toggle-row">
									<span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'narrative-forms' ); ?></span>
								</button>
							</td>
							<td class="shortcode column-shortcode" data-colname="Shortcode">
								<input type="text" class="code" value='[nrfm_form slug="<?php echo esc_attr( $nrfm_form_post->post_name ); ?>"]' readonly onfocus="this.select();">
							</td>
							<td class="submissions column-submissions num" data-colname="Submissions">
							<?php if ( $nrfm_submissions_count > 0 ) : ?>
									<?php
									$nrfm_subs_url2 = add_query_arg(
										array(
											'page'     => 'nrfm-forms',
											'action'   => 'edit',
											'form'     => intval( $nrfm_form_post->ID ),
											'tab'      => 'submissions',
											'_wpnonce' => wp_create_nonce( 'edit_form' ),
										),
										admin_url( 'admin.php' )
									);
									?>
									<a href="<?php echo esc_url( $nrfm_subs_url2 ); ?>">
										<?php echo esc_html( number_format_i18n( $nrfm_submissions_count ) ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html( number_format_i18n( $nrfm_submissions_count ) ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<td class="manage-column column-cb check-column">
							<label class="screen-reader-text" for="cb-select-all-2"><?php esc_html_e( 'Select All', 'narrative-forms' ); ?></label>
							<input id="cb-select-all-2" type="checkbox">
						</td>
						<th scope="col" class="manage-column column-title column-primary sortable desc">
							<a href="#"><span><?php esc_html_e( 'Form', 'narrative-forms' ); ?></span><span class="sorting-indicator"></span></a>
						</th>
						<th scope="col" class="manage-column column-shortcode"><?php esc_html_e( 'Shortcode', 'narrative-forms' ); ?></th>
						<th scope="col" class="manage-column column-submissions num"><?php esc_html_e( 'Submissions', 'narrative-forms' ); ?></th>
					</tr>
				</tfoot>
			</table>
			
			<div class="tablenav bottom">
				<div class="alignleft actions bulkactions">
					<label for="bulk-action-selector-bottom" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'narrative-forms' ); ?></label>
					<select name="action2" id="bulk-action-selector-bottom">
						<option value="-1"><?php esc_html_e( 'Bulk actions', 'narrative-forms' ); ?></option>
						<?php if ( $nrfm_is_trash ) : ?>
							<option value="restore"><?php esc_html_e( 'Restore', 'narrative-forms' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Delete Permanently', 'narrative-forms' ); ?></option>
						<?php else : ?>
							<option value="trash"><?php esc_html_e( 'Move to Trash', 'narrative-forms' ); ?></option>
						<?php endif; ?>
					</select>
					<input type="submit" id="doaction2" class="button action" value="<?php echo esc_attr__( 'Apply', 'narrative-forms' ); ?>">
				</div>
				<div class="tablenav-pages">
					<?php /* translators: %s is the number of forms */ ?>
					<?php $nrfm_total_items = isset( $nrfm_total ) ? $nrfm_total : count( $nrfm_forms ); ?>
					<span class="displaying-num"><?php echo esc_html( sprintf( _n( '%s item', '%s items', $nrfm_total_items, 'narrative-forms' ), number_format_i18n( $nrfm_total_items ) ) ); ?></span>
					<?php if ( ! empty( $nrfm_pages ) && $nrfm_pages > 1 ) : ?>
						<?php
						$base_args = array(
							'page'   => 'nrfm-forms',
							'status' => $nrfm_is_trash ? 'trash' : null,
							's'      => $nrfm_search,
						);
						$base_args = array_filter( $base_args, 'strlen' );
						$base = add_query_arg( $base_args, admin_url( 'admin.php' ) );
						$pagination = paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%', $base ),
							'format'    => '',
							'current'   => isset( $paged ) ? (int) $paged : 1,
							'total'     => (int) $nrfm_pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'type'      => 'array',
						) );
						?>
						<?php if ( is_array( $pagination ) ) : ?>
							<span class="pagination-links">
								<?php foreach ( $pagination as $link ) : ?>
									<?php echo $link; ?>
								<?php endforeach; ?>
							</span>
						<?php endif; ?>
					<?php endif; ?>
				</div>
				<br class="clear">
			</div>
		</form>
</div>