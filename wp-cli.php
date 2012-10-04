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
     * Verify that this command can be executed.
     * @return mixed
     */
    private function verify(){

        if ( is_multisite() && get_current_blog_id() === 0 )
            return WP_CLI::error('You must specify blog url with --blog parameter.');

    }

    /**
     * Setup ThinkUp API before interacting with it
     */
    private function setup( $app_path ) {

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

        $ownersDAO = DAOFactory::getDAO('OwnerDAO');
        $postsDAO = DAOFactory::getDAO('PostDAO');
        $instancesDAO = DAOFactory::getDAO('InstanceDAO');
        $hashtagDAO = DAOFactory::getDAO('HashtagDAO');

        foreach ( $users as $user ) {

            $owner = $ownersDAO->getByEmail($user->user_email);
            $instances = $instancesDAO->getByOwner($owner);

            foreach ( $instances as $instance ) {
                if ( $instance->is_active ) {
                    $user_metafield_name = '_thinkup_import_last_'.sanitize_title($instance->network);

                    // from metadata get data of last imported post
                    $from = get_user_meta($user->ID, $user_metafield_name, true);
                    if ( $from == '' ) $from = 0;

                    $posts = $postsDAO->getPostsByUserInRange($instance->network_user_id, $instance->network, $from,
                        current_time('mysql'), 'pub_date', 'ASC', $iterator=false);

                    foreach ( $posts as $post ) {

                        // skip last post
                        if ( $post->pub_date == $from ) continue;

                        $wp_post = array(
                            # TODO: Change title to short status version of the title
                            'post_title'=>sprintf('%s post by %s', $instance->network, $instance->network_username),
                            'post_content'=>$post->post_text,
                            'post_name'=>$post->post_id,
                            'post_status' => 'draft',
                            'post_date' => $post->pub_date,
                            'post_author' => $user->ID,
                            'post_type' => 'post',
                            'post_category' => array(0)
                        );
                        $post_id = wp_insert_post($wp_post);

                        if ( $post_id ) {

                            add_post_meta($post_id, '_thinkup_post', $post->post_text);
                            add_post_meta($post_id, '_thinkup_source', $instance->network);
                            add_post_meta($post_id, '_thinkup_author_username', $post->author_username);
                            add_post_meta($post_id, '_thinkup_author_fullname', $post->author_fullname);
                            add_post_meta($post_id, '_thinkup_author_avatar', $post->author_avatar);
                            add_post_meta($post_id, '_thinkup_post_id', $post->post_id, true);
                            add_post_meta($post_id, '_thinkup_pending_processing', 'true');
                            wp_set_post_terms( $post_id, array($instance->network), 'source' );
                            set_post_format( $post_id , 'status');

                        } else {

                            WP_CLI::warning(sprintf("%s post could not created in WP.\nDebug Info:\n%s", $instance->network, print_r($wp_post, true)));

                        }

                    }

                    // update the last imported article
                    if ( $posts && $post->pub_date != $from )
                        update_user_meta( $user->ID, $user_metafield_name, $post->pub_date );
                }
            }



        }


        $this->reset();

	}

    public function process( $args, $assoc_args ) {

        $this->verify();

        $query = new WP_Query( array('meta_key'=>'_thinkup_pending_processing', 'meta_value'=>'true', 'nopaging'=>true, 'post_status'=>'draft') );

        $wp_posts = $query->get_posts();

        foreach ( $wp_posts as $wp_post ) {

            $tu_post = new Thinkup_Post();
            $tu_post->wp_post = $wp_post;

            $tu_post = apply_filters('thinkup_parse_original', $tu_post);

            # get the content from links and process it
            $tu_post = apply_filters('thinkup_get_remote_content', $tu_post);

            $new_content = array('ID'=>$wp_post->ID);

            if ( $tu_post->response_code == 200 ) {

                $tu_post = apply_filters('thinkup_extract_images', $tu_post);

                if ( $tu_post->images ) {
                    foreach ( $images as $image ) {

                    }
                }

                if ( $tu_post->links ) add_post_meta($wp_post->ID, '_thinkup_list', $tu_post->links);
                if ( $tu_post->mentions ) wp_set_post_tags( $wp_post->ID, $tu_post->mentions, true );
                if ( $tu_post->tags ) wp_set_post_tags( $wp_post->ID, $tu_post->tags, true);

                if ( !empty($tu_post->title) ) {
                    $new_content['post_title'] = $tu_post->title;
                    $new_content['post_name'] = sanitize_title($tu_post->title);
                }

                if ( !empty( $tu_post->body ) ) $new_content['post_content'] = apply_filters('content_save_pre', $tu_post->body);

                if ( count($new_content) > 1 ) {
                    wp_update_post($new_content);
                    set_post_format( $wp_post->ID , 'standard');
                }

            }

        }


    }

	static function help() {
		WP_CLI::line( 'usage: wp example hello' );
	}

}

// Register the class as the 'example' command handler
WP_CLI::add_command( 'thinkup', 'ThinkUpApp_Import_Command' );