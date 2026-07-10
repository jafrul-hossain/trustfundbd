<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the Launch Checklist scan. Every check is a real, live inspection
 * of the current site rather than a canned result.
 */
class CHP_Checklist {

	/**
	 * @return array{categories: array, score: int, passed: int, total: int, scanned_at: int}
	 */
	public static function run_scan() {
		$categories = array(
			'website'     => array(
				'label' => __( 'Website', 'client-handover-pro' ),
				'items' => array(
					'ssl'          => self::check_ssl(),
					'https'        => self::check_https_forced(),
					'homepage'     => self::check_homepage_loads(),
					'error_404'    => self::check_404_page(),
					'contact_page' => self::check_contact_page(),
				),
			),
			'forms'       => array(
				'label' => __( 'Forms', 'client-handover-pro' ),
				'items' => array(
					'contact_form' => self::check_contact_form(),
					'email_sending' => self::check_email_sending(),
					'smtp'          => self::check_smtp(),
				),
			),
			'seo'         => array(
				'label' => __( 'SEO', 'client-handover-pro' ),
				'items' => array(
					'sitemap'      => self::check_sitemap(),
					'robots'       => self::check_robots(),
					'meta_title'   => self::check_meta_title(),
					'meta_desc'    => self::check_meta_description(),
					'open_graph'   => self::check_open_graph(),
				),
			),
			'performance' => array(
				'label' => __( 'Performance', 'client-handover-pro' ),
				'items' => array(
					'cache'       => self::check_cache(),
					'image_compression' => self::check_image_compression(),
					'lazy_loading'=> self::check_lazy_loading(),
					'core_web_vitals' => self::check_core_web_vitals_estimate(),
				),
			),
			'security'    => array(
				'label' => __( 'Security', 'client-handover-pro' ),
				'items' => array(
					'admin_username' => self::check_admin_username(),
					'login_url'      => self::check_login_url(),
					'file_permissions' => self::check_file_permissions(),
					'debug_mode'     => self::check_debug_mode(),
					'xmlrpc'         => self::check_xmlrpc(),
				),
			),
		);

		$passed = 0;
		$total  = 0;

		foreach ( $categories as $cat_key => $cat ) {
			foreach ( $cat['items'] as $item_key => $item ) {
				$total++;
				if ( 'pass' === $item['status'] ) {
					$passed++;
				}
			}
		}

		$score = $total > 0 ? (int) round( ( $passed / $total ) * 100 ) : 0;

		$result = array(
			'categories' => $categories,
			'score'      => $score,
			'passed'     => $passed,
			'total'      => $total,
			'scanned_at' => time(),
		);

		update_option( 'chp_last_scan', $result );

		return $result;
	}

	public static function get_last_scan() {
		$scan = get_option( 'chp_last_scan', array() );
		if ( empty( $scan ) ) {
			return self::run_scan();
		}
		return $scan;
	}

	private static function item( $status, $label, $message = '', $fix_url = '' ) {
		return array(
			'status'  => $status, // pass | fail | warn
			'label'   => $label,
			'message' => $message,
			'fix_url' => $fix_url,
		);
	}

	/* -------------------------------------------------------------- Website */

	private static function check_ssl() {
		if ( is_ssl() ) {
			return self::item( 'pass', __( 'SSL Certificate', 'client-handover-pro' ), __( 'SSL is active on this request.', 'client-handover-pro' ) );
		}
		return self::item( 'fail', __( 'SSL Certificate', 'client-handover-pro' ), __( 'No SSL detected. Install an SSL certificate with your host.', 'client-handover-pro' ) );
	}

	private static function check_https_forced() {
		$home = home_url();
		if ( 0 === strpos( $home, 'https://' ) ) {
			return self::item( 'pass', __( 'HTTPS Enforced', 'client-handover-pro' ), __( 'Site URL uses https://.', 'client-handover-pro' ) );
		}
		return self::item( 'fail', __( 'HTTPS Enforced', 'client-handover-pro' ), __( 'WordPress Address is not set to https://. Update it in Settings → General.', 'client-handover-pro' ), admin_url( 'options-general.php' ) );
	}

