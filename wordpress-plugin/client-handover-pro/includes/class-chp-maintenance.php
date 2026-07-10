<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maintenance Mode: a branded "Coming Soon" / "Under Maintenance" gate
 * for the front end, one click to toggle.
 */
class CHP_Maintenance {

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_show_maintenance_page' ) );
	}

	public function maybe_show_maintenance_page() {
		$settings = CHP_Plugin::get_settings();
		if ( empty( $settings['maintenance_enabled'] ) ) {
			return;
		}
		if ( current_user_can( 'edit_theme_options' ) ) {
			return; // Let admins/editors preview the live site.
		}
		if ( is_admin() ) {
			return;
		}

		status_header( 503 );
		header( 'Retry-After: 3600' );
		nocache_headers();

		$this->render_maintenance_template( $settings );
		exit;
	}

	private function render_maintenance_template( $settings ) {
		$mode_labels = array(
			'coming_soon'   => __( 'Coming Soon', 'client-handover-pro' ),
			'launching_soon'=> __( 'Launching Soon', 'client-handover-pro' ),
			'maintenance'   => __( 'Website Under Maintenance', 'client-handover-pro' ),
		);
		$mode     = ! empty( $settings['maintenance_mode'] ) ? $settings['maintenance_mode'] : 'coming_soon';
		$label    = isset( $mode_labels[ $mode ] ) ? $mode_labels[ $mode ] : $mode_labels['coming_soon'];
		$headline = ! empty( $settings['maintenance_headline'] ) ? $settings['maintenance_headline'] : $label;
		$message  = ! empty( $settings['maintenance_message'] ) ? $settings['maintenance_message'] : '';
		$primary  = ! empty( $settings['agency_primary'] ) ? $settings['agency_primary'] : '#1E7F5C';
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>" />
			<meta name="viewport" content="width=device-width, initial-scale=1" />
			<title><?php echo esc_html( $label . ' — ' . get_bloginfo( 'name' ) ); ?></title>
			<style>
				body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; background:#0B1F16; color:#fff; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; text-align:center; padding:24px; box-sizing:border-box; }
				.chp-m-badge { display:inline-block; padding:6px 16px; border-radius:999px; background:<?php echo esc_html( $primary ); ?>; font-size:13px; letter-spacing:.04em; text-transform:uppercase; margin-bottom:24px; }
				h1 { font-size:clamp(28px,5vw,48px); margin:0 0 16px; }
				p { font-size:18px; opacity:.8; max-width:520px; margin:0 auto; line-height:1.6; }
				.chp-m-site { margin-top:40px; opacity:.5; font-size:14px; }
			</style>
		</head>
		<body>
			<div>
				<span class="chp-m-badge"><?php echo esc_html( $label ); ?></span>
				<h1><?php echo esc_html( $headline ); ?></h1>
				<p><?php echo esc_html( $message ); ?></p>
				<div class="chp-m-site"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
			</div>
		</body>
		</html>
		<?php
	}

	public function render_settings_page() {
		$settings = CHP_Plugin::get_settings();

		if ( isset( $_POST['chp_maint_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['chp_maint_nonce'] ) ), 'chp_maint_save' ) ) {
			$was_enabled = ! empty( $settings['maintenance_enabled'] );
			$settings['maintenance_enabled']  = ! empty( $_POST['maintenance_enabled'] );
			$settings['maintenance_mode']     = isset( $_POST['maintenance_mode'] ) ? sanitize_key( wp_unslash( $_POST['maintenance_mode'] ) ) : 'coming_soon';
			$settings['maintenance_headline'] = sanitize_text_field( wp_unslash( $_POST['maintenance_headline'] ?? '' ) );
			$settings['maintenance_message']  = sanitize_textarea_field( wp_unslash( $_POST['maintenance_message'] ?? '' ) );
			CHP_Plugin::update_settings( $settings );

			if ( $was_enabled !== $settings['maintenance_enabled'] && class_exists( 'CHP_Agency' ) ) {
				CHP_Agency::log_event( $settings['maintenance_enabled'] ? __( 'Maintenance mode enabled', 'client-handover-pro' ) : __( 'Maintenance mode disabled', 'client-handover-pro' ) );
			}

			echo '<div class="notice notice-success"><p>' . esc_html__( 'Maintenance mode settings saved.', 'client-handover-pro' ) . '</p></div>';
		}
		?>
		<div class="wrap chp-wrap">
			<h1 class="chp-title"><?php esc_html_e( 'Maintenance Mode', 'client-handover-pro' ); ?></h1>
			<p class="chp-subtitle"><?php esc_html_e( 'Show a branded page to visitors before launch.', 'client-handover-pro' ); ?></p>

			<form method="post" class="chp-card chp-form">
				<?php wp_nonce_field( 'chp_maint_save', 'chp_maint_nonce' ); ?>

				<label class="chp-toggle">
					<input type="checkbox" name="maintenance_enabled" value="1" <?php checked( ! empty( $settings['maintenance_enabled'] ) ); ?> />
					<?php esc_html_e( 'Enable Maintenance Mode', 'client-handover-pro' ); ?>
				</label>

				<div class="chp-form-row">
					<label><?php esc_html_e( 'Mode', 'client-handover-pro' ); ?></label>
					<select name="maintenance_mode">
						<option value="coming_soon" <?php selected( $settings['maintenance_mode'], 'coming_soon' ); ?>><?php esc_html_e( 'Coming Soon', 'client-handover-pro' ); ?></option>
						<option value="launching_soon" <?php selected( $settings['maintenance_mode'], 'launching_soon' ); ?>><?php esc_html_e( 'Launching Soon', 'client-handover-pro' ); ?></option>
						<option value="maintenance" <?php selected( $settings['maintenance_mode'], 'maintenance' ); ?>><?php esc_html_e( 'Website Under Maintenance', 'client-handover-pro' ); ?></option>
					</select>
				</div>

				<div class="chp-form-row">
					<label><?php esc_html_e( 'Headline', 'client-handover-pro' ); ?></label>
					<input type="text" class="regular-text" name="maintenance_headline" value="<?php echo esc_attr( $settings['maintenance_headline'] ); ?>" />
				</div>

				<div class="chp-form-row">
					<label><?php esc_html_e( 'Message', 'client-handover-pro' ); ?></label>
					<textarea class="widefat" name="maintenance_message" rows="3"><?php echo esc_textarea( $settings['maintenance_message'] ); ?></textarea>
				</div>

				<button type="submit" class="chp-btn chp-btn--primary"><?php esc_html_e( 'Save Changes', 'client-handover-pro' ); ?></button>
			</form>
		</div>
		<?php
	}
}
