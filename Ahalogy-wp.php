<?php
/*
Plugin Name: Ahalogy WP
Plugin URI: http://app.ahalogy.com/wp/
Description: Inserts the Ahalogy tag code into your website
Version: 0.9
Author: Ahalogy (JP)
Author URI: http://www.ahalogy.com
License: GPLv3
	Copyright 2013 Ahalogy (http://www.ahalogy.com)
*/

if( !class_exists( 'ahalogyWP' ) ) : // namespace collision check
class ahalogyWP {
	// declare globals
	var $options_name = 'ahalogy_wp_item';
	var $options_group = 'ahalogy_wp_option_option';
	var $options_page = 'ahalogy_wp';
	var $plugin_homepage = 'http://app.ahalogy.com/wp/';
	var $plugin_name = 'Ahalogy Tag';
	var $plugin_textdomain = 'ahalogyWP';

	// constructor
	function ahalogyWP() {
		$options = $this->optionsGetOptions();
		add_filter( 'plugin_row_meta', array( &$this, 'optionsSetPluginMeta' ), 10, 2 ); // add plugin page meta links
		add_action( 'admin_init', array( &$this, 'optionsInit' ) ); // whitelist options page
		add_action( 'admin_menu', array( &$this, 'optionsAddPage' ) ); // add link to plugin's settings page in 'settings' menu on admin menu initilization
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
		$input['insert_code'] = ( $input['insert_code'] ? 1 : 0 ); 	// (checkbox) if TRUE then 1, else NULL
		$input['client_id'] =  wp_filter_nohtml_kses( $input['client_id'] ); // (textbox) safe text, no html
		$input['location'] = ( $input['location'] == 'head' ? 'head' : 'body' ); // (radio) either head or body
		return $input;
	}

	// draw a checkbox option
	function optionsDrawCheckbox( $slug, $label, $style_checked='', $style_unchecked='' ) {
		$options = $this->optionsGetOptions();
		if( !$options[$slug] )
			if( !empty( $style_unchecked ) ) $style = ' style="' . $style_unchecked . '"';
			else $style = '';
		else
			if( !empty( $style_checked ) ) $style = ' style="' . $style_checked . '"';
			else $style = '';
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
				<p style="font-size:0.95em"><?php
					printf( __( 'This is a test verson. <a href="%1$s">We welcome feedback</a>.', $this->plugin_textdomain ), $this->plugin_homepage ); ?></p>

				<table class="form-table">

					<?php $this->optionsDrawCheckbox( 'insert_code', 'Include Ahalogy code on your site?', '', 'color:#f00;' ); ?>

					 <!-- <?php _e( 'Client ID', $this->plugin_textdomain ); ?> -->
					<tr valign="top"><th scope="row"><label for="<?php echo $this->options_name; ?>[client_id]"><?php _e( 'Ahalogy Client ID', $this->plugin_textdomain ); ?>: </label></th>
						<td>
							<input type="text" name="<?php echo $this->options_name; ?>[client_id]" value="<?php echo $options['client_id']; ?>" style="width:300px;" maxlength="30" />
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
	$disabled = '
	<!--
	Ahalogy-WP Plugin is installed but widget code is turned off or Client ID not set (http://app.ahalogy.com/wp/)
	-->';

	// Ahalogy widget code
	$widget_code = sprintf( '
        <script type=\'text/javascript\'> /* generated by wordpress plugin */
            (function(a,h,a_,l,o,g,y){
            window[a_]={c:o,b:g,u:l};var s=a.createElement(h);s.src=l,e=a.getElementsByTagName(h)[0];e.parentNode.insertBefore(s,e);
            })(document,\'script\',\'_ahalogy\',\'//w.ahalogy.com/\',{client:"client_id:%1$s"});
        </script>'
	,
		$options['client_id']
	);

	// build code
	if( !$options['insert_code'] || strlen($options['client_id'])<10)
		echo $disabled;
	else
		echo $widget_code ;
	}
} // end class
endif; // end collision check

$ahalogyWP_instance = new ahalogyWP;
?>