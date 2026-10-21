<?php
/**
 * Core: Plugin Bootstrap
 *
 * @package     AffiliateWP Affiliate Product Rates
 * @subpackage  Core
 * @copyright   Copyright (c) 2021, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'AffiliateWP_Affiliate_Product_Rates' ) ) {

	/**
	 * Main plugin bootstrap.
	 *
	 * @since 1.0.0
	 */
	final class AffiliateWP_Affiliate_Product_Rates {

		/**
		 * Holds the instance
		 *
		 * Ensures that only one instance of AffiliateWP_Affiliate_Product_Rates exists in memory at any one
		 * time and it also prevents needing to define globals all over the place.
		 *
		 * TL;DR This is a static property property that holds the singleton instance.
		 *
		 * @var object
		 * @static
		 * @since 1.0
		 */
		private static $instance;

		/**
		 * The version number of AffiliateWP
		 *
		 * @since 1.0
		 * @var string
		 */
		private $version = '';

		/**
		 * Main plugin file.
		 *
		 * @since 1.1
		 * @var   string
		 */
		private $file = '';

		/**
		 * Main AffiliateWP_Affiliate_Product_Rates Instance
		 *
		 * Insures that only one instance of AffiliateWP_Affiliate_Product_Rates exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @since 1.0
		 * @static
		 * @staticvar array $instance
		 * @param string $file Main plugin file.
		 * @return The one true AffiliateWP_Affiliate_Product_Rates
		 */
		public static function instance( $file = null ) {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof AffiliateWP_Affiliate_Product_Rates ) ) {
				self::$instance = new AffiliateWP_Affiliate_Product_Rates;
				self::$instance->file    = $file;
				self::$instance->version = get_plugin_data( self::$instance->file, false, false )['Version'] ?? '';
				self::$instance->setup_constants();
				self::$instance->hooks();
				self::$instance->includes();

			}

			return self::$instance;
		}

		/**
		 * Throw error on object clone
		 *
		 * The whole idea of the singleton design pattern is that there is a single
		 * object therefore, we don't want the object to be cloned.
		 *
		 * @since 1.0
		 * @access protected
		 * @return void
		 */
		public function __clone() {
			// Cloning instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin’ huh?', 'affiliatewp-affiliate-product-rates' ), '1.0' );
		}

		/**
		 * Disable unserializing of the class
		 *
		 * @since 1.0
		 * @access protected
		 * @return void
		 */
		public function __wakeup() {
			// Unserializing instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin’ huh?', 'affiliatewp-affiliate-product-rates' ), '1.0' );
		}

		/**
		 * Constructor Function
		 *
		 * @since 1.0
		 * @access private
		 */
		private function __construct() {
			self::$instance = $this;
		}

		/**
		 * Reset the instance of the class
		 *
		 * @since 1.0
		 * @access public
		 * @static
		 */
		public static function reset() {
			self::$instance = null;
		}

		/**
		 * Setup plugin constants
		 *
		 * @access private
		 * @since 1.0
		 * @return void
		 */
		private function setup_constants() {
			// Plugin version.
			if ( ! defined( 'AFFWP_APR_VERSION' ) ) {
				define( 'AFFWP_APR_VERSION', $this->version );
			}

			// Plugin Folder Path.
			if ( ! defined( 'AFFWP_APR_PLUGIN_DIR' ) ) {
				define( 'AFFWP_APR_PLUGIN_DIR', plugin_dir_path( $this->file ) );
			}

			// Plugin Folder URL.
			if ( ! defined( 'AFFWP_APR_PLUGIN_URL' ) ) {
				define( 'AFFWP_APR_PLUGIN_URL', plugin_dir_url( $this->file ) );
			}

			// Plugin Root File.
			if ( ! defined( 'AFFWP_APR_PLUGIN_FILE' ) ) {
				define( 'AFFWP_APR_PLUGIN_FILE', $this->file );
			}
		}

		/**
		 * Setup the default hooks and actions
		 *
		 * @since 1.0
		 *
		 * @return void
		 */
		private function hooks() {
			add_filter( 'affwp_calc_referral_amount', array( $this, 'calculate_referral_amount' ), 10, 5 );

			// update the product rates when the affiliate is updated.
			add_action( 'affwp_post_update_affiliate', array( $this, 'update_affiliate' ), 10, 2 );

			// add the product rates when adding a new affiliate.
			add_action( 'affwp_insert_affiliate', array( $this, 'add_affiliate_rates' ) );
		}

		/**
		 * Include required files
		 *
		 * @access private
		 * @since 1.0
		 * @return void
		 */
		private function includes() {
			if ( is_admin() ) {
				require_once AFFWP_APR_PLUGIN_DIR . 'includes/class-admin.php';
				require_once AFFWP_APR_PLUGIN_DIR . 'includes/scripts.php';
			}
		}

		/**
		 * Add product rates for the  affiliate
		 * This is done when an affiliate is added to the DB
		 *
		 * @param int $affiliate_id Affiliate ID.
		 */
		public function add_affiliate_rates( $affiliate_id = 0 ) {

			// is_admin() is true on admin-ajax.php, so it never restricted this to administrators.
			// Require the capability and the rate-table nonce, then pass the payload explicitly.
			if ( ! current_user_can( 'manage_affiliates' ) ) {
				return;
			}

			if ( ! isset( $_POST['affwp_product_rates_nonce'] )
				|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['affwp_product_rates_nonce'] ) ), 'affwp_product_rates' )
			) {
				return;
			}

			$user_id = affwp_get_affiliate_user_id( $affiliate_id );

			$this->save_product_rates( $user_id, wp_unslash( $_POST ) );

		}


		/**
		 * Update the affiliate with their product rates
		 * Also handles sanitization
		 *
		 * @param array $data Affiliate data.
		 * @return void
		 */
		public function update_affiliate( $data ) {

			if ( ! current_user_can( 'manage_affiliates' ) ) {
				return;
			}

			if ( ! isset( $_POST['affwp_product_rates_nonce'] )
				|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['affwp_product_rates_nonce'] ) ), 'affwp_product_rates' )
			) {
				return;
			}

			$user_id = isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0;

			if ( $user_id ) {
				// save our rates.
				$this->save_product_rates( $user_id, wp_unslash( $_POST ) );
			}

		}


		/**
		 * Save the product rates when adding or updating an affiliate
		 *
		 * @since 1.0
		 *
		 * @param integer $user_id affiliate's WP user ID.
		 * @param array   $data    affiliate data.
		 */
		public function save_product_rates( $user_id = 0, $data = array() ) {

			// the array saved to the database.
			$saved = array();

			// get the product rates data.
			$product_rates = isset( $data['product_rates'] ) ? $data['product_rates'] : array();

			// sanitize data.
			if ( $product_rates ) {
				// loop through each rate.
				foreach ( $product_rates as $integration_key => $rates_array ) {

					foreach ( $rates_array as $key => $rate ) {

						if ( empty( $rate['products'] ) || ! isset( $rate['rate'] ) || ! is_numeric( $rate['rate'] ) ) {
							// don't save incomplete or non-numeric rates.
							unset( $rates_array[$key] );

						} else {
							// Coerce products to a list of integers and clamp the rate, so a scalar
							// products value cannot fatal the readers and a bad rate cannot exceed the sale.
							$products = array_values( array_filter( array_map( 'absint', (array) $rate['products'] ) ) );

							if ( empty( $products ) ) {
								unset( $rates_array[$key] );
								continue;
							}

							$type   = in_array( $rate['type'] ?? '', array( 'percentage', 'flat' ), true ) ? $rate['type'] : 'percentage';
							$amount = max( 0, (float) $rate['rate'] );

							if ( 'percentage' === $type ) {
								$amount = min( 100, $amount );
							}

							// add to saved array.
							$saved[ $integration_key ][ $key ]['products'] = $products;
							$saved[ $integration_key ][ $key ]['rate']     = $amount;
							$saved[ $integration_key ][ $key ]['type']     = $type;
						}

					}
				}

				// get existing array.
				$existing = get_user_meta( $user_id, 'affwp_product_rates', true );

				// if $saved if empty, delete it.
				if ( empty( $saved ) ) {
					delete_user_meta( $user_id, 'affwp_product_rates' );
				} else {
					// not empty, let's continue.
					// save to user meta if product data exists.
					update_user_meta( $user_id, 'affwp_product_rates', $saved );
				}

			}

		}

		/**
		 * Calculate new referral amounts based on affiliate's product rates
		 *
		 * @since  1.0
		 *
		 * @param string     $referral_amount Base amount to calculate the referral amount from.
		 * @param int        $affiliate_id    Affiliate ID.
		 * @param string     $amount          Base amount to calculate the referral amount from. Usually the Order Total.
		 * @param string|int $reference       Referral reference (usually the order ID).
		 * @param int        $product_id      Product ID. Default 0.
		 */
		public function calculate_referral_amount( $referral_amount, $affiliate_id, $amount, $reference, $product_id ) {

			// get context.
			if ( isset( $_POST['edd_action'] ) && 'purchase' == $_POST['edd_action'] ) {
				$context = 'edd';
			} elseif ( defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
				$context = 'woocommerce';
			} else {
				$context = '';
			}

			if ( $context ) {
				// get the affiliate's product rates.
				$rates = $this->get_rates( $affiliate_id );
				$rates = isset( $rates[ $context ] ) ? $rates[ $context ] : '';

				if ( $rates ) {
					foreach ( $rates as $rate ) {
						// Tolerate a legacy or poisoned row: a scalar products value would otherwise
						// fatal in_array() on PHP 8, and a non-numeric rate would corrupt the amount.
						$rate_products = isset( $rate['products'] ) ? (array) $rate['products'] : array();

						if ( ! isset( $rate['rate'] ) || ! is_numeric( $rate['rate'] ) ) {
							continue;
						}

						// product matches.
						if ( in_array( (int) $product_id, array_map( 'absint', $rate_products ), true ) ) {
							if ( 'percentage' === ( $rate['type'] ?? '' ) ) {
								$referral_amount = $amount * (float) $rate['rate'] / 100;
							} else {
								$referral_amount = (float) $rate['rate'];
							}
						}
					}
				}
			}

			return $referral_amount;
		}

		/**
		 * Get products
		 *
		 * @param string $context The context.
		 * @return array Products list.
		 */
		public function get_products( $context ) {
			switch ( $context ) {

				case 'edd':
					$post_type = 'download';
					break;

				case 'woocommerce':
					$post_type = 'product';
					break;
			}

			$products = get_posts(
				array(
					'post_type'      => $post_type,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'posts_per_page' => 300,
				)
			);

			if ( ! empty( $products ) ) {
				return $products;
			}

			// return empty array.
			return array();
		}

		/**
		 * Retrieve the product rates from user meta
		 *
		 * @access public
		 * @since 1.0
		 *
		 * @param int $affiliate_id Affiliate ID.
		 * @return array
		 */
		public function get_rates( $affiliate_id = 0 ) {
			$rates = get_user_meta( affwp_get_affiliate_user_id( $affiliate_id ), 'affwp_product_rates', true );

			return $rates;
		}

		/**
		 * Modify plugin metalinks
		 *
		 * @access public
		 * @since  1.0
		 * @since  1.2.1 Removed Get More Addons link.
		 *
		 * @param array  $links The current links array.
		 * @param string $file A specific plugin table entry.
		 * @return array $links The modified links array
		 */
		public function plugin_meta( $links, $file ) {
			return $links;
		}

		/**
		 * Currently supported integrations
		 *
		 * @since 1.0
		 * @return array supported integrations
		 */
		public function supported_integrations() {
			$supported_integrations = array(
				'edd',
				'woocommerce',
			);

			return $supported_integrations;
		}

	}

	/**
	 * The main function responsible for returning the one true AffiliateWP_Affiliate_Product_Rates
	 * Instance to functions everywhere.
	 *
	 * Use this function like you would a global variable, except without needing
	 * to declare the global.
	 *
	 * Example: <?php $affiliatewp_affiliate_product_rates = affiliatewp_affiliate_product_rates(); ?>
	 *
	 * @since 1.0
	 * @return object The one true AffiliateWP_Affiliate_Product_Rates Instance
	 */
	function affiliatewp_affiliate_product_rates() {
		return AffiliateWP_Affiliate_Product_Rates::instance();
	}
}
