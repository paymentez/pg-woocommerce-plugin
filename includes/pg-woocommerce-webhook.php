<?php

require_once('../../../../wp-load.php');
require_once( dirname( __FILE__ ) . '/pg-woocommerce-helper.php' );

$requestBody = file_get_contents('php://input');
$requestBodyJs = json_decode($requestBody, true);

$status = $requestBodyJs["transaction"]['status'];
$status_detail = $requestBodyJs["transaction"]['status_detail'];
$transaction_id = $requestBodyJs["transaction"]['id'];
$authorization_code = $requestBodyJs["transaction"]['authorization_code'];
$response_description = $requestBodyJs["transaction"]['order_description'];
$dev_reference = $requestBodyJs["transaction"]['dev_reference'];
$paymentez_message = $requestBodyJs["transaction"]['message'];

$detailPayment = array(
  1  => "Verification required",
  2  => "Paid partially",
  3  => "Paid",
  6  => "Fraud",
  7  => "Refund",
  8  => "Chargeback",
  9  => "Rejected by carrier",
  10 => "System error",
  11 => "Paymentez fraud",
  12 => "Paymentez blacklist",
  13 => "Time tolerance",
  14 => "Expired by Paymentez",
  19 => "Invalid Authorization Code",
  20 => "Authorization code expired",
  29 => "Annulled",
  30 => "Transaction seated"
);

global $woocommerce;
$order = new WC_Order($dev_reference);
$statusOrder = $order->get_status();

$credito = get_post_meta($order->id, '_billing_customer_dni', true);
update_post_meta($order->id, '_transaction_id', $transaction_id);

if (!in_array($statusOrder, ['completed', 'cancelled', 'refunded'])) {
    $description = __("Paymentez Response: Status: ", "pg_woocommerce") . $status_detail .
                   __(" | Status_detail: ", "pg_woocommerce") . $detailPayment[$status_detail] .
                   __(" | Dev_Reference: ", "pg_woocommerce") . $dev_reference .
                   __(" | Authorization_Code: ", "pg_woocommerce") . $authorization_code .
                   __(" | Response_Description: ", "pg_woocommerce") . $response_description .
                   __(" | Transaction_Code: ", "pg_woocommerce") . $transaction_id;

    if ($status == 'success') {
      $comments = __("Successful Payment", "pg_woocommerce");
      $order->update_status('completed');
      $order->reduce_order_stock();
      $woocommerce->cart->empty_cart();
      $order->add_order_note( __('Your payment has been made successfully. Transaction Code: ', 'pg_woocommerce') . $transaction_id . __(' and its Authorization Code is: ', 'pg_woocommerce') . $authorization_code);

    } elseif ($status == 'failure') {
      $comments = __("Payment Failed", "pg_woocommerce");
      $order->update_status('failed');
      $order->add_order_note( __('Your payment has failed. Transaction Code: ', 'pg_woocommerce') . $transaction_id . __(' the reason is: ', 'pg_woocommerce') . $paymentez_message);

    } elseif ($status == 'pending') {
      $comments = __("Pending Payment", "pg_woocommerce");
      $order->update_status('on-hold');
      $order->reduce_order_stock();
      $woocommerce->cart->empty_cart();
      $order->add_order_note( __('Your payment is pending. Transaction Code: ', 'pg_woocommerce') . $transaction_id);

    } else {
      $comments = __("Failed Payment", "pg_woocommerce");
      $order->add_order_note( __('The payment fail.: ', 'pg_woocommerce') );
    }
}

WC_Paymentez_Database_Helper::insert_data($status, $comments, $description, $dev_reference, $transaction_id);
