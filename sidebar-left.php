
		<aside class="sidebar-left">
			<article class="quick-contact">
				
					<?php if (function_exists('dynamic_sidebar') && dynamic_sidebar('quick-contact')) : else : ?>
					<?php endif; ?>

			</article><!--END article.tag-widget-category -->
			<div class="marketing">
				<?php if (function_exists('dynamic_sidebar') && dynamic_sidebar('left-banner')) : else : ?>
				<?php endif; ?>			</div>

			<article class="tag-widget-category">
				<a href="#">
						<h2>קטגוריית ענן</h2>
					</a>
					<?php if (function_exists('dynamic_sidebar') && dynamic_sidebar('tag1-left')) : else : ?>
					<?php endif; ?>

					<a href="#">&larr; הצג עוד </a>
			</article><!--END article.tag-widget-category -->
			<article class="tag-widget">
				<a href="#">
						<h2>תגית ענן</h2>
					</a>
					<?php if (function_exists('dynamic_sidebar') && dynamic_sidebar('tag2-left')) : else : ?>
					<?php endif; ?>

					<a href="#">&larr; הצג עוד </a>
			</article><!--END article.tag-widget-->
		</aside><!-- END aside.sidebar-left-->