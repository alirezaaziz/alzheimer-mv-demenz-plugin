<?php
/*
 Template Name:	"Einrichtung eintragen" Template file
 Description:	Template zur Visualisierung des Formulars zum eintragen einer Einrichtung
 Author:		T.
 Author URI:   	http://customized.technology
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
	die;
}

// load acf-form header
acf_form_head();

wp_enqueue_style('cwd_new_einrichtung_styles',  plugin_dir_url(__FILE__) . 'css/new_einrichtung_styles.css');

// DANNY: Keine Google-Fonts
//wp_enqueue_style('cwd_googlefonts_styles',  'https://fonts.googleapis.com/css?family=Libre+Franklin:300,300i,400,400i,600,600i,800,800i&amp;subset=latin-ext' );


if (isset($_GET['updated']) && $_GET['updated'] == true) {

	wp_enqueue_style('cwd_einrichtung_styles',  plugin_dir_url(__FILE__) . 'css/adressdb_styles.css');

	// DANNY: Mapscript nur laden wenn Google-Maps-Zustimmung in Borlabs Cookie gesetzt ist

	wp_enqueue_script('cwd_einrichtung_scripts',  plugin_dir_url(__FILE__) . 'js/adressdb_scripts.js', array('jquery'));
	wp_enqueue_script('cwd_googlemaps_scripts', 'https://maps.googleapis.com/maps/api/js?key=' . get_field('cwd_gm_apikey', 'option'), array('jquery'));
}

// Load theme header
get_header();


// START OUTPUTTING THE VIEW: 
?>

<script tpye="text/javascript">
	(function($) {

		$(document).ready(function() {


			$('label[for="acf-_post_title"]').html('Name der Einrichtung / des Anbieters <span class="acf-required">*</span>');

			/*
    			var adminurl = '<?php // echo admin_url( 'admin-ajax.php' ); 
								?>';

    			$('.acf-field-5b62f10e5491e').hide();

    
    			$('#acf-field_5b62e47e2a57f').change(function(){
    
    				var data = {
    						'action': 'cwd_registerform_load_services',
    						'category': $(this).val()
    					};

					$.post(adminurl, data, function(response) {
					//	alert('Got this from the server: ' + response);
					});

					
					$('.acf-field-5b62f10e5491e').show();
						
					$('#acf-field_5b62f10e5491e').val(null);

					$('.acf-field-5b62f10e5491e .select2-selection__choice').each(function(){
						$(this).remove();
					});

					$('.acf-field-5b62f10e5491e .select2-search__field').attr("placeholder", "Auswählen");
    
    			}); */
			// - AUFGRUND ÄNDERUNGSWUNSCH "mehrere Kategorien" DEAKTIVIERT

			$('#cwd_box').insertBefore('.acf-field-5b62dd2e8b363');
			$('.acf-field-5b62dd2e8b363').appendTo('#cwd_box');
			$('.acf-field-5b62dd7b8b364').appendTo('#cwd_box');
			$('.acf-field-5bac6d50949d8').appendTo('#cwd_box');
			$('.acf-field-5b62dda18b365').appendTo('#cwd_box');
			$('.acf-field-5b62ddc78b366').appendTo('#cwd_box');
			$('#cwd_box').show();

			$('#cwd_muster_box').prependTo('.acf-field-5b6183cffd008');
			$('#cwd_muster_box').show();
			$('#cwd_muster_close').click(function() {
				$('#cwd_muster_box').hide();
				$('#cwd_muster_show').show();
			});
			$('#cwd_muster_show').appendTo('.acf-field-5b6183cffd008 .acf-label .description');
			$('#cwd_muster_show').click(function() {
				$('#cwd_muster_box').show();
				$(this).hide();
			});

			$('#cwd_tooltip_box').appendTo('.acf-field-5b5066ef8beda .acf-label label');
			$('#cwd_tooltip_box .cwd_icon_info').show();
		});

	})(jQuery);
</script>