	private static function check_homepage_loads() {
		$response = wp_remote_get( home_url( '/' ), array( 'timeout' => 8, 'sslverify' => false ) );
		if ( is_wp_error( $response ) ) {
			return self::item( 'fail', __( 'Homepage Loads', 'client-handover-pro' ), $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 400 ) {
			return self::item( 'pass', __( 'Homepage Loads', 'client-handover-pro' ), sprintf( __( 'Responded with HTTP %d.', 'client-handover-pro' ), $code ) );
		}
		return self::item( 'fail', __( 'Homepage Loads', 'client-handover-pro' ), sprintf( __( 'Responded with HTTP %d.', 'client-handover-pro' ), $code ) );
	}

	private static function check_404_page() {
		$response = wp_remote_get( home_url( '/chp-404-check-' . wp_generate_password( 8, false ) . '/' ), array( 'timeout' => 8, 'sslverify' => false ) );
		if ( is_wp_error( $response ) ) {
			return self::item( 'warn', __( '404 Page', 'client-handover-pro' ), $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 404 === (int) $code ) {
			return self::item( 'pass', __( '404 Page', 'client-handover-pro' ), __( 'Unknown URLs correctly return a 404.', 'client-handover-pro' ) );
		}
		return self::item( 'warn', __( '404 Page', 'client-handover-pro' ), sprintf( __( 'Expected HTTP 404 but got %d. Check permalink settings.', 'client-handover-pro' ), $code ) );
	}

	private static function check_contact_page() {
		$page = self::find_page_by_keyword( 'contact' );
		if ( $page ) {
			return self::item( 'pass', __( 'Contact Page Exists', 'client-handover-pro' ), sprintf( __( 'Found "%s".', 'client-handover-pro' ), get_the_title( $page ) ) );
		}
		return self::item( 'fail', __( 'Contact Page Exists', 'client-handover-pro' ), __( 'No page with "contact" in the title/slug was found.', 'client-handover-pro' ), admin_url( 'post-new.php?post_type=page' ) );
	}

	/* ----------------------------------------------------------------- Forms */

	private static function check_contact_form() {
		$has_form_plugin = self::any_plugin_active( array(
			'contact-form-7/wp-contact-form-7.php',
			'wpforms-lite/wpforms.php',
			'wpforms/wpforms.php',
			'gravityforms/gravityforms.php',
			'formidable/formidable.php',
			'ninja-forms/ninja-forms.php',
		) );

		$page = self::find_page_by_keyword( 'contact' );
		$has_shortcode = false;
		if ( $page ) {
			$content = get_post_field( 'post_content', $page );
			$has_shortcode = (bool) preg_match( '/\[(contact-form-7|wpforms|gravityform|ninja_form|formidable)/i', (string) $content );
		}

		if ( $has_form_plugin && ( $has_shortcode || $page ) ) {
			return self::item( 'pass', __( 'Contact Form Works', 'client-handover-pro' ), __( 'A form plugin is active and a contact page exists.', 'client-handover-pro' ) );
		}
		if ( $has_form_plugin ) {
			return self::item( 'warn', __( 'Contact Form Works', 'client-handover-pro' ), __( 'A form plugin is active, but no form was detected on the contact page.', 'client-handover-pro' ) );
		}
		return self::item( 'fail', __( 'Contact Form Works', 'client-handover-pro' ), __( 'No supported form plugin is active.', 'client-handover-pro' ), admin_url( 'plugin-install.php?s=contact+form&tab=search&type=term' ) );
	}

	private static function check_email_sending() {
		$test_result = get_transient( 'chp_mail_test_result' );
		if ( false !== $test_result ) {
			return $test_result
				? self::item( 'pass', __( 'Email Sending Works', 'client-handover-pro' ), __( 'Last test email sent successfully.', 'client-handover-pro' ) )
				: self::item( 'fail', __( 'Email Sending Works', 'client-handover-pro' ), __( 'The last test email failed to send.', 'client-handover-pro' ) );
		}
		return self::item( 'warn', __( 'Email Sending Works', 'client-handover-pro' ), __( 'Not tested yet. Send a test email from Settings & License.', 'client-handover-pro' ), admin_url( 'admin.php?page=chp-settings' ) );
	}

	private static function check_smtp() {
		$has_smtp_plugin = self::any_plugin_active( array(
			'wp-mail-smtp/wp_mail_smtp.php',
			'easy-wp-smtp/easy-wp-smtp.php',
			'post-smtp/postman-smtp.php',
			'fluent-smtp/fluent-smtp.php',
		) );
		if ( $has_smtp_plugin ) {
			return self::item( 'pass', __( 'SMTP Enabled', 'client-handover-pro' ), __( 'An SMTP plugin is active.', 'client-handover-pro' ) );
		}
		return self::item( 'warn', __( 'SMTP Enabled', 'client-handover-pro' ), __( 'No SMTP plugin detected. Default PHP mail() is unreliable for deliverability.', 'client-handover-pro' ), admin_url( 'plugin-install.php?s=smtp&tab=search&type=term' ) );
	}

	/* ------------------------------------------------------------------- SEO */

	private static function check_sitemap() {
		$candidates = array( home_url( '/wp-sitemap.xml' ), home_url( '/sitemap_index.xml' ), home_url( '/sitemap.xml' ) );
		foreach ( $candidates as $url ) {
			$response = wp_remote_get( $url, array( 'timeout' => 8, 'sslverify' => false ) );
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				return self::item( 'pass', __( 'Sitemap', 'client-handover-pro' ), sprintf( __( 'Found at %s', 'client-handover-pro' ), $url ) );
			}
		}
		return self::item( 'fail', __( 'Sitemap', 'client-handover-pro' ), __( 'No XML sitemap found. Enable one via an SEO plugin or WordPress core.', 'client-handover-pro' ) );
	}

	private static function check_robots() {
		$response = wp_remote_get( home_url( '/robots.txt' ), array( 'timeout' => 8, 'sslverify' => false ) );
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			return self::item( 'pass', __( 'Robots.txt', 'client-handover-pro' ), __( 'robots.txt is reachable.', 'client-handover-pro' ) );
		}
		return self::item( 'fail', __( 'Robots.txt', 'client-handover-pro' ), __( 'robots.txt could not be reached.', 'client-handover-pro' ) );
	}

