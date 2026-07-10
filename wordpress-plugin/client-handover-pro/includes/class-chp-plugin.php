<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main orchestrator. Boots every module and registers the admin menu.
 */
class CHP_Plugin {

	private static $instance = null;

	/** @var array<string,object> */
	public $modules = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
		$this->init_modules();

		load_plugin_textdomain( 'client-handover-pro', false, dirname( CHP_PLUGIN_BASENAME ) . '/languages' );

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	private function load_dependencies() {
		$files = array(
			'includes/class-chp-license.php',
			'includes/class-chp-checklist.php',
			'includes/class-chp-dashboard.php',
			'includes/class-chp-client-mode.php',
			'includes/class-chp-white-label.php',
			'includes/class-chp-admin-lock.php',
			'includes/class-chp-tutorials.php',
			'includes/class-chp-maintenance.php',
			'includes/class-chp-site-cleanup.php',
			'includes/class-chp-plugin-cleanup.php',
			'includes/class-chp-brand-assets.php',
			'includes/class-chp-credentials-vault.php',
			'includes/class-chp-client-notes.php',
			'includes/class-chp-handover.php',
			'includes/class-chp-agency.php',
			'includes/class-chp-ajax.php',
			'includes/class-chp-settings.php',
		);

		foreach ( $files as $file ) {
			require_once CHP_PLUGIN_DIR . $file;
		}
	}

	private function init_modules() {
		$this->modules['dashboard']   = new CHP_Dashboard();
		$this->modules['client_mode'] = new CHP_Client_Mode();
		$this->modules['white_label'] = new CHP_White_Label();
		$this->modules['admin_lock']  = new CHP_Admin_Lock();
		$this->modules['tutorials']   = new CHP_Tutorials();
		$this->modules['maintenance'] = new CHP_Maintenance();
		$this->modules['cleanup']     = new CHP_Site_Cleanup();
		$this->modules['plugins']     = new CHP_Plugin_Cleanup();
		$this->modules['brand']       = new CHP_Brand_Assets();
		$this->modules['vault']       = new CHP_Credentials_Vault();
		$this->modules['notes']       = new CHP_Client_Notes();
		$this->modules['handover']    = new CHP_Handover();
		$this->modules['agency']      = new CHP_Agency();
		$this->modules['ajax']        = new CHP_Ajax();
		$this->modules['settings']    = new CHP_Settings();
	}

