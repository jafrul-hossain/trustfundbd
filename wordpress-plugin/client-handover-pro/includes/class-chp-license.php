<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tier gating. There is no external licensing server wired up yet — the
 * tier is stored locally and validated through a filter so a future
 * licensing API (EDD Software Licensing, Freemius, etc.) can hook in
 * without touching any of the call sites below.
 */
class CHP_License {

	const TIER_FREE   = 'free';
	const TIER_PRO    = 'pro';
	const TIER_AGENCY = 'agency';

	public static function tier() {
		$settings = CHP_Plugin::get_settings();
		$tier     = isset( $settings['license_tier'] ) ? $settings['license_tier'] : self::TIER_FREE;

		/**
		 * Filter the active license tier. Hook a real licensing API here.
		 *
		 * @param string $tier
		 */
		return apply_filters( 'chp_license_tier', $tier );
	}

	public static function is_pro() {
		return in_array( self::tier(), array( self::TIER_PRO, self::TIER_AGENCY ), true );
	}

	public static function is_agency() {
		return self::TIER_AGENCY === self::tier();
	}

	/**
	 * Validates a pasted license key. This is a local stub: any non-empty
	 * key of at least 8 characters is accepted and stored as "pro" so the
	 * UI/workflow can be demoed end to end. Replace with a real remote
	 * validation call before distributing the plugin commercially.
	 */
	public static function activate_key( $key, $tier = self::TIER_PRO ) {
		$key = trim( (string) $key );

		if ( strlen( $key ) < 8 ) {
			return new WP_Error( 'chp_invalid_key', __( 'That license key looks too short. Double check it and try again.', 'client-handover-pro' ) );
		}

		$settings                  = CHP_Plugin::get_settings();
		$settings['license_key']   = $key;
		$settings['license_tier']  = in_array( $tier, array( self::TIER_PRO, self::TIER_AGENCY ), true ) ? $tier : self::TIER_PRO;
		CHP_Plugin::update_settings( $settings );

		return true;
	}

	public static function deactivate_key() {
		$settings                 = CHP_Plugin::get_settings();
		$settings['license_key']  = '';
		$settings['license_tier'] = self::TIER_FREE;
		CHP_Plugin::update_settings( $settings );
	}

	/**
	 * Renders a small inline upsell box. Used by any module that has a
	 * Pro-only section on an otherwise free-tier page.
	 */
	public static function render_upsell( $feature_label, $required_tier = self::TIER_PRO ) {
		$plan = self::TIER_AGENCY === $required_tier ? __( 'Agency', 'client-handover-pro' ) : __( 'Pro', 'client-handover-pro' );
		?>
		<div class="chp-upsell">
			<span class="chp-upsell__badge"><?php echo esc_html( $plan ); ?></span>
			<p>
				<?php
				printf(
					/* translators: 1: feature name, 2: plan name */
					esc_html__( '%1$s is a %2$s feature.', 'client-handover-pro' ),
					'<strong>' . esc_html( $feature_label ) . '</strong>',
					esc_html( $plan )
				);
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=chp-settings' ) ); ?>"><?php esc_html_e( 'Upgrade to unlock', 'client-handover-pro' ); ?></a>
			</p>
		</div>
		<?php
	}
}
