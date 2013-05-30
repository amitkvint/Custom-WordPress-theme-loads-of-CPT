<aside class="sidebar-right">
			<nav class="side-nav">
			<?php if (function_exists('dynamic_sidebar') && dynamic_sidebar('nav-widget')) : else : ?>
			<?php endif; ?>
			</nav>
			<div class="marketing">
				<?php if (function_exists('dynamic_sidebar') && dynamic_sidebar('right-banner')) : else : ?>
				<?php endif; ?>			</div>
</aside>

		<p class="clear"></p>