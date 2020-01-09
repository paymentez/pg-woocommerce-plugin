<?php
/**
 *
 */
require_once( dirname( __DIR__ ) . '/pg-woocommerce-plugin.php' );
require_once( dirname( __FILE__ ) . '/pg-woocommerce-helper.php' );

class WCPaymentezRefund
{
  function refund($order_id, $refund_id)
  {
    $refundObj = new WCGatewayPaymentez();
    $app_code_server = $refundObj->app_code_server;
    $app_key_server = $refundObj->app_key_server;
    $enviroment = $refundObj->enviroment;

    $fecha_actual = time();
    $variable_timestamp = (string)($fecha_actual);
    $uniq_token_string = $app_key_server . $variable_timestamp;
    $uniq_token_hash = hash('sha256', $uniq_token_string);
    $auth_token = base64_encode($app_code_server . ';' . $variable_timestamp . ';' . $uniq_token_hash);

    $urlrefund = ($enviroment == 'yes') ? 'https://ccapi-stg.'.PG_DOMAIN.PG_REFUND : 'https://ccapi.'.PG_DOMAIN.PG_REFUND ;

    $transaction_code = WCPaymentezDatabaseHelper::selectOrder($order_id);
    $data = array(
        'id' => $transaction_code
    );
    $payload = json_encode(array("transaction" => $data));

    $ch = curl_init($urlrefund);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, ($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type:application/json',
        'Auth-Token:' . $auth_token));

    $response = curl_exec($ch);
    $getresponse = json_decode($response, true);
    $status = $getresponse['status'];

    curl_close($ch);

    // TODO: Definir estas dos variables bien
    $comments = "Refund Completed";
    $description = "Refund ID: ". $refund_id;

    WCPaymentezDatabaseHelper::insertData($status, $comments, $description, $order_id, $transaction_code);
  }
}
