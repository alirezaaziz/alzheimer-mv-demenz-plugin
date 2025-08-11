<?php

function cwd_shortcode_map_func()
{

    wp_enqueue_style('cwd_shortmap_styles',  plugin_dir_url(__FILE__) . 'css/shortmap_styles.css');

    // DANNY: Keine Google-Fonts
    //wp_enqueue_style('cwd_shortmapfonts_styles',  'https://fonts.googleapis.com/css?family=Libre+Franklin:300,300i,400,400i,600,600i,800,800i&amp;subset=latin-ext' );

    // DANNY: Google-Karte nur laden, wenn Zustimmung gesetzt ist
    wp_enqueue_script('cwd_shortmap_scripts',  plugin_dir_url(__FILE__) . 'js/adressdb_scripts.js', array('jquery'));
    wp_enqueue_script('cwd_shortmapgm_scripts', 'https://maps.googleapis.com/maps/api/js?key=' . get_field('cwd_gm_apikey', 'option'), array('jquery'));


    ob_start();


    $args = array(
        'posts_per_page' => -1,
        'post_type' => 'cwd_einrichtung'
    );

    $custom_query = new WP_Query($args);


    if ($custom_query->have_posts()) :

        echo '<div class="acf-map">';

        while ($custom_query->have_posts()) : $custom_query->the_post();

            if (! empty(get_field('cwd_lat', get_the_id())) && ! empty(get_field('cwd_lng', get_the_id()))): ?>


                <div class="marker" data-lat="<?php echo get_field('cwd_lat', get_the_id()); ?>" data-lng="<?php echo get_field('cwd_lng', get_the_id()); ?>" data-title="<?php echo get_the_title(); ?>">
                    <a href="<?php echo get_the_permalink(); ?>">
                        <strong><?php echo get_the_title(); ?></strong>
                        <br />zu den Einrichtungs-Details &raquo
                    </a>
                </div>


<?php endif;

        endwhile;

        echo '</div><a id="cwd_db-main_link" href="' . get_permalink(get_field('cwd_mainview_id', 'option')) . '">zur Adressdatenbank &raquo;</a>';

    else :
    // Keine Beiträge gefunden
    endif;

    wp_reset_postdata();


    return ob_get_clean();
}

add_shortcode('CWD_MAP', 'cwd_shortcode_map_func');

?>