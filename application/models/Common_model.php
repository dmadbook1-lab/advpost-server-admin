<?php

class Common_model extends CI_Model
{
    function __construct()
    {
        parent::__construct();
    }

    public function checkLogin($table, $email , $password)
    {
        return $result = $this->db->get_Where($table, ['email' => $email ,'password' => $password])->row();
    }

public function daily_count($table)
{
    $data = [];

    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));

        $this->db->where('DATE(created_at)', $date);
        $data[] = $this->db->count_all_results($table);
        $this->db->reset_query();
    }

    return $data;
}
public function get_users_login_counts()
{
    $today = date('Y-m-d');
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end   = date('Y-m-d', strtotime('sunday this week'));
    $month_start = date('Y-m-01');
    $month_end   = date('Y-m-t');

    $data = [];

    // Total
    $data['total'] = $this->db->count_all_results('users');
    $this->db->reset_query();

    // Today
    $this->db->where('DATE(created_at)', $today);
    $data['today'] = $this->db->count_all_results('users');
    $this->db->reset_query();

    // This Week
    $this->db->where('DATE(created_at) >=', $week_start);
    $this->db->where('DATE(created_at) <=', $week_end);
    $data['week'] = $this->db->count_all_results('users');
    $this->db->reset_query();

    // This Month
    $this->db->where('DATE(created_at) >=', $month_start);
    $this->db->where('DATE(created_at) <=', $month_end);
    $data['month'] = $this->db->count_all_results('users');
    $this->db->reset_query();

    return $data;
}
public function get_new_post_counts()
{
    date_default_timezone_set('Asia/Kolkata');

    $today       = date('Y-m-d');
    $week_start  = date('Y-m-d', strtotime('monday this week'));
    $week_end    = date('Y-m-d', strtotime('sunday this week'));
    $month_start = date('Y-m-01');
    $month_end   = date('Y-m-t');

    $data = [];

    // --- Total Posts
    $data['total'] = $this->db->count_all('posts');

    // --- Today Posts
    $this->db->where('DATE(created_at)', $today);
    $data['today'] = $this->db->count_all_results('posts');
    $this->db->reset_query();

    // --- This Week Posts
    $this->db->where('DATE(created_at) >=', $week_start);
    $this->db->where('DATE(created_at) <=', $week_end);
    $data['week'] = $this->db->count_all_results('posts');
    $this->db->reset_query();

    // --- This Month Posts
    $this->db->where('DATE(created_at) >=', $month_start);
    $this->db->where('DATE(created_at) <=', $month_end);
    $data['month'] = $this->db->count_all_results('posts');
    $this->db->reset_query();

    return $data;
}

public function get_story_counts()
{
    date_default_timezone_set('Asia/Kolkata');

    $today       = date('Y-m-d');
    $week_start  = date('Y-m-d', strtotime('monday this week'));
    $week_end    = date('Y-m-d', strtotime('sunday this week'));
    $month_start = date('Y-m-01');
    $month_end   = date('Y-m-t');

    $data = [];

    // --- Total Stories
    $data['total'] = $this->db->count_all('story');
    $this->db->reset_query();

    // --- Today Stories
    $this->db->where('DATE(created_at)', $today);
    $data['today'] = $this->db->count_all_results('story');
    $this->db->reset_query();

    // --- This Week Stories
    $this->db->where('DATE(created_at) >=', $week_start);
    $this->db->where('DATE(created_at) <=', $week_end);
    $data['week'] = $this->db->count_all_results('story');
    $this->db->reset_query();

    // --- This Month Stories
    $this->db->where('DATE(created_at) >=', $month_start);
    $this->db->where('DATE(created_at) <=', $month_end);
    $data['month'] = $this->db->count_all_results('story');
    $this->db->reset_query();

    return $data;
}


public function get_boost_post_counts()
{
    date_default_timezone_set('Asia/Kolkata');

    $today       = date('Y-m-d');
    $week_start  = date('Y-m-d', strtotime('monday this week'));
    $week_end    = date('Y-m-d', strtotime('sunday this week'));
    $month_start = date('Y-m-01');
    $month_end   = date('Y-m-t');

    $data = [];

    // --- Total Boost Posts
    $data['total'] = $this->db->count_all('post_boost');
    $this->db->reset_query();

    // --- Today Boost Posts
    $this->db->where('DATE(created_at)', $today);
    $data['today'] = $this->db->count_all_results('post_boost');
    $this->db->reset_query();

    // --- This Week Boost Posts
    $this->db->where('DATE(created_at) >=', $week_start);
    $this->db->where('DATE(created_at) <=', $week_end);
    $data['week'] = $this->db->count_all_results('post_boost');
    $this->db->reset_query();

    // --- This Month Boost Posts
    $this->db->where('DATE(created_at) >=', $month_start);
    $this->db->where('DATE(created_at) <=', $month_end);
    $data['month'] = $this->db->count_all_results('post_boost');
    $this->db->reset_query();

    return $data;
}


