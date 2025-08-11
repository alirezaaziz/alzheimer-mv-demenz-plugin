<?php // THE EXPORT ADMIN PAGE

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}


// Export data as requested..
if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {
    
    // Create temp file
	$output = fopen(dirname( __FILE__ ) . '/export_temp/temp.csv', 'w' );
    chmod(dirname( __FILE__ ) . '/export_temp/temp.csv', 0700);
        
    // Begin column headings
    $headings = array('Name', 'Str.', 'Hnr.', 'PLZ', 'Ort', 'Landkreis','Ansprechpartner', 'Tel.', 'Fax', 'E-Mail', 'Webseite', 'Kategorie', 'Angebote', 'HTML-Inhalt "weitere Infos"', 'lat', 'lng');
	
    fputcsv($output, $headings, ';');
    
    $args = array(
		'post_type'      => 'cwd_einrichtung',
		'posts_per_page' => -1,
    );
    $the_query = new WP_Query( $args );
	
	if ( $the_query->have_posts() ) {
		
	    while ( $the_query->have_posts() ) {
			$the_query->the_post();
			
			$new_line = array(
			    get_post_field('post_title'),
			    get_field('cwd_str'),
			    get_field('cwd_hnr'),
			    get_field('cwd_plz'),
			    get_field('cwd_ort'),
			    get_field('cwd_landkreis'),
			    get_field('cwd_contact'),
			    get_field('cwd_tel'),
			    get_field('cwd_fax'),
			    get_field('cwd_mail'),
			    get_field('cwd_web')
			);
			
			$terms = wp_get_post_terms( get_the_id(), 'angebote', array( 'orderby' => 'term_order' ) );
			
			$new_line[] = $terms[0]->name;
			
			$angebote = '';
			$count = 0;
			
			foreach ( $terms as $term ) {
			    
			    if ( $term->parent != 0 ) {
			        
			        if ( $count == 0 ) {
			            $angebote .= $term->name;
			        } else {
			            $angebote .= ', ' . $term->name;
			        }
			        $count++;
			    }
			}
			
			$new_line[] = $angebote;
			
			array_push($new_line, trim( preg_replace('/\s+/', ' ', get_field('cwd_infos')) ), get_field('cwd_lat'), get_field('cwd_lng'));
			
			fputcsv($output, $new_line, ';');
		}
		
		wp_reset_postdata();
		
	} else {
		// no posts found
	}
		
    echo	'<p>&nbsp;</p>
			<p><a class="button" href="'. get_site_url() .'/cwd_download_export"><b>Download CSV-Export &raquo;</b></a></p>
			<p>&nbsp;</p>' ;	

}

// Display the export form:
?>
<div id="cwd_export_page" class="cwd_container">
	<h1>Buchungsexport</h1>
	<p>Drücken Sie auf den Button "Exportieren" um alle Einrichtungsdaten als CSV-Datei zu exportieren.</p>
	
	<form id="cwd_export_form" action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
		<input class="button button-primary" type="submit" value="Exportieren" />
	</form>
</div>