/*-----------------------------------------------------------------------------------*/
/* GENERAL SCRIPTS */
/*-----------------------------------------------------------------------------------*/
jQuery(document).ready(function(){

	

	// Table alt row styling
	jQuery( '.entry table tr:odd' ).addClass( 'alt-table-row' );
	
	// FitVids - Responsive Videos
	jQuery( '.post, .widget, .panel, .page, #featured-slider .slide-media' ).fitVids();
	
	// Add class to parent menu items with JS until WP does this natively
	jQuery("ul.sub-menu, ul.children").parents('li').addClass('parent');
	
	
	// Responsive Navigation (switch top drop down for select)
	jQuery('ul#top-nav').mobileMenu({
		switchWidth: 767,                   //width (in px to switch at)
		topOptionText: 'Select a page',     //first option text
		indentString: '&nbsp;&nbsp;&nbsp;'  //string for indenting nested items
	});
  	
  	// Avoid widows in headings
  	jQuery("article header h1 a, .single article header h1, .product_title, .page-title, h1.title a, .product a h3").each(function(){var wordArray=jQuery(this).text().split(" ");var finalTitle="";for(i=0;i<=wordArray.length-1;i++){finalTitle+=wordArray[i];if(i==(wordArray.length-2)){finalTitle+="&nbsp;"}else{finalTitle+=" "}}jQuery(this).html(finalTitle)});
  	
  	// Show/hide the main navigation
  	jQuery('.nav-toggle').click(function() {
	  jQuery('#navigation').slideToggle('fast', function() {
	  	return false;
	    // Animation complete.
	  });
	});
	
	// Stop the navigation link moving to the anchor (Still need the anchor for semantic markup)
	jQuery('.nav-toggle a').click(function(e) {
        e.preventDefault();
    });
 	
	// Show/hide the mini-cart
	jQuery('#header a.cart-parent').click(function() {
		jQuery('#header .cart_list').fadeToggle('fast', function() {
			return false;
			// Animation complete.
		});
	});

	jQuery('#home-shop a.cart-parent').click(function(e) {
		e.preventDefault();
		jQuery('#home-shop .cart_list').fadeToggle('fast', function() {
			return false;
			// Animation complete.
		});
	});

});

jQuery(window).load(function(){
	
	// Add class to last comment child
	jQuery( 'ol.commentlist .parent li:last' ).addClass('last-comment');
	

});