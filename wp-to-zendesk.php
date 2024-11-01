<?php
/**
 * @package wp-to-zendesk
 * @version 0.1
 */
/*
Plugin Name: Wordpress Page to Zendesk Article
Description: Wordpress Page/Post to Zendesk Article Synchronization Plugin
Author: Vitaly Uvarov
Version: 0.1
Author URI: http://vitalyu.ru/
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class wp_to_zendesk {

	// CREDENTIALS
	private $ZDURL;
	private $ZDUSER;
	private $ZDAPIKEY;
	//---


	function __construct() {

		# on plugin activate
		//register_activation_hook(__FILE__, function(){ ... });

		# on plugin deactivate
		//register_deactivation_hook(__FILE__, function(){ ... });

		$this->load_options_settings();

		# On edit Page action
		add_action('post_edit_form_tag',function(){
			# Adding the metabox
			add_meta_box( 'zd_post_sync', 'Zendesk Article Sync', array(&$this,'wpzd_metabox_form'), array('page','post'), 'side');
		});

		# On save post action
		add_action( 'save_post', array(&$this,'wpzd_metabox_save') );

		# Adding Settings section to WP General settings page
		add_action( 'admin_init', array(&$this,'general_tab_settings') );
	}


	# Loading options (located in General Tab)
	function load_options_settings(){
		$this->ZDURL    = get_option('wpzd_api_url');
		$this->ZDUSER   = get_option('wpzd_email');
		$this->ZDAPIKEY = get_option('wpzd_api_key');
	}

	# Return TRUE if all options is setted;
	function zd_api_options_ok(){
		return !( empty($this->ZDURL) || empty($this->ZDUSER) || empty($this->ZDAPIKEY) );
	}

	# Plugin settings (located in General Tab)
	function general_tab_settings(){
		$settings_page = 'general';
		# Registering settings
		register_setting( $settings_page, 'wpzd_api_url' );
		register_setting( $settings_page, 'wpzd_email' );
		register_setting( $settings_page, 'wpzd_api_key' );
		# Create settings section
		add_settings_section( 'wpzd_section', 'Wordpress to Zendesk', function(){}, $settings_page );
		# Create settings fields
		add_settings_field( 
			  'wpzd_api_url'
			, 'REST API URL'
			, function(){
				printf('<input name="wpzd_api_url" id="wpzd_api_url" class="regular-text" type="text" placeholder="https://yourcompany.zendesk.com/api/v2" value="%s" />', get_option('wpzd_api_url') );
			}
			, $settings_page
			, 'wpzd_section'
			, array( 'label_for' => 'wpzd_api_url' )
		);
		add_settings_field( 
			  'wpzd_email'
			, 'E-mail'
			, function(){
				printf('<input name="wpzd_email" id="wpzd_email" class="regular-text" type="email" placeholder="Zendesk User E-mail" value="%s" />', get_option('wpzd_email') );
			}
			, $settings_page
			, 'wpzd_section'
			, array( 'label_for' => 'wpzd_email' )
		);
		add_settings_field( 
			  'wpzd_api_key'
			, 'Key'
			, function(){
				printf('<input name="wpzd_api_key" id="wpzd_api_key" class="regular-text" type="text" placeholder="Zendesk API Key (not password)" value="%s" />', get_option('wpzd_api_key') );
			}
			, $settings_page
			, 'wpzd_section'
			, array( 'label_for' => 'wpzd_api_key' )
		);
	}


	# Metabox form in Page/Post editor
	function wpzd_metabox_form(){
		# If no settings
		if ( !$this->zd_api_options_ok() ){ echo sprintf( "<a href=\"%s\">Please setup Zendesk Credentials in General tab</a>", admin_url('options-general.php') ); return; }
		# Else show form
		wp_nonce_field(basename( __FILE__ ),'zd_post_sync_nonce');
		$wpzd_obj = get_post_meta(get_the_ID(),'wpzd',true);
		require_once( plugin_dir_path(__FILE__) . 'form.php' );
	}


	# Saving the metabox fields
	function wpzd_metabox_save($post_id){
		if( !$this->zd_api_options_ok() ) return;
		if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if( !isset( $_POST['zd_post_sync_nonce'] ) || !wp_verify_nonce( $_POST['zd_post_sync_nonce'], basename( __FILE__ ) ) ) return;
		if( !current_user_can( 'edit_post' ) ) return;
		#--
		if( !isset( $_POST['wpzd_url'] ) ) return;
		#--
		$wpzd['url'] = esc_url( trim( $_POST['wpzd_url'] ) );
		if ( empty($wpzd['url']) ) { delete_post_meta($post_id, 'wpzd'); return; }
		#--
		$wpzd['id']  = $this->zd_id_from_url( $wpzd['url'] );
		update_post_meta( $post_id, 'wpzd', $wpzd );
		$this->zd_api_update_article( $wpzd['id'], $this->wp_get_post_content($post_id) );
	}


	# Update Zendesk Article via REST API
	function zd_api_update_article($article_id, $html){
		$url = sprintf("/help_center/articles/%s/translations/ru.json", $article_id);
		$json = json_encode( array('body' => $html), JSON_FORCE_OBJECT );
		return $this->zd_curl($url, $json, 'PUT');
	}


	# Getting WP Post content by WP Post ID
	function wp_get_post_content($post_id){
		global $post;
		return get_post_field('post_content', $post_id);
	}


	# Regex to get Article ID from Zendesk full url (equal to JS implementation in Metabox form)
	function zd_id_from_url($article_url){
		$out = array();
		preg_match("/articles\/(\d*)/", $article_url, $out);
		return ( isset($out[1]) ) ? $out[1] : false;
	}

	# Sending data to Zendesk RESR API via CURL
	function zd_curl($url, $json, $action){
		$ch = curl_init();  
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10 );  
		curl_setopt($ch, CURLOPT_URL, $this->ZDURL.$url);  
		curl_setopt($ch, CURLOPT_USERPWD, $this->ZDUSER."/token:".$this->ZDAPIKEY);  
		
		switch($action){
			case "POST":  
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");  
				curl_setopt($ch, CURLOPT_POSTFIELDS, $json);  
	 		break;  
	 		case "GET":  
	 			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");  
	 		break;  
	 		case "PUT":  
	 			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");  
	 			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);  
	 		break;  
	 		case "DELETE":  
	 			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");  
	 		break;  
		}

		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));  
		curl_setopt($ch, CURLOPT_USERAGENT, "Godzilla/1.0");  
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);  
		$output = curl_exec($ch);  
		curl_close($ch);  
		$decoded = json_decode($output);  
		return $decoded;
	}

}

new wp_to_zendesk();

?>
