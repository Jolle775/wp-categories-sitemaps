<?php
/**
 * Plugin Name:     Category Sitemaps
 * Plugin URI:      www.3ele.de
 * Description:     Create a Sitemap per Category
 * Author:          Sebastian Weiss
 * Author URI:      www.3ele.de
 * Text Domain:     category-sitemaps
 * Domain Path:     /languages
 * Version:         1.1.0
 *
 * @package         Category_Sitemaps
 */

// create XML files in root
defined( 'ABSPATH' ) or die( 'Are you ok?' );

$xml_sitemap_creator = new XMLSitemapCreator();

class XMLSitemapCreator {
    public function __construct() {

        /* register and activation wp-cron */
        register_activation_hook( __FILE__, array( $this, 'cts_update_xml_files_activate' ) );
           
        /* deactivate wp-cron */
        register_deactivation_hook(__FILE__, array( $this, 'cts_update_xml_files_deactivation' ) ); 

        /* hook in actions */
       add_action( 'cts_update_xml_files', array( $this, 'cts_fetch_all_posts' ) );
        add_action( 'publish_post', array( $this,'cts_fetch_all_posts') );  
        add_action( 'save_post', array( $this,'cts_fetch_all_posts') ); 

	}

    // create files
    public function cts_create_file($filename,$content){
        if ($this->cts_check_file_writable($filename) == True) {
            $fp = fopen( ABSPATH . $filename."-sitemap.xml", 'w' );
            fwrite( $fp, $content );
            fclose( $fp );
            $path = ABSPATH . $filename."-sitemap.xml"; 
            return $path;
        }    
    }


