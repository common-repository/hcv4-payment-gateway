<?php

/*Added Boostrap Modal css and js */
function hiecor_iframe_modal_js_css() {
    if(is_product() || is_page('cart') || is_page('checkout')){    
        wp_register_style('custom_css', plugins_url('/css/heicor-iframe-model.css', __FILE__));
        wp_enqueue_style('custom_css');
        
    }
    
}  
add_action( 'wp_enqueue_scripts', 'hiecor_iframe_modal_js_css' );  

/* Add a custom button in Single page of Woocommerce */ 
function hiecor_action_woocommerce_before_add_to_cart_button(  ) 
{
    global $product,$wpdb;
    $tbl_name = $wpdb->prefix . 'posts';
    $wc_pro_id = $product->get_id();
    $hiecor_pro_id = $wpdb->get_var("SELECT `corcrm_product_id` FROM $tbl_name WHERE ID = $wc_pro_id");
    
    $is_hiecor_iframe_required = get_post_meta($wc_pro_id ,'hiecor_iframe_required',true);
    if($is_hiecor_iframe_required == 'yes'){ ?> 
        <script>
        jQuery(document).ready(function($) {
           jQuery('.single_add_to_cart_button').css('display','none'); 
           jQuery('.quantity').css('display','none');
        });
        </script>
        <?php 
        echo '<button type="button" class="btn btn-info btn-lg hiecor_pos_iframe_button" value='.$wc_pro_id.' onclick="hiecor_open_cart_modal('.$hiecor_pro_id.');" id="myBtn">SELECT OPTIONS</button>';
        //echo '<button type="button" class="btn btn-info btn-lg hiecor_pos_iframe_button" value='.$wc_pro_id.' onclick="hiecor_open_cart_modal('.$hiecor_pro_id.');" >SELECT OPTIONS</button>';
    }else{ 
        
    }
}

add_action( 'woocommerce_before_add_to_cart_button', 'hiecor_action_woocommerce_before_add_to_cart_button', 10, 0 );
/*-------------------------End----------------------- */

/* Custom Product add to cart via ajax  */
add_action( 'wp_ajax_nopriv_hiecor_add_to_cart', 'hiecor_ajax_handler' );
add_action( 'wp_ajax_hiecor_add_to_cart', 'hiecor_ajax_handler' );

function hiecor_ajax_handler()
{
    global $product;
    /* Quantity  */
    if( isset($_POST['p_data'][qty]) && ! empty($_POST['p_data'][qty]) ){
        $quantity = $_POST['p_data'][qty];
    }
    /* Price */
    if( isset($_POST['p_data']['base_price']) && ! empty($_POST['p_data']['base_price']) ){
        $c_price = $_POST['p_data']['base_price']; 
    }
    /* All selected  attribute text values */
    if( isset($_POST['p_data']['selected_values']) && ! empty($_POST['p_data']['selected_values']) ){
        $selected_values = $_POST['p_data']['selected_values'];
    }   
    
    /* All selected attribute or variation ids value */
    if( isset($_POST['p_data']['variations']) && ! empty($_POST['p_data']['variations']) ){
        $variations = $_POST['p_data']['variations'];
    }
   
    $wc_prod_id = $_POST['wc_prod_id']; //Woocommerce Product Id
    $product = wc_get_product($wc_prod_id);
   
    //Add product to WooCommerce cart.
    $custom_data = array();
    $custom_data['hiecor_iframe_selected_options'] = $_POST['p_data']['selected_values'];
    $custom_data['hiecor_iframe_attr_var_data'] = $variations;
    $custom_data['new_price'] = $c_price;
    
    if( WC()->cart->add_to_cart( $wc_prod_id, $quantity ,$product_id,array(), $custom_data )) {
        global $woocommerce;
        $cart_url = $woocommerce->cart->get_cart_url();
        $output = array('success' => 1, 'msg' =>'Added the product to your cart', 'cart_url' => $cart_url );
    } else {
        $output = array('success' => 0, 'msg' => 'Unable to add product in your cart');
    }
    echo (json_encode($output));
    die;
}
/*----------------------------------------------------------------------*/


/* If custom price added into the cart  */
add_action( 'woocommerce_before_calculate_totals', 'custom_cart_item_price', 30, 1 );
function custom_cart_item_price( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ){
        return;
    }
    
    foreach ( $cart->get_cart() as $cart_item ) {
        //echo "<pre>"; print_r($cart_item); die;
        if( isset($cart_item['new_price']) ){
            $cart_item['data']->set_price( $cart_item['new_price'] );
        }
    }
}

/**/

function hiecor_custom_option_get_item_data( $item_data, $cart_item_data ) {
   
    if( !empty( $cart_item_data['hiecor_iframe_selected_options'] ) ) {
        $item_data = array();
        foreach ($cart_item_data['hiecor_iframe_selected_options'] as $key => $value) {

            $item_data[] = array(
                'id'     => $value['attribute_id'], 
                'key'     => $value['attribute_name'],
                'value'   => wc_clean( $value['attribute_value'] )
            );	 
        }
    }
    return $item_data;
}
add_filter( 'woocommerce_get_item_data', 'hiecor_custom_option_get_item_data', 10, 2 );


