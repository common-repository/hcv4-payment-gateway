<?php
add_action('wp_ajax_woocommerce_save_attributes', 'hiecor_filter_function_name_3102', 0);

function hiecor_filter_function_name_3102() {
    check_ajax_referer('save-attributes', 'security');
    parse_str($_POST['data'], $data);
    $data['product_id'] = absint($_POST['post_id']);
    attributeNameMapping($data);
}

function attributeNameMapping($data) {
    global $wpdb;
    $mappingTable = $wpdb->prefix . 'hiecor_attr_mapping';
    $mappingTable2 = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
    if (!empty($data['attribute_names'])) {
        $newAttributeMapping=array();
        foreach ($data['attribute_names'] as $key => $attrName) {  
            //check if this attribute has any values if not it means this attribute has been deleted
            if(!isset($data['attribute_values'][$key])){
                continue;
            }
            $attname= ltrim($attrName,"pa_");
            $attrs = $wpdb->get_row("SELECT `attribute_id`,`attribute_label` FROM $mappingTable2 WHERE attribute_name = '$attname'");
            $attrMappingId = getAttrMappingId($data['product_id'], $attrs->attribute_id);
            if ($attrMappingId > 0) {
                $upd = $wpdb->update($mappingTable, array('wc_option' => $attrs->attribute_label), array('mapping_id' => $attrMappingId), $format = null, $where_format = null);
                if(!$upd &&  $wpdb->last_error != ''){
                    $log = new WC_Logger();
                    $errors = "SQL ERROR:-".$wpdb->last_error."\n SQL EXECUTED:-".$wpdb->last_query."\n";
                    $log->add('api', $errors);
                }
            } else {
                if(!empty($attrs->attribute_label)){
                    $dataarr = array('wc_product_id' => $data['product_id'], 'mapping_type' => 'attribute', 'wc_option' => $attrs->attribute_label,'foreign_id'=>$attrs->attribute_id,'hiecor_id'=>'');
                    $insert=$wpdb->insert($mappingTable, $dataarr);
                    if(!$insert){
                        $log = new WC_Logger();
                        $errors = "SQL ERROR:-".$wpdb->last_error."\n SQL EXECUTED:-".$wpdb->last_query."\n";
                        $log->add('api', $errors);
                   }else{
                       $attrMappingId = $wpdb->insert_id;
                   }
                }
            }
//            if (!empty($data['attribute_values']) && count($data['attribute_values'] > 0)) {
//                //attributeValueMapping($data, $key);
//            }
            $newAttributeMapping[]=$attrMappingId;
        }
       
        //now remove attr mapping for this products which are not used
        $wp_prduct_Id=$data['product_id'];
        //Get All Mappings and compare with newAttributeMapping
        $getall=$wpdb->get_results("SELECT `mapping_id` FROM $mappingTable WHERE wc_product_id = '$wp_prduct_Id' and mapping_type='attribute'");
        $allAttributeMapping=array();
        foreach ($getall as $mapping_id){
            $allAttributeMapping[]=$mapping_id->mapping_id;
        }
        
       $result=array_diff($allAttributeMapping,$newAttributeMapping);
       foreach ($result as $v){
           $delData = array( 'mapping_id' => $v);
           $isDel = $wpdb->delete( $mappingTable, $delData);
           if(!$isDel  && $wpdb->last_error!=''){
                $log = new WC_Logger();
                echo $errors = "SQL ERROR:-".$wpdb->last_error."\n SQL EXECUTED:-".$wpdb->last_query."\n";
                $log->add('api', $errors);
           }
       }       
}}

function attributeValueMapping($data, $key) {
    global $wpdb;
    $mappingTable = $wpdb->prefix . 'hiecor_attr_mapping';

    $result = strstr($data['attribute_values'][$key], "|");
    if (!empty($result)) {
        $attribute_values = explode('|', $data['attribute_values'][$key]);
        foreach ($attribute_values as $key => $attrValue) {
            $attrValueMappingId = getAttrValueMappingId($data['product_id'], trim($attrValue));
            if (empty($attrValueMappingId)) {
                $insert= $wpdb->insert($mappingTable, array('wc_product_id' => $data['product_id'], 'mapping_type' => 'attribute_value', 'wc_option' => trim($attrValue),'foreign_id'=>'','hiecor_id'=>''));
                if(!$insert){
                    $log = new WC_Logger();
                    $errors = "SQL ERROR:-".$wpdb->last_error."\n SQL EXECUTED:-".$wpdb->last_query."\n";
                    $log->add('api', $errors);
               }
            }
        }
    } else {
        $attrValueMappingId = getAttrValueMappingId($data['product_id'], $data['attribute_values'][$key]);
        if (empty($attrValueMappingId)) {
            $insert=$wpdb->insert($mappingTable, array('wc_product_id' => $data['product_id'], 'mapping_type' => 'attribute_value', 'wc_option' => $data['attribute_values'][$key],'foreign_id'=>'','hiecor_id'=>''));
            if(!$insert){
                    $log = new WC_Logger();
                    $errors = "SQL ERROR:-".$wpdb->last_error."\n SQL EXECUTED:-".$wpdb->last_query."\n";
                    $log->add('api', $errors);
               }
        }
    }
    return true;
}

add_action('woocommerce_after_product_attribute_settings', 'hiecor_action_woocommerce_after_product_attribute_settings', 10, 2);

function hiecor_action_woocommerce_after_product_attribute_settings($attribute, $i) {
    global $post;
    $postId = $post->ID;
    $attribute_name = $attribute->get_name();
    $mappingId = getAttrMappingId($postId, $attribute_name);
    if (!empty($attribute_name) && $mappingId > 0) {
        echo '<input type="hidden" name="hieco_mapping_id[]" value="' . $mappingId . '">';
    }
}

function getAttrMappingId($postId, $attribute_id) {
    global $wpdb;
    $mappingTable = $wpdb->prefix . 'hiecor_attr_mapping';
    $attrMappingId = $wpdb->get_var("SELECT `mapping_id` FROM $mappingTable WHERE wc_product_id = '".trim($postId)."' and mapping_type='attribute' and foreign_id='" . $attribute_id . "'");
    return $attrMappingId;
}

function getAttrValueMappingId($postId, $attribute_value) {
    global $wpdb;
    $mappingTable = $wpdb->prefix . 'hiecor_attr_mapping';
    $attrValueMappingId = $wpdb->get_var("SELECT `mapping_id` FROM $mappingTable WHERE wc_product_id = '".trim($postId)."' and mapping_type='attribute_value' and wc_attr_option='" . $attribute_value . "'");
    return $attrValueMappingId;
}
