		<footer>
			<div class="footer-branding">
				<div class="share">
				<?php echo sharing_display() ?>
				</div>
				<img src="<?php bloginfo('template_url');?>/images/footer-branding.png"/>		
				<div class="copyright">
				&copy; <a href="/"><?php echo date("Y"); echo " "; bloginfo('name'); ?></a>
					| <a href="#">תנאי שימוש באתר</a> | 
					<a href="#">מפת אתר</a> 
				</div>
				<nav class="footer-nav">
						<?php wp_nav_menu(array('menu' => 'footer-nav','after' => ' |')); ?>
				</nav>

				
			</div>
		</footer>


	</div><!-- END div.page-wrap -->

	<?php wp_footer(); ?>
	
	<!-- Don't forget analytics -->
	
</body>

</html>
