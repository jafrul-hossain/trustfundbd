<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The "Website Ready" hero dashboard and the detailed checklist page.
 */
class CHP_Dashboard {

	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		add_filter( 'debug_information', array( $this, 'add_site_health_info' ) );
	}

	public function register_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget( 'chp_health_widget', __( 'Website Health — Client Handover Pro', 'client-handover-pro' ), array( $this, 'render_dashboard_widget' ) );
	}

	public function render_dashboard_widget() {
		$scan = CHP_Checklist::get_last_scan();
		$status = $scan['score'] >= 80 ? 'pass' : ( $scan['score'] >= 50 ? 'warn' : 'fail' );
		?>
		<div class="chp-widget">
			<span class="chp-check-icon chp-check-icon--<?php echo esc_attr( $status ); ?>" style="display:inline-block;vertical-align:middle;margin-right:8px;"></span>
			<strong style="font-size:20px;"><?php echo (int) $scan['score']; ?>%</strong>
			<?php esc_html_e( 'website health', 'client-handover-pro' ); ?>
			<p>
				<?php
				printf(
					/* translators: 1: passed count, 2: total count */
					esc_html__( '%1$d of %2$d launch checks passing.', 'client-handover-pro' ),
					(int) $scan['passed'],
					(int) $scan['total']
				);
				?>
			</p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=chp-dashboard' ) ); ?>"><?php esc_html_e( 'Open Client Handover Pro →', 'client-handover-pro' ); ?></a></p>
		</div>
		<?php
	}

	public function add_site_health_info( $info ) {
		$scan = CHP_Checklist::get_last_scan();
		$info['chp'] = array(
			'label'  => __( 'Client Handover Pro', 'client-handover-pro' ),
			'fields' => array(
				'plan'        => array(
					'label' => __( 'Plan', 'client-handover-pro' ),
					'value' => ucfirst( CHP_License::tier() ),
				),
				'health_score' => array(
					'label' => __( 'Website Health Score', 'client-handover-pro' ),
					'value' => $scan['score'] . '%',
				),
				'checklist'   => array(
					'label' => __( 'Launch Checklist', 'client-handover-pro' ),
					'value' => sprintf( '%d / %d', $scan['passed'], $scan['total'] ),
				),
				'last_scan'   => array(
					'label' => __( 'Last Scanned', 'client-handover-pro' ),
					'value' => ! empty( $scan['scanned_at'] ) ? date_i18n( 'F j, Y g:i a', $scan['scanned_at'] ) : __( 'Never', 'client-handover-pro' ),
				),
				'client_mode' => array(
					'label' => __( 'Client Dashboard Enabled', 'client-handover-pro' ),
					'value' => CHP_Plugin::get_setting( 'client_mode_enabled' ) ? __( 'Yes', 'client-handover-pro' ) : __( 'No', 'client-handover-pro' ),
				),
			),
		);
		return $info;
	}

	public function render_dashboard_page() {
		$scan       = CHP_Checklist::get_last_scan();
		$categories = $scan['categories'];
		$score      = $scan['score'];
		$passed     = $scan['passed'];
		$total      = $scan['total'];
		$scanned_at = ! empty( $scan['scanned_at'] ) ? human_time_diff( $scan['scanned_at'] ) . ' ' . __( 'ago', 'client-handover-pro' ) : __( 'never', 'client-handover-pro' );

		?>
		<div class="wrap chp-wrap">
			<div class="chp-header">
				<div>
					<h1 class="chp-title"><?php esc_html_e( 'Website Ready', 'client-handover-pro' ); ?></h1>
					<p class="chp-subtitle"><?php esc_html_e( 'Deliver every website like a professional agency.', 'client-handover-pro' ); ?></p>
				</div>
				<div class="chp-header-actions">
					<button type="button" class="chp-btn chp-btn--outline" id="chp-run-scan"><?php esc_html_e( 'Run Scan', 'client-handover-pro' ); ?></button>
					<button type="button" class="chp-btn chp-btn--primary" id="chp-generate-client-mode"><?php esc_html_e( 'Generate Client Mode', 'client-handover-pro' ); ?></button>
				</div>
			</div>

			<?php CHP_Onboarding::render_dashboard_tracker(); ?>

			<div class="chp-grid chp-grid--hero">
				<div class="chp-card chp-card--dark">
					<div class="chp-card__label"><?php esc_html_e( 'Website Health', 'client-handover-pro' ); ?></div>
					<div class="chp-card__big-number" id="chp-health-score"><?php echo (int) $score; ?>%</div>
					<div class="chp-card__meta"><?php printf( esc_html__( 'Last scanned %s', 'client-handover-pro' ), esc_html( $scanned_at ) ); ?></div>
				</div>

				<div class="chp-card">
					<div class="chp-card__label"><?php esc_html_e( 'Launch Checklist', 'client-handover-pro' ); ?></div>
					<div class="chp-card__big-number" id="chp-checklist-count"><?php echo (int) $passed; ?> / <?php echo (int) $total; ?></div>
					<div class="chp-card__meta"><?php esc_html_e( 'Completed', 'client-handover-pro' ); ?></div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=chp-checklist' ) ); ?>" class="chp-link"><?php esc_html_e( 'View full checklist →', 'client-handover-pro' ); ?></a>
				</div>

				<div class="chp-card chp-card--checks">
					<div class="chp-card__label"><?php esc_html_e( 'Category Status', 'client-handover-pro' ); ?></div>
					<ul class="chp-check-list">
						<?php foreach ( $categories as $cat ) : ?>
							<?php $cat_status = self::category_status( $cat ); ?>
							<li class="chp-check-list__item chp-check-list__item--<?php echo esc_attr( $cat_status ); ?>">
								<span class="chp-check-icon chp-check-icon--<?php echo esc_attr( $cat_status ); ?>"></span>
								<?php echo esc_html( $cat['label'] ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>

			<div id="chp-scan-result"></div>

			<?php $this->render_quick_links(); ?>
		</div>
		<?php
	}

	public function render_checklist_page() {
		$scan       = CHP_Checklist::get_last_scan();
		$categories = $scan['categories'];
		?>
		<div class="wrap chp-wrap">
			<div class="chp-header">
				<div>
					<h1 class="chp-title"><?php esc_html_e( 'Launch Checklist', 'client-handover-pro' ); ?></h1>
					<p class="chp-subtitle">
						<?php
						printf(
							/* translators: 1: passed count, 2: total count */
							esc_html__( '%1$d / %2$d checks passing', 'client-handover-pro' ),
							(int) $scan['passed'],
							(int) $scan['total']
						);
						?>
					</p>
				</div>
				<div class="chp-header-actions">
					<button type="button" class="chp-btn chp-btn--primary" id="chp-run-scan"><?php esc_html_e( 'Run Scan', 'client-handover-pro' ); ?></button>
				</div>
			</div>

			<div id="chp-scan-result">
				<?php $this->render_categories( $categories ); ?>
			</div>
		</div>
		<?php
	}

	public static function render_categories( $categories ) {
		foreach ( $categories as $cat ) {
			?>
			<div class="chp-card chp-checklist-category">
				<h2><?php echo esc_html( $cat['label'] ); ?></h2>
				<table class="chp-table">
					<tbody>
						<?php foreach ( $cat['items'] as $item ) : ?>
							<tr>
								<td class="chp-table__status">
									<span class="chp-check-icon chp-check-icon--<?php echo esc_attr( $item['status'] ); ?>"></span>
								</td>
								<td class="chp-table__label"><?php echo esc_html( $item['label'] ); ?></td>
								<td class="chp-table__message"><?php echo esc_html( $item['message'] ); ?></td>
								<td class="chp-table__action">
									<?php if ( ! empty( $item['fix_url'] ) && 'pass' !== $item['status'] ) : ?>
										<a href="<?php echo esc_url( $item['fix_url'] ); ?>" class="chp-link"><?php esc_html_e( 'Fix', 'client-handover-pro' ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
		}
	}

	private static function category_status( $cat ) {
		$has_fail = false;
		$has_warn = false;
		foreach ( $cat['items'] as $item ) {
			if ( 'fail' === $item['status'] ) {
				$has_fail = true;
			} elseif ( 'warn' === $item['status'] ) {
				$has_warn = true;
			}
		}
		if ( $has_fail ) {
			return 'fail';
		}
		if ( $has_warn ) {
			return 'warn';
		}
		return 'pass';
	}

	private function render_quick_links() {
		$links = array(
			array( 'label' => __( 'White Label', 'client-handover-pro' ), 'page' => 'chp-white-label' ),
			array( 'label' => __( 'Admin Lock', 'client-handover-pro' ), 'page' => 'chp-admin-lock' ),
			array( 'label' => __( 'Tutorial Center', 'client-handover-pro' ), 'page' => 'chp-tutorials' ),
			array( 'label' => __( 'Maintenance Mode', 'client-handover-pro' ), 'page' => 'chp-maintenance' ),
			array( 'label' => __( 'Site Cleanup', 'client-handover-pro' ), 'page' => 'chp-cleanup' ),
			array( 'label' => __( 'Handover & Reports', 'client-handover-pro' ), 'page' => 'chp-handover' ),
		);
		?>
		<div class="chp-card">
			<div class="chp-card__label"><?php esc_html_e( 'Next Steps', 'client-handover-pro' ); ?></div>
			<div class="chp-quick-links">
				<?php foreach ( $links as $link ) : ?>
					<a class="chp-quick-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $link['page'] ) ); ?>"><?php echo esc_html( $link['label'] ); ?></a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
