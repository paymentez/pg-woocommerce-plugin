<?php

/*
Plugin Name: Paymentez WooCommerce Plugin
Plugin URI: http://www.paymentez.com
Description: This module is a solution that allows WooCommerce users to easily process credit card payments.
Version: 1.0
Author: Paymentez
Author URI: http://www.paymentez.com
Text Domain: pg_woocommerce
Domain Path: /languages
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

define("FLAVOR", "Paymentez");
define("PG_DOMAIN", "paymentez.com");
define("PG_REFUND", "/v2/transaction/refund/");

add_action( 'plugins_loaded', 'pg_woocommerce_plugin' );

include( dirname( __FILE__ ) . '/includes/pg-woocommerce-helper.php' );
register_activation_hook( __FILE__, array( 'PG_WC_Helper', 'create_table' ) );
register_deactivation_hook( __FILE__, array( 'PG_WC_Helper', 'delete_table' ) );

require( dirname( __FILE__ ) . '/includes/pg-woocommerce-refund.php' );

load_plugin_textdomain( 'pg_woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

// TODO: Mover la function paymentez_woocommerce_order_refunded
// define the woocommerce_order_refunded callback
function paymentez_woocommerce_order_refunded($order_id, $refund_id) {
  $refund = new WC_Paymentez_Refund();
  $refund->refund($order_id);
}

// add the action
add_action( 'woocommerce_order_refunded', 'paymentez_woocommerce_order_refunded', 10, 2 );

if (!function_exists('pg_woocommerce_plugin')) {
  function pg_woocommerce_plugin() {
    class PG_WC_Plugin extends WC_Payment_Gateway {
      public function __construct() {
        $this->id                 = 'pg_woocommerce';
        $this->icon               = apply_filters('woocomerce_pg_icon', plugins_url('/assets/imgs/payment_check.png', __FILE__));
        $this->method_title       = FLAVOR.' Plugin';
        $this->method_description = __('This module is a solution developed by'. FLAVOR .'that allows WooCommerce users to easily process credit card payments.', 'pg_woocommerce');

        $this->init_settings();
        $this->init_form_fields();

        $this->title              = $this->get_option('title');
        $this->description        = $this->get_option('description');

        $this->checkout_language  = $this->get_option('checkout_language');
        $this->enviroment         = $this->get_option('staging');

        $this->app_code_client    = $this->get_option('app_code_client');
        $this->app_key_client     = $this->get_option('app_key_client');
        $this->app_code_server    = $this->get_option('app_code_server');
        $this->app_key_server     = $this->get_option('app_key_server');

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
        <h2><?php _e('Paymentez Gateway','pg_woocommerce'); ?></h2>
          <table class="form-table">
            <?php $this->generate_settings_html(); ?>
          </table>
        <?php
      }

      function receipt_page($order) {
        echo $this->generate_payment_form($order);
      }

      public function generate_payment_form($orderId) {
        $webhook_p = plugins_url('/includes/pg-woocommerce-webhook.php', __FILE__);
        $css = plugins_url('/assets/css/styles.css', __FILE__);
        $checkout = plugins_url('/assets/js/payment_checkout.js', __FILE__);
        $order = new WC_Order($orderId);
        $order_data = PG_WC_Helper::get_checkout_params($order);
        ?>
          <link rel="stylesheet" type="text/css" href="<?php echo $css; ?>">

          <div id="msj-succcess" class="hide"> <p class="alert alert-success" ><?php _e('Your payment has been made successfully. Thank you for your purchase.', 'pg_woocommerce'); ?></p> </div>
          <div id="msj-failed" class="hide"> <p class="alert alert-warning"><?php _e('An error occurred while processing your payment and could not be made. Try another Credit Card.', 'pg_woocommerce'); ?></p> </div>

          <div id="button-return" class="hide">
            <p>
              <a class="purchase-button" href="<?php echo get_permalink( wc_get_page_id( 'shop' ) ); ?>"><?php _e( 'Return to Store', 'woocommerce' ) ?></a>
            </p>
          </div>

          <script src="https://cdn.paymentez.com/ccapi/sdk/payment_checkout_stable.min.js"></script>

          <button class="js-payment-checkout"><?php _e('Purchase', 'pg_woocommerce'); ?></button>

          <div id="order_data" class="hide">
            <?php echo $order_data; ?>
          </div>

          <script id="woocommerce_checkout_pg" webhook_p="<?php echo $webhook_p; ?>"
            app_key="<?php echo $this->app_key_client; ?>"
            app_code="<?php echo $this->app_code_client; ?>"
            checkout_language="<?php echo $this->checkout_language; ?>"
            enviroment="<?php echo $this->enviroment; ?>"
            src="<?php echo $checkout; ?>">
          </script>
        <?php
      }

      public function process_payment($orderId) {
          $order = new WC_Order($orderId);
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
