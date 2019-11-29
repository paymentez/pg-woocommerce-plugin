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

// Creación de la base de datos
if (!function_exists('db_paymentez_plugin')) {
  function db_paymentez_plugin() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'paymentez_plugin';

    if ($wpdb->get_var('SHOW TABLES LIKES ' . $table_name) != $table_name) {
      $sql = 'CREATE TABLE ' . $table_name . ' (
             id integer(9) unsigned NOT NULL AUTO_INCREMENT,
             Status varchar(50) NOT NULL,
             Comments varchar(50) NOT NULL,
             description text(500) NOT NULL,
             OrdenId int(9) NOT NULL,
             Transaction_Code varchar(50) NOT NULL,
             PRIMARY KEY  (id)
             );';
      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql);
    }
  }
}

register_activation_hook(__FILE__, 'db_paymentez_plugin');

function pg_woocommerce_plugin() {
  class WC_Gateway_Paymentez extends WC_Payment_Gateway {
    public function __construct() {
      # $this->has_fields = true;
      $this->id = 'pg_woocommerce';
      $this->icon = apply_filters('woocomerce_paymentez_icon', plugins_url('/imgs/paymentezcheck.png', __FILE__));
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
      add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));

      add_action('woocommerce_receipt_paymentez', array(&$this, 'receipt_page'));
    }

    public function init_form_fields() {
      $this->form_fields = array (
        'enabled' => array(
            'title' => __( 'Enable/Disable', 'pg_woocommerce' ),
            'type' => 'checkbox',
            'label' => __( 'Enable Paymentez Gateway', 'pg_woocommerce' ),
            'default' => 'yes'
        ),
        'title' => array(
            'title' => __( 'Title', 'pg_woocommerce' ),
            'type' => 'text',
            'description' => __( 'This controls the title which the user sees during checkout.', 'pg_woocommerce' ),
            'default' => __( 'Paymentez Gateway', 'pg_woocommerce' ),
            'desc_tip' => true,
        ),
        'description' => array(
            'title' => __( 'Customer Message', 'pg_woocommerce' ),
            'type' => 'textarea',
            'default' => 'Paymentez is a complete solution for online payments. Safe, easy and fast.'
        ),
        'app_code_client' => array(
        'title' => __('App Code Client', 'pg_woocommerce'),
        'type' => 'text',
        'description' => __('Unique identifier in Paymentez.', 'pg_woocommerce')
        ),
        'app_key_client' => array(
            'title' => __('App Key Client', 'pg_woocommerce'),
            'type' => 'text',
            'description' => __('Key used to encrypt communication with Paymentez.', 'pg_woocommerce')
        ),
        'app_code_server' => array(
            'title' => __('App Code Server', 'pg_woocommerce'),
            'type' => 'text',
            'description' => __('Unique identifier in Paymentez Server.', 'pg_woocommerce')
        ),
        'app_key_server' => array(
            'title' => __('App Key Server', 'pg_woocommerce'),
            'type' => 'text',
            'description' => __('Key used for reverse communication with Paymentez Server.', 'pg_woocommerce')
        )
      );
    }

    function admin_options() {
      ?>
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
      $variableTimestamp = strtotime($fecha_actual);
      $subtotal = number_format(($order->get_subtotal()), 2, '.', '');
      $vat = number_format(($order->get_total_tax()), 2, '.', '');
      $taxReturnBase = number_format(($amount - $vat), 2, '.', '');
      $tax_percentage = $this->impuesto_pay;
      if ($vat == 0) $taxReturnBase = 0;
      if ($vat == 0) $tax_percentage = 0;
      if (is_null($order_data['customer_id']) or empty($order_data['customer_id'])) {
          $uid = $orderId;
      } else {
          $uid = $order_data['customer_id'];
      }
      $token = 'application_code=' . $this->app_code_client . '&dev_reference=' . $orderId . '&product_amount=' . $amount . '&product_code=' . $sku . '&product_description=' . urlencode($description) . '&uid=' . $uid . '&vat=' . $vat . '&' . $variableTimestamp . '&' . $this->app_key_client;
      $signature = hash('sha256', $token);
      $parametersArgs = array(
        'app_code' => $this->app_code_client,
        'credito' => $credito,
        'purchase_order_id' => $orderId,
        'purchase_description' => $description,
        'purchase_amount' => $amount,
        'subtotal' => $subtotal,
        'purchase_tax' => $vat,
        'purchase_returnbase' => $taxReturnBase,
        'purchase_tax_percentage' => $tax_percentage,
        'purchase_signature' => $signature,
        'token' => $token,
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
        'timestamp' => $variableTimestamp,
        'productos' => $prod,
        'taxable_amount' => $taxable_amount,
      );

      return $parametersArgs;

    }

    public function generate_paymentez_form($orderId) {
      ?>
      <link rel="stylesheet" type="text/css" href="https://cdn.paymentez.com/checkout/1.0.1/paymentez-checkout.min.css" media="all">
      <script src="https://cdn.paymentez.com/checkout/1.0.1/paymentez-checkout.min.js" charset="UTF-8"></script>
      <?php
    }
  }
}

function add_pg_woocommerce_plugin( $methods ) {
    $methods[] = 'WC_Gateway_Paymentez';
    return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_pg_woocommerce_plugin' );
