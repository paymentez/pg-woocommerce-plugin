<?php
/**
 * Abstract base class for Paymentez payment gateways.
 *
 * Centralises the settings form fields, constructor boilerplate, API factory
 * and logger that are shared by every concrete Paymentez gateway. Each
 * concrete gateway only needs to implement its own payment flow.
 *
 * @package PaymentezGateway
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Paymentez_Abstract_Gateway
 */
abstract class Paymentez_Abstract_Gateway extends WC_Payment_Gateway {

	/**
	 * Default brand logo shown next to the payment method at checkout.
	 */
	const BRAND_ICON_URL = 'https://cdn.paymentez.com/img/paymentez_nuvei.png';

	/**
	 * WooCommerce logger source tag.
	 * Each concrete class must set this in its constructor before calling
	 * init_common_settings().
	 *
	 * @var string
	 */
	protected $log_source = 'paymentez';

	/**
	 * @var bool Whether running in staging mode.
	 */
	protected $is_staging;

	/**
	 * @var string Paymentez App Code.
	 */
	protected $app_code;

	/**
	 * @var string Paymentez App Key.
	 */
	protected $app_key;

	/**
	 * @var \WC_Logger
	 */
	private $logger;

	// -----------------------------------------------------------------------
	// Common initialisation
	// -----------------------------------------------------------------------

