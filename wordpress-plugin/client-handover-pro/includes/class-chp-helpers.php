<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small shared helpers so every settings page doesn't re-implement the
 * same nonce-verification boilerplate.
 */
class CHP_Helpers {

	/**
	 * Verifies the nonce for a POSTed settings form and confirms the
	 * current user can manage options. Returns true only when it is
	 * safe to read $_POST for that form.
	 */
	public static function verify_post( $action, $field ) {
		if ( ! isset( $_POST[ $field ] ) ) {
			return false;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), $action ) ) {
			return false;
		}
		return current_user_can( 'manage_options' );
	}

	public static function notice( $message, $type = 'success' ) {
		printf(
			'<div class="notice notice-%1$s"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}
}
