<?php
/**
 * Plugin Name: Woo + SalesBinder
 * Plugin URI: https://wordpress.org/plugins/woo-salesbinder/
 * Description: Sync WooCommerce with your SalesBinder data.
 * Author: SalesBinder
 * Author URI: http://www.salesbinder.com
 * Version: 1.4.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
require_once 'includes/class-wcsb-products.php';
require_once 'includes/class-wcsb-categories.php';
require_once plugin_dir_path( __DIR__ ) . 'woocommerce/includes/wc-notice-functions.php';
//require_once 'includes/image-v5.1.1.php';

if ( ! class_exists( 'WC_SalesBinder' ) ) :

class WC_SalesBinder {

    protected static $instance = null;


    private static $hooks_defined = false;

    /**
     * Initialize the plugin.
     *
     * @since 1.0
     */
    protected function __construct() {
      if ( !self::$hooks_defined ) {
        $this->init_hooks();
        self::$hooks_defined = true;
      }

      $this->full_sync = new WCSB_Products( false );
      $this->partial_sync = new WCSB_Products( true );
      $this->categories = new WCSB_Categories();
    }

    public function init_hooks() {
      if ( class_exists( 'WooCommerce' ) ) {
       // Create a Section in the woocommerce settings
        add_filter( 'woocommerce_settings_tabs_array', array($this, 'wcsalesbinder_add_section') );
        add_action( 'woocommerce_settings_tabs_wcsalesbinder', array($this, 'settings_tab') );
        add_action( 'woocommerce_update_options_wcsalesbinder', array($this, 'update_settings') );
	      add_action( 'woocommerce_checkout_order_processed', array($this, 'woo_order_success') );
        add_action( 'wp_admin_force_sync', array($this, 'force_sync') );
      }

      add_action( 'wcsalesbinder_cron', array($this, 'cron') );
      add_action( 'wcsalesbinder_partial_cron', array($this, 'partial_cron') );
      add_filter( 'cron_schedules', array($this, 'cron_schedules') );
    }

    public function force_sync() {
      $this->update_settings();
      do_action( 'wcsalesbinder_cron' );
    }

    public function wcsalesbinder_add_section( $sections ) {
      $sections['wcsalesbinder'] = __( 'Woo + SalesBinder', 'woocommerce' );
      return $sections;
    }

    public function settings_tab() {
      woocommerce_admin_fields( $this->wcsalesbinder_get_settings() );
      $current_sync_page_cmb = (get_option("current_sync_page")) ? get_option("current_sync_page") : 0;
      $total_pages_to_sync = (get_option("total_pages_to_sync")) ? get_option("total_pages_to_sync") : 0;
      $wcsalesbinder_last_synced = get_option("wcsalesbinder_last_synced", false);
      echo '<a id="link_force_sync" class="button button-secondary" style="float: right;" href="#"> Force Sync Inventory Data </a>';
      if ($current_sync_page_cmb > 0 && $total_pages_to_sync > 0) echo '<div id="message" class="updated"><p><strong>Syncing</strong>: Page ' . $current_sync_page_cmb . ' of ' . $total_pages_to_sync . '</p></div>';
      if ($current_sync_page_cmb > 0 && $total_pages_to_sync > 0) {
        echo '<p><strong>Data last synced:</strong> syncing now...</p>';
      }elseif (!empty($wcsalesbinder_last_synced)) {
        echo '<p><strong>Data last synced:</strong> '. human_time_diff( $wcsalesbinder_last_synced ) .' ago</p>';
      }
      echo '<script type="text/javascript"> jQuery(document).ready(function($){ $(document).on("click", "#link_force_sync", function(e){ e.preventDefault(); $("#wcsalesbinder_withtest").val("1"); $("#mainform").submit();}); });</script>';
    }

    public function wcsalesbinder_get_settings() {
      $settings_wcsalesbinder = array();

      $settings_wcsalesbinder[] = array(
          'name' => __( 'Woo + SalesBinder Settings', 'woocommerce' ),
          'type' => 'title',
          'desc' => __( 'The following options are used to integrate your SalesBinder Account data with this WooCommerce website.', 'woocommerce' ),
          'id' => 'wcsalesbinder'
      );

      $settings_wcsalesbinder[] =  array(
          'name'     => __( 'SalesBinder Web Address', 'woocommerce' ),
          'desc_tip' => __( 'Enter your SalesBinder Web Address (subdomain) where you would normally login to your account.', 'woocommerce' ),
          'id'       => 'wcsalesbinder_subdomain',
          'type'     => 'text',
          'desc'     => __( '.salesbinder.com', 'woocommerce' ),
      );

      $settings_wcsalesbinder[] =  array(
          'name'     => __( 'SalesBinder API Key', 'woocommerce' ),
          'desc_tip' => __( 'Enter your SalesBinder Api Key (found under your Profile page when logged into SalesBinder).', 'woocommerce' ),
          'id'       => 'wcsalesbinder_apikey',
          'type'     => 'text',
          'css'      => 'min-width:400px;',
          'desc'     => __( 'Example: c6d822b53968f4e7894568bfasde57d899bb72k', 'woocommerce' ),
      );

      $settings_wcsalesbinder[] =  array(
          'name'     => __( 'Account Type', 'woocommerce' ),
          'desc_tip' => __( 'Choose the type of account you would like to use when a WooCommerce customer is synced into your SalesBinder account.', 'woocommerce' ),
          'id'       => 'wcsalesbinder_context_account',
          'type'     => 'radio',
          'desc'     => __( '', 'woocommerce' ),
          'options'  => array(
              2=> __('Customer', 'woocommerce'),
              8=> __('Prospect', 'woocommerce'),
          ),
      );

      $settings_wcsalesbinder[] =  array(
          'name'     => __( 'Order Type', 'woocommerce' ),
          'desc_tip' => __( 'Choose the type of order you would like to use when an order is placed in WooCommerce and then synced into your SalesBinder account.', 'woocommerce' ),
          'id'       => 'wcsalesbinder_context_document',
          'type'     => 'select',
          'desc'     => __( '', 'woocommerce' ),
          'options'  => array(
              5=> __('Invoice', 'woocommerce'),
              4=> __('Estimate', 'woocommerce')
          ),
      );

      $settings_wcsalesbinder[] =  array(
          'name'     => __( 'Allow Backorders', 'woocommerce' ),
          'desc_tip' => __( 'If enabled, items with quantity levels of zero or below can still be sold.', 'woocommerce' ),
          'id'       => 'wcsalesbinder_backorders',
          'type'     => 'select',
          'desc'     => __( '', 'woocommerce' ),
          'options'  => array(
              'yes' => __('Yes', 'woocommerce'),
              'no' => __('No', 'woocommerce')
          ),
      );

      $settings_wcsalesbinder[] =  array(
          'name'     => __( 'Full Sync Interval', 'woocommerce' ),
          'desc_tip' => __( 'Choose an interval option from the list. This will do a complete full sync of your SalesBinder Account data. This can be a slow process for large inventory lists.', 'woocommerce' ),
          'id'       => 'wcsalesbinder_sync',
          'type'     => 'select',
          'desc'     => __( '', 'woocommerce' ),
          'options'  => array(
              'disabled'=> __('Disabled', 'woocommerce'),
              //'hourly'=> __('Hourly', 'woocommerce'),
              'daily'=> __('Daily', 'woocommerce'),
              'twicedaily'=> __('Twice Daily', 'woocommerce'),
          ),
      );

      $settings_wcsalesbinder[] =  array(
          'name'     => __( 'Incremental Sync Interval', 'woocommerce' ),
          'desc_tip' => __( 'Choose an interval option from this list. This will sync your latest data changes from your SalesBinder Account (very quick process).', 'woocommerce' ),
          'id'       => 'wcsalesbinder_partial_sync',
          'type'     => 'select',
          'desc'     => __( '', 'woocommerce' ),
          'options'  => array(
              'disabled'=> __('Disabled', 'woocommerce'),
              'onceevery5minutes'=> __('Every 5 minutes', 'woocommerce'),
              'onceevery30minutes'=> __('Every 30 minutes', 'woocommerce'),
          ),
      );

      $settings_wcsalesbinder[] =  array(
          'name'     => __( '', 'woocommerce' ),
          'desc_tip' => __( '', 'woocommerce' ),
          'id'       => 'wcsalesbinder_withtest',
          'type'     => 'text',
          'css'      =>  'display:none;',
          'desc'     => __( '', 'woocommerce' ),
      );


      $settings_wcsalesbinder[] = array(
          'type' => 'sectionend',
          'id' => 'wcsalesbinder_end'
      );

      $settings_wcsalesbinder[] = array(
          'name' => __( 'Note:' ),
          'type' => 'title',
          'desc' => __( '<div style="max-width: 700px;">Pressing the "Save changes" button below will restart the sync process in the background. It may take a few minutes for your initial sync to start showing products in your WooCommerce Products section.</div>', 'woocommerce' ),
          'id' => 'wcsalesbinder_note'
      );

      $settings_wcsalesbinder[] = array(
          'type' => 'sectionend',
          'id' => 'wcsalesbinder_end'
      );

      return apply_filters( 'wc_settings_wcsalesbinder', $settings_wcsalesbinder );
    }

    public function update_settings() {
      woocommerce_update_options( $this->wcsalesbinder_get_settings() );

      wp_clear_scheduled_hook('wcsalesbinder_cron');

      $interval = get_option('wcsalesbinder_sync');
      $partial_interval = get_option('wcsalesbinder_partial_sync');

      if ( !empty( $interval ) ) {
        wp_schedule_event( time(), $interval, 'wcsalesbinder_cron' );
      }

      // Only updates partial sync interval if a sync has already completed.
      // If this is the first sync, this cron job will be setup after the first sync is completed.
      if ( get_option("wcsalesbinder_last_synced") && !empty( $partial_interval ) ) {
        wp_clear_scheduled_hook( 'wcsalesbinder_partial_cron' );
        wp_schedule_event( time(), $partial_interval, 'wcsalesbinder_partial_cron' );
      }

      $withtest = get_option( 'wcsalesbinder_withtest' );
      if ( $withtest == 1 ) {
        update_option( 'wcsalesbinder_withtest', '0' );
        do_action( 'wcsalesbinder_cron' );
      }
    }

    public function cron_schedules( $schedules ) {
      $schedules['onceevery5minutes'] = array(
          'interval' => 60 * 5,
          'display' => 'Once Every 5 Minutes',
          'wcsalesbinder' => true
      );

      $schedules['onceevery30minutes'] = array(
          'interval' => 60 * 30,
          'display' => 'Once Every 30 Minutes',
          'wcsalesbinder' => true
      );

      $schedules['twicehourly'] = array(
          'interval' => 30 * 60,
          'display' => 'Twice Hourly',
          'wcsalesbinder' => true
      );

      return $schedules;
    }

    public function partial_cron() {
      $subdomain = get_option( 'wcsalesbinder_subdomain' );
      $api_key = get_option( 'wcsalesbinder_apikey' );

      if (!$api_key || !$subdomain) {
        return;
      }

      $this->sync_categories();
      $this->sync_products( true );
    }

    public function cron() {
      $subdomain = get_option( 'wcsalesbinder_subdomain' );
      $api_key = get_option( 'wcsalesbinder_apikey' );

      if (!$api_key || !$subdomain) {
        return;
      }

      $this->sync_categories();
      $this->sync_products();
    }

    public function sync_categories() {
      $this->categories->sync();
    }

    private function get_total_product_pages( $is_partial ) {
      if ( $is_partial ) {
        return $this->partial_sync->get_total_pages();
      } else {
        return $this->full_sync->get_total_pages();
      }
    }

    private function push_to_product_queue( $item, $is_partial ) {
      if ( $is_partial ) {
        return $this->partial_sync->push_to_queue( $item );
      } else {
        return $this->full_sync->push_to_queue( $item );
      }
    }

    private function dispatch_products( $is_partial ) {
      if ( $is_partial ) {
        $this->partial_sync->save()->dispatch();
      } else {
        $this->full_sync->save()->dispatch();
      }
    }

    public function sync_products( $is_partial = false ) {
      $page = get_option( 'current_sync_page', 1 );

      $wcsalesbinder_last_synced = get_option( 'wcsalesbinder_last_synced', false);
      $last_sync_timestamp = !empty( $wcsalesbinder_last_synced ) ? $wcsalesbinder_last_synced - 7200 : time() - 43200;
      $wcsalesbinder_last_synced = null;

      $total_pages = $this->get_total_product_pages( $is_partial );
      if ( !$is_partial ) {
        update_option( 'total_pages_to_sync', $total_pages );
      }

      do {
        $this->push_to_product_queue( array(
          'page'                => $page++,
          'last_sync_timestamp' => $last_sync_timestamp,
          'is_last_page'        => $page == $total_pages
        ), $is_partial );
      } while( $page <= $total_pages );

      $this->dispatch_products( $is_partial );
    }

    private function account($context, $email) {
      $subdomain = get_option( 'wcsalesbinder_subdomain' );
      $api_key = get_option( 'wcsalesbinder_apikey' );

      $url = 'https://'.$api_key.':x@app.salesbinder.com/api/2.0/customers.json?emailAddress=' . urlencode($email);
      $response = wp_remote_get($url, $this->basic_args_for_get_request($api_key));

      if (wp_remote_retrieve_response_code($response) != 200 || is_wp_error($response)) {
        wc_print_notice('SalesBinder sync failed to load ' . $url, 'error');
        return;
      }

      $response = json_decode(wp_remote_retrieve_body($response), true);

      if (!empty($response['customers'][0][0]['id'])) {
        return $response['customers'][0][0]['id'];
      }
    }

    public function woo_order_success( $order_id ) {

        $subdomain = get_option( 'wcsalesbinder_subdomain' );
        $api_key = get_option( 'wcsalesbinder_apikey' );

        $order = new WC_Order( $order_id );
        $myuser_id = (int)$order->user_id;
        $user_info = get_userdata($myuser_id);
        $user = $this->getUserData($myuser_id);

        $account_context = get_option('wcsalesbinder_context_account');

    		if (!empty($user)) {
            if (!empty($_POST["billing_first_name"])) {
                $name = sanitize_text_field($_POST["billing_first_name"]);
                if (!empty($_POST["billing_last_name"])) $name = $name . ' ' . sanitize_text_field($_POST["billing_last_name"]);
            }
            if (!empty($_POST["billing_company"])) $name = sanitize_text_field($_POST["billing_company"]); // Use company name in SalesBinder if provided
            $billing_email = (!isset($_POST["billing_email"])) ? "" : sanitize_email($_POST["billing_email"]);
            $office_email = !empty($user->billing_email) ? $user->billing_email : '';
        		$office_phone = !empty($user->billing_phone) ? $user->billing_phone : '';
        		$billing_address_1 = !empty($user->billing_address_1) ? $user->billing_address_1 : '';
        		$billing_address_2 = !empty($user->billing_address_2) ? $user->billing_address_2 : '';
        		$billing_city = !empty($user->billing_city) ? $user->billing_city : '';
        		$billing_region = !empty($user->billing_state) ? $user->billing_state : '';
        		$billing_country = !empty($user->billing_country) ? $user->billing_country : '';
        		$billing_postal_code = !empty($user->billing_postal_code) ? $user->billing_postal_code : '';
        		$shipping_address_1 = !empty($user->shipping_address_1) ? $user->shipping_address_1 : '';
        		$shipping_address_2 = !empty($user->shipping_address_2) ? $user->shipping_address_2 : '';
        		$shipping_city = !empty($user->shipping_city) ? $user->shipping_city : '';
        		$shipping_region = !empty($user->shipping_state) ? $user->shipping_state : '';
        		$shipping_country = !empty($user->shipping_country) ? $user->shipping_country : '';
        		$shipping_postal_code = !empty($user->shipping_postcode) ? $user->shipping_postcode : '';
    		}else{
            if (!empty($_POST["billing_first_name"])) {
                $name = sanitize_text_field($_POST["billing_first_name"]);
                if (!empty($_POST["billing_last_name"])) $name = $name . ' ' . sanitize_text_field($_POST["billing_last_name"]);
            }
            if (!empty($_POST["billing_company"])) $name = sanitize_text_field($_POST["billing_company"]); // Use company name in SalesBinder if provided
            $billing_email = (!isset($_POST["billing_email"])) ? "" : sanitize_email($_POST["billing_email"]);
            $office_email = $billing_email;
            $office_phone = (!isset($_POST["billing_phone"])) ? "" : sanitize_text_field($_POST["billing_phone"]);
            $billing_address_1 = (!isset($_POST["billing_address_1"])) ? "" : sanitize_text_field($_POST["billing_address_1"]);
            $billing_address_2 = (!isset($_POST["billing_address_2"])) ? "" : sanitize_text_field($_POST["billing_address_2"]);
            $billing_city = (!isset($_POST["billing_city"])) ? "" : sanitize_text_field($_POST["billing_city"]);
            $billing_region = (!isset($_POST["billing_state"])) ? "" : sanitize_text_field($_POST["billing_state"]);
            $billing_country = (!isset($_POST["billing_country"])) ? "" : sanitize_text_field($_POST["billing_country"]);
            $billing_postal_code = (!isset($_POST["billing_postcode"])) ? "" : sanitize_text_field($_POST["billing_postcode"]);
            $shipping_address_1 = (!isset($_POST["shipping_address_1"])) ? "" : sanitize_text_field($_POST["shipping_address_1"]);
            $shipping_address_2 = (!isset($_POST["shipping_address_2"])) ? "" : sanitize_text_field($_POST["shipping_address_2"]);
            $shipping_city = (!isset($_POST["shipping_city"])) ? "" : sanitize_text_field($_POST["shipping_city"]);
            $shipping_region = (!isset($_POST["shipping_region"])) ? "" : sanitize_text_field($_POST["shipping_region"]);
            $shipping_country = (!isset($_POST["shipping_country"])) ? "" : sanitize_text_field($_POST["shipping_country"]);
            $shipping_postal_code = (!isset($_POST["shipping_postal_code"])) ? "" : sanitize_text_field($_POST["shipping_postal_code"]);
        }

        $account_id = $this->account($account_context, $billing_email);

        if (empty($account_id)) {
          $account = array(
              'customer' => array(
                  'context_id' => $account_context ?: 8,
                  'name' => (!empty($name)) ? $name : 'No Name Provided',
                  'office_email' => $office_email,
                  'office_phone' => $office_phone,
                  'billing_address_1' => $billing_address_1,
                  'billing_address_2' => $billing_address_2,
                  'billing_city' => $billing_city,
                  'billing_region' => $billing_region,
                  'billing_country' => $billing_country,
                  'billing_postal_code' => $billing_postal_code,
                  'shipping_address_1' => $shipping_address_1,
                  'shipping_address_2' => $shipping_address_2,
                  'shipping_city' => $shipping_city,
                  'shipping_region' => $shipping_region,
                  'shipping_country' => $shipping_country,
                  'shipping_postal_code' => $shipping_postal_code
              )
          );

          $url = 'https://app.salesbinder.com/api/2.0/customers.json';
          $response = wp_remote_post($url, array(
      			'headers' => array(
      			  'Authorization' => 'Basic ' . base64_encode($api_key . ':x'),
              'Content-Type' => 'application/json'
      			),
            'timeout' => 45,
            'body' => json_encode($account),
            'redirection' => 5
          ));

          if (wp_remote_retrieve_response_code($response) != 200 || is_wp_error($response)) {
            wc_print_notice('SalesBinder sync failed to load ' . $url, 'error');
            return;
          }

          $account = json_decode($response['body'], true);
          if (empty($account['customer']['id'])) {
            wc_print_notice('SalesBinder sync failed to load ' . $url, 'error');
            return;
          }
          $account_id = $account['customer']['id'];
        }

        $document_context = get_option('wcsalesbinder_context_document');
        $document = array(
          'document' => array(
            'context_id' => $document_context ?: 4,
            'customer_id' => $account_id,
            'issue_date' => date('Y-m-d', strtotime($order->order_date)),
            'shipping_address' => $order->get_formatted_shipping_address(),
            'document_items' => array()
          )
        );

        $items_order = $order->get_items(); // get all items
        $items_taxes = $order->get_items('tax'); // get taxes
        $subtotal = $order->get_subtotal();

        // Get the total taxes charged and determine actual tax rates to push to SalesBinder
        foreach ($items_taxes as $tax) {
            $tax_array[] = ($tax['item_meta']['tax_amount'][0] / $subtotal) * 100;
        }

        foreach ($items_order as $item) {
            $id_product_salesbinder = get_post_meta( $item['product_id'], 'id_product_salesbinder', true); // get id product salesbinder
            if(!empty($id_product_salesbinder)){
                $item_salesbinder = array(
                    'item_id' => $id_product_salesbinder,
                    'quantity' => $item['qty'],
                    'price' => round($item['line_subtotal']/$item['qty'],2),
                    'tax' => !empty($tax_array[0]) ? $tax_array[0] : 0,
                    'tax2'=> !empty($tax_array[1]) ? $tax_array[1] : 0,
                );

                $document['document']['document_items'][] = $item_salesbinder;
            }
        }

        $url = 'https://app.salesbinder.com/api/2.0/documents.json';
        $response = wp_remote_post( $url, array(
          'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($api_key . ':x'),
            'Content-Type' => 'application/json'
          ),
          'timeout' => 45,
          'body' => json_encode($document),
          'redirection' => 5
        ));

        if (wp_remote_retrieve_response_code($response) != 200 || is_wp_error($response)) {
          wc_print_notice('SalesBinder sync failed to load ' . $url, 'error');
          return;
        }

        $customer = json_decode($response['body'], true);
        if (empty($customer['document']['id'])) {
          wc_print_notice('SalesBinder sync failed to load ' . $url, 'error');
          return;
        }

        update_post_meta( $order_id, 'id_purchase_salesbinder', $customer['document']['id'] );
    }

    private function getUserData($user_id) {
      $userdata = get_user_meta($user_id,'',true);

      $user = new stdClass;
      if (!empty($userdata)) {
        foreach ($userdata as $attr => $valarr) {
            $user->$attr = $valarr[0];
        }
        return $user;
      }
      return false;
    }

    /**
     * Return an instance of this class.
     *
     * @return object A single instance of this class.
     * @since  1.0
     */
    public static function get_instance() {
      // If the single instance hasn't been set, set it now.
      if ( is_null( self::$instance ) ) {
        self::$instance = new self;
      }

      return self::$instance;
    }

    public function basic_args_for_get_request($api_key) {
      return array(
        'headers' => array(
          'Authorization' => 'Basic ' . base64_encode( $api_key . ':' . "x" ),
          'Content-Type' => 'application/json'
        ),
        'timeout' => 60,
      );
    }

}

add_action( 'plugins_loaded', array( 'WC_SalesBinder', 'get_instance' ), 0 );

endif;
