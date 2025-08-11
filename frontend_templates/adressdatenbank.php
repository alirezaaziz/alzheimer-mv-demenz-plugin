<?php
/*
 Template Name:	Adressdatenbank Template file (Fixed Version)
 Description:	Template zur Visualisierung der Adressdatenbank
 Author:		T.
 Author URI:   	http://customized.technology
 */

wp_enqueue_style('cwd_adressdb_styles',  plugin_dir_url(__FILE__) . 'css/adressdb_styles.css');
wp_enqueue_script('cwd_adressdb_scripts',  plugin_dir_url(__FILE__) . 'js/adressdb_scripts.js', array('jquery'));

// Load Google Maps only if API key is valid
$api_key = get_field('cwd_gm_apikey', 'option');
if (!empty($api_key)) {
    wp_enqueue_script('cwd_googlemaps_scripts', 'https://maps.googleapis.com/maps/api/js?key=' . $api_key, array('jquery'));
}

wp_enqueue_script('printThis',  plugin_dir_url(__FILE__) . 'js/printThis.js', array('jquery'));
wp_enqueue_script('customPrint',  plugin_dir_url(__FILE__) . 'js/customPrint.js', array('jquery'));

// Load theme header
get_header();

// Display the pages content
if (have_posts()) :
    while (have_posts()) : the_post();
        the_content();
    endwhile;
else :
endif;

// Improved geocoding function
function cwd_get_geocoding_data($address, $api_key)
{
    if (empty($address) || empty($api_key)) {
        return false;
    }

    $address = sanitize_text_field($address);
    $address = urlencode($address);
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key={$api_key}&region=de";

    $response = wp_remote_get($url, array(
        'timeout' => 10,
        'headers' => array(
            'User-Agent' => 'WordPress/' . get_bloginfo('version')
        )
    ));

    if (is_wp_error($response)) {
        if (defined('CWD_DEBUG') && CWD_DEBUG) {
            error_log('CWD Geocoding Error: ' . $response->get_error_message());
        }
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (defined('CWD_DEBUG') && CWD_DEBUG) {
        error_log('CWD Geocoding Response: ' . print_r($data, true));
    }

    if ($data && $data['status'] === 'OK' && !empty($data['results'])) {
        return $data['results'][0]['geometry']['location'];
    }

    return false;
}

// Improved nearby locations function
function cwd_get_nearby_locations($lat, $lng, $radius = 15)
{
    global $wpdb;

    // Validate input
    if (!is_numeric($lat) || !is_numeric($lng) || !is_numeric($radius)) {
        return array();
    }

    // Limit radius
    $radius = min(max($radius, 1), 200);

    $query = $wpdb->prepare("
        SELECT p.ID,
               (6371 * acos(
                   cos(radians(%f)) * 
                   cos(radians(CAST(lat_meta.meta_value AS DECIMAL(10,8)))) * 
                   cos(radians(CAST(lng_meta.meta_value AS DECIMAL(11,8))) - radians(%f)) + 
                   sin(radians(%f)) * 
                   sin(radians(CAST(lat_meta.meta_value AS DECIMAL(10,8))))
               )) AS distance
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} lat_meta ON p.ID = lat_meta.post_id
        INNER JOIN {$wpdb->postmeta} lng_meta ON p.ID = lng_meta.post_id
        WHERE p.post_type = 'cwd_einrichtung'
          AND p.post_status = 'publish'
          AND lat_meta.meta_key = 'cwd_lat'
          AND lng_meta.meta_key = 'cwd_lng'
          AND lat_meta.meta_value != ''
          AND lng_meta.meta_value != ''
          AND lat_meta.meta_value IS NOT NULL
          AND lng_meta.meta_value IS NOT NULL
        HAVING distance <= %d
        ORDER BY distance ASC
        LIMIT 500
    ", $lat, $lng, $lat, $radius);

    $results = $wpdb->get_results($query);

    if (defined('CWD_DEBUG') && CWD_DEBUG) {
        error_log('CWD Nearby Query: ' . $query);
        error_log('CWD Nearby Results: ' . count($results) . ' found');
    }

    return $results ? array_column($results, 'ID') : array();
}

// Ensure session is started
function cwd_ensure_session()
{
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }

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

cwd_ensure_session();

// SANITIZE USER INPUTS
function cwd_recursive_sanitize_text_field($array)
{
    if (!is_array($array)) {
        return sanitize_text_field($array);
    }

    foreach ($array as $key => &$value) {
        if (is_array($value)) {
            $value = cwd_recursive_sanitize_text_field($value);
        } else {
            $value = sanitize_text_field($value);
        }
    }
    return $array;
}

$_GET = cwd_recursive_sanitize_text_field($_GET);
$_POST = cwd_recursive_sanitize_text_field($_POST);

if (!isset($_GET['kategorie'])) {
    unset($_SESSION['cwd']);
    cwd_ensure_session();
} else {
    if (isset($_GET['kategorie']) && $_GET['kategorie'] == 'clear') {
        unset($_GET['kategorie']);
    }
}

if (isset($_GET['kategorie'])) {
    // Check category change and reset specific session data
    if ($_SESSION['cwd']['current_cat'] != $_GET['kategorie']) {
        $_SESSION['cwd']['angebote'] = null;
        $_SESSION['cwd']['current_cat'] = $_GET['kategorie'];
    }

    // Check for search filters and update session
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['cwd_filter_submitted'])) {
        if (!empty($_POST['cwd_angebote'])) {
            $_SESSION['cwd']['angebote'] = $_POST['cwd_angebote'];
        } else {
            $_SESSION['cwd']['angebote'] = null;
        }
    }
} elseif (!empty($_SESSION['cwd']['current_cat'])) {
    $_SESSION['cwd']['current_cat'] = null;
    $_SESSION['cwd']['angebote'] = null;
}

