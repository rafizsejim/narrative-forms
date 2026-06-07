<?php
defined( 'ABSPATH' ) || exit;

$GLOBALS['post']     = new WP_Post( (object) array( 'filter' => 'raw' ) );
$GLOBALS['wp_query'] = new WP_Query();

$nrfm_form_id = isset( $_GET['nrfm_preview_form'] ) ? intval( wp_unslash( $_GET['nrfm_preview_form'] ) ) : 0;
$nrfm_preview_nonce = isset( $_GET['nrfm_preview_nonce'] ) && ! is_array( $_GET['nrfm_preview_nonce'] )
	? sanitize_text_field( wp_unslash( $_GET['nrfm_preview_nonce'] ) )
	: '';
if ( $nrfm_form_id <= 0 || ! current_user_can( 'edit_posts' ) || empty( $nrfm_preview_nonce ) || ! wp_verify_nonce( $nrfm_preview_nonce, 'nrfm_preview_' . $nrfm_form_id ) ) {
	$nrfm_form_id = 0;
}
$nrfm_form    = new NRFM_Form( $nrfm_form_id );
?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
	<?php
	$nrfm_theme_ver = wp_get_theme()->get( 'Version' );
	wp_enqueue_style( 'nrfm-preview-theme', get_stylesheet_uri(), array(), $nrfm_theme_ver );
	wp_enqueue_scripts();
	wp_print_styles();
	wp_print_head_scripts();
	wp_custom_css_cb();
	?>
	<style type="text/css">
		/* Emitted last in <head> so it overrides the theme's page background. Keeps the
		   preview canvas neutral while the form itself still shows the theme's styling.
		   (wp_add_inline_style printed too early, so the theme's body background won.) */
		html, body { background: #fff !important; width: 100%; max-width: 100%; text-align: left; }
		body::before, body::after, body > *:not(#form-preview) { display: none !important; }
		#form-preview { display: block !important; width: 100%; height: 100%; padding: 20px; border: 0; margin: 0; }
	</style>
</head>
<body class="page-template-default page">
    <div id="form-preview" class="page type-page status-publish hentry post post-content">
        <?php
        if ( $nrfm_form->exists() ) {
            echo do_shortcode( '[nrfm_form id="' . $nrfm_form_id . '"]' );
        }
        ?>
    </div>
	<?php wp_footer(); ?>
</body>
</html>
