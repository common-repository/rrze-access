<?php
define( 'SHORTINIT', true );

require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );

// Spezifischen WP-Dateien laden

require_once( ABSPATH . WPINC . '/l10n.php' );

wp_not_installed();

require( ABSPATH . WPINC . '/formatting.php' );
require( ABSPATH . WPINC . '/capabilities.php' );
require( ABSPATH . WPINC . '/query.php' );
require( ABSPATH . WPINC . '/user.php' );
require( ABSPATH . WPINC . '/meta.php' );
require( ABSPATH . WPINC . '/post.php' );
require( ABSPATH . WPINC . '/rewrite.php' );
require( ABSPATH . WPINC . '/kses.php' );
require( ABSPATH . WPINC . '/deprecated.php' );
require( ABSPATH . WPINC . '/script-loader.php' );
require( ABSPATH . WPINC . '/canonical.php' );
require( ABSPATH . WPINC . '/http.php' );
require( ABSPATH . WPINC . '/class-http.php' );

if ( is_multisite() ) {
	require( ABSPATH . WPINC . '/ms-functions.php' );
	require( ABSPATH . WPINC . '/ms-default-filters.php' );
	require( ABSPATH . WPINC . '/ms-deprecated.php' );
}

wp_plugin_directory_constants( );

if ( is_multisite() )
	ms_cookie_constants(  );

wp_cookie_constants( );

wp_ssl_constants( );

require( ABSPATH . WPINC . '/vars.php' );

create_initial_post_types();

require( ABSPATH . WPINC . '/pluggable.php' );
require( ABSPATH . WPINC . '/pluggable-deprecated.php' );

wp_set_internal_encoding();

if ( WP_CACHE && function_exists( 'wp_cache_postload' ) )
	wp_cache_postload();

wp_functionality_constants( );

wp_magic_quotes();

$GLOBALS['wp_rewrite'] = new WP_Rewrite();

$wp = new WP();

$wp->init();

if ( is_multisite() ) {
	if ( true !== ( $file = ms_site_check() ) ) {
		require( $file );
		die();
	}
	unset($file);
}

// Ende der WP-Dateien

$post_id = RRZE_Files::get_post_id();

if( ! RRZE_Files::is_allowed( $post_id ) ) {
    status_header( 404 );
    die( '404 &#8212; File not found.' );
}

