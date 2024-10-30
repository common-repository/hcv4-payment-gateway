<?php

/* CorCRM Payment Gateway Class */
include_once( 'Corcrm_Utility.php' );
global $crmUtility;
$crmUtility = new Corcrm_Utility();

/* CorCRM Payment Gateway Class */
include_once( 'Payment_Utility.php' );
global $paymentUtility;
$paymentUtility = new Payment_Utility();

use Hiecor\Rest\Client;

class Corcrm_Payment extends WC_Payment_Gateway {

    // Setup our Gateway's id, description and other values
    function __construct() {

        // The global ID for this Payment method
        $this->id = "corcrm_payment";

        // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
        $this->method_title = __("Credit Card", 'corcrm-secure-payments');

        // The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = __("HieCOR Secure Payment Gateway Plug-in for WooCommerce", 'corcrm-secure-payments');

        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title = __("Credit Card", 'corcrm-secure-payments');

        // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
        $this->icon = null;

        // Bool. Can be set to true if you want payment fields to show on the checkout 
        // if doing a direct integration, which we are doing in this case
        $this->has_fields = true;

        // Supports the default credit card form
        $this->supports = array('default_credit_card_form');

        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();

        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        // $this->title = $this->get_option( 'title' );
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }

        // Lets check for SSL
        add_action('admin_notices', array($this, 'do_ssl_check'));

        // check if square payment option is enable 
        $square_option = get_option('woocommerce_corcrm_payment_settings');
        if(isset($square_option['square_payment']) && $square_option['square_payment'] == 'yes') {
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
        }

        // Save settings
        if (is_admin()) {
            // Versions over 2.0
            // Save our administration options. Since we are not going to be doing anything special
            // we have not defined 'process_admin_options' in this class so the method in the parent
            // class will be used instead
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }
    }

