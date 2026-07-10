<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brand Assets: a single place to store the client's logo, colors,
 * fonts, favicon and social links — downloadable at any time.
 */
class CHP_Brand_Assets {

	public function __construct() {
		add_action( 'admin_post_chp_download_brand_kit', array( $this, 'download_brand_kit' ) );
	}

	private function get_assets() {
		return wp_parse_args(
			get_option( 'chp_brand_assets', array() ),
			array(
				'logo'         => '',
				'favicon'      => '',
				'colors'       => array(),
				'fonts'        => array(),
				'social_links' => array(),
			)
		);
	}

	public function render_page() {
		$assets = $this->get_assets();

		if ( isset( $_POST['chp_brand_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['chp_brand_nonce'] ) ), 'chp_brand_save' ) ) {
			$colors_raw = sanitize_text_field( wp_unslash( $_POST['colors'] ?? '' ) );
			$fonts_raw  = sanitize_text_field( wp_unslash( $_POST['fonts'] ?? '' ) );
			$social_raw = sanitize_textarea_field( wp_unslash( $_POST['social_links'] ?? '' ) );

			$colors = array_filter( array_map( 'trim', explode( ',', $colors_raw ) ) );
			$fonts  = array_filter( array_map( 'trim', explode( ',', $fonts_raw ) ) );

			$social_links = array();
			foreach ( preg_split( '/\r\n|\r|\n/', $social_raw ) as $line ) {
				if ( false !== strpos( $line, '|' ) ) {
					list( $platform, $url ) = array_map( 'trim', explode( '|', $line, 2 ) );
					if ( $platform && $url ) {
						$social_links[] = array( 'platform' => sanitize_text_field( $platform ), 'url' => esc_url_raw( $url ) );
					}
				}
			}

			$assets = array(
				'logo'         => esc_url_raw( wp_unslash( $_POST['logo'] ?? '' ) ),
				'favicon'      => esc_url_raw( wp_unslash( $_POST['favicon'] ?? '' ) ),
				'colors'       => array_values( $colors ),
				'fonts'        => array_values( $fonts ),
				'social_links' => $social_links,
			);
			update_option( 'chp_brand_assets', $assets );

			if ( $assets['favicon'] ) {
				$attachment_id = attachment_url_to_postid( $assets['favicon'] );
				if ( $attachment_id ) {
					update_option( 'site_icon', $attachment_id );
				}
			}

			echo '<div class="notice notice-success"><p>' . esc_html__( 'Brand assets saved.', 'client-handover-pro' ) . '</p></div>';
		}

		$colors_value = implode( ', ', (array) $assets['colors'] );
		$fonts_value  = implode( ', ', (array) $assets['fonts'] );
		$social_value = '';
		foreach ( (array) $assets['social_links'] as $link ) {
			$social_value .= $link['platform'] . ' | ' . $link['url'] . "\n";
		}

		?>
		<div class="wrap chp-wrap">
			<div class="chp-header">
				<div>
					<h1 class="chp-title"><?php esc_html_e( 'Brand Assets', 'client-handover-pro' ); ?></h1>
					<p class="chp-subtitle"><?php esc_html_e( 'Keep the client\'s brand kit in one place.', 'client-handover-pro' ); ?></p>
				</div>
				<div class="chp-header-actions">
					<a class="chp-btn chp-btn--outline" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=chp_download_brand_kit' ), 'chp_download_brand_kit' ) ); ?>"><?php esc_html_e( 'Download Brand Kit', 'client-handover-pro' ); ?></a>
				</div>
			</div>

			<form method="post" class="chp-card chp-form">
				<?php wp_nonce_field( 'chp_brand_save', 'chp_brand_nonce' ); ?>

				<div class="chp-form-row">
					<label><?php esc_html_e( 'Logo', 'client-handover-pro' ); ?></label>
					<div class="chp-media-picker">
						<input type="text" name="logo" class="regular-text chp-media-url" value="<?php echo esc_attr( $assets['logo'] ); ?>" />
						<button type="button" class="chp-btn chp-btn--outline chp-media-select"><?php esc_html_e( 'Choose Image', 'client-handover-pro' ); ?></button>
					</div>
				</div>

				<div class="chp-form-row">
					<label><?php esc_html_e( 'Favicon', 'client-handover-pro' ); ?></label>
					<div class="chp-media-picker">
						<input type="text" name="favicon" class="regular-text chp-media-url" value="<?php echo esc_attr( $assets['favicon'] ); ?>" />
						<button type="button" class="chp-btn chp-btn--outline chp-media-select"><?php esc_html_e( 'Choose Image', 'client-handover-pro' ); ?></button>
					</div>
				</div>

				<div class="chp-form-row">
					<label><?php esc_html_e( 'Brand Colors (comma separated hex)', 'client-handover-pro' ); ?></label>
					<input type="text" name="colors" class="regular-text" value="<?php echo esc_attr( $colors_value ); ?>" placeholder="#1E7F5C, #0B1F16, #F5F7F6" />
				</div>

				<div class="chp-form-row">
					<label><?php esc_html_e( 'Fonts (comma separated)', 'client-handover-pro' ); ?></label>
					<input type="text" name="fonts" class="regular-text" value="<?php echo esc_attr( $fonts_value ); ?>" placeholder="Inter, Poppins" />
				</div>

				<div class="chp-form-row">
					<label><?php esc_html_e( 'Social Links (one per line: Platform | URL)', 'client-handover-pro' ); ?></label>
					<textarea class="widefat" name="social_links" rows="4" placeholder="Facebook | https://facebook.com/..."><?php echo esc_textarea( trim( $social_value ) ); ?></textarea>
				</div>

				<button type="submit" class="chp-btn chp-btn--primary"><?php esc_html_e( 'Save Changes', 'client-handover-pro' ); ?></button>
			</form>
		</div>
		<?php
	}

	public function download_brand_kit() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'chp_download_brand_kit' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'client-handover-pro' ) );
		}

		$assets           = $this->get_assets();
		$assets['site']    = get_bloginfo( 'name' );
		$assets['generated'] = gmdate( 'c' );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="brand-kit-' . sanitize_title( get_bloginfo( 'name' ) ) . '.json"' );
		echo wp_json_encode( $assets, JSON_PRETTY_PRINT );
		exit;
	}
}
