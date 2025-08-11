<?php
/*
 Template Name:	"Einrichtung Single" Template file
 Description:	Template zur Visualisierung der Einzelansicht einer Einrichtung
 Author:		T.
 Author URI:   	http://customized.technology
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
	die;
}

wp_enqueue_style('cwd_einrichtung_styles',  plugin_dir_url(__FILE__) . 'css/adressdb_styles.css');

// DANNY: Keine Google-Fonts
//wp_enqueue_style('cwd_googlefonts_styles',  'https://fonts.googleapis.com/css?family=Libre+Franklin:300,300i,400,400i,600,600i,800,800i&amp;subset=latin-ext' );

// DANNY: Google-Karte nur laden, wenn Zustimmung gesetzt ist
wp_enqueue_script('cwd_einrichtung_scripts',  plugin_dir_url(__FILE__) . 'js/adressdb_scripts.js', array('jquery'));
wp_enqueue_script('cwd_googlemaps_scripts', 'https://maps.googleapis.com/maps/api/js?key=' . get_field('cwd_gm_apikey', 'option'), array('jquery'));


// Load theme header
get_header();


// START OUTPUTTING THE VIEW: 
?>

<div id="cwd_main" class="cwd_container cwd_single">

	<div id="cwd_single">

		<h1><?php the_title(); ?></h1>
		<p style="font-style:italic;margin-top:-35px;margin-bottom:25px;text-align:center;"><small>(Stand: <?php the_modified_date('d.m.Y'); ?>)</small></p>

		<div class="cwd_col3">
			<div class="cwd_address">
				<p class="cwd_single_header">Adresse:</p>
				<p><?php the_field('cwd_str'); ?> <?php the_field('cwd_hnr'); ?></p>
				<p><?php the_field('cwd_plz'); ?> <?php the_field('cwd_ort'); ?></p>
			</div>

			<div class="cwd_kontakt">
				<p class="cwd_single_header">Kontakt:</p>
				<p>
					<?php if (get_field('cwd_contact')) { ?>

						<?php the_field('cwd_contact'); ?><br />

					<?php } ?>

					Tel.: <?php the_field('cwd_tel'); ?>

					<?php if (get_field('cwd_fax')) { ?>

						<br />Fax: <?php the_field('cwd_fax'); ?>

					<?php } ?>
				</p>
				<p>
					<?php if (get_field('cwd_mail')) { ?>

						<a href="mailto:<?php the_field('cwd_mail'); ?>"><?php the_field('cwd_mail'); ?></a>

					<?php } ?>

					<?php if (get_field('cwd_web')) { ?>

						<br /><a target="_blank" href="<?php the_field('cwd_web'); ?>"><?php the_field('cwd_web'); ?></a>

					<?php } ?>
				</p>
			</div>
		</div>

		<div class="cwd_col3">
			<?php $terms = wp_get_post_terms(get_the_id(), 'angebote', array('orderby' => 'term_order')); ?>
			<?php /*    	
				<div class="cwd_cat">
								
					<p class="cwd_cat_btn"><?php echo($terms[0]->name); ?></p>
				</div>
				*/ // - ÄNDERUNGSWUNSCH "keine Anzeige der Kategorie bei Ergebnissen"
			?>
			<div class="cwd_services">
				<?php foreach ($terms as $term) {

					if ($term->parent != 0) {

						echo '<p class="cwd_serv_btn">' . $term->name . '</p>';
					}
				} ?>
			</div>
		</div>

		<div id="cwd_single_map" class="cwd_col3">
			<div class="acf-map">
				<?php
				// DANNY: Wenn Zustimmungscookie fehlt, Hinweis zur Karte einblenden
				$address = get_field('cwd_str') . ' ' . get_field('cwd_hnr') . ', ' . get_field('cwd_plz') . ' ' . get_field('cwd_ort');

				$address = str_replace(' ', '+', $address);
				?>
				<div class="marker" data-lat="<?php the_field('cwd_lat'); ?>" data-lng="<?php the_field('cwd_lng'); ?>" data-title="<?php the_title(); ?>">
					<a target="_blank" href="https://www.google.de/maps/place/<?php echo $address; ?>">
						Wegbeschreibung in Google Maps öffnen &raquo;
					</a>
				</div>
			</div>
		</div>

		<div class="cwd_single_desc">

			<?php the_field('cwd_infos'); ?>

			<?php
			if (!empty($_SERVER['HTTP_REFERER']) && stripos($_SERVER['HTTP_REFERER'], get_permalink(get_field('cwd_mainview_id', 'option'))) !== FALSE) {

				$back_url = $_SERVER['HTTP_REFERER'];
			} else {

				$back_url = get_permalink(get_field('cwd_mainview_id', 'option'));

				if (!empty($_SESSION['cwd']['current_cat'])) {

					$back_url .= '?kategorie=' . $_SESSION['cwd']['current_cat'];
				}
			}

			$back_url .= '#Liste';
			?>
			<a class="cwd_btn cwd_cat_btn" href="<?php echo $back_url; ?>">&laquo; zurück zur Suche</a>

		</div>

		<div id="cwd_single_thumb" class="cwd_col3">

			<?php the_post_thumbnail(); ?>
			<p style="font-style:italic;margin-top:-5px;text-align:right;"><small>Quelle: <?php the_field('cwd_thumbnail_quelle'); ?></small></p>

		</div>

	</div>

</div>



<?php // Load theme footer   
get_footer(); ?>