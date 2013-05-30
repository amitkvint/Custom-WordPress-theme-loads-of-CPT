<?php

 /*   // Adding custom post types - from posttypes.php */

   /* include_once(ABSPATH . 'wp-content/themes/posttypes.php');*/


    // Adding featured image to theme
	
    add_theme_support( 'post-thumbnails');

	// Add RSS links to <head> section
	automatic_feed_links();
	
	// Load jQuery
	if ( !is_admin() ) {
	   wp_deregister_script('jquery');
	   wp_register_script('jquery', ("http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js"), false);
	   wp_enqueue_script('jquery');
	}
	
	// Clean up the <head>
	function removeHeadLinks() {
    	remove_action('wp_head', 'rsd_link');
    	remove_action('wp_head', 'wlwmanifest_link');
    }
    add_action('init', 'removeHeadLinks');
    remove_action('wp_head', 'wp_generator');
    
	// Declare sidebar widgets
    if (function_exists('register_sidebar')) {
    	register_sidebar(array(
    		'name' => 'Sidebar Widgets',
    		'id'   => 'sidebar-widgets',
    		'description'   => 'These are widgets for the sidebar.',
    		'before_widget' => '<div id="%1$s" class="widget %2$s">',
    		'after_widget'  => '</div>',
    		'before_title'  => '<h2>',
    		'after_title'   => '</h2>'
    	));
    }
    if (function_exists('register_sidebar')) {
        register_sidebar(array(
            'name' => 'New Speakers',
            'class'   => 'new-speakers',
            'description'   => 'This is the New Speakers Widget',
            'before_widget' => '',
            'before_title'  => '<h2>',
            'after_title'   => '</h2>'
        ));
    }

    if (function_exists('register_sidebar')) {
        register_sidebar(array(
            'name' => 'Recent Events',
            'class'   => 'recent-events',
            'description'   => 'This is the Recent Events Widget',
            'before_widget' => '<article class="recent-events">',
            'after_widget'  => '</article>',
            'before_title'  => '<h2>',
            'after_title'   => '</h2>'
        ));
    }
    // Declare left col widgets

    if (function_exists('register_sidebar')) {
        register_sidebar(array(
            'name' => 'Latest Posts',
            'class'   => 'latest-posts',
            'description'   => 'This is the latest Post Widget',
            'before_widget' => '',
            'before_title'  => '<h2>',
            'after_title'   => '</h2>'
        ));
    }

    if (function_exists('register_sidebar')) {
        register_sidebar(array(
            'name' => 'Speakers Slider',
            'class'   => 'speakers-slider',
            'description'   => 'This is the Speakers Slider Widget',
            'before_widget' => '',
            'before_title'  => '',
            'after_title'   => ''
        ));
    }

        if (function_exists('register_sidebar')) {
        register_sidebar(array(
            'name' => 'Header Widget',
            'id'   => 'header-widget',
            'description'   => 'This widget is for the header.',
            'before_widget' => '',
            'after_widget'  => '',
            'before_title'  => '',
            'after_title'   => ''
        ));
    }

    if (function_exists('register_sidebar')) {
        register_sidebar(array(
            'name' => 'Nav Widget',
            'id'   => 'nav-widget',
            'description'   => 'This widget is for the right sidebar nav.',
            'before_widget' => '',
            'after_widget'  => '',
            'before_title'  => '<h2>',
            'after_title'   => '</h2>'
        ));
    }

    if (function_exists('register_sidebar')) {
        register_sidebar(array(
            'name' => 'Facebook',
            'id'   => 'facebook-widget',
            'description'   => 'This widget is for facebook.',
            'before_widget' => '',
            'after_widget'  => '',
            'before_title'  => '<h2>',
            'after_title'   => '</h2>'
        ));
    }

    if (function_exists('register_sidebar')) {
        register_sidebar(array(
            'name' => 'Videos',
            'id'   => 'video-widget',
            'description'   => 'This widget is for the...videos.',
            'before_widget' => '',
            'after_widget'  => '',
            'before_title'  => '<h2>',
            'after_title'   => '</h2>'
        ));
    }

if (function_exists('register_sidebar')) {
        register_sidebar(array(
            'name' => 'Tag1-Left',
            'id'   => 'tag1',
            'description'   => 'This widget is for the left side bar tag cloud1.',
            'before_widget' => '',
            'after_widget'  => '',
            'before_title'  => '<h2>',
            'after_title'   => '</h2>'
        ));
    }
    if (function_exists('register_sidebar')) {
        register_sidebar(array(
            'name' => 'Tag2-Left',
            'id'   => 'tag2',
            'description'   => 'This widget is for the left side bar tag cloud2.',
            'before_widget' => '',
            'after_widget'  => '',
            'before_title'  => '<h2>',
            'after_title'   => '</h2>'
        ));
    }
    if (function_exists('register_sidebar')) {
        register_sidebar(array(
            'name' => 'Languages',
            'id'   => 'lang',
            'description'   => 'This widget is for languages selector',
            'before_widget' => '',
            'after_widget'  => '',
            'before_title'  => '',
            'after_title'   => ''
        ));
    }
    if (function_exists('register_sidebar')) {
        register_sidebar(array(
            'name' => 'Quick Contact',
            'id'   => 'quick-contact',
            'description'   => 'This widget is for the quick contact form',
            'before_widget' => '',
            'after_widget'  => '',
            'before_title'  => '',
            'after_title'   => ''
        ));
    }
    if (function_exists('register_sidebar')) {
            register_sidebar(array(
                'name' => 'Home-Contact',
                'id'   => 'home-contact',
                'description'   => 'This widget is for the home contact form',
                'before_widget' => '',
                'after_widget'  => '',
                'before_title'  => '',
                'after_title'   => ''
            ));
        }
    if (function_exists('register_sidebar')) {
        register_sidebar(array(
            'name' => 'Company Info',
            'id'   => 'company-info',
            'description'   => 'This widget is for the home company-info',
            'before_widget' => '<p><strong>',
            'after_widget'  => '</strong></p>',
            'before_title'  => '',
            'after_title'   => ''
        ));
    }
    if (function_exists('register_sidebar')) {
        register_sidebar(array(
            'name' => 'Right Banner',
            'id'   => 'right-banner',
            'description'   => 'This widget is for the right banner width: 160px;
    height: 360px;',
            'before_widget' => '<div>',
            'after_widget'  => '</div>',
            'before_title'  => '<h2>',
            'after_title'   => '</h2>'
        ));
    }
    if (function_exists('register_sidebar')) {
        register_sidebar(array(
            'name' => 'Left Banner',
            'id'   => 'left-banner',
            'description'   => 'This widget is for the left banner width: 160px;
    height: 360px;',
            'before_widget' => '<div>',
            'after_widget'  => '</div>',
            'before_title'  => '<h2>',
            'after_title'   => '</h2>'
        ));
    }
    // register navs

    if (function_exists('register_nav_menus')) {

        function register_gilat_menus() {
          register_nav_menus(
            array(
              'main-nav' => __( 'Main Navigation Menu' ),
              'footer-nav' => __( 'Footer Menu' ),
              'side-nav' => __( 'Right Side Menu' ),

            )
          );
        }
    }

        add_action( 'init', 'register_gilat_menus' );

    function jptweak_remove_share() {
        remove_filter( 'the_content', 'sharing_display',19 );
        remove_filter( 'the_excerpt', 'sharing_display',19 );
    }
    add_action( 'loop_end', 'jptweak_remove_share' );

?>