<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX endpoints backing the admin UI buttons. Every handler checks the
 * shared nonce and the manage_options capability before doing anything.
 */
class CHP_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_chp_run_scan', array( $this, 'run_scan' ) );
		add_action( 'wp_ajax_chp_generate_client_mode', array( $this, 'generate_client_mode' ) );
		add_action( 'wp_ajax_chp_cleanup_count', array( $this, 'cleanup_count' ) );
		add_action( 'wp_ajax_chp_cleanup_run', array( $this, 'cleanup_run' ) );
		add_action( 'wp_ajax_chp_plugin_delete', array( $this, 'plugin_delete' ) );
		add_action( 'wp_ajax_chp_send_test_email', array( $this, 'send_test_email' ) );
	}

	private function guard() {
		check_ajax_referer( 'chp_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'client-handover-pro' ) ), 403 );
		}
	}

	public function run_scan() {
		$this->guard();
		$scan = CHP_Checklist::run_scan();
		ob_start();
		CHP_Dashboard::render_categories( $scan['categories'] );
		$html = ob_get_clean();
		wp_send_json_success(
			array(
				'score'  => $scan['score'],
				'passed' => $scan['passed'],
				'total'  => $scan['total'],
				'html'   => $html,
			)
		);
	}

	public function generate_client_mode() {
		$this->guard();
		$settings = CHP_Plugin::get_settings();
		$settings['client_mode_enabled'] = true;
		CHP_Plugin::update_settings( $settings );
		if ( class_exists( 'CHP_Agency' ) ) {
			CHP_Agency::log_event( __( 'Client Dashboard generated', 'client-handover-pro' ) );
		}
		wp_send_json_success(
			array(
				'message' => __( 'Client Dashboard is now active for the selected role.', 'client-handover-pro' ),
				'url'     => admin_url( 'admin.php?page=chp-client-mode' ),
			)
		);
	}

	public function cleanup_count() {
		$this->guard();
		$task = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';
		if ( ! array_key_exists( $task, CHP_Site_Cleanup::tasks() ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown task.', 'client-handover-pro' ) ) );
		}
		wp_send_json_success( array( 'count' => CHP_Site_Cleanup::count( $task ) ) );
	}

	public function cleanup_run() {
		$this->guard();
		$tasks = isset( $_POST['tasks'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['tasks'] ) ) : array();
		$valid = array_keys( CHP_Site_Cleanup::tasks() );
		$results = array();
		foreach ( $tasks as $task ) {
			if ( in_array( $task, $valid, true ) ) {
				$removed           = CHP_Site_Cleanup::run( $task );
				$results[ $task ]  = $removed;
			}
		}
		if ( class_exists( 'CHP_Agency' ) && ! empty( $results ) ) {
			CHP_Agency::log_event( __( 'Site cleanup run', 'client-handover-pro' ) );
		}
		wp_send_json_success( array( 'results' => $results ) );
	}

	public function plugin_delete() {
		$this->guard();
		$plugin = isset( $_POST['plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) : '';
		if ( ! $plugin || false !== strpos( $plugin, '..' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid plugin.', 'client-handover-pro' ) ) );
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'delete_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$all_plugins = get_plugins();
		if ( ! isset( $all_plugins[ $plugin ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Plugin not found.', 'client-handover-pro' ) ) );
		}
		if ( is_plugin_active( $plugin ) ) {
			wp_send_json_error( array( 'message' => __( 'Deactivate the plugin before deleting it.', 'client-handover-pro' ) ) );
		}
		$result = delete_plugins( array( $plugin ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		if ( class_exists( 'CHP_Agency' ) ) {
			CHP_Agency::log_event( sprintf( __( 'Plugin deleted: %s', 'client-handover-pro' ), $plugin ) );
		}
		wp_send_json_success( array( 'message' => __( 'Plugin deleted.', 'client-handover-pro' ) ) );
	}

	public function send_test_email() {
		$this->guard();
		$to     = get_bloginfo( 'admin_email' );
		$sent   = wp_mail( $to, __( 'Client Handover Pro test email', 'client-handover-pro' ), __( 'If you received this, email sending works.', 'client-handover-pro' ) );
		set_transient( 'chp_mail_test_result', $sent, WEEK_IN_SECONDS );
		if ( $sent ) {
			wp_send_json_success( array( 'message' => sprintf( __( 'Test email sent to %s.', 'client-handover-pro' ), $to ) ) );
		}
		wp_send_json_error( array( 'message' => __( 'wp_mail() reported a failure sending the test email.', 'client-handover-pro' ) ) );
	}
}
