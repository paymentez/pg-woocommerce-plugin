<?php
/**
 * Paymentez API Client
 *
 * Handles server-side communication with the Paymentez REST API,
 * including Auth-Token generation (HMAC-SHA256) and request dispatch.
 *
 * @package PaymentezGateway
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Paymentez_API
 */
class Paymentez_API {

	// -----------------------------------------------------------------------
	// Base URLs
	// -----------------------------------------------------------------------

	const BASE_URL_STG  = 'https://ccapi-stg.paymentez.com/v2/';
	const BASE_URL_PROD = 'https://ccapi.paymentez.com/v2/';

	const LINK_BASE_URL_STG  = 'https://noccapi-stg.paymentez.com/';
	const LINK_BASE_URL_PROD = 'https://noccapi.paymentez.com/';

	/**
	 * @var string App code.
	 */
	private $app_code;

	/**
	 * @var string App secret key.
	 */
	private $app_key;

	/**
	 * @var bool Whether running in staging mode.
	 */
	private $is_staging;

	/**
	 * @var \WC_Logger|null Logger instance.
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param string $app_code   Paymentez App Code.
	 * @param string $app_key    Paymentez App Key.
	 * @param bool   $is_staging Use staging environment.
	 */
	public function __construct( string $app_code, string $app_key, bool $is_staging = true ) {
		$this->app_code   = $app_code;
		$this->app_key    = $app_key;
		$this->is_staging = $is_staging;
		$this->logger     = wc_get_logger();
	}

	// -----------------------------------------------------------------------
	// Auth
	// -----------------------------------------------------------------------

	/**
	 * Generate a valid Paymentez Auth-Token header value.
	 *
	 * Format: base64( app_code + ";" + unix_timestamp + ";" + sha256_token )
	 * where sha256_token = SHA-256( app_key + unix_timestamp ).
	 *
	 * @return string
	 */
	public function generate_auth_token(): string {
		$unix_timestamp    = time();
		$uniq_token_hash   = hash( 'sha256', $this->app_key . $unix_timestamp );
		return base64_encode( $this->app_code . ';' . $unix_timestamp . ';' . $uniq_token_hash );
	}

	// -----------------------------------------------------------------------
	// Endpoints
	// -----------------------------------------------------------------------

	/**
	 * Get the base URL for checkout / card API calls.
	 *
	 * @return string
	 */
	private function get_base_url(): string {
		return $this->is_staging ? self::BASE_URL_STG : self::BASE_URL_PROD;
	}

	/**
	 * Get the base URL for Link-to-Pay API calls.
	 *
	 * @return string
	 */
	private function get_link_base_url(): string {
		return $this->is_staging ? self::LINK_BASE_URL_STG : self::LINK_BASE_URL_PROD;
	}

	// -----------------------------------------------------------------------
	// HTTP helpers
	// -----------------------------------------------------------------------

	/**
	 * Perform a POST request to the Paymentez API.
	 *
	 * @param string $endpoint Relative endpoint path.
	 * @param array  $body     Request body (will be JSON-encoded).
	 * @param bool   $use_link Whether to use the Link-to-Pay base URL.
	 * @return array|WP_Error Decoded response body array or WP_Error on failure.
	 */
	public function post( string $endpoint, array $body, bool $use_link = false ) {
		$url = ( $use_link ? $this->get_link_base_url() : $this->get_base_url() ) . ltrim( $endpoint, '/' );

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
				'Auth-Token'   => $this->generate_auth_token(),
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 30,
		);

		$this->log( 'POST ' . $url . ' | body: ' . wp_json_encode( $body ) );

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( 'WP_Error: ' . $response->get_error_message(), 'error' );
			return $response;
		}

		$code          = (int) wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$this->log( 'Response [' . $code . ']: ' . $response_body );

		$decoded = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'paymentez_json_error', __( 'Invalid JSON response from Paymentez.', 'paymentez-gateway' ) );
		}

		if ( $code < 200 || $code >= 300 ) {
			$message = sanitize_text_field( $decoded['detail'] ?? $decoded['message'] ?? __( 'Paymentez API error.', 'paymentez-gateway' ) );
			$this->log( 'HTTP error ' . $code . ': ' . $message, 'error' );
			return new WP_Error( 'paymentez_http_error', $message );
		}

		return $decoded;
	}

	// -----------------------------------------------------------------------
	// Business methods
	// -----------------------------------------------------------------------

	/**
	 * Call init_reference to obtain the transaction token required by the Checkout modal.
	 *
	 * Endpoint: POST /v2/transaction/init_reference/
	 * The returned transaction.token must be passed as order_reference when opening the modal.
	 *
	 * @param array $payload  { user: {...}, order: {...} }
	 * @return array|WP_Error
	 */
	public function init_reference( array $payload ) {
		return $this->post( 'transaction/init_reference/', $payload );
	}

	/**
	 * Create a Link-to-Pay and return the redirect URL.
	 *
	 * @param array $payload Request payload for the link creation endpoint.
	 * @return array|WP_Error
	 */
	public function create_payment_link( array $payload ) {
		return $this->post( 'linktopay/init_order/', $payload, true );
	}

	/**
	 * Request a refund for a card transaction.
	 *
	 * Endpoint: POST /v2/transaction/refund/
	 *
	 * Response status_detail values:
	 *   7  — Full refund successful.
	 *  34  — Partial refund successful.
	 *
	 * @param string $transaction_id Paymentez transaction ID (stored as _paymentez_transaction_id).
	 * @param float  $amount         Amount to refund in the order's currency.
	 * @return array|WP_Error
	 */
	public function refund( string $transaction_id, float $amount ) {
		return $this->post(
			'transaction/refund/',
			array(
				'transaction' => array( 'id' => $transaction_id ),
				'order'       => array( 'amount' => $amount ),
				'more_info'   => true,
			)
		);
	}

	// -----------------------------------------------------------------------
	// Logger helper
	// -----------------------------------------------------------------------

	/**
	 * Write a message to the WooCommerce log.
	 *
	 * @param string $message Log message.
	 * @param string $level   'debug' | 'info' | 'notice' | 'warning' | 'error'.
	 */
	private function log( string $message, string $level = 'debug' ) {
		if ( $this->logger ) {
			$this->logger->log( $level, $message, array( 'source' => 'paymentez-api' ) );
		}
	}
}
