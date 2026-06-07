<?php
// Prevent direct access
defined( 'ABSPATH' ) || exit;
?>

<div class="wrap nrfm-wrap nrfm-settings-wrap">
	<p class="nrfm-breadcrumb">
		<?php esc_html_e( 'You are here:', 'narrative-forms' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nrfm-forms' ) ); ?>"><?php esc_html_e( 'Narrative Forms', 'narrative-forms' ); ?></a>
		&rsaquo; <?php esc_html_e( 'Settings', 'narrative-forms' ); ?>
	</p>

	<h1><?php esc_html_e( 'Settings', 'narrative-forms' ); ?></h1>

	<?php if ( ! empty( $_GET['saved'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nrfm_saved' ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved successfully.', 'narrative-forms' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'nrfm_admin_action' ); ?>
		<input type="hidden" name="nrfm_action" value="save_settings">

		<h2 class="nrfm-section-title"><?php esc_html_e( 'Appearance & Rendering', 'narrative-forms' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Load Stylesheet?', 'narrative-forms' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="load_stylesheet" value="1" <?php checked( ! empty( $settings['load_stylesheet'] ), true ); ?>>
						<?php esc_html_e( 'Apply basic Narrative Forms stylesheet on the frontend.', 'narrative-forms' ); ?>
					</label>
					<p class="description"><?php echo esc_html__( 'Disable this if your theme or custom CSS fully controls form styles.', 'narrative-forms' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wrapper-tag"><?php esc_html_e( 'Wrapper Tag', 'narrative-forms' ); ?></label></th>
				<td>
					<select name="wrapper_tag" id="wrapper-tag">
						<option value="p" <?php selected( $settings['wrapper_tag'] ?? 'p', 'p' ); ?>>p</option>
						<option value="div" <?php selected( $settings['wrapper_tag'] ?? 'p', 'div' ); ?>>div</option>
						<option value="section" <?php selected( $settings['wrapper_tag'] ?? 'p', 'section' ); ?>>section</option>
						<option value="article" <?php selected( $settings['wrapper_tag'] ?? 'p', 'article' ); ?>>article</option>
						<option value="none" <?php selected( $settings['wrapper_tag'] ?? 'p', 'none' ); ?>><?php esc_html_e( 'None', 'narrative-forms' ); ?></option>
					</select>
					<p class="description"><?php echo esc_html__( 'Select the HTML tag to wrap form fields in.', 'narrative-forms' ); ?></p>
				</td>
			</tr>
		</table>

		<?php if ( has_action( 'nrfm_settings_form_urls' ) ) : ?>
			<h2 class="nrfm-section-title"><?php esc_html_e( 'Form URLs', 'narrative-forms' ); ?></h2>
			<table class="form-table"><?php do_action( 'nrfm_settings_form_urls' ); ?></table>
		<?php endif; ?>

		<h2 class="nrfm-section-title"><?php esc_html_e( 'Form Protection & Captcha', 'narrative-forms' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Honeypot Protection', 'narrative-forms' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="honeypot_enabled" value="1" <?php checked( ! empty( $settings['honeypot_enabled'] ), true ); ?>>
						<?php esc_html_e( 'Enable honeypot spam protection', 'narrative-forms' ); ?>
					</label>
					<p class="description"><?php echo esc_html__( 'Adds an invisible field to forms that bots will fill out, allowing us to identify and block spam submissions.', 'narrative-forms' ); ?></p>
				</td>
			</tr>
			<?php do_action( 'nrfm_settings_form_protection' ); ?>
		</table>

		<?php if ( has_action( 'nrfm_settings_api_integrations' ) ) : ?>
			<h2 class="nrfm-section-title"><?php esc_html_e( 'API & Integrations', 'narrative-forms' ); ?></h2>
			<table class="form-table"><?php do_action( 'nrfm_settings_api_integrations' ); ?></table>
		<?php endif; ?>

		<?php if ( has_action( 'nrfm_settings_after_honeypot' ) ) : ?>
			<h2 class="nrfm-section-title"><?php esc_html_e( 'Additional Settings', 'narrative-forms' ); ?></h2>
			<?php /* Backward compatibility for add-ons still using the legacy hook. */ ?>
			<table class="form-table"><?php do_action( 'nrfm_settings_after_honeypot' ); ?></table>
		<?php endif; ?>

		<h2 class="nrfm-section-title"><?php esc_html_e( 'Advanced Anti‑Spam', 'narrative-forms' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Time Trap (seconds)', 'narrative-forms' ); ?></th>
				<td>
					<input type="number" name="spam_time_trap_seconds" class="small-text" min="0" value="<?php echo esc_attr( $settings['spam_time_trap_seconds'] ?? 3 ); ?>">
					<p class="description"><?php echo esc_html__( 'Minimum seconds a form should remain open before submission. 0 to disable.', 'narrative-forms' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Require Same‑origin Referrer', 'narrative-forms' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="spam_same_origin" value="1" <?php checked( ! empty( $settings['spam_same_origin'] ), true ); ?>>
						<?php esc_html_e( 'Reject submissions where the referrer host is not this site.', 'narrative-forms' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Max Links in Message', 'narrative-forms' ); ?></th>
				<td>
					<input type="number" name="spam_max_links" class="small-text" min="0" value="<?php echo esc_attr( $settings['spam_max_links'] ?? 3 ); ?>">
					<p class="description"><?php echo esc_html__( 'Reject if the message contains more than this many URLs. 0 to disable.', 'narrative-forms' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Rate Limit per IP', 'narrative-forms' ); ?></th>
				<td>
					<label><?php esc_html_e( 'Max', 'narrative-forms' ); ?> <input type="number" name="spam_rate_limit_count" class="small-text" min="0" value="<?php echo esc_attr( $settings['spam_rate_limit_count'] ?? 5 ); ?>"> <?php esc_html_e( 'submissions in', 'narrative-forms' ); ?> <input type="number" name="spam_rate_limit_window_min" class="small-text" min="1" value="<?php echo esc_attr( $settings['spam_rate_limit_window_min'] ?? 10 ); ?>"> <?php esc_html_e( 'minutes (0 to disable).', 'narrative-forms' ); ?></label>
				</td>
			</tr>
		</table>

		<h2 class="nrfm-section-title"><?php esc_html_e( 'Data & Uninstall', 'narrative-forms' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Delete all data on uninstall', 'narrative-forms' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="purge_on_uninstall" value="1" <?php checked( ! empty( $settings['purge_on_uninstall'] ), true ); ?>>
						<?php echo esc_html__( 'If checked, remove forms, submissions, and settings when the plugin is deleted.', 'narrative-forms' ); ?>
					</label>
					<p class="description"><?php echo esc_html__( 'Leave unchecked to keep everything so you can reinstall without losing data.', 'narrative-forms' ); ?></p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Changes', 'narrative-forms' ); ?>
			</button>
		</p>
	</form>

	<div class="nrfm-help">
		<p><strong><?php esc_html_e( 'Looking for help?', 'narrative-forms' ); ?></strong></p>
		<?php /* translators: %s is the URL to the Narrative Forms documentation */ ?>
		<p><?php echo wp_kses_post( sprintf( esc_html__( 'See the %s.', 'narrative-forms' ), '<a href="' . esc_url( 'https://narrative-forms.com/docs' ) . '" target="_blank">' . esc_html__( 'Narrative Forms docs', 'narrative-forms' ) . '</a>' ) ); ?></p>
	</div>
</div>
