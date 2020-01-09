<?php
class WCPaymentezDatabaseHelper {
  public static function createDatabase() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'paymentez_plugin';

    if ($wpdb->get_var('SHOW TABLES LIKES ' . $table_name) != $table_name) {
        $sql = 'CREATE TABLE ' . $table_name . ' (
               id integer(9) unsigned NOT NULL AUTO_INCREMENT,
               Status varchar(50) NOT NULL,
               Comments varchar(50) NOT NULL,
               description text(500) NOT NULL,
               OrdenId int(9) NOT NULL,
               Transaction_Code varchar(50) NOT NULL,
               PRIMARY KEY  (id)
               );';
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
      }
  }

  public static function deleteDatabase() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'paymentez_plugin';
    $sql = "DROP TABLE IF EXISTS $table_name";
    require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
    $wpdb->query($sql);
  }

  public static function insertData($status, $comments, $description, $dev_reference, $transaction_id) {
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
            '%s'
          )
    );
  }

  public static function selectOrder($order_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'paymentez_plugin';
    $myrows = $wpdb->get_results("SELECT * FROM $table_name where OrdenId = '$order_id' ", OBJECT);

    foreach ($myrows as $campos) {
      $transaction_code = $campos->Transaction_Code;
    }
    return $transaction_code;
  }
}
