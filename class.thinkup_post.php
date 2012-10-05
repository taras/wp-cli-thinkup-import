<?php
if ( !class_exists('Thinkup_Post') ) {

    class Thinkup_Post {

        function __construct($wp_post) {

            $this->links = get_post_meta($wp_post->ID, '_thinkup_links', true);

        }

        // network that post came from ie. Twitter, Facebook Page
        var $network;

        // corresponding WordPress post
        var $wp_post;

        // discovered metadata for the post
        var $metadata = array();

        // links in this post
        var $links = array();

        // tags in this post
        var $tags = array();

        // mentions in this post
        var $mentions = array();

        // html of the page that post is referring to
        var $html;

        // stores urls to images found in the post
        var $images = array();

        // stores extracted content
        var $body = null;

    }

}
