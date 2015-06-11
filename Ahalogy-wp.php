<?php
/*
Plugin Name: Ahalogy
Plugin URI: https://app.ahalogy.com/
Description: Inserts the Ahalogy snippet into your website
Version: 1.2.8
Author: Ahalogy
Author URI: http://www.ahalogy.com
License: GPLv3
	Copyright 2013-2014 Ahalogy (http://www.ahalogy.com)
*/

if( !class_exists( 'ahalogyWP' ) ) : // namespace collision check
class ahalogyWP {
	// declare globals
	var $options_name = 'ahalogy_wp_item';
	var $options_group = 'ahalogy_wp_option_option';
	var $options_page = 'ahalogy_wp';
	var $plugin_homepage = 'https://app.ahalogy.com/';
	var $plugin_name = 'Ahalogy';
	var $plugin_textdomain = 'ahalogyWP';
	var $plugin_version = '1.2.8';
	var $plugin_api_key = 'VdJXFxivKY9PEyuwN2P';
	var $mobilify_environment = 'development';
	var $mobilify_js_domain = 'https://w.ahalogy.com';
	var $widget_js_domain = '//w.ahalogy.com';
	var $mobilify_api_domain = 'https://app.ahalogy.com';
	var $date_format = 'c';
	var $cached_mobilify_template_time = 1800; //30 minutes
	var $cached_mobilify_request_limit = 300; //5 minutes

	// constructor
	function ahalogyWP() {
		$options = $this->optionsGetOptions();
		add_filter( 'plugin_row_meta', array( &$this, 'optionsSetPluginMeta' ), 10, 2 ); // add plugin page meta links
		
		add_action( 'admin_init', array( &$this, 'optionsInit' ) ); // whitelist options page
		add_action( 'admin_menu', array( &$this, 'optionsAddPage' ) ); // add link to plugin's settings page in 'settings' menu on admin menu initilization
		add_action( 'admin_notices', array( &$this, 'showAdminMessages' ) );     

		if( $options['location'] == 'head' )
			add_action( 'wp_head', array( &$this, 'getAhalogyCode' ), 99999 );
		else
			add_action( 'wp_footer', array( &$this, 'getAhalogyCode' ), 99999 );

	}	

