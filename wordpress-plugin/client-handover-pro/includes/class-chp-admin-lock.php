<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Lock: hide risky admin areas from selected roles so clients
 * can't accidentally break the site.
 */
class CHP_Admin_Lock {

	private static $lockable_menus = array(
		'plugins.php'        => 'Plugins',
		'themes.php'         => 'Themes',
		'users.php'          => 'Users',
		'options-general.php'=> 'Settings',
		'tools.php'          => 'Tools',
		'customize.php'      => 'Customizer',
	);

	/**
	 * Menu-level locking alone leaves the underlying admin pages reachable
	 * by direct URL. This maps each lockable menu to the other core pages
	 * that expose the same capability, so hiding "Plugins" also blocks
	 * plugin-install.php, plugin-editor.php, and the update.php actions
	 * that install/activate/delete plugins — not just the menu link.
	 */
	private static $related_pages = array(
		'plugins.php'          => array( 'plugin-install.php', 'plugin-editor.php' ),
		'themes.php'           => array( 'theme-install.php', 'theme-editor.php' ),
		'users.php'            => array( 'user-new.php', 'user-edit.php' ),
		'options-general.php'  => array( 'options.php', 'options-writing.php', 'options-reading.php', 'options-discussion.php', 'options-permalink.php', 'options-media.php', 'options-privacy.php' ),
		'tools.php'            => array( 'export.php', 'import.php', 'site-health.php' ),
	);

	private static $update_actions_by_menu = array(
		'plugins.php' => array( 'install-plugin', 'upgrade-plugin', 'delete-plugin' ),
		'themes.php'  => array( 'install-theme', 'upgrade-theme', 'delete-theme' ),
	);

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'lock_menus' ), 999 );
		add_action( 'admin_init', array( $this, 'block_direct_access' ) );
	}

	private function locked_roles() {
		return (array) CHP_Plugin::get_setting( 'admin_lock_roles', array() );
	}

	private function locked_menus() {
		return (array) CHP_Plugin::get_setting( 'admin_lock_menus', array() );
	}

	private function current_user_is_locked() {
		if ( current_user_can( 'manage_options' ) ) {
			return false;
		}
		$roles = $this->locked_roles();
		if ( empty( $roles ) ) {
			return false;
		}
		$user = wp_get_current_user();
		return (bool) array_intersect( $roles, (array) $user->roles );
	}

	public function lock_menus() {
		if ( ! $this->current_user_is_locked() ) {
			return;
		}
		foreach ( $this->locked_menus() as $slug ) {
			if ( isset( self::$lockable_menus[ $slug ] ) ) {
				remove_menu_page( $slug );
			}
		}
		if ( in_array( 'customize.php', $this->locked_menus(), true ) ) {
			remove_submenu_page( 'themes.php', 'customize.php' );
		}
	}

	public function block_direct_access() {
		if ( ! $this->current_user_is_locked() ) {
			return;
		}
		global $pagenow;
		$locked = $this->locked_menus();

		foreach ( $locked as $menu_slug ) {
			if ( $pagenow === $menu_slug ) {
				$this->deny();
			}
			if ( ! empty( self::$related_pages[ $menu_slug ] ) && in_array( $pagenow, self::$related_pages[ $menu_slug ], true ) ) {
				$this->deny();
			}
			if ( 'update.php' === $pagenow && ! empty( self::$update_actions_by_menu[ $menu_slug ] ) ) {
				$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
				if ( in_array( $action, self::$update_actions_by_menu[ $menu_slug ], true ) ) {
					$this->deny();
				}
			}
		}
	}

	private function deny() {
		wp_die( esc_html__( 'This area has been locked by your website administrator.', 'client-handover-pro' ), '', array( 'response' => 403, 'back_link' => true ) );
	}

	public function render_settings_page() {
		$settings = CHP_Plugin::get_settings();

		if ( CHP_Helpers::verify_post( 'chp_lock_save', 'chp_lock_nonce' ) ) {
			$settings['admin_lock_roles'] = isset( $_POST['admin_lock_roles'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['admin_lock_roles'] ) ) : array();
			$settings['admin_lock_menus'] = isset( $_POST['admin_lock_menus'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['admin_lock_menus'] ) ) : array();
			CHP_Plugin::update_settings( $settings );
			CHP_Helpers::notice( __( 'Admin Lock settings saved.', 'client-handover-pro' ) );
			$settings = CHP_Plugin::get_settings();
		}

		$roles = wp_roles()->get_names();
		unset( $roles['administrator'] );
		?>
		<div class="wrap chp-wrap">
			<h1 class="chp-title"><?php esc_html_e( 'Admin Lock', 'client-handover-pro' ); ?></h1>
			<p class="chp-subtitle"><?php esc_html_e( 'Prevent clients from breaking their own website.', 'client-handover-pro' ); ?></p>

			<form method="post" class="chp-card chp-form">
				<?php wp_nonce_field( 'chp_lock_save', 'chp_lock_nonce' ); ?>

				<div class="chp-form-row">
					<label><?php esc_html_e( 'Lock these roles', 'client-handover-pro' ); ?></label>
					<div class="chp-checkbox-grid">
						<?php foreach ( $roles as $slug => $label ) : ?>
							<label class="chp-checkbox">
								<input type="checkbox" name="admin_lock_roles[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $settings['admin_lock_roles'], true ) ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="chp-form-row">
					<label><?php esc_html_e( 'Hide these menus', 'client-handover-pro' ); ?></label>
					<div class="chp-checkbox-grid">
						<?php foreach ( self::$lockable_menus as $slug => $label ) : ?>
							<label class="chp-checkbox">
								<input type="checkbox" name="admin_lock_menus[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $settings['admin_lock_menus'], true ) ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<button type="submit" class="chp-btn chp-btn--primary"><?php esc_html_e( 'Save Changes', 'client-handover-pro' ); ?></button>
			</form>
		</div>
		<?php
	}
}
