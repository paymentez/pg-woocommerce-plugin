/**
 * Paymentez Checkout — WooCommerce Checkout Block registration.
 *
 * Registers the "paymentez_checkout" gateway with the WooCommerce Blocks
 * payment method registry so it appears in the Checkout Block.
 *
 * The actual payment (modal trigger) is handled server-side via
 * process_payment(), which redirects the customer to the order-pay page
 * where the Paymentez JS modal fires.
 */
( function () {
	'use strict';

	var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
	var getSetting            = window.wc.wcSettings.getSetting;
	var createElement         = window.wp.element.createElement;
	var useState              = window.wp.element.useState;
	var useEffect             = window.wp.element.useEffect;
	var decodeEntities        = window.wp.htmlEntities.decodeEntities;

	// Data provided by Paymentez_Checkout_Block::get_payment_method_data().
	var settings     = getSetting( 'paymentez_checkout_data', {} );
	var title        = decodeEntities( settings.title || 'Paymentez Checkout' );
	var installments = settings.installments || [];

	/**
	 * Label component — shown next to the radio button.
	 */
	var Label = function () {
		return createElement( 'span', null, title );
	};

	/**
	 * Content component — shown below the radio button when selected.
	 * When installments are configured, renders a selector and wires it
	 * to onPaymentSetup so the value reaches process_payment() via $_POST.
	 */
	var Content = function ( props ) {
		var eventRegistration = props.eventRegistration;
		var emitResponse      = props.emitResponse;
		var description       = decodeEntities( settings.description || '' );

		var stateArr    = useState( installments.length ? installments[0].value : '' );
		var selected    = stateArr[0];
		var setSelected = stateArr[1];

		useEffect( function () {
			if ( ! installments.length || ! eventRegistration || ! emitResponse ) {
				return;
			}

			var unsubscribe = eventRegistration.onPaymentSetup( function () {
				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							paymentez_installments_type: selected,
						},
					},
				};
			} );

			return unsubscribe;
		}, [ selected ] ); // eslint-disable-line react-hooks/exhaustive-deps

		var nodes = [];

		if ( description ) {
			nodes.push( createElement( 'p', { key: 'desc', style: { margin: '0.5em 0 0' } }, description ) );
		}

		if ( installments.length ) {
			nodes.push(
				createElement(
					'p',
					{ key: 'install', className: 'form-row form-row-wide', style: { margin: '0.75em 0 0' } },
					createElement(
						'label',
						{ htmlFor: 'paymentez_installments_type_block', style: { display: 'block', marginBottom: '4px' } },
						decodeEntities( 'Installments' )
					),
					createElement(
						'select',
						{
							id:       'paymentez_installments_type_block',
							value:    selected,
							onChange: function ( e ) { setSelected( e.target.value ); },
							style:    { width: '100%' },
						},
						installments.map( function ( opt ) {
							return createElement( 'option', { key: opt.value, value: opt.value }, opt.label );
						} )
					)
				)
			);
		}

		if ( ! nodes.length ) {
			return null;
		}

		return createElement( 'div', null, nodes );
	};

	registerPaymentMethod( {
		name:           'paymentez_checkout',
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