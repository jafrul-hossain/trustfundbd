<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site Cleanup: removes the WordPress default cruft that has no place
 * on a client's live site.
 */
class CHP_Site_Cleanup {

	public static function tasks() {
		return array(
			'hello_dolly'    => __( 'Hello Dolly plugin', 'client-handover-pro' ),
			'unused_themes'  => __( 'Unused themes', 'client-handover-pro' ),
			'dummy_content'  => __( 'Dummy posts & pages ("Hello world!", "Sample Page")', 'client-handover-pro' ),
			'sample_comment' => __( 'Sample comment', 'client-handover-pro' ),
			'spam_comments'  => __( 'Spam & trashed comments', 'client-handover-pro' ),
			'revisions'      => __( 'Post revisions', 'client-handover-pro' ),
			'empty_media'    => __( 'Unattached / empty media', 'client-handover-pro' ),
		);
	}

	public static function count( $task ) {
		global $wpdb;
		switch ( $task ) {
			case 'hello_dolly':
				return file_exists( WP_PLUGIN_DIR . '/hello.php' ) ? 1 : 0;

			case 'unused_themes':
				$themes = wp_get_themes();
				$active = get_stylesheet();
				$parent = get_template();
				$count  = 0;
				foreach ( $themes as $slug => $theme ) {
					if ( $slug !== $active && $slug !== $parent ) {
						$count++;
					}
				}
				return $count;

			case 'dummy_content':
				$count = 0;
				$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
				if ( $hello ) {
					$count++;
				}
				$sample = get_page_by_path( 'sample-page', OBJECT, 'page' );
				if ( $sample ) {
					$count++;
				}
				return $count;

			case 'sample_comment':
				return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_author = 'A WordPress Commenter'" );

			case 'spam_comments':
				return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash')" );

			case 'revisions':
				return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );

			case 'empty_media':
				return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_parent = 0" );
		}
		return 0;
	}

	public static function run( $task ) {
		global $wpdb;
		$removed = 0;

		switch ( $task ) {
			case 'hello_dolly':
				if ( file_exists( WP_PLUGIN_DIR . '/hello.php' ) ) {
					if ( ! function_exists( 'deactivate_plugins' ) ) {
						require_once ABSPATH . 'wp-admin/includes/plugin.php';
					}
					deactivate_plugins( 'hello.php' );
					if ( ! function_exists( 'delete_plugins' ) ) {
						require_once ABSPATH . 'wp-admin/includes/file.php';
					}
					delete_plugins( array( 'hello.php' ) );
					$removed = 1;
				}
				break;

			case 'unused_themes':
				$themes = wp_get_themes();
				$active = get_stylesheet();
				$parent = get_template();
				if ( ! function_exists( 'delete_theme' ) ) {
					require_once ABSPATH . 'wp-admin/includes/theme.php';
				}
				foreach ( $themes as $slug => $theme ) {
					if ( $slug !== $active && $slug !== $parent ) {
						$result = delete_theme( $slug );
						if ( ! is_wp_error( $result ) ) {
							$removed++;
						}
					}
				}
				break;

			case 'dummy_content':
				$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
				if ( $hello ) {
					wp_delete_post( $hello->ID, true );
					$removed++;
				}
				$sample = get_page_by_path( 'sample-page', OBJECT, 'page' );
				if ( $sample ) {
					wp_delete_post( $sample->ID, true );
					$removed++;
				}
				break;

			case 'sample_comment':
				$ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_author = 'A WordPress Commenter'" );
				foreach ( $ids as $id ) {
					wp_delete_comment( $id, true );
					$removed++;
				}
				break;

			case 'spam_comments':
				$ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash')" );
				foreach ( $ids as $id ) {
					wp_delete_comment( $id, true );
					$removed++;
				}
				break;

			case 'revisions':
				$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'" );
				foreach ( $ids as $id ) {
					wp_delete_post_revision( $id );
					$removed++;
				}
				break;

			case 'empty_media':
				$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_parent = 0" );
				foreach ( $ids as $id ) {
					wp_delete_attachment( $id, true );
					$removed++;
				}
				break;
		}

		return $removed;
	}

	public function render_page() {
		$tasks = self::tasks();
		?>
		<div class="wrap chp-wrap">
			<h1 class="chp-title"><?php esc_html_e( 'Site Cleanup', 'client-handover-pro' ); ?></h1>
			<p class="chp-subtitle"><?php esc_html_e( 'Remove default WordPress clutter before handover.', 'client-handover-pro' ); ?></p>

			<div class="chp-card">
				<form id="chp-cleanup-form">
					<table class="chp-table">
						<tbody>
						<?php foreach ( $tasks as $key => $label ) : ?>
							<tr>
								<td>
									<label class="chp-checkbox">
										<input type="checkbox" name="tasks[]" value="<?php echo esc_attr( $key ); ?>" checked="checked" />
										<?php echo esc_html( $label ); ?>
									</label>
								</td>
								<td class="chp-table__message" id="chp-cleanup-count-<?php echo esc_attr( $key ); ?>">
									<?php echo esc_html( sprintf( _n( '%d item found', '%d items found', self::count( $key ), 'client-handover-pro' ), self::count( $key ) ) ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<button type="button" id="chp-run-cleanup" class="chp-btn chp-btn--primary"><?php esc_html_e( 'Clean Now', 'client-handover-pro' ); ?></button>
				</form>
				<div id="chp-cleanup-result"></div>
			</div>
		</div>
		<?php
	}
}
