<?php
use Aws\Common\Aws;


// Don't allow direct loading of the file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NT_WC_S3_Download {

	private $option     = 'nt_wc_config';
	private $cf_enabled = 'nt_wc_config_cf_enabled';
	private $cf_distro  = 'nt_wc_config_cf_distro';
	private $cf_acckey  = 'nt_wc_config_cf_acckey';
	private $cf_pemfile = 'nt_wc_config_cf_pemfile';
	private $cf_oaiid   = 'nt_wc_config_cf_oaiid';
	private $cf_url     = 'nt_wc_config_cf_url';

	private $configuration;

	private $awsAccessKey;
	private $awsSecretKey;

	private $client;
	private $s3client;
	private $cfclient;

	private $s3;

	public $is_aws_configured = false;

	public function __construct() {
		$this->init();

		// Add the shortcode for Amazon S3 URLs
		add_shortcode( 'wc_s3_download', array( $this, 'download_file_shortcode' ) );

		// Run the download path through do_shortcode
		add_filter( 'woocommerce_file_download_paths', array( $this, 'download_file_path_filter' ) );
		add_filter( 'woocommerce_product_file_download_path', array( $this, 'download_file_path_filter' ) );

		// Save file paths again to avoid escaping quotes inside shortcode.
		add_action( 'woocommerce_process_product_meta', array( $this, 'fix_download_path_shortcodes' ), 30, 2 );

		add_filter( 'upload_mimes', array( $this, 'allow_pem_upload' ) );

	}

	private function init() {
		$this->setup_config();
		$this->instantiate_s3();
	}

	private function setup_config() {
		$this->configuration = $config = get_option( $this->option, false );
		$this->awsAccessKey = isset( $config['awsAccessKey'] ) ? $config['awsAccessKey'] : false;
		$this->awsSecretKey = isset( $config['awsSecretKey'] ) ? $config['awsSecretKey'] : false;

		$this->is_aws_configured = ( $this->awsAccessKey && $this->awsSecretKey ) ? true : false;
	}

	private function instantiate_s3() {

		$args = array(
			'key'    => $this->awsAccessKey,
			'secret' => $this->awsSecretKey,
		);

		$this->client = Aws::factory( $args );

		$this->s3client = $this->client->get( 's3', array() );

		//This comes from key pair you generated for cloudfront
		$cf_acckey = get_option( $this->cf_acckey );
		$cf_pemfile = get_option( $this->cf_pemfile );

		$this->cfclient = $this->client->get( 'CloudFront',
			array(
				'private_key' => $cf_pemfile,
				'key_pair_id' => $cf_acckey,
			) );

	}

	public function get_aws_buckets() {

		try {
			$result = $this->s3client->listBuckets();
		}
		catch ( Exception $e ) {
			return new WP_Error( 'exception', $e->getMessage() );
		}

		$buckets = array();
		foreach ( $result['Buckets'] as $bucket ) {
			$buckets[] = $bucket['Name'];
		}

		return $buckets;

	}

	public function get_aws_bucket_files( $bucket ) {

		try {
			$result = $this->s3client->listObjects( array( 'Bucket' => $bucket ) );
		}
		catch ( Exception $e ) {
			return new WP_Error( 'exception', $e->getMessage() );
		}

		return $result['Contents'];

	}


	public function get_aws_distrubution() {

		try {
			$result = $this->cfclient->listDistributions();
		}
		catch ( Exception $e ) {
			return new WP_Error( 'exception', $e->getMessage() );
		}

		$distros = array();
		foreach ( $result['Items'] as $distro ) {
			$distros[] = $distro['Id'];
		}

		return $distros;

	}


	/**
	 * Create an Amazon S3 download URL.
	 *
	 * @param array $atts Shortcode attributes
	 * @return string File URL
	 */
	public function download_file_shortcode( $atts ) {

		extract( shortcode_atts( array(
			'bucket' => false,
			'path'   => false,
			'expiry' => 60,
		), $atts ) );

		if ( ! $bucket || ! $path ) {
			return '';
		}

		return $this->generate_download_url( $bucket, $path, $expiry );
	}

	/**
	 * Run the file path through do_shortcode
	 *
	 * @param string $path Downloadable file path
	 * @return string do_shortcode processed downloadable file path
	 */
	public function download_file_path_filter( $path ) {
		return do_shortcode( $path );
	}

	/**
	 * Generate the S3 download URL
	 * Based on http://css-tricks.com/snippets/php/generate-expiring-amazon-s3-link/
	 *
	 * @param string $bucket Amazon S3 Bucket
	 * @param string $path Amazon S3 Path
	 * @param bool|int $expiry Minutes until link expires
	 */
	public function generate_download_url( $bucket, $path, $expiry = 60 ) {

		// Convert expiry (minutes) into timestamp
		$expiry_timestamp = time() + absint( $expiry * 60 );

		// Fix the path; encode and sanitize
		$path = str_replace( '%2F', '/', rawurlencode( $path = ltrim( $path, '/' ) ) );

		// CloudFront URL
		$cf_option_enabled = get_option( 'nt_wc_config_cf_enabled' );

		if ( 'enabled' == $cf_option_enabled ) {
			$cf_origin_settings = $this->get_cf_distro( $bucket );
			if ( false !== $cf_origin_settings ) {
				$cfurl = $this->generate_cloudfront_signed_url( $path, $bucket, $cf_origin_settings, $expiry_timestamp );
				return $cfurl;
			}
		}

		// Path for signature starts with the bucket
		$signpath = '/'. $bucket .'/'. $path;

		// S3 friendly string to sign
		$signsz = implode( "\n", $pieces = array( 'GET', null, null, $expiry_timestamp, $signpath ) );

		// Calculate the hash
		$signature = $this->hmac_SHA1_encrypt( $this->awsSecretKey, $signsz );

		// Glue the URL
		$url = sprintf( 'http://%s.s3.amazonaws.com/%s', $bucket, $path );

		// ... to the query string ...
		$qs = http_build_query( $pieces = array(
			'AWSAccessKeyId' => $this->awsAccessKey,
			'Expires'        => $expiry_timestamp,
			'Signature'      => $signature,
		) );

		// ... and return the URL!
		return $url . '?' . $qs;
	}

	private function hmac_SHA1_encrypt( $key, $data, $blocksize = 64 ) {
		if ( strlen( $key ) > $blocksize ) $key = pack( 'H*', sha1( $key ) );

        $key = str_pad( $key, $blocksize, chr( 0x00 ) );
        $ipad = str_repeat( chr( 0x36 ), $blocksize );
        $opad = str_repeat( chr( 0x5c ), $blocksize );
        $hmac = pack( 'H*', sha1(
        ( $key ^ $opad ) . pack( 'H*', sha1(
          ( $key ^ $ipad ) . $data
        ) )
		) );

        return base64_encode( $hmac );
	}

	/**
	 * Process the upload of a CSV file to configure AWS access keys
	 *
	 * @return string Message
	 */
	public function upload_amazon_configuration() {
		// Verify nonce
		if ( ! empty( $_POST ) && check_admin_referer( 'wcs3_upload_csv','wcs3_upload_csv_nonce' ) ) {
			// Check a file has been uploaded
			if ( empty( $_FILES['amazon_aws_csv_file']['tmp_name'] ) ) {
				return __( 'No file uploaded', 'ntwcs3' );
			}

			// Run the file through the CSV class
			$csv = new NT_WC_S3_CSV;

			// If keys were processed successfully then save
			if ( ! empty( $csv->access_key ) && ! empty( $csv->secret_key ) ) {
				return $this->save_amazon_configuration( $csv->access_key, $csv->secret_key );
			} else {
				return __( 'There has been an error with the file you uploaded!', 'ntwcs3' );
			}
		}
	}

	/**
	 * Manually set AWS Access Keys
	 *
	 * @return string Message
	 */
	public function enter_amazon_configuration() {
		// Verify nonce
		if ( ! empty( $_POST ) && check_admin_referer( 'wcs3_save_aws_keys','wcs3_save_aws_keys_nonce' ) ) {
			if ( empty( $_POST['amazon_aws_access_key'] ) || empty( $_POST['amazon_aws_secret_key'] ) ) {
				return __( 'A value must be set for both the Access Key ID and the Secret Access Key!', 'ntwcs3' );
			}

			return $this->save_amazon_configuration( $_POST['amazon_aws_access_key'], $_POST['amazon_aws_secret_key'] );
		}
	}


	/**
	 * Save AWS configuration
	 */
	private function save_amazon_configuration( $access_key, $secret_key ) {
			// If existing configuration is set then modify else create new.
			if ( is_array( $this->configuration ) ) {
				$config = $this->configuration;
			} else {
				$config = array();
			}

			$config['awsAccessKey'] = $access_key;
			$config['awsSecretKey'] = $secret_key;

			update_option( $this->option, $config );

			//Re-run init method
			$this->init();

			return __( 'Your AWS configuration has been saved successfully!', 'ntwcs3' );
	}

	/**
	 * Delete the existing AWS configuration.
	 *
	 * @return boolean Configuration deleted successfully
	 */
	public function delete_amazon_configuration() {
		// Verify nonce
		if ( ! empty( $_POST ) && check_admin_referer( 'wcs3_delete_aws_keys','wcs3_delete_aws_keys_nonce' ) ) {
			// If the plugin isn't configured there's no config to delete.
			if ( ! $this->is_aws_configured ) return false;

			// Unset existing keys
			unset( $this->configuration['awsAccessKey'] );
			unset( $this->configuration['awsSecretKey'] );

			// Update the option with the latest config
			update_option( $this->option, $this->configuration );

			// Re-run init method
			$this->init();

			return __( 'Your AWS configuration has been deleted!', 'ntwcs3' );
		}
	}

	/**
	 * Update the CloudFront configuration.
	 *
	 * @return boolean Configuration deleted successfully
	 */
	public function update_cloudfront_configuration() {
		// Verify nonce
		if ( ! empty( $_POST ) && check_admin_referer( 'wcs3_update_cf_config','wcs3_update_cf_config_nonce' ) ) {

			if ( ! empty( $_POST['wcs3-cf-enabled'] ) ) {
				update_option( $this->cf_enabled, esc_sql( $_POST['wcs3-cf-enabled'] ) );
			}
			if ( ! empty( $_POST['aws-cf-accesskey'] ) ) {
				update_option( $this->cf_acckey, esc_sql( $_POST['aws-cf-accesskey'] ) );
			}

			if ( ! empty( $_FILES ) && isset( $_FILES['aws-cf-pemkey'] ) ) {
				$file_upload = wp_handle_upload( $_FILES['aws-cf-pemkey'], array( 'test_form' => false ) );
				update_option( $this->cf_pemfile, esc_sql( $file_upload['file'] ) );
			}

			return __( 'Your CloudFront settings have been updated!', 'ntwcs3' );
		}

	}

	/**
	 * get the distribution ID / domain for the user
	 *
	 */
	public function get_cf_distro( $bucket ) {

		$budget_domain = $bucket . '.s3.amazonaws.com';

		$cf_origin_distros = get_option( 'wcs3_cf_distros');

		$cf_distro = '';

		if ( is_array( $cf_origin_distros ) ) {
			foreach ( $cf_origin_distros as $domain => $distro ) {
				if ( $domain == $budget_domain ) {
					$cf_distro = $distro;
				}
			}
		}

		if ( '' == $cf_distro ) {
			$cf_distro = $this->add_cf_distro( $bucket, $cf_origin_distros );
		}

		if ( is_wp_error( $cf_distro ) ) {
			return false;
		}


		try {
			$result = $this->cfclient->getDistribution( array( 'Id' => $cf_distro ) );
		}
		catch ( Exception $e ) {
			return false;
		}

		if ( $result['Status'] != 'Deployed' ) {
			return false;
		}

		$cf_origin_settings = get_option( 'wcs3_cf_' . $cf_distro );

		return $cf_origin_settings;

	}


	/**
	 * Check if our s3 origin is already present. If not, add it
	 *
	 */
	public function add_cf_distro( $bucket, $cf_origin_distros ) {

		$budget_domain = $bucket . '.s3.amazonaws.com';

		// Get the CF Access ID
		$cf_access_info = get_option( $this->cf_oaiid );

		// If the OAD is empty, generate
		if ( false == $cf_access_info || empty( $cf_access_info ) ) {

			try {
				$result = $this->cfclient->createCloudFrontOriginAccessIdentity(array(
					'CallerReference' => 'wcs3_cf',
					'Comment'         => 'OAI used for private distribution access for WCS3 plugin.',
				));
			}
			catch ( Exception $e ) {
				return new WP_Error( 'exception', $e->getMessage() );
			}

			if ( isset( $result['S3CanonicalUserId'] ) && ! empty( $result['S3CanonicalUserId'] ) ) {
				update_option( $this->cf_oaiid, array( 'Id' => $result['Id'], 'S3CanonicalUserId' => esc_sql( $result['S3CanonicalUserId'] ) ) );
				$cf_access_info = array( 'Id' => esc_sql( $result['Id'] ), 'S3CanonicalUserId' => esc_sql( $result['S3CanonicalUserId'] ) );
			}

		}

		$new_setup = array(
				'CallerReference' => $bucket,
				'Aliases' => array(
					'Quantity' => 0,
				),
				'DefaultRootObject' => '',
				'Origins' => array(
					'Quantity' => 1,
					'Items' => array(
						array(
							'Id' => 'S3-' . $bucket,
							'DomainName' => $bucket . '.s3.amazonaws.com',
							'OriginPath' => '',
							'S3OriginConfig' => array(
								'OriginAccessIdentity' => 'origin-access-identity/cloudfront/' . $cf_access_info['Id'],
							),
						),
					),
				),
				'DefaultCacheBehavior' => array(
					'TargetOriginId' => 'S3-' . $bucket,
					'ForwardedValues' => array(
						'QueryString' => false,
						'Cookies' => array(
							'Forward' => 'none',
							'WhitelistedNames' => array(
								'Quantity' => 0,
							),
						),
						'Headers' => array(
							'Quantity' => 0,
						),
					),
					'TrustedSigners' => array(
						'Enabled' => true,
						'Quantity' => 1,
						'Items' => array( 'self' ),
					),
					'ViewerProtocolPolicy' => 'allow-all',
					'MinTTL' => 0,
					'AllowedMethods' => array(
						'Quantity' => 2,
						'Items' => array( 'HEAD', 'GET' ),
						'CachedMethods' => array(
							'Quantity' => 2,
							'Items' => array( 'HEAD', 'GET' ),
						),
					),
					'SmoothStreaming' => false,
					'DefaultTTL' => 86400,
					'MaxTTL' => 31536000,
				),
				'CacheBehaviors' => array(
					'Quantity' => 0,
				),
				'CustomErrorResponses' => array(
					'Quantity' => 0,
				),
				// Comment is required
				'Comment' => 'Caching for s3 bucket ' . $bucket,
				'Logging' => array(
					'Enabled' => false,
					'IncludeCookies' => false,
					'Bucket' => '',
					'Prefix' => '',
				),
				'PriceClass' => 'PriceClass_All',
				'Enabled' => true,
				'ViewerCertificate' => array(
					'CloudFrontDefaultCertificate' => true,
					'MinimumProtocolVersion' => 'SSLv3',
				),
				'Restrictions' => array(
					'GeoRestriction' => array(
						'RestrictionType' => 'none',
						'Quantity' => 0,
					),
				),
		);

		// Create a new distro
		try {
			$result = $this->cfclient->createDistribution( $new_setup );
		}
		catch ( Exception $e ) {
			return new WP_Error( 'exception', $e->getMessage() );
		}

		$distro_id = $result['Id'];

		$bucket_distro = ( is_array( $cf_origin_distros ) ? array_merge( $cf_origin_distros, array( $budget_domain => $distro_id ) ) : array( $budget_domain => $distro_id ) );

		update_option( 'wcs3_cf_distros', $bucket_distro );
		update_option( 'wcs3_cf_' . $distro_id, array( 'DomainName' => $result['DomainName'] ) );

		return $distro_id;

	}

	/**
	 * Generate the signed url in CloudFront
	 */
	public function set_bucket_policy( $bucket ) {

		// Get the CF Access ID
		$cf_access_info = get_option( $this->cf_oaiid );

		// Manage the policy for the s3 bucket
		$bucket_policy = array(
			'Bucket' => $bucket,
			'Policy' => json_encode(array(
				'Statement' => array(
					array(
						'Sid' => 's3-to-cloudflare',
						'Action' => array(
							's3:GetObject',
						),
						'Effect' => 'Allow',
						'Resource' => "arn:aws:s3:::{$bucket}/*",
						'Principal' => array(
							'CanonicalUser' => "{$cf_access_info['S3CanonicalUserId']}",
						)
					)
				)
			))
		);

		// Set the policy for the s3 bucket
		try {
			$set_policy = $this->s3client->putBucketPolicy(array(
				'Bucket' => $bucket,
				'Policy' => json_encode(array(
					'Statement' => array(
						array(
							'Sid' => 's3-to-cloudflare',
							'Action' => array(
								's3:GetObject',
							),
							'Effect' => 'Allow',
							'Resource' => "arn:aws:s3:::{$bucket}/*",
							'Principal' => array(
								'CanonicalUser' => array(
									"{$cf_access_info['S3CanonicalUserId']}",
								)
							)
						)
					)
				))
			));
		}
		catch ( Exception $e ) {
			return new WP_Error( 'exception', $e->getMessage() );
		}

	}

	/**
	 * Generate the signed url in CloudFront
	 */
	public function generate_cloudfront_signed_url( $resource, $bucket, $cf_origin_settings, $expiry_timestamp ) {

		$this->set_bucket_policy( $bucket );

		// Get the CF url
		$cf_url = get_option( $this->cf_url );

		//Read Cloudfront Private Key Pair
		$cf_pemfile = get_option( $this->cf_pemfile );
		$cf_acckey = get_option( $this->cf_acckey );

		$url = 'http://' . $cf_origin_settings['DomainName'] . '/' . $resource;

		$signed_url = $this->cfclient->getSignedUrl(array(
			'url'         => $url,
			'expires'     => $expiry_timestamp,
		));

		return $signed_url;

	}

	/**
	 * Add the .pem extension for admins so that we can upload the aws private key
	 */
	function allow_pem_upload( $existing_mimes ) {

		if ( is_admin() && current_user_can( 'administrator' ) ) {
			$existing_mimes['pem'] = 'application/octet-stream';
		}

		return $existing_mimes;
	}


	/**
	 * When download paths are set they are passed through esc_attr which will
	 * convert single and double quotes breaking the shortcode. This method will
	 * fix shortcodes and re-save the download paths.
	 *
	 * @param int $post_id ID of the product being saved
	 */
	public function fix_download_path_shortcodes( $post_id ) {

		if ( isset( $_POST['_file_paths'] ) ) {

			// Don't escape file paths so early
			$_file_paths = array();
			$file_paths = str_replace( "\r\n", "\n", $_POST['_file_paths']);
			$file_paths = trim( preg_replace( "/\n+/", "\n", $file_paths ) );

			if ( $file_paths ) {
				$file_paths = explode( "\n", $file_paths );

				foreach ( $file_paths as $file_path ) {
					$file_path = trim( $file_path );

					/**
					 * Check if the file path contains the shortcode.
					 *
					 * Only wc_s3_download shortcode is allowed.
					 * If the shortcode is found then instead of esc_attr we'll
					 * remove all but the shortcode from the string.
					 */
					if ( has_shortcode( $file_path, 'wc_s3_download' ) ) {
						$matches = array();
						preg_match_all( '/' . get_shortcode_regex() . '/s', $file_path, $matches );
						$file_path = implode( '', $matches[0] );
					} else {
						$file_path = esc_attr( $file_path );
					}

					$_file_paths[ md5( $file_path ) ] = $file_path;
				}
			}

			// grant permission to any newly added files on any existing orders for this product
			do_action( 'woocommerce_process_product_file_download_paths', $post_id, 0, $_file_paths );

			update_post_meta( $post_id, '_file_paths', $_file_paths );
		} elseif ( isset( $_POST['_wc_file_urls'] ) ) {
			// file paths will be stored in an array keyed off md5(file path)
			$files = array();

			$file_names    = isset( $_POST['_wc_file_names'] ) ? array_map( 'wc_clean', $_POST['_wc_file_names'] ) : array();
			$file_urls     = isset( $_POST['_wc_file_urls'] ) ? $_POST['_wc_file_urls'] : array();

			if ( ! empty( $file_urls ) ) {
				foreach ( $file_urls as $file_key => $file_url ) {
					$url = trim( $file_url );

					/**
					 * Check if the URL contains the shortcode.
					 *
					 * Only wc_s3_download shortcode is allowed.
					 * If the shortcode is found then instead of esc_url_raw we'll
					 * remove all but the shortcode from the string.
					 */
					if ( has_shortcode( $url, 'wc_s3_download' ) ) {
						$matches = array();
						preg_match_all( '/' . get_shortcode_regex() . '/s', $url, $matches );
						$file_urls[ $file_key ] = implode( '', $matches[0] );
					} else {
						$file_urls[ $file_key ] = esc_url_raw( $url );
					}
				}

				$file_url_size = sizeof( $file_urls );

				for ( $i = 0; $i < $file_url_size; $i ++ ) {
					if ( ! empty( $file_urls[ $i ] ) ) {
						$files[ md5( $file_urls[ $i ] ) ] = array(
							'name' => $file_names[ $i ],
							'file' => $file_urls[ $i ]
						);
					}
				}
			}

			// grant permission to any newly added files on any existing orders for this product prior to saving
			do_action( 'woocommerce_process_product_file_download_paths', $post_id, 0, $files );

			update_post_meta( $post_id, '_downloadable_files', $files );
		}
	}
}
