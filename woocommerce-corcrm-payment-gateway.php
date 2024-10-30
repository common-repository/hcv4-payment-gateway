<?php

/*
  Plugin Name: HieCOR Payment Gateway Plugin
  Plugin URI: http://www.hiecor.com/
  Description: This is a plugin will enable automatic inventory sync and accept payments with your HieCOR system.
  Version: 1.4.8
  WC tested up to: 8.8.3
  Author: HieCOR
  
 */


// If we made it this far, then include our Gateway Class
include_once( 'hc-rest/autoload.php' );
include_once( 'Corcrm_Utility.php' );
include_once( 'hiecor_routes.php' );//custom rest APIs for hiecor CRM
include_once( 'hiecor_attr_mapping.php' );//hiecore attribute mapping
include_once('hiecor-iframe-modal.php');   //opening Iframe model pos


global $crmUtility;
$crmUtility = new Corcrm_Utility();

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action('plugins_loaded', 'corcrm_payment_init', 0);

function corcrm_payment_init() {
    // If the parent WC_Payment_Gateway class doesn't exist
    // it means WooCommerce is not installed on the site
    // so do nothing
    if (!class_exists('WC_Payment_Gateway'))
        return;

    // If we made it this far, then include our Gateway Class
    include_once( 'woocommerce-corcrm-payment.php' );
    // Include Estimate gateway class
    include_once('woocommerce-estimate-gateway.php');

    // Now that we have successfully included our class,
    // Lets add it too WooCommerce
    add_filter('woocommerce_payment_gateways', 'corcrm_payment_gateway');

}
function corcrm_payment_gateway($methods) {
    $methods[] = 'Corcrm_Payment';
    $methods[] = 'WC_Gateway_Estimate';
    return $methods;

}

// Add custom action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'corcrm_payment_action_links');

function corcrm_payment_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=corcrm_payment') . '">' . __('Settings', 'corcrm_payment') . '</a>',
    );

    // Merge our new link with the default ones
    return array_merge($plugin_links, $links);
}

// run the install scripts upon plugin activation
register_activation_hook(__FILE__, 'corcrm_plugin_install_script');

function corcrm_plugin_install_script($network_wide) {
    global $wpdb;
    if ( is_multisite() && $network_wide ) {
        // Get all blogs in the network and activate plugin on each one
        $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
        foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            create_hiecor_table();
            restore_current_blog();
        }
    } else {
        create_hiecor_table();
    }
}

