jQuery(document).ready(function($) {
  var checkout_values = document.getElementById('woocommerce_checkout_pg');
  var language = checkout_values.getAttribute('checkout_language');
  var app_code_js = checkout_values.getAttribute('app_code');
  var app_key_js = checkout_values.getAttribute('app_key');
  var order_data = JSON.parse(document.getElementById('order_data').textContent);
  var webhook_p = checkout_values.getAttribute('webhook_p');
  var staging = checkout_values.getAttribute('enviroment');
  var enviroment = (staging === "yes") ? "stg" : "prod";

  var paymentCheckout = new PaymentCheckout.modal({
      client_app_code: app_code_js,
      client_app_key: app_key_js,
      locale: language,
      env_mode: enviroment,
      onOpen: function() {
          console.log('modal open');
      },
      onClose: function() {
          console.log('modal closed');
      },
      onResponse: function(response) {
          console.log('modal response');
          announceTransaction(response);
          if (response.transaction["status_detail"] === 3) {
             // console.log(response);
             showMessageSuccess();
          } else {
             // console.log(response);
             showMessageError();
          }
      }
  });

  var btnOpenCheckout = document.querySelector('.js-payment-checkout');
  btnOpenCheckout.addEventListener('click', function(){
    paymentCheckout.open({
      user_id: order_data.user_id.toString(),
      user_email: order_data.customer_email,
      user_phone: order_data.customer_phone.toString(),
      order_description: order_data.purchase_description.toString(),
      order_amount: Number(order_data.purchase_amount),
      order_vat: Number(order_data.vat),
      order_reference: order_data.purchase_order_id.toString(),
      //order_installments_type: 2, // optional: For Colombia an Brazil to show installments should be 0, For Ecuador the valid values are: https://paymentez.github.io/api-doc/#payment-methods-cards-debit-with-token-installments-type
      //order_taxable_amount: 0, // optional: Only available for Ecuador. The taxable amount, if it is zero, it is calculated on the total. Format: Decimal with two fraction digits.
      //order_tax_percentage: 10 // optional: Only available for Ecuador. The tax percentage to be applied to this order.
    });
  });

  // Close Checkout on page navigation:
  window.addEventListener('popstate', function() {
    paymentezCheckout.close();
  });

  function showMessageSuccess() {
    $("#buttonspay").addClass("hide");
    $("#msj-succcess").removeClass("hide");
    $("#buttonreturn").removeClass("hide");
  }

  function showMessageError() {
    $("#buttonspay").addClass("hide");
    $("#messagetres").removeClass("hide");
    $("#msj-failed").removeClass("hide");
  }

  function announceTransaction(data) {
    fetch(webhook_p, { method: "POST", body: JSON.stringify(data) })
    .then(function(response) { console.log(response); })
    .catch(function(myJson) { console.log(myJson); });
  }
});
