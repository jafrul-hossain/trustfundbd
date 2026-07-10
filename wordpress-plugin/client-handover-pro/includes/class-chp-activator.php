<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation / deactivation side effects.
 */
class CHP_Activator {

	public static function activate() {
		$defaults = array(
			'license_tier'        => 'free', // free | pro | agency
			'license_key'         => '',
			'client_mode_enabled' => false,
			'client_role'         => 'editor',
			'agency_name'         => 'Badhon Studio',
			'agency_email'        => 'support@email.com',
			'agency_logo'         => '',
			'agency_primary'      => '#1E7F5C',
			'admin_lock_roles'    => array(),
			'admin_lock_menus'    => array(),
			'maintenance_enabled' => false,
			'maintenance_mode'    => 'coming_soon', // coming_soon | launching_soon | maintenance
			'maintenance_headline' => 'Something great is on the way.',
			'maintenance_message' => "We're putting the finishing touches on our new website. Please check back soon.",
		);

		if ( false === get_option( 'chp_settings' ) ) {
			add_option( 'chp_settings', $defaults );
		}

		if ( false === get_option( 'chp_last_scan' ) ) {
			add_option( 'chp_last_scan', array() );
		}

		if ( false === get_option( 'chp_brand_assets' ) ) {
			add_option(
				'chp_brand_assets',
				array(
					'logo'         => '',
					'colors'       => array(),
					'fonts'        => array(),
					'favicon'      => '',
					'social_links' => array(),
				)
			);
		}

		if ( false === get_option( 'chp_client_notes' ) ) {
			add_option( 'chp_client_notes', array() );
		}

		if ( false === get_option( 'chp_vault' ) ) {
			add_option( 'chp_vault', array() );
		}

		if ( false === get_option( 'chp_plugin_activity' ) ) {
			add_option( 'chp_plugin_activity', array() );
		}

		if ( false === get_option( 'chp_maintenance_log' ) ) {
			add_option( 'chp_maintenance_log', array() );
		}

		if ( false === get_option( 'chp_client_approval' ) ) {
			add_option( 'chp_client_approval', array( 'status' => 'pending' ) );
		}

		if ( ! wp_next_scheduled( 'chp_monthly_report' ) ) {
			wp_schedule_event( time(), 'monthly', 'chp_monthly_report' );
		}

		// Always leave a dismissible admin notice as a fallback path to the
		// Welcome screen (covers bulk/network activation, where a forced
		// redirect is skipped below).
		update_option( 'chp_show_activation_notice', true );

		// Single-activation only: skip on bulk activation or network activation,
		// where a forced redirect would either fire for every plugin in the
		// batch or land on a site the network admin isn't currently viewing.
		if ( ! is_network_admin() && empty( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of the current activation request shape, not a state change.
			set_transient( 'chp_activation_redirect', true, MINUTE_IN_SECONDS );
		}

		flush_rewrite_rules();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'chp_monthly_report' );
		flush_rewrite_rules();
	}
}
