jQuery(document).ready(function($) {
  var app_code_js = document.getElementById('checkout_php').getAttribute('app-code');
  var app_key_js = document.getElementById('checkout_php').getAttribute('app-key');
  var orderData = document.getElementById('orderDataJSON').textContent;
  var orderDataJSON = JSON.parse(orderData);
  var callback = document.getElementById('checkout_php').getAttribute('callback');

  var paymentezCheckout = new PaymentezCheckout.modal({
      client_app_code: app_code_js, // Client Credentials Provied by Paymentez
      client_app_key: app_key_js, // Client Credentials Provied by Paymentez
      locale: 'pt', // User's preferred language (es, en, pt). English will be used by default.
      env_mode: 'stg', // `prod`, `stg` to change environment. Default is `stg`
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
             console.log(response);
             showMessageSuccess();
          } else if (response.transaction["status_detail"] === 1) {
             console.log(response);
             showMessagePending();
          } else {
             console.log(response);
             showMessageError();
          }
      }
  });

  var btnOpenCheckout = document.querySelector('.js-paymentez-checkout');
  btnOpenCheckout.addEventListener('click', function(){
    // Open Checkout with further options:
    paymentezCheckout.open({
      user_id: orderDataJSON.user_id.toString(),
      user_email: orderDataJSON.customer_email, //optional
      user_phone: orderDataJSON.customer_phone.toString(), //optional
      order_description: orderDataJSON.purchase_description,
      order_amount: Number(orderDataJSON.purchase_amount),
      order_vat: Number(orderDataJSON.taxable_amount),
      order_reference: orderDataJSON.purchase_order_id.toString(),
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
    $("#mensajeSucccess").removeClass("hide");
    $("#buttonreturn").removeClass("hide");
  }

  function showMessageError() {
    $("#buttonspay").addClass("hide");
    $("#messagetres").removeClass("hide");
    $("#mensajeFailed").removeClass("hide");
  }

  function showMessagePending() {
    $("#buttonspay").addClass("hide");
    $("#mensajePending").removeClass("hide");
    $("#buttonreturn").removeClass("hide");
  }

  function announceTransaction(data) {
    fetch(callback, { method: "POST", body: JSON.stringify(data) })
    .then(function(response) { console.log(response); })
    .catch(function(myJson) { console.log(myJson); });
  }
});
