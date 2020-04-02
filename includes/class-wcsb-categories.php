<?php

if ( !defined( 'ABSPATH' ) ) {
  exit;
}

require_once 'class-wcsb-helpers.php';
require_once __DIR__ . '/../vendors/wp-background-processing/wp-async-request.php';
require_once __DIR__ . '/../vendors/wp-background-processing/wp-background-process.php';

if ( !class_exists( 'WCSB_Categories' ) ) {
  class WCSB_Categories extends WP_Background_Process {

    /**
     * Name of the sync action
     * @var string
     */
    protected $action = 'wcsb_categories_sync';

    /**
     * SalesBinder subdomain value.
     * @var string
     */
    protected $subdomain;

    /**
     * SalesBinder API key.
     * @var string
     */
    protected $api_key;

    /**
     * @var WCSB_Helpers
     */
    private $helpers;

    /**
     * Define class attributes.
     */
    public function __construct() {
      parent::__construct();

      $this->subdomain  = get_option( 'wcsalesbinder_subdomain' );
      $this->api_key    = get_option( 'wcsalesbinder_apikey' );

      $this->helpers = new WCSB_Helpers();
    }

    /**
     * Single queue task. Calls sync() method and if successful return false or
     * return $item array which puts task back to queue.
     * @override
     * @param  array $item
     * @return bool|array
     */
    protected function task( $item ) {

    }

    private function get_remote_categories( $page ) {
      $url = 'https://' . $this->api_key . ':x@app.salesbinder.com/api/2.0/categories.json?page=' . $page;
      $response = wp_remote_get( $url, $this->helpers->basic_args_for_get_request( $this->api_key ) );

      if ( wp_remote_retrieve_response_code( $response ) != 200 || is_wp_error( $response ) ) {
        wc_print_notice('SalesBinder sync failed to load ' . $url, 'error');
        return;
      }

      return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    /**
     * Return total number of API pages for items endpoint.
     */
    public function get_total_pages() {
      $response = $this->get_remote_categories( 1 );
      if ( wp_remote_retrieve_response_code( $response ) != 200 || is_wp_error( $response ) ) {
        return 0;
      }

      try {
        $response = json_decode( wp_remote_retrieve_body( $response ), true );
      } catch ( Exception $e ) {
        return 0;
      }

      return isset( $response['pages'] ) ? intval( $response['pages'] ) : 1;
    }

    public function sync() {
      $page = 1;
      $local_categories = array();
      $remote_categories = array();

      $total_pages = $this->get_total_pages();

      do {
        $response = $this->get_remote_categories( $page );

        if ( !empty($response['categories'][0]) ) {
          foreach ( $response['categories'][0] as $category ) {
            $remote_categories[] = $category['id'];
            $category_name = sanitize_text_field(str_replace('&', '&amp;', $category['name'])); // clean ampersands
            $category_name = str_replace('/', '&#x2F;', $category_name); // clean forward slashes

            // check if exists
            $term = get_term_by('name', $category_name, 'product_cat');

            if ( !empty($term->term_id) ) {
              // exists then update
              $category_id = $term->term_id;

              wp_update_term($category_id, 'product_cat', array(
                'description' => $category['description']
              ));
            } else {
              // doesn't exist, then creaget_all_categories
              $category_id = wp_insert_term($category_name, 'product_cat', array('description'=>$category['description']));
              $category_id = (array_key_exists('term_id', $category_id)) ? $category_id['term_id'] : null;
            }

            if ( !empty($category_id) ) {
              // check if it has woocommerce_term_meta
              $old_id = get_woocommerce_term_meta($category_id, 'id_category_salesbinder', true);

              if ( !$old_id ) { // doesn't have, then create
                add_woocommerce_term_meta($category_id, 'id_category_salesbinder', $category['id'], true);
                add_woocommerce_term_meta($category_id, 'product_count_product_cat', 0);
              }
            }
          } // foreach
        }
      } while ( ++$page <= $total_pages );

      // delete categories
      // TODO: Check for duplicate category IDs,
      // caused by renaming category in SalesBinder

      $local_categories = $this->get_all_categories();
      $to_delete = array_diff( $local_categories, $remote_categories );

      foreach ( $to_delete as $delete ) {
          $index = array_search( $delete, $local_categories );
          delete_woocommerce_term_meta( $index, 'id_category_salesbinder', $delete );
          wp_delete_term( $index, 'product_cat' );
      }
      gc_collect_cycles();
    }

    private function get_all_categories() {
      global $wpdb;
      $results = $wpdb->get_results( 'SELECT term_id, meta_value as value FROM ' . $wpdb->prefix . 'termmeta WHERE meta_key = "id_category_salesbinder"', OBJECT );

      try {
        $categories = array();
        foreach ($results as $result) {
          $categories[$result->term_id] = $result->value;
        }
        return $categories;
      } catch( Exception $e ) {
        return array();
      }

    }
  }
}
