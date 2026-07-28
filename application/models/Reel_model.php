<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reel_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    // 🔹 Get blocked user IDs for logged-in user
    public function get_blocked_user_ids($user_id)
    {
        if (!$user_id) return [];

        $this->db->select('block_user_id');
        $this->db->from('profile_block');
        $this->db->where('user_id', $user_id);
        $query = $this->db->get();

        return array_column($query->result_array(), 'blocked_user_id');
    }

    // 🔹 Fetch reels excluding blocked users
    public function get_reels($blockedUserIds = [], $limit = 10, $offset = 0)
    {
        $this->db->from('posts');
        $this->db->where('post_type', 'reel');
        $this->db->where('status', 1);
        $this->db->where('is_delete', 0);
        if (!empty($blockedUserIds)) {
            $this->db->where_not_in('user_id', $blockedUserIds);
        }
        $this->db->order_by('post_id', 'DESC');
        $this->db->limit($limit, $offset);
        return $this->db->get()->result();
    }

    // 🔹 Get all videos for a reel
public function get_reel_videos($reel_id)
{
    if (!$reel_id) return [];
    $this->db->from('post_video');
    $this->db->where('post_id', $reel_id);
    return $this->db->get()->result();
}


    // 🔹 Check if logged-in user follows the reel owner
    public function is_follow($login_user_id, $target_user_id)
    {
        $this->db->from('follow');
        $this->db->where(['follow_id' => $login_user_id, 'from_user' => $target_user_id]);
        return $this->db->count_all_results() > 0;
    }

    // 🔹 Check if logged-in user liked the reel
    public function is_liked($reel_id, $user_id)
    {
        $this->db->from('reel_like');
        $this->db->where(['reel_id' => $reel_id, 'user_id' => $user_id]);
        return $this->db->count_all_results() > 0;
    }

    // 🔹 Count total likes of a reel
    public function reel_like($reel_id)
    {
        $this->db->from('reel_like');
        $this->db->where('reel_id', $reel_id);
        return $this->db->count_all_results();
    }

    // 🔹 Count total comments of a reel
    public function count_comment($reel_id)
    {
        $this->db->from('reel_comment');
        $this->db->where('reel_id', $reel_id);
        return $this->db->count_all_results();
    }

    // ====================== POSTS (IMAGE + VIDEO) ======================



    // 🔹 Count all posts (for pagination)
    public function get_all_posts($blockedUserIds = [])
    {
        $this->db->from('posts');
        $this->db->where('post_type !=', 'reel');
        $this->db->where('status', 1);
        $this->db->where('is_delete', 0);
        if (!empty($blockedUserIds)) {
            $this->db->where_not_in('user_id', $blockedUserIds);
        }
        return $this->db->get()->result();
    }

public function get_posts($blockedUserIds, $limit, $offset, $hashtag = null)
{
    $this->db->select('*');
    $this->db->from('posts');

    if (!empty($blockedUserIds)) {
        $this->db->where_not_in('user_id', $blockedUserIds);
    }

    // ✅ ONLY FILTER IF HASHTAG IS SENT
    if (!empty($hashtag)) {
        $this->db->like('text', $hashtag);
    }

    $this->db->order_by('post_id', 'DESC');
    $this->db->limit($limit, $offset);

    return $this->db->get()->result();
}
    /* ================= POST IMAGES ================= */
    public function get_post_images($post_id)
    {
        return $this->db
            ->where('post_id', $post_id)
            ->get('post_image')
            ->result();
    }

    /* ================= POST VIDEOS (FIXED) ================= */
    public function get_post_videos($post_id)
    {
        return $this->db
            ->where('post_id', $post_id)   // ⚠️ agar tumhare table me column alag ho to yahin change hoga
            ->get('post_video')            // ⚠️ table name exact hona chahiye
            ->result();
    }


    // 🔹 Count likes of a post
    public function count_like($post_id)
    {
        $this->db->from('post_like');
        $this->db->where('post_id', $post_id);
        return $this->db->count_all_results();
    }

    // 🔹 Check if a post is bookmarked
    public function is_bookmarked($post_id, $user_id)
    {
        $this->db->from('bookmark_post');
        $this->db->where(['post_id' => $post_id, 'user_id' => $user_id]);
        return $this->db->count_all_results() > 0;
    }
}
