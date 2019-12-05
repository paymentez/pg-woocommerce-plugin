<?php

return array (
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
