<?php
/**
 * Plugin Name: Nuvei Paymentez Gateway for WooCommerce
 * Plugin URI:  https://github.com/paymentez/paymentez-woocommerce
 * Description: Integrates Paymentez payment gateway (Card Checkout & Link to Pay) into WooCommerce.
 * Version:     3.0.0
 * Author:      Paymentez
 * Author URI:  https://www.paymentez.com
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: paymentez-gateway
 * Domain Path: /languages
 * Requires at least: 6.5
 * Tested up to:      6.9
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 * WC tested up to:      10.0
 *
 * @package PaymentezGateway
 */

defined( 'ABSPATH' ) || exit;

define( 'PAYMENTEZ_GATEWAY_VERSION', '3.0.0' );
define( 'PAYMENTEZ_GATEWAY_FILE', __FILE__ );
define( 'PAYMENTEZ_GATEWAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'PAYMENTEZ_GATEWAY_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare WooCommerce HPOS (High-Performance Order Storage) compatibility.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				__FILE__,
				true
			);
		}
	}
);

/**
 * Check that WooCommerce is active before loading the plugin.
 */
function paymentez_gateway_check_requirements() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'paymentez_gateway_missing_wc_notice' );
		return false;
	}
	return true;
}

/**
 * Admin notice shown when WooCommerce is not active.
 */
function paymentez_gateway_missing_wc_notice() {
	echo '<div class="error"><p>' .
		esc_html__( 'Nuvei Paymentez Gateway requires WooCommerce to be installed and active.', 'paymentez-gateway' ) .
		'</p></div>';
}

/**
 * Load plugin text domain.
 */
function paymentez_gateway_load_textdomain() {
	load_plugin_textdomain(
		'paymentez-gateway',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'init', 'paymentez_gateway_load_textdomain' );

/**
 * Bootstrap the plugin after all plugins are loaded.
 */
function paymentez_gateway_init() {
	if ( ! paymentez_gateway_check_requirements() ) {
		return;
	}

	require_once PAYMENTEZ_GATEWAY_DIR . 'includes/class-paymentez-api.php';
	require_once PAYMENTEZ_GATEWAY_DIR . 'includes/class-paymentez-abstract-gateway.php';
	require_once PAYMENTEZ_GATEWAY_DIR . 'includes/class-paymentez-checkout-gateway.php';
	require_once PAYMENTEZ_GATEWAY_DIR . 'includes/class-paymentez-link-gateway.php';
	require_once PAYMENTEZ_GATEWAY_DIR . 'includes/class-paymentez-webhook.php';

	// Register REST webhook endpoint.
	Paymentez_Webhook::init();

	// Register payment gateways with WooCommerce.
	add_filter(
		'woocommerce_payment_gateways',
		function ( $gateways ) {
			$gateways[] = 'Paymentez_Checkout_Gateway';
			$gateways[] = 'Paymentez_Link_Gateway';
			return $gateways;
		}
	);

	// Register Checkout Block integrations when WooCommerce Blocks is available.
	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function ( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry ) {
			require_once PAYMENTEZ_GATEWAY_DIR . 'includes/class-paymentez-blocks.php';
			$registry->register( new Paymentez_Checkout_Block() );
			$registry->register( new Paymentez_Link_Block() );
		}
	);
}
add_action( 'plugins_loaded', 'paymentez_gateway_init' );
