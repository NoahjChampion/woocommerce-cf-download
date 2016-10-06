<?php
// Don't allow direct loading of the file.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Add the settings page to the WC menu.
 * Users must have woocommerce management capabilities ('manage_woocommerce')
 */
function nt_wc_s3_admin_settings_menu() {
	global $nt_wc_s3_menu_page;
	$nt_wc_s3_menu_page = add_submenu_page( 'woocommerce', __( 'WooCommerce S3 Downloads', 'ntwcs3' ), __( 'S3 Downloads', 'ntwcs3' ), 'manage_woocommerce', 'wc-amazon-s3-downloads', 'nt_wc_s3_admin_settings_panel' );
}
add_action( 'admin_menu', 'nt_wc_s3_admin_settings_menu' );

/**
 * Add a link to the plugins page to the plugin documentation
 */
function nt_wc_s3_admin_documentation_link( $links, $file ) {
	if ( $file == plugin_basename( NT_WC_S3_PLUGIN_PATH . 'woocommerce-s3-download.php' ) ) {
		$links[] = '<a href="' . esc_url( NT_WC_S3_PLUGIN_URL . 'documentation/index.html' ) . '" target="_blank">' . __( 'Documentation' ) . '</a>';
	}

	return $links;
}
add_filter( 'plugin_action_links', 'nt_wc_s3_admin_documentation_link', 10, 2 );

/**
 * Output the admin panel. If AWS isn't already configured then display AWS config panel.
 * Else display the regular panel.
 *
 * @global object|bool $nt_wc_s3_download S3 Download Object
 * @return void
 */
function nt_wc_s3_admin_settings_panel() {
	global $nt_wc_s3_download;

	if ( ! $nt_wc_s3_download ) return;

	$message = false;

	if ( ! empty( $_FILES ) && isset( $_POST['upload_csv_keys'] ) ) {
		$message = $nt_wc_s3_download->upload_amazon_configuration();
	} elseif ( ! empty( $_POST ) && isset( $_POST['save_aws_keys'] ) ) {
		$message = $nt_wc_s3_download->enter_amazon_configuration();
	} elseif ( ! empty( $_POST ) && isset( $_POST['delete_aws_keys'] ) ) {
		$message = $nt_wc_s3_download->delete_amazon_configuration();
	} elseif ( ! empty( $_POST ) && isset( $_POST['update_cf_settings'] ) ) {
		$message = $nt_wc_s3_download->update_cloudfront_configuration();
	} ?>

	<div class="wrap">
		<h2><?php _e( 'WooCommerce S3 Downloads', 'ntwcs3' ); ?></h2>

		<?php if ( $message ) { ?>
			<div id="message" class="updated">
				<p><strong><?php echo esc_html( $message ); ?></strong></p>
			</div>
		<?php } ?>

		<?php if ( $nt_wc_s3_download->is_aws_configured ) {
			include( NT_WC_S3_PLUGIN_PATH . 'admin/admin-panel-s3-download.php' );
		} else {
			include( NT_WC_S3_PLUGIN_PATH . 'admin/admin-panel-amazon-config.php' );
		} ?>

	</div>

	<?php
}

/**
 * Load additional scripts when on the plugin's admin page.
 *
 * @param string $hook Current page.
 * @return void
 */
function nt_wc_s3_admin_enqueue_scripts( $hook ) {
	global $nt_wc_s3_menu_page;

	// Return if not on the plugin admin page
	if ( $hook != $nt_wc_s3_menu_page ) return;

	wp_enqueue_script( 'jquery-ui-accordion' );
	wp_enqueue_script( 'ntwcs3-admin', NT_WC_S3_PLUGIN_URL . 'admin/js/ntwcs3-admin.js', array( 'jquery', 'jquery-ui-accordion' ) );

	wp_enqueue_script( 'ntwcs3-ajax', NT_WC_S3_PLUGIN_URL . 'admin/js/ntwcs3-ajax.js', array( 'jquery' ) );
	wp_localize_script( 'ntwcs3-ajax', 'ntwcs3_vars', array( 'ntwcs3_nonce' => wp_create_nonce( 'ntwcs3_nonce' ) ) );

	wp_enqueue_style( 'ntwcs3-admin', NT_WC_S3_PLUGIN_URL . 'admin/css/ntwcs3-admin.css' );

	wp_enqueue_script( 'ntwcs3-clipboard', NT_WC_S3_PLUGIN_URL . 'vendor/clipboard/clipboard.min.js' );
	wp_enqueue_script( 'ntwcs3-highlight', NT_WC_S3_PLUGIN_URL . 'vendor/clipboard/highlight.pack.min.js' );
	wp_enqueue_script( 'ntwcs3-tooltip', NT_WC_S3_PLUGIN_URL . 'vendor/clipboard/tooltips.js', array( 'ntwcs3-highlight' ) );

}
add_action( 'admin_enqueue_scripts', 'nt_wc_s3_admin_enqueue_scripts' );

