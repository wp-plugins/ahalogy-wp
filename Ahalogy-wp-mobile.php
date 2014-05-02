<?php
if( !class_exists( 'ahalogyWPMobile' ) ) : // namespace collision check
  class ahalogyWPMobile {

  ////////////////////////////
  // Mobilify functions
  // JSON API functionality adapted from JSON API plugin by Dan Phiffer (http://wordpress.org/plugins/json-api/)
  ////////////////////////////    

  // constructor
  function ahalogyWPMobile() {
      // Mobilify additions
      global $ahalogyWP_instance;
      
      if ($ahalogyWP_instance) {
        $options = $ahalogyWP_instance->optionsGetOptions();
      }

      add_action( 'template_redirect', array(&$this, 'jsonTemplateRedirect'));

      add_action( 'admin_init', array( &$this, 'mobilifyOptinCheck' ) ); // mobile reqs check


      if (( isset($options)) && (isset($options['mobilify_api_optin'])) && ($options['mobilify_api_optin'])) {
        add_action( 'admin_init', array( &$this, 'ahalogyMobileCheck' ) ); // mobile reqs check
        add_action( 'save_post', array( &$this, 'updateMobilePost' ) ); 
      }

      if (( isset($options)) && (isset($options['mobilify_optin'])) && ($options['mobilify_optin'])) {
        add_action( 'wp_head', array( &$this, 'getMobilifyHeaderCode' ), 99999 );        
      }
  }   

  function getMobilifyHeaderCode() {
    global $ahalogyWP_instance;
    global $post;

    if (is_single()) {
      $options = $ahalogyWP_instance->optionsGetOptions();
      if ($ahalogyWP_instance->display_none) {
        $css_rule = '<style type="text/css">body{display:none;}</style>';
      } else {
        $css_rule = '';
      }

      // Mobilify widget code
      $widget_code = sprintf( '
%2$s
<script data-cfasync="false" type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
<script data-cfasync="false" type="text/javascript" src="%3$s/mobilify.js" id="mobilify-loader" data-client="%4$s" data-article="%5$s">/* generated by Ahalogy wordpress plugin [version %1$s] */</script>
'
      ,
        $ahalogyWP_instance->plugin_version
      ,
        $css_rule
      ,
        $ahalogyWP_instance->mobilify_js_domain      
      ,
        $options['client_id']
      ,
        $post->ID
      );

      // build code
      if( $options['insert_code'] && strlen($options['client_id'])>=10)
        echo $widget_code;
    }   
  }   
      
  // Ping ahalogy when a post when it is saved or updated. Update the post meta field 'ahalogy_response' when their response
  function updateMobilePost( $post_id ) {
    global $ahalogyWP_instance;
    $options = $ahalogyWP_instance->optionsGetOptions();

    $slug = 'post';

    // If this isn't a 'post' post, don't update it.
    if(isset($_POST['post_type'])) {
      if ( $slug != $_POST['post_type'] ) {
        return;
      }

      // If this is just a revision, don't do anything
      if ((wp_is_post_revision($post_id)) || ($_POST['post_status'] != 'publish') || ($_POST['visibility'] != 'public')) {
          return;
      } 

      //Here is where we will ping ahalogy's servers with new post information.
      if ($options['client_id']) {
        $url = $ahalogyWP_instance->mobilify_api_domain . '/api/articles/notify_changed/' . $options['client_id'] . '/' . $post_id;

        $response = wp_remote_post( $url, array(
          'method' => 'POST',
          'timeout' => 10,
          'redirection' => 5,
          'httpversion' => '1.0',
          'blocking' => true,
          'headers' => array(),
          'body' => array( 'url' => get_permalink($post_id), 'modified_at' => get_post_modified_time($ahalogyWP_instance->date_format, true, $post_id) ),
          'cookies' => array()
            )
        );

        if ( is_wp_error( $response ) ) {
          //Setting the error but we won't do anything with it for now.
          $error_message = $response->get_error_message();
           //print_r( $error_message );
        } else {
           //print_r( $response );
           update_post_meta( $post_id, 'ahalogy_response', sanitize_text_field( time() ) );
        }
      }        
    }
  }
  
  // Verify we can use native JSON functions
  function ahalogyMobileCheck() {
    if (phpversion() < 5) {
      add_action('admin_notices', array( &$this, 'ahalogyPHPWarning' ) );
      return;
    }
  }

  //Check that the mobilify_optin option is set. If not, set it and default to 1
  function mobilifyOptinCheck() {
    global $ahalogyWP_instance;
    $options = $ahalogyWP_instance->optionsGetOptions();

    if (!isset($options['mobilify_api_optin'])) {
      $options['mobilify_api_optin'] = 1;
      //register_setting( $ahalogyWP_instance->options_group, $ahalogyWP_instance->options_name, array( &$this, 'optionsValidate' ) );
      update_option( $ahalogyWP_instance->options_name, $options );
    }
  }

  // Check for PHP version 5 or greater
  function ahalogyPHPWarning() {
    echo "<div id=\"json-api-warning\" class=\"updated fade\"><p>Sorry, the Ahalogy plugin requires PHP version 5.0 or greater.</p></div>";
  }

  //Authentication - check for API Key
  function authenticateAPIKey() {
    global $ahalogyWP_instance;

    //Authentication - check for API Key
    if (isset($_REQUEST['api_key'])) {
      $apikey = $_REQUEST['api_key'];
      if ($apikey != $ahalogyWP_instance->plugin_api_key) {
        $response = array("status" => "error", "error" => "Ahalogy API key is not valid.");
        $this->respond($response);
        exit;                              
      }
    } else {
      $response = array("status" => "error", "error" => "Ahalogy API key is required.");
      $this->respond($response);
      exit;                      
    }        
  }

  //redirect to JSON template if necessary
  function jsonTemplateRedirect() {
    global $ahalogyWP_instance;
    $options = $ahalogyWP_instance->optionsGetOptions();

      //Check that is this is a single post
      if(is_single()) {
        if (isset($_REQUEST['mobilify_json'])) {
          if ($_REQUEST['mobilify_json'] == 1) {
            
            //Authenticate the api key
            $this->authenticateAPIKey();

            if ($options['mobilify_api_optin']) {
              //Post is single and has the ahalogy_json parameter. Let's redirect it.
              global $post;

              $response = array(
                'post' => new Mobilify_JSON_API_Post($post)
              );                  
            } else {
              $response = array(
                'status' => 'error',
                'error' => 'Ahalogy API not enabled'
              );
            }

            $this->respond($response);
            exit;
          } 
        } 
      }

      //Check if it's the homepage for site wide API calls
      if (is_front_page()) {  
        
        //Check for initial API request
        if ((isset($_REQUEST['mobilify_json'])) && ($_REQUEST['mobilify_json'] == 1)) {

          //Authenticate the API Key
          $this->authenticateAPIKey();

          //Ahalogy plugin settings query
          if ((isset($_REQUEST['ahalogy_settings_index'])) && ($_REQUEST['ahalogy_settings_index'] == 1)) { 

            //Build settings array
            $response = array();
            $response['plugin_version'] = $ahalogyWP_instance->plugin_version;

            if (isset($options)) {
              foreach ($options as $key => $value) {
                $response[$key] = $value;
              }
            }

            $this->respond($response); 
          }

          $response = array();

          //Ahalogy plugin settings update
          if ((isset($_REQUEST['ahalogy_settings_update'])) && ($_REQUEST['ahalogy_settings_update'] == 1)) {
            
            $updatesettings = false;
            $updatedoptions = $options;
            
            if (isset($options)) {
              foreach ($options as $key => $value) {
                if (isset($_REQUEST[$key])) {                  

                  if (in_array($key, array('insert_code','mobilify_api_optin','mobilify_optin'))) {
                    //boolean
                    if ($_REQUEST[$key] == '0') {
                      $updatedoptions[$key] = 0;
                    } else if ($_REQUEST[$key] == '1') {
                      $updatedoptions[$key] = 1;
                    }
                  } else if (in_array($key, array('client_id'))) {
                    //check against regex
                    $client_id_pattern = '/[0-9]{9,10}(-[a-z]+)?/i';
                    if (preg_match($client_id_pattern,$_REQUEST[$key],$matches)) {
                      $updatedoptions[$key] = sanitize_text_field($_REQUEST[$key]);
                    }
                  } else if (in_array($key, array('location'))) {
                    //string
                    if (in_array($_REQUEST[$key], array('head','body'))) {
                      $updatedoptions[$key] = sanitize_text_field($_REQUEST[$key]);
                    }
                  }

                } 
              }
            }
            
            update_option($ahalogyWP_instance->options_name, $updatedoptions);

            $this->respond($response);             
          }          

          //The Index API. mobilify_json is true. Check the method
          if ((isset($_REQUEST['mobilify_index'])) && ($_REQUEST['mobilify_index'] == 1)) {
            
            //Make sure mobilify is enabaled
            if ($options['mobilify_api_optin']) {

              //Pagination
              $paged = (isset($_GET["page"]) && $_GET['page'] !== '') ? $_GET["page"] : 1;
              $count = (isset($_GET["rpp"]) && $_GET['rpp'] !== '') ? $_GET["rpp"] : 100;

              // Arguments for WP_Query
              $args = 
                array( 
                  'posts_per_page' => $count,
                  'paged' => $paged,
                  'orderby' => 'modified',
                  'order' => 'DESC'
                );  

              //Check for modified_since date parameter
              if (isset($_REQUEST['modified_since'])) {
                $modifieddate = $_REQUEST['modified_since'];

                if ($this->is_timestamp($modifieddate)) {  
                  $moddatearray = getdate($modifieddate);

                  $args['date_query'] = array(
                        'column' => 'post_modified_gmt',
                        'after'  => 
                          array(
                            'year'  => $moddatearray['year'],
                            'month' => $moddatearray['mon'],
                            'day'   => $moddatearray['mday'],
                          )
                      );
                } 
              }

              $the_query = new WP_Query( $args );

              if ( $the_query->have_posts() ) {
                
                $response = array();

                $response['page'] = $paged;
                $response['rpp'] = $count;

                while ( $the_query->have_posts() ) {
                  $the_query->the_post();   
                  global $post;
                  $postoutput['id'] = $post->ID;    
                  $postoutput['title'] = $post->post_title;
                  $postoutput['url'] = get_permalink($post->id);
                  $postoutput['modified'] = date($ahalogyWP_instance->date_format, strtotime($post->post_modified));
                  $response['posts'][] = $postoutput;
                }
              } else {
                $response = array("status" => "no results");
              }
            } else {
              $response = array(
                'status' => 'error',
                'error' => 'Ahalogy API not enabled'
              );                
            }
            $this->respond($response);      
            exit;                  
            }
          }
        }
      }

  function get_json($data, $status = 'ok') {
    global $ahalogyWP_instance;

    // Include a status value with the response
    // Include plugin version with the response
    if (is_array($data)) {
      $data = array_merge(array('status' => $status), $data);
    } else if (is_object($data)) {
      $data = get_object_vars($data);
      $data = array_merge(array('status' => $status), $data);
    }
    
    if (function_exists('json_encode')) {
      // Use the built-in json_encode function if it's available
      $json = json_encode($data);
    } else {
      // Use PEAR's Services_JSON encoder otherwise
      if (!class_exists('Services_JSON')) {
        require_once dirname(__FILE__) . "/library/JSON.php";
      }
      $json_service = new Services_JSON();
      $json = $json_service->encode($data);
    }
          
    return $json;
  }

  // JSON Output
  function output($result) {
    $charset = get_option('blog_charset');
    if (!headers_sent()) {
      header('HTTP/1.1 200 OK', true);
      header("Content-Type: application/json; charset=$charset", true);
    }
    echo $result;
  }

  function respond($result, $status = 'ok') {
    $json = $this->get_json($result, $status);

    // Output the result
    $this->output($json); 
    exit;
  }

  //Validate our modified_date timestamp
  function is_timestamp($timestamp) {
  $check = (is_int($timestamp) OR is_float($timestamp))
    ? $timestamp
    : (string) (int) $timestamp;
 
  return  ($check === $timestamp)
          AND ( (int) $timestamp <=  PHP_INT_MAX)
          AND ( (int) $timestamp >= ~PHP_INT_MAX);
  }

} //end class

endif;

$ahalogyWPMobile_instance = new ahalogyWPMobile;