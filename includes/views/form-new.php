<?php
// Prevent direct access
defined( 'ABSPATH' ) || exit;
?>

<div class="wrap nrfm-wrap">
	<p class="nrfm-breadcrumb">
		<?php esc_html_e( 'You are here:', 'narrative-forms' ); ?> 
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nrfm-forms' ) ); ?>"><?php esc_html_e( 'Narrative Forms', 'narrative-forms' ); ?></a> 
		&rsaquo; <?php esc_html_e( 'Add New Form', 'narrative-forms' ); ?>
	</p>
	
	<h1><?php esc_html_e( 'Add New Form', 'narrative-forms' ); ?></h1>
	
	<form method="post" class="nrfm-new-form">
		<?php wp_nonce_field( 'nrfm_admin_action' ); ?>
		<input type="hidden" name="nrfm_action" value="create_form">
		
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="form-title"><?php esc_html_e( 'Form Title', 'narrative-forms' ); ?></label>
				</th>
				<td>
					<input type="text" name="form_title" id="form-title" class="large-text" 
							placeholder="<?php esc_attr_e( 'Your form title...', 'narrative-forms' ); ?>" required>
				</td>
			</tr>
		</table>
		
		<p class="submit">
			<button type="submit" class="button button-primary button-large">
				<?php esc_html_e( 'Create Form', 'narrative-forms' ); ?>
			</button>
		</p>
	</form>
	
	<div class="nrfm-help">
		<p><strong><?php esc_html_e( 'Looking for help?', 'narrative-forms' ); ?></strong></p>
		<p><?php echo esc_html__( 'Build forms with HTML or add fields using the buttons on the editor.', 'narrative-forms' ); ?></p>
	</div>
</div>