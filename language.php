<?php
/**
 * Stresslimit quick multi-lingual setup
 */

add_action( 'init', 'na_setup');
function na_setup() {

	if ( is_admin() || is_login_page() ) {
		// load_theme_textdomain( 'na', TEMPLATEPATH.'/lang' );
	} else {

	// Grab the $lang parameter manually based on the first URL segment.
	// see sld_rewrite_rules() for explanation of why we're not doing this the WP way.
	global $lang;
	$orig_uri = $_SERVER['REQUEST_URI'];
	$uri = explode( '/', substr( $orig_uri, 1 ) ); // substr removes the initial '/' which will return $uri[0] as empty
	$lang = array_shift( $uri );
	if ( $lang == 'fb_images' ) return;
	
	if ( empty($lang) ) { wp_redirect( '/en', 301 ); exit; }

	// make sure the first url segment is /en/ or /fr/, if not just add /en/ in front of the request uri
	if ( !empty($lang) && ( $lang!='fr' && $lang!='en' ) ) { wp_redirect( '/en/' . $orig_uri, 301 ); exit; }

	// set content-language header for fun
	header( 'Content-Language: '. $lang .'_CA' );


	// uncomment to convert .po.mo when we've edited the translation file
	// plus regenerate rewrite rules in case we've changed sld_rewrite_rules() below
	$regenerate_rules_and_languages = false;
	// $regenerate_rules_and_languages = true;
	if ( $regenerate_rules_and_languages ) {
		// include( TEMPLATEPATH.'/lang/po-mo.php' );
		// phpmo_convert( TEMPLATEPATH.'/lang/fr_CA.po', TEMPLATEPATH.'/lang/fr_CA.mo' );
		sld_flush_rewrite();
	}
	// load_theme_textdomain( 'na', TEMPLATEPATH.'/lang' );

	// Language rewrite rules
	// for /en/ or /fr/ in location.href
	add_filter( 'post_link', 'sld_permalink' );
	add_filter( 'page_link', 'sld_permalink' );
	add_filter( 'the_permalink', 'sld_permalink' );

	add_filter( 'the_title', 'sld_title', 10, 2 );

	}

}


// Set the locale to be en_CA or fr_CA based on lang var above
add_filter( 'locale', 'sld_locale', 10, 2);
function sld_locale( $locale ) {
	if ( is_admin() || is_login_page() ) return 'fr_CA';
	global $lang;
	if ( !empty($lang) )
		return $lang.'_CA';
	return $locale;
}


// Generate a language switcher link
function sld_lang_switcher_link( $args = null ) {
	global $lang;
	$defaults = array(
		'labels' => array( 'fr'=>'English', 'en'=>'Français' ),
		'links' => array( 'fr'=>'en', 'en'=>'fr' ),
	);
	$args = wp_parse_args( $args, $defaults );

	$lang_switcher_link = $args['links'];
	$lang_switcher_label = $args['labels'];
	$lang_label = $lang_switcher_label[$lang];
	$lang_link = preg_replace( '@^/('.$lang.')(.*)?$@', '/'.$lang_switcher_link[$lang].'$2', @$_SERVER['REQUEST_URI'] );
	return '<a href="'.$lang_link.'">'.$lang_label.'</a>';
}

// Function to add /$lang/ to URL — added to filters in asg_init() above
function sld_permalink($p) {
	global $lang;
	$url = get_bloginfo('home');
	return str_replace( $url, $url.'/'.$lang, $p );
}

// Translation for title — we are able to do nice & clean with a filter
function sld_title( $t, $post_id ) {
	global $lang;
	if ( $lang!='en' ) {
		$post = get_post( $post_id );
		// if we are a nav menu item, so we need to find the actual page instead
		if ( $post->post_type=='nav_menu_item' )
			$post_id = get_post_meta( $post_id, '_menu_item_object_id', true );
		$p = get_post_meta( $post_id, 'title_'.$lang, true );
		if ( !empty($p ) ) {
			$t = $p;
			remove_filter( 'the_title', 'sld_title' );
			$t = apply_filters( 'the_title', $t );
			add_filter( 'the_title', 'sld_title', 10, 2 );
		}
	}
	return $t;
}

// Translation for content — stupid the_content filter doesn't give us the ID of
// the object in question, so we have to wrap another function to replace. Fart.
function sld_content( $post=null ) { echo sld_get_content( $post ); }
function sld_get_content( $post=null ) {
	if ( empty($post) )
		global $post;
	global $lang;
	if ( $lang!='en' ) {
		$c = get_post_meta( $post->ID, 'content_'.$lang, true );
	} else {
		$c = $post->post_content;
	}
	$c = apply_filters( 'the_content', $c );
	return $c;
}




// Rewrite rules & query_var stuff [even though query_var was not entirely successful
// add_action( 'init', 'sld_flush_rewrite' );
// only uncomment when we need to regenerate rules, better not to run every pageload
function sld_flush_rewrite() {
	flush_rewrite_rules( false );
}
add_filter( 'rewrite_rules_array', 'sld_rewrite_rules' );
function sld_rewrite_rules($aRules) {
	// this works for in-template switching, but actual localization doesn't work because of the order of
	// actions and filters in wp. query_vars is called after theme_setup, init, and any other useful/dependable
	// action. So, we never have query_vars in time to be able to add $lang into the locale action. Damn.
	// We still need this stuff to parse out the /en|fr/ in order to pass WP the right object query string.	
	$aNewRules = array(
		'(en|fr)/([^/]+)/?$' => 'index.php?pagename=$matches[2]&lang=$matches[1]',
		'(en|fr)/?$' => 'index.php',
		);
	$aRules = $aNewRules + $aRules;
	return $aRules;
}
add_filter( 'query_vars' , 'sld_query_vars' );
function sld_query_vars( $vars ) {
	array_push( $vars, 'lang' );
	return $vars;
}


