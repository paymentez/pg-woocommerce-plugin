<?php

/*
Plugin Name: Paymentez Woocommerce Plugin
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
        public function __construct(){
            $this->id = 'Paymentez Woocommerce Plugin';
            $this->method_title = 'Paymentez Plugin';
            $this->method_description = 'This module is a solution that allows WooCommerce users to easily process credit card payments.';
            $this->init_form_fields();
            $this->init_settings();

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'Paymentez' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable Paymentez Gateway', 'Paymentez' ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __( 'Title', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'Paymentez' ),
                    'default' => __( 'Paymentez Gateway', 'paymentez' ),
                    'desc_tip'      => true,
                ),
                'description' => array(
                    'title' => __( 'Customer Message', 'paymentez' ),
                    'type' => 'textarea',
                    'default' => 'Paymentez es una solución completa para pagos en línea. Segura, fácil y rápida.'
                )
            );
        }
    }

}

function add_pg_woocommerce_plugin( $methods ) {
    $methods[] = 'WC_Gateway_Paymentez';
    return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_pg_woocommerce_plugin' );

?>