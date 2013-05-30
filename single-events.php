<?php get_header(); ?>

	<?php if (have_posts()) : while (have_posts()) : the_post(); ?>

		<div <?php post_class() ?> id="post-<?php the_ID(); ?>">

 <?php get_sidebar('left'); ?>

			<div class="entry">
				<?php the_content(); ?>
			</div>
			
			<?php edit_post_link('Edit this entry','','.'); ?>
			
		</div>

	<?php endwhile; endif; ?>
	
<?php get_sidebar('right'); ?>


<?php get_footer(); ?>