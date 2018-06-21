<?php

if ( !defined( 'ABSPATH' ) ) {
  exit;
}

if ( !class_exists( 'WCSB_Helpers' ) ) {
  class WCSB_Helpers {

    public function create_product( $item ) {
      $my_user_id = 1;
      $post = array(
        'post_author'     => $my_user_id,
        'post_status'     => "publish",
        'post_title'      => $item['name'],
        'post_content'    => $item['description'],
        'post_parent'     => '',
        'post_type'       => "product",
        'post_date'       => $item['created'],
        'comment_status'  => 'closed',
        'ping_status'     => 'closed'
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

      update_post_meta( $post_id,'_ltl_freight', 70 );
      wp_set_object_terms( $post_id, array(76), 'product_shipping_class' );

      // Set taxonomy product type simple
      wp_set_object_terms( $post_id, 2, 'product_type' );

      $product = wc_get_product($post_id);
      unset($post);
      gc_collect_cycles();
      return $product;
    }

    public function da_update_post( $data ) {
      wp_update_post( $data );
    }

    public function delete_post_author_zero() {
      global $wpdb;
      $results = $wpdb->get_results(
        "SELECT ID FROM $wpdb->posts WHERE post_author = 0"
      );

      foreach ( $results as $result ) {
        $images = explode( ',', get_post_meta($result->ID, '_product_image_gallery', true) );
        foreach ( $images as $image ) {
          @unlink( get_attached_file( $image ) );
        }

        wp_delete_post( $result->ID );
      }
      unset( $results );
    }

    public function get_product_by_id_salesbinder( $sb_id ) {
      global $wpdb;

      $id_post = null;
      $results = $wpdb->get_results( "SELECT post_id as id FROM " . $wpdb->prefix . "postmeta WHERE meta_key = 'id_product_salesbinder' AND meta_value='" . $sb_id . "' ", OBJECT );

      // duplicate check
      if ( count( $results ) > 1 ) {
        $first = array_shift( $results );
        // use the oldest post_id
        $id_post = $first->id;

        // remove any remaining duplicates
        foreach ( $results as $result ) {
          wp_delete_post( $result->id );
        }
      } else {
        if ( !empty( $results ) ) {
          foreach ( $results as $result ) {
            $id_post = $result->id;
          }
        }
      }
      $results = null;
      return $id_post;
    }

    public function basic_args_for_get_request( $api_key ) {
      return array(
        'headers' => array(
          'Authorization' => 'Basic ' . base64_encode( $api_key . ':' . "x" ),
          'Content-Type' => 'application/json'
        ),
        'timeout' => 60,
      );
    }

  }
}
