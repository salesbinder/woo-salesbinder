<?php

class wc_salesbinder_customs{

	public static function create_product($item)
    {
        global $wpdb;
        //$cu = wp_get_current_user();
				//$my_user_id = ($cu) ? $cu->ID : 1;
				$my_user_id = 1;
        $post = array(
            'post_author' => $my_user_id,
            'post_status' => "publish",
            'post_title' => $item['name'],
            'post_content' => $item['description'],
            'post_parent' => '',
            'post_type' => "product",
            'post_date' => $item['created'],
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        );

        $post_id = wp_insert_post( $post, true );

        update_post_meta( $post_id, 'id_product_salesbinder', $item['id'] ); // meta salesbinder

        update_post_meta( $post_id, '_visibility', 'visible' );
        update_post_meta( $post_id, '_stock_status', 'instock');
        update_post_meta( $post_id, 'total_sales', '0');
        update_post_meta( $post_id, '_downloadable', 'no');
        update_post_meta( $post_id, '_virtual', 'no');
        update_post_meta( $post_id, '_regular_price', "" );
        update_post_meta( $post_id, '_sale_price', "" );
        update_post_meta( $post_id, '_purchase_note', "" );
        update_post_meta( $post_id, '_featured', "no" );
        update_post_meta( $post_id, '_weight', "" );
        update_post_meta( $post_id, '_length', "" );
        update_post_meta( $post_id, '_width', "" );
        update_post_meta( $post_id, '_height', "" );
        update_post_meta( $post_id, '_sku', "");
        update_post_meta( $post_id, '_product_attributes', array());
        update_post_meta( $post_id, '_sale_price_dates_from', "" );
        update_post_meta( $post_id, '_sale_price_dates_to', "" );
        update_post_meta( $post_id, '_price', "1" );
        update_post_meta( $post_id, '_sold_individually', "" );
        update_post_meta( $post_id, '_manage_stock', "yes" );
        update_post_meta( $post_id, '_backorders', "yes" );
        update_post_meta( $post_id, '_stock', "" );
        update_post_meta( $post_id, '_edit_last', "1" );
        update_post_meta( $post_id, '_edit_lock', strtotime('now') );
        update_post_meta( $post_id, '_tax_status', "taxable" );
        update_post_meta( $post_id, '_tax_class', "" );

        // Set taxonomy product type simple
        wp_set_object_terms( $post_id, 2, 'product_type' );

        $_pf = new WC_Product_Factory();
        $product = $_pf->get_product($post_id) ;

        return $product;
    }


    public static function da_update_post($data)
    {
    	wp_update_post($data);
    }

    public static function delete_post_author_zero()
    {
    	global $wpdb;
        $results = $wpdb->get_results(
            "
            SELECT ID
            FROM $wpdb->posts
            WHERE  post_author = 0
            "
        );

        foreach ( $results as $result )
        {
            $images = explode(',', get_post_meta($result->ID, '_product_image_gallery', true));
            foreach ($images as $image) {
                @unlink(get_attached_file( $image ));
            }

            wp_delete_post( $result->ID );
        }
    }
}


?>
