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

       require_once WP_CLI_THINKUP_IMPORT_PATH . 'dao.php';

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

        if ( array_key_exists('thinkup', $assoc_args) ) {
            $thinkup = $assoc_args['thinkup'];
        } else {
            $thinkup = ABSPATH . 'thinkup';
        }

        $this->setup($thinkup);

        if ( array_key_exists('wpuser', $assoc_args) ) {

            $wpuser = get_user_by('login', $assoc_args['wpuser']);

            if ( !$wpuser ) {

                WP_CLI::error(sprintf('%s user was not found.', $assoc_args['wpuser']));

            }


        } else {

            WP_CLI::error('You must specify wpuser to use for import. ie --wpuser=admin');
            return;

        }

        $posts = array();

        switch ( $args[0] ) :
            case 'twitter':
                switch ( $args[1] ):
                    case 'mentions':

                        // check that user was specified
                        if ( count($args) < 3 ) {

                            WP_CLI::error('You must specify Twitter usernames (without @).');
                            return;

                        } else {

                            $users = array_slice($args, 2);

                        }

                        $mentionDAO = DAOFactory::getDAO('MentionDAO');
                        $postDAO = DAOFactory::getDAO('PostDAO');
                        $linkDAO = DAOFactory::getDAO('LinkDAO');
                        $wppostDAO = new WordPressPostMySQLDAO();

                        foreach ( $users as $username ) {

                            $mention = $mentionDAO->getMentionInfoUserName($username, 'twitter');

                            if ( $mention ) {

                                $last_import_option_key = '_thinkup_import_last_twitter_'.$username;

                                if ( $from = get_option($last_import_option_key, 0) ) {
                                    if ( WP_DEBUG ) WP_CLI::line("Last import: $from");
                                } else {
                                    if ( WP_DEBUG) WP_CLI::line('First import');
                                }

                                $posts = $wppostDAO->getPostsByMentionIDInRange($mention['id'], 'twitter', $from,
                                    current_time('mysql'), 'pub_date', 'ASC', $iterator=false);

                            } else {

                                WP_CLI::warning('%s could not be found in Thinkup.', $username);

                            }
                        }

                        break;
                    default:
                        WP_CLI::error('%s %s is not implemented', $args[0], $args[1]);
                        return;
                endswitch;
            break;
            default:
                WP_CLI::error('%s is not implemented', $args[0]);
        endswitch;

        // skip first post if its same as last imported post
        if ( $posts[0]->pub_date == $from ) array_shift($posts);

        if ( array_key_exists('show-only', $assoc_args) && $assoc_args['show-only'] == true ) {

            foreach ( $posts as $post ) {

                WP_CLI::line(sprintf('%s - %s', $post->pub_date, $post->post_text));

            }

            // exit and do nothing
            return;

        }

        foreach ( $posts as $post ) {

            $wp_post = array(
                # TODO: Change title to short status version of the title
                'post_title'=>sprintf('%s post from %s', $post->network, $post->author_username),
                'post_content'=>$post->post_text,
                'post_name'=>$post->post_id,
                'post_status' => 'draft',
                'post_date' => $post->pub_date,
                'post_author' => $wpuser->ID,
                'post_type' => 'post',
                'post_category' => array(0)
            );
            $post_id = wp_insert_post($wp_post);

            if ( $post_id ) {

                $links = $linkDAO->getLinksForPost($post->post_id, $post->network);
                $ls = array();

                foreach ($links as $link) {
                    if ( $link->expanded_url ) {
                        $ls[] = $link->expanded_url;
                    } else {
                        $ls[] = $link->url;
                    }
                }

                add_post_meta($post_id, '_thinkup_links', $ls);
                add_post_meta($post_id, '_thinkup_post', $post->post_text);
                add_post_meta($post_id, '_thinkup_source', $post->network);
                add_post_meta($post_id, '_thinkup_author_username', $post->author_username);
                add_post_meta($post_id, '_thinkup_author_fullname', $post->author_fullname);
                add_post_meta($post_id, '_thinkup_author_avatar', $post->author_avatar);
                add_post_meta($post_id, '_thinkup_post_id', $post->post_id, true);
                add_post_meta($post_id, '_thinkup_pending_processing', 'true');
                wp_set_post_terms( $post_id, array($post->network), 'source' );
                set_post_format( $post_id , 'status');

            } else {

                WP_CLI::warning(sprintf("%s post could not created in WP.\nDebug Info:\n%s", $post->network, print_r($wp_post, true)));

            }

        }

        // update the last imported article
        if ( $posts && $post->pub_date != $from )
            update_option( $last_import_option_key, $post->pub_date );


        $this->reset();

	}

    public function process( $args, $assoc_args ) {

        $this->verify();

        $query = new WP_Query( array('meta_key'=>'_thinkup_pending_processing', 'meta_value'=>'true', 'nopaging'=>true, 'post_status'=>'draft') );

        $wp_posts = $query->get_posts();

        foreach ( $wp_posts as $wp_post ) {

            $tu_post = new Thinkup_Post($wp_post);
            $tu_post->wp_post = $wp_post;

            $tu_post = apply_filters('thinkup_parse_original', $tu_post);

            # get the content from links and process it
            $tu_post = apply_filters('thinkup_get_remote_content', $tu_post);

            $new_content = array('ID'=>$wp_post->ID);

            if ( $tu_post->response_code == 200 ) {

                $tu_post = apply_filters('thinkup_extract_images', $tu_post);

                if ( array_key_exists('opengraph', $tu_post->metadata) ) {

                    $og = $tu_post->metadata['opengraph'];
                    if ( array_key_exists('og:image', $og) && !empty($og['og:image']) ) {
                        $tu_post->images[] = $og['og:image'];
                    }
                    if ( array_key_exists('og:description', $og) && !empty($og['og:description']) ) {
                        $new_content['post_excerpt'] = $og['og:description'];
                    }

                }

                if ( $tu_post->images ) {
                    foreach ( $tu_post->images as $image_url ) {
                        if ( is_wp_error($image = media_sideload_image($image_url, $tu_post->wp_post->ID)) ) {
                            WP_CLI::warning($image);
                        }
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

                update_post_meta($wp_post->ID, '_thinkup_pending_processing', false );

            }

        }


    }

	static function help() {
		WP_CLI::line( 'usage: wp example hello' );
	}

}

// Register the class as the 'example' command handler
WP_CLI::add_command( 'thinkup', 'ThinkUpApp_Import_Command' );