<?php 

/*
    Plugin Name: Sync Snapp Ecommerce
    description: >- Sync products with Snapp Ecommerce. 
  Setting up configurable fields for our plugin.
    Author: Ali Salmani Garamaleki
    Version: 1.5.0
*/

function sync_snapp_ecommerce_products(){
    $max_rows = (int) sanitize_text_field(get_option('snapp_ecommerce_number_of_rows'));
    $only_instock = false;
    $purchasable = NULL;
    $on_sale = NULL;
    if (isset($_REQUEST['max_rows']) && (int) $_REQUEST['max_rows'] > 0)
        $max_rows = min($max_rows, $_REQUEST['max_rows']);
    if (isset($_REQUEST['only_instock']) &&  $_REQUEST['only_instock'] == "true")
        $only_instock = true;
    if (isset($_REQUEST['on_sale'])){
        if($_REQUEST['on_sale'] == "true")
            $on_sale = true;
        if($_REQUEST['on_sale'] == "false")
            $on_sale = false;
    }
    $args = array(
        'post_type'      => array('product'),
        'posts_per_page'=>  $max_rows,
        'paged' => ((isset($_REQUEST['paged']) && (int) $_REQUEST['paged'] > 0) ? sanitize_text_field($_REQUEST['paged']) : 1),
    );
    if ($only_instock == true)
        $args['stock_status'] = 'instock';

    if ($on_sale === true)
        $args['include'] = wc_get_product_ids_on_sale();
    else if ($on_sale === false)
        $args['exclude'] = wc_get_product_ids_on_sale();
    

    $listOfProducts  = array();
    $all_products = wc_get_products($args);
    foreach ($all_products as $key => $product) {
        $variationsSerialized = [];
        if ($product->get_type() == "variable") {
            $available_variations = $product->get_children();
            foreach ($available_variations as $key => $value) 
            { 
                $variationObj = new WC_Product_Variation($value);
                if ($only_instock == true && $variationObj->get_stock_status() != 'instock')
                    continue;
                if (!is_null($on_sale) && $on_sale != $variationObj->is_on_sale())
                    continue;

                $variationSerialized = [
                    'id'  => $variationObj->get_id(),
                    'regular_price' => $variationObj->get_regular_price(),
                    'sale_price' => $variationObj->get_sale_price(),
                    'price' => $variationObj->get_price(),
                    'sku' => $variationObj->get_sku(),
                    'stock_quantity' => $variationObj->get_stock_quantity(),
                    'stock_status' => $variationObj->get_stock_status(),
                    'purchasable' => $variationObj->is_purchasable(),
                    'on_sale' => $variationObj->is_on_sale(),
                    'date_modified' => $variationObj->get_date_modified()->date('F j, Y, g:i a'),
                    'parent' => $product->get_id(),
                ];
                array_push($listOfProducts, $variationSerialized);
            }
        }

        $body = [
            'id'  => $product->get_id(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'price' => $product->get_price(),
            'sku' => $product->get_sku(),
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
            'purchasable' => $product->is_purchasable(),
            'on_sale' => $product->is_on_sale(),
            'date_modified' => $product->get_date_modified()->date('F j, Y, g:i a'),
            'parent' => null,
        ];
     
    array_push($listOfProducts, $body);

    }



    return $listOfProducts;
}


add_action('rest_api_init', function(){
    register_rest_route('sync-snapp-ecommerce/v1', 'products', [
        'methods' => 'GET',
        'callback' => 'sync_snapp_ecommerce_products',
        'permission_callback' => function( WP_REST_Request $request ) {
            if ( sanitize_text_field(get_option('snapp_ecommerce_key')) != sanitize_text_field($request->get_param('key')) ) {
                return false;
            } else {
                return true;
            }
        },
    ]);
});




function sync_snapp_ecommerce_register_settings() {
    add_option( 'snapp_ecommerce_key', '0654321');
    add_option( 'snapp_ecommerce_number_of_rows', '100');
    register_setting( 'sync_snapp_ecommerce_options_group', 'snapp_ecommerce_key', 'sync_snapp_ecommerce_callback' );
    register_setting( 'sync_snapp_ecommerce_options_group', 'snapp_ecommerce_number_of_rows', 'sync_snapp_ecommerce_callback' );
 }
 add_action( 'admin_init', 'sync_snapp_ecommerce_register_settings' );

 function sync_snapp_ecommerce_register_options_page() {
    add_options_page('Sync Snapp Ecommerce settings', 'Sync Snapp', 'manage_options', 'snapp-ecommerce', 'sync_snapp_ecommerce_options_page');
  }
  add_action('admin_menu', 'sync_snapp_ecommerce_register_options_page');

 function sync_snapp_ecommerce_options_page()
{
?>
  <div>
  <?php screen_icon(); ?>
  <h2>تنظیمات پلاگین اسنپ</h2>
  <form method="post" action="options.php">
  <?php settings_fields( 'sync_snapp_ecommerce_options_group' ); ?>
  <table>
  <tr valign="top">
  <th scope="row"><label for="snapp_ecommerce_key">کلید خصوصی</label></th>
  <td><input type="text" id="snapp_ecommerce_key" name="snapp_ecommerce_key" value="<?php echo esc_html(get_option('snapp_ecommerce_key')); ?>" /></td>
  </tr>
  <tr valign="top">
  <th scope="row"><label for="snapp_ecommerce_number_of_rows"> تعداد سطرها</label></th>
  <td><input type="number" min=1 id="snapp_ecommerce_number_of_rows" name="snapp_ecommerce_number_of_rows" value="<?php echo esc_html(get_option('snapp_ecommerce_number_of_rows')); ?>" /></td>
  </tr>
  </table>
  <?php  submit_button(); ?>
  </form>
  </div>
<?php
} ?>