// Check search and write session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['cwd_filter_submitted'])) {

    $_SESSION['cwd']['ort'] = !empty($_POST['cwd_ort']) ? $_POST['cwd_ort'] : '';
    $_SESSION['cwd']['stichwort'] = !empty($_POST['cwd_stichwort']) ? $_POST['cwd_stichwort'] : '';
    $_SESSION['cwd']['umkreis'] = !empty($_POST['cwd_umkreis']) ? intval($_POST['cwd_umkreis']) : 15;

    // Location search
    if (!empty($_POST['cwd_ort'])) {
        $address = $_POST['cwd_ort'] . ', Deutschland';

        $location = cwd_get_geocoding_data($address, get_field('cwd_gm_apikey', 'option'));

        if ($location && isset($location['lat']) && isset($location['lng'])) {
            $lat = floatval($location['lat']);
            $lng = floatval($location['lng']);
            $radius = intval($_SESSION['cwd']['umkreis']);

            $nearby_ids = cwd_get_nearby_locations($lat, $lng, $radius);

            $_SESSION['cwd']['ids_in_umkreis'] = !empty($nearby_ids) ? $nearby_ids : array(-1);

            if (defined('CWD_DEBUG') && CWD_DEBUG) {
                error_log('CWD Location Search: ' . $address . ' -> ' . $lat . ',' . $lng);
                error_log('CWD Nearby IDs: ' . print_r($_SESSION['cwd']['ids_in_umkreis'], true));
            }
        } else {
            $_SESSION['cwd']['ids_in_umkreis'] = array(-1);

            if (defined('CWD_DEBUG') && CWD_DEBUG) {
                error_log('CWD Location Search Failed: ' . $address);
            }
        }
    } else {
        $_SESSION['cwd']['ids_in_umkreis'] = null;
    }

    // Keyword search
    if (!empty($_POST['cwd_stichwort'])) {
        $keywords = preg_split("/[\s,]+/", $_POST['cwd_stichwort']);
        $term_ids = array();
        $ids_in_search = array();

        foreach ($keywords as $keyword) {
            if (strlen(trim($keyword)) > 2) { // Only search for keywords longer than 2 characters
                $terms = get_terms(array(
                    'taxonomy' => 'angebote',
                    'name__like' => trim($keyword),
                    'hide_empty' => false
                ));

                foreach ($terms as $term) {
                    if ($term->parent != 0) {
                        $term_ids[] = $term->term_id;
                    }
                }
            }
        }

        $term_ids = array_unique($term_ids);

        if (!empty($term_ids)) {
            $args = array(
                'posts_per_page' => -1,
                'post_type' => 'cwd_einrichtung',
                'post_status' => 'publish',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'angebote',
                        'field' => 'term_id',
                        'terms' => $term_ids
                    )
                ),
                'fields' => 'ids'
            );
            $custom_query = new WP_Query($args);
            $ids_in_search = array_merge($ids_in_search, $custom_query->posts);
        }

        // Also search in post titles and content
        $args = array(
            'posts_per_page' => -1,
            'post_type' => 'cwd_einrichtung',
            'post_status' => 'publish',
            's' => $_POST['cwd_stichwort'],
            'fields' => 'ids'
        );
        $custom_query = new WP_Query($args);
        $ids_in_search = array_merge($ids_in_search, $custom_query->posts);

        $ids_in_search = array_unique($ids_in_search);

        $_SESSION['cwd']['ids_in_stichwort'] = !empty($ids_in_search) ? $ids_in_search : array(-1);
    } else {
        $_SESSION['cwd']['ids_in_stichwort'] = null;
    }
}

