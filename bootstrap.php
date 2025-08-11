<?php
/**
 * Bootstrap file for loading WordPress core when running outside of WordPress
 */

// Define WordPress constants if not already defined
if (!defined('ABSPATH')) {
    // Try to find wp-load.php by going up directories
    $path = dirname(__FILE__);
    $max_depth = 5;
    $found = false;
    
    while ($max_depth > 0) {
        if (file_exists($path . '/wp-load.php')) {
            $found = true;
            require_once($path . '/wp-load.php');
            break;
        }
        
        // Go up one directory
        $path = dirname($path);
        $max_depth--;
    }
    
    if (!$found) {
        // If we couldn't find wp-load.php, define essential WordPress constants
        define('WPINC', 'wp-includes');
        define('WP_CONTENT_DIR', dirname(dirname(__FILE__)) . '/wp-content');
        
        // Define common WordPress functions if they don't exist
        if (!function_exists('__')) {
            function __($text, $domain = 'default') {
                return $text;
            }
        }
        
        if (!function_exists('_x')) {
            function _x($text, $context, $domain = 'default') {
                return $text;
            }
        }
        
        if (!function_exists('add_action')) {
            function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
                return true;
            }
        }
        
        if (!function_exists('add_filter')) {
            function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
                return true;
            }
        }
        
        if (!function_exists('plugin_dir_url')) {
            function plugin_dir_url($file) {
                return '/wp-content/plugins/' . basename(dirname($file)) . '/';
            }
        }
        
        if (!function_exists('wp_enqueue_style')) {
            function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all') {
                return true;
            }
        }
        
        if (!function_exists('wp_enqueue_script')) {
            function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) {
                return true;
            }
        }
        
        if (!function_exists('get_header')) {
            function get_header($name = null) {
                return true;
            }
        }
        
        if (!function_exists('get_footer')) {
            function get_footer($name = null) {
                return true;
            }
        }
        
        if (!function_exists('have_posts')) {
            function have_posts() {
                return false;
            }
        }
        
        if (!function_exists('the_post')) {
            function the_post() {
                return false;
            }
        }
        
        if (!function_exists('the_content')) {
            function the_content($more_link_text = null, $strip_teaser = false) {
                return '';
            }
        }
        
        if (!function_exists('sanitize_text_field')) {
            function sanitize_text_field($str) {
                return trim(strip_tags($str));
            }
        }
        
        if (!function_exists('get_terms')) {
            function get_terms($args = array()) {
                return array();
            }
        }
        
        if (!function_exists('get_permalink')) {
            function get_permalink($post = 0, $leavename = false) {
                return '#';
            }
        }
        
        if (!function_exists('get_the_permalink')) {
            function get_the_permalink($post = 0) {
                return get_permalink($post);
            }
        }
        
        if (!function_exists('the_title')) {
            function the_title($before = '', $after = '', $echo = true) {
                return $before . 'Title' . $after;
            }
        }
        
        if (!function_exists('get_query_var')) {
            function get_query_var($var, $default = '') {
                return $default;
            }
        }
        
        if (!function_exists('wp_get_post_terms')) {
            function wp_get_post_terms($post_id, $taxonomy, $args = array()) {
                return array();
            }
        }
        
        if (!function_exists('get_the_id')) {
            function get_the_id() {
                return 0;
            }
        }
        
        if (!function_exists('get_the_title')) {
            function get_the_title($post = 0) {
                return 'Title';
            }
        }
        
        if (!function_exists('paginate_links')) {
            function paginate_links($args = '') {
                return '';
            }
        }
        
        if (!function_exists('esc_url')) {
            function esc_url($url, $protocols = null, $_context = 'display') {
                return $url;
            }
        }
        
        if (!function_exists('get_pagenum_link')) {
            function get_pagenum_link($pagenum = 1, $escape = true) {
                return '#';
            }
        }
        
        if (!function_exists('wp_reset_postdata')) {
            function wp_reset_postdata() {
                return true;
            }
        }
        
        if (!function_exists('do_shortcode')) {
            function do_shortcode($content, $ignore_html = false) {
                return $content;
            }
        }
        
        if (!function_exists('get_term_by')) {
            function get_term_by($field, $value, $taxonomy = '', $output = OBJECT, $filter = 'raw') {
                return false;
            }
        }
        
        if (!function_exists('get_term')) {
            function get_term($term, $taxonomy = '', $output = OBJECT, $filter = 'raw') {
                return false;
            }
        }
        
        if (!function_exists('register_post_type')) {
            function register_post_type($post_type, $args = array()) {
                return false;
            }
        }
        
        if (!function_exists('register_taxonomy')) {
            function register_taxonomy($taxonomy, $object_type, $args = array()) {
                return false;
            }
        }
        
        if (!function_exists('flush_rewrite_rules')) {
            function flush_rewrite_rules($hard = true) {
                return false;
            }
        }
        
        if (!function_exists('add_menu_page')) {
            function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '', $position = null) {
                return false;
            }
        }
        
        if (!function_exists('add_submenu_page')) {
            function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '') {
                return false;
            }
        }
        
        if (!function_exists('get_post')) {
            function get_post($post = null, $output = OBJECT, $filter = 'raw') {
                return null;
            }
        }
        
        if (!function_exists('get_post_type')) {
            function get_post_type($post = null) {
                return false;
            }
        }
        
        if (!function_exists('update_post_meta')) {
            function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '') {
                return false;
            }
        }
        
        if (!function_exists('get_site_url')) {
            function get_site_url($blog_id = null, $path = '', $scheme = null) {
                return 'http://example.com';
            }
        }
        
        if (!function_exists('wp_mail')) {
            function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
                return false;
            }
        }
        
        if (!function_exists('current_user_can')) {
            function current_user_can($capability) {
                return true;
            }
        }
        
        if (!function_exists('is_admin')) {
            function is_admin() {
                return false;
            }
        }
        
        if (!function_exists('get_current_screen')) {
            function get_current_screen() {
                return (object) array('id' => '', 'base' => '');
            }
        }
        
        if (!class_exists('WP_Query')) {
            class WP_Query {
                public $posts = array();
                public $post_count = 0;
                public $found_posts = 0;
                public $max_num_pages = 0;
                
                public function __construct($query = '') {
                    // Empty constructor
                }
                
                public function have_posts() {
                    return false;
                }
                
                public function the_post() {
                    return false;
                }
            }
        }
        
        // Define WordPress constants if not already defined
        if (!defined('OBJECT')) {
            define('OBJECT', 'OBJECT');
        }
        
        if (!defined('ARRAY_A')) {
            define('ARRAY_A', 'ARRAY_A');
        }
        
        if (!defined('ARRAY_N')) {
            define('ARRAY_N', 'ARRAY_N');
        }
    }
}