<?php

require_once('../../../wp-load.php');

$requestBody = file_get_contents('php://input');
$requestBodyJs = json_decode($requestBody, true);

$status = $requestBodyJs["transaction"]['status'];

$status_detail = $requestBodyJs["transaction"]['status_detail'];

$transaction_id = $requestBodyJs["transaction"]['id'];

$authorization_code = $requestBodyJs["transaction"]['authorization_code'];

$response_description = $requestBodyJs["transaction"]['order_description'];

$dev_reference = $requestBodyJs["transaction"]['dev_reference'];

global $woocommerce;
$order = new WC_Order($dev_reference);

$credito = get_post_meta($order->id, '_billing_customer_dni', true);
update_post_meta($order->id, '_transaction_id', $transaction_id);

function insert_data($status, $comments, $description, $dev_reference, $transaction_id)
{

    $statusfinal = $status;
    $commentsfinal = $comments;
    $guardar = $description;
    $dev_reference = $dev_reference;
    $transaction_id = $transaction_id;


    global $wpdb;
    $table_name = $wpdb->prefix . 'paymentez_plugin';

    $wpdb->insert($table_name, array(
        'id' => $id,
        'Status' => $statusfinal,
        'Comments' => $commentsfinal,
        'description' => $guardar,
        'OrdenId' => $dev_reference,
        'Transaction_Code' => $transaction_id
    ), array(
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s')
    );

}

switch ($status_detail) {
    case 2:
        {
            $detailPayment = "Paid partially";
            break;
        }
    case 3:
        {
            $detailPayment = "Paid";
            break;
        }
    case 6:
        {
            $detailPayment = "Fraud";
            break;
        }
    case 7:
        {
            $detailPayment = "Refund";
            break;
        }
    case 8:
        {
            $detailPayment = "Chargeback";
            break;
        }
    case 9:
        {
            $detailPayment = "Rejected by carrier";
            break;
        }
    case 10:
        {
            $detailPayment = "System error";
            break;
        }
    case 11:
        {
            $detailPayment = "Paymentez fraud";
            break;
        }
    case 12:
        {
            $detailPayment = "Paymentez blacklist";
            break;
        }
    case 16:
        {
            $detailPayment = "Rejected by our fraud control system";
            break;
        }
    case 19:
        {
            $detailPayment = "Rejected by invalid data";
            break;
        }
    case 20:
        {
            $detailPayment = "Rejected by bank";
            break;
        }
}

$statusOrder = $order->get_status();

if ($statusOrder != 'completed' && $statusOrder != 'cancelled' && $statusOrder != 'failed') {

    $description = "Respuesta Paymentez: Status: " . $status_detail . " | Status_detail: " . $detailPayment . " | Dev_Reference: " . $dev_reference . " | Authorization_Code: " . $authorization_code . " | Response_Description: " . $response_description . " | Transaction_Code: " . $transaction_id;


    if ($status == 1 || $status == 'success') {


        $comments = "Pago exitoso";
        $order->update_status('completed');
        $order->reduce_order_stock();
        $woocommerce->cart->empty_cart();
        $order->add_order_note('Su pago se ha efectuado Satisfactoriamente. Pago ' . $tipo . '. Código Transacción: ' . $transaction_id . ' y su Código de Autorización es: ' . $authorization_code);
        $mensaje = 'Su pago se ha efectuado Satisfactoriamente';

        insert_data($status, $comments, $description, $dev_reference, $transaction_id);
        $statusOrder = $order->get_status();

        if (!headers_sent()) {
            header("HTTP/1.0 200 confirmado");
        }


    } else {

        $statusOrder = $order->get_status();

        if ($statusOrder != 'refunded') {

            // Marca la orden como completa automáticamente
            $order->update_status('failed');
            $woocommerce->cart->empty_cart();
            //$order->add_order_note( __( 'Error mientras se procesaba el pago.', 'paymentez' ) );
            $comments = "Pago fallido";
            $statusOrder = $order->get_status();
            insert_data($status, $comments, $description, $dev_reference, $transaction_id);

            if (!headers_sent()) {
                header("HTTP/1.0 204 confirmado");
            }


        } else {


            $comments = "Se confirma reverso";
            insert_data($status, $comments, $description, $dev_reference, $transaction_id);
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
    insert_data($status, $comments, $description, $dev_reference, $transaction_id);

    if (!headers_sent()) {
        header("HTTP/1.0 204 confirmado");
    }

}

?>
