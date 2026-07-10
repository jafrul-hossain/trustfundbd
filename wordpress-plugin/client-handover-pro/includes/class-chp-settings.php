<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings & License: license key activation and general agency info
 * used across White Label, Client Mode, and the Handover document.
 */
class CHP_Settings {

	public function render_page() {
		$settings = CHP_Plugin::get_settings();
		$notice   = '';

		if ( CHP_Helpers::verify_post( 'chp_settings_save', 'chp_settings_nonce' ) ) {
			if ( isset( $_POST['activate_license'] ) ) {
				$key    = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );
				$tier   = isset( $_POST['license_tier'] ) ? sanitize_key( wp_unslash( $_POST['license_tier'] ) ) : CHP_License::TIER_PRO;
				$result = CHP_License::activate_key( $key, $tier );
				$notice = is_wp_error( $result )
					? '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>'
					: '<div class="notice notice-success"><p>' . esc_html__( 'License activated.', 'client-handover-pro' ) . '</p></div>';
			} elseif ( isset( $_POST['deactivate_license'] ) ) {
				CHP_License::deactivate_key();
				$notice = '<div class="notice notice-success"><p>' . esc_html__( 'License deactivated. Reverted to Free plan.', 'client-handover-pro' ) . '</p></div>';
			} else {
				$settings['agency_name']  = sanitize_text_field( wp_unslash( $_POST['agency_name'] ?? '' ) );
				$settings['agency_email'] = sanitize_email( wp_unslash( $_POST['agency_email'] ?? '' ) );
				CHP_Plugin::update_settings( $settings );
				$notice = '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'client-handover-pro' ) . '</p></div>';
			}
			$settings = CHP_Plugin::get_settings();
		}

		$tier = CHP_License::tier();
		?>
		<div class="wrap chp-wrap">
			<h1 class="chp-title"><?php esc_html_e( 'Settings & License', 'client-handover-pro' ); ?></h1>
			<?php echo wp_kses_post( $notice ); ?>

			<div class="chp-card">
				<div class="chp-card__label"><?php esc_html_e( 'Current Plan', 'client-handover-pro' ); ?></div>
				<p><span class="chp-badge chp-badge--pass"><?php echo esc_html( ucfirst( $tier ) ); ?></span></p>

				<form method="post" class="chp-form">
					<?php wp_nonce_field( 'chp_settings_save', 'chp_settings_nonce' ); ?>
					<?php if ( CHP_License::TIER_FREE === $tier ) : ?>
						<div class="chp-form-row">
							<label><?php esc_html_e( 'License Key', 'client-handover-pro' ); ?></label>
							<input type="text" name="license_key" class="regular-text" placeholder="XXXX-XXXX-XXXX-XXXX" />
						</div>
						<div class="chp-form-row">
							<label><?php esc_html_e( 'Plan', 'client-handover-pro' ); ?></label>
							<select name="license_tier">
								<option value="pro"><?php esc_html_e( 'Pro ($79/year)', 'client-handover-pro' ); ?></option>
								<option value="agency"><?php esc_html_e( 'Agency ($199/year)', 'client-handover-pro' ); ?></option>
							</select>
						</div>
						<button type="submit" name="activate_license" value="1" class="chp-btn chp-btn--primary"><?php esc_html_e( 'Activate License', 'client-handover-pro' ); ?></button>
					<?php else : ?>
						<button type="submit" name="deactivate_license" value="1" class="chp-btn chp-btn--outline"><?php esc_html_e( 'Deactivate License', 'client-handover-pro' ); ?></button>
					<?php endif; ?>
				</form>
			</div>

			<div class="chp-card">
				<div class="chp-card__label"><?php esc_html_e( 'Agency Info', 'client-handover-pro' ); ?></div>
				<form method="post" class="chp-form">
					<?php wp_nonce_field( 'chp_settings_save', 'chp_settings_nonce' ); ?>
					<div class="chp-form-row">
						<label><?php esc_html_e( 'Agency Name', 'client-handover-pro' ); ?></label>
						<input type="text" name="agency_name" class="regular-text" value="<?php echo esc_attr( $settings['agency_name'] ); ?>" />
					</div>
					<div class="chp-form-row">
						<label><?php esc_html_e( 'Support Email', 'client-handover-pro' ); ?></label>
						<input type="email" name="agency_email" class="regular-text" value="<?php echo esc_attr( $settings['agency_email'] ); ?>" />
					</div>
					<button type="submit" class="chp-btn chp-btn--primary"><?php esc_html_e( 'Save Changes', 'client-handover-pro' ); ?></button>
				</form>
			</div>

			<div class="chp-card">
				<div class="chp-card__label"><?php esc_html_e( 'Test Email Sending', 'client-handover-pro' ); ?></div>
				<p><?php esc_html_e( 'Sends a test email to the site admin address and records the result for the Launch Checklist.', 'client-handover-pro' ); ?></p>
				<button type="button" class="chp-btn chp-btn--outline" id="chp-send-test-email"><?php esc_html_e( 'Send Test Email', 'client-handover-pro' ); ?></button>
				<div id="chp-test-email-result"></div>
			</div>
		</div>
		<?php
	}
}
