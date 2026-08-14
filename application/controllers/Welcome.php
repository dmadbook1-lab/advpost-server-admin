<?php
defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . 'libraries/RestController.php';
require_once VENDORPATH . 'autoload.php';

use chriskacerguis\RestServer\RestController;
use Google\Auth\OAuth2;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class Welcome extends CI_Controller
{
public function __construct()
{
    parent::__construct();

    $this->load->library('session');
    $this->load->model('Common_model');
    $this->load->database();
    $this->load->config('razorpay');
    $this->load->helper(['url', 'form', 'html']);

    date_default_timezone_set('Asia/Kolkata');

    $allowedMethods = [
        'login',
        'index',
        'privacy_policy',
        'terms_condition',
        'child_safety',
        'contact_us',
        'account_deletion',
        'data_deletion',
    ];

    if (!in_array($this->router->fetch_method(), $allowedMethods)) {

        if (
            !$this->session->userdata('is_login') ||
            !in_array($this->session->userdata('role'), ['Admin', 'Subadmin', 'user'])
        ) {
            redirect('welcome/index');
            exit;
        }
    }
}


    // ===============================
    // LOGIN PAGE
    // ===============================
    public function index()
    {
        $this->load->view('admin_view/login');
    }
    
    
    
    public function login()
{
    if ($this->input->post()) {

        $email    = $this->input->post('email');
        $password = $this->input->post('password');

        $user = $this->Common_model->checkLogin('users', $email, $password);

        if ($user) {

            $this->session->set_userdata([
                'id'       => $user->id,
                'email'    => $user->email,
                'name'     => $user->name,
                'role'     => $user->role,
                'is_login' => true
            ]);

            redirect('welcome/dashboard');
        }

        echo "<script>alert('Invalid login');</script>";
    }

    $this->load->view('admin_view/login');
}


    // ===============================
    // LOGIN ACTION
    // ===============================
    // public function login()
    // {
    //     if ($this->input->post()) {

    //         $email    = $this->input->post('email');
    //         $password = $this->input->post('password');

    //         $chk_login = $this->Common_model->checkLogin('users', $email, $password);

    //         if ($chk_login) {

    //             // ❌ Block normal users
    //             if (!in_array($chk_login->role, ['Admin', 'Subadmin', 'user'])) {
    //                 echo "<script>alert('Access Denied! Only Admin/Subadmin allowed');</script>";
    //                 $this->load->view('admin_view/login');
    //                 return;
    //             }

    //             // ✅ Set session
    //             $sessionData = [
    //                 'id'       => $chk_login->id,
    //                 'email'    => $chk_login->email,
    //                 'name'     => $chk_login->name,
    //                 'role'     => $chk_login->role,
    //                 'is_login' => true
    //             ];

    //             $this->session->set_userdata($sessionData);

    //             redirect('welcome/dashboard');

    //         } else {
    //             echo "<script>alert('Invalid Email or Password');</script>";
    //             $this->load->view('admin_view/login');
    //         }

    //     } else {
    //         $this->load->view('admin_view/login.php');
    //     }
    // }


	public function logout()
	{
		$this->session->unset_userdata('email');
		$this->session->unset_userdata('password');
		$this->load->driver('cache');
		$this->session->sess_destroy();
		$this->cache->clean();
		ob_clean();
		return redirect('welcome');
	}

public function dashboard()
{
    $this->load->model('Common_model');
    date_default_timezone_set('Asia/Kolkata');

    // 🔐 ROLE (must be set)
    $data['user_role'] = $this->session->userdata('role');

    // fallback (extra safety)
    if (empty($data['user_role'])) {
        $data['user_role'] = 'user';
    }

    /* USERS */
    $users = $this->Common_model->get_users_login_counts();
    $data['users_total'] = $users['total'];
    $data['users_today'] = $users['today'];
    $data['users_week']  = $users['week'];
    $data['users_month'] = $users['month'];

    /* POSTS */
    $posts = $this->Common_model->get_new_post_counts();
    $data['posts_total'] = $posts['total'];
    $data['posts_today'] = $posts['today'];
    $data['posts_week']  = $posts['week'];
    $data['posts_month'] = $posts['month'];

    /* STORY */
    $story = $this->Common_model->get_story_counts();
    $data['story_total'] = $story['total'];
    $data['story_today'] = $story['today'];
    $data['story_week']  = $story['week'];
    $data['story_month'] = $story['month'];

    /* BOOST */
    $boost = $this->Common_model->get_boost_post_counts();
    $data['boost_total'] = $boost['total'];
    $data['boost_today'] = $boost['today'];
    $data['boost_week']  = $boost['week'];
    $data['boost_month'] = $boost['month'];

    /* GRAPH */
    $days = [];
    for ($i = 6; $i >= 0; $i--) {
        $days[] = date('d M', strtotime("-$i days"));
    }

    $data['trend_days']  = json_encode($days);
    $data['trend_users'] = json_encode($this->Common_model->daily_count('users'));
    $data['trend_posts'] = json_encode($this->Common_model->daily_count('posts'));
    $data['trend_story'] = json_encode($this->Common_model->daily_count('story'));
    $data['trend_boost'] = json_encode($this->Common_model->daily_count('post_boost'));

    $this->load->view('admin_view/dashboard', $data);
}

public function users()
{
    $data['users'] = $this->Common_model->getAllUsersWithActivity();

    // 🔥 SESSION ROLE USE KARO
    $data['user_role'] = $this->session->userdata('role') ?? 'user';

    $this->load->view('admin_view/userlist', $data);
}
public function edit($id)
{
    $this->load->model('User_model');
    $data['user'] = $this->User_model->getById($id);

    $this->load->view('admin_view/edit_user', $data);
}


public function update()
{
    $this->load->model('User_model');

    $id = $this->input->post('id');

    $data = [
        'first_name' => $this->input->post('first_name'),
        'username'  => $this->input->post('username'),
        'email'      => $this->input->post('email'),
        'mobile'     => $this->input->post('mobile'),
        'gender'  => $this->input->post('gender'),
        'country'      => $this->input->post('country'),
        'address'     => $this->input->post('address'),
    ];

    $this->User_model->updateUser($id, $data);

    redirect('welcome/users');
}
public function delete($id)
{
    $this->db->where('id', $id);
    $this->db->update('users', ['is_deleted' => 1]);

    redirect('welcome/users');
}

public function event()
{
    date_default_timezone_set('Asia/Kolkata');

    $onlyLocal = $this->input->get('only_local');
    if ($onlyLocal === null) {
        $onlyLocal = '1'; // default: show posts that have local media
    }
    $onlyLocal = ($onlyLocal === '1');

    $this->db->select("
        posts.post_id,
        posts.user_id,
        users.first_name,
        posts.text,
        posts.location,
        posts.post_type,
        GROUP_CONCAT(DISTINCT post_image.new_post) AS images,
        GROUP_CONCAT(DISTINCT post_video.post_video) AS videos
    ");
    $this->db->from('posts');
    $this->db->join('users', 'users.id = posts.user_id', 'left');
    $this->db->join('post_image', 'post_image.post_id = posts.post_id', 'left');
    $this->db->join('post_video', 'post_video.post_id = posts.post_id', 'left');
    $this->db->group_by('posts.post_id');
    $this->db->order_by('posts.post_id', 'DESC');

    $posts = $this->db->get()->result();

    $this->load->helper('media');

    $localCount = 0;
    $filtered = [];
    foreach ($posts as $post) {
        $hasLocal = false;
        foreach (explode(',', (string) ($post->images ?? '')) as $img) {
            if (adv_media_exists(trim($img))) {
                $hasLocal = true;
                break;
            }
        }
        if (!$hasLocal) {
            foreach (explode(',', (string) ($post->videos ?? '')) as $vid) {
                if (adv_media_exists(trim($vid))) {
                    $hasLocal = true;
                    break;
                }
            }
        }
        if ($hasLocal) {
            $localCount++;
            $filtered[] = $post;
        } elseif (!$onlyLocal) {
            $filtered[] = $post;
        }
    }

    // Prefer posts with local media when showing all
    if (!$onlyLocal) {
        usort($filtered, function ($a, $b) {
            $score = function ($post) {
                $n = 0;
                foreach (explode(',', (string) ($post->images ?? '')) as $img) {
                    if (adv_media_exists(trim($img))) {
                        $n++;
                    }
                }
                foreach (explode(',', (string) ($post->videos ?? '')) as $vid) {
                    if (adv_media_exists(trim($vid))) {
                        $n++;
                    }
                }
                return $n;
            };
            $diff = $score($b) - $score($a);
            return $diff !== 0 ? $diff : (((int) $b->post_id) - ((int) $a->post_id));
        });
    }

    $data['posts'] = $filtered;
    $data['only_local'] = $onlyLocal;
    $data['local_media_posts'] = $localCount;
    $data['total_posts'] = count($posts);
    $data['user_role'] = $this->session->userdata('role') ?? 'user';

    $this->load->view('admin_view/event', $data);
}

public function edit_post($id)
   {
    $data['post'] = $this->db
        ->where('post_id', $id)
        ->get('posts')
        ->row();

    if (!$data['post']) {
        show_error('Post not found');
    }

    $this->load->view('admin_view/edit_post', $data);
   }


public function update_post()
  { 
    $id = $this->input->post('post_id');

    $data = [
        'text'     => $this->input->post('text'),
        'location' => $this->input->post('location'),
        'post_type'=> $this->input->post('post_type'),
    ];

    $this->db->where('post_id', $id);
    $this->db->update('posts', $data);

    redirect('welcome/event');
   }
public function report()
{
    $data['stories'] = $this->Common_model->getstory();

    $data['user_role'] = $this->session->userdata('role') ?? 'user';


    $this->load->view('admin_view/report', $data);
}


public function boost_post()
{
    date_default_timezone_set('Asia/Kolkata');

    // Fetch boost post list with user name
    $this->db->select('
        post_boost.*,
        users.first_name
    ');
    $this->db->from('post_boost');
    $this->db->join('users', 'users.id = post_boost.user_id', 'left'); // join with users table
    $this->db->order_by('post_boost.id', 'DESC');

    $query = $this->db->get();
    $data['boost'] = $query->result();

    $data['user_role'] = $this->session->userdata('role') ?? 'user';

    $this->load->view('admin_view/boost_post', $data);
}

public function notification_list()
{
    $data['notification'] = $this->Common_model->getnotification();

    $data['user_role'] = $this->session->userdata('role') ?? 'Subadmin';


    $this->load->view('admin_view/notification_list', $data);
}

  public function notification()
    {
        $this->load->view('admin_view/notification');
    }

    /* ===============================
       FETCH USERS FOR DROPDOWN (AJAX)
    =============================== */
    public function fetch_names()
    {
        $users = $this->db
            ->select('id, first_name')
            ->from('users')
            ->where('first_name !=', '')
            ->get()
            ->result_array();

        // JSON response
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($users));
    }
public function store_notification()
{
    $target_type  = $this->input->post('target_type'); // user | all_user
    $user_id      = $this->input->post('name_select');
    $notification = $this->input->post('notification');

    if (!$target_type || !$notification) {
        $this->session->set_flashdata('error', 'Missing required fields');
        redirect('welcome/notification');
    }

    /* ===============================
       1️⃣ SAVE NOTIFICATION TO DB
    =============================== */
    $dbData = [
        'target_type'  => $target_type,
        'user_id'      => ($target_type === 'user') ? $user_id : null,
        'notification' => $notification,
        'created_at'   => date('Y-m-d H:i:s')
    ];

    $this->Common_model->insert_notification($dbData);

    /* ===============================
       2️⃣ FIREBASE AUTH SETUP
    =============================== */
    $jsonKeyPath = FIREBASE_CREDENTIALS;
    if (!file_exists($jsonKeyPath)) {
        $this->session->set_flashdata('error', 'Firebase key missing');
        redirect('welcome/notification');
    }

    $jsonKey = json_decode(file_get_contents($jsonKeyPath), true);

    $oauth = new OAuth2([
        'audience'           => 'https://oauth2.googleapis.com/token',
        'issuer'             => $jsonKey['client_email'],
        'signingAlgorithm'   => 'RS256',
        'signingKey'         => $jsonKey['private_key'],
        'tokenCredentialUri' => 'https://oauth2.googleapis.com/token',
        'scope'              => 'https://www.googleapis.com/auth/firebase.messaging'
    ]);

    $tokenData   = $oauth->fetchAuthToken();
    $accessToken = $tokenData['access_token'] ?? null;

    if (!$accessToken) {
        $this->session->set_flashdata('error', 'Firebase access token error');
        redirect('welcome/notification');
    }

    /* ===============================
       3️⃣ COMMON FCM DATA
    =============================== */
    $projectId = 'advpost';
    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    $basePayload = [
        'message' => [
            'notification' => [
                'title' => 'ADVPOST',
                'body'  => $notification
            ],
            'android' => [
                'priority' => 'high'
            ]
        ]
    ];

    /* ===============================
       4️⃣ SEND NOTIFICATION
    =============================== */

    // 🔹 SINGLE USER
    if ($target_type === 'user' && $user_id) {

        $token = $this->db->select('device_token')
                          ->where('id', $user_id)
                          ->get('users')
                          ->row('device_token');

        if (!$token) {
            $this->session->set_flashdata('error', 'User device token not found');
            redirect('welcome/notification_list');
        }

        $payload = $basePayload;
        $payload['message']['token'] = $token;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_exec($ch);
        curl_close($ch);
    }

    // 🔹 ALL USERS (FROM DB notification_topic = all_user)
    else {

        $users = $this->db->select('device_token')
                          ->where('notification_topic', 'all_user')
                          ->where('device_token IS NOT NULL', null, false)
                          ->get('users')
                          ->result();

        if (empty($users)) {
            $this->session->set_flashdata('error', 'No users found for all_user');
            redirect('welcome/notification_list');
        }

        foreach ($users as $row) {

            $payload = $basePayload;
            $payload['message']['token'] = $row->device_token;

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_exec($ch);
            curl_close($ch);
        }
    }

    /* ===============================
       5️⃣ SUCCESS
    =============================== */
    $this->session->set_flashdata('success', 'Notification sent successfully');
    redirect('welcome/notification_list');
}


public function payment($post_id = null)
{
    if (!$this->session->userdata('is_login')) {
        redirect('welcome/login');
        return;
    }

    if (empty($post_id)) {
        show_error('Invalid post id');
        return;
    }

    $boost = $this->db
        ->where('post_id', $post_id)
        ->where('status', 'pending')
        ->get('post_boost')
        ->row();

    if (!$boost) {
        show_error('Boost record not found');
        return;
    }

    $data = [
        'post_id' => $post_id,
        'amount'  => (float) $boost->total_price
    ];

    $this->load->view('admin_view/payment', $data);
}


public function create_razorpay_order()
{
    $input = json_decode(file_get_contents("php://input"), true);
    $post_id = $input['post_id'] ?? null;

    if (!$post_id) {
        echo json_encode(['error' => 'Invalid post id']);
        return;
    }

    // 🔐 DB se exact decimal amount
    $boost = $this->db
        ->where('post_id', $post_id)
        ->where('status', 'pending')
        ->get('post_boost')
        ->row();

    if (!$boost) {
        echo json_encode(['error' => 'Boost not found']);
        return;
    }

    $amount_rupees = (float) $boost->total_price; // 81.6
    $amount_paise  = (int) round($amount_rupees * 100); // 8160

    require VENDORPATH . 'razorpay/razorpay/Razorpay.php';
    $api = new \Razorpay\Api\Api(
        'rzp_test_SHtjbZaBfQbQDw',
        '5AVQU020MOsAlUra5fmna7mn'
    );

    $order = $api->order->create([
        'receipt'  => 'post_'.$post_id.'_'.time(),
        'amount'   => $amount_paise,
        'currency' => 'INR'
    ]);

    echo json_encode([
        'order_id' => $order['id'],
        'amount'   => $order['amount'] // paise
    ]);
}
   
 public function payment_success()
{
    $payment_id = $this->input->get('payment_id');
    $post_id    = $this->input->get('post_id');

    if (!$payment_id || !$post_id) {
        show_error('Invalid payment response');
    }

    // 🔐 Start DB Transaction
    $this->db->trans_start();

    // 1️⃣ UPDATE post_boost TABLE
    $this->db->where('post_id', $post_id)
             ->update('post_boost', [
                 'payment_id'  => $payment_id,
                 'status'      => 'paid',
                 'isBoostPost' => 1
                 ]);

    // 2️⃣ UPDATE posts TABLE
    $this->db->where('post_id', $post_id)
             ->update('posts', [
                 'isBoostPost' => 1
             ]);

    // 🔐 Complete Transaction
    $this->db->trans_complete();

    if ($this->db->trans_status() === FALSE) {
        show_error('Payment update failed');
    }

    echo "<h2>✅ Payment Successful & Post Boost Activated</h2>";
}

public function update_post_boost()
{
    header('Content-Type: application/json');
    date_default_timezone_set('Asia/Kolkata');

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['post_id'])) {
        echo json_encode([
            "status" => false,
            "message" => "Post ID missing"
        ]);
        return;
    }

    $post_id = $data['post_id'];

    // 🔍 Check already exists
    $exists = $this->db->where('post_id', $post_id)
                       ->get('post_boost')
                       ->row();

    if ($exists) {
        $this->db->update('post_boost', [
            'status'      => 1,
            'isBoostPost' => 1,
            'updated_at'  => date('Y-m-d H:i:s')
        ], ['post_id' => $post_id]);
    } else {
        $this->db->insert('post_boost', [
            'post_id'     => $post_id,
            'status'      => 1,
            'isBoostPost' => 1,
            'created_at'  => date('Y-m-d H:i:s')
        ]);
    }

    echo json_encode([
        "status" => true,
        "message" => "Post boosted"
    ]);
}



 public function create_order()
{
    header('Content-Type: application/json');

    if (!file_exists(VENDORPATH . 'autoload.php')) {
        echo json_encode([
            'status' => false,
            'error'  => 'vendor/autoload.php not found'
        ]);
        return;
    }

    require_once VENDORPATH . 'autoload.php';

    try {
        $amount = (int) $this->input->post('amount');

        if ($amount <= 0) {
            echo json_encode([
                'status' => false,
                'error'  => 'Invalid amount'
            ]);
            return;
        }

        $api = new \Razorpay\Api\Api(
            'rzp_test_xxxxxxxx',
            'xxxxxxxxxxxxxxx'
        );

        $order = $api->order->create([
            'receipt'         => 'rcpt_' . time(),
            'amount'          => $amount * 100,
            'currency'        => 'INR',
            'payment_capture' => 1
        ]);

        echo json_encode([
            'status'   => true,
            'order_id' => $order['id'],
            'amount'   => $order['amount']
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'status' => false,
            'error'  => $e->getMessage()
        ]);
    }
}


