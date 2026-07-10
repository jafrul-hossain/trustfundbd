<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * First-run experience: redirects to a Welcome screen right after a
 * single-site activation, falls back to a dismissible notice when the
 * redirect can't fire (bulk/network activation), and tracks the same
 * "getting started" steps shown on the Welcome screen and the main
 * Dashboard so the workflow this plugin promises is visible immediately.
 */
class CHP_Onboarding {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_welcome' ) );
		add_action( 'admin_menu', array( $this, 'register_welcome_page' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_activation_notice' ) );
		add_action( 'admin_post_chp_dismiss_activation_notice', array( $this, 'dismiss_activation_notice' ) );
		add_action( 'admin_post_chp_dismiss_getting_started', array( $this, 'dismiss_getting_started' ) );
		add_filter( 'plugin_action_links_' . CHP_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	public function maybe_redirect_to_welcome() {
		if ( ! get_transient( 'chp_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'chp_activation_redirect' );

		if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=chp-welcome' ) );
		exit;
	}

	public function register_welcome_page() {
		// Hidden (no nav entry) — reached via the activation redirect,
		// the activation notice, or the "Getting Started" link on Settings.
		add_submenu_page( null, __( 'Welcome to Client Handover Pro', 'client-handover-pro' ), '', 'manage_options', 'chp-welcome', array( $this, 'render_welcome_page' ) );
	}

	public function maybe_show_activation_notice() {
		if ( ! get_option( 'chp_show_activation_notice' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && false !== strpos( $screen->id, 'chp-' ) ) {
			return; // Already deep in the plugin's own screens.
		}
		?>
		<div class="notice notice-success is-dismissible chp-activation-notice">
			<p>
				<strong><?php esc_html_e( 'Client Handover Pro is active.', 'client-handover-pro' ); ?></strong>
				<?php esc_html_e( 'Run your first Launch Checklist scan and get this site ready for handover.', 'client-handover-pro' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=chp-welcome' ) ); ?>"><?php esc_html_e( 'Get Started', 'client-handover-pro' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=chp_dismiss_activation_notice' ), 'chp_dismiss_activation_notice' ) ); ?>"><?php esc_html_e( 'Dismiss', 'client-handover-pro' ); ?></a>
			</p>
		</div>
		<?php
	}

	public function dismiss_activation_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'chp_dismiss_activation_notice' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'client-handover-pro' ) );
		}
		delete_option( 'chp_show_activation_notice' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	public function dismiss_getting_started() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'chp_dismiss_getting_started' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'client-handover-pro' ) );
		}
		update_option( 'chp_getting_started_dismissed', true );
		wp_safe_redirect( admin_url( 'admin.php?page=chp-dashboard' ) );
		exit;
	}

	public function plugin_action_links( $links ) {
		$custom = array(
			'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=chp-dashboard' ) ) . '">' . esc_html__( 'Dashboard', 'client-handover-pro' ) . '</a>',
			'welcome'  => '<a href="' . esc_url( admin_url( 'admin.php?page=chp-welcome' ) ) . '">' . esc_html__( 'Getting Started', 'client-handover-pro' ) . '</a>',
		);
		return array_merge( $custom, $links );
	}

	/**
	 * The five milestones of the workflow this plugin sells: scan, fix,
	 * hand the client a simplified dashboard, teach them, and package the
	 * handover. Shared by the Welcome screen and the Dashboard tracker so
	 * they never drift out of sync.
	 */
	public static function steps() {
		$scan     = CHP_Checklist::get_last_scan();
		$settings = CHP_Plugin::get_settings();
		$has_tutorial = (bool) get_posts( array( 'post_type' => 'chp_tutorial', 'posts_per_page' => 1, 'post_status' => 'any', 'fields' => 'ids' ) );
		$approval = get_option( 'chp_client_approval', array( 'status' => 'pending' ) );

		return array(
			array(
				'label' => __( 'Run the Launch Checklist', 'client-handover-pro' ),
				'done'  => ! empty( $scan['scanned_at'] ),
				'url'   => admin_url( 'admin.php?page=chp-dashboard&chp_autorun=1' ),
			),
			array(
				'label' => __( 'Fix flagged issues (score 80%+)', 'client-handover-pro' ),
				'done'  => ( $scan['score'] ?? 0 ) >= 80,
				'url'   => admin_url( 'admin.php?page=chp-checklist' ),
			),
			array(
				'label' => __( 'Generate the Client Dashboard', 'client-handover-pro' ),
				'done'  => ! empty( $settings['client_mode_enabled'] ),
				'url'   => admin_url( 'admin.php?page=chp-client-mode' ),
			),
			array(
				'label' => __( 'Add a tutorial to the Website Guide', 'client-handover-pro' ),
				'done'  => $has_tutorial,
				'url'   => admin_url( 'admin.php?page=chp-tutorials' ),
			),
			array(
				'label' => __( 'Export the handover package', 'client-handover-pro' ),
				'done'  => 'approved' === ( $approval['status'] ?? '' ),
				'url'   => admin_url( 'admin.php?page=chp-handover' ),
			),
		);
	}

	public function render_welcome_page() {
		$settings = CHP_Plugin::get_settings();

		if ( CHP_Helpers::verify_post( 'chp_welcome_save', 'chp_welcome_nonce' ) ) {
			$settings['agency_name']  = sanitize_text_field( wp_unslash( $_POST['agency_name'] ?? $settings['agency_name'] ) );
			$settings['agency_email'] = sanitize_email( wp_unslash( $_POST['agency_email'] ?? $settings['agency_email'] ) );
			$settings['agency_primary'] = sanitize_hex_color( wp_unslash( $_POST['agency_primary'] ?? '' ) ) ?: $settings['agency_primary'];
			CHP_Plugin::update_settings( $settings );
			delete_option( 'chp_show_activation_notice' );
			wp_safe_redirect( admin_url( 'admin.php?page=chp-dashboard&chp_autorun=1' ) );
			exit;
		}

		$steps = self::steps();
		?>
		<div class="wrap chp-wrap chp-welcome">
			<div class="chp-welcome-hero">
				<span class="chp-welcome-hero__eyebrow"><?php esc_html_e( 'Client Handover Pro', 'client-handover-pro' ); ?></span>
				<h1><?php esc_html_e( 'Deliver this website like a professional agency.', 'client-handover-pro' ); ?></h1>
				<p><?php esc_html_e( 'Run a checklist, hand the client a simple dashboard, and export a polished handover package — in a few minutes.', 'client-handover-pro' ); ?></p>
			</div>

			<div class="chp-grid chp-grid--welcome">
				<div class="chp-card">
					<div class="chp-card__label"><?php esc_html_e( 'Quick Setup', 'client-handover-pro' ); ?></div>
					<form method="post">
						<?php wp_nonce_field( 'chp_welcome_save', 'chp_welcome_nonce' ); ?>
						<div class="chp-form-row">
							<label><?php esc_html_e( 'Agency / Your Name', 'client-handover-pro' ); ?></label>
							<input type="text" name="agency_name" class="regular-text" value="<?php echo esc_attr( $settings['agency_name'] ); ?>" />
						</div>
						<div class="chp-form-row">
							<label><?php esc_html_e( 'Support Email', 'client-handover-pro' ); ?></label>
							<input type="email" name="agency_email" class="regular-text" value="<?php echo esc_attr( $settings['agency_email'] ); ?>" />
						</div>
						<div class="chp-form-row">
							<label><?php esc_html_e( 'Brand Color', 'client-handover-pro' ); ?></label>
							<input type="text" name="agency_primary" class="chp-color-field" value="<?php echo esc_attr( $settings['agency_primary'] ); ?>" />
						</div>
						<div class="chp-header-actions">
							<button type="submit" class="chp-btn chp-btn--primary"><?php esc_html_e( 'Save & Run First Scan', 'client-handover-pro' ); ?></button>
							<a class="chp-btn chp-btn--outline" href="<?php echo esc_url( admin_url( 'admin.php?page=chp-dashboard' ) ); ?>"><?php esc_html_e( 'Skip for now', 'client-handover-pro' ); ?></a>
						</div>
					</form>
				</div>

				<div class="chp-card">
					<div class="chp-card__label"><?php esc_html_e( 'Your Workflow', 'client-handover-pro' ); ?></div>
					<ol class="chp-steps">
						<?php foreach ( $steps as $i => $step ) : ?>
							<li class="chp-steps__item <?php echo $step['done'] ? 'is-done' : ''; ?>">
								<span class="chp-steps__marker"><?php echo $step['done'] ? '&#10003;' : (int) ( $i + 1 ); ?></span>
								<a href="<?php echo esc_url( $step['url'] ); ?>"><?php echo esc_html( $step['label'] ); ?></a>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * The compact tracker embedded at the top of the main Dashboard until
	 * every step is done or the user dismisses it.
	 */
	public static function render_dashboard_tracker() {
		if ( get_option( 'chp_getting_started_dismissed' ) ) {
			return;
		}
		$steps = self::steps();
		$remaining = array_filter( $steps, static function ( $s ) {
			return ! $s['done'];
		} );
		if ( empty( $remaining ) ) {
			return;
		}
		?>
		<div class="chp-card chp-getting-started">
			<div class="chp-getting-started__header">
				<div class="chp-card__label"><?php esc_html_e( 'Getting Started', 'client-handover-pro' ); ?></div>
				<a class="chp-link" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=chp_dismiss_getting_started' ), 'chp_dismiss_getting_started' ) ); ?>"><?php esc_html_e( 'Hide', 'client-handover-pro' ); ?></a>
			</div>
			<ol class="chp-steps chp-steps--inline">
				<?php foreach ( $steps as $i => $step ) : ?>
					<li class="chp-steps__item <?php echo $step['done'] ? 'is-done' : ''; ?>">
						<span class="chp-steps__marker"><?php echo $step['done'] ? '&#10003;' : (int) ( $i + 1 ); ?></span>
						<a href="<?php echo esc_url( $step['url'] ); ?>"><?php echo esc_html( $step['label'] ); ?></a>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php
	}
}
