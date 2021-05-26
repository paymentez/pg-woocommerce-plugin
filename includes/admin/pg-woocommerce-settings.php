<?php

return array (
    'staging' => array(
        'title' => __( 'Staging Environment', 'pg_woocommerce' ),
        'type' => 'checkbox',
        'label' => __( 'Use staging environment in ', 'pg_woocommerce' ).PG_FLAVOR.'.',
        'default' => 'yes'
    ),
    'enable_card' => array(
        'title' => __( 'Enable Card Payment', 'pg_woocommerce' ),
        'type' => 'checkbox',
        'label' => __( 'If selected, card payment can be used to pay.', 'pg_woocommerce' ),
        'default' => 'no'
    ),
    'enable_ltp' => array(
        'title' => __( 'Enable LinkToPay', 'pg_woocommerce' ),
        'type' => 'checkbox',
        'label' => __( 'If selected, LinkToPay(Bank transfer, cash) can be used to pay.', 'pg_woocommerce' ),
        'default' => 'no'
    ),
    'ltp_expiration' => array(
        'title' => __( 'Expiration Days for LinkToPay', 'pg_woocommerce' ),
        'type' => 'number',
        'description' => __( 'This value controls the number of days that the generated LinkToPay will be available to pay.', 'pg_woocommerce' ),
        'default' => 1,
        'desc_tip' => true,
    ),
    'title' => array(
        'title' => __( 'Title', 'pg_woocommerce' ),
        'type' => 'text',
        'description' => __( 'This controls the title which the user sees during checkout page.', 'pg_woocommerce' ),
        'default' => PG_FLAVOR.' Gateway',
        'desc_tip' => true,
    ),
    'description' => array(
        'title' => __( 'Customer Message', 'pg_woocommerce' ),
        'type' => 'textarea',
        'description' => __( 'This controls the message which the user sees during checkout page.', 'pg_woocommerce' ),
        'default' => PG_FLAVOR.__(' is a complete solution for online payments. Safe, easy and fast.', 'pg_woocommerce
        ')
    ),
    'card_button_text' => array(
        'title' => __( 'Card Button Text', 'pg_woocommerce' ),
        'type' => 'text',
        'description' => __( 'This controls the text that the user sees in the card payment button.', 'pg_woocommerce' ),
        'default' => __('Pay With Card', 'pg_woocommerce'),
        'desc_tip' => true,
    ),
    'ltp_button_text' => array(
        'title' => __( 'LinkToPay Button Text', 'pg_woocommerce' ),
        'type' => 'text',
        'description' => __( 'This controls the text that the user sees in the LinkToPay button.', 'pg_woocommerce' ),
        'default' =>  __( 'Pay with Cash/Bank Transfer', 'pg_woocommerce' ),
        'desc_tip' => true,
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
    'enable_installments' => array(
        'title' => __('Enable Installments', 'pg_woocommerce'),
        'type' => 'checkbox',
        'default' => 'no',
        'label' => __('If selected, the installments options will be showed on the payment screen (Only on card payment).', 'pg_woocommerce')
    ),
    'app_code_client' => array(
        'title' => __('App Code Client', 'pg_woocommerce'),
        'type' => 'text',
        'description' => __('Unique commerce identifier in ', 'pg_woocommerce').PG_FLAVOR.'.'
    ),
    'app_key_client' => array(
        'title' => __('App Key Client', 'pg_woocommerce'),
        'type' => 'text',
        'description' => __('Key used to encrypt communication with ', 'pg_woocommerce').PG_FLAVOR.'.'
    ),
    'app_code_server' => array(
        'title' => __('App Code Server', 'pg_woocommerce'),
        'type' => 'text',
        'description' => __('Unique commerce identifier to perform admin actions on ', 'pg_woocommerce').PG_FLAVOR.'.'
    ),
    'app_key_server' => array(
        'title' => __('App Key Server', 'pg_woocommerce'),
        'type' => 'text',
        'description' => __('Key used to encrypt admin communication with ', 'pg_woocommerce').PG_FLAVOR.'.'
    )
);
