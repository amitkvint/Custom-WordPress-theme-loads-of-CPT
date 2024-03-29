<?php
class SitePress{

    private $settings;
    private $active_languages = array();
    private $this_lang;
    private $wp_query;
    private $admin_language = null;

    function __construct(){
        global $wpdb, $pagenow;

        $this->settings = get_option('icl_sitepress_settings');

        if(false != $this->settings){
            $this->verify_settings();
        }
        if(isset($_GET['icl_action'])){
            require_once ABSPATH . WPINC . '/pluggable.php';
            if($_GET['icl_action']=='reminder_popup'){
                add_action('init', array($this, 'reminders_popup'));
            }
            elseif($_GET['icl_action']=='dismiss_help'){
                $this->settings['dont_show_help_admin_notice'] = true;
                $this->save_settings();
            }elseif($_GET['icl_action']=='dbdump' ){
                include_once ICL_PLUGIN_PATH . '/inc/functions-troubleshooting.php';
                add_action('init', 'icl_troubleshooting_dumpdb');                                
            }
        }

        if(isset($_GET['page']) && $_GET['page']== ICL_PLUGIN_FOLDER . '/menu/troubleshooting.php' && isset($_GET['debug_action'])){
            ob_start();
        }

        if(isset($_REQUEST['icl_ajx_action'])){
            add_action('init', array($this, 'ajax_setup'));
        }

        add_action('plugins_loaded', array($this,'init'), 1);
        add_action('plugins_loaded', array($this,'initialize_cache'), 0);

        add_action('admin_print_scripts', array($this,'js_scripts_setup'));
        add_action('admin_print_styles', array($this,'css_setup'));
        
        // Administration menus
        add_action('admin_menu', array($this, 'administration_menu'));
        add_action('init', array($this, '_taxonomy_languages_menu'), 99); //allow hooking in

        // Process post requests
        if(!empty($_POST)){
            add_action('init', array($this,'process_forms'));
        }

        add_action('init', array($this,'plugin_localization'));

        if($this->settings['existing_content_language_verified']){

            // Post/page language box
            if($pagenow == 'post.php' || $pagenow == 'post-new.php'){
                add_action('admin_head', array($this,'post_edit_language_options'));            
            }
            
            // Post/page save actions
            add_action('save_post', array($this,'save_post_actions'), 10, 2); 
                   
            // Post/page delete actions
            add_action('delete_post', array($this,'delete_post_actions'));
            add_action('trash_post', array($this,'trash_post_actions'));
            add_action('untrashed_post', array($this,'untrashed_post_actions'));

            add_filter('posts_join', array($this,'posts_join_filter'));
            add_filter('posts_where', array($this,'posts_where_filter'));
            add_filter('comment_feed_join', array($this,'comment_feed_join'));
            
            add_filter('comments_clauses', array($this, 'comments_clauses'));

            // Allow us to filter the Query before vars before the posts query is being built and executed
            add_filter('pre_get_posts', array($this, 'pre_get_posts'));

            // show untranslated posts
            if(!is_admin() && isset($this->settings['show_untranslated_blog_posts']) &&
                    $this->settings['show_untranslated_blog_posts'] && $this->get_current_language() != $this->get_default_language()){
                add_filter('the_posts', array($this, 'the_posts'));
            }

            $this->queries = array();

            /* preWP3 compatibility  - start */
            if(ICL_PRE_WP3){
                if($pagenow == 'edit.php'){
                    add_action('restrict_manage_posts', array($this,'language_filter'));
                }elseif($pagenow == 'edit-pages.php'){
                    add_action('admin_footer', array($this,'language_filter'));
                }
            }else{
            /* preWP3 compatibility  - end */
                if($pagenow == 'edit.php'){
                    add_action('admin_footer', array($this,'language_filter'));
                    //add_action('restrict_manage_posts', array($this,'language_filter'));
                }
            }


            add_filter('get_pages', array($this, 'exclude_other_language_pages2'));
            add_filter('wp_dropdown_pages', array($this, 'wp_dropdown_pages'));



            // posts and pages links filters
            add_filter('post_link', array($this, 'permalink_filter'),1,2);
            add_filter('post_type_link', array($this, 'permalink_filter'),1,2);
            add_filter('page_link', array($this, 'permalink_filter'),1,2);

            if(version_compare(preg_replace('#-RC[0-9]+(-[0-9]+)?$#', '', $GLOBALS['wp_version']), '3.1', '<')){
                add_filter('category_link', array($this, 'category_permalink_filter'),1,2);
                add_filter('tag_link', array($this, 'tax_permalink_filter'),1,2);
            }

            add_filter('term_link', array($this, 'tax_permalink_filter'),1,2);

            add_filter('get_comment_link', array($this, 'get_comment_link_filter'));

            add_action('create_term',  array($this, 'create_term'),1, 2);
            add_action('edit_term',  array($this, 'create_term'),1, 2);
            add_action('delete_term',  array($this, 'delete_term'),1,3);
            
            add_filter('get_terms_args', array($this, 'get_terms_args_filter'));
            // filters terms by language
            /* preWP3 compatibility  - start */
            if(ICL_PRE_WP3){
                add_filter('list_terms_exclusions', array($this, 'exclude_other_terms'),1,2);
            }
            /* preWP3 compatibility  - end */
            add_filter('terms_clauses', array($this, 'terms_clauses'));
            

            // allow adding terms with the same name in different languages
            add_filter("pre_term_name", array($this, 'pre_term_name'), 1, 2);
            // allow adding categories with the same name in different languages
            add_action('admin_init', array($this, 'pre_save_category'));

            // category language selection
            // add_action('edit_category',  array($this, 'create_term'),1, 2);

            /* preWP3 compatibility  - start */
            if(ICL_PRE_WP3 && $pagenow == 'categories.php'){
                add_action('admin_print_scripts-categories.php', array($this,'js_scripts_categories'));
                add_action('edit_category_form', array($this, 'edit_term_form'));
                add_action('admin_footer', array($this,'terms_language_filter'));
            }
            /* preWP3 compatibility  - end */

            // custom hook for adding the language selector to the template
            add_action('icl_language_selector', array($this, 'language_selector'));

            // front end js
            add_action('wp_head', array($this, 'front_end_js'));

            add_action('wp_head', array($this,'rtl_fix'));
            add_action('admin_print_styles', array($this,'rtl_fix'));

            add_action('restrict_manage_posts', array($this, 'restrict_manage_posts'));

            /* preWP3 compatibility  - start */
            if(ICL_PRE_WP3){
                add_action('admin_print_scripts-edit-pages.php', array($this,'restrict_manage_pages'));
            }
            /* preWP3 compatibility  - endif */

            add_filter('get_edit_post_link', array($this, 'get_edit_post_link'), 1, 3);
            add_filter('get_edit_term_link', array($this, 'get_edit_term_link'), 1, 4);

            // short circuit get default category
            add_filter('pre_option_default_category', array($this, 'pre_option_default_category'));
            add_filter('update_option_default_category', array($this, 'update_option_default_category'), 1, 2);

            add_filter('the_category', array($this,'the_category_name_filter'));
            add_filter('get_terms', array($this,'get_terms_filter'));
            add_filter('single_cat_title', array($this,'the_category_name_filter'));
            add_filter('term_links-category', array($this,'the_category_name_filter'));

            add_filter('term_links-post_tag', array($this,'the_category_name_filter'));
            add_filter('tags_to_edit', array($this,'the_category_name_filter'));
            add_filter('single_tag_title', array($this,'the_category_name_filter'));

            // adjacent posts links
            add_filter('get_previous_post_join', array($this,'get_adjacent_post_join'));
            add_filter('get_next_post_join', array($this,'get_adjacent_post_join'));
            add_filter('get_previous_post_where', array($this,'get_adjacent_post_where'));
            add_filter('get_next_post_where', array($this,'get_adjacent_post_where'));

            // feeds links
            add_filter('feed_link', array($this,'feed_link'));

            // commenting links
            add_filter('post_comments_feed_link', array($this,'post_comments_feed_link'));
            add_filter('trackback_url', array($this,'trackback_url'));
            add_filter('user_trailingslashit', array($this,'user_trailingslashit'),1, 2);

            // date based archives
            add_filter('year_link', array($this,'archives_link'));
            add_filter('month_link', array($this,'archives_link'));
            add_filter('day_link', array($this,'archives_link'));
            add_filter('getarchives_join', array($this,'getarchives_join'));
            add_filter('getarchives_where', array($this,'getarchives_where'));
            add_filter('pre_option_home', array($this,'pre_option_home'));

            if (!is_admin()) {
                add_filter('attachment_link', array($this, 'attachment_link_filter'), 10, 2);
            }
            
            // Filter custom type archive link (since WP 3.1)
            add_filter('post_type_archive_link', array($this,'post_type_archive_link_filter'), 10, 2);
            
            /* preWP3 compatibility  - start */
            if(ICL_PRE_WP3){
            // author template
                add_filter('author_link', array($this,'convert_url'));
            }
            /* preWP3 compatibility  - end */
            add_filter('author_link', array($this,'author_link'));

            add_filter('home_url', array($this, 'home_url'), 1, 4) ;

            // language negotiation
            add_action('query_vars', array($this,'query_vars'));

            //
            add_filter('language_attributes', array($this, 'language_attributes'));

            add_action('locale', array($this, 'locale'));

            if(isset($_GET['____icl_validate_domain'])){ echo '<!--'.get_option('home').'-->'; exit; }

            add_filter('pre_option_page_on_front', array($this,'pre_option_page_on_front'));
            add_filter('pre_option_page_for_posts', array($this,'pre_option_page_for_posts'));

            add_filter('option_sticky_posts', array($this,'option_sticky_posts'));

            add_filter('request', array($this,'request_filter'));

            add_action('wp_head', array($this,'set_wp_query'));

            add_action('show_user_profile', array($this, 'show_user_options'));
            add_action('personal_options_update', array($this, 'save_user_options'));

            // column with links to translations (or add translation) - low priority
            add_action('init', array($this,'configure_custom_column'), 16);            
            


            // adjust queried categories and tags ids according to the language
            if($this->settings['auto_adjust_ids']){
                add_action('parse_query', array($this, 'parse_query'));
                add_action('wp_list_pages_excludes', array($this, 'adjust_wp_list_pages_excludes'));
                if(!is_admin()){
                    add_filter('get_term', array($this,'get_term_adjust_id'), 1, 1);
                    add_filter('category_link', array($this,'category_link_adjust_id'), 1, 2);
                    add_filter('get_terms', array($this,'get_terms_adjust_ids'), 1, 3);
                    add_filter('get_pages', array($this,'get_pages_adjust_ids'), 1, 2);
                }
            }

            if(!is_admin()){
                add_action('wp_head', array($this, 'meta_generator_tag'));
            }

            if(!ICL_PRE_WP3){
                require_once ICL_PLUGIN_PATH . '/inc/wp-nav-menus/iclNavMenu.class.php';
                $iclNavMenu = new iclNavMenu;
            }

            if(is_admin() || defined('XMLRPC_REQUEST')){
                global $iclTranslationManagement, $ICL_Pro_Translation;
                $iclTranslationManagement = new TranslationManagement;
                $ICL_Pro_Translation = new ICL_Pro_Translation();                 
            }
            
            add_action('wp_login', array($this, 'reset_admin_language_cookie'));

        } //end if the initial language is set - existing_content_language_verified

        add_action('wp_dashboard_setup', array($this, 'dashboard_widget_setup'));
        if(is_admin() && $pagenow == 'index.php'){
            add_action('icl_dashboard_widget_notices', array($this, 'print_translatable_custom_content_status'));
            if(trim(get_option('permalink_structure'), '/') == '%postname%'){
                add_action('icl_dashboard_widget_notices', array($this, 'warn_permalink_structure'));
            }
        }

        if($pagenow == 'options-permalink.php' && trim(get_option('permalink_structure'), '/') == '%postname%'){
            add_action('admin_notices', array($this, 'warn_permalink_structure'));
        }

        if(is_admin()){
            add_action('icl_post_languages_options_after', array($this, 'copy_from_original'));    
        }
        
        add_filter('core_version_check_locale', array($this, 'wp_upgrade_locale'));
        
        if($pagenow == 'post.php' && $_REQUEST['action']=='edit' && isset($_GET['post'])){
            add_action('init', '_icl_trash_restore_prompt');
        }
        
    }

