<!DOCTYPE html>
<html dir="rtl" lang="he">
 <!--rtl definitio - look into that later 
<html <?php language_attributes(); ?>>
-->

<head>
	<!-- IE Fix for HTML5 Tags -->
	<!--[if lt IE 9]>
	<script src=" http://html5shiv.googlecode.com/svn/trunk/html5.js "></script>
	<![endif]--> 
	<meta charset="<?php bloginfo('charset'); ?>" />
	
	<?php if (is_search()) { ?>
	   <meta name="robots" content="noindex, nofollow" /> 
	<?php } ?>

	<title>
		   <?php
		      if (function_exists('is_tag') && is_tag()) {
		         single_tag_title("Tag Archive for &quot;"); echo '&quot; - '; }
		      elseif (is_archive()) {
		         wp_title(''); echo ' Archive - '; }
		      elseif (is_search()) {
		         echo 'Search for &quot;'.wp_specialchars($s).'&quot; - '; }
		      elseif (!(is_404()) && (is_single()) || (is_page())) {
		         wp_title(''); echo ' - '; }
		      elseif (is_404()) {
		         echo 'Not Found - '; }
		      if (is_home()) {
		         bloginfo('name'); echo ' - '; bloginfo('description'); }
		      else {
		          bloginfo('name'); }
		      if ($paged>1) {
		         echo ' - page '. $paged; }
		   ?>
	</title>
	
	<link rel="shortcut icon" href="/favicon.ico">
	
	<link rel="stylesheet" href="<?php bloginfo('stylesheet_url'); ?>">
	<?php if(is_page_template('contact.php')) :?>
	<link rel="stylesheet" type="text/css" href="<?php bloginfo('template_directory'); ?>/css/main-contact.css" media="screen" />
	<?php endif;?>
	
	<link rel="pingback" href="<?php bloginfo('pingback_url'); ?>">

	<?php if ( is_singular() ) wp_enqueue_script('comment-reply'); ?>

	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?> class="home">
	
	<div id="page-wrap" class="page-wrap">
		<header>
				<div class="info">
					<div class="contact">
						לשירותכם: טלפון 077-5177807 / 052-2456313 | דוא"ל <a class="mailto" href="mailto:gilataviv@gmail.com">gilataviv@gmail.com</a> | <a class="contact-upper" href=" <?php bloginfo( home_url('/') ); ?>/צור-קשר">צור קשר</a>
					</div>
					<!--<nav class="lang">
						<a href="#">עברית</a> |
						<a href="#">English</a>


					</nav>-->
				</div>
				<div class="branding">
					<div class="faces">
 					<?php if (function_exists('dynamic_sidebar') && dynamic_sidebar('header-widget')) : else : ?>
					<?php endif; ?>
					</div>
					<a href="/">

						<div class="logo">
				
						</div>
					</a>
					<div class="social-media">
							
							<a target="_blank" href="http://www.facebook.com/gilataviv" class="facebook"></a>
							<a target="_blank" href="http://plus.google.com/u/0/b/103667738054320152580/103667738054320152580" class="plus"></a>
							<a target="_blank" href="http://youtube.com/user/avivgilat" class="youtube"></a>

					</div>
				</div><!-- END div.branding -->


		</header>
			<nav class="main-nav">
				<?php
				          wp_nav_menu( array(
				    'theme_location' => 'main-nav', // Setting up the location for the main-menu, Main Navigation.
				    'menu_class' => 'dropdown', //Adding the class for dropdowns
				    'container_id' => 'navwrap', //Add CSS ID to the containter that wraps the menu.
				    'fallback_cb' => 'wp_page_menu', //if wp_nav_menu is unavailable, WordPress displays wp_page_menu function, which displays the pages of your blog.
				    )
				      );
				?>

			</nav>