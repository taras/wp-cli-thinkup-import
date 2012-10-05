<?php

/**
 * Extract Open Graph metadata from Post html and add it to post's metadata
 * @param $html
 * @param $data
 */
function thinkup_opengraph_filter ( $tu_post ) {

    if ( $tu_post->html ) {
        $doc = new DomDocument();
        $doc->loadHTML($tu_post->html);
        $xpath = new DOMXPath($doc);
        $query = '//*/meta[starts-with(@property, \'og:\')]';
        $metas = $xpath->query($query);
        foreach ($metas as $meta) {
            $property = $meta->getAttribute('property');
            $content = $meta->getAttribute('content');
            $tu_post->metadata['opengraph'][$property] = $content;
        }
    }

    return $tu_post;
}
add_filter('thinkup_post_content', 'thinkup_opengraph_filter');

function thinkup_readability_filter($tu_post) {

    $html = $tu_post->html;

    // If we've got Tidy, let's clean up input.
    // This step is highly recommended - PHP's default HTML parser
    // often does a terrible job and results in strange output.
    if (function_exists('tidy_parse_string')) {
        $tidy = tidy_parse_string($html, array(), 'UTF8');
        $tidy->cleanRepair();
        $html = $tidy->value;
    }

    // give it to Readability
    $readability = new Readability($html, $tu_post->links[0]);

    // print debug output?
    // useful to compare against Arc90's original JS version -
    // simply click the bookmarklet with FireBug's console window open
    $readability->debug = WP_DEBUG;

    // convert links to footnotes?
    $readability->convertLinksToFootnotes = true;

    // process it
    $result = $readability->init();

    if ( $result ) {

        $tu_post->title = $readability->getTitle()->textContent;
        $tu_post->body = $readability->getContent()->innerHTML;

    }

    return $tu_post;
}
add_filter('thinkup_post_content', 'thinkup_readability_filter');

function thinkup_extract_images_filter( $tu_post ) {

    // this is ugly but I broke up the string to not have to escape it
    $regex = '/<img[^>]+>/i';

    if ( $tu_post->body && preg_match_all($regex, $tu_post->body, $matches) ) {

        $matches = $matches[0];

        foreach ( $matches as $match ) {

            $regex = '/(src)=("[^"]*")/i';
            if ( preg_match_all($regex, $match, $src, PREG_SET_ORDER) ) {
                $src = $src[0];
                $tu_post->images[] = trim($src[2], '"');
            }

        }

    }

    return $tu_post;

}
add_filter('thinkup_post_content', 'thinkup_extract_images_filter', 15);

/**
 * Extacts urls from content and adds them to metadata
 * @param $tu_post
 */
function thinkup_extract_urls_filter ( $tu_post ) {

    $regex = '/[a-z]+:\/\/[a-z0-9-_]+\.[a-z0-9-_@:~%&\?\+#\/.=]+[^:\.,\)\s*$]/i';

    if ( preg_match_all($regex, $tu_post->wp_post->post_content, $matches) ) {

        $tu_post->links = array_merge($tu_post->links, $matches[0]);

    }

    return $tu_post;

}
#add_filter('thinkup_parse_original', 'thinkup_extract_urls_filter');

/**
 * Extact #hashtags from content and add them to tags
 * @param $tu_post
 */
function thinkup_extract_hashtags_filter ( $tu_post ) {

    $regex = "/(^|[^&\w'\"]+)\#([a-zA-Z0-9_]+)/";

    if ( preg_match_all($regex, $tu_post->wp_post->post_content, $matches) ) {

        $tu_post->tags = array_merge($tu_post->tags, $matches[2]);

    }

    return $tu_post;

}
add_filter('thinkup_parse_original', 'thinkup_extract_hashtags_filter');

/**
 * Extact @ users from content and add them to mentions
 * @param $tu_post
 */
function thinkup_extract_at_filter ( $tu_post ) {

    $regex = '/(^|[^\w]+)\@([a-zA-Z0-9_]{1,15}(\/[a-zA-Z0-9-_]+)*)/';

    if ( preg_match_all($regex, $tu_post->wp_post->post_content, $matches) ) {

        $tu_post->mentions = array_merge($tu_post->mentions, $matches[0]);

    }

    return $tu_post;

}
add_filter('thinkup_parse_original', 'thinkup_extract_at_filter');

function thinkup_get_remote_content_filter ( $tu_post ) {

    $tu_post->response_code = null;

    # this is stupid but for now, I'm just taking the first list
    # TODO: Figure out how to deal with multiple links
    if ( $tu_post->links ) {

        $link = $tu_post->links[0];
        $response = wp_remote_get($link);

        if ( is_wp_error($response) ) {

            WP_CLI::warning($response->get_error_message());

        } else {

            $code = $response['response']['code'];

            if ( $code == 200 ) {

                $tu_post->html = $response['body'];

                $tu_post = apply_filters('thinkup_post_content', $tu_post);

            } else {

                WP_CLI::warning(sprintf('%s produced response code %s', $link, $code));

            }

            $tu_post->response_code = $code;

        }

    }

    return $tu_post;

}

add_filter('thinkup_get_remote_content', 'thinkup_get_remote_content_filter');