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
	include( dirname(__FILE__) . '/wp-cli.php' );
}
