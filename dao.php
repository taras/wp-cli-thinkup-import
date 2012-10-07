<?php

class WordPressPostMySQLDAO extends PostMySQLDAO {

    public function getPostsByMentionIDInRange($mention_id, $network, $from, $until, $order_by="pub_date", $direction="DESC",
        $iterator=false, $is_public = false) {

        $direction = $direction=="DESC" ? "DESC": "ASC";

        $order_by = $this->sanitizeOrderBy($order_by);

        $q = "SELECT mp.mention_id, p.*, pub_date + interval #gmt_offset# hour as adj_pub_date ";
        $q .= "FROM #prefix#mentions_posts mp LEFT JOIN #prefix#posts p ON p.post_id=mp.post_id ";
        $q .= "WHERE mp.mention_id=:mention_id AND p.network=:network AND pub_date BETWEEN :from AND :until ";
        if ($order_by == 'reply_count_cache') {
            $q .= "AND reply_count_cache > 0 ";
        }
        if ($order_by == 'retweet_count_cache') {
            $q .= "AND retweet_count_cache > 0 ";
        }
        if ($is_public) {
            $q .= 'AND p.is_protected = 0 ';
        }
        $q .= "ORDER BY $order_by $direction ";
        $vars = array(
            ':mention_id'=> (string)$mention_id,
            ':network'=> $network,
            ':from' => $from,
            ':until' => $until
        );
        $ps = $this->execute($q, $vars);

        if ($iterator) {
            return (new PostIterator($ps));
        }

        $all_rows = $this->getDataRowsAsArrays($ps);
        $posts = array();
        if ($all_rows) {
            $post_keys_array = array();
            foreach ($all_rows as $row) {
                $post_keys_array[] = $row['id'];
            }

            // Get links
            $q = "SELECT * FROM #prefix#links WHERE post_key in (".implode(',', $post_keys_array).")";
            if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
            $ps = $this->execute($q);
            $all_link_rows = $this->getDataRowsAsArrays($ps);

            // Combine posts and links
            foreach ($all_rows as $post_row) {
                $post = new Post($post_row);
                foreach ($all_link_rows as $link_row) {
                    if ($link_row['post_key'] == $post->id) {
                        $post->addLink(new Link($link_row));
                    }
                }
                $posts[] = $post;
            }
        }
        return $posts;

    }

}