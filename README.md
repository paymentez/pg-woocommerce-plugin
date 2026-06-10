# Nuvei Paymentez Gateway for WooCommerce
Contributors:      paymentez
Tags:              woocommerce, payment gateway, paymentez, credit card, link to pay
Requires at least: 6.5
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        3.0.0
License:           GPL-2.0+
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 8.0
WC tested up to:      10.0

Integrates Paymentez payment gateway (Card Checkout modal and Link to Pay) into WooCommerce.

# Description 

Nuvei Paymentez Gateway for WooCommerce** adds two payment methods to your WooCommerce store:

# Paymentez Checkout (Card) 
Presents a secure modal powered by the official Paymentez JS SDK so customers can pay with their credit or debit card
without leaving your store.

# Paymentez Link to Pay 
Generates a hosted payment link on the Paymentez platform and redirects the customer there. Supports multiple payment
methods (PSE, cash vouchers, wallets, etc.) depending on your Paymentez account configuration.

# Key features 

* Two independent payment methods, each configurable separately.
* Staging / Production environment toggle.
* Webhook endpoint (`/wp-json/paymentez/webhook/v1/params`) with HMAC-SHA256 signature validation.
* Automatic WooCommerce order status updates: success → processing, failure → failed, pending → on-hold.
* WC_Logger integration for easy debugging.
* Fully internationalised (English, Spanish es_ES, Portuguese pt_BR).
* HPOS (High-Performance Order Storage) compatible.

# Installation

# 1.- Prerequisites

### 1.1.- XAMPP, LAMPP, MAMPP, Bitnami or any PHP development environment

- XAMPP: https://www.apachefriends.org/download.html
- LAMPP: https://www.apachefriends.org/download.html
- MAMPP: https://www.mamp.info/en/mac/
- Bitnami: https://bitnami.com/stack/wordpress

### 1.2.- Wordpress

If you already install the Bitnami option, this step can be omitted.

The documentation necessary to install and configure Wordpress is at the following link:

https://wordpress.org/support/article/how-to-install-wordpress/

All the minimum requirements (PHP and MySQL) must be fulfilled so that the developed plugin can work correctly.

### 1.3.- WooCommerce

The documentation needed to install WooCommerce is at the following link:

https://docs.woocommerce.com/document/installing-uninstalling-woocommerce/

There you will also find information necessary for troubleshooting related to the installation.

### 1.4.- WooCommerce Admin

The documentation needed to install WooCommerce is at the following link:

https://wordpress.org/plugins/woocommerce-admin/

There you will also find information necessary for troubleshooting related to the installation.

## 2.- Git Repository

You can download the current stable release from: https://github.com/paymentez/pg-woocommerce-plugin/releases

## 3.- Plugin Installation

1. Upload the `paymentez-gateway` folder to `/wp-content/plugins/`.
2. Activate the plugin via **Plugins > Installed Plugins**.
3. Go to **WooCommerce > Settings > Payments**.
4. Enable and configure each payment method individually.

# Configuration

Each method has its own settings page under **WooCommerce > Settings > Payments**:

* **Environment** — select *Staging* for testing or *Production* for live transactions.
* **App Code** — your Paymentez App Code.
* **App Key** — your Paymentez App Key (stored securely).
* **Title** — payment method label visible to customers.
* **Description** — short text shown below the title at checkout.
* **Button Text** (Checkout method) — label on the payment button.

# Webhook 
Add the following URL in your Paymentez merchant dashboard as the confirmation/webhook URL:

`https://your-site.com/wp-json/paymentez/webhook/v1/params`

The endpoint validates the `Auth-Token` header sent by Paymentez before processing any order update.

**Note on plain permalinks:** If your WordPress site uses plain permalinks (Settings → Permalinks set to "Plain"), the
`/wp-json/` URL will not work. Use this format instead:

`https://your-site.com/?rest_route=/paymentez/webhook/v1/params`

To check which format applies to your store, visit `https://your-site.com/wp-json/` — if it returns JSON, use the clean
URL. If it returns a 404, use the `?rest_route=` format.
https://your-site.com/wp-json/paymentez/webhook/v1/params
# Frequently Asked Questions

1. Does this plugin support PHP 8? 
Yes. The plugin is compatible with PHP 7.4 through 8.x.

2.  Is the plugin compatible with WooCommerce HPOS? 
Yes. High-Performance Order Storage compatibility is declared.

3. How do I test payments? 
Set the environment to *Staging* in the gateway settings and use Paymentez test card credentials.

4. Where are errors logged? 
Go to **WooCommerce > Status > Logs** and filter by `paymentez-checkout`, `paymentez-link`, `paymentez-api`, or
`paymentez-webhook`.

== Changelog ==

= 3.0.0 =

* Initial release.

== Upgrade Notice ==

= 3.0.0 =
Initial release — no upgrade steps required.
