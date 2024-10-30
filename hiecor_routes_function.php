<?php

function map_hiecor_prod_id($data) {
    global $wpdb;
    $corcrm_prod_id = $data['id'];
    $wc_id = isset($_POST['woo_product_id']) ? $_POST['woo_product_id'] : 0;
    if(!empty($wc_id)){
        $upd = $wpdb->update($wpdb->prefix . 'posts', array('corcrm_product_id' => $corcrm_prod_id), array('id' => $wc_id), $format = null, $where_format = null);
        if (!$upd && !empty($wpdb->last_error)) {
            return array('success'=>false,'message'=>'','error'=>$wpdb->last_error);
        }else{
            return array('success'=>true,'message'=>'','error'=>'');
        }
    }else{
        return array('success'=>false,'message'=>'','error'=>'woo_product_id cannot be blank.');
    }
    
}

function attribute_mappings($data) {
    global $wpdb;
    $mappingTable = $wpdb->prefix . 'hiecor_attr_mapping';
    $prodTbl = $wpdb->prefix . 'posts';
    $attr_data_arr = json_decode($data->get_body(),true);
    $response = array('success' => true, 'message' => '');
    if(!empty($attr_data_arr)){
        $newAttributeMapping=array();
        foreach($attr_data_arr as $key=>$value){
            $hiecor_prod_id = $value['hiecor_prod_id'];
            $hiecor_id = $value['hiecor_id'];
            $wc_id = $value['wc_id'];
            $mapping_type = $value['mapping_type'];
            $attr_name = $value['attribute_name'];
            $newAttributeMapping[]=$hiecor_id;
            $sql="SELECT `ID` FROM $prodTbl WHERE corcrm_product_id = {$hiecor_prod_id}";
            $wc_prod_id = $wpdb->get_var($sql);
            if(!$wc_prod_id){
                $log = new WC_Logger();
                $errors = "SQL ERROR:-".$wpdb->last_error."\n SQL EXECUTED:-".$wpdb->last_query."\n";
                $log->add('api', $errors);
            }
            $insertData = array(
                'wc_product_id' => $wc_prod_id,
                'foreign_id' => $wc_id,
                'hiecor_id' => $hiecor_id,
                'mapping_type' => "$mapping_type", 
                'wc_option' => "$attr_name"
            );
            $sql="SELECT `mapping_id` FROM $mappingTable WHERE `wc_product_id` = {$wc_prod_id} AND `foreign_id`={$wc_id} AND `hiecor_id`={$hiecor_id} ";
            $mapping_id = $wpdb->get_var($sql);
            if(!$mapping_id){
                if(!$wpdb->insert($mappingTable, $insertData)){
                $log = new WC_Logger();
                $errors = "SQL ERROR:-".$wpdb->last_error."\n SQL EXECUTED:-".$wpdb->last_query."\n";
                $errors.=print_r($insertData,true);
                $log->add('api', $errors);
                }
            }
        }
        $getall=$wpdb->get_results("SELECT `hiecor_id` FROM $mappingTable WHERE wc_product_id = '$wc_prod_id' and mapping_type='attribute'");
        $allAttributeMapping=array();
        foreach ($getall as $ids){
            $allAttributeMapping[]=$ids->hiecor_id;
        }
        $result=array_diff($allAttributeMapping,$newAttributeMapping);
        if(empty($result)){
            return $response;
        }else{
            $sqlerrors = array();
            foreach ($result as $value){
            $delData = array( 'hiecor_id' => $value,'wc_product_id'=>$wc_prod_id);
            $isDel = $wpdb->delete( $mappingTable, $delData);
                if(!$isDel  && $wpdb->last_error!=''){
                    $log = new WC_Logger();
                    echo $errors = "SQL ERROR:-".$wpdb->last_error."\n SQL EXECUTED:-".$wpdb->last_query."\n";
                    $log->add('api', $errors);
                    $sqlerrors[] = $wpdb->last_error;
                }
            }   
            if (count($sqlerrors) == 0) {
                $response['message'] = 'success';
            } else {
                $response['success'] = false;
                $response['error'] = $sqlerrors;
            }
            return $response;
        }
    } 
}

function delete_mapping($data) {
    global $wpdb;
     $mappingTable = $wpdb->prefix . 'hiecor_attr_mapping';
    $wc_prod_id = $data['id'];
    $mapping_type = isset($_POST['mapping_type']) ? sanitize_text_field($_POST['mapping_type']):"ALL";
    $where = "WHERE 1=1";
        if($mapping_type == "attribute" || $mapping_type == "variation" ){
            $where.=" AND mapping_type={$mapping_type}";
        }
        $sql = "DELETE
                FROM $mappingTable
               {$where} AND `wc_product_id`={$wc_prod_id}";  
        $result =$wpdb->query($sql);
    if ($result) {
        return array('success'=>true,'message'=>'','error'=>'');
    }else{
        return array('success'=>false,'message'=>'','error'=>'');
    }
}

function ping_request(){
    return array('success'=>true);
}