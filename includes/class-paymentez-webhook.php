<?php
/**
 * Paymentez Webhook Handler.
 *
 * Registers a REST API endpoint at:
 *   /wp-json/paymentez/webhook/v1/params
 *
 * Validates the Paymentez signature and updates the WooCommerce order status
 * according to the received transaction status.
 *
 * @package PaymentezGateway
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Paymentez_Webhook
 */
class Paymentez_Webhook {

	/**
	 * Mapping of Paymentez status_detail (int) → WooCommerce order status.
	 *
	 * Source: https://developers.paymentez.com/api/#status-details
	 *
	 *  1  – Pending (initial, waiting for payment action)
	 *  2  – Processing (payment started / pending 3DS step)
	 *  3  – Paid (successful)
	 *  4  – Waiting for OTP / additional confirmation
	 *  5  – Cancelled (merchant-side cancellation)
	 *  6  – Reversed / refunded
	 *  7  – Fraudulent / blocked by fraud rules
	 *  8  – Error (gateway error)
	 *  9  – Rejected by issuer / bank decline
	 * 10  – Cancelled by user
	 * 11  – Cancelled by integration / timeout
	 */
	const STATUS_DETAIL_MAP = array(
		1  => 'on-hold',    // Pending — awaiting action
		2  => 'on-hold',    // Processing — in-flight
		3  => 'processing', // Paid ✓
		4  => 'on-hold',    // Waiting for confirmation
		5  => 'cancelled',  // Cancelled by merchant
		6  => 'refunded',   // Reversed / refunded
		7  => 'failed',     // Fraudulent
		8  => 'failed',     // Gateway error
		9  => 'failed',     // Rejected by bank
		10 => 'cancelled',  // Cancelled by user
		11 => 'cancelled',  // Cancelled by integration
	);

	/**
	 * Register the REST route.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	/**
	 * Register the webhook REST route.
	 */
	public static function register_route() {
		register_rest_route(
			'paymentez/webhook',
			'/v1/params',
			array(
				'methods'             => WP_REST_Server::CREATABLE, // POST
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => '__return_true', // Paymentez sends unauthenticated POST
			)
		);
	}

