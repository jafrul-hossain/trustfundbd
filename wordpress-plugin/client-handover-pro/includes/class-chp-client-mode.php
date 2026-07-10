<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Dashboard: replaces the default wp-admin menu with a simple,
 * friendly "Quick Actions" screen for the assigned client role.
 */
class CHP_Client_Mode {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'maybe_build_client_menu' ), 999 );
		add_action( 'admin_bar_menu', array( $this, 'maybe_trim_admin_bar' ), 999 );
		add_action( 'admin_init', array( $this, 'maybe_redirect_client_role' ) );
	}

	private function is_enabled_for_current_user() {
		$settings = CHP_Plugin::get_settings();
		if ( empty( $settings['client_mode_enabled'] ) ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return false; // Admins always see the real dashboard.
		}
		$client_role = ! empty( $settings['client_role'] ) ? $settings['client_role'] : 'editor';
		$user        = wp_get_current_user();
		return in_array( $client_role, (array) $user->roles, true );
	}

	public function maybe_build_client_menu() {
		if ( ! $this->is_enabled_for_current_user() ) {
			return;
		}

		global $menu;

		$allowed_slugs = array( 'chp-client-home' );

		if ( ! empty( $menu ) ) {
			foreach ( $menu as $key => $item ) {
				$slug = isset( $item[2] ) ? $item[2] : '';
				if ( ! in_array( $slug, $allowed_slugs, true ) ) {
					remove_menu_page( $slug );
				}
			}
		}

		add_menu_page(
			__( 'My Website', 'client-handover-pro' ),
			__( 'My Website', 'client-handover-pro' ),
			'read',
			'chp-client-home',
			array( $this, 'render_client_home' ),
			'dashicons-admin-home',
			2
		);
	}

	public function maybe_redirect_client_role() {
		if ( ! $this->is_enabled_for_current_user() ) {
			return;
		}
		global $pagenow;
		if ( 'index.php' === $pagenow && ! isset( $_GET['page'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=chp-client-home' ) );
			exit;
		}
	}

	public function maybe_trim_admin_bar( $wp_admin_bar ) {
		if ( ! $this->is_enabled_for_current_user() ) {
			return;
		}
		$wp_admin_bar->remove_node( 'wp-logo' );
		$wp_admin_bar->remove_node( 'updates' );
		$wp_admin_bar->remove_node( 'comments' );
	}

	public function render_client_home() {
		$settings   = CHP_Plugin::get_settings();
		$agency     = ! empty( $settings['agency_name'] ) ? $settings['agency_name'] : get_bloginfo( 'name' );
		$support    = ! empty( $settings['agency_email'] ) ? $settings['agency_email'] : get_bloginfo( 'admin_email' );
		$front_page = (int) get_option( 'page_on_front' );
		$about_page = 0;
		$about      = get_page_by_path( 'about' );
		if ( $about ) {
			$about_page = $about->ID;
		}

		$actions = array(
			array(
				'label' => __( 'Edit Homepage', 'client-handover-pro' ),
				'url'   => $front_page ? get_edit_post_link( $front_page, '' ) : admin_url( 'edit.php?post_type=page' ),
				'icon'  => 'dashicons-edit-page',
			),
			array(
				'label' => __( 'Edit About', 'client-handover-pro' ),
				'url'   => $about_page ? get_edit_post_link( $about_page, '' ) : admin_url( 'edit.php?post_type=page' ),
				'icon'  => 'dashicons-admin-page',
			),
			array(
				'label' => __( 'Blog Posts', 'client-handover-pro' ),
				'url'   => admin_url( 'edit.php' ),
				'icon'  => 'dashicons-admin-post',
			),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$actions[] = array(
				'label' => __( 'Products', 'client-handover-pro' ),
				'url'   => admin_url( 'edit.php?post_type=product' ),
				'icon'  => 'dashicons-cart',
			);
		}

		$actions[] = array(
			'label' => __( 'Media Library', 'client-handover-pro' ),
			'url'   => admin_url( 'upload.php' ),
			'icon'  => 'dashicons-admin-media',
		);
		$actions[] = array(
			'label' => __( 'Contact Messages', 'client-handover-pro' ),
			'url'   => CHP_Integrations::contact_messages_url(),
			'icon'  => 'dashicons-email',
		);
		$actions[] = array(
			'label' => __( 'Website Guide', 'client-handover-pro' ),
			'url'   => admin_url( 'admin.php?page=chp-tutorials-view' ),
			'icon'  => 'dashicons-welcome-learn-more',
		);

		?>
		<div class="wrap chp-wrap chp-client-home">
			<div class="chp-header">
				<div>
					<h1 class="chp-title"><?php printf( esc_html__( 'Welcome back, %s', 'client-handover-pro' ), esc_html( wp_get_current_user()->display_name ) ); ?></h1>
					<p class="chp-subtitle"><?php esc_html_e( 'Everything you need to update your website.', 'client-handover-pro' ); ?></p>
				</div>
			</div>

			<div class="chp-card">
				<div class="chp-card__label"><?php esc_html_e( 'Quick Actions', 'client-handover-pro' ); ?></div>
				<div class="chp-quick-actions">
					<?php foreach ( $actions as $action ) : ?>
						<a class="chp-quick-action" href="<?php echo esc_url( $action['url'] ); ?>">
							<span class="dashicons <?php echo esc_attr( $action['icon'] ); ?>"></span>
							<span><?php echo esc_html( $action['label'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="chp-card chp-card--support">
				<div class="chp-card__label"><?php esc_html_e( 'Need Help?', 'client-handover-pro' ); ?></div>
				<p><?php printf( esc_html__( 'This website is built and maintained by %s.', 'client-handover-pro' ), esc_html( $agency ) ); ?></p>
				<a class="chp-btn chp-btn--primary" href="mailto:<?php echo esc_attr( $support ); ?>"><?php esc_html_e( 'Contact Support', 'client-handover-pro' ); ?></a>
			</div>
		</div>
		<?php
	}


	public function render_settings_page() {
		$settings = CHP_Plugin::get_settings();

		if ( CHP_Helpers::verify_post( 'chp_client_mode_save', 'chp_client_mode_nonce' ) ) {
			$settings['client_mode_enabled'] = ! empty( $_POST['client_mode_enabled'] );
			$settings['client_role']         = isset( $_POST['client_role'] ) ? sanitize_key( wp_unslash( $_POST['client_role'] ) ) : 'editor';
			CHP_Plugin::update_settings( $settings );
			CHP_Helpers::notice( __( 'Client Dashboard settings saved.', 'client-handover-pro' ) );
		}

		$roles = wp_roles()->get_names();
		?>
		<div class="wrap chp-wrap">
			<h1 class="chp-title"><?php esc_html_e( 'Client Dashboard', 'client-handover-pro' ); ?></h1>
			<p class="chp-subtitle"><?php esc_html_e( 'Replace the standard WordPress admin menu with a simple, client-friendly dashboard.', 'client-handover-pro' ); ?></p>

			<form method="post" class="chp-card chp-form">
				<?php wp_nonce_field( 'chp_client_mode_save', 'chp_client_mode_nonce' ); ?>
				<label class="chp-toggle">
					<input type="checkbox" name="client_mode_enabled" value="1" <?php checked( ! empty( $settings['client_mode_enabled'] ) ); ?> />
					<?php esc_html_e( 'Enable Client Dashboard', 'client-handover-pro' ); ?>
				</label>

				<p>
					<label for="client_role"><?php esc_html_e( 'Client role', 'client-handover-pro' ); ?></label><br />
					<select name="client_role" id="client_role">
						<?php foreach ( $roles as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $settings['client_role'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<button type="submit" class="chp-btn chp-btn--primary"><?php esc_html_e( 'Save Changes', 'client-handover-pro' ); ?></button>
			</form>
		</div>
		<?php
	}
}