public function verify_payment()
{
    $this->load->database();
    $this->load->config('razorpay');

    echo "VERIFY PAYMENT HIT<br>";

    $data = json_decode(file_get_contents("php://input"), true);
    print_r($data); echo "<br>";

    if (!$data) {
        echo "No data received";
        return;
    }

    $api = new Api(
        $this->config->item('razorpay_key_id'),
        $this->config->item('razorpay_key_secret')
    );

    try {
        $api->utility->verifyPaymentSignature([
            'razorpay_order_id'    => $data['razorpay_order_id'],
            'razorpay_payment_id' => $data['razorpay_payment_id'],
            'razorpay_signature'  => $data['razorpay_signature']
        ]);

        echo "Signature verified<br>";

        // ✅ INSERT NEW ROW
        $insert = [
            'original_order_id'   => $data['order_id'],
            'amount'              => $data['amount'],
            'payment_status'      => 'paid',
            'razorpay_order_id'   => $data['razorpay_order_id'],
            'razorpay_payment_id' => $data['razorpay_payment_id'],
            'paid_at'             => date('Y-m-d H:i:s')
        ];

        $this->db->insert('orders', $insert);

        if ($this->db->affected_rows() > 0) {
            echo "PAYMENT SUCCESS & DATA INSERTED";
        } else {
            echo "INSERT FAILED";
        }

    } catch (Exception $e) {
        echo "Verification Failed: ".$e->getMessage();
    }
}


   public function sub_admin()
    {
        $this->load->view('admin_view/sub_admin');
    }


   public function sub_admin_list()
{
   $data['users'] = $this->Common_model->getAllSubadmin();

    // 🔥 SESSION ROLE USE KARO
    $data['user_role'] = $this->session->userdata('role') ?? 'user';


    $this->load->view('admin_view/sub_admin_list', $data);
}


 public function store()
    {
        $data = [
            'name'         => $this->input->post('name'),
            'email'        => $this->input->post('email'),
            'username'     => $this->input->post('username'),
            'password'     => $this->input->post('password'),
            'mobile'       => $this->input->post('mobile'),
            'country_code' => $this->input->post('country_code'),
            'gender'       => $this->input->post('gender'),
            'country'      => $this->input->post('country'),
            'address'      => $this->input->post('address'),

            // ✅ FIXED ROLE
            'role'          => 'Subadmin',
            'platform_type' => 'Web',
            'created_at'    => date('Y-m-d H:i:s')
        ];

        $insert = $this->Common_model->insert_user($data);

        if ($insert) {
            $this->session->set_flashdata('success', 'Sub Admin added successfully');
        } else {
            $this->session->set_flashdata('error', 'Failed to add Sub Admin');
        }

        redirect('welcome/sub_admin_list');
    }
    
