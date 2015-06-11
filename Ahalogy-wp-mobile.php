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

      if (( isset($options)) && ( isset($options['mobilify_optin'] )) && ($options['mobilify_optin'])) {
        add_action( 'admin_init', array( &$this, 'ahalogyMobileCheck' ) ); // mobile reqs check
        add_action( 'save_post', array( &$this, 'updateMobilePost' ) ); 
      }

      if (( isset($options)) && (isset($options['mobilify_optin'])) && ($options['mobilify_optin'])) {
        add_action( 'wp_head', array( &$this, 'getMobilifyPluginComment' ), 1 );
        add_action( 'wp_head', array( &$this, 'getMobilifyHeaderCode' ), 1 );
      }
  }   

  function getMobilifyPluginComment() {
    //Insert a HTML comment with the plugin version regardless of page
    global $ahalogyWP_instance;    
    $ahalogy_comment = sprintf( '<!-- Ahalogy Mobilify enabled [plugin version %1$s] -->
      ', 
      $ahalogyWP_instance->plugin_version
    );
    
    echo $ahalogy_comment;
  }

  function getMobilifyHeaderCode() {
    global $ahalogyWP_instance;
    global $post;
    
    $options = $ahalogyWP_instance->optionsGetOptions();

    // Mobilify widget code
    if( $options['mobilify_optin'] && strlen($options['client_id']) >= 10) {
      if (is_single()) {

        // Check to see if the mobilify javascript is cached.
        // If the cached javascript has not expired, use it. Otherwise, grab a new version   

        $cached_snippet = get_option('ahalogy_js_template');

        $bypass_cache = false;
        $print_debug_comment = false;

        // See if we should bypass the caching if the debug param has been sent.
        if ( ( isset( $_REQUEST['ahalogy_snippet_debug'] ) && ( $_REQUEST['ahalogy_snippet_debug'] == 1 ) ) ) {
          
          //Verify API key
          if ( (isset($_REQUEST['api_key'])) && ($_REQUEST['api_key'] == $ahalogyWP_instance->plugin_api_key) ) {
            $bypass_cache = true;
            $print_debug_comment = true;
            $ahalogyWP_instance->clearCache();
          }
        }


        //Should we use the cache?
        $last_request = get_option('ahalogy_snippet_last_request');
        
        if (!$last_request) {
          $bypass_cache = true;
        } else {
          if ( ( $last_request ) && ( time() - $last_request > $ahalogyWP_instance->cached_mobilify_request_limit ) ) {
            if ( !isset($cached_snippet) ) {
              $bypass_cache = true;
            } else {
              if (!isset($cached_snippet['timestamp'])) {
                $bypass_cache = true;
              } else {
                if  ( time() - $cached_snippet['timestamp'] > $ahalogyWP_instance->cached_mobilify_template_time ) {
                  $bypass_cache = true;
                }
              }
            }
          }
        }

        if ( !$bypass_cache ) {
          
          // echo 'CACHED';
          // Cached template is still valid. Print to page.
          if ($cached_snippet["snippet"]) {
            $snippet = stripslashes($cached_snippet["snippet"]);
            $snippet = str_replace('${client_id}', $options['client_id'], $snippet);
            $snippet = str_replace('${post_id}', $post->ID, $snippet);                
            echo $snippet;
          }
        } else {

          // echo 'NOT CACHED';
          // Cache is invalid or not set. Get the template from Ahalogy
          $snippet_url = $ahalogyWP_instance->mobilify_js_domain . '/mobilify/client/' . $options['client_id'] . '/snippet-template?pv=' . $ahalogyWP_instance->plugin_version;

          $js_response = wp_remote_get( $snippet_url, array( 'timeout' => 3 ) );

          // Regardless of the response, store a timestamp of when we made the request so we can wait 5 minutes.
          update_option('ahalogy_snippet_last_request', time());

          if( is_wp_error( $js_response ) ) {
            //There was an error getting a response from Ahalogy. Print the error message in an HTML comment
            if ($print_debug_comment) {
              echo "<!-- Ahalogy Mobilify JS Response (error): " . $js_response->get_error_message() . " -->";
            }
          } else if ( ( isset($js_response["response"]["code"] ) ) && ( $js_response["response"]["code"] == '200') && (isset($js_response['body']))) {
            // Get the snippet
            
            $snippet = $js_response['body'];
            $snippet = str_replace('${client_id}', $options['client_id'], $snippet);
            $snippet = str_replace('${post_id}', $post->ID, $snippet);    

            // If debugging, print response
            if ($print_debug_comment) {
              $printable_js_response = str_replace("-->", "", $js_response);
              echo "<!-- Ahalogy Mobilify JS Response (success): " . var_export($printable_js_response, TRUE) . " -->";          
            }

            // Print the snippet on the page
            echo $snippet;

            // Update the cached snippet
            $snippet_option_array = array(
              'snippet' => addslashes($js_response['body']),
              'timestamp' =>  time()
            );

            update_option('ahalogy_js_template', $snippet_option_array);
          } else {
            // If debugging, print response
            if ($print_debug_comment) {
              $printable_js_response = str_replace("-->", "", $js_response);
              echo "<!-- Ahalogy Mobilify JS Response (not accepted): " . var_export($printable_js_response, TRUE) . " -->";          
            }
          }
        }
      }
    }
  }   
      
  // Ping ahalogy when a post is saved or updated. Update the post meta field 'ahalogy_response' when their response
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

      // Here is where we will ping ahalogy's servers with new post information.
      if ( ( $options['client_id'] ) && ( isset($options['mobilify_optin']) ) && ( $options['mobilify_optin'] ) ) {
        
        // First we need to check that we've pasted the cache time for this post.
        $last_update = get_post_meta( $post_id, 'ahalogy_response', TRUE );
        
        $ping_ahalogy = false;

        if (!$last_update) {
          $ping_ahalogy = true;
        } else if ( time() - $last_update > $ahalogyWP_instance->cached_mobilify_request_limit )  {
          $ping_ahalogy = true;
        }

        if ($ping_ahalogy) {
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
          
          update_post_meta( $post_id, 'ahalogy_response', sanitize_text_field( time() ) );

          if ( is_wp_error( $response ) ) {
            //Setting the error but we won't do anything with it for now.
            $error_message = $response->get_error_message();
            if ($print_debug_comment) {
              echo "<!-- Ahalogy check for Ahalogy API notify change post: " . $response->get_error_message() . " -->";
            }
          }
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
    
    // Compatibility with Disqus plugin
    remove_action('loop_end', 'dsq_loop_end');
    
    $options = $ahalogyWP_instance->optionsGetOptions();

      //Check that is this is a single post
      if(is_single()) {
        if (isset($_REQUEST['mobilify_json'])) {
          if ($_REQUEST['mobilify_json'] == 1) {
            
            //Authenticate the api key
            $this->authenticateAPIKey();

            if ($options['mobilify_optin']) {
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

            // Return the registered post types
            $registered_post_types = get_post_types();
            foreach ($registered_post_types as $post_type) {
              $response['registered_post_types'][] = $post_type;
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

                  if (in_array($key, array('insert_code','mobilify_optin','mobilify_optin'))) {
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
            if ($options['mobilify_optin']) {

              //Pagination
              $paged = (isset($_GET["page"]) && $_GET['page'] !== '') ? $_GET["page"] : 1;
              $count = (isset($_GET["rpp"]) && $_GET['rpp'] !== '') ? $_GET["rpp"] : 100;
              
              $post_types = array();

              if (isset($_GET["post_types"]) && $_GET['post_types']) {
                $post_type = explode(',', $_GET["post_types"]);
              } else {
                $post_type = 'post';
              }
              

              // Arguments for WP_Query
              $args = 
                array( 
                  'posts_per_page' => $count,
                  'paged' => $paged,
                  'orderby' => 'modified',
                  'order' => 'DESC',
                  'post_type' => $post_type
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
      $data = array_merge(array('status' => $status), array('plugin_version' => $ahalogyWP_instance->plugin_version), $data);
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