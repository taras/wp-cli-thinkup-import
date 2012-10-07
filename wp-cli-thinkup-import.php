<?php
/*
Plugin Name: Thinkup Import WP CLI Commands
Plugin URI: https://github.com/wrktg/wp-cli-thinkup-import
Description: Provides WP CLI commands to import posts from Thinkup App into WP
Version: 1.0
Author: Taras Mankovski
Author URI: http://taras.cc
License: GPL2
*/

if ( defined('WP_CLI') && WP_CLI ) {

    include_once('class.thinkup_post.php');
    include_once('fivefilters-php-readability/Readability.php');
    include_once('filters.php');
	include( dirname(__FILE__) . '/wp-cli.php' );

}

function thinkup_import_init() {

    // Add new taxonomy, make it hierarchical (like categories)
    $labels = array(
        'name' => _x( 'Source', 'taxonomy general name' ),
        'singular_name' => _x( 'Source', 'taxonomy singular name' ),
        'search_items' =>  __( 'Sources' ),
        'all_items' => __( 'All Sources' ),
        'parent_item' => __( 'Parent Source' ),
        'parent_item_colon' => __( 'Parent Source:' ),
        'edit_item' => __( 'Edit Source' ),
        'update_item' => __( 'Update Source' ),
        'add_new_item' => __( 'Add Source' ),
        'new_item_name' => __( 'New Source Name' ),
        'menu_name' => __( 'Sources' ),
    );

    # Visibility taxonomy determines if the post is exposed to the public
    register_taxonomy('source', array('post'), array(
        'hierarchical' => false,
        'labels' => $labels,
        'show_ui' => true,
        'public' => true,
      ));

    define( 'WP_CLI_THINKUP_IMPORT_PATH', plugin_dir_path(__FILE__) );

    add_action( 'add_meta_boxes', 'thinkup_import_admin_metabox' );

    $plugin_dir = basename(dirname(__FILE__));
    load_plugin_textdomain( 'thinkup-import', false, $plugin_dir );

}
add_action('init', 'thinkup_import_init', 0);

function thinkup_import_admin_metabox() {

    add_meta_box(
         'thinkup_import_metabox',
         __( 'Thinkup Post Information', 'thinkup-import' ),
         'thinkup_import_metabox_html',
         'post'
     );

}

function thinkup_import_metabox_html() {

    $exclude = array('_thinkup_pending_processing');

    $metadata = get_post_custom();

    if ( $metadata ) {
        print '<ul>';
        foreach ( $metadata as $key => $value ) {

            if ( in_array($key, $exclude) ) continue;

            if ( strstr($key, '_thinkup') ) {
                if ( is_array($value) ) $value = $value[0];
                print '<li>';
                if ( is_serialized($value) ) {
                    $value = unserialize($value);
                    print '<ul>';
                    foreach ( $value as $v ) {
                        printf('<li>%s: %s</li>', __($key), $v);
                    }
                    echo '</ul>';
                } else {
                    printf('%s: %s', __($key), $value);
                }
                print '</li>';
            }

        }
        print '</ul>';
    }

}