if( ! is_multisite() || $current_blog->blog_id == 1 ) {
    
    if ( ! defined('WP_CONTENT_URL') )
        define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
    
    error_reporting( 0 );
    
    list( $basedir ) = array_values( array_intersect_key( wp_upload_dir(), array( 'basedir' => 1) ) );

    $file =  rtrim( $basedir, '/' ) . '/' . str_replace( '..', '', isset( $_GET['file'] ) ? $_GET['file'] : '' );

    if ( ! $basedir || ! is_file( $file ) ) {
        status_header( 404 );
        die( '404 &#8212; File not found.' );
    }

    $mime = wp_check_filetype( $file );
    if( false === $mime['type'] && function_exists( 'mime_content_type' ) )
        $mime['type'] = mime_content_type( $file );

    if( $mime['type'] )
        $mimetype = $mime['type'];
    else
        $mimetype = 'image/' . substr( $file, strrpos( $file, '.' ) + 1 );

    header( 'Content-Type: ' . $mimetype );
    
    if ( false === strpos( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS' ) )
        header( 'Content-Length: ' . filesize( $file ) );
    
} else {
    
    ms_file_constants();

    error_reporting( 0 );

    if ( $current_blog->archived == '1' || $current_blog->spam == '1' || $current_blog->deleted == '1' ) {
        status_header( 404 );
        die( '404 &#8212; File not found.' );
    }

    $file = rtrim( BLOGUPLOADDIR, '/' ) . '/' . str_replace( '..', '', $_GET['file'] );

    if ( ! is_file( $file ) ) {
        status_header( 404 );
        die( '404 &#8212; File not found.' );
    }

    $mime = wp_check_filetype( $file );
    if( false === $mime['type'] && function_exists( 'mime_content_type' ) )
        $mime['type'] = mime_content_type( $file );

    if( $mime['type'] )
        $mimetype = $mime['type'];
    else
        $mimetype = 'image/' . substr( $file, strrpos( $file, '.' ) + 1 );

    header( 'Content-Type: ' . $mimetype );
    
    if ( false === strpos( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS' ) )
        header( 'Content-Length: ' . filesize( $file ) );

    // Optionale Unterstützung für X-Sendfile und X-Accel-Redirect
    if ( WPMU_ACCEL_REDIRECT ) {
        header( 'X-Accel-Redirect: ' . str_replace( WP_CONTENT_DIR, '', $file ) );
        exit;
    } elseif ( WPMU_SENDFILE ) {
        header( 'X-Sendfile: ' . $file );
        exit;
    }

}

$last_modified = gmdate( 'D, d M Y H:i:s', filemtime( $file ) );
$etag = '"' . md5( $last_modified ) . '"';
header( "Last-Modified: $last_modified GMT" );
header( 'ETag: ' . $etag );
header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 100000000 ) . ' GMT' );

$client_etag = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? stripslashes( $_SERVER['HTTP_IF_NONE_MATCH'] ) : false;

if( ! isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) )
	$_SERVER['HTTP_IF_MODIFIED_SINCE'] = false;

$client_last_modified = trim( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );

$client_modified_timestamp = $client_last_modified ? strtotime( $client_last_modified ) : 0;

$modified_timestamp = strtotime($last_modified);

if ( ( $client_last_modified && $client_etag )
	? ( ( $client_modified_timestamp >= $modified_timestamp) && ( $client_etag == $etag ) )
	: ( ( $client_modified_timestamp >= $modified_timestamp) || ( $client_etag == $etag ) )
	) {
	status_header( 304 );
	exit;
}

readfile( $file );
exit;

class RRZE_Files {

    const option_name = '_rrze_access';
    
    const post_meta_name = '_rrze_access';
    
    const post_meta_name_values = '_rrze_access_values';
    
    const ipv4_filter = '#((\d{1,3}|\*)(\.(\d{1,3}|\*)){1,3}|\*)#';

    public static function get_post_id() {
        $attachment_url = self::get_attachment_url();
        
        $attachment_id = self::get_attachment_id_by_url( $attachment_url );
        if( empty( $attachment_id ) )
            $attachment_id = 0;
        
        $post_parent = self::get_post_parent( $attachment_id );
        if( empty( $post_parent ) )
            $post_parent = 0;
        
        return $post_parent;
    }
    
    private static function get_attachment_url() {
        $http = isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http';
        $port = $_SERVER['SERVER_PORT'] =! '80' && $_SERVER['SERVER_PORT'] != '443' ? ':' . $_SERVER['SERVER_PORT'] : '';
        return sprintf( '%s://%s%s%s', $http, $_SERVER['HTTP_HOST'], $port, $_SERVER['REQUEST_URI'] );
    }

    private static function get_attachment_id_by_url( $attachment_url ) {
        global $wpdb;

        $attachment_url = preg_replace('/-\d+x\d+(?=\.(jpg|jpe|jpeg|png|gif)$)/i', '', $attachment_url);

        return $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE guid='$attachment_url'" ) );
    }    

    private static function get_post_parent( $attachment_id ) {
        global $wpdb;

        return $wpdb->get_var( $wpdb->prepare( "SELECT post_parent FROM " . $wpdb->prefix . "posts" . " WHERE ID='" . $attachment_id . "';" ) );
    }

    private static function get_post_meta( $post_id = 0 ) {
        $post_meta = get_post_meta( $post_id, self::post_meta_name_values );
        
        if( isset( $post_meta[0] ) )
            extract( $post_meta[0] );

        $meta = array(
            'allow_access' => isset( $allow_access ) ? $allow_access : '1',
            'ip_addresses' => isset( $ip_addresses ) ? $ip_addresses : ''
        );

        return $meta;
    }
    
	public static function is_allowed( $post_id = 0 ) {
        global $wpdb;
                 
        $visitor_ips = self::get_visitor_ips();

        $restricted_posts_by_status = self::restricted_posts_by_status();
        
        if( ! empty( $restricted_posts_by_status ) ) 
            if( in_array( $post_id, $restricted_posts_by_status ) && ! current_user_can( 'read_private_posts', $post_id ) )
                return false;
            
        $restricted_posts_by_meta = self::restricted_posts_by_meta();
        
        if( ! empty( $restricted_posts_by_meta ) ) {
            $meta = self::get_post_meta( $post_id );

            if( $meta['allow_access'] == '1' )
               return true;

            $deny_access = ( $meta['allow_access'] == '3' );

            foreach( $visitor_ips as $visitor_ip ) {

                if( self::wildcard_in_array( $visitor_ip, self::ip_addresses( $meta['ip_addresses'] ) ) == $deny_access )

                    if( ! current_user_can( 'edit_post', $post_id ) )
                        return false;

            }
        }
        
        return true;
	}
        
    private static function restricted_posts_by_status() {
        global $wpdb;
        
        $restricted_posts = array();

        $results = $wpdb->get_results( sprintf( "SELECT DISTINCT ID FROM %sposts WHERE post_status = '%s';", $wpdb->prefix, 'private' ) );

        foreach ( $results as $post ) {
            $restricted_posts[$post->ID] = $post->ID;
        }
        
        return $restricted_posts;
    }
    
    private static function restricted_posts_by_meta() {
        global $wpdb;
        
        $restricted_posts = array();

        $results = $wpdb->get_results( sprintf( "SELECT DISTINCT post_id as ID FROM %spostmeta WHERE meta_key = '%s' AND meta_value > '1';", $wpdb->prefix, self::post_meta_name ) );

        foreach ( $results as $post ) {
            $restricted_posts[$post->ID] = $post->ID;
        }
        
        return $restricted_posts;
    }
    
	private static function ip_addresses( $ip_addresses = '' ) {
		$ips = array();
		$ipv4 = array();
        
		$match = preg_match_all( self::ipv4_filter, $ip_addresses, $ipv4 );
		if( $match !== false && $match > 0 )
            $ips = array_merge( $ips, $ipv4[0] );
        
		return $ips;
	}

	private static function wildcard_match( $pattern, $string ) {
		$regex = '/^' . strtr( addcslashes( $pattern, '.+^$(){}=!<>|' ), array( '*' => '.*', '?' => '.?' ) ) . '$/i';
		return @preg_match( $regex, $string );
	}


	private static function wildcard_in_array( $needle, $haystack ) {
		foreach( $haystack as $value ) {
			$match = self::wildcard_match( $value, $needle );
			if( $match !== false && $match > 0 )
				return true;
		}
		return false;
	}
    
	private static function get_visitor_ips() {
		$ips = array();

        if( ( $hostname = gethostname() ) && ( $remote_addr = gethostbyname( $hostname ) ) )
            $ips[] = $remote_addr;
        
		$ips[] = $_SERVER['REMOTE_ADDR'];

		if( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) )
			$ips[] = $_SERVER['HTTP_CLIENT_IP'];

		if( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )
			$ips[] = $_SERVER['HTTP_X_FORWARDED_FOR'];

		if( ! empty( $_SERVER['HTTP_FORWARDED_FOR'] ) )
			$ips[] = $_SERVER['HTTP_FORWARDED_FOR'];
        
		return $ips;
	}

}
