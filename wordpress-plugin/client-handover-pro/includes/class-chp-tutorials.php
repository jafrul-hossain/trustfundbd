<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tutorial Center: simple lessons for clients (video + numbered steps).
 * Free plan is capped at 3 tutorials; Pro/Agency is unlimited.
 */
class CHP_Tutorials {

	const FREE_LIMIT = 3;

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_chp_tutorial', array( $this, 'save_meta' ) );
		add_action( 'admin_menu', array( $this, 'register_hidden_view_page' ) );
		add_shortcode( 'chp_tutorials', array( $this, 'shortcode' ) );
	}

	public function register_post_type() {
		register_post_type(
			'chp_tutorial',
			array(
				'labels'       => array(
					'name'          => __( 'Tutorials', 'client-handover-pro' ),
					'singular_name' => __( 'Tutorial', 'client-handover-pro' ),
					'add_new_item'  => __( 'Add New Tutorial', 'client-handover-pro' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title', 'editor' ),
				'capability_type' => 'post',
				'map_meta_cap' => true,
			)
		);
	}

	public function add_meta_boxes() {
		add_meta_box( 'chp_tutorial_details', __( 'Tutorial Details', 'client-handover-pro' ), array( $this, 'render_meta_box' ), 'chp_tutorial', 'normal', 'high' );
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'chp_tutorial_save', 'chp_tutorial_nonce' );
		$video_url = get_post_meta( $post->ID, '_chp_video_url', true );
		$steps     = get_post_meta( $post->ID, '_chp_steps', true );
		$pdf_url   = get_post_meta( $post->ID, '_chp_pdf_url', true );
		$image_url = get_post_meta( $post->ID, '_chp_image_url', true );
		?>
		<p>
			<label><strong><?php esc_html_e( 'Video URL (YouTube, Vimeo, or Loom)', 'client-handover-pro' ); ?></strong></label><br />
			<input type="text" name="chp_video_url" class="widefat" value="<?php echo esc_attr( $video_url ); ?>" placeholder="https://www.youtube.com/watch?v=..." />
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Steps (one per line)', 'client-handover-pro' ); ?></strong></label><br />
			<textarea name="chp_steps" class="widefat" rows="5" placeholder="Click Edit Homepage&#10;Change the text&#10;Click Save"><?php echo esc_textarea( $steps ); ?></textarea>
		</p>
		<p>
			<label><strong><?php esc_html_e( 'PDF Attachment URL', 'client-handover-pro' ); ?></strong></label><br />
			<input type="text" name="chp_pdf_url" class="widefat chp-media-url" value="<?php echo esc_attr( $pdf_url ); ?>" />
			<button type="button" class="chp-btn chp-btn--outline chp-media-select"><?php esc_html_e( 'Choose File', 'client-handover-pro' ); ?></button>
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Image URL', 'client-handover-pro' ); ?></strong></label><br />
			<input type="text" name="chp_image_url" class="widefat chp-media-url" value="<?php echo esc_attr( $image_url ); ?>" />
			<button type="button" class="chp-btn chp-btn--outline chp-media-select"><?php esc_html_e( 'Choose Image', 'client-handover-pro' ); ?></button>
		</p>
		<?php
	}

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['chp_tutorial_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['chp_tutorial_nonce'] ) ), 'chp_tutorial_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_chp_video_url', esc_url_raw( wp_unslash( $_POST['chp_video_url'] ?? '' ) ) );
		update_post_meta( $post_id, '_chp_steps', sanitize_textarea_field( wp_unslash( $_POST['chp_steps'] ?? '' ) ) );
		update_post_meta( $post_id, '_chp_pdf_url', esc_url_raw( wp_unslash( $_POST['chp_pdf_url'] ?? '' ) ) );
		update_post_meta( $post_id, '_chp_image_url', esc_url_raw( wp_unslash( $_POST['chp_image_url'] ?? '' ) ) );
	}

	/**
	 * Hidden page (no visible menu entry) so client-role users can view
	 * tutorials via a plain link from their Quick Actions dashboard.
	 */
	public function register_hidden_view_page() {
		add_submenu_page( null, __( 'Website Guide', 'client-handover-pro' ), __( 'Website Guide', 'client-handover-pro' ), 'read', 'chp-tutorials-view', array( $this, 'render_client_view' ) );
	}

	public function render_admin_page() {
		$tutorials = get_posts( array( 'post_type' => 'chp_tutorial', 'posts_per_page' => -1, 'post_status' => array( 'publish', 'draft' ) ) );
		$is_pro    = CHP_License::is_pro();
		$at_limit  = ! $is_pro && count( $tutorials ) >= self::FREE_LIMIT;
		?>
		<div class="wrap chp-wrap">
			<div class="chp-header">
				<div>
					<h1 class="chp-title"><?php esc_html_e( 'Tutorial Center', 'client-handover-pro' ); ?></h1>
					<p class="chp-subtitle"><?php esc_html_e( 'Teach clients how to update their own site.', 'client-handover-pro' ); ?></p>
				</div>
				<div class="chp-header-actions">
					<?php if ( $at_limit ) : ?>
						<span class="chp-badge chp-badge--warn"><?php esc_html_e( 'Free plan limit reached (3)', 'client-handover-pro' ); ?></span>
					<?php else : ?>
						<a class="chp-btn chp-btn--primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=chp_tutorial' ) ); ?>"><?php esc_html_e( 'Add New Tutorial', 'client-handover-pro' ); ?></a>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $at_limit ) : ?>
				<?php CHP_License::render_upsell( __( 'Unlimited tutorials', 'client-handover-pro' ) ); ?>
			<?php endif; ?>

			<div class="chp-card">
				<table class="chp-table">
					<tbody>
					<?php if ( empty( $tutorials ) ) : ?>
						<tr><td><?php esc_html_e( 'No tutorials yet.', 'client-handover-pro' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $tutorials as $tutorial ) : ?>
						<tr>
							<td class="chp-table__label"><?php echo esc_html( get_the_title( $tutorial ) ); ?></td>
							<td class="chp-table__message"><?php echo esc_html( ucfirst( $tutorial->post_status ) ); ?></td>
							<td class="chp-table__action">
								<a class="chp-link" href="<?php echo esc_url( get_edit_post_link( $tutorial ) ); ?>"><?php esc_html_e( 'Edit', 'client-handover-pro' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<p><a class="chp-link" href="<?php echo esc_url( admin_url( 'admin.php?page=chp-tutorials-view' ) ); ?>" target="_blank"><?php esc_html_e( 'Preview the client-facing Website Guide →', 'client-handover-pro' ); ?></a></p>
		</div>
		<?php
	}

	public function render_client_view() {
		?>
		<div class="wrap chp-wrap">
			<h1 class="chp-title"><?php esc_html_e( 'Website Guide', 'client-handover-pro' ); ?></h1>
			<p class="chp-subtitle"><?php esc_html_e( 'Short lessons on how to update your website.', 'client-handover-pro' ); ?></p>
			<?php echo do_shortcode( '[chp_tutorials]' ); ?>
		</div>
		<?php
	}

	public function shortcode() {
		$tutorials = get_posts( array( 'post_type' => 'chp_tutorial', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
		if ( empty( $tutorials ) ) {
			return '<p>' . esc_html__( 'No tutorials published yet.', 'client-handover-pro' ) . '</p>';
		}

		ob_start();
		echo '<div class="chp-tutorial-list">';
		foreach ( $tutorials as $tutorial ) {
			$video_url = get_post_meta( $tutorial->ID, '_chp_video_url', true );
			$steps     = get_post_meta( $tutorial->ID, '_chp_steps', true );
			$pdf_url   = get_post_meta( $tutorial->ID, '_chp_pdf_url', true );
			$image_url = get_post_meta( $tutorial->ID, '_chp_image_url', true );

			echo '<div class="chp-card chp-tutorial">';
			echo '<h2>' . esc_html( get_the_title( $tutorial ) ) . '</h2>';

			$embed = self::embed_for_url( $video_url );
			if ( $embed ) {
				echo '<div class="chp-tutorial__video">' . $embed . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from a whitelisted iframe template.
			}

			if ( $image_url ) {
				echo '<img class="chp-tutorial__image" src="' . esc_url( $image_url ) . '" alt="" />';
			}

			if ( $steps ) {
				echo '<ol class="chp-tutorial__steps">';
				foreach ( preg_split( '/\r\n|\r|\n/', $steps ) as $line ) {
					$line = trim( $line );
					if ( '' !== $line ) {
						echo '<li>' . esc_html( $line ) . '</li>';
					}
				}
				echo '</ol>';
			}

			if ( $pdf_url ) {
				echo '<p><a class="chp-link" href="' . esc_url( $pdf_url ) . '" target="_blank">' . esc_html__( 'Download PDF guide', 'client-handover-pro' ) . '</a></p>';
			}

			echo '</div>';
		}
		echo '</div>';

		return ob_get_clean();
	}

	public static function embed_for_url( $url ) {
		if ( empty( $url ) ) {
			return '';
		}

		if ( preg_match( '~(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]+)~', $url, $m ) ) {
			return '<iframe width="100%" height="360" src="https://www.youtube.com/embed/' . esc_attr( $m[1] ) . '" frameborder="0" allowfullscreen loading="lazy"></iframe>';
		}
		if ( preg_match( '~vimeo\.com/(\d+)~', $url, $m ) ) {
			return '<iframe width="100%" height="360" src="https://player.vimeo.com/video/' . esc_attr( $m[1] ) . '" frameborder="0" allowfullscreen loading="lazy"></iframe>';
		}
		if ( preg_match( '~loom\.com/share/([A-Za-z0-9]+)~', $url, $m ) ) {
			return '<iframe width="100%" height="360" src="https://www.loom.com/embed/' . esc_attr( $m[1] ) . '" frameborder="0" allowfullscreen loading="lazy"></iframe>';
		}

		return '';
	}
}
