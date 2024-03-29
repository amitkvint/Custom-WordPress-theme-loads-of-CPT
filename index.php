<?php

/* 
Template Name: gilat posts
*/

?>
<?php get_header(); ?>
				<?php get_sidebar('left'); ?>
				<section class="main-content">

	<?php if (have_posts()) : while (have_posts()) : the_post(); ?>	
		<div class="post" id="post-<?php the_ID(); ?>">

			<h2><?php the_title(); ?></h2>



			<div class="entry">

				<?php the_content(); ?>

				<?php wp_link_pages(array('before' => 'Pages: ', 'next_or_number' => 'number')); ?>

			</div>


			<?php edit_post_link('Edit this entry.', '<p>', '</p>'); ?>

		</div>

		
		<?php // comments_template(); ?>

		<?php endwhile; endif; ?>
								</section>
								<?php get_sidebar('right'); ?>


</div>

<?php get_footer(); ?>