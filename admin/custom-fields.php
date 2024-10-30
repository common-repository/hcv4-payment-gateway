<?php

// Add UPC field for simple product


if (!in_array('woo-add-gtin/woocommerce-gtin.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('woocommerce_product_options_inventory_product_data', 'hiecor_add_upc_in_inventory_tab');
}

function hiecor_add_upc_in_inventory_tab() {
    global $post;
    $upc = get_post_meta($post->ID, 'hwp_product_gtin', true);

    woocommerce_wp_text_input(
            array(
                'id' => '_hiecor_upc',
                'name' => '_hiecor_upc',
                'label' => __('UPC', 'woocommerce'),
                'placeholder' => '',
                'value' => $upc,
                'type' => 'text',
                'custom_attributes' => array()
            )
    );
}

// Add Cost field for simple product

if (!in_array('woocommerce-cost-of-goods/woocommerce-cost-of-goods.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('woocommerce_product_options_general_product_data', 'hiecor_add_cost_field');
}

//add_action('woocommerce_product_options_general_product_data', 'hiecor_add_cost_field');

function hiecor_add_cost_field() {
    global $post;
    $cost = get_post_meta($post->ID, '_wc_cog_cost', true);

    woocommerce_wp_text_input(
            array(
                'id' => '_hiecor_cost',
                'name' => '_hiecor_cost',
                'label' => __('Cost of Good', 'woocommerce'),
                'placeholder' => '',
                'value' => $cost,
                'type'  => 'text',
                'class' => 'wc_input_price',
                'custom_attributes' => array()
            )
    );
}

// Save UPC and Cost for simple products

add_action('woocommerce_process_product_meta_simple', 'hiecor_save_cost_and_upc');

function hiecor_save_cost_and_upc($post_id) {
    global $wpdb;
    $hiecorpro_cost = trim($_POST['_hiecor_cost']);
    if (isset($hiecorpro_cost) && !empty($hiecorpro_cost)) {
        update_post_meta($post_id, '_wc_cog_cost', $hiecorpro_cost );
    }
    $hiecorepro_upc = sanitize_text_field($_POST['_hiecor_upc']);
    if (isset($hiecorepro_upc) && !empty($hiecorepro_upc)) {
        update_post_meta($post_id, 'hwp_product_gtin',$hiecorepro_upc);
    }
}

// Add Cost and UPC fields for variation products

add_action('woocommerce_product_after_variable_attributes', 'hiecor_add_upc_fields_for_variations', 10, 3);

function hiecor_add_upc_fields_for_variations($loop, $variation_data, $variation) {

    if (!in_array('woocommerce-cost-of-goods/woocommerce-cost-of-goods.php', apply_filters('active_plugins', get_option('active_plugins')))) {

        $cost = get_post_meta($variation->ID, '_wc_cog_cost', true);

        woocommerce_wp_text_input(
                array(
                    'id' => '_hiecor_cost',
                    'name' => '_hiecor_cost[]',
                    'label' => __('Cost of Good', 'woocommerce'),
                    'placeholder' => '',
                    'desc_tip' => 'true',
                    'description' => __('Product Cost', 'woocommerce'),
                    'value' => $cost,
                    'type'  => 'text',
                    'class' => 'wc_input_price',
                    'custom_attributes' => array()
                )
        );
    }

    if (!in_array('woo-add-gtin/woocommerce-gtin.php', apply_filters('active_plugins', get_option('active_plugins')))) {

        $upc = get_post_meta($variation->ID, 'hwp_var_gtin', true);

        woocommerce_wp_text_input(array(
            'id' => '_hiecor_upc',
            'name' => '_hiecor_upc[]',
            'label' => __('UPC', 'woocommerce'),
            'placeholder' => '',
            'desc_tip' => 'true',
            'description' => __('UPC code', 'woocommerce'),
            'value' => $upc,
            'custom_attributes' => array()
                )
        );
    }
}

add_action('woocommerce_save_product_variation', 'hiecor_save_upc_cost_for_variation', 10, 2);

function hiecor_save_upc_cost_for_variation($post_id) {

    global $wpdb;
    $hiecor_cost = wc_clean(wp_unslash($_POST['_hiecor_cost']));
    if (isset($hiecor_cost) && count($hiecor_cost) > 0) {
        $hiecor_var_post_id = wc_clean(wp_unslash($_POST['variable_post_id']));
        if (isset($hiecor_var_post_id) && count($hiecor_var_post_id) > 0) {
            
            $hiecor_var_post_id = array_values($hiecor_var_post_id);
             
            foreach ($hiecor_var_post_id as $key => $variation_id) {
                $hiecor_cost_key = wc_clean(wp_unslash($_POST['_hiecor_cost'][$key]));
                if ($hiecor_cost_key > 0) {
                    update_post_meta($variation_id, '_wc_cog_cost', $hiecor_cost_key);
                }
            }
        }
    }

    if (isset($_POST['_hiecor_upc']) && count($_POST['_hiecor_upc']) > 0) {
        $hiecor_var_upc_id = wc_clean(wp_unslash($_POST['variable_post_id']));
        if (isset($hiecor_var_upc_id) && count($hiecor_var_upc_id) > 0) {
            $hiecor_var_upc_id = array_values($hiecor_var_upc_id);
            foreach ($hiecor_var_upc_id as $key => $variation_id) {
                $hiecor_upc = wc_clean(wp_unslash($_POST['_hiecor_upc'][$key]));
                if (!empty($hiecor_upc)) {
                    update_post_meta($variation_id, 'hwp_var_gtin', $hiecor_upc);
                }
            }
        }
    }
}

?>