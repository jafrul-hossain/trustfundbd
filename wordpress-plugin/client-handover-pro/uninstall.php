<?php
/**
 * Fires only when the plugin is deleted from the Plugins screen (not on
 * simple deactivation), so client data isn't wiped by accident.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$chp_options = array(
	'chp_settings',
	'chp_last_scan',
	'chp_white_label',
	'chp_brand_assets',
	'chp_client_notes',
	'chp_vault',
	'chp_plugin_activity',
	'chp_maintenance_log',
	'chp_client_approval',
	'chp_agency_sites',
	'chp_show_activation_notice',
	'chp_getting_started_dismissed',
);

foreach ( $chp_options as $option ) {
	delete_option( $option );
}

$tutorials = get_posts(
	array(
		'post_type'      => 'chp_tutorial',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);
foreach ( $tutorials as $tutorial_id ) {
	wp_delete_post( $tutorial_id, true );
}

wp_clear_scheduled_hook( 'chp_monthly_report' );
