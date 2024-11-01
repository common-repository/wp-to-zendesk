<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<!-- wordpress to zendesk -->

<style type="text/css">
	#zd_post_sync { cursor: default; }
	#zd_post_sync .wpzd_notice{ border-left:2px solid #0085ba; padding-left: 5px; margin-bottom: 1em; }
	#zd_post_sync .green{ border-color:green; }
	#zd_post_sync .submit{ margin:0; padding:0; text-align: right; margin-top: 1em; }
	#zd_post_sync #wpzd_url{ width: 100%; margin-top: 0.5em; }
</style>

<div>
	<div class='wpzd_notice'><small>The Zendesk article must to be <span style="color:green">"Published"</small></div>
	<div class='wpzd_notice green'><small><?php echo __("May be some latency while updating the post with Zendesk integration, because it's updating in <strong>Zendesk article</strong> too"); ?></small></div>

	<label for="wpzd_url">Article URL in Zendesk</label>
	<input  id="wpzd_url" name="wpzd_url" type="text" value="<?php echo @$wpzd_obj['url'] ?: ''; ?>" />
	<p>
	<div>Article ID: <strong id="wpzd_id"><?php echo @$wpzd_obj['id'] ?: __('Url not setted'); ?></strong></div>
	
	<div><?php submit_button(__('Update')); ?></div>
</div>

<script type="text/javascript">
	(function(){
		// vars
		let wpzd_url = document.getElementById('wpzd_url');
		let wpzd_id  = document.getElementById('wpzd_id' );
		// listener
		wpzd_url.oninput = function(){
			wpzd_id.innerHTML = zd_id_from_url(wpzd_url.value);
		};
		// Same func as in php script
		let zd_id_from_url = function($article_url){
			let regex = /articles\/(\d*)/g;
			let match   = regex.exec($article_url);
			return (match !== null && match[1] !== undefined) ? match[1] : '<?php echo __('No ID founded'); ?>';
		}
	})();
</script>

<!-- - wordpress to zendesk -->
