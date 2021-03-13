# Paymentez Payment Gateway Plugin for WooCommerce
This is a Wordpress plugin prepared to work as a payment gateway for another plugin called WooCommerce.

## 1.- Prerequisites
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
The development works like a Wordpress plugin that connects to another Wordpress plugin, WooCommerce.

So when it is installed and activated, WooCommerce and Wordpress hooks and actions are used.

### 3.1 Installation and Activation Through Wordpress Admin
When we have the project compressed in .zip format, we proceed to the installation through Wordpress Admin.

1. The first step will be to login into Wordpress Admin as administrator.

2. Being in the main screen of the admin we click on the Plugins tab.

3. Within the Plugins screen we click on Add New.

4. Within the Add Plugins screen we click on Upload Plugin.

5. The option to upload our plugin in .zip format will be displayed. We upload it and click on the Install Now button.

6. We will be redirected to the plugin installation screen. We wait to get the message Plugin installed successfully and click on the Activate Plugin button.

7. We will be redirected to the Plugins screen where we will see our plugin installed and activated.

### 3.2.- Languages
The language of the plugin is dynamically selected according to the language that is configured in Wordpress. The languages that are available are:
- Spanish
- Spanish MX
- Spanish CO
- Spanish PE
- Spanish EC
- Spanish LA
- Portuguese
- Portuguese BR

## 4. Activation and Configuration of the Plugin in WooCommerce
After having installed our plugin in Wordpress we must proceed to configure it in the WooCommerce admin.

This is found in the WooCommerce tab of the main WordPress admin. Then we click on the Settings option and later on the Payments tab.

### 4.1 Payment Gateway Activation
To activate our payment gateway within WooCommerce we need to be within **WooCommerce -> Settings -> Payments** and we will see our plugin installed and detected.

To enable it we must activate the Enabled button. This enablement is different from that of Wordpress which we did previously.

### 4.2 Gateway Settings in WooCommerce Admin
By enabling our plugin in the WooCommerce admin, we will have some options to configure. To do this we click on the Manage button that will appear on the side of our plugin.

The options to configure are the following:

- **Staging Environment:** When enabled, the plugin will point to the Paymentez or GlobalPay staging server.

- **Enable LinkToPay:** If selected, LinkToPay(Bank transfer, cash) can be used to pay.

- **Title:** This option configures the text that the customer will see in the checkout window next to the Paymentez or GlobalPay logo.

- **Customer Message:** This option configures the message that the customer will see in the checkout window when they select Paymentez or GlobalPay as the payment method.

- **Checkout Language:** This option selects the language that will be displayed in the checkout window. The available options are Spanish, Portuguese and English (by default).

- **Installments Type:** Select the installments type that will be enabled on the payment screen (Only on card payment).

- **App Code Client:** Unique identifier in Paymentez or GlobalPay.

- **App Key Client:** Key used to encrypt communication with Paymentez or GlobalPay.

- **App Code Server:** Unique identifier on the Paymentez or GlobalPay server.

- **App Key Server:** Key used for communication with the GlobalPay server.

## 5.- Selecting the Plugin in the Store Checkout
When we have all our plugin activated and configured in WooCommerce, we will see it available to be selected by customers on the Checkout page of our store.

Just select it, fill in the Billing Details and click on the Place Order button.

By clicking we will arrive at the Order-Pay or Pay For Order window in which we will see a summary of our order. The Purchase button will be displayed which will open the payment checkout.

## 6. Process to make a Refund
The refund process will start in the main Wordpress admin window.

We select the WooCommerce tab and click on the Orders option.

We select the order that we want to refund and the Edit Order window will open.

In the item detail we will find the **Refund** button, we click and the refund options will be displayed.

We type the amount to be reimbursed and click the **Refund via Paymentez** button. The status within WooCommerce will change and so will the status on the gateway.

## 7. Configuración del Webhook
The plugin includes the functionality of a webhook to receive the transaction updates that are made. This webhook receives transaction notifications and updates them in the WooCommerce admin and database.

To configure it, the merchant must provide its **Paymentez** commercial advisor with the address where the webhook is installed, it will be in the following format: https://{{URL-COMMERCE}}/wp-content/plugins/pg-woocommerce-plugin/includes/pg-woocommerce-webhook.php.
