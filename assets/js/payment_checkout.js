jQuery(document).ready(function($) {
  var checkout_values = document.getElementById('woocommerce_checkout_pg');
  var language = checkout_values.getAttribute('checkout_language');
  var app_code_js = checkout_values.getAttribute('app_code');
  var app_key_js = checkout_values.getAttribute('app_key');
  var order_data = JSON.parse(document.getElementById('order_data').textContent);
  var webhook_p = checkout_values.getAttribute('webhook_p');
  var staging = checkout_values.getAttribute('environment');
  var environment = (staging === "yes") ? "stg" : "prod";
  var installments_type = checkout_values.getAttribute('installments_type');

  var paymentCheckout = new PaymentCheckout.modal({
      client_app_code: app_code_js,
      client_app_key: app_key_js,
      locale: language,
      env_mode: environment,
      onOpen: function() {
          //console.log('modal open');
      },
      onClose: function() {
          //console.log('modal closed');
      },
      onResponse: function(response) {
          //console.log('modal response');
          announceTransaction(response);
          if (response.transaction["status_detail"] === 3) {
             showMessageSuccess();
          } else {
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
      order_installments_type: Number(installments_type),
    });
  });

  // Close Checkout on page navigation:
  window.addEventListener('popstate', function() {
    paymentCheckout.close();
  });

  function showMessageSuccess() {
    $("#checkout-button").addClass("hide");
    $("#msj-succcess").removeClass("hide");
    $("#button-return").removeClass("hide");
    if (document.getElementById("ltp-button")) {
      $("#ltp-button").addClass("hide");
    }
    if (document.getElementById("msj-failed")) {
      $("#msj-failed").addClass("hide");
    }
  }

  function showMessageError() {
    $("#msj-failed").removeClass("hide");
  }

  function announceTransaction(data) {
    fetch(webhook_p, { method: "POST", body: JSON.stringify(data) })
    .then(function(response) { console.log(response); })
    .catch(function(myJson) { console.log(myJson); });
  }
});
