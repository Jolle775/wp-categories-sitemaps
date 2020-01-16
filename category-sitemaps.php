<?php
/**
 * Plugin Name:     Category Sitemaps
 * Plugin URI:      www.3ele.de
 * Description:     PLUGIN DESCRIPTION HERE
 * Author:          Sebastian Weiss
 * Author URI:      www.3ele.de
 * Text Domain:     category-sitemaps
 * Domain Path:     /languages
 * Version:         0.1.0
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
     /*   add_action( 'publish_post', array( $this,'cts_fetch_all_posts') );  */
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

        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
        $sitemap .= "/n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        foreach ($categories as $category) {
            $update_time = date("Y-m-d");
            $sitemap .= "\t" . '<url>' . "\n" .
            "\t\t" . '<loc>/' . get_category_link($category) . '</loc>' .
            "\n\t\t" . '<lastmod>' . $update_time . '</lastmod>' .
            "\n\t" . '</url>' . "\n";

            $posts_for_sitemap = get_posts( array(
                'numberposts' => -1,
                'orderby' => 'modified',
                'order' => 'DESC',
                'category' => $category->ID,
                'post_status' => 'publish',    
                'post_type' => array( 'post' )
            ));

            foreach( $posts_for_sitemap as $post ) {
                setup_postdata( $post );
                $postdate = explode( " ", $post->post_modified );
                $sitemap .= "\t" . '<url>' . "\n" .
                    "\t\t" . '<loc>' . get_permalink( $post->ID ) . '</loc>' .
                    "\n\t\t" . '<lastmod>' . $postdate[0] . '</lastmod>' .
                    "\n\t" . '</url>' . "\n";

                if ($this->cts_check_post_time($post, $now) == True){
                    $news_posts[$post->ID] = $post;
                }

                $filtered_mime_types = array();

                    foreach( get_allowed_mime_types() as $key => $type ):
                        if( false === strpos( $type, 'image' ) )
                            $filtered_mime_types[] = $type;
                    endforeach;

                    $args = array(
                        'post_type' => array( 'attachment' ),
                        'posts_per_page' => -1,
                        'post_status' => 'any',
                        'post_parent' => $post->ID,

                        'post_mime_type' => implode( ',', $filtered_mime_types )
                    );
                    $results =  new WP_Query( $args );
                    if ($results->have_posts()){
                        $post_images[$post->ID] = $post;
                    }
            }

            $sitemap .= '</urlset>';

            $filename = $category->name;

            /* create_categories_sitemaps */
            $sitemap = $this->cts_create_file($filename,$sitemap);
            $sitemaps[] = $sitemap;   

        }

        /* create_news_sitemap */
        $this->create_news_sitemap($news_posts);
        /* create_image_sitemap */
        $this->create_image_sitemap($post_images);  
        /* create_master_sitemap */
        $this->create_master_sitemap($sitemaps);   
    }


    public function create_news_sitemap($posts) {
        $filename = 'news';
        $publication_name = get_bloginfo('name');
        $publication_language = get_bloginfo('language');
        $xml_sitemap_google_news = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml_sitemap_google_news .= "\n".'<urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9" xmlns:n="https://www.google.com/schemas/sitemap-news/0.9">';
        foreach ($posts as $post): 
            $postdate = explode( " ", $post->post_modified );
            $xml_sitemap_google_news .= "\n \t".'<url>'."\n \t \t".'<loc>'.$this->cts_escape_xml_entities(get_permalink($post->ID)).'</loc>'."\n \t \t".'<n:news>'."\n \t \t \t".'<n:publication>'."\n \t \t \t \t".'<n:name>'.$publication_name.'</n:name>'."\n \t \t \t \t".'<n:language>'.$publication_language.'</n:language>'."\n \t \t \t".'</n:publication>';
            $xml_sitemap_google_news .= "\n \t \t \t".'<n:publication_date>'.$postdate[0].'</n:publication_date>'."\n \t \t \t".' <n:title>'.htmlspecialchars($post->post_title).'</n:title>'."\n \t \t ".'</n:news>'."\n \t".'</url>'."\n".'';
        endforeach;
        $xml_sitemap_google_news .= '</urlset>';
        $sitemap = $this->cts_create_file($filename,$xml_sitemap_google_news);
        return $sitemap;
    }

    public function create_image_sitemap($posts) {

        $filtered_mime_types = array();

        foreach( get_allowed_mime_types() as $key => $type ):
            if( false === strpos( $type, 'image' ) )
                $filtered_mime_types[] = $type;
        endforeach;

        $filename = 'image';
        $publication_name = get_bloginfo('name');
        $publication_language = get_bloginfo('language');
		$xml_sitemap_images  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml_sitemap_images .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        foreach ($posts as $post): 

  
            $postdate = explode( " ", $post->post_modified );
            $xml_sitemap_images .= "\n \t".'<url>'."\n \t \t".'<loc>'.$this->cts_escape_xml_entities(get_permalink($post->ID)); get_permalink().'</loc>'."\n \t \t";
            
      
            $args = array(
                'post_type' => 'attachment',
                'posts_per_page' => -1,
                'post_status' => 'any',
                'post_parent' => $post->ID,
                'post_mime_type' => implode( ',', $filtered_mime_types )
            );
            $images_objs = get_posts( $args );
            foreach ($images_objs as $post){
                $xml_sitemap_images .='<image:image>'."\n \t \t \t \t".'<image:loc>'."\n \t \t \t \t".$post->ID.'</image:loc>'."\n \t \t \t \t".'</image:image>';
                $xml_sitemap_images .= "\n \t".'</url>'."\n".'';
            }
          
        endforeach;
        $xml_sitemap_images .= '</urlset>';
        $sitemap = $this->cts_create_file($filename,$xml_sitemap_images);
        return $sitemap;
    }

    public function create_master_sitemap(){
        $files = glob('*sitemap.xml');
        $update_time = date('Y-m-d');
        $filename = "master";
        $master_sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
        $master_sitemap .= "\n ".'<urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9" xmlns:n="https://www.google.com/schemas/sitemap-news/0.9">';
        $master_sitemap .= "\n \t".'<sitemapindex>';
        foreach($files as $sitemap):
            if($sitemap !='master-sitemap.xml'): 
                $master_sitemap .="\n \t \t".'<sitemap>';
                $master_sitemap .="\n \t \t \t".'<loc>'.$sitemap.'</loc>';
                $master_sitemap .="\n \t \t \t".'<lastmod>'.$update_time.'</lastmod>';
                $master_sitemap .="\n \t \t".'</sitemap>';  
            endif;    
        endforeach;   
        $master_sitemap.= "\n \t".'</sitemapindex>';
        $master_sitemap.= "\n".'</urlset>';
        
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

