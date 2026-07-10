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
		if ( in_array( $pagenow, $locked, true ) ) {
			wp_die( esc_html__( 'This area has been locked by your website administrator.', 'client-handover-pro' ), '', array( 'response' => 403, 'back_link' => true ) );
		}
		if ( 'customize.php' === $pagenow && in_array( 'customize.php', $locked, true ) ) {
			wp_die( esc_html__( 'The Customizer has been locked by your website administrator.', 'client-handover-pro' ), '', array( 'response' => 403, 'back_link' => true ) );
		}
	}

	public function render_settings_page() {
		$settings = CHP_Plugin::get_settings();

		if ( isset( $_POST['chp_lock_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['chp_lock_nonce'] ) ), 'chp_lock_save' ) ) {
			$settings['admin_lock_roles'] = isset( $_POST['admin_lock_roles'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['admin_lock_roles'] ) ) : array();
			$settings['admin_lock_menus'] = isset( $_POST['admin_lock_menus'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['admin_lock_menus'] ) ) : array();
			CHP_Plugin::update_settings( $settings );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Admin Lock settings saved.', 'client-handover-pro' ) . '</p></div>';
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