private function count_range($table)
{
    $today       = date('Y-m-d');
    $week_start  = date('Y-m-d', strtotime('monday this week'));
    $week_end    = date('Y-m-d', strtotime('sunday this week'));
    $month_start = date('Y-m-01');
    $month_end   = date('Y-m-t');

    $data = [];

    $data['total'] = $this->db->count_all($table);

    $this->db->where('DATE(created_at)', $today);
    $data['today'] = $this->db->count_all_results($table);
    $this->db->reset_query();

    $this->db->where('DATE(created_at) >=', $week_start);
    $this->db->where('DATE(created_at) <=', $week_end);
    $data['week'] = $this->db->count_all_results($table);
    $this->db->reset_query();

    $this->db->where('DATE(created_at) >=', $month_start);
    $this->db->where('DATE(created_at) <=', $month_end);
    $data['month'] = $this->db->count_all_results($table);
    $this->db->reset_query();

    return $data;
}

//userlist
public function getAllUsersWithActivity()
{
    return $this->db
        ->select("
            id,
            CONCAT_WS(' ', first_name, last_name) AS name,
            email,
            mobile,
            username,
            gender,
            country,
            address,
            role
        ")
        ->from('users')
        ->where('is_deleted', 0)   // ✅ hide deleted users
        ->order_by('id', 'DESC')   // ✅ latest first
        ->get()
        ->result();
}



//userlist
public function getstory()
{
    $this->db->select('
        story.story_id,
        story.user_id,
        users.first_name,
        story.url,
        story.video_thumbnail,
        story.location,
        story.text,
        story.type
    ');
    $this->db->from('story');
    $this->db->join('users', 'users.id = story.user_id', 'left'); // join users table
    $this->db->order_by('story.story_id', 'DESC');

    return $this->db->get()->result(); // object result
}


public function getnotification()
{
    $this->db->select('
        notifications.id,
        notifications.target_type,
        notifications.user_id,
        notifications.notification,
        users.first_name,
        notifications.created_at,
    ');
    $this->db->from('notifications');
    $this->db->join('users', 'users.id = notifications.user_id', 'left'); // join with users table
    $this->db->order_by('notifications.id', 'DESC');

    return $this->db->get()->result(); // object result
}

 public function insert_user($data)
    {
        return $this->db->insert('web_user', $data);
    }
    
      public function insert_notification($data)
    {
        return $this->db->insert('notifications', $data);
    }
    
    
    
public function getAllSubadmin()
{
    $this->db->select("
        id,
name,
        email,
        mobile,
        username,
        gender,
        country,
        role
    ");
    $this->db->from('web_user');

    // ✅ EXACT MATCH (Screenshot verified)

    $this->db->order_by('id', 'DESC');

    return $this->db->get()->result();
}


public function get_report_post()
{
    $this->db->reset_query();

    $this->db->select('
        pr.user_id AS report_user_id,
        ru.first_name AS report_user_name,

        pr.post_id,
        pr.report_text_id,

        p.text AS post_text,

        COALESCE(pi.user_id, pv.user_id) AS post_user_id,
        pu.first_name AS post_user_name,

        COALESCE(pi.new_post, pv.post_video) AS media_file
    ');

    $this->db->from('post_report pr');

    $this->db->join('posts p', 'p.post_id = pr.post_id', 'left');

    $this->db->join('post_image pi', 'pi.post_id = pr.post_id', 'left');

    $this->db->join('post_video pv', 'pv.post_id = pr.post_id', 'left');

    // User who reported
    $this->db->join('users ru', 'ru.id = pr.user_id', 'left');

    // User who created post/media
    $this->db->join('users pu', 'pu.id = COALESCE(pi.user_id, pv.user_id)', 'left');

    $this->db->order_by('pr.post_id', 'DESC');

    return $this->db->get()->result();
}


}

