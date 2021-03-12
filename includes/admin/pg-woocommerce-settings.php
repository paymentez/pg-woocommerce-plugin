<?php

return array (
    'staging' => array(
        'title' => __( 'Staging Enviroment', 'pg_woocommerce' ),
        'type' => 'checkbox',
        'label' => __( 'Use staging environment in '.FLAVOR, 'pg_woocommerce' ),
        'default' => 'yes'
    ),
    'enable_ltp' => array(
        'title' => __( 'Enable LinkToPay', 'pg_woocommerce' ),
        'type' => 'checkbox',
        'label' => __( 'If selected, LinkToPay(Bank transfer, cash) can be used to pay.', 'pg_woocommerce' ),
        'default' => 'no'
    ),
    'title' => array(
        'title' => __( 'Title', 'pg_woocommerce' ),
        'type' => 'text',
        'description' => __( 'This controls the title which the user sees during checkout page.', 'pg_woocommerce' ),
        'default' => FLAVOR.' Gateway',
        'desc_tip' => true,
    ),
    'description' => array(
        'title' => __( 'Customer Message', 'pg_woocommerce' ),
        'type' => 'textarea',
        'description' => __( 'This controls the message which the user sees during checkout page.', 'pg_woocommerce' ),
        'default' => __(FLAVOR.' is a complete solution for online payments. Safe, easy and fast.', 'pg_woocommerce
        ')
    ),
    'checkout_language' => array(
      'title' => __('Checkout Language', 'pg_woocommerce'),
      'type' => 'select',
      'default' => 'en',
      'options' => array(
        'en' => 'EN',
        'es' => 'ES',
        'pt' => 'PT',
      ),
      'description' => __('User\'s preferred language for checkout window. English will be used by default.', 'pg_woocommerce')
    ),
    'installments_type' => array(
      'title' => __('Installments Type', 'pg_woocommerce'),
      'type' => 'select',
      'default' => -1,
      'options' => array(
        -1 => __('Disabled', 'pg_woocommerce'),
        0  => __('Enabled', 'pg_woocommerce'),
        1  => __('Revolving and deferred without interest (The bank will pay to the commerce the installment, month by month)(Ecuador)', 'pg_woocommerce'),
        2  => __('Deferred with interest (Ecuador)', 'pg_woocommerce'),
        3  => __('Deferred without interest (Ecuador)', 'pg_woocommerce'),
        7  => __('Deferred with interest and months of grace (Ecuador)', 'pg_woocommerce'),
        6  => __('Deferred without interest pay month by month (Ecuador)(Medianet)', 'pg_woocommerce'),
        9  => __('Deferred without interest and months of grace (Ecuador)', 'pg_woocommerce'),
        10 => __('Deferred without interest promotion bimonthly (Ecuador)(Medianet)', 'pg_woocommerce'),
        21 => __('For Diners Club exclusive, deferred with and without interest (Ecuador)', 'pg_woocommerce'),
        22 => __('For Diners Club exclusive, deferred with and without interest (Ecuador)', 'pg_woocommerce'),
        30 => __('Deferred with interest pay month by month (Ecuador)(Medianet)', 'pg_woocommerce'),
        50 => __('Deferred without interest promotions (Supermaxi)(Ecuador)(Medianet)', 'pg_woocommerce'),
        51 => __('Deferred with interest (Cuota fácil)(Ecuador)(Medianet)', 'pg_woocommerce'),
        52 => __('Without interest (Rendecion Produmillas)(Ecuador)(Medianet)', 'pg_woocommerce'),
        53 => __('Without interest sale with promotions (Ecuador)(Medianet)', 'pg_woocommerce'),
        70 => __('Deferred special without interest (Ecuador)(Medianet)', 'pg_woocommerce'),
        72 => __('Credit without interest (cte smax)(Ecuador)(Medianet)', 'pg_woocommerce'),
        73 => __('Special credit without interest (smax)(Ecuador)(Medianet)', 'pg_woocommerce'),
        74 => __('Prepay without interest (smax)(Ecuador)(Medianet)', 'pg_woocommerce'),
        75 => __('Defered credit without interest (smax)(Ecuador)(Medianet)', 'pg_woocommerce'),
        90 => __('Without interest with months of grace (Supermaxi)(Ecuador)(Medianet)', 'pg_woocommerce'),
      ),
      'description' => __('Select the installments type that will be enabled on the payment screen (Only on card payment).', 'pg_woocommerce')
    ),
    'app_code_client' => array(
      'title' => __('App Code Client', 'pg_woocommerce'),
      'type' => 'text',
      'description' => __('Unique commerce identifier in '.FLAVOR.' .', 'pg_woocommerce')
    ),
    'app_key_client' => array(
      'title' => __('App Key Client', 'pg_woocommerce'),
      'type' => 'text',
      'description' => __('Key used to encrypt communication with '.FLAVOR.' .', 'pg_woocommerce')
    ),
    'app_code_server' => array(
      'title' => __('App Code Server', 'pg_woocommerce'),
      'type' => 'text',
      'description' => __('Unique commerce identifier to perform admin actions on '.FLAVOR.' .', 'pg_woocommerce')
    ),
    'app_key_server' => array(
      'title' => __('App Key Server', 'pg_woocommerce'),
      'type' => 'text',
      'description' => __('Key used to encrypt admin communication with '.FLAVOR, 'pg_woocommerce')
    )
  );
