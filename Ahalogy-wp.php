<?php
/*
Plugin Name: Ahalogy
Plugin URI: https://app.ahalogy.com/
Description: Inserts the Ahalogy snippet into your website
Version: 1.1.6
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
	var $plugin_version = '1.1.6';	
  var $plugin_api_key = 'VdJXFxivKY9PEyuwN2P';
	var $mobilify_environment = 'development';
	var $mobilify_js_domain = 'https://w.ahalogy.com';
	var $widget_js_domain = '//w.ahalogy.com';
	var $mobilify_api_domain = 'https://app.ahalogy.com';
  var $date_format = 'c';
  var $display_none = false;

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
			'mobilify_api_optin' => 1
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

	// draw a checkbox option
	function optionsDrawCheckbox( $slug, $label, $style_checked='', $style_unchecked='' ) {
		$options = $this->optionsGetOptions();
		$defaults = $this->optionsGetDefaults();

		if (!isset($options[$slug])) {	
			//index isn't identified. set the default
			$options[$slug] = $defaults[$slug];
		}

		if( !$options[$slug] ) {
			if( !empty( $style_unchecked ) ) $style = ' style="' . $style_unchecked . '"';
			else $style = '';
		} else {
			if( !empty( $style_checked ) ) $style = ' style="' . $style_checked . '"';
			else $style = '';
		}
	?>
		 <!-- <?php _e( $label, $this->plugin_textdomain ); ?> -->
			<tr valign="top">
				<th scope="row">
					<label<?php echo $style; ?> for="<?php echo $this->options_name; ?>[<?php echo $slug; ?>]">
						<?php _e( $label, $this->plugin_textdomain ); ?>
					</label>
				</th>
				<td>
					<input name="<?php echo $this->options_name; ?>[<?php echo $slug; ?>]" type="checkbox" value="1" <?php checked( $options[$slug], 1 ); ?>/>
				</td>
			</tr>

	<?php }

	// draw the options page
	function optionsDrawPage() { ?>
		<div class="wrap">
		<div class="icon32" id="icon-options-general"><br /></div>
			<h2><?php echo $this->plugin_name . __( ' Settings', $this->plugin_textdomain ); ?></h2>
			<form name="form1" id="form1" method="post" action="options.php">
				<?php settings_fields( $this->options_group ); // nonce settings page ?>
				<?php $options = $this->optionsGetOptions();  //populate $options array from database ?>

				<!-- Description -->
				<p><a href="http://ahalogy.uservoice.com" target="_new">Ahalogy Support</a>
				</p>

				<table class="form-table">

					<?php $this->optionsDrawCheckbox( 'insert_code', 'Include code snippet on my site', '', 'color:#000;' ); ?>

					 <!-- <?php _e( 'Client ID', $this->plugin_textdomain ); ?> -->
					<tr valign="top"><th scope="row"><label for="<?php echo $this->options_name; ?>[client_id]"><?php _e( 'Ahalogy Client ID', $this->plugin_textdomain ); ?>: </label></th>
						<td>
							<input type="text" name="<?php echo $this->options_name; ?>[client_id]" value="<?php echo $options['client_id']; ?>" style="width:200px;" maxlength="30" />
						</td>
					</tr>

					<!-- Head/Body insert (radio buttons) -->
					<tr valign="top"><th scope="row" valign="middle"><label for="<?php echo $this->options_name; ?>[location]"><?php _e( 'Insert Location', $this->plugin_textdomain ); ?>:</label></th>
						<td>
							<input name="<?php echo $this->options_name; ?>[location]" type="radio" value="head" <?php checked( $options['location'], 'head', TRUE ); ?> />
							<?php printf( 'before %1$shead%2$s tag', '&lt;/', '&gt;' ); ?> (recommended)<br />
							<input name="<?php echo $this->options_name; ?>[location]" type="radio" value="body" <?php checked( $options['location'], 'body', TRUE ); ?> />
							<?php printf( 'before %1$sbody%2$s tag', '&lt;/', '&gt;' ); ?>
						</td>
					</tr>

					<?php $this->optionsDrawCheckbox( 'mobilify_api_optin', 'Enable Ahalogy API Access', '', 'color:#000;' ); ?>

					<?php $this->optionsDrawCheckbox( 'mobilify_optin', 'Enable Mobile Optimization', '', 'color:#000;' ); ?>

				</table>
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e( 'Save Changes', $this->plugin_textdomain ) ?>" />
				</p>
			</form>
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
'
	,
		$this->plugin_version
	,
		$options['client_id']
		,
        $this->widget_js_domain
	);

	// build code

	if( !$options['insert_code'] || strlen($options['client_id'])<10)
		echo $disabled;
	else
		echo $widget_code ;
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



} // end class
endif; // end collision check

$ahalogyWP_instance = new ahalogyWP;

include_once dirname(__FILE__) . '/Ahalogy-wp-mobile.php';
include_once dirname(__FILE__) . '/Ahalogy-wp-mobile-post.php';
include_once dirname(__FILE__) . '/Ahalogy-wp-mobile-author.php';
include_once dirname(__FILE__) . '/Ahalogy-wp-mobile-attachment.php';