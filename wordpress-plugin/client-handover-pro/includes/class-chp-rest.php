<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A small `chp/v1` REST namespace so the health score and checklist can
 * be read by something other than wp-admin — a headless dashboard, a
 * mobile companion app, or (eventually) the multi-site agency view this
 * plugin will grow into once it moves to its own project.
 */
class CHP_Rest {

	const NAMESPACE_ = 'chp/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_health' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/scan',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_scan' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'refresh' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	public function permission_check() {
		return current_user_can( 'manage_options' );
	}

	public function get_health( WP_REST_Request $request ) {
		$scan   = CHP_Checklist::get_last_scan();
		$report = CHP_Handover::launch_report();

		return rest_ensure_response(
			array(
				'site'        => home_url(),
				'health_score'=> $scan['score'],
				'checklist'   => array(
					'passed' => $scan['passed'],
					'total'  => $scan['total'],
				),
				'launch_report' => $report,
				'scanned_at'    => ! empty( $scan['scanned_at'] ) ? gmdate( 'c', $scan['scanned_at'] ) : null,
				'plan'          => CHP_License::tier(),
			)
		);
	}

	public function get_scan( WP_REST_Request $request ) {
		$scan = $request->get_param( 'refresh' ) ? CHP_Checklist::run_scan() : CHP_Checklist::get_last_scan();
		return rest_ensure_response( $scan );
	}
}
