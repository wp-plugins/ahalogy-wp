<?php
class Mobilify_JSON_API_Post {
  
  // JSON_API_Post objects must be instantiated within The Loop.
  
  var $id;              // Integer
  var $type;            // String
  var $slug;            // String
  var $url;             // String
  var $status;          // String ("draft", "published", or "pending")
  var $title;           // String
  var $title_plain;     // String
  var $content;         // String (modified by read_more query var)
  var $raw_content;     // String
  var $excerpt;         // String
  var $date;            // String (modified by date_format query var)
  var $modified;        // String (modified by date_format query var)
  var $author;          // Object
  var $attachments;     // Array of objects
  var $featured_image;   // Object
  
  function Mobilify_JSON_API_Post($wp_post = null) {
    if (!empty($wp_post)) {
      $this->import_wp_object($wp_post);
    }
  }

  function import_wp_object($wp_post) {
    global $post;
    global $ahalogyWP_instance;

    $this->id = (int) $wp_post->ID;
    setup_postdata($wp_post);
    $this->set_value('type', $wp_post->post_type);
    $this->set_value('slug', $wp_post->post_name);
    $this->set_value('url', get_permalink($this->id));
    $this->set_value('status', $wp_post->post_status);
    $this->set_value('title', get_the_title($this->id));
    $this->set_value('title_plain', strip_tags(@$this->title));
    $this->set_content_value();
    $this->set_value('raw_content', wpautop($post->post_content));
    $this->set_value('excerpt', apply_filters('the_excerpt', get_the_excerpt()));
    $this->set_value('date', get_the_time($ahalogyWP_instance->date_format));
    $this->set_value('modified', date($ahalogyWP_instance->date_format, strtotime($wp_post->post_modified)));
    $this->set_author_value($wp_post->post_author);
    $this->set_attachments_value();
    $this->set_featured_image();
  }
  
  function set_value($key, $value) {
    $this->$key = html_entity_decode($value, ENT_COMPAT, 'UTF-8');
  }
    
  function set_content_value() {
    $content = get_the_content("Read more");
    $content = apply_filters('the_content', $content);
    $content = str_replace(']]>', ']]&gt;', $content);
    $this->content = $content;
  }

  function print_filters_for( $hook = '' ) {
    global $wp_filter;
    if( empty( $hook ) || !isset( $wp_filter[$hook] ) )
        return;

    print '<pre>';
    print_r( $wp_filter[$hook] );
    print '</pre>';
  }
      
  function set_author_value($author_id) {
    $this->author = new Mobilify_JSON_API_Author($author_id);
  }
    
  function set_attachments_value() {
    $wp_attachments = get_children(array(
      'post_type' => 'attachment',
      'post_parent' => $this->id,
      'orderby' => 'menu_order',
      'order' => 'ASC',
      'suppress_filters' => false
    ));
    $attachments = array();

    if (!empty($wp_attachments)) {
      foreach ($wp_attachments as $wp_attachment) {
        $attachments[] = new Ahalogy_JSON_API_Attachment($wp_attachment);
      }
    }

    $this->attachments = $attachments;
  }
  
  function set_featured_image() {
    $attachment_id = get_post_thumbnail_id($this->id);

    if (!$attachment_id) {
      unset($this->thumbnail);
      return;
    }

    $wp_attachment = get_post($attachment_id);
    $attachment = new Ahalogy_JSON_API_Attachment($wp_attachment);
    $this->featured_image = $attachment;
  }
}