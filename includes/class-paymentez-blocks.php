<?php
/**
 * WooCommerce Checkout Block integrations for Paymentez gateways.
 *
 * Each class registers one gateway so it appears in the Checkout Block.
 * The actual payment processing is still handled server-side by the
 * corresponding WC_Payment_Gateway subclass via process_payment().
 *
 * @package PaymentezGateway
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

// ---------------------------------------------------------------------------
// Paymentez Checkout (card modal) — Block integration
// ---------------------------------------------------------------------------

/**
 * Class Paymentez_Checkout_Block
 */
class Paymentez_Checkout_Block extends AbstractPaymentMethodType {

	/**
	 * Payment method name — must match the gateway id.
	 *
	 * @var string
	 */
	protected $name = 'paymentez_checkout';

	/**
	 * Reference to the WC gateway instance.
	 *
	 * @var Paymentez_Checkout_Gateway|null
	 */
	private $gateway;

	/**
	 * Load settings and gateway instance.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_paymentez_checkout_settings', array() );
		$gateways       = WC()->payment_gateways()->payment_gateways();
		$this->gateway  = $gateways['paymentez_checkout'] ?? null;
	}

	/**
	 * Whether this payment method is active / available.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->gateway instanceof WC_Payment_Gateway && $this->gateway->is_available();
	}

	/**
	 * Register and return the JS handle(s) for this payment method.
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles(): array {
		wp_register_script(
			'paymentez-checkout-block',
			PAYMENTEZ_GATEWAY_URL . 'assets/js/paymentez-checkout-block.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
			PAYMENTEZ_GATEWAY_VERSION,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'paymentez-checkout-block', 'paymentez-gateway' );
		}

		return array( 'paymentez-checkout-block' );
	}

	/**
	 * Data passed to the JS block via wc.wcSettings.getSetting().
	 *
	 * @return array
	 */
	public function get_payment_method_data(): array {
		$installments = array();
		if ( $this->gateway instanceof Paymentez_Checkout_Gateway ) {
			foreach ( $this->gateway->get_configured_installment_options() as $value => $label ) {
				$installments[] = array(
					'value' => (string) $value,
					'label' => $label,
				);
			}
		}

		return array(
			'title'        => $this->get_setting( 'title', __( 'Pay with Card', 'paymentez-gateway' ) ),
			'description'  => $this->get_setting( 'description', '' ),
			'supports'     => array( 'products' ),
			'installments' => $installments,
		);
	}
}

// ---------------------------------------------------------------------------
// Paymentez Link to Pay — Block integration
// ---------------------------------------------------------------------------

/**
 * Class Paymentez_Link_Block
 */
class Paymentez_Link_Block extends AbstractPaymentMethodType {

	/**
	 * Payment method name — must match the gateway id.
	 *
	 * @var string
	 */
	protected $name = 'paymentez_link';

	/**
	 * Reference to the WC gateway instance.
	 *
	 * @var Paymentez_Link_Gateway|null
	 */
	private $gateway;

	/**
	 * Load settings and gateway instance.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_paymentez_link_settings', array() );
		$gateways       = WC()->payment_gateways()->payment_gateways();
		$this->gateway  = $gateways['paymentez_link'] ?? null;
	}

	/**
	 * Whether this payment method is active / available.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->gateway instanceof WC_Payment_Gateway && $this->gateway->is_available();
	}

	/**
	 * Register and return the JS handle(s) for this payment method.
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles(): array {
		wp_register_script(
			'paymentez-link-block',
			PAYMENTEZ_GATEWAY_URL . 'assets/js/paymentez-link-block.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
			PAYMENTEZ_GATEWAY_VERSION,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'paymentez-link-block', 'paymentez-gateway' );
		}

		return array( 'paymentez-link-block' );
	}

	/**
	 * Data passed to the JS block via wc.wcSettings.getSetting().
	 *
	 * @return array
	 */
	public function get_payment_method_data(): array {
		return array(
			'title'       => $this->get_setting( 'title', __( 'Other Payment Methods', 'paymentez-gateway' ) ),
			'description' => $this->get_setting( 'description', '' ),
			'supports'    => array( 'products' ),
		);
	}
}