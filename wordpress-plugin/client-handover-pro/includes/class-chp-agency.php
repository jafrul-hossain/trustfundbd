<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agency-tier features: maintenance logs, scheduled email reports,
 * a lightweight multi-site directory, and one-click settings export
 * for moving tutorials/branding/settings to another site.
 */
class CHP_Agency {

	const LOG_LIMIT = 200;

	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'add_monthly_schedule' ) );
		add_action( 'upgrader_process_complete', array( $this, 'log_upgrade' ), 10, 2 );
		add_action( 'activated_plugin', array( $this, 'log_plugin_activated' ) );
		add_action( 'deactivated_plugin', array( $this, 'log_plugin_deactivated' ) );
		add_action( 'wp_login', array( $this, 'log_client_login' ), 10, 2 );
		add_action( 'chp_monthly_report', array( $this, 'send_scheduled_report' ) );
		add_action( 'admin_post_chp_export_settings', array( $this, 'export_settings' ) );
		add_action( 'admin_post_chp_import_settings', array( $this, 'import_settings' ) );
	}

	public function add_monthly_schedule( $schedules ) {
		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once Monthly', 'client-handover-pro' ),
			);
		}
		return $schedules;
	}

	public static function log_event( $message ) {
		$log   = get_option( 'chp_maintenance_log', array() );
		$log[] = array( 'message' => $message, 'time' => time() );
		if ( count( $log ) > self::LOG_LIMIT ) {
			$log = array_slice( $log, -1 * self::LOG_LIMIT );
		}
		update_option( 'chp_maintenance_log', $log );
	}

	public function log_upgrade( $upgrader, $data ) {
		if ( empty( $data['action'] ) || 'update' !== $data['action'] ) {
			return;
		}
		if ( 'plugin' === $data['type'] ) {
			self::log_event( __( 'Plugin(s) updated', 'client-handover-pro' ) );
		} elseif ( 'theme' === $data['type'] ) {
			self::log_event( __( 'Theme(s) updated', 'client-handover-pro' ) );
		} elseif ( 'core' === $data['type'] ) {
			self::log_event( __( 'WordPress core updated', 'client-handover-pro' ) );
		}
	}

	public function log_plugin_activated( $plugin ) {
		self::log_event( sprintf( __( 'Plugin activated: %s', 'client-handover-pro' ), $plugin ) );
	}

	public function log_plugin_deactivated( $plugin ) {
		self::log_event( sprintf( __( 'Plugin deactivated: %s', 'client-handover-pro' ), $plugin ) );
	}

	public function log_client_login( $user_login, $user ) {
		$settings = CHP_Plugin::get_settings();
		if ( ! empty( $settings['client_mode_enabled'] ) && in_array( $settings['client_role'], (array) $user->roles, true ) ) {
			self::log_event( sprintf( __( 'Client login: %s', 'client-handover-pro' ), $user_login ) );
		}
	}

	public function send_scheduled_report() {
		if ( ! CHP_License::is_pro() ) {
			return;
		}
		$settings = CHP_Plugin::get_settings();
		$report   = CHP_Handover::launch_report();
		$to       = ! empty( $settings['agency_email'] ) ? $settings['agency_email'] : get_bloginfo( 'admin_email' );

		$body  = sprintf( "Site Health Report — %s\n\n", get_bloginfo( 'name' ) );
		$body .= sprintf( "Overall Score: %d%%\n", $report['overall'] );
		$body .= sprintf( "Performance: %d\n", $report['performance'] );
		$body .= sprintf( "Security: %d\n", $report['security'] );
		$body .= sprintf( "SEO: %d\n", $report['seo'] );
		$body .= sprintf( "Accessibility: %d\n\n", $report['accessibility'] );
		$body .= "Recent Maintenance Activity:\n";
		$log   = array_slice( array_reverse( get_option( 'chp_maintenance_log', array() ) ), 0, 10 );
		foreach ( $log as $entry ) {
			$body .= '- ' . $entry['message'] . ' (' . date_i18n( get_option( 'date_format' ), $entry['time'] ) . ")\n";
		}

		wp_mail( $to, sprintf( '[%s] Monthly Site Health Report', get_bloginfo( 'name' ) ), $body );
		self::log_event( __( 'Scheduled report emailed', 'client-handover-pro' ) );
	}

	public function export_settings() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'chp_export_settings' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'client-handover-pro' ) );
		}
		if ( ! CHP_License::is_pro() ) {
			wp_die( esc_html__( 'One-Click Export requires Pro or Agency.', 'client-handover-pro' ) );
		}

		$tutorials = get_posts( array( 'post_type' => 'chp_tutorial', 'posts_per_page' => -1, 'post_status' => 'any' ) );
		$tutorial_export = array();
		foreach ( $tutorials as $tutorial ) {
			$tutorial_export[] = array(
				'title'  => $tutorial->post_title,
				'content'=> $tutorial->post_content,
				'video'  => get_post_meta( $tutorial->ID, '_chp_video_url', true ),
				'steps'  => get_post_meta( $tutorial->ID, '_chp_steps', true ),
				'pdf'    => get_post_meta( $tutorial->ID, '_chp_pdf_url', true ),
				'image'  => get_post_meta( $tutorial->ID, '_chp_image_url', true ),
			);
		}

		$bundle = array(
			'chp_settings'      => CHP_Plugin::get_settings(),
			'chp_white_label'   => get_option( 'chp_white_label', array() ),
			'chp_brand_assets'  => get_option( 'chp_brand_assets', array() ),
			'tutorials'         => $tutorial_export,
			'exported_at'       => gmdate( 'c' ),
			'source_site'       => home_url(),
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="chp-export-' . sanitize_title( get_bloginfo( 'name' ) ) . '.json"' );
		echo wp_json_encode( $bundle, JSON_PRETTY_PRINT );
		exit;
	}

	public function import_settings() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'chp_import_settings' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'client-handover-pro' ) );
		}
		if ( ! CHP_License::is_pro() ) {
			wp_die( esc_html__( 'One-Click Export requires Pro or Agency.', 'client-handover-pro' ) );
		}

		if ( empty( $_FILES['chp_import_file']['tmp_name'] ) || UPLOAD_ERR_OK !== $_FILES['chp_import_file']['error'] ) {
			wp_safe_redirect( admin_url( 'admin.php?page=chp-agency&chp_import=error' ) );
			exit;
		}

		$contents = file_get_contents( $_FILES['chp_import_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading an uploaded temp file, not a remote URL.
		$bundle   = json_decode( $contents, true );

		if ( ! is_array( $bundle ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=chp-agency&chp_import=error' ) );
			exit;
		}

		if ( ! empty( $bundle['chp_settings'] ) && is_array( $bundle['chp_settings'] ) ) {
			CHP_Plugin::update_settings( wp_parse_args( $bundle['chp_settings'], CHP_Plugin::get_settings() ) );
		}
		if ( ! empty( $bundle['chp_white_label'] ) ) {
			update_option( 'chp_white_label', $bundle['chp_white_label'] );
		}
		if ( ! empty( $bundle['chp_brand_assets'] ) ) {
			update_option( 'chp_brand_assets', $bundle['chp_brand_assets'] );
		}
		if ( ! empty( $bundle['tutorials'] ) && is_array( $bundle['tutorials'] ) ) {
			foreach ( $bundle['tutorials'] as $tutorial ) {
				$post_id = wp_insert_post(
					array(
						'post_type'    => 'chp_tutorial',
						'post_title'   => sanitize_text_field( $tutorial['title'] ?? '' ),
						'post_content' => wp_kses_post( $tutorial['content'] ?? '' ),
						'post_status'  => 'publish',
					)
				);
				if ( $post_id && ! is_wp_error( $post_id ) ) {
					update_post_meta( $post_id, '_chp_video_url', esc_url_raw( $tutorial['video'] ?? '' ) );
					update_post_meta( $post_id, '_chp_steps', sanitize_textarea_field( $tutorial['steps'] ?? '' ) );
					update_post_meta( $post_id, '_chp_pdf_url', esc_url_raw( $tutorial['pdf'] ?? '' ) );
					update_post_meta( $post_id, '_chp_image_url', esc_url_raw( $tutorial['image'] ?? '' ) );
				}
			}
		}

		self::log_event( __( 'Settings imported via One-Click Export', 'client-handover-pro' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=chp-agency&chp_import=success' ) );
		exit;
	}

	public function render_page() {
		$is_agency = CHP_License::is_agency();
		$is_pro    = CHP_License::is_pro();
		$sites     = get_option( 'chp_agency_sites', array() );

		if ( $is_agency && CHP_Helpers::verify_post( 'chp_sites_save', 'chp_sites_nonce' ) ) {
			$name = sanitize_text_field( wp_unslash( $_POST['site_name'] ?? '' ) );
			$url  = esc_url_raw( wp_unslash( $_POST['site_url'] ?? '' ) );
			if ( $name && $url ) {
				$sites[] = array( 'name' => $name, 'url' => $url );
				update_option( 'chp_agency_sites', $sites );
			}
		}

		?>
		<div class="wrap chp-wrap">
			<h1 class="chp-title"><?php esc_html_e( 'Agency Tools', 'client-handover-pro' ); ?></h1>
			<p class="chp-subtitle"><?php esc_html_e( 'Multi-site management, scheduled reports, maintenance logs, and export.', 'client-handover-pro' ); ?></p>

			<div class="chp-card">
				<div class="chp-card__label"><?php esc_html_e( 'Multi-Site Management', 'client-handover-pro' ); ?></div>
				<?php if ( ! $is_agency ) : ?>
					<?php CHP_License::render_upsell( __( 'Multi-Site Management', 'client-handover-pro' ), CHP_License::TIER_AGENCY ); ?>
				<?php else : ?>
					<table class="chp-table">
						<tbody>
						<?php foreach ( $sites as $site ) : ?>
							<tr>
								<td class="chp-table__label"><?php echo esc_html( $site['name'] ); ?></td>
								<td class="chp-table__message"><a href="<?php echo esc_url( $site['url'] ); ?>" target="_blank"><?php echo esc_html( $site['url'] ); ?></a></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<form method="post" class="chp-form">
						<?php wp_nonce_field( 'chp_sites_save', 'chp_sites_nonce' ); ?>
						<input type="text" name="site_name" placeholder="<?php esc_attr_e( 'Client site name', 'client-handover-pro' ); ?>" />
						<input type="text" name="site_url" placeholder="https://example.com/wp-admin" />
						<button type="submit" class="chp-btn chp-btn--outline"><?php esc_html_e( 'Add Site', 'client-handover-pro' ); ?></button>
					</form>
				<?php endif; ?>
			</div>

			<div class="chp-card">
				<div class="chp-card__label"><?php esc_html_e( 'Scheduled Reports', 'client-handover-pro' ); ?></div>
				<?php if ( ! $is_pro ) : ?>
					<?php CHP_License::render_upsell( __( 'Scheduled Reports', 'client-handover-pro' ) ); ?>
				<?php else : ?>
					<p><?php esc_html_e( 'A monthly site health email is sent automatically to the agency support address.', 'client-handover-pro' ); ?></p>
					<p class="description"><?php printf( esc_html__( 'Next scheduled run: %s', 'client-handover-pro' ), esc_html( wp_next_scheduled( 'chp_monthly_report' ) ? date_i18n( 'F j, Y g:i a', wp_next_scheduled( 'chp_monthly_report' ) ) : __( 'not scheduled', 'client-handover-pro' ) ) ); ?></p>
				<?php endif; ?>
			</div>

			<div class="chp-card">
				<div class="chp-card__label"><?php esc_html_e( 'Maintenance Logs', 'client-handover-pro' ); ?></div>
				<?php if ( ! $is_pro ) : ?>
					<?php CHP_License::render_upsell( __( 'Maintenance Logs', 'client-handover-pro' ) ); ?>
				<?php else : ?>
					<table class="chp-table">
						<tbody>
						<?php
						$log = array_slice( array_reverse( get_option( 'chp_maintenance_log', array() ) ), 0, 25 );
						if ( empty( $log ) ) :
							?>
							<tr><td><?php esc_html_e( 'No activity logged yet.', 'client-handover-pro' ); ?></td></tr>
							<?php
						endif;
						foreach ( $log as $entry ) :
							?>
							<tr>
								<td class="chp-table__label"><?php echo esc_html( $entry['message'] ); ?></td>
								<td class="chp-table__message"><?php echo esc_html( human_time_diff( $entry['time'] ) . ' ' . __( 'ago', 'client-handover-pro' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<div class="chp-card">
				<div class="chp-card__label"><?php esc_html_e( 'One-Click Export', 'client-handover-pro' ); ?></div>
				<?php if ( ! $is_pro ) : ?>
					<?php CHP_License::render_upsell( __( 'One-Click Export', 'client-handover-pro' ) ); ?>
				<?php else : ?>
					<p><?php esc_html_e( 'Move tutorials, branding and settings to another website running Client Handover Pro.', 'client-handover-pro' ); ?></p>
					<a class="chp-btn chp-btn--outline" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=chp_export_settings' ), 'chp_export_settings' ) ); ?>"><?php esc_html_e( 'Download Export File', 'client-handover-pro' ); ?></a>

					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
						<input type="hidden" name="action" value="chp_import_settings" />
						<?php wp_nonce_field( 'chp_import_settings' ); ?>
						<input type="file" name="chp_import_file" accept="application/json" />
						<button type="submit" class="chp-btn chp-btn--primary"><?php esc_html_e( 'Import', 'client-handover-pro' ); ?></button>
					</form>
					<?php if ( isset( $_GET['chp_import'] ) && 'success' === $_GET['chp_import'] ) : ?>
						<p class="notice notice-success"><?php esc_html_e( 'Import completed.', 'client-handover-pro' ); ?></p>
					<?php elseif ( isset( $_GET['chp_import'] ) && 'error' === $_GET['chp_import'] ) : ?>
						<p class="notice notice-error"><?php esc_html_e( 'Import failed. Check the file and try again.', 'client-handover-pro' ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
