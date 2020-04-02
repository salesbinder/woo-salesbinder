<?php

if ( !defined( 'ABSPATH' ) ) {
  exit;
}

require_once 'class-wcsb-helpers.php';
require_once 'class-wcsb-product.php';
require_once __DIR__ . '/../vendors/wp-background-processing/wp-async-request.php';
require_once __DIR__ . '/../vendors/wp-background-processing/wp-background-process.php';

if ( !class_exists( 'WCSB_Products' ) ) {
  class WCSB_Products extends WP_Background_Process {

    /**
     * Name of the sync action
     * @var string
     */
    protected $action = 'wcsb_products_sync';

    /**
     * If the sync is partial.
     * @var bool
     */
    protected $is_partial = false;

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
     * @var WC_Product_Factory
     */
    private $wc_pf;

    /**
     * @var WCSB_Helpers
     */
    private $helpers;

    /**
     * @var WCSB_Product
     */
    private $product;

    /**
     * Define class attributes.
     * @param bool $is_partial Defaults to false.
     */
    public function __construct( $is_partial = false ) {
      $this->is_partial = $is_partial;
      $this->action .= $this->is_partial ? '_partial' : '_full';

      parent::__construct();

      $this->subdomain  = get_option( 'wcsalesbinder_subdomain' );
      $this->api_key    = get_option( 'wcsalesbinder_apikey' );

      $this->helpers = new WCSB_Helpers();
      $this->product = new WCSB_Product();
    }

    /**
     * Single queue task. Calls sync() method and if successful return false or
     * return $item array which puts task back to queue.
     * @override
     * @param  array $item
     * @return bool|array
     */
    protected function task( $item ) {
      try {
        $is_succesful = $this->sync(
          $item['page'],
          isset( $item['last_sync_timestamp'] ) ? $item['last_sync_timestamp'] : null
        );
      } catch( Exception $e ) {
        $is_succesful = false;
      }

      return $is_succesful ? false : $item;
    }

    /**
     * Triggered after last item in the queue.
     * @return void
     */
    protected function complete() {
      parent::complete();

      if( !get_option("wcsalesbinder_last_synced") ) {
        // Setup incremental cron now that the first full sync has completed.
        // This will only run after the first sync successfully completes.
        $partial_interval = get_option('wcsalesbinder_partial_sync');
        if ( !empty($partial_interval) ) {
            wp_clear_scheduled_hook('wcsalesbinder_partial_cron');
            wp_schedule_event(time(), $partial_interval, 'wcsalesbinder_partial_cron');
        }
      }

      update_option("wcsalesbinder_last_synced", time());
      update_option("current_sync_page", 0); // Set to zero if sync fully completes
      update_option("total_pages_to_sync", 0);

      $page = 1;

      $wcsalesbinder_last_synced = get_option( 'wcsalesbinder_last_synced', false);
      $last_sync_timestamp = !empty( $wcsalesbinder_last_synced ) ? $wcsalesbinder_last_synced - 7200 : time() - 43200;

      $wcsalesbinder_last_synced = null;

      // Sync deleted inventory items from SalesBinder by removing them from Woo
      $url = 'https://' . $this->api_key . ':x@app.salesbinder.com/api/2.0/deleted_log.json?pageLimit=200&contextId=6&deletedSince=' . $last_sync_timestamp;
      $response = wp_remote_get( $url, $this->helpers->basic_args_for_get_request( $this->api_key ) );

      if (wp_remote_retrieve_response_code($response) != 200 || is_wp_error($response)) {
        wc_print_notice('SalesBinder (deleted items) sync failed to load ' . $url, 'error');
      }

      $response = json_decode(wp_remote_retrieve_body($response), true);

      if (!empty($response['deletedlog'][0])) {
        foreach ($response['deletedlog'][0] as $item) {
          // check if product exists and delete it
          $post_id = $this->helpers->get_product_by_id_salesbinder($item['record_id']);
          if (!empty($post_id)) wp_delete_post( $post_id );
        }
      }
    }

    public function sync( $page, $last_sync_timestamp = false, $is_last_page = false ) {
      ini_set( 'max_execution_time', 0 );

      $response = $this->get_remote_products( $page, $last_sync_timestamp );
      if ( wp_remote_retrieve_response_code( $response ) != 200 || is_wp_error( $response ) ) {
        if ( $page > 1 ) {
          update_option( 'current_sync_page', 0); // reset page number if error occurs after first page
        }
        wc_print_notice('SalesBinder sync failed to load ' . $page, 'error');
        return false;
      }

      $response = json_decode( wp_remote_retrieve_body( $response ), true );
      if ( !isset( $response['items'] ) || !is_array( $response['items'] ) || count( $response['items'] ) == 0 ) {
        return false;
      }

      $item_index = 0;
      foreach ( $response['items'][0] as $item ) {
        $this->product->data( array(
          'item'  => $item,
          'index' => $item_index++,
          'parent' => $this->action,
          'page'   => $page,
        ) )->dispatch();
      }

      return true;
    }

    public function get_remote_products( $page, $last_sync_timestamp = false ) {
      $url = 'https://' . $this->api_key . ':x@app.salesbinder.com/api/2.0/items.json?page=' . $page . '&pageLimit=20';
      if ( $this->is_partial ) {
        if ( !$last_sync_timestamp ) {
          $wcsalesbinder_last_synced = get_option( 'wcsalesbinder_last_synced', false);
          $last_sync_timestamp = !empty( $wcsalesbinder_last_synced ) ? $wcsalesbinder_last_synced - 7200 : time() - 43200;
        }
        $url .= '&modifiedSince=' . $last_sync_timestamp;
      }

      return wp_remote_get( $url, $this->helpers->basic_args_for_get_request( $this->api_key ) );
    }

    /**
     * Return total number of API pages for items endpoint.
     */
    public function get_total_pages() {
      $response = $this->get_remote_products( 1 );
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

  }
}
