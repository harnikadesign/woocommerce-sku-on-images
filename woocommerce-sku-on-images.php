<?php
/**
 * Plugin Name: WooCommerce SKU On Images
 * Description: Automatically overlays product SKU text onto WooCommerce product images upon upload/assignment, with admin settings and bulk regeneration.
 * Version: 1.0.0
 * Author: Harnika Design
 * Author URI: 	https://www.harnikadesign.com
 * Text Domain: woocommerce-sku-on-images
 * License: GPLv2 or later
 * License URI:	http://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constants
define( 'WCSIO_VERSION', '1.0.0' );
define( 'WCSIO_PLUGIN_FILE', __FILE__ );
define( 'WCSIO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WCSIO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCSIO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Activation/Deactivation
register_activation_hook( __FILE__, function () {
    // Require WooCommerce
    if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins', [] ) ), true ) &&
         ! ( is_multisite() && array_key_exists( 'woocommerce/woocommerce.php', (array) get_site_option( 'active_sitewide_plugins', [] ) ) ) ) {
        deactivate_plugins( WCSIO_PLUGIN_BASENAME );
        wp_die( esc_html__( 'WooCommerce SKU On Images requires WooCommerce to be active.', 'woocommerce-sku-on-images' ) );
    }

    // Create default options
    $defaults = [
        'enabled'     => 1,
        'position'    => 'bottom-right',
        'font_size'   => 18,
        'line_height' => 1.2,
        'inner_padding' => 6,
        'inner_padding_top'    => 6,
        'inner_padding_right'  => 6,
        'inner_padding_bottom' => 6,
        'inner_padding_left'   => 6,
        'text_align' => 'center',
        'backup_original' => 1,
        'text_color'  => '#FFFFFF',
        'bg_color'    => '#000000',
        'bg_opacity'  => 50, // 0-100
        'margin'      => 10,
        'margin_top'    => 10,
        'margin_right'  => 10,
        'margin_bottom' => 10,
        'margin_left'   => 10,
        'font_path'   => WCSIO_PLUGIN_DIR . 'assets/fonts/arial.ttf',
        'use_google_font'   => 0,
        'google_font_family' => 'Roboto',
    ];
    if ( ! get_option( 'wcsio_options' ) ) {
        add_option( 'wcsio_options', $defaults );
    } else {
        $existing = get_option( 'wcsio_options', [] );
        update_option( 'wcsio_options', wp_parse_args( $existing, $defaults ) );
    }
} );

register_deactivation_hook( __FILE__, function () {
    // No destructive cleanup by default
} );

// Bootstrap
add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'WooCommerce SKU On Images requires WooCommerce to be active.', 'woocommerce-sku-on-images' ) . '</p></div>';
        } );
        return;
    }

    // Load files
    require_once WCSIO_PLUGIN_DIR . 'includes/class-sku-overlay-main.php';
    require_once WCSIO_PLUGIN_DIR . 'includes/class-settings.php';
    require_once WCSIO_PLUGIN_DIR . 'includes/class-font-manager.php';
    require_once WCSIO_PLUGIN_DIR . 'includes/class-image-processor.php';
    require_once WCSIO_PLUGIN_DIR . 'includes/class-product-hooks.php';
    require_once WCSIO_PLUGIN_DIR . 'includes/class-admin.php';

    // Init plugin
    \WCSIO\SKU_Overlay_Main::instance();
} );
