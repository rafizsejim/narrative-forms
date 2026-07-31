<?php

defined( 'ABSPATH' ) || exit;

$GLOBALS['post']     = new WP_Post( (object) array( 'filter' => 'raw' ) );
$GLOBALS['wp_query'] = new WP_Query();

$form_id = isset( $form_id ) ? intval( $form_id ) : 0;
$form    = new NRFM_Form( $form_id );

if ( ! $form->exists() ) {
	status_header( 404 );
	echo esc_html__( 'Form not found.', 'narrative-forms' );
	return;
}

show_admin_bar( false );
add_filter( 'pre_handle_404', '__return_true' );
remove_all_actions( 'template_redirect' );

// Ensure core frontend assets load by making the share page look like a shortcode page.
$GLOBALS['post']->post_content = '[nrfm_form id="' . $form_id . '"]';
$GLOBALS['post']->post_title   = $form->get_title();

?><!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
	<?php
	$theme_ver = wp_get_theme()->get( 'Version' );
	wp_enqueue_style( 'nrfm-share-theme', get_stylesheet_uri(), array(), $theme_ver );
	wp_enqueue_scripts();
	wp_print_styles();
	wp_print_head_scripts();
	wp_custom_css_cb();
	?>
	<style type="text/css">
		/* Emitted last in <head> so it overrides the theme's page background (same approach
		   as core form-preview.php — see AUDIT U4). Keeps the hosted form on a neutral canvas. */
		html, body { background: #fff !important; margin: 0; padding: 24px; }
		.nrfm-form-wrapper { margin: 0; }
	</style>
</head>
<body class="page-template-default page nrfm-share-page">
	<div class="nrfm-share-wrap">
		<?php echo do_shortcode( '[nrfm_form id="' . $form_id . '"]' ); ?>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
