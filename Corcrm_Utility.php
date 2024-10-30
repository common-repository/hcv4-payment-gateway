<?php

use Hiecor\Rest\Client;

class Corcrm_Utility {

    /**
     * The plugin ID. Used for option names.
     * @var string
     */
    public $plugin_id = 'woocommerce_';

    /**
     * Method ID.
     * @var string
     */
    public $id = 'corcrm_payment';

    /**
     * Setting values.
     * @var array
     */
    public $settings = array();
    public $enabled;
    public $title;
    public $description;
    public $wsdl_url;
    public $auth_key;
    public $user_name;
    public $order_status;
    public $visit_plugin;

    public function __construct() {

        $this->init_settings();
// Turn these settings into variables we can use
        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }
    }


    public function init_settings() {

// Load form_field settings
        $this->settings = get_option($this->plugin_id . $this->id . '_settings', null);

        if (!$this->settings || !is_array($this->settings)) {

            $this->settings = array();

// If there are no settings defined, load defaults
            if ($form_fields = $this->get_form_fields()) {

                foreach ($form_fields as $k => $v) {
                    $this->settings[$k] = isset($v['default']) ? $v['default'] : '';
                }
            }
        }

        if ($this->settings && is_array($this->settings)) {
            $this->settings = array_map(array($this, 'format_settings'), $this->settings);
            $this->enabled = isset($this->settings['enabled']) && $this->settings['enabled'] == 'yes' ? 'yes' : 'no';
        }
    }

    public function get_settings($key) {
        if (isset($this->settings[$key])) {
            return $this->settings[$key];
        }
        return null;
    }

    public function get_form_fields() {
// Build the administration fields for this specific Gateway
        return array(
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
            'wsdl_url' => array(
                'title' => __('WSDL Url', 'corcrm-secure-payments'),
                'type' => 'text',
                'desc_tip' => __('This is the CRM API WSDL url provided by HieCOR.', 'corcrm-secure-payments'),
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
            )
        );
    }

    /**
     * Decode values for settings.
     *
     * @param mixed $value
     * @return array
     */
    public function format_settings($value) {
        return is_array($value) ? $value : $value;
    }

    public static function logger($msg) {
        $log = new WC_Logger();
        $log->add('api', $msg);
    }

    public function getProduct($post_id) {
        $_pf = new WC_Product_Factory();
        return $_pf->get_product($post_id);
    }

    public function get_productImage($post_id) {
        $product = wc_get_product($post_id);
        $img = $product->get_image('shop_thumbnail', '', false); // accepts 2 arguments ( size, attr 
        return $img;
    }

    public static function cleanDescription($description) {
        $str1 = strip_tags($description);
        $str = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
        $xml = new DOMDocument();
        $internalErrors = libxml_use_internal_errors(true);
        $xml->loadHTML($str);
        libxml_use_internal_errors($internalErrors);
        $xpath = new DOMXpath($xml);
        $elements = $xpath->query("//body//text()[not(ancestor::script)][not(ancestor::style)]");

        $text = "";
        for ($i = 0; $i < $elements->length; $i++) {
            $text .= trim(strip_tags($elements->item($i)->nodeValue));
            $text = str_replace(chr(194), "", trim($text));
        }
        $description = strip_tags($text);
        $description = preg_replace('/[[:^print:]]/', '', $description);
        return str_replace(array("&nbsp;", "\'", "\"", "&quot;"), "", $description);
    }

    public static function dump($data) {
        echo "<pre>";
        print_r($data);
        echo "</pre>";
        exit;
    }

    public function get_post($post_id) {
        return get_post($post_id);
    }

    public function push_coupon_to_corcrm($post_id, $post, $update, $post_data) {
        global $wpdb;
        $coupon = $this->getCoupon($post, $post_data);
        $wp_coupon_id = $post_id;
        $post = get_post($post_id);
        $corcrm_id = $post->corcrm_product_id;
        $endpoint="coupon/";
        $url = $this->hiecor_url . '/rest/v1/'.$endpoint;
        $hiecor_prod_ids = array();
        if(!empty($post_data['product_ids'])){
            $corcrmProdID = array();
            foreach ($post_data['product_ids'] as $key => $prodID) {
                $parent_id = wp_get_post_parent_id($prodID);
                if(!empty($parent_id)){
                    $postdata = get_post($parent_id);
                    $corcrmProdID[$key] = $postdata->corcrm_product_id;
                }else{
                    $postdata = get_post($prodID);
                    $corcrmProdID[$key] = $postdata->corcrm_product_id;
                }
                $hiecor_prod_ids = array_values(array_unique($corcrmProdID));
            }
        }
        $cdata=array();
        $cdata['wp_coupon_id'] = $wp_coupon_id;
        $cdata['coupon_name']=$coupon['coupon_description'];
        $cdata['coupon_code']=$coupon['coupon_code'];
        $cdata['discount_type']=$coupon['discount_type'];
        $cdata['discount_amount']=$coupon['discount_amount'];
        $cdata['total_amount']=$coupon['total_amount'];
        $cdata['products']= $hiecor_prod_ids;
        $cdata['free_shipping']=$coupon['free_shipping'];
        $cdata['date_start']=$coupon['date_start'];
        $cdata['date_end']=$coupon['date_end'];
        $cdata['uses_per_coupon']=$coupon['uses_per_coupon'];
        $cdata['uses_per_customer']=$coupon['uses_per_customer'];
        $cdata['active']=$coupon['active'];
        if(!empty($corcrm_id)){
            $cdata['hiecor_coupon_id']=$corcrm_id;
        }
        $coudata = json_encode($cdata);
        try {
            $response = wp_remote_post( $url, array(
                    'method'      => 'POST',
                    'timeout'     => 45,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking'    => true,
                    'headers'     => array(
                        'Content-Type'=> 'application/json',
                        'X-USERNAME' =>  $this->user_name,
                        'X-AUTH-KEY' => html_entity_decode($this->auth_key),
                        'X-AGENT-ID' => $this->agent_id
                    ),
                    'body'        => $coudata
                )
            );
        } catch (\Exception $ex) {
            $log = "CorCRM API Exception \n URL-#{$url} \n ".$ex->getMessage().' \n Error occured during create/update Hiecor Coupon.';
            $log .= "\n PARAM- ".print_r($coudata,true);
            self::logger($log);
            return false;
        }

        if(is_wp_error($response)){
            $log = "ERROR IN CREATE COUPON-".print_r($response,true);
            self::logger($log);
            return false;
        }

        $res = '';
        if(isset($response['body'])){
            $res = json_decode($response['body']);
        }

        if(empty($res) || (isset($res->success) && $res->success==false)){
            $_SESSION['hiecor_coupon_sync_failed']= isset($res->error)? $res->error:'Something went wrong.';
            $log="ERROR IN CREATE COUPON Log1- \n URL-".$url;
            $log.="PARAM- ".print_r($cdata,true)."RESPONSE-".print_r($res,true);
            self::logger($log);
        }

        if(empty($res->data) ||  $res->data->success == false){
            $log="ERROR IN CREATE COUPON Log2- \n URL-".$url;
            $log.="PARAM- ".print_r($cdata,true)."RESPONSE-".print_r($res,true);
            self::logger($log);
        }

        if (isset($res->data->coupon_data->coupon_id) && !empty($res->data->coupon_data->coupon_id)) {
            $this->saveHiecorProdcuctId($wp_coupon_id, $res->data->coupon_data->coupon_id);
        }
        return;
    }

    public function delete_product_to_corcrm($post_id) {
        global $wpdb;
        $post = get_post($post_id);
        $corcrmid = $post->corcrm_product_id;
        $endpoint="product/delete/";
        $url = $this->hiecor_url . '/rest/v1/'.$endpoint;
        $pro_data=array();
        $pro_data['product_id']=$corcrmid;
        $pro_data['source']="woocommerce";
        $data = json_encode($pro_data);
        $response = wp_remote_post( $url, array(
                'method'      => 'POST',
                'timeout'     => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => array(
                    'Content-Type'=> 'application/json',
                    'X-USERNAME' =>  $this->user_name,
                    'X-AUTH-KEY' => html_entity_decode($this->auth_key),
                    'X-AGENT-ID' => $this->agent_id
                ),
                'body'        => $data
            )
        );

        if(is_wp_error($response)){
            $log = "ERROR IN DELETE PRODUCT- \n URL-".$url."\n PRODUCT ID_".$post_id;
            $log.= "RESPONSE-".print_r($response,true);
            self::logger($log);
            return false;
        }

        $res='';
        if(isset($response['body'])){
            $res = json_decode($response['body']);
        }

        if(empty($res) || (isset($res->success) && $res->success==false)){
            $log="ERROR IN DELETE PRODUCT- \n URL-".$url."\n PRODUCT ID_".$post_id;
            $log.="PARAM- ".print_r($pro_data,true)."RESPONSE-".print_r($res,true);
            self::logger($log);
        }
        return;
    }


    public function getCoupon($post, $data) {
        global $wpdb, $product;

        $table_name = $wpdb->prefix . 'posts';

        if ($data['discount_type'] == "fixed_cart" || $data['discount_type'] == "fixed_product") {
            $data['discount_type'] = "Flat";
        } else {
            $data['discount_type'] = "Percent";
        }
        $meta_data_of_coupon = get_post_meta($post->ID);
        foreach ($meta_data_of_coupon as $k => $v) {
            $meta_data_of_coupon[$k] = array_shift($v);
        }
        $max_spend = $meta_data_of_coupon['minimum_amount'];
        if (!empty($max_spend)) {
            $data['total_amount'] = $max_spend;
        } else {
            $data['total_amount'] = 0;
        }

        $d_start = new DateTime($post->post_date);
        $date_start = $d_start->format('Y-m-d') . " " . "00:00:00";
        $c_date = new DateTime($post->post_date);
        $created_date = $c_date->format('Y-m-d') . " " . "00:00:00";
        $u_date = new DateTime($post->post_modified);
        $updated_date = $u_date->format('Y-m-d') . " " . "00:00:00";

        $coupon = array(
            'coupon_code' => $post->post_title,
            'coupon_description' => isset($post->post_excerpt) ? $post->post_excerpt:'',
            'discount_type' => $data['discount_type'],
            'discount_amount' => $data['coupon_amount'],
            "active" => 'yes',
            'coupon_name' => $post->post_title,
            "total_amount" => $data['total_amount'],
            'free_shipping' => 'No',
            'date_start' => $date_start,
            'date_end' => $data['expiry_date'],
            'uses_per_coupon' => $data['usage_limit'],
            'uses_per_customer' => $data['usage_limit_per_user'],
            'products' => '',
            'date_created' => $created_date,
            'date_updated' => $updated_date
        );
        if (isset($data['free_shipping'])) {
            $coupon['free_shipping'] = $data['free_shipping'];
        }
        $corcrm_products = array();
        $corcrmid = "";
        $prod = $data['product_ids'];

        foreach ($prod as $key => $value) {
            $product = $this->getProduct($value);

            if ($product->is_type('simple')) {
                $id = get_post($product->ID);
                $corcrmid = $id->corcrm_product_id . ",";
            } else if ($product->is_type('variable')) {
                $available_variations = $product->get_available_variations();

                foreach ($available_variations as $key => $value) {
                    $variation_id = get_post($value['id']);
                    $corcrmid .= $variation_id->corcrm_product_id . ",";
                }
            }
        }
        $corcrm_id = rtrim($corcrmid, ",");

        $coupon['products'] = $corcrm_id;
        return $coupon;
    }

    public function dimension_conversion($dim_qty){
        $dimension_unit = get_option('woocommerce_dimension_unit');
        switch ($dimension_unit) {
            case 'm':
                $inchs  = 39.3701*$dim_qty;
                break;
            case 'cm':
                $inchs  = 0.393701*$dim_qty;
                break;
            case 'mm':
                $inchs  = 0.0393701*$dim_qty;
                break;
            case 'yd':
                $inchs  = 36*$dim_qty;
                break;
            default:
                $inchs  = $dim_qty;
        }
        if(is_numeric($inchs)){
            $inchs_round = round($inchs,2);
        }else{
            $inchs_round = 0; // or handle accordingly
        }
        return  $inchs_round;
    }

    public function weight_conversion($weight_qty){
        $weight_unit  = get_option('woocommerce_weight_unit');
        switch ($weight_unit) {
            case 'kg':
                $lbs  = 2.20462*$weight_qty;
                break;
            case 'g':
                $lbs  = 0.00220462*$weight_qty;
                break;
            case 'oz':
                $lbs  = 0.0625*$weight_qty;
                break;
            default:
                $lbs  = $weight_qty;
        }
        if(is_numeric($lbs)){
            $lbs_round = round($lbs,2);
        }else{
            $lbs_round = 0;
        }
        return  $lbs_round;
    }

    public function getHiecorProdID($post,$post_id)
    {
        global $wpdb;
        $hiecor_prod_id = $post->corcrm_product_id;
        if(empty($hiecor_prod_id)){
            $query = $wpdb->prepare("SELECT corcrm_product_id FROM {$wpdb->posts} WHERE ID = {$post_id}");
            $hiecor_prod_id = $wpdb->get_var($query);
        }
        if(empty($hiecor_prod_id)){
            $hiecor_prod_id =  get_post_meta($post_id, 'hiecor_product_id', true);
        }
        return $hiecor_prod_id;
    }

    public function push_to_corcrm($post_id) {
        global $product;
        $product = $this->getProduct($post_id);
        $post = $this->get_post($post_id);
        $wc_prod_id = $product->get_id();
        $prod['title'] = trim($product->get_title());
        $hc_prod_type = get_post_meta($post_id, 'corcrm_custom_product_type', true);
        $prod['type'] = !empty($hc_prod_type) ? $hc_prod_type : 'straight';
        $prod['long_description'] = (!empty($post->post_content)) ? self::cleanDescription($post->post_content) : "";
        $prod['short_description'] = (!empty($post->post_excerpt)) ? self::cleanDescription($post->post_excerpt) : "";
        $prod['product_code'] = (!empty($product->get_sku())) ? $product->get_sku() : $product->get_title();

        $brand = get_post_custom_values('Brand_Name',$post_id);
        if(!empty($brand[0])){
            $prod['brand'] = $brand[0];
        }else{
            // Check if the product has the 'Brand' attribute
            $all_attrs = $product->get_attributes();
            if ( isset( $all_attrs['pa_brand'] ) ) {
                $values = wc_get_product_terms( $product->get_id(), 'pa_brand', array( 'fields' => 'names' ) );
                if(!empty($values[0])){
                    $prod['brand'] = $values[0];
                }
            }
        }

        /*
         * check if this product already exists in hiecor
         */
        $hiecor_prod_id = $this->getHiecorProdID($post,$post_id);
        if(empty($hiecor_prod_id)){
            $product_sku = (!empty($product->get_sku())) ? $product->get_sku() : '';
            $product_brand = (!empty($prod['brand'])) ? $prod['brand'] : '';
            $this->check_product_linking($post_id,$product_sku,$product_brand);
            $hiecor_prod_id = $this->getHiecorProdID($post,$post_id);
            //if product is auto linked then disable hide_from_web flag in hiecor
            $prod['hide_from_web'] = 0;
        }

        //Stop sync to hiecor if hiecor_iframe_required is "Yes"
        $hiecor_ifrmae_req = strtolower(get_post_meta($post_id, 'hiecor_iframe_required', true));
        if($hiecor_ifrmae_req=="yes"){
            $_SESSION['hiecor_prod_sync_failed'] = "This product cannot be synced to Hiecor.";
            return;
        }

        $weight = (!empty($product->get_weight())) ? $product->get_weight() : 0.00;
        $length = (!empty($product->get_length())) ? $product->get_length() : 0.00;
        $width  = (!empty($product->get_width())) ? $product->get_width() : 0.00;
        $height = (!empty($product->get_height())) ? $product->get_height() : 0.00;

        // wieght and dimention convert
        $prod['weight'] =   $this->weight_conversion($weight);
        $prod['length'] =   $this->dimension_conversion($length);
        $prod['width']  =   $this->dimension_conversion($width);
        $prod['height'] =   $this->dimension_conversion($height);


        $regularPrice = $product->get_regular_price();
        $prod['price'] = $regularPrice;


        $salePrice = $product->get_sale_price();
        if (!empty($salePrice) && $product->is_type('simple')) {
            $sales_price_from = $product->get_date_on_sale_from();
            $sales_price_to = $product->get_date_on_sale_to();
            $prod['price_special'] = $salePrice;
            if(!empty($sales_price_from) ){
                $prod['price_special_date_start'] = $sales_price_from->date('Y-m-d H:i:s');
            }
            if(!empty($sales_price_to)){
                $prod['price_special_date_end'] = $sales_price_to->date('Y-m-d H:i:s');
            }

        }

        if($product->is_type('variable')){
            $variation_ids = $product->get_visible_children();
            $prod['price_special'] = 0;
            foreach( $variation_ids as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                $var_sale_price = $variation->get_sale_price();
                if ( empty($var_sale_price) ) {
                    continue;
                }
                $var_date_on_sale_from = $variation->get_date_on_sale_from();
                $var_date_on_sale_to   = $variation->get_date_on_sale_to();
                if(empty($prod['price_special']) ){
                    $prod['price_special'] = $var_sale_price;
                }else{
                    $prod['price_special'] = max([$prod['price_special'], $var_sale_price]);
                }
                if(!empty($var_date_on_sale_from)){
                    $prod['price_special_date_start'] = $var_date_on_sale_from->date('Y-m-d H:i:s');
                }
                if(!empty($var_date_on_sale_to)){
                    $prod['price_special_date_end'] = $var_date_on_sale_to->date('Y-m-d H:i:s');
                }
            }
        }

        if(empty($prod['price_special'])){
            $prod['price_special'] = 0;
            unset($prod['price_special_date_start']);
            unset($prod['price_special_date_end']);
        }

        //manage stock at parent level
//        if ($product->is_type('simple') || $product->is_type('composite')) {
//            if ($product->managing_stock()) {
//                $prod['stock'] = get_post_meta($product->get_id(), '_stock', true);
//            } else {
//                if($product->get_stock_status()=="instock"){
//                    $prod['unlimited_stock'] = 'Yes';
//                    $prod['stock'] = 0;
//                }elseif($product->get_stock_status()=="outofstock"){
//                    $prod['stock'] = 0;
//                }
//            }
//        }

        // get the tax status and class of simple product
        $tax_status = get_post_meta($product->get_id(), '_tax_status', true);
        //$tax_class = get_post_meta($product->get_id(), '_tax_class', true);
        if($tax_status == 'taxable'){
            $prod['taxable'] = "Yes";
        }else{
            $prod['taxable'] = "No";
        }

        //get product code - UPC
        $prod['upc'] = get_post_meta($product->get_id(), 'hwp_product_gtin', true);

        //$prod['external_id'] = $ex_prod_id;
        //$prod['external_id_type'] = 'upc';

        $prodImages = $this->getProductImages($post_id);
        if (!empty($prodImages)) {
            $prodImages = Unserialize($prodImages);
            foreach ($prodImages as $imageValue) {
                $imageName = explode('/', $imageValue);
                $uploadBasedir = wp_upload_dir();
                $file_upload_path = $uploadBasedir['basedir']."/".$imageName[5]."/".$imageName[6];
                $imageDetail = pathinfo($file_upload_path . '/' . end($imageName));
                if(file_exists($file_upload_path.'/'.end($imageName)) || file_exists($uploadBasedir['basedir']."/".end($imageName))){
                    $image['images'][] = $imageValue;
                }else{
                    self::logger("Product image upload failed \n Product ID-".$wc_prod_id."\n Image path:".$file_upload_path.'/'.end($imageName));
                }
            }
        }
        get_post_thumbnail_id($post_id);
        $prodCategories = $this->getProductCategories($post_id);
        if (!empty($prodCategories)) {
            //$prod['category_name'] = $prodCategories;
            $prod = array_merge($prod, $prodCategories);
        }

        $attributes = $this->getProductAttributes($product);
        if (!empty($attributes)) {
            $prod = array_merge($prod, $attributes);
            if ($product->is_type('variable')) {
                $prod['price'] = 0;

                $variations = $this->getProductVariations($product, $post_id);
                $prod = array_merge($prod, $variations);
                //$prod['variation'] = $variations;
            }
        }


        $prod['external_prod_id'] = $wc_prod_id;
        $prod['external_prod_source'] = "woocommerce";
        $prod = $this->getSubscriptionData($prod, $post_id);

        //$hiecor_prod_id = $this->getHiecorProdID($post,$post_id);
        if (!empty($hiecor_prod_id)) {
            $prod['product_id'] = $hiecor_prod_id;
            $endPoint = 'product/';
        } else {
            $prod['hide_from_pos'] = 0;
            $prod['hide_from_web'] = 0;
            $prod['raw_product_cost'] = get_post_meta($product->get_id(), '_wc_cog_cost', true);
            $endPoint = 'product/create/';
        }

        /*
        if($product->is_type('variable')){
            $syncable = $this->check_syncable($product,$post_id);
            if($syncable){
                $prod = array_merge($prod, $syncable);
            }else{
                $_SESSION['hiecor_invt_sync_failed'] = "Yes";
                return;
            }
        }
         */

        if($product->is_type('composite')){
            $prod['status'] = "inactive";
        }
        $url = rtrim($this->hiecor_url,'/') . '/rest/v1/' . $endPoint;
        $productData = '';
        // First, add the standard POST fields:
        $boundary = wp_generate_password( 24 );
        foreach ( $prod as $name => $value ) {
            $productData .= '--' . $boundary;
            $productData .= "\r\n";
            $productData .= 'Content-Disposition: form-data; name="' . $name .
                '"' . "\r\n\r\n";
            $productData .= $value;
            $productData .= "\r\n";
        }
        // Upload the file
        if (!empty($image['images'])) {
            //array_reverse - Primary image in hiecor should be at zeroth index
            $images = array_reverse($image['images']);
            foreach ($images as $key => $value) {
                $productData .= '--' . $boundary;
                $productData .= "\r\n";
                $productData .= 'Content-Disposition: form-data; name="' . 'images['.$key.']' .
                    '"' . "\r\n\r\n";
                $productData .= $value;
                $productData .= "\r\n";
                break;
            }
        }
        $productData .= '--' . $boundary . '--';
        try {
            $response = wp_remote_post( $url, array(
                    'method'      => 'POST',
                    'timeout'     => 45,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking'    => true,
                    'headers'     =>  array(
                        'content-type' => 'multipart/form-data; boundary=' . $boundary,
                        'X-USERNAME' =>  $this->user_name,
                        'X-AUTH-KEY' => html_entity_decode($this->auth_key),
                        'X-AGENT-ID' => $this->agent_id
                    ),
                    'body'        => $productData
                )
            );
        }catch (\Exception $ex) {
            $log = "CorCRM API Exception \n URL-#{$url} \n ".$ex->getMessage().' \n Error occured during create/update Hiecor Product.';
            $log .= "\n PARAM- ".print_r($prod,true);
            self::logger($log);
            return false;
        }

        //self::logger(print_r($response,true));
        if(is_wp_error($response)){
            $log = "ERROR IN CREATE/UPDATE PRODUCT- \n URL-".$url;
            $log .= "\nWP Error:".$response->get_error_message();
            $log.= "\nRAW RESPONSE".print_r($response,true);
            self::logger($log);
            return false;
        }

        $res = '';
        if(isset($response['body'])){
            $res = json_decode($response['body']);
        }

        if(empty($res) || (isset($res->success) && $res->success==false)){
            $log="ERROR IN CREATE/UPDATE PRODUCT- \n URL-".$url;
            $log.="\n PARAM- ".print_r($prod,true)."\n RESPONSE-".print_r($res,true)."\n Raw Response ".print_r($response,true);
            self::logger($log);
        }

        $resData = new \stdClass();
        $resData->product_id = $res->data[0]->product_id;
        $resData->variation_data = $res->data[0]->product_mapping->woocommerce->variation_mapping;
        $resData->attribute_data = $res->data[0]->product_mapping->woocommerce->attribute_mapping;
        //$resData->attribute_value = $res->data[0]->product_mapping->woocommerce->attribute_value_mapping;
        $this->handleAPIResponce($resData, $post->corcrm_product_id, $wc_prod_id);
        return;
    }

    public function handleAPIResponce($response, $hiecorId, $wc_prod_id) {
//        if (!empty($hiecorId)) {
//            foreach ($response->attribute_data as $key => $value) {
//              if (isset($value->external_attribute_id) && $value->external_attribute_id > 0) {
//                      $this->saveAttrMapping($value->external_attribute_id, $value->attr_id);
//                  }
//            }
//        } else {
        if ($response->product_id) {
            $this->saveHiecorProdcuctId($wc_prod_id, $response->product_id);
        }
        if (!empty($response->variation_data)) {
            foreach ($response->variation_data as $key => $value) {
                if (isset($value->var_id) && $value->var_id > 0) {
                    $this->saveHiecorProdcuctId($value->external_var_id, $value->var_id);
                }
            }
        }
        if (!empty($response->attribute_data)) {
            foreach ($response->attribute_data as $key => $value) {
                $this->saveAttrMapping($wc_prod_id,$value->external_attribute_id, $value->attr_id);
            }
        }

//        }
    }

    public function saveAttrMapping($wc_prod_id,$wc_attr_id, $hiecor_attr_id) {
        global $wpdb;
        $att_mapping_table = $wpdb->prefix."hiecor_attr_mapping";
        $upd = $wpdb->update($att_mapping_table,
            array('hiecor_id' => $hiecor_attr_id),
            array('foreign_id' => $wc_attr_id,'wc_product_id'=>$wc_prod_id,'mapping_type'=>'attribute'),
            $format = null,
            $where_format = null
        );
        if(!$upd &&  $wpdb->last_error != ''){
            $log = new WC_Logger();
            $errors = "SQL ERROR:-".$wpdb->last_error."\n SQL EXECUTED:-".$wpdb->last_query."\n";
            $log->add('api', $errors);
        }
    }

    public function getGallaryImage($post_id) {
        $prodGalleryImage = '';
        $product = $this->getProduct($post_id);
        $attachment_ids = $product->get_gallery_attachment_ids();

        foreach ($attachment_ids as $attachment_id) {
            echo $image_link = wp_get_attachment_url($attachment_id);
        }
    }

    public function getProductImages($post_id) {
        $prodImages = '';
        $product = $this->getProduct($post_id);
        $product_image = $this->get_productImage($post_id);
        $attachment_ids[0] = get_post_thumbnail_id($post_id);
        $attachment = wp_get_attachment_image_src($attachment_ids[0], 'full');

        if (!empty($product_image)) {
            $image_link[] = $attachment[0];
        }
        //$this->dump($attachment);
        // return $attachment[0];
        $attachment_ids = $product->get_gallery_image_ids();

        foreach ($attachment_ids as $attachment_id) {
            $image_link[] = wp_get_attachment_url($attachment_id);
        }
        if (!empty($image_link)) {
            $image_link = array_reverse($image_link);
            $prodImages = serialize(array_unique($image_link));
        }
        //$this->dump($prodImages);
        return $prodImages;
    }

    public function getProductCategories($post_id) {
        $prod_terms = get_the_terms($post_id, 'product_cat');
        $category = array();
        if (!empty($prod_terms)) {
            foreach ($prod_terms as $cat) {
                $category[] = $cat->name;
            }
        }
        //$this->dump($category);
        $temp = array();
        foreach ($category as $key => $value) {
            $temp["category_name[$key]"] = $value;
        }
        return $temp;
        //return $category;
    }

    /*
     * @param object $product - Current product object
     */

    public function getProductAttributes($product) {
        global $wpdb;
        $attr = $product->get_attributes();
        $productId = $product->get_id();
        if (is_array($product->get_attributes())) {
            foreach ($product->get_attributes() as $attrKey => $attr) {
                if(isset($attr['id'])&& !empty($attr['id'])){
                    $att_taxo_table = $wpdb->prefix."woocommerce_attribute_taxonomies";
                    $sql="SELECT `attribute_label` FROM $att_taxo_table WHERE attribute_id = {$attr['id']}";
                    $wc_attribute_name = $wpdb->get_row($sql);
                    $optionName=$wc_attribute_name->attribute_label;
                    // $optionName = str_replace('pa_', '', $attrKey);
                    $attrValues = $product->get_attribute($attrKey);
                    $attrVal = explode(",", $attrValues);
                    $attrMappingId = $this->getAttrMappingId($productId, $attr->get_id());
                    if(!empty($attrMappingId)){
                        $hicorAttrId = $this->getHicorAttrId($attrMappingId);
                    }
                    if (empty($hicorAttrId)) {
                        $hicorAttrId = '';
                    }
                    $attrData['attribute_name'] = $optionName;
                    $attrData['attribute_id'] = $hicorAttrId;//hicore attr id
                    $attrData['external_attribute_id'] = $attr->get_id();// wo commer att
                    foreach ($attrVal as $key => $value) {
                        //$attrValueMappingId = $this->getAttrValueMappingId($productId, $value);
                        $attrData['attribute_value'][$key]['value_name'] = $value;
                        $attrData['attribute_value'][$key]['external_attr_value_id'] = '';
                    }
                }
                if(isset($attr['id']) && $attr['id'] == 0){
                    $optionName = $attr['name'];
                    $attrValues = $product->get_attribute($attrKey);
                    $attrVal = explode("|" ,$attrValues);
                    $attrMappingId = $this->getAttrMappingId($productId, $attr->get_id());
//                    $hicorAttrId = $this->getHicorAttrId($attrMappingId);
//                    if ($hicorAttrId == 0) {
//                        $hicorAttrId = '';
//                    }
                    if(!empty($attrMappingId)){
                        $hicorAttrId = $this->getHicorAttrId($attrMappingId);
                    }
                    if (empty($hicorAttrId)) {
                        $hicorAttrId = '';
                    }
                    $attrData['attribute_name'] = html_entity_decode($optionName);
                    $attrData['attribute_id'] = $hicorAttrId;//hicore attr id
                    $attrData['external_attribute_id'] = $attr->get_id();// wo commer att
                    foreach ($attrVal as $key => $value) {
                        //$attrValueMappingId = $this->getAttrValueMappingId($productId, $value);
                        $attrData['attribute_value'][$key]['value_name'] = $value;
                        $attrData['attribute_value'][$key]['external_attr_value_id'] = '';
                    }
                }

                $attrData['attribute_type'] = 'radio';
                $attrData['attribute_required'] = 'on';
                $attrData['is_variation'] = $attr['variation'];
                $attrArray[] = $attrData;
                $attrData=[];
                $temp = array();
                $index = 0;
                foreach ($attrArray as $key => $value) {
                    if(empty($value['is_variation'])){
                        continue;
                    }
                    $temp["attributes[$index][attribute_id]"] = isset($value['attribute_id']) ? $value['attribute_id'] : '';
                    $temp["attributes[$index][external_attribute_id]"] = isset($value['external_attribute_id']) ? $value['external_attribute_id'] : '';
                    $temp["attributes[$index][attribute_name]"] = isset($value['attribute_name']) ? $value['attribute_name'] : '';
                    $temp["attributes[$index][attribute_type]"] = isset($value['attribute_type']) ? $value['attribute_type'] : '';
                    $temp["attributes[$index][attribute_required]"] = isset($value['attribute_required']) ? $value['attribute_required'] : 'on';
                    if (isset($value['attribute_value'])) {
                        foreach ($value['attribute_value'] as $k2 => $val2) {
                            $temp["attributes[$index][attribute_value][$k2][value_name]"] = $val2['value_name'];
                            $temp["attributes[$index][attribute_value][$k2][external_attr_value_id]"] = $val2['external_attr_value_id'];
                        }
                    }
                    $index++;
                }
            }
        } else {
            $attributes = '';
        }
        return $temp;

    }

    public function getAttrMappingId($productId, $attrID) {
        global $wpdb;
        $mappingTable=$wpdb->prefix."hiecor_attr_mapping";
        $attrMappingId = $wpdb->get_var("SELECT `mapping_id` FROM $mappingTable WHERE wc_product_id = $productId and mapping_type='attribute' and foreign_id='" . $attrID . "'");
        return $attrMappingId;
    }

    public function getHicorAttrId($attrMappingId) {
        global $wpdb;
        $mappingTable=$wpdb->prefix."hiecor_attr_mapping";
        $hicorAttrId = $wpdb->get_var("SELECT `hiecor_id` FROM $mappingTable WHERE mapping_id = $attrMappingId");
        return $hicorAttrId;
    }

    public function getAttrValueMappingId($productId, $attrValue) {
        global $wpdb;
        $mappingTable=$wpdb->prefix."hiecor_attr_mapping";
        $attrValueMappingId = $wpdb->get_var("SELECT `mapping_id` FROM $mappingTable WHERE wc_product_id = $productId and mapping_type='attribute_value' and wc_option='" . $attrValue . "'");
        return $attrValueMappingId;
    }

    public function getVariationPosts($post_id) {
        $args = array(
            'post_type' => 'product_variation',
            'post_status' => array('private', 'publish'),
            'order' => 'asc',
            'post_parent' => $post_id,
            'numberposts'=>-1
        );
        $prodVariations = get_posts($args);

        return $prodVariations;
    }

    public function getVariationAttributes($variation_id,$product,$post_id) {
        global $wpdb;
        $variationObj = wc_get_product($variation_id);
        $varAttributes = $variationObj->get_variation_attributes();
        $varAttrArray=array();
        $allarributes = $product->get_attributes();
        /*
         * Array ( [attribute_pa_ptr-test] => grd-test [attribute_pradeep] => prd ptr )
         * "attribute_pa_*" => global attribute
         * "attribute_*" => custom attribute
         */
        foreach ($varAttributes as $attribute => $value) {
            $varAttrData=array();
            if(strpos($attribute,'attribute_pa_')!==false){
                $attr_slug = str_replace('attribute_pa_','', $attribute);
                $value_slug = $value;
                $att_table = $wpdb->prefix."woocommerce_attribute_taxonomies";
                $sql="SELECT `attribute_label` FROM $att_table WHERE attribute_name = '{$attr_slug}'";
                $wc_attribute_name = $wpdb->get_row($sql);
                $varAttrData['attribute_name']=$wc_attribute_name->attribute_label;
                $termsTable = $wpdb->prefix."terms";
                $wp_term_taxonomy = $wpdb->prefix."term_taxonomy";
                $wp_term_relationships = $wpdb->prefix."term_relationships";
                $sql= "SELECT t.`name` FROM  $wp_term_relationships as tr
                    JOIN $wp_term_taxonomy tt
                    ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    JOIN $termsTable t
                    ON t.term_id = tt.term_id
                    WHERE tr.object_id= $post_id and t.slug='".$value_slug."'";
                $value_name = $wpdb->get_row($sql);
                $varAttrData['attribute_value'] = $value_name->name;
            }else{
                //code for custom variation attribute name
                $attr_name = str_replace('attribute_','', $attribute);
                if (array_key_exists($attr_name, $allarributes)) {
                    $attr_name =  $allarributes[$attr_name]['name'];
                }
                //This is a custom attribute slug extraction is not required
                $varAttrData['attribute_name'] = html_entity_decode($attr_name);
                $varAttrData['attribute_value'] = $value;
            }
            $varAttrArray[] = $varAttrData;
        }
        return $varAttrArray;
    }

    public function getProductVariations($productObj, $post_id) {
        global $wpdb;
        $variationProductData = array();
        $variationPosts = $this->getVariationPosts($post_id);
        foreach ($variationPosts as $variation) {
            $variationData['var_id'] = '';
            $variationObj = wc_get_product($variation->ID);
//            $qty = $variationObj->get_stock_quantity();
//            $managing_stock = $variationObj->managing_stock();
//            if ($managing_stock == 1) {
//                $variationData['var_stock'] = $qty;
//            } else {
//                $variationData['var_use_parent_stock'] = 'Yes';
//            }

            $variationData['var_attrs'] = $this->getVariationAttributes($variation->ID,$productObj,$post_id);
            $variationPost = get_post($variation->ID);

            $hiecor_var_prod_id = $variationPost->corcrm_product_id;
            if(empty($hiecor_var_prod_id)){
                $query = $wpdb->prepare("SELECT corcrm_product_id FROM {$wpdb->posts} WHERE ID = {$variation->ID}");
                $hiecor_var_prod_id = $wpdb->get_var($query);
            }
            if(empty($hiecor_var_prod_id)){
                $hiecor_var_prod_id =  get_post_meta($variation->ID, 'hiecor_product_id', true);
            }
            $variationData['var_id'] = $hiecor_var_prod_id;
            $variationData['var_required'] = 'No';
            $upc = $this->getVariationProductUPC($post_id, $variation->ID);
            $variationData['var_upc'] = $upc;
            $code = $this->getVariationProductSku($productObj, $variationObj);
            $variationData['var_product_code'] = $code;
            $regular_price = $variationObj->get_regular_price();
            $sales_price = $variationObj->get_sale_price();
            $variationData['var_price'] = (!empty($regular_price)) ? $regular_price : '';
            $variationData['var_sp_price'] = (!empty($sales_price)) ? $sales_price : '';
            if (!empty($variationData['var_sp_price'])) {
                $variationData['var_sp_price_start'] = '';
                $variationData['var_sp_price_end'] = '';
            }
            $variationData['var_description'] = $variationObj->get_variation_description();
            $variationData['external_var_id'] = $variation->ID;
            $variationProductData[] = $variationData;
            //$variationData['var_use_parent_upc'] = 'on';
            //$variationData['var_price_surcharge'] = '';
            //$variationData['var_wholesale_price'] = '19';
            //$variationData['var_msrp'] = '23';
        }
        $temp = array();
        foreach ($variationProductData as $key => $value) {
            isset($v['default']) ? $v['default'] : '';
            $temp["variation[$key][var_id]"] = isset($value['var_id']) ? $value['var_id'] : '';
            $temp["variation[$key][var_product_code]"] = isset($value['var_product_code']) ? $value['var_product_code'] : '';
            $temp["variation[$key][external_var_id]"] = isset($value['external_var_id']) ? $value['external_var_id'] : '';
            $temp["variation[$key][var_required]"] = isset($value['var_required']) ? $value['var_required'] : '';
            if (empty($value['var_upc'])) {
                //$temp["variation[$key][var_use_parent_upc]"] = isset($value['var_use_parent_upc']) ? $value['var_use_parent_upc'] : '';
            } else {
                $temp["variation[$key][var_upc]"] = isset($value['var_upc']) ? $value['var_upc'] : '';
            }
            $temp["variation[$key][var_product_code]"] = isset($value['var_product_code']) ? $value['var_product_code'] : '';
            //$temp["variation[$key][var_price_surcharge]"] = isset($value['var_price_surcharge']) ? $value['var_price_surcharge'] : '';
            if (empty($value['var_price'])) {
                //$temp["variation[$key][var_use_parent_price]"] = isset($value['var_use_parent_price']) ? $value['var_use_parent_price'] : '';
            } else {
                $temp["variation[$key][var_price]"] = isset($value['var_price']) ? $value['var_price'] : '';
            }
            $temp["variation[$key][var_wholesale_price]"] = isset($value['var_wholesale_price']) ? $value['var_wholesale_price'] : '';
            $temp["variation[$key][var_msrp]"] = isset($value['var_msrp']) ? $value['var_msrp'] : '';
            $temp["variation[$key][var_sp_price]"] = isset($value['var_sp_price']) ? $value['var_sp_price'] : '';
            $temp["variation[$key][var_sp_price_start]"] = isset($value['var_sp_price_start']) ? $value['var_sp_price_start'] : '';
            $temp["variation[$key][var_sp_price_end]"] = isset($value['var_sp_price_end']) ? $value['var_sp_price_end'] : '';
            $temp["variation[$key][var_description]"] = isset($value['var_description']) ? $value['var_description'] : '';
            $temp["variation[$key][var_attribute_id]"] = isset($value['var_attribute_id']) ? $value['var_attribute_id'] : '';
            /*
            $temp["variation[$key][var_stock]"] = isset($value['var_stock']) ? $value['var_stock'] : '';
            if(!empty($value['var_use_parent_stock'])){
                $temp["variation[$key][var_use_parent_stock]"] = 'Yes';
            }
             */
            if (!empty($value['var_attrs'])) {
                foreach ($value['var_attrs'] as $k2 => $val2) {
                    $temp["variation[$key][var_attrs][$k2][attribute_name]"] = $val2['attribute_name'];
                    $temp["variation[$key][var_attrs][$k2][attribute_value]"] = $val2['attribute_value'];
                }
            }
        }
        return $temp;
    }

    public function getVariationProductSku($productObj, $variationObj) {
        $varSku = $variationObj->get_sku();
        $prodSku = $productObj->get_sku();
        $sku = (!empty($varSku)) ? $varSku : $prodSku;
        $code = (!empty($sku)) ? $sku : $productObj->get_title();
        return $code;
    }

    public function getVariationProductCost($productObj, $variationObj) {
        $cost = get_post_meta($prod['post_id'], '_wc_cog_cost', true);
        $prod['raw_product_cost'] = $cost;
    }

    public function getVariationProductUPC($post_id, $variation_id) {
        $varUPC = get_post_meta($variation_id, 'hwp_var_gtin', true);
        $productUPC = get_post_meta($post_id, 'hwp_var_gtin', true);
        $checkUPC = (!empty($varUPC)) ? $varUPC : $productUPC;
        $upc = (!empty($checkUPC)) ? $checkUPC : '';
        return $upc;
    }

    public function getSubscriptionData($product, $post_id) {
        $product['days_or_months'] = get_post_meta($post_id, 'corcrm_custom_subscription_time', true);
        $product['sub_days_between'] = get_post_meta($post_id, 'subscription_interval', true);
        $infiniteLifetime = get_post_meta($post_id, 'subscription_lifetime_checkbox', true);
        if (empty($infiniteLifetime)) {
            $product['sub_lifetime'] = get_post_meta($post_id, 'subscription_lifetime', true);
        } else {
            $product['infinite_lifetime'] = 1;
        }
        return $product;
    }

    private function bulk_update_product($productDataArray) {
        try {
            $client = new SoapClient($this->wsdl_url, array('trace' => 1, 'encoding' => 'ISO-8859-1'));
            $accountInfo = array('user_name' => $this->user_name, 'auth_key' => htmlspecialchars_decode($this->auth_key), 'digest' => "", "nonce" => "");

            $data = array(
                'accountInfo' => $accountInfo,
                'productDataArray' => $productDataArray
            );

            $api_response_pro = $client->__soapCall('update_bulk_product', $data);
            $response['response'] = (get_object_vars($api_response_pro));
            if ($response['response']['status'] == "success") {

            } else {
                self::logger(print_r($response['response'], true));
            }
        } catch (Exception $ex) {

        }
    }

    public function check_corcrm_existance($product_id)
    {
        global $wpdb;
        $postTable = $wpdb->prefix . 'posts';
        $wc_post_id = $product_id;
        $log ="--------Checking in check_corcrm_existance-------------\n";
        // $hiecorProdId = $wpdb->get_var("SELECT `corcrm_product_id` FROM $postTable WHERE ID = $product_id");
        $sql = "SELECT `ID`,`corcrm_product_id`,`post_type`,post_title FROM $postTable WHERE `ID` = $product_id ";
        $result = $wpdb->get_row($sql);

        if(!isset($result->post_type) || $result->post_type != "product"){
            $log.="SQL Executed: \n".$sql;
            $log.="\nWpProductData:\n".print_r($result,true);
            self::logger($log);
            return false;
        }
        $hiecorProdId = (isset($result->corcrm_product_id)) ? $result->corcrm_product_id : 0;
        if($hiecorProdId>0){
            try {

                $hicorClient = $this->getHiecorClient();

                if(empty($hicorClient)){
                    throw new \Exception('Unable to Connect Hiecor CRM.');
                }

                $resp = $hicorClient->get('product/'.$hiecorProdId.'/');
                if (isset($resp->data->product_id) && !empty($resp->data->product_id) ) {
                    return true;
                }else{
                    $log.= "Product not found in hiecor ProductID #{$hiecorProdId} \n WP ProductID #{$product_id} \n".print_r($resp,true);
                    self::logger($log);
                    $upd = $wpdb->update($postTable, array('corcrm_product_id' => 0), array('id' => $wc_post_id), $format = null, $where_format = null);
                    if (!$upd  && $wpdb->last_error!='') {
                        self::logger("`check_corcrm_existance-` Unable to update HieCOR productID - " . $product_id . " Error:" . $wpdb->last_error);
                    }
                    $this->push_to_corcrm($product_id);
                }
            } catch (\Exception $ex) {

                $log.="Exception in `check_corcrm_existance` WP ProductID #{$product_id}: ".$ex->getMessage();
                self::logger($log);
                return false;
            }
        }else{
            $log.="Product doesn't exist WPProductID#{$product_id} calling `push_to_corcrm()`";
            self::logger($log);
            $this->push_to_corcrm($product_id);
        }

    }

    public function productRequest($data, $action) {

        $authKey = html_entity_decode($this->auth_key);
        $options = array('hc_api' => true, 'version' => 'v1');
        try {
            $hicorClient = new Client($this->hiecor_url, $this->user_name, $authKey, $this->agent_id, $options);
            $resp = $hicorClient->post($action, $data);
            //$this->dump($resp);
            if ($resp->success) {
                return $resp->data;
            }
        } catch (Exception $ex) {
            // echo 'here';
            print_r($ex->getMessage());
            self::logger($ex->getMessage());
            exit;
        }
    }

    public function getHiecorClient() {
        $authKey = html_entity_decode($this->auth_key);
        $options = array('hc_api' => true, 'version' => 'v1','timeout'=>60,'hc_api_prefix'=>'/rest/','X_API_SOURCE'=>'WPSite');
        $hicorClient=null;
        try {

            $hicorClient = new Client($this->hiecor_url, $this->user_name, $authKey, $this->agent_id, $options);
        } catch (\Exception $ex) {
            self::logger($ex->getMessage());
        }
        return $hicorClient;
    }

    public function saveHiecorProdcuctId($wc_prod_id, $hiecor_prod_id) {
        global $wpdb;
        $upd = $wpdb->update($wpdb->prefix . 'posts', array('corcrm_product_id' => $hiecor_prod_id), array('id' => $wc_prod_id));
        update_post_meta($wc_prod_id, 'hiecor_product_id', $hiecor_prod_id);
        if (!$upd && !empty($wpdb->last_error)) {
            self::logger("Unable to update CorCRM productID Error:" . $wpdb->last_error);
        }
    }

    public function check_syncable($product,$post_id) {
        global $product;
        $all_var_in_stock = 0;
        $all_var_outofstock = 0;
        $all_stock_at_var_level = 0;
        $all_stock_at_parent_level = 0;
        $variationPosts = $this->getVariationPosts($post_id);
        $product_level_stock = $product->managing_stock();
        $prod= array();
        if(!empty($variationPosts) && $product->is_type('variable')){
            // Stock managed at parent i.e parent.Manage_Stock=true
            if (!empty($product_level_stock)) {
                foreach ($variationPosts as $key => $value) {
                    $variationObj = wc_get_product($value->ID);
                    $notparent = $variationObj->managing_stock(); // if true i.e. it is managed at variation level
                    if($notparent == 1){
                        break;
                    }elseif($notparent == "parent"){
                        $var_stock_status = $variationObj->get_stock_status();
                        if($var_stock_status == "instock"){
                            $all_var_in_stock = 1;
                        }else{
                            $all_var_outofstock = 1;
                        }
                    }
                }
                if(($all_var_in_stock == 1 && $all_var_outofstock == 0) || ($all_var_in_stock == 0 && $all_var_outofstock == 1)){
                    if($all_var_in_stock == 1 && $all_var_outofstock == 0){
                        $prod['stock'] = get_post_meta($product->get_id(), '_stock', true);
                    }
                    if($all_var_in_stock == 0 && $all_var_outofstock == 1){
                        $prod['stock'] = 0;
                    }
                }
            }else{
                // Stock managed at Variation Level i.e parent.Manage_Stock=false
                foreach ($variationPosts as $key => $value) {
                    $variationObj = wc_get_product($value->ID);
                    $notparent = $variationObj->managing_stock(); // if true i.e. it is managed at variation level
                    if($notparent == 1){
                        $all_stock_at_var_level = 1;
                    }else{
                        $all_stock_at_parent_level = 1;
                        $var_stock_status = $variationObj->get_stock_status();
                        if($var_stock_status == "instock"){
                            $all_var_in_stock = 1;
                        }else{
                            $all_var_outofstock = 1;
                        }
                    }
                }
                if(($all_stock_at_var_level == 1 && $all_stock_at_parent_level == 0) || ($all_stock_at_var_level == 0 && $all_stock_at_parent_level == 1)){
                    if($all_stock_at_var_level == 0 && $all_stock_at_parent_level == 1){
                        if(($all_var_in_stock == 1 && $all_var_outofstock == 0) || ($all_var_in_stock == 0 && $all_var_outofstock == 1)){
                            if($all_var_in_stock == 1 && $all_var_outofstock == 0){
                                $prod['unlimited_stock'] = 'Yes';
                                $prod['stock'] = 0;
                            }
                            if($all_var_in_stock == 0 && $all_var_outofstock == 1){
                                $prod['stock'] = 0;
                            }
                        }else{
                            return false;
                        }
                    }else{
                        $prod['stock'] = 0;
                    }
                }else{
                    return false;
                }
            }
        }
        return $prod;
    }

    public function check_product_linking($wcProdId,$sku,$prodBrand){
        global $wpdb;
        $postTable = $wpdb->prefix . 'posts';
        $corcrmProdId = get_post_meta( $wcProdId, 'hiecor_product_id', true );
        if(!empty($corcrmProdId)){
            $upd = $wpdb->update($postTable, array('corcrm_product_id' => $corcrmProdId), array('id' => $wcProdId));
            if (!$upd && !empty($wpdb->last_error)) {
                self::logger('Unable to update CorCRM productID Error:' . $wpdb->last_error);
            }
        }

        if( !empty($sku)){
            $endpoint="/rest/v1/product/get-brand-product/";
            $url = rtrim($this->hiecor_url,'/') .$endpoint;
            $pro_data=array();
            $pro_data['product_code']= $sku;
            $pro_data['brand']= $prodBrand;
            $pro_data['source']="woocommerce";
            try {
                $url = $url . '?' . http_build_query($pro_data);
                $response = wp_remote_get( $url, array(
                        'timeout'     => 45,
                        'redirection' => 5,
                        'httpversion' => '1.0',
                        'blocking'    => true,
                        'headers'     =>  array(
                            'X-USERNAME' =>  $this->user_name,
                            'X-AUTH-KEY' => html_entity_decode($this->auth_key),
                            'X-AGENT-ID' => $this->agent_id
                        )
                    )
                );
            }catch (\Exception $ex) {
                $log = "CorCRM API Exception \n URL-#{$url} \n ".$ex->getMessage().' \n Error occured during get brand product.';
                $log .= "\n PARAM- ".print_r($pro_data,true);
                self::logger($log);
            }

            if(is_wp_error($response)){
                $log = "ERROR IN get-brand-product - \n URL-".$url;
                $log.= "\n RAW RESPONSE".print_r($response,true);
                self::logger($log);
            }

            $res = '';
            if(isset($response['body'])){
                $res = json_decode($response['body']);
            }

            if(empty($res) || (isset($res->success) && $res->success==false)){
                $log="ERROR IN get-brand-product \n URL-".$url;
                $log.="\n PARAM- ".print_r($pro_data,true)."\n RESPONSE-".print_r($res,true)."\n Raw Response ".print_r($response,true);
                self::logger($log);
            }

            if(!empty($res->data[0]->product_id)){
                $hiecorProdId = $res->data[0]->product_id;
                $this->saveHiecorProdcuctId($wcProdId, $hiecorProdId);

                //self::logger(print_r($res,true));
                // Get the variable product
                if(!empty($res->data[0]->variation)){
                    $externalVariations = [];
                    foreach ($res->data[0]->variation as $varExt) {
                        $attributes = [];
                        foreach ($varExt->variation_attribute as $attribute) {
                            $attributes[strtolower($attribute->attribute_name)] = strtolower($attribute->attribute_value);
                        }
                        $externalVariations[$varExt->variation_id] = $attributes;
                    }
                }
                $productData = wc_get_product( $wcProdId );
                if ($productData->is_type('variable')) {
                    $matchingVariations = $this->find_matching_variations($externalVariations, $productData);
                    if(!empty($matchingVariations)){
                        foreach ($matchingVariations as $key => $value) {
                            $hiecor_variation_id = $value['external_variation_id'];
                            $woocommerce_variation_id = $value['woocommerce_variation_id'];
                            $this->saveHiecorProdcuctId($woocommerce_variation_id, $hiecor_variation_id);
                        }
                    }
                    return;
                }
                return;
            }
        }
    }

    public function find_matching_variations($externalVariations, $woocommerceProduct)
    {
        // Get all children IDs of the variable product
        $variations_ids = $woocommerceProduct->get_children();
        $formattedWcVariations = [];
        //self::logger(print_r($variations_ids,true));
        foreach ($variations_ids as $variation_id) {
            $variation_obj = wc_get_product($variation_id);
            $attributes_info = [];
            foreach ($variation_obj->get_attributes() as $attr => $value) {
                //get attribute name
                $attribute_obj = get_taxonomy($attr);
                if( isset($attribute_obj->labels) && isset($attribute_obj->labels->singular_name)){
                    $attribute_name = $attribute_obj->labels->singular_name;
                }else{
                    $attribute_name = wc_attribute_label($attr);
                }
                $attr_name = strtolower($attribute_name);

                //get attribute value
                $attr_value = '';
                $term = get_term_by('slug', $value, $attr);
                if ($term && !is_wp_error($term)) {
                    $attr_value = $term->name;
                }
                $attributes_info[$attr_name] = strtolower($attr_value);
            }
            $formattedWcVariations[$variation_id] = $attributes_info;
        }
        //self::logger(print_r($formattedWcVariations,true));
        //self::logger(print_r($externalVariations,true));
        // Compare with hiecor variations
        $matches = [];
        foreach ($externalVariations as $extId => $extAttributes) {
            foreach ($formattedWcVariations as $wcId => $wcAttributes) {
                $arrayDiff=array_diff_assoc($extAttributes,$wcAttributes);
                if (count($extAttributes)==count($wcAttributes) && count($arrayDiff)===0) {
                    $matches[] = [
                        'external_variation_id' => $extId,
                        'woocommerce_variation_id' => $wcId
                    ];
                }
            }
        }
        //self::logger(print_r($matches,true));
        return $matches;
    }

}