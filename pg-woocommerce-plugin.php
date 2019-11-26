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
            'default' => 'Paymentez es una solución completa para pagos en línea. Segura, fácil y rápida.'
        ),
        'app_code_client' => array(
        'title' => __('App Code Client', 'pg_woocommerce'),
        'type' => 'text',
        'description' => __('Identificador único en Paymentez.', 'pg_woocommerce')
        ),
        'app_key_server' => array(
            'title' => __('App Key Client', 'pg_woocommerce'),
            'type' => 'text',
            'description' => __('Llave que sirve para encriptar la comunicación con Paymentez.', 'pg_woocommerce')
        ),
        'app_code_server' => array(
            'title' => __('App Code Server', 'pg_woocommerce'),
            'type' => 'text',
            'description' => __('Identificador único en Paymentez Server.', 'pg_woocommerce')
        ),
        'app_key_server' => array(
            'title' => __('App Key Server', 'pg_woocommerce'),
            'type' => 'text',
            'description' => __('Llave que sirve para la comunicación de reverso con Paymentez Server.', 'pg_woocommerce')
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
  }
}

function add_pg_woocommerce_plugin( $methods ) {
    $methods[] = 'WC_Gateway_Paymentez';
    return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_pg_woocommerce_plugin' );
