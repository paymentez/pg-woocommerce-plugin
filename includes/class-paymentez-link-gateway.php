<?php
/**
 * Paymentez Link-to-Pay Gateway.
 *
 * Generates a hosted payment link via the Paymentez Link-to-Pay API and
 * redirects the customer to it. The order status is updated asynchronously
 * via the Paymentez webhook.
 *
 * API endpoint:
 *   STG:  POST https://noccapi-stg.paymentez.com/linktopay/init_order/
 *   PROD: POST https://noccapi.paymentez.com/linktopay/init_order/
 *
 * @package PaymentezGateway
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Paymentez_Link_Gateway
 */
class Paymentez_Link_Gateway extends Paymentez_Abstract_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'paymentez_link';
		$this->icon               = apply_filters( 'paymentez_link_icon', parent::BRAND_ICON_URL );
		$this->has_fields         = false;
		$this->method_title       = __( 'Paymentez Link to Pay', 'paymentez-gateway' );
		$this->method_description = __( 'Redirect customers to a Paymentez-hosted payment page (cash, PSE, and more).', 'paymentez-gateway' );
		$this->log_source         = 'paymentez-link';

		$this->init_common_settings();
	}

	// -----------------------------------------------------------------------
	// Admin form fields
	// -----------------------------------------------------------------------

	/**
	 * Define admin settings form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array_merge(
			$this->common_form_fields(),
			array(
				'enabled'     => array(
					'title'   => __( 'Enable / Disable', 'paymentez-gateway' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Paymentez Link to Pay', 'paymentez-gateway' ),
					'default' => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'paymentez-gateway' ),
					'type'        => 'text',
					'description' => __( 'Payment method title shown to the customer at checkout.', 'paymentez-gateway' ),
					'default'     => __( 'Other Payment Methods', 'paymentez-gateway' ),
					'desc_tip'    => true,
				),
				'description'     => array(
					'title'       => __( 'Description', 'paymentez-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'Description shown below the payment method title at checkout.', 'paymentez-gateway' ),
					'default'     => __( 'Pay via multiple methods on the Paymentez secure payment page.', 'paymentez-gateway' ),
					'desc_tip'    => true,
				),
				'expiration_days' => array(
					'title'             => __( 'Expiration Days', 'paymentez-gateway' ),
					'type'              => 'number',
					'description'       => __( 'Number of days before the payment link expires. Leave empty to use the Paymentez default.', 'paymentez-gateway' ),
					'default'           => '',
					'desc_tip'          => true,
					'custom_attributes' => array(
						'min'  => 1,
						'step' => 1,
					),
				),
			)
		);
	}

	// -----------------------------------------------------------------------
	// Process payment
	// -----------------------------------------------------------------------

	/**
	 * Process payment: create a Link-to-Pay and redirect the customer.
	 *
	 * Single-click protection: if a valid payment link was already generated for
	 * this order (e.g. double form submission), we reuse it instead of calling
	 * the API again.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$this->log( '── process_payment start — order #' . $order_id, 'info' );

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			$this->log( 'Order #' . $order_id . ' not found.', 'error' );
			wc_add_notice( __( 'Order not found.', 'paymentez-gateway' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$this->log(
			sprintf(
				'Order #%d — total: %s %s | customer: %s | environment: %s',
				$order_id,
				$order->get_total(),
				$order->get_currency(),
				$order->get_billing_email(),
				$this->is_staging ? 'staging' : 'production'
			),
			'info'
		);

		// Single-click protection: reuse an existing link if already generated.
		$existing_url = $order->get_meta( '_paymentez_link_url' );
		if ( $existing_url ) {
			$this->log( 'Order #' . $order_id . ' — reusing existing link: ' . $existing_url, 'info' );
			WC()->cart->empty_cart();
			return array(
				'result'   => 'success',
				'redirect' => $existing_url,
			);
		}

		$payload  = $this->build_payload( $order );
		$response = $this->make_api()->create_payment_link( $payload );

		if ( is_wp_error( $response ) ) {
			$this->log( 'WP_Error — ' . $response->get_error_message(), 'error' );
			wc_add_notice(
				__( 'Payment error: could not create payment link. Please try again.', 'paymentez-gateway' ),
				'error'
			);
			return array( 'result' => 'failure' );
		}

		// Paymentez returns: { success: true, data: { payment: { payment_url: "..." }, order: { id: "..." } } }
		$payment_url = $response['data']['payment']['payment_url'] ?? '';

		if ( empty( $payment_url ) ) {
			$this->log( 'Empty payment_url — full response: ' . wp_json_encode( $response ), 'error' );
			wc_add_notice(
				__( 'Payment error: invalid response from Paymentez. Please try again.', 'paymentez-gateway' ),
				'error'
			);
			return array( 'result' => 'failure' );
		}

		$payment_url = esc_url_raw( $payment_url );

		$order->update_meta_data( '_paymentez_link_url', $payment_url );

		$paymentez_order_id = '';
		if ( ! empty( $response['data']['order']['id'] ) ) {
			$paymentez_order_id = sanitize_text_field( $response['data']['order']['id'] );
			$order->update_meta_data( '_paymentez_link_id', $paymentez_order_id );
		}

		$order->update_status( 'pending', __( 'Awaiting payment via Paymentez Link to Pay.', 'paymentez-gateway' ) );

		if ( $paymentez_order_id ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: Paymentez order ID */
					__( 'Paymentez Order ID: %s', 'paymentez-gateway' ),
					$paymentez_order_id
				)
			);
		}
		$order->save();

		WC()->cart->empty_cart();

		$this->log( '── process_payment end — order #' . $order_id . ' redirecting to: ' . $payment_url, 'info' );

		return array(
			'result'   => 'success',
			'redirect' => $payment_url,
		);
	}

	// -----------------------------------------------------------------------
	// Payload builder
	// -----------------------------------------------------------------------

	/**
	 * Build the request payload for the Link-to-Pay init_order endpoint.
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	private function build_payload( WC_Order $order ): array {
		$user_id = $order->get_user_id();

		$user = array(
			'id'        => (string) ( $user_id ?: 'guest_' . $order->get_id() ),
			'email'     => $order->get_billing_email(),
			'name'      => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'last_name' => $order->get_billing_last_name(),
			'phone'     => $this->format_phone( $order->get_billing_phone(), $order->get_billing_country() ),
			'address'   => trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() ),
		);

		// Append fiscal data only when available.
		[ $fiscal_number, $fiscal_number_type ] = $this->get_fiscal_data( $order );
		if ( $fiscal_number !== '' ) {
			$user['fiscal_number']      = $fiscal_number;
			$user['fiscal_number_type'] = $fiscal_number_type;
		}

		$billing_address = $this->get_billing_address( $order );

		$order_data = array(
			'dev_reference'     => (string) $order->get_id(),
			'description'       => sprintf(
				/* translators: %s: order number */
				__( 'Order #%s', 'paymentez-gateway' ),
				$order->get_order_number()
			),
			'amount'            => (float) $order->get_total(),
			'currency'          => $order->get_currency(),
			'installments_type' => -1,
		);

		$expiration_days = (int) $this->get_option( 'expiration_days' );
		if ( $expiration_days > 0 ) {
			$order_data['expiration_days'] = $expiration_days;
		}

		return array(
			'user'            => $user,
			'order'           => $order_data,
			'billing_address' => $billing_address,
			'configuration'   => array(
				'allowed_payment_methods' => array( 'All' ),
				'success_url'             => $this->get_return_url( $order ),
				'failure_url'             => $order->get_checkout_payment_url(),
				'pending_url'             => $this->get_return_url( $order ),
				'review_url'              => $this->get_return_url( $order ),
			),
		);
	}

}
