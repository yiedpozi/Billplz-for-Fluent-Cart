<?php
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Admin class.
 * 
 * @since 1.0.0
 */
class Billplz_FluentCart_Admin {
    /**
     * Constructor.
     * 
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'plugin_action_links_' . BILLPLZ_FLUENTCART_BASENAME, [ $this, 'register_settings_link' ] );
    }

    /**
     * Register plugin settings link.
     * 
     * @since 1.0.0
     * 
     * @param array $links
     * @return array
     */
    public function register_settings_link( $links ) {
        $url = admin_url( 'admin.php?page=fluent-cart#/settings/payments/billplz' );
        $label = esc_html__( 'Settings', 'billplz-for-fluent-cart' );

        $settings_link = '<a href="' . esc_url( $url ) . '">' . $label . '</a>';
        array_unshift( $links, $settings_link );

        return $links;
    }
}

new Billplz_FluentCart_Admin();
