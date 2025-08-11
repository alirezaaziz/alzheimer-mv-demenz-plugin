<?php
/*
 Template Name:	Adressdatenbank Template file
 Description:	Template zur Visualisierung der Adressdatenbank
 Author:		T.
 Author URI:   	http://customized.technology
 */


wp_enqueue_style('cwd_adressdb_styles',  plugin_dir_url(__FILE__) . 'css/adressdb_styles.css');


// DANNY: Keine Google-Fonts^M
//wp_enqueue_style('cwd_googlefonts_styles',  'https://fonts.googleapis.com/css?family=Libre+Franklin:300,300i,400,400i,600,600i,800,800i&amp;subset=latin-ext' );

// DANNY: Mapscript nur laden wenn Google-Maps-Zustimmung in Borlabs Cookie gesetzt ist

wp_enqueue_script('cwd_adressdb_scripts',  plugin_dir_url(__FILE__) . 'js/adressdb_scripts.js', array('jquery'));
wp_enqueue_script('cwd_googlemaps_scripts', 'https://maps.googleapis.com/maps/api/js?key=' . get_field('cwd_gm_apikey', 'option'), array('jquery'));

wp_enqueue_script('printThis',  plugin_dir_url(__FILE__) . 'js/printThis.js', array('jquery'));
wp_enqueue_script('customPrint',  plugin_dir_url(__FILE__) . 'js/customPrint.js', array('jquery'));

// Load theme header^M
get_header();

// Display the pages content^M
if (have_posts()) :
    while (have_posts()) : the_post();
        the_content();
    endwhile;
else :
//
endif;



// SANTIZE USER INPUTS
function recursive_sanitize_text_field($array)
{
    foreach ($array as $key => &$value) {
        if (is_array($value)) {
            $value = recursive_sanitize_text_field($value);
        } else {
            $value = sanitize_text_field($value);
        }
    }

    return $array;
}

$_GET = recursive_sanitize_text_field($_GET);
$_POST = recursive_sanitize_text_field($_POST);