	/**
	 * Handle the incoming webhook request.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$logger = wc_get_logger();
		$body   = $request->get_body();

		$logger->debug( 'Paymentez webhook received: ' . $body, array( 'source' => 'paymentez-webhook' ) );

		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE || empty( $data ) ) {
			$logger->error( 'Invalid JSON payload.', array( 'source' => 'paymentez-webhook' ) );
			return self::response( 'error', __( 'Invalid payload.', 'paymentez-gateway' ), 400 );
		}

		// Validate required fields.
		if ( empty( $data['transaction'] ) ) {
			$logger->error( 'Missing transaction data.', array( 'source' => 'paymentez-webhook' ) );
			return self::response( 'error', __( 'Missing required data.', 'paymentez-gateway' ), 400 );
		}

		$transaction = $data['transaction'];
		$user        = $data['user'] ?? array();

		// Validate signature.
		if ( ! self::validate_signature( $transaction, $user ) ) {
			$logger->error( 'Webhook signature validation failed.', array( 'source' => 'paymentez-webhook' ) );
			return self::response( 'error', __( 'Invalid signature.', 'paymentez-gateway' ), 401 );
		}

		// Resolve order from dev_reference (our WooCommerce order ID).
		// Paymentez sends dev_reference inside the transaction object.
		$order_id = isset( $transaction['dev_reference'] ) ? absint( $transaction['dev_reference'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : null;

		if ( ! $order ) {
			$logger->error( 'Order not found: ' . $order_id, array( 'source' => 'paymentez-webhook' ) );
			return self::response( 'error', __( 'Order not found.', 'paymentez-gateway' ), 404 );
		}

		$paymentez_status_detail = isset( $transaction['status_detail'] ) ? (int) $transaction['status_detail'] : -1;
		$paymentez_status        = strtolower( sanitize_text_field( $transaction['status'] ?? '' ) );
		$paymentez_tx_id         = sanitize_text_field( $transaction['id'] ?? '' );
		$paymentez_auth_code     = sanitize_text_field( $transaction['authorization_code'] ?? '' );
		$paymentez_amount        = isset( $transaction['amount'] ) ? (float) $transaction['amount'] : null;

		$logger->info(
			sprintf(
				'Order #%d — status: %s, status_detail: %d, tx_id: %s',
				$order_id,
				$paymentez_status,
				$paymentez_status_detail,
				$paymentez_tx_id
			),
			array( 'source' => 'paymentez-webhook' )
		);

		// Store Paymentez transaction metadata.
		if ( $paymentez_tx_id ) {
			$order->update_meta_data( '_paymentez_transaction_id', $paymentez_tx_id );
		}
		if ( $paymentez_auth_code ) {
			$order->update_meta_data( '_paymentez_auth_code', $paymentez_auth_code );
		}
		$order->update_meta_data( '_paymentez_status', $paymentez_status );
		$order->update_meta_data( '_paymentez_status_detail', $paymentez_status_detail );

		// Map and update WC order status using status_detail (integer).
		$wc_status = self::STATUS_DETAIL_MAP[ $paymentez_status_detail ] ?? null;

		if ( null !== $wc_status ) {
			$note = sprintf(
				/* translators: 1: status_detail code, 2: Paymentez status string, 3: transaction ID */
				__( 'Paymentez: status_detail %1$d (%2$s). Transaction ID: %3$s.', 'paymentez-gateway' ),
				$paymentez_status_detail,
				$paymentez_status,
				$paymentez_tx_id
			);

			// Downgrade protection: never move a successfully paid order back to
			// failed or cancelled. Out-of-order webhook delivery or a replayed
			// older event must not undo a confirmed payment.
			$current_status    = $order->get_status();
			$positive_statuses = array( 'processing', 'completed' );
			$negative_statuses = array( 'failed', 'cancelled' );

			if ( in_array( $current_status, $positive_statuses, true ) &&
			     in_array( $wc_status, $negative_statuses, true ) ) {
				$logger->warning(
					sprintf(
						'Order #%d — ignoring downgrade from "%s" to "%s" (status_detail %d).',
						$order_id,
						$current_status,
						$wc_status,
						$paymentez_status_detail
					),
					array( 'source' => 'paymentez-webhook' )
				);
				$order->add_order_note(
					sprintf(
						/* translators: 1: incoming status, 2: current status */
						__( 'Paymentez: received status "%1$s" but order is already "%2$s" — status not changed.', 'paymentez-gateway' ),
						$wc_status,
						$current_status
					)
				);
			} elseif ( 'processing' === $wc_status && null !== $paymentez_amount ) {
				// Amount validation: block payment confirmation if the amount Paymentez
				// reports does not match the order total (prevents partial-payment fraud).
				$order_total    = (float) $order->get_total();
				$amount_matches = abs( $paymentez_amount - $order_total ) < 0.01;

				if ( ! $amount_matches ) {
					$logger->error(
						sprintf(
							'Order #%d — amount mismatch: Paymentez reported %.2f, order total is %.2f. Payment NOT confirmed.',
							$order_id,
							$paymentez_amount,
							$order_total
						),
						array( 'source' => 'paymentez-webhook' )
					);
					$order->add_order_note(
						sprintf(
							/* translators: 1: amount from Paymentez, 2: expected order total */
							__( 'Paymentez: payment NOT confirmed — amount mismatch (received: %1$.2f, expected: %2$.2f). Manual review required.', 'paymentez-gateway' ),
							$paymentez_amount,
							$order_total
						)
					);
					$order->save();
					return self::response( 'error', __( 'Amount mismatch.', 'paymentez-gateway' ), 400 );
				}

				$order->update_status( $wc_status, $note );
			} else {
				$order->update_status( $wc_status, $note );
			}
		} else {
			$logger->warning(
				'Unknown status_detail: ' . $paymentez_status_detail,
				array( 'source' => 'paymentez-webhook' )
			);
			$order->add_order_note(
				sprintf(
					/* translators: 1: status_detail code, 2: status string */
					__( 'Paymentez: received unknown status_detail %1$d (%2$s).', 'paymentez-gateway' ),
					$paymentez_status_detail,
					esc_html( $paymentez_status )
				)
			);
		}

		$order->save();

		return self::response( 'ok', __( 'Webhook processed successfully.', 'paymentez-gateway' ) );
	}

	// -----------------------------------------------------------------------
	// Signature validation
	// -----------------------------------------------------------------------

	/**
	 * Validate the Paymentez webhook signature.
	 *
	 * Paymentez signs each callback with an stoken field inside the transaction
	 * object. The expected value is:
	 *
	 *   MD5( transaction_id + "_" + application_code + "_" + user_id + "_" + app_key )
	 *
	 * Where:
	 *   - transaction_id    = transaction.id
	 *   - application_code  = transaction.application_code
	 *   - user_id           = user.id
	 *   - app_key           = secret key stored in gateway settings
	 *
	 * @param array $transaction Transaction data from payload.
	 * @param array $user        User data from payload.
	 * @return bool
	 */
	private static function validate_signature( array $transaction, array $user ): bool {
		$received_stoken  = $transaction['stoken'] ?? '';
		$application_code = $transaction['application_code'] ?? '';
		$transaction_id   = $transaction['id'] ?? '';
		$user_id          = $user['id'] ?? '';

		if ( empty( $received_stoken ) || empty( $application_code ) ) {
			return false;
		}

		// Try to find a matching gateway and validate.
		$gateway_ids = array( 'paymentez_checkout', 'paymentez_link' );

		foreach ( $gateway_ids as $gateway_id ) {
			$settings = get_option( 'woocommerce_' . $gateway_id . '_settings', array() );
			$app_code = $settings['app_code'] ?? '';
			$app_key  = $settings['app_key'] ?? '';

			if ( empty( $app_code ) || empty( $app_key ) ) {
				continue;
			}

			if ( $application_code !== $app_code ) {
				continue;
			}

			$expected_stoken = md5( $transaction_id . '_' . $application_code . '_' . $user_id . '_' . $app_key );

			if ( hash_equals( $expected_stoken, $received_stoken ) ) {
				return true;
			}
		}

		return false;
	}

	// -----------------------------------------------------------------------
	// Response helper
	// -----------------------------------------------------------------------

	/**
	 * Build a standardised REST response.
	 *
	 * @param string $status  'ok' | 'error'
	 * @param string $message Human-readable message.
	 * @param int    $code    HTTP status code.
	 * @return WP_REST_Response
	 */
	private static function response( string $status, string $message, int $code = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'status'  => $status,
				'message' => $message,
			),
			$code
		);
	}
}
