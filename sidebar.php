    
<!--     	<?php get_search_form(); ?>
 -->    
    	<div class="right-col">
                <article class="company-profile">
                            <img src="<?php bloginfo('template_url');?>/images/gilat-aviv.png" />
                <?php if (function_exists('dynamic_sidebar') && dynamic_sidebar('Company Info')) : else : ?>
                <?php endif; ?>


                    <nav class="about">
                        <a class="profile-about" href=" <?php bloginfo( home_url('/') ); ?>/אודות">אודות</a>
                        <a class="profile-about" href=" <?php bloginfo( home_url('/') ); ?>/נבחרת-המרצים-3">נבחרת המרצים</a>
                        <a class="profile-about" href=" <?php bloginfo( home_url('/') ); ?>/לקוחותינו">לקוחותינו</a>
                        <a class="profile-about" href=" <?php bloginfo( home_url('/') ); ?>/צור-קשר">צור קשר</a>
                    </nav>

                </article>

                <article class="new-speakers">
                <?php if (function_exists('dynamic_sidebar') && dynamic_sidebar('New Speakers')) : else : ?>
                <?php endif; ?>
                    <!--<h2>מרצים חדשים - תוכן חדש אצלנו</h2>
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

                    </ul>
                -->

                <a href="http://atwild.com/נבחרת-המרצים-3/">&larr; הצג עוד </a>

                </article>

<!--                 <article class="recent-events">
 -->                <?php if (function_exists('dynamic_sidebar') && dynamic_sidebar('Recent Events')) : else : ?>
                <?php endif; ?>
                    <!--<h2>לוח אירועים <time data-look-into this="???">09.2012</time></h2>
                    

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

                    </ul>
                    -->
                   <!--  <a href="#">&larr; הצג עוד </a>
                </article> -->

                <div class="facebook-widget">
                <?php if (function_exists('dynamic_sidebar') && dynamic_sidebar('Facebook')) : else : ?>
                <?php endif; ?>
                </div>
    
                <?php if (function_exists('dynamic_sidebar') && dynamic_sidebar('Sidebar Widgets')) : else : ?>
                <?php endif; ?>
        <!-- All this stuff in here only shows up if you DON'T have any widgets active in this zone -->

            </aside><!-- END aside.widget-area -->

        </div><!-- END div.right-col -->