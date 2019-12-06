<?php

/*
Plugin Name: Paymentez WooCommerce Plugin
Plugin URI: http://www.paymentez.com
Description: This module is a solution that allows WooCommerce users to easily process credit card payments.
Version: 1.0
Author: Paymentez
Author URI: http://www.paymentez.com
License: A "Slug" license name e.g. GPL2
*/

add_action( 'plugins_loaded', 'pg_woocommerce_plugin' );

// Creación de la base de datos si no existe
if (!function_exists('db_paymentez_plugin')) {
  function db_paymentez_plugin() {
    require( dirname( __FILE__ ) . '/database_helper.php' );
    echo WC_Paymentez_Database_Helper::create_database();
  }
}

register_activation_hook(__FILE__, 'db_paymentez_plugin');

function pg_woocommerce_plugin() {
  class WC_Gateway_Paymentez extends WC_Payment_Gateway {
    public function __construct() {
      # $this->has_fields = true;
      $this->id = 'pg_woocommerce';
      $this->icon = apply_filters('woocomerce_paymentez_icon', plugins_url('/assets/imgs/paymentezcheck.png', __FILE__));
      $this->method_title = 'Paymentez Plugin';
      $this->method_description = 'This module is a solution that allows WooCommerce users to easily process credit card payments.';

      $this->init_settings();
      $this->init_form_fields();

      $this->title = $this->get_option('title');
      $this->description = $this->get_option('description');

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

    public function get_params_post($orderId) {
      $order = new WC_Order($orderId);
      $order_data = $order->get_data();
      $currency = get_woocommerce_currency();
      $amount = $order_data['total'];
      $credito = get_post_meta($orderId, '_billing_customer_dni', true);
      $products = $order->get_items();
      $description = '';
      $taxable_amount = 0.00;
      foreach ($products as $product) {
        $description .= $product['name'] . ',';
        if ($product['subtotal_tax'] != 0 && $product['subtotal_tax'] != '') {
            $taxable_amount = number_format(($product['subtotal']), 2, '.', '');
        }
      }
      foreach ($order->get_items() as $item_key => $item) {
        $prod = $order->get_product_from_item($item);
        $sku = $prod->get_id();
      }
      $fecha_actual = date('Y-m-d');
      $subtotal = number_format(($order->get_subtotal()), 2, '.', '');
      $vat = number_format(($order->get_total_tax()), 2, '.', '');
      $taxReturnBase = number_format(($amount - $vat), 2, '.', '');
      if ($vat == 0) $taxReturnBase = 0;
      if ($vat == 0) $tax_percentage = 0;
      if (is_null($order_data['customer_id']) or empty($order_data['customer_id'])) {
          $uid = $orderId;
      } else {
          $uid = $order_data['customer_id'];
      }
      $parametersArgs = array(
        'purchase_order_id' => $orderId,
        'purchase_description' => $description,
        'purchase_amount' => $amount,
        'subtotal' => $subtotal,
        'purchase_currency' => $currency,
        'customer_firstname' => $order_data['billing']['first_name'],
        'customer_lastname' => $order_data['billing']['last_name'],
        'customer_phone' => $order_data['billing']['phone'],
        'customer_email' => $order_data['billing']['email'],
        'address_street' => $order_data['billing']['address_1'],
        'address_city' => $order_data['billing']['city'],
        'address_country' => $order_data['billing']['country'],
        'address_state' => $order_data['billing']['state'],
        'user_id' => $uid,
        'cod_prod' => $sku,
        'productos' => $prod,
        'taxable_amount' => $taxable_amount,
      );

      return $parametersArgs;

    }

    public function generate_paymentez_form($orderId) {
      $callback = plugins_url('/callback.php', __FILE__);
      $css = plugins_url('/assets/css/styles.css', __FILE__);
      $checkout = plugins_url('/assets/js/paymentez_checkout.js', __FILE__);
      $orderData = $this->get_params_post($orderId);
      $orderDataJSON = json_encode($orderData)
      ?>
        <link rel="stylesheet" type="text/css" href="<?php echo $css; ?>">

        <div id="messagetwo" class="hide"> <p class="alert alert-success" > Su pago se ha realizado con éxito. Muchas gracias por su compra </p> </div>
        <div id="messagetres" class="hide"> <p class="alert alert-warning"> Ocurrió un error al comprar y su pago no se pudo realizar. Intente con otra Tarjeta de Crédito </p> </div>

        <div id="buttonreturn" class="hide">
          <p>
            <a class="btn-tienda" href="<?php echo get_permalink( wc_get_page_id( 'shop' ) ); ?>"><?php _e( 'Return to Store', 'woocommerce' ) ?></a>
          </p>
        </div>

        <script src="https://cdn.paymentez.com/checkout/1.0.1/paymentez-checkout.min.js"></script>

        <button class="js-paymentez-checkout">Purchazze</button>

        <div id="orderDataJSON" class="hide">
          <?php echo $orderDataJSON; ?>
        </div>

        <script id="checkout_php" callback="<?php echo $callback; ?>"
          app-key="<?php echo $this->app_key_client; ?>"
          app-code="<?php echo $this->app_code_client; ?>"
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

function add_pg_woocommerce_plugin( $methods ) {
    $methods[] = 'WC_Gateway_Paymentez';
    return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_pg_woocommerce_plugin' );
