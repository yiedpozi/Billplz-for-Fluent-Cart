<?php

if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Main class.
 * 
 * @since 1.0.0
 */
class Billplz_FluentCart {
    /**
     * Class instance.
     * 
     * @since 1.0.0
     * 
     * @var Billplz_FluentCart
     */
    private static $_instance;

    /**
     * Get an instance of the class.
     */
    public static function get_instance() {
        if ( self::$_instance === null ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /* 
     * Constructor.
     * 
     * @since 1.0.0
     */
    public function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define plugin constants.
     * 
     * @since 1.0.0
     */
    private function define_constants() {
        define( 'BILLPLZ_FLUENTCART_URL', plugin_dir_url( BILLPLZ_FLUENTCART_FILE ) );
        define( 'BILLPLZ_FLUENTCART_PATH', plugin_dir_path( BILLPLZ_FLUENTCART_FILE ) );
        define( 'BILLPLZ_FLUENTCART_BASENAME', plugin_basename( BILLPLZ_FLUENTCART_FILE ) );
    }

    /**
     * Include required core files.
     * 
     * @since 1.0.0
     */
    private function includes() {
        // Debug logger
        require_once BILLPLZ_FLUENTCART_PATH . 'includes/class-billplz-fluent-cart-logger.php';

        // API
        require_once BILLPLZ_FLUENTCART_PATH . 'includes/abstracts/abstract-billplz-fluent-cart-client.php';
        require_once BILLPLZ_FLUENTCART_PATH . 'includes/class-billplz-fluent-cart-api.php';

        // Admin
        require_once BILLPLZ_FLUENTCART_PATH . 'includes/admin/class-billplz-fluent-cart-admin.php';
    }

    /**
     * Register action and filter hooks.
     * 
     * @since 1.0.0
     */
    private function init_hooks() {
        add_action( 'fluent_cart/register_payment_methods', [ $this, 'register_payment_methods' ] );
    }

    /**
     * Register Billplz as a payment method in FluentCart.
     * 
     * @since 1.0.0
     */
    public function register_payment_methods() {
        if ( !function_exists( 'fluent_cart_api' ) ) {
            return;
        }

        include_once BILLPLZ_FLUENTCART_PATH . 'includes/class-billplz-fluent-cart-gateway-settings.php';
        include_once BILLPLZ_FLUENTCART_PATH . 'includes/class-billplz-fluent-cart-gateway.php';
        include_once BILLPLZ_FLUENTCART_PATH . 'includes/class-billplz-fluent-cart-handler.php';

        fluent_cart_api()->registerCustomPaymentMethod( 'billplz', new Billplz_FluentCart_Gateway() );
    }
}

Billplz_FluentCart::get_instance();
