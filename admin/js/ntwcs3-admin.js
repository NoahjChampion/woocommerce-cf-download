jQuery(document).ready(function($) {
	$( "#aws-config-container" ).accordion({
      collapsible: true
    });

var clipboardShortcode = new Clipboard('[data-clipboard-shortcode]');
clipboardShortcode.on('success',function(e){e.clearSelection();
	$('[data-clipboard-shortcode]').html("Copied!");
	showTooltip(e.trigger,'Copied!');
});

clipboardShortcode.on('error',function(e){console.error('Action:',e.action);
	console.error('Trigger:',e.trigger);
	showTooltip(e.trigger,fallbackMessage(e.action));
});

});
