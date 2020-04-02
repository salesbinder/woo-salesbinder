<?php

if ( !defined( 'ABSPATH' ) ) {
  exit;
}

require_once 'class-wcsb-helpers.php';
require_once __DIR__ . '/../vendors/wp-background-processing/wp-async-request.php';

if ( !class_exists( 'WCSB_Product' ) ) {
  class WCSB_Product extends WP_Async_Request {

    /**
     * @var string
     */
   protected $action = 'wcsb_product_sync';

    /**
     * @var WC_Product_Factory
     */
    private $wc_pf;

    /**
     * @var WCSB_Helpers
     */
    private $helpers;

    /**
     * Class constructor.
     */
    public function __construct() {
      parent::__construct();
      $this->wc_pf = new WC_Product_Factory();
      $this->helpers = new WCSB_Helpers();
    }

    /**
     * Handle async request via ajax.
     * @override
     */
    protected function handle() {
      if ( isset( $_POST['item'] ) && isset( $_POST['index'] ) ) {
        $this->sync( $_POST );
      }
    }

    /**
     * Sync procedure for single product. Return true if successful or false
     * otherwise. $data should be defined as:
     *  {
     *    'index' => int
     *    'item'  => array
     *  }
     * @param  array $data
     * @return bool
     */
    public function sync( $data ) {
      ini_set( 'max_execution_time', 0 );

      $item = $data['item'];
      $post_id = $this->helpers->get_product_by_id_salesbinder( $item['id'] );

      // remove any unpublished or archived items from Woo
      if ( empty($item['published']) || !empty($item['archived']) ) {
        if ( !empty($post_id) ) {
          wp_delete_post( $post_id );
        }
        return false;
      }

      if ( $post_id ) { // update
        $this->helpers->da_update_post( array(
          'ID'            => $post_id,
          'post_title'    => $item['name'],
          'post_content'  => $item['description']
        ) );

        $product = $this->wc_pf->get_product($post_id);
      } else { // doesn't exist => create!
        $product = $this->helpers->create_product($item);
        if (!$product) {
          wc_print_notice('SalesBinder sync failed to add ' . $item['name'] . ' (' . $item['id'] . ')', 'error');
          return false;
        }
      }
      gc_collect_cycles();

      $product_id = method_exists( $product, 'get_id' ) ? $product->get_id() : $product->id;

      // update prices
      update_post_meta( $product_id, '_regular_price', $item['price'] );
      update_post_meta( $product_id, '_price', $item['price'] );

      // update stock
      update_post_meta( $product_id, '_sku', $item['sku'] );
      update_post_meta( $product_id, '_stock', $item['quantity'] );

      // update backorder option
      $backorders_enabled = get_option('wcsalesbinder_backorders');
      if ( empty($backorders_enabled) || $backorders_enabled == 'yes' ) {
        update_post_meta( $product_id, '_backorders', 'yes' );
        update_post_meta( $product_id, '_stock_status', 'instock' );
      } else {
        update_post_meta( $product_id, '_backorders', 'no' );
        if ($item['quantity'] <= 0) {
          update_post_meta( $product_id, '_stock_status', 'outofstock' );
        } else {
          update_post_meta( $product_id, '_stock_status', 'instock' );
        }
      }

      $this->update_weight( $item, $product_id );

      if ( !empty( $item['images'] ) ) {
        $this->process_images( $item['images'], $item['name'], $product_id );
      }

      // asign categories to product
      if ( !empty( $item['category']['name'] ) ) {
        $this->assign_category( $item['category']['name'], $product_id );
      }
      $product = null;
      gc_collect_cycles();

      return true;
    }

    private function process_images( $images, $item_name, $product_id ) {
      $existing_filenames = $this->get_existing_filenames( $product_id );

      // make sure image order is correct so primary image is first
      if ( count($images) > 1 ) {
        usort( $images, array( $this, 'sort_images' ) );
      }

      $image_index = 0;
      foreach ( $images as $image ) {
        $this->process_image(
          $image,
          $image_index++,
          $item_name,
          $product_id,
          $existing_filenames
        );
      }
    }

    public function sort_images( $a, $b ) {
      if ( !isset( $a['weight'] ) || !isset( $b['weight'] ) ) {
        return 0;
      }
      return $a['weight'] - $b['weight'];
    }

    private function process_image( $image, $image_index, $item_name, $product_id, $existing_filenames ) {
      // get url_medium filename
      $path_parts = pathinfo( $image['url_medium'] );
      $image['filename'] = $path_parts['basename'];
      $image['filename_no_ext'] = $path_parts['filename'];

      if ( !$this->custom_search( $image['filename_no_ext'], $existing_filenames ) ) {
        $image_response = wp_remote_get($image['url_medium'], array(
          'stream' => true,
          'timeout' => 24
        ));

        if ( wp_remote_retrieve_response_code($image_response) != 200 || is_wp_error($image_response) || !is_readable($image_response['filename']) ) {
          error_log( 'SalesBinder could not sync image for "' . $item_name .'" (image url likely incorrect): ' . $image['url_medium'] );
          return false;
        } else {
          $this->set_product_gallery( $product_id, $image_response['filename'], $item_name, $image_index );
        }

        if ( file_exists( $image_response['filename'] ) ) {
          unlink( $image_response['filename'] );
        }
      }
    }

    private function assign_category( $category_name, $product_id ) {
      $category_name = sanitize_text_field( str_replace( '&', '&amp;', $category_name ) ); // clean ampersands
      $category_name = str_replace( '/', '|', $category_name ); // clean forward slashes

      $term = get_term_by( 'name', $category_name, 'product_cat' );
      if ( !empty( $term ) ) {
        $check_asign = wp_set_object_terms( $product_id, $term->term_id, 'product_cat' );
        if( isset( $check_asign ) ) {
          //update_woocommerce_term_meta( $term->term_id, 'product_count_product_cat', ($term->count + 1) ); // depreciated
          update_term_meta( $term->term_id, 'product_count_product_cat', ($term->count + 1) );
        }
      }
    }

    private function custom_search( $keyword, $array_to_search ) {
      foreach( $array_to_search as $key => $item ) {
        if ( stristr( $item, $keyword ) ) {
          return $key;
        }
      }
    }

    private function get_existing_filenames( $product_id ) {
      $existing_filenames = array();

      $featured_id = get_post_thumbnail_id( $product_id );
      if ( !empty( $featured_id ) ) {
        $featured_url = wp_get_attachment_url( $featured_id );
        $existing_filenames[$featured_id] = basename($featured_url);
      }

      $gallery_ids = get_post_meta( $product_id, '_product_image_gallery', true );
      $image_ids   = explode( ',', $gallery_ids );

      if ( !empty( $image_ids ) ) {
        foreach ( $image_ids as $image_id ) {
          $filename_only = basename( get_attached_file( $image_id ) );
          $existing_filenames[$image_id] = $filename_only;
        }
      }

      return $existing_filenames;
    }

    private function update_weight( $item, $product_id ) {
      if ( !empty($item['item_details']) ) {

        $i = 0;
        $product_weight = null;

        foreach ($item['item_details'] as $detail) {

          if (!empty($detail['custom_field']['publish']) && !empty($detail['value'])) {
            $custom_field_name = str_replace('/', '-', $detail['custom_field']['name']);

            if (isset($detail['custom_field']['name']) && (strpos(strtolower($detail['custom_field']['name']),'weight') !== false)) {
              $product_weight = round($detail['value']);
            }else{
              $specs[$custom_field_name] = array(
                  'name'=> $custom_field_name,
                  'value'=> $detail['value'],
                  'position'=> $detail['custom_field']['weight'],
                  'is_visible'=> 1,
                  'is_variation'=> 0,
                  'is_taxonomy'=> 0
              );
            }
            $i++;
          }

        }

        if (!empty($specs)) { // add custom fields
            update_post_meta($product_id, '_product_attributes', $specs);
        }

        if (!empty($product_weight)) {
            update_post_meta($product_id, '_weight', preg_replace('/\D/', '', $product_weight) ); // set weight
        }else{
            update_post_meta($product_id, '_weight', null);
        }

        // foreach ($item['item_details'] as $detail) {
        //   if (!empty($detail['custom_field']['publish']) && !empty($detail['value'])) {
        //     if (isset($detail['custom_field']['name']) && (strpos(strtolower($detail['custom_field']['name']),'weight') !== false)) {
        //       $product_weight = round($detail['value']);
        //       break;
        //     }
        //   }
        // }

        // if ( !empty($product_weight) ) {
        //   update_post_meta($product_id, '_weight', preg_replace('/\D/', '', $product_weight) ); // set weight
        // } else {
        //   update_post_meta($product_id, '_weight', null);
        // }

      }
    }

    private function set_product_gallery( $post_id, $image_path, $item_name, $image_index = -1 ) {
      $upload = wp_upload_bits( basename( $image_path ), null, file_get_contents( $image_path ) );
      $wp_filetype = wp_check_filetype( basename( $upload['file'] ), null );

      $wp_upload_dir = wp_upload_dir();

      $attachment = array(
        'guid'           => $wp_upload_dir['url'] . '/' . basename( $upload['file'] ),
        'post_parent'    => $post_id,
        'post_mime_type' => $wp_filetype['type'],
        'post_title'     => $item_name ?: 'Product Photo',
        'post_content'   => '',
        'post_status'    => 'inherit'
      );

      $bng_attach_id = get_post_thumbnail_id( $post_id );
      $base_path = explode( "/", get_attached_file( $bng_attach_id ) );

      unset( $base_path[count( $base_path ) - 1] );

      $attach_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );

      // generate metadata and thumbnails
      if ( !function_exists( 'wp_generate_attachment_metadata') ) {
        require_once 'image-v5.1.1.php';
      }

      if ( $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] ) ) {
        wp_update_attachment_metadata( $attach_id, $attach_data );
      }

      $old = get_post_meta( $post_id, '_product_image_gallery', true );

      if ( empty( $old ) ) {
        update_post_meta( $post_id, '_product_image_gallery', $attach_id );
      } else {
        update_post_meta( $post_id, '_product_image_gallery', $old . ',' . $attach_id );
      }

      if ( $attach_id && $image_index == 0 ) {
        update_post_meta( $post_id, '_thumbnail_id', $attach_id ); // sets the primary image
      }
    }

  }
}
