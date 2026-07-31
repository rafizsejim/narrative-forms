<?php
// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Narrative Forms Add-ons hub (Free).
 *
 * Presents optional paid add-ons (currently Frontend Submissions) and gives each
 * installed add-on a place to render its own panel, e.g. a license activate/
 * deactivate form, via the nrfm_addons_screen action. Replaces the old "Go PRO"
 * upsell, which marketed features that are now free.
 */
class NRFM_Addons_Screen {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	public function add_menu() {
		add_submenu_page(
			'nrfm-forms',
			esc_html__( 'Add-ons', 'narrative-forms' ),
			esc_html__( 'Add-ons', 'narrative-forms' ),
			'manage_options',
			'nrfm-forms-addons',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_styles( $hook ) {
		if ( strpos( (string) $hook, 'nrfm-forms-addons' ) === false ) {
			return;
		}
		$css = '
		.nrfm-addons-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:14px; margin-top:16px; max-width:920px; }
		.nrfm-addons-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:18px; }
		.nrfm-addons-card h2 { margin:0 0 8px; font-size:15px; display:flex; align-items:center; gap:8px; }
		.nrfm-addons-card p { margin:0 0 12px; color:#4b5563; }
		.nrfm-addons-chip { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:600; background:#e5e7eb; color:#374151; }
		.nrfm-addons-chip.active { background:#d1fae5; color:#065f46; }
		.nrfm-addons-cta { margin:0; }
		';
		wp_add_inline_style( 'nrfm-admin', $css );
	}

	public function render_page() {
		$fs_active = defined( 'NRFM_FS_VERSION' );
		?>
		<div class="wrap nrfm-wrap nrfm-addons-wrap">
			<h1><?php esc_html_e( 'Narrative Forms Add-ons', 'narrative-forms' ); ?></h1>
			<p class="description" style="max-width:720px"><?php esc_html_e( 'Extend Narrative Forms with optional add-ons. Everything in the core plugin, including the AI form builder, conditional logic, notifications, and save & resume, is free.', 'narrative-forms' ); ?></p>

			<div class="nrfm-addons-grid">
				<div class="nrfm-addons-card">
					<h2>
						<?php esc_html_e( 'Frontend Submissions (Views)', 'narrative-forms' ); ?>
						<?php if ( $fs_active ) : ?>
							<span class="nrfm-addons-chip active"><?php esc_html_e( 'Installed', 'narrative-forms' ); ?></span>
						<?php endif; ?>
					</h2>
					<p><?php esc_html_e( 'Publish your form submissions as public content: directories, testimonial walls, galleries, job boards, and more, with search, pagination, single pages, per-field privacy, and approval moderation.', 'narrative-forms' ); ?></p>
					<?php if ( ! $fs_active ) : ?>
						<p class="nrfm-addons-cta">
							<a class="button button-primary" target="_blank" rel="noopener" href="https://narrative-forms.com/frontend-submissions"><?php esc_html_e( 'Get Frontend Submissions', 'narrative-forms' ); ?></a>
						</p>
					<?php endif; ?>
				</div>
			</div>

			<?php
			/**
			 * Installed add-ons render their own panels here, e.g. a license
			 * activate/deactivate form. Fired once on the Add-ons screen.
			 */
			do_action( 'nrfm_addons_screen' );
			?>
		</div>
		<?php
	}
}
