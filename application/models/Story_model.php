<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Story_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get single story row with optional columns
     */
    public function get_row($where = [], $select = '*')
    {
        if (!empty($select)) {
            $this->db->select($select);
        }
        $this->db->from('story'); // table name
        $this->db->where($where);
        $query = $this->db->get();

        return $query->num_rows() > 0 ? $query->row() : null;
    }

    /**
     * Get all users who viewed the story excluding owner
     */
    public function get_where($where = [], $exclude_user_id = 0)
    {
        $this->db->select('*');
        $this->db->from('view_story'); // table to track story views
        if (!empty($where)) {
            $this->db->where($where);
        }
        if ($exclude_user_id) {
            $this->db->where('user_id !=', $exclude_user_id);
        }
        $query = $this->db->get();

        return $query->num_rows() > 0 ? $query->result() : [];
    }
    
     public function get_story_by_id_user($story_id, $user_id)
    {
        return $this->db
            ->where('story_id', $story_id)
            ->where('user_id', $user_id)
            ->get('story')
            ->row();
    }

    /* =====================================================
       DELETE STORY (ONLY OWNER CAN DELETE)
    ===================================================== */
    public function delete_story($story_id, $user_id)
    {
        $this->db
            ->where('story_id', $story_id)
            ->where('user_id', $user_id)
            ->delete('story');

        return ($this->db->affected_rows() > 0);
    }
    
        public function get_all_languages()
    {
        $this->db->select('
            status_id AS status_id,
            language,
            status,
            default_status,
            country,
            language_alignment
        ');

        $this->db->from('language_statuses');
        $this->db->order_by('language', 'ASC');

        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return $query->result();   // return array of objects
        }

        return [];
    }
    
    
      public function get_language_by_status_id($status_id)
    {
        $query = $this->db
            ->select('status_id AS status_id, language, status, default_status, country, language_alignment')
            ->from('language_statuses')
            ->where('status_id', $status_id)
            ->limit(1)
            ->get();

        if ($query->num_rows() > 0) {
            return $query->row();
        }

        return null;
    }

    /* =======================================================
       GET DEFAULT LANGUAGE
       Returns the language with default_status = 1
    ======================================================== */
    public function get_default_language()
    {
        $query = $this->db
            ->select('status_id AS status_id, language, status, default_status, country, language_alignment')
            ->from('language_statuses')
            ->where('default_status', 1)
            ->limit(1)
            ->get();

        if ($query->num_rows() > 0) {
            return $query->row();
        }

        return null;
    }
    
    public function get_boosts_by_user($user_id)
{
    return $this->db
        ->select('id, post_id, user_id, start_date, end_date, days, price, status, created_at, payment_method, total_price')
        ->from('post_boost')
        ->where('user_id', $user_id)
        ->order_by('created_at', 'DESC')
        ->get()
        ->result();
}

public function get_post($post_id)
{
    return $this->db
        ->where('post_id', $post_id)
        ->get('posts')
        ->row();
}

public function get_images_by_post($post_id)
{
    return $this->db
        ->select('id, post_id, new_post')
        ->where('post_id', $post_id)
        ->get('post_image')
        ->result();
}

public function get_videos_by_post($post_id)
{
    return $this->db
        ->select('id, post_id, post_video, post_video_thumbnail')
        ->where('post_id', $post_id)
        ->get('post_video')
        ->result();
}

}
