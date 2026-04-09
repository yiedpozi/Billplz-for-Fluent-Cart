<?php

if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Debug logger class.
 * 
 * @since 1.0.0
 */
class Billplz_FluentCart_Logger {
    /**
     * Class instance.
     * 
     * @since 1.0.0
     * 
     * @var Billplz_FluentCart_Logger
     */
    private static $_instance;

    /**
     * Gateway settings.
     * 
     * @since 1.0.0
     */
    private Billplz_FluentCart_Gateway_Settings $settings;

    /**
     * Get an instance of the class.
     */
    public static function get_instance() {
        if ( self::$_instance === null ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function __construct() {
        $this->settings = new Billplz_FluentCart_Gateway_Settings();
    }

    /**
     * Log message into Fluent Cart.
     * 
     * @since 1.0.0
     * 
     * @return void
     */
    public function log( string $type, string $title, string $message ): void
    {
        if ( !function_exists( 'fluent_cart_add_log' ) ) {
            return;
        }

        $debugMode = $this->settings->get( 'debug_mode' );

        if ( $debugMode !== 'yes') {
            return;
        }

        $other_info = [
            'module_type' => Billplz_FluentCart::class,
            'module_name' => 'Billplz',
        ];

        fluent_cart_add_log( $title, $message, $type, $other_info );
    }

    /**
     * Log success message into Fluent Cart.
     * 
     * @since 1.0.0
     * 
     * @return void
     */
    public function success( string $title, string $message ): void
    {
        $this->log( 'success', $title, $message );
    }

    /**
     * Log error message into Fluent Cart.
     * 
     * @since 1.0.0
     * 
     * @return void
     */
    public function error( string $title, string $message ): void
    {
        $this->log( 'error', $title, $message );
    }

    /**
     * Log warning message into Fluent Cart.
     * 
     * @since 1.0.0
     * 
     * @return void
     */
    public function warning( string $title, string $message ): void
    {
        $this->log( 'warning', $title, $message );
    }

    /**
     * Log info message into Fluent Cart.
     * 
     * @since 1.0.0
     * 
     * @return void
     */
    public function info( string $title, string $message ): void
    {
        $this->log( 'info', $title, $message );
    }
}
