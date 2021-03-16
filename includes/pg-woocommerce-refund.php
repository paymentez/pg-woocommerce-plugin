<?php
require_once( dirname( __DIR__ ) . '/pg-woocommerce-plugin.php' );
require_once( dirname( __FILE__ ) . '/pg-woocommerce-helper.php' );

/**
 *
 */
class WC_Payment_Refund_PG
{
  function refund($order_id, $amount)
  {
    $refundObj = new PG_WC_Plugin();

    $auth_token = PG_WC_Helper::generate_auth_token('server');

    $environment = $refundObj->environment;
    $urlrefund = ($environment == 'yes') ? 'https://ccapi-stg.'.PG_DOMAIN.PG_REFUND : 'https://ccapi.'.PG_DOMAIN.PG_REFUND ;

    $transactionCode = PG_WC_Helper::select_order($order_id);
    $data = array(
      'transaction' => array(
        'id' => $transactionCode,
      ),
      'order' => array(
        'amount' => (float)$amount
      ),
    );
    $payload = json_encode($data);

    $ch = curl_init($urlrefund);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, ($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Auth-Token:' . $auth_token));

    try {
      $response = curl_exec($ch);
      $getresponse = json_decode($response, true);
      $status = $getresponse['status'];
      $detail = $getresponse['detail'];

    } catch (Exception $e) {
      $status = 'error';
      $detail = $e->getMessage();
    }

    curl_close($ch);

    $success = ($status == 'success') ? true : false ;
    $comments = "Refund ".$status;

    PG_WC_Helper::insert_data($status, $comments, $detail, $order_id, $transactionCode);
    return array('status' => $status, 'transaction_id' => $transactionCode, 'success' => $success);
  }
}
