/**
 * Paymentez Checkout JS
 *
 * Handles the Paymentez modal flow:
 *  1. Customer selects the "Paymentez Checkout" gateway and clicks "Place Order".
 *  2. WooCommerce creates the order and redirects to the pay page.
 *  3. Our JS calls the server AJAX handler which calls POST /v2/transaction/init_reference/
 *     and returns the transaction reference.
 *  4. JS initialises the PaymentCheckout modal and calls checkout.open() with the reference.
 *  5. On modal response the page is redirected to the appropriate URL.
 *
 * Depends on: jQuery, paymentez-sdk (payment_checkout_3.0.0.min.js)
 * Localised object: paymentezCheckoutParams (see PHP enqueue_scripts())
 *
 * @package PaymentezGateway
 */

/* global jQuery, PaymentCheckout, paymentezCheckoutParams */

( function ( $ ) {
	'use strict';

	// Guard: only run on the checkout pay page when our gateway is active.
	if ( typeof paymentezCheckoutParams === 'undefined' ) {
		return;
	}

	var params      = paymentezCheckoutParams;
	var modalOpened = false;  // Prevent double-click / double-open.
	var $payButton  = null;

	// -----------------------------------------------------------------------
	// Utility
	// -----------------------------------------------------------------------

	/**
	 * Extract the order ID from the current page URL.
	 *
	 * Supports both permalink formats:
	 *  - Pretty:  /checkout/order-pay/24/?key=...
	 *  - Plain:   ?page_id=8&order-pay=24&key=...
	 *
	 * @return {number|null}
	 */
	function getOrderIdFromUrl() {
		var pathMatch = window.location.pathname.match( /order-pay\/(\d+)/i );
		if ( pathMatch ) {
			return parseInt( pathMatch[1], 10 );
		}
		var queryMatch = window.location.search.match( /[?&]order-pay=(\d+)/i );
		return queryMatch ? parseInt( queryMatch[1], 10 ) : null;
	}

	/**
	 * Extract the WooCommerce order key from the current page URL query string.
	 * The key is used server-side to verify the caller owns this order.
	 *
	 * @return {string}
	 */
	function getOrderKeyFromUrl() {
		var match = window.location.search.match( /[?&]key=(wc_order_\w+)/i );
		return match ? match[1] : '';
	}

	/**
	 * Show a WooCommerce-style error notice above the payment form.
	 *
	 * @param {string} message
	 */
	function showError( message ) {
		$( '.woocommerce-error, .woocommerce-info, .woocommerce-message' ).remove();
		insertNotice( $( '<ul class="woocommerce-error" role="alert"><li>' + message + '</li></ul>' ) );
	}

	/**
	 * Show a payment result message to the customer.
	 *
	 * @param {string} type     'success' | 'info' | 'error'
	 * @param {string} message
	 */
	function showPaymentMessage( type, message ) {
		$( '.woocommerce-error, .woocommerce-info, .woocommerce-message' ).remove();

		var cssClass = type === 'success' ? 'woocommerce-message'
		             : type === 'info'    ? 'woocommerce-info'
		             : 'woocommerce-error';

		insertNotice( $( '<div class="' + cssClass + '">' + message + '</div>' ) );
	}

	/**
	 * Insert a notice into the page and scroll to it.
	 * Falls back to prepending to <body> if no WooCommerce wrapper is found.
	 *
	 * @param {jQuery} $notice
	 */
	function insertNotice( $notice ) {
		var $target = $( 'form#order_review, form.woocommerce-checkout, .woocommerce' ).first();

		if ( $target.length ) {
			$target.before( $notice );
		} else {
			$( 'body' ).prepend( $notice );
		}

		var offset = $notice.offset();
		if ( offset ) {
			$( 'html, body' ).animate( { scrollTop: offset.top - 100 }, 400 );
		}
	}

	/**
	 * Re-enable the pay button so the user can retry.
	 */
	function resetButton() {
		modalOpened = false;
		if ( $payButton ) {
			$payButton.prop( 'disabled', false ).removeClass( 'paymentez-loading' );
		}
	}

	// -----------------------------------------------------------------------
	// Modal initialisation
	// -----------------------------------------------------------------------

	/**
	 * Call the server AJAX handler to run init_reference, then open the modal.
	 *
	 * @param {number} orderId WooCommerce order ID.
	 */
	function openPaymentezModal( orderId ) {
		$.ajax( {
			url:    params.ajax_url,
			method: 'POST',
			data: {
				action:    'paymentez_get_token',
				nonce:     params.nonce,
				order_id:  orderId,
				order_key: getOrderKeyFromUrl(),
			},
		} )
		.done( function ( response ) {
			if ( ! response.success ) {
				showError( response.data.message || params.i18n.error_token );
				resetButton();
				return;
			}

			var data = response.data;

			if ( typeof PaymentCheckout === 'undefined' ) {
				showError( params.i18n.error_generic );
				resetButton();
				return;
			}

			var checkout = new PaymentCheckout.modal( {
				env_mode:   params.environment,
				onClose:    function () {
					resetButton();
				},
				onResponse: function ( modalResponse ) {
					handleModalResponse( modalResponse, data );
				},
			} );

			checkout.open( { reference: data.reference } );
		} )
		.fail( function () {
			showError( params.i18n.error_token );
			resetButton();
		} );
	}

	// -----------------------------------------------------------------------
	// Modal response handler
	// -----------------------------------------------------------------------

	/**
	 * Send the modal response to PHP for server-side logging.
	 * Fire-and-forget — does not block the UI flow.
	 *
	 * @param {Object} modalResponse
	 * @param {number} orderId
	 */
	function logModalResponse( modalResponse, orderId ) {
		$.ajax( {
			url:    params.ajax_url,
			method: 'POST',
			data: {
				action:         'paymentez_log_modal_response',
				nonce:          params.nonce,
				order_id:       orderId,
				modal_response: JSON.stringify( modalResponse ),
			},
		} );
	}

	/**
	 * Handle the Paymentez modal response object.
	 *
	 * Gives the customer immediate visual feedback based on the modal response.
	 * The actual WooCommerce order status update happens asynchronously via the
	 * Paymentez webhook — we never trust the client response alone.
	 *
	 * @param {Object} modalResponse Paymentez modal response payload.
	 * @param {Object} paymentData   Data returned by ajax_get_token (urls, order_id).
	 */
	function handleModalResponse( modalResponse, paymentData ) {
		if ( ! modalResponse || ! modalResponse.transaction ) {
			showError( params.i18n.error_generic );
			resetButton();
			return;
		}

		var tx     = modalResponse.transaction;
		var status = ( tx.status || '' ).toLowerCase();

		// Log to server (fire-and-forget, does not update the order).
		logModalResponse( modalResponse, paymentData.order_id );

		if ( status === 'success' || status === 'approved' ) {
			showPaymentMessage(
				'success',
				params.i18n.payment_approved +
				( tx.id ? ' — ' + params.i18n.transaction_id + ': ' + tx.id : '' ) +
				( tx.authorization_code ? ' | ' + params.i18n.auth_code + ': ' + tx.authorization_code : '' )
			);

			$.ajax( {
				url:    params.ajax_url,
				method: 'POST',
				data:   { action: 'paymentez_empty_cart', nonce: params.nonce },
				complete: function () {
					window.location.href = paymentData.url_success;
				},
			} );

		} else if ( status === 'pending' ) {
			showPaymentMessage( 'info', params.i18n.payment_pending );

			$.ajax( {
				url:    params.ajax_url,
				method: 'POST',
				data:   { action: 'paymentez_empty_cart', nonce: params.nonce },
				complete: function () {
					window.location.href = paymentData.url_success;
				},
			} );

		} else if ( status === 'failure' || status === 'rejected' || status === 'error' ) {
			showError(
				( tx.message || params.i18n.error_generic ) +
				( tx.carrier_code ? ' (' + tx.carrier_code + ')' : '' )
			);
			resetButton();

		} else {
			showError( params.i18n.error_generic );
			resetButton();
		}
	}

	// -----------------------------------------------------------------------
	// DOM ready
	// -----------------------------------------------------------------------

	$( function () {
		var isPayPage = $( 'body' ).hasClass( 'woocommerce-order-pay' ) ||
		                window.location.pathname.indexOf( 'order-pay' ) !== -1 ||
		                window.location.search.indexOf( 'order-pay' ) !== -1;

		if ( ! isPayPage ) {
			return;
		}

		var orderId = getOrderIdFromUrl();

		if ( ! orderId ) {
			return;
		}

		$payButton = $( '#place_order, #payment [type="submit"]' ).first();

		if ( ! modalOpened && params.auto_open ) {
			modalOpened = true;

			if ( $payButton.length ) {
				$payButton.prop( 'disabled', true ).addClass( 'paymentez-loading' );
			}

			openPaymentezModal( orderId );
		}

		$( document.body ).on( 'click', '#place_order, #payment [type="submit"]', function ( e ) {
			var selectedGateway = $( 'input[name="payment_method"]:checked' ).val();

			if ( selectedGateway !== params.gateway_id ) {
				return;
			}

			if ( modalOpened ) {
				e.preventDefault();
				return;
			}

			e.preventDefault();
			modalOpened = true;

			$payButton = $( this );
			$payButton.prop( 'disabled', true ).addClass( 'paymentez-loading' );

			openPaymentezModal( orderId );
		} );
	} );

} )( jQuery );