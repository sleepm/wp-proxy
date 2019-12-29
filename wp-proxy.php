<?php
/**
 * Plugin Name: WP Proxy
 * Plugin URI: https://xn--vkuk.org/blog/wp-proxy
 * Description: manage proxy for WordPress
 * Version: 1.3.4
 * Author: sleepm
 * Text Domain: wp-proxy
 * Domain Path: /languages
 * License: GPLv2 or later
 *
 * @package WP_Proxy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
define( 'WP_PROXY_PLUGIN_NAME', basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ) );
require_once 'class-wp-proxy.php';

/**
 * The single instance of the class
 */
function wp_proxy() {
	return WP_Proxy::instance();
}

$GLOBALS['wp_proxy'] = wp_proxy();