	public function register_admin_menu() {
		$capability = 'manage_options';

		add_menu_page(
			__( 'Client Handover Pro', 'client-handover-pro' ),
			__( 'Handover Pro', 'client-handover-pro' ),
			$capability,
			'chp-dashboard',
			array( $this->modules['dashboard'], 'render_dashboard_page' ),
			'dashicons-yes-alt',
			2
		);

		add_submenu_page( 'chp-dashboard', __( 'Dashboard', 'client-handover-pro' ), __( 'Dashboard', 'client-handover-pro' ), $capability, 'chp-dashboard', array( $this->modules['dashboard'], 'render_dashboard_page' ) );
		add_submenu_page( 'chp-dashboard', __( 'Launch Checklist', 'client-handover-pro' ), __( 'Launch Checklist', 'client-handover-pro' ), $capability, 'chp-checklist', array( $this->modules['dashboard'], 'render_checklist_page' ) );
		add_submenu_page( 'chp-dashboard', __( 'Client Dashboard', 'client-handover-pro' ), __( 'Client Dashboard', 'client-handover-pro' ), $capability, 'chp-client-mode', array( $this->modules['client_mode'], 'render_settings_page' ) );
		add_submenu_page( 'chp-dashboard', __( 'White Label', 'client-handover-pro' ), __( 'White Label', 'client-handover-pro' ), $capability, 'chp-white-label', array( $this->modules['white_label'], 'render_settings_page' ) );
		add_submenu_page( 'chp-dashboard', __( 'Admin Lock', 'client-handover-pro' ), __( 'Admin Lock', 'client-handover-pro' ), $capability, 'chp-admin-lock', array( $this->modules['admin_lock'], 'render_settings_page' ) );
		add_submenu_page( 'chp-dashboard', __( 'Tutorial Center', 'client-handover-pro' ), __( 'Tutorial Center', 'client-handover-pro' ), $capability, 'chp-tutorials', array( $this->modules['tutorials'], 'render_admin_page' ) );
		add_submenu_page( 'chp-dashboard', __( 'Maintenance Mode', 'client-handover-pro' ), __( 'Maintenance Mode', 'client-handover-pro' ), $capability, 'chp-maintenance', array( $this->modules['maintenance'], 'render_settings_page' ) );
		add_submenu_page( 'chp-dashboard', __( 'Site Cleanup', 'client-handover-pro' ), __( 'Site Cleanup', 'client-handover-pro' ), $capability, 'chp-cleanup', array( $this->modules['cleanup'], 'render_page' ) );
		add_submenu_page( 'chp-dashboard', __( 'Plugin Cleanup', 'client-handover-pro' ), __( 'Plugin Cleanup', 'client-handover-pro' ), $capability, 'chp-plugin-cleanup', array( $this->modules['plugins'], 'render_page' ) );
		add_submenu_page( 'chp-dashboard', __( 'Brand Assets', 'client-handover-pro' ), __( 'Brand Assets', 'client-handover-pro' ), $capability, 'chp-brand-assets', array( $this->modules['brand'], 'render_page' ) );
		add_submenu_page( 'chp-dashboard', __( 'Credentials Vault', 'client-handover-pro' ), __( 'Credentials Vault', 'client-handover-pro' ), $capability, 'chp-vault', array( $this->modules['vault'], 'render_page' ) );
		add_submenu_page( 'chp-dashboard', __( 'Client Notes', 'client-handover-pro' ), __( 'Client Notes', 'client-handover-pro' ), $capability, 'chp-notes', array( $this->modules['notes'], 'render_page' ) );
		add_submenu_page( 'chp-dashboard', __( 'Handover & Reports', 'client-handover-pro' ), __( 'Handover & Reports', 'client-handover-pro' ), $capability, 'chp-handover', array( $this->modules['handover'], 'render_page' ) );
		add_submenu_page( 'chp-dashboard', __( 'Agency Tools', 'client-handover-pro' ), __( 'Agency Tools', 'client-handover-pro' ), $capability, 'chp-agency', array( $this->modules['agency'], 'render_page' ) );
		add_submenu_page( 'chp-dashboard', __( 'Settings & License', 'client-handover-pro' ), __( 'Settings & License', 'client-handover-pro' ), $capability, 'chp-settings', array( $this->modules['settings'], 'render_page' ) );
	}

	public function enqueue_admin_assets( $hook ) {
		if ( strpos( (string) $hook, 'chp-' ) === false && ! $this->is_chp_admin_page() ) {
			return;
		}

		wp_enqueue_style( 'chp-admin', CHP_PLUGIN_URL . 'admin/css/admin.css', array(), CHP_VERSION );
		wp_enqueue_script( 'chp-admin', CHP_PLUGIN_URL . 'admin/js/admin.js', array( 'jquery' ), CHP_VERSION, true );
		wp_enqueue_media();

		wp_localize_script(
			'chp-admin',
			'CHP',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'chp_nonce' ),
				'i18n'    => array(
					'scanning'   => __( 'Scanning your website…', 'client-handover-pro' ),
					'confirm'    => __( 'Are you sure? This cannot be undone.', 'client-handover-pro' ),
					'done'       => __( 'Done', 'client-handover-pro' ),
				),
			)
		);
	}

	private function is_chp_admin_page() {
		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}
		return 0 === strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'chp-' );
	}

	/**
	 * Central settings accessor so every module reads/writes the same
	 * option shape.
	 */
	public static function get_settings() {
		$defaults = array(
			'license_tier'         => CHP_License::TIER_FREE,
			'license_key'          => '',
			'client_mode_enabled'  => false,
			'client_role'          => 'editor',
			'agency_name'          => 'Badhon Studio',
			'agency_email'         => 'support@email.com',
			'agency_logo'          => '',
			'agency_primary'       => '#1E7F5C',
			'admin_lock_roles'     => array(),
			'admin_lock_menus'     => array(),
			'maintenance_enabled'  => false,
			'maintenance_mode'     => 'coming_soon',
			'maintenance_headline' => 'Something great is on the way.',
			'maintenance_message'  => "We're putting the finishing touches on our new website. Please check back soon.",
		);

		$settings = get_option( 'chp_settings', array() );
		return wp_parse_args( $settings, $defaults );
	}

	public static function update_settings( $settings ) {
		update_option( 'chp_settings', $settings );
	}

	public static function get_setting( $key, $default = null ) {
		$settings = self::get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}
}