	// load i18n textdomain
	function loadTextDomain() {
		load_plugin_textdomain( $this->plugin_textdomain, false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) . 'lang/' );
	}


	// get default plugin options
	function optionsGetDefaults() {
		$defaults = array(
			'client_id' => '',
			'insert_code' => 0,
			'location' => 'head',
			'mobilify_optin' => 0,
			'mobilify_api_optin' => 1,
		);
		return $defaults;
	}

	function optionsGetOptions() {
		$options = get_option( $this->options_name, $this->optionsGetDefaults() );
		return $options;
	}

	// set plugin links
	function optionsSetPluginMeta( $links, $file ) {
		$plugin = plugin_basename( __FILE__ );
		if ( $file == $plugin ) { // if called for THIS plugin then:
			$newlinks = array( '<a href="options-general.php?page=' . $this->options_page . '">' . __( 'Settings', $this->plugin_textdomain ) . '</a>' ); // array of links to add
			return array_merge( $links, $newlinks ); // merge new links into existing $links
		}
	return $links; // return the $links (merged or otherwise)
	}

	// plugin startup
	function optionsInit() {
		register_setting( $this->options_group, $this->options_name, array( &$this, 'optionsValidate' ) );

		$options = $this->optionsGetOptions();

		if ( false === $options || ! isset( $options['plugin_version'] ) || $options['plugin_version'] != $this->plugin_version ) {
			$this->clearCache();

			if ( is_array( $options ) ) {
				$options['plugin_version'] = $this->plugin_version;
				delete_option('ahalogy_snippet_last_request');
				delete_option('ahalogy_js_template');				
				update_option( $this->options_name, $options );
			}
		}
	}

	// create and link options page
	function optionsAddPage() {
		add_options_page( $this->plugin_name . ' ' . __( 'Settings', $this->plugin_textdomain ), __( 'Ahalogy', $this->plugin_textdomain ), 'manage_options', $this->options_page, array( &$this, 'optionsDrawPage' ) );
	}

	// sanitize and validate options input
	function optionsValidate( $input ) {
		$input['insert_code'] = (( isset($input['insert_code']) && ($input['insert_code'] )) ? 1 : 0 ); 	// (checkbox) if TRUE then 1, else NULL
		$input['client_id'] =  wp_filter_nohtml_kses( $input['client_id'] ); // (textbox) safe text, no html
		$input['location'] = ( $input['location'] == 'head' ? 'head' : 'body' ); // (radio) either head or body
		$input['mobilify_api_optin'] = (( isset($input['mobilify_api_optin']) && ( $input['mobilify_api_optin'] )) ? 1 : 0 ); // (checkbox) if TRUE then 1, else NULL
		$input['mobilify_optin'] = (( isset($input['mobilify_optin']) && ( $input['mobilify_optin'] )) ? 1 : 0 ); // (checkbox) if TRUE then 1, else NULL

		//Check if the mobility_optin has changed or not
		$current_options = $this->optionsGetOptions();

		if ($current_options['client_id']) {

			//Check if client_id has changed. If so, clear any mobilify cache by deleting the 'ahalogy_js_template' option
			if ((isset($current_options['mobilify_optin'])) && (isset($input['mobilify_optin']))) {
				if ($current_options['client_id'] <> $input['client_id']) {
					//Client IDs are different. Clear the cache.
					delete_option("ahalogy_js_template");
				}
			}


			if ((isset($current_options['mobilify_optin'])) && (isset($input['mobilify_optin']))) {
				if ($current_options['mobilify_optin'] <> $input['mobilify_optin']) {
					if ($input['mobilify_optin']) {
						//They turned it on	
						$payload_value = 'true';
					} else {
						//They turned it off	
						$payload_value = 'false';
					}

					$url = $this->mobilify_api_domain . '/api/clients/' . $current_options['client_id'] . '/labs_changed';

		      $response = wp_remote_post( $url, array(
		        'method' => 'POST',
		        'timeout' => 10,
		        'redirection' => 5,
		        'httpversion' => '1.0',
		        'blocking' => true,
		        'headers' => array(),
		        'body' => array( 'value' => $payload_value ),
		        'cookies' => array()
		          )
		      );	

	        if ( is_wp_error( $response ) ) {
	          //Setting the error but we won't do anything with it for now.
	          $error_message = $response->get_error_message();
	        } 
				} 
			}
		}

		return $input;
	}

	// draw the options page
	function optionsDrawPage() { ?>
    <style>
      /* AHALOGY PLUGIN SETTINGS STYLE */

      /* Typefaces */
      @font-face {
        font-family: 'futurabook';
        src: url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/futuralt-book-webfont.eot');
        src: url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/futuralt-book-webfont.eot?#iefix') format('embedded-opentype'),
          url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/futuralt-book-webfont.woff') format('woff'),
          url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/futuralt-book-webfont.ttf') format('truetype'),
          url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/futuralt-book-webfont.svg#futurabook') format('svg');
        font-weight: normal;
        font-style: normal;
      }

      @font-face {
        font-family: 'futurabold';
        src: url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/futuralt-bold-webfont.eot');
        src: url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/futuralt-bold-webfont.eot?#iefix') format('embedded-opentype'),
          url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/futuralt-bold-webfont.woff') format('woff'),
          url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/futuralt-bold-webfont.ttf') format('truetype'),
          url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/futuralt-bold-webfont.svg#futurabold') format('svg');
        font-weight: normal;
        font-style: normal;
      }
      
      @font-face {
        font-family: 'freight-text-book';
        src: url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/freight-text-book.eot');
        src: url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/freight-text-book.eot?#iefix') format('embedded-opentype'),
          url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/freight-text-book.woff') format('woff'),
          url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/freight-text-book.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
      }
      
      @font-face {
        font-family: 'freight-text-bold';
        src: url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/freight-text-bold.eot');
        src: url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/freight-text-bold.eot?#iefix') format('embedded-opentype'),
          url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/freight-text-bold.woff') format('woff'),
          url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/freight-text-bold.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
      }
      
      @font-face {
        font-family: 'freight-text-book-italic';
        src: url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/freight-text-book-italic.eot');
        src: url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/freight-text-book-italic.eot?#iefix') format('embedded-opentype'),
          url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/freight-text-book-italic.woff') format('woff'),
          url('//s3.amazonaws.com/pingage-images/engagements/_default/poster/fonts/freight-text-book-italic.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
      }

      /* Style */
      html {
        background: #f4f4f4;
      }
      
      body {
      }

      .wrap {
        width: 480px;
        padding: 64px 0 0 64px;
        font-familiy: 'futurabook' !important;
        text-rendering: optimizeLegibility;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        -moz-font-feature-settings: "liga" on;
        color: #2d2d2d !important;
      }

      .wrap h2 {
        font-family: 'futurabook';
        font-size: 24px;
        font-weight: 400;
        line-height: 3rem;
        letter-spacing: 0.25em;
        text-transform: uppercase;
        vertical-align: middle;
      }
      
      .ahalogyIcon {
        width: 3rem;
        height: 3rem;
        display: inline-block;
        background-image: url("https://res.cloudinary.com/ahalogy/image/upload/v1420732046/ahalogyRedHollowIcon_yedsim.png");
        background-repeat: no-repeat;
        background-size: 100% 100%;
        background-position: center center;
        opacity: 1;
        text-align: left;
        text-indent: 99999px;
        white-space: nowrap;
        overflow: hidden;
        user-select: none;
        text-decoration: none;
        margin-right: 1rem;
        vertical-align: middle;
        position: relative;
        top: -3px;
      }
      
      .ahalogyIcon:hover,
      .ahalogyIcon:focus,
      .ahalogyIcon:active {
        background-image: url("https://res.cloudinary.com/ahalogy/image/upload/v1420732046/ahalogyRedFullIcon_nlmpn5.png");
        outline: none;
      }
      
      .separator {
        height: 1px;
        width: 128px;
        background-color: #c6c6c6;
        margin: 32px 0;
      }
      
      .formWrap {
        margin-bottom: 80px;
      }
      
      p {
        font-family: 'freight-text-book';
        font-size: 14px;
        font-weight: 400;
        line-height: 1.55555;
        letter-spacing: 0.05em;
        margin: 0 0 1rem 0;
        padding: 0;
      }
      
      p.lede {
        font-size: 24px;
        line-height: 1.2857142857;
      }
      
      .formGroup {
        margin: 2rem 0;
      }
      
      .formGroup.hidden {
        visibility: hidden;
      }
      
      .generalInputLabel {
        display: block;
        font-family: 'futurabold';
        font-size: 10px;
        font-weight: 700;
        line-height: 1.2857142857;
        letter-spacing: .15em;
        color: #2d2d2d;
        text-transform: uppercase;
        margin-bottom: 0.4rem;
      }
      
      input[type="text"].generalInput {
        display: block;
        font-family: 'freight-text-book';
        font-size: 18px;
        font-weight: 400;
        letter-spacing: 0.05em;
        color: #2d2d2d;
        text-transform: none;
        text-decoration: none;
        padding: 1.375rem;
        background: white;
        border: 2px solid white;
        outline: none;
        position: relative;
        -webkit-appearance: none;
        appearance: none;
        -webkit-box-sizing: border-box;
        -moz-box-sizing: border-box;
        box-sizing: border-box;
        -webkit-box-shadow: none !important;
        -moz-box-shadow: none !important;
        box-shadow: none !important;
        -webkit-transition:.1s border-color,.1s color;
        height: auto;
        width: 100%;
        text-rendering: optimizeLegibility;
        -webkit-font-smoothing: antialiased;
      }
      
      input[type="text"].generalInput:hover,
      input[type="text"].generalInput:focus,
      input[type="text"].generalInput:active {
        text-rendering: optimizeLegibility;
        -webkit-font-smoothing: antialiased;
        border: 2px solid #1b5d7e;
      }
      
      .btn {
        display: inline-block;
        padding: .75rem 1.5rem;
        border: 2px solid #2d2d2d;
        background: transparent;
        cursor: pointer;
        font-family: 'futurabold';
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.1em;
        color: #2d2d2d;
        text-transform: uppercase;
        text-decoration: none;
        text-align: center;
        -webkit-box-shadow: 0 !important;
        -moz-box-shadow: 0 !important;
        box-shadow: 0 !important;
        -webkit-transition: .1s;
        -moz-transition: .1s;
        transition: .1s;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        user-select: none;
        text-rendering: optimizeLegibility;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        -moz-font-feature-settings: "liga" on;
      }
    
      .btn:hover,
      .btn:focus,
      .btn:active {
        background: #2d2d2d;
        color: white;
        outline: none;
      }
      
      .btn--primary {
        border-color: #1b5d7e;
        background: #1b5d7e;
        color: white;
      }
      
      .btn--primary:hover,
      .btn--primary:focus {
        background: #28759c;
        border-color: #28759c;
      }

      .btn--primary:active {
        background: #436476;
        border-color: #436476;
      }
      
      .formFieldExplanation {
        display: block;
        text-align: left;
        font-family: 'freight-text-book-italic';
        font-size: 14px;
        font-weight: 400;
        line-height: 1.2857142857;
        letter-spacing: 0.025em;
        font-style: italic;
        color: #626262;
        margin-top: .5em;
      }
  
      .formFieldExplanation a {
        color: #1b5d7e;
        outline: none;
      }
      
      .formFieldExplanation a:focus,
      .formFieldExplanation a:hover,
      .formFieldExplanation a:active {
        text-decoration: underline;
      }
    </style>
		<div class="wrap">
		<div class="icon32" id="icon-options-general"><br /></div>
      <h2 class="pageTitle">
        <a class="ahalogyIcon" href="https://www.ahalogy.com" target="_blank">Ahalogy</a>
        <span>Plugin Settings</span>
      </h2>
      <div class="separator"></div>
      <div class="formWrap">
        <p>Installing the Ahalogy plugin lets you track the clicks your content received on Pinterest, plus it enables you to show engagements whenever you get a visitor from Pinterest. It's the secret weapon of the Content Network.</p>
        <form name="form1" id="form1" method="post" action="options.php">
          <?php settings_fields( $this->options_group ); // nonce settings page ?>
          <?php $options = $this->optionsGetOptions();  //populate $options array from database ?>

		  <input name="ahalogy_wp_item[insert_code]" type="hidden" value="1"/>
		  <input name="ahalogy_wp_item[location]" type="hidden" value="head" />
		  <input name="ahalogy_wp_item[mobilify_api_optin]" type="hidden" value="1" />
		  <input name="ahalogy_wp_item[mobilify_optin]" type="hidden" value="0" />

          <div class="formGroup">
            <!-- <?php _e( 'Client ID', $this->plugin_textdomain ); ?> -->
            <label class="generalInputLabel" for="<?php echo $this->options_name; ?>[client_id]"><?php _e( 'Client ID', $this->plugin_textdomain ); ?></label>
            <input class="generalInput" type="text" autofocus="autofocus" placeholder="Enter your Ahalogy Client ID" name="<?php echo $this->options_name; ?>[client_id]" value="<?php echo $options['client_id']; ?>" maxlength="30" />
            <span class="formFieldExplanation">Don't know your id? No sweat, <a href="https://app.ahalogy.com/settings/pinning/code-snippet" target="_blank">we've got it for you here</a>.</span>
          </div>

          <input type="submit" class="btn btn--primary" value="<?php _e( 'Save Changes', $this->plugin_textdomain ) ?>" />
        </form>
      </div>
      <span class="formFieldExplanation">Need a hand? <a href="https://help.ahalogy.com/customer/portal/articles/1821494" target="_blank">Check out the Ahalogy Help Center</a>.</span>
    </div>
		<?php
	}

	// 	the Ahalogy widget code to be inserted
	function getAhalogyCode() {
		$options = $this->optionsGetOptions();

	// code removed for all pages
	$disabled = sprintf('
<!--
Ahalogy wordpress plugin [version %1$s] is installed but widget code is turned off or Client ID not set
-->
'
	,
		$this->plugin_version
	);

	// Ahalogy widget code
	$widget_code = sprintf( '
<script data-cfasync="false" type="text/javascript"> /* generated by Ahalogy wordpress plugin [version %1$s] */
  (function(a,h,a_,l,o,g,y){
  window[a_]={c:o,b:g,u:l};var s=a.createElement(h);s.src=l,e=a.getElementsByTagName(h)[0];e.parentNode.insertBefore(s,e);
  })(document,"script","_ahalogy","%3$s/",{client:"%2$s"});
</script>
'		,
			$this->plugin_version
		,
			$options['client_id']
			,
	        $this->widget_js_domain
		);

		// build code

		if ( ( !$options['insert_code'] || strlen($options['client_id']) < 10) ) {
			echo $disabled;
		} else {
			echo $widget_code;
		}
	}

	/**
	 * Generic function to show a message to the user using WP's 
	 * standard CSS classes to make use of the already-defined
	 * message colour scheme.
	 *
	 * @param $message The message you want to tell the user.
	 * @param $errormsg If true, the message is an error, so use 
	 * the red message style. If false, the message is a status 
	  * message, so use the yellow information message style.
	 */
	function showMessage($message, $errormsg = false) {
		if ($errormsg) {
			echo '<div id="message" class="error">';
		}
		else {
			echo '<div id="message" class="updated fade">';
		}

		echo "<p><strong>$message</strong></p></div>";
	}

	/**
	 * Just show ClientID error message if necessary
	 */
	function showAdminMessages() {
	    //Show a message on all admin pages if the client id is not set
		$options = get_option( $this->options_name, $this->optionsGetDefaults() );

		if (empty($options['client_id'])) {
		    // Only show to admins
		    if ( current_user_can('manage_options') ) {
		       $this->showMessage("Please <a href='" . admin_url( 'options-general.php?page=ahalogy_wp' ) . "'>enter your client ID</a> to activate the Ahalogy plugin.", true);
		    }
		} 

	}

  public static function clearCache() {
    //Remove our options
    //delete_option('ahalogy_snippet_last_request');
    //delete_option('ahalogy_js_template');       

    // Check for W3 Total Cache
    if (function_exists('w3tc_pgcache_flush')) { 
      w3tc_pgcache_flush();
      //echo '<!-- Cleared w3 Total Cache -->';
    }

    // Check for WP Super Cache
    if (function_exists('wp_cache_clear_cache')) {
      wp_cache_clear_cache();
      //	echo '<!-- Cleared WP Super Cache -->';    
    }    
  }

} // end class
endif; // end collision check

register_activation_hook( __FILE__, array( 'ahalogyWP', 'clearCache' ) );

$ahalogyWP_instance = new ahalogyWP;

include_once dirname(__FILE__) . '/Ahalogy-wp-mobile.php';
include_once dirname(__FILE__) . '/Ahalogy-wp-mobile-post.php';
include_once dirname(__FILE__) . '/Ahalogy-wp-mobile-author.php';
include_once dirname(__FILE__) . '/Ahalogy-wp-mobile-attachment.php';