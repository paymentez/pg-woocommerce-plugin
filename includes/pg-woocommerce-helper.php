<?php
global $wpdb;

define("TABLE_NAME", $wpdb->prefix . 'pg_wc_plugin');

/**
 *
 */
class PG_WC_Helper
{
  public static function create_table() {

    if ($wpdb->get_var('SHOW TABLES LIKES ' .TABLE_NAME) != TABLE_NAME) {
        $sql = 'CREATE TABLE '.TABLE_NAME.' (
               id integer(9) unsigned NOT NULL AUTO_INCREMENT,
               status varchar(50) NOT NULL,
               comments varchar(50) NOT NULL,
               description text(500) NOT NULL,
               order_id int(9) NOT NULL,
               pg_transaction_id varchar(50) NOT NULL,
               PRIMARY KEY  (id)
               );';
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
      }
  }

  /**
   *
   */
  public static function delete_table() {
    $sql = "DROP TABLE IF EXISTS ".TABLE_NAME;
    require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
    $wpdb->query($sql);
  }

  /**
   *
   */
  public static function insert_data($status, $comments, $description, $dev_reference, $transaction_id) {
    $wpdb->insert(
      TABLE_NAME,
      array(
        'id'                => $id,
        'status'            => $status,
        'comments'          => $comments,
        'description'       => $description,
        'order_id'          => $dev_reference,
        'pg_transaction_id' => $transaction_id
      ),
      array(
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s'
      )
    );
  }

  /**
   *
   */
  public static function select_order($order_id) {
    $myrows = $wpdb->get_results("SELECT * FROM ".TABLE_NAME." where order_id = '$order_id' ", OBJECT);

    foreach ($myrows as $campos) {
      $transactionCode = $campos->Transaction_Code;
    }
    return $transactionCode;
  }

  /**
   *
   */
  public static function get_checkout_params($order) {

    $order_data = $order->get_data();

    $description = "";
    foreach ($order->get_items() as $product) {
      $description .= $product['name'] . ',';
    }

    if (is_null($order_data['customer_id']) or empty($order_data['customer_id'])) {
        $uid = $orderId;
    } else {
        $uid = $order_data['customer_id'];
    }

    $vat = number_format(($order->get_total_tax()), 2, '.', '');

    $parametersArgs = array(
      'purchase_order_id'    => $order->get_id(),
      'purchase_amount'      => $order_data['total'],
      'purchase_description' => $description,
      'customer_phone'       => $order_data['billing']['phone'],
      'customer_email'       => $order_data['billing']['email'],
      'user_id'              => $uid,
      'vat'                  => $vat
    );

    return json_encode($parametersArgs);
  }
}
