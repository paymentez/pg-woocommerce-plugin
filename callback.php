<?php

require_once('../../../wp-load.php');
require( dirname( __FILE__ ) . '/database_helper.php' );

$requestBody = file_get_contents('php://input');
$requestBodyJs = json_decode($requestBody, true);

$status = $requestBodyJs["transaction"]['status'];
$status_detail = $requestBodyJs["transaction"]['status_detail'];
$transaction_id = $requestBodyJs["transaction"]['id'];
$authorization_code = $requestBodyJs["transaction"]['authorization_code'];
$response_description = $requestBodyJs["transaction"]['order_description'];
$dev_reference = $requestBodyJs["transaction"]['dev_reference'];

$detailPayment = array(
  2 => "Paid partially",
  3 => "Paid",
  6 => "Fraud",
  7 => "Refund",
  8 => "Chargeback",
  9 => "Rejected by carrier",
  10 => "System error",
  11 => "Paymentez fraud",
  12 => "Paymentez blacklist",
  16 => "Rejected by our fraud control system",
  19 => "Rejected by invalid data",
  20 => "Rejected by bank"
);

global $woocommerce;
$order = new WC_Order($dev_reference);
$statusOrder = $order->get_status();

$credito = get_post_meta($order->id, '_billing_customer_dni', true);
update_post_meta($order->id, '_transaction_id', $transaction_id);

if (!in_array($statusOrder, ['completed', 'cancelled', 'failed'])) {
    $description = "Respuesta Paymentez: Status: " . $status_detail .
                " | Status_detail: " . $detailPayment[$status_detail] .
                " | Dev_Reference: " . $dev_reference .
                " | Authorization_Code: " . $authorization_code .
                " | Response_Description: " . $response_description .
                " | Transaction_Code: " . $transaction_id;

    if ($status == 1 || $status == 'success') {
        $comments = "Pago exitoso";
        $order->update_status('completed');
        $order->reduce_order_stock();
        $woocommerce->cart->empty_cart();
        $order->add_order_note('Su pago se ha efectuado Satisfactoriamente. Código Transacción: ' . $transaction_id . ' y su Código de Autorización es: ' . $authorization_code);
        $mensaje = 'Su pago se ha efectuado Satisfactoriamente';

        WC_Paymentez_Database_Helper::insert_data($status, $comments, $description, $dev_reference, $transaction_id);
        $statusOrder = $order->get_status();

        if (!headers_sent()) {
            header("HTTP/1.0 200 confirmado");
        }
    } else {
        if ($statusOrder != 'refunded') {
            // Marca la orden como completa automáticamente
            $order->update_status('failed');
            $woocommerce->cart->empty_cart();
            //$order->add_order_note( __( 'Error mientras se procesaba el pago.', 'paymentez' ) );
            $comments = "Pago fallido";
            $statusOrder = $order->get_status();
            WC_Paymentez_Database_Helper::insert_data($status, $comments, $description, $dev_reference, $transaction_id);

            if (!headers_sent()) {
                header("HTTP/1.0 204 confirmado");
            }
        } else {
            $comments = "Se confirma reverso";
            WC_Paymentez_Database_Helper::insert_data($status, $comments, $description, $dev_reference, $transaction_id);
            $order->update_status('refunded');

            if (!headers_sent()) {
                header("HTTP/1.0 204 confirmado");
            }
        }
    }
  } else {

    $description = "Respuesta Paymentez: Status: " . $status_detail . " | Dev_Reference: " . $dev_reference . " | Transaction_Code: " . $transaction_id;

    $comments = "Se confirma transacción";
    $order->payment_complete($transaction_id);
    $order->update_status('completed');
    WC_Paymentez_Database_Helper::insert_data($status, $comments, $description, $dev_reference, $transaction_id);

    if (!headers_sent()) {
        header("HTTP/1.0 204 confirmado");
    }
}

?>