if (! isset($_GET['kategorie'])) {
    unset($_SESSION['cwd']);
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

    $_SESSION['cwd']['ort'] = $_POST['cwd_ort'];
    /* - A1 -
    $_SESSION['cwd']['plz'] = $_POST['cwd_plz'];
    $_SESSION['cwd']['straße'] = $_POST['cwd_straße'];
    */
    $_SESSION['cwd']['stichwort'] = $_POST['cwd_stichwort'];
    // - A1 - ENDE
    $_SESSION['cwd']['umkreis'] = $_POST['cwd_umkreis'];

    if (!empty($_POST['cwd_ort']) || !empty($_POST['cwd_plz']) || !empty($_POST['cwd_straße'])) {

        $address = '';

        if (!empty($_POST['cwd_straße'])) $address .= $_POST['cwd_straße'] . ' ';
        if (!empty($_POST['cwd_plz'])) $address .= $_POST['cwd_plz'] . ' ';
        if (!empty($_POST['cwd_ort'])) $address .= $_POST['cwd_ort'] . ' ';

        $address .= 'deutschland';

        $address = str_replace(' ', '+', $address);

        $gm_json_url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . $address . '&key=' . get_field('cwd_gm_apikey', 'option');
        $gm_loc_json = file_get_contents($gm_json_url);
        //$gm_loc_json = file_get_contents($gm_json_url, false, stream_context_create(['socket' => ['bindto' => '0:0']]));

        $json_data = json_decode($gm_loc_json, true);
        $loc_array = isset($json_data['results'][0]['geometry']['location']) ? $json_data['results'][0]['geometry']['location'] : array('lat' => 0, 'lng' => 0);

        $lat = isset($loc_array['lat']) ? $loc_array['lat'] : 0;
        $lng = isset($loc_array['lng']) ? $loc_array['lng'] : 0;
        $radius = isset($_SESSION['cwd']['umkreis']) ? intval($_SESSION['cwd']['umkreis']) : 15;

        global $wpdb;
        $results = $wpdb->get_results('SELECT *,(
                                                        6371 * acos
                                                        (
                                                            cos(
                                                                radians( ' . $lat . ' )
                                                            )
                                                            * cos(
                                                                radians( lat )
                                                            ) * cos(
                                                                radians( lng ) - radians( ' . $lng . ' )
                                                            ) + sin(
                                                                radians( ' . $lat . ' )
                                                            ) * sin(
                                                                radians( lat)
                                                            )
                                                        )
                                                    ) AS distance
                                            FROM (SELECT id, lat, lng FROM ' .  $wpdb->prefix . 'posts AS p JOIN (SELECT post_id, meta_value AS lat FROM ' .  $wpdb->prefix . 'postmeta WHERE meta_key = "cwd_lat") AS lt ON p.id = lt.post_id JOIN (SELECT post_id, meta_value AS lng FROM ' .  $wpdb->prefix . 'postmeta WHERE meta_key = "cwd_lng") AS lg ON p.id = lg.post_id WHERE post_type = "cwd_einrichtung" AND post_status = "publish") AS locations
                                            HAVING distance <= ' . $radius . '
                                            ORDER BY distance ASC;');


        $_SESSION['cwd']['ids_in_umkreis'] = array_column($results, 'id');

        if (empty($_SESSION['cwd']['ids_in_umkreis'])) {
            $_SESSION['cwd']['ids_in_umkreis'] = array(-1);
        }
    } else {

        $_SESSION['cwd']['ids_in_umkreis'] = null;
    }

    // - A1 -   
    if (!empty($_POST['cwd_stichwort'])) {

        $keywords = preg_split("/[\s,]+/", $_POST['cwd_stichwort']);
        $term_ids = array();

        $ids_in_search = [];

        foreach ($keywords as $keyword) {

            $terms = get_terms([
                'taxonomy' => 'angebote',
                'name__like' => $keyword,
            ]);

            foreach ($terms as $term) {
                if ($term->parent != 0) {
                    $term_ids[] = $term->term_id;
                }
            }
        }

        $term_ids = array_unique($term_ids);

        if (!empty($term_ids)) {

            $args = array(
                'posts_per_page' => -1,
                'post_type' => 'cwd_einrichtung',
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

            $ids_in_search = $custom_query->posts;
        }

        $args = array(
            'posts_per_page' => -1,
            'post_type' => 'cwd_einrichtung',
            's' => $_POST['cwd_stichwort'],
            'fields' => 'ids',
            'order_by' => 'relevance'
        );
        $custom_query = new WP_Query($args);

        $ids_in_search = array_unique(array_merge($custom_query->posts, $ids_in_search));

        if (empty($ids_in_search)) {

            $_SESSION['cwd']['ids_in_stichwort'] = array(-1);
        } else {

            $_SESSION['cwd']['ids_in_stichwort'] = $ids_in_search;
        }
    } else {

        $_SESSION['cwd']['ids_in_stichwort'] = null;
    }
    // - A1 - ÄNDERUNGSWUNSCH "nur noch Ortssuche, dafür auch Stichwortsuche" - ENDE

}

$request_base_url = get_permalink(get_field('cwd_mainview_id', 'option')) . '';
$request_cat_url = get_permalink(get_field('cwd_mainview_id', 'option'));

if (!empty($_GET['kategorie'])) {

    $request_cat_url .= '?kategorie=' . $_GET['kategorie'];
}

$request_cat_url .= '';

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
            <?php /* 
        		<input id="cwd_straße" class="cwd_textinput" name="cwd_straße" type="text" placeholder="Straße" value="<?php echo $_SESSION['cwd']['straße']; ?>" />
        		<input id="cwd_plz" class="cwd_textinput" name="cwd_plz" type="text" placeholder="Plz" value="<?php echo $_SESSION['cwd']['plz']; ?>" /> 
        		*/ ?>
            <h3 class="cwd_subtitle new_subtitle">Stichwortsuche:</h3>
            <input id="cwd_stichwort" class="cwd_textinput" name="cwd_stichwort" type="text" placeholder="Stichwort eingeben..." value="<?php echo isset($_SESSION['cwd']['stichwort']) ? $_SESSION['cwd']['stichwort'] : ''; ?>" />
            <p>Geben Sie hier ein Stichwort, wie z.B. ein Angebot oder Einrichtungsname, nach welchem gesucht werden soll, ein.</p>
            <?php // - A1 - 
            ?>
            <h3 class="cwd_subtitle new_subtitle">Umkreissuche:</h3>
            <input id="cwd_ort" class="cwd_textinput" name="cwd_ort" type="text" placeholder="PLZ und/oder Ort eingeben..." value="<?php echo isset($_SESSION['cwd']['ort']) ? $_SESSION['cwd']['ort'] : ''; ?>" />

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
        // - A1 -
        $search_ids = array();

        // Make sure session variables are set
        if (!isset($_SESSION['cwd'])) {
            $_SESSION['cwd'] = array();
        }

        if (!isset($_SESSION['cwd']['ids_in_umkreis'])) {
            $_SESSION['cwd']['ids_in_umkreis'] = null;
        }

        if (!isset($_SESSION['cwd']['ids_in_stichwort'])) {
            $_SESSION['cwd']['ids_in_stichwort'] = null;
        }

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
        // - A1 - ENDE

        $count = 10;
        $paged = get_query_var('paged') ? get_query_var('paged') : 1;
        $offset = ($paged - 1) * $count;

        $args = array(
            'posts_per_page' => -1, // KEINE PAGINIERUNG
            'post_type' => 'cwd_einrichtung',
            'post__in' =>  $search_ids, // - s.o. - // $_SESSION['cwd']['ids_in_umkreis'],
            'orderby' => 'post__in'
        );

        if (empty($search_ids)) {
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

            $array = array(
                'relation' => 'OR'
            );

            foreach ($subs as $sub) {
                array_push($array, array(
                    'taxonomy' => 'angebote',
                    'field' => 'term_id',
                    'terms' => $sub
                ));
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

                    <?php $terms = $terms = wp_get_post_terms(get_the_id(), 'angebote', array('orderby' => 'term_order')); ?>

                    <?php /* // - A2 -
								<div class="cwd_cat">
									<p class="cwd_cat_btn"><?php echo($terms[0]->name); ?></p>
								</div>
								*/ // - A2 - AUFGRUND ÄNDERUNGSWUNSCH "keine Anzeige der Kategorie bei den Ergebnissen" DEAKTIVIERT
                    ?>

                    <div class="cwd_services">
                        <?php foreach ($terms as $term) {

                            if ($term->parent != 0) {
                                echo '<p class="cwd_serv_btn">' . $term->name . '</p>';
                            }
                        } ?>
                    </div>
                </div>

        <?php $result_list .= ob_get_clean();


                $i = count($geocodes);
                $geocodes[$i]['name'] = get_the_title();
                /* $geocodes[$i]['slug'] = $post->post_name; */ //Änderung Link direkt zu Details nicht zu Anker in Liste
                $geocodes[$i]['link'] = get_the_permalink();
                $geocodes[$i]['lat']  = get_field('cwd_lat', get_the_id());
                $geocodes[$i]['lng']  = get_field('cwd_lng', get_the_id());

            endwhile;


            ob_start();

            echo '<div class="cwd_page_nav">';

            $big = 999999999; // need an unlikely integer

            $pagelinks =    paginate_links(array(
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
        // Keine Beiträge gefunden
        endif;

        wp_reset_postdata();
        ?>

        <div class="acf-map<?php if (empty($geocodes)) echo '-placeholder'; ?>">
            <?php
            // DANNY: Wenn Zustimmungscookie fehlt, Hinweis zur Karte einblenden
            if (empty($geocodes)) {

                echo '<div class="cwd_overlay-w"><p class="cwd_noresult_map">Leider keine Suchergebnisse...</p></div>';
            }
            foreach ($geocodes as $location) {

                if (! empty($location['lat']) && ! empty($location['lng'])): ?>

                    <div class="marker" data-lat="<?php echo $location['lat']; ?>" data-lng="<?php echo $location['lng']; ?>" data-title="<?php echo $location['name']; ?>">
                        <?php /* Änderung Link direkt zu Details nicht zu Anker in Liste
                    		<a href="<?php echo $_SERVER['REQUEST_URI'] . '#' . $location['slug']; ?>">
                    		*/ ?>
                        <a href="<?php echo $location['link']; ?>">
                            <strong><?php echo $location['name']; ?></strong>
                            <br />zu den Einrichtungsdetails &raquo; <?php // (s.o.) <br />Springe zur Einrichtung &raquo 
                                                                        ?>
                        </a>
                    </div>

            <?php endif;
            } ?>
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

                $output = '<a href="' . get_permalink() . '?kategorie=' . $cat->slug . '" class="';

                if (!empty($_GET['kategorie'])) {
                    if ($_GET['kategorie'] == $cat->slug) {

                        $output .= 'cwd_active';
                    }
                }

                $output .= '">' .  $cat->name . '</a>';

                echo $output;
            }
            ?>
        </div>

    </div>

    <?php if (!empty($_GET['kategorie'])) { ?>

        <div id="cwd_cur_cat_title">Kategorie: <span id="cwd_cur_cat_name"><?php echo get_term_by('slug', $_GET['kategorie'], 'angebote')->name; ?></span></div>
        <div id="cwd_service_filter">
            <h4>Nach Angeboten filtern:</h4>

            <form id="cwd_service_filter_form" method="POST" action="<?php echo $request_cat_url; ?>">
                <?php
                $parent_id = get_term_by('slug', $_GET['kategorie'], 'angebote')->term_id;

                $sub_cats = get_terms(array(
                    'taxonomy' => 'angebote',
                    'parent' => $parent_id,
                    'hide_empty' => false
                ));

                $count = 0;
                foreach ($sub_cats as $sub) {

                    $output = '<input type="checkbox" id="cwd_subcat_check_' . $count . '" class="cwd_subcat_check" name="cwd_angebote[' . $sub->slug . ']" value="' . $sub->term_id .  '" ';

                    if (!empty($_SESSION['cwd']['angebote'][$sub->slug])) {

                        $output .= 'checked';
                    }
                    $output .= ' />';
                    $output .= '<label  for="cwd_subcat_check_' . $count . '" class="cwd_check_label">' . $sub->name;

                    if (!empty($sub->description)) {

                        $output .= '<img class="cwd_icon_info" src="' . plugin_dir_url(__DIR__) . 'imgs/info-circle.png" >';
                        $output .= '<div class="cwd_tool_tip">' . $sub->description  . '</div>';
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
                <?php /* 
   		    	<span class="cwd_cat_header">Kategorie</span>
   		    	*/ // - A2 - 
                ?>
                <span class="cwd_serv_header">Angebote</span>
            </div>

        <?php echo $result_list;
        }
        ?>
    </div>

</div>
<script>
    if (window.history.replaceState) {
        console.log(window.location.pathname);
        window.history.replaceState(null, null, window.location.pathname);
    }
</script>

<?php
// Load theme footer   
get_footer(); ?>