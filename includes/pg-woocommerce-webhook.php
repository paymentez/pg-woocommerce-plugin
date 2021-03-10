<?php
header("HTTP/1.0 204 transaction_id already received");
date_default_timezone_set("UTC");
require_once('../../../../wp-load.php');
require_once( dirname( __FILE__ ) . '/pg-woocommerce-helper.php' );
require_once( dirname( __DIR__ ) . '/pg-woocommerce-plugin.php' );

$requestBody = file_get_contents('php://input');
$requestBodyJs = json_decode($requestBody, true);

$status = $requestBodyJs["transaction"]['status'];
$status_detail = $requestBodyJs["transaction"]['status_detail'];
$transaction_id = $requestBodyJs["transaction"]['id'];
$authorization_code = $requestBodyJs["transaction"]['authorization_code'];
$dev_reference = $requestBodyJs["transaction"]['dev_reference'];
$paymentez_message = $requestBodyJs["transaction"]['message'];
$paymentezStoken = $requestBodyJs["transaction"]['stoken'];
$payment_date = strtotime($requestBodyJs["transaction"]['payment_date']);
$actual_date = strtotime(date("Y-m-d H:i:s",time()));
$time_difference = ceil(($actual_date - $payment_date)/60);

if ($time_difference > 3 && !$paymentezStoken) {
  header("HTTP/1.0 400 time error");
}

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
  30 => "Transaction seated",
  31 => "Waiting for OTP",
  32 => "OTP successfully validated",
  33 => "OTP not validated",
  35 => "3DS method requested, waiting to continue",
  36 => "3DS challenge requested, waiting CRES",
  37 => "Rejected by 3DS"
);

global $woocommerce;
$order = new WC_Order($dev_reference);
$statusOrder = $order->get_status();

update_post_meta($order->id, '_transaction_id', $transaction_id);

if ($paymentezStoken) {
  $webhookObj = new PG_WC_Plugin();
  $app_code_client = $webhookObj->app_code_client;
  $app_key_client = $webhookObj->app_key_client;
  $userId = $requestBodyJs["user"]["id"];
  $stoken = md5($transaction_id ."_". $app_code_client ."_". $userId ."_". $app_key_client);
  if ($stoken != $paymentezStoken) {
    header("HTTP/1.0 203 token error");
  } elseif ($status_detail == 8) {
      $description = $detailPayment[$status_detail];
      $comments = __("Payment Cancelled", "pg_woocommerce");
      $order->update_status('cancelled');
      $order->add_order_note( __('Your payment was cancelled. Transaction Code: ', 'pg_woocommerce') . $transaction_id . __(' the reason is chargeback. ', 'pg_woocommerce'));
  } elseif ($status_detail == 3 && $statusOrder == "completed") {
    header("HTTP/1.0 204 transaction_id already received");
  }
}

if (!in_array($statusOrder, ['completed', 'cancelled', 'refunded'])) {
    $description = __("Paymentez Response: Status: ", "pg_woocommerce") . $status_detail .
                   __(" | Status_detail: ", "pg_woocommerce") . $detailPayment[$status_detail] .
                   __(" | Dev_Reference: ", "pg_woocommerce") . $dev_reference .
                   __(" | Authorization_Code: ", "pg_woocommerce") . $authorization_code .
                   __(" | Transaction_Code: ", "pg_woocommerce") . $transaction_id;

    if ($status == 'success') {
      $comments = __("Successful Payment", "pg_woocommerce");
      $order->update_status('completed');
      $order->reduce_order_stock();
      $woocommerce->cart->empty_cart();
      $order->add_order_note( __('Your payment has been made successfully. Transaction Code: ', 'pg_woocommerce') . $transaction_id . __(' and its Authorization Code is: ', 'pg_woocommerce') . $authorization_code);

    } elseif ($status == 'failure' || $status == 'pending') {
      $comments = __("Payment Failed", "pg_woocommerce");
      $order->update_status('failed');
      $order->add_order_note( __('Your payment has failed. Transaction Code: ', 'pg_woocommerce') . $transaction_id . __(' the reason is: ', 'pg_woocommerce') . $paymentez_message);

    } else {
      $comments = __("Failed Payment", "pg_woocommerce");
      $order->add_order_note( __('The payment fails.: ', 'pg_woocommerce') );
    }
}

PG_WC_Helper::insert_data($status, $comments, $description, $dev_reference, $transaction_id);
