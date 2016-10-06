<h3>Amazon S3 Configuration - Access Keys</h3>

<p>If you don't already have an Amazon S3 account, you can <a href="http://aws.amazon.com/s3" target="_blank">signup here</a>.</p>

<p>This plugin needs your <strong>Amazon AWS Access Keys</strong> to function. You can retrieve these from the <a href="https://console.aws.amazon.com/iam/home?#security_credential" target="_blank">Security Credentials</a> page.</p>

<p>You have the option of uploading your Amazon <strong>AWS Keys CSV file</strong> or entering the keys manually.</p>

<div id="aws-config-container">
	<h4><a href="#">Upload CSV File</a></h4>
	<div id="aws-upload">
		<form method="post" name="wcs3-upload-aws-keys-csv" id="wcs3-upload-aws-keys-csv" enctype="multipart/form-data">
			<table>
				<tbody>
					<tr>
						<td><label for="amazon_aws_csv_file" class="textinput">AWS Keys CSV File:</label></td>
						<td><input type="file" name="amazon_aws_csv_file" id="amazon_aws_csv_file" /></td>
					</tr>
				</tbody>
			</table>

			<?php wp_nonce_field( 'wcs3_upload_csv','wcs3_upload_csv_nonce' ); ?>

			<p class="submit">
				<input name="upload_csv_keys" class="button-primary" type="submit" value="Upload CSV Keys" />
			</p>

		</form>
	</div><!-- #aws-upload -->

	<h4><a href="#">Enter Keys</a></h4>
	<div id="aws-manual">
		<form method="post" name="wcs3-download-aws-settings" id="wcs3-download-aws-settings">
			<table>
				<tbody>
					<tr>
						<td><label for="amazon_aws_access_key" class="textinput">AWS Access Key ID<span class="required">*</span>:</label></td>
						<td><input type="text" name="amazon_aws_access_key" class="required textinput" placeholder="Amazon AWS Access Key" value="" /></td>
					</tr>

					<tr>
						<td><label for="amazon_aws_secret_key" class="textinput">AWS Secret Access Key<span class="required">*</span>:</label></td>
						<td><input type="text" name="amazon_aws_secret_key" class="required textinput" placeholder="Amazon AWS Secret Key" value="" /></td>
					</tr>
				</tbody>
			</table>

			<?php wp_nonce_field( 'wcs3_save_aws_keys','wcs3_save_aws_keys_nonce' ); ?>

			<p class="submit">
				<input name="save_aws_keys" class="button-primary" type="submit" value="Save Keys" />
			</p>
		</form>
	</div><!-- #aws-manual -->
</div><!-- #aws-config-container -->