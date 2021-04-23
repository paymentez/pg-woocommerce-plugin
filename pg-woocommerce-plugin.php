<?php

/*
Plugin Name: Paymentez WooCommerce Plugin
Plugin URI: http://www.paymentez.com
Description: This module is a solution that allows WooCommerce users to easily process credit card payments.
Version: 2.0.1
Author: Paymentez
Author URI: https://developers.paymentez.com/docs/payments/
Text Domain: pg_woocommerce
Domain Path: /languages
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

require( dirname( __FILE__ ) . '/includes/pg-woocommerce-refund.php' );
require(dirname(__FILE__) . '/includes/pg-woocommerce-webhook-api.php');

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

const PG_FLAVOR = "Paymentez";
const PG_DOMAIN = "paymentez.com";
const PG_REFUND = "/v2/transaction/refund/";
const PG_LTP = "/linktopay/init_order/";

add_action( 'plugins_loaded', 'pg_woocommerce_plugin' );

function paymentez_payment_webhook( WP_REST_Request $request ) {
    $parameters = $request->get_params();
    try {
        $order = new WC_Order($parameters['transaction']['dev_reference']);
        $response_params = WC_Payment_Webhook_PG::update_order($order, $parameters);
        return new WP_REST_Response($response_params['message'], $response_params['code']);
    } catch (Exception $e){
        return new WP_REST_Response("update order fails, details: {$e}", 400);
    }
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'paymentez/webhook/v1', 'params', array(
        'methods' => WP_REST_SERVER::CREATABLE,
        'callback' => 'paymentez_payment_webhook',
        'args' => array(),
        'permission_callback' => function () {
            return true;
        }
    ) );
});

register_activation_hook( __FILE__, array( 'PG_WC_Helper', 'create_table' ) );

load_plugin_textdomain( 'pg_woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

if (!function_exists('pg_woocommerce_plugin')) {
  function pg_woocommerce_plugin() {
    class PG_WC_Plugin extends WC_Payment_Gateway {
      public function __construct() {
        $this->id                 = 'pg_woocommerce';
        $this->icon               = apply_filters('woocomerce_pg_icon', plugins_url('/assets/imgs/payment_checkout.png', __FILE__));
        $this->method_title       = PG_FLAVOR;
        $this->method_description = __('This module is a solution that allows WooCommerce users to easily process credit card payments. Developed by: ', 'pg_woocommerce').PG_FLAVOR;
        $this->supports           = array( 'products', 'refunds' );

        $this->init_settings();
        $this->init_form_fields();

        $this->title              = $this->get_option('title');
        $this->description        = $this->get_option('description');

        $this->checkout_language  = $this->get_option('checkout_language');
        $this->environment        = $this->get_option('staging');
        $this->enable_ltp         = $this->get_option('enable_ltp');
        $this->installments_type  = $this->get_option('installments_type');

        $this->app_code_client    = $this->get_option('app_code_client');
        $this->app_key_client     = $this->get_option('app_key_client');
        $this->app_code_server    = $this->get_option('app_code_server');
        $this->app_key_server     = $this->get_option('app_key_server');

        $this->css                = plugins_url('/assets/css/styles.css', __FILE__);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));

        add_action('woocommerce_receipt_pg_woocommerce', array(&$this, 'receipt_page'));
      }

      public function init_form_fields() {
        $this->form_fields = require( dirname( __FILE__ ) . '/includes/admin/pg-woocommerce-settings.php' );
      }

      function admin_options() {
        $logo = plugins_url('/assets/imgs/payment.png', __FILE__);
        ?>
        <p>
          <img style='width: 30%;position: relative;display: inherit;'src='<?php echo $logo;?>'>
        </p>
        <h2><?php echo PG_FLAVOR.' Gateway'; ?></h2>
          <table class="form-table">
            <?php $this->generate_settings_html(); ?>
          </table>
        <?php
      }

      function receipt_page($orderId) {
        $order = new WC_Order($orderId);
        echo $this->generate_cc_form($order);
        if ($this->enable_ltp == 'yes') {
          echo $this->generate_ltp_form($order);
        }
      }

			public function process_refund( $order_id, $amount = null,  $reason = '' ) {
				$refund = new WC_Payment_Refund_PG();
				$refund_data = $refund->refund($order_id, $amount);
				if ($refund_data['success']) {
					$order = new WC_Order($order_id);
					$order->add_order_note( __('Transaction: ', 'pg_woocommerce') . $refund_data['transaction_id'] . __(' refund status: ', 'pg_woocommerce') . $refund_data['status'] . __(' reason: ', 'pg_woocommerce') . $reason);
					return $refund_data['success'];
				} else {
					return $refund_data['success'];
				}
			}

      public function generate_ltp_form($order) {
        $url = PG_WC_Helper::generate_ltp($order, $this->environment);
				$order->update_status( 'on-hold', __( 'Payment status will be updated via webhook.', 'pg_woocommerce' ) );
        ?>
          <link rel="stylesheet" type="text/css" href="<?php echo $this->css; ?>">
          <button id="ltp-button" class="<?php if($url == NULL){echo "hide";} else {echo "ltp-button";} ?>" onclick="ltpRedirect()">
            <?php _e('Pay with Cash/Bank Transfer', 'pg_woocommerce'); ?>
          </button>
          <script>
            function ltpRedirect() {
              location.replace("<?php echo $url; ?>")
            }
          </script>
        <?php
      }

      public function generate_cc_form($order) {
        $webhook_p = plugins_url('/includes/pg-woocommerce-webhook-checkout.php', __FILE__);
        $checkout = plugins_url('/assets/js/payment_checkout.js', __FILE__);
        $order_data = PG_WC_Helper::get_checkout_params($order);
        ?>
          <link rel="stylesheet" type="text/css" href="<?php echo $this->css; ?>">

          <div id="msj-succcess" class="hide"> <p class="alert alert-success" ><?php _e('Your payment has been made successfully. Thank you for your purchase.', 'pg_woocommerce'); ?></p> </div>
          <div id="msj-failed" class="hide"> <p class="alert alert-warning"><?php _e('An error occurred while processing your payment and could not be made. Try another Credit Card.', 'pg_woocommerce'); ?></p> </div>

          <div id="button-return" class="hide">
            <p>
              <a class="purchase-button" href="<?php echo get_permalink( wc_get_page_id( 'shop' ) ); ?>"><?php _e( 'Return to Store', 'pg_woocommerce' ) ?></a>
            </p>
          </div>

          <script src="https://cdn.paymentez.com/ccapi/sdk/payment_checkout_stable.min.js"></script>

          <button id="checkout-button" class="js-payment-checkout"><?php _e('Pay With Card', 'pg_woocommerce'); ?></button>

          <div id="order_data" class="hide">
            <?php echo json_encode($order_data); ?>
          </div>

          <script id="woocommerce_checkout_pg"
                  webhook_p="<?php echo $webhook_p; ?>"
                  app_key="<?php echo $this->app_key_client; ?>"
                  app_code="<?php echo $this->app_code_client; ?>"
                  checkout_language="<?php echo $this->checkout_language; ?>"
                  environment="<?php echo $this->environment; ?>"
                  installments_type="<?php echo $this->installments_type; ?>"
                  src="<?php echo $checkout; ?>">
          </script>
        <?php
      }

      /**
  		 * Process the payment and return the result
  		 *
  		 * @param int $orderId
  		 * @return array
  		 */
      public function process_payment($orderId) {
          $order = new WC_Order($orderId);
					WC()->cart->empty_cart();
          return array(
              'result' => 'success',
              'redirect' => $order->get_checkout_payment_url(true)
          );
      }
    }
  }
}

function add_pg_woocommerce_plugin( $methods ) {
    $methods[] = 'PG_WC_Plugin';
    return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_pg_woocommerce_plugin' );