<div id="cwd_main" class="cwd_container <?php if (isset($_GET['updated']) && $_GET['updated'] == true) echo 'cwd_single'; ?>">

	<?php if (isset($_GET['update']) && $_GET['update'] == true) {

		acf_form(array(
			'post_id'		=> $_SESSION['cwd']['new_einrichtung'],
			'field_groups' => array('group_5b61824f123e9'),
			'submit_value'	=> 'Daten absenden',
			'post_title' => true,
			'return' =>  get_permalink(get_field('cwd_registerview_id', 'option')) . '?updated=true'
		));
	} elseif (isset($_GET['updated']) && $_GET['updated'] == true) {

		echo '<h1>Vielen Dank für Ihre Eintragung!</h1>';
		echo '<p>Nach Prüfung der Angaben wird die Einrichtung veröffentlicht werden.
              <br />Sollten sich Rückfragen ergeben, nehmen wir Kontakt mit dem angegebenen Ansprechpartner auf.
              <br /><br />
              Nachfolgend sehen Sie eine Vorschau der Einrichtungsdetails, so wie diese nach Veröffentlichung auf der Seite angezeigt werden. 
              <br />Sollten Ihnen Fehler auffallen, haben Sie unten die Möglichkeit die Daten nochmals zu bearbeiten.
              <br /></p>';

		global $post;

		$post = $_SESSION['cwd']['new_einrichtung']; ?>

		<h2 style="margin-bottom: 0; font-weight: bold; text-transform: uppercase;">Vorschau</h2>

		<div id="cwd_single" style="border: 1px solid black; border-radius: 4px; padding: 25px 30px 5px;">

			<h1><?php the_title(); ?></h1>
			<p style="font-style:italic;margin-top:-35px;margin-bottom:25px;"><small>(Stand: <?php the_modified_date('d.m.Y'); ?>)</small></p>

			<div class="cwd_col3">
				<div class="cwd_address">
					<p class="cwd_single_header">Adresse:</p>
					<p><?php the_field('cwd_str'); ?>, <?php the_field('cwd_hnr'); ?></p>
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

				<a class="cwd_btn cwd_cat_btn" style="cursor:not-allowed;" href="#Vorschau">&laquo; zurück zur Suche</a>

			</div>

			<div id="cwd_single_thumb" class="cwd_col3">

				<?php the_post_thumbnail(); ?>

			</div>

		</div>

		<?php
		echo '<a class="cwd_btn cwd_cat_btn" style="float:left;border-color:#e30613;text-transform:uppercase;border-width:2px;" href="' . get_permalink(get_field('cwd_registerview_id', 'option')) . '?update=true">&laquo; Daten bearbeiten</a>';
		echo '<a class="cwd_btn cwd_cat_btn" style="float:right;border-color:#e30613;text-transform:uppercase;border-width:2px;" href="' . get_permalink(get_field('cwd_mainview_id', 'option')) . '">Fertig, zurück zur Übersicht &raquo;</a>';
	} else {

		// Display the pages content
		if (have_posts()) :
			while (have_posts()) : the_post(); ?>

				<h1><?php the_title(); ?></h1>

				<div id="cwd_register_desc">
					<?php the_content(); ?>
				</div>

		<?php endwhile;
		else :
		//
		endif; ?>

		<div id="cwd_box"></div>
		<div id="cwd_tooltip_box">
			<img class="cwd_icon_info" src="<?php echo plugin_dir_url(__DIR__) . 'imgs/info-circle.png'; ?>">
			<div class="cwd_tool_tip">
				Bitte geben Sie die vollständige URL mit korrektem Protokoll ("http://" oder "https://") und mit oder ohne "www." entsprechend den Einstellungen Ihres Webservers an.
				<br /><br />
				Sollten Sie unsicher sein, so öffnen Sie einfach die Webseite in Ihrem Browser und kopieren die URL aus der Adresszeile oder fragen Ihren Administrator.
			</div>
		</div>
		<a id="cwd_muster_show" href="#Beispiel"> Beispiel anzeigen &raquo;</a>
		<div id="cwd_muster_box">
			<span id="#Beispiel"></span>
			<strong>Beispiel:</strong><span id="cwd_muster_close">X</span><br />
			<br />
			Sprechzeiten:<br />
			Mo. - Do.: &nbsp; 11:00 bis 14:00<br />
			Fr.: &nbsp; 10:00 bis 12:00 Uhr<br />
			<br />
			Ansprechpartner: <i style="font-style:italic;">(falls mehrere als nur der eine, oben angegebene...)</i><br />
			<br />
			Max Mustermann - Tel.: 0123 456 1<br />
			Zuständig für xyz<br />
			<br />
			Erika Musterfrau - Tel.: 0123 456 2<br />
			Zuständig für zxy<br />
			<br />
			<br />
			Hier steht ein kurzer Beschreibungstext mit einigen wichtigen Informationen zur Einrichtung, z.B. kann dieser auch Links zu weiterführenden Infos (<a href="#Beispiel">www.example.org/über-uns</a>) enthalten.<br />
			<br />
		</div>

		<?php
		acf_form(array(
			'post_id'		=> 'new_post',
			'new_post'		=> array(
				'post_type'		=> 'cwd_einrichtung',
				'post_status'	=> 'draft'
			),
			'field_groups' => array('group_5b61824f123e9'),
			'submit_value'	=> 'Daten absenden',
			'post_title' => true,
		));
		?>

		<a id="cwd_search_link" href="<?php echo get_permalink(get_field('cwd_mainview_id', 'option')); ?>">zurück zum Demenzkompass &raquo;</a>

	<?php
	} ?>

</div>


<?php // Load theme footer   
get_footer(); ?>