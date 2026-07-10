<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central registry of the third-party plugins the Launch Checklist and
 * Client Dashboard detect. Keeping the lists here means adding support
 * for a new SEO/cache/form plugin only requires editing one file.
 */
class CHP_Integrations {

	const CONTACT_FORMS = array(
		'contact-form-7/wp-contact-form-7.php',
		'wpforms-lite/wpforms.php',
		'wpforms/wpforms.php',
		'gravityforms/gravityforms.php',
		'formidable/formidable.php',
		'ninja-forms/ninja-forms.php',
	);

	const SEO = array(
		'wordpress-seo/wp-seo.php',
		'seo-by-rank-math/rank-math.php',
		'all-in-one-seo-pack/all_in_one_seo_pack.php',
	);

	const SMTP = array(
		'wp-mail-smtp/wp_mail_smtp.php',
		'easy-wp-smtp/easy-wp-smtp.php',
		'post-smtp/postman-smtp.php',
		'fluent-smtp/fluent-smtp.php',
	);

	const CACHE = array(
		'wp-rocket/wp-rocket.php',
		'wp-super-cache/wp-cache.php',
		'w3-total-cache/w3-total-cache.php',
		'litespeed-cache/litespeed-cache.php',
		'wp-fastest-cache/wpFastestCache.php',
		'sg-cachepress/sg-cachepress.php',
	);

	const IMAGE_OPTIMIZATION = array(
		'imagify/imagify.php',
		'shortpixel-image-optimiser/wp-shortpixel.php',
		'ewww-image-optimizer/ewww-image-optimizer.php',
		'wp-smushit/wp-smush.php',
		'optimole-wp/optimole-wp.php',
	);

	const LOGIN_SECURITY = array(
		'wps-hide-login/wps-hide-login.php',
		'better-wp-security/better-wp-security.php',
		'itsec/itsec.php',
		'all-in-one-wp-security-and-firewall/wp-security.php',
	);

	const TWO_FACTOR = array(
		'two-factor/two-factor.php',
		'wp-2fa/wp-2fa.php',
		'miniorange-2-factor-authentication/miniorange_2_factor_settings.php',
		'wordfence/wordfence.php',
	);

	public static function any_active( array $plugins ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( $plugins as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				return true;
			}
		}
		return false;
	}

	public static function contact_messages_url() {
		if ( self::any_active( array( 'contact-form-7/wp-contact-form-7.php' ) ) ) {
			return admin_url( 'admin.php?page=wpcf7' );
		}
		if ( self::any_active( array( 'wpforms-lite/wpforms.php', 'wpforms/wpforms.php' ) ) ) {
			return admin_url( 'admin.php?page=wpforms-entries' );
		}
		return admin_url( 'edit.php?post_type=page' );
	}
}
