<?php
namespace WCSIO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SKU_Overlay_Main {
    private static $instance = null;

    /** @var Settings */
    public $settings;
    /** @var Image_Processor */
    public $processor;
    /** @var Product_Hooks */
    public $hooks;
    /** @var Admin */
    public $admin;

    public static function instance() : self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings  = new Settings();
        $this->processor = new Image_Processor( $this->settings );
        $this->hooks     = new Product_Hooks( $this->settings, $this->processor );

        if ( is_admin() ) {
            $this->admin = new Admin( $this->settings, $this->processor );
        }

        add_action( 'admin_init', [ $this, 'maybe_show_requirements_notice' ] );
    }

    public function maybe_show_requirements_notice() : void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        // Check at least one library available
        if ( ! $this->processor->is_library_available() ) {
            add_action( 'admin_notices', function () {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'WooCommerce SKU On Images: No PHP image library available. Please enable GD or Imagick.', 'woocommerce-sku-on-images' ) . '</p></div>';
            } );
        }
    }
}