function create_hiecor_table(){
    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $table_name = $wpdb->prefix . 'posts';
    $db_name = $wpdb->dbname;
    // create the corcrm_product_id colmn in posts table
    $sql = "SELECT COLUMN_NAME 
                FROM information_schema.COLUMNS 
                WHERE 
                    TABLE_SCHEMA = '" . $db_name . "' 
                AND TABLE_NAME = '" . $table_name . "' 
                AND COLUMN_NAME = 'corcrm_product_id'";

    if ($wpdb->get_var($sql) != 'corcrm_product_id') {

        $sql = "CREATE TABLE $table_name (
                            corcrm_product_id INT(11) NULL DEFAULT 0,
                       );";


        dbDelta($sql);
    }

    $mappingTable = $wpdb->prefix . 'hiecor_attr_mapping';
    if ($wpdb->get_var("SHOW TABLES LIKE '$mappingTable'") != $mappingTable) {
        $mappingTableSql = "CREATE TABLE `$mappingTable` (
            `mapping_id` int(6) NOT NULL AUTO_INCREMENT,
            `wc_product_id` int(11),
            `mapping_type` varchar(255) NOT NULL,
            `wc_option` varchar(255),
            `foreign_id` int(11) DEFAULT '0',
            `hiecor_id` int(11) DEFAULT '0',
            `status` int(1) NOT NULL DEFAULT '1',
            PRIMARY KEY (`mapping_id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
        dbDelta($mappingTableSql);
    }
}
// Creating table whenever a new blog is created
function on_create_new_hiecor_sub_site( $new_site ) {
    global $wpdb;
    $blog_id = $new_site->blog_id;
    if(is_multisite() && !empty($blog_id)){
        if ( is_plugin_active_for_network( 'hcv4-payment-gateway/woocommerce-corcrm-payment-gateway.php' ) ) {
            switch_to_blog( $blog_id );
            create_hiecor_table();
            restore_current_blog();
        }
    }
}
//Fires once a site has been inserted into the database.
add_action( 'wp_initialize_site', 'on_create_new_hiecor_sub_site', 11 );

if (isset($_POST['action']) && $_POST['action'] == 'editpost' && $_POST['post_type'] == 'shop_coupon') {
    add_action('save_post', 'coupon_to_corcrm', 10, 3);
}

function coupon_to_corcrm($post_id, $post, $update) {
    global $crmUtility;
    // If this isn't a 'product' post, don't go further
    if ($post->post_type == 'shop_coupon' && $post->post_status == 'publish') {
        $crmUtility->push_coupon_to_corcrm($post_id, $post, $update, $_POST);
    }
}

add_action('wp_trash_post', 'delete_product_corcrm', 10, 3);

function delete_product_corcrm($post_id) {
    global $crmUtility;
    global $wpdb;
    // If this is a 'product' post
    $post_type=get_post_type($post_id);
    if($post_type == 'product') {
        $data=wp_update_post(
            array(
                'ID'          => $post_id,
                'post_status' => 'trash',
            )
        );
        if(isset($data)){
            $mappingTable = $wpdb->prefix . 'hiecor_attr_mapping';
            $sql = "DELETE
                FROM $mappingTable WHERE
                `wc_product_id`={$post_id}";
            $result =$wpdb->query($sql);
            if(!$result && $wpdb->last_error!=''){
                $log = new WC_Logger();
                $errors = "SQL ERROR:-".$wpdb->last_error."\n SQL EXECUTED:-".$wpdb->last_query."\n";
                $log->add('api', $errors);
            }
            $crmUtility->delete_product_to_corcrm($post_id);
        }
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'editpost') {
    add_action('save_post', 'save_to_corcrm', 10, 3);
}

function save_to_corcrm($post_id, $post, $update) {
    global $crmUtility;
    // If this isn't a 'product' post, don't go further
    if ($post->post_type == 'product' && ($post->post_status == 'publish' || $post->post_status == 'private')) {
        $crmUtility->push_to_corcrm($post_id);
    }

}

add_action('admin_notices', 'hiecor_sync_admin_notice', 10 ,3);
function hiecor_sync_admin_notice() {
    if(isset($_SESSION['hiecor_invt_sync_failed']) && $_SESSION['hiecor_invt_sync_failed'] == "Yes"){
        echo '<div class="notice notice-warning is-dismissible"><p>Product sync to HieCOR failed due to misconfiguration. Variation\'s "Manage Stock" mismatched.</p></div>';
        unset($_SESSION['hiecor_invt_sync_failed']);
    }
    //Coupon sync to hiecor failed display error in wp-admin
    if(!empty($_SESSION['hiecor_coupon_sync_failed'])){
        echo '<div class="error notice is-dismissible"><p>Coupon sync to HieCOR failed. ERROR-'.$_SESSION['hiecor_coupon_sync_failed'].'</p></div>';
        unset($_SESSION['hiecor_coupon_sync_failed']);
    }
    if(isset($_SESSION['hiecor_prod_sync_failed'])){
        echo '<div class="notice notice-warning is-dismissible"><p>Product sync to HieCOR failed. Error-'.$_SESSION['hiecor_prod_sync_failed'].'</p></div>';
        unset($_SESSION['hiecor_prod_sync_failed']);
    }
}
/*
// add the action 
add_action('woocommerce_add_to_cart', 'action_woocommerce_add_to_cart', 10, 6);
// define the woocommerce_add_to_cart callback 
function action_woocommerce_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
   
    global $crmUtility;
//    $variation_id = 0;
//    $product_id=0;
//    if (isset($_POST['product_id'])) {
//        $product_id = intval($_POST['product_id']);
//    }
//
//    if (isset($_POST['add-to-cart'])) {
//        $product_id = intval($_POST['add-to-cart']);
//    }
//
//    if (isset($_POST['variation_id']) && $_POST['variation_id'] > 0) {
//        $variation_id = intval($_POST['variation_id']);
//    }
    
    if ($product_id > 0) {
        $crmUtility->check_corcrm_existance($product_id);
    }
}
 * 
 */

add_action('admin_enqueue_scripts', 'product_type_enqueue');

function product_type_enqueue($hook) {
    wp_enqueue_script('my_custom_script', plugin_dir_url(__FILE__) . 'js/type.js');
}

/*
 * Added By Tarun on 21-01-2016
 * This will display CorCRM product id in woocommerce product general data tab
 */

add_action('woocommerce_product_options_general_product_data', 'corcrm_add_custom_general_fields');

function corcrm_add_custom_general_fields() {
    global $post;
    global $wpdb;
    $corcrm_product_id = (!empty($post->corcrm_product_id)) ? $post->corcrm_product_id : '';
    if(empty($corcrm_product_id)){
        $query = $wpdb->prepare("SELECT corcrm_product_id FROM {$wpdb->posts} WHERE ID = {$post->ID}");
        $corcrm_product_id = $wpdb->get_var($query);
    }
    if(empty($corcrm_product_id)){
        $corcrm_product_id =  get_post_meta($post->ID, 'hiecor_product_id', true);
    }
    $subscriptionInterval = get_post_meta($post->ID, 'subscription_interval', true);
    $subscriptionLifetime = get_post_meta($post->ID, 'subscription_lifetime', true);
    $subscriptionLifetimeCheckbox = get_post_meta($post->ID, 'subscription_lifetime_checkbox', true);

    woocommerce_wp_text_input(
        array(
            'id' => 'corcrm_prod_id',
            'name' => 'corcrm_prod[]',
            'label' => __('Hiecor Product ID', 'woocommerce'),
            'placeholder' => '',
            'value' => $corcrm_product_id,
            'type' => 'text',
            'custom_attributes' => array()
        )
    );

    // Product type( straight/subscription ) select  box for simple products
    $type_field = array(
        'id' => 'corcrm_custom_product_type',
        'label' => __('Hiecor Product type', 'textdomain'),
        'options' => array(
            'straight' => __('Straight', 'straight'),
            'subscription' => __('Subscription', 'subscription')
        )
    );
    woocommerce_wp_select($type_field);
    // Product type( straight ) allow hiecore subscription checkbox
    woocommerce_wp_checkbox(
        array(
            'id' => 'allow_hiecor_subscription',
            'label' => __('Allow Hiecor Subscription', 'woocommerce')
        )
    );

    $sub_opt=array();
    $sub_opt[0]['name']="One-Time Purchase (No Subscription)";
    $sub_opt[0]['value']="";
    $sub_opt[1]['name']="Deliver Every Week (7 days)";
    $sub_opt[1]['value']="7 days";
    $sub_opt[2]['name']="Deliver Every 2 Week (14 days)";
    $sub_opt[2]['value']="14 days";
    $sub_opt[3]['name']="Deliver Every Month (1 month)";
    $sub_opt[3]['value']="1 months";
    $sub_opt[4]['name']="Deliver Every 2 Months (2 months)";
    $sub_opt[4]['value']="2 months";

    echo "<div class='subscription_options' style='margin-left:162px;'>
            <ul>";
    foreach ($sub_opt as $key => $value) {
        echo '<li>'. $value['name'];
        echo '<input type="hidden" name="subscription_options['.$key.'][name]" value="'.$value['name'].'"/>';
        echo '<input type="hidden" name="subscription_options['.$key.'][value]" value="'.$value['value'].'"/>';
        echo  '</li>';
    }
    echo "</ul></div>";
    // Subscription Duration Select field for simple products
    $subscription_duration = array(
        'id' => 'corcrm_custom_subscription_time',
        'label' => __('CRM Subscription Time', 'woocommerce'),
        'options' => array(
            'months' => __('Months', 'woocommerce'),
            'days' => __('Days', 'woocommerce'),
        )
    );
    woocommerce_wp_select($subscription_duration);

    // Subscription period for simple products

    woocommerce_wp_text_input(
        array(
            'id' => 'hiecor_subscription_interval',
            'wrapper_class' => 'hiecor_subscription_interval',
            'name' => 'subscription_interval',
            'label' => __('Subscription Interval', 'woocommerce'),
            'placeholder' => 'No of Days/Months',
            'value' => $subscriptionInterval,
            'type' => 'text',
            'custom_attributes' => array()
        )
    );

    woocommerce_wp_checkbox(
        array(
            'id' => 'subscription_lifetime_checkbox',
            'wrapper_class' => 'subscription_lifetime',
            'label' => __('Subscription Lifetime', 'woocommerce'),
            'description' => __('Infinite', 'woocommerce'),
        )
    );

    woocommerce_wp_text_input(
        array(
            'id' => 'enter_subscription_lifetime',
            'wrapper_class' => 'enter_subscription_lifetime',
            'name' => 'subscription_lifetime',
            'label' => __('Enter Subscription Lifetime', 'woocommerce'),
            'placeholder' => 'Lifetime in Days/Months',
            'value' => $subscriptionLifetime,
            'type' => 'text',
            'custom_attributes' => array()
        )
    );
}

// Saving prodct type and subscription duration in meta
add_action('woocommerce_process_product_meta', 'save_custom_field');

function save_custom_field($post_id) {

    $corcrm_custom_product_type = isset($_POST['corcrm_custom_product_type']) ? sanitize_text_field($_POST['corcrm_custom_product_type']) : '';
    $corcrm_custom_subscription_time = isset($_POST['corcrm_custom_subscription_time']) ? sanitize_text_field($_POST['corcrm_custom_subscription_time']) : '';
    $subscription_interval = isset($_POST['subscription_interval']) ? sanitize_text_field($_POST['subscription_interval']) : '';
    $subscription_lifetime_checkbox = isset($_POST['subscription_lifetime_checkbox']) ? sanitize_text_field($_POST['subscription_lifetime_checkbox']) : '';
    $subscription_lifetime = isset($_POST['subscription_lifetime']) ? sanitize_text_field($_POST['subscription_lifetime']) : '';
    $allowHiecorSub = isset($_POST['allow_hiecor_subscription']) ? sanitize_text_field($_POST['allow_hiecor_subscription']) : 'no';
    $subscription_options = (isset($_POST['subscription_options']) && $allowHiecorSub=='yes') ? wp_unslash($_POST['subscription_options']) : array();

    $product = wc_get_product($post_id);
    $product->update_meta_data('corcrm_custom_product_type', $corcrm_custom_product_type);
    $product->update_meta_data('corcrm_custom_subscription_time', $corcrm_custom_subscription_time);
    $product->update_meta_data('subscription_interval', $subscription_interval);
    $product->update_meta_data('subscription_lifetime_checkbox', $subscription_lifetime_checkbox);
    $product->update_meta_data('subscription_lifetime', $subscription_lifetime);
    $product->update_meta_data('allow_hiecor_subscription', $allowHiecorSub);
    $product->update_meta_data('hiecor_subscription_options',$subscription_options);
    $product->save();
}
// Add Variation Settings
add_action('woocommerce_product_after_variable_attributes', 'corcrm_variation_settings_fields', 10, 3);
// Save Variation Settings
add_action('woocommerce_save_product_variation', 'corcrm_save_variation_settings_fields', 10, 2);

function corcrm_variation_settings_fields($loop, $variation_data, $variation) {
    global $wpdb;

    $hiecor_var_id = !empty($variation->corcrm_product_id) ? $variation->corcrm_product_id:'';
    if(empty($hiecor_var_id)){
        $query = $wpdb->prepare("SELECT corcrm_product_id FROM {$wpdb->posts} WHERE ID = {$variation->ID}");
        $hiecor_var_id = $wpdb->get_var($query);
    }
    if(empty($hiecor_var_id)){
        $hiecor_var_id =  get_post_meta($variation->ID, 'hiecor_product_id', true);
    }

    woocommerce_wp_text_input(
        array(
            'id' => 'corcrm_prod',
            //'name' => 'corcrm_prod['.$loop.']',
            'name' => 'corcrm_prod_var_'.$variation->ID,
            'label' => __('Hiecor Variation ID', 'woocommerce'),
            'placeholder' => 'Hiecor Variation ID',
            'desc_tip' => 'true',
            'description' => __('This is Hiecor Variation ID.', 'woocommerce'),
            'value' => $hiecor_var_id,
            'custom_attributes' => array()
        )
    );

    // For product type straight or subsscription

//    $options = array(
//        'straight' => __('Straight', 'straight'),
//        'subscription' => __('Subscription', 'subscription')
//    );
//    $type = get_post_meta($variation->ID, '_corcrm_custom_product_type', true);
//    if ($type == "subscription") {
//        $options = array('subscription' => __('Subscription', 'subscription'));
//    }
//    echo '<div class="variation-custom-fields">';
//    // Product Type select field for variation product
//
//    woocommerce_wp_select(array(
//        'id' => 'corcrm_custom_product_type_' . $variation->ID,
//        'class' => 'crm_select_class',
//        'label' => __('CorCRM Product type', 'woocommerce'),
//        'value' => get_post_meta($variation->ID, '_corcrm_custom_product_type', true),
//        'options' => $options,
//        'custom_attributes' => array(),
//    ));
//    echo '</div>';

//    if (get_post_meta($variation->ID, '_corcrm_custom_product_type', true) == 'straight') {
//        $display = 'style="display:none;"';
//    } elseif (get_post_meta($variation->ID, '_corcrm_custom_product_type', true) == 'subscription') {
//        $display = 'style="display:block;"';
//    } else {
//        $display = 'style="display:none;"';
//    }
//
//    echo '<div class="variation-custom-fields-times" ' . $display . '>';
//
//    // Subscription Duration select field for variation product
//    woocommerce_wp_select(array(
//        'id' => 'corcrm_custom_subscription_time_' . $variation->ID,
//        'label' => __('CRM Subscription Time', 'woocommerce'),
//        'value' => get_post_meta($variation->ID, '_corcrm_custom_subscription_time', true),
//        'options' => array(
//            '' => __('Select', ''),
//            'day' => __('Day', 'day'),
//            'month' => __('Month', 'month'),
//        )
//    ));
//    echo '</div>';
}

/**
 * Save new fields for variations
 *
 */
function corcrm_save_variation_settings_fields($post_id) {

    global $wpdb;

    //$hiecor_corcrm_prod = wc_clean(wp_unslash($_POST['corcrm_prod']));
    //if (isset($hiecor_corcrm_prod) && count($hiecor_corcrm_prod) > 0) {
    $hiecor_variable_post = wc_clean(wp_unslash($_POST['variable_post_id']));
    if (isset($hiecor_variable_post) && count($hiecor_variable_post) > 0) {
        $hiecor_variable_post = array_values($hiecor_variable_post);
        foreach ($hiecor_variable_post as $key => $value) {
            $corId = wc_clean(wp_unslash($_POST['corcrm_prod_var_'.$value]));
            $wooVarId = $value;
            if ($corId > 0) {
                $upd = $wpdb->update($wpdb->prefix . 'posts', array('corcrm_product_id' => $corId), array('id' => $wooVarId));
            }
        }
    }
    //}

    // Save variation settings for product type straight or subscription
    // Save product type - straight/subscription
//    $select = esc_attr($_POST["corcrm_custom_product_type_$post_id"]);
//    if (!empty($select)) {
//        update_post_meta($post_id, '_corcrm_custom_product_type', $select);
//    }
//
//    // Save subsscription duration
//    $select = esc_attr($_POST["corcrm_custom_subscription_time_$post_id"]);
//    if (!empty($select)) {
//        update_post_meta($post_id, '_corcrm_custom_subscription_time', $select);
//    }

}

add_action('woocommerce_process_product_meta_simple', 'woo_add_custom_general_fields_save');

function woo_add_custom_general_fields_save($post_id) {

    global $wpdb;
    $hiecor_corcrm_prod_fields = wc_clean(wp_unslash($_POST['corcrm_prod']));
    if (isset($hiecor_corcrm_prod_fields) && is_array($hiecor_corcrm_prod_fields)) {
        foreach ($hiecor_corcrm_prod_fields as $key => $corid) {
            if ($corid > 0) {
                $upd = $wpdb->update($wpdb->prefix . 'posts', array('corcrm_product_id' => $corid), array('id' => $post_id), $format = null, $where_format = null);
            }
        }
    }
}

// 01-APR-2016 By Tarun
add_filter('woocommerce_credit_card_form_fields', 'corcrm_custom_wc_checkout_fields');

function corcrm_custom_wc_checkout_fields($fields) {
    $fields['card-expiry-field'] = '<p class="form-row form-row-first">
				<label for="corcrm_payment-card-expiry">' . __('Expiry (MM/YYYY)', 'woocommerce') . ' <span class="required">*</span></label>
				<input id="corcrm_payment-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="' . esc_attr__('MM / YYYY', 'woocommerce') . '" name="corcrm_payment-card-expiry" />
			</p>';
    return $fields;
}

/*
 * To set the order status to complete after successfull payment
 */
$orderStaus = $crmUtility->get_settings('order_status');
if ($orderStaus == "complete") {
    add_filter('woocommerce_payment_complete_order_status', 'corcrm_change_status_function');
}

function corcrm_change_status_function() {
    return 'completed';
}



// Allow wp-admin to be loaded in Iframe by removing the option header
remove_action('admin_init', 'send_frame_options_header', 10);
remove_action('login_init', 'send_frame_options_header', 10);
//Insert corcrm visit plugin code to site footer

add_action('wp_footer', 'corcrm_insert_visit_code');

function corcrm_insert_visit_code() {
    $get_settings = get_option('woocommerce_corcrm_payment_settings', null);
    $get_url = parse_url($get_settings['hiecor_url']);
    if ($get_settings['visit_plugin'] == "yes") {
        ?>
        <script type="text/javascript">
            var custom1 = "", custom2 = "", custom3 = "", _cords = [], ua_agent = "CORUA-87";
            _cords.push(contentType = document.contentType);
            _cords.push(Date.now());
            _cords.push(window.location.pathname);
            _cords.push(window.location.protocol + "//" + window.location.hostname + "" + window.location.pathname);
            _cords.push(custom1);
            _cords.push(custom2);
            _cords.push(custom3);
            _cords.push(ua_agent);
            _cords.push("<?php echo $get_url['host']; ?>");

            (function () {
                var corcrm = document.createElement("script");
                corcrm.type = "text/javascript";
                corcrm.async = true;
                var file_loc = window.location.protocol;
                if (file_loc == "file:" || file_loc == "about:") {
                    file_loc = "https:";
                }
                corcrm.src = file_loc + "//<?php echo $get_url['host']; ?>/includes/plugins/visit/hiecor_visit.js";
                var s = document.getElementsByTagName("script")[0];
                s.parentNode.insertBefore(corcrm, s);
            })();

        </script>
        <?php
    }
}

add_action('init', 'hiecor_track_session');
function hiecor_track_session(){
    if( !session_id() ){
        //session_start();
    }
}

//Add track code into session
add_action('wp_head', 'hiecor_save_track_code');

function hiecor_save_track_code() {
    if (!empty($_GET['track_code'])) {
        $_SESSION['hiecor_track_code']= esc_attr($_GET['track_code']);
        $_SESSION['hiecor_at_id']= esc_attr($_GET['at_id']);
    }
    if(!empty($_GET['source'])){
        $_SESSION['hiecor_order_source']= esc_attr($_GET['source']);
    }
}

add_filter('woocommerce_thankyou_order_received_text', 'hiecor_woocommerce_thankyou_order_received_text', 10, 2);

function hiecor_woocommerce_thankyou_order_received_text($var, $order)
{
    $wc_order_id = $order->get_order_number();
    $hiecor_orderid_sess_key = 'hiecor_order_id_'.$wc_order_id;
    $hiecor_billingid_sess_key = 'hiecor_billing_profile_id_'.$wc_order_id;
    $log = new WC_Logger();
    $logData = '';
    $logData.=$hiecor_orderid_sess_key.' ---> '.WC()->session->get( $hiecor_orderid_sess_key );
    $logData.=' || '.$hiecor_billingid_sess_key.' ---> '.WC()->session->get( $hiecor_billingid_sess_key );
    $log->add('api', $logData);
    if(!empty($_SESSION['hiecor_track_code'])){
        // make filter magic happen here... 
        $url = parse_url(get_site_url());
        $final_url = $url['host'];
        $get_settings = get_option('woocommerce_corcrm_payment_settings', null);
        $hiecor_track_code     = $_SESSION['hiecor_track_code'];
        $hiecor_at_id     = $_SESSION['hiecor_at_id'];
        $destination       = $final_url;
        $hiecor_order_id      = WC()->session->get( $hiecor_orderid_sess_key );
        $hiecor_url = rtrim($get_settings['hiecor_url'],"/");
        $pixel = $hiecor_url .'/pixel/' . $hiecor_track_code . '/' . $destination . '/?order_id=' . $hiecor_order_id.'&at_id='.$hiecor_at_id;
        $var .= file_get_contents($pixel);
        unset($_SESSION['hiecor_track_code']);
        unset($_SESSION['hiecor_at_id']);
        unset(WC()->session->$hiecor_orderid_sess_key);
        unset(WC()->session->$hiecor_billingid_sess_key);
        // Just playing safer
        WC()->session->__unset($hiecor_orderid_sess_key);
        WC()->session->__unset($hiecor_billingid_sess_key);
        return $var;
    }else{
        unset(WC()->session->$hiecor_orderid_sess_key);
        unset(WC()->session->$hiecor_billingid_sess_key);
        // Just playing safer
        WC()->session->__unset($hiecor_orderid_sess_key);
        WC()->session->__unset($hiecor_billingid_sess_key);
        return $var;
    }

}

function corcrm_hide_shipping($rates) {
    $free = array();
    if (is_array($rates) || is_object($rates)){
        foreach ($rates as $rate_id => $rate) {
            if ('free_shipping' === $rate->method_id) {
                $free[$rate_id] = $rate;
                break;
            }
        }
    }
    return !empty($free) ? $free : $rates;
}

add_filter('woocommerce_package_rates', 'corcrm_hide_shipping', 100);


/*
 * Add a corcrm_product_id field to the Product API response.
 */

function prefix_wc_rest_prepare_order_object($response, $object, $request) {
    // Get the value

    global $wpdb;
    $tbl_name = $wpdb->prefix . 'posts';
    $id = $object->get_id();

    if ($object->is_type('variable') && $object->has_child()) {
        foreach ($response->data['variations'] as $key => $value) {
            $prodId = $wpdb->get_var("SELECT `corcrm_product_id` FROM $tbl_name WHERE ID = $value");
            $response->data['variation_to_corcrm_id'][$value] = $prodId;
        }
    } else {
        $prodId = $wpdb->get_var("SELECT `corcrm_product_id` FROM $tbl_name WHERE ID=$id");
        $response->data['corcrm_product_id'] = $prodId;
    }
    return $response;
}

add_filter('woocommerce_rest_prepare_product_object', 'prefix_wc_rest_prepare_order_object', 10, 3);
add_filter('wc_product_has_unique_sku', '__return_false'); //Allows Adding of duplicate SKU Products

// code for Hiecor subscription options Date 28-06-19
add_action( 'woocommerce_before_add_to_cart_button', 'add_fields_before_add_to_cart' );
function add_fields_before_add_to_cart( ) {

    if ( $meta = get_post_meta( get_the_ID(),'hiecor_subscription_options', true ) )
    {
        echo "<div><strong>Subscription</strong> <select style=' padding: 4px 8px;
        border-radius: 5px;
        color: #666!important;
        background-color: #ececec;
        font-size: 12px;
        font-weight: 500;
        border: none;
        width: 56%;
        margin-left: 8%;
        margin-bottom: 15px;' name='sub_option'>";
        foreach ($meta as $key => $value) {
            echo '<option value="'.$value['value'].'">'.$value['name'].'</option>';
        }
        echo '</select></div>';
    }
}

/**
 * Add data to cart item
 */
add_filter( 'woocommerce_add_cart_item_data', 'add_cart_item_data', 25, 2 );
function add_cart_item_data( $cart_item_meta, $product_id ) {
    if ( isset( $_POST['sub_option']) ) {
        $hiecor_sub_option = sanitize_text_field($_POST['sub_option']);
        $custom_data  = array() ;
        $custom_data [ 'sub_option' ]    = isset( $hiecor_sub_option ) ?  $hiecor_sub_option : "" ;
        $cart_item_meta ['sub_option']     = $custom_data ;
    }
    return $cart_item_meta;
}

/**
 * Display custom data on cart and checkout page.
 */
add_filter( 'woocommerce_get_item_data', 'get_item_data' , 25, 2 );
function get_item_data ( $other_data, $cart_item ) {

    if ( isset( $cart_item [ 'sub_option' ] ) && !empty($cart_item [ 'sub_option' ]['sub_option']) ) {
        $custom_data  = $cart_item [ 'sub_option' ];
        $other_data[] = array( 'name' => 'Subscription',
            'display'  => $custom_data['sub_option'] );
    }
    return $other_data;
}

/**
 * Add order item meta.
 */
add_action( 'woocommerce_new_order_item', 'hcv4_add_order_item_meta' , 10, 3);
function hcv4_add_order_item_meta ( $item_id, $item, $values ) {
    if ( isset( $item->legacy_values[ 'hiecor_iframe_selected_options' ] ) && !empty($item->legacy_values[ 'hiecor_iframe_selected_options' ]) ) {
        $item_text_data = '';
        foreach ($item->legacy_values[ 'hiecor_iframe_selected_options' ] as $key => $value) {
            $item_text_data .= $value['attribute_name']."=".$value['attribute_value'].',';
        }
        if(!empty($item_text_data)){
            $final_selected_text_attribute = rtrim( $item_text_data,',');
            wc_add_order_item_meta( $item_id, 'Options', $final_selected_text_attribute );
        }
        if(!empty($item->legacy_values['hiecor_iframe_attr_var_data'])){
            wc_add_order_item_meta( $item_id, 'hiecor_iframe_attr_var_ids', $item->legacy_values['hiecor_iframe_attr_var_data'] );
        }
    }
    if ( isset( $item->legacy_values[ 'sub_option' ] ) ) {
        $custom_data  = $item->legacy_values[ 'sub_option' ];
        if(!empty($custom_data['sub_option'])){
            wc_add_order_item_meta( $item_id, 'hiecor_subscription', $custom_data['sub_option'] );
        }
    }
}

/* Showing source order on cart page */
add_action('woocommerce_before_cart', 'hcv4_cart_page_order_source_msg', 1);
function hcv4_cart_page_order_source_msg()
{
    if(!empty($_SESSION['hiecor_order_source']) ){
        echo '<div class="woocommerce-info">Order from : '.$_SESSION['hiecor_order_source'].'</div>';
    }
}
/* Showing source order on checkout page */
add_action( 'woocommerce_before_checkout_form', 'hcv4_checkout_page_order_source_msg', 10, 1 );
function hcv4_checkout_page_order_source_msg()
{
    if(!empty($_SESSION['hiecor_order_source']) ){
        echo '<div class="woocommerce-info">Order from : '.$_SESSION['hiecor_order_source'].'</div>';
    }

}
/* Showing source order on product page */
add_action( 'woocommerce_before_single_product', 'hcv4_product_page_order_source_msg', 10, 2 );
function hcv4_product_page_order_source_msg()
{
    if(!empty($_SESSION['hiecor_order_source']) ){
        echo '<div class="woocommerce-info">Order from : '.$_SESSION['hiecor_order_source'].'</div>';
    }
}

//To save a product on Hiecor when importing products using the WP All Import plugin
function save_product_on_hiecor( $post_id, $xml_node, $is_update )
{
    global $crmUtility;
    $post = get_post( $post_id );
    // Check if the post ID is a product and its status is 'publish' or 'private'
    if ( $post && $post->post_type == 'product' && ( $post->post_status == 'publish' || $post->post_status == 'private' ) ) {
        $crmUtility->push_to_corcrm( $post_id );
    }
}
add_action( 'pmxi_saved_post', 'save_product_on_hiecor', 10, 3 );
// UPC and Cost Field
include_once( 'admin/custom-fields.php' );

//Bulk import functionality
include_once('admin/bulkimport.php');
