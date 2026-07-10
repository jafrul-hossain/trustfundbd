<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Cleanup: surfaces inactive plugins with a "last used" timestamp
 * so agencies can confidently remove dead weight before handover.
 */
class CHP_Plugin_Cleanup {

	public function __construct() {
		add_action( 'deactivated_plugin', array( $this, 'record_deactivation' ) );
	}

	public function record_deactivation( $plugin ) {
		$activity               = get_option( 'chp_plugin_activity', array() );
		$activity[ $plugin ]    = time();
		update_option( 'chp_plugin_activity', $activity );
	}

	public function render_page() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$activity       = get_option( 'chp_plugin_activity', array() );

		?>
		<div class="wrap chp-wrap">
			<h1 class="chp-title"><?php esc_html_e( 'Plugin Cleanup', 'client-handover-pro' ); ?></h1>
			<p class="chp-subtitle"><?php esc_html_e( 'Find and remove plugins that are no longer in use.', 'client-handover-pro' ); ?></p>

			<div class="chp-card">
				<table class="chp-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Plugin', 'client-handover-pro' ); ?></th>
							<th><?php esc_html_e( 'Status', 'client-handover-pro' ); ?></th>
							<th><?php esc_html_e( 'Last Used', 'client-handover-pro' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $all_plugins as $file => $data ) : ?>
						<?php
						$is_active = in_array( $file, $active_plugins, true );
						if ( $is_active || CHP_PLUGIN_BASENAME === $file ) {
							continue; // Only list inactive plugins (and never itself).
						}
						$last_used = isset( $activity[ $file ] ) ? human_time_diff( $activity[ $file ] ) . ' ' . __( 'ago', 'client-handover-pro' ) : __( 'Never activated', 'client-handover-pro' );
						?>
						<tr>
							<td class="chp-table__label"><?php echo esc_html( $data['Name'] ); ?></td>
							<td><span class="chp-badge chp-badge--warn"><?php esc_html_e( 'Unused', 'client-handover-pro' ); ?></span></td>
							<td class="chp-table__message"><?php echo esc_html( $last_used ); ?></td>
							<td class="chp-table__action">
								<button type="button" class="chp-btn chp-btn--outline chp-plugin-delete" data-plugin="<?php echo esc_attr( $file ); ?>"><?php esc_html_e( 'Delete', 'client-handover-pro' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<div id="chp-plugin-cleanup-result"></div>
			</div>
		</div>
		<?php
	}
}