	private static function check_meta_title() {
		$title = get_bloginfo( 'name' );
		if ( ! empty( $title ) ) {
			return self::item( 'pass', __( 'Meta Title', 'client-handover-pro' ), sprintf( __( 'Site title set: "%s"', 'client-handover-pro' ), $title ) );
		}
		return self::item( 'fail', __( 'Meta Title', 'client-handover-pro' ), __( 'Site title is empty. Set it in Settings → General.', 'client-handover-pro' ), admin_url( 'options-general.php' ) );
	}

	private static function check_meta_description() {
		$tagline    = get_bloginfo( 'description' );
		$seo_active = self::any_plugin_active( array(
			'wordpress-seo/wp-seo.php',
			'seo-by-rank-math/rank-math.php',
			'all-in-one-seo-pack/all_in_one_seo_pack.php',
		) );
		if ( $seo_active || ! empty( $tagline ) ) {
			return self::item( 'pass', __( 'Meta Description', 'client-handover-pro' ), $seo_active ? __( 'An SEO plugin manages meta descriptions.', 'client-handover-pro' ) : __( 'Tagline is set as a fallback description.', 'client-handover-pro' ) );
		}
		return self::item( 'fail', __( 'Meta Description', 'client-handover-pro' ), __( 'No SEO plugin and no tagline set.', 'client-handover-pro' ), admin_url( 'options-general.php' ) );
	}

