<?php
/**
 * Command to import posts from Thinkup App into WordPress
 *
 * @package wp-cli-thinkup-import
 * @subpackage commands/community
 * @maintainer Taras Mankovski (http://twitter.com/tarasm)
 */
class ThinkUpApp_Import_Command extends WP_CLI_Command {

    // stores original current working directory value before switching to ThinkUp
    var $cwd;

    /**
     * Setup ThinkUp API before interacting with it
     */
    private function setup( $app_path ) {

        if ( is_multisite() && get_current_blog_id() === 0 )
            return WP_CLI::fail('You must specify blog url with --blog parameter.');

        // store current working directory
        $this->cwd = getcwd();

        chdir($app_path);
        require_once 'init.php';

    }

    private function reset( ) {

        // return to original working directory
        chdir($this->cwd);

    }

    /**
	 * Example subcommand
	 *
	 * @param array $args
	 */
	public function import( $args = array(), $assoc_args = array() ) {

        if ( array_key_exists('at', $assoc_args) ) {
            $at = $assoc_args['at'];
        } else {
            $at = ABSPATH . 'thinkup';
        }

        $this->setup($at);

        $users = get_users(array('blog_id'=>get_current_blog_id(), 'orderby'=>'email', 'role'=>'administrator'));

        $owners = DAOFactory::getDAO('OwnerDAO');

        foreach ( $users as $user ) {

            $owner = $owners->getByEmail($user->user_email);

        }


        $this->reset();


		// Print a success message
		WP_CLI::success( 'Hello world!' );
	}

	static function help() {
		WP_CLI::line( 'usage: wp example hello' );
	}

}

// Register the class as the 'example' command handler
WP_CLI::add_command( 'thinkup', 'ThinkUpApp_Import_Command' );