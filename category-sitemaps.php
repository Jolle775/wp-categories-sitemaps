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

// Your code starts here.

function create_sitemap($post_ID) {
    $categories =  get_the_category($post_ID );
    $sitemaps = array();
    foreach($categories as $category) {
        $posts_for_sitemap = get_posts( array(
            'numberposts' => -1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'category' => $category->ID,
            'post_type' => array( 'post' )
        ));
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd http://www.google.com/schemas/sitemap-image/1.1 http://www.google.com/schemas/sitemap-image/1.1/sitemap-image.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach( $posts_for_sitemap as $post ) {
        setup_postdata( $post );
        $postdate = explode( " ", $post->post_modified );
        $sitemap .= "\t" . '<url>' . "\n" .
             "\t\t" . '<loc>' . get_permalink( $post->ID ) . '</loc>' .
            "\n\t\t" . '<lastmod>' . $postdate[0] . '</lastmod>' .
            "\n\t\t" . '<changefreq>monthly</changefreq>' .
            "\n\t" . '</url>' . "\n";
    }
    $sitemap .= '</urlset>';
    $fp = fopen( ABSPATH . $category->name."-sitemap.xml", 'w' );
    fwrite( $fp, $sitemap );
    fclose( $fp );
    $url = ABSPATH . $category->name."-sitemap.xml"
    $sitemaps[] = $url;

}
 if(!empty($sitemaps)) {
    create_master_sitemap();
 }
}

function create_master_sitemap($sitemaps){
    $files = glob(ABSPATH .'*.-sitemap.xml');
    $master_sitemap =' <?xml version="1.0" encoding="UTF-8"?>' . "\n" .' <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
    foreach($files as $sitemap) { 
        $master_sitemap .='
    <sitemap>
       <loc>'.$sitemap.'</loc>
    </sitemap>'
    };   
    $master_sitemap.= '</sitemapindex>'  
};

add_action( 'publish_post', 'create_sitemap' ); 
add_action( 'save_post', 'create_sitemap' ); 



