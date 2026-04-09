<?php
/**
 * Plugin Name: Billplz for FluentCart
 * Plugin URI: https://wordpress.org/plugins/billplz-for-fluent-cart/
 * Description: Billplz payment integration for FluentCart.
 * Version: 1.0.0
 * Requires at least: 4.6
 * Requires PHP: 7.0
 * Author: Billplz Sdn Bhd
 * Author URI: https://www.billplz.com/
 * Text Domain: billplz-for-fluent-cart
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Requires Plugins: fluent-cart
 */

if ( !defined( 'ABSPATH' ) ) exit;

if ( !defined( 'BILLPLZ_FLUENTCART_FILE' ) ) {
    define( 'BILLPLZ_FLUENTCART_FILE', __FILE__ );
}

if ( !defined( 'BILLPLZ_FLUENTCART_VERSION' ) ) {
    define( 'BILLPLZ_FLUENTCART_VERSION', '1.0.0' );
}

// Plugin core class
if ( !class_exists( 'Billplz_FluentCart' ) ) {
    require_once plugin_dir_path( BILLPLZ_FLUENTCART_FILE ) . 'includes/class-billplz-fluent-cart.php';
}
