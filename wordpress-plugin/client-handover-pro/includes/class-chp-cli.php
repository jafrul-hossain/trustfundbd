<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-CLI commands for the freelancers/agencies who script their
 * deployments instead of clicking through wp-admin. Only loaded when
 * WP-CLI is actually running (see client-handover-pro.php).
 *
 *   wp chp scan
 *   wp chp scan --format=json
 *   wp chp cleanup --dry-run
 *   wp chp cleanup --tasks=revisions,spam_comments
 */
class CHP_CLI_Command {

	/**
	 * Runs the Launch Checklist scan and prints the result.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output as a table or json.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * @when after_wp_load
	 */
	public function scan( $args, $assoc_args ) {
		$scan   = CHP_Checklist::run_scan();
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $scan ) );
			return;
		}

		$rows = array();
		foreach ( $scan['categories'] as $cat ) {
			foreach ( $cat['items'] as $item ) {
				$rows[] = array(
					'category' => $cat['label'],
					'check'    => $item['label'],
					'status'   => strtoupper( $item['status'] ),
					'message'  => $item['message'],
				);
			}
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'category', 'check', 'status', 'message' ) );
		WP_CLI::success( sprintf( 'Website Health: %d%% (%d/%d checks passing)', $scan['score'], $scan['passed'], $scan['total'] ) );
	}

	/**
	 * Runs Site Cleanup tasks (Hello Dolly, dummy content, revisions, etc).
	 *
	 * ## OPTIONS
	 *
	 * [--tasks=<tasks>]
	 * : Comma-separated task keys to run. Defaults to all tasks.
	 *
	 * [--dry-run]
	 * : Only report how many items would be removed.
	 *
	 * @when after_wp_load
	 */
	public function cleanup( $args, $assoc_args ) {
		$all_tasks = array_keys( CHP_Site_Cleanup::tasks() );
		$tasks     = isset( $assoc_args['tasks'] )
			? array_intersect( array_map( 'trim', explode( ',', $assoc_args['tasks'] ) ), $all_tasks )
			: $all_tasks;

		if ( empty( $tasks ) ) {
			WP_CLI::error( 'No valid task keys given. Valid keys: ' . implode( ', ', $all_tasks ) );
			return;
		}

		$dry_run = ! empty( $assoc_args['dry-run'] );

		foreach ( $tasks as $task ) {
			if ( $dry_run ) {
				WP_CLI::line( sprintf( '%s: %d item(s) would be removed', $task, CHP_Site_Cleanup::count( $task ) ) );
				continue;
			}
			$removed = CHP_Site_Cleanup::run( $task );
			WP_CLI::line( sprintf( '%s: %d item(s) removed', $task, $removed ) );
		}

		WP_CLI::success( $dry_run ? 'Dry run complete.' : 'Site cleanup complete.' );
	}
}

WP_CLI::add_command( 'chp', 'CHP_CLI_Command' );
