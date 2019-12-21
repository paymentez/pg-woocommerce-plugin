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

add_action( 'plugins_loaded', 'pg_woocommerce_plugin' );

include( dirname( __FILE__ ) . '/includes/pg-woocommerce-helper.php' );
register_activation_hook( __FILE__, array( 'WC_Paymentez_Database_Helper', 'create_database' ) );
register_deactivation_hook( __FILE__, array( 'WC_Paymentez_Database_Helper', 'delete_database' ) );

require( dirname( __FILE__ ) . '/includes/pg-woocommerce-refund.php' );

load_plugin_textdomain( 'pg_woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

define("PG_DOMAIN", "paymentez.com");
define("PG_REFUND", "/v2/transaction/refund/");

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
    class WC_Gateway_Paymentez extends WC_Payment_Gateway {
      public function __construct() {
        # $this->has_fields = true;
        $this->id = 'pg_woocommerce';
        $this->icon = apply_filters('woocomerce_paymentez_icon', plugins_url('/assets/imgs/paymentezcheck.png', __FILE__));
        $this->method_title = 'Paymentez Plugin';
        $this->method_description = __('This module is a solution that allows WooCommerce users to easily process credit card payments.', 'pg_woocommerce');

        $this->init_settings();
        $this->init_form_fields();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        $this->checkout_language = $this->get_option('checkout_language');
        $this->enviroment = $this->get_option('staging');

        $this->app_code_client = $this->get_option('app_code_client');
        $this->app_key_client = $this->get_option('app_key_client');
        $this->app_code_server = $this->get_option('app_code_server');
        $this->app_key_server = $this->get_option('app_key_server');

        // Para guardar sus opciones, simplemente tiene que conectar la función process_admin_options en su constructor.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));

        add_action('woocommerce_receipt_pg_woocommerce', array(&$this, 'receipt_page'));
      }

      public function init_form_fields() {
        $this->form_fields = require( dirname( __FILE__ ) . '/includes/admin/paymentez-settings.php' );
      }

      function admin_options() {
        $logo = plugins_url('/assets/imgs/paymentez.png', __FILE__);
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
        echo $this->generate_paymentez_form($order);
      }

      // TODO: Reposicionar la function get_params_post en otro archivo
      public function get_params_post($orderId) {
        $order = new WC_Order($orderId);
        $order_data = $order->get_data();
        $amount = $order_data['total'];
        $products = $order->get_items();
        $description = "";
        foreach ($products as $product) {
          $description .= $product['name'] . ',';
        }
        $subtotal = number_format(($order->get_subtotal()), 2, '.', '');
        $vat = number_format(($order->get_total_tax()), 2, '.', '');
        if (is_null($order_data['customer_id']) or empty($order_data['customer_id'])) {
            $uid = $orderId;
        } else {
            $uid = $order_data['customer_id'];
        }
        $parametersArgs = array(
          'purchase_order_id'    => $orderId,
          'purchase_amount'      => $amount,
          'purchase_description' => $description,
          'customer_phone'       => $order_data['billing']['phone'],
          'customer_email'       => $order_data['billing']['email'],
          'user_id'              => $uid,
          'vat'                  => $vat
        );

        return $parametersArgs;
      }

      public function generate_paymentez_form($orderId) {
        $webhook_p = plugins_url('/includes/pg-woocommerce-webhook.php', __FILE__);
        $css = plugins_url('/assets/css/styles.css', __FILE__);
        $checkout = plugins_url('/assets/js/paymentez_checkout.js', __FILE__);
        $orderData = $this->get_params_post($orderId);
        $orderDataJSON = json_encode($orderData)
        ?>
          <link rel="stylesheet" type="text/css" href="<?php echo $css; ?>">

          <div id="mensajeSucccess" class="hide"> <p class="alert alert-success" ><?php _e('Your payment has been made successfully. Thank you for your purchase.', 'pg_woocommerce'); ?></p> </div>
          <div id="mensajeFailed" class="hide"> <p class="alert alert-warning"><?php _e('An error occurred while processing your payment and could not be made. Try another Credit Card.', 'pg_woocommerce'); ?></p> </div>

          <div id="buttonreturn" class="hide">
            <p>
              <a class="btn-tienda" href="<?php echo get_permalink( wc_get_page_id( 'shop' ) ); ?>"><?php _e( 'Return to Store', 'woocommerce' ) ?></a>
            </p>
          </div>

          <script src="https://cdn.paymentez.com/checkout/1.0.1/paymentez-checkout.min.js"></script>

          <button class="js-paymentez-checkout"><?php _e('Purchase', 'pg_woocommerce'); ?></button>

          <div id="orderDataJSON" class="hide">
            <?php echo $orderDataJSON; ?>
          </div>

          <script id="woocommerce_checkout_pg" webhook_p="<?php echo $webhook_p; ?>"
            app-key="<?php echo $this->app_key_client; ?>"
            app-code="<?php echo $this->app_code_client; ?>"
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
    $methods[] = 'WC_Gateway_Paymentez';
    return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_pg_woocommerce_plugin' );
