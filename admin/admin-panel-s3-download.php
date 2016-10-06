<h3>Generate a file download shortcode</h3>
<?php global $nt_wc_s3_download;
$buckets = $nt_wc_s3_download->get_aws_buckets(); ?>

<?php if ( $buckets ) : ?>
	<form method="post" name="wcs3-generate-shortcode" id="wcs3-generate-shortcode">
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><label for="aws-bucket">AWS Bucket:</label></th>
					<td>
						<select name="aws-bucket" id="aws-bucket">
							<option value="">Select AWS Bucket</option>
							<?php foreach ( $buckets as $bucket ) {
								$disabled = '';

								// Disable buckets with capital letters in the name
								if ( nt_wc_s3_string_has_caps( $bucket ) ) {
									$bucket .= " (no caps allowed)";
									$disabled = " disabled='disabled'";
								}
								echo "<option value='" . esc_attr( $bucket ) . "'{$disabled}>{$bucket}</option>";
							} ?>
						</select>
						<img src="<?php echo admin_url( '/images/wpspin_light.gif') ?>" id="ntwcs3-bucket-loading" class="waiting" style="display: none;" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="aws-object">AWS Object:</label></th>
					<td><select name="aws-object" id="aws-object" class="chosen_select" disabled="disabled"></select></td>
				</tr>

				<tr valign="top">
					<th scope="row"><label for="aws-expiry" class="textinput">Expire After (minutes):</label></th>
					<td><input type="text" name="aws-expiry" id="aws-expiry" class="textinput" value="" placeholder="Default: 60" /></td>
				</tr>
			</tbody>
		</table>
		<p class="submit">
			<input name="generate_aws_shortcode" class="button-primary" type="submit" value="Generate Shortcode!" />
		</p>
	</form>
<?php else : ?>
	<h3>No buckets could be found!</h3>
	<p>This error is likely due to either a problem with your access keys or because your S3 account doesn't have any buckets setup.</p>
<?php endif; ?>

<h3>GLOBAL CloudFront Configuration</h3>
<p>This will be the settings used by <strong>all</strong> of your wc_s3_download shortcodes.</p>

<?php
$cf_distros = $nt_wc_s3_download->get_aws_distrubution();
$cf_option_enabled = get_option( 'nt_wc_config_cf_enabled' );
$cf_option_distro  = get_option( 'nt_wc_config_cf_distro' );
$cf_option_acckey  = get_option( 'nt_wc_config_cf_acckey' );
$cf_option_pemfile = get_option( 'nt_wc_config_cf_pemfile' );

?>
<form method="post" name="wcs3-cf-settings" id="wcs3-cf-settings" enctype="multipart/form-data">
	<?php wp_nonce_field( 'wcs3_update_cf_config','wcs3_update_cf_config_nonce' ); ?>

	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row"><label for="wcs3-cf-enabled">Use CloudFront:</label></th>
				<td>
					<select name="wcs3-cf-enabled" id="wcs3-cf-enabled">
						<option value="disabled" <?php echo ( 'disabled' == $cf_option_enabled ? 'selected' : ''); ?>>CloudFront Disabled</option>
						<option value="enabled" <?php echo ( 'enabled' == $cf_option_enabled ? 'selected' : ''); ?>>CloudFront Enabled</option>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="aws-cf-accesskey">CloudFront Access Key ID:</label></th>
				<td>
					<input type="password" name="aws-cf-accesskey" size=35 value="<?php echo $cf_option_acckey; ?>">
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="aws-cf-pemkey">CloudFront Private PEM Key:</label></th>
				<td>
					<?php echo $cf_option_pemfile; ?><input type="file" id="aws-cf-pemkey" name="aws-cf-pemkey" />
				</td>
			</tr>
		</tbody>
	</table>
	<p class="submit">
		<input name="update_cf_settings" class="button-primary" type="submit" value="Update CloudFront Settings" />
	</p>
</form>

<h3>Delete AWS Configuration</h3>
<p>Need to re-enter your AWS Access Keys? Delete your existing keys below!</p>
<form method="post" name="wcs3-delete-aws-keys" id="wcs3-delete-aws-keys">
	<?php wp_nonce_field( 'wcs3_delete_aws_keys','wcs3_delete_aws_keys_nonce' ); ?>

	<p class="submit">
		<input name="delete_aws_keys" class="button-primary" type="submit" value="Delete Keys" />
	</p>
</form>