//iframe Modal Popup
function hiecor_pos_iframe_modal_function() {  
    $iframe_settings_url = get_option('woocommerce_corcrm_payment_settings', null);
    
    if(!empty($iframe_settings_url['hiecor_url'])){
        $hiecor_base_url = rtrim($iframe_settings_url['hiecor_url'],'/');
        $iframe_src_url  = $hiecor_base_url.'/pos_attribute_iframe/?productID=';
    }
     
    if(is_product()){
    ?>
   <div id="hiecorIframeModal" class="hiecor-modal">

        <!-- Modal content -->
        <div class="hiecor-modal-content">
          <div class="hiecor-modal-header">
            <span class="hiecor-modal-close">&times;</span>

          </div>
          <div class="hiecor-modal-body">
            <img id="hiecor-loader" class="center-block" style="top:200px; position:relative;" src="<?php echo plugin_dir_url(__FILE__) . 'images/loader.gif'; ?>"  alt="loading gif"/> 
                <iframe id="custom_iframe" width="1200" height="600" scrolling="yes" class="embed-responsive-item" frameborder="0"></iframe>
          </div>
          <div class="hiecor-modal-footer">
            <button type="button" class="btn btn-default  close_popup" data-dismiss="modal">CLOSE</button>
            <button type="button" class="btn btn-default btn-success custom_add_to_cart">ADD</button>
          </div>
        </div>

    </div>

  
    <?php }?>
   
   <script type="text/javascript">
       
    function hiecor_open_cart_modal(h_prod_id)
    {
        
        var hiecorIframeModal = document.getElementById("hiecorIframeModal");
        
        hiecorIframeModal.style.display = "block";
        
        // Get the <span> element that closes the modal
        var span = document.getElementsByClassName("hiecor-modal-close")[0];
       
        jQuery('#hiecor-loader').show();
        
        var url = "<?php echo $iframe_src_url; ?>"+h_prod_id;
        jQuery('#custom_iframe').on('load', function () {
            jQuery('#hiecor-loader').hide();
        });
        
        if(jQuery('#hiecorIframeModal').css('display') == 'block'){  
            hcv4_hide_body_scroll();
            jQuery("#custom_iframe").attr('src',url); 
        }
        
        // When the user clicks on <span> (x), close the modal
        span.onclick = function() {
          hcv4_show_body_scroll();  
          jQuery('#hiecor-loader').css('display','block');
          jQuery("#custom_iframe").attr('src',''); 
          var obj = {name:'modal_close',data:''};
          jQuery("#custom_iframe")[0].contentWindow.postMessage(obj,"*");
          hiecorIframeModal.style.display = "none";
        }
        
        // when user click on the cose button
        jQuery('body').on('click', '.close_popup', function() {
          hcv4_show_body_scroll();  
          jQuery('#hiecor-loader').css('display','block');
          jQuery("#custom_iframe").attr('src',''); 
          var obj = {name:'modal_close',data:''};
          jQuery("#custom_iframe")[0].contentWindow.postMessage(obj,"*");
          hiecorIframeModal.style.display = "none";
            
        });
        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
          if (event.target == hiecorIframeModal) {
            jQuery('#hiecor-loader').css('display','block');
            jQuery("#custom_iframe").attr('src',''); 
            var obj = {name:'modal_close',data:''};
            jQuery("#custom_iframe")[0].contentWindow.postMessage(obj,"*");
            hiecorIframeModal.style.display = "none";
            hcv4_show_body_scroll();
          }
        }
        
        
        
    }
    
    
    jQuery('body').on('click', '.custom_add_to_cart', function(e) {
        e.preventDefault();
        var obj = {name:'add_product',data:''};
        jQuery('#hiecor-loader').show();
        jQuery("#custom_iframe")[0].contentWindow.postMessage(obj,"*");
        window.addEventListener("message", receiveMessageFromModal, false);
    });
    
    
    function hcv4_hide_body_scroll(){
        const scrollY = document.documentElement.style.getPropertyValue('--scroll-y');
        const body = document.body;
        body.style.position = 'fixed';
        body.style.top = `-jQuery{scrollY}`;
    }
        
    window.addEventListener('scroll', () => {
            document.documentElement.style.setProperty('--scroll-y', `${window.scrollY}px`);
    });
    
    function hcv4_show_body_scroll(){
        const body = document.body;
        const scrollY = body.style.top;
        body.style.position = '';
        body.style.top = '';
        window.scrollTo(0, parseInt(scrollY || '0') * -1);
    }
    
    
    function receiveMessageFromModal(event)
    {
        
        if (event.data.name === "product_data"){
            var p_data = event.data.data;
            var prod_id = jQuery('.hiecor_pos_iframe_button[type="button"]').val();
            jQuery.ajax({
                type : "POST",
                dataType : "json",
                url : "<?php echo admin_url('admin-ajax.php'); ?>",
                data : {action: "hiecor_add_to_cart",'p_data':p_data,'wc_prod_id':prod_id },
                success: function(response) {
                    if(response.success == 1){
                        jQuery('#hiecor-loader').css('display','block');
                        jQuery( ".close_popup" ).trigger( "click" );
                        window.location.href = response.cart_url;
                    }

                }
            });
         }
    }
    
    

 </script>        
<?php }
add_action('wp_footer', 'hiecor_pos_iframe_modal_function');