    // check if file writable
    public function cts_check_file_writable($filename) {
        if (!is_writable($filename)) {
            if (!@chmod($filename, 0666)) {
                $dirname = dirname($filename);
                if (!is_writable($dirname)) {
                    if (!@chmod($dirname, 0666)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    // escape xml entities 
    public function cts_escape_xml_entities($content)
    {
        return str_replace(
            array('&', '<', '>', '\'', '"'),
            array('&amp;', '&lt;', '&gt;', '&apos;', '&quot;'),
            $content
        );
    }

    // check date from posts for news
    public function cts_check_post_time($post, $now){
        $postDate = strtotime($post->post_date);
        
        $twoDays = 2*24*60*60;
        if ($now - $postDate < $twoDays) {
            return True;
        }
    }
    public function cts_fetch_all_posts(){
        $now = time();
        $sitemaps =array();
        $news_posts = array();
        $post_images = array();
        $categories = get_categories( array(
            'hide_empty' => true,
            'exclude' => 1,
        )  );


        
        foreach ($categories as $category) {
        	$sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
        	$sitemap .= "\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            $update_time = date("Y-m-d");
            $sitemap .= "\n \t" . '<url>' . "\n" .
            "\t\t" . '<loc>' . get_category_link($category) . '</loc>' .
            "\n\t\t" . '<lastmod>' . $update_time . '</lastmod>' .
            "\n\t" . '</url>' . "\n";
            $posts_for_sitemap   = get_posts( array(
                'numberposts' => -1,
                'orderby' => 'modified',
                'order' => 'DESC',
                'category' => $category->ID,
                'post_status' => 'publish',    
                'post_type' => array( 'post' )
            ));
            foreach($posts_for_sitemap as $post ) {
                setup_postdata ( $post );
                $postdate = explode( " ", $post->post_modified );
                $sitemap .= "\t" . '<url>' . "\n" .
                    "\t\t" . '<loc>' . get_permalink( $post->ID ) . '</loc>' .
                    "\n\t\t" . '<lastmod>' . $postdate[0] . '</lastmod>' .
                    "\n\t" . '</url>' . "\n";

                if ($this->cts_check_post_time($post, $now) == True){
                    $news_posts[$post->ID] = $post;
                }

         
                    $args = array('post_type'=>'attachment','numberposts'=>null,'post_status'=>null, 'post_parent' => $post->ID);
                    $attachments = get_posts($args);
                     if($attachments){
                        $post_images[$post->ID] = $post;
                       }
                   
            }

            $sitemap .= '</urlset>';

            $filename = $category->name;

            /* create_categories_sitemaps */
            $sitemap = $this->cts_create_file($filename,$sitemap);
            $sitemaps[] = $sitemap;   

        }

        /* create_image_sitemap */
        $this->create_image_sitemap($post_images);  
	/* create_news_sitemap */
        $this->create_news_sitemap($news_posts);
        /* create_master_sitemap */
        $this->create_master_sitemap();   
    }


    public function create_news_sitemap($posts) {
        $filename = 'News';
        $publication_name = get_bloginfo('name');
        $publication_language = get_bloginfo('language');
        $xml_sitemap_google_news = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml_sitemap_google_news .= "\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">';
        foreach ($posts as $post): 
            $postdate = explode( " ", $post->post_modified );
            $xml_sitemap_google_news .= "\n \t".'<url>'."\n \t \t".'<loc>'.$this->cts_escape_xml_entities(get_permalink($post->ID)).'</loc>'."\n \t \t".'<news:news>'."\n \t \t \t".'<news:publication>'."\n \t \t \t \t".'<news:name>'.$publication_name.'</news:name>'."\n \t \t \t \t".'<news:language>'.$publication_language.'</news:language>'."\n \t \t \t".'</news:publication>';
            $xml_sitemap_google_news .= "\n \t \t \t".'<news:publication_date>'.$postdate[0].'</news:publication_date>'."\n \t \t \t".' <news:title>'.htmlspecialchars($post->post_title).'</news:title>'."\n \t \t ".'</news:news>'."\n \t".'</url>'."\n".'';
        endforeach;
        $xml_sitemap_google_news .= '</urlset>';
        $sitemap = $this->cts_create_file($filename,$xml_sitemap_google_news);
        return $sitemap;
    }

    public function create_image_sitemap($posts) {


        $filename = 'Image';
        $publication_name = get_bloginfo('name');
        $publication_language = get_bloginfo('language');
		$xml_sitemap_images  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml_sitemap_images .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        foreach ($posts as $post): 

  
            $postdate = explode( " ", $post->post_modified );
            $xml_sitemap_images .= "\t".'<url>'."\n \t \t".'<loc>'.$this->cts_escape_xml_entities(get_permalink($post->ID)).'</loc>';
            
            $args = array('post_type'=>'attachment','numberposts'=>null,'post_status'=>null, 'post_parent' => $post->ID);
            $attachments = get_posts($args);
             if($attachments){
            foreach ($attachments as $attachment){
                $xml_sitemap_images .="\n \t".'<image:image>'."\n \t \t \t \t".'<image:loc>'.$attachment->guid.'</image:loc>'."\n \t \t \t".'</image:image>';
                
            }
	//Syntax Failure, URL only when all Images from 1 Post are Done.
	 $xml_sitemap_images .= "\n \t".'</url>';
        }
        endforeach;
        $xml_sitemap_images .= '</urlset>';
        $sitemap = $this->cts_create_file($filename,$xml_sitemap_images);
        return $sitemap;
    }

    public function create_master_sitemap(){
	$url = get_bloginfo('url');  
        $files = glob('*sitemap.xml');
        $update_time = date('Y-m-d');
        $filename = "Master";
        $master_sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
        $master_sitemap .= "\n ".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach($files as $sitemap):
            if($sitemap !='Master-sitemap.xml'): 
                $master_sitemap .="\n \t ".'<sitemap>';
                $master_sitemap .="\n \t  \t".'<loc>'.$url.'/'.$sitemap.'</loc>';
                $master_sitemap .="\n \t  \t".'<lastmod>'.$update_time.'</lastmod>';
                $master_sitemap .="\n  \t".'</sitemap>';  
            endif;    
        endforeach;   
        $master_sitemap.= "\n \t".'</sitemapindex>';
        
        $sitemap = $this->cts_create_file($filename,$master_sitemap);
    }

    public function cts_update_xml_files_activate(){
        if (! wp_next_scheduled ( 'cts_update_xml_files' )) {
            wp_schedule_event( time(), 'daily', 'cts_update_xml_files' );
        }
    }

    public function cts_update_xml_files_deactivation(){
        if ( wp_next_scheduled( 'cts_update_xml_files' ) ) {
            wp_clear_scheduled_hook( 'cts_update_xml_files' );
        }
    }

}

