<?php
/**
 * Paymentez Checkout Gateway (card modal).
 *
 * @package PaymentezGateway
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Paymentez_Checkout_Gateway
 *
 * Presents a "Pay with Card" button that opens the Paymentez JS modal.
 */
class Paymentez_Checkout_Gateway extends Paymentez_Abstract_Gateway {

	const SDK_URL = 'https://cdn.paymentez.com/ccapi/sdk/payment_checkout_3.2.0.min.js';

	/**
	 * @var string Button label shown on checkout.
	 */
	private $button_text;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'paymentez_checkout';
		$this->icon               = apply_filters( 'paymentez_checkout_icon', parent::BRAND_ICON_URL );
		$this->has_fields         = true;
		$this->method_title       = __( 'Paymentez Checkout', 'paymentez-gateway' );
		$this->method_description = __( 'Accept card payments via the Paymentez modal checkout.', 'paymentez-gateway' );
		$this->log_source         = 'paymentez-checkout';

		$this->init_common_settings();

		$this->supports[]  = 'refunds';
		$this->button_text = $this->get_option( 'button_text', __( 'Pay with Card', 'paymentez-gateway' ) );

		// Enqueue scripts on checkout.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// AJAX handlers (both logged-in and guest users).
		add_action( 'wp_ajax_paymentez_get_token',          array( $this, 'ajax_get_token' ) );
		add_action( 'wp_ajax_nopriv_paymentez_get_token',   array( $this, 'ajax_get_token' ) );

		add_action( 'wp_ajax_paymentez_log_modal_response',        array( $this, 'ajax_log_modal_response' ) );
		add_action( 'wp_ajax_nopriv_paymentez_log_modal_response', array( $this, 'ajax_log_modal_response' ) );

