$(function() {

  // var app_code_php = document.getElementById('add_card_js').getAttribute('app-code');
  // var app_key_php = document.getElementById('add_card_js').getAttribute('app-key');

  Paymentez.init('stg', 'app_code_php', 'app_key_php');

  var form              = $("#add-card-form");
  var submitButton            = form.find("button");
  var submitInitialText = submitButton.text();
  $("#add-card-form").submit(function(e){
    var myCard = $('#my-card');
    $('#messages').text("");
    var cardToSave = myCard.PaymentezForm('card');
    if(cardToSave == null){
      $('#messages').text("Invalid Card Data");
    }else{
      submitButton.attr("disabled", "disabled").text("Card Processing...");

      var uid = "uid1234";
      var email = "dev@paymentez.com";

      Paymentez.addCard(uid, email, cardToSave, successHandler, errorHandler);
    }

    e.preventDefault();
  });
  var successHandler = function(cardResponse) {
    console.log(cardResponse.card);
    if(cardResponse.card.status === 'valid'){
      $('#messages').html('Card Successfully Added<br>'+
                    'status: ' + cardResponse.card.status + '<br>' +
                    "Card Token: " + cardResponse.card.token + "<br>" +
                    "transaction_reference: " + cardResponse.card.transaction_reference
                  );
    }else if(cardResponse.card.status === 'review'){
      $('#messages').html('Card Under Review<br>'+
                    'status: ' + cardResponse.card.status + '<br>' +
                    "Card Token: " + cardResponse.card.token + "<br>" +
                    "transaction_reference: " + cardResponse.card.transaction_reference
                  );
    }else{
      $('#messages').html('Error<br>'+
                    'status: ' + cardResponse.card.status + '<br>' +
                    "message Token: " + cardResponse.card.message + "<br>"
                  );
    }
    submitButton.removeAttr("disabled");
    submitButton.text(submitInitialText);
  };
  var errorHandler = function(err) {
    console.log(err.error);
    $('#messages').html(err.error.type);
    submitButton.removeAttr("disabled");
    submitButton.text(submitInitialText);
  };
});
