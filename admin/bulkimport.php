<?php

class Hiecor_BulkImport{
	
	private static $instance;
 
    /**
     * Main Instance
     *
     * @staticvar   array   $instance
     * @return      The one true instance
     */
    public static function instance() {
        if (!isset( self::$instance )){
                self::$instance = new self;
        }
        return self::$instance;
    }
    
	public function __construct(){
		add_action('admin_menu', array($this,'bulkimport_menu'));
		add_action('admin_init', array($this,'getProduct'));
	}
	
	public function bulkimport_menu() {

		add_menu_page('HieCOR Payments','HieCOR Payments','manage_options','wc-settings&tab=checkout&section=corcrm_payment','__return_null','',13 ); 
		add_submenu_page('wc-settings&tab=checkout&section=corcrm_payment','Bulk Post','Bulk Post','manage_options','corcrm-bulkimport', array($this,'admin_page'), 'dashicons-upload'); 
	}
	
	function admin_page(){
		?>
		<h1 class="wp-heading-inline">Bulk Push Products to HieCOR</h1>
		
		<h3 style="color: #a70707;">Warning: Please don't refresh the page while Bulk Importing the products to HieCOR.</h3>
		<div id="corcrm-bulkimport">
			<button id="corcrm-bulkimport-btn" class="button button-primary">Bulk Import</button>
			<span class="spinner"></span>
		</div>
		
		<div class="bulkimport-response">
                <h5 id="syncHeading">Products Syncing...</h5>
                <span id="progressCounter"> </span>
                <div id="progressContainer" style="width: 100%; background-color: #ddd;">
                    <span id="progressBar" style="width: 0%; display: block; height: 20px; background-color: #4CAF50;"></span>
                </div>
		<ul></ul>
		</div>
		
		<?php
			$this->ajaxscript();
	}
	