		add_action( 'wp_ajax_paymentez_empty_cart',        array( $this, 'ajax_empty_cart' ) );
		add_action( 'wp_ajax_nopriv_paymentez_empty_cart', array( $this, 'ajax_empty_cart' ) );
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
					'label'   => __( 'Enable Paymentez Checkout', 'paymentez-gateway' ),
					'default' => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'paymentez-gateway' ),
					'type'        => 'text',
					'description' => __( 'Payment method title shown to the customer at checkout.', 'paymentez-gateway' ),
					'default'     => __( 'Pay with Card', 'paymentez-gateway' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'paymentez-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'Description shown below the payment method title at checkout.', 'paymentez-gateway' ),
					'default'     => __( 'Secure card payment powered by Paymentez.', 'paymentez-gateway' ),
					'desc_tip'    => true,
				),
				'button_text'       => array(
					'title'       => __( 'Button Text', 'paymentez-gateway' ),
					'type'        => 'text',
					'description' => __( 'Label displayed on the payment button.', 'paymentez-gateway' ),
					'default'     => __( 'Pay with Card', 'paymentez-gateway' ),
					'desc_tip'    => true,
				),
				'installments_type' => array(
					'title'       => __( 'Installment Types', 'paymentez-gateway' ),
					'type'        => 'multiselect',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Select the installment types your store accepts. Applies to Mexico (MX) and Ecuador (EC) stores only. Leave empty to disable installments.', 'paymentez-gateway' ),
					'default'     => array(),
					'options'     => $this->get_installment_options(),
				),
			)
		);
	}

	// -----------------------------------------------------------------------
	// Payment fields rendered on checkout page
	// -----------------------------------------------------------------------

	/**
	 * Output the payment fields block.
	 *
	 * Shows the description and, for stores with installments configured,
	 * a selector so the customer can choose their preferred installment plan
	 * before placing the order.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo '<p>' . esc_html( $this->description ) . '</p>';
		}

		$options = $this->get_configured_installment_options();
		if ( ! empty( $options ) ) {
			echo '<p class="form-row form-row-wide">';
			echo '<label for="paymentez_installments_type">' . esc_html__( 'Installments', 'paymentez-gateway' ) . ' <span class="required">*</span></label>';
			echo '<select id="paymentez_installments_type" name="paymentez_installments_type" class="woocommerce-select">';
			foreach ( $options as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
			echo '</p>';
		}
	}

	// -----------------------------------------------------------------------
	// Enqueue scripts & styles
	// -----------------------------------------------------------------------

	/**
	 * Enqueue Paymentez SDK and custom JS/CSS on the checkout page.
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( 'yes' !== $this->enabled ) {
			return;
		}

		wp_enqueue_script(
			'paymentez-sdk',
			self::SDK_URL,
			array(),
			'3.0.0',
			true
		);

		wp_enqueue_script(
			'paymentez-checkout',
			PAYMENTEZ_GATEWAY_URL . 'assets/js/checkout.js',
			array( 'jquery', 'paymentez-sdk' ),
			PAYMENTEZ_GATEWAY_VERSION,
			true
		);

		wp_enqueue_style(
			'paymentez-styles',
			PAYMENTEZ_GATEWAY_URL . 'assets/css/paymentez.css',
			array(),
			PAYMENTEZ_GATEWAY_VERSION
		);

		$auto_open = false;
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
			if ( $order_id ) {
				$order     = wc_get_order( $order_id );
				$auto_open = $order && $order->get_payment_method() === $this->id;
			}
		}

		wp_localize_script(
			'paymentez-checkout',
			'paymentezCheckoutParams',
			array(
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'paymentez_checkout_nonce' ),
				'environment' => $this->is_staging ? 'stg' : 'prod',
				'button_text' => esc_html( $this->button_text ),
				'gateway_id'  => $this->id,
				'auto_open'   => $auto_open,
				'i18n'        => array(
					'processing'       => __( 'Processing payment, please wait...', 'paymentez-gateway' ),
					'error_generic'    => __( 'An error occurred while processing your payment. Please try again.', 'paymentez-gateway' ),
					'error_token'      => __( 'Could not retrieve payment token. Please try again.', 'paymentez-gateway' ),
					'cancelled'        => __( 'Payment cancelled.', 'paymentez-gateway' ),
					'payment_approved' => __( 'Payment approved. Redirecting...', 'paymentez-gateway' ),
					'payment_pending'  => __( 'Payment pending. Redirecting...', 'paymentez-gateway' ),
					'transaction_id'   => __( 'Transaction', 'paymentez-gateway' ),
					'auth_code'        => __( 'Auth', 'paymentez-gateway' ),
				),
			)
		);
	}

	// -----------------------------------------------------------------------
	// Process payment
	// -----------------------------------------------------------------------

	/**
	 * Process the payment.
	 *
	 * For the Checkout modal flow, WooCommerce redirects the customer to the
	 * "pay" page; the modal is triggered by our JS. The order stays "pending"
	 * until the webhook confirms payment.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wc_add_notice( __( 'Order not found.', 'paymentez-gateway' ), 'error' );
			return array( 'result' => 'failure' );
		}

		// Save the customer's installment type selection (validated against allowed options).
		$installment_options = $this->get_configured_installment_options();
		if ( ! empty( $installment_options ) && isset( $_POST['paymentez_installments_type'] ) ) {
			$selected = sanitize_text_field( wp_unslash( $_POST['paymentez_installments_type'] ) );
			if ( array_key_exists( $selected, $installment_options ) ) {
				$order->update_meta_data( '_paymentez_installments_type', (int) $selected );
			}
		}

		// Mark as pending and send customer to the checkout pay page.
		// Cart is emptied only after the modal confirms approval (ajax_empty_cart).
		// update_status() calls save() internally, which persists the installments meta.
		$order->update_status( 'pending', __( 'Awaiting Paymentez card payment.', 'paymentez-gateway' ) );

		$this->log( 'Order #' . $order_id . ' set to pending, redirecting to pay page.' );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	// -----------------------------------------------------------------------
	// Refunds
	// -----------------------------------------------------------------------

	/**
	 * Process a refund for a card payment.
	 *
	 * Called by WooCommerce when an admin initiates a refund from the order screen.
	 * Supports both full and partial refunds. Only orders that were paid
	 * (WC status "processing", backed by a Paymentez transaction ID) can be refunded.
	 *
	 * Paymentez response status_detail:
	 *   7  — Full refund approved.
	 *  34  — Partial refund approved.
	 *
	 * @param int        $order_id WooCommerce order ID.
	 * @param float|null $amount   Amount to refund. Null means full refund.
	 * @param string     $reason   Optional reason note.
	 * @return true|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'paymentez_refund', __( 'Order not found.', 'paymentez-gateway' ) );
		}

		// Refunds are only possible on paid orders.
		if ( 'processing' !== $order->get_status() ) {
			return new WP_Error(
				'paymentez_refund',
				__( 'Refunds are only available for paid orders (status: processing).', 'paymentez-gateway' )
			);
		}

		$transaction_id = $order->get_meta( '_paymentez_transaction_id' );

		if ( empty( $transaction_id ) ) {
			return new WP_Error(
				'paymentez_refund',
				__( 'No Paymentez transaction ID found for this order.', 'paymentez-gateway' )
			);
		}

		// Default to the full order amount if none supplied.
		$refund_amount = ( null !== $amount && $amount > 0 ) ? (float) $amount : (float) $order->get_total();

		$this->log(
			sprintf(
				'Refund request for order #%d — transaction: %s, amount: %.2f',
				$order_id,
				$transaction_id,
				$refund_amount
			),
			'info'
		);

		$response = $this->make_api()->refund( $transaction_id, $refund_amount );

		$this->log( 'Refund raw response for order #' . $order_id . ': ' . wp_json_encode( $response ) );

		if ( is_wp_error( $response ) ) {
			$this->log( 'Refund WP_Error: ' . $response->get_error_message(), 'error' );
			return new WP_Error( 'paymentez_refund', $response->get_error_message() );
		}

		$status_detail = isset( $response['transaction']['status_detail'] )
			? (int) $response['transaction']['status_detail']
			: -1;

		// 7 = full refund approved, 34 = partial refund approved.
		if ( 7 === $status_detail || 34 === $status_detail ) {
			$refund_label = 7 === $status_detail
				? __( 'Full refund', 'paymentez-gateway' )
				: __( 'Partial refund', 'paymentez-gateway' );

			$note = sprintf(
				/* translators: 1: "Full refund" or "Partial refund", 2: formatted amount, 3: transaction ID */
				__( 'Paymentez: %1$s of %2$s processed successfully. Transaction ID: %3$s.', 'paymentez-gateway' ),
				$refund_label,
				wc_price( $refund_amount ),
				$transaction_id
			);

			if ( $reason ) {
				$note .= ' ' . sprintf(
					/* translators: %s: refund reason */
					__( 'Reason: %s', 'paymentez-gateway' ),
					sanitize_text_field( $reason )
				);
			}

			$order->add_order_note( $note );
			$this->log( 'Refund OK for order #' . $order_id . ' (status_detail ' . $status_detail . ')', 'info' );

			return true;
		}

		// Any other status_detail is a failure.
		$error_message = sanitize_text_field( $response['transaction']['message'] ?? '' );
		if ( empty( $error_message ) ) {
			$error_message = __( 'Refund failed. Please process it manually in the Paymentez dashboard.', 'paymentez-gateway' );
		}

		$this->log(
			sprintf( 'Refund failed for order #%d — status_detail: %d, message: %s', $order_id, $status_detail, $error_message ),
			'error'
		);

		return new WP_Error( 'paymentez_refund', $error_message );
	}

	// -----------------------------------------------------------------------
	// AJAX: obtain transaction reference server-side
	// -----------------------------------------------------------------------

	/**
	 * AJAX endpoint: call Paymentez init_reference server-side and return the
	 * transaction reference to the JS modal.
	 *
	 * Flow:
	 *  1. JS sends order_id + nonce.
	 *  2. PHP calls POST /v2/transaction/init_reference/ with order & user data.
	 *  3. PHP returns { reference, order_id, url_success, url_failure } to JS.
	 *  4. JS opens the modal using the reference.
	 */
	public function ajax_get_token() {
		check_ajax_referer( 'paymentez_checkout_nonce', 'nonce' );

		$order_id  = isset( $_POST['order_id'] )  ? absint( $_POST['order_id'] )                                        : 0;
		$order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'paymentez-gateway' ) ) );
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'paymentez-gateway' ) ) );
			return;
		}

		// Verify the caller knows the order secret key (same check WooCommerce uses
		// on the pay page). This prevents an authenticated user with a valid nonce
		// from retrieving a payment reference for someone else's order.
		if ( empty( $order_key ) || ! hash_equals( $order->get_order_key(), $order_key ) ) {
			$this->log( 'ajax_get_token — order key mismatch for order #' . $order_id, 'warning' );
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'paymentez-gateway' ) ) );
			return;
		}

		$api = $this->make_api();

		$user_id     = $order->get_user_id();
		$description = sprintf(
			/* translators: %s: order number */
			__( 'Order #%s', 'paymentez-gateway' ),
			$order->get_order_number()
		);

		// ── User ────────────────────────────────────────────────────────────
		$user = array(
			'id'        => (string) ( $user_id ?: 'guest_' . $order->get_id() ),
			'email'     => $order->get_billing_email(),
			'name'      => $order->get_billing_first_name(),
			'last_name' => $order->get_billing_last_name(),
		);

		// Optional: phone + area code.
		$raw_phone = $order->get_billing_phone();
		if ( $raw_phone ) {
			$user['phone'] = $this->format_phone( $raw_phone, $order->get_billing_country() );

			$alpha3 = $this->get_country_alpha3( $order->get_billing_country() );
			if ( $alpha3 ) {
				$user['country_phone_area_code'] = $alpha3;
			}
		}

		// Optional: IP address.
		$ip = WC_Geolocation::get_ip_address();
		if ( $ip ) {
			$user['ip_address'] = sanitize_text_field( $ip );
		}

		// Optional: fiscal number.
		[ $fiscal_number ] = $this->get_fiscal_data( $order );
		if ( $fiscal_number ) {
			$user['fiscal_number'] = $fiscal_number;
		}

		// ── Order ───────────────────────────────────────────────────────────
		$total_tax     = (float) $order->get_total_tax();
		$store_country = strtoupper( WC()->countries->get_base_country() );

		$order_data = array(
			'dev_reference' => (string) $order->get_id(),
			'description'   => $description,
			'amount'        => (float) $order->get_total(),
			'currency'      => $order->get_currency(),
            'vat'         => $total_tax
		);

		if ( 'EC' === $store_country || $total_tax > 0 ) {
			$taxable_amount = $this->calculate_taxable_amount( $order );
			$tax_percentage = $this->calculate_tax_percentage( $total_tax, $taxable_amount );

			$order_data['taxable_amount'] = $taxable_amount;
			$order_data['tax_percentage'] = $tax_percentage;
		}


		// Optional: installments_type — customer's selection saved in process_payment().
		$installments_type = $order->get_meta( '_paymentez_installments_type' );
		if ( $installments_type !== '' && $installments_type !== false && $installments_type !== null ) {
			$order_data['installments_type'] = (int) $installments_type;
		}

		// ── Payload ─────────────────────────────────────────────────────────
		$payload = array(
			'user'            => $user,
			'order'           => $order_data,
			'billing_address' => $this->get_billing_address( $order ),
		);

		$response = $api->init_reference( $payload );

		$this->log( 'init_reference raw response: ' . wp_json_encode( $response ) );

		if ( is_wp_error( $response ) ) {
			$this->log( 'init_reference WP_Error: ' . $response->get_error_message(), 'error' );
			wp_send_json_error( array( 'message' => __( 'Could not retrieve payment token. Please try again.', 'paymentez-gateway' ) ) );
			return;
		}

		$reference = $response['reference'] ?? '';

		if ( empty( $reference ) ) {
			$this->log( 'init_reference — empty reference. Full response: ' . wp_json_encode( $response ), 'error' );
			wp_send_json_error( array( 'message' => __( 'Could not retrieve payment token. Please try again.', 'paymentez-gateway' ) ) );
			return;
		}

		$order->update_meta_data( '_paymentez_init_reference', $reference );
		if ( ! empty( $response['checkout_url'] ) ) {
			$order->update_meta_data( '_paymentez_checkout_url', esc_url_raw( $response['checkout_url'] ) );
		}
		$order->save();

		$this->log( 'init_reference OK for order #' . $order_id . ' — reference: ' . $reference );

		wp_send_json_success(
			array(
				'reference'   => $reference,
				'order_id'    => $order->get_id(),
				'url_success' => $this->get_return_url( $order ),
				'url_failure' => wc_get_checkout_url(),
			)
		);
	}

	// -----------------------------------------------------------------------
	// AJAX: log modal response
	// -----------------------------------------------------------------------

	/**
	 * Receive the Paymentez modal onResponse payload from JS and write it to
	 * the WooCommerce log. Does NOT update the order — that is the webhook's job.
	 */
	public function ajax_log_modal_response() {
		check_ajax_referer( 'paymentez_checkout_nonce', 'nonce' );

		$order_id       = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$modal_response = isset( $_POST['modal_response'] ) ? wp_unslash( $_POST['modal_response'] ) : '';

		$this->log(
			sprintf(
				'Modal onResponse for order #%d: %s',
				$order_id,
				sanitize_textarea_field( $modal_response )
			),
			'info'
		);

		wp_send_json_success();
	}

	// -----------------------------------------------------------------------
	// AJAX: empty cart
	// -----------------------------------------------------------------------

	// -----------------------------------------------------------------------
	// Installment options
	// -----------------------------------------------------------------------

	/**
	 * Return only the installment type options the admin has enabled for this store.
	 * An empty array means installments are not configured.
	 *
	 * @return array<string,string>
	 */
	public function get_configured_installment_options(): array {
		$allowed = $this->get_option( 'installments_type', array() );

		if ( empty( $allowed ) ) {
			return array();
		}

		return array_intersect_key(
			$this->get_installment_options(),
			array_flip( array_map( 'strval', $allowed ) )
		);
	}

	/**
	 * Return the valid installment type options for the store's base country.
	 *
	 * Each country has a specific set of types negotiated with the banks.
	 * The full master list is provided as a fallback for any country not
	 * explicitly mapped below.
	 *
	 * @return array<string,string>
	 */
	private function get_installment_options(): array {
		/** Full master list of Paymentez installment types. */
		$all = array(
			'0'  => __( '0 — Revolving credit', 'paymentez-gateway' ),
			'1'  => __( '1 — Revolving and deferred without interest', 'paymentez-gateway' ),
			'2'  => __( '2 — Deferred with interest', 'paymentez-gateway' ),
			'3'  => __( '3 — Deferred without interest', 'paymentez-gateway' ),
			'6'  => __( '6 — Deferred without interest, pay month by month', 'paymentez-gateway' ),
			'7'  => __( '7 — Deferred with interest and months of grace', 'paymentez-gateway' ),
			'9'  => __( '9 — Deferred without interest and months of grace', 'paymentez-gateway' ),
			'10' => __( '10 — Deferred without interest, bimonthly promotion', 'paymentez-gateway' ),
			'21' => __( '21 — Diners Club: deferred with and without interest', 'paymentez-gateway' ),
			'22' => __( '22 — Diners Club: deferred with and without interest (variant)', 'paymentez-gateway' ),
			'30' => __( '30 — Deferred with interest, pay month by month', 'paymentez-gateway' ),
			'53' => __( '53 — Without interest sale with promotions', 'paymentez-gateway' ),
			'70' => __( '70 — Deferred special without interest', 'paymentez-gateway' ),
		);

		/** Valid types per country (keys must match WC base country codes). */
		$by_country = array(
			'MX' => array( '2', '3', '9' ),
		);

		$store_country  = WC()->countries->get_base_country();
		$allowed_keys   = $by_country[ $store_country ] ?? null;

		if ( null === $allowed_keys ) {
			return $all;
		}

		return array_intersect_key( $all, array_flip( $allowed_keys ) );
	}

	// -----------------------------------------------------------------------
	// AJAX: empty cart
	// -----------------------------------------------------------------------

	/**
	 * Empty the WooCommerce cart after a confirmed modal approval.
	 * Called by JS only when transaction.status === 'success'.
	 */
	public function ajax_empty_cart() {
		check_ajax_referer( 'paymentez_checkout_nonce', 'nonce' );

		if ( ! is_null( WC()->cart ) ) {
			WC()->cart->empty_cart();
		}

		wp_send_json_success();
	}

	// -----------------------------------------------------------------------
	// Tax helpers
	// -----------------------------------------------------------------------

	/**
	 * Calculate the tax percentage for a Paymentez payload.
	 *
	 * Only available for Ecuador and Colombia per Paymentez docs.
	 * Ecuador: 0 or 12. Colombia: 0 or 19.
	 * round() before cast avoids FP truncation (e.g. 14.9999... → 15, not 14).
	 *
	 * @param  float $total_tax      Total tax amount on the order.
	 * @param  float $taxable_amount Taxable base (output of calculate_taxable_amount).
	 * @return int
	 */
	protected function calculate_tax_percentage( float $total_tax, float $taxable_amount ): int {
		if ( $total_tax <= 0 || $taxable_amount <= 0 ) {
			return 0;
		}

		return (int) round( ( $total_tax / $taxable_amount ) * 100 );
	}

	/**
	 * Calculate the taxable base amount for a Paymentez payload.
	 *
	 * Only available for Ecuador and Colombia per Paymentez docs.
	 * Returns the sum of line totals (before tax) of items that carry tax.
	 * Returns 0.0 when the order has no tax at all.
	 *
	 * @param  WC_Order $order
	 * @return float
	 */
	protected function calculate_taxable_amount( WC_Order $order ): float {
		if ( (float) $order->get_total_tax() <= 0 ) {
			return 0.0;
		}

		$taxable_amount = 0.0;

		foreach ( $order->get_items() as $item ) {
			if ( (float) $item->get_total_tax() > 0 ) {
				$taxable_amount += (float) $item->get_total();
			}
		}

		// Include shipping base when WooCommerce has configured it as taxable.
		if ( (float) $order->get_shipping_tax() > 0 ) {
			$taxable_amount += (float) $order->get_shipping_total();
		}

		return round( $taxable_amount, 2 );
	}
}
