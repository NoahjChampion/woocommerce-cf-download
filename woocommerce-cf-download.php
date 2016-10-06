<?php
/**
 * Plugin Name: WooCommerce Amazon CloudFront Downloads
 * Description: A plugin enabling you to serve digital downloads via Amazon S3
 * Version: 1.5.1
 * Author: NuclearThemes
 * Author URI: http://codecanyon.net/user/NuclearThemes
 */

// Don't allow direct loading of the file.
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'NT_WC_S3_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'NT_WC_S3_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// Amazon S3 Class
require_once( NT_WC_S3_PLUGIN_PATH . '/vendor/aws/aws-autoloader.php' );


// S3 CSV Class
require_once( NT_WC_S3_PLUGIN_PATH . 'class-nt-wc-s3-csv.php' );

// S3 Download Class
require_once( NT_WC_S3_PLUGIN_PATH . 'class-nt-wc-s3-download.php' );

// Initialize admin
if ( is_admin() ) {
	require_once( NT_WC_S3_PLUGIN_PATH . 'admin/admin-init.php' );
}

// If has_shortcode doesn't exist then user is running under 3.6. Add the function.
if ( ! function_exists( 'has_shortcode' ) ) {
	require_once( NT_WC_S3_PLUGIN_PATH . 'plugin-compatibility.php' );
}

global $nt_wc_s3_download;
// Instantiate the class if WooCommerce is active.
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	$nt_wc_s3_download = new NT_WC_S3_Download;
} else {
	$nt_wc_s3_download = false;
}
