<?php

/* 
Template Name: contact
*/

?>
<?php get_header(); ?>

	<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
			
		<div class="post" id="post-<?php the_ID(); ?>">
			
<?php get_sidebar('left'); ?>

			<div class="entry">

<!--  				<h2 class="form-title"><?php the_title(); ?></h2>
 -->
				<?php the_content(); ?>


				<?php wp_link_pages(array('before' => 'Pages: ', 'next_or_number' => 'number')); ?>

			</div>

			<?php edit_post_link('Edit this entry.', '<p>', '</p>'); ?>

		</div>
		
		<?php // comments_template(); ?>

		<?php endwhile; endif; ?>

<?php get_sidebar('right'); ?>

<?php get_footer(); ?>