/**
 * Paymentez Link to Pay — WooCommerce Checkout Block registration.
 *
 * Registers the "paymentez_link" gateway with the WooCommerce Blocks
 * payment method registry so it appears in the Checkout Block.
 *
 * The actual payment is handled server-side via process_payment(), which
 * calls the Paymentez Link-to-Pay API and redirects the customer to the
 * hosted payment page.
 */
( function () {
	'use strict';

	var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
	var getSetting            = window.wc.wcSettings.getSetting;
	var createElement         = window.wp.element.createElement;
	var decodeEntities        = window.wp.htmlEntities.decodeEntities;

	// Data provided by Paymentez_Link_Block::get_payment_method_data().
	var settings = getSetting( 'paymentez_link_data', {} );
	var title    = decodeEntities( settings.title || 'Other Payment Methods' );

	/**
	 * Label component — shown next to the radio button.
	 */
	var Label = function () {
		return createElement( 'span', null, title );
	};

	/**
	 * Content component — shown below the radio button when selected.
	 */
	var Content = function () {
		var description = decodeEntities( settings.description || '' );
		if ( ! description ) {
			return null;
		}
		return createElement( 'p', { style: { margin: '0.5em 0 0' } }, description );
	};

	registerPaymentMethod( {
		name:           'paymentez_link',
		label:          createElement( Label, null ),
		content:        createElement( Content, null ),
		edit:           createElement( Content, null ),
		canMakePayment: function () { return true; },
		ariaLabel:      title,
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );
} )();
