<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Validate token and return user id
     */
    public function validate_token_and_get_user($token)
    {
        if (empty($token)) {
            return false;
        }

        $this->db->select('id');
        $this->db->from('users'); // 👈 table name
        $this->db->where('token', $token);
        $this->db->where('status', '1');
        $query = $this->db->get();

        if ($query->num_rows() === 1) {
            return $query->row()->id; // ✅ return user_id
        }

        return false;
    }
    
    public function validate_token($token)
{
    if (empty($token)) {
        return false;
    }

    $this->db->select('id, email'); // 👈 email bhi lo
    $this->db->from('users');
    $this->db->where('token', $token);
    $this->db->where('status', '1');
    $query = $this->db->get();

    if ($query->num_rows() === 1) {
        return $query->row(); // ✅ FULL USER OBJECT return karo
    }

    return false;
}

public function getById($id)
{
    return $this->db->where('id', $id)->get('users')->row();
}

public function updateUser($id, $data)
{
    $this->db->where('id', $id)->update('users', $data);
}

    
     public function insert_story($data)
    {
        if (empty($data)) {
            return false;
        }

        $this->db->insert('story', $data); // 👈 table name

        if ($this->db->affected_rows() > 0) {
            return $this->db->insert_id(); // ✅ success
        }

        return false;
    }
    

  public function get_multiple_users($user_ids = [])
    {
        if (empty($user_ids)) return [];

        $this->db->select('id, username, first_name, last_name, mobile, profile_pic');
        $this->db->from('users');
        $this->db->where_in('id', $user_ids);
        $query = $this->db->get();

        $users = [];
        foreach ($query->result() as $row) {
            $users[$row->id] = $row;
        }

        return $users;
    }

 public function get_distinct_hashtags()
    {
        $this->db->distinct();
        $this->db->select('text');       // <-- column name where hashtags are stored
        $this->db->from('hash_tag');     // <-- table name
        $query = $this->db->get();

        return $query->result();
    }
    
      public function get_user($user_id)
    {
        if (empty($user_id)) return null;

        $this->db->select('id, username, first_name, last_name, mobile, profile_pic');
        $this->db->from('users'); // <-- table name
        $this->db->where('id', $user_id);
        $this->db->where('status', '1'); // optional: only active users
        $query = $this->db->get();

        return $query->num_rows() === 1 ? $query->row() : null;
    }
    
    
      public function get_my_blocked_users($user_id)
    {
        if (empty($user_id)) return [];

        $this->db->select('block_user_id');
        $this->db->from('profile_block'); // assuming table for blocked users
        $this->db->where('user_id', $user_id);
        $query = $this->db->get();

        $blocked_ids = array_map(function($row) {
            return (int)$row->blocked_user_id;
        }, $query->result());

        return $blocked_ids;
    }

    /**
     * Check if $user_id follows $other_user_id
     * Returns true/false
     */
    public function is_followings($user_id, $other_user_id)
    {
        if (empty($user_id) || empty($other_user_id)) return false;

        $this->db->from('follow');
        $this->db->where('from_user', $user_id);
        $this->db->where('to_user', $other_user_id);

        return $this->db->count_all_results() > 0;
    }


 public function get_search_list_with_user($user_id)
    {
        $this->db->select('
            su.id as search_id,
            su.search_user_id,
            su.username,
            u.first_name,
            u.last_name,
            u.profile_pic
        ');
        $this->db->from('search_username su');
        $this->db->join('users u', 'u.id = su.search_user_id', 'left');
        $this->db->where('su.user_id', $user_id);
        $this->db->order_by('su.id', 'DESC');

        return $this->db->get()->result();
    }
    
    



  public function check_request($from_user, $to_user)
    {
        return $this->db
            ->where('from_user', $from_user)
            ->where('to_user', $to_user)
            ->get('friends_requests')
            ->num_rows() > 0;
    }

    /* =====================================
       DELETE FOLLOW REQUEST
    ===================================== */
    public function delete_request($from_user, $to_user)
    {
        return $this->db
            ->where('from_user', $from_user)
            ->where('to_user', $to_user)
            ->delete('friends_requests');
    }

    /* =====================================
       CHECK FOLLOWING
    ===================================== */
    public function check_follow($from_user, $to_user)
    {
        return $this->db
            ->where('from_user', $from_user)
            ->where('to_user', $to_user)
            ->where('status', 'follow')
            ->get('follow')
            ->num_rows() > 0;
    }

    /* =====================================
       UNFOLLOW
    ===================================== */
    public function unfollow($from_user, $to_user)
    {
        return $this->db
            ->where('from_user', $from_user)
            ->where('to_user', $to_user)
            ->delete('follow');
    }

    /* =====================================
       FOLLOW
    ===================================== */
    public function follow($data)
    {
        return $this->db->insert('follow', $data);
    }

    /* =====================================
       ADD FOLLOW REQUEST
    ===================================== */
    public function add_request($data)
    {
        return $this->db->insert('friends_requests', [
            'from_user' => $data['from_user'],
            'to_user'   => $data['to_user'],
            'date'      => $data['date'],
            'status'    => 'Pending'
        ]);
    }
    
    public function get_users($user_id)
{
    return $this->db
        ->select('id, is_private') // 👈 MUST BE HERE
        ->where('id', $user_id)
        ->get('users')
        ->row();
}
  public function get_all_avatar()
    {
        $this->db->select('id, name, image, gender');
        $this->db->from('avtar');

        // Optional: only active avatars
        // agar status column hai to

        $this->db->order_by('id', 'ASC');

        $query = $this->db->get();

        return $query->num_rows() > 0 ? $query->result() : [];
    }

    
        public function get_by_id($id)
    {
        return $this->db
            ->where('id', $id)
            ->limit(1)
            ->get('user_login_status')
            ->row();
    }

    /* =====================================
       SITE SETUP (COLORS)
       Table: site_setup
    ===================================== */
    public function get_first()
    {
        return $this->db
            ->limit(1)
            ->get('site_setup')
            ->row();
    }
    
public function get_my_followers($user_id)
{
    $this->db->select('
        f.follow_id,
        f.from_user,
        f.to_user,
        f.friend_type,
        f.date,
        f.status,
        u.first_name,
        u.last_name,
        u.username,
        u.profile_pic
    ');
    $this->db->from('follow f');
    $this->db->join('users u', 'u.id = f.from_user', 'left');
    $this->db->where('f.to_user', $user_id);

    // ✅ FIX HERE
    $this->db->where('f.status', 'follow');

    $this->db->order_by('f.follow_id', 'DESC');

    return $this->db->get()->result();
}

public function check_follow_back($from_user, $to_user)
{
    return $this->db
        ->where('from_user', $from_user)
        ->where('to_user', $to_user)
        ->get('follow')
        ->row();
}

public function check_friend_request($from_user, $to_user)
{
    return $this->db
        ->where('from_user', $from_user)
        ->where('to_user', $to_user)
        ->get('friends_request')
        ->row();
}


      public function get_all_keys()
 {
        return $this->db
            ->select('
                id,
                text,
                public_key,
                mode,
                status,
                country_code,
                currency_code
            ')
            ->from('payment_gateway_key')
            ->where('status', 1)
            ->order_by('id', 'ASC')
            ->get()
            ->result();
    }
    
    
    
}