// End __construct()

    /**
     * Payment form on checkout page
     */

    public function payment_fields()
    {
        $square_option = get_option('woocommerce_corcrm_payment_settings');
        if(isset($square_option['square_payment']) && $square_option['square_payment'] == 'yes') {  ?>
            <fieldset class="hcv4square-checkout">
                <?php
                $allowed = array(
                    'a' => array(
                        'href' => array(),
                        'title' => array()
                    ),
                    'br' => array(),
                    'em' => array(),
                    'strong' => array(),
                    'span'  => array(
                        'class' => array(),
                    ),
                );
                if ( $this->description ) {
                    echo apply_filters( 'hcv4_square_description', wpautop( wp_kses( $this->description, $allowed ) ) );
                }
                ?>
                <p class="form-row form-row-wide">
                    <label for="sq-card-number"><?php esc_html_e( 'Card Number', 'hcv4square' ); ?> <span class="required">*</span></label>
                    <input id="sq-card-number" type="text" maxlength="20" autocomplete="off" placeholder="���� ���� ���� ����" name="<?php echo esc_attr( $this->id ); ?>-card-number" />
                </p>

                <p class="form-row form-row-first">
                    <label for="sq-expiration-date"><?php esc_html_e( 'Expiry (MM/YY)', 'hcv4square' ); ?> <span class="required">*</span></label>
                    <input id="sq-expiration-date" type="text" autocomplete="off" placeholder="<?php esc_attr_e( 'MM / YY', 'woosquare' ); ?>" name="<?php echo esc_attr( $this->id ); ?>-card-expiry" />
                </p>

                <p class="form-row form-row-last">
                    <label for="sq-cvv"><?php esc_html_e( 'Card Code', 'hcv4square' ); ?> <span class="required">*</span></label>
                    <input id="sq-cvv" type="text" autocomplete="off" placeholder="<?php esc_attr_e( 'CVV', 'woosquare' ); ?>" name="<?php echo esc_attr( $this->id ); ?>-card-cvv" />
                </p>

                <p class="form-row form-row-wide">
                    <label for="sq-postal-code"><?php esc_html_e( 'Card Postal Code', 'hcv4square' ); ?> <span class="required">*</span></label>
                    <input id="sq-postal-code" type="text" autocomplete="off" placeholder="<?php esc_attr_e( 'Card Postal Code', 'woosquare' ); ?>" name="<?php echo esc_attr( $this->id ); ?>-card-postal-code" />
                </p>
            </fieldset>

            <?php

        }else{
            $cc_form = new WC_Payment_Gateway_CC();
            $cc_form->id = $this->id;
            $cc_form->supports = $this->supports;
            $cc_form->form();
        }

    }
    public function payment_scripts(){
        if ( ! is_checkout() ) {
            return;
        }
        $square_option_payment = get_option('woocommerce_corcrm_payment_settings');
        $environment = $square_option_payment['square_environment'];
        if($environment == 'staging'){
            wp_register_script( 'square', 'https://js.squareupsandbox.com/v2/paymentform', array(),false,true );
        }else{
            wp_register_script( 'square', 'https://js.squareup.com/v2/paymentform', array(),false,true );
        }
        wp_register_script( 'hcv4-square', plugin_dir_url(__FILE__) . 'js/SquarePayments.js', array( 'jquery', 'square' ), '1.0.5',true );

        global $woocommerce;
        // Will get you cart object
        $cart_total = $woocommerce->cart->get_totals();

        $application_id = $square_option_payment['square_payment_application'];
        $location    = $square_option_payment['square_payment_location_id'];

        wp_localize_script( 'hcv4-square', 'square_params', array(
            'application_id'               =>  $application_id,
            'environment'                  =>  $environment,
            'locationId'                   =>  $location,
            'cart_total'                   =>  $cart_total['total'] ,
            'get_woocommerce_currency'     =>  get_woocommerce_currency(),
            'placeholder_card_number'      => __( '���� ���� ���� ����', 'hcv4square' ),
            'placeholder_card_expiration'  => __( 'MM / YY', 'hcv4square' ),
            'placeholder_card_cvv'         => __( 'CVV', 'hcv4square' ),
            'placeholder_card_postal_code' => __( 'Card Postal Code', 'hcv4square' ),
            'payment_form_input_styles'    => esc_js( $this->get_input_styles() ),
            'custom_form_trigger_element'  => apply_filters( 'woocommerce_square_payment_form_trigger_element', esc_js( '' ) ),
        ) );

        wp_enqueue_script( 'hcv4-square' );

        return true;
    }

    public function get_input_styles(){
        $styles = array(
            array(
                'fontSize'        => '1.2em',
                'padding'         => '.618em',
                'fontWeight'      => 400,
                'backgroundColor' => 'transparent',
                'lineHeight'      => 1.7
            ),
            array(
                'mediaMaxWidth' => '1200px',
                'fontSize'      => '1em'
            )
        );

        return apply_filters( 'woocommerce_corcrm_payment_input_styles', wp_json_encode( $styles ) );
    }



    // Build the administration fields for this specific Gateway
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable / Disable', 'corcrm-secure-payments'),
                'label' => __('Enable this payment gateway', 'corcrm-secure-payments'),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'corcrm-secure-payments'),
                'type' => 'text',
                'desc_tip' => __('Payment title the customer will see during the checkout process.', 'corcrm-secure-payments'),
                'default' => __('Credit Card', 'corcrm-secure-payments'),
            ),
            'description' => array(
                'title' => __('Description', 'corcrm-secure-payments'),
                'type' => 'textarea',
                'desc_tip' => __('Payment description the customer will see during the checkout process.', 'corcrm-secure-payments'),
                'default' => __('Pay securely using your credit card.', 'corcrm-secure-payments'),
                'css' => 'max-width:350px;'
            ),
            'hiecor_url' => array(
                'title' => __('Hiecor Url', 'corcrm-secure-payments'),
                'type' => 'text',
                'placeholder' => 'https://store.corcrm.com',
                'desc_tip' => __('This is the Hiecor CRM account URL.', 'corcrm-secure-payments'),
            ),
            'auth_key' => array(
                'title' => __('Authorization Key', 'corcrm-secure-payments'),
                'type' => 'text',
                'desc_tip' => __('This is the Authorization Key provided by HieCOR.', 'corcrm-secure-payments'),
            ),
            'user_name' => array(
                'title' => __('User Name', 'corcrm-secure-payments'),
                'type' => 'text',
                'desc_tip' => __('This is the API User Name provided by HieCOR.', 'corcrm-secure-payments'),
                'default' => '',
            ),
            'agent_id' => array(
                'title' => __('Agent Id', 'corcrm-secure-payments'),
                'type' => 'text',
                'desc_tip' => __('This is the Hiecor User Id.', 'corcrm-secure-payments'),
                'default' => '',
            ),
            'source' => array(
                'title' => __('Source', 'corcrm-secure-payments'),
                'type' => 'text',
                'desc_tip' => __('This is the source of order.', 'corcrm-secure-payments'),
                'default' => __('woocommerce', 'corcrm-secure-payments'),
            ),
            array(
                'title' => __('General options', 'corcrm-secure-payments'),
                'type' => 'title',
                'desc' => '',
                'id' => 'cor_general_options'
            ),
            'order_status' => array(
                'title' => __('Order Status', 'corcrm-secure-payments'),
                'type' => 'select',
                'default' => 'complete',
                'options' => array(
                    'complete' => __('Complete', 'woocommerce'),
                    'processing' => __('Processing', 'woocommerce'),
                ),
                'class' => 'wc-enhanced-select',
                'desc_tip' => __('Set default order status', 'corcrm-secure-payments'),
            ),
            'visit_plugin' => array(
                'title' => __('Visit', 'corcrm-secure-payments'),
                'label' => __('Include visit tracking code.', 'corcrm-secure-payments'),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            array(
                'title' => __('Hiecor Square Payment', 'corcrm-secure-payments'),
                'type' => 'title',
                'desc' => '',
                'id' => 'cor_square_payment_options'
            ),
            'square_payment' => array(
                'title' => __('Hiecor Square Payment', 'corcrm-secure-payments'),
                'label' => __('Use Hiecor\'s Square Payment Gateway.', 'corcrm-secure-payments'),
                'type' => 'checkbox',
                'default' => 'no',

            ),
            'square_environment' => array(
                'title' => __('Environment', 'corcrm-secure-payments'),
                'type' => 'select',
                'default' => 'Sandbox',
                'options' => array(
                    'staging' => __('Sandbox', 'woocommerce'),
                    'production' => __('Production', 'woocommerce'),
                ),
                'class' => 'wc-enhanced-select',
                'desc_tip' => __('Set Environment of the Payment method', 'hcv4_square-corcrm-secure-payments'),
            ),
            'square_payment_application' => array(
                'title' => __('Square Payment Application Id', 'corcrm-secure-payments'),
                'label' => __('Square Payment Application Id', 'corcrm-secure-payments'),
                'type' => 'text',
                'desc_tip' => __('Square Payment Application Id', 'corcrm-secure-payments'),
            ),
            'square_payment_location_id' => array(
                'title' => __('Square Payment Location', 'corcrm-secure-payments'),
                'label' => __('Square Payment Location', 'corcrm-secure-payments'),
                'type' => 'text',
                'desc_tip' => __('Square Payment Location', 'corcrm-secure-payments'),
            )
        );
    }

    public function getCustomerInfo($userData) {

        global $crmUtility;
        $hicorClient = $crmUtility->getHiecorClient();
        $data = array('email' => $userData['bill_email']);
        try {
            if(empty($hicorClient)){
                throw new \Exception('Unable to Connect Hiecor CRM.');
            }
            $resp = $hicorClient->get('user/', $data);
            //print_r($resp);
            if (isset($resp->success) && $resp->success && !empty($resp->data)) {
                return $resp->data[0]->userID;
            } else {
                $createUserData = array(
                    'first_name' => $userData['bill_first_name'],
                    'last_name' => $userData['bill_last_name'],
                    'email' => $userData['bill_email'],
                    'phone' => $userData['bill_phone'],
                    'address' => $userData['bill_address_1'],
                    'address2' => $userData['bill_address_2'],
                    'city' => $userData['bill_city'],
                    'region' => $userData['bill_region'],
                    'postal_code' => $userData['bill_postal_code']
                );

                $createUserResp = $hicorClient->post('user/', $createUserData);
                //print_r($hicorClient);
                //print_r($createUserResp);die('test');
                if (isset($createUserResp->success) && $createUserResp->success && !empty($createUserResp->data)) {
                    return $createUserResp->data->userID;
                } else {
                    throw new \Exception('Unable to Create User in Hiecor CRM.');
                }

            }
        } catch (\Exception $ex) {
            $log = "CorCRM Payment Exception OrderID #{$order_id}: ".$ex->getMessage().'Error occured during fetching UserID.';
            self::logger($log);
            return false;
        }
    }

    // Submit payment and handle response
    public function process_payment($order_id) {
        global $woocommerce;
        global $crmUtility;
        global $paymentUtility;
        global $wpdb;
        $delivery_date = '';
        $tbl_name = $wpdb->prefix . 'posts';
        try {
            // check if square payment option is enabled or not
            $square_nonce = '';
            $buyerVerification_token = '';
            $hcv4_settings = get_option('woocommerce_corcrm_payment_settings');
            if(isset($hcv4_settings['square_payment']) && $hcv4_settings['square_payment'] == 'yes') {
                if(isset( $_POST['square_nonce']) && !empty($_POST['square_nonce']) ){
                    $square_nonce = $_POST['square_nonce'];
                }
                if(isset($_POST['buyerVerification_token'])&& !empty($_POST['buyerVerification_token'])){
                    $buyerVerification_token = $_POST['buyerVerification_token'];
                }

            }
            // Get this Order's information
            $customer_order = new WC_Order($order_id);

            $shipping_method = @array_shift($customer_order->get_shipping_methods());
            $shipping_method_name = $shipping_method['name'];
            $customer_note = $customer_order->get_customer_note();

            $items = $customer_order->get_items();
            $tip = $this->getTipAmount($items);
            $productData = $paymentUtility->getProductsData($items);

            $cardExpDate = sanitize_text_field($_POST['corcrm_payment-card-expiry']);
            $cardExp = explode('/', $cardExpDate);
            if (strlen(trim($cardExp[1])) == 2) {
                $cardExp[1] = '20' . trim($cardExp[1]);
            }

            //Coupon Code

            $coupon = '';
            if (!empty($customer_order->get_coupon_codes())) {
                $coupon = $customer_order->get_coupon_codes();
                $coupon = $coupon[0];
            }

            //Shipping Fee
            $process_fee = 0;
            if ($woocommerce->session->fee_total > 0) {
                $process_fee = $woocommerce->session->fee_total;
            }

            $shipping = ($process_fee + $customer_order->get_total_shipping());

            //Tax
            $tax = $customer_order->get_total_tax();

            $orderData['customer_info'] = array(
                'first_name' => $customer_order->get_billing_first_name(),
                'last_name' => $customer_order->get_billing_last_name(),
                'email' => $customer_order->get_billing_email(),
                'phone' => $customer_order->get_billing_phone(),
                'address' => $customer_order->get_billing_address_1(),
                'address2' => $customer_order->get_billing_address_2(),
                'city' => $customer_order->get_billing_city(),
                'state' => $customer_order->get_billing_state(),
                'zip' => $customer_order->get_billing_postcode(),
                'country' => $customer_order->get_billing_country()
            );

            $orderData['billing_info'] = array(
                "bill_first_name" => $customer_order->get_billing_first_name(),
                "bill_last_name" => $customer_order->get_billing_last_name(),
                "bill_address_1" => $customer_order->get_billing_address_1(),
                "bill_address_2" => $customer_order->get_billing_address_2(),
                "bill_city" => $customer_order->get_billing_city(),
                "bill_region" => $customer_order->get_billing_state(),
                "bill_country" => $customer_order->get_billing_country(),
                "bill_postal_code" => $customer_order->get_billing_postcode(),
                "bill_email" => $customer_order->get_billing_email(),
                "bill_phone" => $customer_order->get_billing_phone(),
                "bill_company" => $customer_order->get_billing_company()
            );
//            $customerId = $this->getCustomerInfo($orderData['billing_info']);
//            //print_r($customerId);die;
//            if($customerId===false){
//                // Transaction was not succesful - Resetting Order Id
//                WC()->session->__unset('order_awaiting_payment');
//                // Add notice to the cart
//                $cartError = "Due to some technical issue we are unable to process your Order #{$order_id} . Please contact site admin.";
//                wc_add_notice($cartError, 'error');
//                // Add note to the order for your reference
//                $customer_order->add_order_note('Error: Order failed - Unable to create User in Hiecor CRM.');
//                return;
//            }
//            $orderData['cust_id'] = $customerId;
            $orderData['cust_id'] = '';

            $orderData['shipping_info'] = array(
                "ship_first_name" => $customer_order->get_shipping_first_name(),
                "ship_last_name" => $customer_order->get_shipping_last_name(),
                "ship_address_1" => $customer_order->get_shipping_address_1(),
                "ship_address_2" => $customer_order->get_shipping_address_2(),
                "ship_city" => $customer_order->get_shipping_city(),
                "ship_region" => $customer_order->get_shipping_state(),
                "ship_country" => $customer_order->get_shipping_country(),
                "ship_postal_code" => $customer_order->get_shipping_postcode()
            );

            $orderData['is_billing_same'] = "";

            $orderData['cart_info'] = array(
                "coupon" => $coupon,
                "products" => $productData,
                "subtotal" => "",
                "discount" => "",
                "manual_discount" => "",
                "shipping_handling" => $shipping,
                "custom_tax_id" => "",
                "total" => $customer_order->get_total()
            );

            $orderData['manual_tax'] = true;
            $orderData['tax'] = $tax;
            $orderData['merchant_id'] = "";
            $orderData['payment_type'] = "credit";
            $orderData['payment_method'] = "";
            if(isset($hcv4_settings['square_payment']) && $hcv4_settings['square_payment'] == 'yes') {
                $orderData['credit'] = array(
                    "merchant" => "squareup",
                    "nonce"=> $square_nonce,
                    "cc_account" => "",
                    "cc_exp_mo" => "",
                    "cc_exp_yr" => "",
                    "cc_cvv" => "",
                    "tip" => $tip,
                    "total" => $customer_order->get_total()
                );
            }else{
                $orderData['credit'] = array(
                    "cc_account" => str_replace(array(' ', '-'), '', sanitize_text_field($_POST['corcrm_payment-card-number'])),
                    "cc_exp_mo" => trim($cardExp[0]),
                    "cc_exp_yr" => trim($cardExp[1]),
                    "cc_cvv" => ( isset($_POST['corcrm_payment-card-cvc']) ) ? sanitize_text_field($_POST['corcrm_payment-card-cvc']) : '',
                    "tip" => $tip,
                    "total" => $customer_order->get_total()
                );
            }
            $option_data = get_option('woocommerce_corcrm_payment_settings');
            if(!empty($option_data['source'])){
                $orderData['order_source'] = $option_data['source'];
            }else{
                $orderData['order_source'] = "woocommerce";
            }
            //Override source if passed as query string which gets saved in $_SESSION['hiecor_order_source']
            if(!empty($_SESSION['hiecor_order_source']) ){
                $orderData['order_source'] = $_SESSION['hiecor_order_source'];
            }

            $orderData['user_comments'] = $customer_note;
            $orderData['crm_partner_order_id'] = str_replace("#", "", $customer_order->get_order_number());
            $orderData['sendOrderMail'] = "yes";
            $orderData['enable_split_row'] = true;
            $client_ip_address = $customer_order->get_customer_ip_address();
            $orderData['request_info'] = array(
                "client_ip" => $client_ip_address
            );
            $shippingData = self::getShippingData($order_id);
            if(!empty($shippingData)){
                $orderData['shipping_method'] = $shippingData;
            }
            //Send orderID if already placed an order and had any error earlier e.g. CVV invalid etc.
            $hiecor_orderid_sess_key = 'hiecor_order_id_'.$order_id;
            $hiecor_billingid_sess_key = 'hiecor_billing_profile_id_'.$order_id;

            $hiecorOrderID=WC()->session->get( $hiecor_orderid_sess_key );
            if(!empty($hiecorOrderID)){
                $orderData['orderId'] = $hiecorOrderID;
            }
            $hiecorBillingID=WC()->session->get( $hiecor_billingid_sess_key );
            if(!empty($hiecorBillingID)){
                $orderData['bill_profile_id'] = $hiecorBillingID;
            }

            $hicorClient = $crmUtility->getHiecorClient();

            $orderResp = $hicorClient->post('order/', $orderData);

            if (isset($orderResp->success) && $orderResp->success && !empty($orderResp->data) && empty($orderResp->error)) {
                // Set hiecor orderID into session for tracking code
                // Set hiecor orderID on unique session index (for unique index adding woocommerce order id) to avoid any false assignment
                WC()->session->set($hiecor_orderid_sess_key, $orderResp->data->order_id);
                // Store the corcrm order ID in the order meta
                update_post_meta($order_id, 'hiecor_order_id', $orderResp->data->order_id);
                // Payment has been successful
                $customer_order->add_order_note(__('CorCRM payment completed.', 'corcrm-secure-payments'));

                // Mark order as Paid
                $customer_order->payment_complete();

                // Empty the cart (Very important step)
                $woocommerce->cart->empty_cart();

                // Redirect to thank you page
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($customer_order),
                );
            } else {
                // Set hiecor orderid & billing_profile_id to avoid duplicate orders
                // Set hiecor orderID/billing_profile_id on unique session index (for unique index adding woocommerce order id) to avoid any false assignment
                if(isset($orderResp->data->order_id)){
                    WC()->session->set($hiecor_orderid_sess_key, $orderResp->data->order_id);
                }
                if(isset($orderResp->data->billing_profile_id)){
                    WC()->session->set($hiecor_billingid_sess_key, $orderResp->data->billing_profile_id);
                }
                // Transaction was not succesful - Resetting Order Id
                //WC()->session->__unset('order_awaiting_payment');
                // Add notice to the cart
                $cartError = (!empty($orderResp->error)) ? $orderResp->error:"Due to some technical issue we are unable to process your Order #{$order_id} . Please contact site admin.";
                wc_add_notice($cartError, 'error');
                // Add note to the order for your reference
                $customer_order->add_order_note('Error: ' .print_r($orderResp,true));
                self::logger("CorCRM Payment ERROR OrderID #{$order_id}: " .print_r($orderResp,true));
            }
        } catch (\Exception $ex) {

            wc_add_notice("Due to some technical issue we are unable to process your Order #{$order_id} . Please contact site admin.", 'error');
            $customer_order->add_order_note("Order #{$order_id} failed to process. Due to Hiecor API failure.");
            $log =  "CorCRM Payment Exception OrderID #{$order_id}: ".$ex->getMessage();
            if(isset($hicorClient)){
                $log.=" \nHiecorClient: ".print_r($hicorClient,true);
            }
            self::logger($log);
        }
    }

    public function getTipAmount($items)
    {
        global $paymentUtility;
        $tip_amount = '';
        foreach ($items as $item) {
            $product = $paymentUtility->getProduct($item['product_id']);
            $product_name = $item['name'];
            $sku = $product->get_sku();
            if (strtolower($product_name) == 'tip' || strtolower($sku) == 'tip') {
                $tip_amount = $item['subtotal'];
                break;
            }
        }
        return $tip_amount;
    }

    // Validate fields
    public function validate_fields()
    {
        $square_option = get_option('woocommerce_corcrm_payment_settings');
        //if square payment is enabled then no need to validate
        if(isset($square_option['square_payment']) && $square_option['square_payment'] == 'yes'){
            if(empty($_POST['square_nonce']) ){
                wc_add_notice(  "Unable to generate Hiecor square nonce.", 'error' );
                return false;
            }else{
                return true;
            }

        }
        // Start credit card validation
        $msg = '';
        $error = 0;

        // Validate card number
        $card_number = sanitize_text_field($_POST['corcrm_payment-card-number']);
        $card_number = str_replace(array(' ', '-'), '', $card_number);
        if(empty($card_number)) {
            $msg .= 'Credit card number is required.';
            $error = 1;
        }elseif(!$this->is_valid_card_number($card_number)){
            $msg .= 'Invalid card number';
            $error = 1;
        }

        // Validate card expiry
        $expiry_date = sanitize_text_field($_POST['corcrm_payment-card-expiry']);
        $expiry_date = str_replace(' ', '', $expiry_date);
        if(empty($expiry_date)) {
            $msg .= ',Expiry date is required.';
            $error = 1;
        }elseif (!$this->is_valid_expiry($expiry_date)){
            $msg .= ',Invalid expiry Month/Year. Expiry date must be a valid date in MM/YYYY or MM/YY format.';
            $error = 1;
        }

        // Validate CVC
        $cvc = sanitize_text_field($_POST['corcrm_payment-card-cvc']);
        if(empty($cvc)) {
            $msg .= ',CVC is required.';
            $error = 1;
        }elseif (!$this->is_valid_cvc($cvc)){
            $msg .= ',Invalid CVC';
            $error = 1;
        }

        // If there are any validation errors, add the notice
        if($error) {
            wc_add_notice(trim($msg, ','), 'error');
            return false;
        }
        return true;

    }

    // Check if we are forcing SSL on checkout pages
    // Custom function not required by the Gateway
    public function do_ssl_check() {
        if ($this->enabled == "yes") {
            if (get_option('woocommerce_force_ssl_checkout') == "no") {
                echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>"), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
            }
        }
    }

    public static function logger($msg) {
        $log = new WC_Logger();
        $log->add('api', $msg);
    }

    public static function getShippingData($order_id)
    {
        $customer_order = new WC_Order($order_id);
        $shipping_method = @array_shift($customer_order->get_shipping_methods());
        $shipping_data = array();
        if(empty($shipping_method)){
            return $shipping_data; //do nothing
        }
        $method= "";
        if (isset($shipping_method['method_id']) && $shipping_method['method_id'] ==  "local_pickup") {
            $method= "local_pickup";
        }
        if (isset($shipping_method['method_id']) && strpos($shipping_method['method_id'], 'usps') !==false) {
            $method= "usps";
        }
        if (isset($shipping_method['method_id']) && strpos($shipping_method['method_id'], 'ups') !==false) {
            $method= "ups";
        }
        if (isset($shipping_method['method_id']) && strpos($shipping_method['method_id'], 'fedex') !==false) {
            $method= "fedex";
        }
        $shipping_data["shipping_method_id"] =  $method;
        $shipping_data["shipping_method_name"] = $method;
        $shipping_data["shipping_method_title"] = isset($shipping_method['method_title']) ? $shipping_method['method_title']:'';
        if($shipping_method['method_id'] == "local_pickup"){
            $shipping_data["shipping_method_service"] = "";
        }else{
            $shipping_data["shipping_method_service"] = isset($shipping_method['name']) ? $shipping_method['name']:'';
        }
        $shipping_data["shipping_method_total"] = isset($shipping_method['total']) ? $shipping_method['total']:0;

        if(empty($shipping_data["shipping_method_id"])){
            return array();
        }else{
            return $shipping_data;
        }

    }

    // Validate credit card number format (Luhn algorithm can be used for real validation)
    private function is_valid_card_number($card_number)
    {
        return preg_match('/^[0-9]{13,19}$/', $card_number); // Basic length check (13-19 digits)
    }

    // Validate expiry date (MM/YY or MM/YYYY)
    private function is_valid_expiry($expiry_date)
    {
        // Match the format MM/YY or MM/YYYY
        if (preg_match('/^(0[1-9]|1[0-2])\/(2[0-9]{1,3})$/', $expiry_date, $matches)) {
            $month = $matches[1];
            $year = $matches[2];
            // Handle the MM/YY format by converting to MM/YYYY
            if (strlen($year) == 2) {
                $year = '20' . $year; // Assume 20XX for 2-digit year format
            }
            $current_year = date('Y');
            $current_month = date('m');

            // Check if the expiry date is in the future
            return ($year > $current_year || ($year == $current_year && $month >= $current_month));
        }
        return false; // Return false if format doesn't match
    }

    // Validate CVC (typically 3 or 4 digits)
    private function is_valid_cvc($cvc)
    {
        return preg_match('/^[0-9]{3,4}$/', $cvc);
    }
}

// End of Corcrm_Payment