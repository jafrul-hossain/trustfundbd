<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Credentials Vault (Pro): encrypted storage for hosting, domain,
 * Cloudflare, SMTP, analytics and API key credentials. Never rendered
 * for anyone below the manage_options capability.
 */
class CHP_Credentials_Vault {

	private static $fields = array(
		'hosting_provider' => 'Hosting Provider',
		'hosting_login'    => 'Hosting Login URL',
		'hosting_notes'    => 'Hosting Notes',
		'domain_registrar' => 'Domain Registrar',
		'domain_login'     => 'Domain Login',
		'cloudflare_login' => 'Cloudflare Login',
		'smtp_details'     => 'SMTP Details',
		'analytics_id'     => 'Analytics ID',
		'fb_pixel_id'      => 'Facebook Pixel ID',
		'api_keys'         => 'API Keys',
	);

	/**
	 * Derives a storage key from WordPress's own auth salts. If wp-config
	 * salts are ever regenerated, previously stored values become
	 * unreadable — an accepted trade-off for not inventing new secret
	 * storage. There is nothing "administrator" can do to read this key
	 * from the database itself.
	 */
	private function encryption_key() {
		$key = ( defined( 'AUTH_KEY' ) && AUTH_KEY ? AUTH_KEY : wp_salt( 'auth' ) )
			. ( defined( 'AUTH_SALT' ) && AUTH_SALT ? AUTH_SALT : wp_salt( 'auth' ) );
		return hash( 'sha256', $key, true );
	}

	private function gcm_available() {
		return in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true );
	}

	/**
	 * Authenticated encryption (AES-256-GCM): a tampered ciphertext fails
	 * to decrypt rather than silently returning corrupted plaintext.
	 * Falls back to AES-256-CBC only on environments without GCM support.
	 */
	private function encrypt( $value ) {
		if ( '' === $value ) {
			return '';
		}
		$key = $this->encryption_key();

		if ( $this->gcm_available() ) {
			$iv   = random_bytes( 12 );
			$tag  = '';
			$data = openssl_encrypt( $value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
			if ( false === $data ) {
				return '';
			}
			return 'gcm:' . base64_encode( $iv . $tag . $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- storage encoding, not obfuscation.
		}

		$iv   = random_bytes( 16 );
		$data = openssl_encrypt( $value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $data ) {
			return '';
		}
		return 'cbc:' . base64_encode( $iv . $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- storage encoding, not obfuscation.
	}

	private function decrypt( $value ) {
		if ( '' === $value || false === strpos( $value, ':' ) ) {
			return '';
		}
		list( $mode, $encoded ) = explode( ':', $value, 2 );
		$raw = base64_decode( $encoded ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw ) {
			return '';
		}
		$key = $this->encryption_key();

		if ( 'gcm' === $mode ) {
			if ( strlen( $raw ) < 29 ) {
				return '';
			}
			$iv         = substr( $raw, 0, 12 );
			$tag        = substr( $raw, 12, 16 );
			$ciphertext = substr( $raw, 28 );
			$plain      = openssl_decrypt( $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			return false === $plain ? '' : $plain;
		}

		if ( 'cbc' === $mode ) {
			if ( strlen( $raw ) < 17 ) {
				return '';
			}
			$iv         = substr( $raw, 0, 16 );
			$ciphertext = substr( $raw, 16 );
			$plain      = openssl_decrypt( $ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
			return false === $plain ? '' : $plain;
		}

		return '';
	}

	private function get_vault() {
		$stored = get_option( 'chp_vault', array() );
		$vault  = array();
		foreach ( self::$fields as $key => $label ) {
			$vault[ $key ] = isset( $stored[ $key ] ) ? $this->decrypt( $stored[ $key ] ) : '';
		}
		return $vault;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'The Credentials Vault is only visible to administrators.', 'client-handover-pro' ) );
		}

		if ( ! CHP_License::is_pro() ) {
			?>
			<div class="wrap chp-wrap">
				<h1 class="chp-title"><?php esc_html_e( 'Credentials Vault', 'client-handover-pro' ); ?></h1>
				<?php CHP_License::render_upsell( __( 'Credentials Vault', 'client-handover-pro' ) ); ?>
			</div>
			<?php
			return;
		}

		if ( CHP_Helpers::verify_post( 'chp_vault_save', 'chp_vault_nonce' ) ) {
			$stored = array();
			foreach ( self::$fields as $key => $label ) {
				$raw            = isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';
				$stored[ $key ] = $this->encrypt( $raw );
			}
			update_option( 'chp_vault', $stored );
			CHP_Helpers::notice( __( 'Credentials saved securely.', 'client-handover-pro' ) );
		}

		$vault = $this->get_vault();
		?>
		<div class="wrap chp-wrap">
			<h1 class="chp-title"><?php esc_html_e( 'Credentials Vault', 'client-handover-pro' ); ?></h1>
			<p class="chp-subtitle"><?php esc_html_e( 'Encrypted storage, visible only to administrators.', 'client-handover-pro' ); ?></p>

			<form method="post" class="chp-card chp-form">
				<?php wp_nonce_field( 'chp_vault_save', 'chp_vault_nonce' ); ?>
				<?php foreach ( self::$fields as $key => $label ) : ?>
					<div class="chp-form-row">
						<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
						<textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="widefat" rows="2"><?php echo esc_textarea( $vault[ $key ] ); ?></textarea>
					</div>
				<?php endforeach; ?>
				<button type="submit" class="chp-btn chp-btn--primary"><?php esc_html_e( 'Save Securely', 'client-handover-pro' ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * Used by the Handover module to include a redacted/plain summary for
	 * admins only — never called for client-facing output.
	 */
	public function get_decrypted_for_handover() {
		if ( ! current_user_can( 'manage_options' ) || ! CHP_License::is_pro() ) {
			return array();
		}
		return $this->get_vault();
	}
}
