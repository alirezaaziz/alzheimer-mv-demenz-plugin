<?php
/*
Plugin Name:	DAlzG MV - CWD (Customized Wayfinding Database) 
Description:	Für den "Deutsche Alzheimer Gesellschaft Landesverband Mecklenburg-Vorpommern e.V. Selbsthilfe Demenz" maßgeschneiderte Adressdatenbankverwaltung mit Umkreissuche zum Auffinden von Hilfseinrichtungen in der Nähe des vom Nutzer eingegebenen Standorts. 
Version:		1.0
Author:			Thomas Schuller
Author URI:   	http://customized.technology
*/

// Include WordPress bootstrap file if not running within WordPress
if (!defined('WPINC')) {
	require_once dirname(__FILE__) . '/bootstrap.php';
}

// Only try to include this file if WordPress is properly loaded
if (defined('WP_CONTENT_DIR')) {
	$email_template_path = WP_CONTENT_DIR . '/themes/alzheimermv-theme/includes/emails-senden-templete.php';
	if (file_exists($email_template_path)) {
		require $email_template_path;
	}
}

// Start the Plug-INs Session
if (!function_exists('register_my_session')) {
	function register_my_session()
	{
		// Check if session is already started or headers are already sent
		if (!session_id() && !headers_sent()) {
			// Use error suppression to prevent warnings about headers already sent
			@session_start();

			// Initialize session variables if not set
			if (!isset($_SESSION['cwd'])) {
				$_SESSION['cwd'] = array(
					'ort' => '',
					'stichwort' => '',
					'ids_in_stichwort' => null,
					'umkreis' => 15,
					'ids_in_umkreis' => null,
					'current_cat' => null
				);
			}
		}
	}
}

// Register the session function to WordPress init hook
add_action('init', 'register_my_session');

// Continue with the plugin class
class cwd
{
	function __construct()
	{
		/* Do nothing here - move everything to init() */
	}

	// Function to register the custom post types... 	
	function cwd_setup_post_types()
	{
		register_post_type(
			'cwd_einrichtung',
			[
				'labels'      => [
					'name'          => __('Einrichtungen'),
					'singular_name' => __('Einrichtung'),
					'add_new_item'  => __('Neue Einrichtung anlegen'),
					'edit_item'     => __('Einrichtung bearbeiten'),
				],
				'public'      => true,
				'show_ui'     => true,
				'has_archive' => false,
				'rewrite'     => ['slug' => __('einrichtung')],
				'capability_type' => 'post',
				'supports'        => array('title', 'revisions', 'thumbnail'),
				'show_in_menu' => 'cwd_menu',
				'taxonomies' => [
					'angebote',
					'emails_senden',
					'schlagworte'
				]
			]
		);

		$labels = array(
			'name'              => _x('Kategorien u. Angebote', 'taxonomy general name'),
			'singular_name'     => _x('Kategorie u. Angebote', 'taxonomy singular name'),
			'search_items'      => __('Suchen'),
			'all_items'         => __('Alle'),
			'parent_item'       => __('Übergeordnete Kategorie'),
			'parent_item_colon' => __('Übergeordnete Kategorie'),
			'edit_item'         => __('Kategorie/Angebot bearbeiten'),
			'update_item'       => __('Aktualisieren'),
			'add_new_item'      => __('Kategorie/Angebot hinzufügen'),
			'new_item_name'     => __('TEST'),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'public'            => false,
			'show_in_menu' 		=> false,
		);

		register_taxonomy('angebote', array('cwd_einrichtung'), $args);

		$labels = array(
			'name'              => _x('Emails senden', 'taxonomy general name'),
			'singular_name'     => _x('Emails senden', 'taxonomy singular name'),
			'search_items'      => __('Suchen'),
			'all_items'         => __('Alle'),
			'parent_item'       => __('Übergeordnete Kategorie'),
			'parent_item_colon' => __('Übergeordnete Kategorie'),
			'edit_item'         => __('Email Bearbeiten'),
			'update_item'       => __('Aktualisieren'),
			'add_new_item'      => __('Email Senden'),
			'new_item_name'     => __('TEST'),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'public'            => false,
			'show_in_menu' 		=> false,
		);

		register_taxonomy('emails_senden', array('cwd_einrichtung'), $args);

		$labels = array(
			'name' => _x('Tags', 'taxonomy general name'),
			'singular_name' => _x('Tag', 'taxonomy singular name'),
			'search_items' =>  __('Search Tags'),
			'popular_items' => __('Popular Tags'),
			'all_items' => __('All Tags'),
			'parent_item' => null,
			'parent_item_colon' => null,
			'edit_item' => __('Edit Tag'),
			'update_item' => __('Update Tag'),
			'add_new_item' => __('Add New Tag'),
			'new_item_name' => __('New Tag Name'),
			'separate_items_with_commas' => __('Separate tags with commas'),
			'add_or_remove_items' => __('Add or remove tags'),
			'choose_from_most_used' => __('Choose from the most used tags'),
			'menu_name' => __('Tags'),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'public'            => true,
			'show_in_menu' 		=> false,
		);

		register_taxonomy('schlagworte', array('cwd_einrichtung'), $args);

		flush_rewrite_rules();
	}

