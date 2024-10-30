<?php

use Hiecor\Rest\Client;

class Payment_Utility {

    public function getProductsData($items) {
        foreach ($items as $item) {
            global $wpdb;  
            $hiecorVariationId = 0;
            $product = $this->getProduct($item['product_id']);
            $hiecorProdId = $product->post->corcrm_product_id;
            if(empty($hiecorProdId)){
                $query = $wpdb->prepare("SELECT corcrm_product_id FROM {$wpdb->posts} WHERE ID = {$item['product_id']}");
                $hiecorProdId = $wpdb->get_var($query);
            }
            if(empty($hiecorProdId)){
                $hiecorProdId =  get_post_meta($item['product_id'], 'hiecor_product_id', true);
            }
            $product_name = $item['name'];
            $sku = $product->get_sku();
            if(strtolower($product_name) == 'tip' || strtolower($sku) == 'tip'){
                continue;
            }
            $price = $item['line_subtotal'] / $item['qty'];

            $productData = array(
                "man_price" => $price,
                "product_id" => $hiecorProdId,
                "qty" => $item['qty'],
            );

            $meta = $item->get_meta_data();
           
            $iframe_attr_values = array();
            foreach ($meta as $key => $value) {
                if($value->key == 'hiecor_iframe_attr_var_ids'){
                    $iframe_attr_values = $value->value;
                    break;
                }
            }
            
            $formatted_meta_data = $item->get_formatted_meta_data();
            
            if(!empty($formatted_meta_data)){ 
                $manual_description = "";
                foreach($formatted_meta_data as $metadata){
                    // This is hiecor iframe attribute options so skip them
                    if($metadata->key == 'Options'){  
                        continue;
                    }else{ 
                        $manual_description .= strip_tags($metadata->display_key).": ".strip_tags($metadata->value).", ";
                    }
                   
                    
                }
				$formatted_manual_description = rtrim($manual_description,', ');
                $productData['manual_description'] =  $formatted_manual_description;
            }
            
            
            
            if ( get_post_meta($item['product_id'],'allow_hiecor_subscription',true ) && get_post_meta($item['product_id'],'allow_hiecor_subscription',true )== "yes" ){		
                if(!empty($item['hiecor_subscription'])){		
                   $sub_opt= explode(" ",$item['hiecor_subscription']);		
                    $subscription_arr  = array(		
                        "days_or_months" => $sub_opt[1],		
                        "payment_period" => $sub_opt[0],		
                        "lifespan" => 0		
                    );		
                     $productData['subscription_data'] = $subscription_arr;  		
                }    		
            }
                
            if ($product->is_type('variable')) {
                $variation_id = $item->get_variation_id();
                $variationPost = $this->get_post($variation_id);
                $hiecorVariationId = $variationPost->corcrm_product_id;
                if(empty($hiecorVariationId)){
                    $query = $wpdb->prepare("SELECT corcrm_product_id FROM {$wpdb->posts} WHERE ID = {$variation_id}");
                    $hiecorVariationId = $wpdb->get_var($query);
                }
                if(empty($hiecorVariationId)){
                    $hiecorVariationId =  get_post_meta($variation_id, 'hiecor_product_id', true);
                }
            }

            if ($hiecorVariationId) {
                $productData['variations'][] = array('variation_id' => $hiecorVariationId);
            }
            
            /*Check If product iframe meta hiecor iframe requierd if yes than added to the product variation*/
            if ( get_post_meta($item['product_id'],'hiecor_iframe_required',true ) && get_post_meta($item['product_id'],'hiecor_iframe_required',true )== "yes" ){
                if(!empty($iframe_attr_values)){
                    $productData['variations'] = $iframe_attr_values;
                }
            }
             
            $products[] = $productData;
        }
        return $products;
    }

    public function getProduct($post_id) {
        $_pf = new WC_Product_Factory();
        return $_pf->get_product($post_id);
    }
    
     public function get_post($post_id) {
        return get_post($post_id);
    }
    
    public function logger($msg) {
        $log = new WC_Logger();
        $log->add('api', $msg);
    }
}