	private static function check_open_graph() {
		$seo_active = self::any_plugin_active( array(
			'wordpress-seo/wp-seo.php',
			'seo-by-rank-math/rank-math.php',
			'all-in-one-seo-pack/all_in_one_seo_pack.php',
			'jetpack/jetpack.php',
		) );
		if ( $seo_active ) {
			return self::item( 'pass', __( 'Open Graph Tags', 'client-handover-pro' ), __( 'An SEO/social plugin is generating Open Graph tags.', 'client-handover-pro' ) );
		}
		return self::item( 'warn', __( 'Open Graph Tags', 'client-handover-pro' ), __( 'No SEO plugin detected to generate Open Graph tags for social sharing.', 'client-handover-pro' ) );
	}

	/* ----------------------------------------------------------- Performance */

	private static function check_cache() {
		$has_cache_plugin = self::any_plugin_active( array(
			'wp-rocket/wp-rocket.php',
			'wp-super-cache/wp-cache.php',
			'w3-total-cache/w3-total-cache.php',
			'litespeed-cache/litespeed-cache.php',
			'wp-fastest-cache/wpFastestCache.php',
			'sg-cachepress/sg-cachepress.php',
		) );
		if ( $has_cache_plugin || wp_using_ext_object_cache() ) {
			return self::item( 'pass', __( 'Cache Enabled', 'client-handover-pro' ), __( 'A caching solution is active.', 'client-handover-pro' ) );
		}
		return self::item( 'fail', __( 'Cache Enabled', 'client-handover-pro' ), __( 'No caching plugin detected.', 'client-handover-pro' ), admin_url( 'plugin-install.php?s=cache&tab=search&type=term' ) );
	}

	private static function check_image_compression() {
		$has_plugin = self::any_plugin_active( array(
			'imagify/imagify.php',
			'shortpixel-image-optimiser/wp-shortpixel.php',
			'ewww-image-optimizer/ewww-image-optimizer.php',
			'wp-smushit/wp-smush.php',
			'optimole-wp/optimole-wp.php',
		) );
		if ( $has_plugin ) {
			return self::item( 'pass', __( 'Image Compression', 'client-handover-pro' ), __( 'An image optimization plugin is active.', 'client-handover-pro' ) );
		}
		return self::item( 'warn', __( 'Image Compression', 'client-handover-pro' ), __( 'No image optimization plugin detected.', 'client-handover-pro' ), admin_url( 'plugin-install.php?s=image+optimization&tab=search&type=term' ) );
	}

	private static function check_lazy_loading() {
		if ( wp_lazy_loading_enabled( 'img', 'the_content' ) ) {
			return self::item( 'pass', __( 'Lazy Loading', 'client-handover-pro' ), __( 'Native WordPress lazy loading is enabled.', 'client-handover-pro' ) );
		}
		return self::item( 'fail', __( 'Lazy Loading', 'client-handover-pro' ), __( 'Lazy loading has been disabled by a theme or plugin.', 'client-handover-pro' ) );
	}

	private static function check_core_web_vitals_estimate() {
		$cache_ok = 'pass' === self::check_cache()['status'];
		$image_ok = 'pass' === self::check_image_compression()['status'];
		$lazy_ok  = 'pass' === self::check_lazy_loading()['status'];

		$score = ( $cache_ok ? 1 : 0 ) + ( $image_ok ? 1 : 0 ) + ( $lazy_ok ? 1 : 0 );

		if ( 3 === $score ) {
			return self::item( 'pass', __( 'Core Web Vitals (Estimate)', 'client-handover-pro' ), __( 'Cache, image optimization and lazy loading are all in place — good baseline.', 'client-handover-pro' ) );
		}
		if ( $score >= 1 ) {
			return self::item( 'warn', __( 'Core Web Vitals (Estimate)', 'client-handover-pro' ), __( 'Some performance basics are missing. This is a rough estimate, not a lab measurement.', 'client-handover-pro' ) );
		}
		return self::item( 'fail', __( 'Core Web Vitals (Estimate)', 'client-handover-pro' ), __( 'None of the performance basics were detected.', 'client-handover-pro' ) );
	}

	/* ------------------------------------------------------------- Security */