	function init()
	{
		// ---------------- //
		// --- LOAD ACF --- //
		// ---------------- //

		if (!class_exists('acf')) {
			if (!function_exists('get_field')) {
				add_action('admin_notices', function () {
					echo '<div class="notice notice-error"><p>Bitte installieren und aktivieren Sie das Plugin „Advanced Custom Fields“.</p></div>';
				});
				return;
			}
		}
		// --- LOAD FIELDS TO REGISTER: --- //

		function cwd_acf_add_local_field_groups()
		{
			// Register static fields
			include_once('fields/static_fields.php');
		}
		add_action('acf/init', 'cwd_acf_add_local_field_groups');

		// --- LOAD SHORTCODE: --- //

		include_once('frontend_templates/shortcode.php');

		// - END INIT ACF - //
		// ---------------- //

		// -------------------------------------------------- //
		// --- SET UP ADMIN MENU AND REGISTER POST TYPES ---- //
		// ------- ( + OPTION PAGE) ------------------------- //

		// Set up admin menu
		function cwd_admin_menu()
		{
			add_menu_page(
				'Einrichtungen',
				'Einrichtungen',
				'read',
				'cwd_menu',
				'', // Callback, leave empty
				'dashicons-calendar',
				2 // Position
			);
		}
		add_action('admin_menu', 'cwd_admin_menu');

		// Register custom post types
		add_action('init', array($this, 'cwd_setup_post_types'));

		// Add option page 			
		if (function_exists('acf_add_options_page')) {
			$option_page = acf_add_options_page(array(
				'parent_slug' 	=> 'cwd_menu',
				'page_titel' => 'Einstellungen',
				'menu_title' => 'Einstellungen',
				'menu_slug' => 'cwd_options'
			));
		}

		// Add custom taxonomy admin sub menu
		function cwd_custax_admin_sub_menu()
		{
			add_submenu_page(
				'cwd_menu',
				'Kategorien u. Angebote',
				'Kategorien u. Angebote',
				'read',
				'/edit-tags.php?taxonomy=angebote',
				'' // Callback, leave empty
			);
			add_submenu_page(
				'cwd_menu',
				'Emails senden',
				'Emails senden',
				'read',
				'/edit-tags.php?taxonomy=emails_senden',
				'emails_callback' // Callback, leave empty
			);

			add_submenu_page(
				'cwd_menu',
				'Schlagworte',
				'Schlagworte',
				'read',
				'/edit-tags.php?taxonomy=schlagworte',
				'' // Callback, leave empty
			);
		}
		add_action('admin_menu', 'cwd_custax_admin_sub_menu');

		// Add export admin sub menu
		function cwd_admin_sub_menu()
		{
			add_submenu_page(
				'cwd_menu',
				'Export',
				'Export',
				'read',
				'cwd_export_sub_menu',
				'cwd_export_submenu' // Callback
			);
		}
		add_action('admin_menu', 'cwd_admin_sub_menu');

		function cwd_export_submenu()
		{
			include_once('admin_page_export.php');
		}

		// ------ END ADMIN MENU, OPTION PAGE AND CPT ------- //
		// -------------------------------------------------- //

		// -------------------------------------------------- //
		// ------ CUSTOMIZATIONS & FUNCTIONS CTP + TAX ------ //
		// -------------------------------------------------- //

		// Set up Google Map Api Key for being used by ACF
		function my_acf_init()
		{
			acf_update_setting('google_api_key', get_field('cwd_gm_apikey', 'option'));
		}
		add_action('acf/init', 'my_acf_init');

		// Ger Terms herachically by TAX
		function cwd_custom_sort_get_terms_args($args, $taxonomies)
		{
			if (!empty($taxonomies) && is_array($taxonomies) && isset($taxonomies[0]) && $taxonomies[0] == 'angebote') {
				$args['orderby'] = 'term_order';
				$args['order']   = 'ASC';
			}
			return $args;
		}
		add_filter('get_terms_args', 'cwd_custom_sort_get_terms_args', 10, 2);

		//---- TAX Settings: ----//

		function my_taxonomy_query_cat($args, $field, $post_id)
		{
			// modify args
			$args['parent'] = 0;
			// return
			return $args;
		}
		add_filter('acf/fields/taxonomy/query/name=cwd_register_kategorie', 'my_taxonomy_query_cat', 10, 3);

		function my_taxonomy_query_services($args, $field, $post_id)
		{
			// modify args 
			$args['childless'] = true;
			// return
			return $args;
		}
		add_filter('acf/fields/taxonomy/query/name=cwd_register_angebote', 'my_taxonomy_query_services', 10, 3);

		function my_taxonomy_result_cat($value, $field, $post_id)
		{
			$filtered_values = array();
			if (!empty($value)) {
				foreach ($value as $term_id) {
					if (get_term($term_id, 'angebote')->parent == 0) {
						$filtered_values[] = $term_id;
					}
				}
			}
			return $filtered_values;
		}
		add_filter('acf/load_value/name=cwd_register_kategorie', 'my_taxonomy_result_cat', 10, 3);

		function my_taxonomy_result_services($value, $field, $post_id)
		{
			$filtered_values = array();
			if (!empty($value)) {
				foreach ($value as $term_id) {
					if (get_term($term_id, 'angebote')->parent != 0) {
						$filtered_values[] = $term_id;
					}
				}
			}
			return $filtered_values;
		}
		add_filter('acf/load_value/name=cwd_register_angebote', 'my_taxonomy_result_services', 10, 3);

		function cwd_acf_featured_image($value, $post_id, $field)
		{
			if ($value != '') {
				update_post_meta($post_id, '_thumbnail_id', $value);
			}
			return;
		}
		add_filter('acf/update_value/name=cwd_thumbnail', 'cwd_acf_featured_image', 10, 3);

		// Via FRONTEND Form saved new "Einrichtung"-CPT
		function cwd_pre_save_post($post_id)
		{
			if ($post_id == 'new_post') {
				$contact_person = array_merge(
					array_values($_POST["acf"]["field_5bac767e03b4f"]),
					array_values($_POST["acf"]["field_5bac775303b50"]),
					array_values($_POST["acf"]["field_5bac77b503b51"]),
					array_values($_POST["acf"]["field_5bac77f303b52"])
				);

				// Send E-Mail notification to admin
				$subject = 'Neue Einrichtung eingetragen';

				$body =    '<html><head></head><body>
                                <h3>Es wurde eine neue Einrichtung eingetragen.</h3>
    
                                <p>Auf ' . get_site_url() . ' wurden Daten zur Eintragung einer neuen Einrichtung übermittelt,
                                <br />die Einrichtung wurde unter folgendem Titel als Entwurf gespeichert:<br />
    
                                <br /><b>"' . $_POST['acf']['_post_title'] . '"</b><br />
     
                                <br />Bitte überprüfen Sie die Daten vor Veröffentlichung der Einrichtung!</p>
                                
                                <br />
                                <p>Für Rückfragen wurde folgender Kontakt angegeben: <br />
    
                                <br />Name: <b>' . $contact_person[0] . '</b>
                                <br />Position: <b>' . $contact_person[1] . '</b>
                                <br />Tel.: <b>' . $contact_person[2] . '</b>
                                <br />E-Mail: <b>' . $contact_person[3] . '</b>
    
                                <br /><br /></p>
    			                </body></html>';

				$to =      get_field('cwd_admin_mail', 'option');

				$headers = array('Content-Type: text/html; charset=UTF-8');
				$headers[] = 'From: Adressdatenbank <noreply@' . str_replace(array('http://', 'https://', 'www.'), '', $_SERVER['SERVER_NAME']) . '>';

				wp_mail($to, $subject, $body, $headers);
			}

			return $post_id;
		}

		add_filter('acf/pre_save_post', 'cwd_pre_save_post', 1, 1);

		// Via BACKEND saved new "Einrichtung"-CPT				
		function cwd_einrichtung_saved($post_id)
		{
			if (get_post_type($post_id) != 'cwd_einrichtung') {
				return;
			}

			$_SESSION['cwd']['new_einrichtung'] = $post_id;

			if (get_field('cwd_standorteingabe', $post_id)) {
				$location = get_field('cwd_manueller_standort', $post_id);

				if ($location['lat'] != get_field('cwd_lat', $post_id)) {
					update_field('cwd_lat', $location['lat'], $post_id);
				}

				if ($location['lng'] != get_field('cwd_lng', $post_id)) {
					update_field('cwd_lng', $location['lng'], $post_id);
				}
			} else {
				$address = get_field('cwd_str', $post_id) . ' ' . get_field('cwd_hnr', $post_id) . ', ' . get_field('cwd_plz', $post_id) . ' ' . get_field('cwd_ort', $post_id);

				$location = [
					'address' => $address,
					'lat'     => '',
					'lng'     => ''
				];

				$address = str_replace(' ', '+', $address);

				$gm_json_url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . $address . '&key=' . get_field('cwd_gm_apikey', 'option');
				$gm_loc_json = file_get_contents($gm_json_url);

				$loc_array = json_decode($gm_loc_json, true)['results'][0]['geometry']['location'];

				$location['lat'] = $loc_array['lat'];
				$location['lng'] = $loc_array['lng'];

				if (get_field('cwd_lat', $post_id) != $location['lat'] || get_field('cwd_lng', $post_id) != $location['lng']) {
					update_field('cwd_manueller_standort', $location, $post_id);
					update_field('cwd_lat', $location['lat'], $post_id);
					update_field('cwd_lng', $location['lng'], $post_id);
				}
			}
		}
		add_action('acf/save_post', 'cwd_einrichtung_saved', 20);

		// -------------------------- //
		// --- TEMPLATE REDIRECTS --- //
		// -------------------------- //

		// Template redirect for plug-in pages
		function cwd_get_custom_page_template($page_template)
		{
			global $post;

			if ($post->ID == get_field('cwd_mainview_id', 'option')) {
				$page_template = dirname(__FILE__) . '/frontend_templates/adressdatenbank.php';
			}

			if ($post->ID == get_field('cwd_registerview_id', 'option')) {
				$page_template = dirname(__FILE__) . '/frontend_templates/einrichtung_eintragen.php';
			}

			return $page_template;
		}
		add_filter("page_template", "cwd_get_custom_page_template");

		// CPT template redirect
		function cwd_get_custom_post_type_template($single_template)
		{
			global $post;

			if ($post->post_type == 'cwd_einrichtung') {
				$single_template = dirname(__FILE__) . '/frontend_templates/einrichtung_single.php';
			}

			return $single_template;
		}
		add_filter('single_template', 'cwd_get_custom_post_type_template');

		// Redirect for the export download link
		function cwd_download_link()
		{
			if ($_SERVER['REQUEST_URI'] == get_site_url(null, '', 'relative') . '/cwd_download_export' && current_user_can('administrator')) {
				header("Content-type: application/csv; charset=utf-8", true, 200);
				header("Content-Disposition: attachment; filename=export_" . time() . ".csv");
				header('Content-Length: ' . filesize(dirname(__FILE__) . '/export_temp/temp.csv'));
				header("Pragma: no-cache");
				header("Expires: 0");

				readfile(dirname(__FILE__) . '/export_temp/temp.csv');

				exit();
			}
		}
		add_action('template_redirect', 'cwd_download_link');

		// ------------------- //
		// --- ADMIN STUFF --- //
		// ------------------- //

		if (is_admin()) {
			// Add admin styles if we are on backend screen
			function enqueue_cwd_admin_scripts_styles()
			{
				wp_enqueue_style('cwd_admin_styles',  plugin_dir_url(__FILE__) . 'admin_styles.css');
				wp_enqueue_script('cwd_admin_scripts',  plugin_dir_url(__FILE__) . 'admin_scripts.js', array('jquery'));
			}
			add_action('admin_enqueue_scripts', 'enqueue_cwd_admin_scripts_styles', 1);

			// Change admin display for cpt and taxonomy:
			function cwd_replace_column_title($posts_columns)
			{
				$posts_columns['taxonomy-angebote'] = 'Kategorie u. Angebote';
				return $posts_columns;
			}
			add_filter('manage_cwd_einrichtung_posts_columns', 'cwd_replace_column_title');

			function cwd_change_meta_box_title($post_type)
			{
				global $wp_meta_boxes;

				if ('cwd_einrichtung' == $post_type) {
					$wp_meta_boxes['cwd_einrichtung']['side']['core']['angebotediv']['title'] = 'Kategorie u. Angebote';
				}
			}
			add_action('add_meta_boxes',  'cwd_change_meta_box_title');

			function cwd_change_title_text($title)
			{
				$screen = get_current_screen();

				if ('cwd_einrichtung' == $screen->post_type) {
					$title = 'Name der Einrichtung / des Anbieters';
				}

				return $title;
			}
			add_filter('enter_title_here', 'cwd_change_title_text');

			if (! function_exists('mbe_set_current_menu')) {
				function mbe_set_current_menu($parent_file)
				{
					global $submenu_file;
					$current_screen = get_current_screen();

					if ($current_screen->taxonomy == 'angebote') {
						$submenu_file = 'edit-tags.php?taxonomy=angebote';
						$parent_file = 'cwd_menu';
					}

					return $parent_file;
				}

				add_filter('parent_file', 'mbe_set_current_menu');
			}

			function cwd_no_reorder_cats($args, $post_id)
			{
				if (get_post_type($post_id) == 'cwd_einrichtung') {
					$args['checked_ontop'] = false;
				}
				return $args;
			}
			add_filter('wp_terms_checklist_args', 'cwd_no_reorder_cats', 10, 2);
		} // ADMIN - END			

	} // init() - END
} // Main class - END

function cwd()
{
	global $cwd;

	// Return the Plug-INs only instance...		
	if (!isset($cwd)) {
		$cwd = new cwd();
		$cwd->init();
	}
	return $cwd;
}
cwd(); // "Start" the Plug-IN...