    function init(){
        global $wpdb;
        
        // // default value for theme_localization_type OR
        // reset theme_localization_type if string translation was on (theme_localization_type was set to 2) and then it was deactivated
        if(
            !isset($this->settings['theme_localization_type']) ||
            isset($this->settings['theme_localization_type']) && $this->settings['theme_localization_type'] == 1 && !defined('WPML_ST_VERSION')
        ){
            if(!defined('WPML_DOING_UPGRADE')){
                $this->settings['theme_localization_type'] = 2;    
            }
            
        }
        
        $this->set_admin_language();
        //configure callbacks for plugin menu pages
        if(defined('WP_ADMIN') && isset($_GET['page']) && 0 === strpos($_GET['page'],basename(ICL_PLUGIN_PATH).'/')){
            add_action('icl_menu_footer', array($this, 'menu_footer'));
        }
        if($this->settings['existing_content_language_verified']){
            if(defined('WP_ADMIN')){
                if(isset($_GET['lang'])){
                    $this->this_lang = rtrim($_GET['lang'],'/');    
                // force default language for string translation
                // we also make sure it's not saved in the cookie
                }elseif(isset($_GET['page']) && 
                    ((defined('WPML_ST_FOLDER') && $_GET['page'] == WPML_ST_FOLDER . '/menu/string-translation.php') || 
                    (defined('WPML_TM_FOLDER') && $_GET['page'] == WPML_TM_FOLDER . '/menu/translations-queue.php'))
                    ){
                    $this->this_lang = $this->get_default_language();                        
                }elseif($lang = $this->get_admin_language_cookie()){
                    $this->this_lang = $lang;                                    
                }else{
                    $this->this_lang = $this->get_default_language();
                }  
                if((isset($_GET['admin_bar']) && $_GET['admin_bar']==1) && (!isset($_GET['page']) || !defined('WPML_ST_FOLDER') || $_GET['page'] != WPML_ST_FOLDER . '/menu/string-translation.php')){
                    $this->set_admin_language_cookie();              
                }
            }else{
                $al = $this->get_active_languages();
                foreach($al as $l){
                    $active_languages[] = $l['code'];
                }
                $active_languages[] = 'all';
                $s = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on'?'s':'';
                $request = 'http' . $s . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                $home = get_option('home');
                if($s){
                    $home = preg_replace('#^http://#', 'https://', $home);    
                }                
                $url_parts = parse_url($home);
                $blog_path = !empty($url_parts['path'])?$url_parts['path']:'';
                switch($this->settings['language_negotiation_type']){
                    case 1:
                        $path  = str_replace($home,'',$request);
                        $parts = explode('?', $path);
                        $path = $parts[0];
                        $exp = explode('/',trim($path,'/'));

                        if(in_array($exp[0], $active_languages)){
                            $this->this_lang = $exp[0];

                            // before hijiking the SERVER[REQUEST_URI]
                            // override the canonical_redirect action
                            // keep a copy of the original request uri
                            remove_action('template_redirect', 'redirect_canonical');
                            global $_icl_server_request_uri;
                            $_icl_server_request_uri = $_SERVER['REQUEST_URI'];
                            add_action('template_redirect', 'icl_redirect_canonical_wrapper', 11);
                            function icl_redirect_canonical_wrapper(){
                                global $_icl_server_request_uri, $wp_query;
                                $requested_url  = ( !empty($_SERVER['HTTPS'] ) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
                                $requested_url .= $_SERVER['HTTP_HOST'];
                                $requested_url .= $_icl_server_request_uri;
                                redirect_canonical($requested_url);

                                /*
                                if($wp_query->query_vars['error'] == '404'){
                                    $wp_query->is_404 = true;
                                    $template = get_404_template();
                                    include($template);
                                    exit;
                                }
                                */

                            }
                            //

                            //deal with situations when template files need to be called directly
                            add_action('template_redirect', array($this, '_allow_calling_template_file_directly'));

                            //$_SERVER['REQUEST_URI'] = preg_replace('@^'. $blog_path . '/' . $this->this_lang.'@i', $blog_path ,$_SERVER['REQUEST_URI']);

                            // Check for special case of www.example.com/fr where the / is missing on the end
                            $parts = parse_url($_SERVER['REQUEST_URI']);
                            if(strlen($parts['path']) == 0){
                                $_SERVER['REQUEST_URI'] = '/' . $_SERVER['REQUEST_URI'];
                            }
                        }else{
                            $this->this_lang = $this->get_default_language();
                        }
                        break;
                    case 2:
                        $exp = explode('.', $_SERVER['HTTP_HOST']);
                        $__l = array_search('http' . $s . '://' . $_SERVER['HTTP_HOST'] . $blog_path, $this->settings['language_domains']);
                        $this->this_lang = $__l?$__l:$this->get_default_language();
                        if(defined('ICL_USE_MULTIPLE_DOMAIN_LOGIN') && ICL_USE_MULTIPLE_DOMAIN_LOGIN){
                            include ICL_PLUGIN_PATH . '/modules/multiple-domains-login.php';
                        }
                        break;
                    case 3:
                    default:
                        if(isset($_GET['lang'])){
                            $this->this_lang = preg_replace("/[^0-9a-zA-Z-]/i", '',$_GET['lang']);
                        // set the language based on the content id - for short links
                        }elseif(isset($_GET['page_id'])){
                            $this->this_lang = $wpdb->get_var($wpdb->prepare("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_type='post_page' AND element_id=%d", $_GET['page_id']));
                        }elseif(isset($_GET['p'])){
                            $post_type = $wpdb->get_var($wpdb->prepare("SELECT post_type FROM {$wpdb->posts} WHERE ID=%d", $_GET['p']));
                            $this->this_lang = $wpdb->get_var($wpdb->prepare("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_type=%s AND element_id=%d",
                                'post_' . $post_type, $_GET['p']));
                        }elseif(isset($_GET['cat_ID'])){
                            $cat_tax_id = $wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy=%s",
                                $_GET['cat_ID'], 'category'));
                            $this->this_lang = $wpdb->get_var($wpdb->prepare("SELECT language_code FROM {$wpdb->prefix}icl_translations
                                WHERE element_type='tax_category' AND element_id=%d", $cat_tax_id));
                        }elseif(isset($_GET['tag'])){
                            $tag_tax_id = $wpdb->get_var($wpdb->prepare("
                                SELECT x.term_taxonomy_id FROM {$wpdb->term_taxonomy} x JOIN {$wpdb->terms} t ON t.term_id = x.term_id
                                WHERE t.slug='%s' AND x.taxonomy='%s'",
                                $_GET['tag'], 'post_tag'));
                            $this->this_lang = $wpdb->get_var($wpdb->prepare("SELECT language_code FROM {$wpdb->prefix}icl_translations
                                WHERE element_type='tax_post_tag' AND element_id=%d", $tag_tax_id));
                        }
                        //
                        if(!isset($_GET['lang']) && ($this->this_lang && $this->this_lang != $this->get_default_language())){
                            if(!isset($GLOBALS['wp_rewrite'])){
                                require_once ABSPATH . WPINC . '/rewrite.php';
                                $GLOBALS['wp_rewrite'] = new WP_Rewrite();
                            }
                            define('ICL_DOING_REDIRECT', true);
                            if(isset($_GET['page_id'])){
                                wp_redirect(get_page_link($_GET['page_id']), '301');
                                exit;
                            }elseif(isset($_GET['p'])){
                                wp_redirect(get_permalink($_GET['p']), '301');
                                exit;
                            }elseif(isset($_GET['cat_ID'])){
                                wp_redirect(get_term_link( intval($_GET['cat_ID']), 'category' ));
                                exit;
                            }elseif(isset($_GET['tag'])){
                                wp_redirect(get_term_link( $_GET['tag'], 'post_tag' ));
                                exit;
                            }else{
                                global $wp_taxonomies;
                                if(isset($this->settings['taxonomies_sync_option'])){
                                    $taxs = array_keys((array)$this->settings['taxonomies_sync_option']);
                                    foreach($taxs as $t){
                                        if(isset($_GET[$t])){
                                            $term_obj = $wpdb->get_row($wpdb->prepare(
                                                "SELECT * FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} x ON t.term_id = x.term_id
                                                WHERE t.slug=%s AND x.taxonomy=%s"
                                                , $_GET[$t], $t));
                                            $term_link = get_term_link( $term_obj, $t );
                                            $term_link = str_replace('&amp;', '&', $term_link); // fix
                                            if($term_link && !is_wp_error($term_link)){
                                                wp_redirect($term_link);
                                                exit;
                                            }
                                        }
                                    }
                                }
                            }

                        }

                        if(empty($this->this_lang)){
                            $this->this_lang = $this->get_default_language();
                        }
                }
                // allow forcing the current language when it can't be decoded from the URL
                $this->this_lang = apply_filters('icl_set_current_language', $this->this_lang);
            }
            
            //reorder active language to put 'this_lang' in front
            foreach($this->active_languages as $k=>$al){
                if($al['code']==$this->this_lang){
                    unset($this->active_languages[$k]);
                    $this->active_languages = array_merge(array($k=>$al), $this->active_languages);
                }
            }


            //if($this->settings['language_negotiation_type']==3){
               // fix pagenum links for when using the language as a parameter
            //   add_filter('get_pagenum_link', array($this,'get_pagenum_link_filter'));
            //}

            // filter some queries
            add_filter('query', array($this, 'filter_queries'));

            if( $this->settings['language_negotiation_type']==1  && $this->get_current_language()!=$this->get_default_language()){
                add_filter('option_rewrite_rules', array($this, 'rewrite_rules_filter'));
                if(version_compare($GLOBALS['wp_version'], '2.8.4', '<=')){
                    add_filter('transient_rewrite_rules', array($this, 'rewrite_rules_filter'));
                }
            }
                     
            $this->set_language_cookie();
            
            if(is_admin() && 
                (!isset($_GET['page']) || !defined('WPML_ST_FOLDER') || $_GET['page'] != WPML_ST_FOLDER . '/menu/string-translation.php') &&
                (!isset($_GET['page']) || !defined('WPML_TM_FOLDER') || $_GET['page'] != WPML_TM_FOLDER . '/menu/translations-queue.php')
            ){
                add_action('in_admin_header', array($this, 'admin_language_switcher'));
            }
            
        }

        // Automatic redirect
        if(!is_admin() && !empty($this->settings['automatic_redirect'])){
            add_action('template_redirect', array($this, 'automatic_redirect'));
        }

        if(is_admin() && empty($this->settings['dont_show_help_admin_notice'])){
            if(count($this->get_active_languages()) < 2){
                add_action('admin_notices', array($this, 'help_admin_notice'));
            }
        }

        $short_v = implode('.', array_slice(explode('.', ICL_SITEPRESS_VERSION), 0, 3));
        if(is_admin() && (!isset($this->settings['hide_upgrade_notice']) || $this->settings['hide_upgrade_notice'] != $short_v)){
            add_action('admin_notices', array($this, 'upgrade_notice'));
        }

        if(is_admin() && current_user_can('manage_options')){
            add_action('admin_notices', array($this, 'new_version_notice'));
            if($this->icl_account_configured()) {
                add_action('admin_notices', array($this, 'icl_reminders'));
            }
        }

        require ICL_PLUGIN_PATH . '/inc/template-constants.php';
        if(defined('WPML_LOAD_API_SUPPORT')){
            require ICL_PLUGIN_PATH . '/inc/wpml-api.php';

        }

        add_action('wp_footer', array($this, 'display_wpml_footer'),20);

        if(defined('XMLRPC_REQUEST') && XMLRPC_REQUEST){
            add_action('xmlrpc_call', array($this, 'xmlrpc_call_actions'));
            add_filter('xmlrpc_methods',array($this, 'xmlrpc_methods'));
        }

        if(defined('WPML_TM_VERSION') && is_admin()){
            require ICL_PLUGIN_PATH . '/inc/quote.php';
        }

    }
    
    function automatic_redirect(){

        $save_cookie = false;

        if(empty($_COOKIE['_icl_visitor_lang'])){
            //No cookie found
            $lang = $this->get_current_language();
            $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach($langs as $lang){
                $lang = preg_replace('#q=(.+)$#', '', $lang);
                $lang = str_replace('-', '_', $lang); // normalize
                if(!in_array($lang, array_keys($this->get_active_languages()))){
                    $lang = substr($lang, 0, strpos($lang, '_'));
                }
                if(in_array($lang, array_keys($this->get_active_languages()))){
                    //'Got lang from browser: %s', $lang
                    $save_cookie = true;
                    break;
                }
            }

        }else{

            //'Cookie found: %s', $_COOKIE['_icl_visitor_lang']
            if($_COOKIE['_icl_visitor_lang'] != $this->get_current_language()){
                //'User changed language: %s', $this->get_current_language())
                $lang = $this->get_current_language();
                $save_cookie = true;
            }else{
                //'Using cookie lang: %s', $_COOKIE['_icl_visitor_lang']
                $lang = $_COOKIE['_icl_visitor_lang'];
            }

        }

        if($save_cookie){
            $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : $_SERVER['HTTP_HOST'];
            $cookie_path = defined('COOKIEPATH') ? COOKIEPATH : '/';
            $s = setcookie('_icl_visitor_lang', $lang, time()+3600 * intval($this->settings['remember_language']), $cookie_path, $cookie_domain);
            //'Saving cookie: %s', $lang
        }

        if($lang != $this->get_current_language()){
            //'Doing redirect from %s to %s', $this->get_current_language(), $lang
            if($this->settings['automatic_redirect'] == 1){
                $args['skip_missing'] = 1;
            }else{
                $args['skip_missing'] = 0;
            }
            $languages = $this->get_ls_languages($args);
            if(isset($languages[$lang])){
                wp_redirect($languages[$lang]['url'], 301);
                exit;
            }
        }
        //'No redirect needed.'
    }

    function ajax_setup(){        
        require ICL_PLUGIN_PATH . '/ajax.php';
    }

    function configure_custom_column(){
        global $pagenow, $wp_post_types;

        /* preWP3 compatibility  - start */
        if(ICL_PRE_WP3 && $pagenow == 'edit-pages.php'){
            $pagenow_ = $pagenow;
            $_REQUEST['post_type'] = 'page';
        }else{$pagenow_ = ''; }
        /* preWP3 compatibility  - end */

        $is_ajax = false;
        if($pagenow == 'admin-ajax.php'){
            if(
                isset($_POST['action']) && $_POST['action']=='inline-save' ||
                isset($_GET['action'])  && $_GET['action'] == 'fetch-list'
            ){
                $is_ajax = true;
            }
        }

        if(($pagenow == 'edit.php' || $pagenow_ == 'edit-pages.php' || $is_ajax)
                && empty($this->settings['hide_translation_controls_on_posts_lists'])){
            $post_type = isset($_REQUEST['post_type']) ? $_REQUEST['post_type'] : 'post';
            switch($post_type){
                case 'post': case 'page':
                    add_filter('manage_'.$post_type.'s_columns',array($this,'add_posts_management_column'));
                    if(ICL_PRE_WP3 && $pagenow == 'edit-pages.php' || isset($_GET['post_type']) && $_GET['post_type']=='page'){
                        add_action('manage_'.$post_type.'s_custom_column',array($this,'add_content_for_posts_management_column'));
                    }
                    add_action('manage_posts_custom_column',array($this,'add_content_for_posts_management_column'));
                    break;
                default:
                    if(in_array($post_type, array_keys($this->get_translatable_documents()))){
                        add_filter('manage_'.$post_type.'_posts_columns',array($this,'add_posts_management_column'));
                        if($wp_post_types[$post_type]->hierarchical){
                            add_action('manage_pages_custom_column',array($this,'add_content_for_posts_management_column'));
                            add_action('manage_posts_custom_column',array($this,'add_content_for_posts_management_column')); // add this too - for more types plugin
                        }else{
                            add_action('manage_posts_custom_column',array($this,'add_content_for_posts_management_column'));
                        }
                    }
            }
            add_action('admin_print_scripts', array($this, '__set_posts_management_column_width'));
        }
    }

    function _taxonomy_languages_menu(){
        // tags language selection
        global $pagenow, $wpdb;
        if($pagenow == 'edit-tags.php'){
            // handle case of the tax edit page (after a taxonomy has been added)            
            // needs to redirect back to
            if(isset($_GET['trid']) && isset($_GET['source_lang'])){
                $translations = $this->get_element_translations($_GET['trid'], 'tax_' . $_GET['taxonomy']);
                if(isset($translations[$_GET['lang']])){                                                    
                    wp_redirect(get_edit_term_link($translations[$_GET['lang']]->term_id, $_GET['taxonomy']));
                    exit;
                }else{
                     add_action( 'admin_notices', array($this, '_tax_adding') );
                }                
            }
            
            $taxonomy = isset($_GET['taxonomy']) ? $wpdb->escape($_GET['taxonomy']) : 'post_tag';
            if($this->is_translated_taxonomy($taxonomy)){
                add_action('admin_print_scripts-edit-tags.php', array($this,'js_scripts_tags'));
                if($taxonomy == 'category'){
                    add_action('edit_category_form', array($this, 'edit_term_form'));
                }else{
                    add_action('add_tag_form', array($this, 'edit_term_form'));
                    add_action('edit_tag_form', array($this, 'edit_term_form'));
                }   
                add_action('admin_footer', array($this,'terms_language_filter'));
                add_filter('wp_dropdown_cats', array($this, 'wp_dropdown_cats_select_parent'));
            }
        }
    }
    
    function _tax_adding(){
        $translations = $this->get_element_translations($_GET['trid'], 'tax_' . $_GET['taxonomy']);
        if(!empty($translations) && isset($translations[$_GET['source_lang']]->name)){
            $tax_name = apply_filters('the_category', $translations[$_GET['source_lang']]->name);
            echo '<div id="icl_tax_adding_notice" class="updated fade"><p>'. sprintf(__('Adding translation for: %s.', 'sitepress'), $tax_name). '</p></div>';
        }
    }
    
    function the_posts($posts){
        global $wpdb, $wp_query;
        $db = debug_backtrace();
        $custom_wp_query = isset($db[3]['object']) ? $db[3]['object'] : false;
        //exceptions
        if(
            ($this->get_current_language() == $this->get_default_language())  // original language
            || ($wp_query != $custom_wp_query)   // called by a custom query
            || (!$custom_wp_query->is_posts_page && !$custom_wp_query->is_home) // not the blog posts page
            || $wp_query->is_singular //is singular
            || !empty($custom_wp_query->query_vars['category__not_in'])
            //|| !empty($custom_wp_query->query_vars['category__in'])
            //|| !empty($custom_wp_query->query_vars['category__and'])
            || !empty($custom_wp_query->query_vars['tag__not_in'])
            || !empty($custom_wp_query->query_vars['post__in'])
            || !empty($custom_wp_query->query_vars['post__not_in'])
            || !empty($custom_wp_query->query_vars['post_parent'])
        ){

            //$wp_query->query_vars = $this->wp_query->query_vars;
            return $posts;
        }
        // get the posts in the default language instead
        $this_lang = $this->this_lang;
        $this->this_lang = $this->get_default_language();

        remove_filter('the_posts', array($this, 'the_posts'));

        $custom_wp_query->query_vars['suppress_filters'] = 0;

        if(isset($custom_wp_query->query_vars['pagename']) && !empty($custom_wp_query->query_vars['pagename'])){
            if (isset($custom_wp_query->queried_object_id) && !empty($custom_wp_query->queried_object_id)) {
                $page_id = $custom_wp_query->queried_object_id;
            } else {
                // urlencode added for languages that have urlencoded post_name field value
                $custom_wp_query->query_vars['pagename'] = urlencode($custom_wp_query->query_vars['pagename']);
                $page_id = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_name='{$custom_wp_query->query_vars['pagename']}' AND post_type='page'");
            }
            if($page_id){
                $tr_page_id = icl_object_id($page_id, 'page', false, $this->get_default_language());
                if($tr_page_id){
                    $custom_wp_query->query_vars['pagename'] = $wpdb->get_var("SELECT post_name FROM {$wpdb->posts} WHERE ID={$tr_page_id}");
                }
            }
        }



        // look for posts without translations
        if($posts){
            foreach($posts as $p){
                $pids[] = $p->ID;
            }
            $trids = $wpdb->get_col("
                SELECT trid
                FROM {$wpdb->prefix}icl_translations
                WHERE element_type='post_post' AND element_id IN (".join(',', $pids).") AND language_code = '".$this_lang."'");

            if(!empty($trids)){
                $posts_not_translated = $wpdb->get_col("
                    SELECT element_id, COUNT(language_code) AS c
                    FROM {$wpdb->prefix}icl_translations
                    WHERE trid IN (".join(',', $trids).") GROUP BY trid HAVING c = 1
                ");

                if(!empty($posts_not_translated)){
                        $GLOBALS['__icl_the_posts_posts_not_translated'] = $posts_not_translated;
                        add_filter('posts_where', array($this, '_posts_untranslated_extra_posts_where'), 99);
                }
            }


        }

        //fix page for posts
        unset($custom_wp_query->query_vars['page_id']); unset($custom_wp_query->query_vars['p']);

        $my_query = new WP_Query($custom_wp_query->query_vars);
        add_filter('the_posts', array($this, 'the_posts'));
        $this->this_lang = $this_lang;

        // create a map of the translated posts
        foreach($posts as $post){
            $trans_posts[$post->ID] = $post;
        }

        // loop original posts
        foreach($my_query->posts as $k=>$post){ // loop posts in the default language
            $trid = $this->get_element_trid($post->ID);
            $translations = $this->get_element_translations($trid); // get translations

            if(isset($translations[$this->get_current_language()])){ // if there is a translation in the current language
                if(isset($trans_posts[$translations[$this->get_current_language()]->element_id])){  //check the map of translated posts
                    $my_query->posts[$k] = $trans_posts[$translations[$this->get_current_language()]->element_id];
                }else{  // check if the translated post exists in the database still
                    $_post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE ID = %d AND post_status='publish' LIMIT 1", $translations[$this->get_current_language()]->element_id));
                    if(!empty($_post)){
                        $_post = sanitize_post($_post);
                        $my_query->posts[$k] = $_post;

                    }else{
                        $my_query->posts[$k]->original_language = true;
                    }
                }
            }else{
                $my_query->posts[$k]->original_language = true;
            }

        }

        if($custom_wp_query == $wp_query){
            $wp_query->max_num_pages = $my_query->max_num_pages;
        }

        $posts = $my_query->posts;

        unset($GLOBALS['__icl_the_posts_posts_not_translated']);
        remove_filter('posts_where', array($this, '_posts_untranslated_extra_posts_where'), 99);

        return $posts;
    }

    function _posts_untranslated_extra_posts_where($where){
        global $wpdb;
        $where .= ' OR ' . $wpdb->posts . '.ID IN (' . join(',', $GLOBALS['__icl_the_posts_posts_not_translated']) . ')';
        return $where;
    }

    function initialize_cache(){
        require_once ICL_PLUGIN_PATH . '/inc/cache.php';
        $this->icl_translations_cache = new icl_cache();
        $this->icl_locale_cache = new icl_cache('locale', true);
        $this->icl_flag_cache = new icl_cache('flags', true);
        $this->icl_language_name_cache = new icl_cache('language_name', true);
        $this->icl_term_taxonomy_cache = new icl_cache();
    }

    function set_admin_language(){
        global $wpdb, $current_user;

        if(is_null($current_user) && function_exists('wp_get_current_user')){
            $u = wp_get_current_user();
            if($u->ID > 0){
                $current_user = $u;
            }
        }

        $active_languages = array_keys($wpdb->get_col("SELECT code FROM {$wpdb->prefix}icl_languages WHERE active=1"));   //don't use method get_active_language()

        if(!empty($current_user->data->ID)){
            $this->admin_language = $this->get_user_admin_language($current_user->data->ID);
        }

        if($this->admin_language != '' && !in_array($this->admin_language, $active_languages)){
            delete_user_meta($current_user->data->ID,'icl_admin_language');
        }
        if(empty($this->settings['admin_default_language']) || !in_array($this->settings['admin_default_language'], $active_languages)){
            $this->settings['admin_default_language'] = '_default_';
            $this->save_settings();
        }

        if(!$this->admin_language){
            $this->admin_language = $this->settings['admin_default_language'];
        }
        if($this->admin_language == '_default_' && $this->get_default_language()){
            $this->admin_language = $this->get_default_language();
        }
        
    }

    function get_admin_language(){
        return $this->admin_language;
    }

    function get_user_admin_language($user_id) {
        static $lang = null;
        if ($lang === null) {
            $lang = get_user_meta($user_id,'icl_admin_language',true);
        }
        return $lang;
    }

    function administration_menu(){

        if (1 < count($this->get_active_languages())) {
            $main_page = apply_filters('icl_menu_main_page', basename(ICL_PLUGIN_PATH).'/menu/languages.php');
            add_menu_page(__('WPML','sitepress'), __('WPML','sitepress'), 'manage_options',
                $main_page, null, ICL_PLUGIN_URL . '/res/img/icon16.png');

            do_action('icl_wpml_top_menu_added');

            add_submenu_page($main_page,
                __('Languages','sitepress'), __('Languages','sitepress'),
                'manage_options', basename(ICL_PLUGIN_PATH).'/menu/languages.php');
            add_submenu_page($main_page,
                __('Theme and plugins localization','sitepress'), __('Theme and plugins localization','sitepress'),
                'manage_options', basename(ICL_PLUGIN_PATH).'/menu/theme-localization.php');
            if(!defined('WPML_TM_VERSION')){
                add_submenu_page($main_page,
                    __('Translation options','sitepress'), __('Translation options','sitepress'),
                    'manage_options', basename(ICL_PLUGIN_PATH).'/menu/translation-options.php');
            }

            /*
            add_submenu_page($main_page,
                __('Comments translation','sitepress'), __('Comments translation','sitepress'),
                'manage_options', basename(ICL_PLUGIN_PATH).'/menu/comments-translation.php');
            */
        } else {
            $main_page = basename(ICL_PLUGIN_PATH).'/menu/languages.php';
            add_menu_page(__('WPML','sitepress'), __('WPML','sitepress'), 'manage_options',
                $main_page,null, ICL_PLUGIN_URL . '/res/img/icon16.png');
            add_submenu_page($main_page,
                __('Languages','sitepress'), __('Languages','sitepress'),
                'manage_options', basename(ICL_PLUGIN_PATH).'/menu/languages.php');
        }

        if(isset($_GET['page']) && $_GET['page'] == basename(ICL_PLUGIN_PATH).'/menu/troubleshooting.php'){
            add_submenu_page($main_page,
                 __('Troubleshooting','sitepress'), __('Troubleshooting','sitepress'),
                'manage_options', basename(ICL_PLUGIN_PATH).'/menu/troubleshooting.php');
        }

        //$alert = '&nbsp;<img width="12" height="12" style="margin-bottom:-2px;" src="'.ICL_PLUGIN_URL.'/res/img/alert.png" />';
        add_submenu_page($main_page,
            __('Support','sitepress'), __('Support','sitepress'), 'manage_options', basename(ICL_PLUGIN_PATH).'/menu/support.php');

    }
    
    function save_settings($settings=null){
        if(!is_null($settings)){
            foreach($settings as $k=>$v){
                if(is_array($v)){
                    foreach($v as $k2=>$v2){
                        $this->settings[$k][$k2] = $v2;
                    }
                }else{
                    $this->settings[$k] = $v;
                }
            }
        }
        if(!empty($this->settings)){
            update_option('icl_sitepress_settings', $this->settings);
        }
        do_action('icl_save_settings', $settings);
    }

    function get_settings(){
        return $this->settings;
    }

    function verify_settings(){

        $default_settings = array(
            'interview_translators' => 1,
            'existing_content_language_verified' => 0,
            'language_negotiation_type' => 3,
            'icl_lso_header' => 0,
            'icl_lso_link_empty' => 0,
            'icl_lso_flags' => 0,
            'icl_lso_native_lang' => 1,
            'icl_lso_display_lang' => 1,
            'sync_page_ordering' => 1,
            'sync_page_parent' => 1,
            'sync_page_template' => 1,
            'sync_ping_status' => 1,
            'sync_comment_status' => 1,
            'sync_sticky_flag' => 1,
            'sync_private_flag' => 1,
            'sync_post_format' => 1,
            'sync_delete' => 0,
            'sync_post_taxonomies' => 1,
            'sync_post_date' => 0,
            'sync_taxonomy_parents' => 0,            
            'translation_pickup_method' => 0,
            'notify_complete' => 1,
            'translated_document_status' => 1,
            'remote_management' => 0,
            'auto_adjust_ids' => 1,
            'alert_delay' => 0,
            'promote_wpml' => 1,
            'troubleshooting_options' => array('http_communication' => 1),
            'automatic_redirect' => 0,
            'remember_language' => 24,
            'icl_lang_sel_type' => 'dropdown',
            'icl_widget_title_show' => 1 
        );

        //congigured for three levels
        $update_settings = false;
        foreach($default_settings as $key => $value){
            if(is_array($value)){
                foreach($value as $k2 => $v2){
                    if(is_array($v2)){
                        foreach($v2 as $k3 => $v3){
                            if(!isset($this->settings[$key][$k2][$k3])){
                                $this->settings[$key][$k2][$k3] = $v3;
                                $update_settings = true;
                            }
                        }
                    }else{
                        if(!isset($this->settings[$key][$k2])){
                            $this->settings[$key][$k2] = $v2;
                            $update_settings = true;
                        }
                    }
                }
            }else{
                if(!isset($this->settings[$key])){
                    $this->settings[$key] = $value;
                    $update_settings = true;
                }
            }
        }


        if($update_settings){
            $this->save_settings();
        }
    }

    function _validate_language_per_directory($language_code){
        if(!class_exists('WP_Http')) include_once ABSPATH . WPINC . '/class-http.php';
        $client = new WP_Http();
        if(false === @strpos($_POST['url'],'?')){$url_glue='?';}else{$url_glue='&';}
        $response = $client->request(get_option('home') . '/' . $language_code .'/' . $url_glue . '____icl_validate_domain=1', array('timeout'=>15, 'decompress'=>false));
        return (!is_wp_error($response) && ($response['response']['code']=='200') && ($response['body'] == '<!--'.get_option('home').'-->'));
    }

    function save_language_pairs() {
        // clear existing languages
        $lang_pairs = $this->settings['language_pairs'];
        if (is_array($lang_pairs)) {
            foreach ($lang_pairs as $from => $to) {
                $lang_pairs[$from] = array();
            }
        }

        // get the from languages
        $from_languages = array();
        foreach($_POST as $k=>$v){
            if(0 === strpos($k,'icl_lng_from_')){
                $f = str_replace('icl_lng_from_','',$k);
                $from_languages[] = $f;
            }
        }

        foreach($_POST as $k=>$v){
            if(0 !== strpos($k,'icl_lng_')) continue;
            if(0 === strpos($k,'icl_lng_to')){
                $t = str_replace('icl_lng_to_','',$k);
                $exp = explode('_',$t);
                if (in_array($exp[0], $from_languages)){
                    $lang_pairs[$exp[0]][$exp[1]] = 1;
                }
            }
        }

        $iclsettings['language_pairs'] = $lang_pairs;
        $this->save_settings($iclsettings);
    }

    function get_active_languages($refresh = false){
        global $wpdb;

        if($refresh || !$this->active_languages){
            if(defined('WP_ADMIN') && $this->admin_language){
                $in_language = $this->admin_language;
            }else{
                $in_language = $this->get_current_language()?$this->get_current_language():$this->get_default_language();
            }
            if (isset($this->icl_language_name_cache)) {
                $res = $this->icl_language_name_cache->get('in_language_'.$in_language);
            } else {
                $res = null;
            }

            if (!$res || !is_array($res)) {
                $res = $wpdb->get_results("
                    SELECT l.id, code, english_name, active, lt.name AS display_name
                    FROM {$wpdb->prefix}icl_languages l
                        JOIN {$wpdb->prefix}icl_languages_translations lt ON l.code=lt.language_code
                    WHERE
                        active=1 AND lt.display_language_code = '{$in_language}'
                    ORDER BY major DESC, english_name ASC", ARRAY_A);
                if (isset($this->icl_language_name_cache)) {
                    $this->icl_language_name_cache->set('in_language_'.$in_language, $res);
                }
            }

            $languages = array();
            if($res){
                foreach($res as $r){
                    $languages[$r['code']] = $r;
                }
            }

            if (isset($this->icl_language_name_cache)) {
                $res = $this->icl_language_name_cache->get('languages_'.$languages);
            } else {
                $res = null;
            }
            if (!$res) {

                $res = $wpdb->get_results("
                    SELECT language_code, name
                    FROM {$wpdb->prefix}icl_languages_translations
                    WHERE language_code IN ('".join("','",array_keys($languages))."') AND language_code = display_language_code
                ");
                if (isset($this->icl_language_name_cache)) {
                    $this->icl_language_name_cache->set('languages_'.$languages, $res);
                }
            }

            foreach($res as $row){
                $languages[$row->language_code]['native_name'] = $row->name;
            }

            $this->active_languages = $languages;
        }

        // hide languages for front end
        global $current_user;
        get_currentuserinfo();
        if(!is_admin() && !empty($this->settings['hidden_languages']) && is_array($this->settings['hidden_languages'])){
            if(empty($current_user->data) || !get_user_meta($current_user->data->ID, 'icl_show_hidden_languages', true)){
                foreach($this->settings['hidden_languages'] as $l){
                    unset($this->active_languages[$l]);
                }
            }
        }
        return $this->active_languages;
    }

    function set_active_languages($arr){
        global $wpdb;
        if(!empty($arr)){
            foreach($arr as $code){
                $tmp[] = mysql_real_escape_string(trim($code));
            }

            // set the locale
            $current_active_languages = (array)$wpdb->get_col("SELECT code FROM {$wpdb->prefix}icl_languages WHERE active = 1");
            $new_languages = array_diff($tmp, $current_active_languages);

            if(!empty($new_languages)){
                foreach($new_languages as $code){
                    $default_locale = $wpdb->get_var("SELECT default_locale FROM {$wpdb->prefix}icl_languages WHERE code='{$code}'");
                    if($default_locale){
                        if($wpdb->get_var("SELECT code FROM {$wpdb->prefix}icl_locale_map WHERE code='{$code}'")){
                            $wpdb->update($wpdb->prefix.'icl_locale_map', array('locale'=>$default_locale), array('code'=>$code));
                        }else{
                            $wpdb->insert($wpdb->prefix.'icl_locale_map', array('code'=>$code, 'locale'=>$default_locale));
                        }
                    }
                }
            }

            $codes = '(\'' . join('\',\'',$tmp) . '\')';
            $wpdb->update($wpdb->prefix.'icl_languages', array('active'=>0), array('active'=>'1'));
            $wpdb->query("UPDATE {$wpdb->prefix}icl_languages SET active=1 WHERE code IN {$codes}");
            $this->icl_language_name_cache->clear();
        }

        $res = $wpdb->get_results("
            SELECT code, english_name, active, lt.name AS display_name
            FROM {$wpdb->prefix}icl_languages l
                JOIN {$wpdb->prefix}icl_languages_translations lt ON l.code=lt.language_code
            WHERE
                active=1 AND lt.display_language_code = '{$this->get_default_language()}'
            ORDER BY major DESC, english_name ASC", ARRAY_A);
        $languages = array();
        foreach($res as $r){
            $languages[] = $r;
        }
        $this->active_languages = $languages;

        return true;
    }

    function get_languages($lang=false){
        global $wpdb;
        if(!$lang){
            $lang = $this->get_default_language();
        }
        $res = $wpdb->get_results("
            SELECT
                code, english_name, major, active, default_locale, lt.name AS display_name
            FROM {$wpdb->prefix}icl_languages l
                JOIN {$wpdb->prefix}icl_languages_translations lt ON l.code=lt.language_code
            WHERE lt.display_language_code = '{$lang}'
            ORDER BY major DESC, english_name ASC", ARRAY_A);
        $languages = array();
        foreach((array)$res as $r){
            $languages[] = $r;
        }
        return $languages;
    }

    function get_language_details($code){
        global $wpdb;
        if(defined('WP_ADMIN')){
            $dcode = $this->admin_language;
        }else{
            $dcode = $code;
        }
        if (isset($this->icl_language_name_cache)){
            $details = $this->icl_language_name_cache->get('language_details_'.$code.$dcode);
        } else {
            $details = null;
        }
        if (!$details){
            $details = $wpdb->get_row("
                SELECT
                    code, english_name, major, active, lt.name AS display_name
                FROM {$wpdb->prefix}icl_languages l
                    JOIN {$wpdb->prefix}icl_languages_translations lt ON l.code=lt.language_code
                WHERE lt.display_language_code = '{$dcode}' AND code='{$code}'
                ORDER BY major DESC, english_name ASC", ARRAY_A);
            if (isset($this->icl_language_name_cache)){
                $this->icl_language_name_cache->set('language_details_'.$code.$dcode, $details);
            }
        }

        return $details;
    }

    function get_language_code($english_name){
        global $wpdb;
        $code = $wpdb->get_row("
            SELECT
                code
            FROM {$wpdb->prefix}icl_languages
            WHERE english_name = '{$english_name}'", ARRAY_A);
        return $code['code'];
    }

    function get_icl_translator_status(&$iclsettings, $res = NULL){

        if ($res == NULL) {
            // check what languages we have translators for.
            require_once ICL_PLUGIN_PATH . '/lib/Snoopy.class.php';
            require_once ICL_PLUGIN_PATH . '/lib/xml2array.php';
            require_once ICL_PLUGIN_PATH . '/lib/icl_api.php';

            $icl_query = false;
            if (empty($iclsettings['site_id'])) {
                // Must be for support
                if(!empty($iclsettings['support_site_id'])){
                    $icl_query = new ICanLocalizeQuery($iclsettings['support_site_id'], $iclsettings['support_access_key']);
                }
            } else {
                $icl_query = new ICanLocalizeQuery($iclsettings['site_id'], $iclsettings['access_key']);
            }

            if($icl_query === false) return;

            $res = $icl_query->get_website_details();

        }
        if(isset($res['translation_languages']['translation_language'])){

            // reset $this->settings['icl_lang_status']
            $iclsettings['icl_lang_status'] = array();

            $translation_languages = $res['translation_languages']['translation_language'];
            if(!isset($translation_languages[0])){
                $buf = $translation_languages;
                $translation_languages = array(0 => $buf);
            }
            foreach($translation_languages as $lang){
                $translators = $_tr = array();
                $max_rate = false;
                if(isset($lang['translators']) && !empty($lang['translators'])){
                    if(!isset($lang['translators']['translator'][0])){
                        $_tr[0] = $lang['translators']['translator'];
                    }else{
                        $_tr = $lang['translators']['translator'];
                    }
                    foreach($_tr as $t){
                        if($max_rate === false || $t['attr']['amount'] > $max_rate){
                            $max_rate = $t['attr']['amount'];
                        }
                        $translators[] = array('id'=>$t['attr']['id'], 'nickname'=>$t['attr']['nickname'], 'contract_id' => $t['attr']['contract_id']);
                    }
                }
                $target[] = array(
                    'from' => $this->get_language_code(ICL_Pro_Translation::server_languages_map($lang['attr']['from_language_name'], true)),
                    'to' => $this->get_language_code(ICL_Pro_Translation::server_languages_map($lang['attr']['to_language_name'], true)),
                    'have_translators' => $lang['attr']['have_translators'],
                    'available_translators' => $lang['attr']['available_translators'],
                    'applications' => $lang['attr']['applications'],
                    'contract_id' => $lang['attr']['contract_id'],
                    'id' => $lang['attr']['id'],
                    'translators' => $translators,
                    'max_rate' => $max_rate
                );
            }
            $iclsettings['icl_lang_status'] = $target;
        }

        if(isset($res['client']['attr'])){
            $iclsettings['icl_balance'] = $res['client']['attr']['balance'];
            $iclsettings['icl_anonymous_user'] = $res['client']['attr']['anon'];
        }
        if(isset($res['html_status']['value'])){
            $iclsettings['icl_html_status'] = html_entity_decode($res['html_status']['value']);
            $iclsettings['icl_html_status'] = preg_replace_callback('#<a([^>]*)href="([^"]+)"([^>]*)>#i', create_function(
                '$matches',
                'global $sitepress; return $sitepress->create_icl_popup_link($matches[2]);'
            ) ,$iclsettings['icl_html_status']);
        }

        if(isset($res['translators_management_info']['value'])){
            $iclsettings['translators_management_info'] = html_entity_decode($res['translators_management_info']['value']);
            $iclsettings['translators_management_info'] = preg_replace_callback('#<a([^>]*)href="([^"]+)"([^>]*)>#i', create_function(
                '$matches',
                'global $sitepress; return $sitepress->create_icl_popup_link($matches[2], array(\'unload_cb\'=>\'icl_thickbox_refresh\'));'
            ) ,$iclsettings['translators_management_info']);
        }

        $iclsettings['icl_support_ticket_id'] = @intval($res['attr']['support_ticket_id']);
    }

    function get_language_status_text($from_lang, $to_lang, $popclose_cb = false) {

        $popargs = array('title'=>'ICanLocalize');
        if($popclose_cb){
            $popargs['unload_cb'] =  $popclose_cb;
        }

        $lang_status = !empty($this->settings['icl_lang_status']) ? $this->settings['icl_lang_status'] : array();
        $response = '';
        foreach ($lang_status as $lang) {
            if ($from_lang == $lang['from'] && $to_lang == $lang['to']) {
                if (isset($lang['available_translators'])) {
                    if (!$lang['available_translators']) {
                        if ($this->settings['icl_support_ticket_id'] == '') {
                            // No translators available on icanlocalize for this language pair.
                            $response = sprintf(__('- (No translators available - please %sprovide more information about your site%s)', 'sitepress'),
                                                $this->create_icl_popup_link(ICL_API_ENDPOINT. '/websites/' . $this->settings['site_id'] . '/explain?after=refresh_langs',
                                                    $popargs),
                                                '</a>');
                        } else {
                            $response = sprintf(__('- (No translators available - %scheck progress%s)', 'sitepress'),
                                                $this->create_icl_popup_link(ICL_API_ENDPOINT. '/support/show/' . $this->settings['icl_support_ticket_id'] . '?after=refresh_langs',
                                                    $popargs),
                                                '</a>');
                        }

                    } else if (!$lang['applications']) {
                        // No translators have applied for this language pair.
                        $popargs['class'] = 'icl_hot_link';
                        $response = ' | ' . $this->create_icl_popup_link("@select-translators;{$from_lang};{$to_lang}@", $popargs) .
                                    __('Select translators', 'sitepress') .  '</a>';
                    } else if (!$lang['have_translators']) {
                        // translators have applied but none selected yet
                        $popargs['class'] = 'icl_hot_link';
                        $response = ' | ' . $this->create_icl_popup_link("@select-translators;{$from_lang};{$to_lang}@", $popargs) . __('Select translators', 'sitepress') . '</a>';
                    } else {
                        // there are translators ready to translate
                        $translators = array();
                        if(is_array($lang['translators'])){
                            foreach($lang['translators'] as $translator){
                                $link = $this->create_icl_popup_link(ICL_API_ENDPOINT. '/websites/' . $this->settings['site_id'] . '/website_translation_offers/' .
                                                $lang['id'] . '/website_translation_contracts/' . $translator['contract_id'], $popargs);
                                $translators[] = $link . esc_html($translator['nickname']) . '</a>';
                            }
                        }
                        $response = ' | ' . $this->create_icl_popup_link("@select-translators;{$from_lang};{$to_lang}@", $popargs) . __('Select translators', 'sitepress') . '</a>';
                        $response .= ' | ' . sprintf(__('Communicate with %s', 'sitepress'), join(', ', $translators));

                    }

                    return $response;

                }
            break;
            }

        }
        $popargs['class'] = 'icl_hot_link';
        $response = ' | ' . $this->create_icl_popup_link("@select-translators;{$from_lang};{$to_lang}@", $popargs) . __('Select translators', 'sitepress') .  '</a>';

        // no status found
        return $response;
    }

    function are_waiting_for_translators($from_lang) {
        $lang_status = $this->settings['icl_lang_status'];
        if ($lang_status && $this->icl_account_configured()) {
            foreach ($lang_status as $lang) {
                if ($from_lang == $lang['from']) {
                    if (isset($lang['available_translators'])) {
                        if ($lang['available_translators'] && !$lang['applications']) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    function get_default_language(){
        return isset($this->settings['default_language']) ? $this->settings['default_language'] : false;
    }

    function get_current_language(){
        return apply_filters('icl_current_language' , $this->this_lang);
    }

    function set_default_language($code){
        global $wpdb;
        $iclsettings['default_language'] = $code;
        $this->save_settings($iclsettings);

        // change WP locale
        $locale = $this->get_locale($code);
        if($locale){
            update_option('WPLANG', $locale);
        }
        if($code != 'en' && !file_exists(ABSPATH . LANGDIR . '/' . $locale . '.mo')){
            return 1; //locale not installed
        }

        return true;
    }

    function get_icl_translation_enabled($lang=null, $langto=null){
        if(!is_null($lang)){
            if(!is_null($langto)){
                return $this->settings['language_pairs'][$lang][$langto];
            }else{
                return !empty($this->settings['language_pairs'][$lang]);
            }
        }else{
            return isset($this->settings['enable_icl_translations']) ? $this->settings['enable_icl_translations'] : false;
        }
    }

    function set_icl_translation_enabled(){
        $iclsettings['translation_enabled'] = true;
        $this->save_settings($iclsettings);
    }

    function icl_account_reqs(){
        $errors = array();
        if(!$this->get_icl_translation_enabled()){
            $errors[] = __('Professional translation not enabled', 'sitepress');
        }
        return $errors;
    }

    function icl_account_configured(){
        return isset($this->settings['site_id']) && $this->settings['site_id'] && isset($this->settings['access_key']) && $this->settings['access_key'];
    }

    function icl_support_configured(){
        return isset($this->settings['support_site_id']) && isset($this->settings['support_access_key'])
            && $this->settings['support_site_id'] && $this->settings['support_access_key'];
    }

    function reminders_popup(){
        include ICL_PLUGIN_PATH . '/modules/icl-translation/icl-reminder-popup.php';
        exit;
    }

    function create_icl_popup_link($link, $args = array(), $just_url = false, $support_mode = FALSE) {

        // defaults
        $defaults = array(
            'title' => null,
            'class' => '',
            'id'    => '',
            'ar'    => 0,  // auto_resize
            'unload_cb' => false, // onunload callback
        );

        extract($defaults);
        extract($args, EXTR_OVERWRITE);

        if(!empty($ar)){
            $auto_resize = '&amp;auto_resize=1';
        }else{
            $auto_resize = '';
        }

        $unload_cb = isset($unload_cb) ? '&amp;unload_cb=' . $unload_cb : '';

        $url_glue = false !== strpos($link,'?') ? '&' : '?';
        $link .= $url_glue . 'compact=1';

        if (isset($this->settings['access_key']) || isset($this->settings['support_access_key'])){
            if ($support_mode && isset($this->settings['support_access_key'])) {
                $link .= '&accesskey=' . $this->settings['support_access_key'];
            } elseif (isset($this->settings['access_key'])) {
                $link .= '&accesskey=' . $this->settings['access_key'];
            }
        }

		if (!empty($id)) {
			$id = ' id="' . $id . '"';
		}
		if (isset($title) && !$just_url) {
            return '<a class="icl_thickbox ' . $class . '" title="' . $title . '" href="admin.php?page='.ICL_PLUGIN_FOLDER .
                "/menu/languages.php&amp;icl_action=reminder_popup{$auto_resize}{$unload_cb}&amp;target=" . urlencode($link) .'"' . $id . '>';
        } else if (!$just_url) {
            return '<a class="icl_thickbox ' . $class . '" href="admin.php?page='.ICL_PLUGIN_FOLDER .
                "/menu/languages.php&amp;icl_action=reminder_popup{$auto_resize}{$unload_cb}&amp;target=" . urlencode($link) .'"' . $id . '>';
        } else {
            return 'admin.php?page='.ICL_PLUGIN_FOLDER . "/menu/languages.php&amp;icl_action=reminder_popup{$auto_resize}{$unload_cb}&amp;target=" . urlencode($link);
        }
    }

    function js_scripts_setup(){
        global $pagenow, $wpdb;
        if(isset($_GET['page'])){
            $page = basename($_GET['page']);
            $page_basename = str_replace('.php','',$page);
        }else{
            $page_basename = false;
        }
        ?>
        <script type="text/javascript">
        // <![CDATA[
        <?php if(defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN): ?>
        var icl_ajx_url = '<?php echo str_replace('http://', 'https://', rtrim(get_option('siteurl'),'/')) . '/wp-admin/' ?>admin.php?page=<?php echo ICL_PLUGIN_FOLDER ?>/menu/languages.php';
        <?php else: ?>
        var icl_ajx_url = '<?php echo rtrim(get_option('siteurl'),'/') . '/wp-admin/' ?>admin.php?page=<?php echo ICL_PLUGIN_FOLDER ?>/menu/languages.php';
        <?php endif; ?>
        var icl_ajx_saved = '<?php echo icl_js_escape( __('Data saved','sitepress')); ?>';
        var icl_ajx_error = '<?php echo icl_js_escape( __('Error: data not saved','sitepress')); ?>';
        var icl_default_mark = '<?php echo icl_js_escape(__('default','sitepress')); ?>';
        var icl_this_lang = '<?php echo $this->this_lang ?>';
        var icl_ajxloaderimg_src = '<?php echo ICL_PLUGIN_URL ?>/res/img/ajax-loader.gif';
        var icl_cat_adder_msg = '<?php echo icl_js_escape(sprintf(__('To add categories that already exist in other languages go to the <a%s>category management page</a>','sitepress'), ' href="'.admin_url('edit-tags.php?taxonomy=category').'"'));?>';
        // ]]>

        <?php if(empty($this->settings['ajx_health_checked'])): ?>
        addLoadEvent(function(){
            jQuery.ajax({type: "POST",url: icl_ajx_url,data: "icl_ajx_action=health_check", error: function(msg){
                    if(jQuery('#icl_initial_language').length){
                        jQuery('#icl_initial_language input').attr('disabled', 'disabled');
                    }
                    jQuery('.wrap').prepend('<div class="error"><p><?php
                        echo icl_js_escape(sprintf(__("WPML can't run normally. There is an installation or server configuration problem. %sShow details%s",'sitepress'),
                        '<a href="#" onclick="jQuery(this).parent().next().slideToggle()">', '</a>'));
                    ?></p><p style="display:none"><?php echo icl_js_escape(__('AJAX Error:', 'sitepress'))?> ' + msg.statusText + ' ['+msg.status+']<br />URL:'+ icl_ajx_url +'</p></div>');
            }});
        });
        <?php endif; ?>
        </script>
        <?php
        wp_enqueue_script('sitepress-scripts', ICL_PLUGIN_URL . '/res/js/scripts.js', array(), ICL_SITEPRESS_VERSION);
        if(isset($page_basename) && file_exists(ICL_PLUGIN_PATH . '/res/js/'.$page_basename.'.js')){
            wp_enqueue_script('sitepress-' . $page_basename, ICL_PLUGIN_URL . '/res/js/'.$page_basename.'.js', array(), ICL_SITEPRESS_VERSION);
        }
        if('options-reading.php' == $pagenow ){
                list($warn_home, $warn_posts) = $this->verify_home_and_blog_pages_translations();
                if($warn_home || $warn_posts){ ?>
                <script type="text/javascript">
                addLoadEvent(function(){
                jQuery('input[name="show_on_front"]').parent().parent().parent().parent().append('<?php echo str_replace("'","\\'",$warn_home . $warn_posts); ?>');
                });
                </script>
                <?php }
        }

        // display correct links on the posts by status break down
        // also fix links to category and tag pages
        if( ('edit.php' == $pagenow || 'edit-pages.php' == $pagenow || 'categories.php' == $pagenow || 'edit-tags.php' == $pagenow)
                && $this->get_current_language() != $this->get_default_language()){
                ?>
                <script type="text/javascript">
                addLoadEvent(function(){
                    jQuery('.subsubsub li a').each(function(){
                        h = jQuery(this).attr('href');
                        if(-1 == h.indexOf('?')) urlg = '?'; else urlg = '&';
                        jQuery(this).attr('href', h + urlg + 'lang=<?php echo $this->get_current_language()?>');
                    });
                    jQuery('.column-categories a, .column-tags a, .column-posts a').each(function(){
                        jQuery(this).attr('href', jQuery(this).attr('href') + '&lang=<?php echo $this->get_current_language()?>');
                    });
                    <?php /*  needs jQuery 1.3
                    jQuery('.column-categories a, .column-tags a, .column-posts a').live('mouseover', function(){
                        if(-1 == jQuery(this).attr('href').search('lang='+icl_this_lang)){
                            h = jQuery(this).attr('href');
                            if(-1 == h.indexOf('?')) urlg = '?'; else urlg = '&';
                            jQuery(this).attr('href', h + urlg + 'lang='+icl_this_lang);
                        }
                    });
                    */ ?>
                });
                </script>
                <?php
        }
        
        if('edit-tags.php' == $pagenow){
            ?>
            <script type="text/javascript">    
            addLoadEvent(function(){
                if(jQuery('#edittag [name="_wp_original_http_referer"]').length && jQuery('#edittag [name="_wp_http_referer"]').length){
                    jQuery('#edittag [name="_wp_original_http_referer"]').val('<?php echo admin_url('edit-tags.php?taxonomy=' . $_GET['taxonomy'] . '&lang='.$this->get_current_language().'&message=3') ?>');     
                }
            });
            </script>
            <?php            
        }

        if('post-new.php' == $pagenow){
            if(isset($_GET['trid'])){
                $translations = $wpdb->get_col("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid='{$_GET['trid']}'");
                remove_filter('option_sticky_posts', array($this,'option_sticky_posts')); // remove filter used to get language relevant stickies. get them all
                $sticky_posts = get_option('sticky_posts');
                add_filter('option_sticky_posts', array($this,'option_sticky_posts')); // add filter back
                $is_sticky = false;
                foreach($translations as $t){
                    if(in_array($t, $sticky_posts)){
                        $is_sticky = true;
                        break;
                    }
                }
                if(isset($_GET['trid']) && ($this->settings['sync_ping_status'] || $this->settings['sync_comment_status'])){
                    $res = $wpdb->get_row("SELECT comment_status, ping_status FROM {$wpdb->prefix}icl_translations t
                    JOIN {$wpdb->posts} p ON t.element_id = p.ID WHERE t.trid='".intval($_GET['trid'])."'"); ?>
                    <script type="text/javascript">addLoadEvent(function(){
                    <?php if($this->settings['sync_comment_status']): ?>
                        <?php if($res->comment_status == 'open'): ?>
                        jQuery('#comment_status').attr('checked','checked');
                        <?php else: ?>
                        jQuery('#comment_status').removeAttr('checked');
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if($this->settings['sync_ping_status']): ?>
                        <?php if($res->ping_status == 'open'): ?>
                        jQuery('#ping_status').attr('checked','checked');
                        <?php else: ?>
                        jQuery('#ping_status').removeAttr('checked');
                        <?php endif; ?>
                    <?php endif; ?>
                    });</script><?php
                }
                if(isset($_GET['trid']) && $this->settings['sync_private_flag']){
                    if('private' == $wpdb->get_var("
                        SELECT p.post_status FROM {$wpdb->prefix}icl_translations t
                        JOIN {$wpdb->posts} p ON t.element_id = p.ID
                        WHERE t.trid='{$_GET['trid']}' AND t.element_type='post_post'
                    ")){
                        ?><script type="text/javascript">addLoadEvent(function(){
                            jQuery('#visibility-radio-private').attr('checked','checked');
                            jQuery('#post-visibility-display').html('<?php echo icl_js_escape(__('Private', 'sitepress')); ?>');
                        });
                        </script><?php
                    }
                }
                
                if(isset($_GET['trid']) && $this->settings['sync_post_taxonomies']){
                    
                    $post_type = isset($_GET['post_type'])?$_GET['post_type']:'post';
                    $source_lang = isset($_GET['source_lang'])?$_GET['source_lang']:$this->get_default_language();
                    $translatable_taxs = $this->get_translatable_taxonomies(true, $post_type);
                    $all_taxs = get_object_taxonomies($post_type);
                    
                    
                    $translations = $this->get_element_translations($_GET['trid'], 'post_' . $post_type);
                    $js = array();
                    if(!empty($all_taxs)) foreach($all_taxs as $tax){                                    
                        $tax_detail = get_taxonomy($tax);                        
                        $terms = get_the_terms($translations[$source_lang]->element_id, $tax);    
                        $term_names = array();
                        if($terms) foreach($terms as $term){
                            if($tax_detail->hierarchical){
                                if(in_array($tax, $translatable_taxs)){
                                    $term_id = icl_object_id($term->term_id, $tax, false);                                
                                }else{
                                    $term_id = $term->term_id;                                                                
                                }                                
                                $js[] = "jQuery('#in-".$tax."-".$term_id."').attr('checked', 'checked');";    
                            }else{
                                if(in_array($tax, $translatable_taxs)){
                                    $term_id = icl_object_id($term->term_id, $tax, false);                                
                                    if($term_id){
                                        $term = get_term_by('id', $term_id, $tax);
                                        $term_names[] = esc_html($term->name);    
                                    }
                                }else{
                                    $term_names[] = esc_html($term->name);
                                }
                            }
                            
                        }
                        
                        if($term_names){
                            $js[] = "jQuery('#{$tax} .taghint').css('visibility','hidden');";
                            $js[] = "jQuery('#new-tag-{$tax}').val('".join(', ', $term_names)."');";    
                        }
                    }
                    
                    if($js){
                        echo '<script type="text/javascript">';
                        echo PHP_EOL . '// <![CDATA[' . PHP_EOL;
                        echo 'addLoadEvent(function(){'. PHP_EOL;
                        echo join(PHP_EOL, $js);
                        echo PHP_EOL . 'tagBox.flushTags();'. PHP_EOL;
                        echo PHP_EOL . '});'. PHP_EOL;
                        echo PHP_EOL . '// ]]>' . PHP_EOL;                        
                        echo '</script>';    
                    } 
                    
                }
                
                // sync custom fields                
                foreach((array)$this->settings['translation-management']['custom_fields_translation'] as $key=>$sync_opt){
                    if($sync_opt == 1){
                        $copied_cf[] = $key;    
                    }
                }
                if(!empty($copied_cf)){
                    $source_lang = isset($_GET['source_lang'])?$_GET['source_lang']:$this->get_default_language();
                    $lang_details = $this->get_language_details($source_lang);                    
                    $ccf_note = sprintf(__('WPML will copy %s from %s when you save this post.', 'sitepress'), '<i>' . esc_html(join(', ', $copied_cf)) . '</i>', $lang_details['display_name']);
                    ?>
                    <script type="text/javascript">
                    addLoadEvent(
                        function(){
                            jQuery('#postcustom .inside').prepend('<div class="icl_cyan_box"><?php echo icl_js_escape($ccf_note) ?></div>');                            
                    });
                    </script>
                    <?php
                }
                
            }
            ?>
            <?php if(!empty($is_sticky) && $this->settings['sync_sticky_flag']): ?>
                <script type="text/javascript">
                    addLoadEvent(
                        function(){
                            jQuery('#sticky').attr('checked','checked');
                            jQuery('#post-visibility-display').html(jQuery('#post-visibility-display').html()+', <?php echo icl_js_escape(__('Sticky', 'sitepress')) ?>');
                    });
                </script>
            <?php endif; ?>
            <?php
        }
        if('page-new.php' == $pagenow || ('post-new.php' == $pagenow && isset($_GET['post_type']) && $_GET['post_type']=='page')){
            if(isset($_GET['trid']) && ($this->settings['sync_page_template'] || $this->settings['sync_page_ordering'])){
                $res = $wpdb->get_row("
                    SELECT p.ID, p.menu_order FROM {$wpdb->prefix}icl_translations t
                    JOIN {$wpdb->posts} p ON t.element_id = p.ID
                    WHERE t.trid='{$_GET['trid']}' AND p.post_type='page' AND t.element_type='post_page'
                ");
                if($this->settings['sync_page_ordering']){
                    $menu_order = $res->menu_order;
                }else{
                    $menu_order = false;
                }
                if($this->settings['sync_page_template']){
                    $page_template = get_post_meta($res->ID, '_wp_page_template', true);
                }else{
                    $page_template = false;
                }
                if($menu_order || $page_template){
                    ?><script type="text/javascript">addLoadEvent(function(){ <?php
                    if($menu_order){ ?>
                        jQuery('#menu_order').val(<?php echo $menu_order ?>);
                    <?php }
                    if($page_template && 'default' != $page_template){ ?>
                        jQuery('#page_template').val('<?php echo $page_template ?>');
                    <?php }
                    ?>});</script><?php
                }
            }
        }elseif('edit-comments.php' == $pagenow || 'index.php' == $pagenow || 'post.php' == $pagenow){
            wp_enqueue_script('sitepress-' . $page_basename, ICL_PLUGIN_URL . '/res/js/comments-translation.js', array(), ICL_SITEPRESS_VERSION);
        }
        
        // sync post dates
        if(icl_is_post_edit()){            
            if($this->settings['sync_post_date']){
                $post_type = isset($_GET['post_type'])?$_GET['post_type']:'post';
                
                if(isset($_GET['trid'])){
                    $trid = $_GET['trid'];
                }else{
                    $post_id = @intval($_GET['post']);
                    $trid = $this->get_element_trid($post_id, 'post_' . $post_type);    
                }
                
                $translations = $this->get_element_translations($trid, 'post_' . $post_type);
                if(!$translations[$this->get_current_language()]->original){
                    $source_lang = isset($_GET['source_lang'])?$_GET['source_lang']:$this->get_default_language();
                    $original_date = $wpdb->get_var($wpdb->prepare("SELECT post_date FROM {$wpdb->posts} WHERE ID=%d", $translations[$source_lang]->element_id));
                    $exp = explode(' ', $original_date);
                    list($aa, $mm, $jj) = explode('-', $exp[0]);
                    list($hh, $mn, $ss) = explode(':', $exp[1]);
                    ?>
                    <script type="text/javascript">
                        addLoadEvent(
                            function(){
                                jQuery('#aa').val('<?php echo $aa ?>').attr('readonly','readonly');
                                jQuery('#mm').val('<?php echo $mm ?>').attr('readonly','readonly');
                                jQuery('#jj').val('<?php echo $jj ?>').attr('readonly','readonly');
                                jQuery('#hh').val('<?php echo $hh ?>').attr('readonly','readonly');
                                jQuery('#mn').val('<?php echo $mn ?>').attr('readonly','readonly');
                                jQuery('#ss').val('<?php echo $ss ?>').attr('readonly','readonly');                                
                                jQuery('#timestamp b').html('<?php esc_html_e('copy from original', 'sitepress') ?>');                                
                                jQuery('#timestamp').next().html('<?php esc_html_e('show', 'sitepress') ?>');
                        });
                    </script>
                    <?php
                }
            }
        }

        if('page-new.php' == $pagenow && isset($_GET['trid']) && $this->settings['sync_post_format'] && function_exists('get_post_format')){
            $format = get_post_format($wpdb->get_var($wpdb->prepare(
                "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d and language_code=%s", $_GET['trid'], $_GET['source_lang'])));
            ?><script type="text/javascript">addLoadEvent(function(){
                jQuery('#post-format-<?php echo $format ?>').attr('checked','checked');
            });
            </script><?php
        }


        if (is_admin()) {
            wp_enqueue_script('thickbox');
            wp_enqueue_script( 'theme-preview' );
            wp_enqueue_script('sitepress-icl_reminders', ICL_PLUGIN_URL . '/res/js/icl_reminders.js', array(), ICL_SITEPRESS_VERSION);
        }

        //if('content-translation' == $page_basename) {
        //    wp_enqueue_script('icl-sidebar-scripts', ICL_PLUGIN_URL . '/res/js/icl_sidebar.js', array(), ICL_SITEPRESS_VERSION);
        //}
        if('languages' == $page_basename || 'string-translation' == $page_basename) {
            wp_enqueue_script( 'colorpicker' );
        }
    }

    function front_end_js(){
        if(defined('ICL_DONT_LOAD_LANGUAGES_JS') && ICL_DONT_LOAD_LANGUAGES_JS){
            return;
        }
        echo '<script type="text/javascript">var icl_lang = \''.$this->this_lang.'\';var icl_home = \''.$this->language_url().'\';</script>' . PHP_EOL;
        echo '<script type="text/javascript" src="'. ICL_PLUGIN_URL . '/res/js/sitepress.js"></script>' . PHP_EOL;
    }

    function js_scripts_categories(){
        wp_enqueue_script('sitepress-categories', ICL_PLUGIN_URL . '/res/js/categories.js', array(), ICL_SITEPRESS_VERSION);
    }

    function js_scripts_tags(){
        wp_enqueue_script('sitepress-tags', ICL_PLUGIN_URL . '/res/js/tags.js', array(), ICL_SITEPRESS_VERSION);
    }

    function rtl_fix(){
        global $wp_styles;
        if($this->is_rtl()){
            $wp_styles->text_direction = 'rtl';
        }
    }

    function css_setup(){
        if(isset($_GET['page'])){
            $page = basename($_GET['page']);
            $page_basename = str_replace('.php','',$page);
        }
        wp_enqueue_style('sitepress-style', ICL_PLUGIN_URL . '/res/css/style.css', array(), ICL_SITEPRESS_VERSION);
        if(isset($page_basename) && file_exists(ICL_PLUGIN_PATH . '/res/css/'.$page_basename.'.css')){
            wp_enqueue_style('sitepress-' . $page_basename, ICL_PLUGIN_URL . '/res/css/'.$page_basename.'.css', array(), ICL_SITEPRESS_VERSION);
        }

        if (is_admin()) {
            wp_enqueue_style('thickbox');
        }

    }

    function transfer_icl_account($create_account_and_transfer) {
        $user = $_POST['user'];
        $user['site_id'] = $this->settings['site_id'];
        $user['accesskey'] = $this->settings['access_key'];
        $user['create_account'] = $create_account_and_transfer ? '1' : '0';
        $icl_query = new ICanLocalizeQuery();
        list($success, $access_key) = $icl_query->transfer_account($user);
        if ($success) {
            $this->settings['access_key'] = $access_key;
            // set the support data the same.
            $this->settings['support_access_key'] = $access_key;
            $this->save_settings();
            return true;
        } else {
            $_POST['icl_form_errors'] = $access_key;

            return false;
        }
    }

    function process_forms(){
        global $wpdb;
        require_once ICL_PLUGIN_PATH . '/lib/Snoopy.class.php';
        require_once ICL_PLUGIN_PATH . '/lib/xml2array.php';
        require_once ICL_PLUGIN_PATH . '/lib/icl_api.php';

        if(isset($_POST['icl_post_action'])){
            switch($_POST['icl_post_action']){
                case 'save_theme_localization':
                    $locales = array();
                    foreach($_POST as $k=>$v){
                        if(0 !== strpos($k, 'locale_file_name_') || !trim($v)) continue;
                        $locales[str_replace('locale_file_name_','',$k)] = $v;
                    }
                    if(!empty($locales)){
                        $this->set_locale_file_names($locales);
                    }
                    break;
            }
            return;
        }
        $create_account = isset($_POST['icl_create_account_nonce']) && $_POST['icl_create_account_nonce']==wp_create_nonce('icl_create_account');
        $create_account_and_transfer = isset($_POST['icl_create_account_and_transfer_nonce']) && $_POST['icl_create_account_and_transfer_nonce']==wp_create_nonce('icl_create_account_and_transfer');
        $config_account = isset($_POST['icl_configure_account_nonce']) && $_POST['icl_configure_account_nonce']==wp_create_nonce('icl_configure_account');
        $create_support_account = isset($_POST['icl_create_support_account_nonce']) && $_POST['icl_create_support_account_nonce']==wp_create_nonce('icl_create_support_account');
        $config_support_account = isset($_POST['icl_configure_support_account_nonce']) && $_POST['icl_configure_support_account_nonce']==wp_create_nonce('icl_configure_support_account');
        $use_existing_account = isset($_POST['icl_use_account_nonce']) && $_POST['icl_use_account_nonce']==wp_create_nonce('icl_use_account');
        $transfer_to_account = isset($_POST['icl_transfer_account_nonce']) && $_POST['icl_transfer_account_nonce']==wp_create_nonce('icl_transfer_account');
        if( $create_account || $config_account || $create_support_account || $config_support_account){
            if (isset($_POST['icl_content_trans_setup_back_2'])) {
                // back button in wizard mode.
                $this->settings['content_translation_setup_wizard_step'] = 2;
                $this->save_settings();

            } else {
                $user = $_POST['user'];
                $user['create_account'] = (isset($_POST['icl_create_account_nonce']) ||
                                           isset($_POST['icl_create_support_account_nonce'])) ? 1 : 0;
                $user['platform_kind'] = 2;
                $user['cms_kind'] = 1;
                $user['blogid'] = $wpdb->blogid?$wpdb->blogid:1;
                $user['url'] = get_option('home');
                $user['title'] = get_option('blogname');
                $user['description'] = $this->settings['icl_site_description'];
                $user['is_verified'] = 1;

               if($user['create_account'] && defined('ICL_AFFILIATE_ID') && defined('ICL_AFFILIATE_KEY')){
                    $user['affiliate_id'] = ICL_AFFILIATE_ID;
                    $user['affiliate_key'] = ICL_AFFILIATE_KEY;
                }

                $user['interview_translators'] = $this->settings['interview_translators'];
                $user['project_kind'] = $this->settings['website_kind'];
                /*
                 if(is_null($user['project_kind']) || $user['project_kind']==''){
                    $_POST['icl_form_errors'] = __('Please select the kind of website','sitepress');
                    return;
                }
                */
                $user['pickup_type'] = intval($this->settings['translation_pickup_method']);

                $notifications = 0;
                if ( $this->settings['icl_notify_complete']){
                    $notifications += 1;
                }
                if ( $this->settings['alert_delay']){
                    $notifications += 2;
                }
                $user['notifications'] = $notifications;

                // prepare language pairs

                $pay_per_use = $this->settings['translator_choice'] == 1;

                $language_pairs = $this->settings['language_pairs'];
                $lang_pairs = array();
                if(isset($language_pairs)){
                    foreach($language_pairs as $k=>$v){
                        $english_fr = $wpdb->get_var("SELECT english_name FROM {$wpdb->prefix}icl_languages WHERE code='{$k}' ");
                        foreach($v as $k=>$v){
                            $incr++;
                            $english_to = $wpdb->get_var("SELECT english_name FROM {$wpdb->prefix}icl_languages WHERE code='{$k}' ");
                            $lang_pairs['from_language'.$incr] = ICL_Pro_Translation::server_languages_map($english_fr);
                            $lang_pairs['to_language'.$incr] = ICL_Pro_Translation::server_languages_map($english_to);
                            if ($pay_per_use) {
                                $lang_pairs['pay_per_use'.$incr] = 1;
                            }
                        }
                    }
                }
                $icl_query = new ICanLocalizeQuery();
                list($site_id, $access_key) = $icl_query->createAccount(array_merge($user,$lang_pairs));

                if(!$site_id){
                    $user['pickup_type'] = ICL_PRO_TRANSLATION_PICKUP_POLLING;
                    list($site_id, $access_key) = $icl_query->createAccount(array_merge($user,$lang_pairs));
                }

                if(!$site_id){

                    if ($access_key) {
                        $_POST['icl_form_errors'] = $access_key;
                    } else {
                        $_POST['icl_form_errors'] = __('An unknown error has occurred when communicating with the ICanLocalize server. Please try again.', 'sitepress');
                        // We will force the next try to be http.
                        update_option('_force_mp_post_http', 1);
                    }
                }else{
                    if ($create_account || $config_account) {
                        $iclsettings['site_id'] = $site_id;
                        $iclsettings['access_key'] = $access_key;
                        $iclsettings['icl_account_email'] = $user['email'];
                        // set the support data the same.
                        $iclsettings['support_site_id'] = $site_id;
                        $iclsettings['support_access_key'] = $access_key;
                        $iclsettings['support_icl_account_email'] = $user['email'];
                    } else {
                        $iclsettings['support_site_id'] = $site_id;
                        $iclsettings['support_access_key'] = $access_key;
                        $iclsettings['support_icl_account_email'] = $user['email'];
                    }
                    if(isset($user['pickup_type']) && $user['pickup_type']==ICL_PRO_TRANSLATION_PICKUP_POLLING){
                        $iclsettings['translation_pickup_method'] = ICL_PRO_TRANSLATION_PICKUP_POLLING;
                    }
                    $this->save_settings($iclsettings);
                    if($user['create_account']==1){
                        $_POST['icl_form_success'] = __('A project on ICanLocalize has been created.', 'sitepress') . '<br />';

                    }else{
                        $_POST['icl_form_success'] = __('Project added','sitepress');
                    }
                    $this->get_icl_translator_status($iclsettings);
                    $this->save_settings($iclsettings);

                }

                if (!$create_support_account &&
                            !$config_support_account &&
                            intval($site_id) > 0 &&
                            $access_key &&
                            $this->settings['content_translation_setup_complete'] == 0 &&
                            $this->settings['content_translation_setup_wizard_step'] == 3 &&
                            !isset($_POST['icl_form_errors'])) {
                    // we are running the wizard, so we can finish it now.
                    $this->settings['content_translation_setup_complete'] = 1;
                    $this->settings['content_translation_setup_wizard_step'] = 0;
                    $this->save_settings();

                }

            }
        }
        elseif ($use_existing_account || $transfer_to_account || $create_account_and_transfer) {

            if (isset($_POST['icl_content_trans_setup_back_2'])) {
                // back button in wizard mode.
                $this->settings['content_translation_setup_wizard_step'] = 2;
                $this->save_settings();
            } else {
                if ($transfer_to_account) {
                    $_POST['user']['email'] = $_POST['user']['email2'];
                }
                // we will be using the support account for the icl_account
                $this->settings['site_id'] = $this->settings['support_site_id'];
                $this->settings['access_key'] = $this->settings['support_access_key'];
                $this->settings['icl_account_email'] = $this->settings['support_icl_account_email'];

                $this->save_settings();

                update_icl_account();

                if ($transfer_to_account || $create_account_and_transfer) {
                    if (!$this->transfer_icl_account($create_account_and_transfer)) {
                        return;
                    }

                }

                // we are running the wizard, so we can finish it now.
                $this->settings['content_translation_setup_complete'] = 1;
                $this->settings['content_translation_setup_wizard_step'] = 0;
                $this->save_settings();

                $iclsettings['site_id'] = $this->settings['site_id'];
                $iclsettings['access_key'] = $this->settings['access_key'];
                $this->get_icl_translator_status($iclsettings);
                $this->save_settings($iclsettings);


            }
        }
        elseif(isset($_POST['icl_initial_languagenonce']) && $_POST['icl_initial_languagenonce']==wp_create_nonce('icl_initial_language')){

            $this->prepopulate_translations($_POST['icl_initial_language_code']);
            $wpdb->update($wpdb->prefix . 'icl_languages', array('active'=>'1'), array('code'=>$_POST['icl_initial_language_code']));
            $blog_default_cat = get_option('default_category');
            $blog_default_cat_tax_id = $wpdb->get_var("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id='{$blog_default_cat}' AND taxonomy='category'");

            if(isset($_POST['save_one_language'])){
                $this->settings['setup_wizard_step'] = 0;
                $this->settings['setup_complete'] = 1;
            }else{
                $this->settings['setup_wizard_step'] = 2;
            }

            $this->settings['default_categories'] = array($_POST['icl_initial_language_code'] => $blog_default_cat_tax_id);
            $this->settings['existing_content_language_verified'] = 1;
            $this->settings['default_language'] = $_POST['icl_initial_language_code'];
            $this->settings['admin_default_language'] = $this->admin_language = $_POST['icl_initial_language_code'];

            // set the locale in the icl_locale_map (if it's not set)
            if(!$wpdb->get_var("SELECT code FROM {$wpdb->prefix}icl_locale_map WHERE code='{$_POST['icl_initial_language_code']}'")){
                $default_locale = $wpdb->get_var("SELECT default_locale FROM {$wpdb->prefix}icl_languages WHERE code='{$_POST['icl_initial_language_code']}'");
                if($default_locale){
                    $wpdb->insert($wpdb->prefix.'icl_locale_map', array('code'=>$_POST['icl_initial_language_code'], 'locale'=>$default_locale));

                }
            }

            $this->save_settings();
            global $sitepress_settings;
            $sitepress_settings = $this->settings;
            $this->get_active_languages(true); //refresh active languages list
            do_action('icl_initial_language_set');
        }elseif(isset($_POST['icl_language_pairs_formnounce']) && $_POST['icl_language_pairs_formnounce'] == wp_create_nonce('icl_language_pairs_form')) {
            $this->save_language_pairs();

            $this->settings['content_translation_languages_setup'] = 1;
            // Move onto the site description page
            $this->settings['content_translation_setup_wizard_step'] = 2;

            $this->settings['website_kind'] = 2;
            $this->settings['interview_translators'] = 1;

            $this->save_settings();

        }elseif(isset($_POST['icl_site_description_wizardnounce']) && $_POST['icl_site_description_wizardnounce'] == wp_create_nonce('icl_site_description_wizard')) {
            if(isset($_POST['icl_content_trans_setup_back_2'])){
                // back button.
                $this->settings['content_translation_languages_setup'] = 0;
                $this->settings['content_translation_setup_wizard_step'] = 1;
                $this->save_settings();
            }elseif(isset($_POST['icl_content_trans_setup_next_2']) || isset($_POST['icl_content_trans_setup_next_2_enter'])){
                // next button.
                $description = $_POST['icl_description'];
                if ($description == "") {
                    $_POST['icl_form_errors'] = __('Please provide a short description of the website so that translators know what background is required from them.','sitepress');
                } else {
                    $this->settings['icl_site_description'] = $description;
                    $this->settings['content_translation_setup_wizard_step'] = 3;
                    $this->save_settings();
                }
            }
        }
    }

    function prepopulate_translations($lang){
        global $wpdb;
        if($this->settings['existing_content_language_verified']) return;

        $this->icl_translations_cache->clear();

        // case of icl_sitepress_settings accidentally lost
        // if there's at least one translation do not initialize the languages for elements
        $one_translation = $wpdb->get_var($wpdb->prepare("SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE language_code<>%s", $lang));
        if($one_translation){
            return;
        }
        
        mysql_query("TRUNCATE TABLE {$wpdb->prefix}icl_translations");
        mysql_query("
            INSERT INTO {$wpdb->prefix}icl_translations(element_type, element_id, trid, language_code, source_language_code)
            SELECT CONCAT('post_',post_type), ID, ID, '{$lang}', NULL FROM {$wpdb->posts} WHERE post_status IN ('draft', 'publish','schedule','future','private', 'pending')
            ");
        $maxtrid = 1 + $wpdb->get_var("SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations");

        /* preWP3 compatibility  - start */
        if(ICL_PRE_WP3){
        mysql_query("
            INSERT INTO {$wpdb->prefix}icl_translations(element_type, element_id, trid, language_code, source_language_code)
            SELECT 'tax_category', term_taxonomy_id, {$maxtrid}+term_taxonomy_id, '{$lang}', NULL FROM {$wpdb->term_taxonomy}
            WHERE taxonomy = 'category'
            ");
        $maxtrid = 1 + $wpdb->get_var("SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations");
        mysql_query("
            INSERT INTO {$wpdb->prefix}icl_translations(element_type, element_id, trid, language_code, source_language_code)
            SELECT 'tax_post_tag', term_taxonomy_id, {$maxtrid}+term_taxonomy_id, '{$lang}', NULL FROM {$wpdb->term_taxonomy}
            WHERE taxonomy = 'post_tag'
            ");
        $maxtrid = 1 + $wpdb->get_var("SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations");
        }else{
        /* preWP3 compatibility  - end */
        global $wp_taxonomies;
        $taxonomies = array_keys((array)$wp_taxonomies);
        foreach($taxonomies as $tax){
            mysql_query("
                INSERT INTO {$wpdb->prefix}icl_translations(element_type, element_id, trid, language_code, source_language_code)
                SELECT 'tax_".$tax."', term_taxonomy_id, {$maxtrid}+term_taxonomy_id, '{$lang}', NULL FROM {$wpdb->term_taxonomy} WHERE taxonomy = '{$tax}'
                ");
            $maxtrid = 1 + $wpdb->get_var("SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations");
        }

        /* preWP3 compatibility  - start */
        }
        /* preWP3 compatibility  - end */

        mysql_query("
            INSERT INTO {$wpdb->prefix}icl_translations(element_type, element_id, trid, language_code, source_language_code)
            SELECT 'comment', comment_ID, {$maxtrid}+comment_ID, '{$lang}', NULL FROM {$wpdb->comments}
            ");
    }

    function post_edit_language_options(){
        global $wpdb, $post, $iclTranslationManagement;

        if (current_user_can('manage_options')) {
            add_meta_box('icl_div_config', __('Multilingual Content Setup', 'sitepress'), 
                    array($this, 'meta_box_config'), $post->post_type, 'normal', 'low');
        }
            
        if(isset($_POST['icl_action']) && $_POST['icl_action'] == 'icl_mcs_inline'){
            if(!in_array($_POST['custom_post'], array('post', 'page'))){
                $iclsettings['custom_posts_sync_option'][$_POST['custom_post']] = @intval($_POST['translate']);
                if(@intval($_POST['translate'])){
                    $this->verify_post_translations($_POST['custom_post']);                
                }            
            }
            
            if(!empty($_POST['custom_taxs_off'])){
                foreach($_POST['custom_taxs_off'] as $off){
                    $iclsettings['taxonomies_sync_option'][$off] = 0;    
                }
            }

            if(!empty($_POST['custom_taxs_on'])){
                foreach($_POST['custom_taxs_on'] as $on){
                    $iclsettings['taxonomies_sync_option'][$on] = 1;    
                    $this->verify_taxonomy_translations($on);        
                }
            }
            
            if(!empty($_POST['cfnames'])){
                foreach($_POST['cfnames'] as $k=>$v){
                    $iclTranslationManagement->settings['custom_fields_translation'][base64_decode($v)] = @intval($_POST['cfvals'][$k]);
                    $iclTranslationManagement->save_settings();
                }
            }
            
            
            $this->save_settings($iclsettings);
        }
        
        $post_types = array_keys($this->get_translatable_documents());        
        if(in_array($post->post_type, $post_types)){
            add_meta_box('icl_div', __('Language', 'sitepress'), array($this,'meta_box'), $post->post_type, 'side', 'high');            
        }
        
                            
        
    }

    function set_element_language_details($el_id, $el_type='post_post', $trid, $language_code, $src_language_code = null, $check_duplicates = true){
        global $wpdb;

        // special case for posts and taxonomies
        // check if a different record exists for the same ID
        // if it exists don't save the new element and get out
        if($check_duplicates && $el_id){            
            $exp = explode('_', $el_type);
            $_type = $exp[0];
            if(in_array($_type, array('post', 'tax'))){
                $_el_exists = $wpdb->get_var("
                    SELECT translation_id FROM {$wpdb->prefix}icl_translations
                    WHERE element_id={$el_id} AND element_type <> '{$el_type}' AND element_type LIKE '{$_type}\\_%'");
                if($_el_exists){
                    trigger_error('Element ID already exists with a different type', E_USER_NOTICE);
                    return false;
                }
            }
        }

        if($trid){  // it's a translation of an existing element

            // check whether we have an orphan translation - the same trid and language but a different element id
            $translation_id = $wpdb->get_var("
                SELECT translation_id FROM {$wpdb->prefix}icl_translations
                WHERE   trid = '{$trid}'
                    AND language_code = '{$language_code}'
                    AND element_id <> '{$el_id}'
            "); 
            
            if($translation_id){
                $wpdb->query("DELETE FROM {$wpdb->prefix}icl_translations WHERE translation_id={$translation_id}");
                $this->icl_translations_cache->clear();
            }
            
            if(!is_null($el_id) && $translation_id = $wpdb->get_var("SELECT translation_id FROM {$wpdb->prefix}icl_translations 
                WHERE element_type='{$el_type}' AND element_id='{$el_id}' AND trid='{$trid}' AND element_id IS NOT NULL")){
                //case of language change
                $wpdb->update($wpdb->prefix.'icl_translations', 
                    array('language_code'=>$language_code), 
                    array('translation_id'=>$translation_id));                
            } elseif(!is_null($el_id) && $translation_id = $wpdb->get_var("SELECT translation_id FROM {$wpdb->prefix}icl_translations 
                WHERE element_type='{$el_type}' AND element_id='{$el_id}' AND element_id IS NOT NULL ")){                
                //case of changing the "translation of"
                $wpdb->update($wpdb->prefix.'icl_translations',
                    array('trid'=>$trid, 'language_code'=>$language_code, 'source_language_code'=>$src_language_code),
                    array('element_type'=>$el_type, 'element_id'=>$el_id));
                $this->icl_translations_cache->clear();
            } elseif($translation_id = $wpdb->get_var($wpdb->prepare("
                SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND language_code='%s' AND element_id IS NULL",                 
                $trid, $language_code ))){                
                    $wpdb->update($wpdb->prefix.'icl_translations', 
                        array('element_id'=>$el_id), 
                        array('translation_id'=>$translation_id)
                    );
            }else{
                //get source
                $src_language_code = $wpdb->get_var("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE trid={$trid} AND source_language_code IS NULL");
                // case of adding a new language
                $new = array(
                        'trid'=>$trid,
                        'element_type'=>$el_type,
                        'language_code'=>$language_code,
                        'source_language_code'=>$src_language_code
                        );
                if($el_id){
                    $new['element_id'] = $el_id;
                }
                $wpdb->insert($wpdb->prefix.'icl_translations', $new);
                $translation_id = $wpdb->insert_id;
                $this->icl_translations_cache->clear();

            }
        }else{ // it's a new element or we are removing it from a trid
            if($translation_id = $wpdb->get_var("
                    SELECT translation_id 
                    FROM {$wpdb->prefix}icl_translations WHERE element_type='{$el_type}' AND element_id='{$el_id}' AND element_id IS NOT NULL"
                    )){
                $wpdb->query("DELETE FROM {$wpdb->prefix}icl_translations WHERE translation_id={$translation_id}");
                $this->icl_translations_cache->clear();
            }

            $trid = 1 + $wpdb->get_var("SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations");

            $new = array(
                    'trid'=>$trid,
                    'element_type'=>$el_type,
                    'language_code'=>$language_code
            );
            if($el_id){
                $new['element_id'] = $el_id;
            }

            $wpdb->insert($wpdb->prefix.'icl_translations', $new);
            $translation_id = $wpdb->insert_id;
        }
        return $translation_id;
    }

    function delete_element_translation($trid, $el_type, $language_code = false){
        global $wpdb;
        $trid = intval($trid);
        $el_type = $wpdb->escape($el_type);
        $where = '';
        if($language_code){
            $where .= " AND language_code='".$wpdb->escape($language_code)."'";
        }
        $wpdb->query("DELETE FROM {$wpdb->prefix}icl_translations WHERE trid='{$trid}' AND element_type='{$el_type}' {$where}");
        $this->icl_translations_cache->clear();
    }

    function get_element_language_details($el_id, $el_type='post_post'){
        global $wpdb;
        static $pre_load_done = false;
        if (!$pre_load_done && !ICL_DISABLE_CACHE) {
            // search previous queries for a group of posts
            foreach ($this->queries as $query){
                $pos = strstr($query, 'post_id IN (');
                if ($pos !== FALSE) {
                    $group = substr($pos, 10);
                    $group = substr($group, 0, strpos($group, ')') + 1);

                    $query =
                        "SELECT element_id, trid, language_code, source_language_code
                        FROM {$wpdb->prefix}icl_translations
                        WHERE element_id IN {$group} AND element_type='{$el_type}'";
                    $ret = $wpdb->get_results($query);
                    foreach($ret as $details){
                        if (isset($this->icl_translations_cache)) {
                            $this->icl_translations_cache->set($details->element_id.$el_type, $details);
                        }
                    }

                    // get the taxonomy for the posts for later use
                    // categories first
                    $query =
                        "SELECT DISTINCT(tr.term_taxonomy_id), tt.term_id, tt.taxonomy, icl.trid, icl.language_code, icl.source_language_code
                        FROM {$wpdb->prefix}term_relationships as tr
                        LEFT JOIN {$wpdb->prefix}term_taxonomy AS tt
                        ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        LEFT JOIN {$wpdb->prefix}icl_translations as icl ON tr.term_taxonomy_id = icl.element_id
                        WHERE tr.object_id IN {$group}
                        AND (icl.element_type='tax_category' and tt.taxonomy='category')
                        ";
                    $query .= "UNION
                    ";
                    $query .=
                        "SELECT DISTINCT(tr.term_taxonomy_id), tt.term_id, tt.taxonomy, icl.trid, icl.language_code, icl.source_language_code
                        FROM {$wpdb->prefix}term_relationships as tr
                        LEFT JOIN {$wpdb->prefix}term_taxonomy AS tt
                        ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        LEFT JOIN {$wpdb->prefix}icl_translations as icl ON tr.term_taxonomy_id = icl.element_id
                        WHERE tr.object_id IN {$group}
                        AND (icl.element_type='tax_post_tag' and tt.taxonomy='post_tag')"
                        ;
                    global $wp_taxonomies;
                    $custom_taxonomies = array_diff(array_keys($wp_taxonomies), array('post_tag','category','link_category'));
                    if(!empty($custom_taxonomies)){
                        foreach($custom_taxonomies as $tax){
                            $query .= " UNION
                                SELECT DISTINCT(tr.term_taxonomy_id), tt.term_id, tt.taxonomy, icl.trid, icl.language_code, icl.source_language_code
                                FROM {$wpdb->prefix}term_relationships as tr
                                LEFT JOIN {$wpdb->prefix}term_taxonomy AS tt
                                ON tr.term_taxonomy_id = tt.term_taxonomy_id
                                LEFT JOIN {$wpdb->prefix}icl_translations as icl ON tr.term_taxonomy_id = icl.element_id
                                WHERE tr.object_id IN {$group}
                                AND (icl.element_type='tax_{$tax}' and tt.taxonomy='{$tax}')"
                                ;
                        }
                    }
                    $ret = $wpdb->get_results($query);

                    foreach($ret as $details){
                        // save language details
                        $lang_details = new stdClass();
                        $lang_details->trid = $details->trid;
                        $lang_details->language_code = $details->language_code;
                        $lang_details->source_language_code = $details->source_language_code;
                        if (isset($this->icl_translations_cache)) {
                            $this->icl_translations_cache->set($details->term_taxonomy_id.'tax_' . $details->taxonomy, $lang_details);
                            // save the term taxonomy
                            $this->icl_term_taxonomy_cache->set('category_'.$details->term_id, $details->term_taxonomy_id);
                        }
                    }

                    break;
                }
            }
            $pre_load_done = true;
        }

        if (isset($this->icl_translations_cache) && $this->icl_translations_cache->has_key($el_id.$el_type)) {
            return $this->icl_translations_cache->get($el_id.$el_type);
        }

        $details = $wpdb->get_row("
            SELECT trid, language_code, source_language_code
            FROM {$wpdb->prefix}icl_translations
            WHERE element_id='{$el_id}' AND element_type='{$el_type}'");
        if (isset($this->icl_translations_cache)) {
            $this->icl_translations_cache->set($el_id.$el_type, $details);
        }

        return $details;
    }

    function save_post_actions($pidd, $post){
        global $wpdb;

        list($post_type, $post_status) = $wpdb->get_row("SELECT post_type, post_status FROM {$wpdb->posts} WHERE ID = " . $pidd, ARRAY_N);                             
           
        // exceptions
        if(
               !$this->is_translated_post_type($post_type)
            || isset($_POST['autosave'])
            || isset($_POST['skip_sitepress_actions'])
            || (isset($_POST['post_ID']) && $_POST['post_ID']!=$pidd) || (isset($_POST['post_type']) && $_POST['post_type']=='revision')
            || $post_type == 'revision'
            || get_post_meta($pidd, '_wp_trash_meta_status', true)
            || ( isset($_GET['action']) && $_GET['action']=='restore')
            || $post_status == 'auto-draft'
        ){
            return;
        }

        // allow post arguments to be passed via wp_insert_post directly and not be expected on $_POST exclusively
        $postvars = (array)$_POST;
        foreach((array)$post as $k=>$v){
            $postvars[$k] = $v;                
        }
        
        if (!isset($postvars['post_type'])) {
            $postvars['post_type'] = $post_type;
        }
        
        if(isset($postvars['action']) && $postvars['action']=='post-quickpress-publish'){
            $post_id = $pidd;
            $language_code = $this->get_default_language();
        }elseif(isset($_GET['bulk_edit'])){
            $post_id = $wpdb->get_var("SELECT post_parent FROM {$wpdb->posts} WHERE ID={$pidd}");
        }
        else{
            $post_id = isset($postvars['post_ID'])?$postvars['post_ID']:$pidd; //latter case for XML-RPC publishing
            
            if(isset($postvars['icl_post_language'])){
                $language_code = $postvars['icl_post_language'];    
            }elseif($_ldet   = $this->get_element_language_details($post_id, 'post_' . $post_type)){
                $language_code = $_ldet->language_code;
            }else{
                $language_code = $this->get_default_language(); //latter case for XML-RPC publishing    
            }
            
        }

        if(isset($postvars['action']) && $postvars['action']=='inline-save' || isset($_GET['bulk_edit']) || isset($_GET['doing_wp_cron']) || @$_GET['action']=='untrash'){            
            $res = $wpdb->get_row("SELECT trid, language_code FROM {$wpdb->prefix}icl_translations WHERE element_id={$post_id} AND element_type LIKE 'post\\_%'");            
            $trid = $res->trid;
            $language_code = $res->language_code;
            
        }else{
            if(isset($postvars['icl_trid'])){
                $trid = @intval($postvars['icl_trid']);    
            }else{
                $trid = $this->get_element_trid($post_id, 'post_' . $post->post_type);
            }
            
            // see if we have a "translation of" setting.
            if(isset($postvars['icl_translation_of'])){
                if(is_numeric($postvars['icl_translation_of'])){
                    $trid = $wpdb->get_var($wpdb->prepare("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s", $postvars['icl_translation_of'], 'post_' . $post->post_type));                    
                }else{
                    $trid = null;    
                }
            }
        }
        $this->set_element_language_details($post_id, 'post_'.$post_type, $trid, $language_code);

        if(!in_array($post_type, array('post','page')) && $this->settings['custom_posts_sync_option'][$post_type] != 1){
            return;
        }

        
        // used by the sync jobs
        $translated_posts = $wpdb->get_col("
                SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid='{$trid}' AND element_id<>{$post_id}");        
        
        // synchronize the page order for translations
        if($trid && $this->settings['sync_page_ordering']){
            $menu_order = $wpdb->escape($postvars['menu_order']);            
            if(!empty($translated_posts)){
                $wpdb->query("UPDATE {$wpdb->posts} SET menu_order={$menu_order} WHERE ID IN (".join(',', $translated_posts).")");
            }
        }

        // synchronize the page parent for translations
        if($trid && $this->settings['sync_page_parent']){
            $translations = $this->get_element_translations($trid, 'post_' . $postvars['post_type']);
            foreach($translations as $target_lang => $target_details){
                if($target_lang != $language_code){
                    if ($target_details->element_id) {
                        $this->fix_translated_parent($post_id, $target_details->element_id, $target_lang, $language_code);
    
                        // restore child-parent relationships
                        $children = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_parent={$target_details->element_id} AND post_type='page'");
    
                        foreach($children as $ch){
                            $ch_trid = $this->get_element_trid($ch, 'post_' . $postvars['post_type']);
                            $ch_translations = $this->get_element_translations($ch_trid, 'post_' . $postvars['post_type']);
                            if(isset($ch_translations[$language_code])){
                                $wpdb->update($wpdb->posts, array('post_parent'=>$post_id), array('ID'=>$ch_translations[$language_code]->element_id));
                            }
                        }
                    }
                }
            }
        }

        // synchronize the page template
        if(isset($postvars['page_template']) && $trid && $postvars['post_type']=='page' && $this->settings['sync_page_template']){            
            if(!empty($translated_posts)){
                foreach($translated_posts as $tp){
                    if($tp != $post_id){
                        update_post_meta($tp, '_wp_page_template', $postvars['page_template']);
                    }
                }
            }
        }

        // synchronize comment and ping status
        if($trid && ($this->settings['sync_ping_status'] || $this->settings['sync_comment_status'])){
            $arr = array();
            if($this->settings['sync_comment_status']){
                $arr['comment_status'] = $postvars['comment_status'];
            }
            if($this->settings['sync_ping_status']){
                $arr['ping_status'] = $postvars['ping_status'];
            }
            if(!empty($arr)){
                if(!empty($translated_posts)){
                    foreach($translated_posts as $tp){
                        if($tp != $post_id){
                            $wpdb->update($wpdb->posts, $arr, array('ID'=>$tp));
                        }
                    }
                }
            }
        }

        
        $this->sync_custom_fields($post_id, $language_code);            

        //sync posts stcikiness
        if(isset($postvars['post_type']) && $postvars['post_type']=='post' && isset($postvars['action']) && $postvars['action']!='post-quickpress-publish' && $this->settings['sync_sticky_flag']){ //not for quick press
            remove_filter('option_sticky_posts', array($this,'option_sticky_posts')); // remove filter used to get language relevant stickies. get them all
            $sticky_posts = get_option('sticky_posts');
            // get ids of othe translations
            if($trid){
                $translations = $wpdb->get_col("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid='{$trid}'");
            }else{
                $translations = array();
            }
            if(isset($postvars['sticky']) && $postvars['sticky'] == 'sticky'){
                $sticky_posts = array_unique(array_merge($sticky_posts, $translations));
            }else{
                //makes sure translations are not set to sticky if this posts switched from sticky to not-sticky
                $sticky_posts = array_diff($sticky_posts, $translations);
            }
            update_option('sticky_posts',$sticky_posts);
        }

        //sync private flag
        if($this->settings['sync_private_flag']){
            if($post_status=='private' && $postvars['original_post_status']!='private'){
                if(!empty($translated_posts)){
                    foreach($translated_posts as $tp){
                        if($tp != $post_id){
                            $wpdb->update($wpdb->posts, array('post_status'=>'private'), array('ID'=>$tp));
                        }
                    }
                }
            }elseif($post_status!='private' && isset($postvars['original_post_status']) && $postvars['original_post_status']=='private'){
                if(!empty($translated_posts)){
                    foreach($translated_posts as $tp){
                        if($tp != $post_id){
                            $wpdb->update($wpdb->posts, array('post_status'=>$post_status), array('ID'=>$tp));
                        }
                    }
                }
            }
        }

        //sync post format
        if($this->settings['sync_post_format'] && function_exists('set_post_format')){
            $format = get_post_format( $post_id );
            if(!empty($translated_posts)){
                foreach($translated_posts as $tp){
                    if($tp != $post_id){
                        set_post_format($tp, $format);
                    }
                }
            }
        }

        // sync taxonomies (ONE WAY)
        if(!empty($this->settings['sync_post_taxonomies']) && $language_code == $this->get_default_language()){
            $translatable_taxs = $this->get_translatable_taxonomies(true, $postvars['post_type']);
            $all_taxs = get_object_taxonomies($postvars['post_type']);
            if(!empty($all_taxs)){            
                $translations = $this->get_element_translations($trid, 'post_' . $postvars['post_type']);
                foreach($all_taxs as $tt){
                    $terms = get_the_terms($post_id, $tt);    
                    if(!empty($terms)){
                        foreach($translations as $target_lang=>$translation){
                            if($target_lang != $language_code){
                                $tax_sync = array();
                                foreach($terms as $term){
                                    if(in_array($tt, $translatable_taxs)){
                                        $term_id = icl_object_id($term->term_id, $tt, false, $target_lang);       
                                    }else{
                                        $term_id = $term->term_id;   
                                    }
                                    if($term_id){
                                        $tax_sync[] = intval($term_id);
                                    }
                                }    
                                wp_set_object_terms($translation->element_id, $tax_sync, $tt, false);
                            } 
                        }
                    }
                }
            }
        }
        
        // sync post dates
        if(!empty($this->settings['sync_post_date'])){
            if($language_code == $this->get_default_language()){
                if(!empty($translated_posts)){
                    $post_date = $wpdb->get_var($wpdb->prepare("SELECT post_date FROM {$wpdb->posts} WHERE ID=%d", $post_id));
                    foreach($translated_posts as $tp){
                        if($tp != $post_id){
                            $wpdb->update($wpdb->posts, array('post_date'=>$post_date), array('ID'=>$tp));
                        }
                    }
                }
            }else{
                $source_lang = isset($_GET['source_lang'])?$_GET['source_lang']:$this->get_default_language();
                $original_id = $wpdb->get_var($wpdb->prepare("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND language_code=%s", $trid, $source_lang));                        
                $post_date = $wpdb->get_var($wpdb->prepare("SELECT post_date FROM {$wpdb->posts} WHERE ID=%d", $original_id));
                $wpdb->update($wpdb->posts, array('post_date'=>$post_date), array('ID'=>$post_id));
            }            
        }

        // new categories created inline go to the correct language
        if(isset($postvars['post_category']) && is_array($postvars['post_category']) && $postvars['action']!='inline-save' && $postvars['icl_post_language'])
        foreach($postvars['post_category'] as $cat){
            $ttid = $wpdb->get_var("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id={$cat} AND taxonomy='category'");
            $wpdb->update($wpdb->prefix.'icl_translations', 
                array('language_code'=>$postvars['icl_post_language']), 
                array('element_id'=>$ttid, 'element_type'=>'tax_category'));
        }

        if(isset($postvars['icl_tn_note'])){
            update_post_meta($post_id, '_icl_translator_note', $postvars['icl_tn_note']);
        }

        require_once ICL_PLUGIN_PATH . '/inc/cache.php';
        @icl_cache_clear($postvars['post_type'].'s_per_language');
    }

    function fix_translated_parent($original_id, $translated_id, $lang_code, $language_code){
        global $wpdb;

        $icl_post_type = isset($_POST['post_type']) ? 'post_' . $_POST['post_type'] : 'post_page';

        $original_parent = $wpdb->get_var("SELECT post_parent FROM {$wpdb->posts} WHERE ID = {$original_id} AND post_type = 'page'");

        if (!is_null($original_parent)){
            if($original_parent === '0'){
                $parent_of_translated_id = $wpdb->get_var("SELECT post_parent FROM {$wpdb->posts} WHERE ID = {$translated_id} AND post_type = 'page'");
                $translations = $this->get_element_translations($this->get_element_trid($parent_of_translated_id,$icl_post_type),$icl_post_type);
                if(isset($translations[$language_code])){
                    $wpdb->query("UPDATE {$wpdb->posts} SET post_parent='0' WHERE ID = ".$translated_id);
                }
            }else{
                $trid = $this->get_element_trid($original_parent, $icl_post_type);

                if($trid){
                    $translations = $this->get_element_translations($trid, $icl_post_type);
                    if (isset($translations[$lang_code])){
                        $current_parent = $wpdb->get_var("SELECT post_parent FROM {$wpdb->posts} WHERE ID = ".$translated_id);
                        if ($current_parent != $translations[$lang_code]->element_id){
                            $wpdb->query("UPDATE {$wpdb->posts} SET post_parent={$translations[$lang_code]->element_id} WHERE ID = ".$translated_id);
                        }
                    }
                }
            }
        }
    }

    function sync_custom_fields($post_id, $language_code, $single = true){
        global $wpdb;

        $field_names = array();

        if(!empty($this->settings['translation-management']['custom_fields_translation']))
        foreach($this->settings['translation-management']['custom_fields_translation'] as $cf => $op){
            if($op == 1){
                $field_names[] = $cf;
            }
        }
        
        
        if(!empty($field_names)){
            
            $post_type = $wpdb->get_var("SELECT post_type FROM {$wpdb->posts} WHERE ID={$post_id}");
            $trid = $wpdb->get_var("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_type='post_{$post_type}' AND element_id={$post_id}");
            if(!$trid){
                return;
            }
            
            $translations = $this->get_element_translations($trid, 'post_'.$post_type);
            
            if($translations[$language_code]->original){  // copy *to* translations
                foreach($field_names as $field_name){
                    if(isset($_POST['meta']) && $single){
                        if(is_null($metas)){
                            foreach((array)$_POST['meta'] as $meta_id=>$meta){
                                $metas[$meta['key']] = $meta['value'];    
                            }
                        }
                        $field_value = isset($metas[$field_name]) ? $metas[$field_name] : false;                    
                    }else{
                        $field_value = get_post_meta($post_id, $field_name, $single);    
                    }
                    foreach($translations as $t){
                        if($t->original) continue;
                        if($field_value === false || $field_value === ''){
                            delete_post_meta($t->element_id, $field_name);
                        }else{
                            update_post_meta($t->element_id, $field_name, $field_value);
                        }
                    }
                }
            }else{ // copy *from* original
                foreach($translations as $l=>$t){
                    if($t->original){
                        $original_id = $t->element_id;
                        break;
                    }
                }
                foreach($field_names as $field_name){
                    $field_value = get_post_meta($original_id, $field_name, $single);        
                    
                    if($field_value === false || $field_value === ''){
                        delete_post_meta($post_id, $field_name);
                    }else{
                        update_post_meta($post_id, $field_name, $field_value);
                    }
                }                    
                
            }
            
        }
    }

    function delete_post_actions($post_id){
        global $wpdb;
        $post_type = $wpdb->get_var("SELECT post_type FROM {$wpdb->posts} WHERE ID={$post_id}");
        $wpdb->query("DELETE FROM {$wpdb->prefix}icl_translations WHERE element_type='post_{$post_type}' AND element_id='{$post_id}' LIMIT 1");

        require_once ICL_PLUGIN_PATH . '/inc/cache.php';
        icl_cache_clear($post_type.'s_per_language');
    }

    function trash_post_actions($post_id){
        if($this->settings['sync_delete']){
            global $wpdb;
            static $trashed_posts = array();
            $post_type = $wpdb->get_var("SELECT post_type FROM {$wpdb->posts} WHERE ID={$post_id}");
            if(isset($trashed_posts[$post_id])){
                return; // avoid infinite loop
            }

            $trashed_posts[$post_id] = $post_id;

            $trid = $this->get_element_trid($post_id, 'post_' . $post_type);
            $translations = $this->get_element_translations($trid, 'post_' . $post_type);
            foreach($translations as $t){
                if($t->element_id != $post_id){
                    wp_trash_post($t->element_id);
                }
            }
            require_once ICL_PLUGIN_PATH . '/inc/cache.php';
            icl_cache_clear($post_type.'s_per_language');
        }
    }

    function untrashed_post_actions($post_id){
        if($this->settings['sync_delete']){
            global $wpdb;
            static $untrashed_posts = array();
            $post_type = $wpdb->get_var("SELECT post_type FROM {$wpdb->posts} WHERE ID={$post_id}");

            if(isset($untrashed_posts[$post_id])){
                return; // avoid infinite loop
            }

            $untrashed_posts[$post_id] = $post_id;

            $trid = $this->get_element_trid($post_id, 'post_' . $post_type);
            $translations = $this->get_element_translations($trid, 'post_' . $post_type);
            foreach($translations as $t){
                if($t->element_id != $post_id){
                    wp_untrash_post($t->element_id);
                }
            }
            require_once ICL_PLUGIN_PATH . '/inc/cache.php';

            icl_cache_clear($post_type.'s_per_language');
        }
    }

    function get_element_translations($trid, $el_type='post_post', $skip_empty = false){
        global $wpdb;
        $translations = array();
        $sel_add = '';
        if($trid){
            if(0 === strpos($el_type, 'post_')){
                $sel_add = ', p.post_title, p.post_status';
                $join_add = " LEFT JOIN {$wpdb->posts} p ON t.element_id=p.ID";
                $groupby_add = "";
            }elseif(preg_match('#^tax_(.+)$#',$el_type)){
                $sel_add = ', tm.name, tm.term_id, COUNT(tr.object_id) AS instances';
                $join_add = " LEFT JOIN {$wpdb->term_taxonomy} tt ON t.element_id=tt.term_taxonomy_id
                              LEFT JOIN {$wpdb->terms} tm ON tt.term_id = tm.term_id
                              LEFT JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id=tt.term_taxonomy_id
                              ";
                $groupby_add = "GROUP BY tm.term_id";
            }
            $where_add = " AND t.trid='{$trid}'";

            $query = "
                SELECT t.translation_id, t.language_code, t.element_id, t.source_language_code IS NULL AS original {$sel_add}
                FROM {$wpdb->prefix}icl_translations t
                     {$join_add}
                WHERE 1 {$where_add}
                {$groupby_add} 
            ";       
            
            $ret = $wpdb->get_results($query);        
            
            foreach($ret as $t){                                
                if((preg_match('#^tax_(.+)$#',$el_type)) 
                    && $t->instances==0 && !_icl_tax_has_objects_recursive($t->element_id)
                    && $skip_empty) continue;
                $translations[$t->language_code] = $t;
            }
            
        }
        
        return $translations;
    }

    function get_element_trid($element_id, $el_type='post_post'){
        global $wpdb;
        return $wpdb->get_var("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id='{$element_id}' AND element_type='{$el_type}'");
    }

    function get_language_for_element($element_id, $el_type='post_post'){
        global $wpdb;
        return $wpdb->get_var("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id='{$element_id}' AND element_type='{$el_type}'");
    }

    function get_elements_without_translations($el_type, $target_lang, $source_lang){
        global $wpdb;

        // first get all the trids for the target languages
        // These will be the trids that we don't want.
        $sql = "SELECT
                    trid
                FROM
                    {$wpdb->prefix}icl_translations
                WHERE
                    language_code = '{$target_lang}'";
                    

        $trids_for_target = $wpdb->get_col($sql);
        if (sizeof($trids_for_target) > 0) {
            $trids_for_target = join(',', $trids_for_target);
            $not_trids = 'AND trid NOT IN (' .$trids_for_target . ')';
        } else {
            $not_trids = '';
        }
        
        $join = $where = '';
        // exclude trashed posts
        if(0 === strpos($el_type, 'post_')){
            $join .= " JOIN {$wpdb->posts} ON {$wpdb->posts}.ID = {$wpdb->prefix}icl_translations.element_id";
            $where .= " AND {$wpdb->posts}.post_status <> 'trash'";
        }
        
        // Now get all the elements that are in the source language that
        // are not already translated into the target language.
        $sql = "SELECT
                    element_id
                FROM
                    {$wpdb->prefix}icl_translations
                    {$join}
                WHERE
                    language_code = '{$source_lang}'
                    {$not_trids}
                    AND element_type= '{$el_type}'
                    {$where}
                ";
           
                    
        return $wpdb->get_col($sql);
    }

    function get_posts_without_translations($selected_language, $default_language, $post_type='post_post') {
        global $wpdb;
        $untranslated_ids = $this->get_elements_without_translations($post_type, $selected_language, $default_language);
        
        if (sizeof($untranslated_ids)) {
            // filter for "page" or "post"
            $ids = join(',',$untranslated_ids);
            $type = preg_replace('#^post_#','',$post_type);
            $untranslated_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE ID IN ({$ids}) AND post_type = '{$type}' AND post_status <> 'auto-draft'");
        }

        $untranslated = array();

        foreach ($untranslated_ids as $id) {
            $untranslated[$id] = $wpdb->get_var("SELECT post_title FROM {$wpdb->prefix}posts WHERE ID = {$id}");
        }

        return $untranslated;
    }

    function meta_box($post){
        global $wpdb, $wp_post_types, $iclTranslationManagement;
        
        if(in_array($post->post_type, array_keys($this->get_translatable_documents()))){
            $active_languages = $this->get_active_languages();
            $default_language = $this->get_default_language();
            if($post->ID && $post->post_status != 'auto-draft'){
                $res = $this->get_element_language_details($post->ID, 'post_'.$post->post_type);
                $trid = @intval($res->trid);
                if($trid){   
                    $element_lang_code = $res->language_code;
                }else{
                    $trid = $this->set_element_language_details($post->ID,'post_'.$post->post_type,null, $this->get_current_language());
                    $element_lang_code = $this->get_current_language();
                }            
            }else{
                $trid = isset($_GET['trid']) ? $_GET['trid'] : false;
                $element_lang_code = isset($_GET['lang']) ? $_GET['lang'] : $this->get_current_language();
            } 
            
            $translations = array();                
            if($trid){
                $translations = $this->get_element_translations($trid, 'post_'.$post->post_type);        
            }
            
            $selected_language = $element_lang_code?$element_lang_code:$this->get_current_language();
            
            if(isset($_GET['lang'])){
                $_selected_language = $_GET['lang'];
            }else{
                $_selected_language = $selected_language;
            }                
            
            $untranslated = $this->get_posts_without_translations($_selected_language, $default_language, 'post_' . $post->post_type);
            
            $source_language = isset($_GET['source_lang']) ? $_GET['source_lang'] : false;
            
            //globalize some variables to make them available through hooks
            global $icl_meta_box_globals;
            $icl_meta_box_globals = array(
                'active_languages' => $active_languages,
                'translations' => $translations,
                'selected_language' => $selected_language
            );
            
            include ICL_PLUGIN_PATH . '/menu/post-menu.php';
            
        }
        
    }
    
    function meta_box_config($post){
        global $wpdb, $wp_post_types, $iclTranslationManagement;   
        global $wp_taxonomies, $wp_post_types;
        
        
        echo '<div class="icl_form_success" style="display:none">'.__('Settings saved', 'sitepress').'</div>';
        
        $cp_editable = false;
        if(!in_array($post->post_type, array('post', 'page'))){
            
            if(in_array($post->post_type, array_keys($this->get_translatable_documents()))){
                $checked = ' checked="checked"';
                $rdisabled = isset($iclTranslationManagement->settings['custom_types_readonly_config'][$post->post_type]) ? 'disabled="disabled"':'';
            }else{
                $checked = $rdisabled = '';
            }
            
            if(!$rdisabled){
                $cp_editable = true;
            }
            
            echo '<br style="line-height:8px;" /><label><input id="icl_make_translatable" type="checkbox" value="'.$post->post_type.'"'.$checked . $rdisabled.'/>&nbsp;' .             
            sprintf(__("Make '%s' translatable", 'sitepress'), $wp_post_types[$post->post_type]->labels->name) . '</label><br style="line-height:8px;" />';

        }else{
            echo '<input id="icl_make_translatable" type="checkbox" checked="checked" value="'.$post->post_type.'" style="display:none" />';
            $checked = true;
        }

        echo '<br clear="all" /><span id="icl_mcs_details">';        
        
        if($checked){
            
            //echo '<div style="width:49%;float:left;min-width:265px;margin-right:5px;margin-top:3px;">';
                
            $ctaxonomies = array_diff(get_object_taxonomies($post->post_type), array('post_tag','category', 'nav_menu', 'link_category', 'post_format'));    

            if(!empty($ctaxonomies)){
                ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th colspan="2"><?php _e('Custom taxonomies', 'sitepress'); ?></th>
                        </tr>             
                    </thead> 
                    <tbody>
                        <?php foreach($ctaxonomies as $ctax): ?>
                        <?php 
                            $checked1 = !empty($this->settings['taxonomies_sync_option'][$ctax]) ? ' checked="checked"' : '';
                            $checked0 = empty($this->settings['taxonomies_sync_option'][$ctax]) ? ' checked="checked"' : '';
                        ?>
                        <tr>
                            <td><?php echo $wp_taxonomies[$ctax]->labels->name ?></td>
                            <td align="right">
                                <label><input name="icl_mcs_custom_taxs_<?php echo $ctax ?>" class="icl_mcs_custom_taxs" type="radio" value="<?php echo $ctax ?>" <?php echo $checked1; ?> />&nbsp;<?php _e('Translate', 'sitepress')?></label>
                                <label><input name="icl_mcs_custom_taxs_<?php echo $ctax ?>" type="radio" value="0" <?php echo $checked0; ?> />&nbsp;<?php _e('Do nothing', 'sitepress')?></label>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>                   
                </table>
                <br />
                <?php
            }
            
            //echo '</div><div style="width:50%;float:left;min-width:265px;margin-top:3px;">';
            
            if(defined('WPML_TM_VERSION')){
                $custom_keys = (array)get_post_custom_keys($post->ID);                
                $cf_keys_exceptions = array('_edit_last', '_edit_lock', '_wp_page_template', '_wp_attachment_metadata', '_icl_translator_note', '_alp_processed');
                $custom_keys = array_diff($custom_keys, $cf_keys_exceptions);
                $cf_settings_ro = (array)$iclTranslationManagement->settings['custom_fields_readonly_config'];  
                $cf_settings = $iclTranslationManagement->settings['custom_fields_translation'];  
                
                if(!empty($custom_keys)){
                    ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th colspan="2"><?php _e('Custom fields', 'sitepress'); ?></th>
                            </tr>             
                        </thead>
                        <tbody>
                            <?php foreach($custom_keys as $cfield): ?>
                            <?php 
                                $rdisabled = in_array($cfield, $cf_settings_ro) ? 'disabled="disabled"' : '';
                                $checked0 = empty($cf_settings[$cfield]) ? ' checked="checked"' : '';
                                $checked1 = isset($cf_settings[$cfield]) && $cf_settings[$cfield]==1 ? ' checked="checked"' : '';
                                $checked2 = isset($cf_settings[$cfield]) && $cf_settings[$cfield]==2 ? ' checked="checked"' : '';
                            ?>
                            <tr>
                                <td><?php echo $cfield; ?></td>
                                <td align="right"> 
                                    <label><input class="icl_mcs_cfs" name="icl_mcs_cf_<?php echo base64_encode($cfield); ?> " type="radio" value="0" <?php echo $rdisabled.$checked0 ?> />&nbsp;<?php _e("Don't translate", 'sitepress') ?></label>
                                    <label><input class="icl_mcs_cfs" name="icl_mcs_cf_<?php echo base64_encode($cfield); ?> " type="radio" value="1" <?php echo $rdisabled.$checked1 ?> />&nbsp;<?php _e("Copy", 'sitepress') ?></label>
                                    <label><input class="icl_mcs_cfs" name="icl_mcs_cf_<?php echo base64_encode($cfield); ?> " type="radio" value="2" <?php echo $rdisabled.$checked2 ?> />&nbsp;<?php _e("Translate", 'sitepress') ?></label>
                                </td>
                            </tr>                                
                            <?php endforeach;?>
                        </tbody>
                    </table>
                    <br />
                    <?php                    
                }
            }
            
            //echo '</div><br clear="all" />';
            
            if(!empty($ctaxonomies) || !empty($custom_keys)){
                echo '<small>' . __('Note: Custom taxonomies and custom fields are shared across different post types.', 'sitepress') . '</small>';
            }
            
        }
        echo '</span>';

        if($cp_editable || !empty($ctaxonomies) || !empty($custom_keys)){
            echo '<p class="submit" style="margin:0;padding:0"><input class="button-secondary" id="icl_make_translatable_submit" type="button" value="'.__('Apply', 'sitepress').'" /></p><br clear="all" />';
        }else{
            _e('Nothing to configure.', 'sitepress');
        }
            
            

    }

    function pre_get_posts($wpq){

        // case of internal links list
        //
        if(isset($_POST['action']) && 'wp-link-ajax' == $_POST['action']){
            if(!empty($_SERVER['HTTP_REFERER'])){
                $parts = parse_url($_SERVER['HTTP_REFERER']);
                parse_str(strval($parts['query']), $query);
                $lang = isset($query['lang']) ?  $query['lang'] : $this->get_default_language();
            }else $lang = $this->get_default_language();
            $this->this_lang = $lang;
            $wpq->query_vars['suppress_filters'] = false;
        }


        return $wpq;
    }

    function posts_join_filter($join){
        global $wpdb, $pagenow, $wp_taxonomies;
        //exceptions
        if(isset($_POST['wp-preview']) && $_POST['wp-preview']=='dopreview' || is_preview()){
            $is_preview = true;
        }else{
            $is_preview = false;
        }
        if($pagenow=='upload.php' || $pagenow=='media-upload.php' || is_attachment() || $is_preview){
            return $join;
        }

        // determine post type
        $db = debug_backtrace();
        foreach($db as $o){
            if($o['function']=='apply_filters_ref_array' && $o['args'][0]=='posts_join'){
                $post_type =  $wpdb->escape($o['args'][1][1]->query_vars['post_type']);
                break;
            }
        }
        
        if($post_type == 'any' || 'all' == $this->this_lang){
            $ljoin = "LEFT";
        }else{
            $ljoin = "";
        }        
        
        if(is_array($post_type)){
            $ptypes = array();
            foreach($post_type as $ptype){
                if($this->is_translated_post_type($ptype)){
                    $ptypes[] = $wpdb->escape('post_' . $ptype);
                }
            }
            if(!empty($ptypes)){
                $join .= " {$ljoin} JOIN {$wpdb->prefix}icl_translations t ON {$wpdb->posts}.ID = t.element_id
                        AND t.element_type IN ('".join("','", $ptypes)."') JOIN {$wpdb->prefix}icl_languages l ON t.language_code=l.code AND l.active=1";
            }
        }elseif($post_type){
            if($this->is_translated_post_type($post_type)){
                $join .= " {$ljoin} JOIN {$wpdb->prefix}icl_translations t ON {$wpdb->posts}.ID = t.element_id
                        AND t.element_type = 'post_{$post_type}' JOIN {$wpdb->prefix}icl_languages l ON t.language_code=l.code AND l.active=1";
            }elseif($post_type == 'any'){
                $join .= " {$ljoin} JOIN {$wpdb->prefix}icl_translations t ON {$wpdb->posts}.ID = t.element_id
                        AND t.element_type LIKE 'post\\_%' {$ljoin} JOIN {$wpdb->prefix}icl_languages l ON t.language_code=l.code AND l.active=1";
            }
        }else{

            if(is_tax()){
                $tax = get_query_var('taxonomy');
                $ttypes = $wp_taxonomies[$tax]->object_type;                        
                foreach($ttypes as $k=>$v){
                    if(!$this->is_translated_post_type($v)) unset($ttypes[$k]);
                }
            }else{
                $ttypes = array_keys($this->get_translatable_documents(false));    
            }
            
            if(!empty($ttypes)){
                foreach($ttypes as $k=>$v) $ttypes[$k] = 'post_' . $v;
                $post_types = "'" . join("','",$ttypes) . "'";
                $join .= " {$ljoin} JOIN {$wpdb->prefix}icl_translations t ON {$wpdb->posts}.ID = t.element_id
                        AND t.element_type IN ({$post_types}) JOIN {$wpdb->prefix}icl_languages l ON t.language_code=l.code AND l.active=1";
            }
        }
        
        return $join;
    }

    function posts_where_filter($where){
        global $wpdb, $pagenow, $wp_taxonomies;
        //exceptions

        //$post_type = get_query_var('post_type');

        // determine post type
        $db = debug_backtrace();
        foreach($db as $o){
            if($o['function']=='apply_filters_ref_array' && $o['args'][0]=='posts_where'){
                $post_type =  $o['args'][1][1]->query_vars['post_type'];
                break;
            }
        }

        // case of taxonomy archive
        if(empty($post_type) && is_tax()){
            $tax = get_query_var('taxonomy');
            $post_type = $wp_taxonomies[$tax]->object_type;                        
            foreach($post_type as $k=>$v){
                if(!$this->is_translated_post_type($v)) unset($post_type[$k]);
            }
            if(empty($post_type)) return $where;  // don't filter
        } 
        
        if(!$post_type) $post_type = 'post';
        
        if(is_array($post_type) && !empty($post_type)){
            $none_translated = true;
            foreach($post_type as $ptype){
                if($this->is_translated_post_type($ptype)){
                    $none_translated = false;
                }
            }
            if($none_translated) return $where;
        }else{
            if(!$this->is_translated_post_type($post_type) && 'any' != $post_type){
                return $where;
            }
        }

        if(isset($_POST['wp-preview']) && $_POST['wp-preview']=='dopreview' || is_preview()){
            $is_preview = true;
        }else{
            $is_preview = false;
        }
        if($pagenow=='upload.php' || $pagenow=='media-upload.php' || is_attachment() || $is_preview){
            return $where;
        }

        if('all' != $this->this_lang){
            if('any' == $post_type){
                $cond = " AND (t.language_code='{$wpdb->escape($this->get_current_language())}' OR t.language_code IS NULL )";
            }else{            
                $cond = " AND t.language_code='{$wpdb->escape($this->get_current_language())}'";
            }
        }else{
            $cond = '';    
        }
        
        $where .= $cond;
        return $where;
    }

    function comment_feed_join($join){
        global $wpdb, $wp_query;
        $type = $wp_query->query_vars['post_type'] ? $wpdb->escape($wp_query->query_vars['post_type']) : 'post';
        $wp_query->query_vars['is_comment_feed'] = true;
        $join .= " JOIN {$wpdb->prefix}icl_translations t ON {$wpdb->comments}.comment_post_ID = t.element_id
                    AND t.element_type='post_{$type}' AND t.language_code='{$wpdb->escape($this->this_lang)}' ";
        return $join;
    }
    
    function comments_clauses($clauses){
        global $wpdb;
        
        if($this->get_current_language() != 'all'){
            $clauses['join'] .= " JOIN {$wpdb->prefix}icl_translations icltr1 ON 
                                    icltr1.element_id = {$wpdb->comments}.comment_ID
                                    JOIN {$wpdb->prefix}icl_translations icltr2 ON 
                                    icltr2.element_id = {$wpdb->comments}.comment_post_ID
                                    "; 
            $clauses['where'] .= " AND icltr1.element_type = 'comment' 
                                   AND icltr1.language_code = '".$this->get_current_language()."'
                                   AND icltr2.language_code = '".$this->get_current_language()."'
                                   AND icltr2.element_type LIKE 'post\\_%' ";
        }
        
        return $clauses;
    }

    function language_filter(){
        require_once ICL_PLUGIN_PATH . '/inc/cache.php';
        global $wpdb, $pagenow;

        /* preWP3 compatibility  - start */
        if(ICL_PRE_WP3){
            if($pagenow=='edit.php'){
                $type = 'post';
            }else{
                $type = 'page';
            }
        }else{
        /* preWP3 compatibility  - end */
            $type = isset($_GET['post_type'])?$_GET['post_type']:'post';    
        }        
                
        if(!in_array($type, array('post','page')) && !in_array($type, array_keys($this->get_translatable_documents()))){            
            return;
        }

        $active_languages = $this->get_active_languages();

        $post_status = get_query_var('post_status');

        $langs = icl_cache_get($type.'s_per_language#' . $post_status);
        if(!$langs){

            $extra_cond = "";
            if($post_status){
                $extra_cond .= " AND post_status = '" . $post_status . "'";
            }
            if($post_status != 'trash'){
                $extra_cond .= " AND post_status <> 'trash'";
            }

            $res = $wpdb->get_results("
                SELECT language_code, COUNT(p.ID) AS c FROM {$wpdb->prefix}icl_translations t
                JOIN {$wpdb->posts} p ON t.element_id=p.ID
                JOIN {$wpdb->prefix}icl_languages l ON t.language_code=l.code AND l.active = 1
                WHERE p.post_type='{$type}' AND t.element_type='post_{$type}' {$extra_cond}
                GROUP BY language_code
                ");
            $langs['all'] = 0;
            foreach($res as $r){
                $langs[$r->language_code] = $r->c;
                $langs['all'] += $r->c;
            }
            icl_cache_set($type.'s_per_language', $langs);

        }

        $active_languages[] = array('code'=>'all','display_name'=>__('All languages','sitepress'));
        foreach($active_languages as $lang){
            if($lang['code']== $this->this_lang){
                $px = '<strong>';
                $sx = ' <span class="count">('. @intval($langs[$lang['code']]) .')<\/span><\/strong>';
            }elseif(!isset($langs[$lang['code']])){
                $px = '<span>';
                $sx = '<\/span>';
            }else{
                if($post_status){
                    $px = '<a href="?post_type='.$type.'&post_status='.$post_status.'&lang='.$lang['code'].'">';
                }else{
                    $px = '<a href="?post_type='.$type.'&lang='.$lang['code'].'">';
                }
                $sx = '<\/a> <span class="count">('. intval($langs[$lang['code']]) .')<\/span>';
            }
            $as[] =  $px . $lang['display_name'] . $sx;
        }
        $allas = join(' | ', $as);
        if($type == 'page' && !$this->get_icl_translation_enabled()){
            $prot_link = '<span class="button" style="padding:4px;margin-top:10px;"><img align="baseline" src="' . ICL_PLUGIN_URL .'/res/img/icon16.png" width="16" height="16" style="margin-bottom:-4px" /> <a href="http://wpml.org/?page_id=3416">' .
            __('How to translate', 'sitepress') . '<\/a>' . '<\/span>';
        }else{
            $prot_link = '';
        }
        ?>
        <script type="text/javascript">
            jQuery(".subsubsub").append('<br /><span id="icl_subsubsub"><?php echo $allas ?><\/span><br /><?php echo $prot_link ?>');
        </script>
        <?php
    }

    function exclude_other_language_pages2($arr){
        if($this->get_current_language() != 'all'){
            global $wpdb;
            
            if(is_array($arr) && !empty($arr[0]->post_type)){
                $post_type = $arr[0]->post_type;
            }else{
                $post_type = 'page';
            }
            
            $filtered_pages = array();
            // grab list of pages NOT in the current language
            $excl_pages = $wpdb->get_col($wpdb->prepare("
                SELECT p.ID FROM {$wpdb->posts} p
                JOIN {$wpdb->prefix}icl_translations t ON p.ID = t.element_id
                WHERE t.element_type=%s AND p.post_type=%s AND t.language_code <> %s
                ", 'post_' . $post_type, $post_type , $this->this_lang));
            // exclude them from the result set
            
            foreach($arr as $page){
                if(!in_array($page->ID,$excl_pages)){
                    $filtered_pages[] = $page;
                }
            }
            $arr = $filtered_pages;
        }
        return $arr;
    }

    function wp_dropdown_pages($output){
        global $wpdb;
        if(isset($_POST['lang_switch'])){
            $post_id = $wpdb->escape($_POST['lang_switch']);
            $lang = $wpdb->escape($_GET['lang']);
            $parent = $wpdb->get_var($wpdb->prepare("SELECT post_parent FROM {$wpdb->posts} WHERE ID=%d", $post_id));
            if($parent){
                $trid = $wpdb->get_var("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id='{$parent}' AND element_type='post_page'");
                $translated_parent_id = $wpdb->get_var("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid='{$trid}' AND element_type='post_page' AND language_code='{$lang}'");
                if($translated_parent_id){
                    $output = str_replace('selected="selected"','',$output);
                    $output = str_replace('value="'.$translated_parent_id.'"','value="'.$translated_parent_id.'" selected="selected"',$output);
                }
            }
        }elseif(isset($_GET['lang']) && isset($_GET['trid'])){
            $lang = $wpdb->escape($_GET['lang']);
            $trid = $wpdb->escape($_GET['trid']);
            $elements_id = $wpdb->get_col("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid='{$trid}' AND element_type='post_page' AND element_id IS NOT NULL");
            $translated_parent_id = 0;
            foreach($elements_id as $element_id){
                $parent = $wpdb->get_var("SELECT post_parent FROM {$wpdb->posts} WHERE ID={$element_id}");
                $trid = $wpdb->get_var("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id='{$parent}' AND element_type='post_page'");
                $translated_parent_id = $wpdb->get_var("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid='{$trid}' AND element_type='post_page' AND language_code='{$lang}'");
                if($translated_parent_id) break;
            }
            if($translated_parent_id){
                $output = str_replace('selected="selected"','',$output);
                $output = str_replace('value="'.$translated_parent_id.'"','value="'.$translated_parent_id.'" selected="selected"',$output);
            }
        }
        if(!$output){
            $output = '<select id="parent_id"><option value="">' . __('Main Page (no parent)','sitepress') . '</option></select>';
        }
        return $output;
    }

    function edit_term_form($term){
        global $wpdb, $pagenow;
        $element_id = isset($term->term_taxonomy_id)?$term->term_taxonomy_id:false;

        /* preWP3 compatibility  - start */
        if(ICL_PRE_WP3){
            $icl_element_type = $pagenow=='categories.php'?'tax_category':'tax_post_tag';
        }else{
        /* preWP3 compatibility  - end */
            $element_type = isset($_GET['taxonomy']) ? $wpdb->escape($_GET['taxonomy']) : 'post_tag';
            $icl_element_type = 'tax_' . $element_type;
        }

        $default_language = $this->get_default_language();

        if($element_id){
            $res = $wpdb->get_row("SELECT trid, language_code, source_language_code
                FROM {$wpdb->prefix}icl_translations WHERE element_id='{$element_id}' AND element_type='{$icl_element_type}'");
            $trid = $res->trid;
            if($trid){
                $element_lang_code = $res->language_code;
            }else{
                $element_lang_code = $this->get_current_language();
                $trid = $this->set_element_language_details($element_id, $icl_element_type, null, $element_lang_code);                
            }
        }else{
            $trid = isset($_GET['trid']) ? $_GET['trid'] : false;
            $element_lang_code = isset($_GET['lang']) ? $_GET['lang'] : $this->get_current_language();
        }
        if($trid){
            $translations = $this->get_element_translations($trid, $icl_element_type);
        }
        $active_languages = $this->get_active_languages();
        $selected_language = $element_lang_code?$element_lang_code:$default_language;

        $source_language = isset($_GET['source_lang']) ? $_GET['source_lang'] : false;
        $untranslated_ids = $this->get_elements_without_translations($icl_element_type, $selected_language, $default_language);

        /* preWP3 compatibility  - start */
        if(ICL_PRE_WP3 && $icl_element_type == 'tax_category'){
            include ICL_PLUGIN_PATH . '/menu/category-menu.php';
        }else{
        /* preWP3 compatibility  - end */
            include ICL_PLUGIN_PATH . '/menu/taxonomy-menu.php';
        /* preWP3 compatibility  - start */
        }
        /* preWP3 compatibility  - end */

    }

    function wp_dropdown_cats_select_parent($html){
        global $wpdb;
        if(isset($_GET['trid'])){
            $element_type = $taxonomy = isset($_GET['taxonomy']) ? $_GET['taxonomy'] : 'post_tag';
            $icl_element_type = 'tax_' . $element_type;
            $trid = intval($_GET['trid']);
            $source_lang = isset($_GET['source_lang']) ? $_GET['source_lang'] : $this->get_default_language();
            $parent = $wpdb->get_var("
                SELECT parent
                FROM {$wpdb->term_taxonomy} tt
                    JOIN {$wpdb->prefix}icl_translations tr ON tr.element_id=tt.term_taxonomy_id
                        AND tr.element_type='{$icl_element_type}' AND tt.taxonomy='{$taxonomy}'
                WHERE trid='{$trid}' AND tr.language_code='{$source_lang}'
            ");
            if($parent){
                $parent = icl_object_id($parent, $element_type);
                $html = str_replace('value="'.$parent.'"', 'value="'.$parent.'" selected="selected"', $html);
            }
        }
        return $html;
    }

    function add_language_selector_to_page($active_languages, $selected_language, $translations, $element_id, $type) {
        ?>
        <div id="icl_tax_menu" style="display:none">

        <div id="dashboard-widgets" class="metabox-holder">
        <div class="postbox-container" style="width: 99%;line-height:normal;">

        <div id="icl_<?php echo $type ?>_lang" class="postbox" style="line-height:normal;">
            <h3 class="hndle">
                <span><?php echo __('Language', 'sitepress')?></span>
            </h3>
            <div class="inside" style="padding: 10px;">

        <select name="icl_<?php echo $type ?>_language">

            <?php
                foreach($active_languages as $lang){
                    if ($lang['code'] == $selected_language) {
                        ?>
                        <option value="<?php echo $selected_language ?>" selected="selected"><?php echo $lang['display_name'] ?></option>
                        <?php
                    }
                }
            ?>

            <?php foreach($active_languages as $lang):?>
                <?php if($lang['code'] == $selected_language || (isset($translations[$lang['code']]->element_id) && $translations[$lang['code']]->element_id != $element_id)) continue ?>
                    <option value="<?php echo $lang['code'] ?>"<?php if($selected_language==$lang['code']): ?> selected="selected"<?php endif;?>><?php echo $lang['display_name'] ?></option>
            <?php endforeach; ?>
        </select>
        <?php
        }

    
    function get_category_name($id) {
        _deprecated_function( __FUNCTION__, '2.3.1', 'get_cat_name()' );
        global $wpdb;
        $term_id = $wpdb->get_var("SELECT term_id FROM {$wpdb->prefix}term_taxonomy WHERE term_taxonomy_id = {$id}");
        if ($term_id) {
            return $wpdb->get_var("SELECT name FROM {$wpdb->prefix}terms WHERE term_id = {$term_id}");
        } else {
            return null;
        }
    }
    

    function add_translation_of_selector_to_page($trid,
                                                 $selected_language,
                                                 $default_language,
                                                 $source_language,
                                                 $untranslated_ids,
                                                 $element_id,
                                                 $type) {
        global $wpdb;
                
        ?>
        <input type="hidden" name="icl_trid" value="<?php echo $trid ?>" />

        <?php if($selected_language != $default_language && 'all' != $this->get_current_language()): ?>
            <br /><br />
            <?php echo __('This is a translation of', 'sitepress') ?><br />
            <select name="icl_translation_of" id="icl_translation_of"<?php if($_GET['action'] != 'edit' && $trid) echo " disabled"?>>                
                <?php if($source_language == null || $source_language == $default_language): ?>
                    <?php if($trid): ?>
                        <option value="none"><?php echo __('--None--', 'sitepress') ?></option>
                        <?php                            
                            //get source
                            $src_language_id = $wpdb->get_var("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid={$trid} AND language_code='{$default_language}'");
                            if(!$src_language_id) {
                                // select the first id found for this trid
                                $src_language_id = $wpdb->get_var("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid={$trid}");
                            }
                            if($src_language_id && $src_language_id != $element_id) {
                                $term_id = $wpdb->get_var($wpdb->prepare("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $src_language_id));
                                $src_language_title = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->terms} WHERE term_id=%d", $term_id));
                            }
                        ?>
                        <?php if($src_language_title): ?>
                            <option value="<?php echo $src_language_id ?>" selected="selected"><?php echo $src_language_title ?></option>
                        <?php endif; ?>
                    <?php else: ?>
                        <option value="none" selected="selected"><?php echo __('--None--', 'sitepress') ?></option>
                    <?php endif; ?>
                    <?php foreach($untranslated_ids as $translation_of_id):?>
                        <?php if ($translation_of_id != $src_language_id): ?>
                            <?php
                                $title = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->terms} WHERE term_id=%d", 
                                    $wpdb->get_var($wpdb->prepare("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $translation_of_id))));
                            ?>
                            <?php if ($title): ?>
                                <option value="<?php echo $translation_of_id ?>"><?php echo $title ?></option>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php if($trid): ?>
                        <?php
                            // add the source language
                            $src_language_id = $wpdb->get_var("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid={$trid} AND language_code='{$source_language}'");                            
                            if($src_language_id) {
                                $term_id = $wpdb->get_var($wpdb->prepare("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $src_language_id));
                                $src_language_title = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->terms} WHERE term_id=%d", $term_id));
                            }
                        ?>
                        <?php if($src_language_title): ?>
                            <option value="<?php echo $src_language_id ?>" selected="selected"><?php echo $src_language_title ?></option>
                        <?php endif; ?>
                    <?php else: ?>
                        <option value="none" selected="selected"><?php echo __('--None--', 'sitepress') ?></option>
                    <?php endif; ?>
                <?php endif; ?>
            </select>

        <?php endif; ?>


        <?php
    }

    function add_translate_options($trid,
                                   $active_languages,
                                   $selected_language,
                                   $translations,
                                   $type) {
        global $wpdb;
        ?>

        <?php if($trid && isset($_GET['action']) && $_GET['action'] == 'edit'): ?>

            <div id="icl_translate_options">

            <?php
                // count number of translated and un-translated pages.
                $translations_found = 0;
                $untranslated_found = 0;
                foreach($active_languages as $lang) {
                    if($selected_language==$lang['code']) continue;
                    if(isset($translations[$lang['code']]->element_id)) {
                        $translations_found += 1;
                    } else {
                        $untranslated_found += 1;
                    }
                }
            ?>

            <?php if($untranslated_found > 0): ?>
                <p style="clear:both;"><b>Translate</b>
                <table cellspacing="1">
                <?php foreach($active_languages as $lang): if($selected_language==$lang['code']) continue; ?>
                <tr>
                    <?php if(!isset($translations[$lang['code']]->element_id)):?>
                        <td style="padding: 0px;line-height:normal;"><?php echo $lang['display_name'] ?></td>
                        <?php
                            /* preWP3 compatibility  - start */
                            if(ICL_PRE_WP3){
                                if ($type == 'tax_post_tag') {
                                    $add_link = "edit-tags.php?trid=" . $trid . "&amp;lang=" . $lang['code'] . "&amp;source_lang=" . $selected_language;
                                } else {
                                    $add_link = "categories.php?trid=" . $trid . "&amp;lang=" . $lang['code'] . "&amp;source_lang=" . $selected_language;
                                }
                            }else
                            /* preWP3 compatibility  - end */
                            {
                                $taxonomy = $_GET['taxonomy'];
                                $add_link = "edit-tags.php?taxonomy=".esc_html($taxonomy)."&amp;trid=" . $trid . "&amp;lang=" . $lang['code'] . "&amp;source_lang=" . $selected_language;
                            }
                        ?>
                        <td style="padding: 0px;line-height:normal;"><a href="<?php echo $add_link ?>"><?php echo __('add','sitepress') ?></a></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </table>
                </p>
            <?php endif; ?>

            <?php if($translations_found > 0): ?>
            <p style="clear:both;margin:5px 0 5px 0">
                <b><?php _e('Translations', 'sitepress') ?></b> 
                (<a class="icl_toggle_show_translations" href="#" <?php if(empty($this->settings['show_translations_flag'])):?>style="display:none;"<?php endif;?>><?php _e('hide','sitepress')?></a><a class="icl_toggle_show_translations" href="#" <?php if(!empty($this->settings['show_translations_flag'])):?>style="display:none;"<?php endif;?>><?php _e('show','sitepress')?></a>)                
                <table cellspacing="1" width="100%" id="icl_translations_table" style="<?php if(empty($this->settings['show_translations_flag'])):?>display:none;<?php endif;?>margin-left:0;">

                <?php foreach($active_languages as $lang): if($selected_language==$lang['code']) continue; ?>
                <tr>
                    <?php if(isset($translations[$lang['code']]->element_id)):?>
                        <td style="line-height:normal;"><?php echo $lang['display_name'] ?></td>
                        <?php
                            /* preWP3 compatibility  - start */
                            if(ICL_PRE_WP3){
                                if ($type == 'tax_post_tag') {
                                    $edit_link = "edit-tags.php?action=edit&amp;tag_ID=" . $translations[$lang['code']]->term_id . "&amp;lang=" . $lang['code'];
                                } else {
                                    $edit_link = "categories.php?action=edit&amp;cat_ID=" . $translations[$lang['code']]->term_id . "&amp;lang=" . $lang['code'];
                                }
                            }else
                            /* preWP3 compatibility  - end */
                            {
                                $taxonomy = $_GET['taxonomy'];
                                $edit_link = "edit-tags.php?taxonomy=".esc_html($taxonomy)."&amp;action=edit&amp;tag_ID=" . $translations[$lang['code']]->term_id . "&amp;lang=" . $lang['code'];
                            }
                        ?>
                        <td align="right" width="30%" style="line-height:normal;"><?php echo isset($translations[$lang['code']]->name)?'<a href="'.$edit_link.'" title="'.__('Edit','sitepress').'">'.$translations[$lang['code']]->name.'</a>':__('n/a','sitepress') ?></td>

                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </table>



            <?php endif; ?>

            <br clear="all" style="line-height:1px;" />

            </div>
        <?php endif; ?>


        <?php
    }

    function create_term($cat_id, $tt_id){
        global $wpdb, $wp_taxonomies;

        // case of ajax inline category creation
        // ajax actions
        foreach($wp_taxonomies as $ktx=>$tx){
            $ajx_actions[] = 'add-' . $ktx;
        }
        if(isset($_POST['_ajax_nonce']) && in_array($_POST['action'], $ajx_actions)){
            $referer = $_SERVER['HTTP_REFERER'];
            $url_pieces = parse_url($referer);
            @parse_str($url_pieces['query'], $qvars);
            if(!empty($qvars['post'])){
                $post_type = $wpdb->get_var($wpdb->prepare("SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $qvars['post']));
                $lang_details = $this->get_element_language_details($qvars['post'],'post_' . $post_type);
                $term_lang = $lang_details->language_code;
            }else{
                $term_lang = isset($qvars['lang']) ? $qvars['lang'] : $this->get_language_cookie();
            }
        }

        $el_type = $wpdb->get_var("SELECT taxonomy FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id={$tt_id}");

        if(!$this->is_translated_taxonomy($el_type)){
            return;
        };

        $icl_el_type = 'tax_' . $el_type;

        // case of adding a tag via post save
        $post_action = isset($_POST['action']) ? $_POST['action'] : false;
        if($post_action == 'editpost'){
            $term_lang = $_POST['icl_post_language'];
        }elseif($post_action == 'post-quickpress-publish'){
            $term_lang = $this->get_default_language();
        }elseif($post_action == 'inline-save-tax'){
            $lang_details = $this->get_element_language_details($tt_id, $icl_el_type);
            $term_lang = $lang_details->language_code;
        }elseif($post_action == 'inline-save'){
            $post_type = $wpdb->get_var("SELECT post_type FROM {$wpdb->posts} WHERE ID=" . $_POST['post_ID']);
            $lang_details = $this->get_element_language_details($_POST['post_ID'], 'post_' . $post_type);
            $term_lang = $lang_details->language_code;
        }

        // has trid only when it's a translation of another tag
        $trid = isset($_POST['icl_trid']) && (isset($_POST['icl_'.$icl_el_type.'_language'])) ? $_POST['icl_trid']:null;
        // see if we have a "translation of" setting.
        $src_language = false;
        if (isset($_POST['icl_translation_of']) && $_POST['icl_translation_of']) {
            $src_term_id = $_POST['icl_translation_of'];
            if ($src_term_id != 'none') {
                $res = $wpdb->get_row("SELECT trid, language_code
                    FROM {$wpdb->prefix}icl_translations WHERE element_id={$src_term_id} AND element_type='{$icl_el_type}'");
                $trid = $res->trid;
                $src_language = $res->language_code;
            } else {
                $trid = null;
            }
        }

        if(!isset($term_lang)){
            $term_lang = isset($_POST['icl_'.$icl_el_type.'_language']) ? $_POST['icl_'.$icl_el_type.'_language'] : false;        
        }        
        if($post_action == 'inline-save-tax'){
            $trid = $this->get_element_trid($tt_id, $icl_el_type);
        }
        
        $this->set_element_language_details($tt_id, $icl_el_type, $trid, $term_lang, $src_language);
        
        // sync translations parent 
        if($this->settings['sync_taxonomy_parents'] && isset($_POST['parent']) && $term_lang == $this->get_default_language()){
            $parent = intval($_POST['parent']);
            $translations = $this->get_element_translations($trid, $icl_el_type);
            foreach($translations as $lang=> $translation){
                if($lang != $this->get_default_language()){
                    if($parent > 0){
                        $translated_parent = icl_object_id($parent, $el_type, false, $lang);    
                    }
                    if($parent == 0 || $translated_parent != $parent){
                        $wpdb->update($wpdb->term_taxonomy, array('parent'=>$translated_parent), array('term_taxonomy_id'=>$translation->element_id));                        
                    }                    
                }
            }
            delete_option($el_type . '_children');
        }
        
    }

    function get_language_for_term($term_id, $el_type) {
        global $wpdb;
        $term_id = $wpdb->get_var("SELECT term_taxonomy_id FROM {$wpdb->prefix}term_taxonomy WHERE term_id = {$term_id}");
        if ($term_id) {
            return $wpdb->get_var("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = {$term_id} AND element_type = '{$el_type}'");
        } else {
            return $this->get_default_language();
        }

    }

    function pre_term_name($value, $taxonomy){
        //allow adding terms with the same name in different languages
        global $wpdb;
        //check if term exists
        $term_id = $wpdb->get_var("SELECT term_id FROM {$wpdb->terms} WHERE name='".$wpdb->escape($value)."'");
        // translate to WPML notation
        $taxonomy = 'tax_' . $taxonomy;
        if(!empty($term_id)){
            if(isset($_POST['icl_'.$taxonomy.'_language'])) {
                // see if the term_id is for a different language
                $this_lang = $_POST['icl_'.$taxonomy.'_language'];
                if ($this_lang != $this->get_language_for_term($term_id, $taxonomy)) {
                    if ($this_lang != $this->get_default_language()){
                        $value .= ' @'.$_POST['icl_'.$taxonomy.'_language'];
                    }
                }
            }
        }
        return $value;
    }

    function pre_save_category(){
        // allow adding categories with the same name in different languages
        global $wpdb;
        if(isset($_POST['action']) && $_POST['action']=='add-cat'){
            if(category_exists($_POST['cat_name']) && isset($_POST['icl_category_language']) && $_POST['icl_category_language'] != $this->get_default_language()){
                $_POST['cat_name'] .= ' @'.$_POST['icl_category_language'];
            }
        }
    }

    function delete_term($cat, $tt_id, $taxonomy){
        global $wpdb;
        $taxonomy = 'tax_' . $taxonomy;
        $wpdb->query("DELETE FROM {$wpdb->prefix}icl_translations WHERE element_type ='{$taxonomy}' AND element_id='{$tt_id}' LIMIT 1");
    }

    function terms_language_filter(){
        global $wpdb, $pagenow;

        /* preWP3 compatibility  - start */
        if(ICL_PRE_WP3){
            if($pagenow=='categories.php'){
                $taxonomy = 'category';
            }else{
                $taxonomy = 'post_tag';
            }
        }else
        /* preWP3 compatibility  - end */
        {
            $taxonomy = isset($_GET['taxonomy']) ? $_GET['taxonomy'] : 'post_tag';
        }
        $icl_element_type = 'tax_' . $taxonomy;

        $active_languages = $this->get_active_languages();

        $res = $wpdb->get_results("
            SELECT language_code, COUNT(tm.term_id) AS c FROM {$wpdb->prefix}icl_translations t
            JOIN {$wpdb->term_taxonomy} tt ON t.element_id = tt.term_taxonomy_id
            JOIN {$wpdb->terms} tm ON tt.term_id = tm.term_id
            JOIN {$wpdb->prefix}icl_languages l ON t.language_code = l.code
            WHERE t.element_type='{$icl_element_type}' AND tt.taxonomy='{$taxonomy}' AND l.active=1
            GROUP BY language_code
            ");
        $langs = array('all'=>0);
        foreach($res as $r){
            $langs[$r->language_code] = $r->c;
            $langs['all'] += $r->c;
        }
        $active_languages[] = array('code'=>'all','display_name'=>__('All languages','sitepress'));
        foreach($active_languages as $lang){
            if($lang['code']== $this->this_lang){
                $px = '<strong>';
                $sx = ' ('. @intval($langs[$lang['code']]) .')<\/strong>';
            /*
            }elseif(!isset($langs[$lang['code']])){
                $px = '<span>';
                $sx = '<\/span>';
            */
            }else{
                $px = '<a href="?taxonomy='.$taxonomy.'&amp;lang='.$lang['code'];
                $px .= isset($_GET['post_type']) ? '&amp;post_type=' . $_GET['post_type'] : '';
                $px .= '">';
                $sx = '<\/a> ('. @intval($langs[$lang['code']]) .')';
            }
            $as[] =  $px . $lang['display_name'] . $sx;
        }
        $allas = join(' | ', $as);
        ?>
        <script type="text/javascript">
            jQuery('table.widefat').before('<span id="icl_subsubsub"><?php echo $allas ?><\/span>');
        </script>
        <?php
    }

    function get_terms_args_filter($args){
        
        // special case for when term hierarchy is cached in wp_options
        $dbbt = debug_backtrace();
        if(isset($dbbt[4]) && $dbbt[4]['function'] == '_get_term_hierarchy'){
            $args['_icl_show_all_langs'] = true;
        }
        
        return $args;
    }
    
    function exclude_other_terms($exclusions, $args){
        
        // special case for when term hierarchy is cached in wp_options
        if(isset($args['_icl_show_all_langs']) && $args['_icl_show_all_langs']) return $exclusions;
        
        // get_terms doesn't seem to have a filter that can be used efficiently in order to filter the terms by language
        // in addition the taxonomy name is not being passed to this filter we're using 'list_terms_exclusions'
        // getting the taxonomy name from debug_backtrace

        global $wpdb, $pagenow;
        /* preWP3 compatibility  - start */
        if(ICL_PRE_WP3){
            $taxonomy = isset($_GET['taxonomy']) ? $_GET['taxonomy'] : $args['type'];
            if(!$taxonomy) $taxonomy = 'post_tag';
            if(isset($_GET['cat_ID']) && $_GET['cat_ID']){
                $_GET['tag_ID'] = $_GET['cat_ID'];
            }

        }
        /* preWP3 compatibility  - end */
        else{

            if(isset($_GET['taxonomy'])){
                $taxonomy = $_GET['taxonomy'];
            }elseif(isset($args['taxonomy'])){
                $taxonomy = $args['taxonomy'];
            }elseif(isset($_POST['action']) && $_POST['action']=='get-tagcloud'){
                $taxonomy = $_POST['tax'];
            }else{
                if(in_array($pagenow, array('post-new.php','post.php', 'edit.php'))){
                    $dbt = debug_backtrace();
                    $taxonomy = $dbt[3]['args'][0];
                }else{
                    $taxonomy = 'post_tag';
                }
            }
        }

        if(!$this->is_translated_taxonomy($taxonomy)){
            return $exclusions;
        }

        $icl_element_type = 'tax_' . $taxonomy;

        if(isset($_GET['lang']) && $_GET['lang']=='all'){
            return $exclusions;
        }
        if(isset($_GET['tag_ID']) && $_GET['tag_ID']){
            $element_lang_details = $this->get_element_language_details($wpdb->get_var("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id='{$_GET['tag_ID']}' AND taxonomy='{$taxonomy}'"),$icl_element_type);
            $this_lang = $element_lang_details->language_code;
        }elseif($this->this_lang != $this->get_default_language()){
            $this_lang = $this->get_current_language();
        }elseif(isset($_GET['post'])){
            $icl_post_type = isset($_GET['post_type']) ? 'post_' . $_GET['post_type'] : 'post_'. $wpdb->get_var("SELECT post_type FROM {$wpdb->posts} WHERE ID = '".$wpdb->escape($_GET['post'])."'");
            $element_lang_details = $this->get_element_language_details($_GET['post'],$icl_post_type);
            $this_lang = $element_lang_details ? $element_lang_details->language_code : $this->get_default_language();
        }elseif(isset($_POST['action']) && ($_POST['action']=='get-tagcloud' || $_POST['action']=='menu-quick-search')){
            $urlparts = parse_url($_SERVER['HTTP_REFERER']);
            @parse_str($urlparts['query'], $qvars);
            $this_lang = isset($qvars['lang']) ? $qvars['lang'] : $this->get_default_language();
        }else{
            $this_lang = $this->get_default_language();
        }
        $exclude =  $wpdb->get_col("
            SELECT tt.term_taxonomy_id FROM {$wpdb->term_taxonomy} tt
            LEFT JOIN {$wpdb->terms} tm ON tt.term_id = tm.term_id
            LEFT JOIN {$wpdb->prefix}icl_translations t ON (tt.term_taxonomy_id = t.element_id OR t.element_id IS NULL)
            WHERE tt.taxonomy='{$taxonomy}' AND t.element_type='{$icl_element_type}' AND t.language_code <> '{$this_lang}'
            ");
        $exclude[] = 0;
        $exclusions .= ' AND tt.term_taxonomy_id NOT IN ('.join(',',$exclude).')';
        return $exclusions;
    }
    
    function terms_clauses($clauses){
        global $wpdb;

        // special case for when term hierarchy is cached in wp_options
        $dbbt = debug_backtrace();
        if(isset($dbbt[4]) && $dbbt[4]['function'] == '_get_term_hierarchy'){
            return $clauses;
        }
        
        $int = preg_match('#tt\.taxonomy IN \(([^\)]+)\)#', $clauses['where'], $matches);
        $leftjoin = '';
        if($int){
            $exp = explode(',', $matches[1]);
            foreach($exp as $k=>$v){
                $tax = trim($v, ' \'');
                if($this->is_translated_taxonomy($tax)){
                    $icl_taxs[] = 'tax_' . $tax;    
                }else{
                    $leftjoin = ' LEFT';
                }
            } 
        }else{
            // taxonomy type not found
            return $clauses;
        }
        
        if(empty($icl_taxs)) return $clauses;
        
        $icl_taxs = "'" . join( "','" , $icl_taxs) . "'";
        
        $lang = $this->get_current_language();
        if($lang == 'all'){
            $leftjoin = ' LEFT';    
            $where_lang = '';
        }else{
            $where_lang = " AND icl_t.language_code = '{$lang}'";
        }
        
        $clauses['join'] .= "{$leftjoin} JOIN {$wpdb->prefix}icl_translations icl_t ON icl_t.element_id = tt.term_taxonomy_id";
        $clauses['where'] .= "{$where_lang} AND icl_t.element_type IN({$icl_taxs})";
        
        return $clauses;
    }

    function set_wp_query(){
        global $wp_query;
        $this->wp_query = $wp_query;
    }

    // filter for WP home_url function
    function home_url($url, $path, $orig_scheme, $blog_id){
        // exception for get_page_num_link and language_negotiation_type = 3
        if($this->settings['language_negotiation_type'] == 3){
            $db = debug_backtrace();
            if(!empty($db[6]) && $db[6]['function'] == 'get_pagenum_link') return $url;
        }

        // only apply this for home url - not for posts or page permalinks since this filter is called there too
        if(did_action('template_redirect') && rtrim($url,'/') == rtrim(get_option('home'),'/')){
            $url = $this->convert_url($url);
        }
        return $url;
    }

    // converts WP generated url to language specific based on plugin settings
    function convert_url($url, $code=null){
        if(is_null($code)){
            $code = $this->this_lang;
        }

        if($code && $code != $this->get_default_language()){
            $abshome = preg_replace('@\?lang=' . $code . '@i','',get_option('home'));
            switch($this->settings['language_negotiation_type']){
                case '1':
                    if(0 === strpos($url, 'https://')){
                        $abshome = preg_replace('#^http://#', 'https://', $abshome);
                    }
                    if($abshome==$url) $url .= '/';
                    if (0 !== strpos($url, $abshome . '/' . $code . '/')) {
                        // only replace if it is there already
                        $url = str_replace($abshome, $abshome . '/' . $code, $url);
                    }
                    break;
                case '2':
                    $url = str_replace($abshome, $this->settings['language_domains'][$code], $url);
                    break;
                case '3':
                default:
                    // remove any previous value.
                    if (strpos($url, '?lang=' . $code . '&') !== FALSE) {
                        $url = str_replace('?lang=' . $code . '&', '', $url);
                    } elseif (strpos($url, '?lang=' . $code . '/' ) !== FALSE) {
                        $url = str_replace('?lang=' . $code . '/', '', $url);
                    } elseif (strpos($url, '?lang=' . $code ) !== FALSE) {
                        $url = str_replace('?lang=' . $code, '', $url);
                    } elseif (strpos($url, '&lang=' . $code . '/') !== FALSE) {
                        $url = str_replace('&lang=' . $code . '/', '', $url);
                    } elseif (strpos($url, '&lang=' . $code ) !== FALSE) {
                        $url = str_replace('&lang=' . $code, '', $url);
                    }
                    
                    if(false===strpos($url,'?')){
                        $url_glue = '?';
                    }else{

                        // special case post preview link
                        $db = debug_backtrace();
                        if(is_admin() && (@$db[6]['function'] == 'post_preview')){
                           $url_glue = '&';
                        }

                        elseif(isset($_POST['comment']) || defined('ICL_DOING_REDIRECT')){ // will be used for a redirect
                            $url_glue = '&';
                        }else{
                            $url_glue = '&amp;';
                        }
                    }
                    $url .= $url_glue . 'lang=' . $code;
            }
        }
      return $url;
    }

    function language_url($code=null){
        if(is_null($code)) $code = $this->this_lang;
        $abshome = get_option('home');
        if($this->settings['language_negotiation_type'] == 1 || $this->settings['language_negotiation_type'] == 2){
            $url = trailingslashit($this->convert_url($abshome, $code));
        }else{
            $url = $this->convert_url($abshome, $code);
        }
        return $url;
    }

    function permalink_filter($p, $pid){        
        global $wpdb;
        if(is_object($pid)){
            $pid = $pid->ID;
        }
        if($pid == (int)get_option('page_on_front')){
            /* preWP3 compatibility  - start */
            if(ICL_PRE_WP3){
                $p = $this->convert_url($p, $this->get_current_language());
            }
            /* preWP3 compatibility  - end */
            return $p;
        }
        $post_type = $wpdb->get_var("SELECT post_type FROM {$wpdb->posts} WHERE ID={$pid}");
        $element_lang_details = $this->get_element_language_details($pid,'post_'.$post_type);
        if(!empty($element_lang_details) && $element_lang_details->language_code && $this->get_default_language() != $element_lang_details->language_code){
            $p = $this->convert_url($p, $element_lang_details->language_code);
        }elseif(isset($_POST['action']) && $_POST['action']=='sample-permalink'){ // check whether this is an autosaved draft
            $exp = explode('?', $_SERVER["HTTP_REFERER"]);
            if(isset($exp[1])) parse_str($exp[1], $args);
            if(isset($args['lang']) && $this->get_default_language() != $args['lang']){
                $p = $this->convert_url($p, $args['lang']);
            }
        }
        if(is_feed()){
            $p = str_replace("&lang=", "&#038;lang=", $p);
        }
        return $p;
    }

    function category_permalink_filter($p, $cat_id){
        global $wpdb;
        if (isset($this->icl_term_taxonomy_cache)) {
            $term_cat_id = $this->icl_term_taxonomy_cache->get('category_'.$cat_id);
        } else {
            $term_cat_id = null;
        }
        if (!$term_cat_id) {
            $term_cat_id = $wpdb->get_var("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id={$cat_id} AND taxonomy='category'");
            if (isset($this->icl_term_taxonomy_cache)) {
                $this->icl_term_taxonomy_cache->set('category_'.$cat_id, $term_cat_id);
            }
        }
        $cat_id = $term_cat_id;

        $element_lang_details = $this->get_element_language_details($cat_id,'tax_category');
        if($this->get_default_language() != $element_lang_details->language_code){
            $p = $this->convert_url($p, $element_lang_details->language_code);
        }
        return $p;
    }

    function post_type_archive_link_filter($link, $post_type) {
        if (isset($this->settings['custom_posts_sync_option'][$post_type])
                && $this->settings['custom_posts_sync_option'][$post_type]) {
            return $this->convert_url($link);
        }
        return $link;
    }
          
    function tax_permalink_filter($p, $tag){
        global $wpdb;
        if(is_object($tag)){
            $tag_id = $tag->term_taxonomy_id;
            $taxonomy = $tag->taxonomy;
        }else{
            $taxonomy = 'post_tag';
            if (empty($tag_id)) {
                $tag_id = $wpdb->get_var("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id={$tag} AND taxonomy='{$taxonomy}'");
                if (isset($this->icl_term_taxonomy_cache)) {
                    $this->icl_term_taxonomy_cache->set($taxonomy . '_'.$tag, $tag_id);
                }
            }
        }
        $element_lang_details = $this->get_element_language_details($tag_id,'tax_' . $taxonomy);

        if(!empty($element_lang_details) && $this->get_default_language() != $element_lang_details->language_code){
            $p = $this->convert_url($p, $element_lang_details->language_code);
        }
        return $p;
    }

    function get_comment_link_filter($link){
        // decode html characters since they are already encoded in the template for some reason
        $link = html_entity_decode($link);
        return $link;
    }
    
    function attachment_link_filter($link, $id) {
        return $this->convert_url($link);
    }
    
     
    function get_ls_languages($template_args=array()){
            global $wpdb, $post, $cat, $tag_id, $w_this_lang;

            if(is_null($this->wp_query)) $this->set_wp_query();

             // use original wp_query for this
             // backup current $wp_query
             global $wp_query;
             $_wp_query_back = clone $wp_query;
             $wp_query = $this->wp_query;


            $w_active_languages = $this->get_active_languages();
                                    
            $this_lang = $this->this_lang;
            if($this_lang=='all'){
                $w_this_lang = array(
                    'code'=>'all',
                    'english_name' => 'All languages',
                    'display_name' => __('All languages', 'sitepress')
                );
            }else{
                $w_this_lang = $this->get_language_details($this_lang);
            }

            if(isset($template_args['skip_missing'])){
                //override default setting
                $icl_lso_link_empty = !$template_args['skip_missing'];
            }else{
                $icl_lso_link_empty = $this->settings['icl_lso_link_empty'];
            }

            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            if(preg_match('#MSIE ([0-9]+)\.[0-9]#',$user_agent,$matches)){
                $ie_ver = $matches[1];
            }
            
            if(is_attachment()){  // Exception for attachments. Not translated.          
                $translations[$this->get_current_language()] = (object) array(
                    'translation_id' => 0,
                    'language_code' => $this->get_default_language(),
                    'element_id' => 0,
                    'original' => 1,
                    'post_title' => $this->wp_query->post->post_title,
                    'post_status' => $this->wp_query->post->post_status
                );
            }elseif(is_singular() && !empty($wp_query->posts)){
                $trid = $wpdb->get_var("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id='{$this->wp_query->post->ID}' AND element_type LIKE 'post\\_%'");
                $translations = $this->get_element_translations($trid,'post_'.$wp_query->posts[0]->post_type);
            }elseif(is_category() && !empty($wp_query->posts)){
                $cat_id = $wpdb->get_var("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id={$cat} AND taxonomy='category'");
                $trid = $wpdb->get_var("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id='{$cat_id}' AND element_type='tax_category'");                                
                $skip_empty = true;
                $translations = $this->get_element_translations($trid,'tax_category', $skip_empty);                                
            }elseif(is_tag() && !empty($wp_query->posts)){                
                $tag_id = $wpdb->get_var("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id={$tag_id} AND taxonomy='post_tag'");                
                $trid = $wpdb->get_var("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id='{$tag_id}' AND element_type='tax_post_tag'");                                
                $skip_empty = true;
                $translations = $this->get_element_translations($trid,'tax_post_tag', $skip_empty);                             
            }elseif(is_tax()){                
                $tax_id = $wpdb->get_var("SELECT term_taxonomy_id 
                    FROM {$wpdb->term_taxonomy} WHERE term_id='".$wp_query->get_queried_object_id()."' AND taxonomy='".$wpdb->escape(get_query_var('taxonomy'))."'");                
                $trid = $this->get_element_trid($tax_id, 'tax_' . get_query_var('taxonomy'));
                $translations = $this->get_element_translations($trid,'tax_' . get_query_var('taxonomy'), $skip_empty);
            }elseif(is_archive() && !empty($wp_query->posts)){
                $translations = array();
            }elseif( 'page' == get_option('show_on_front') && (isset($this->wp_query->queried_object_id) && $this->wp_query->queried_object_id == get_option('page_on_front') || (isset($this->wp_query->queried_object_id) && $this->wp_query->queried_object_id == get_option('page_for_posts'))) ){
                $trid = $wpdb->get_var("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id='{$this->wp_query->queried_object_id}' AND element_type='post_page'");
                $translations = $this->get_element_translations($trid,'post_page');
            }else{
                $wp_query->is_singular = false;
                $wp_query->is_archive = false;
                $wp_query->is_category = false;
                $wp_query->is_404 = true;
            }
                                       
            foreach($w_active_languages as $k=>$lang){                
                $skip_lang = false;
                if(is_singular() || (!empty($this->wp_query->queried_object_id) && $this->wp_query->queried_object_id == get_option('page_for_posts'))){
                    $this_lang_tmp = $this->this_lang;
                    $this->this_lang = $lang['code'];
                    $lang_page_on_front = get_option('page_on_front');
                    $lang_page_for_posts = get_option('page_for_posts');
                    $this->this_lang = $this_lang_tmp;
                    if ( 'page' == get_option('show_on_front') && !empty($translations[$lang['code']]) && $translations[$lang['code']]->element_id == $lang_page_on_front ){
                        $lang['translated_url'] = $this->language_url($lang['code']);
                    }elseif('page' == get_option('show_on_front') && !empty($translations[$lang['code']]) && $translations[$lang['code']]->element_id && $translations[$lang['code']]->element_id == $lang_page_for_posts){
                        if($lang_page_for_posts){
                            $lang['translated_url'] = get_permalink($lang_page_for_posts);
                        }else{
                            $lang['translated_url'] = $this->language_url($lang['code']);
                        }
                    }else{
                        if(!empty($translations[$lang['code']]) && isset($translations[$lang['code']]->post_title)){
                            $lang['translated_url'] = get_permalink($translations[$lang['code']]->element_id);                            
                            $lang['missing'] = 0;
                        }else{                            
                            if($icl_lso_link_empty){                                
                                if(!empty($template_args['link_empty_to'])){
                                    $lang['translated_url'] = str_replace('{%lang}', $lang['code'], $template_args['link_empty_to']);
                                }else{
                                    $lang['translated_url'] = $this->language_url($lang['code']);    
                                }
                                
                            }else{
                                $skip_lang = true;
                            }
                            $lang['missing'] = 1;
                        }
                    }
                }elseif(is_tax()){
                    if(isset($translations[$lang['code']])){
                        global $icl_adjust_id_url_filter_off;  // force  the category_link_adjust_id to not modify this
                        $icl_adjust_id_url_filter_off = true;

                        $lang['translated_url'] = get_term_link((int)$translations[$lang['code']]->term_id, get_query_var('taxonomy'));

                        $icl_adjust_id_url_filter_off = false; // restore default bahavior
                        $lang['missing'] = 0;
                    }else{
                        if($icl_lso_link_empty){
                            if(!empty($template_args['link_empty_to'])){
                                $lang['translated_url'] = str_replace('{%lang}', $lang['code'], $template_args['link_empty_to']);
                            }else{
                                $lang['translated_url'] = $this->language_url($lang['code']);    
                            }
                        }else{
                            $skip_lang = true;
                        }
                        $lang['missing'] = 1;
                    }
                }elseif(is_category()){
                    if(isset($translations[$lang['code']])){
                        global $icl_adjust_id_url_filter_off;  // force  the category_link_adjust_id to not modify this
                        $icl_adjust_id_url_filter_off = true;
                        
                        $lang['translated_url'] = get_category_link($translations[$lang['code']]->term_id);

                        $icl_adjust_id_url_filter_off = false; // restore default bahavior
                        $lang['missing'] = 0;
                    }else{
                        if($icl_lso_link_empty){
                            if(!empty($template_args['link_empty_to'])){
                                $lang['translated_url'] = str_replace('{%lang}', $lang['code'], $template_args['link_empty_to']);
                            }else{
                                $lang['translated_url'] = $this->language_url($lang['code']);    
                            }
                        }else{
                            $skip_lang = true;
                        }
                        $lang['missing'] = 1;
                    }
                }elseif(is_tag()){                      
                    if(isset($translations[$lang['code']])){
                        global $icl_adjust_id_url_filter_off;  // force  the category_link_adjust_id to not modify this
                        $icl_adjust_id_url_filter_off = true;

                        $lang['translated_url'] = get_tag_link($translations[$lang['code']]->term_id);                        

                        $icl_adjust_id_url_filter_off = false; // restore default bahavior
                        $lang['missing'] = 0;
                    }else{
                        if($icl_lso_link_empty){
                            if(!empty($template_args['link_empty_to'])){
                                $lang['translated_url'] = str_replace('{%lang}', $lang['code'], $template_args['link_empty_to']);
                            }else{
                                $lang['translated_url'] = $this->language_url($lang['code']);    
                            }
                        }else{
                            $skip_lang = true;
                        }
                        $lang['missing'] = 1;
                    }
                }elseif(is_author()){
                    global $authordata, $wp_query;
                    if(empty($authordata)){
                        $authordata = get_userdata(get_query_var('author'));
                    }
                    $post_type = get_query_var('post_type') ? get_query_var('post_type') : 'post';
                    if($wpdb->get_var("SELECT COUNT(p.ID) FROM {$wpdb->posts} p
                    JOIN {$wpdb->prefix}icl_translations t ON p.ID=t.element_id AND t.element_type = 'post_{$post_type}'
                    WHERE p.post_author='{$authordata->ID}' AND post_type='{$post_type}' AND post_status='publish' AND language_code='{$lang['code']}'")
                    ){
                        remove_filter('home_url', array($this,'home_url'), 1, 4);
                        remove_filter('author_link', array($this,'author_link'));
                        $author_url = get_author_posts_url($authordata->ID);
                        add_filter('home_url', array($this,'home_url'), 1, 4);
                        add_filter('author_link', array($this,'author_link'));
                        $lang['translated_url'] = $this->convert_url($author_url, $lang['code']);
                        $lang['missing'] = 0;
                    }else{
                        if($icl_lso_link_empty){
                            if(!empty($template_args['link_empty_to'])){
                                $lang['translated_url'] = str_replace('{%lang}', $lang['code'], $template_args['link_empty_to']);
                            }else{
                                $lang['translated_url'] = $this->language_url($lang['code']);    
                            }
                        }else{
                            $skip_lang = true;
                        }
                        $lang['missing'] = 1;
                    }
                }elseif(is_archive() && !is_tag()){
                    global $icl_archive_url_filter_off;
                    $icl_archive_url_filter_off = true;
                    if($this->wp_query->is_year){
                        if(isset($this->wp_query->query_vars['m']) && !$this->wp_query->query_vars['year'] ){
                            $this->wp_query->query_vars['year'] = substr($this->wp_query->query_vars['m'], 0, 4);
                        }
                        $lang['translated_url'] = $this->archive_url(get_year_link( $this->wp_query->query_vars['year'] ), $lang['code']);
                    }elseif($this->wp_query->is_month){
                        if(isset($this->wp_query->query_vars['m']) && !$this->wp_query->query_vars['year'] ){
                            $this->wp_query->query_vars['year'] = substr($this->wp_query->query_vars['m'], 0, 4);
                            $this->wp_query->query_vars['monthnum'] = substr($this->wp_query->query_vars['m'], 4, 2);
                        }
                        $lang['translated_url'] = $this->archive_url(get_month_link( $this->wp_query->query_vars['year'], $this->wp_query->query_vars['monthnum'] ), $lang['code']);
                    }elseif($this->wp_query->is_day){
                        if(isset($this->wp_query->query_vars['m']) && !$this->wp_query->query_vars['year'] ){
                            $this->wp_query->query_vars['year'] = substr($this->wp_query->query_vars['m'], 0, 4);
                            $this->wp_query->query_vars['monthnum'] = substr($this->wp_query->query_vars['m'], 4, 2);
                            $this->wp_query->query_vars['day'] = substr($this->wp_query->query_vars['m'], 6, 2);
                            gmdate('Y', current_time('timestamp')); //force wp_timezone_override_offset to be called
                        }
                        $lang['translated_url'] = $this->archive_url(get_day_link( $this->wp_query->query_vars['year'], $this->wp_query->query_vars['monthnum'], $this->wp_query->query_vars['day'] ), $lang['code']);
                    } else if (isset($this->wp_query->query_vars['post_type'])) {
                        if (isset($this->settings['custom_posts_sync_option'][$this->wp_query->query_vars['post_type']])
                                && $this->settings['custom_posts_sync_option'][$this->wp_query->query_vars['post_type']]
                                && function_exists('get_post_type_archive_link')) {
                            remove_filter('post_type_archive_link', array($this,'post_type_archive_link_filter'), 10);
                            $lang['translated_url'] = $this->convert_url(get_post_type_archive_link($this->wp_query->query_vars['post_type']), $lang['code']);
                        } else {
                            $lang['translated_url'] = $this->language_url($lang['code']);
                        }
                    }
                    add_filter('post_type_archive_link', array($this,'post_type_archive_link_filter'), 10, 2);
                    $icl_archive_url_filter_off = false;
                }elseif(is_search()){
                    $url_glue = strpos($this->language_url($lang['code']),'?')===false ? '?' : '&';
                    $lang['translated_url'] = $this->language_url($lang['code']) . $url_glue . 's=' . htmlspecialchars($_GET['s']);
                }else{
                    global $icl_language_switcher_preview;
                    if($icl_lso_link_empty || is_home() || is_404()
                        || ('page' == get_option('show_on_front') && ($this->wp_query->queried_object_id == get_option('page_on_front')
                        || $this->wp_query->queried_object_id == get_option('page_for_posts')))
                        || $icl_language_switcher_preview){
                        $lang['translated_url'] = $this->language_url($lang['code']);
                        $skip_lang = false;
                    }else{
                        $skip_lang = true;
                        unset($w_active_languages[$k]);
                    }
                }
                if(!$skip_lang){
                    $w_active_languages[$k] = $lang;
                }else{
                    unset($w_active_languages[$k]);
                }
            }
            
            foreach($w_active_languages as $k=>$v){
                $lang_code = $w_active_languages[$k]['language_code'] = $w_active_languages[$k]['code'];
                unset($w_active_languages[$k]['code']);

                $native_name = $this->get_display_language_name($lang_code, $lang_code);
                if(!$native_name) $native_name = $w_active_languages[$k]['english_name'];
                $w_active_languages[$k]['native_name'] = $native_name;
                unset($w_active_languages[$k]['english_name']);


                $translated_name = $this->get_display_language_name($lang_code, $this->get_current_language());
                if(!$translated_name) $translated_name = $w_active_languages[$k]['english_name'];
                $w_active_languages[$k]['translated_name'] = $translated_name;
                unset($w_active_languages[$k]['display_name']);

                $w_active_languages[$k]['url'] = $w_active_languages[$k]['translated_url'];
                unset($w_active_languages[$k]['translated_url']);

                $flag = $this->get_flag($lang_code);

                if($flag->from_template){
                    $wp_upload_dir = wp_upload_dir();
                    $flag_url = $wp_upload_dir['baseurl'] . '/flags/' . $flag->flag;
                }else{
                    $flag_url = ICL_PLUGIN_URL . '/res/flags/'.$flag->flag;
                }
                $w_active_languages[$k]['country_flag_url'] = $flag_url;
                                                                               
                $w_active_languages[$k]['active'] = $this->get_current_language()==$lang_code?'1':0;;
            }


            // restore current $wp_query
            $wp_query = clone $_wp_query_back;
            unset($_wp_query_back);

            $w_active_languages = apply_filters('icl_ls_languages', $w_active_languages);

            $w_active_languages = $this->sort_ls_languages($w_active_languages, $template_args);            
            
            return $w_active_languages;

    }

    function sort_ls_languages($w_active_languages, $template_args) {
        // sort languages according to parameters
        if(isset($template_args['orderby'])){
            if(isset($template_args['order'])){
                $order = $template_args['order'];
            }else{
                $order = 'asc';
            }
            $comp = $order == 'asc' ? '>' : '<';
            switch($template_args['orderby']){
                case 'id':
                    uasort($w_active_languages, create_function('$a,$b','return $a[\'id\'] '.$comp.' $b[\'id\'];'));
                    break;
                case 'code':
                    ksort($w_active_languages);
                    if($order == 'desc'){
                        $w_active_languages = array_reverse($w_active_languages);
                    }
                    break;
                case 'name':
                default:
                    uasort($w_active_languages, create_function('$a,$b','return $a[\'translated_name\'] '.$comp.' $b[\'translated_name\'];')); 
            }                
        }                    
    
        return $w_active_languages;        
        
    }
    function get_display_language_name($lang_code, $display_code) {
        global $wpdb;
        if (isset($this->icl_language_name_cache)) {
            $translated_name = $this->icl_language_name_cache->get($lang_code.$display_code);
        } else {
            $translated_name = null;
        }
        if (!$translated_name) {
            $translated_name = $wpdb->get_var("SELECT name FROM {$wpdb->prefix}icl_languages_translations WHERE language_code='{$lang_code}' AND display_language_code='{$display_code}'");
            if (isset($this->icl_language_name_cache)) {
                $this->icl_language_name_cache->set($lang_code.$display_code, $translated_name);
            }
        }
        return $translated_name;
    }

    function get_flag($lang_code){
        global $wpdb;

        if (isset($this->icl_flag_cache)) {
            $flag = $this->icl_flag_cache->get($lang_code);
        } else {
            $flag = null;
        }
        if (!$flag) {
            $flag = $wpdb->get_row("SELECT flag, from_template FROM {$wpdb->prefix}icl_flags WHERE lang_code='{$lang_code}'");
            if (isset($this->icl_flag_cache)) {
                $this->icl_flag_cache->set($lang_code, $flag);
            }
        }

        return $flag;
    }

    function get_flag_url($code){
        $flag = $this->get_flag($code);
        if($flag->from_template){
            $flag_url = get_bloginfo('template_directory') . '/images/flags/'.$flag->flag;
        }else{
            $flag_url = ICL_PLUGIN_URL . '/res/flags/'.$flag->flag;
        }

        return $flag_url;
    }

    function language_selector(){
        if(is_single()){
            $post_type = get_query_var('post_type');
            if(!$post_type) $post_type = 'post';
            if(!$this->is_translated_post_type($post_type)){
                return;
            }
        }elseif(is_tax()){
            $tax = get_query_var('taxonomy');
            if(!$this->is_translated_taxonomy($tax)){
                return;
            }
        }

        global $icl_language_switcher_preview;
        if ($this->settings['icl_lang_sel_type'] == 'list' || $icl_language_switcher_preview){
            global $icl_language_switcher;
            $icl_language_switcher->widget_list();
            if (!$icl_language_switcher_preview) return '';
        }

        $active_languages = $this->get_ls_languages();
        foreach($active_languages as $k=>$al){
            if($al['active']==1){
                $main_language = $al;
                unset($active_languages[$k]);
                break;
            }
        }
        include ICL_PLUGIN_PATH . '/menu/language-selector.php';
    }

    function have_icl_translator($source, $target){
        // returns true if we have ICL translators for the language pair
        if (isset($this->settings['icl_lang_status'])){
            foreach($this->settings['icl_lang_status'] as $lang) {
                if ($lang['from'] == $source && $lang['to'] == $target) {
                    return $lang['have_translators'];
                }
            }

        }

        return false;
    }

    function get_default_categories(){
        $default_categories_all = $this->settings['default_categories'];

        foreach($this->active_languages as $l) $alcodes[] = $l['code'];
        foreach($default_categories_all as $c=>$v){
            if(in_array($c, $alcodes)){
                $default_categories[$c] = $v;
            }
        }

        return $default_categories;
    }

    function set_default_categories($def_cat){
        $this->settings['default_categories'] = $def_cat;
        $this->save_settings();
    }

    function pre_option_default_category($setting){
        global $wpdb;
        if(isset($_POST['icl_post_language']) && $_POST['icl_post_language'] || (isset($_GET['lang']) && $_GET['lang']!='all')){
            $lang = isset($_POST['icl_post_language'])  && $_POST['icl_post_language']?$_POST['icl_post_language']:$_GET['lang'];
            $ttid = intval($this->settings['default_categories'][$lang]);
            return $tid = $wpdb->get_var("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id={$ttid} AND taxonomy='category'");
        }
        return false;
    }

    function update_option_default_category($oldvalue, $newvalue){
        $newvalue = $wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy='category' AND term_id=%d", $newvalue));
        $translations = $this->get_element_translations($this->get_element_trid($newvalue, 'tax_category'));
        if(!empty($translations)){
            foreach($translations as $t){
                $icl_settings['default_categories'][$t->language_code] = $t->element_id;
            }
            $this->save_settings($icl_settings);
        }
    }

    function the_category_name_filter($name){
        if(is_array($name)){
            foreach($name as $k=>$v){
                $name[$k] = $this->the_category_name_filter($v);
            }
            return $name;
        }
        if(false === strpos($name, '@')) return $name;
        if(false !== strpos($name, '<a')){
            $int = preg_match_all('|<a([^>]+)>([^<]+)</a>|i',$name,$matches);
            if($int && count($matches[0]) > 1){
                $originals = $filtered = array();
                foreach($matches[0] as $m){
                    $originals[] = $m;
                    $filtered[] = $this->the_category_name_filter($m);
                }
                $name = str_replace($originals, $filtered, $name);
            }else{
                $name_sh = strip_tags($name);
                $exp = explode('@', $name_sh);
                $name = str_replace($name_sh, trim($exp[0]),$name);
            }
        }else{
            $name = preg_replace('#(.*) @(.*)#i','$1',$name);
        }
        return $name;
    }

    function get_terms_filter($terms){
        foreach($terms as $k=>$v){
            if(isset($terms[$k]->name)) $terms[$k]->name = $this->the_category_name_filter($terms[$k]->name);
        }
        return $terms;
    }

    function get_term_adjust_id($term){  
        global $icl_adjust_id_url_filter_off, $wpdb;
        if($icl_adjust_id_url_filter_off) return $term; // special cases when we need the category in a different language

        // exception: don't filter when called from get_permalink. When category parents are determined
        $db = debug_backtrace();        
        if($db[5]['function'] == 'get_category_parents' || $db[6]['function'] == 'get_permalink'){
            return $term;    
        }
        
        $translated_id = icl_object_id($term->term_id, $term->taxonomy, true);
        if($translated_id != $term->term_id){
            //$translated_id = $wpdb->get_var("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id='{$translated_id}'");
            remove_filter('get_term', array($this,'get_term_adjust_id'), 1);
            $t_term = get_term($translated_id, $term->taxonomy);
            if(!is_wp_error($t_term)){
                $term = $t_term;
            }
            add_filter('get_term', array($this,'get_term_adjust_id'), 1, 1);
        }

        return $term;
    }

    function wp_list_pages_adjust_ids($out, $args){
        static $__run_once = false; // only run for calls that have 'include' as an argument. ant only run once.
        if($args['include'] && !$__run_once && $this->get_current_language() != $this->get_default_language()){
            $__run_once = true;
            $include = array_map('trim', explode(',', $args['include']));
            $tr_include = array();
            foreach($include as $i){
                $t = icl_object_id($i, 'page',true);
                if($t){
                    $tr_include[] = $t;
                }
            }
            $args['include'] = join(',',$tr_include);
            $out = wp_list_pages($args);
        }
        return $out;
    }

    function get_terms_adjust_ids($terms, $taxonomies, $args){
        static $__run_once = false; // only run for calls that have 'include' as an argument. ant only run once.
        if($args['include'] && !$__run_once && $this->get_current_language() != $this->get_default_language()){
            $__run_once = true;
            if(is_array($args['include'])){
                $include = $args['include'];
            }else{
                $include = array_map('trim', explode(',', $args['include']));
            }
            $tr_include = array();
            foreach($include as $i){
                $t = icl_object_id($i, $taxonomies[0],true);
                if($t){
                    $tr_include[] = $t;
                }
            }
            $args['include'] = join(',',$tr_include);
            $terms = get_terms($taxonomies, $args);
        }
        return $terms;
    }

    function get_pages_adjust_ids($pages, $args){
        static $__run_once = false; // only run for calls that have 'include' as an argument. ant only run once.
        if(!$__run_once && $this->get_current_language() != $this->get_default_language()){
            $__run_once = true;
            $args_updated = false;
            if($args['include']){
                $include = array_map('trim', explode(',', $args['include']));
                $tr_include = array();
                foreach($include as $i){
                    $t = icl_object_id($i, 'page', true);
                    if($t){
                        $tr_include[] = $t;
                    }
                }
                $args['include'] = join(',',$tr_include);
                $args_updated = true;
            }
            if($args['child_of']){
                $args['child_of'] = icl_object_id($args['child_of'], 'page', true);
                $args_updated = true;
            }
            if($args_updated){
                $pages = get_pages($args);
            }
        }
        return $pages;
    }

    function category_link_adjust_id($catlink, $cat_id){
        global $icl_adjust_id_url_filter_off, $wpdb;
        if($icl_adjust_id_url_filter_off) return $catlink; // special cases when we need the categiry in a different language

        $translated_id = icl_object_id($cat_id, 'category', true);
        if($translated_id && $translated_id != $cat_id){
            $translated_id = $wpdb->get_var("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id='{$translated_id}'");
            remove_filter('category_link', array($this,'category_link_adjust_id'), 1);
            $catlink = get_category_link($translated_id, 'category');
            add_filter('category_link', array($this,'category_link_adjust_id'), 1, 2);
        }
        return $catlink;
    }

    // adjacent posts links
    function get_adjacent_post_join($join){
        global $wpdb;
        $post_type = get_query_var('post_type');
        if(!$post_type) $post_type = 'post';
        if($this->is_translated_post_type($post_type)){
            $join .= " JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID AND t.element_type = 'post_{$post_type}'";    
        }
        return $join;
    }

    function get_adjacent_post_where($where){
        global $wpdb;
        $post_type = get_query_var('post_type');
        if(!$post_type) $post_type = 'post';
        if($this->is_translated_post_type($post_type)){
            $where .= " AND language_code = '{$wpdb->escape($this->this_lang)}'";
        }
        return $where;
    }

    // feeds links
    function feed_link($out){
        return $this->convert_url($out);
    }

    // commenting links
    function post_comments_feed_link($out){
        if($this->settings['language_negotiation_type']==3){
            $out = preg_replace('@(\?|&)lang=([^/]+)/feed/@i','feed/$1lang=$2',$out);
        }
        return $out;
        //return $this->convert_url($out);
    }

    function trackback_url($out){
        return $this->convert_url($out);
    }

    function user_trailingslashit($string, $type_of_url){
        // fixes comment link for when the comments list pagination is enabled
        if($type_of_url=='comment'){
            $string = preg_replace('@(.*)/\?lang=([a-z-]+)/(.*)@is','$1/$3?lang=$2', $string);
        }
        return $string;
    }

    // archives links
    function getarchives_join($join){
        global $wpdb;
        $join .= " JOIN {$wpdb->prefix}icl_translations t ON t.element_id = {$wpdb->posts}.ID AND t.element_type='post_post'";
        return $join;
    }

    function getarchives_where($where){
        global $wpdb;
        $where .= " AND language_code = '{$wpdb->escape($this->this_lang)}'";
        return $where;
    }

    function archives_link($out){
        global $icl_archive_url_filter_off;
        if(!$icl_archive_url_filter_off){
            $out = $this->archive_url($out, $this->this_lang);
        }
        $icl_archive_url_filter_off = false;
        return $out;
    }

    function archive_url($url, $lang){
        $url = $this->convert_url($url, $lang);
        return $url;
    }

    function author_link($url){
        $url = $this->convert_url($url);
        return preg_replace('#^http://(.+)//(.+)$#','http://$1/$2', $url);
    }

    // Navigation
    //function get_pagenum_link_filter($url){
        // fix pagenum links for when using the language as a parameter
        // remove language query string appended by WP
        // WPML adds the language parameter after the url is built
    //    $url = str_replace(get_option('home') . '?lang=' . $this->get_current_language(), get_option('home'), $url);
    //    return $url;
    //}

    function pre_option_home(){

        if(!defined('TEMPLATEPATH')) return false;

        $dbbt = debug_backtrace();

        static $inc_methods = array('include','include_once','require','require_once');
        if(isset($dbbt['4']) && $dbbt['4']['function']=='get_bloginfo' && isset($dbbt['5']) && $dbbt['5']['function']=='bloginfo'){  // case of bloginfo
            $is_template_file = false !== strpos($dbbt[5]['file'], realpath(TEMPLATEPATH));
            $is_direct_call   = in_array($dbbt[6]['function'], $inc_methods) || (false !== strpos($dbbt[6]['file'], realpath(TEMPLATEPATH)));
        }elseif(isset($dbbt['4']) && $dbbt['4']['function']=='get_bloginfo'){  // case of get_bloginfo
            $is_template_file = false !== strpos($dbbt[4]['file'], realpath(TEMPLATEPATH));
            $is_direct_call   = in_array($dbbt[5]['function'], $inc_methods) || (false !== strpos($dbbt[5]['file'], realpath(TEMPLATEPATH)));
        }elseif(isset($dbbt['4']) && $dbbt['4']['function']=='get_settings'){  // case of get_settings
            $is_template_file = false !== strpos($dbbt[4]['file'], realpath(TEMPLATEPATH));
            $is_direct_call   = in_array($dbbt[5]['function'], $inc_methods) || (false !== strpos($dbbt[5]['file'], realpath(TEMPLATEPATH)));
        }else{ // case of get_option
            $is_template_file = isset($dbbt[3]['file']) && (false !== strpos($dbbt[3]['file'], realpath(TEMPLATEPATH)));
            $is_direct_call   = isset($dbbt['4']) && in_array($dbbt[4]['function'], $inc_methods) || (isset($dbbt[4]['file']) && false !== strpos($dbbt[4]['file'], realpath(TEMPLATEPATH)));
        }

        //if($dbbt[3]['file'] == @realpath(TEMPLATEPATH . '/header.php')){
        if($is_template_file && $is_direct_call){
            $ret = $this->language_url($this->this_lang);
        }else{
            $ret = false;
        }
        return $ret;
    }

    function query_vars($public_query_vars){
        $public_query_vars[] = 'lang';
        global $wp_query;
        $wp_query->query_vars['lang'] = $this->this_lang;
        return $public_query_vars;
    }

    function parse_query($q){
        global $wp_query, $wpdb;
        //if($q == $wp_query) return; // not touching the WP query
        if(is_admin()) return;

        if($this->get_current_language() != $this->get_default_language()) {
            $cat_array = array();
            // cat
            if(isset($q->query_vars['cat']) && !empty($q->query_vars['cat'])){
                $cat_array = array_map('trim', explode(',', $q->query_vars['cat']));
            }

            // category_name
            if(isset($q->query_vars['category_name']) && !empty($q->query_vars['category_name'])){
                $cat = get_term_by( 'slug', preg_replace('#((.*)/)#','',$q->query_vars['category_name']), 'category' );
                if(!$cat){
                    $cat = get_term_by( 'name', $q->query_vars['category_name'], 'category' );
                }
                if($cat_id = $cat->term_id){
                    $cat_array = array($cat_id);
                }else{
                    $q->query_vars['p'] = -1;
                }
            }

            // category_and
            if(isset($q->query_vars['category__and']) && !empty($q->query_vars['category__and'])){
                $cat_array = $q->query_vars['category__and'];
            }
            // category_in
            if(isset($q->query_vars['category__in']) && !empty($q->query_vars['category__in'])){
                $cat_array = $q->query_vars['category__in'];
            }
            // category__not_in
            if(isset($q->query_vars['category__not_in']) && !empty($q->query_vars['category__not_in'])){
                $cat_array = $q->query_vars['category__not_in'];
                $cat_array = array_map(create_function('$a', 'if (intval($a) > 0 ) return -1*intval($a); else return $a;'), $cat_array);
            }
            
            if(!empty($cat_array)){
                $translated_ids = array();
                foreach($cat_array as $c){
                    if(intval($c) < 0){
                        $sign = -1;
                    }else{
                        $sign = 1;
                    }
                    $translated_ids[] = $sign * intval(icl_object_id(abs($c), 'category', true));
                }
                
                //cat
                if(isset($q->query_vars['cat']) && !empty($q->query_vars['cat'])){
                    $q->query_vars['cat'] = join(',', $translated_ids);
                }
                
                // category_name
                if(isset($q->query_vars['category_name']) && !empty($q->query_vars['category_name'])){
                    $_ctmp = get_term_by('id', $translated_ids[0], 'category');
                    $q->query_vars['category_name'] = $_ctmp->slug;
                }
                // category__and
                if(isset($q->query_vars['category__and']) && !empty($q->query_vars['category__and'])){
                    $q->query_vars['category__and'] = $translated_ids;
                }
                // category__in
                if(isset($q->query_vars['category__in']) && !empty($q->query_vars['category__in'])){
                    $q->query_vars['category__in'] = $translated_ids;
                }
                // category__not_in
                if(isset($q->query_vars['category__not_in']) && !empty($q->query_vars['category__not_in'])){
                    $q->query_vars['category__not_in'] = array_map('abs', $translated_ids);                    
                }
                
            }
            
            // TAGS
            $tag_array = array();
            // tag
            if(isset($q->query_vars['tag']) && !empty($q->query_vars['tag'])){
                if(false !== strpos($q->query_vars['tag'],' ')){
                    $tag_glue = '+';
                    $exp = explode(' ', $q->query_vars['tag']);
                }else{
                    $tag_glue = ',';
                    $exp = explode(',', $q->query_vars['tag']);
                }
                $tag_ids = array();
                foreach($exp as $e){
                    $tag_array[] = $wpdb->get_var($wpdb->prepare( "SELECT x.term_id FROM $wpdb->terms t
                        JOIN $wpdb->term_taxonomy x ON t.term_id=x.term_id WHERE x.taxonomy='post_tag' AND t.slug='%s'", $wpdb->escape($e)));
                }
                $_tmp = array_unique($tag_array);
                if(count($_tmp) == 1 && empty($_tmp[0])){
                    $tag_array = array();
                }
            }
            // tag_id
            if(isset($q->query_vars['tag_id']) && !empty($q->query_vars['tag_id'])){
                $tag_array = array_map('trim', explode(',', $q->query_vars['tag_id']));
            }

            // tag__and
            if(isset($q->query_vars['tag__and']) && !empty($q->query_vars['tag__and'])){
                $tag_array = $q->query_vars['tag__and'];
            }
            // tag__in
            if(isset($q->query_vars['tag__in']) && !empty($q->query_vars['tag__in'])){
                $tag_array = $q->query_vars['tag__in'];
            }
            // tag__not_in
            if(isset($q->query_vars['tag__not_in']) && !empty($q->query_vars['tag__not_in'])){
                $tag_array = $q->query_vars['tag__not_in'];
            }
            // tag_slug__in
            if(isset($q->query_vars['tag_slug__in']) && !empty($q->query_vars['tag_slug__in'])){
                foreach($q->query_vars['tag_slug__in'] as $t){
                    $tag_array[] = $wpdb->get_var($wpdb->prepare( "SELECT x.term_id FROM $wpdb->terms t
                        JOIN $wpdb->term_taxonomy x ON t.term_id=x.term_id WHERE x.taxonomy='post_tag' AND t.slug='%s'", $wpdb->escape($t)));
                }
            }
            // tag_slug__and
            if(isset($q->query_vars['tag_slug__and']) && !empty($q->query_vars['tag_slug__and'])){
                foreach($q->query_vars['tag_slug__and'] as $t){
                    $tag_array[] = $wpdb->get_var($wpdb->prepare( "SELECT x.term_id FROM $wpdb->terms t
                        JOIN $wpdb->term_taxonomy x ON t.term_id=x.term_id WHERE x.taxonomy='post_tag' AND t.slug='%s'", $wpdb->escape($t)));
                }
            }

            if(!empty($tag_array)){
                $translated_ids = array();
                foreach($tag_array as $c){
                    if(intval($c) < 0){
                        $sign = -1;
                    }else{
                        $sign = 1;
                    }
                     $_tid = intval(icl_object_id(abs($c), 'post_tag', true));
                     $translated_ids[] = $sign * $_tid;
                }
            }


            //tag
            if(isset($q->query_vars['tag']) && !empty($q->query_vars['tag'])){
                if(isset($translated_ids)){
                    $slugs = $wpdb->get_col("SELECT slug FROM $wpdb->terms WHERE term_id IN (".join(',', $translated_ids).")");
                    $q->query_vars['tag'] = join($tag_glue, $slugs);
                }
            }
            //tag_id
            if(isset($q->query_vars['tag_id']) && !empty($q->query_vars['tag_id'])){
                $q->query_vars['tag_id'] = join(',', $translated_ids);
            }
            // tag__and
            if(isset($q->query_vars['tag__and']) && !empty($q->query_vars['tag__and'])){
                $q->query_vars['tag__and'] = $translated_ids;
            }
            // tag__in
            if(isset($q->query_vars['tag__in']) && !empty($q->query_vars['tag__in'])){
                $q->query_vars['tag__in'] = $translated_ids;
            }
            // tag__not_in
            if(isset($q->query_vars['tag__not_in']) && !empty($q->query_vars['tag__not_in'])){
                $q->query_vars['tag__not_in'] = array_map('abs', $translated_ids);
            }
            // tag_slug__in
            if(isset($q->query_vars['tag_slug__in']) && !empty($q->query_vars['tag_slug__in'])){
                $q->query_vars['tag_slug__in'] = $wpdb->get_col("SELECT slug FROM $wpdb->terms WHERE term_id IN (".join(',', $translated_ids).")");
            }
            // tag_slug__and
            if(isset($q->query_vars['tag_slug__and']) && !empty($q->query_vars['tag_slug__and'])){
                $q->query_vars['tag_slug__and'] = $wpdb->get_col("SELECT slug FROM $wpdb->terms WHERE term_id IN (".join(',', $translated_ids).")");
            }


            // POST & PAGES
            $post_type = isset($q->query_vars['post_type']) ? $q->query_vars['post_type'] : 'post';
            // page_id
            if(isset($q->query_vars['page_id']) && !empty($q->query_vars['page_id'])){
                $q->query_vars['page_id'] = icl_object_id($q->query_vars['page_id'], 'page', true);
                $q->query = preg_replace('/page_id=[0-9]+/','page_id='.$q->query_vars['page_id'], $q->query);
            }

            // p
            if(isset($q->query_vars['p']) && !empty($q->query_vars['p'])){
                $q->query_vars['p'] = icl_object_id($q->query_vars['p'], $post_type, true);
            }
            // name
            if(isset($q->query_vars['name']) && !empty($q->query_vars['name'])){
                $pid = $wpdb->get_var("SELECT ID FROM $wpdb->posts
                                        WHERE
                                        post_name='".$wpdb->escape($q->query_vars['name']) . 
                                        "' AND post_type='" . $post_type . "'");
                if (!empty($pid)) {
                    $q->query_vars['p'] = icl_object_id($pid, $post_type, true);
                    unset($q->query_vars['name']);
                }
            }
            // pagename
            if(isset($q->query_vars['pagename']) && !empty($q->query_vars['pagename'])){
                
                // find the page with the page name in the current language.
                
                $pid = $wpdb->get_var($wpdb->prepare(" 
                                SELECT ID
                                FROM $wpdb->posts p
                                
                                JOIN {$wpdb->prefix}icl_translations t
                                ON p.ID = t.element_id AND element_type='post_page'
                                
                                WHERE p.post_name=%s AND t.language_code = %s
                                
                                ", $q->query_vars['pagename'], $this->get_current_language()));

                if ($pid) {
                    $q->query_vars['page_id'] = $pid;
                    // We have found the page id
                    unset($q->query_vars['pagename']);
                    if  ( $q->query_vars['page_id'] == get_option('page_for_posts') ) {
                        // it's the blog page.
                        $wp_query->is_page = false;
                        $wp_query->is_home = true;
                        $wp_query->is_posts_page = true;
                    }
                }
            }
            // post__in
            if(isset($q->query_vars['post__in']) && !empty($q->query_vars['post__in'])){
                $pid = array();
                foreach($q->query_vars['post__in'] as $p){
                    if(is_array($post_type)){
                        foreach($post_type as $pt){
                            $pid[] = icl_object_id($p, $pt, true);
                        }
                    }
                    else{
                        $pid[] = icl_object_id($p, $post_type, true);
                    }                    
                }
                $q->query_vars['post__in'] = $pid;
            }
            // post__not_in
            if(isset($q->query_vars['post__not_in']) && !empty($q->query_vars['post__not_in'])){
                $pid = array();
                foreach($q->query_vars['post__not_in'] as $p){
                    if(is_array($post_type)){
                        foreach($post_type as $pt){
                            $pid[] = icl_object_id($p, $pt, true);
                        }
                    }
                    else{
                        $pid[] = icl_object_id($p, $post_type, true);
                    }                    
                }
                $q->query_vars['post__not_in'] = $pid;
            }
            // post_parent
            if(isset($q->query_vars['post_parent']) && !empty($q->query_vars['post_parent']) && $q->query_vars['post_type']!='attachment'){
                $q->query_vars['post_parent'] = icl_object_id($q->query_vars['post_parent'], $post_type, true);
            }

            // custom taxonomies
            if(isset($q->query_vars['taxonomy'])){
                $tax_id = $wpdb->get_var("SELECT term_id FROM {$wpdb->terms} WHERE slug='".$wpdb->escape($q->query_vars['term'])."'");
                if($tax_id){
                    $translated_tax_id = icl_object_id($tax_id, $q->query_vars['taxonomy'], true);
                }
                $q->query_vars['term'] = $wpdb->get_var($wpdb->prepare(
                    "SELECT slug FROM {$wpdb->terms} WHERE term_id = %d", $translated_tax_id));
                $q->query[$q->query_vars['taxonomy']] = $q->query_vars['term'];
            }

            // TODO Discuss this. Why WP assumes it's there if query vars are altered?
            // Look at wp-includes/query.php line #2468 search: if ( $this->query_vars_changed ) {
            if (!isset($q->query_vars['meta_query'])) {
                $q->query_vars['meta_query'] = array();
            }

        }
        
        return $q;
    }

    function adjust_wp_list_pages_excludes($pages){
        foreach($pages as $k=>$v){
            $pages[$k] = icl_object_id($v, 'page', true);
        }
        return $pages;
    }

    function language_attributes($output){               
        if(preg_match('#lang="[a-z-]+"#i',$output)){
            $output = preg_replace('#lang="([a-z-]+)"#i', 'lang="'.$this->this_lang.'"', $output);
        }else{
            $output .= ' lang="'.$this->this_lang.'"';
        }
        return $output;
    }

    // Localization
    function plugin_localization(){
        load_plugin_textdomain( 'sitepress', false, ICL_PLUGIN_FOLDER . '/locale');
    }

    function locale(){
        global $wpdb, $locale;
        
        add_filter('language_attributes', array($this, '_language_attributes'));
        
        if(defined('WP_ADMIN')){            
            if( function_exists('wp_get_current_user') && get_user_meta(get_current_user_id(), 'icl_admin_language_for_edit', true) && icl_is_post_edit()){
                $l = $this->get_locale($this->get_current_language());   
            }else{
                $l = $this->get_locale($this->admin_language);    
            }            
        }else{
            $l = $this->get_locale($this->this_lang);
        }
        if($l){
            $locale = $l;
        }

        $template_path = defined('TEMPLATEPATH') ? TEMPLATEPATH : get_template_directory();        
        
        // theme localization
        remove_filter('locale', array($this, 'locale')); //avoid infinite loop
        static $theme_locales_loaded = false;
        if(
            !$theme_locales_loaded 
            && !empty($this->settings['theme_localization_load_textdomain']) 
            && !empty($this->settings['gettext_theme_domain_name'])
            && !empty($this->settings['theme_language_folders'])
        ){
            foreach($this->settings['theme_language_folders'] as $folder){
                load_textdomain($this->settings['gettext_theme_domain_name'], $folder . '/'.$locale.'.mo');    
            }
            $theme_locales_loaded = true;
        }
        add_filter('locale', array($this, 'locale'));
                
        
        return $locale;
    }

    function _language_attributes($latr){
        global $locale;
        $latr = preg_replace('#lang="(.[a-z])"#i', 'lang="'.str_replace('_','-',$locale).'"', $latr);
        return $latr;
    }

    function get_locale($code) {
        global $wpdb;

        if (isset($this->icl_locale_cache) && $this->icl_locale_cache->has_key($code)){
            return $this->icl_locale_cache->get($code);
        }

        $locale = $wpdb->get_var("SELECT locale FROM {$wpdb->prefix}icl_locale_map WHERE code='{$code}'");
        if (isset($this->icl_locale_cache)){
            $this->icl_locale_cache->set($code, $locale);
        }
        return $locale;
    }

    function get_locale_file_names(){
        global $wpdb;
        $locales = array();
        $res = $wpdb->get_results("
            SELECT lm.code, locale
            FROM {$wpdb->prefix}icl_locale_map lm JOIN {$wpdb->prefix}icl_languages l ON lm.code = l.code AND l.active=1");
        foreach($res as $row){
            $locales[$row->code] = $row->locale;
        }
        return $locales;
    }

    function set_locale_file_names($locale_file_names_pairs){
        global $wpdb;
        $lfn = $this->get_locale_file_names();

        $new = array_diff(array_keys($locale_file_names_pairs), array_keys($lfn));
        if(!empty($new)){
            foreach($new as $code){
                $wpdb->insert($wpdb->prefix.'icl_locale_map', array('code'=>$code,'locale'=>$locale_file_names_pairs[$code]));
            }
        }
        $remove = array_diff(array_keys($lfn), array_keys($locale_file_names_pairs));
        if(!empty($remove)){
            $wpdb->query("DELETE FROM {$wpdb->prefix}icl_locale_map WHERE code IN (".join(',', array_map(create_function('$a','return "\'".$a."\'";'),$remove)).")");
        }

        $update = array_diff($locale_file_names_pairs, $lfn);
        foreach($update as $code=>$locale){
            $wpdb->update($wpdb->prefix.'icl_locale_map', array('locale'=>$locale), array('code'=>$code));
        }

        $this->icl_locale_cache->clear();

        return true;
    }

    function pre_option_page_on_front(){
        global $wpdb;
        static $page_on_front_sc = array();
        if (@is_null($page_on_front_sc[$this->this_lang]) || ICL_DISABLE_CACHE) {
            $page_on_front_sc[$this->this_lang] = false;
            $page_on_front = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='page_on_front'");
            $_el_lang_det = $this->get_element_language_details($page_on_front, 'post_page');
            if(!empty($_el_lang_det->trid)){
                $trid = $_el_lang_det->trid;
                $translations = $wpdb->get_results("SELECT element_id, language_code FROM {$wpdb->prefix}icl_translations WHERE trid={$trid}");
                foreach($translations as $t){
                    if($t->language_code==$this->this_lang){
                        $page_on_front_sc[$this->this_lang] = $t->element_id;
                    }
                }
            }
        }
        
        return $page_on_front_sc[$this->this_lang];
    }

    function pre_option_page_for_posts(){
        global $wpdb;
        static $page_for_posts_sc = array();
        if (@is_null($page_for_posts_sc[$this->this_lang]) || ICL_DISABLE_CACHE) {
            $page_for_posts_sc[$this->this_lang] = false;
            $page_for_posts = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='page_for_posts'");
            $_el_lang_det = $this->get_element_language_details($page_for_posts, 'post_page');
            if(!empty($_el_lang_det->trid)){
                $trid = $_el_lang_det->trid;
                $translations = $wpdb->get_results("SELECT element_id, language_code FROM {$wpdb->prefix}icl_translations WHERE trid={$trid}");
                foreach($translations as $t){
                    if($t->language_code==$this->this_lang){
                        $page_for_posts_sc[$this->this_lang] = $t->element_id;
                    }
                }
            }
        }
        return $page_for_posts_sc[$this->this_lang];
    }

    function verify_home_and_blog_pages_translations(){
        global $wpdb;
        $warn_home = $warn_posts = '';
        if( 'page' == get_option('show_on_front') && get_option('page_on_front')){
            $page_on_front = get_option('page_on_front');
            $page_home_trid = $wpdb->get_var("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id={$page_on_front} AND element_type='post_page'");
            $page_home_translations = $this->get_element_translations($page_home_trid, 'post_page');
            $missing_home = array();
            foreach($this->active_languages as $lang){
             if(!isset($page_home_translations[$lang['code']])){
                 $addlink = admin_url('post-new.php?post_type=page&trid='.$page_home_trid.'&lang=' . $lang['code'] . '&source_lang=' . $this->get_default_language());
                 //$missing_home[] = '<a href="'.$addlink.'" title="'.__('add translation', 'sitepress').'">' . $lang['display_name'] . '</a>';
                 $missing_home[] =  $lang['display_name'];
             }elseif($page_home_translations[$lang['code']]->post_status != 'publish'){
                 $editlink = admin_url('post.php?post='.$page_home_translations[$lang['code']]->element_id.'&action=edit&lang='.$lang['code']);
                 //$missing_home[] = '<a href="'.$editlink.'" title="'.__('Not published - edit page', 'sitepress').'">' . $lang['display_name'] . '</a>';
                 $missing_home[] =  $lang['display_name'];
             }
            }
            if(!empty($missing_home)){
             $warn_home  = '<div class="icl_form_errors" style="font-weight:bold">';
             $warn_home .= sprintf(__('Your home page does not exist or its translation is not published in %s.', 'sitepress'), join(', ', $missing_home));
             $warn_home .= '<br />';
             $warn_home .= '<a href="'.get_edit_post_link($page_on_front).'">' . __('Edit this page to add translations', 'sitepress') . '</a>';
             $warn_home .= '</div>';
            }
        }
        if(get_option('page_for_posts')){
            $page_for_posts = get_option('page_for_posts');
            $page_posts_trid = $wpdb->get_var("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id={$page_for_posts} AND element_type='post_page'");
            $page_posts_translations = $this->get_element_translations($page_posts_trid, 'post_page');
            $missing_posts = array();
            foreach($this->active_languages as $lang){
             if(!isset($page_posts_translations[$lang['code']])){
                 $addlink = admin_url('post-new.php?post_type=page&trid='.$page_posts_trid.'&lang=' . $lang['code'] . '&source_lang=' . $this->get_default_language());
                 //$missing_posts[] = '<a href="'.$addlink.'" title="'.__('add translation', 'sitepress').'">' . $lang['display_name'] . '</a>';
                 $missing_posts[] = $lang['display_name'];
             }elseif($page_posts_translations[$lang['code']]->post_status != 'publish'){
                 $editlink = admin_url('post.php?post='.$page_posts_translations[$lang['code']]->element_id.'&action=edit&lang='.$lang['code']);
                 //$missing_posts[] = '<a href="'.$editlink.'" title="'.__('Not published - edit page', 'sitepress').'">' . $lang['display_name'] . '</a>';
                 $missing_posts[] = $lang['display_name'];
             }
            }
            if(!empty($missing_posts)){
             $warn_posts  = '<div class="icl_form_errors" style="font-weight:bold;margin-top:4px;">';
             $warn_posts .= sprintf(__('Your blog page does not exist or its translation is not published in %s.', 'sitepress'), join(', ', $missing_posts));
             $warn_posts .= '<br />';
             $warn_posts .= '<a href="'.get_edit_post_link($page_for_posts).'">' . __('Edit this page to add translations', 'sitepress') . '</a>';
             $warn_posts .= '</div>';
            }
        }
        return array($warn_home, $warn_posts);
    }

    // adds the language parameter to the admin post filtering/search
    function restrict_manage_posts(){
        echo '<input type="hidden" name="lang" value="'.$this->this_lang.'" />';
    }

    // adds the language parameter to the admin pages search
    function restrict_manage_pages(){
        ?>
        <script type="text/javascript">
        addLoadEvent(function(){jQuery('p.search-box').append('<input type="hidden" name="lang" value="<?php echo $this->this_lang ?>">');});
        </script>
        <?php
    }

    function get_edit_post_link($link, $id, $context = 'display'){
        global $wpdb;
        if ( current_user_can( 'edit_post', $id ) ) {
            if ( 'display' == $context )
                $and = '&amp;';
            else
                $and = '&';

            if($id){
                $post_type = $wpdb->get_var("SELECT post_type FROM {$wpdb->posts} WHERE ID='{$id}'");
                $details = $this->get_element_language_details($id, 'post_' . $post_type);
                if(isset($details->language_code)){
                    $lang = $details->language_code;
                }else{
                    $lang = $this->get_current_language();
                }
                if($lang != $this->get_default_language() || $this->get_current_language() != $this->get_default_language()){
                    $link .= $and . 'lang=' . $lang;
                }
            }
        }
        return $link;
    }
    
    function get_edit_term_link($link, $term_id, $taxonomy, $object_type){
        global $wpdb;
        $term_tax_id = $wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy=%s", $term_id, $taxonomy));        
        $details = $this->get_element_language_details($term_tax_id, 'tax_' . $taxonomy);    
        $and = '&';
        if(isset($details->language_code)){
            $lang = $details->language_code;
        }else{
            $lang = $this->get_current_language();
        }
        
        if($lang != $this->get_default_language() || $this->get_current_language() != $this->get_default_language()){
            $link .= $and . 'lang=' . $lang;
        }
        
        return $link;
    }

    function option_sticky_posts($posts){
        global $wpdb;
        if(is_array($posts) && !empty($posts)){
            $posts = array_filter($posts, create_function('$a', 'return $a > 0;'));
            $posts = $wpdb->get_col("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_id IN (".join(',',$posts).") AND element_type='post_post' AND language_code = '{$this->this_lang}'");
        }
        return $posts;
    }

    function request_filter($request){
        // bug http://forum.wpml.org/topic.php?id=5
        if(!defined('WP_ADMIN') && $this->settings['language_negotiation_type']==3 && isset($request['lang'])) {
            // Count the parameters that have settings and remove our 'lang ' setting it's the only one.
            // This is required so that home page detection works for other languages.
            $count = 0;
            foreach ($request as $data) {
                if ($data !== '') {
                    $count += 1;
                }
            }
            if ($count == 1) {
                unset($request['lang']);
            }
        }
        return $request;
    }

    function noscript_notice(){
        ?><noscript><div class="error"><?php echo __('WPML admin screens require JavaScript in order to display. JavaScript is currently off in your browser.', 'sitepress') ?></div></noscript><?php
    }

    function filter_queries($sql){
        global $wpdb, $pagenow;
        // keep a record of the queries
        $this->queries[] = $sql;

        if($pagenow=='categories.php' || $pagenow=='edit-tags.php'){
            if(preg_match('#^SELECT COUNT\(\*\) FROM '.$wpdb->term_taxonomy.' WHERE taxonomy = \'(category|post_tag)\' $#',$sql,$matches)){
                $element_type= 'tax_' . $matches[1];
                $sql = "
                    SELECT COUNT(*) FROM {$wpdb->term_taxonomy} tx
                        JOIN {$wpdb->prefix}icl_translations tr ON tx.term_taxonomy_id=tr.element_id
                    WHERE tx.taxonomy='{$matches[1]}' AND tr.element_type='{$element_type}' AND tr.language_code='".$this->get_current_language()."'";
            }
        }

        if($pagenow=='edit.php' || $pagenow=='edit-pages.php'){
            /* preWP3 compatibility  - start */
            if(ICL_PRE_WP3 && $pagenow == 'edit-pages.php'){
                $_GET['post_type'] = 'page';
            }
            /* preWP3 compatibility  - end */
            $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : 'post';
            $element_type = 'post_' . $post_type;
            if($this->is_translated_post_type($post_type)){
                if(preg_match('#SELECT post_status, COUNT\( \* \) AS num_posts FROM '.$wpdb->posts.' WHERE post_type = \'(.+)\' GROUP BY post_status#i',$sql,$matches)){
                    if('all'!=$this->get_current_language()){
                        $sql = '
                        SELECT post_status, COUNT( * ) AS num_posts
                        FROM '.$wpdb->posts.' p
                            JOIN '.$wpdb->prefix.'icl_translations t ON p.ID = t.element_id
                        WHERE p.post_type = \''.$matches[1].'\'
                            AND t.element_type=\''.$element_type.'\'
                            AND t.language_code=\''.$this->get_current_language().'\'
                        GROUP BY post_status';
                    }else{
                        $sql = '
                        SELECT post_status, COUNT( * ) AS num_posts
                        FROM '.$wpdb->posts.' p
                            JOIN '.$wpdb->prefix.'icl_translations t ON p.ID = t.element_id
                            JOIN '.$wpdb->prefix.'icl_languages l ON t.language_code = l.code AND l.active = 1
                        WHERE p.post_type = \''.$matches[1].'\'
                            AND t.element_type=\''.$element_type.'\'
                        GROUP BY post_status';
                    }
                }
            }
        }

        if(isset($_GET['action']) && $_GET['action']=='ajax-tag-search'){
            $search = 'SELECT t.name FROM '. $wpdb->term_taxonomy
                .' AS tt INNER JOIN '.$wpdb->terms.' AS t ON tt.term_id = t.term_id WHERE tt.taxonomy = \''. $wpdb->escape($_GET['tax'])
                .'\' AND t.name LIKE (\'%' . $wpdb->escape($_GET['q']) . '%\')';
            if($sql == $search){
                $parts = parse_url($_SERVER['HTTP_REFERER']);
                @parse_str($parts['query'], $query);
                $lang = isset($query['lang']) ? $query['lang'] : $this->get_language_cookie();
                $element_type = 'tax_' . $_GET['tax'];
                $sql =
                    'SELECT t.name FROM '. $wpdb->term_taxonomy
                    .' AS tt
                    INNER JOIN '.$wpdb->terms.' AS t ON tt.term_id = t.term_id
                    JOIN '.$wpdb->prefix.'icl_translations tr ON tt.term_taxonomy_id = tr.element_id
                    WHERE tt.taxonomy = \''. $wpdb->escape($_GET['tax'])
                    .'\' AND tr.language_code=\''.$lang.'\' AND element_type=\''.$element_type.'\'
                    AND t.name LIKE (\'%' . $wpdb->escape($_GET['q']) . '%\')
                ';
            }
        }

        return $sql;
    }

    function get_inactive_content(){
        global $wpdb;
        $inactive = array();
        $res_p = $wpdb->get_results("
           SELECT COUNT(p.ID) AS c, p.post_type, lt.name AS language FROM {$wpdb->prefix}icl_translations t
            JOIN {$wpdb->posts} p ON t.element_id=p.ID AND t.element_type LIKE 'post\\_%'
            JOIN {$wpdb->prefix}icl_languages l ON t.language_code = l.code AND l.active = 0
            JOIN {$wpdb->prefix}icl_languages_translations lt ON lt.language_code = l.code  AND lt.display_language_code='".$this->get_current_language()."'
            GROUP BY p.post_type, t.language_code
        ");
        foreach($res_p as $r){
            $inactive[$r->language][$r->post_type] = $r->c;
        }
        $res_t = $wpdb->get_results("
           SELECT COUNT(p.term_taxonomy_id) AS c, p.taxonomy, lt.name AS language FROM {$wpdb->prefix}icl_translations t
            JOIN {$wpdb->term_taxonomy} p ON t.element_id=p.term_taxonomy_id
            JOIN {$wpdb->prefix}icl_languages l ON t.language_code = l.code AND l.active = 0
            JOIN {$wpdb->prefix}icl_languages_translations lt ON lt.language_code = l.code  AND lt.display_language_code='".$this->get_current_language()."'
            WHERE t.element_type LIKE  'tax\\_%'
            GROUP BY p.taxonomy, t.language_code
        ");
        foreach($res_t as $r){
            if($r->taxonomy=='category' && $r->c == 1){
                continue; //ignore the case of just the default category that gets automatically created for a new language
            }
            $inactive[$r->language][$r->taxonomy] = $r->c;
        }
        return $inactive;
    }

    function menu_footer(){
        include ICL_PLUGIN_PATH . '/menu/menu-footer.php';
    }

    function _allow_calling_template_file_directly(){
        if(is_404()){
            global $wp_query, $wpdb;
            $parts = parse_url(get_bloginfo('url'));
            $req = str_replace($parts['path'], '', $_SERVER['REQUEST_URI']);
            if(file_exists(ABSPATH . $req) && !is_dir(ABSPATH . $req)){
                $wp_query->is_404 = false;
                header('HTTP/1.1 200 OK');
                include ABSPATH . $req;
                exit;
            }
        }
    }

    function show_user_options(){
        global $current_user;
        $active_languages = $this->get_active_languages();
        $default_language = $this->get_default_language();
        $user_language = get_user_meta($current_user->data->ID,'icl_admin_language',true);
        if($this->settings['admin_default_language'] == '_default_'){
            $this->settings['admin_default_language'] = $default_language;
        }
        $lang_details = $this->get_language_details($this->settings['admin_default_language']);
        $admin_default_language = $lang_details['display_name'];
        ?>
        <a name="wpml"></a>
        <h3><?php _e('WPML language settings','sitepress'); ?></h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><?php _e('Select your language:', 'sitepress') ?></th>
                    <td>
                        <select name="icl_user_admin_language">
                        <option value=""<?php if($user_language==$this->settings['admin_default_language']) echo ' selected="selected"'?>><?php printf(__('Default admin language (currently %s)','sitepress'), $admin_default_language );?>&nbsp;</option>
                        <?php foreach($active_languages as $al):?>
                        <option value="<?php echo $al['code'] ?>"<?php if($user_language==$al['code']) echo ' selected="selected"'?>><?php echo $al['display_name']; if($this->admin_language != $al['code']) echo ' ('. $al['native_name'] .')'; ?>&nbsp;</option>
                        <?php endforeach; ?>
                        </select>
                        <span class="description"><?php _e('this will be your admin language and will also be used for translating comments.', 'sitepress'); ?></span>
                        <br />
                        <label><input type="checkbox" name="icl_admin_language_for_edit" value="1" <?php if(get_user_meta(get_current_user_id(), 'icl_admin_language_for_edit', true)):?>checked="checked"<?php endif;?> />&nbsp;<?php _e('Set admin language as editing language.', 'sitepress'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Hidden languages:', 'sitepress') ?></th>
                    <td>
                        <p>
                        <?php if(!empty($this->settings['hidden_languages'])): ?>
                            <?php
                             if(1 == count($this->settings['hidden_languages'])){
                                 printf(__('%s is currently hidden to visitors.', 'sitepress'),
                                    $active_languages[$this->settings['hidden_languages'][0]]['display_name']);
                             }else{
                                 foreach($this->settings['hidden_languages'] as $l){
                                     $_hlngs[] = $active_languages[$l]['display_name'];
                                 }
                                 $hlangs = join(', ', $_hlngs);
                                 printf(__('%s are currently hidden to visitors.', 'sitepress'), $hlangs);
                             }
                            ?>
                        <?php else: ?>
                        <?php _e('All languages are currently displayed. Choose what to do when site languages are hidden.', 'sitepress'); ?>
                        <?php endif; ?>
                        </p>
                        <p>
                        <label><input name="icl_show_hidden_languages" type="checkbox" value="1" <?php
                            if(get_user_meta($current_user->data->ID, 'icl_show_hidden_languages', true)):?>checked="checked"<?php endif?> />&nbsp;<?php
                            _e('Display hidden languages', 'sitepress') ?></label>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    function  save_user_options(){
        $user_id = $_POST['user_id'];
        if($user_id){
            update_user_meta($user_id,'icl_admin_language',$_POST['icl_user_admin_language']);
            update_user_meta($user_id,'icl_show_hidden_languages',intval($_POST['icl_show_hidden_languages']));
            update_user_meta($user_id,'icl_admin_language_for_edit',intval($_POST['icl_admin_language_for_edit']));            
            $this->icl_locale_cache->clear();
        }
    }

    function help_admin_notice(){
        $q = http_build_query(array(
            'name'      => 'wpml-intro',
            'iso'       => defined('WPLANG') ? WPLANG : '',
            'src'    => get_option('home')
        ));
        ?>
        <br clear="all" />
        <div id="message" class="updated message fade" style="clear:both;margin-top:5px;"><p>
        <?php _e('WPML is a powerful plugin with many features. Would you like to see a quick overview?', 'sitepress'); ?>
        </p>
        <p>
        <a href="<?php echo ICL_API_ENDPOINT ?>/destinations/go?<?php echo $q ?>" target="_blank" class="button-primary"><?php _e('Yes', 'sitepress')?></a>&nbsp;
        <a href="admin.php?page=<?php echo basename(ICL_PLUGIN_PATH).'/menu/languages.php&icl_action=dismiss_help'; ?>"  class="button"><?php _e('No thanks, I will configure myself', 'sitepress')?></a>&nbsp;
        <a title="<?php _e('Stop showing this message', 'sitepress') ?>" id="icl_dismiss_help" href=""><?php _e('Dismiss', 'sitepress')?></a>
        </p>
        </div>
        <?php
    }

    function upgrade_notice(){
        include ICL_PLUGIN_PATH . '/menu/upgrade_notice.php';
    }

    function new_version_notice(){
        include ICL_PLUGIN_PATH . '/menu/new_version_notice.php';
    }

    function icl_reminders(){
        include ICL_PLUGIN_PATH . '/menu/icl_reminders.php';
    }

    function add_posts_management_column($columns){
        global $posts, $wpdb, $__management_columns_posts_translations;
        $element_type = isset($_REQUEST['post_type']) ? 'post_' . $_REQUEST['post_type'] : 'post_post';
        if(count($this->get_active_languages()) <= 1 || get_query_var('post_status') == 'trash'){
            return $columns;
        }

        if(isset($_POST['action']) && $_POST['action']=='inline-save' && $_POST['post_ID']){
            $p = new stdClass();
            $p->ID = $_POST['post_ID'];
            $posts = array($p);
        }elseif(empty($posts)){
            return $columns;
        }
        if(is_null($__management_columns_posts_translations)){
            foreach($posts as $p){
                $post_ids[] = $p->ID;
            }
            // get posts translations
            // get trids
            $trids = $wpdb->get_col("
                SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_type='{$element_type}' AND element_id IN (".join(',', $post_ids).")
            ");
            $ptrs = $wpdb->get_results("
                SELECT trid, element_id, language_code, source_language_code FROM {$wpdb->prefix}icl_translations WHERE trid IN (".join(',', $trids).")

            ");
            foreach($ptrs as $v){
                $by_trid[$v->trid][] = $v;
            }

            foreach($ptrs as $v){
                if(in_array($v->element_id, $post_ids)){
                    $el_trid = $v->trid;
                    foreach($ptrs as $val){
                        if($val->trid == $el_trid){
                            $__management_columns_posts_translations[$v->element_id][$val->language_code] = $val;
                        }
                    }
                }
            }
        }
        $active_languages = $this->get_active_languages();
        foreach($active_languages as $k=>$v){
            if($v['code']==$this->get_current_language()) continue;
            $langs[] = $v['code'];
        }
        $res = $wpdb->get_results("
            SELECT f.lang_code, f.flag, f.from_template, l.name
            FROM {$wpdb->prefix}icl_flags f
                JOIN {$wpdb->prefix}icl_languages_translations l ON f.lang_code = l.language_code
            WHERE l.display_language_code = '".$this->admin_language."' AND f.lang_code IN('".join("','",$langs)."')
        ");
        foreach($res as $r){
            if($r->from_template){
                $fpath = get_bloginfo('template_directory') . '/images/flags/';
            }else{
            }   $fpath = ICL_PLUGIN_URL . '/res/flags/';
            $flags[$r->lang_code] = '<img src="'.$fpath.$r->flag.'" width="18" height="12" alt="'.$r->name.'" title="'.$r->name.'" />';
        }
        $colh = '';
        foreach($active_languages as $v){
            if(isset($flags[$v['code']])) $colh .= $flags[$v['code']];
        }
        foreach($columns as $k=>$v){
            $new_columns[$k] = $v;
            if($k=='title'){
                $new_columns['icl_translations'] = $colh;
            }
        }
        return $new_columns;
    }

    function add_content_for_posts_management_column($column_name){
        global $wpdb, $sitepress_settings;
        if($column_name != 'icl_translations') return;
        global $id, $__management_columns_posts_translations, $pagenow, $iclTranslationManagement;
        $active_languages = $this->get_active_languages();
        foreach($active_languages as $k=>$v){
            if($v['code']==$this->get_current_language()) continue;
            $post_type = isset($_REQUEST['post_type']) ? $_REQUEST['post_type'] : 'post';

            if(isset($__management_columns_posts_translations[$id][$v['code']]) && $__management_columns_posts_translations[$id][$v['code']]->element_id){
                // Translation exists
                $img = 'edit_translation.png';
                $alt = sprintf(__('Edit the %s translation','sitepress'), $v['display_name']);
                switch($iclTranslationManagement->settings['doc_translation_method']){
                    case ICL_TM_TMETHOD_EDITOR:
                        $job_id = $iclTranslationManagement->get_translation_job_id($__management_columns_posts_translations[$id][$v['code']]->trid, $v['code']);
                        $args = array('lang_from'=>$this->get_current_language(), 'lang_to'=>$v['code'], 'job_id'=>@intval($job_id));
                        $current_user_is_translator = $iclTranslationManagement->is_translator(get_current_user_id(), $args);
                        if(!$current_user_is_translator){
                            $img = 'edit_translation_disabled.png';
                            $link = '#';
                            $alt = sprintf(__('You are not allowed to edit the %s translation','sitepress'), $v['display_name']);
                        }else{
                            if($job_id){
                                $link = admin_url('admin.php?page='.WPML_TM_FOLDER.'/menu/translations-queue.php&job_id='.$job_id);
                            }else{
                                $link = admin_url('admin.php?page='.WPML_TM_FOLDER.'/menu/translations-queue.php&icl_tm_action=create_job&iclpost[]='.
                                    $id.'&translate_to['.$v['code'].']=1&iclnonce=' . wp_create_nonce('pro-translation-icl'));
                            }
                        }
                        break;
                    case ICL_TM_TMETHOD_PRO:
                        if(!$__management_columns_posts_translations[$id][$v['code']]->source_language_code){
                            $link = get_edit_post_link($__management_columns_posts_translations[$id][$v['code']]->element_id);
                            $alt = __('Edit the original document','sitepress');
                        }else{
                            $job_id = $iclTranslationManagement->get_translation_job_id($__management_columns_posts_translations[$id][$v['code']]->trid, $v['code']);
                            if($job_id){
                                $job_details = $iclTranslationManagement->get_translation_job($job_id);
                                if($job_details->status == ICL_TM_IN_PROGRESS || $job_details->status == ICL_TM_WAITING_FOR_TRANSLATOR){
                                    $img = 'in-progress.png';
                                    $alt = sprintf(__('Translation to %s is in progress','sitepress'), $v['display_name']);
                                    $link = false;
                                    echo '<img style="padding:1px;margin:2px;" border="0" src="'.ICL_PLUGIN_URL . '/res/img/' .$img.'" title="'.$alt.'" alt="'.$alt.'" width="16" height="16" />';
                                }else{
                                    $link = admin_url('admin.php?page='.WPML_TM_FOLDER.'/menu/translations-queue.php&job_id='.$job_id);
                                }
                            }
                        }
                        break;
                    default:
                        $link = 'post.php?post_type='.$post_type.'&action=edit&amp;post='.$__management_columns_posts_translations[$id][$v['code']]->element_id.'&amp;lang='.$v['code'];
                }

            }else{
                // Translation does not exist
                $img = 'add_translation.png';
                $alt = sprintf(__('Add translation to %s','sitepress'), $v['display_name']);
                $src_lang = $this->get_current_language() == 'all' ? $this->get_default_language() : $this->get_current_language();
                switch($iclTranslationManagement->settings['doc_translation_method']){
                    case ICL_TM_TMETHOD_EDITOR:
                        if(isset($__management_columns_posts_translations[$id][$v['code']])){
                            $job_id = $iclTranslationManagement->get_translation_job_id($__management_columns_posts_translations[$id][$v['code']]->trid, $v['code']);
                        }else{
                            $job_id = 0;
                        }
                        $args = array('lang_from'=>$src_lang, 'lang_to'=>$v['code'], 'job_id'=>@intval($job_id));
                        $current_user_is_translator = $iclTranslationManagement->is_translator(get_current_user_id(), $args);

                        if($job_id){
                            if($current_user_is_translator){
                                $job_details = $iclTranslationManagement->get_translation_job($job_id);
                                if($job_details && $job_details->status == ICL_TM_IN_PROGRESS){
                                    $img = 'in-progress.png';
                                    $alt = sprintf(__('Translation to %s is in progress','sitepress'), $v['display_name']);
                                }
                                $link = admin_url('admin.php?page='.WPML_TM_FOLDER.'/menu/translations-queue.php&job_id='.$job_id);
                            }else{
                                $link = '#';
                                $tres = $wpdb->get_row($wpdb->prepare("
                                    SELECT s.* FROM {$wpdb->prefix}icl_translation_status s
                                        JOIN {$wpdb->prefix}icl_translate_job j ON j.rid = s.rid
                                        WHERE job_id=%d

                                ", $job_id));
                                if($tres->status == ICL_TM_IN_PROGRESS){
                                    $img = 'in-progress.png';
                                    $alt = sprintf(__('Translation to %s is in progress (by a different translator)','sitepress'), $v['display_name']);
                                }elseif($tres->status == ICL_TM_NOT_TRANSLATED || $tres->status == ICL_TM_WAITING_FOR_TRANSLATOR){
                                    $img = 'add_translation_disabled.png';
                                }elseif($tres->status == ICL_TM_NEEDS_UPDATE || $tres->status == ICL_TM_COMPLETE){
                                    $img = 'edit_translation_disabled.png';
                                }
                            }
                        }else{
                            if($current_user_is_translator){
                                $link = admin_url('admin.php?page='.WPML_TM_FOLDER.'/menu/translations-queue.php&icl_tm_action=create_job&iclpost[]='.
                                    $id.'&translate_to['.$v['code'].']=1&iclnonce=' . wp_create_nonce('pro-translation-icl'));
                            }else{
                                $link = '#2';
                                $img = 'add_translation_disabled.png';
                                $alt = sprintf(__('You are not allowed to edit the %s translation','sitepress'), $v['display_name']);
                            }
                        }

                        break;
                    case ICL_TM_TMETHOD_PRO:
                        if($this->have_icl_translator($src_lang,$v['code'])){
                            $job_id = $iclTranslationManagement->get_translation_job_id($__management_columns_posts_translations[$id][$v['code']]->trid, $v['code']);
                            if($job_id){
                                $job_details = $iclTranslationManagement->get_translation_job($job_id);
                                if($job_details->status == ICL_TM_IN_PROGRESS || $job_details->status == ICL_TM_WAITING_FOR_TRANSLATOR){
                                    $img = 'in-progress.png';
                                    $alt = sprintf(__('Translation to %s is in progress','sitepress'), $v['display_name']);
                                    $link = false;
                                    echo '<img style="padding:1px;margin:2px;" border="0" src="'.ICL_PLUGIN_URL . '/res/img/' .$img.'" title="'.$alt.'" alt="'.$alt.'" width="16" height="16" />';
                                }else{
                                    $link = admin_url('admin.php?page='.WPML_TM_FOLDER.'/menu/translations-queue.php&job_id='.$job_id);
                                }
                            }else{
                                $qs = array();
                                if(!empty($_SERVER['QUERY_STRING']))
                                foreach($_exp = explode('&', $_SERVER['QUERY_STRING']) as $q=>$qv){
                                    $__exp = explode('=', $qv);
                                    $__exp[0] = preg_replace('#\[(.*)\]#', '', $__exp[0]);
                                    if(!in_array($__exp[0], array('icl_tm_action', 'translate_from', 'translate_to', 'iclpost', 'service', 'iclnonce'))){
                                        $qs[$q] = $qv;
                                    }
                                }
                                $link = admin_url('edit.php?'.join('&', $qs).'&icl_tm_action=send_jobs&translate_from='.$src_lang
                                    .'&translate_to['.$v['code'].']=1&iclpost[]='.$id
                                    .'&service=icanlocalize&iclnonce=' . wp_create_nonce('pro-translation-icl'));

                            }
                        }else{
                            $link = false;
                            $alt = sprintf(__('Get %s translators','sitepress'), $v['display_name']);
                            $img = 'add_translators.png';
                            echo $this->create_icl_popup_link("@select-translators;{$src_lang};{$v['code']}@",
                                array(
                                    'ar'=>1,
                                    'title'=>$alt,
                                    'unload_cb' => 'icl_pt_reload_translation_box'
                                )
                            ) . '<img style="padding:1px;margin:2px;" border="0" src="'.ICL_PLUGIN_URL . '/res/img/' .$img.'" alt="'.$alt.'" width="16" height="16" />' . '</a>';
                        }
                        break;
                    default:                        
                        $link = 'post-new.php?post_type='.$post_type.'&trid='
                            . $__management_columns_posts_translations[$id][$src_lang]->trid.'&amp;lang='.$v['code'].'&amp;source_lang=' . $src_lang;
                        
                }
            }
            if($link){
                echo '<a href="'.$link.'" title="'.$alt.'">';
                echo '<img style="padding:1px;margin:2px;" border="0" src="'.ICL_PLUGIN_URL . '/res/img/' .$img.'" alt="'.$alt.'" width="16" height="16" />';
                echo '</a>';
            }
        }
    }

    function __set_posts_management_column_width(){
        $w = 22 * count($this->get_active_languages());
        echo '<style type="text/css">.column-icl_translations{width:'.$w.'px;}.column-icl_translations img{margin:2px;}</style>';
    }

    function display_wpml_footer(){
        if($this->settings['promote_wpml']){

            $wpml_in_other_langs = array('es','de','ja','zh-hans');
            $cl = in_array(ICL_LANGUAGE_CODE, $wpml_in_other_langs) ? ICL_LANGUAGE_CODE . '/' : '';

            $wpml_in_other_langs_icl = array('es','fr','de');
            $cl_icl = in_array(ICL_LANGUAGE_CODE, $wpml_in_other_langs_icl) ? ICL_LANGUAGE_CODE . '/' : '';

            if(in_array(ICL_LANGUAGE_CODE, array('ja', 'zh-hans', 'zh-hant'))){
                echo '<p id="wpml_credit_footer">' .
                        sprintf(__('<a href="%s">Multilingual WordPress</a> by <a href="%s">ICanLocalize</a>', 'sitepress'),
                        'http://www.icanlocalize.com/site/'.$cl_icl, 'http://wpml.org/'.$cl) . '</p>';
            }else{
                echo '<p id="wpml_credit_footer">' .
                        sprintf(__('<a href="%s">Multilingual WordPress</a> by <a href="%s">ICanLocalize</a>', 'sitepress'),
                        'http://wpml.org/'.$cl, 'http://www.icanlocalize.com/site/'.$cl_icl) . '</p>';
            }

            /*
            $footers = array(
                '1' => __('Multilingual thanks to WPML', 'sitepress'),
                '2' => __('Multilingual WordPress by WPML', 'sitepress'),
                '3' => __('Translated with WPML', 'sitepress'),
                '4' => __('Translating with WPML', 'sitepress'),
                '5' => __('We translate using WPML', 'sitepress'),
            );

            if(!isset($this->settings['promote_wpml_footer_version'])){
                $iclsettings['promote_wpml_footer_version'] = $this->settings['promote_wpml_footer_version'] = rand(1,5);
                $this->save_settings($iclsettings);
            }

            $wpml_in_other_langs = array('es','de','ja','zh-hans');
            $cl = in_array(ICL_LANGUAGE_CODE, $wpml_in_other_langs) ? ICL_LANGUAGE_CODE . '/' : '';
            echo '<p id="wpml_credit_footer"><a href="http://wpml.org/'.$cl.'">' . $footers[$this->settings['promote_wpml_footer_version']] . '</a></p>';
            */
        }
    }

    function xmlrpc_methods($methods){
        $methods['icanlocalize.get_languages_list'] = array($this, 'xmlrpc_get_languages_list');
        return $methods;
    }

    function xmlrpc_call_actions($action){
        global $HTTP_RAW_POST_DATA, $wpdb;
        $params = icl_xml2array($HTTP_RAW_POST_DATA);
        switch($action){
            case 'wp.getPage':
            case 'blogger.getPost': // yet this doesn't return custom fields
                if(isset($params['methodCall']['params']['param'][1]['value']['int']['value'])){
                    $page_id = $params['methodCall']['params']['param'][1]['value']['int']['value'];
                    $lang_details = $this->get_element_language_details($page_id, 'post_post');
                    update_post_meta($page_id, '_wpml_language', $lang_details->language_code);
                    update_post_meta($page_id, '_wpml_trid', $lang_details->trid);
                    $active_languages = $this->get_active_languages();
                    $res = $this->get_element_translations($lang_details->trid);
                    $translations = array();
                    foreach($active_languages as $k=>$v){
                        if($page_id != $res[$k]->element_id){
                            $translations[$k] = isset($res[$k]->element_id) ? $res[$k]->element_id : 0;
                        }
                    }
                    update_post_meta($page_id, '_wpml_translations', json_encode($translations));
                }
                break;
            case 'metaWeblog.getPost':
                if(isset($params['methodCall']['params']['param'][0]['value']['int']['value'])){
                    $page_id = $params['methodCall']['params']['param'][0]['value']['int']['value'];
                    $lang_details = $this->get_element_language_details($page_id, 'post_post');
                    update_post_meta($page_id, '_wpml_language', $lang_details->language_code);
                    update_post_meta($page_id, '_wpml_trid', $lang_details->trid);
                    $active_languages = $this->get_active_languages();
                    $res = $this->get_element_translations($lang_details->trid);
                    $translations = array();
                    foreach($active_languages as $k=>$v){
                        if($page_id != $res[$k]->element_id){
                            $translations[$k] = isset($res[$k]->element_id) ? $res[$k]->element_id : 0;
                        }
                    }
                    update_post_meta($page_id, '_wpml_translations', json_encode($translations));
                }
                break;
            case 'metaWeblog.getRecentPosts':
                $num_posts = intval($params['methodCall']['params']['param'][3]['value']['int']['value']);
                if($num_posts){
                    $posts = get_posts('suppress_filters=false&numberposts='.$num_posts);
                    foreach($posts as $p){
                        $lang_details = $this->get_element_language_details($p->ID, 'post_post');
                        update_post_meta($p->ID, '_wpml_language', $lang_details->language_code);
                        update_post_meta($p->ID, '_wpml_trid', $lang_details->trid);
                        $active_languages = $this->get_active_languages();
                        $res = $this->get_element_translations($lang_details->trid);
                        $translations = array();
                        foreach($active_languages as $k=>$v){
                            if($p->ID != $res[$k]->element_id){
                                $translations[$k] = isset($res[$k]->element_id) ? $res[$k]->element_id : 0;
                            }
                        }
                        update_post_meta($p->ID, '_wpml_translations', json_encode($translations));
                    }
                }
                break;

            case 'metaWeblog.newPost':
                $custom_fields = $params['methodCall']['params']['param'][3]['value']['struct']['member'][3]['value']['array']['data']['value'];
                if(is_array($custom_fields)){
                    foreach($custom_fields as $cf){
                        if($cf['struct']['member'][0]['value']['string']['value'] == '_wpml_language'){
                            $icl_post_language = $cf['struct']['member'][1]['value']['string']['value'];
                        }elseif($cf['struct']['member'][0]['value']['string']['value'] == '_wpml_trid'){
                            $icl_trid = $cf['struct']['member'][1]['value']['string']['value'];
                        }
                    }
                    if($icl_trid && $icl_post_language &&
                        !$wpdb->get_var("SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE element_type='post_post'
                            AND trid={$icl_trid} AND language_code='{$icl_post_language}'")){
                        $_POST['icl_post_language'] = $icl_post_language;
                        $_POST['icl_trid']          = $icl_trid;
                    }else{
                        $IXR_Error = new IXR_Error( 401, __('A translation for this post already exists', 'sitepress') );
                        echo $IXR_Error->getXml();
                        exit(1);
                    }
                }
                break;
            case 'metaWeblog.editPost':
                $post_id = $params['methodCall']['params']['param'][0]['value']['int']['value'];
                if(!$post_id){
                    break;
                }
                $custom_fields = $params['methodCall']['params']['param'][3]['value']['struct']['member'][3]['value']['array']['data']['value'];
                if(is_array($custom_fields)){
                    foreach($custom_fields as $cf){
                        if($cf['struct']['member'][0]['value']['string']['value'] == '_wpml_language'){
                            $icl_post_language = $cf['struct']['member'][1]['value']['string']['value'];
                        }elseif($cf['struct']['member'][0]['value']['string']['value'] == '_wpml_trid'){
                            $icl_trid = $cf['struct']['member'][1]['value']['string']['value'];
                        }
                    }

                    $epost_id = $wpdb->get_var("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type='post_post'
                        AND trid={$icl_trid} AND language_code='{$icl_post_language}'");
                    if($icl_trid && $icl_post_language && (!$epost_id || $epost_id == $post_id)){
                        $_POST['icl_post_language'] = $icl_post_language;
                        $_POST['icl_trid']          = $icl_trid;
                    }else{
                        $IXR_Error = new IXR_Error( 401, __('A translation in this language already exists', 'sitepress') );
                        echo $IXR_Error->getXml();
                        exit(1);
                    }
                }
                break;
        }
    }

    function xmlrpc_get_languages_list($lang){
        global $wpdb;
        if(!is_null($lang)){
            if(!$wpdb->get_var("SELECT code FROM {$wpdb->prefix}icl_languages WHERE code='".mysql_real_escape_string($lang)."'")){
                $IXR_Error = new IXR_Error( 401, __('Invalid language code', 'sitepress') );
                echo $IXR_Error->getXml();
                exit(1);
            }
            $this->admin_language = $lang;
        }
        define('WP_ADMIN', true); // hack - allow to force display language
        $active_languages = $this->get_active_languages(true);
        return $active_languages;

    }

    function get_current_action_step() {
        global $wpdb;

        $icl_lang_status = $this->settings['icl_lang_status'];
        $has_translators = false;
        foreach((array)$icl_lang_status as $k => $lang){
            if(!is_numeric($k)) continue;
            if(!empty($lang['translators'])){
                $has_translators = true;
                break;
            }
        }
        if(!$has_translators){ return 0; }

        $cms_count = $wpdb->get_var("SELECT COUNT(rid) FROM {$wpdb->prefix}icl_core_status WHERE status=3");

        if($cms_count > 0) {
            return 4;
        }
        $cms_count = $wpdb->get_var("SELECT COUNT(rid) FROM {$wpdb->prefix}icl_core_status WHERE 1");
        if($cms_count == 0) {
            // No documents sent yet
            return 1;
        }

        if ($this->settings['icl_balance'] <= 0) {
            return 2;
        }


        return 3;

    }

    function show_action_list() {
        $steps = array(__('Select translators', 'sitepress'),
                        __('Send documents to translation', 'sitepress'),
                        __('Deposit payment', 'sitepress'),
                        __('Translations will be returned to your site', 'sitepress'));

        $current_step = $this->get_current_action_step();
        if ($current_step >= sizeof($steps)) {
            // everything is already setup.
            if ($this->settings['last_action_step_shown']) {
                return '';
            } else {
                $this->save_settings(array('last_action_step_shown' => 1));
            }
        }

        $output = '
            <h3>' . __('Setup check list', 'sitepress') . '</h3>
            <ul id="icl_check_list">';

        foreach($steps as $index => $step) {
            $step_data = $step;

            if ($index < $current_step || ($index == 4 && $this->settings['icl_balance'] > 0)) {
                $attr = ' class="icl_tick"';
            } else {
                $attr = ' class="icl_next_step"';
            }

            if ($index == $current_step) {
                $output .= '<li class="icl_info"><b>' . $step_data . '</b></li>';
            } else {
                $output .= '<li' . $attr. '>' . $step_data . '</li>';
            }
            $output .= "\n";
        }

        $output .= '
            </ul>';

        return $output;
    }

    function show_pro_sidebar() {
        $output = '<div id="icl_sidebar" class="icl_sidebar" style="display:none">';

        $action_list = $this->show_action_list();
        $show_minimized = $this->settings['icl_sidebar_minimized'];
        if ($action_list != '') {
            $show_minimized = false;
        }

        if ($show_minimized) {
            $output .= '<div id="icl_sidebar_full" style="display:none">';
        } else {
            $output .= '<div id="icl_sidebar_full">';
        }

        if ($action_list == '') {
            $output .= '<a id="icl_sidebar_hide" href="#">hide</a>';
        } else {
            $output .= $action_list;
        }

        $output .= '<h3>' . __('Help', 'sitepress') . '</h3>';
        $output .= '<div id="icl_help_links"></div>';
        $output .= '</div>';
        if ($show_minimized) {
            $output .= '<div id="icl_sidebar_hide_div">';
        } else {
            $output .= '<div id="icl_sidebar_hide_div" style="display:none">';
        }
        $output .= '<a id="icl_sidebar_show" href="#"><img width="16" height="16" src="' . ICL_PLUGIN_URL . '/res/img/question1.png' . '" alt="'.__('Get help','sitepress').'" title="'.__('Get help','sitepress').'" /></a>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;

    }

    function meta_generator_tag(){
        $lids = array();
        foreach($this->get_active_languages() as $l){
            $lids[] = $l['id'];
        }
        $stt = join(",",$lids);
        $stt .= ";" . intval($this->get_icl_translation_enabled());
        printf('<meta name="generator" content="WPML ver:%s stt:%s" />' . PHP_EOL, ICL_SITEPRESS_VERSION, $stt);
    }

    function set_language_cookie(){
        if (!headers_sent()){
            if(preg_match('@\.(css|js|png|jpg|gif|jpeg|bmp)@i',basename(preg_replace('@\?.*$@','',$_SERVER['REQUEST_URI']))) ||
                isset($_POST['icl_ajx_action']) || isset($_POST['_ajax_nonce'])){
                return;
            }

            $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : $_SERVER['HTTP_HOST'];
            $cookie_path = defined('COOKIEPATH') ? COOKIEPATH : '/';
            setcookie('_icl_current_language', $this->get_current_language(), time()+86400, $cookie_path, $cookie_domain);
        }
    }

    function get_language_cookie(){
        if(isset($_COOKIE['_icl_current_language'])){
            $lang = $_COOKIE['_icl_current_language'];
            $active_languages = $this->get_active_languages();
            if(!isset($active_languages[$lang])){
                $lang = $this->get_default_language();
            }
        }else{
            $lang = '';
        }
        return $lang;
    }

    // _icl_current_language will have to be replaced with _icl_current_language
    function set_admin_language_cookie($lang = false){
        if (!headers_sent()){
            if(preg_match('@\.(css|js|png|jpg|gif|jpeg|bmp)@i',basename(preg_replace('@\?.*$@','',$_SERVER['REQUEST_URI']))) ||
                isset($_POST['icl_ajx_action']) || isset($_POST['_ajax_nonce'])){
                return;
            }

            $parts = parse_url(admin_url());
            $cookie_path = $parts['path'];
            if($lang === false) $lang = $this->get_current_language();
            setcookie('_icl_current_admin_language', $lang, time()+7200, $cookie_path);            
        }
    }
    
    function get_admin_language_cookie(){
        if(isset($_COOKIE['_icl_current_admin_language'])){
            $lang = $_COOKIE['_icl_current_admin_language'];
            $active_languages = $this->get_active_languages();
            if(!isset($active_languages[$lang]) && $lang != 'all'){
                $lang = $this->get_default_language();
            }
        }else{
            $lang = '';
        }
        return $lang;
    }
        
    function reset_admin_language_cookie(){
        $this->set_admin_language_cookie($this->get_default_language());    
    }
    
    function rewrite_rules_filter($value){
        foreach((array)$value as $k=>$v){
            $value[$this->get_current_language().'/'.$k] = $v;
            unset($value[$k]);
        }
        $value[$this->get_current_language()] = 'index.php';
        return $value;
    }

    function is_rtl(){
        if(is_admin()){
            return in_array($this->get_admin_language(), array('ar','he','fa'));
        }else{
            return in_array($this->get_current_language(), array('ar','he','fa'));
        }
    }

    function get_translatable_documents($include_not_synced = false){
        global $wp_post_types;
        $icl_post_types = array();
        foreach($wp_post_types as $k=>$v){
            if(!in_array($k, array('attachment','revision','nav_menu_item'))){
                if(!$include_not_synced &&
                    (empty($this->settings['custom_posts_sync_option'][$k]) || $this->settings['custom_posts_sync_option'][$k] != 1) && !in_array($k, array('post','page'))) continue;
                $icl_post_types[$k] = $v;
                /* preWP3 compatibility  - start */
                if(ICL_PRE_WP3){
                    if(is_array($v->labels)){
                        $icl_post_types[$k]->labels = (object) $icl_post_types[$k]->labels;
                    }
                }
                /* preWP3 compatibility  - end */
            }
        }
        /* preWP3 compatibility  - start */
        if(ICL_PRE_WP3){
            $icl_post_types['post']->labels->singular_name = 'Post';
            $icl_post_types['post']->labels->name = 'Posts';
            $icl_post_types['page']->labels->singular_name = 'Page';
            $icl_post_types['page']->labels->name = 'Pages';
        }
        /* preWP3 compatibility  - end */
        $icl_post_types = apply_filters('get_translatable_documents', $icl_post_types);
        return $icl_post_types;
    }

    function get_translatable_taxonomies($include_not_synced = false, $object_type = 'post'){
        global $wp_taxonomies;
        $t_taxonomies = array();
        if($include_not_synced){
            if(in_array($object_type, $wp_taxonomies['post_tag']->object_type)) $t_taxonomies[] = 'post_tag';
            if(in_array($object_type, $wp_taxonomies['category']->object_type)) $t_taxonomies[] = 'category';
        }
        foreach($wp_taxonomies as $taxonomy_name => $taxonomy){
            // exceptions
            if('post_format' == $taxonomy_name) continue;
            if(in_array($object_type, $taxonomy->object_type) && !empty($this->settings['taxonomies_sync_option'][$taxonomy_name])){
                $t_taxonomies[] = $taxonomy_name;
            }
        }
        
        if(has_filter('get_translatable_taxonomies')){
            $filtered = apply_filters('get_translatable_taxonomies', array('taxs'=>$t_taxonomies, 'object_type'=>$object_type));
            $t_taxonomies = $filtered['taxs'];
            $ot = $filtered['object_type']; 
            if(empty($t_taxonomies)) $t_taxonomies = array();
        }

        return $t_taxonomies;
    }

    function is_translated_taxonomy($tax){
        
        switch($tax){
            case 'category':
            case 'post_tag':
                $ret = true;
                break;
            default:
                if(isset($this->settings['taxonomies_sync_option'][$tax])){
                    $ret = $this->settings['taxonomies_sync_option'][$tax];
                }elseif(isset($this->settings['translation-management']['taxonomies_readonly_config'][$tax]) 
                        && $this->settings['translation-management']['taxonomies_readonly_config'][$tax] == 1){
                    $ret = true;
                }else{
                    $ret = false;
                }
        }
        return $ret;
    }
    
    function is_translated_post_type($type){ 
        
        switch($type){
            case 'post':
            case 'page':
                $ret = true;
                break;
            default:
                if(isset($this->settings['custom_posts_sync_option'][$type])){
                    $ret = $this->settings['custom_posts_sync_option'][$type];
                }elseif(isset($this->settings['translation-management']['custom_types_readonly_config'][$type])){
                    $ret = $this->settings['translation-management']['custom_types_readonly_config'][$type];
                }else{
                    $ret = false;
                }
        }
        return $ret;
    }

    function print_translatable_custom_content_status(){
        global $wp_taxonomies;
        $icl_post_types = $this->get_translatable_documents(true);
        $cposts = array();
        $notice = '';
        foreach ($icl_post_types as $k => $v) {
            if (!in_array($k, array('post', 'page'))) {
                $cposts[$k] = $v;
            }
        }
        foreach ($cposts as $k => $cpost) {
            if (!isset($this->settings['custom_posts_sync_option'][$k])) {
                $cposts_sync_not_set[] = $cpost->labels->name;
            }
        }
        if (defined('WPML_TM_VERSION') && !empty($cposts_sync_not_set)) {
            $notice = '<p class="updated fade">';
            $notice .= sprintf(__("You haven't set your <a %s>synchronization preferences</a> for these custom posts: %s. Default value was selected.", 'sitepress'),
                            'href="admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&sm=mcsetup"', '<i>' . join('</i>, <i>', $cposts_sync_not_set) . '</i>');
            $notice .= '</p>';
        }

        $ctaxonomies = array_diff(array_keys((array) $wp_taxonomies), array('post_tag', 'category', 'nav_menu', 'link_category', 'post_format'));
        foreach ($ctaxonomies as $ctax) {
            if (!isset($this->settings['taxonomies_sync_option'][$ctax])) {
                $tax_sync_not_set[] = $wp_taxonomies[$ctax]->label;
            }
        }
        if (defined('WPML_TM_VERSION') && !empty($tax_sync_not_set)) {
            $notice .= '<p class="updated">';
            $notice .= sprintf(__("You haven't set your <a %s>synchronization preferences</a> for these taxonomies: %s. Default value was selected.", 'sitepress'),
                            'href="admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&sm=mcsetup"', '<i>' . join('</i>, <i>', $tax_sync_not_set) . '</i>');
            $notice .= '</p>';
        }

        echo $notice;
    }

    function dashboard_widget_setup(){
        if (current_user_can('manage_options')) {
            $dashboard_widgets_order = (array)get_user_option( "meta-box-order_dashboard" );
            $icl_dashboard_widget_id = 'icl_dashboard_widget';
            $all_widgets = array();
            foreach($dashboard_widgets_order as $k=>$v){
                $all_widgets = array_merge($all_widgets, explode(',', $v));
            }
            if(!in_array($icl_dashboard_widget_id, $all_widgets)){
                $install = true;
            }else{$install = false;}
            wp_add_dashboard_widget($icl_dashboard_widget_id, sprintf(__('Multi-language | WPML %s', 'sitepress'),ICL_SITEPRESS_VERSION), array($this, 'dashboard_widget'), null);
            if($install){
                $dashboard_widgets_order['side'] = $icl_dashboard_widget_id . ',' . @strval($dashboard_widgets_order['side']);
                $user = wp_get_current_user();
                update_user_option($user->ID, 'meta-box-order_dashboard', $dashboard_widgets_order, true);
                /* preWP3 compatibility  - start */
                if(ICL_PRE_WP3){
                    // bug with WP 2.9 reading the correct data after update_user_option
                    header("Location: index.php");
                    exit;
                }
                /* preWP3 compatibility  - end   */
            }
        }
    }

    function dashboard_widget(){
        do_action('icl_dashboard_widget_notices');
        include_once ICL_PLUGIN_PATH . '/menu/dashboard-widget.php';
    }

    function verify_post_translations($post_type){
        global $wpdb;
        $post_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='{$post_type}' AND post_status <> 'auto-draft'");
        if(!empty($post_ids)){
            foreach($post_ids as $id){
                $translation_id = $wpdb->get_var("SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE element_id='{$id}' AND element_type='post_{$post_type}'");
                if(!$translation_id){
                    $this->set_element_language_details($id, 'post_' . $post_type , false, $this->get_default_language());
                }
            }
        }
    }

    function verify_taxonomy_translations($taxonomy){
        global $wpdb;
        $element_ids = $wpdb->get_col("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy='{$taxonomy}'");
        if(!empty($element_ids)){
            foreach($element_ids as $id){
                $translation_id = $wpdb->get_var("SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE element_id='{$id}' AND element_type='tax_{$taxonomy}'");
                if(!$translation_id){
                    $this->set_element_language_details($id, 'tax_' . $taxonomy , false, $this->get_default_language());
                }
            }
        }
    }

    function warn_permalink_structure(){
        global $pagenow;
        if($pagenow == 'options-permalink.php'):
        ?>
        <div class="error message fade">
            <p><?php printf(__("This permalink format can cause problems with translations. See <a href=%s>WPML's minimum requirement page</a>.",
                'sitepress'), '"http://wpml.org/?page_id=716"') ?></p>
        </div>
        <?php
        else:
        ?>
        <div class="icl_form_errors" style="width: 98%;">
            <p><?php printf(__("The current permalink format can cause problems with translations. See <a href=%s>WPML's minimum requirement page</a>.",
                'sitepress'), '"http://wpml.org/?page_id=716"') ?></p>
        </div>
        <?php
        endif;
    }
    
    function copy_from_original(){
        global $wpdb;
        $show = false;
        
        $disabled = '';
        if(isset($_GET['source_lang']) && isset($_GET['trid'])){            
            $source_lang = $_GET['source_lang'];
            $trid = $_GET['trid'];
            $_lang_details = $this->get_language_details($source_lang);
            $source_lang_name = $_lang_details['display_name'];
            $show = true;
            
        }elseif(isset($_GET['post']) && isset($_GET['lang']) && $_GET['lang'] != $this->get_default_language()){
            global $post;
            
            if(trim($post->post_content)){
                $disabled = ' disabled="disabled"';    
            }
            
            $trid = $this->get_element_trid($post->ID, 'post_'. $post->post_type);
            $source_lang = $wpdb->get_var($wpdb->prepare("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE source_language_code IS NULL AND trid=%d", $trid));
            $_lang_details = $this->get_language_details($source_lang);
            $source_lang_name = $_lang_details['display_name'];
            
            $show = true;
        }
        
        if($show){
            echo '<input id="icl_cfo" class="button-secondary" type="button" value="' . sprintf(__('Copy content from %s', 'sitepress'), $source_lang_name) .'" 
                onclick="icl_copy_from_original(\''.esc_js($source_lang).'\', \''.esc_js($trid).'\')"'.$disabled.'/>';
            if($disabled){
                echo '&nbsp;<i>';
                _e("Enabled only when there's no translation", 'sitepress');
                echo '</i>';
            }
        }
        
    }

    function wp_upgrade_locale($locale){
        if(defined('WPLANG') && WPLANG){
            $locale = WPLANG;
        }else{
            $locale = ICL_WP_UPDATE_LOCALE;
        }
        return $locale;
    }
    
    function admin_language_switcher(){
        global $pagenow, $wpdb;
        
        $all_langs_enabled = true;
        $current_page = basename($_SERVER['SCRIPT_NAME']);
        
        // individual translations
        $is_post = false;
        $is_tax = false;
        $is_menu = false;
        switch($pagenow){
            case 'post.php':
                $is_post = true;
                $all_langs_enabled = false;
                $post_id =  @intval($_GET['post']);
                $post = get_post($post_id);
                
                $trid = $this->get_element_trid($post_id, 'post_' . $post->post_type);
                $translations = $this->get_element_translations($trid, 'post_' . $post->post_type, true);

                break;
            case 'post-new.php':
                $all_langs_enabled = false;
                if(isset($_GET['trid'])){
                    $trid = $_GET['trid'];
                    $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : 'post';
                    $translations = $this->get_element_translations($trid, 'post_' . $post_type, true);                    
                    $is_post = true;
                }
                break;
            case 'edit-tags.php':
                $is_tax = true;
                if(isset($_GET['action']) && $_GET['action']=='edit'){
                    $all_langs_enabled = false;
                }  
                $term_id = @intval($_GET['tag_ID']);  
                $taxonomy = $_GET['taxonomy'];
                $term_tax_id = $wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy=%s AND term_id=%d", $taxonomy, $term_id));

                $trid = $this->get_element_trid($term_tax_id, 'tax_' . $taxonomy);
                $translations = $this->get_element_translations($trid, 'tax_' . $taxonomy, true);
                
                break;
            case 'nav-menus.php':
                $is_menu = true;
                if(isset($_GET['menu']) && $_GET['menu']){
                     $menu_id = $_GET['menu'];
                     $trid = $trid = $this->get_element_trid($menu_id, 'tax_nav_menu');
                     $translations = $this->get_element_translations($trid, 'tax_nav_menu', true);
                }
                $all_langs_enabled = false;
                break;
            
        }
        
        
        
        foreach($this->get_active_languages() as $lang){
            
            $current_page_lang = $current_page;
            
            parse_str($_SERVER['QUERY_STRING'], $query_vars);
            unset($query_vars['lang'], $query_vars['admin_bar']);
            
            // individual translations
            if($is_post){
                if(isset($translations[$lang['code']]) && isset($translations[$lang['code']]->element_id)){
                    $query_vars['post'] = $translations[$lang['code']]->element_id;    
                    unset($query_vars['source_lang']);
                    $current_page_lang = 'post.php';  
                    $query_vars['action'] = 'edit';
                }else{
                    $current_page_lang = 'post-new.php';  
                    if(isset($post)){
                        $query_vars['post_type'] = $post->post_type;
                        $query_vars['source_lang'] = $this->get_current_language();
                    }else{
                        $query_vars['post_type'] = $post_type;                            
                    }  
                    $query_vars['trid'] = $trid;
                    unset($query_vars['post'], $query_vars['action']);
                }
            }elseif($is_tax){
                if(isset($translations[$lang['code']]) && isset($translations[$lang['code']]->element_id)){
                    $query_vars['tag_ID'] = $translations[$lang['code']]->element_id;    
                }else{
                    $query_vars['trid'] = $trid;
                    $query_vars['source_lang'] = $this->get_current_language();
                    unset($query_vars['tag_ID'], $query_vars['action']);
                }
            }elseif($is_menu){
                if(!empty($menu_id)){
                    if(isset($translations[$lang['code']]->element_id)){
                        $query_vars['menu'] = $translations[$lang['code']]->element_id;        
                    }else{
                        $query_vars['menu'] = 0;
                        $query_vars['trid'] = $trid;
                        $query_vars['action'] = 'edit';
                    }
                }
            }
            
            $query_string = http_build_query($query_vars);
            
            $query = '?';
            if(!empty($query_string)){
                $query .= $query_string . '&';     
            }    
            $query .= 'lang=' . $lang['code']; // the default language need to specified explictly yoo in order to set the lang cookie
            
            
            $linkurl = admin_url($current_page_lang . $query);
            
            $flag = $this->get_flag($lang['code']);

            if($flag->from_template){
                $wp_upload_dir = wp_upload_dir();
                $flag_url = $wp_upload_dir['baseurl'] . '/flags/' . $flag->flag;
            }else{
                $flag_url = ICL_PLUGIN_URL . '/res/flags/'.$flag->flag;
            }
            
            $langlinks[$lang['code']] = array(
                'url'       => $linkurl . '&admin_bar=1',
                'current'   => $lang['code'] == $this->get_current_language(),
                'anchor'    => $lang['display_name'],
                'flag'      => '<img class="admin_iclflag" src="'.$flag_url.'" alt="'.$lang['code'].'" width="18" height="12" />'        
            );
            
        }

        if($all_langs_enabled){
            $query = '?';
            if(!empty($query_string)){
                $query .= $query_string . '&';     
            }    
            $query .= 'lang=all'; 
            $linkurl = admin_url(basename($_SERVER['SCRIPT_NAME']) . $query);
           
            $langlinks['all'] = array(
                'url'       => $linkurl,
                'current'   => 'all' == $this->get_current_language(),
                'anchor'    => __('All languages', 'sitepress'),
                'flag'      => '<img class="admin_iclflag" src="'.ICL_PLUGIN_URL.'/res/img/icon16.png" alt="all" width="16" height="16" />'    
            );
        }else{
            // set the default language as current
            if('all' == $this->get_current_language()){
                $langlinks[$this->get_default_language()]['current'] = true;    
            }
        }
        
        include ICL_PLUGIN_PATH . '/menu/admin-language-switcher.php';
    }

}