<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handover & Reports: the Launch Report score, the printable PDF
 * Handover document (Pro), and the Client Approval workflow (Pro).
 */
class CHP_Handover {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_hidden_print_page' ) );
	}

	public function register_hidden_print_page() {
		add_submenu_page( null, __( 'Handover Document', 'client-handover-pro' ), __( 'Handover Document', 'client-handover-pro' ), 'manage_options', 'chp-handover-print', array( $this, 'render_print_page' ) );
	}

	/* ------------------------------------------------------------- Scoring */

	public static function launch_report() {
		$scan = CHP_Checklist::get_last_scan();
		$cats = $scan['categories'];

		$score_for = static function ( $cat ) {
			$passed = 0;
			$total  = 0;
			foreach ( $cat['items'] as $item ) {
				$total++;
				if ( 'pass' === $item['status'] ) {
					$passed++;
				} elseif ( 'warn' === $item['status'] ) {
					$passed += 0.5;
				}
			}
			return $total > 0 ? (int) round( ( $passed / $total ) * 100 ) : 0;
		};

		$performance = $score_for( $cats['performance'] );
		$security    = $score_for( $cats['security'] );
		$seo         = $score_for( $cats['seo'] );
		$accessibility = self::accessibility_estimate();

		$overall = (int) round( ( $performance + $security + $seo + $accessibility ) / 4 );

		return array(
			'performance'   => $performance,
			'security'      => $security,
			'seo'           => $seo,
			'accessibility' => $accessibility,
			'overall'       => $overall,
			'ready'         => $overall >= 80,
		);
	}

	private static function accessibility_estimate() {
		global $wpdb;
		$total_images = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'" );
		if ( 0 === $total_images ) {
			return 100;
		}
		$with_alt = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_wp_attachment_image_alt' AND pm.meta_value <> ''
			 AND p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%'"
		);
		return (int) round( ( $with_alt / $total_images ) * 100 );
	}

	/* --------------------------------------------------------------- Pages */

	public function render_page() {
		$report   = self::launch_report();
		$approval = get_option( 'chp_client_approval', array( 'status' => 'pending' ) );
		$is_pro   = CHP_License::is_pro();

		if ( $is_pro && CHP_Helpers::verify_post( 'chp_approval_save', 'chp_approval_nonce' ) ) {
			$status  = isset( $_POST['approval_status'] ) ? sanitize_key( wp_unslash( $_POST['approval_status'] ) ) : 'pending';
			$comment = sanitize_textarea_field( wp_unslash( $_POST['approval_comment'] ?? '' ) );
			$approval = array(
				'status'    => in_array( $status, array( 'approved', 'changes_requested' ), true ) ? $status : 'pending',
				'comment'   => $comment,
				'user'      => wp_get_current_user()->display_name,
				'timestamp' => time(),
			);
			update_option( 'chp_client_approval', $approval );
			CHP_Helpers::notice( __( 'Approval status updated.', 'client-handover-pro' ) );
		}

		?>
		<div class="wrap chp-wrap">
			<h1 class="chp-title"><?php esc_html_e( 'Handover & Reports', 'client-handover-pro' ); ?></h1>
			<p class="chp-subtitle"><?php esc_html_e( 'The final score, the polished document, and client sign-off.', 'client-handover-pro' ); ?></p>

			<div class="chp-grid chp-grid--report">
				<div class="chp-card chp-card--dark">
					<div class="chp-card__label"><?php esc_html_e( 'Website Score', 'client-handover-pro' ); ?></div>
					<div class="chp-card__big-number"><?php echo (int) $report['overall']; ?>%</div>
					<div class="chp-card__meta">
						<?php esc_html_e( 'Ready for Launch:', 'client-handover-pro' ); ?>
						<strong><?php echo $report['ready'] ? esc_html__( 'YES', 'client-handover-pro' ) : esc_html__( 'NOT YET', 'client-handover-pro' ); ?></strong>
					</div>
				</div>
				<div class="chp-card">
					<div class="chp-card__label"><?php esc_html_e( 'Score Breakdown', 'client-handover-pro' ); ?></div>
					<ul class="chp-score-list">
						<li><?php esc_html_e( 'Performance', 'client-handover-pro' ); ?> <strong><?php echo (int) $report['performance']; ?></strong></li>
						<li><?php esc_html_e( 'Security', 'client-handover-pro' ); ?> <strong><?php echo (int) $report['security']; ?></strong></li>
						<li><?php esc_html_e( 'SEO', 'client-handover-pro' ); ?> <strong><?php echo (int) $report['seo']; ?></strong></li>
						<li><?php esc_html_e( 'Accessibility', 'client-handover-pro' ); ?> <strong><?php echo (int) $report['accessibility']; ?></strong></li>
					</ul>
				</div>
			</div>

			<div class="chp-card">
				<div class="chp-card__label"><?php esc_html_e( 'PDF Handover Document', 'client-handover-pro' ); ?></div>
				<p><?php esc_html_e( 'A polished document covering the website overview, pages, forms, SEO, hosting, brand assets, tutorials, maintenance tips and support info.', 'client-handover-pro' ); ?></p>
				<?php if ( $is_pro ) : ?>
					<a class="chp-btn chp-btn--primary" target="_blank" href="<?php echo esc_url( admin_url( 'admin.php?page=chp-handover-print' ) ); ?>"><?php esc_html_e( 'Generate Handover Document', 'client-handover-pro' ); ?></a>
					<p class="description"><?php esc_html_e( 'Opens a print-ready page — use your browser\'s Print → Save as PDF to export.', 'client-handover-pro' ); ?></p>
				<?php else : ?>
					<?php CHP_License::render_upsell( __( 'PDF Handover', 'client-handover-pro' ) ); ?>
				<?php endif; ?>
			</div>

			<div class="chp-card">
				<div class="chp-card__label"><?php esc_html_e( 'Client Approval', 'client-handover-pro' ); ?></div>
				<?php if ( ! $is_pro ) : ?>
					<?php CHP_License::render_upsell( __( 'Client Approval', 'client-handover-pro' ) ); ?>
				<?php else : ?>
					<p>
						<?php esc_html_e( 'Status:', 'client-handover-pro' ); ?>
						<span class="chp-badge chp-badge--<?php echo 'approved' === $approval['status'] ? 'pass' : ( 'changes_requested' === $approval['status'] ? 'fail' : 'warn' ); ?>">
							<?php echo esc_html( ucwords( str_replace( '_', ' ', $approval['status'] ) ) ); ?>
						</span>
						<?php if ( ! empty( $approval['user'] ) ) : ?>
							&mdash; <?php echo esc_html( $approval['user'] ); ?>, <?php echo esc_html( human_time_diff( $approval['timestamp'] ) . ' ' . __( 'ago', 'client-handover-pro' ) ); ?>
						<?php endif; ?>
					</p>
					<?php if ( ! empty( $approval['comment'] ) ) : ?>
						<p><em><?php echo esc_html( $approval['comment'] ); ?></em></p>
					<?php endif; ?>
					<form method="post" class="chp-form">
						<?php wp_nonce_field( 'chp_approval_save', 'chp_approval_nonce' ); ?>
						<textarea name="approval_comment" class="widefat" rows="2" placeholder="<?php esc_attr_e( 'Optional comment', 'client-handover-pro' ); ?>"></textarea>
						<div class="chp-header-actions" style="margin-top:8px;">
							<button type="submit" name="approval_status" value="approved" class="chp-btn chp-btn--primary"><?php esc_html_e( 'Approve', 'client-handover-pro' ); ?></button>
							<button type="submit" name="approval_status" value="changes_requested" class="chp-btn chp-btn--outline"><?php esc_html_e( 'Request Changes', 'client-handover-pro' ); ?></button>
						</div>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function render_print_page() {
		if ( ! current_user_can( 'manage_options' ) || ! CHP_License::is_pro() ) {
			wp_die( esc_html__( 'This feature requires Client Handover Pro.', 'client-handover-pro' ) );
		}

		$report      = self::launch_report();
		$settings    = CHP_Plugin::get_settings();
		$brand       = get_option( 'chp_brand_assets', array() );
		$notes       = get_option( 'chp_client_notes', array() );
		$vault_module = class_exists( 'CHP_Credentials_Vault' ) ? new CHP_Credentials_Vault() : null;
		$vault       = $vault_module ? $vault_module->get_decrypted_for_handover() : array();
		$pages       = get_pages();
		$tutorials   = get_posts( array( 'post_type' => 'chp_tutorial', 'posts_per_page' => -1, 'post_status' => 'publish' ) );

		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="utf-8" />
			<title><?php echo esc_html( get_bloginfo( 'name' ) . ' — ' . __( 'Handover Document', 'client-handover-pro' ) ); ?></title>
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; color:#111; max-width:820px; margin:40px auto; padding:0 24px; }
				h1 { color:#1E7F5C; }
				h2 { border-bottom:2px solid #1E7F5C; padding-bottom:6px; margin-top:36px; }
				table { width:100%; border-collapse:collapse; margin:12px 0; }
				td, th { text-align:left; padding:6px 8px; border-bottom:1px solid #eee; }
				.chp-print-toolbar { text-align:right; margin-bottom:24px; }
				@media print { .chp-print-toolbar { display:none; } }
			</style>
		</head>
		<body>
			<div class="chp-print-toolbar">
				<button onclick="window.print()"><?php esc_html_e( 'Print / Save as PDF', 'client-handover-pro' ); ?></button>
			</div>

			<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
			<p><?php esc_html_e( 'Website Handover Document', 'client-handover-pro' ); ?> — <?php echo esc_html( date_i18n( get_option( 'date_format' ) ) ); ?></p>

			<h2><?php esc_html_e( 'Website Overview', 'client-handover-pro' ); ?></h2>
			<p><?php echo esc_html( home_url() ); ?></p>
			<table>
				<tr><td><?php esc_html_e( 'Overall Score', 'client-handover-pro' ); ?></td><td><?php echo (int) $report['overall']; ?>%</td></tr>
				<tr><td><?php esc_html_e( 'Ready for Launch', 'client-handover-pro' ); ?></td><td><?php echo $report['ready'] ? 'YES' : 'NOT YET'; ?></td></tr>
			</table>

			<h2><?php esc_html_e( 'Pages', 'client-handover-pro' ); ?></h2>
			<table>
				<?php foreach ( $pages as $page ) : ?>
					<tr><td><?php echo esc_html( $page->post_title ); ?></td><td><?php echo esc_html( get_permalink( $page ) ); ?></td></tr>
				<?php endforeach; ?>
			</table>

			<h2><?php esc_html_e( 'SEO', 'client-handover-pro' ); ?></h2>
			<table>
				<tr><td><?php esc_html_e( 'SEO Score', 'client-handover-pro' ); ?></td><td><?php echo (int) $report['seo']; ?></td></tr>
				<tr><td><?php esc_html_e( 'Performance Score', 'client-handover-pro' ); ?></td><td><?php echo (int) $report['performance']; ?></td></tr>
				<tr><td><?php esc_html_e( 'Accessibility Score', 'client-handover-pro' ); ?></td><td><?php echo (int) $report['accessibility']; ?></td></tr>
			</table>

			<?php if ( ! empty( $vault ) && current_user_can( 'manage_options' ) ) : ?>
				<h2><?php esc_html_e( 'Hosting & Credentials', 'client-handover-pro' ); ?></h2>
				<table>
					<?php foreach ( $vault as $key => $value ) : ?>
						<?php if ( '' !== $value ) : ?>
							<tr><td><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></td><td><?php echo esc_html( $value ); ?></td></tr>
						<?php endif; ?>
					<?php endforeach; ?>
				</table>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Brand Assets', 'client-handover-pro' ); ?></h2>
			<p><?php esc_html_e( 'Colors:', 'client-handover-pro' ); ?> <?php echo esc_html( implode( ', ', (array) ( $brand['colors'] ?? array() ) ) ); ?></p>
			<p><?php esc_html_e( 'Fonts:', 'client-handover-pro' ); ?> <?php echo esc_html( implode( ', ', (array) ( $brand['fonts'] ?? array() ) ) ); ?></p>

			<h2><?php esc_html_e( 'Tutorial Links', 'client-handover-pro' ); ?></h2>
			<table>
				<?php foreach ( $tutorials as $tutorial ) : ?>
					<tr><td><?php echo esc_html( get_the_title( $tutorial ) ); ?></td></tr>
				<?php endforeach; ?>
			</table>

			<h2><?php esc_html_e( 'Maintenance & Renewal Notes', 'client-handover-pro' ); ?></h2>
			<table>
				<?php foreach ( (array) $notes as $note ) : ?>
					<tr><td><?php echo esc_html( $note['title'] ); ?></td><td><?php echo esc_html( $note['date'] ); ?></td></tr>
				<?php endforeach; ?>
			</table>

			<h2><?php esc_html_e( 'Support Information', 'client-handover-pro' ); ?></h2>
			<p><?php echo esc_html( $settings['agency_name'] ); ?> &mdash; <?php echo esc_html( $settings['agency_email'] ); ?></p>
		</body>
		</html>
		<?php
	}
}