	private static function check_admin_username() {
		$user = get_user_by( 'login', 'admin' );
		if ( $user && in_array( 'administrator', (array) $user->roles, true ) ) {
			return self::item( 'fail', __( 'Admin Username', 'client-handover-pro' ), __( 'A user with the login "admin" exists. Rename or remove it.', 'client-handover-pro' ), admin_url( 'users.php' ) );
		}
		return self::item( 'pass', __( 'Admin Username', 'client-handover-pro' ), __( 'No administrator uses the "admin" login.', 'client-handover-pro' ) );
	}

	private static function check_login_url() {
		$has_hider = self::any_plugin_active( array(
			'wps-hide-login/wps-hide-login.php',
			'better-wp-security/better-wp-security.php',
			'itsec/itsec.php',
			'all-in-one-wp-security-and-firewall/wp-security.php',
		) );
		if ( $has_hider ) {
			return self::item( 'pass', __( 'Login URL Protected', 'client-handover-pro' ), __( 'A security plugin is protecting or hiding the login URL.', 'client-handover-pro' ) );
		}
		return self::item( 'warn', __( 'Login URL Protected', 'client-handover-pro' ), __( 'The default /wp-login.php URL is unprotected.', 'client-handover-pro' ), admin_url( 'plugin-install.php?s=hide+login&tab=search&type=term' ) );
	}

	private static function check_file_permissions() {
		$config_file = ABSPATH . 'wp-config.php';
		if ( ! file_exists( $config_file ) ) {
			$config_file = dirname( ABSPATH ) . '/wp-config.php';
		}
		if ( ! file_exists( $config_file ) ) {
			return self::item( 'warn', __( 'File Permissions', 'client-handover-pro' ), __( 'Could not locate wp-config.php to check.', 'client-handover-pro' ) );
		}
		$perms = substr( sprintf( '%o', fileperms( $config_file ) ), -3 );
		if ( in_array( $perms, array( '400', '440', '600', '440', '444' ), true ) ) {
			return self::item( 'pass', __( 'File Permissions', 'client-handover-pro' ), sprintf( __( 'wp-config.php permissions are %s.', 'client-handover-pro' ), $perms ) );
		}
		if ( '644' === $perms || '640' === $perms ) {
			return self::item( 'warn', __( 'File Permissions', 'client-handover-pro' ), sprintf( __( 'wp-config.php permissions are %s. Consider tightening to 600/440.', 'client-handover-pro' ), $perms ) );
		}
		return self::item( 'fail', __( 'File Permissions', 'client-handover-pro' ), sprintf( __( 'wp-config.php permissions are %s, which is too open.', 'client-handover-pro' ), $perms ) );
	}

	private static function check_debug_mode() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ( ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY ) ) {
			return self::item( 'fail', __( 'Debug Mode', 'client-handover-pro' ), __( 'WP_DEBUG (with display on) is enabled on a live site. Disable it before handover.', 'client-handover-pro' ) );
		}
		return self::item( 'pass', __( 'Debug Mode', 'client-handover-pro' ), __( 'Debug output is not exposed.', 'client-handover-pro' ) );
	}

	private static function check_xmlrpc() {
		$enabled = apply_filters( 'xmlrpc_enabled', true );
		if ( ! $enabled ) {
			return self::item( 'pass', __( 'XML-RPC Disabled', 'client-handover-pro' ), __( 'XML-RPC has been disabled.', 'client-handover-pro' ) );
		}
		return self::item( 'warn', __( 'XML-RPC Disabled', 'client-handover-pro' ), __( 'XML-RPC is enabled, which is a common brute-force target.', 'client-handover-pro' ) );
	}

	/* ---------------------------------------------------------------- Utils */

	private static function any_plugin_active( array $plugins ) {
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

	private static function find_page_by_keyword( $keyword ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				's'              => $keyword,
				'fields'         => 'ids',
			)
		);
		if ( $query->have_posts() ) {
			return $query->posts[0];
		}
		// Fallback: check slugs directly, since core search doesn't match slugs.
		$page = get_page_by_path( $keyword );
		return $page ? $page->ID : 0;
	}
}
