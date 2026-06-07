<?php
// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Narrative Forms – PRO Upsell screen (Free only)
 */
class NRFM_Pro_Upsell {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	public function add_menu() {
		// Add a "Go PRO" submenu under Narrative Forms (namespaced slug)
		add_submenu_page(
			'nrfm-forms',
			esc_html__( 'Go PRO', 'narrative-forms' ),
			esc_html__( 'Go PRO', 'narrative-forms' ),
			'manage_options',
			'nrfm-forms-pro',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_styles( $hook ) {
		// Highlight the submenu link in green and style the upsell page
		$css = '/* Narrative Forms PRO Upsell */
		#adminmenu .wp-submenu a[href$="page=nrfm-forms-pro"] { color: #059669 !important; font-weight: 600; }
		.nrfm-upsell-wrap h1 .badge { display:inline-block; margin-left:8px; padding:2px 6px; border-radius:999px; background:#10b981; color:#fff; font-size:11px; letter-spacing:.2px; }
		.nrfm-upsell-hero { background:#ecfdf5; border:1px solid #a7f3d0; padding:18px; border-radius:8px; margin:16px 0; }
		.nrfm-upsell-hero h2 { margin:0 0 8px; color:#065f46; }
		.nrfm-upsell-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:14px; margin-top:14px; }
		.nrfm-upsell-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px; }
		.nrfm-upsell-card h3 { margin:0 0 6px; font-size:14px; }
		.nrfm-upsell-card p { margin:0; color:#4b5563; }
		.nrfm-chip { display:inline-block; margin-left:8px; padding:2px 6px; border-radius:999px; background:#e5e7eb; color:#111; font-size:11px; }
		.nrfm-chip.soon { background:#fef3c7; color:#92400e; }
		.nrfm-upsell-cta { margin-top:16px; }
		.nrfm-upsell-cta .button-primary { background:#059669; border-color:#059669; box-shadow:none; }
		.nrfm-upsell-cta .button-primary:hover { background:#047857; border-color:#047857; }
		/* Better secondary button (See full feature list) */
		.nrfm-upsell-cta .button:not(.button-primary) {
			border-color:#10b981;
			color:#065f46;
			background:#ffffff;
			box-shadow:none;
			height:auto;
			padding:8px 14px;
			border-radius:6px;
		}
		.nrfm-upsell-cta .button:not(.button-primary):hover {
			background:#ecfdf5;
			border-color:#059669;
			color:#065f46;
		}
		';
		wp_add_inline_style( 'nrfm-admin', $css );
	}

	public function render_page() {
		?>
		<div class="wrap nrfm-wrap nrfm-upsell-wrap">
			<h1><?php esc_html_e( 'Narrative Forms PRO', 'narrative-forms' ); ?><span class="badge"><?php esc_html_e( 'Recommended', 'narrative-forms' ); ?></span></h1>
			<div class="nrfm-upsell-hero">
				<h2><?php esc_html_e( 'Unlock powerful features for serious forms', 'narrative-forms' ); ?></h2>
				<p><?php esc_html_e( 'Go beyond simple submissions with conditional logic, access control, scheduling, webhooks, and a REST API — all while keeping the same lightweight, HTML-first workflow you love.', 'narrative-forms' ); ?></p>
				<div class="nrfm-upsell-cta">
					<a class="button button-primary button-hero" target="_blank" href="https://narrative-forms.com/pro"><?php esc_html_e( 'Upgrade to PRO', 'narrative-forms' ); ?></a>
					<a class="button" target="_blank" href="https://narrative-forms.com/pro#features"><?php esc_html_e( 'See full feature list', 'narrative-forms' ); ?></a>
				</div>
			</div>

			<div class="nrfm-upsell-grid">
				<div class="nrfm-upsell-card">
					<h3><?php esc_html_e( 'Conditional Logic', 'narrative-forms' ); ?></h3>
					<p><?php esc_html_e( 'Show or hide fields based on what users enter. Build smarter forms without code.', 'narrative-forms' ); ?></p>
				</div>
				<div class="nrfm-upsell-card">
					<h3><?php esc_html_e( 'Require Login', 'narrative-forms' ); ?></h3>
					<p><?php esc_html_e( 'Restrict submissions to logged-in users and prefill their details automatically.', 'narrative-forms' ); ?></p>
				</div>
				<div class="nrfm-upsell-card">
					<h3><?php esc_html_e( 'Schedule Windows', 'narrative-forms' ); ?></h3>
					<p><?php esc_html_e( 'Open and close a form automatically on set dates, with your own messages.', 'narrative-forms' ); ?></p>
				</div>
				<div class="nrfm-upsell-card">
					<h3><?php esc_html_e( 'Save & Resume', 'narrative-forms' ); ?></h3>
					<p><?php esc_html_e( 'Let visitors save their progress and finish later from a private link.', 'narrative-forms' ); ?></p>
				</div>
				<div class="nrfm-upsell-card">
					<h3><?php esc_html_e( 'Direct Form Links', 'narrative-forms' ); ?></h3>
					<p><?php esc_html_e( 'Share a hosted form at its own URL — no page or shortcode required.', 'narrative-forms' ); ?></p>
				</div>
				<div class="nrfm-upsell-card">
					<h3><?php esc_html_e( 'Submission Notifications', 'narrative-forms' ); ?></h3>
					<p><?php esc_html_e( 'Unread badges across the admin so you never miss a new entry.', 'narrative-forms' ); ?></p>
				</div>
				<div class="nrfm-upsell-card">
					<h3><?php esc_html_e( 'Webhook Logs & Retry', 'narrative-forms' ); ?></h3>
					<p><?php esc_html_e( 'See every webhook delivery and automatically retry the ones that fail.', 'narrative-forms' ); ?></p>
				</div>
				<div class="nrfm-upsell-card">
					<h3><?php esc_html_e( 'Webhook Templates', 'narrative-forms' ); ?></h3>
					<p><?php esc_html_e( 'Custom JSON payloads with one-click Discord, Slack, and Teams presets.', 'narrative-forms' ); ?></p>
				</div>
				<div class="nrfm-upsell-card">
					<h3><?php esc_html_e( 'REST API', 'narrative-forms' ); ?></h3>
					<p><?php esc_html_e( 'Read and manage your forms and submissions from anywhere with a secure key.', 'narrative-forms' ); ?></p>
				</div>
				<div class="nrfm-upsell-card">
					<h3><?php esc_html_e( 'Multi‑step', 'narrative-forms' ); ?> <span class="nrfm-chip soon"><?php esc_html_e( 'Coming Soon', 'narrative-forms' ); ?></span></h3>
					<p><?php esc_html_e( 'Split long forms into bite‑sized steps with progress indicators.', 'narrative-forms' ); ?></p>
				</div>
				<div class="nrfm-upsell-card">
					<h3><?php esc_html_e( 'Submission Limits', 'narrative-forms' ); ?> <span class="nrfm-chip soon"><?php esc_html_e( 'Coming Soon', 'narrative-forms' ); ?></span></h3>
					<p><?php esc_html_e( 'Cap total submissions per form or per user, with a custom closed message.', 'narrative-forms' ); ?></p>
				</div>
				<div class="nrfm-upsell-card">
					<h3><?php esc_html_e( 'And more…', 'narrative-forms' ); ?></h3>
					<p><?php esc_html_e( 'Integrations, automation, and templates — with new features on the way.', 'narrative-forms' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}
}