$request_base_url = get_permalink(get_field('cwd_mainview_id', 'option'));
$request_cat_url = get_permalink(get_field('cwd_mainview_id', 'option'));

if (!empty($_GET['kategorie'])) {
    $request_cat_url .= '?kategorie=' . $_GET['kategorie'];
}

// START OUTPUTTING THE VIEW: 
?>

<div id="cwd_main" class="cwd_container">

    <div id="cwd_main_header">
        <h1><?php the_title(); ?></h1>
        <a id="cwd_register_link" href="<?php echo get_permalink(get_field('cwd_registerview_id', 'option')); ?>">neues Angebot eintragen &raquo;</a>
    </div>

    <div id="Karte" class="cwd_anker"></div>
    <div id="cwd_search_map">
        <form id="cwd_main_search" method="POST" action="<?php echo $request_cat_url; ?>" style="position: relative;">
            <div class="before-block" style="position: absolute; left: -7%; font-size: 6em; top: -26%; font-weight: 700; color: rgba(227, 6, 19, 0.15); z-index: -1;">1</div>

            <h3 class="cwd_subtitle new_subtitle">Stichwortsuche:</h3>
            <input id="cwd_stichwort" class="cwd_textinput" name="cwd_stichwort" type="text" placeholder="Stichwort eingeben..." value="<?php echo isset($_SESSION['cwd']['stichwort']) ? esc_attr($_SESSION['cwd']['stichwort']) : ''; ?>" />
            <p>Geben Sie hier ein Stichwort, wie z.B. ein Angebot oder Einrichtungsname, nach welchem gesucht werden soll, ein.</p>

            <h3 class="cwd_subtitle new_subtitle">Umkreissuche:</h3>
            <input id="cwd_ort" class="cwd_textinput" name="cwd_ort" type="text" placeholder="PLZ und/oder Ort eingeben..." value="<?php echo isset($_SESSION['cwd']['ort']) ? esc_attr($_SESSION['cwd']['ort']) : ''; ?>" />

            <select id="cwd_umkreis" class="cwd_select" name="cwd_umkreis">
                <option value="15" <?php if (isset($_SESSION['cwd']['umkreis']) && $_SESSION['cwd']['umkreis'] == 15) echo 'selected' ?>>15 km</option>
                <option value="25" <?php if (isset($_SESSION['cwd']['umkreis']) && $_SESSION['cwd']['umkreis'] == 25) echo 'selected' ?>>25 km</option>
                <option value="50" <?php if (isset($_SESSION['cwd']['umkreis']) && $_SESSION['cwd']['umkreis'] == 50) echo 'selected' ?>>50 km</option>
                <option value="100" <?php if (isset($_SESSION['cwd']['umkreis']) && $_SESSION['cwd']['umkreis'] == 100) echo 'selected' ?>>100 km</option>
                <option value="150" <?php if (isset($_SESSION['cwd']['umkreis']) && $_SESSION['cwd']['umkreis'] == 150) echo 'selected' ?>>150 km</option>
            </select>
            <span class="cwd_cap"> Umkreis</span>

            <input id="cwd_submit_search" class="cwd_btn" type="submit" value="Suchen" />

            <hr style="border-bottom: 1px solid #e32212; margin-bottom: 2rem !important; margin-top: 2rem !important;">
        </form>

        <?php
        // Combine search results
        $search_ids = array();

        // Ensure session variables are set
        cwd_ensure_session();

        if ((isset($_SESSION['cwd']['ids_in_umkreis']) && $_SESSION['cwd']['ids_in_umkreis'] == array(-1)) ||
            (isset($_SESSION['cwd']['ids_in_stichwort']) && $_SESSION['cwd']['ids_in_stichwort'] == array(-1))
        ) {
            $search_ids = array(-1);
        } elseif (
            isset($_SESSION['cwd']['ids_in_umkreis']) && $_SESSION['cwd']['ids_in_umkreis'] != null &&
            isset($_SESSION['cwd']['ids_in_stichwort']) && $_SESSION['cwd']['ids_in_stichwort'] != null
        ) {
            $search_ids = array_intersect($_SESSION['cwd']['ids_in_umkreis'], $_SESSION['cwd']['ids_in_stichwort']);
        } elseif (!isset($_SESSION['cwd']['ids_in_umkreis']) || $_SESSION['cwd']['ids_in_umkreis'] == null) {
            $search_ids = isset($_SESSION['cwd']['ids_in_stichwort']) ? $_SESSION['cwd']['ids_in_stichwort'] : array();
        } else {
            $search_ids = isset($_SESSION['cwd']['ids_in_umkreis']) ? $_SESSION['cwd']['ids_in_umkreis'] : array();
        }

        $args = array(
            'posts_per_page' => -1,
            'post_type' => 'cwd_einrichtung',
            'post_status' => 'publish'
        );

        if (!empty($search_ids)) {
            $args['post__in'] = $search_ids;
            $args['orderby'] = 'post__in';
        } else {
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
        }

        if (!empty($_GET['kategorie'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'angebote',
                    'field' => 'slug',
                    'terms' => $_GET['kategorie']
                )
            );
        }

        if (!empty($_SESSION['cwd']['angebote'])) {
            $subs = array_values($_SESSION['cwd']['angebote']);
            $array = array('relation' => 'OR');

            foreach ($subs as $sub) {
                array_push($array, array(
                    'taxonomy' => 'angebote',
                    'field' => 'term_id',
                    'terms' => $sub
                ));
            }

            if (!isset($args['tax_query'])) {
                $args['tax_query'] = array();
            }
            $args['tax_query'][] = $array;
        }

        $custom_query = new WP_Query($args);
        $geocodes = array();
        $result_count = $custom_query->found_posts;
        $result_list = '';

        if ($custom_query->have_posts()) :
            while ($custom_query->have_posts()) : $custom_query->the_post();
                ob_start(); ?>

                <div id="<?php echo $post->post_name; ?>" class="cwd_result_anker"></div>
                <div class="cwd_result">
                    <a class="cwd_result_link" target="_blank" title="Details zur Einrichtung anzeigen &raquo" href="<?php echo get_the_permalink(); ?>"></a>

                    <p class="cwd_name">
                        <strong><?php the_title(); ?></strong>
                        <?php if (get_field('cwd_contact')) {
                            echo ('<br /><strong>Kontakt: </strong>');
                        }
                        the_field('cwd_contact'); ?>
                        <?php if (get_field('cwd_tel')) {
                            echo ('<br /><strong>Tel: </strong>');
                        }
                        the_field('cwd_tel'); ?>
                        <?php if (get_field('cwd_mail')) {
                            echo ('<br /><strong>Mail: </strong>');
                        }
                        the_field('cwd_mail'); ?>
                    </p>

                    <div class="cwd_address">
                        <p><?php the_field('cwd_str'); ?> <?php the_field('cwd_hnr'); ?></p>
                        <p><?php the_field('cwd_plz'); ?> <?php the_field('cwd_ort'); ?></p>
                    </div>

                    <?php $terms = wp_get_post_terms(get_the_id(), 'angebote', array('orderby' => 'term_order')); ?>

                    <div class="cwd_services">
                        <?php foreach ($terms as $term) {
                            if ($term->parent != 0) {
                                echo '<p class="cwd_serv_btn">' . esc_html($term->name) . '</p>';
                            }
                        } ?>
                    </div>
                </div>

        <?php $result_list .= ob_get_clean();

                $i = count($geocodes);
                $geocodes[$i]['name'] = get_the_title();
                $geocodes[$i]['link'] = get_the_permalink();
                $geocodes[$i]['lat']  = get_field('cwd_lat', get_the_id());
                $geocodes[$i]['lng']  = get_field('cwd_lng', get_the_id());

            endwhile;

            ob_start();
            echo '<div class="cwd_page_nav">';

            $big = 999999999;
            $pagelinks = paginate_links(array(
                'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                'format' => '?paged=%#%',
                'current' => max(1, get_query_var('paged')),
                'total' => $custom_query->max_num_pages,
                'add_fragment' => '#Liste',
            ));

            if ($pagelinks != '') {
                echo $pagelinks;
            } else {
                echo '<span class="page-numbers current">1</span>';
            }

            echo '</div>';
            $result_list .= ob_get_clean();

        else :
        // No posts found
        endif;

        wp_reset_postdata();
        ?>

        <div class="acf-map<?php if (empty($geocodes)) echo '-placeholder'; ?>">
            <?php
            if (empty($geocodes)) {
                echo '<div class="cwd_overlay-w"><p class="cwd_noresult_map">Leider keine Suchergebnisse...</p></div>';
            } else {
                foreach ($geocodes as $location) {
                    if (!empty($location['lat']) && !empty($location['lng'])): ?>
                        <div class="marker" data-lat="<?php echo esc_attr($location['lat']); ?>" data-lng="<?php echo esc_attr($location['lng']); ?>" data-title="<?php echo esc_attr($location['name']); ?>">
                            <a href="<?php echo esc_url($location['link']); ?>">
                                <strong><?php echo esc_html($location['name']); ?></strong>
                                <br />zu den Einrichtungsdetails &raquo;
                            </a>
                        </div>
            <?php endif;
                }
            }
            ?>
        </div>

        <div id="cwd_cat_nav" style="position: relative;">
            <div class="before-block" style="position: absolute; left: -7%; font-size: 6em; top: -26%; font-weight: 700; color: rgba(227, 6, 19, 0.15); z-index: -1;">2</div>
            <h3 class="cwd_subtitle">Kategorien:</h3>
            <a href="<?php echo $request_base_url ?>?kategorie=clear" class="<?php if (empty($_GET['kategorie'])) echo 'cwd_active'; ?>">Alle</a>
            <?php
            $cats = get_terms(array(
                'taxonomy' => 'angebote',
                'parent' => 0,
                'hide_empty' => false
            ));

            foreach ($cats as $cat) {
                $output = '<a href="' . esc_url(get_permalink() . '?kategorie=' . $cat->slug) . '" class="';

                if (!empty($_GET['kategorie'])) {
                    if ($_GET['kategorie'] == $cat->slug) {
                        $output .= 'cwd_active';
                    }
                }

                $output .= '">' . esc_html($cat->name) . '</a>';
                echo $output;
            }
            ?>
        </div>

    </div>

    <?php if (!empty($_GET['kategorie'])) { ?>
        <div id="cwd_cur_cat_title">Kategorie: <span id="cwd_cur_cat_name"><?php echo esc_html(get_term_by('slug', $_GET['kategorie'], 'angebote')->name); ?></span></div>
        <div id="cwd_service_filter">
            <h4>Nach Angeboten filtern:</h4>

            <form id="cwd_service_filter_form" method="POST" action="<?php echo esc_url($request_cat_url); ?>">
                <?php
                $parent_id = get_term_by('slug', $_GET['kategorie'], 'angebote')->term_id;
                $sub_cats = get_terms(array(
                    'taxonomy' => 'angebote',
                    'parent' => $parent_id,
                    'hide_empty' => false
                ));

                $count = 0;
                foreach ($sub_cats as $sub) {
                    $output = '<input type="checkbox" id="cwd_subcat_check_' . $count . '" class="cwd_subcat_check" name="cwd_angebote[' . esc_attr($sub->slug) . ']" value="' . esc_attr($sub->term_id) .  '" ';

                    if (!empty($_SESSION['cwd']['angebote'][$sub->slug])) {
                        $output .= 'checked';
                    }
                    $output .= ' />';
                    $output .= '<label for="cwd_subcat_check_' . $count . '" class="cwd_check_label">' . esc_html($sub->name);

                    if (!empty($sub->description)) {
                        $output .= '<img class="cwd_icon_info" src="' . plugin_dir_url(__DIR__) . 'imgs/info-circle.png" >';
                        $output .= '<div class="cwd_tool_tip">' . esc_html($sub->description)  . '</div>';
                    }
                    $output .= '</label>';

                    $count++;
                    echo $output;
                } ?>

                <input id="cwd_hidden_submit_filter" class="cwd_hidden" name="cwd_filter_submitted" type="checkbox" value="1" checked />
                <input id="cwd_submit_filter" class="cwd_btn" type="submit" value="Filter anwenden" />
            </form>
        </div>
    <?php } ?>

    <div id="Liste" class="cwd_anker"></div>
    <div id="cwd_list">
        <?php
        if (empty($result_list)) {
            echo '<h2 class="cwd_noresult_list">&raquo; zu Ihrer Sucheingabe konnten leider keine Einrichtungen gefunden werden</h2>';
        } else { ?>
            <a id="cwd_print_2">Liste drucken</a>
            <?php echo do_shortcode('[print-me target="body"/]'); ?>
            <h2>Ergebnisse
                <span class="cwd_small">&raquo;
                    <?php
                    echo $result_count;
                    if ($result_count > 1) {
                        echo ' Einrichtungen wurden';
                    } else {
                        echo ' Einrichtung wurde';
                    } ?>
                    gefunden</span>
            </h2>

            <div class="cwd_list_header">
                <span class="cwd_name_header">Name der Einrichtung</span>
                <span class="cwd_add_header">Adresse</span>
                <span class="cwd_serv_header">Angebote</span>
            </div>

            <?php echo $result_list; ?>
        <?php } ?>
    </div>

</div>

<script>
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.pathname + window.location.search);
    }

    // Add debug info to console
    <?php if (defined('CWD_DEBUG') && CWD_DEBUG): ?>
        console.log('CWD Debug Info:', {
            searchIds: <?php echo json_encode($search_ids); ?>,
            sessionData: <?php echo json_encode($_SESSION['cwd'] ?? array()); ?>,
            resultCount: <?php echo intval($result_count); ?>
        });
    <?php endif; ?>
</script>

<?php
// Load theme footer   
get_footer();
