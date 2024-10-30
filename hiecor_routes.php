<?php
include_once( 'hiecor_routes_function.php' );

add_action('rest_api_init', function () {
   
  register_rest_route('hiecor/v2', '/productid/(?P<id>\d+)', array(
        'methods' => 'POST',
        'callback' => 'map_hiecor_prod_id',
    ));
  
    register_rest_route('hiecor/v2', '/attributeMappings/(?P<id>\d+)', array(
        'methods' => 'POST',
        'callback' => 'attribute_mappings',
    ));
    
    register_rest_route('hiecor/v2', '/deletemapping/(?P<id>\d+)', array(
        'methods' => 'POST',
        'callback' => 'delete_mapping',
    ));
    
    register_rest_route('hiecor/v2', '/ping', array(
        'methods' => 'GET',
        'callback' => 'ping_request',
    ));
});

