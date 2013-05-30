<?php

/* 
Template Name: homepage
*/

?>

<?php get_header(); ?>

<?php get_sidebar(); ?>

<div class="content">
			<div class="left-col">
				<section class="boxes">

					<div class="box-speakers">
							<div class="avatar-speakers">
<!--<?php wp_carousel(1); ?>-->
								<?php if (function_exists('dynamic_sidebar') && dynamic_sidebar('Speakers-Slider')) : else : ?>
								<?php endif; ?>
                
<!-- 
								<a href="#"><img src="images/pina.png" /></a>
								<a href="#"><img src="images/pina.png" /></a>
								<a href="#"><img src="images/pina.png" /></a>
								<a href="#"><img src="images/pina.png" /></a>
								<a href="#"><img src="images/pina.png" /></a> -->
							</div> <!-- END div.avatar-speakers -->
					</div><!-- END div.box-speakers -->
					<div class="home-contact">
								<?php if (function_exists('dynamic_sidebar') && dynamic_sidebar('Home-Contact')) : else : ?>
								<?php endif; ?>

					</div>
					<div class="box-lectures">
						<a href=" <?php bloginfo( home_url('/') ); ?> /הרצאות">
							<h2>הרצאות</h2>
						</a>
						<h3>כותרת משנה סדנאות</h3>
					</div>

					<div class="box-workshops">
						<a href="<?php bloginfo( home_url('/') ); ?> /סדנאות">
							<h2>סדנאות</h2>
						</a>
						<h3>כותרת משנה הרצאות</h3>
					</div>

					<div class="box-events">
						<a href="<?php bloginfo( home_url('/') ); ?> /אירועי-קונספט/">
							<h2>מופעים ואירועי<br />קונספט</h2>
						</a>
						<h3>כותרת משנה מופעים ואירועי קונספט</h3>
					</div>

				</section><!-- END section.boxes -->


				<article class="latest-posts">
				<?php if (function_exists('dynamic_sidebar') && dynamic_sidebar('Latest Posts')) : else : ?>
                <?php endif; ?>
                <!--<a href="#">
						<h2>צפיית מאמרים</h2>
					</a>
					<ul>

						<li>
							רשימות למחיקה קישורים מתן דת, של בידור קלאסיים ותשובות כדי, זאת המשפט רשימות גאוגרפיה של.
						</li>
						<li>
							רשימות למחיקה קישורים מתן דת, של בידור קלאסיים ותשובות כדי, זאת המשפט רשימות גאוגרפיה של.
						</li>
						<li>
							רשימות למחיקה קישורים מתן דת, של בידור קלאסיים ותשובות כדי, זאת המשפט רשימות גאוגרפיה של.
						</li>
						<li>
							רשימות למחיקה קישורים מתן דת, של בידור קלאסיים ותשובות כדי, זאת המשפט רשימות גאוגרפיה של.
						</li>

					</ul>
				-->


				</article>
				
		</div> <!-- END div.left-col -->

		
			<section class="watch-video clearfix">
				<?php if (function_exists('dynamic_sidebar') && dynamic_sidebar('Videos')) : else : ?>
                <?php endif; ?>
				</div>

				<a href="http://atwild.com/הרצאות-לצפייה/">&larr; הצג עוד </a>
		<p class="clear">
		</section>
		<!-- END section.watch-video -->
		
		</div><!-- END div.content -->

<?php get_footer(); ?>