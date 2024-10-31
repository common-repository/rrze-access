<?php
/**
 * Plugin Name: RRZE-Access
 * Description: Zugriffsbeschränkung von IP-Adressen auf Artikel, Seiten und die entsprechenden Media-Dateien.
 * Version: 1.0
 * Author: rvdforst
 * Author URI: http://blogs.fau.de/webworking/
 * License: GPLv2 or later
 */

/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

class RRZE_Access {

    const version = '1.0'; // Plugin-Version
    
    const option_name = '_rrze_access';
    
    const post_meta_name = '_rrze_access';
    
    const post_meta_name_values = '_rrze_access_values';

    const version_option_name = '_rrze_access_version';
    
    const textdomain = '_rrze_access';
    
    const ipv4_filter = '#((\d{1,3}|\*)(\.(\d{1,3}|\*)){1,3}|\*)#';
    
    const php_version = '5.2.4'; // Minimal erforderliche PHP-Version
    
    const wp_version = '3.4.2'; // Minimal erforderliche WordPress-Version
    
    public static function init() {
        
        load_plugin_textdomain( self::textdomain, false, sprintf( '%slang', plugin_dir_path( __FILE__ ) ) );
                        
        add_action( 'init', array( __CLASS__, 'update_version' ) );
                                
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

        add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'publish_access_restriction' ) );
        
        add_action( 'save_post', array( __CLASS__, 'save_postdata' ) );

        add_filter( 'posts_where', array( __CLASS__, 'allow_or_deny_post_access' ) );
        
        if( ! is_admin() )
            add_filter( 'the_title', array( __CLASS__, 'set_title' ), 10, 2 );
        
        if ( is_multisite() )
            add_action( 'network_admin_menu', array( __CLASS__, 'network_admin_page' ) );

        else
            add_action( 'admin_menu', array( __CLASS__, 'admin_page' ) );

    }

    public static function activation() {
        self::version_compare();
        
        update_option( self::version_option_name , self::version );
    }
    
    public static function deactivation() {
        
    }
    
    public static function uninstall() {
        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) || ( __FILE__ != WP_UNINSTALL_PLUGIN ) )
            return;
        
        delete_option( self::option_name );
        delete_option( self::version_option_name );
    }
    
    public static function version_compare() {
        $error = '';
        
        if ( version_compare( PHP_VERSION, self::php_version, '<' ) ) {
            $error = sprintf( __( 'Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', self::textdomain ), PHP_VERSION, self::php_version );
        }

        if ( version_compare( $GLOBALS['wp_version'], self::wp_version, '<' ) ) {
            $error = sprintf( __( 'Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', self::textdomain ), $GLOBALS['wp_version'], self::wp_version );
        }

        if( ! empty( $error ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ), false, true );
            wp_die( $error );
        }
        
    }
    
    public static function update_version() {
		if( get_option( self::version_option_name, null) != self::version )
			update_option( self::version_option_name , self::version );
    }
    
    private static function get_options( $key = '' ) {
        $defaults = array();

        $options = (array) get_option( self::option_name );    
        $options = wp_parse_args( $options, $defaults );
        $options = array_intersect_key( $options, $defaults );

        if( !empty( $key ) )
            return isset($options[$key]) ? $options[$key] : null;

        return $options;
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
    
    public static function enqueue_scripts() {
        wp_register_style( 'rrze-access-admin', sprintf( '%scss/admin.css', plugin_dir_url( __FILE__ ) ) );
        wp_enqueue_style( 'rrze-access-admin' );
        
        wp_register_script( 'rrze-access-admin', sprintf( '%sjs/admin.js', plugin_dir_url( __FILE__ ) ) );
        wp_enqueue_script( 'rrze-access-admin' );
    }
    
    public static function network_admin_page() {
        add_submenu_page( 'settings.php', __( 'Zugriffsbeschränkung-Einrichtung', self::textdomain ), __( 'Zugriffsbeschränkung-Einrichtung', self::textdomain ), 'manage_network', 'access-setup', array( __CLASS__, 'access_setup' ) );        
    }
    
    public static function admin_page() {
        add_options_page( __( 'Zugriffsbeschränkung-Einrichtung', self::textdomain ), __( 'Zugriffsbeschränkung-Einrichtung', self::textdomain ), 'manage_options', 'access-setup', array( __CLASS__, 'access_setup' ) );        
    }
    
    public static function access_setup() {
        global $base;
        
        list( $basedir ) = array_values( array_intersect_key( wp_upload_dir(), array( 'basedir' => 1) ) );
        $base_path = trim( str_replace( ABSPATH, '', $basedir ) , '/' );
        $rewrite_path = trim( str_replace( ABSPATH, '', plugin_dir_path( __FILE__ ) ) , '/' );
        
        $is_subdomain_install = false;
        $textarea_rows = 11;
        
        $htaccess_file  = 'RewriteEngine On' . PHP_EOL;
        $htaccess_file .= 'RewriteBase ' . $base . PHP_EOL;
        $htaccess_file .= 'RewriteRule ^index\.php$ - [L]' . PHP_EOL;
        
        $htaccess_file .= PHP_EOL . '# uploaded files' . PHP_EOL;
        
        if( is_multisite() ) {
        
            $is_subdomain_install = is_subdomain_install() ? true : false;
            $textarea_rows = $is_subdomain_install ? 13 : 18;
            
            $htaccess_file .= 'RewriteRule ^' . ( $is_subdomain_install ? '' : '([_0-9a-zA-Z-]+/)?' ) . 'files/(.+) ' . $rewrite_path . '/rrze-files.php?file=$' . ( $is_subdomain_install ? 1 : 2 ) . ' [L]' . PHP_EOL;
            $htaccess_file .= 'RewriteRule ^' . ( $is_subdomain_install ? '' : '([_0-9a-zA-Z-]+/)?' ) . $base_path . '/(.+) ' . $rewrite_path . '/rrze-files.php?file=$' . ( $is_subdomain_install ? 1 : 2 ) . ' [L]' . PHP_EOL;

            if ( ! $is_subdomain_install )
                $htaccess_file .= PHP_EOL . '# add a trailing slash to /wp-admin' . PHP_EOL . 'RewriteRule ^([_0-9a-zA-Z-]+/)?wp-admin$ $1wp-admin/ [R=301,L]' . PHP_EOL;

            $htaccess_file .= PHP_EOL . 'RewriteCond %{REQUEST_FILENAME} -f [OR]' . PHP_EOL;
            $htaccess_file .= 'RewriteCond %{REQUEST_FILENAME} -d' . PHP_EOL;
            $htaccess_file .= 'RewriteRule ^ - [L]';

            if ( ! $is_subdomain_install )
                $htaccess_file .= PHP_EOL . 'RewriteRule ^[_0-9a-zA-Z-]+/(wp-(content|admin|includes).*) $1 [L]\nRewriteRule  ^[_0-9a-zA-Z-]+/(.*\.php)$ $1 [L]';

        } else {
            $htaccess_file .= 'RewriteRule ^' . $base_path . '/(.+) ' . $rewrite_path . '/rrze-files.php?file=$1 [L]' . PHP_EOL;
        }

        $htaccess_file .= PHP_EOL . 'RewriteRule . index.php [L]';
        ?>
        <div class="wrap">
        <?php screen_icon( is_multisite() ? 'tools' : '' ); ?>
        <h2><?php echo esc_html( __( 'Zugriffsbeschränkung-Einrichtung', self::textdomain ) ); ?></h2>
        <p><?php _e( 'Die Original Konfigurationsschritte werden hier zur Referenz gezeigt.', self::textdomain ); ?></p>
        <p><?php printf( __( 'Fügen Sie folgendes zu Ihrer <code>.htaccess</code> Datei in  <code>%s</code> hinzu, ersetzen Sie andere WordPress Regeln:', self::textdomain ), ABSPATH ); ?></p>
        <textarea class="code" readonly="readonly" cols="100" rows="<?php echo $textarea_rows; ?>"><?php echo esc_textarea( $htaccess_file ); ?></textarea>
        </div>
        <?php
    }
        
    public static function publish_access_restriction() {
        global $post;

        $meta = self::get_post_meta( $post->ID );
        
        $status_arry = array( 
            'rrze-access-none' => __( 'Keine', self::textdomain ),
            'rrze-access-active' => __( 'Aktiv', self::textdomain )
        );
        
        $status = $meta['allow_access'] > 1 ? 'rrze-access-active' : 'rrze-access-none';
        
        $checked_arry = array( 
            '1' => 'allow-all',
            '2' => 'allow',
            '3' => 'deny'
        );
        
        $checked = $checked_arry[$meta['allow_access']];
        ?>
        <?php foreach( $status_arry as $key => $value ): ?>
        <input type="hidden" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo $value; ?>" />
        <?php endforeach; ?>
        <input type="hidden" id="rrze-access-checked" name="rrze-access-checked" value="<?php echo $checked; ?>" />
        <div class="misc-pub-section" id="rrze-access">
        <?php _e( 'Beschränkung von IP-Adressen:', self::textdomain ); ?> <span id="post-rrze-access-display"><?php echo esc_html( $status_arry[$status] ); ?></span>
        
        <?php if ( current_user_can( 'publish_post' ) ) : ?>
        <a href="#rrze-access" class="edit-rrze-access hide-if-no-js"><?php _e( 'Editieren', self::textdomain ); ?></a>

        <div id="post-rrze-access-select" class="inside hide-if-js">
            <input type="radio" id="rrze-access-allow-all" name="<?php printf( '%s[allow_access]', self::post_meta_name ); ?>" value="1" <?php checked( $meta['allow_access'] == 1 ); ?> />
            <label><?php _e( 'Keine', self::textdomain ); ?></label>
            <br />            
            <input type="radio" id="rrze-access-allow" name="<?php printf( '%s[allow_access]', self::post_meta_name ); ?>" value="2" <?php checked( $meta['allow_access'] == 2 ); ?> />
            <label><?php _e( 'Zugriff von IP-Adressen gewähren', self::textdomain ); ?></label>
            <br />
            <input type="radio" id="rrze-access-deny" name="<?php printf( '%s[allow_access]', self::post_meta_name ); ?>" value="3" <?php checked( $meta['allow_access'] == 3 ); ?> />
            <label><?php _e( 'Zugriff von IP-Adressen verweigern', self::textdomain ); ?></label>
            <div id="rrze-access-field">           
                <label><?php _e( 'IP-Adressen:', self::textdomain ); ?></label>
                <textarea id="rrze-access-text" name="<?php printf( '%s[ip_addresses]', self::post_meta_name ); ?>" cols="36" rows="2" tabindex="6"><?php echo $meta['ip_addresses']; ?></textarea>
            </div>
            <p>
             <a href="#rrze-access" class="save-post-rrze-access hide-if-no-js button"><?php _e( 'Ok', self::textdomain ); ?></a>
             <a href="#rrze-access" class="cancel-post-rrze-access hide-if-no-js"><?php _e( 'Abbrechen', self::textdomain ); ?></a>
            </p>
        </div>
        <?php endif; ?>

        </div>
        <?php
    }

    public static function save_postdata( $post_id ) {

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
            return false;
        
        if ( ! current_user_can( 'edit_page', $post_id ) ) 
            return false;
        
        if( empty( $post_id ) ) 
            return false;

        if( isset( $_POST['action'] ) && $_POST['action'] == 'editpost' ) {
            delete_post_meta( $post_id, self::post_meta_name );
            delete_post_meta( $post_id, self::post_meta_name_values );
        }
        
        if( ! isset( $_POST[self::post_meta_name] ) )
            return false;
                
        $input = $_POST[self::post_meta_name];

        $meta = self::get_post_meta( $post_id );
        
        if( isset( $input['allow_access'] ) && in_array( $input['allow_access'], array( '1', '2', '3' ) ) )
            $meta['allow_access'] = $input['allow_access'];
        
        if( !empty( $input['ip_addresses'] ) && $meta['allow_access'] > 1 ) {
            $meta['ip_addresses'] = $input['ip_addresses'];
        }
        
        update_post_meta( $post_id, self::post_meta_name, $meta['allow_access'] );       
        update_post_meta( $post_id, self::post_meta_name_values, $meta );
    }
    
	public static function allow_or_deny_post_access( $where ) {
        global $wpdb;
        
        $exclude = array();
        
        $restricted_posts_by_status = self::restricted_posts_by_status();
        
        if( ! empty( $restricted_posts_by_status ) ) {
            
            foreach( $restricted_posts_by_status as $post_id ) {
                if( ! current_user_can( 'edit_post', $post_id ) )
                    $exclude = array_merge( $exclude, self::get_attachments( $post_id ) );
            }
            
        }
        
        $visitor_ips = self::get_visitor_ips();
        
        $restricted_posts_by_meta = self::restricted_posts_by_meta();
        
        if( ! empty( $restricted_posts_by_meta ) ) {
            
            foreach( $restricted_posts_by_meta as $post_id ) {

                if( isset( $exclude[$post_id] ) )
                    continue;
                
                $meta = self::get_post_meta( $post_id );

                if( $meta['allow_access'] == '1' )
                   continue;

                $deny_access = ( $meta['allow_access'] == '3' );

                $restricted = false;
                foreach( $visitor_ips as $visitor_ip ) {

                    if( self::wildcard_in_array( $visitor_ip, self::ip_addresses( $meta['ip_addresses'] ) ) == $deny_access ) {

                        $restricted = true;
                        break;

                    }

                }

                if( $restricted ) {
                    if( ! current_user_can( 'edit_post', $post_id ) ) {
                        $exclude[$post_id] = $post_id;
                        $exclude = array_merge( $exclude, self::get_attachments( $post_id ) );
                    }
                    
                    add_filter( 'display_post_states', array( __CLASS__, 'display_post_states' ) );

                }

            }

        }
        
        if( ! empty( $exclude ) ) {
            
            $exclude = implode( ',', $exclude );
            
            $where .= sprintf( " AND %sposts.ID NOT IN (%s) ", $wpdb->prefix, $exclude );
            
            add_filter( 'views_upload', array( __CLASS__, 'set_meta_links' ) );
        }
        
        return $where;
	}
    
    public static function display_post_states( $post_states ) {
        global $post;
        
        $restricted_posts = self::restricted_posts_by_meta();
        if( in_array( $post->ID, $restricted_posts ) )
            $post_states['restricted'] = __( 'Beschränkt', self::textdomain );
        
        return $post_states;
    }
    
    public static function set_title( $title, $post_id ) {
        if( ! current_user_can('edit_post', $post_id ) )
            return $title;
        
        $restricted_posts = self::restricted_posts_by_meta();
        if( in_array( $post_id, $restricted_posts ) ) {
            $title = sprintf( __( '[Beschränkt] %s', self::textdomain ), $title );
        }
        
        return $title;
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
    
    private static function get_attachments( $post_id ) {
        $attachments = array();
        
        $args = array(
            'post_type' => 'attachment',
            'numberposts' => null,
            'post_status' => null,
            'post_parent' => $post_id
        );
        
        $posts = get_posts( $args );
        if( $posts ) {
            foreach( $posts as $post ) {
                $attachments[$post->ID] = $post->ID;
            }
        }
        
        return $attachments;
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
    
    public static function set_meta_links( $meta_links ) {
		global $wpdb, $post_mime_types, $avail_post_mime_types;

        $detached = isset( $_REQUEST['detached'] ) || isset( $_REQUEST['find_detached'] );
		
        $_num_posts = (array) self::count_attachments();
		$_total_posts = array_sum($_num_posts) - $_num_posts['trash'];
		
        if ( !isset( $total_orphans ) )
				$total_orphans = $wpdb->get_var( "SELECT COUNT( * ) FROM $wpdb->posts WHERE post_type = 'attachment' AND post_status != 'trash' AND post_parent < 1" );
		$matches = wp_match_mime_types(array_keys($post_mime_types), array_keys($_num_posts));
		
        foreach ( $matches as $type => $reals )
			foreach ( $reals as $real )
				$num_posts[$type] = ( isset( $num_posts[$type] ) ) ? $num_posts[$type] + $_num_posts[$real] : $_num_posts[$real];

		$class = ( empty($_GET['post_mime_type']) && !$detached && !isset($_GET['status']) ) ? ' class="current"' : '';
		$meta_links['all'] = "<a href='upload.php'$class>" . sprintf( _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $_total_posts, 'uploaded files' ), number_format_i18n( $_total_posts ) ) . '</a>';
		
        foreach ( $post_mime_types as $mime_type => $label ) {
			$class = '';

			if ( ! wp_match_mime_types($mime_type, $avail_post_mime_types) )
				continue;

			if ( ! empty($_GET['post_mime_type']) && wp_match_mime_types($mime_type, $_GET['post_mime_type']) )
				$class = ' class="current"';
			if ( ! empty( $num_posts[$mime_type] ) )
				$meta_links[$mime_type] = "<a href='upload.php?post_mime_type=$mime_type'$class>" . sprintf( translate_nooped_plural( $label[2], $num_posts[$mime_type] ), number_format_i18n( $num_posts[$mime_type] ) ) . '</a>';
		}
        
		$meta_links['detached'] = '<a href="upload.php?detached=1"' . ( $detached ? ' class="current"' : '' ) . '>' . sprintf( _nx( 'Unattached <span class="count">(%s)</span>', 'Unattached <span class="count">(%s)</span>', $total_orphans, 'detached files' ), number_format_i18n( $total_orphans ) ) . '</a>';

		if ( !empty($_num_posts['trash']) )
			$meta_links['trash'] = '<a href="upload.php?status=trash"' . ( (isset($_GET['status']) && $_GET['status'] == 'trash' ) ? ' class="current"' : '') . '>' . sprintf( _nx( 'Trash <span class="count">(%s)</span>', 'Trash <span class="count">(%s)</span>', $_num_posts['trash'], 'uploaded files' ), number_format_i18n( $_num_posts['trash'] ) ) . '</a>';

		return $meta_links;
    }
    
    private static function count_attachments() {
        global $wpdb;

        $exclude = array_merge( self::restricted_posts_by_status(), self::restricted_posts_by_meta() );
        
        $exclude = implode( ',', $exclude );

        $post_parent_where = sprintf( " AND %sposts.post_parent NOT IN (%s) ", $wpdb->prefix, $exclude );
        
        $mime_type = '';
        $mime_type_where = wp_post_mime_type_where( $mime_type );
        $count = $wpdb->get_results( "SELECT post_mime_type, COUNT( * ) AS num_posts FROM $wpdb->posts WHERE post_type = 'attachment' AND post_status != 'trash' $mime_type_where $post_parent_where GROUP BY post_mime_type", ARRAY_A );

        $stats = array( );
        foreach( (array) $count as $row ) {
            $stats[$row['post_mime_type']] = $row['num_posts'];
        }
        $stats['trash'] = $wpdb->get_var( "SELECT COUNT( * ) FROM $wpdb->posts WHERE post_type = 'attachment' AND post_status = 'trash' $mime_type_where $post_parent_where");

        return (object) $stats;
    }
    
}

add_action( 'plugins_loaded', array( 'RRZE_Access', 'init' ) );

register_activation_hook( __FILE__, array( 'RRZE_Access', 'activation' ) );

register_deactivation_hook( __FILE__, array( 'RRZE_Access', 'deactivation' ) );

register_uninstall_hook( __FILE__, array( 'RRZE_Access', 'uninstall' ) );