public function report_post()
{
    // Reported posts
    $data['report_post'] = $this->Common_model->get_report_post();

    // ---- Report Text Mapping (API / Static) ----
    $allReportText = [
        ["id"=>"2","text"=>"Hate speech or symbols","type"=>"post"],
        ["id"=>"5","text"=>"Scam or fraud","type"=>"post"],
        ["id"=>"8","text"=>"Violence or dangerous organisations","type"=>"post"],
        ["id"=>"11","text"=>"Bullying or harassment","type"=>"post"],
        ["id"=>"14","text"=>"Suicide or self-injury","type"=>"post"],
        ["id"=>"17","text"=>"Nudity or sexual activity","type"=>"post"]
    ];

    // ID => TEXT map
    $reportTextMap = [];
    foreach ($allReportText as $row) {
        $reportTextMap[$row['id']] = $row['text'];
    }

    // Send to view
    $data['reportTextMap'] = $reportTextMap;

    $this->load->view('admin_view/report_post', $data);
}

public function restore_post($post_id)
{
    $this->db->trans_start();



    $this->db->where('post_id', $post_id);
    $this->db->delete('post_report');

    $this->db->trans_complete();

    redirect('welcome/report_post');
}
	public function terms_condition()
{    
    $this->load->view('admin_view/terms_condition'); 
}


public function privacy_policy()
{
    $this->load->view('admin_view/privacy_policy');
}

public function account_deletion()
{
    $this->load->view('admin_view/account_deletion');
}

public function data_deletion()
{
    $this->load->view('admin_view/account_deletion');
}


  	public function child_safety()
{    
    $this->load->view('admin_view/child_safety'); 
}

  	public function contact_us()
{    
    $this->load->view('admin_view/contact_us'); 
}


  	public function about_us()
{    
    $this->load->view('admin_view/about_us'); 
}

public function generation_logs()
{
    $data['logs'] = $this->Common_model->get_generation_logs(500);
    $this->load->view('admin_view/generation_logs', $data);
}

public function generation_log_detail($id = null)
{
    $id = (int) $id;
    if ($id <= 0) {
        redirect('welcome/generation_logs');
        return;
    }
    $log = $this->Common_model->get_generation_log($id);
    if (!$log) {
        $this->session->set_flashdata('error', 'Generation log not found');
        redirect('welcome/generation_logs');
        return;
    }
    $data['log'] = $log;
    $this->load->view('admin_view/generation_log_detail', $data);
}

}