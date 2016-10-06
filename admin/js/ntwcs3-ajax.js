jQuery(document).ready(function($) {
	$('#aws-bucket').change(function() {

		if ( $(this).attr('value') != '' ) {
			$('#ntwcs3-bucket-loading').show();
			$('#aws-bucket').attr('disabled', 'disabled');

			data = {
				action: 'ntwcs3_get_bucket_contents',
				ntwcs3_nonce: ntwcs3_vars.ntwcs3_nonce,
				aws_bucket: $(this).attr('value')
			};

			$.post(ajaxurl, data, function(response) {
				$('#aws-object').html(response).removeAttr('disabled');
				$('#ntwcs3-bucket-loading').hide();
				$('#aws-bucket').removeAttr('disabled');
			});

		}
		return false;
	});

	$('#wcs3-generate-shortcode').submit(function() {
		data = {
			action: 'ntwcs3_generate_shortcode',
			ntwcs3_nonce: ntwcs3_vars.ntwcs3_nonce,
			aws_bucket: $('#aws-bucket').attr('value'),
			aws_object: $('#aws-object').attr('value'),
			aws_expiry: $('#aws-expiry').attr('value')
		};

		$.post(ajaxurl, data, function(response) {
			if ( ! $('#generated-shortcode').length ) {
				$('#wcs3-generate-shortcode').after( '<div id="generated-shortcode"></div><button class="btn" data-clipboard-shortcode data-clipboard-target="#generated-shortcode" style="margin-top:3px;">Copy To Clipboard</button>' );
			}

			$('#generated-shortcode').html( response );
			$('[data-clipboard-shortcode]').html("Copy To Clipboard");

		});

		return false;
	});
});
