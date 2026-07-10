<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * White Label: replace WordPress branding with the agency's own.
 */
class CHP_White_Label {

	public function __construct() {
		add_action( 'login_enqueue_scripts', array( $this, 'login_styles' ) );
		add_filter( 'login_headerurl', array( $this, 'login_logo_url' ) );
		add_filter( 'login_headertext', array( $this, 'login_logo_text' ) );
		add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ) );
		add_filter( 'update_footer', array( $this, 'admin_footer_version' ), 11 );
		add_action( 'admin_head', array( $this, 'admin_head_branding' ) );
		add_action( 'wp_before_admin_bar_render', array( $this, 'admin_bar_branding' ) );
	}

	private function get_wl_settings() {
		return get_option(
			'chp_white_label',
			array(
				'login_logo'  => '',
				'login_url'   => home_url(),
				'login_bg'    => '#F5F7F6',
				'footer_text' => 'Built by Badhon Studio',
				'footer_email'=> 'support@email.com',
				'dashboard_logo' => '',
			)
		);
	}

	public function login_styles() {
		$settings = $this->get_wl_settings();
		$primary  = CHP_Plugin::get_setting( 'agency_primary', '#1E7F5C' );
		?>
		<style>
			body.login {
				background: <?php echo esc_html( $settings['login_bg'] ); ?>;
			}
			<?php if ( ! empty( $settings['login_logo'] ) ) : ?>
			#login h1 a {
				background-image: url(<?php echo esc_url( $settings['login_logo'] ); ?>);
				background-size: contain;
				width: 100%;
				height: 80px;
			}
			<?php endif; ?>
			.wp-core-ui .button-primary {
				background: <?php echo esc_html( $primary ); ?>;
				border-color: <?php echo esc_html( $primary ); ?>;
			}
			#backtoblog a, #nav a {
				color: <?php echo esc_html( $primary ); ?>;
			}
		</style>
		<?php
	}

	public function login_logo_url() {
		$settings = $this->get_wl_settings();
		return ! empty( $settings['login_url'] ) ? esc_url( $settings['login_url'] ) : home_url();
	}

	public function login_logo_text() {
		return get_bloginfo( 'name' );
	}

	public function admin_footer_text( $text ) {
		$settings = $this->get_wl_settings();
		if ( empty( $settings['footer_text'] ) ) {
			return $text;
		}
		$email = ! empty( $settings['footer_email'] ) ? ' &middot; <a href="mailto:' . esc_attr( $settings['footer_email'] ) . '">' . esc_html( $settings['footer_email'] ) . '</a>' : '';
		return esc_html( $settings['footer_text'] ) . $email;
	}

	public function admin_footer_version( $text ) {
		return '';
	}

	public function admin_head_branding() {
		$settings = $this->get_wl_settings();
		if ( empty( $settings['dashboard_logo'] ) ) {
			return;
		}
		?>
		<style>
			#wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-icon:before {
				background-image: url(<?php echo esc_url( $settings['dashboard_logo'] ); ?>);
				background-size: contain;
				background-repeat: no-repeat;
				content: '';
			}
		</style>
		<?php
	}

	public function admin_bar_branding( $wp_admin_bar ) {
		// Handled via admin_head CSS to avoid removing useful "Visit Site" links.
	}

	public function render_settings_page() {
		$settings = $this->get_wl_settings();

		if ( isset( $_POST['chp_wl_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['chp_wl_nonce'] ) ), 'chp_wl_save' ) ) {
			$settings['login_logo']    = esc_url_raw( wp_unslash( $_POST['login_logo'] ?? '' ) );
			$settings['login_url']     = esc_url_raw( wp_unslash( $_POST['login_url'] ?? home_url() ) );
			$settings['login_bg']      = sanitize_hex_color( wp_unslash( $_POST['login_bg'] ?? '' ) ) ?: '#F5F7F6';
			$settings['footer_text']   = sanitize_text_field( wp_unslash( $_POST['footer_text'] ?? '' ) );
			$settings['footer_email']  = sanitize_email( wp_unslash( $_POST['footer_email'] ?? '' ) );
			$settings['dashboard_logo'] = esc_url_raw( wp_unslash( $_POST['dashboard_logo'] ?? '' ) );
			update_option( 'chp_white_label', $settings );

			$main_settings = CHP_Plugin::get_settings();
			$main_settings['agency_primary'] = sanitize_hex_color( wp_unslash( $_POST['agency_primary'] ?? '' ) ) ?: $main_settings['agency_primary'];
			$main_settings['agency_name']    = sanitize_text_field( wp_unslash( $_POST['footer_text'] ?? $main_settings['agency_name'] ) );
			$main_settings['agency_email']   = sanitize_email( wp_unslash( $_POST['footer_email'] ?? $main_settings['agency_email'] ) );
			CHP_Plugin::update_settings( $main_settings );

			echo '<div class="notice notice-success"><p>' . esc_html__( 'White label settings saved.', 'client-handover-pro' ) . '</p></div>';
			$settings = $this->get_wl_settings();
		}

		$primary = CHP_Plugin::get_setting( 'agency_primary', '#1E7F5C' );
		$is_pro  = CHP_License::is_pro();
		?>
		<div class="wrap chp-wrap">
			<h1 class="chp-title"><?php esc_html_e( 'White Label', 'client-handover-pro' ); ?></h1>
			<p class="chp-subtitle"><?php esc_html_e( 'Replace WordPress branding with your agency identity.', 'client-handover-pro' ); ?></p>

			<form method="post" class="chp-card chp-form">
				<?php wp_nonce_field( 'chp_wl_save', 'chp_wl_nonce' ); ?>

				<div class="chp-form-row">
					<label><?php esc_html_e( 'Login Logo URL', 'client-handover-pro' ); ?></label>
					<div class="chp-media-picker">
						<input type="text" name="login_logo" class="regular-text chp-media-url" value="<?php echo esc_attr( $settings['login_logo'] ); ?>" />
						<button type="button" class="chp-btn chp-btn--outline chp-media-select"><?php esc_html_e( 'Choose Image', 'client-handover-pro' ); ?></button>
					</div>
				</div>

				<div class="chp-form-row">
					<label><?php esc_html_e( 'Login Logo Link URL', 'client-handover-pro' ); ?></label>
					<input type="text" name="login_url" class="regular-text" value="<?php echo esc_attr( $settings['login_url'] ); ?>" />
				</div>

				<div class="chp-form-row">
					<label><?php esc_html_e( 'Login Background Color', 'client-handover-pro' ); ?></label>
					<input type="text" name="login_bg" class="chp-color-field" value="<?php echo esc_attr( $settings['login_bg'] ); ?>" />
				</div>

				<div class="chp-form-row">
					<label><?php esc_html_e( 'Dashboard Accent Color', 'client-handover-pro' ); ?></label>
					<input type="text" name="agency_primary" class="chp-color-field" value="<?php echo esc_attr( $primary ); ?>" />
				</div>

				<div class="chp-form-row">
					<label><?php esc_html_e( 'Dashboard / Admin Bar Logo URL', 'client-handover-pro' ); ?></label>
					<div class="chp-media-picker">
						<input type="text" name="dashboard_logo" class="regular-text chp-media-url" value="<?php echo esc_attr( $settings['dashboard_logo'] ); ?>" />
						<button type="button" class="chp-btn chp-btn--outline chp-media-select"><?php esc_html_e( 'Choose Image', 'client-handover-pro' ); ?></button>
					</div>
				</div>

				<div class="chp-form-row">
					<label><?php esc_html_e( 'Footer Text', 'client-handover-pro' ); ?></label>
					<input type="text" name="footer_text" class="regular-text" value="<?php echo esc_attr( $settings['footer_text'] ); ?>" placeholder="Built by Badhon Studio" />
				</div>

				<div class="chp-form-row">
					<label><?php esc_html_e( 'Support Email', 'client-handover-pro' ); ?></label>
					<input type="email" name="footer_email" class="regular-text" value="<?php echo esc_attr( $settings['footer_email'] ); ?>" placeholder="support@email.com" />
				</div>

				<?php if ( ! $is_pro ) : ?>
					<p class="description"><?php esc_html_e( 'Free plan applies white label branding site-wide. Upgrade to Agency to save unlimited branding presets per client site.', 'client-handover-pro' ); ?></p>
				<?php endif; ?>

				<button type="submit" class="chp-btn chp-btn--primary"><?php esc_html_e( 'Save Changes', 'client-handover-pro' ); ?></button>
			</form>
		</div>
		<?php
	}
}
