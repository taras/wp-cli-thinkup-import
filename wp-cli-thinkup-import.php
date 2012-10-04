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

}

add_action('init', 'thinkup_import_init', 0);