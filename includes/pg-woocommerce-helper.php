<?php
require_once( dirname( __DIR__ ) . '/pg-woocommerce-plugin.php' );

global $wpdb;

define("PG_TABLE_NAME", $wpdb->prefix . 'pg_wc_plugin');

/**
 * Helper class to manage actions from the payment gateway and actions on database.
 */
class PG_WC_Helper
{

    /**
     * Creates the required table on Wordpress database.
     */
    public static function create_table() {
        global $wpdb;

        $sql = 'CREATE TABLE '.PG_TABLE_NAME.' (
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

    /**
     * Inserts data to the plugin table.
     */
    public static function insert_data($status, $comments, $description, $dev_reference, $transaction_id) {
        global $wpdb;
        $wpdb->insert(
            PG_TABLE_NAME,
            array(
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
                '%s'
            )
        );
    }

    /**
     * Gets a payment gateway transaction code for a Woocommerce order id.
     * @param string $order_id
     * @return string
     */
    public static function select_order($order_id) {
        global $wpdb;
        $myrows = $wpdb->get_results("SELECT * FROM ".PG_TABLE_NAME." where order_id = '$order_id' ", OBJECT);

        foreach ($myrows as $campos) {
            $transactionCode = $campos->pg_transaction_id;
        }
        return $transactionCode;
    }

    /**
     * Method that build the required params to initialize the payment checkout.
     * @param WC_Order $order
     * @return array
     */
    public static function get_checkout_params($order) {
        $order_data = $order->get_data();

        $description = '';
        foreach ($order->get_items() as $product) {
            $description .= $product['name'] . ',';
        }
        if (strlen($description) > 100) {
            $description = substr($description,0,99);
        }

        if (is_null($order_data['customer_id']) or empty($order_data['customer_id'])) {
            $uid = $order->id;
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
            'vat'                  => $vat,
            'street'               => $order_data['billing']['address_1'],
            'city'                 => $order_data['billing']['city'],
            'country'              => self::get_convert_country($order_data['billing']['country']),
            'state'                => $order_data['billing']['state'],
            'zip'                  => $order_data['billing']['postcode']
        );

        return $parametersArgs;
    }

    /**
     * Method to manage the LinkToPay request.
     * @param WC_Order $order
     * @param string $environment
     * @return string URL to LinkToPay
     */
    public static function generate_ltp($order, $environment) {
        $url_ltp = ($environment == 'yes') ? 'https://noccapi-stg.'.PG_DOMAIN.PG_LTP : 'https://noccapi.'.PG_DOMAIN.PG_LTP ;
        $auth_token = PG_WC_Helper::generate_auth_token('server');
        $checkout_data = PG_WC_Helper::get_checkout_params($order);
        $redirect_url = $order->get_view_order_url();
        $plugin = new PG_WC_Plugin();

        $data = [
            'user' => [
                'id'=> $checkout_data['user_id'],
                'name'=> $order->get_billing_first_name(),
                'last_name'=> $order->get_billing_last_name(),
                'email'=> $checkout_data['customer_email'],
            ],
            'order' => [
                'dev_reference' => $checkout_data['purchase_order_id'],
                'description' => $checkout_data['purchase_description'],
                'amount' => $checkout_data['purchase_amount'],
                'installments_type' => -1,
                'currency' => $order->get_currency(),
                'vat' => $checkout_data['vat'],
            ],
            'configuration' => [
                'partial_payment' => false,
                'expiration_days' => $plugin->ltp_expiration,
                'success_url' => $redirect_url,
                'failure_url' => $redirect_url,
                'pending_url' => $redirect_url,
                'review_url' => $redirect_url,
            ]
        ];

        $payload = json_encode($data);

        $ch = curl_init($url_ltp);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, ($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Auth-Token:' . $auth_token));

        try {
            $response = curl_exec($ch);
        } catch (Exception $e) {
            curl_close($ch);
            return NULL;
        }
        $get_response = json_decode($response, true);

        $data = $get_response['data'] ?: [];
        if (array_key_exists('payment', $data)) {
            curl_close($ch);
            return $data['payment']['payment_url'];
        } else {
            if (curl_errno($ch)) {
                $response = curl_error($ch);
            } else {
                $response = json_encode($get_response);
            }
            curl_close($ch);
            ?>
            <div id="ltp-failed">
                <p class="alert alert-warning">
                    <?php _e('An error occurred generating the payment link, gateway response', 'pg_woocommerce')?>: <?php echo $response;?>
                </p>
            </div>
            <?php
            return NULL;
        }
    }

    /**
     * Method to generate the payment gateway token for authentication.
     * @param string $type server
     * @return string|void
     */
    public static function generate_auth_token($type) {
        $plugin = new PG_WC_Plugin();
        if ($type == 'server') {
            $app_code = $plugin->app_code_server;
            $app_key = $plugin->app_key_server;
        } elseif ($type == 'client') {
            $app_code = $plugin->app_code_client;
            $app_key = $plugin->app_key_client;
        } else {
            return;
        }

        $timestamp = (string)(time());
        $token_string = $app_key . $timestamp;
        $token_hash = hash('sha256', $token_string);

        return base64_encode($app_code . ';' . $timestamp . ';' . $token_hash);
    }

    /**
     * Method to calculate the payment gateway stokens used for authentication.
     * @param string $user_id
     * @param string $transaction_id
     * @return array containing the stokens for client and server credentials.
     */
    public static function get_stokens($user_id, $transaction_id)
    {
        $webhookObj = new PG_WC_Plugin();
        $stoken_client = md5($transaction_id . "_" . $webhookObj->app_code_client . "_" . $user_id . "_" . $webhookObj->app_key_client);
        $stoken_server = md5($transaction_id . "_" . $webhookObj->app_code_server . "_" . $user_id . "_" . $webhookObj->app_key_server);
        return array($stoken_server, $stoken_client);

    }
    /**
     * Method to change two-letter country codes to three-letter country codes.
     *
     * @param string $country default woocommerce(alpha-2)
     * @return string $country in format ISO 3166-1 alpha-3 (this format is required for 3ds)
     */
    public static function get_convert_country($country)
    {
        $countries = array(
            'AF' => 'AFG', //Afghanistan
            'AX' => 'ALA', //land Islands
            'AL' => 'ALB', //Albania
            'DZ' => 'DZA', //Algeria
            'AS' => 'ASM', //American Samoa
            'AD' => 'AND', //Andorra
            'AO' => 'AGO', //Angola
            'AI' => 'AIA', //Anguilla
            'AQ' => 'ATA', //Antarctica
            'AG' => 'ATG', //Antigua and Barbuda
            'AR' => 'ARG', //Argentina
            'AM' => 'ARM', //Armenia
            'AW' => 'ABW', //Aruba
            'AU' => 'AUS', //Australia
            'AT' => 'AUT', //Austria
            'AZ' => 'AZE', //Azerbaijan
            'BS' => 'BHS', //Bahamas
            'BH' => 'BHR', //Bahrain
            'BD' => 'BGD', //Bangladesh
            'BB' => 'BRB', //Barbados
            'BY' => 'BLR', //Belarus
            'BE' => 'BEL', //Belgium
            'BZ' => 'BLZ', //Belize
            'BJ' => 'BEN', //Benin
            'BM' => 'BMU', //Bermuda
            'BT' => 'BTN', //Bhutan
            'BO' => 'BOL', //Bolivia
            'BQ' => 'BES', //Bonaire, Saint Estatius and Saba
            'BA' => 'BIH', //Bosnia and Herzegovina
            'BW' => 'BWA', //Botswana
            'BV' => 'BVT', //Bouvet Islands
            'BR' => 'BRA', //Brazil
            'IO' => 'IOT', //British Indian Ocean Territory
            'BN' => 'BRN', //Brunei
            'BG' => 'BGR', //Bulgaria
            'BF' => 'BFA', //Burkina Faso
            'BI' => 'BDI', //Burundi
            'KH' => 'KHM', //Cambodia
            'CM' => 'CMR', //Cameroon
            'CA' => 'CAN', //Canada
            'CV' => 'CPV', //Cape Verde
            'KY' => 'CYM', //Cayman Islands
            'CF' => 'CAF', //Central African Republic
            'TD' => 'TCD', //Chad
            'CL' => 'CHL', //Chile
            'CN' => 'CHN', //China
            'CX' => 'CXR', //Christmas Island
            'CC' => 'CCK', //Cocos (Keeling) Islands
            'CO' => 'COL', //Colombia
            'KM' => 'COM', //Comoros
            'CG' => 'COG', //Congo
            'CD' => 'COD', //Congo, Democratic Republic of the
            'CK' => 'COK', //Cook Islands
            'CR' => 'CRI', //Costa Rica
            'CI' => 'CIV', //Côte d Ivoire
            'HR' => 'HRV', //Croatia
            'CU' => 'CUB', //Cuba
            'CW' => 'CUW', //Curaçao
            'CY' => 'CYP', //Cyprus
            'CZ' => 'CZE', //Czech Republic
            'DK' => 'DNK', //Denmark
            'DJ' => 'DJI', //Djibouti
            'DM' => 'DMA', //Dominica
            'DO' => 'DOM', //Dominican Republic
            'EC' => 'ECU', //Ecuador
            'EG' => 'EGY', //Egypt
            'SV' => 'SLV', //El Salvador
            'GQ' => 'GNQ', //Equatorial Guinea
            'ER' => 'ERI', //Eritrea
            'EE' => 'EST', //Estonia
            'ET' => 'ETH', //Ethiopia
            'FK' => 'FLK', //Falkland Islands
            'FO' => 'FRO', //Faroe Islands
            'FJ' => 'FIJ', //Fiji
            'FI' => 'FIN', //Finland
            'FR' => 'FRA', //France
            'GF' => 'GUF', //French Guiana
            'PF' => 'PYF', //French Polynesia
            'TF' => 'ATF', //French Southern Territories
            'GA' => 'GAB', //Gabon
            'GM' => 'GMB', //Gambia
            'GE' => 'GEO', //Georgia
            'DE' => 'DEU', //Germany
            'GH' => 'GHA', //Ghana
            'GI' => 'GIB', //Gibraltar
            'GR' => 'GRC', //Greece
            'GL' => 'GRL', //Greenland
            'GD' => 'GRD', //Grenada
            'GP' => 'GLP', //Guadeloupe
            'GU' => 'GUM', //Guam
            'GT' => 'GTM', //Guatemala
            'GG' => 'GGY', //Guernsey
            'GN' => 'GIN', //Guinea
            'GW' => 'GNB', //Guinea-Bissau
            'GY' => 'GUY', //Guyana
            'HT' => 'HTI', //Haiti
            'HM' => 'HMD', //Heard Island and McDonald Islands
            'VA' => 'VAT', //Holy See (Vatican City State)
            'HN' => 'HND', //Honduras
            'HK' => 'HKG', //Hong Kong
            'HU' => 'HUN', //Hungary
            'IS' => 'ISL', //Iceland
            'IN' => 'IND', //India
            'ID' => 'IDN', //Indonesia
            'IR' => 'IRN', //Iran
            'IQ' => 'IRQ', //Iraq
            'IE' => 'IRL', //Republic of Ireland
            'IM' => 'IMN', //Isle of Man
            'IL' => 'ISR', //Israel
            'IT' => 'ITA', //Italy
            'JM' => 'JAM', //Jamaica
            'JP' => 'JPN', //Japan
            'JE' => 'JEY', //Jersey
            'JO' => 'JOR', //Jordan
            'KZ' => 'KAZ', //Kazakhstan
            'KE' => 'KEN', //Kenya
            'KI' => 'KIR', //Kiribati
            'KP' => 'PRK', //Korea, Democratic People\'s Republic of
            'KR' => 'KOR', //Korea, Republic of (South)
            'KW' => 'KWT', //Kuwait
            'KG' => 'KGZ', //Kyrgyzstan
            'LA' => 'LAO', //Laos
            'LV' => 'LVA', //Latvia
            'LB' => 'LBN', //Lebanon
            'LS' => 'LSO', //Lesotho
            'LR' => 'LBR', //Liberia
            'LY' => 'LBY', //Libya
            'LI' => 'LIE', //Liechtenstein
            'LT' => 'LTU', //Lithuania
            'LU' => 'LUX', //Luxembourg
            'MO' => 'MAC', //Macao S.A.R., China
            'MK' => 'MKD', //Macedonia
            'MG' => 'MDG', //Madagascar
            'MW' => 'MWI', //Malawi
            'MY' => 'MYS', //Malaysia
            'MV' => 'MDV', //Maldives
            'ML' => 'MLI', //Mali
            'MT' => 'MLT', //Malta
            'MH' => 'MHL', //Marshall Islands
            'MQ' => 'MTQ', //Martinique
            'MR' => 'MRT', //Mauritania
            'MU' => 'MUS', //Mauritius
            'YT' => 'MYT', //Mayotte
            'MX' => 'MEX', //Mexico
            'FM' => 'FSM', //Micronesia
            'MD' => 'MDA', //Moldova
            'MC' => 'MCO', //Monaco
            'MN' => 'MNG', //Mongolia
            'ME' => 'MNE', //Montenegro
            'MS' => 'MSR', //Montserrat
            'MA' => 'MAR', //Morocco
            'MZ' => 'MOZ', //Mozambique
            'MM' => 'MMR', //Myanmar
            'NA' => 'NAM', //Namibia
            'NR' => 'NRU', //Nauru
            'NP' => 'NPL', //Nepal
            'NL' => 'NLD', //Netherlands
            'AN' => 'ANT', //Netherlands Antilles
            'NC' => 'NCL', //New Caledonia
            'NZ' => 'NZL', //New Zealand
            'NI' => 'NIC', //Nicaragua
            'NE' => 'NER', //Niger
            'NG' => 'NGA', //Nigeria
            'NU' => 'NIU', //Niue
            'NF' => 'NFK', //Norfolk Island
            'MP' => 'MNP', //Northern Mariana Islands
            'NO' => 'NOR', //Norway
            'OM' => 'OMN', //Oman
            'PK' => 'PAK', //Pakistan
            'PW' => 'PLW', //Palau
            'PS' => 'PSE', //Palestinian Territory
            'PA' => 'PAN', //Panama
            'PG' => 'PNG', //Papua New Guinea
            'PY' => 'PRY', //Paraguay
            'PE' => 'PER', //Peru
            'PH' => 'PHL', //Philippines
            'PN' => 'PCN', //Pitcairn
            'PL' => 'POL', //Poland
            'PT' => 'PRT', //Portugal
            'PR' => 'PRI', //Puerto Rico
            'QA' => 'QAT', //Qatar
            'RE' => 'REU', //Reunion
            'RO' => 'ROU', //Romania
            'RU' => 'RUS', //Russia
            'RW' => 'RWA', //Rwanda
            'BL' => 'BLM', //Saint Barth&eacute;lemy
            'SH' => 'SHN', //Saint Helena
            'KN' => 'KNA', //Saint Kitts and Nevis
            'LC' => 'LCA', //Saint Lucia
            'MF' => 'MAF', //Saint Martin (French part)
            'SX' => 'SXM', //Sint Maarten / Saint Matin (Dutch part)
            'PM' => 'SPM', //Saint Pierre and Miquelon
            'VC' => 'VCT', //Saint Vincent and the Grenadines
            'WS' => 'WSM', //Samoa
            'SM' => 'SMR', //San Marino
            'ST' => 'STP', //S&atilde;o Tom&eacute; and Pr&iacute;ncipe
            'SA' => 'SAU', //Saudi Arabia
            'SN' => 'SEN', //Senegal
            'RS' => 'SRB', //Serbia
            'SC' => 'SYC', //Seychelles
            'SL' => 'SLE', //Sierra Leone
            'SG' => 'SGP', //Singapore
            'SK' => 'SVK', //Slovakia
            'SI' => 'SVN', //Slovenia
            'SB' => 'SLB', //Solomon Islands
            'SO' => 'SOM', //Somalia
            'ZA' => 'ZAF', //South Africa
            'GS' => 'SGS', //South Georgia/Sandwich Islands
            'SS' => 'SSD', //South Sudan
            'ES' => 'ESP', //Spain
            'LK' => 'LKA', //Sri Lanka
            'SD' => 'SDN', //Sudan
            'SR' => 'SUR', //Suriname
            'SJ' => 'SJM', //Svalbard and Jan Mayen
            'SZ' => 'SWZ', //Swaziland
            'SE' => 'SWE', //Sweden
            'CH' => 'CHE', //Switzerland
            'SY' => 'SYR', //Syria
            'TW' => 'TWN', //Taiwan
            'TJ' => 'TJK', //Tajikistan
            'TZ' => 'TZA', //Tanzania
            'TH' => 'THA', //Thailand
            'TL' => 'TLS', //Timor-Leste
            'TG' => 'TGO', //Togo
            'TK' => 'TKL', //Tokelau
            'TO' => 'TON', //Tonga
            'TT' => 'TTO', //Trinidad and Tobago
            'TN' => 'TUN', //Tunisia
            'TR' => 'TUR', //Turkey
            'TM' => 'TKM', //Turkmenistan
            'TC' => 'TCA', //Turks and Caicos Islands
            'TV' => 'TUV', //Tuvalu
            'UG' => 'UGA', //Uganda
            'UA' => 'UKR', //Ukraine
            'AE' => 'ARE', //United Arab Emirates
            'GB' => 'GBR', //United Kingdom
            'US' => 'USA', //United States
            'UM' => 'UMI', //United States Minor Outlying Islands
            'UY' => 'URY', //Uruguay
            'UZ' => 'UZB', //Uzbekistan
            'VU' => 'VUT', //Vanuatu
            'VE' => 'VEN', //Venezuela
            'VN' => 'VNM', //Vietnam
            'VG' => 'VGB', //Virgin Islands, British
            'VI' => 'VIR', //Virgin Island, U.S.
            'WF' => 'WLF', //Wallis and Futuna
            'EH' => 'ESH', //Western Sahara
            'YE' => 'YEM', //Yemen
            'ZM' => 'ZMB', //Zambia
            'ZW' => 'ZWE', //Zimbabwe

        );

        return isset($countries[$country]) ? $countries[$country] : $country;
    }

    /**
     * Method to show the installments on the payment page.
     * @param string $enable_installments
     * @return void
     */
    public static function get_installments_type($enable_installments)
    {
        $installments_options = [
            0  => __('Enable Installments', 'pg_woocommerce'),
            1  => __('Revolving and deferred without interest (The bank will pay to the commerce the installment, month by month)(Ecuador)', 'pg_woocommerce'),
            2  => __('Deferred with interest (Ecuador, México)', 'pg_woocommerce'),
            3  => __('Deferred without interest (Ecuador, México)', 'pg_woocommerce'),
            7  => __('Deferred with interest and months of grace (Ecuador)', 'pg_woocommerce'),
            6  => __('Deferred without interest pay month by month (Ecuador)(Medianet)', 'pg_woocommerce'),
            9  => __('Deferred without interest and months of grace (Ecuador, México)', 'pg_woocommerce'),
            10 => __('Deferred without interest promotion bimonthly (Ecuador)(Medianet)', 'pg_woocommerce'),
            21 => __('For Diners Club exclusive, deferred with and without interest (Ecuador)', 'pg_woocommerce'),
            22 => __('For Diners Club exclusive, deferred with and without interest (Ecuador)', 'pg_woocommerce'),
            30 => __('Deferred with interest pay month by month (Ecuador)(Medianet)', 'pg_woocommerce'),
            50 => __('Deferred without interest promotions (Supermaxi)(Ecuador)(Medianet)', 'pg_woocommerce'),
            51 => __('Deferred with interest (Cuota fácil)(Ecuador)(Medianet)', 'pg_woocommerce'),
            52 => __('Without interest (Rendecion Produmillas)(Ecuador)(Medianet)', 'pg_woocommerce'),
            53 => __('Without interest sale with promotions (Ecuador)(Medianet)', 'pg_woocommerce'),
            70 => __('Deferred special without interest (Ecuador)(Medianet)', 'pg_woocommerce'),
            72 => __('Credit without interest (cte smax)(Ecuador)(Medianet)', 'pg_woocommerce'),
            73 => __('Special credit without interest (smax)(Ecuador)(Medianet)', 'pg_woocommerce'),
            74 => __('Prepay without interest (smax)(Ecuador)(Medianet)', 'pg_woocommerce'),
            75 => __('Defered credit without interest (smax)(Ecuador)(Medianet)', 'pg_woocommerce'),
            90 => __('Without interest with months of grace (Supermaxi)(Ecuador)(Medianet)', 'pg_woocommerce'),
        ];
        ?>
        <div class="select" id="installments_div">
            <select name="installments_type" id="installments_type">
                <option selected disabled><?php _e('Installments Type', 'pg_woocommerce'); ?>:</option>
                <option value=-1><?php _e('Without Installments', 'pg_woocommerce'); ?></option>
                <?php
                if ($enable_installments == 'yes')
                {
                    foreach($installments_options as $value => $text)
                    {
                        ?>
                        <option value=<?php echo $value;?>><?php echo $text; ?></option>
                        <?php
                    }
                }
                ?>
            </select>
            <br><br>
        </div>
        <?php
    }
}