	function ajaxscript(){
		global $wpdb;
		$limit 		= 1;
		$startfrom 	= (isset($_REQUEST['startfrom']))? intval($_REQUEST['startfrom']) : 1;
		$firstURL 	= add_query_arg(array('corcrm-bulkimport'=>1, 'limit' => $limit,'pagenum' => $startfrom),get_admin_url());
		$postTable = $wpdb->prefix . 'posts';
        $sqlcount = $wpdb->prepare(
                            "SELECT COUNT(*) 
                             FROM $postTable 
                             WHERE `post_type` = 'product' AND `post_status` = 'publish' 
                             AND (`corcrm_product_id` IS NULL OR `corcrm_product_id` = 0)"
                        );
         $totalProducts = $wpdb->get_var($sqlcount);
         $urldel 	= add_query_arg(array('delete-sync-status'=>1),get_admin_url());
		?>
        <script type="text/javascript">
            (function($){

                var url = '<?php echo $firstURL; ?>';
                var delUrl = '<?php echo $urldel; ?>';
                var total_products = '<?php echo $totalProducts;?>';
                var syncProductInt = 0;
                $('#corcrm-bulkimport-btn').click(function(){
                        deletesyncStatus(delUrl, url);
                });

                function bulkimportCorcrm(url){
                    $('#corcrm-bulkimport .spinner').addClass('is-active');
                    $.get( url, function( data ) {
                        $('.bulkimport-response').show();
                        console.log(data);
                        var totalProductsInt = parseInt(total_products);
                        var progress = (syncProductInt / totalProductsInt) * 100;
                        $("#progressBar").css('width', progress + '%');
                        $("#progressCounter").html(syncProductInt +'/'+ totalProductsInt);
                        $('.bulkimport-response > ul').html(data.msg);
                        if(data.msg == 'done'){
                            alert('Products Synced Successfully!');
                            $('#corcrm-bulkimport .spinner').removeClass('is-active');
                            $('#corcrm-bulkimport-btn').prop('disabled', false);
                            $('#syncHeading').text('Sync Completed !');

                        }else{
                            syncProductInt += 1;
                            bulkimportCorcrm(data.returnurl);
                        }

                    },'json');
                }
                function deletesyncStatus(delUrl,url){
                    $('#corcrm-bulkimport .spinner').addClass('is-active');
                    $('#corcrm-bulkimport-btn').prop('disabled', true);

                    $.get( delUrl, function( data ) {
                        //alert('Delete previous sync status!');
                        console.log(data);
                        if(data.msg == 'done'){
                            bulkimportCorcrm(url);
                        }

                    },'json');
                }
            }(jQuery));
        </script>

        <style>
        .bulkimport-response{background: lightgreen;padding: 10px;border-radius: 3px;max-height: 500px;overflow-y: scroll;display: none;margin-top: 20px;}
        #corcrm-bulkimport .spinner{float: left;}
        </style>
		<?php
	}
	
	
	function getProduct(){
        global $wpdb;
        $log='';
		if(isset($_REQUEST['corcrm-bulkimport'])){

			$paged = (isset($_REQUEST['pagenum'])) ? intval($_REQUEST['pagenum']) : 1;
			$limit = (isset($_REQUEST['limit'])) ? intval($_REQUEST['limit']) : 1;
//			$postTable = $wpdb->prefix . 'posts';
//                        $sql = $wpdb->prepare(
//                            "SELECT `ID`, `corcrm_product_id`, `post_type`, `post_title`, `post_content`, `post_date` 
//                            FROM $postTable 
//                            WHERE `post_type` = 'product' AND `post_status` = 'publish' 
//                            AND (`corcrm_product_id` IS NULL OR `corcrm_product_id` = 0)
//                            ORDER BY `ID` 
//                            LIMIT {$limit}",
//                        );
            $sql = $wpdb->prepare(
                        "SELECT p.`ID`, p.`corcrm_product_id`, p.`post_type`, p.`post_title`, p.`post_content`, p.`post_date`
                        FROM {$wpdb->prefix}posts AS p
                        LEFT JOIN {$wpdb->prefix}postmeta AS pm ON p.ID = pm.post_id AND pm.meta_key = %s
                        WHERE p.`post_type` = 'product' 
                        AND p.`post_status` = 'publish'
                        AND (p.`corcrm_product_id` IS NULL OR p.`corcrm_product_id` = 0)
                        AND pm.meta_id IS NULL
                        ORDER BY RAND()
                        LIMIT %d",
                        'current_sync_processed',
                        $limit
                    );
            $posts = $wpdb->get_results($sql, OBJECT);
            if (!empty($wpdb->last_error)) {
                $log.="SQL Executed: \n".$sql.'Error-'.$wpdb->last_error;
                $log.="\nWpProductData:\n".print_r($posts,true);
                $logger = new WC_Logger();
                $logger->add('sql Error',$log);
                return false;
            }
            if(!empty($posts)){
                foreach ($posts as $post) {
                    $product_id = $post->ID;
                    $product_title = $post->post_title;
                    $productData = wc_get_product($product_id);
                    $product_sku = $productData ? $productData->get_sku() : '';
                    update_post_meta($product_id, 'current_sync_processed', 'processed');
                    $this->pushToCorcrm($product_id);
                    $pagenum = $paged+1;
                    $returnURL = add_query_arg(array('corcrm-bulkimport' => 1, 'limit' => 1,'pagenum' => $pagenum),get_admin_url());
                    $msg = "<li>Product ID:#{$product_id} {$product_sku} ({$product_title})</li>";
                    $returnData = array('returnurl' => $returnURL,'pid'=>$product_id,'title'=>$product_title,'msg'=>$msg);
                }
            }else{
                $delete_query = "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'current_sync_processed'";
                $result = $wpdb->query($delete_query);
                $returnData = array('msg' => 'done');
            }
			echo json_encode($returnData);
			die;
		}
                
        if(isset($_REQUEST['delete-sync-status'])){
            global $wpdb;
            $delete_query = "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'current_sync_processed'";

            $result = $wpdb->query($delete_query);

            if ($result !== false) {
                $resData = array('msg' => 'done');
            } else {
                $resData = array('msg' => 'failed');
            }
            echo json_encode($resData);
            die;
        }
	}
	
	function pushToCorcrm($product_id){
		global $crmUtility;
		return $crmUtility->push_to_corcrm($product_id);
	}
}

// Call the class and add the menus automatically. 
$BulkImport = Hiecor_BulkImport::instance();