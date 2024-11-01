<?php
/**
 * Plugin Name: WordPress Lucky Wheel
 * Description: Collect customer's emails by spinning the lucky wheel game to get discount coupons.
 * Version: 1.0.12
 * Author: VillaTheme
 * Author URI: http://villatheme.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-lucky-wheel
 * Domain Path: /languages
 * Copyright 2018-2024 VillaTheme.com. All rights reserved.
 * Tested up to: 6.6
 * Requires PHP: 7.0
 * Requires at least: 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
define( 'VI_WP_LUCKY_WHEEL_VERSION', '1.0.12' );
if ( is_plugin_active( 'wordpress-lucky-wheel/wordpress-lucky-wheel.php' ) ) {
	return;
}

if ( ! class_exists( 'WP_LUCKY_WHEEL' ) ) {
	class WP_LUCKY_WHEEL {
		protected $settings;

		public function __construct() {
			add_action( 'plugins_loaded', function () {
				if ( ! class_exists( 'VillaTheme_Require_Environment' ) ) {
					include_once WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . "wp-lucky-wheel" . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . 'support.php';
				}

				$environment = new \VillaTheme_Require_Environment( [
						'plugin_name'     => 'WordPress Lucky Wheel',
						'php_version'     => '7.0',
						'wp_version'      => '5.0',
						'require_plugins' => []
					]
				);

				if ( $environment->has_error() ) {
					return;
				}
				$init_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . "wp-lucky-wheel" . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "includes.php";
				require_once $init_file;
				$this->settings = VI_WP_LUCKY_WHEEL_DATA::get_instance();
				add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

				add_action( 'before_woocommerce_init', array( $this, 'before_woocommerce_init' ) );
				add_action( 'init', array( $this, 'create_custom_post_type' ) );
				add_filter( 'manage_wplwl_email_posts_columns', array( $this, 'add_column' ), 10, 1 );
				add_action( 'manage_wplwl_email_posts_custom_column', array( $this, 'add_column_data' ), 10, 2 );
				add_filter( 'plugin_action_links_wp-lucky-wheel/wp-lucky-wheel.php', array( $this, 'settings_link' ) );
			} );

		}

		public function before_woocommerce_init() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
			}
		}

		public function settings_link( $links ) {
			$settings_link = '<a href="admin.php?page=wp-lucky-wheel" title="' . esc_html__( 'Settings', 'wp-lucky-wheel' ) . '">' . esc_html__( 'Settings', 'wp-lucky-wheel' ) . '</a>';
			array_unshift( $links, $settings_link );

			return $links;
		}

		public function create_custom_post_type() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			if ( post_type_exists( 'wplwl_email' ) ) {
				return;
			}
			$args = array(
				'labels'              => array(
					'name'               => esc_html_x( 'Lucky Wheel Email', 'wp-lucky-wheel' ),
					'singular_name'      => esc_html_x( 'Email', 'wp-lucky-wheel' ),
					'menu_name'          => esc_html_x( 'Emails', 'Admin menu', 'wp-lucky-wheel' ),
					'name_admin_bar'     => esc_html_x( 'Emails', 'Add new on Admin bar', 'wp-lucky-wheel' ),
					'view_item'          => esc_html__( 'View Email', 'wp-lucky-wheel' ),
					'all_items'          => esc_html__( 'Email Subscribe', 'wp-lucky-wheel' ),
					'search_items'       => esc_html__( 'Search Email', 'wp-lucky-wheel' ),
					'parent_item_colon'  => esc_html__( 'Parent Email:', 'wp-lucky-wheel' ),
					'not_found'          => esc_html__( 'No Email found.', 'wp-lucky-wheel' ),
					'not_found_in_trash' => esc_html__( 'No Email found in Trash.', 'wp-lucky-wheel' )
				),
				'description'         => esc_html__( 'WordPress lucky wheel emails.', 'wp-lucky-wheel' ),
				'public'              => false,
				'show_ui'             => true,
				'capability_type'     => 'post',
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_in_menu'        => false,
				'hierarchical'        => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
			);
			register_post_type( 'wplwl_email', $args );
		}

		public function add_column( $columns ) {
			$columns['customer_name'] = esc_html__( 'Customer name', 'wp-lucky-wheel' );
			$columns['mobile']        = esc_html__( 'Mobile', 'wp-lucky-wheel' );
			$columns['spins']         = esc_html__( 'Number of spins', 'wp-lucky-wheel' );
			$columns['last_spin']     = esc_html__( 'Last spin', 'wp-lucky-wheel' );
			$columns['label']         = esc_html__( 'Labels', 'wp-lucky-wheel' );
			$columns['coupon']        = esc_html__( 'Coupons', 'wp-lucky-wheel' );

			return $columns;
		}

		public function add_column_data( $column, $post_id ) {
			switch ( $column ) {
				case 'customer_name':
					if ( get_post( $post_id )->post_content ) {
						echo wp_kses_post(get_post( $post_id )->post_content);
					}
					break;
				case 'mobile':
					if ( get_post_meta( $post_id, 'wplwl_email_mobile', true ) ) {
						echo wp_kses_post(get_post_meta( $post_id, 'wplwl_email_mobile', true ));
					}
					break;
				case 'spins':
					if ( get_post_meta( $post_id, 'wplwl_spin_times', true ) ) {
						echo wp_kses_post(get_post_meta( $post_id, 'wplwl_spin_times', true )['spin_num']);
					}
					break;
				case 'last_spin':
					if ( get_post_meta( $post_id, 'wplwl_spin_times', true ) ) {
						echo wp_kses_post(date( 'Y-m-d h:i:s', get_post_meta( $post_id, 'wplwl_spin_times', true )['last_spin'] ));// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
					}
					break;

				case 'label':
					if ( get_post_meta( $post_id, 'wplwl_email_labels', true ) ) {
						$label = get_post_meta( $post_id, 'wplwl_email_labels', true );
						if ( sizeof( $label ) > 1 ) {
							for ( $i = sizeof( $label ) - 1; $i >= 0; $i -- ) {
								echo '<p>' . esc_html( $label[ $i ] ) . '</p>';
							}
						} else {
							echo esc_html( $label[0] );
						}
					}
					break;
				case 'coupon':
					if ( get_post_meta( $post_id, 'wplwl_email_coupons', true ) ) {
						$coupon = get_post_meta( $post_id, 'wplwl_email_coupons', true );
						if ( sizeof( $coupon ) > 1 ) {
							for ( $i = sizeof( $coupon ) - 1; $i >= 0; $i -- ) {
								echo '<p>' . esc_html( $coupon[ $i ] ) . '</p>';
							}
						} else {
							echo esc_html( $coupon[0] );
						}
					}
					break;
			}
		}

		function load_plugin_textdomain() {
			$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
			$locale = apply_filters( 'plugin_locale', $locale, 'wp-lucky-wheel' );
			load_textdomain( 'wp-lucky-wheel', WP_PLUGIN_DIR . "/wp-lucky-wheel/languages/wp-lucky-wheel-$locale.mo" );
			load_plugin_textdomain( 'wp-lucky-wheel', false, basename( dirname( __FILE__ ) ) . "/languages" );
			if ( class_exists( 'VillaTheme_Support' ) ) {
				new VillaTheme_Support(
					array(
						'support'    => 'https://wordpress.org/support/plugin/wp-lucky-wheel/',
						'docs'       => 'http://docs.villatheme.com/?item=wp-lucky-wheel',
						'review'     => 'https://wordpress.org/support/plugin/wp-lucky-wheel/reviews/?rate=5#rate-response',
						'pro_url'    => 'https://1.envato.market/xDRb1',
						'css'        => VI_WP_LUCKY_WHEEL_CSS,
						'image'      => VI_WP_LUCKY_WHEEL_IMAGES,
						'slug'       => 'wp-lucky-wheel',
						'menu_slug'  => 'wp-lucky-wheel',
						'version'    => VI_WP_LUCKY_WHEEL_VERSION,
						'survey_url' => 'https://script.google.com/macros/s/AKfycbycVmkGavLGdtx0ir6jwJaufhJhxv23dyInxdQtAWrTdktLqu4Ve4Iq3klsj8MUtzzD/exec'
					)
				);
			}
		}
	}
}

new WP_LUCKY_WHEEL();