/**
 * AJAX function to get the contents of the bucket selected in dropdown.
 */
function nt_wc_s3_ajax_get_bucket_contents() {
	// Verify nonce
	if ( ! isset( $_POST['ntwcs3_nonce'] ) || ! wp_verify_nonce( $_POST['ntwcs3_nonce'], 'ntwcs3_nonce' ) ) {
		die( '<strong>Permission denied!</strong>' );
	}

	global $nt_wc_s3_download;

	// Get the bucket
	$bucket = ( isset( $_POST['aws_bucket'] ) && ! empty( $_POST['aws_bucket'] ) ) ? esc_attr( $_POST['aws_bucket'] ) : false;

	// If the bucket is set then get the content of that bucket
	if ( $bucket ) {
		$bucket_contents = $nt_wc_s3_download->get_aws_bucket_files( $bucket );

		// If the bucket contents were found then output in option tags.
		if ( $bucket_contents ) {
			$files_html = '';

			foreach ( $bucket_contents as $file ) {
				$disabled = '';

				if ( 0 == $file['Size'] ) {
					continue;
				}

				if ( nt_wc_s3_string_has_spaces( $file['Key'] ) ) {
					$file['Key'] .= ' (no spaces allowed)';
					$disabled = " disabled='disabled'";
				}

				$files_html .= "<option value='" . $file['Key'] . "'{$disabled}>{$file['Key']}</option>";
			}

			echo $files_html;
		}
	}

	// Die
	die();
}
add_action( 'wp_ajax_ntwcs3_get_bucket_contents', 'nt_wc_s3_ajax_get_bucket_contents' );

/**
 * AJAX function to generate the shortcode.
 */
function nt_wc_s3_ajax_generate_shortcode() {
	// Verify nonce
	if ( ! isset( $_POST['ntwcs3_nonce'] ) || ! wp_verify_nonce( $_POST['ntwcs3_nonce'], 'ntwcs3_nonce' ) ) {
		die( '<strong>Permission denied!</strong>' );
	}

	global $nt_wc_s3_download;

	// Get the bucket
	$bucket = ( isset( $_POST['aws_bucket'] ) && ! empty( $_POST['aws_bucket'] ) ) ? esc_attr( $_POST['aws_bucket'] ) : false;

	// Get the object
	$object = ( isset( $_POST['aws_object'] ) && ! empty( $_POST['aws_object'] ) ) ? esc_attr( $_POST['aws_object'] ) : false;

	// Get the expiry
	$expiry = ( isset( $_POST['aws_expiry'] ) && ! empty( $_POST['aws_expiry'] ) ) ? esc_attr( $_POST['aws_expiry'] ) : false;

	// Trigger an error if bucket isn't set
	if ( ! $bucket ) {
		die( '<strong>Bucket must be set!</strong>' );
	}

	// Trigger an error if object isn't set
	if ( ! $object ) {
		die( '<strong>Object must be set!</strong>' );
	}

	// Expiry is optional. If it's set then create a string to add to shortcode.
	$expiry_string = ( $expiry ) ? ' expiry="' . $expiry . '"' : '';

	// Construct the shortcode
	$shortcode = '[wc_s3_download bucket="' . $bucket . '" path="' . $object . '"' . $expiry_string . ']';

	// Prime out distrubtion if necessary
	try {
		$nt_wc_s3_download->generate_download_url( $bucket, $object, $expiry_string );
	}
	catch ( Exception $e ) {

	}

	// Output shortcode
	echo '<code>' . $shortcode . '</code>';

	//Die
	die();
}
add_action( 'wp_ajax_ntwcs3_generate_shortcode', 'nt_wc_s3_ajax_generate_shortcode' );

/**
 * Check if a string contains any uppercase chars
 *
 * @param string $string String to test
 * @return boolean True if string contains any uppercase chars
 */
function nt_wc_s3_string_has_caps( $string ) {
	return (bool) preg_match( '/[A-Z]/', $string );
}

/**
 * Check if a string contains any spaces
 *
 * @param string $string String to test
 * @return boolean True if string contains any spaces
 */
function nt_wc_s3_string_has_spaces( $string ) {
	return (bool) preg_match( '/ /', $string );
}
