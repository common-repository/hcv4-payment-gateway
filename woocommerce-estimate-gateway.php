<?php

/* CorCRM Payment Gateway Class */
include_once( 'Corcrm_Utility.php' );
global $crmUtility;
$crmUtility = new Corcrm_Utility();


include_once( 'Payment_Utility.php' );
global $paymentUtility;
$paymentUtility = new Payment_Utility();

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Gateway_Estimate extends WC_Payment_Gateway {

    public function __construct()
    {
        $this->id                 = 'estimate';
        $this->icon               = '';
        $this->has_fields         = false;
        $this->method_title       = __('Estimate', 'woocommerce');
        $this->method_description = __('Allows customers to place orders without payment information. Orders will be processed as estimates.', 'woocommerce');

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Payment listener/API hook
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

        // Custom thank you page
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        
        // Filter to modify the order number on thankyou page(show corcrm order id)
        add_filter('woocommerce_order_number', array($this, 'custom_order_number'), 10, 2);

        // Prevent stock reduction for estimate payment method
        add_filter( 'woocommerce_can_reduce_order_stock', array($this,'prevent_stock_reduce_for_estimate'), 10, 2 );
 
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Enable Estimate Payment', 'woocommerce'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('Title', 'woocommerce'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default'     => __('Estimate', 'woocommerce'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'woocommerce'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                'default'     => __('Place an order without payment information. The order will be processed as an estimate.', 'woocommerce')
            ),
            'order_source' => array(
                'title' => __('Source', 'woocommerce'),
                'type' => 'text',
                'desc_tip' => __('This is the source of order.', 'woocommerce'),
                'default' => __('Website', 'woocommerce'),
            )
        );
    }

    public function process_payment($order_id)
    {
        global $woocommerce;
        global $paymentUtility;
        global $crmUtility;

        try {
            $option_data = get_option('woocommerce_estimate_settings');
        // Get this Order's information
        $customer_order = new WC_Order($order_id);
        //$shipping_method = @array_shift($customer_order->get_shipping_methods());
       // $shipping_method_name = $shipping_method['name'];
        
        $items = $customer_order->get_items();
        $productData = $paymentUtility->getProductsData($items);
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
        $invoiceData['cust_id'] = '';
        $invoiceData['smartlist_id'] = '';
        $invoiceData['customer_info'] = array(
                    'first_name' => $customer_order->get_billing_first_name(),
                    'last_name' => $customer_order->get_billing_last_name(),
                    'email' => $customer_order->get_billing_email(),
                    'phone' => $customer_order->get_billing_phone(),
                    'address' => $customer_order->get_billing_address_1(),
                    'address2' => $customer_order->get_billing_address_2(),
                    'city' => $customer_order->get_billing_city(),
                    'country' => $customer_order->get_billing_country(),
                    'state' => $customer_order->get_billing_state(),
                    'zip' => $customer_order->get_billing_postcode(),
                );
        $invoiceData['bill_profile_id'] = '';
        $invoiceData['billing_info'] = array(
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
        $invoiceData['shipping_info'] = array(
            "ship_first_name" => $customer_order->get_shipping_first_name(),
            "ship_last_name" => $customer_order->get_shipping_last_name(),
            "ship_address_1" => $customer_order->get_shipping_address_1(),
            "ship_address_2" => $customer_order->get_shipping_address_2(),
            "ship_city" => $customer_order->get_shipping_city(),
            "ship_region" => $customer_order->get_shipping_state(),
            "ship_country" => $customer_order->get_shipping_country(),
            "ship_postal_code" => $customer_order->get_shipping_postcode()
        );
        $invoiceData['is_billing_same'] = "";
         
         $invoiceData['cart_info'] = array(
                "coupon" => $coupon,
                "products" => $productData,
                "subtotal" => "",
                "discount" => "",
                "manual_discount" => "",
                "shipping_handling" => $shipping,
                "custom_tax_id" => "",
                "total" => $customer_order->get_total()
            );

            $invoiceData['isSaveInvoice'] = "";
            $invoiceData['manual_tax'] = true;
            $invoiceData['tax'] = $tax;
            $invoiceData['merchant_id'] = "";
            $invoiceData['payment_type'] = "credit";
            $invoiceData['payment_method'] = "";
            if (!empty($option_data['order_source'])) {
                $invoiceData['order_source'] = $option_data['order_source'];
            } else {
                $invoiceData['order_source'] = "Website";
            }
            $customer_note = $customer_order->get_customer_note();
            $invoiceData['user_comments'] = $customer_note;
            $invoiceData['crm_partner_order_id'] = str_replace("#", "", $customer_order->get_order_number());
            $invoiceData['sendOrderMail'] = "yes";
            $invoiceData['enable_split_row'] = true;
            $client_ip_address = $customer_order->get_customer_ip_address();
            $invoiceData['request_info'] = array(
                                            "client_ip" => $client_ip_address
                                        );
            
        // Call crm invoice API with invoice details
        $hicorClient = $crmUtility->getHiecorClient();
        $invoiceResp = $hicorClient->post('invoice/', $invoiceData);
        if (isset($invoiceResp->success) && $invoiceResp->success && !empty($invoiceResp->data) && empty($invoiceResp->error)) {
               $customer_order->add_order_note(__('Estimate order completed.', 'Estimate'));
               // Mark as on-hold (we're awaiting the estimate)
               $customer_order->update_status('on-hold', __('Awaiting estimate', 'woocommerce'));
               $woocommerce->cart->empty_cart();
               $orderId = $invoiceResp->data->order_id;
               // Store the corcrm order ID in the order meta
                update_post_meta($order_id, 'hiecor_order_id', $orderId);
               $customOrderStatus = $invoiceResp->data->customOrderStatus;
               $msg = self::update_delivery_status($orderId,$customOrderStatus);
               $customer_order->add_order_note('Estimate: ' .print_r($msg,true));
               return array(
                   'result' => 'success',
                   'redirect' => $this->get_return_url($customer_order),
               );
           } else {
               // Add notice to the cart
               $cartError = (!empty($invoiceResp->error)) ? $invoiceResp->error:"Due to some technical issue we are unable to process your Order #{$order_id} . Please contact site admin.";
               wc_add_notice($cartError, 'error');
               $customer_order->add_order_note('Error: ' .print_r($invoiceResp,true));
               $paymentUtility->logger("Estimate order ERROR OrderID #{$order_id}: " .print_r($invoiceResp,true));
           }
        } catch (\Exception $ex) {

            wc_add_notice("Due to some technical issue we are unable to process your Order #{$order_id} . Please contact site admin.", 'error');
            $customer_order->add_order_note("Order #{$order_id} failed to process. Due to Hiecor API failure.");
            $log =  "Estimate order Exception OrderID #{$order_id}: ".$ex->getMessage();
            $paymentUtility->logger($log);
        }
    }
    
     

    public function thankyou_page() {
        // Custom thank you page content
        echo '<p>' . __('Thank you for your order. Your estimate will be processed shortly.', 'woocommerce') . '</p>';
    }

    public function receipt_page($order) {
        echo '<p>' . __('Thank you for your order. Your estimate will be processed shortly.', 'woocommerce') . '</p>';
    }
    
    public function update_delivery_status($order_id,$customOrderStatus) {
        if (!empty($customOrderStatus)) {
            $status_id = '';
            foreach ($customOrderStatus as $key => $value) {
                if(strtolower($value->name) == "estimate"){
                   $status_id =  $value->status_id;
                }
            }
        }
        
        if(!empty($order_id) && !empty($status_id)){
            global $crmUtility;
            global $paymentUtility;
            $hicorClient = $crmUtility->getHiecorClient();
            $data = array('order_id' => $order_id,'status_id'=>$status_id);
            try {
                $resp = $hicorClient->post('order/update-delivery-status/', $data);
                if (isset($resp->success) && $resp->success && !empty($resp->data)) {
                    return $resp->data->message;
                } else {
                      $log = 'Unable to update delivery status in Hiecor CRM.';
                      $paymentUtility->logger($log);
                }
            } catch (\Exception $ex) {
                $log = "Estimate Exception OrderID #{$order_id}: ".$ex->getMessage().'Error occured during update delivery status.';
                $paymentUtility->logger($log);
                return $ex->getMessage();
           }
        }
    }
    
     public function custom_order_number($order_number, $order) {
        // Only change the order number for this specific estimate payment gateway
        //if ($order->get_payment_method() === $this->id) {
            $hiecor_order_id = get_post_meta($order->get_id(), 'hiecor_order_id', true);
            if (!empty($hiecor_order_id)) {
                return $hiecor_order_id;
            }
        //}
        return $order_number;
    }

    function prevent_stock_reduce_for_estimate( $reduce_stock, $order ) {
        if ( $order->get_payment_method() == 'estimate' ) {
            $reduce_stock = false;
        }
        return $reduce_stock;
    }
    
    
}