	/**
	 * Initialise settings, assign shared properties and register the admin-save
	 * hook. Call this at the end of each concrete constructor, after setting
	 * $this->id and $this->log_source.
	 */
	protected function init_common_settings(): void {
		$this->supports = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->is_staging  = 'staging' === $this->get_option( 'environment', 'staging' );
		$this->app_code    = $this->get_option( 'app_code' );
		$this->app_key     = $this->get_option( 'app_key' );
		$this->logger      = wc_get_logger();

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'process_admin_options' )
		);
	}

	// -----------------------------------------------------------------------
	// Shared form fields
	// -----------------------------------------------------------------------

	/**
	 * Returns the form fields common to all Paymentez gateways.
	 * Concrete classes merge their own fields on top of these.
	 *
	 * @return array
	 */
	protected function common_form_fields(): array {
		return array(
			'enabled'     => array(
				'title'   => __( 'Enable / Disable', 'paymentez-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this payment method', 'paymentez-gateway' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'       => __( 'Title', 'paymentez-gateway' ),
				'type'        => 'text',
				'description' => __( 'Payment method title shown to the customer at checkout.', 'paymentez-gateway' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'paymentez-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Description shown below the payment method title at checkout.', 'paymentez-gateway' ),
				'desc_tip'    => true,
			),
			'environment' => array(
				'title'       => __( 'Environment', 'paymentez-gateway' ),
				'type'        => 'select',
				'description' => __( 'Select the Paymentez environment to use.', 'paymentez-gateway' ),
				'default'     => 'staging',
				'desc_tip'    => true,
				'options'     => array(
					'staging'    => __( 'Staging', 'paymentez-gateway' ),
					'production' => __( 'Production', 'paymentez-gateway' ),
				),
			),
			'app_code'    => array(
				'title'       => __( 'App Code', 'paymentez-gateway' ),
				'type'        => 'text',
				'description' => __( 'Your Paymentez application code.', 'paymentez-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'app_key'     => array(
				'title'       => __( 'App Key', 'paymentez-gateway' ),
				'type'        => 'password',
				'description' => __( 'Your Paymentez application secret key.', 'paymentez-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
		);
	}

	// -----------------------------------------------------------------------
	// API factory
	// -----------------------------------------------------------------------

	/**
	 * Create a configured Paymentez API client using the gateway credentials.
	 *
	 * @return Paymentez_API
	 */
	protected function make_api(): Paymentez_API {
		return new Paymentez_API( $this->app_code, $this->app_key, $this->is_staging );
	}

	// -----------------------------------------------------------------------
	// Logger
	// -----------------------------------------------------------------------

	/**
	 * Write a message to the WooCommerce log under this gateway's source tag.
	 *
	 * @param string $message
	 * @param string $level  'debug' | 'info' | 'warning' | 'error'
	 */
	protected function log( string $message, string $level = 'debug' ): void {
		$this->logger->log( $level, $message, array( 'source' => $this->log_source ) );
	}

	// -----------------------------------------------------------------------
	// Shared payload helpers (used by Checkout and Link gateways)
	// -----------------------------------------------------------------------

	/**
	 * Returns the filterable map of ISO 3166-1 alpha-2 → international dialling prefix.
	 *
	 * @return array<string,string>
	 */
	protected function get_phone_prefix_map(): array {
		return apply_filters( 'paymentez_link_phone_prefix_map', array(
			'CO' => '57',
			'MX' => '52',
			'EC' => '593',
			'PE' => '51',
			'CL' => '56',
			'AR' => '54',
			'BR' => '55',
			'US' => '1',
			'CA' => '1',
			'PA' => '507',
			'CR' => '506',
			'GT' => '502',
			'HN' => '504',
			'SV' => '503',
			'NI' => '505',
			'BO' => '591',
			'PY' => '595',
			'UY' => '598',
			'VE' => '58',
			'DO' => '1',
		) );
	}

	/**
	 * Maps an ISO 3166-1 alpha-2 country code to its alpha-3 equivalent.
	 *
	 * @param  string $alpha2 Two-letter country code (e.g. "CO").
	 * @return string Three-letter code (e.g. "COL"), or empty string if not found.
	 */
	protected function get_country_alpha3( string $alpha2 ): string {
		$map = array(
			'CO' => 'COL',
			'MX' => 'MEX',
			'EC' => 'ECU',
			'PE' => 'PER',
			'CL' => 'CHL',
			'AR' => 'ARG',
			'BR' => 'BRA',
			'US' => 'USA',
			'CA' => 'CAN',
			'PA' => 'PAN',
			'CR' => 'CRI',
			'GT' => 'GTM',
			'HN' => 'HND',
			'SV' => 'SLV',
			'NI' => 'NIC',
			'BO' => 'BOL',
			'PY' => 'PRY',
			'UY' => 'URY',
			'VE' => 'VEN',
			'DO' => 'DOM',
		);
		return $map[ strtoupper( $alpha2 ) ] ?? '';
	}

	/**
	 * Format a phone number to E.164-style with country prefix (e.g. +573104989976).
	 *
	 * Rules:
	 *  1. If the number already starts with '+', return it with only digits after the '+'.
	 *  2. Strip all non-digit characters.
	 *  3. Remove a leading '0' (trunk prefix used in some countries).
	 *  4. Prepend the country calling code from the map.
	 *  5. If the country is not in the map, return the digits as-is.
	 *
	 * @param  string $phone   Raw phone number from WooCommerce.
	 * @param  string $country ISO 3166-1 alpha-2 country code.
	 * @return string
	 */
	protected function format_phone( string $phone, string $country ): string {
		$prefix_map = $this->get_phone_prefix_map();

		if ( strpos( $phone, '+' ) === 0 ) {
			return '+' . preg_replace( '/\D/', '', substr( $phone, 1 ) );
		}

		$digits = ltrim( preg_replace( '/\D/', '', $phone ), '0' );

		if ( empty( $digits ) ) {
			return $phone;
		}

		$prefix = $prefix_map[ strtoupper( $country ) ] ?? '';

		if ( $prefix === '' ) {
			return $digits;
		}

		if ( strpos( $digits, $prefix ) === 0 ) {
			return '+' . $digits;
		}

		return '+' . $prefix . $digits;
	}

	/**
	 * Build the billing_address object for a Paymentez payload.
	 *
	 * Required fields (city, zip, country) are always included.
	 * Optional fields are included only when WooCommerce has a value for them.
	 * country is converted from ISO 3166-1 alpha-2 to alpha-3 via a filterable map.
	 *
	 * @param  WC_Order $order
	 * @return array
	 */
	protected function get_billing_address( WC_Order $order ): array {
		$alpha2  = $order->get_billing_country();
		$alpha3  = $this->get_country_alpha3( $alpha2 );
		$country = $alpha3 !== '' ? $alpha3 : $alpha2;

		$address = array(
			'first_name' => sanitize_text_field( $order->get_billing_first_name() ),
			'last_name'  => sanitize_text_field( $order->get_billing_last_name() ),
			'city'       => mb_substr( sanitize_text_field( $order->get_billing_city() ), 0, 50 ),
			'zip'        => mb_substr( sanitize_text_field( $order->get_billing_postcode() ), 0, 50 ),
			'country'    => sanitize_text_field( $country ),
			'locale'     => $this->get_locale_code(),
		);

		$optional = array(
			'street'       => $order->get_billing_address_1(),
			'house_number' => $order->get_billing_address_2(),
			'state'        => $order->get_billing_state(),
		);

		foreach ( $optional as $key => $value ) {
			$value = sanitize_text_field( (string) $value );
			if ( $value !== '' ) {
				$address[ $key ] = $value;
			}
		}

		return $address;
	}

	/**
	 * Resolve the customer's fiscal number and type from order meta.
	 *
	 * Checks the most common meta keys used by Latin American WooCommerce stores.
	 * Override the resolved values with the `paymentez_link_fiscal_data` filter.
	 *
	 * @param  WC_Order $order
	 * @return array { string $fiscal_number, string $fiscal_number_type }
	 */
	protected function get_fiscal_data( WC_Order $order ): array {
		$candidate_keys = array(
			'_billing_nit',
			'billing_nit',
			'_billing_fiscal_number',
			'billing_fiscal_number',
			'_billing_dni',
			'billing_dni',
			'_billing_document',
			'billing_document',
		);

		$fiscal_number = '';
		foreach ( $candidate_keys as $key ) {
			$value = $order->get_meta( $key );
			if ( $value !== '' && $value !== null ) {
				$fiscal_number = sanitize_text_field( $value );
				break;
			}
		}

		$fiscal_number_type = $fiscal_number !== '' ? 'NIT' : '';

		/**
		 * Filter the fiscal data sent to Paymentez.
		 *
		 * @param array    $data  { fiscal_number: string, fiscal_number_type: string }
		 * @param WC_Order $order The current order.
		 */
		$data = apply_filters( 'paymentez_link_fiscal_data', array(
			'fiscal_number'      => $fiscal_number,
			'fiscal_number_type' => $fiscal_number_type,
		), $order );

		return array(
			sanitize_text_field( $data['fiscal_number'] ?? '' ),
			sanitize_text_field( $data['fiscal_number_type'] ?? '' ),
		);
	}

	/**
	 * Map the WordPress locale to a Paymentez-supported language code.
	 *
	 * Supported values: 'en', 'es', 'pt'. Defaults to 'en'.
	 *
	 * @return string
	 */
	protected function get_locale_code(): string {
		$lang      = substr( get_locale(), 0, 2 );
		$supported = array( 'en', 'es', 'pt' );
		return in_array( $lang, $supported, true ) ? $lang : 'en';
	}
}