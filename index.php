<?php
/*
Plugin Name: WooCommerce WorldNet Secure Payments (redirect)
Plugin URI: http://www.worldnettps.com/
Description: Extends WooCommerce with WorldNet TPS Hosted Payment Page redirect gateway.
Version: 1.4
Author: Kevin Pattison (WorldNet TPS)
Author URI: http://www.worldnettps.com/

Copyright: © 2009-2013 WorldNet TPS.
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/*  Copyright 2013  WorldNEt TPS  (email: support@worldnettps.com)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

add_action('plugins_loaded', 'woocommerce_worldnet_hpp_init', 0);

function woocommerce_worldnet_hpp_init() {

    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

    /**
     * Localisation
     */
    load_plugin_textdomain('wc-worldnet_hpp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');

    /**
     * Gateway class
     */
    class WC_WorldNet_HPP extends WC_Payment_Gateway {
	protected $msg = array();
        public function __construct() {

            $this->id = 'worldnet_hpp';
            $this->method_title = __('WorldNet TPS HPP', 'worldnet_hpp');
            $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/WorldnetTps.jpg';
            $this->has_fields = false;

	    $this->worldnet_gateways = 'payments.worldnettps.com,WorldNet,cashflows.worldnettps.com,CashFlows,payments.pagotechnology.com,Pago Technology,
                                        payments.payius.com,Payius,payment.payzone.ie,Payzone,payments.globalone.me,GlobalOnePay,payments.anywherecommerce.com,AnywhereCommerce,
                                        payments.ct-payment.com,CT Payments,gateway.payconex.net,PayConex Plus,' ;
	    $this->currencies_available = 'EUR,Euro (EUR),GBP,British Pounds Sterling (GBP),USD,US Dollar (USD),SEK,Swedish Krona (SEK),DKK,Danish Krone (DKK),NOK,Nerwegian Krone (NOK)
                                           ,AUD,Australian Dollar (AUD),CAD,Canadian Dollar (CAD)';

            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];

            $this->terminal_id = $this->settings['terminal_id'];
            $this->currency = $this->settings['currency'];
            $this->shared_secret = $this->settings['shared_secret'];
            $this->redirect_page_id = $this->settings['redirect_page_id'];
            $this->msg['message'] = "";
            $this->msg['class'] = "";

           //old - add_action('woocommerce_api_'.strtolower(get_class($this)), array($this, 'check_worldnet_hpp_response'));
            $this->check_worldnet_hpp_response();
            add_action('valid-worldnet_hpp-request', array(&$this, 'successful_request'));

            add_action('woocommerce_update_options_payment_gateways_'.$this->id, array(&$this, 'process_admin_options'));

            add_action('woocommerce_receipt_worldnet_hpp', array(&$this, 'receipt_page'));
        //    add_action('woocommerce_thankyou_worldnet_hpp',array(&$this, 'thankyou_page'));
        }

        function init_form_fields() {
	    $gateway_options=array();
            $available_gateways = explode(',', $this->worldnet_gateways);
            for ($i=0; $i < count($available_gateways); $i+=2) {
                $gateway_options[$available_gateways[$i]] = $available_gateways[$i+1];
            }
	    $currency_options=array();
            $available_currencies = explode(',', $this->currencies_available);
            for ($i=0; $i < count($available_currencies); $i+=2) {
                $currency_options[$available_currencies[$i]] = $available_currencies[$i+1];
            }
	    $primary_currency_options=array();
            $primary_currency_options["multi"] = "MultiCurrency";
            for ($i=0; $i < count($available_currencies); $i+=2) {
                $primary_currency_options[$available_currencies[$i]] = $available_currencies[$i+1];
            }
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'worldnet_hpp'),
                    'type' => 'checkbox',
                    'label' => __('Enable WorldNet HPP Payment.', 'worldnet_hpp'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Title:', 'worldnet_hpp'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'worldnet_hpp'),
                    'default' => __('Secure payments via WorldNet', 'worldnet_hpp')),
                'description' => array(
                    'title' => __('Description:', 'worldnet_hpp'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'worldnet_hpp'),
                    'default' => __('Pay securely by Credit or Debit card through the WorldNet TPS Secure Servers.', 'worldnet_hpp')),
                'gateway'  =>  array(
                    'title' => __('WorldNet Gateway', 'worldnet_hpp'),
                    'type' => 'select',
                    'options' => $gateway_options,
                    'description' => __( 'Select the WorldNet gateway that your account is under.', 'worldnet_hpp' )),
                'test_account' => array(
                    'title' => __('Test Account?', 'worldnet_hpp'),
                    'type' => 'checkbox',
                    'label' => __('Check this if the account you are using is a test account.', 'worldnet_hpp'),
                    'default' => 'yes'),
                'currency1'  =>  array(
                    'title' => __('Primary Terminal Currency', 'worldnet_hpp'),
                    'type' => 'select',
                    'options' => $primary_currency_options,
                    'description' => __( 'Select the currency of the 1st WorldNet Terminal ID you will be using.', 'worldnet_hpp' )),
                'terminal_id1' => array(
                    'title' => __('Primary Terminal ID', 'worldnet_hpp'),
                    'type' => 'text',
                    'description' => __('Your WorldNet Terminal ID for this currency.')),
                'shared_secret1' => array(
                    'title' => __('Primary Terminal Shared Secret', 'worldnet_hpp'),
                    'type' => 'text',
                    'description' =>  __('Your Shared Secret for the Terminal ID above.', 'worldnet_hpp')),
                'currency2'  =>  array(
                    'title' => __('Secondary Terminal Currency', 'worldnet_hpp'),
                    'type' => 'select',
                    'options' => $currency_options,
                    'description' => __( 'Select the currency of the 2nd WorldNet Terminal ID you will be using.', 'worldnet_hpp' )),
                'terminal_id2' => array(
                    'title' => __('Secondary Terminal ID', 'worldnet_hpp'),
                    'type' => 'text',
                    'description' => __('Your WorldNet Terminal ID for this currency.')),
                'shared_secret2' => array(
                    'title' => __('Secondary Terminal Shared Secret', 'worldnet_hpp'),
                    'type' => 'text',
                    'description' =>  __('Your Shared Secret for the Terminal ID above.', 'worldnet_hpp')),
                'currency3'  =>  array(
                    'title' => __('Tertiary Terminal Currency', 'worldnet_hpp'),
                    'type' => 'select',
                    'options' => $currency_options,
                    'description' => __( 'Select the currency of the 3rd WorldNet Terminal ID you will be using.', 'worldnet_hpp' )),
                'terminal_id3' => array(
                    'title' => __('Tertiary Terminal ID', 'worldnet_hpp'),
                    'type' => 'text',
                    'description' => __('Your WorldNet Terminal ID for this currency.')),
                'shared_secret3' => array(
                    'title' => __('Tertiary Terminal Shared Secret', 'worldnet_hpp'),
                    'type' => 'text',
                    'description' =>  __('Your Shared Secret for the Terminal ID above.', 'worldnet_hpp')),
                'send_receipt' => array(
                    'title' => __('Send receipt from WorldNet?', 'worldnet_hpp'),
                    'type' => 'checkbox',
                    'label' => __('Should the WorldNet host send a receipt to the customer when a transaction is attempted?', 'worldnet_hpp'),
                    'default' => 'yes')
            );


        }
        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
        public function admin_options() {
            echo '<h3>'.__('WorldNet HPP Payment Gateway', 'worldnet_hpp').'</h3>';
            echo '<p>'.__('WorldNet TPS provide secure payment gateway processing including eDCC and 3D Secure.').'</p>';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';

        }
        /**
         *  There are no payment fields for WorldNet HPP, but we want to show the description if set.
         **/
        function payment_fields() {
            if($this->description) echo wpautop(wptexturize($this->description));
        }
        /**
         * Receipt Page
         **/
        function receipt_page($order) {
            echo '<p>'.__('Thank you for your order, please click the button below to pay via the WorldNet Secure Servers.', 'worldnet_hpp').'</p>';
            echo $this->generate_worldnet_hpp_form($order);
        }
        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id) {
            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }
        /**
         * Check for valid WorldNet HPP server callback
         **/
        function check_worldnet_hpp_response() {
            global $woocommerce;
		
            if(isset($_REQUEST['TERMINALID']) && isset($_REQUEST['ORDERID']) && isset($_REQUEST['RESPONSECODE'])) {
                $order_id = $_REQUEST['ORDERID'];
                switch($_REQUEST['TERMINALID']) {
                	case $this->settings['terminal_id1'] :
                		$secret = $this->settings['shared_secret1'];
                		break;
                	case $this->settings['terminal_id2'] :
                		$secret = $this->settings['shared_secret2'];
                		break;
                	case $this->settings['terminal_id3'] :
                		$secret = $this->settings['shared_secret3'];
                		break;
                }
			
                if($order_id != '') {
                    try{
                        $order = new WC_Order($order_id);
			$expectedHash = md5($_REQUEST['TERMINALID'].$_REQUEST['ORDERID'].$_REQUEST['AMOUNT'].$_REQUEST['DATETIME'].$_REQUEST['RESPONSECODE'].$_REQUEST['RESPONSETEXT'].$secret);
                        $transauthorised = false;
                        if($order->status !=='completed') {
                            if($expectedHash==$_REQUEST['HASH'])
                            {
                                if($_REQUEST['RESPONSECODE']=="A") {
                                    $transauthorised = true;
                                    $this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
                                    $this->msg['class'] = 'success';
                                    if($order->status != 'processing') {
                                        $order->payment_complete();
                                        $order->add_order_note('WorldNet HPP payment successful<br />Authorisation Code: '.$_REQUEST['APPROVALCODE'].'<br />Unique Reference: '.$_REQUEST['UNIQUEREF']);
                                        $order->add_order_note('Message to cardholder: '.$this->msg['message']);
                                        $woocommerce->cart->empty_cart();
                                    }
                                }
                                else {
                                    $this->msg['class'] = 'error';
                                    $this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                                }
                            } else {
                                $this->msg['class'] = 'error';
                                $this->msg['message'] = "Security Error. Illegal access detected";
                            }
                            if($transauthorised==false) {
                                $order->update_status('failed');
                                $order->add_order_note('Failed');
                                $order->add_order_note($this->msg['message']);
                            }
                            // old - add_action('the_content', array(&$this, 'showMessage'));
                            wc_add_notice($this->msg['message'], $notice_type = $this->msg['class']);
                        }
		    } catch(Exception $e) {
                        $msg = "Error";
                    }
		    
	            $redirect_url = ($this->redirect_page_id=="" || $this->redirect_page_id==0)?get_site_url() . "/":get_permalink($this->redirect_page_id);
	            //For wooCoomerce 2.0
	            $redirect_url = add_query_arg(array('msg'=>urlencode($this->msg['message']), 'type'=>$this->msg['class']), $redirect_url);

	        //old -    wp_redirect($redirect_url);
                wp_redirect($this->get_return_url($order));
	            exit;
                }
            }
        }

        function showMessage($content) {
            return '<div class="box '.$this->msg['class'].'-box">'.$this->msg['message'].'</div>'.$content;
        }

        /**
         * Generate WorldNet HPP button link
         **/
        public function generate_worldnet_hpp_form($order_id) {
            global $woocommerce;
	    echo $order_id;
            $order = new WC_Order($order_id);
       //     $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
       //     $redirect_url = add_query_arg('wc-api', get_class($this), $redirect_url);
            $redirect_url = $this->get_return_url($order);
       	    $dateTime =  date('d-m-Y:H:i:s:000');

            switch($order->order_currency) {
            	case $this->settings['currency1'] :
			$currency = $this->settings['currency1'];
            		$terminalid = $this->settings['terminal_id1'];
            		$secret = $this->settings['shared_secret1'];
           		$hash =  md5($terminalid . $order_id . $order->order_total . $dateTime . $redirect_url . $secret);
            		break;
            	case $this->settings['currency2'] :
			$currency = $this->settings['currency2'];
            		$terminalid = $this->settings['terminal_id2'];
            		$secret = $this->settings['shared_secret2'];
           		$hash =  md5($terminalid . $order_id . $order->order_total . $dateTime . $redirect_url . $secret);
            		break;
            	case $this->settings['currency3'] :
			$currency = $this->settings['currency3'];
            		$terminalid = $this->settings['terminal_id3'];
            		$secret = $this->settings['shared_secret3'];
           		$hash =  md5($terminalid . $order_id . $order->order_total . $dateTime . $redirect_url . $secret);
            		break;
		default:
			if($this->settings['currency1'] == 'multi') {
				$currency = $order->order_currency;
				$terminalid = $this->terminal_id1;
				$secret = $this->shared_secret1;
           			$hash =  md5($terminalid . $order_id . $currency . $order->order_total . $dateTime . $redirect_url . $secret);
			}
            		break;
            }

            $worldnet_hpp_args = array(
                'TERMINALID' => $terminalid,
                'ORDERID' => $order_id,
                'AMOUNT' => $order->order_total,
                'CURRENCY' => $currency,
		'DATETIME' => $dateTime,
                'RECEIPTPAGEURL' => $redirect_url,
                'HASH' => $hash,
                'CARDHOLDERNAME' => $order->billing_first_name . ' ' . $order->billing_last_name,
                'ADDRESS1' => $order->billing_address_1 . ', ' . $order->billing_country,
                'ADDRESS2' => $order->billing_state . ', ' . $order->billing_city,
                'POSTCODE' => $order->shipping_postcode);

	    if ($this->settings['send_receipt'] == 'yes') $worldnet_hpp_args['EMAIL'] = $order->billing_email;

            $worldnet_hpp_args_array = array();
            foreach($worldnet_hpp_args as $key => $value) {
                $worldnet_hpp_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
            }
            return '<form action="https://'.($this->settings['test_account'] == 'yes' ? 'test' : '').$this->settings['gateway'].'/merchant/paymentpage" method="get" id="worldnet_hpp_payment_form">
                ' . implode('', $worldnet_hpp_args_array) . '
                <input type="submit" class="button-alt" id="submit_worldnet_hpp_payment_form" value="'.__('Pay via WorldNet HPP', 'worldnet_hpp').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'worldnet_hpp').'</a>
                <script type="text/javascript">
jQuery(function() {
    jQuery("body").block(
            {
                message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to WorldNet HPP to make payment.', 'worldnet_hpp').'",
                    overlayCSS:
            {
                background: "#fff",
                    opacity: 0.6
        },
        css: {
            padding:        20,
                textAlign:      "center",
                color:          "#555",
                border:         "3px solid #aaa",
                backgroundColor:"#fff",
                cursor:         "wait",
                lineHeight:"32px"
        }
        });
        jQuery("#submit_worldnet_hpp_payment_form").click();

        });
                    </script>
                </form>';


        }


        // get all pages
        function get_pages($title = false, $indent = true) {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while($has_parent) {
                        $prefix .=  ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_worldnet_hpp_gateway($methods) {
        $methods[] = 'WC_WorldNet_HPP';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_worldnet_hpp_gateway' );
}

?>
