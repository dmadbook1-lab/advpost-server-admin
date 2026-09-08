<?php
require APPPATH . 'libraries/RestController.php';

require_once VENDORPATH . 'autoload.php';

require_once APPPATH . 'third_party/JWT/JWT.php';
require_once APPPATH . 'third_party/JWT/ExpiredException.php';
require_once APPPATH . 'third_party/JWT/BeforeValidException.php';
require_once APPPATH . 'third_party/JWT/SignatureInvalidException.php';

use \Firebase\JWT\JWT;
    use Firebase\JWT\Key;


// Import OAuth2 class
use Google\Auth\OAuth2;use chriskacerguis\RestServer\RestController;

defined('BASEPATH') or exit('No direct script access allowed');

class PostController extends RestController

{
      private $msg91ApiKey = "YOUR_MSG91_API_KEY";
    private $otpTemplateId = "YOUR_TEMPLATE_ID"; 
    private $senderId = "SENDER_ID"; // (Optional) Configure according to MSG91
    
 public function __construct()
    {
        parent::__construct();
        $this->load->database(); // Ensure the database library is loaded
        $this->load->library('session');
        $this->load->model('Common_model');
$this->load->model('User_model');
$this->load->model('Reel_model');

    }
    
  public function web_login_post()
    {
        // 1️⃣ Authorization Header
        $authHeader = $this->input->get_request_header('Authorization', TRUE);

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $this->response([
                'status' => false,
                'message' => 'Token missing'
            ], 401);
        }

        $token = $matches[1];

        // 2️⃣ Validate token (DO NOT CHANGE THIS FUNCTION)
        $user_id = $this->User_model->validate_token_and_get_user($token);

        if (!$user_id) {
            return $this->response([
                'status' => false,
                'message' => 'Invalid token'
            ], 401);
        }

        // 3️⃣ Get user
        $user = $this->db->where('id', $user_id)->get('users')->row();
        if (!$user) {
            return $this->response([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        // 4️⃣ CREATE WEB SESSION (🔥 IMPORTANT)
        $this->session->set_userdata([
            'id'       => $user->id,
            'email'    => $user->email,
            'name'     => $user->first_name,
            'role'     => $user->role ?? 'user',
            'is_login' => true
        ]);

        // 5️⃣ Response
        return $this->response([
            'status'  => true,
            'message' => 'Web session created'
        ], 200);
    }
    
    
public function store_otp_post() {
    header("Content-Type: application/json");
    date_default_timezone_set('Asia/Kolkata');

    // Read JSON Input
    $inputJSON   = file_get_contents("php://input");
    $requestData = json_decode($inputJSON, true);

    // Validate mobile_number field
    if (empty($requestData['mobile_number'])) {
        echo json_encode([
            "response_code" => "0",
            "status"        => "failed",
            "message"       => "Mobile number is required"
        ]);
        return;
    }

    // Extract inputs
    $mobile        = trim($requestData['mobile_number']);
    $country_code  = $requestData['country_code'] ?? "+91";
    $country       = $requestData['country'] ?? "";
    $platform_type = $requestData['platform_type'] ?? "";

    // Validate Indian mobile number
    if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
        echo json_encode([
            "response_code" => "0",
            "status"        => "failed",
            "message"       => "Invalid mobile number"
        ]);
        return;
    }

    // ******************************************************
    // 🚫 BLOCK THIS NUMBER COMPLETELY (NO OTP, NO DB ENTRY)
    // ******************************************************
    if ($mobile === "9960664278") {
        echo json_encode([
            "response_code" => "1",
            "status"        => "success",
            "message"       => "OTP not allowed for this number"
        ]);
        return;
    }
    // ******************************************************


    // Check if user exists, insert if not
    $this->db->where('mobile', $mobile);
    $userQuery = $this->db->get('users');
    if ($userQuery->num_rows() == 0) {
        $userdata = [
            "mobile"        => $mobile,
            "country_code"  => $country_code,
            "created_at"    => date("Y-m-d H:i:s")
        ];
        $this->db->insert('users', $userdata);
    }

    // Generate OTP
    $otp     = rand(1000, 9999);
    $expiry  = date("Y-m-d H:i:s", strtotime("+10 minutes"));

    // Save/Update OTP (INSERT + UPDATE)
    $data = [
        'mobile_number' => $mobile,
        'otp'           => $otp,
        'expiry'        => $expiry,
        'type'          => 'register',
        'country_code'  => $country_code,
        'country'       => $country,
        'platform_type' => $platform_type
    ];

    $this->db->replace('otp_verification', $data);

    // Fast2SMS Payload
    $fields = [
        "sender_id"        => "ADvPST",
        "message"          => "208618",
        "variables_values" => $otp,
        "route"            => "dlt",
        "numbers"          => $mobile
    ];

    // CURL Request
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => "https://www.fast2sms.com/dev/bulkV2",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($fields),
        CURLOPT_HTTPHEADER     => [
            "authorization: qV1C3jK0PUmof2uEQh74rc8axJiHOSsZDXyBGRkbdNAtvnWewILrK8RJ24iok1juf6lEsqTZMtnUcO5G",
            "content-type: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $err      = curl_error($curl);
    curl_close($curl);

    // Final JSON Response
    if ($err) {
        echo json_encode([
            "response_code" => "0",
            "status"        => "failed",
            "message"       => "SMS sending failed"
        ]);
    } else {
        echo json_encode([
            "response_code" => "1",
            "status"        => "success",
            "message"       => "OTP sent successfully"
        ]);
    }
}

    
    

// public function verify_otp_post()
// {
//     header("Content-Type: application/json");

//     $inputJSON   = file_get_contents("php://input");
//     $requestData = json_decode($inputJSON, true);

//     // Required Input
//     if (!isset($requestData['mobile_number']) || !isset($requestData['otp'])) {
//         echo json_encode([
//             "response_code" => "0",
//             "status"        => "failed",
//             "message"       => "Mobile number and OTP are required"
//         ]);
//         return;
//     }

//     $mobile = trim($requestData['mobile_number']);
//     $otp    = trim($requestData['otp']);

//     // Validate 10-digit mobile number
//     if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
//         echo json_encode([
//             "response_code" => "0",
//             "status"        => "failed",
//             "message"       => "Invalid mobile number"
//         ]);
//         return;
//     }

//     // OTP Check
//     $this->db->where("mobile_number", $mobile);
//     $this->db->where("otp", $otp);
//     $this->db->where("expiry >=", date("Y-m-d H:i:s"));
//     $otpQuery = $this->db->get("otp_verification");

//     if ($otpQuery->num_rows() == 0) {
//         echo json_encode([
//             "response_code" => "0",
//             "status"        => "failed",
//             "message"       => "Invalid or expired OTP"
//         ]);
//         return;
//     }

//     // USER CHECK
//     $this->db->where("mobile", $mobile);
//     $userQuery = $this->db->get("users");

//     if ($userQuery->num_rows() > 0) {
//         // Existing user
//         $user = $userQuery->row();

//         $country_code  = $user->country_code ?? "";
//         $username      = $user->username ?? "";
//         $first_name    = $user->first_name ?? "";
//         $last_name     = $user->last_name ?? "";
//         $user_id       = $user->id;
//         $avtar_id      = $user->avtar_id ?? "";
//         $token         = $user->token ?? "";

       

//     } else {
//         // New User insert
//         $device_token = base64_encode(time() . rand(1000, 9999));
//         $userdata = [
//             "mobile"        => $mobile,
//             "country_code"  => "",
//             "username"      => "",
//             "first_name"    => "",
//             "last_name"     => "",
//             "user_id"       => "",
//             "avtar_id"      => "",
//             "token"  => "",
//             "created_at"    => date("Y-m-d H:i:s")
//         ];
//         $this->db->insert("users", $userdata);
//         $user_id = $this->db->insert_id();
//     }

//     // FINAL RESPONSE
//     echo json_encode([
//         "response_code" => "1",
//         "status"        => "success",
//         "message"       => "User Login Successfully",
//         "mobile"        => $mobile,
//         "country_code"  => $country_code ?? "",
//         "username"      => $username ?? "",
//         "first_name"    => $first_name ?? "",
//         "last_name"     => $last_name ?? "",
//         "user_id"       => $user_id,
//         "avtar_id"      => $avtar_id ?? "",
//         "token"         => $token ?? ""
//     ]);
// }





public function verify_otp_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set('Asia/Kolkata');

    $inputJSON   = file_get_contents("php://input");
    $requestData = json_decode($inputJSON, true);

    if (!isset($requestData['mobile_number']) || !isset($requestData['otp'])) {
        echo json_encode([
            "response_code" => "0",
            "status"        => "failed",
            "message"       => "Mobile number and OTP are required"
        ]);
        return;
    }

    $mobile = trim($requestData['mobile_number']);
    $otp    = trim($requestData['otp']);

    if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
        echo json_encode([
            "response_code" => "0",
            "status"        => "failed",
            "message"       => "Invalid mobile number"
        ]);
        return;
    }

    // ----------------------------------------------------------------------
    // Special Bypass: Only for 9923659085 → OTP must be 1234
    // ----------------------------------------------------------------------
    if ($mobile == "9960664278") {

        if ($otp != "1234") {
            echo json_encode([
                "response_code" => "0",
                "status"        => "failed",
                "message"       => "Invalid OTP"
            ]);
            return;
        }

        // OTP is valid → No DB check required
        $otp_verified = true;

    } else {

        // ------------------------------------------------------------------
        // Normal OTP verification from database for all others
        // ------------------------------------------------------------------
        $this->db->where("mobile_number", $mobile);
        $this->db->where("otp", $otp);
        $this->db->where("expiry >=", date("Y-m-d H:i:s"));
        $otpQuery = $this->db->get("otp_verification");

        if ($otpQuery->num_rows() == 0) {
            echo json_encode([
                "response_code" => "0",
                "status"        => "failed",
                "message"       => "Invalid or expired OTP"
            ]);
            return;
        }

        $otp_verified = true;
    }

    // ----------------------------------------------------------------------
    // Continue Login Logic (same as before)
    // ----------------------------------------------------------------------

    // Generate random token
    $token = bin2hex(random_bytes(30));
    $token_expiry = date("Y-m-d H:i:s", strtotime("+30 days"));

    // Check user exists
    $this->db->where("mobile", $mobile);
    $userQuery = $this->db->get("users");

    if ($userQuery->num_rows() > 0) {
        $user = $userQuery->row();
        $user_id = $user->id;

        // Block login for deactivated accounts (status != 1)
        if ((string)$user->status !== '1') {
            echo json_encode([
                "response_code"  => "1",
                "status"         => "success",
                "message"        => "Your Account has been deactivated",
                "mobile"         => $mobile,
                "user_id"        => (string)$user_id,
                "account_status" => 0,
                "token"          => ""
            ]);
            return;
        }

        $this->db->where("id", $user_id)->update("users", [
            "token"        => $token,
            "token_expiry" => $token_expiry,
            "updated_at"   => date("Y-m-d H:i:s")
        ]);
    } else {
        $this->db->insert("users", [
            "mobile"       => $mobile,
            "token"        => $token,
            "token_expiry" => $token_expiry,
            "status"       => 1,
            "created_at"   => date("Y-m-d H:i:s")
        ]);
        $user_id = $this->db->insert_id();
        $user = (object)["status" => 1];
    }

    // Delete OTP only for normal users (not required for special number)
    if ($mobile != "9923659085") {
        $this->db->where("mobile_number", $mobile)->delete("otp_verification");
    }

    // safe values
    $country_code = isset($user->country_code) ? $user->country_code : null;
    $first_name   = isset($user->first_name) ? $user->first_name : null;
    $last_name    = isset($user->last_name) ? $user->last_name : null;
    $avtar_id     = isset($user->avtar_id) ? $user->avtar_id : null;
    $account_status = (isset($user->status) && (string)$user->status === '1') ? 1 : 0;

    echo json_encode([
        "response_code"  => "1",
        "status"         => "success",
        "message"        => "User Login Successfully",
        "mobile"         => $mobile,
        "country_code"   => $country_code,
        "first_name"     => $first_name,
        "last_name"      => $last_name,
        "user_id"        => (string)$user_id,
        "avtar_id"       => $avtar_id,
        "account_status" => $account_status,
        "token"          => "Bearer " . $token
    ]);
}







   public function login_post() {
    header("Content-Type: application/json");

    $this->load->model('Reel_model');

    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    $email = isset($input['email']) ? $input['email'] : '';
    $password = isset($input['password']) ? $input['password'] : '';

    if (empty($email) || empty($password)) {
        echo json_encode([
            'success' => 'failure',
            'message' => 'Users Not Found'
        ]);
        return;
    }

    $user = $this->Reel_model->get_user_by_email($email);

    if (!$user || $password !== $user->password) {
        echo json_encode([
            'success' => 'failure',
            'message' => 'Users Not Found'
        ]);
        return;
    }

    // ✅ Save user_id in session for subsequent requests
    $this->session->set_userdata('user_id', $user->id);

    echo json_encode([
        'success' => 'success',
        'message' => 'User signed in',
        'data' => [
            'id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'profile_pic' => $user->profile_pic
        ]
    ]);
}

public function username_check_post()
{
    header("Content-Type: application/json");

    // Get JSON request
    $inputJSON = file_get_contents("php://input");
    $requestData = json_decode($inputJSON, true);

    // Validate input
    if (!isset($requestData['username']) || empty($requestData['username'])) {
        echo json_encode([
            "response_code" => "0",
            "message"       => "Username is required",
            "status"        => "failed"
        ]);
        return;
    }

    $username = trim($requestData['username']);

    // Check in database (usees_login table)
    $query = $this->db->get_where("users", ["username" => $username]);

    if ($query->num_rows() > 0) {
        // Username already exists
        echo json_encode([
            "response_code" => "0",
            "message"       => "Username already registered",
            "status"        => "failed"
        ]);
    } else {
        // Username available
        echo json_encode([
            "response_code" => "1",
            "message"       => "Username not already registered",
            "status"        => "success"
        ]);
    }
}


// public function get_avtar_image_post()
// {
//     header("Content-Type: application/json");

//     $this->load->model('Avtar_model');

//     $banners = $this->Avtar_model->get_all_avtar_images();
//     $canceledDones = [];

//     if (!empty($banners)) {
//         foreach ($banners as $item) {
//             $raw['id']     = (string) $item['id'];
//             $raw['gender'] = $item['gender'] ?? '';
//             $raw['image']  = !empty($item['image']) ? base_url('assets/images/avtar/' . $item['image']) : '';
//             $raw['status'] = $item['status'] ?? '';

//             $canceledDones[] = $raw;
//         }

//         $response = [
//             'response_code' => '1',
//             'message'       => 'All Avtar Image List Found',
//             'status'        => 'success',
//             'all_avtar'     => $canceledDones,
//         ];
//     } else {
//         $response = [
//             'response_code' => '0',
//             'message'       => 'All Avtar Image List Not Found',
//             'status'        => 'failure',
//             'all_avtar'     => [],
//         ];
//     }

//     echo json_encode($response);
// }

public function get_avtar_image_post()
{
    header("Content-Type: application/json");
    $base_url = base_url();

    // 🔹 Fetch avatar images from users_login
    $avatar_query = $this->db->get('avtar');
    $avatar_data  = $avatar_query->result_array();

    // Format avatar data with full image URL
    $all_avtar = [];
    foreach ($avatar_data as $row) {
        $all_avtar[] = [
            "id"     => $row['id'],
            "gender" => $row['gender'],
            "image"  => !empty($row['image']) ?  $row['image'] : "",
            "status" => "0"  // 🔥 ALWAYS 0
        ];
    }

    echo json_encode([
        "response_code" => "1",
        "status"        => "success",
        "message"       => "All Avtar Image List Found",
        "all_avtar"     => $all_avtar
    ]);
}
 public function get_state_post()
    {
    $this->output
     ->set_header("Cache-Control: public, max-age=3600")
     ->set_header("Pragma: public")
     ->set_header("Expires: ".gmdate("D, d M Y H:i:s", time() + 3600)." GMT");

        // Fetch data from the 'student_add' table
        $result = $this->db->get('states');
        
        if ($result->num_rows() > 0) {
            $student_data = $result->result();
            $this->response([
                'status' => true,
                'message' => 'States fetched successfully',
                'data' => $student_data
            ], RestController::HTTP_OK);
        } else {
            $this->response([
                'status' => false,
                'message' => 'No state found'
            ], RestController::HTTP_OK);
        }
    }

  //district ko get karne ke liye
public function get_district_post($state_id = null)
{
    // ===============================
    // 1️⃣ TIMEZONE
    // ===============================
    date_default_timezone_set('Asia/Kolkata');

    // ===============================
    // 2️⃣ CACHE HEADERS
    // ===============================
    $this->output
        ->set_header("Content-Type: application/json; charset=utf-8")
        ->set_header("Cache-Control: public, max-age=3600")
        ->set_header("Pragma: public")
        ->set_header("Expires: " . gmdate("D, d M Y H:i:s", time() + 3600) . " GMT");

    // ===============================
    // 3️⃣ DATABASE
    // ===============================
    $this->load->database();

    // ===============================
    // 4️⃣ QUERY
    // ===============================
    if (!empty($state_id)) {
        $this->db->where('state_id', $state_id);
    }

    $this->db->order_by('district_name', 'ASC'); // optional but good
    $query = $this->db->get('distric');

    // ===============================
    // 5️⃣ RESPONSE
    // ===============================
    if ($query->num_rows() > 0) {

        return $this->response([
            'status'  => true,
            'message' => 'Districts fetched successfully',
            'data'    => $query->result()
        ], RestController::HTTP_OK);

    } else {

        return $this->response([
            'status'  => false,
            'message' => 'No district found',
            'data'    => []
        ], RestController::HTTP_NOT_FOUND);
    }
}

//taluka ko get karne ke liye
public function get_taluka_post($state_id = null, $district_id = null)
{
$this->output
     ->set_header("Cache-Control: public, max-age=3600")
     ->set_header("Pragma: public")
     ->set_header("Expires: ".gmdate("D, d M Y H:i:s", time() + 3600)." GMT");

    // Load database if not already loaded
    $this->load->database();

    // Apply filters if parameters are provided
    if ($state_id !== null) {
        $this->db->where('state_id', $state_id);
    }
    if ($district_id !== null) {
        $this->db->where('district_id', $district_id);
    }

    // Fetch taluka records
    $result = $this->db->get('taluka');

    if ($result->num_rows() > 0) {
        $talukas = $result->result();

        $this->response([
            'status' => true,
            'message' => 'Talukas fetched successfully',
            'data' => $talukas
        ], RestController::HTTP_OK);
    } else {
        $this->response([
            'status' => false,
            'message' => 'No Taluka found'
        ], RestController::HTTP_NOT_FOUND);
    }
}


public function user_profile_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set('Asia/Kolkata');

    /* ===============================
       1️⃣ AUTH TOKEN
    =============================== */
    $authHeader = $this->input->get_request_header('Authorization', TRUE);

    if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Unauthorized: Token missing"
        ]);
        return;
    }

    $token = trim(substr($authHeader, 7));

    /* ===============================
       2️⃣ TOKEN VALIDATION
    =============================== */
    $user = $this->db
        ->where("token", $token)
        ->where("token_expiry >=", date("Y-m-d H:i:s"))
        ->get("users")
        ->row();

    if (!$user) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Unauthorized: Invalid or expired token"
        ]);
        return;
    }

    /* ===============================
       3️⃣ READ DATA
    =============================== */
    $requestData = [];

    $rawInput = file_get_contents("php://input");
    $jsonData = json_decode($rawInput, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
        $requestData = $jsonData;
    }

    if (isset($_POST['data'])) {
        $formJson = json_decode($_POST['data'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $requestData = array_merge($requestData, $formJson);
        }
    }

    if (!empty($_POST)) {
        $requestData = array_merge($requestData, $_POST);
    }

    /* ===============================
       4️⃣ ALLOWED UPDATE FIELDS
    =============================== */
    $allowed_fields = [
        'first_name','business_name','email','username','mobile','gender','bio',
        'avtar_id','country_code','device_token','role','address',
        'dob','platform_type','country',
        'state', 'district', 'taluka' // ✅ Added new fields here
    ];

    $updateData = [];

    foreach ($allowed_fields as $field) {
        if (isset($requestData[$field])) {
            $updateData[$field] = $requestData[$field];
        }
    }

    /* ===============================
       ✅ CHANGE #1: DEFAULT ROLE
    =============================== */
    if (empty($updateData['role'])) {
        $updateData['role'] = 'user';
    }

    /* ===============================
       ✅ CHANGE #2: NOTIFICATION TOPIC
    =============================== */
    $updateData['notification_topic'] = 'all_user';

    /* ==================================================
       🔥 avatar_id vs profile_pic
    ================================================== */
    if (!empty($updateData['avtar_id'])) {
        $updateData['profile_pic'] = null;
    }

    /* ===============================
       5️⃣ PROFILE PIC UPLOAD → S3
    =============================== */
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            echo json_encode([
                "response_code" => "0",
                "status" => "failed",
                "message" => "Invalid profile image type"
            ]);
            return;
        }

        $key = adv_user_media_key(
            $user->id,
            'profile',
            null,
            null,
            'profile_' . uniqid('', true) . '.' . $ext
        );

        $s3Url = adv_s3_upload_file_field($_FILES['profile_pic'], $key);
        if ($s3Url === false) {
            echo json_encode([
                "response_code" => "0",
                "status" => "failed",
                "message" => "Profile image upload to S3 failed: " . adv_s3_last_error()
            ]);
            return;
        }

        $updateData['profile_pic'] = $s3Url;
        $updateData['avtar_id'] = null;
    }

    /* ===============================
       5️⃣b LOGO UPLOAD → S3 (optional)
    =============================== */
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            echo json_encode([
                "response_code" => "0",
                "status" => "failed",
                "message" => "Invalid logo image type"
            ]);
            return;
        }

        $key = adv_user_media_key(
            $user->id,
            'brand',
            null,
            null,
            'logo_' . uniqid('', true) . '.' . $ext
        );

        $s3Url = adv_s3_upload_file_field($_FILES['logo'], $key);
        if ($s3Url === false) {
            echo json_encode([
                "response_code" => "0",
                "status" => "failed",
                "message" => "Logo upload to S3 failed: " . adv_s3_last_error()
            ]);
            return;
        }

        $updateData['logo_url'] = $s3Url;
    }

    /* ===============================
       6️⃣ UPDATE USER
    =============================== */
    if (!empty($updateData)) {
        $updateData['updated_at'] = date("Y-m-d H:i:s");
        $this->db->where("id", $user->id)->update("users", $updateData);
    }

    /* ===============================
       7️⃣ FETCH UPDATED USER
    =============================== */
    $updated_user = $this->db
        ->where("id", $user->id)
        ->get("users")
        ->row();

    /* ===============================
       8️⃣ FOLLOW COUNTS
    =============================== */
    $followers_count = $this->db
        ->where("to_user", $user->id)
        ->count_all_results("follow");

    $this->db->reset_query();

    $following_count = $this->db
        ->where("from_user", $user->id)
        ->count_all_results("follow");

    /* ===============================
       9️⃣ PROFILE PIC PRIORITY
    =============================== */
    $final_profile_pic = adv_profile_pic_url('');

    if (!empty($updated_user->profile_pic)) {
        $final_profile_pic = adv_profile_pic_url($updated_user->profile_pic);
    } elseif (!empty($updated_user->avtar_id)) {
        $avatar = $this->db->where("id", $updated_user->avtar_id)->get("avtar")->row();
        if ($avatar && !empty($avatar->image)) {
            $final_profile_pic = adv_media_url($avatar->image);
        }
    }

    /* ===============================
       🔟 RESPONSE (UNCHANGED + added new fields)
    =============================== */
    $user_data = [
        "id" => (string)$updated_user->id,
        "first_name" => $updated_user->first_name,
        "business_name" => $updated_user->business_name,
        "email" => $updated_user->email,
        "login_type" => $updated_user->login_type ?? "Mobile Number",
        "username" => $updated_user->username,
        "mobile" => $updated_user->mobile,
        "country_code" => $updated_user->country_code ?? "+91",
        "device_token" => $updated_user->device_token ?? "",
        "role" => $updated_user->role ?? "user",
        "profile_pic" => $final_profile_pic,
        "address" => $updated_user->address ?? "",
        "bio" => $updated_user->bio ?? "",
        "dob" => $updated_user->dob ?? "1989-01-01",
        "gender" => $updated_user->gender ?? "",
        "avtar_id" => $updated_user->avtar_id ?? "",
        "followers" => (string)$followers_count,
        "following" => (string)$following_count,
        "is_followers" => $updated_user->is_followers ?? "0",
        "is_user_following_me" => $updated_user->is_user_following_me ?? "0",
        "is_block" => $updated_user->is_block ?? "0",
        "total_posts" => $updated_user->total_posts ?? "0",
        "total_reels" => $updated_user->total_reels ?? "0",
        "total_tags" => $updated_user->total_tags ?? "0",
        "platform_type" => $updated_user->platform_type ?? "Mobile",
        "country" => $updated_user->country ?? "India",
        "state" => $updated_user->state ?? "",      // ✅ new field
        "district" => $updated_user->district ?? "",// ✅ new field
        "taluka" => $updated_user->taluka ?? "",
        "notification_topic" => $updated_user->notification_topic ?? "",// ✅ new field
        "access_token" => "Bearer " . $token
    ];

    echo json_encode([
        "response_code" => "1",
        "message" => "User updated successfully",
        "status" => "success",
        "user_data" => $user_data
    ]);
}




public function add_post_post()
{
    header('Content-Type: application/json');
    date_default_timezone_set('Asia/Kolkata');
    $this->db->query("SET NAMES utf8mb4");

    // 🔹 Get Bearer token from Authorization header
    // Prefer CI / $_SERVER so this works on Apache AND PHP's built-in server
    // (apache_request_headers() often omits Authorization under `php -S`).
    $authHeader = $this->input->get_request_header('Authorization');
    if (empty($authHeader)) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';
    }
    if (empty($authHeader) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, 'Authorization') === 0) {
                $authHeader = $value;
                break;
            }
        }
    }

    $token = null;
    if (!empty($authHeader) && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        $token = $matches[1];
    }

    if (empty($token)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Authorization token is missing",
            "status"  => "failed"
        ]);
        return;
    }

    // 🔹 Validate token
    $this->db->where('token', $token);
    $this->db->where('token_expiry >=', date('Y-m-d H:i:s'));
    $userQuery = $this->db->get('users');

    if ($userQuery->num_rows() == 0) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Invalid or expired token",
            "status"  => "failed"
        ]);
        return;
    }

    $user    = $userQuery->row();
    $user_id = $user->id;

    // 🔹 Helper function (ONLY for thumbnail)
    function create_webp_thumbnail($source, $destination, $maxKB = 100)
    {
        $info = getimagesize($source);
        if (!$info) return false;

        switch ($info['mime']) {
            case 'image/jpeg':
                $img = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $img = imagecreatefrompng($source);
                imagepalettetotruecolor($img);
                imagealphablending($img, true);
                imagesavealpha($img, true);
                break;
            case 'image/webp':
                $img = imagecreatefromwebp($source);
                break;
            default:
                return false;
        }

        for ($quality = 80; $quality >= 10; $quality -= 5) {
            imagewebp($img, $destination, $quality);
            if (filesize($destination) <= ($maxKB * 1024)) {
                break;
            }
        }

        imagedestroy($img);
        return true;
    }

    try {
        // 🔹 Input fields (app sends post_type=photo|reel)
        $text      = $this->input->post('text');
        $location  = $this->input->post('location');
        $post_type = adv_normalize_post_type($this->input->post('post_type'));
        $tag_users = $this->input->post('tag_users');

        // Auto-detect reel when only video is uploaded with reel type from app
        if ($post_type === 'image' && !empty($_FILES['post_video']['name']) && empty($_FILES['post_image']['name'])) {
            $rawType = strtolower(trim((string) $this->input->post('post_type')));
            if ($rawType === 'reel' || $rawType === 'video') {
                $post_type = 'reel';
            }
        }

        // 🔹 Insert main post
        $post_data = [
            'user_id'    => $user_id,
            'text'       => $text,
            'location'   => $location,
            'post_type'  => $post_type,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $this->db->insert('posts', $post_data);
        $post_id = $this->db->insert_id();

        // S3 root folder: users/{id}/posts|reels/{post_id}/...
        $media_category = ($post_type === 'reel') ? 'reels' : 'posts';

        // 🔹 Helper to ensure array
        function ensure_array($file_field) {
            if (!empty($file_field['name']) && !is_array($file_field['name'])) {
                return [
                    'name'     => [$file_field['name']],
                    'type'     => [$file_field['type']],
                    'tmp_name' => [$file_field['tmp_name']],
                    'error'    => [$file_field['error']],
                    'size'     => [$file_field['size']]
                ];
            }
            return $file_field;
        }

        // 🔹 Image Upload → S3  users/{uid}/posts/{post_id}/images/
        if (!empty($_FILES['post_image']['name'])) {
            $images = ensure_array($_FILES['post_image']);
            foreach ($images['name'] as $key => $image) {
                if ((int) $images['error'][$key] !== 0 || empty($images['tmp_name'][$key])) {
                    continue;
                }

                $ext = strtolower(pathinfo($images['name'][$key], PATHINFO_EXTENSION));
                if ($ext === '') {
                    $ext = 'jpg';
                }
                $fileName = uniqid('img_', true) . '.' . $ext;
                $s3Key = adv_user_media_key($user_id, 'posts', $post_id, 'images', $fileName);

                $fileArr = [
                    'name'     => $images['name'][$key],
                    'type'     => $images['type'][$key],
                    'tmp_name' => $images['tmp_name'][$key],
                    'error'    => $images['error'][$key],
                    'size'     => $images['size'][$key],
                ];

                $s3Url = adv_s3_upload_file_field($fileArr, $s3Key, $images['type'][$key] ?: null);
                if ($s3Url === false) {
                    throw new Exception('Post image S3 upload failed: ' . adv_s3_last_error());
                }

                $this->db->insert('post_image', [
                    'post_id'    => $post_id,
                    'user_id'    => $user_id,
                    'new_post'   => $s3Url,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }

        // 🔹 Video Upload → S3
        // reels → users/{uid}/reels/{post_id}/videos|thumbnails/
        // posts → users/{uid}/posts/{post_id}/videos|thumbnails/
        $videoUploaded = false;
        if (!empty($_FILES['post_video']['name'])) {
            $videos     = ensure_array($_FILES['post_video']);
            $thumbnails = ensure_array($_FILES['post_video_thumbnail'] ?? []);

            foreach ($videos['name'] as $index => $videoFile) {
                $uploadError = (int) $videos['error'][$index];
                if ($uploadError !== 0 || empty($videos['tmp_name'][$index])) {
                    // Surface PHP upload limit / size failures instead of silently
                    // creating an empty reel post.
                    if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                        throw new Exception(
                            'Video is too large for this server upload limit. Increase upload_max_filesize/post_max_size.'
                        );
                    }
                    if ($uploadError !== UPLOAD_ERR_NO_FILE) {
                        throw new Exception('Video upload failed (PHP error code ' . $uploadError . ')');
                    }
                    continue;
                }

                $video_name = time() . '_' . $index . '_' . adv_s3_safe_name($videoFile);
                $s3VideoKey = adv_user_media_key($user_id, $media_category, $post_id, 'videos', $video_name);

                $videoArr = [
                    'name'     => $videos['name'][$index],
                    'type'     => $videos['type'][$index],
                    'tmp_name' => $videos['tmp_name'][$index],
                    'error'    => $videos['error'][$index],
                    'size'     => $videos['size'][$index],
                ];
                $videoUrl = adv_s3_upload_file_field($videoArr, $s3VideoKey, $videos['type'][$index] ?: 'video/mp4');
                if ($videoUrl === false) {
                    throw new Exception('Post video S3 upload failed: ' . adv_s3_last_error());
                }

                $thumbUrl = '';
                if (!empty($thumbnails['tmp_name'][$index]) && (int) $thumbnails['error'][$index] === 0) {
                    $tmpThumb = tempnam(sys_get_temp_dir(), 'thumb_') . '.webp';
                    create_webp_thumbnail($thumbnails['tmp_name'][$index], $tmpThumb, 100);
                    $webp_name = time() . '_thumb_' . $index . '.webp';
                    $s3ThumbKey = adv_user_media_key($user_id, $media_category, $post_id, 'thumbnails', $webp_name);
                    $thumbUrl = adv_s3_upload_path($tmpThumb, $s3ThumbKey, 'image/webp') ?: '';
                    @unlink($tmpThumb);
                }

                $this->db->insert('post_video', [
                    'post_id'              => $post_id,
                    'user_id'              => $user_id,
                    'post_video'           => $videoUrl,
                    'post_video_thumbnail' => $thumbUrl,
                    'created_at'           => date('Y-m-d H:i:s')
                ]);
                $videoUploaded = true;
            }
        }

        if ($post_type === 'reel' && !$videoUploaded) {
            // Roll back empty reel rows so the client can retry.
            $this->db->where('id', $post_id)->delete('posts');
            throw new Exception('Reel video file is missing or failed to upload. Please try again.');
        }

        // 🔹 Hashtag Save
        if (!empty($text)) {
            preg_match_all('/#(\w+)/', $text, $matches);
            if (!empty($matches[1])) {
                foreach (array_unique($matches[1]) as $tag) {
                    $this->db->insert('hash_tag', [
                        'post_id'    => $post_id,
                        'user_id'    => $user_id,
                        'text'       => $tag,
                        'post_type'  => $post_type,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
        }

        // 🔹 Tag Users (UNCHANGED)
        if (!empty($tag_users)) {
            foreach (explode(',', $tag_users) as $tagUser) {
                $this->db->insert('post_user_tags', [
                    'post_id'    => $post_id,
                    'user_id'    => $user_id,
                    'tag_users'  => trim($tagUser),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }

        echo json_encode([
            "response_code" => "1",
            "message"       => "Post is Added Successfully",
            "status"        => "success",
            "post_id"       => $post_id
        ]);

    } catch (Exception $e) {
        echo json_encode([
            "response_code" => "0",
            "message"       => $e->getMessage(),
            "status"        => "failed"
        ]);
    }
}



// public function add_post_post()
// {
//     header('Content-Type: application/json');
//     date_default_timezone_set('Asia/Kolkata');

//     // 📌 Get JSON input
//     $inputJSON = file_get_contents('php://input');
//     $request = json_decode($inputJSON, true);

//     // Fallback to form-data
//     $user_id   = $request['user_id']   ?? $this->input->post('user_id');
//     $text      = $request['text']      ?? $this->input->post('text');
//     $location  = $request['location']  ?? $this->input->post('location');
//     $post_type = $request['post_type'] ?? $this->input->post('post_type');
//     $tag_users = $request['tag_users'] ?? $this->input->post('tag_users');

//     if (empty($user_id)) {
//         echo json_encode(["response_code" => "0", "message" => "User authentication failed", "status" => "failed"]);
//         return;
//     }

//     try {
//         // 🟢 Insert main post
//         $data = [
//             'user_id'    => $user_id,
//             'text'       => $text,
//             'location'   => $location,
//             'post_type'  => !empty($post_type) ? $post_type : 'general',
//             'created_at' => date('Y-m-d H:i:s')
//         ];
//         $this->db->insert('posts', $data);
//         $post_id = $this->db->insert_id();

//         // ===================== 📸 IMAGE UPLOAD (Base64) =====================
//         if (!empty($request['images_base64'])) {
//             foreach ($request['images_base64'] as $imgData) {
//                 $imgName = uniqid() . ".jpg";
//                 $imgPath = "assetsNew/images/new_post/" . $imgName;
//                 file_put_contents($imgPath, base64_decode($imgData));

//                 $this->db->insert('post_image', [
//                     'post_id'    => $post_id,
//                     'user_id'    => $user_id,
//                     'new_post'   => $imgPath,
//                     'created_at' => date('Y-m-d H:i:s')
//                 ]);
//             }
//         }

//         // ===================== 📸 IMAGE UPLOAD (Multipart/form-data) =====================
//         if (!empty($_FILES['new_post']['name'])) {

//             // Check if single or multiple
//             if (is_array($_FILES['new_post']['name'])) {
//                 // Multiple files
//                 $files = $_FILES['new_post'];
//                 for ($i = 0; $i < count($files['name']); $i++) {
//                     if (!empty($files['name'][$i])) {
//                         $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
//                         $fileName = uniqid() . '.' . $ext;
//                         $uploadPath = "assetsNew/images/new_post/" . $fileName;

//                         move_uploaded_file($files['tmp_name'][$i], $uploadPath);

//                         $this->db->insert('post_image', [
//                             'post_id'    => $post_id,
//                             'user_id'    => $user_id,
//                             'new_post'   => $uploadPath,
//                             'created_at' => date('Y-m-d H:i:s')
//                         ]);
//                     }
//                 }
//             } else {
//                 // Single file
//                 $ext = pathinfo($_FILES['new_post']['name'], PATHINFO_EXTENSION);
//                 $fileName = uniqid() . '.' . $ext;
//                 $uploadPath = "assetsNew/images/new_post/" . $fileName;

//                 move_uploaded_file($_FILES['new_post']['tmp_name'], $uploadPath);

//                 $this->db->insert('post_image', [
//                     'post_id'    => $post_id,
//                     'user_id'    => $user_id,
//                     'new_post'   => $uploadPath,
//                     'created_at' => date('Y-m-d H:i:s')
//                 ]);
//             }
//         }

//         // ==================== 🎥 VIDEO UPLOAD (Base64 with Thumbnail) ====================
//         if (!empty($request['videos_base64'])) {
//             foreach ($request['videos_base64'] as $index => $videoData) {
//                 $video_name = uniqid() . ".mp4";
//                 $video_path = "assetsNew/videos/post_video/" . $video_name;
//                 file_put_contents($video_path, base64_decode($videoData));

//                 $thumb_name = uniqid() . ".jpg";
//                 $thumb_path = "assetsNew/videos/post_video_thumbnails/" . $thumb_name;
//                 file_put_contents($thumb_path, base64_decode($request['video_thumbnails_base64'][$index]));

//                 $this->db->insert('post_video', [
//                     'post_id'              => $post_id,
//                     'user_id'              => $user_id,
//                     'post_video'           => $video_path,
//                     'post_video_thumbnail' => $thumb_path,
//                     'created_at'           => date('Y-m-d H:i:s')
//                 ]);
//             }
//         }

//         // ==================== 🎥 VIDEO UPLOAD (Multipart/form-data) ====================
//         if (!empty($_FILES['post_video']['name'])) {
//             if (is_array($_FILES['post_video']['name'])) {

//                 // Multiple video files
//                 $files = $_FILES['post_video'];
//                 for ($i = 0; $i < count($files['name']); $i++) {
//                     if (!empty($files['name'][$i])) {
//                         $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
//                         $videoName = uniqid() . '.' . $ext;
//                         $videoPath = "assetsNew/videos/post_video/" . $videoName;

//                         move_uploaded_file($files['tmp_name'][$i], $videoPath);
//                         $thumbPath = "assetsNew/videos/post_video_thumbnails/default.jpg";

//                         $this->db->insert('post_video', [
//                             'post_id'              => $post_id,
//                             'user_id'              => $user_id,
//                             'post_video'           => $videoPath,
//                             'post_video_thumbnail' => $thumbPath,
//                             'created_at'           => date('Y-m-d H:i:s')
//                         ]);
//                     }
//                 }

//             } else {

//                 // Single video file
//                 $ext = pathinfo($_FILES['post_video']['name'], PATHINFO_EXTENSION);
//                 $videoName = uniqid() . "." . $ext;
//                 $videoPath = "assetsNew/videos/post_video/" . $videoName;

//                 move_uploaded_file($_FILES['post_video']['tmp_name'], $videoPath);
//                 $thumbPath = "assetsNew/videos/post_video_thumbnails/default.jpg";

//                 $this->db->insert('post_video', [
//                     'post_id'              => $post_id,
//                     'user_id'              => $user_id,
//                     'post_video'           => $videoPath,
//                     'post_video_thumbnail' => $thumbPath,
//                     'created_at'           => date('Y-m-d H:i:s')
//                 ]);
//             }
//         }

//         // ➿ Hashtag Save
//         if (!empty($text)) {
//             preg_match_all('/#(\w+)/', $text, $matches);
//             foreach (array_unique($matches[1]) as $tag) {
//                 $this->db->insert('hash_tag', [
//                     'post_id'   => $post_id,
//                     'user_id'   => $user_id,
//                     'text'      => $tag,
//                     'post_type' => $post_type,
//                     'created_at'=> date('Y-m-d H:i:s')
//                 ]);
//             }
//         }

//         // 👥 Tag Users
//         if (!empty($tag_users)) {
//             $userList = is_array($tag_users) ? $tag_users : explode(',', $tag_users);
//             foreach ($userList as $tagUser) {
//                 $this->db->insert('post_user_tags', [
//                     'post_id'   => $post_id,
//                     'user_id'   => $user_id,
//                     'tag_users' => trim($tagUser),
//                     'created_at'=> date('Y-m-d H:i:s')
//                 ]);
//             }
//         }

//         echo json_encode([
//             "response_code" => "1",
//             "message" => "Post added successfully",
//             "status"  => "success",
//             "post_id" => $post_id
//         ]);

//     } catch (Exception $e) {
//         echo json_encode([
//             "response_code" => "0",
//             "message" => $e->getMessage(),
//             "status"  => "failed"
//         ]);
//     }
// }

public function get_all_latest_reel_and_post_pagination_post()
{
    header('Content-Type: application/json');
    date_default_timezone_set('Asia/Kolkata');

    /* ===============================
       READ INPUT
    ================================*/
    $input = json_decode($this->input->raw_input_stream, true);

    $per_page = isset($input['per_page']) && $input['per_page'] > 0 ? (int)$input['per_page'] : 20;
    $page_no  = isset($input['page_no']) && $input['page_no'] > 0 ? (int)$input['page_no'] : 1;
    $offset   = ($page_no - 1) * $per_page;

    /* ===============================
       LOGIN USER FROM TOKEN
    ================================*/
    $authHeader    = $this->input->get_request_header('Authorization', TRUE);
    $login_user_id = 0;

    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];

        $user = $this->db
            ->where('token', $token)
            ->where('token_expiry >=', date('Y-m-d H:i:s'))
            ->get('users')
            ->row();

        if ($user) {
            $login_user_id = (int)$user->id;
        }
    }

    /* ===============================
       REPORTED POSTS
    ================================*/
    $reported_post_ids = [];

    $reported = $this->db
        ->select('DISTINCT(post_id)')
        ->from('post_report')
        ->where('post_id IS NOT NULL', null, false)
        ->get()
        ->result_array();

    if (!empty($reported)) {
        $reported_post_ids = array_map('intval', array_column($reported, 'post_id'));
    }

    /* ===============================
       ACTIVE BOOST POSTS
    ================================*/
    $today = date('Y-m-d');

    $boosted_post_ids = $this->db
        ->select('post_id')
        ->from('post_boost')
        ->where('status', 'paid')
        ->where('start_date <=', $today)
        ->where('end_date >=', $today)
        ->get()
        ->result_array();

    $boosted_post_ids = array_map('intval', array_column($boosted_post_ids, 'post_id'));
    $total_boosts    = count($boosted_post_ids);

    /* ===============================
       FETCH NORMAL POSTS ONLY
       (BOOST POSTS EXCLUDED HERE)
       This is the general Home feed — it must return every post_type
       (images/photos/reels mixed), not reels only.
    ================================*/
    $this->db->select('posts.*, users.username, users.first_name, users.profile_pic, users.mobile');
    $this->db->from('posts');
    $this->db->join('users', 'users.id = posts.user_id', 'left');
    $this->db->where('posts.status', 1);
    $this->db->where('posts.is_delete', 0);

    if (!empty($reported_post_ids)) {
        $this->db->where_not_in('posts.post_id', $reported_post_ids);
    }

    if (!empty($boosted_post_ids)) {
        $this->db->where_not_in('posts.post_id', $boosted_post_ids); // 🔥 VERY IMPORTANT
    }

    $this->db->order_by('posts.created_at', 'DESC');
    $this->db->limit($per_page, $offset);
    $normal_posts = $this->db->get()->result();

    /* ===============================
       BUILD FINAL FEED
    ================================*/
    $final_post     = [];
    $normal_counter = 0;
    $boost_index    = 0;

    foreach ($normal_posts as $post) {

        // Add normal post
        $final_post[] = $this->_format_post($post, $login_user_id, "0");
        $normal_counter++;

        // 🔥 After every 4 normal posts → add 1 boost
        if ($normal_counter % 4 == 0 && $total_boosts > 0) {

            $boost_post_id = $boosted_post_ids[$boost_index % $total_boosts];
            $boost_index++;

            // Skip reported boost
            if (in_array($boost_post_id, $reported_post_ids)) {
                continue;
            }

            $boost_post = $this->db
                ->select('posts.*, users.username, users.first_name, users.profile_pic, users.mobile')
                ->from('posts')
                ->join('users', 'users.id = posts.user_id', 'left')
                ->where('posts.post_id', $boost_post_id)
                ->where('posts.status', 1)
                ->where('posts.is_delete', 0)
                ->get()
                ->row();

            if ($boost_post) {
                $final_post[] = $this->_format_post($boost_post, $login_user_id, "1");
            }
        }
    }

    echo json_encode([
        "response_code"  => !empty($final_post) ? "1" : "0",
        "message"        => !empty($final_post) ? "Content Found" : "No Posts Available",
        "page_no"        => $page_no,
        "per_page"       => $per_page,
        "recent_content" => $final_post
    ], JSON_UNESCAPED_SLASHES);
}

private function _format_post($post, $login_user_id, $isBoostPost = "0")
{
    $post_id = $post->post_id;
    $media   = [];

    $images = $this->db->where('post_id', $post_id)->get('post_image')->result_array();
    foreach ($images as $img) {
        $media[] = [
            "url" => adv_media_url($img['new_post']),
            "type" => pathinfo(parse_url($img['new_post'], PHP_URL_PATH) ?: $img['new_post'], PATHINFO_EXTENSION),
            "post_video_thumbnail" => ""
        ];
    }

    $videos = $this->db->where('post_id', $post_id)->get('post_video')->result_array();
    foreach ($videos as $video) {
        $media[] = [
            "url" => adv_media_url($video['post_video']),
            "type" => "video",
            "post_video_thumbnail" => adv_media_url($video['post_video_thumbnail'])
        ];
    }

    $total_likes =
        $this->db->where('post_id', $post_id)->count_all_results('post_like') +
        $this->db->where('reel_id', $post_id)->count_all_results('reel_like');

    $total_comments =
        $this->db->where('post_id', $post_id)->count_all_results('post_comment') +
        $this->db->where('reel_id', $post_id)->count_all_results('reel_comment');

    $is_liked = "0";
    if ($login_user_id) {
        if (
            $this->db->where('post_id', $post_id)->where('user_id', $login_user_id)->count_all_results('post_like') ||
            $this->db->where('reel_id', $post_id)->where('user_id', $login_user_id)->count_all_results('reel_like')
        ) {
            $is_liked = "1";
        }
    }

    $is_bookmark = "0";
    if ($login_user_id) {
        $is_bookmark = $this->db
            ->where('post_id', $post_id)
            ->where('user_id', $login_user_id)
            ->count_all_results('bookmark_post') > 0 ? "1" : "0";
    }

    return [
        "post_id"       => (int)$post->post_id,
        "user_id"       => (int)$post->user_id,
        "username"      => $post->username ?? "",
        "first_name"    => $post->first_name ?? "",
        "mobile"        => $post->mobile ?? "",
        "profile_pic"   => adv_profile_pic_url($post->profile_pic ?? ""),
        "text"          => $post->text,
        "post_type"     => $post->post_type,
        "created_at"    => date("Y-m-d\TH:i:s.000000\Z", strtotime($post->created_at)),
        "post_image"    => $media,
        "is_liked"      => $is_liked,
        "total_like"    => (int)$total_likes,
        "total_comment" => (int)$total_comments,
        "is_bookmark"   => $is_bookmark,
        "isBoostPost"   => $isBoostPost
    ];
}


public function all_my_post_pagination_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set('Asia/Kolkata');

    // 🔹 Read Bearer Token
    $authHeader = $this->input->get_request_header('Authorization');
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "status" => "1",
            "message" => "No posts found",
            "post" => [],
            "current_page" => 1,
            "last_page" => 1
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return;
    }
    $token = $matches[1];

    // 🔹 Validate Token
    $user_id = $this->User_model->validate_token_and_get_user($token);
    if (!$user_id) {
        echo json_encode([
            "status" => "1",
            "message" => "No posts found",
            "post" => [],
            "current_page" => 1,
            "last_page" => 1
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return;
    }

    // 🔹 Inputs
    $request = json_decode(file_get_contents('php://input'), true);
    $per_page = isset($request['per_page']) ? (int)$request['per_page'] : 10;
    $page_no  = isset($request['page_no']) ? (int)$request['page_no'] : 1;
    if ($page_no < 1) $page_no = 1;
    $offset   = ($page_no - 1) * $per_page;

    // 🔹 Count Total Posts
    $this->db->where_in('post_type', ['image', 'photo', 'reel']);
    $this->db->where('user_id', $user_id);
    $this->db->where('status', 1);
    $this->db->where('is_delete', '0');
    $totalPosts = $this->db->count_all_results('posts');
    $last_page = $totalPosts > 0 ? ceil($totalPosts / $per_page) : 1;

    if ($totalPosts == 0) {
        echo json_encode([
            "status" => "1",
            "message" => "No posts found",
            "post" => [],
            "current_page" => $page_no,
            "last_page" => 1
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return;
    }

    // 🔹 Fetch Posts
    $posts = $this->db
        ->select('post_id, user_id, text, location, post_type, status, is_delete, created_at, updated_at')
        ->from('posts')
        ->where_in('post_type', ['image', 'photo', 'reel'])
        ->where('user_id', $user_id)
        ->where('status', 1)
        ->where('is_delete', '0')
        ->order_by('post_id', 'DESC')
        ->limit($per_page, $offset)
        ->get()
        ->result_array();

    $final_posts = [];

    // 🔹 Process each post
    foreach ($posts as $post) {
        $post_id = $post['post_id'];
        $post_user_id = $post['user_id'];

        // ================= IMAGE =================
        $image_data = $this->db->select('id, new_post')
            ->where('post_id', $post_id)
            ->get('post_image')
            ->result_array();

        $images = [];
        foreach ($image_data as $img) {
            $images[] = [
                "post_image_id" => (int)$img['id'],
                "url" => adv_media_url($img['new_post']),
                "type" => pathinfo($img['new_post'], PATHINFO_EXTENSION),
                "post_video_thumbnail" => ""
            ];
        }

        // ================= VIDEO =================
        $video_data = $this->db->select('id, post_video, post_video_thumbnail')
            ->where('post_id', $post_id)
            ->get('post_video')
            ->result_array();

        foreach ($video_data as $video) {
            $images[] = [
                "post_image_id" => (int)$video['id'],
                "url" => adv_media_url($video['post_video']),
                "type" => "video",
                "post_video_thumbnail" => adv_media_url($video['post_video_thumbnail'])
            ];
        }

        // ================= USER INFO =================
        $user_info = $this->db->select("username, profile_pic, first_name, last_name")
            ->from("users")
            ->where("id", $post_user_id)
            ->get()->row_array();

        // ================= COUNTERS =================
        $total_likes = (int)$this->db->where("post_id", $post_id)->count_all_results("post_like");
        $total_comments = (int)$this->db->where("post_id", $post_id)->count_all_results("post_comment");
        $is_likes = $this->db->where(["post_id" => $post_id, "user_id" => $user_id])->count_all_results("post_like") ? "0" : "1";
        $bookmark = $this->db->where(["post_id" => $post_id, "user_id" => $user_id])->count_all_results("bookmark_post") ? "0" : "1";
        $total_share = 27;
        $is_follow = $this->db->where(["from_user" => $user_id, "to_user" => $post_user_id])->count_all_results("follow") ? "1" : "0";

        $final_posts[] = [
            "post_id" => (string)$post['post_id'],
            "user_id" => (string)$post_user_id,
            "text" => $post['text'] ?? "",
            "image" => $images,
            "type" => $post['post_type'],
            "location" => $post['location'] ?? "",
            "created_at" => $post['created_at'],
            "is_likes" => $is_likes,
            "total_likes" => $total_likes,
            "total_comments" => $total_comments,
            "bookmark" => $bookmark,
            "total_view" => 0,
            "comment" => [],
            "profile_image" => $user_info['profile_pic'] ?? "",
            "username" => $user_info['username'] ?? "",
            "total_share" => (string)$total_share,
            "is_follow" => $is_follow,
            "is_blocked" => "0",
            "isBoostPost" => "0",
            "tag_user_list" => []
        ];
    }

    // ================= FINAL RESPONSE =================
    echo json_encode([
        "status" => "1",
        "message" => "User Posts Found",
        "post" => $final_posts,
        "current_page" => (int)$page_no,
        "last_page" => $last_page
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}





public function like_post_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set('Asia/Kolkata');

    // AUTH TOKEN
    $authHeader = $this->input->get_request_header('Authorization');
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => 0,
            "status" => "failure",
            "message" => "Authorization token missing"
        ]);
        return;
    }

    $token = $matches[1];
    $user_id = $this->User_model->validate_token_and_get_user($token);

    if (!$user_id) {
        echo json_encode([
            "response_code" => 0,
            "status" => "failure",
            "message" => "Invalid or expired token"
        ]);
        return;
    }

    // INPUT
    $requestData = json_decode(file_get_contents('php://input'), true);
    if (empty($requestData['post_id'])) {
        echo json_encode([
            "response_code" => 0,
            "status" => "failure",
            "message" => "Post ID is required"
        ]);
        return;
    }

    $post_id = (int)$requestData['post_id'];
    $now = date('Y-m-d H:i:s');

    // GET POST
    $post = $this->db
        ->select('post_id, post_type, user_id')
        ->where('post_id', $post_id)
        ->get('posts')
        ->row();

    if (!$post) {
        echo json_encode([
            "response_code" => 0,
            "status" => "failure",
            "message" => "Post not found"
        ]);
        return;
    }

    // LIKE TABLE
    if ($post->post_type === 'reel') {
        $like_table = 'reel_like';
        $id_column = 'reel_id';
    } else {
        $like_table = 'post_like';
        $id_column = 'post_id';
    }

    // CHECK ALREADY LIKED
    $already_liked = $this->db
        ->where('user_id', $user_id)
        ->where($id_column, $post_id)
        ->count_all_results($like_table);

    // IF ALREADY LIKED → UNLIKE
    if ($already_liked > 0) {
        $this->db
            ->where('user_id', $user_id)
            ->where($id_column, $post_id)
            ->delete($like_table);

        // FRESH TOTAL LIKE COUNT FROM TABLE
        $total_likes = $this->db
            ->from($like_table)
            ->where($id_column, $post_id)
            ->count_all_results();

        $this->db
            ->where('post_id', $post_id)
            ->update('posts', [
                'like_count' => $total_likes,
                'updated_at' => $now
            ]);

        echo json_encode([
            "response_code" => 1,
            "status" => "success",
            "message" => "Post Unliked",
            "like_count" => (string)$total_likes
        ]);
        return;
    }

    // ELSE → LIKE
    $this->db->insert($like_table, [
        'user_id' => $user_id,
        $id_column => $post_id,
        'created_at' => $now
    ]);

    // FRESH TOTAL LIKE COUNT FROM TABLE (always correct)
    $total_likes = $this->db
        ->from($like_table)
        ->where($id_column, $post_id)
        ->count_all_results();

    $this->db
        ->where('post_id', $post_id)
        ->update('posts', [
            'like_count' => $total_likes,
            'updated_at' => $now
        ]);

    // SEND NOTIFICATION ONLY ON LIKE
    if ($post->user_id != $user_id) {
        $liker = $this->db
            ->select('first_name')
            ->where('id', $user_id)
            ->get('users')
            ->row();
        $liker_name = $liker->first_name ?? 'Someone';

        $owner = $this->db
            ->select('device_token')
            ->where('id', $post->user_id)
            ->get('users')
            ->row();

        if (!empty($owner->device_token)) {
            $firebaseResponse = file_get_contents(
                base_url('index.php/api/PostController/get_firebase_token')
            );
            $decoded = json_decode($firebaseResponse, true);
            $access_token = $decoded['access_token'] ?? null;

            if ($access_token) {
                $payload = [
                    "message" => [
                        "token" => $owner->device_token,
                        "notification" => [
                            "title" => "New Like ❤️",
                            "body" => $liker_name . " liked your post"
                        ],
                        "data" => [
                            "post_id" => (string)$post_id,
                            "type" => "like"
                        ],
                        "android" => ["priority" => "HIGH"]
                    ]
                ];

                $ch = curl_init("https://fcm.googleapis.com/v1/projects/advpost/messages:send");
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        "Authorization: Bearer {$access_token}",
                        "Content-Type: application/json"
                    ],
                    CURLOPT_POSTFIELDS => json_encode($payload)
                ]);
                curl_exec($ch);
                curl_close($ch);
            }
        }
    }

    // FINAL RESPONSE
    echo json_encode([
        "response_code" => 1,
        "status" => "success",
        "message" => "Post Liked",
        "like_count" => (string)$total_likes
    ]);
}



public function post_add_comment_post()
{
    header('Content-Type: application/json; charset=utf-8');
    date_default_timezone_set('Asia/Kolkata');

    // ✅ FORCE EMOJI SUPPORT
    $this->db->query("SET NAMES utf8mb4");
    $this->db->query("SET CHARACTER SET utf8mb4");
    $this->db->query("SET collation_connection = utf8mb4_unicode_ci");

    /* =============================
       READ JSON INPUT
    ==============================*/
    $inputJSON = file_get_contents('php://input');
    $request   = json_decode($inputJSON, true);

    /* =============================
       AUTH TOKEN
    ==============================*/
    $authHeader = $this->input->get_request_header('Authorization');
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            'response_code' => 0,
            'message' => 'Authorization token missing',
            'status' => 'failure'
        ]);
        return;
    }

    $token   = $matches[1];
    $user_id = $this->User_model->validate_token_and_get_user($token);

    if (!$user_id) {
        echo json_encode([
            'response_code' => 0,
            'message' => 'Invalid or expired token',
            'status' => 'failure'
        ]);
        return;
    }

    /* =============================
       INPUT VALIDATION
    ==============================*/
    $post_id = $request['post_id'] ?? '';
    $text    = $request['text'] ?? '';

    if (empty($post_id) || empty($text)) {
        echo json_encode([
            'response_code' => 0,
            'message' => 'Post ID and comment text required',
            'status' => 'failure'
        ]);
        return;
    }

    /* =============================
       INSERT COMMENT
    ==============================*/
    $comment_data = [
        'user_id' => $user_id,
        'post_id' => $post_id,
        'text'    => $text, // 😍🔥👍 emoji supported
        'date'    => date('Y-m-d H:i:s')
    ];

    if (!$this->db->insert('post_comment', $comment_data)) {
        echo json_encode([
            'response_code' => 0,
            'message' => 'Database Error',
            'status' => 'failure'
        ]);
        return;
    }

    /* =============================
       GET POST OWNER
    ==============================*/
    $post = $this->db
        ->select('post_id, user_id')
        ->where('post_id', $post_id)
        ->get('posts')
        ->row();

    if (!$post) {
        echo json_encode([
            'response_code' => 0,
            'message' => 'Post not found',
            'status' => 'failure'
        ]);
        return;
    }

    $to_user = $post->user_id;

    /* =============================
       NO SELF NOTIFICATION
    ==============================*/
    if ($user_id == $to_user) {
        echo json_encode([
            'response_code' => 1,
            'message' => 'Post Comment added 💬',
            'status' => 'success'
        ]);
        return;
    }

    /* =============================
       COMMENTER INFO
    ==============================*/
    $commenter = $this->db
        ->select('id, first_name')
        ->where('id', $user_id)
        ->get('users')
        ->row();

    $commenter_name = (!empty($commenter->first_name))
        ? $commenter->first_name
        : 'Someone';

    /* =============================
       INSERT NOTIFICATION (DB)
    ==============================*/
    $this->db->insert('user_notifications', [
        'from_user'       => $user_id,
        'to_user'         => $to_user,
        'title'           => 'New Comment 💬',
        'post_id'         => $post_id,
        'message'         => $commenter_name . ' commented on your post 🔥',
        'notifiable_type' => 'comment',
        'created_at'      => date('Y-m-d H:i:s'),
        'post_user_id'    => $to_user
    ]);

    /* =============================
       SEND FCM NOTIFICATION
    ==============================*/
    $owner = $this->db
        ->select('id, device_token')
        ->where('id', $to_user)
        ->get('users')
        ->row();

    if ($owner && !empty($owner->device_token)) {

        // 🔹 Get Firebase Access Token
        $ch = curl_init(base_url('index.php/api/PostController/get_firebase_token'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $firebaseResponse = curl_exec($ch);
        curl_close($ch);

        $access_token = null;
        if ($firebaseResponse) {
            $decoded = json_decode($firebaseResponse, true);
            $access_token = $decoded['access_token'] ?? null;
        }

        if ($access_token) {

            $payload = [
                "message" => [
                    "token" => $owner->device_token,
                    "notification" => [
                        "title" => "New Comment 💬",
                        "body"  => $commenter_name . " commented on your post 🔥"
                    ],
                    "data" => [
                        "type"    => "comment",
                        "post_id" => (string)$post_id
                    ],
                    "android" => [
                        "priority" => "HIGH"
                    ]
                ]
            ];

            $fcm = curl_init("https://fcm.googleapis.com/v1/projects/advpost/messages:send");
            curl_setopt($fcm, CURLOPT_POST, true);
            curl_setopt($fcm, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$access_token}",
                "Content-Type: application/json"
            ]);
            curl_setopt($fcm, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($fcm, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_exec($fcm);
            curl_close($fcm);
        }
    }

    /* =============================
       FINAL RESPONSE
    ==============================*/
    echo json_encode([
        'response_code' => 1,
        'message' => 'Post Comment added successfully 😍',
        'status' => 'success'
    ]);
}


private function validate_token_get_user_id()
{
    $token = $this->_get_bearer_token();
    if ($token === '') {
        return false;
    }

    // Lookup user by token
    $user = $this->db->get_where('users', ['token' => $token])->row();
    return $user ? $user->id : false;
}

/**
 * Read Bearer token in a way that works on Apache and PHP's built-in server.
 * `getallheaders()` often omits Authorization under `php -S`.
 */
private function _get_bearer_token()
{
    $authHeader = $this->input->get_request_header('Authorization');
    if (empty($authHeader)) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';
    }
    if (empty($authHeader) && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                if (strcasecmp((string) $key, 'Authorization') === 0) {
                    $authHeader = $value;
                    break;
                }
            }
        }
    }

    if (!empty($authHeader) && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        return $matches[1];
    }
    return '';
}



public function post_on_comment_post()
{
    
    header('Content-Type: application/json; charset=utf-8');
    date_default_timezone_set('Asia/Kolkata');
$this->db->query("SET NAMES utf8mb4");
    /* ================= TOKEN VALIDATION ================= */
    $authHeader = $this->input->get_request_header('Authorization');
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Authorization Token Missing",
            "status" => "failure"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $token   = $matches[1];
    $user_id = $this->User_model->validate_token_and_get_user($token);

    if (!$user_id) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Invalid Token",
            "status" => "failure"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    /* ================= READ INPUT ================= */
    $input = json_decode(file_get_contents("php://input"), true);

    $post_id    = $input['post_id'] ?? '';
    $page_no    = isset($input['page_no']) ? (int)$input['page_no'] : 1;
    $comment_id = $input['comment_id'] ?? null;

    $per_page = 10;   // ✅ ONLY 10 COMMENTS
    $offset   = ($page_no - 1) * $per_page;

    if (!$post_id) {
        echo json_encode([
            "response_code" => "0",
            "message" => "post_id is required",
            "status" => "failure"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $all_comments  = [];
    $total_records = 0;

    /* =====================================================
       🔹 CASE 1: FETCH SUB COMMENTS
    ====================================================== */
    if ($comment_id) {

        $sub_comments = $this->db
            ->where('post_id', $post_id)
            ->where('post_comment_id', $comment_id)
            ->order_by('id', 'DESC')
            ->limit($per_page, $offset)
            ->get('post_sub_comment')
            ->result();

        foreach ($sub_comments as $sc) {

            $user = $this->db->get_where('users', ['id' => $sc->user_id])->row();

            $is_like = $this->db
                ->where('post_sub_comment_id', $sc->id)
                ->where('user_id', $user_id)
                ->count_all_results('post_sub_comment_like') > 0;

            $total_like_count = $this->db
                ->where('post_sub_comment_id', $sc->id)
                ->count_all_results('post_sub_comment_like');

            $all_comments[] = [
                "post_sub_comment_id" => (string)$sc->id,
                "user_id"             => (string)$sc->user_id,
                "post_id"             => (string)$sc->post_id,
                "comment_id"          => (string)$sc->post_comment_id,
                "text"                => $sc->text, // ✅ EMOJI SAFE
                "created_at"          => date("Y-m-d H:i:s", strtotime($sc->created_at)),
                "username"            => $user->username ?? "",
                "profile_pic"         => !empty($user->profile_pic)
                    ? (preg_match('/^https?:\/\//', $user->profile_pic)
                        ? $user->profile_pic
                        : base_url('assets/images/user/' . $user->profile_pic))
                    : "",
                "total_reply_count"   => "0",
                "total_like_count"    => (int)$total_like_count,
                "is_like"             => $is_like
            ];
        }

        $total_records = $this->db
            ->where('post_id', $post_id)
            ->where('post_comment_id', $comment_id)
            ->count_all_results('post_sub_comment');
    }

    /* =====================================================
       🔹 CASE 2: FETCH MAIN COMMENTS
    ====================================================== */
    else {

        $comments = $this->db
            ->where('post_id', $post_id)
            ->order_by('id', 'DESC')
            ->limit($per_page, $offset)
            ->get('post_comment')
            ->result();

        foreach ($comments as $c) {

            $user = $this->db->get_where('users', ['id' => $c->user_id])->row();

            $is_like = $this->db
                ->where('post_comment_id', $c->id)
                ->where('user_id', $user_id)
                ->count_all_results('post_comment_like') > 0;

            $total_like_count = $this->db
                ->where('post_comment_id', $c->id)
                ->count_all_results('post_comment_like');

            $total_comment_count = $this->db
                ->where('post_comment_id', $c->id)
                ->count_all_results('post_sub_comment');

            $all_comments[] = [
                "user_id"             => (string)$c->user_id,
                "post_id"             => (string)$c->post_id,
                "comment_id"          => (string)$c->id,
                "text"                => $c->text, // ✅ EMOJI SAFE
                "created_at"          => date("Y-m-d H:i:s", strtotime($c->created_at)),
                "username"            => $user->username ?? "",
                "profile_pic"         => !empty($user->profile_pic)
                    ? (preg_match('/^https?:\/\//', $user->profile_pic)
                        ? $user->profile_pic
                        : base_url('assets/images/user/' . $user->profile_pic))
                    : "",
                "total_comment_count" => (string)$total_comment_count,
                "total_like_count"    => (int)$total_like_count,
                "is_like"             => $is_like
            ];
        }

        $total_records = $this->db
            ->where('post_id', $post_id)
            ->count_all_results('post_comment');
    }

    /* ================= PAGINATION ================= */
    $total_page = ($total_records > 0) ? ceil($total_records / $per_page) : 0;
    $remaining_value = ($total_records > ($page_no * $per_page))
        ? $total_records - ($page_no * $per_page)
        : 0;

    /* ================= FINAL RESPONSE ================= */
    if (!empty($all_comments)) {
        echo json_encode([
            "response_code"    => "1",
            "message"          => "Comment Found",
            "status"           => "success",
            "current_page"     => $page_no,
            "total_page"       => $total_page,
            "remaining_value"  => $remaining_value,
            "all_post_comment" => $all_comments
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            "response_code"    => "0",
            "message"          => "No Comment Found",
            "status"           => "failure",
            "current_page"     => 0,
            "total_page"       => 0,
            "remaining_value"  => 0,
            "all_post_comment" => []
        ], JSON_UNESCAPED_UNICODE);
    }
}



// public function comment_like_post_post()
// {
//     header('Content-Type: application/json');
//     date_default_timezone_set('Asia/Kolkata');

//     // 🔹 Validate Token
//     $authHeader = $this->input->get_request_header('Authorization');
//     if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
//         echo json_encode([
//             "response_code" => "0",
//             "message" => "Authorization Token Missing",
//             "status" => "failed"
//         ]);
//         return;
//     }
//     $token = $matches[1];
//     $user_id = $this->User_model->validate_token_and_get_user($token);

//     if (!$user_id) {
//         echo json_encode([
//             "response_code" => "0",
//             "message" => "Invalid Token",
//             "status" => "failed"
//         ]);
//         return;
//     }

//     // 🔹 Receive JSON Input
//     $inputJSON = file_get_contents('php://input');
//     $requestData = json_decode($inputJSON, true);

//     $post_id = isset($requestData['post_id']) ? trim($requestData['post_id']) : '';
//     $post_comment_id = isset($requestData['post_comment_id']) ? trim($requestData['post_comment_id']) : '';

//     if ($post_id === '' || $post_comment_id === '') {
//         echo json_encode(["response_code" => "0", "message" => "Enter post_id and post_comment_id", "status" => "failed"]);
//         return;
//     }

//     // 🔹 Check if Comment Exists
//     $comment = $this->db
//         ->where('id', $post_comment_id)
//         ->where('post_id', $post_id)
//         ->get('post_comment')
//         ->row();

//     if (!$comment) {
//         echo json_encode([
//             "response_code" => "0",
//             "message" => "Invalid post_id or post_comment_id",
//             "status" => "failed"
//         ]);
//         return;
//     }

//     // 🔹 Check if Already Liked
//     $existing_like = $this->db
//         ->where('user_id', $user_id)
//         ->where('post_id', $post_id)
//         ->where('post_comment_id', $post_comment_id)
//         ->get('post_comment_like')
//         ->row();

//     if ($existing_like) {
//         echo json_encode([
//             "response_code" => "0",
//             "message" => "Already Liked",
//             "status" => "failed"
//         ]);
//         return;
//     }

//     // 🔹 Insert Like
//     $this->db->insert('post_comment_like', [
//         'user_id' => $user_id,
//         'post_id' => $post_id,
//         'post_comment_id' => $post_comment_id,
//         'date' => round(microtime(true) * 1000)
//     ]);

//     // 🔹 Calculate total likes for this comment
//     $like_count = $this->db
//         ->where('post_comment_id', $post_comment_id)
//         ->count_all_results('post_comment_like');

//     // 🔹 Update like_count in post_comment table
//     $this->db
//         ->where('id', $post_comment_id)
//         ->update('post_comment', ['like_count' => $like_count]);

//     echo json_encode([
//         "response_code" => "1",
//         "message" => "Comment Post Like successfull",
//         "status" => "success"
//     ]);
// }




public function post_add_subcomment_post()
{
    header('Content-Type: application/json');
    date_default_timezone_set('Asia/Kolkata');
$this->db->query("SET NAMES utf8mb4");
    $inputJSON = file_get_contents('php://input');
    $request = json_decode($inputJSON, true);

    // Get user_id from token
    $user_id = $this->validate_token_get_user_id(); // Implement this

    $post_id = isset($request['post_id']) ? $request['post_id'] : '';
    $post_comment_id = isset($request['post_comment_id']) ? $request['post_comment_id'] : '';
    $text = isset($request['text']) ? $request['text'] : '';

    if (empty($post_id) || empty($text) || empty($post_comment_id)) {
        echo json_encode([
            'response_code' => 0,
            'message' => 'Enter Data',
            'status' => 'failure'
        ]);
        return;
    }

    // Get post owner id
    $post_owner_row = $this->db->select('user_id')->get_where('posts', ['post_id' => $post_id])->row();
    $post_user_id = $post_owner_row ? $post_owner_row->user_id : null;

    // Insert subcomment
    $comment_data = [
        'user_id' => $user_id,          // ✅ the commenter
        'post_id' => $post_id,
        'post_comment_id' => $post_comment_id,
        'text' => $text,
        'date' => date('Y-m-d H:i:s')
    ];

    $insert = $this->db->insert('post_sub_comment', $comment_data);

    if (!$insert) {
        echo json_encode([
            'response_code' => 0,
            'message' => 'Database Error',
            'status' => 'failure'
        ]);
        return;
    }

    // Get parent comment owner
    $parent_comment = $this->db->get_where('post_comment', [
        'id' => $post_comment_id,
        'post_id' => $post_id
    ])->row();

    if (!$parent_comment) {
        echo json_encode([
            'response_code' => 0,
            'message' => 'Parent comment not found',
            'status' => 'failure'
        ]);
        return;
    }

    $to_user = $parent_comment->user_id; // who wrote parent comment

    // No notification if replying to own comment
    if ($user_id == $to_user) {
        echo json_encode([
            'response_code' => 1,
            'message' => 'Post SubComment Add, but no notification sent for your own comment',
            'status' => 'success'
        ]);
        return;
    }

    // Get device token safely
    $user_token_row = $this->db->select('device_token')->get_where('users', ['id' => $to_user])->row();
    $FcmToken = $user_token_row ? $user_token_row->device_token : '';

    // Get notification template
    $proviver_noti = $this->db->get_where('notification_permissions', ['id' => 7, 'status' => 1])->row();

    // Get commenting username
    $user_row = $this->db->get_where('users', ['id' => $user_id])->row();
    $username = $user_row ? $user_row->username : 'Someone';

    $message = $proviver_noti ? str_replace(['[[ username ]]', '[[ time ]]'], [$username, ''], $proviver_noti->description) : "$username replied to your comment";

    // Insert into user_notification table
    $not_all = [
        'from_user' => $user_id,
        'to_user' => $to_user,
        'title' => $proviver_noti ? $proviver_noti->title : 'Post SubComment',
        'post_id' => $post_id,
        'message' => $message,
        'notifiable_type' => $proviver_noti ? $proviver_noti->type : 'subcomment',
        'created_at' => date('Y-m-d H:i:s'),
        'post_user_id' => $post_user_id, // owner of the post
    ];
    $this->db->insert('user_notifications', $not_all);

    echo json_encode([
        'response_code' => 1,
        'message' => 'Post SubComment Add',
        'status' => 'success'
    ]);
}

public function my_post_like_list_post()
{
    header("Content-Type: application/json; charset=UTF-8");
    date_default_timezone_set('Asia/Kolkata');

    $this->load->model('User_model');

    // 🔹 Get Authorization Token (works on php -S and Apache)
    $token = $this->_get_bearer_token();

    // 🔹 Validate Token
    $user_id = $this->User_model->validate_token_and_get_user($token);
    if (!$user_id) {
        echo json_encode([
            "response_code" => "0",
            "message"       => "Invalid or missing token",
            "post_like_list"=> []
        ]);
        return;
    }

    // 🔹 Read post_id from JSON body
    $inputJSON = file_get_contents('php://input');
    $request = json_decode($inputJSON, true);
    $post_id = isset($request['post_id']) ? trim($request['post_id']) : '';

    if (empty($post_id)) {
        echo json_encode([
            'response_code'   => '0',
            'message'         => 'post_id is required',
            'post_like_list'  => []
        ]);
        return;
    }

    // 🔹 Fetch Post Type
    $post = $this->db->get_where('posts', ['post_id' => $post_id])->row();
    if (!$post) {
        echo json_encode([
            'response_code'   => '0',
            'message'         => 'Post Not Found',
            'post_like_list'  => []
        ]);
        return;
    }

    $post_type = $post->post_type;

    // 🔹 Fetch Likes
    if ($post_type == 'image') {
        $this->db->select('user_id, post_id, created_at')
                 ->from('post_like')
                 ->where('post_id', $post_id);
        $res = $this->db->get()->result();
    } else {
        $this->db->select('user_id, reel_id as post_id, created_at')
                 ->from('reel_like')
                 ->where('reel_id', $post_id);
        $res = $this->db->get()->result();
    }

    if (empty($res)) {
        echo json_encode([
            'response_code'   => '0',
            'message'         => 'No Likes Found',
            'post_like_list'  => []
        ]);
        return;
    }

    $data = [];
    foreach ($res as $row) {
        $user = $this->db->get_where('users', ['id' => $row->user_id])->row();

        // 🔹 Default image if profile_pic empty
        $profilePic = adv_profile_pic_url($user->profile_pic ?? '');

        $data[] = [
            'user_id'     => (int)($row->user_id ?? 0),
            'post_id'     => (int)($row->post_id ?? 0),
            'created_at'  => date(DATE_ISO8601, strtotime($row->created_at)),
            'username'    => $user->username ?? '',
            'email'       => $user->email ?? '',
            'profile_pic' => $profilePic,
            'is_follow'   => false
        ];
    }

    echo json_encode([
        'response_code'   => '1',
        'message'         => 'Post Like User List',
        'post_like_list'  => $data,
                'status' => 'Success'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}



public function post_reply_on_comment_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set('Asia/Kolkata');

    $this->load->model('User_model');

    // 🔹 Get Token (works on php -S and Apache)
    $token = $this->_get_bearer_token();

    // 🔹 Validate Token
    $user_id = $this->User_model->validate_token_and_get_user($token);
    if (!$user_id) {
        echo json_encode([
            'response_code' => '0',
            'message' => 'Invalid or missing token',
            'status' => 'failure'
        ]);
        return;
    }

    // 🔹 Read Input (JSON body)
    $inputJSON = file_get_contents('php://input');
    $request = json_decode($inputJSON, true);

    $post_subcomment_id = isset($request['post_subcomment_id']) ? trim($request['post_subcomment_id']) : '';
    $post_comment_id    = isset($request['post_comment_id']) ? trim($request['post_comment_id']) : '';
    $pageNo             = isset($request['page_no']) ? (int)$request['page_no'] : 1;
    $perPage            = 5;

    if (empty($post_subcomment_id)) {
        echo json_encode([
            'response_code' => '0',
            'message' => 'post_subcomment_id is required',
            'status' => 'failure',
            'all_post_comment' => []
        ]);
        return;
    }

    // 🔹 Pagination Setup
    $offset = ($pageNo - 1) * $perPage;

    // 🔹 Fetch Replies
    $this->db->select('*')
        ->from('post_reply')
        ->where('post_subcomment_id', $post_subcomment_id)
        ->where('post_comment_id', $post_comment_id)
        ->limit($perPage, $offset);

    $query = $this->db->get();
    $res = $query->result();

    $data = [];
    foreach ($res as $row) {

        // Check Like
        $is_like = $this->db->get_where('post_reply_like', [
            'post_reply_id' => $row->id,
            'user_id' => $user_id
        ])->row();

        // Total Like Count
        $total_like = $this->db->where('post_reply_id', $row->id)
            ->from('post_reply_like')
            ->count_all_results();

        // User Info
        $user = $this->db->get_where('users', ['id' => $row->user_id])->row();

        $data[] = [
            'post_reply_id'     => (string)$row->id,
            'user_id'           => (string)$row->user_id,
            'post_subcomment_id'=> (string)$row->post_subcomment_id,
            'post_comment_id'   => (string)$row->post_comment_id,
            'text'              => (string)$row->text,
            'created_at'        => (string)$row->created_at,
            'is_like'           => $is_like ? true : false,
            'total_like_count'  => $total_like,
            'username'          => $user->username ?? '',
            'profile_pic'       => !empty($user->profile_pic) 
                                    ? adv_profile_pic_url($user->profile_pic)
                                    : ''
        ];
    }

    // 🔹 Total Reply Count
    $totalReplies = $this->db->where('post_subcomment_id', $post_subcomment_id)
        ->where('post_comment_id', $post_comment_id)
        ->from('post_reply')
        ->count_all_results();

    $total_page = ceil($totalReplies / $perPage);
    $remaining_value = max(0, $totalReplies - ($pageNo * $perPage));

    // 🔹 Final Response
    if (!empty($data)) {
        echo json_encode([
            'response_code'  => '1',
            'message'        => 'All Comment Found',
            'status'         => 'success',
            'total_page'     => $total_page,
            'current_page'   => $pageNo,
            'remaining_value'=> $remaining_value,
            'all_post_comment'=> $data
        ]);
    } else {
        echo json_encode([
            'response_code'  => '0',
            'message'        => 'All Comment List Not Found',
            'status'         => 'failure',
            'total_page'     => 0,
            'current_page'   => 0,
            'remaining_value'=> 0,
            'all_post_comment'=> []
        ]);
    }
}




public function add_reel_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set('Asia/Kolkata');

    $this->load->model('User_model');

    // 🔹 Get Authorization Token (works on php -S and Apache)
    $token = $this->_get_bearer_token();

    // 🔹 Validate Token
    $user_id = $this->User_model->validate_token_and_get_user($token);
    if (!$user_id) {
        echo json_encode([
            "response_code" => "0",
            "status"        => "failed",
            "message"       => "Invalid or missing token"
        ]);
        return;
    }

    try {
        // Input fields
        $title       = $this->input->post('title');
        $location    = $this->input->post('location');
        $description = $this->input->post('description');
        $reel_type   = $this->input->post('reel_type');
        $tag_users   = $this->input->post('tag_users');

        // Insert main reel data
        $reelData = [
            'user_id'     => $user_id,
            'title'       => $title,
            'location'    => $location,
            'description' => $description,
            'reel_type'   => $reel_type,
            'created_at'  => date('Y-m-d H:i:s')
        ];
        $this->db->insert('reels', $reelData);
        $reel_id = $this->db->insert_id();

        // ------------ SAFE FILE UPLOAD FOR SINGLE & MULTIPLE ------------
        if (!empty($_FILES['reel_video']['name'])) {

            // Convert to array if single file upload
            $isMultiple = is_array($_FILES['reel_video']['name']);
            $fileCount = $isMultiple ? count($_FILES['reel_video']['name']) : 1;

            for ($index = 0; $index < $fileCount; $index++) {

                // Video file handling → S3
                $video_name = $isMultiple ? $_FILES['reel_video']['name'][$index] : $_FILES['reel_video']['name'];
                $tmp_video  = $isMultiple ? $_FILES['reel_video']['tmp_name'][$index] : $_FILES['reel_video']['tmp_name'];
                $video_type = $isMultiple ? ($_FILES['reel_video']['type'][$index] ?? 'video/mp4') : ($_FILES['reel_video']['type'] ?? 'video/mp4');
                $video_err  = $isMultiple ? $_FILES['reel_video']['error'][$index] : $_FILES['reel_video']['error'];

                if ((int) $video_err !== 0 || empty($tmp_video)) {
                    continue;
                }

                $safeVideoName = adv_s3_safe_name($video_name);
                $videoFileName = time() . "_" . $index . "_" . $safeVideoName;
                $s3VideoKey = adv_user_media_key($user_id, 'reels', $reel_id, 'videos', $videoFileName);

                $videoArr = [
                    'name' => $video_name,
                    'type' => $video_type,
                    'tmp_name' => $tmp_video,
                    'error' => $video_err,
                    'size' => $isMultiple ? $_FILES['reel_video']['size'][$index] : $_FILES['reel_video']['size'],
                ];
                $videoUrl = adv_s3_upload_file_field($videoArr, $s3VideoKey, $video_type ?: 'video/mp4');
                if ($videoUrl === false) {
                    throw new Exception('Reel video S3 upload failed: ' . adv_s3_last_error());
                }

                // Thumbnail → S3
                $thumbnailUrl = '';
                if (!empty($_FILES['reel_video_thumbnail']['name'])) {
                    $thumb_name = $isMultiple ? $_FILES['reel_video_thumbnail']['name'][$index] : $_FILES['reel_video_thumbnail']['name'];
                    $tmp_thumb  = $isMultiple ? $_FILES['reel_video_thumbnail']['tmp_name'][$index] : $_FILES['reel_video_thumbnail']['tmp_name'];
                    $thumb_err  = $isMultiple ? ($_FILES['reel_video_thumbnail']['error'][$index] ?? 4) : ($_FILES['reel_video_thumbnail']['error'] ?? 4);
                    $thumb_type = $isMultiple ? ($_FILES['reel_video_thumbnail']['type'][$index] ?? '') : ($_FILES['reel_video_thumbnail']['type'] ?? '');

                    if (!empty($thumb_name) && (int) $thumb_err === 0 && !empty($tmp_thumb)) {
                        $safeThumbName = adv_s3_safe_name($thumb_name);
                        $thumbFileName = time() . "_" . $index . "_thumb_" . $safeThumbName;
                        $s3ThumbKey = adv_user_media_key($user_id, 'reels', $reel_id, 'thumbnails', $thumbFileName);
                        $thumbArr = [
                            'name' => $thumb_name,
                            'type' => $thumb_type,
                            'tmp_name' => $tmp_thumb,
                            'error' => $thumb_err,
                            'size' => $isMultiple ? $_FILES['reel_video_thumbnail']['size'][$index] : $_FILES['reel_video_thumbnail']['size'],
                        ];
                        $thumbnailUrl = adv_s3_upload_file_field($thumbArr, $s3ThumbKey, $thumb_type ?: null) ?: '';
                    }
                }

                // Insert video record
                $videoData = [
                    'reel_id'              => $reel_id,
                    'user_id'              => $user_id,
                    'reel_video'           => $videoUrl,
                    'reel_video_thumbnail' => $thumbnailUrl,
                    'created_at'           => date('Y-m-d H:i:s')
                ];
                $this->db->insert('reels_video', $videoData);
            }
        }

        // ----------- Tag Users Handling ----------------
        if (!empty($tag_users)) {
            if (is_string($tag_users)) {
                $tags = explode(',', $tag_users);
            } elseif (is_array($tag_users)) {
                $tags = $tag_users;
            } else {
                $tags = [];
            }

            foreach ($tags as $tag) {
                $this->db->insert('reel_user_tags', [
                    'reel_id'    => $reel_id,
                    'user_id'    => $user_id,
                    'tag_users'  => trim($tag),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }

        // Final Response
        echo json_encode([
            'response_code' => '1',
            'message'       => 'Reel is Added Successfully',
            'status'        => 'success',
            'reel_id'       => $reel_id,
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'response_code' => '0',
            'status'        => 'failed',
            'message'       => 'Reel is not Added',
            'error'         => $e->getMessage()
        ]);
    }
}



public function view_reel_post()
{
    header('Content-Type: application/json');
    date_default_timezone_set('Asia/Kolkata');
    $this->load->model('User_model');

    /* ================= TOKEN ================= */
    $authHeader = $this->input->get_request_header('Authorization');
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Authorization token missing",
            "status" => "failure"
        ]);
        return;
    }
    $token = $matches[1];

    $user_id = $this->User_model->validate_token_and_get_user($token);
    if (!$user_id) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Invalid or expired token",
            "status" => "failure"
        ]);
        return;
    }

    /* ================= INPUT ================= */
    $request = json_decode(file_get_contents("php://input"), true);
    $reel_id = $request['reel_id'] ?? '';

    if (!$reel_id) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Reel ID is required",
            "status" => "failure"
        ]);
        return;
    }

    /* ================= 1 MINUTE CHECK ================= */
    $oneMinuteAgo = date('Y-m-d H:i:s', strtotime('-1 minute'));

    $recentView = $this->db
        ->where('reel_id', $reel_id)
        ->where('user_id', $user_id)
        ->where('created_at >=', $oneMinuteAgo)
        ->count_all_results('view_reel');

    // total views = sum of count
    $viewCount = $this->db
        ->select_sum('count')
        ->where('reel_id', $reel_id)
        ->get('view_reel')
        ->row()
        ->count ?? 0;

    if ($recentView > 0) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Already viewed within last 1 minute",
            "status" => "failure",
            "view_count" => (string)$viewCount
        ]);
        return;
    }

    /* ================= CHECK SAME USER + REEL ================= */
    $existing = $this->db
        ->where('reel_id', $reel_id)
        ->where('user_id', $user_id)
        ->get('view_reel')
        ->row_array();

    if ($existing) {
        // ✅ UPDATE COUNT
        $this->db
            ->set('count', 'count + 1', false)
            ->where('id', $existing['id'])
            ->update('view_reel');
    } else {
        // ✅ INSERT FIRST TIME
        $this->db->insert('view_reel', [
            'reel_id'    => $reel_id,
            'user_id'    => $user_id,
            'count'      => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    $newViewCount = $this->db
        ->select_sum('count')
        ->where('reel_id', $reel_id)
        ->get('view_reel')
        ->row()
        ->count ?? 0;

    echo json_encode([
        "response_code" => "1",
        "message" => "Reel View Recorded Successfully",
        "status" => "success",
        "view_count" => (string)$newViewCount
    ]);
}


 
// public function all_my_reel_pagination_post()
// {
//     header("Content-Type: application/json; charset=utf-8");
//     date_default_timezone_set('Asia/Kolkata');

//     // 🔹 Read JSON Request
//     $inputJSON = file_get_contents("php://input");
//     $request   = json_decode($inputJSON, true);

//     // 🔹 Check JSON Validity
//     if (!$request) {
//         echo json_encode([
//             "status" => "Failure",
//             "message" => "Invalid JSON format"
//         ]);
//         return;
//     }

//     // 🔹 User ID Required
//     $user_id = $request['user_id'] ?? null;
//     if (empty($user_id)) {
//         echo json_encode([
//             "status" => "Failure",
//             "message" => "User ID is required"
//         ]);
//         return;
//     }

//     // 🔹 Pagination setup
//     $per_page = isset($request['per_page']) ? (int)$request['per_page'] : 10;
//     $page_no  = isset($request['page_no']) ? (int)$request['page_no'] : 1;
//     if ($page_no < 1) $page_no = 1;
//     $offset   = ($page_no - 1) * $per_page;

//     // 🔹 Total reels count (Only user's reels and not deleted)
//     $this->db->where('reel_type', 'reel');
//     $this->db->where('user_id', $user_id);
//     $total_reels = $this->db->count_all_results('reels');

//     if ($total_reels == 0) {
//         echo json_encode([
//             "status" => "Failure",
//             "message" => "No Reels Found",
//             "reels" => [],
//             "current_page" => $page_no,
//             "last_page" => 0
//         ]);
//         return;
//     }

//     $last_page = ceil($total_reels / $per_page);

//     // 🔹 Fetch paginated reels
//     $reels = $this->db->select('*')
//         ->from('reels')
//         ->where('reel_type', 'reel')
//         ->where('user_id', $user_id)
//         ->order_by('reel_id', 'DESC')
//         ->limit($per_page, $offset)
//         ->get()
//         ->result_array();

//     $result = [];

//     foreach ($reels as $reel) {
//         $reel_id = $reel['reel_id'];

//         // 🔹 Fetch Reel Videos from reels_video table
//         $videoRows = $this->db->select('*')
//             ->from('reels_video')
//             ->where('reel_id', $reel_id)
//             ->get()
//             ->result_array();

//         $videos = [];
//         foreach ($videoRows as $vid) {
//             $videos[] = [
//                 "reel_video_id" => $vid['id'],
//                 "reel_video" => (strpos($vid['reel_video'], 'http') === 0)
//                     ? $vid['reel_video']
//                     : base_url('assets/videos/reel_video/' . basename($vid['reel_video'])),
//                 "type" => "video",
//                 "reel_video_thumbnail" => !empty($vid['reel_video_thumbnail'])
//                     ? base_url('assets/videos/reel_video_thumbnails/' . basename($vid['reel_video_thumbnail']))
//                     : ""
//             ];
//         }

//         // 🔹 Count likes, comments, views, bookmark, user_like
//         $total_like    = $this->db->where('reel_id', $reel_id)->count_all_results('reel_like');
//         $total_comment = $this->db->where('reel_id', $reel_id)->count_all_results('reel_comment');
//         $total_view    = $this->db->where('reel_id', $reel_id)->count_all_results('view_reel');

//         $bookmark = $this->db->where([
//             'post_id' => $reel_id,
//             'user_id' => $user_id
//         ])->count_all_results('bookmark_post') ? '1' : '0';

//         $is_likes = $this->db->where([
//             'reel_id' => $reel_id,
//             'user_id' => $user_id
//         ])->count_all_results('reel_like') ? '1' : '0';

//         // 🔹 Final Output Format
//         $result[] = [
//             "reel_id"       => $reel['reel_id'],
//             "user_id"       => $reel['user_id'],
//             "text"          => $reel['text'],
//             "location"      => $reel['location'],
//             "reel_type"     => $reel['reel_type'],
//             "created_at"    => $reel['created_at'],
//             "updated_at"    => $reel['updated_at'],
//             "video"         => $videos,
//             "is_delete"     => $reel['is_delete'],
//             "total_like"    => $total_like,
//             "total_comment" => $total_comment,
//             "total_view"    => $total_view,
//             "bookmark"      => $bookmark,
//             "is_likes"      => $is_likes
//         ];
//     }

//     // 🔹 Final Response
//     echo json_encode([
//         "status"       => "Success",
//         "message"      => "My Reel Found",
//         "reels"        => $result,
//         "current_page" => $page_no,
//         "last_page"    => $last_page
//     ], JSON_UNESCAPED_UNICODE);
// }



public function add_story_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set('Asia/Kolkata');

    $this->load->model('User_model');
    $this->load->model('Story_model');
    $this->load->library('upload');
$this->db->query("SET NAMES utf8mb4");
    // 🔹 Get Token from Header
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? '';

    if (strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
    } else {
        echo json_encode([
            'response_code' => '0',
            'message'       => 'Token missing',
            'status'        => 'failed',
        ]);
        return;
    }

    // 🔹 Validate Token & Get user_id
    $user_id = $this->User_model->validate_token_and_get_user($token);
    if (!$user_id) {
        echo json_encode([
            'response_code' => '0',
            'message'       => 'Invalid or expired token',
            'status'        => 'failed',
        ]);
        return;
    }

    // 🔹 Now user_id is NOT null
    $type     = $this->input->post('type');
    $location = $this->input->post('location') ?? '';
    $text     = $this->input->post('text') ?? '';

    // Insert stub first so S3 path can include story_id
    $update = [
        'user_id'    => $user_id,
        'type'       => $type,
        'location'   => $location,
        'text'       => $text,
        'url'        => '',
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $insert_id = $this->User_model->insert_story($update);
    if (!$insert_id) {
        echo json_encode([
            'response_code' => '0',
            'message'       => 'Failed to add story',
            'status'        => 'failed',
        ]);
        return;
    }

    $storyUpdates = [];

    // ===== Upload MAIN STORY FILE → S3  users/{uid}/stories/{story_id}/media/ =====
    if (!empty($_FILES['url']['name'])) {
        $fileName = time() . '_' . adv_s3_safe_name($_FILES['url']['name']);
        $s3Key = adv_user_media_key($user_id, 'stories', $insert_id, 'media', $fileName);
        $s3Url = adv_s3_upload_file_field($_FILES['url'], $s3Key);
        if ($s3Url === false) {
            echo json_encode([
                'response_code' => '0',
                'message'       => 'Story upload to S3 failed: ' . adv_s3_last_error(),
                'status'        => 'failed',
            ]);
            return;
        }
        $storyUpdates['url'] = $s3Url;
    }

    // ===== Upload THUMBNAIL → S3  users/{uid}/stories/{story_id}/thumbnails/ =====
    if (!empty($_FILES['video_thumbnail']['name'])) {
        $fileThumb = 'thumb_' . date('YmdHis') . '_' . adv_s3_safe_name($_FILES['video_thumbnail']['name']);
        $s3Key = adv_user_media_key($user_id, 'stories', $insert_id, 'thumbnails', $fileThumb);
        $s3Url = adv_s3_upload_file_field($_FILES['video_thumbnail'], $s3Key);
        if ($s3Url === false) {
            echo json_encode([
                'response_code' => '0',
                'message'       => 'Story thumbnail S3 upload failed: ' . adv_s3_last_error(),
                'status'        => 'failed',
            ]);
            return;
        }
        $storyUpdates['video_thumbnail'] = $s3Url;
    }

    if (!empty($storyUpdates)) {
        $this->db->where('story_id', $insert_id)->update('story', $storyUpdates);
    }

    echo json_encode([
        'response_code' => '1',
        'message'       => 'Story Added successfully',
        'status'        => 'success',
        'story_id'      => (int) $insert_id,
    ]);
}


public function get_story_by_user_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set('Asia/Kolkata');

    $this->load->model('User_model');

    /* ===================== TOKEN ===================== */
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? '';

    if (strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
    } else {
        echo json_encode([
            'response_code' => '0',
            'message' => 'Token Missing',
            'status' => 'failed',
        ]);
        return;
    }

    $user_id = $this->User_model->validate_token_and_get_user($token);
    if (!$user_id) {
        echo json_encode([
            'response_code' => '0',
            'message' => 'Invalid Token',
            'status' => 'failed',
        ]);
        return;
    }

    /* ===================== MY STORY (LAST 24 HOURS) ===================== */

    $my_post = [];

    $selfStories = $this->db->query("
        SELECT *
        FROM story
        WHERE user_id = ?
        AND is_delete = 0
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY story_id DESC
        LIMIT 1
    ", [$user_id])->result();

    foreach ($selfStories as $list) {

        $selfCount = $this->db->query("
            SELECT COUNT(*) AS total_stories
            FROM story
            WHERE user_id = ?
            AND is_delete = 0
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ", [$user_id])->row();

        $user = $this->db->get_where('users', ['id' => $user_id])->row();

        $selfData = [
            'story_id' => (string)$list->story_id,
            'user_id' => (string)$list->user_id,
            'url' => adv_story_media_url($list->url),
            'type' => $list->type,
            'create_date' => date('Y-m-d\TH:i:sP', strtotime($list->created_at)),
            'total_stories' => (string)$selfCount->total_stories,
            'user_read_count' => "0",
            'username' => $user->username ?? "",
            'profile_pic' => !empty($user->profile_pic)
                ? adv_profile_pic_url($user->profile_pic)
                : ""
        ];

        $storyArr = [];
        $stories = $this->db->query("
            SELECT *
            FROM story
            WHERE user_id = ?
            AND is_delete = 0
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC
        ", [$user_id])->result();

        foreach ($stories as $row) {
            $storyArr[] = [
                'story_id' => (string)$row->story_id,
                'url' => adv_story_media_url($row->url),
                'type' => $row->type,
                'created_at' => date('Y-m-d\TH:i:sP', strtotime($row->created_at)),
                'location' => $row->location ?? "",
                'text' => $row->text ?? "",
                'is_like' => $this->db->where('user_id', $user_id)
                        ->where('story_id', $row->story_id)
                        ->count_all_results('story_like') > 0 ? '1' : '0',
                'is_hightlight' => (string)($row->is_hightlight ?? "0"),
                'is_seen' => (string)$this->db->where('user_id', $user_id)
                        ->where('story_id', $row->story_id)
                        ->count_all_results('view_story'),
            ];
        }

        $selfData['story_image'] = $storyArr;
        $my_post[] = $selfData;
    }

    /* ===================== OTHER USERS STORIES (LAST 24 HOURS) ===================== */

    $res = $this->db->query("
        SELECT s.*
        FROM story s
        JOIN (
            SELECT user_id, MAX(story_id) AS max_story_id
            FROM story
            WHERE is_delete = 0
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND user_id != ?
            GROUP BY user_id
        ) t ON s.story_id = t.max_story_id
        ORDER BY s.story_id DESC
    ", [$user_id])->result();

    $storyImageList = [];

    foreach ($res as $list) {

        $res_done = $this->db->query("
            SELECT COUNT(*) AS total_stories
            FROM story
            WHERE user_id = ?
            AND is_delete = 0
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ", [$list->user_id])->row();

        $user = $this->db->get_where('users', ['id' => $list->user_id])->row();

        $storyList = [
            'story_id' => (string)$list->story_id,
            'user_id' => (string)$list->user_id,
            'url' => adv_story_media_url($list->url),
            'type' => $list->type,
            'create_date' => date('Y-m-d\TH:i:sP', strtotime($list->created_at)),
            'total_stories' => (string)$res_done->total_stories,
            'user_read_count' => "0",
            'username' => $user->username ?? "",
            'profile_pic' => !empty($user->profile_pic)
                ? adv_profile_pic_url($user->profile_pic)
                : ""
        ];

        $stories = $this->db->query("
            SELECT *
            FROM story
            WHERE user_id = ?
            AND is_delete = 0
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC
        ", [$list->user_id])->result();

        $storyArr = [];
        foreach ($stories as $row) {
            $storyArr[] = [
                'story_id' => (string)$row->story_id,
                'url' => adv_story_media_url($row->url),
                'type' => $row->type,
                'created_at' => date('Y-m-d\TH:i:sP', strtotime($row->created_at)),
                'location' => $row->location ?? "",
                'text' => $row->text ?? "",
                'is_like' => '0',
                'is_hightlight' => (string)($row->is_hightlight ?? "0"),
                'is_seen' => '0',
            ];
        }

        $storyList['story_image'] = $storyArr;
        $storyImageList[] = $storyList;
    }

    echo json_encode([
        'response_code' => '1',
        'message' => (!empty($my_post) || !empty($storyImageList))
            ? 'Story Found'
            : 'Story Not Found',
        'my_post' => $my_post,
        'story' => $storyImageList,
    ], JSON_UNESCAPED_SLASHES);
}




public function view_story_post()
{
    header('Content-Type: application/json');
    date_default_timezone_set('Asia/Kolkata');

    // 🔹 Authorize Token & Get user_id
    $authHeader = $this->input->get_request_header('Authorization');
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Authorization Token Missing",
            "status" => "failure"
        ]);
        return;
    }
    $token = $matches[1];
    $user_id = $this->User_model->validate_token_and_get_user($token);

    if (!$user_id) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Invalid Token",
            "status" => "failure"
        ]);
        return;
    }

    // 🔹 Read JSON Input
    $input = json_decode(file_get_contents("php://input"), true);
    $story_id = isset($input['story_id']) ? $input['story_id'] : '';

    if (!$story_id) {
        echo json_encode([
            "response_code" => "0",
            "message" => "story_id is required",
            "status" => "failure"
        ]);
        return;
    }

    // 🔹 Check if already seen
    $exists = $this->db->where('story_id', $story_id)
                       ->where('user_id', $user_id)
                       ->count_all_results('view_story');

    if ($exists > 0) {
        echo json_encode([
            "response_code" => "0",
            "message" => "User is story already seen",
            "status" => "failure"
        ]);
        return;
    }

    // 🔹 Insert record
  // 🔹 Insert record
$data = [
    'story_id'   => $story_id,
    'user_id'    => $user_id,
    'created_at' => date('Y-m-d H:i:s')  // Always use this format
];

$insert = $this->db->insert('view_story', $data);

if ($insert) {
    echo json_encode([
        "response_code" => "1",
        "message" => "Story Seen Success",
        "status" => "success"
    ]);
} else {
    echo json_encode([
        "response_code" => "0",
        "message" => "Failed to insert",
        "status" => "failure"
    ]);
}
}








public function all_userlist_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    $this->load->model('User_model');

    // 🔹 Get Authorization Token
    $authHeader = $this->input->get_request_header('Authorization');
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Authorization Token Missing",
            "status" => "failure"
        ]);
        return;
    }
    $token = $matches[1];

    // 🔹 Validate Token → Get Logged-in User ID
    $user_id = $this->User_model->validate_token_and_get_user($token);

    if (!$user_id) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Invalid or Expired Token",
            "status" => "failure"
        ]);
        return;
    }

    // 🔹 Fetch all users except logged-in user
    $this->db->where('id !=', $user_id);
    $this->db->order_by('id', 'DESC');
    $users = $this->db->get('users')->result_array();

    $result_all = [];

    if (!empty($users)) {
        foreach ($users as $user) {
            $user_list = [];

            $user_list['user_id']           = (string)($user['id'] ?? '');
            $user_list['first_name']        = (string)($user['first_name'] ?? '');
            $user_list['last_name']         = (string)($user['last_name'] ?? '');
            $user_list['email']             = (string)($user['email'] ?? '');
            $user_list['login_type']        = (string)($user['login_type'] ?? '');
            $user_list['username']          = (string)($user['username'] ?? '');
            $user_list['mobile']            = (string)($user['mobile'] ?? '');
            $user_list['email_verified_at'] = (string)($user['email_verified_at'] ?? '');
            $user_list['country_code']      = (string)($user['country_code'] ?? '');
            $user_list['device_token']      = (string)($user['device_token'] ?? '');
            $user_list['status']            = (string)($user['status'] ?? '');
            $user_list['address']           = (string)($user['address'] ?? '');
            $user_list['display_name']      = (string)($user['display_name'] ?? '');

            // 🔹 Profile Image Handling
            if (!empty($user['profile_pic'])) {
                $first_part = explode(':', $user['profile_pic'])[0];

                if ($first_part === 'http' || $first_part === 'https') {
                    $user_list['profile_pic'] = $user['profile_pic'];
                } else {
                    $user_list['profile_pic'] = base_url('assets/images/user/' . $user['profile_pic']);
                }
            } else {
                $user_list['profile_pic'] = "";
            }

            $result_all[] = $user_list;
        }

        echo json_encode([
            "response_code" => "1",
            "message"       => "Users Found",
            "tag_users"     => $result_all
            ]);
    } else {
        echo json_encode([
            "response_code" => "0",
            "message"       => "No Users Found",
            "tag_users"     => [],
            "status"        => "failure"
        ]);
    }
}




// public function story_seen_list_post()
// {
//     header("Content-Type: application/json");
//     date_default_timezone_set('Asia/Kolkata');

//     // 🔹 Get Input (POST JSON)
//     $inputJSON = file_get_contents("php://input");
//     $request = json_decode($inputJSON, true);
//     $story_id = $request['story_id'] ?? '';

//     if (empty($story_id)) {
//         echo json_encode([
//             "status" => "0",
//             "message" => "story_id required",
//             "story_seen" => []
//         ]);
//         return;
//     }

//     // 🔹 Story owner user_id
//     $story_owner = $this->db->select('user_id')
//                             ->where('story_id', $story_id)
//                             ->get('story')
//                             ->row();

//     if (!$story_owner) {
//         echo json_encode([
//             "status" => "0",
//             "message" => "Invalid story_id",
//             "story_seen" => []
//         ]);
//         return;
//     }

//     $story_owner_id = $story_owner->user_id;

//     // 🔹 Get Users who viewed story (excluding owner)
//     $views = $this->db->where('story_id', $story_id)
//                       ->where('user_id !=', $story_owner_id)
//                       ->get('view_story')
//                       ->result();

//     $all_users = [];

//     foreach ($views as $row) {
//         $user = $this->db->where('id', $row->user_id)->get('users')->row();

//         $all_users[] = [
//             'user_id'      => $row->user_id,
//             'story_id'     => $row->story_id,
//             'created_at'   => $row->created_at,
//             'username'     => $user->username ?? '',
//             'first_name'   => $user->first_name ?? '',
//             'last_name'    => $user->last_name ?? '',
//             'mobile_number'=> $user->mobile ?? '',
//             'profile_pic'  => !empty($user->profile_pic) ?
//                               adv_profile_pic_url($user->profile_pic) : ''
//         ];
//     }

//     // 🔹 Final Response
//     if (!empty($all_users)) {
//         echo json_encode([
//             "status" => "1",
//             "message" => "Story Seen User List Found",
//             "story_seen" => $all_users
//         ]);
//     } else {
//         echo json_encode([
//             "status" => "0",
//             "message" => "Story Seen User List Not Found",
//             "story_seen" => []
//         ]);
//     }
// }


public function story_seen_list_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set("Asia/Kolkata");

    // Load Models
    $this->load->model("Story_model");
    $this->load->model("User_model");

    // Receive JSON Input
    $input = json_decode(trim(file_get_contents('php://input')), true);
    $story_id = isset($input['story_id']) ? (int)$input['story_id'] : 0;

    if (!$story_id) {
        echo json_encode([
            "status" => "0",
            "message" => "story_id required",
            "story_seen" => []
        ]);
        exit;
    }

    // Get story owner
    $story_owner = $this->Story_model->get_row(["story_id" => $story_id], "user_id");
    if (!$story_owner) {
        echo json_encode([
            "status" => "0",
            "message" => "Story not found",
            "story_seen" => []
        ]);
        exit;
    }
    $story_owner_id = $story_owner->user_id;

    // Get all users who viewed the story except the owner
    $viewed_users = $this->Story_model->get_where(["story_id" => $story_id], $story_owner_id);

    if (empty($viewed_users)) {
        echo json_encode([
            "status" => "0",
            "message" => "No one except the owner has seen the story",
            "story_seen" => []
        ]);
        exit;
    }

    // Collect all user IDs
    $user_ids = array_map(function($v) { return $v->user_id; }, $viewed_users);

    // Get all user details in a single query
    $users_details = $this->User_model->get_multiple_users($user_ids);

    $all_users = [];
    foreach ($viewed_users as $vu) {
        $ud = $users_details[$vu->user_id] ?? null;
        $posts_list = [
            "user_id" => $vu->user_id,
            "story_id" => $vu->story_id,
"created_at" => $this->formatDate($vu->created_at),
            "username" => $ud->username ?? '',
            "first_name" => $ud->first_name ?? '',
            "last_name" => $ud->last_name ?? '',
            "mobile_number" => $ud->mobile,
    "profile_pic" => !empty($ud->profile_pic) 
        ? adv_profile_pic_url($ud->profile_pic) 
        : adv_profile_pic_url('') 
        ];
        $all_users[] = $posts_list;
    }

    echo json_encode([
        "status" => 1,
        "message" => "Story Seen User List Found",
        "story_seen" => $all_users
    ]);
}


public function get_user_stories_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    $this->load->model('User_model');

    // 🔹 Get Authorization Token
    $authHeader = $this->input->get_request_header('Authorization');
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "status" => false,
            "message" => "Authorization Token Missing",
            "data" => []
        ]);
        return;
    }
    $token = $matches[1];

    // 🔹 Validate Token → Get Logged-in User ID
    $login_user_id = $this->User_model->validate_token_and_get_user($token);

    if (!$login_user_id) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid or Expired Token",
            "data" => []
        ]);
        return;
    }

    // 🔹 Read Input JSON
    $inputJSON   = file_get_contents("php://input");
    $requestData = json_decode($inputJSON, true);

    $user_id = isset($requestData['user_id']) ? trim($requestData['user_id']) : '';

    if ($user_id == "") {
        echo json_encode([
            "status"        => false,
            "message"       => "user_id is required",
            "data"          => []
        ]);
        return;
    }

    // 🔹 Check User Exists
    $user = $this->db->where('id', $user_id)->get('users')->row();
    if (!$user) {
        echo json_encode([
            "status"        => false,
            "message"       => "User not found",
            "data"          => []
        ]);
        return;
    }

    // 🔹 Fetch Stories
    $stories = $this->db
        ->where('user_id', $user_id)
        ->where('is_delete', 0)
        ->order_by('created_at', 'DESC')
        ->get('story')
        ->result();

    if (empty($stories)) {
        echo json_encode([
            "status"        => false,
            "message"       => "No stories found",
            "data"          => []
        ]);
        return;
    }

    // 🔹 Profile Pic URL
    $profilePic = "";
    if (!empty($user->profile_pic)) {
        $profilePic = preg_match('/^https?:\/\//', $user->profile_pic)
            ? $user->profile_pic
            : adv_profile_pic_url($user->profile_pic);
    }

    // 🔹 Create Slides Data
    $storyImages = [];
    foreach ($stories as $story) {

        $url = "";
        if (!empty($story->url)) {
            $url = preg_match('/^https?:\/\//', $story->url)
                ? $story->url
                : adv_story_media_url($story->url);
        }

        if ($url == "") continue;

        // 🔹 Check Like
        $is_like = $this->db->where('story_id', $story->story_id)
                            ->where('user_id', $login_user_id)
                            ->count_all_results('story_like') > 0 ? '1' : '0';

        // 🔹 Check Seen Count
        $seen_count = $this->db->where('story_id', $story->story_id)
                               ->count_all_results('view_story');

        $storyImages[] = [
            "story_id"      => (string)$story->story_id,
            "url"           => $url,
            "type"          => $story->type,
            "created_at"    => date('Y-m-d H:i:s', strtotime($story->created_at)),
            "location"      => $story->location ?? "",
            "text"          => $story->text ?? "",
            "is_like"       => $is_like,
            "is_hightlight" => "0",
            "is_seen"       => (string)$seen_count,
        ];
    }

    // 🔹 Final Response Format
    $latest = $stories[0];
    $dataItem = [
        "story_id"    => (string)$latest->story_id,
        "user_id"     => (string)$user_id,
        "url"         => $storyImages[0]['url'],
        "type"        => $latest->type,
        "create_date" => date('Y-m-d H:i:s', strtotime($latest->created_at)),
        "username"    => $user->username ?? "",
        "profile_pic" => $profilePic,
        "story_image" => $storyImages,
    ];

    echo json_encode([
        "status"        => true,
        "message"       => "Success",
        "data"          => [$dataItem]
    ]);
}

 
    
    
public function check_auth_status_post()
{
    header("Content-Type: application/json");

    // Prefer CI helper (works on PHP built-in server); fall back to apache headers
    $authHeader = $this->input->get_request_header('Authorization', TRUE);
    if (!$authHeader && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
    }

    if (!$authHeader) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Authorization token missing or invalid",
            "status" => "failed",
            "account_status" => "Unauthorized"
        ]);
        return;
    }

    // Extract raw token (strip any number of "Bearer " prefixes)
    $token = trim(preg_replace('/^Bearer\s+/i', '', $authHeader));

    if ($token === '') {
        echo json_encode([
            "response_code" => "0",
            "message" => "Authorization token missing or invalid",
            "status" => "failed",
            "account_status" => "Unauthorized"
        ]);
        return;
    }

    $user = $this->db->where('token', $token)->get('users')->row();

    if (!$user) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Invalid token or user not found",
            "status" => "failed",
            "account_status" => "Unauthorized"
        ]);
        return;
    }

    $isActive = ((string)$user->status === '1');
    $account_status = $isActive ? 'Activated' : 'Deactivated';

    echo json_encode([
        "response_code" => $isActive ? "1" : "0",
        "message" => $isActive ? "Account Status Fetched" : "Your Account has been deactivated",
        "status" => $isActive ? "success" : "failed",
        "account_status" => $account_status,
        "user_id" => $user->id
    ]);
}


public function get_all_latest_reel_by_pagination_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set("Asia/Kolkata");

    /* ===============================
       READ INPUT
    ================================*/
    $input = json_decode(file_get_contents("php://input"), true);

    $per_page = isset($input['per_page']) && $input['per_page'] > 0 ? (int)$input['per_page'] : 6;
    $page_no  = isset($input['page_no']) && $input['page_no'] > 0 ? (int)$input['page_no'] : 1;
    $offset   = ($page_no - 1) * $per_page;

    /* ===============================
       AUTH TOKEN
    ================================*/
    $authHeader    = $this->input->get_request_header('Authorization', TRUE);
    $login_user_id = 0;

    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];

        $user = $this->db
            ->where('token', $token)
            ->where('token_expiry >=', date('Y-m-d H:i:s'))
            ->get('users')
            ->row();

        if ($user) {
            $login_user_id = (int)$user->id;
        }
    }

    if (!$login_user_id) {
        echo json_encode([
            "response_code" => "0",
            "message"       => "Invalid or Expired Token",
            "rescent_post"  => []
        ]);
        return;
    }

    /* ===============================
       🔥 REPORTED POSTS (GLOBAL HIDE)
       (SAME AS FIRST API)
    ================================*/
    $reported_post_ids = [];

    $reported = $this->db
        ->select('DISTINCT(post_id)')
        ->from('post_report')
        ->where('post_id IS NOT NULL', null, false)
        ->get()
        ->result_array();

    if (!empty($reported)) {
        $reported_post_ids = array_map('intval', array_column($reported, 'post_id'));
    }

    /* ===============================
       BLOCKED USERS
    ================================*/
    $blockedUserIds = $this->db
        ->select('block_user_id')
        ->from('profile_block')
        ->where('user_id', $login_user_id)
        ->get()
        ->result_array();

    $blockedUserIds = array_column($blockedUserIds, 'block_user_id');

    /* ===============================
       ACTIVE BOOSTED REELS
    ================================*/
    $now = date('Y-m-d H:i:s');

    $boosted = $this->db
        ->select('post_id')
        ->from('post_boost')
        ->where('start_date <=', $now)
        ->where('end_date >=', $now)
        ->get()
        ->result_array();

    $boosted_ids = array_column($boosted, 'post_id');
    $total_boost = count($boosted_ids);

    /* ===============================
       NORMAL REELS
    ================================*/
    $this->db->select('posts.*, users.username, users.first_name, users.profile_pic, users.mobile');
    $this->db->from('posts');
    $this->db->join('users', 'users.id = posts.user_id', 'left');
    $this->db->where('posts.post_type', 'reel');
    $this->db->where('posts.status', 1);
    $this->db->where('posts.is_delete', 0);

    if (!empty($blockedUserIds)) {
        $this->db->where_not_in('posts.user_id', $blockedUserIds);
    }

    if (!empty($boosted_ids)) {
        $this->db->where_not_in('posts.post_id', $boosted_ids);
    }

    if (!empty($reported_post_ids)) {
        $this->db->where_not_in('posts.post_id', $reported_post_ids);
    }

    $this->db->order_by('posts.created_at', 'DESC');
    $this->db->limit($per_page, $offset);
    $normal_reels = $this->db->get()->result();

    /* ===============================
       FINAL FEED
    ================================*/
    $finalPosts   = [];
    $normal_count = 0;
    $boost_index  = ($page_no - 1) * floor($per_page / 4);

    foreach ($normal_reels as $post) {

        $finalPosts[] = $this->_format_reel($post, $login_user_id, "0");
        $normal_count++;

        if ($normal_count % 4 == 0 && $total_boost > 0) {

            $boost_post_id = $boosted_ids[$boost_index % $total_boost];

            // 🔥 SAME GLOBAL REPORT CHECK
            if (!empty($reported_post_ids) && in_array($boost_post_id, $reported_post_ids)) {
                $boost_index++;
                continue;
            }

            $boost_post = $this->db
                ->select('posts.*, users.username, users.first_name, users.profile_pic, users.mobile')
                ->from('posts')
                ->join('users', 'users.id = posts.user_id', 'left')
                ->where('posts.post_id', $boost_post_id)
                ->where('posts.post_type', 'reel')
                ->where('posts.status', 1)
                ->where('posts.is_delete', 0)
                ->get()
                ->row();

            if ($boost_post) {
                $finalPosts[] = $this->_format_reel($boost_post, $login_user_id, "1");
                $boost_index++;
            }
        }
    }

    echo json_encode([
        "response_code" => !empty($finalPosts) ? "1" : "0",
        "message"       => !empty($finalPosts) ? "Reels Found" : "No Reels Found",
        "page_no"       => $page_no,
        "per_page"      => $per_page,
        "rescent_post"  => $finalPosts
    ], JSON_UNESCAPED_SLASHES);
}

private function _format_reel($post, $login_user_id, $isBoostPost = "0")
{
    $post_id = $post->post_id;

    /* ===============================
       VIDEOS
    ================================*/
    $videos = $this->db->where('post_id', $post_id)->get('post_video')->result_array();
    $post_videos = [];

    foreach ($videos as $vid) {
        $post_videos[] = [
            "url" => adv_media_url($vid['post_video']),
            "type" => "video",
            "post_video_thumbnail" => adv_media_url($vid['post_video_thumbnail'])
        ];
    }

    /* ===============================
       TOTAL LIKES & COMMENTS
    ================================*/
    $total_likes    = $this->db->where('reel_id', $post_id)->count_all_results('reel_like');
    $total_comments = $this->db->where('reel_id', $post_id)->count_all_results('reel_comment');

    /* ===============================
       IS LIKED (POST + REEL)
    ================================*/
    $is_liked = "0";

    if (!empty($login_user_id)) {

        $liked_post = $this->db
            ->where('post_id', $post_id)
            ->where('user_id', $login_user_id)
            ->count_all_results('post_like');

        $liked_reel = $this->db
            ->where('reel_id', $post_id)
            ->where('user_id', $login_user_id)
            ->count_all_results('reel_like');

        if ($liked_post > 0 || $liked_reel > 0) {
            $is_liked = "1";
        }
    }

    /* ===============================
       IS BOOKMARK
    ================================*/
    $is_bookmark = "0";

    if (!empty($login_user_id)) {
        $is_bookmark = $this->db
            ->where('post_id', $post_id)
            ->where('user_id', $login_user_id)
            ->count_all_results('bookmark_post') > 0 ? "1" : "0";
    }

    return [
        "post_id"        => (int)$post->post_id,
        "user_id"        => (int)$post->user_id,
        "text"           => $post->text,
        "post_type"      => "reel",
        "created_at"     => $post->created_at,
        "post_videos"    => $post_videos,
        "username"       => $post->username ?? "",
        "first_name"     => $post->first_name ?? "",
        "profile_pic"    => !empty($post->profile_pic)
            ? adv_profile_pic_url($post->profile_pic)
            : adv_profile_pic_url(''),
        "mobile"         => $post->mobile ?? "",
        "is_liked"       => $is_liked,
        "is_bookmark"    => $is_bookmark,
        "total_likes"    => (int)$total_likes,
        "total_comments" => (int)$total_comments,
        "isBoostPost"    => $isBoostPost
    ];
}






public function online_user_list_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set("Asia/Kolkata");

    $this->load->model('User_model');

    // 🔹 Get Authorization Token
    $authHeader = $this->input->get_request_header('Authorization');
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Authorization Token Missing",
            "online_user" => []
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return;
    }

    $token = $matches[1];

    // 🔹 Validate Token → Get User ID
    $login_user_id = $this->User_model->validate_token_and_get_user($token);

    if (!$login_user_id) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Invalid or Expired Token",
            "online_user" => []
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return;
    }

    // 🔹 Fetch all online users except the logged-in user (optional)
    $this->db->select('id, username');
    $this->db->from('users');
    $this->db->where('is_online', 1);
    $this->db->where('id !=', $login_user_id); // exclude self
    $query = $this->db->get();
    $all_user_raw = $query->result_array();

    // 🔹 Cast user_id to integer
    $all_user = [];
    foreach ($all_user_raw as $row) {
        $all_user[] = [
            'user_id' => (int) $row['id'],
            'username' => $row['username']
        ];
    }

    if (!empty($all_user)) {
        echo json_encode([
            "response_code" => "1",
            "message" => "Online User List Found",
            "online_user" => $all_user
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            "response_code" => "0",
            "message" => "Online User List Not Found",
            "online_user" => []
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}



public function get_hashtags_list_post()
{
    header('Content-Type: application/json');
    date_default_timezone_set('Asia/Kolkata');

    $this->load->model('User_model');

    // Fetch data
    $hashtags = $this->User_model->get_distinct_hashtags();

    // Convert to simple array
    $finalHashtags = array_map(function($row) {
        return $row->text;  // <-- correct your column name here
    }, $hashtags);

    echo json_encode([
        'response_code' => '1',
        'status' => 'success',
        'hashtags' => $finalHashtags
    ], JSON_UNESCAPED_UNICODE);
}

public function all_my_reel_pagination_post()
{
    header('Content-Type: application/json; charset=utf-8');
    date_default_timezone_set("Asia/Kolkata");

    $this->load->model('User_model');

    // Read JSON input
    $json = file_get_contents('php://input');
    $input = json_decode($json, true);

    // Inputs from JSON
    $user_id  = $input['user_id'] ?? "";
    $perPage  = $input['per_page'] ?? 10;
    $page_no  = $input['page_no'] ?? 1;
    $offset   = ($page_no - 1) * $perPage;

    // Token Validate
    $authHeader = $this->input->get_request_header('Authorization');
    $login_user_id = 0;

    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        $login_user_id = $this->User_model->validate_token_and_get_user($token);
    }

    // user_id required
    if (empty($user_id)) {
        echo json_encode([
            "status"  => "false",
            "message" => "user_id is required"
        ]);
        return;
    }

    // COUNT TOTAL RECORDS
    $this->db->from("posts");
    $this->db->where("post_type", "reel");
    $this->db->where("status", 1);
    $this->db->where("is_delete", 0);
    $this->db->where("user_id", $user_id);

    $total_rows = $this->db->count_all_results();
    $last_page  = ceil($total_rows / $perPage);

    // FETCH DATA
    $this->db->select("*");
    $this->db->from("posts");
    $this->db->where("post_type", "reel");
    $this->db->where("status", 1);
    $this->db->where("is_delete", 0);
    $this->db->where("user_id", $user_id);
    $this->db->order_by("post_id", "DESC");
    $this->db->limit($perPage, $offset);
    $posts = $this->db->get()->result_array();

    $result = [];

    foreach ($posts as $row) {

        $post_id      = $row['post_id'];
        $post_user_id = $row['user_id'];

        // Get user data
        $user = $this->User_model->get_user($post_user_id);

        $username = $user->username ?? "";
        $profile_image = (!empty($user->profile_pic))
            ? adv_profile_pic_url($user->profile_pic)
            : "";

        // Checks
        $isLiked = $this->db->where(["reel_id" => $post_id, "user_id" => $login_user_id])
                            ->count_all_results("reel_like") ? "1" : "0";

        $isBookmarked = $this->db->where(["post_id" => $post_id, "user_id" => $login_user_id])
                                 ->count_all_results("bookmark_post") ? "1" : "0";

        // Counts (converted to string)
        $totalLike    = strval($this->db->where("reel_id", $post_id)->count_all_results("reel_like"));
        $totalComment = strval($this->db->where("reel_id", $post_id)->count_all_results("reel_comment"));
        $totalView    = strval($this->db->where("reel_id", $post_id)->count_all_results("view_reel"));

        // total_share fix (string)
        $totalShare = isset($row['total_share']) && $row['total_share'] !== ""
            ? strval($row['total_share'])
            : "0";

        // Videos
        $videos = $this->db->where("post_id", $post_id)->get("post_video")->result();

        $videoArray = [];
        foreach ($videos as $img) {
            $videoArray[] = [
                "reel_image_id"        => intval($img->id),
                "reel_video"           => adv_media_url($img->post_video),
                "type"                 => "video",
                "reel_video_thumbnail" => $img->post_video_thumbnail
                    ? adv_media_url($img->post_video_thumbnail)
                    : ""
            ];
        }

        // Push result
        $result[] = [
            "reel_id"        => strval($post_id),
            "user_id"        => strval($post_user_id),
            "title"          => $row['title'] ?? "",
            "description"    => $row['description'] ?? "",
            "location"       => $row['location'] ?? "",

            "created_at" => !empty($row['created_at'])
                ? date("Y-m-d\TH:i:s.000000\Z", strtotime($row['created_at']))
                : "",

            "is_likes"       => strval($isLiked),
            "bookmark"       => strval($isBookmarked),

            // 🔥 ALL FIXED AS STRING
"total_likes"    => intval($totalLike),
"total_comments" => intval($totalComment),

            "total_share"    => $totalShare,
            "total_view"     => intval($totalView),

            "profile_image"  => $profile_image,
            "username"       => $username,
            "video"          => $videoArray,
            "comment"        => []
        ];
    }

    // Response
    echo json_encode([
        "status"        => "success",
        "message"       => "My Reel List",
        "current_page"  => strval($page_no),
        "last_page"     => strval($last_page),
        "reels"         => $result
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}






public function search_username_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set("Asia/Kolkata");

    $this->load->model("User_model");

    // Authorization
    $authHeader = $this->input->get_request_header("Authorization");
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => "0",
            "message"       => "Unauthorized",
            "users"         => [],
            "status"        => "failure"
        ]);
        return;
    }

    $token   = $matches[1];
    $user_id = $this->User_model->validate_token_and_get_user($token);

    if (!$user_id) {
        echo json_encode([
            "response_code" => "0",
            "message"       => "Invalid Token",
            "users"         => [],
            "status"        => "failure"
        ]);
        return;
    }

    // Raw input
    $input = json_decode(file_get_contents("php://input"), true);
    $text  = !empty($input["text"]) ? trim($input["text"]) : "";

    if (empty($text)) {
        echo json_encode([
            "response_code" => "0",
            "message"       => "Users Not Found",
            "users"         => [],
            "status"        => "failure"
        ]);
        return;
    }

    // Blocked Users
    $blockedUserIds = $this->User_model->get_my_blocked_users($user_id);

    /* ================= USERS SEARCH (AS IT IS) ================= */
    $this->db->select("*");
    $this->db->from("users");
    $this->db->like("username", $text, "after");

    if (!empty($blockedUserIds)) {
        $this->db->where_not_in("id", $blockedUserIds);
    }

    $this->db->order_by("id", "DESC");
    $users = $this->db->get()->result();

    $finalUsers = [];

    foreach ($users as $u) {

        if (!empty($u->profile_pic)) {
            $url = explode(":", $u->profile_pic);
            $profile_pic = adv_profile_pic_url($u->profile_pic);
        } else {
            $profile_pic = adv_profile_pic_url('');
        }

        $isFollow = $this->User_model->is_followings($user_id, $u->id) ? "1" : "0";

        $created_at = !empty($u->created_at)
            ? date("Y-m-d\TH:i:s.000000\Z", strtotime($u->created_at))
            : "1970-01-01T00:00:00.000000Z";

        $updated_at = !empty($u->updated_at)
            ? date("Y-m-d\TH:i:s.000000\Z", strtotime($u->updated_at))
            : "1970-01-01T00:00:00.000000Z";

        $finalUsers[] = [
            "id"                => (string)$u->id,
            "first_name"        => (string)$u->first_name,
            "last_name"         => (string)$u->last_name,
            "email"             => (string)$u->email,
            "login_type"        => (string)$u->login_type,
            "username"          => (string)$u->username,
            "email_verified_at" => $u->email_verified_at,
            "password"          => null,
            "main_password"     => null,
            "mobile"            => (string)$u->mobile,
            "country_code"      => (string)$u->country_code,
            "device_token"      => (string)$u->device_token,
            "otp"               => (int)$u->otp,
            "is_otp_verified"   => (int)$u->is_otp_verified,
            "dob"               => (string)$u->dob,
            "gender"            => (string)$u->gender,
            "profile_pic"       => $profile_pic,
            "avtar_id"          => (int)$u->avtar_id,
            "status"            => (int)$u->status,
            "role"              => (string)$u->role,
            "display_name"      => (string)$u->display_name,
            "bio"               => (string)$u->bio,
            "country"           => (string)$u->country,
            "address"           => (string)$u->address,
            "is_private"        => (int)$u->is_private,
            "is_online"         => (int)$u->is_online,
            "is_admin"          => (int)$u->is_admin,
            "soket_id"          => (string)$u->soket_id,
            "platform_type"     => (string)$u->platform_type,
            "created_at"        => $created_at,
            "updated_at"        => $updated_at,
            "is_follow"         => $isFollow
        ];
    }
/* ================= HASHTAG SEARCH ================= */

$isOnlyHash = ($text === "#");

$this->db->select("id, text");
$this->db->from("hash_tag");

if (!$isOnlyHash) {
    // "#" ke alawa kuch ho to LIKE lagao
    $this->db->like("text", ltrim($text, "#"), "after");
}

$this->db->order_by("id", "DESC");
$hashtags = $this->db->get()->result();

foreach ($hashtags as $h) {

    $tagText   = ltrim($h->text, "#");
    $searchTag = "#" . $tagText;

    /* ===== POSTS FOR HASHTAG ===== */
    $posts = $this->db
        ->select("
            p.post_id,
            p.user_id,
            p.text,
            p.post_type,
            p.created_at,
            u.username,
            u.profile_pic
        ")
        ->from("posts p")
        ->join("users u", "u.id = p.user_id", "left")
        ->like("p.text", $searchTag)
        ->order_by("p.created_at", "DESC")
        ->limit(10)
        ->get()
        ->result();

    $postData = [];

    foreach ($posts as $p) {

        $media = [];
        $images = $this->db
            ->select("new_post")
            ->from("post_image")
            ->where("post_id", $p->post_id)
            ->get()
            ->result();

        foreach ($images as $img) {
            $media[] = [
                "url" => base_url($img->new_post),
                "postVideoThumbnail" => ""
            ];
        }

        $postData[] = [
            "post_id"      => (string)$p->post_id,
            "user_id"      => (string)$p->user_id,
            "username"     => (string)$p->username,
            "profile_pic"  => !empty($p->profile_pic)
                ? adv_profile_pic_url($p->profile_pic)
                : adv_profile_pic_url(''),
            "post_type"    => (string)$p->post_type,
            "text"         => (string)$p->text,
            "post_image"   => $media,
            "totalLike"    => "0",
            "totalComment" => "0",
            "totalShare"   => "0",
            "isLiked"      => "0",
            "isBookmark"   => "0",
            "createdAt"    => date("Y-m-d\TH:i:s\Z", strtotime($p->created_at))
        ];
    }

    $finalUsers[] = [
        "id"             => (string)$h->id,
        "login_type"     => "hashtag",
        "username"       => "#" . $tagText,
        "display_name"   => "#" . $tagText,
        "profile_pic"    => adv_profile_pic_url(''),
        "role"           => "hashtag",
        "recent_content" => $postData
    ];
}


    /* ================= RESPONSE ================= */
    echo json_encode([
        "response_code" => !empty($finalUsers) ? "1" : "0",
        "message" => !empty($finalUsers) ? "Users Found" : "Users Not Found",
        "users" => $finalUsers
    ]);
}



public function search_username_add_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set("Asia/Kolkata");

    $this->load->model("User_model");

    // Read Token
    $authHeader = $this->input->get_request_header('Authorization');

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => "0",
            "message"       => "Unauthorized",
            "status"        => "failure"
        ]);
        return;
    }

    $token = $matches[1];
    $user_id = $this->User_model->validate_token_and_get_user($token);

    if (!$user_id) {
        echo json_encode([
            "response_code" => "0",
            "message"       => "Invalid Token",
            "status"        => "failure"
        ]);
        return;
    }

    // -----------------------------
    // FIX: JSON + FORM BOTH ACCEPT
    // -----------------------------
    $inputJSON = file_get_contents("php://input");
    $input = json_decode($inputJSON, true);

    if (!empty($input['username'])) {
        $username = trim($input['username']);   // JSON username
    } else {
        $username = trim($this->input->post("username")); // form-data username
    }

    // Validation
    if (empty($username)) {
        echo json_encode([
            "response_code" => "0",
            "message"       => "Username required",
            "status"        => "failure"
        ]);
        return;
    }

    // Search user
    $search_user = $this->db->where("username", $username)->get("users")->row();

    $insert_data = [
        "username" => $username,
        "user_id"  => $user_id,
        "created_at" => date('Y-m-d H:i:s') // Asia/Kolkata time

    ];

    if (!empty($search_user)) {
        $insert_data["search_user_id"] = $search_user->id;
    }

    $this->db->insert("search_username", $insert_data);

    echo json_encode([
        "response_code" => "1",
        "message"       => "Username Added Successfully Done",
        "status"        => "success"
    ]);
}


public function get_all_reels_datainshow_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set("Asia/Kolkata");

    $this->load->model("User_model");
    $this->load->model("Reel_model");

    /* ================= INPUT ================= */
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) $input = $_POST;

    $user_id = $input["user_id"] ?? null;
    $page    = (int)($input["page_no"] ?? 1);
    $limit   = (int)($input["per_page"] ?? 10);
    $offset  = ($page - 1) * $limit;

    // ✅ NEW (SAFE) — OPTIONAL HASHTAG
    $hashtag = $input["hashtag"] ?? null;

    /* ================= BLOCKED USERS ================= */
    $blockedUserIds = $this->Reel_model->get_blocked_user_ids($user_id);

    /* ================= POSTS ================= */
    // ✅ SAME FUNCTION + OPTIONAL HASHTAG (NO BREAK)
    $posts = $this->Reel_model->get_posts($blockedUserIds, $limit, $offset, $hashtag);

    $final_posts = [];

    foreach ($posts as $post) {

        $images = $this->Reel_model->get_post_images($post->post_id);
        $videos = $this->Reel_model->get_post_videos($post->post_id);
        $user   = $this->User_model->get_user($post->user_id);
        if (!$user) continue;

        /* ================= PROFILE PIC ================= */
        $profile_pic = !empty($user->profile_pic)
            ? adv_profile_pic_url($user->profile_pic)
            : adv_profile_pic_url('');

        $media = [];
        $post_type = "";

        /* ================= VIDEO ================= */
        if (!empty($videos)) {
            foreach ($videos as $vid) {
                if (empty($vid->post_video)) continue;

                $media[] = [
                    "post_image_id" => (string)$vid->id,
                    "url" => adv_media_url($vid->post_video),
                    "type" => "video",
                    "post_video_thumbnail" => !empty($vid->post_video_thumbnail)
                        ? adv_media_url($vid->post_video_thumbnail)
                        : ""
                ];
            }
            $post_type = "video";
        }

        /* ================= IMAGE ================= */
        elseif (!empty($images)) {
            foreach ($images as $img) {
                if (empty($img->new_post)) continue;

                $media[] = [
                    "post_image_id" => (string)$img->id,
                    "url" => adv_media_url($img->new_post),
                    "type" => "image"
                ];
            }
            $post_type = "image";
        }

        if (empty($media)) continue;

        /* ================= TAG USER LIST ================= */
        $tagUsers = $this->db->select("u.id, u.first_name, u.last_name, u.profile_pic")
            ->from("post_user_tags t")
            ->join("users u", "u.id = t.tag_users")
            ->where("t.post_id", $post->post_id)
            ->where("t.tag_users !=", $user_id)
            ->get()
            ->result();

        $tag_list = [];
        foreach ($tagUsers as $u) {
            $tag_list[] = [
                "tag_user_id" => (int)$u->id,
                "first_name"  => $u->first_name ?? "",
                "last_name"   => $u->last_name ?? "",
                "profile_pic"=> !empty($u->profile_pic)
                    ? adv_profile_pic_url($u->profile_pic)
                    : adv_profile_pic_url('')
            ];
        }

        /* ================= FINAL POST ================= */
        $final_posts[] = [
            "post_id" => (string)$post->post_id,
            "user_id" => (string)$post->user_id,
            "text" => $post->text ?? "",
            "location" => $post->location ?? "",
            "post_type" => $post_type,
            "created_at" => $this->formatDate($post->created_at),
            "updated_at" => $this->formatDate($post->updated_at),
            "post_image" => $media,
            "username" => $user->username ?? "",
            "profile_pic" => $profile_pic,
            "is_follow" => $this->Reel_model->is_follow($user_id, $post->user_id) ? "1" : "0",
            "is_liked" => $this->Reel_model->is_liked($post->post_id, $user_id) ? "1" : "0",
            "total_like" => (string)$this->Reel_model->count_like($post->post_id),
            "total_comment" => (string)$this->Reel_model->count_comment($post->post_id),
            "is_bookmark" => $this->Reel_model->is_bookmarked($post->post_id, $user_id) ? "1" : "0",
            "tag_user_list" => $tag_list
        ];
    }

    echo json_encode([
        "response_code" => !empty($final_posts) ? "1" : "0",
        "message" => !empty($final_posts) ? "Content Found" : "Content Not Found",
        "recent_content" => $final_posts,
        "current_page" => $page,
        "status" => !empty($final_posts) ? "success" : "failure"
    ]);
}

private function formatDate($date) { 
    if (!$date || $date == "0000-00-00 00:00:00") return null; 
    return date("Y-m-d\TH:i:s.000000\Z", strtotime($date));
    }




public function all_my_tag_post_pagination_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set("Asia/Kolkata");

    /* ============================
       AUTH USER
    ============================ */
    $token = $this->_get_bearer_token();

    if ($token === '') {
        echo json_encode([
            "status" => false,
            "message" => "Authorization Token Required"
        ]);
        return;
    }

    $user = $this->db->where("token", $token)->get("users")->row();
    if (!$user) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid Token"
        ]);
        return;
    }

    $user_id = (int)$user->id;

    /* ============================
       PAGINATION
    ============================ */
    $per_page = $this->input->post("per_page") ? (int)$this->input->post("per_page") : 10;
    $page_no  = $this->input->post("page_no") ? (int)$this->input->post("page_no") : 1;
    $offset  = ($page_no - 1) * $per_page;

    /* ============================
       TOTAL COUNT
    ============================ */
    $total_posts = $this->db
        ->from("post_user_tags")
        ->join("posts", "posts.post_id = post_user_tags.post_id")
        ->where("post_user_tags.tag_users", $user_id)
        ->where("posts.is_delete", 0)
        ->where("posts.status", 1)
        ->count_all_results();

    $last_page = $total_posts > 0 ? ceil($total_posts / $per_page) : 0;

    /* ============================
       FETCH POSTS
    ============================ */
    $posts = $this->db
        ->select("posts.*")
        ->from("post_user_tags")
        ->join("posts", "posts.post_id = post_user_tags.post_id")
        ->where("post_user_tags.tag_users", $user_id)
        ->where("posts.is_delete", 0)
        ->where("posts.status", 1)
        ->order_by("posts.post_id", "DESC")
        ->limit($per_page, $offset)
        ->get()
        ->result();

    $final = [];

    foreach ($posts as $p) {

        $post_id = (int)$p->post_id;

        /* ============================
           LIKE / COMMENT / BOOKMARK
        ============================ */
        $is_likes = $this->db->where([
            "post_id" => $post_id,
            "user_id" => $user_id
        ])->count_all_results("post_like") > 0;

        $bookmark = $this->db->where([
            "post_id" => $post_id,
            "user_id" => $user_id
        ])->count_all_results("bookmark_post") > 0;

        $total_like    = $this->db->where("post_id", $post_id)->count_all_results("post_like");
        $total_comment = $this->db->where("post_id", $post_id)->count_all_results("post_comment");

        /* ============================
           POST OWNER
        ============================ */
        $post_user = $this->db->where("id", $p->user_id)->get("users")->row();

        $profile_image = !empty($post_user->profile_pic)
            ? adv_profile_pic_url($post_user->profile_pic)
            : adv_profile_pic_url('');

        /* ============================
           FOLLOW / BLOCK
        ============================ */
        $is_follow = $this->db->where([
            "from_user" => $user_id,
            "to_user"   => $p->user_id
        ])->count_all_results("follow") > 0;

        $is_blocked = $this->db->where([
            "user_id"       => $p->user_id,
            "block_user_id" => $user_id
        ])->count_all_results("profile_block") > 0;

        /* ============================
           MEDIA (IMAGE + VIDEO)
        ============================ */
        $media = [];

        // IMAGES
        $imgQuery = $this->db->where("post_id", $post_id)->get("post_image")->result();
        foreach ($imgQuery as $img) {
            $media[] = [
                "post_image_id"        => (int)$img->id,
                "url"                  => !empty($img->new_post) ? adv_media_url($img->new_post) : "",
                "type"                 => "image",
                "post_video_thumbnail" => ""
            ];
        }

        // VIDEOS
        $vidQuery = $this->db->where("post_id", $post_id)->get("post_video")->result();
        foreach ($vidQuery as $vid) {
            $media[] = [
                "post_image_id"        => (int)$vid->id,
                "url"                  => !empty($vid->post_video) ? adv_media_url($vid->post_video) : "",
                "type"                 => "video",
                "post_video_thumbnail" => !empty($vid->post_video_thumbnail)
                    ? adv_media_url($vid->post_video_thumbnail)
                    : ""
            ];
        }

        /* ============================
           TAG USERS
        ============================ */
        $tagUsers = $this->db
            ->select("u.id, u.first_name, u.last_name, u.profile_pic")
            ->from("post_user_tags t")
            ->join("users u", "u.id = t.tag_users")
            ->where("t.post_id", $post_id)
            ->get()
            ->result();

        $tag_list = [];
        foreach ($tagUsers as $u) {
            $tag_list[] = [
                "tag_user_id" => (int)$u->id,
                "first_name"  => $u->first_name ?? "",
                "last_name"   => $u->last_name ?? "",
                "profile_pic" => !empty($u->profile_pic)
                    ? adv_profile_pic_url($u->profile_pic)
                    : adv_profile_pic_url('')
            ];
        }

        /* ============================
           FINAL POST
        ============================ */
        $final[] = [
            "post_id"        => (string)$p->post_id,
            "user_id"        => (string)$p->user_id,
            "text"           => $p->text ?? "",
            "type"           => $p->post_type ?? "",
            "location"       => $p->location ?? "",
            "created_at"     => $p->created_at,
            "is_likes"       => $is_likes ? "1" : "0",
            "total_likes"    => (string)$total_like,
            "total_comments" => (string)$total_comment,
            "bookmark"       => $bookmark ? "1" : "0",
            "total_view"     => "0",
            "total_share"    => "0",
            "comment"        => [],
            "profile_image"  => $profile_image,
            "username"       => $post_user->username ?? "",
            "is_follow"      => $is_follow ? "1" : "0",
            "is_blocked"     => $is_blocked ? "1" : "0",
            "isBoostPost"    => !empty($p->boost_status) ? "1" : "0",
            "tag_user_list"  => $tag_list,
            "image"          => $media
        ];
    }

    echo json_encode([
        "response_code" => "0",
        "status"        => "success",
        "message"       => "My Tagged Post List",
        "current_page"  => $page_no,
        "last_page"     => $last_page,
        "post"          => $final
    ]);
}





public function search_userlist_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set("Asia/Kolkata");

    $this->load->model("User_model");

    // 🔹 Read Authorization token
    $authHeader = $this->input->get_request_header('Authorization');
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => "1",
            "message" => "Unauthorized",
            "search_user_list" => [],
            "status" => "error"
        ]);
        return;
    }

$token = $matches[1];
$user_id = $this->User_model->validate_token_and_get_user($token);

if (!$user_id) {
    echo json_encode([
        "response_code" => "0",
        "message"       => "Unauthorized",
        "follower"      => [],
        "status"        => "error"
    ]);
    return;
}

    // 🔹 Get recent searched users
    $search_user_list = $this->User_model->get_search_list_with_user($user_id);

    $all_done = [];
    $unique_keys = [];

    foreach ($search_user_list as $item) {
        $unique_key = strtolower(trim($item->username)) . '|' . strtolower(trim($item->first_name ?? ""));
        if (in_array($unique_key, $unique_keys)) continue;

        $all_done[] = [
            "search_id" => (int)$item->search_id,
            "search_user_id" => (int)$item->search_user_id,
            "search_text" => (string)$item->username,
            "first_name" => (string)($item->first_name ?? ""),
            "last_name" => (string)($item->last_name ?? ""),
            "profile_pic" => adv_profile_pic_url($item->profile_pic ?? ""),
        ];

        $unique_keys[] = $unique_key;
    }

    // 🔹 Prepare final response
    if (!empty($all_done)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Recent search list",
            "search_user_list" => $all_done
            ]);
    } else {
        // Empty state
        echo json_encode([
            "response_code" => "0",
            "message" => "No recent searches found",
            "search_user_list" => [],
            "status" => "success"
        ]);
    }
}


public function my_following_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set("Asia/Kolkata");

    $this->load->model('User_model');

    // -------------------------
    // Get user_id from token
    // -------------------------
    $authHeader = $this->input->get_request_header('Authorization');
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => "0",
            "message"       => "Unauthorized",
            "follower"      => [],
            "status"        => "failure"
        ]);
        return;
    }

$token = $matches[1];
$user_id = $this->User_model->validate_token_and_get_user($token);

if (!$user_id) {
    echo json_encode([
        "response_code" => "0",
        "message"       => "Unauthorized",
        "follower"      => [],
        "status"        => "error"
    ]);
    return;
}

    // -------------------------
    // Get following list
    // -------------------------
    $this->db->select('follow_id, follow.from_user, follow.to_user, follow.friend_type, follow.date, follow.status,
                       users.username, users.first_name, users.last_name, users.profile_pic, users.id as follow_user_id');
    $this->db->from('follow');
    $this->db->join('users', 'users.id = follow.to_user', 'left');
    $this->db->where('follow.from_user', $user_id);
    $query = $this->db->get();

    $followers = $query->result();
    $result = [];

    foreach ($followers as $user) {

        $follow_data = [
            "follow_id"      => (string)$user->follow_id,
            "from_user"      => (string)$user->from_user,
            "to_user"        => (string)$user->to_user,
            "friend_type"    => (string)$user->friend_type,
            "date"           => (string)$user->date,
            "status"         => (string)$user->status,
            "first_name"     => (string)($user->first_name ?? ""),
            "last_name"      => (string)($user->last_name ?? ""),
            "username"       => (string)($user->username ?? ""),
            "follow_user_id" => (string)$user->follow_user_id
        ];

        // Profile Pic
        if (!empty($user->profile_pic)) {
            $follow_data["profile_pic"] = adv_profile_pic_url($user->profile_pic);
        } else {
            $follow_data["profile_pic"] = adv_profile_pic_url('');
        }

        // Is Follow Back?
        $this->db->where('to_user', $user->from_user);
        $this->db->where('from_user', $user->to_user);
        $check_follow = $this->db->get('follow')->row();

        $follow_data["is_follow"] = $check_follow ? "1" : "0";

        $result[] = $follow_data;
    }

    // -------------------------
    // Response
    // -------------------------
    if (empty($result)) {
        echo json_encode([
            "response_code" => "1",
            "message"       => "No following users found",
            "follower"      => [],
            "status"        => "success"
        ]);
        return;
    }

    echo json_encode([
        "response_code" => "1",
        "message"       => "My Following List",
        "follower"      => $result
        ]);
}


public function my_followers_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set("Asia/Kolkata");

    $this->load->model('User_model');

    // 🔐 Token
    $authHeader = $this->input->get_request_header('Authorization');
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Unauthorized",
            "follower" => [],
            "status" => "error"
        ]);
        return;
    }

    $token   = $matches[1];
    $user_id = $this->User_model->validate_token_and_get_user($token);

    if (!$user_id) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Invalid Token",
            "follower" => [],
            "status" => "error"
        ]);
        return;
    }

    // 🔥 FOLLOWERS QUERY (IMPORTANT FIX)
    $this->db->select('follow.follow_id, follow.from_user, follow.to_user, follow.friend_type, follow.date, follow.status,
                       users.username, users.first_name, users.last_name, users.profile_pic, users.id as follow_user_id');
    $this->db->from('follow');
    $this->db->join('users', 'users.id = follow.from_user', 'left');
    $this->db->where('follow.to_user', $user_id);
    $query = $this->db->get();

    $followers = $query->result();
    $result = [];

    foreach ($followers as $user) {

        // 🔁 Follow back check
        $this->db->where('from_user', $user_id);
        $this->db->where('to_user', $user->from_user);
        $check_follow = $this->db->get('follow')->row();

        $result[] = [
            "follow_id"      => (string)$user->follow_id,
            "from_user"      => (string)$user->from_user,
            "to_user"        => (string)$user->to_user,
            "friend_type"    => (string)$user->friend_type,
            "date"           => (string)$user->date,
            "status"         => (string)$user->status,
            "first_name"     => $user->first_name ?? "",
            "last_name"      => $user->last_name ?? "",
            "username"       => $user->username ?? "",
            "follow_user_id" => (string)$user->follow_user_id,
            "profile_pic"    => !empty($user->profile_pic)
                ? (adv_profile_pic_url($user->profile_pic))
                : "",
            "is_follow" => $check_follow ? "1" : "0"
        ];
    }

    echo json_encode([
        "response_code" => "1",
        "message" => "My Followers List",
        "follower" => $result,
        "status" => "success"
    ]);
}




public function follow_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set("Asia/Kolkata");

    $this->load->model("User_model");

    // ===========================
    // GET TOKEN (Bearer Token)
    // ===========================
    $authHeader = $this->input->get_request_header('Authorization');
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode(["response_code" => "0", "message" => "Unauthorized"]);
        return;
    }

    $token = $matches[1];
    $user_id = $this->User_model->validate_token_and_get_user($token);

    if (!$user_id) {
        echo json_encode(["response_code" => "0", "message" => "Invalid Token"]);
        return;
    }

    // ===========================
    // READ JSON INPUT
    // ===========================
    $json = json_decode(file_get_contents("php://input"), true);
    $to_user = isset($json["to_user"]) ? $json["to_user"] : "";

    if (empty($to_user)) {
        echo json_encode([
            "response_code" => "1",
            "message"       => "Enter data",
            "status"        => "failure"
        ]);
        return;
    }

    if ($user_id == $to_user) {
        echo json_encode([
            "response_code" => "1",
            "message"       => "You cannot follow yourself",
            "is_follow"     => "0",
            "status"        => "failure"
        ]);
        return;
    }

    // ===========================
    // CHECK IF REQUEST ALREADY SENT
    // ===========================
    if ($this->User_model->check_request($user_id, $to_user)) {

        $this->User_model->delete_request($user_id, $to_user);

        echo json_encode([
            "response_code" => "1",
            "message"       => "Follow",
            "is_follow"     => "1",
            "status"        => "success"
        ]);
        return;
    }

    // ===========================
    // CHECK IF ALREADY FOLLOWING
    // ===========================
    if ($this->User_model->check_follow($user_id, $to_user)) {

        $this->User_model->unfollow($user_id, $to_user);

        echo json_encode([
            "response_code" => "1",
            "message"       => "unfollow",
            "is_follow"     => "0",
            "status"        => "success"
        ]);
        return;
    }

    // ===========================
    // GET USER DATA
    // ===========================
    $target_user = $this->User_model->get_users($to_user);

    if (!$target_user) {
        echo json_encode([
            "response_code" => "0",
            "message"       => "User not found"
        ]);
        return;
    }

    // ===========================
    // PRIVATE ACCOUNT
    // ===========================
    if ($target_user->is_private == "1") {

        $data = [
            "from_user" => $user_id,
            "to_user"   => $to_user,
            "date"      => date("Y-m-d H:i:s"),
            "status"    => "Pending"
        ];

        $this->User_model->follow($data);
        $this->User_model->add_request($data);

        echo json_encode([
            "response_code" => "1",
            "message"       => "Requested",
            "status"        => "success"
        ]);
        return;
    }

    // ===========================
    // PUBLIC ACCOUNT
    // ===========================
    $data = [
        "from_user" => $user_id,
        "to_user"   => $to_user,
        "date"      => date("Y-m-d H:i:s"),
        "status"    => "follow"
    ];

    $this->User_model->follow($data);

    echo json_encode([
        "response_code" => "0",
        "message"       => "follow",
        "is_follow"     => "1",
        "status"        => "success"
    ]);
}


public function profile_blocked_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set("Asia/Kolkata");

    // Load Models
    $this->load->model("User_model");
    $this->load->database();

    // 🔹 Read Token
    $authHeader = $this->input->get_request_header("Authorization");
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => 0,
            "message"       => "Unauthorized",
            "status"        => "failure"
        ]);
        return;
    }

    $token = $matches[1];
    $user_id = $this->User_model->validate_token_and_get_user($token);

    if (!$user_id) {
        echo json_encode([
            "response_code" => 0,
            "message"       => "Invalid Token",
            "status"        => "failure"
        ]);
        return;
    }

    // =========================
    // READ JSON INPUT
    // =========================
    $json = json_decode(file_get_contents("php://input"), true);

    $block_user_id = isset($json["block_user_id"]) ? $json["block_user_id"] : "";
    $to_user_id    = isset($json["to_user_id"]) ? $json["to_user_id"] : "";

    if ($block_user_id == "") {
        echo json_encode([
            "response_code" => 0,
            "message"       => "Enter Data",
            "status"        => "failure"
        ]);
        return;
    }

    // 🔹 Check Already Blocked
    $this->db->where("user_id", $user_id);
    $this->db->where("block_user_id", $block_user_id);
    $like = $this->db->count_all_results("profile_block");

    // 🔹 If Already Blocked → Unblock
    if ($like == 1) {

        $this->db->where("user_id", $user_id);
        $this->db->where("block_user_id", $block_user_id);
        $this->db->delete("profile_block");

        echo json_encode([
            'response_code' => '0',
            'message'       => 'unblocked',
            'is_blocked'    => '0',
            'status'        => 'success'
        ]);
        return;
    }

    // 🔹 Block Profile
    if ($like == 0) {

        $data = [
            "user_id"       => $user_id,
            "block_user_id" => $block_user_id,
            "created_at"    => date("Y-m-d H:i:s")

        ];
        $this->db->insert("profile_block", $data);

        // Remove follow from both sides
        $this->db->group_start()
            ->where("from_user", $user_id)
            ->where("to_user", $block_user_id)
            ->group_end();

        $this->db->or_group_start()
            ->where("from_user", $block_user_id)
            ->where("to_user", $user_id)
            ->group_end();

        $this->db->delete("follow");

        // Notification
        $tuser = $this->db->select("username")->from("users")->where("id", $user_id)->get()->row()->username;
        $fuser = $this->db->select("username")->from("users")->where("id", $block_user_id)->get()->row()->username;

        $create_date = round(microtime(true) * 1000);

        $notif = [
            "from_user" => $user_id,
            "to_user"   => $to_user_id,
            "post_id"   => 0,
            "not_type"  => "0",
            "message"   => "$fuser has been blocked by $tuser.",
            "title"     => "User Blocked",
            "date"      => $create_date
        ];

        $this->db->insert("app_notification", $notif);

        echo json_encode([
            "response_code" => "0",
            "message"       => "blocked",
            "is_blocked"    => "1",
            "status"        => "success"
        ]);
        return;
    }

    // Already blocked fallback
    echo json_encode([
        'response_code' => '1',
        'message'       => 'Already Profile Blocked',
        'status'        => 'success'
    ]);
}



public function my_story_delete_post()
{
    header('Content-Type: application/json');
    date_default_timezone_set("Asia/Kolkata");

    $this->load->model("Story_model");
    $this->load->model("User_model");
    $this->load->database();

    // 🔹 Read Token
    $authHeader = $this->input->get_request_header("Authorization");
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => 0,
            "message"       => "Unauthorized",
            "status"        => "failure"
        ]);
        return;
    }

    $token = $matches[1];
    $user_id = $this->User_model->validate_token_and_get_user($token);

    if (!$user_id) {
        echo json_encode([
            "response_code" => 0,
            "message"       => "Invalid Token",
            "status"        => "failure"
        ]);
        return;
    }

    // JSON Body Support
    $rawData = json_decode($this->input->raw_input_stream, true);

    if (!empty($rawData['story_id'])) {
        $story_id = $rawData['story_id'];
    } else {
        $story_id = $this->input->post('story_id');
    }

    if (empty($story_id)) {
        echo json_encode([
            'response_code' => '0',
            'message' => 'Story ID Required',
            'status' => 'failure'
        ]);
        return;
    }

    // Check story
    $check_story = $this->Story_model->get_story_by_id_user($story_id, $user_id);
    if (empty($check_story)) {
        echo json_encode([
            'response_code' => '0',
            'message' => 'This story is not authorized for you.',
            'status' => 'failure'
        ]);
        return;
    }

    // Delete
    $done = $this->Story_model->delete_story($story_id, $user_id);

    echo json_encode([
        'response_code' => $done ? '1' : '0',
        'message'       => $done ? 'Story successfully deleted.' : 'Story could not be deleted.',
        'status'        => $done ? 'success' : 'failure'
    ]);
}


public function avtar_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set("Asia/Kolkata");

    // Your base URL
    $baseURL = "https://insta.vihaanshika.com/";

    // Load Model
    $this->load->model("User_model");

    // Fetch all avatar data
    $avatars = $this->User_model->get_all_avatar();

    if (!empty($avatars)) {

        $result = [];

        foreach ($avatars as $av) {

            // Profile Image
            $avatar_image = !empty($av->image)
                ? $baseURL . "assetsNew/images/avatar/" . $av->image
                : $baseURL . "assetsNew/images/avatar/default.png";

            $result[] = [
                "avatar_id"   => $av->id,
                "avatar_name" => $av->name
                // "avatar_image" => $avatar_image
            ];
        }

        echo json_encode([
            "response_code" => "1",
            "message"       => "Avatar Found",
            "avatar_list"   => $result
            ]);

    } else {

        echo json_encode([
            "response_code" => "0",
            "message"       => "Avatar Not Found",
            "avatar_list"   => []
            ]);

    }
}

public function verified_token_post()
{
    header("Content-Type: application/json; charset=utf-8");

    echo json_encode([
        "message" => "Token verification success!",
        "success" => true
    ]);
    return;
}



public function all_login_status_get()
{
    header("Content-Type: application/json; charset=utf-8");

    try {

        // Load Models
        $this->load->model('User_model');

        // Fetch login status rows by fixed IDs
        $facebook   = $this->User_model->get_by_id(1);
        $google     = $this->User_model->get_by_id(2);
        $apple      = $this->User_model->get_by_id(3);
        $email      = $this->User_model->get_by_id(4);
        $mobileotp  = $this->User_model->get_by_id(5);

        // Fetch Site Setup data
        $category = $this->User_model->get_first();

        // SUCCESS RESPONSE DATA
        $response = [
            "response_code"   => "200",
            "message"         => "Success",
            "status"          => "1",
            "facebook"        => isset($facebook->status) ? (string)$facebook->status : "0",
            "apple"           => isset($apple->status) ? (string)$apple->status : "0",
            "google"          => isset($google->status) ? (string)$google->status : "0",
            "email"           => isset($email->status) ? (string)$email->status : "0",
            "mobileotp"       => isset($mobileotp->status) ? (string)$mobileotp->status : "0",
            "primary_color"   => $category->primary_color ?? "#000000",
            "secondary_color" => $category->secondary_color ?? "#FFFFFF"
        ];

        echo json_encode($response);
        return;

    } catch (Exception $e) {

        // ERROR RESPONSE (ALWAYS RETURN ALL KEYS)
        $errorResponse = [
            "response_code"   => "400",
            "message"         => "Something went wrong",
            "status"          => "0",
            "facebook"        => "0",
            "apple"           => "0",
            "google"          => "0",
            "email"           => "0",
            "mobileotp"       => "0",
            "primary_color"   => "#000000",
            "secondary_color" => "#FFFFFF"
        ];

        echo json_encode($errorResponse);
        return;
    }
}


public function get_all_settings_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    // Fetch site setup settings
    $category = $this->db->get('site_setup')->row();

    if ($category) {

        $result_all['app_name'] = $category->name ?? "";

        $result_all['dark_logo'] = !empty($category->dark_logo)
            ? base_url('assetsNew/images/logo/' . $category->dark_logo)
            : "";

        $result_all['light_logo'] = !empty($category->light_logo)
            ? base_url('assetsNew/images/logo/' . $category->light_logo)
            : "";

        $result_all['copyright_text'] = $category->copyright_text ?? "";

        $result_all['color_code'] = $category->color_code ?? "";

        $result_all['email'] = $category->email ?? "";

        $result_all['fav_icon'] = !empty($category->fav_icon)
            ? base_url('assetsNew/images/logo/' . $category->fav_icon)
            : "";

        $result_all['banner_image'] = !empty($category->banner_image)
            ? base_url('assetsNew/images/logo/' . $category->banner_image)
            : "";

        $result_all['splash_image'] = !empty($category->splash_image)
            ? base_url('assetsNew/images/logo/' . $category->splash_image)
            : "";

        $result_all['purchase_code'] = $category->purchase_code ?? "";

        $result_all['primary_color'] = $category->primary_color ?? "";

        $result_all['secondary_color'] = $category->secondary_color ?? "";

        $result_all['price'] = $category->price ?? "";

        // Fetch Tax Rate
        $this->db->where('status', 1);
        $done = $this->db->get('tax_rates')->row();
        $result_all['tax'] = $done ? $done->tax_rate : "";

        // Final return
        $response = [
            "response_code" => "1",
            "message"       => "All Setting List Done",
            "status"        => "success",
            "setting_list"  => $result_all
        ];

        echo json_encode($response);
        return;
    }

    // If not found
    $response = [
        "response_code" => "0",
        "message"       => "All Key List Not found",
        "status"        => "failure"
    ];

    echo json_encode($response);
}

public function listAllLanguages_post()
{
    header('Content-Type: application/json');

    try {

        // Load model
        $this->load->model('Story_model');

        // Fetch all languages
        $languages = $this->Story_model->get_all_languages();

        // Check if any languages found
        if (empty($languages)) {
            echo json_encode([
                'success' => false,
                'message' => 'No languages found'
            ]);
            return;
        }

        // Format languages
        $formattedLanguages = array_map(function ($language) {
            return [
                'language'            => $language->language,
                'status'              => $language->status,
                'default_status'      => $language->default_status,
                'status_id'           => $language->status_id,
                'country'             => $language->country,
                'language_alignment'  => $language->language_alignment,
            ];
        }, $languages);

        // Response
        echo json_encode([
            'success'       => true,
            'response_code' => 200,
            'message'       => 'Languages retrieved successfully',
            'languages'     => $formattedLanguages
        ]);

    } catch (Exception $e) {

        log_message('error', 'Error fetching languages: ' . $e->getMessage());

        echo json_encode([
            'success' => false,
            'message' => 'An error occurred'
        ]);
    }
}



public function fetchDefaultLanguage_post()
{
    header("Content-Type: application/json");

    try {

        // Read JSON input
        $input = json_decode(file_get_contents("php://input"), true);
        $statusId = isset($input['status_id']) ? $input['status_id'] : null;

        // Load model
        $this->load->model('Story_model');

        // -------- Fetch language by status_id OR default language --------
        if (!empty($statusId)) {

            $language = $this->Story_model->get_language_by_status_id($statusId);

        } else {

            $language = $this->Story_model->get_default_language();

        }

        if (!$language) {
            echo json_encode([
                "success" => false,
                "message" => "Language is not available"
            ]);
            return;
        }

        // -------- Fetch translations from language_settings --------
        // SELECT setting_id, key, <language_column> AS Translation

        $this->db->select("setting_id, `key`, {$language->language} AS Translation", false);
        $results = $this->db->get("language_settings")->result();

        // -------- Response --------
        echo json_encode([
            "language_alignment" => $language->language_alignment ?? "ltr",
            "success"            => true,
            "message"            => "Language found",
            "language"           => $language->language,
            "results"            => $results
        ]);

    } catch (Exception $e) {

        echo json_encode([
            "success" => false,
            "message" => "An error occurred",
            "error"   => $e->getMessage()
        ]);
    }
}


public function delete_search_item_post()
{
    header('Content-Type: application/json');

    // Read RAW JSON Input
    $rawData = file_get_contents("php://input");
    $input = json_decode($rawData, true);

    // Debug Purpose (Check JSON received or not)
    if (!$input) {
        echo json_encode([
            "response_code" => "0",
            "message"       => "Invalid JSON format",
            "status"        => "failure"
        ]);
        return;
    }

    // Getting search_id from JSON
    $search_id = isset($input['search_id']) ? $input['search_id'] : null;

    if (empty($search_id)) {
        echo json_encode([
            'response_code' => '0',
            'message'       => 'search_id is required',
            'status'        => 'failure'
        ]);
        return;
    }

    // Check if record exists
    $this->db->where('id', $search_id);
    $query = $this->db->get('search_username');

    if ($query->num_rows() > 0) {
        // Delete record
        $this->db->where('id', $search_id);
        $this->db->delete('search_username');
    }

    // Final Response
    echo json_encode([
        'response_code' => '1',
        'message'       => 'Search Items Deleted Successfully',
        'status'        => 'success'
    ]);
}


public function add_story_highlight_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    // --- READ JSON INPUT ---
    $json = json_decode(file_get_contents("php://input"), true);

    // --- AUTH TOKEN VALIDATION ---
    $authHeader = $this->input->get_request_header("Authorization");
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => "401",
            "message"       => "Unauthorized",
            "status"        => "0"
        ]);
        return;
    }

    $token = $matches[1];
    $user_id = $this->User_model->validate_token_and_get_user($token);

    if (!$user_id) {
        echo json_encode([
            "response_code" => "401",
            "message"       => "Invalid Token",
            "status"        => "0"
        ]);
        return;
    }

    // --- CHECK REQUIRED FIELD ---
    $story_id = $json["story_id"] ?? "";

    if (empty($story_id)) {
        echo json_encode([
            "response_code" => "400",
            "message"       => "story_id is required",
            "status"        => "0"
        ]);
        return;
    }

    // --- STORY VALIDATION ---
    $this->db->where('user_id', $user_id);
    $this->db->where('story_id', $story_id);
    $story = $this->db->get('story')->row();

    if (!$story) {
        echo json_encode([
            "response_code" => "400",
            "message"       => "Invalid story ID",
            "status"        => "0"
        ]);
        return;
    }

    // --- INSERT DATA INTO story_highlight ---
    $data = [
        "user_id"    => $user_id,
        "story_id"   => $story_id,
        "title"      => $story->title ?? '',
        "cover_pic"  => $story->cover_pic ?? '',
        "created_at" => date("Y-m-d H:i:s")
    ];

    $this->db->insert("story_highlight", $data);

    // --- SUCCESS RESPONSE ---
    echo json_encode([
        "response_code" => "200",
        "message"       => "Highlight created successfully",
        "status"        => "1"
    ]);
}

public function post_on_comments_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) $input = $_POST;

    $post_id    = isset($input['post_id']) ? intval($input['post_id']) : 0;
    $comment_id = isset($input['comment_id']) ? intval($input['comment_id']) : 0;
    $page_no    = isset($input['page_no']) ? intval($input['page_no']) : 1;
    $limit      = 10;
    $start      = ($page_no - 1) * $limit;

    // Validation
    if ($post_id == 0) {
        echo json_encode([
            "response_code" => "0",
            "message"       => "post_id is required",
            "status"        => "failure"
        ]);
        return;
    }

    // ============================
    // FETCH MAIN COMMENTS
    // ============================
    if ($comment_id == 0) {
        // Count total comments for pagination
        $this->db->where("post_id", $post_id);
        $total_count = $this->db->count_all_results("post_comment");

        $total_page = ($total_count > 0) ? ceil($total_count / $limit) : 0;
        $remaining  = max($total_count - ($page_no * $limit), 0);

        // Fetch paginated main comments
        $this->db->where("post_id", $post_id);
        $this->db->order_by("id", "ASC");
        $this->db->limit($limit, $start);
        $query = $this->db->get("post_comment");

        $result = [];
        foreach ($query->result() as $row) {
            $user = $this->db->get_where("users", ["id" => $row->user_id])->row();

            // Total replies for this comment
            $total_reply_count = $this->db->where("post_comment_id", $row->id)
                                           ->count_all_results("post_sub_comment");

            // Use like_count field from post_comment table
            $total_like_count = intval($row->like_count);

            // Check if user liked it (optional)
            $is_like = $this->db->get_where("post_comment_like", [
                "post_comment_id" => $row->id,
                "user_id" => $row->user_id
            ])->num_rows() > 0;

            $result[] = [
                "user_id"            => strval($row->user_id),
                "post_id"            => strval($row->post_id),
                "comment_id"         => strval($row->id),
                "text"               => $row->text ?? "",
                "total_like_count"   => strval($total_like_count),
                "created_at"         => $row->created_at ?? "",
                "username"           => $user->username ?? "",
                "profile_pic"        => $user->profile_pic ?? "",
                "total_comment_count"=> strval($total_reply_count),
                "is_like"            => $is_like ? true : false
            ];
        }

        echo json_encode([
            "response_code"    => "1",
            "message"          => "Comment Found",
            "status"           => "success",
            "current_page"     => $page_no,
            "total_page"       => $total_page,
            "remaining_value"  => $remaining,
            "all_post_comment" => $result
        ]);
    }

    // ============================
    // FETCH SUB-COMMENTS (REPLIES)
    // ============================
    else {
        // Count total replies for pagination
        $this->db->where("post_comment_id", $comment_id);
        $total_count = $this->db->count_all_results("post_sub_comment");

        $total_page = ($total_count > 0) ? ceil($total_count / $limit) : 0;
        $remaining  = max($total_count - ($page_no * $limit), 0);

        // Fetch paginated sub-comments
        $this->db->where("post_comment_id", $comment_id);
        $this->db->order_by("id", "ASC");
        $this->db->limit($limit, $start);
        $query = $this->db->get("post_sub_comment");

        $result = [];
        foreach ($query->result() as $row) {
            $user = $this->db->get_where("users", ["id" => $row->user_id])->row();

            // Total likes for sub-comment
            $total_like_count = $this->db->where("post_sub_comment_id", $row->id)
                                         ->count_all_results("post_sub_comment_like");

            // Check if user liked it
            $is_like = $this->db->get_where("post_sub_comment_like", [
                "post_sub_comment_id" => $row->id,
                "user_id" => $row->user_id
            ])->num_rows() > 0;

            $result[] = [
                "post_sub_comment_id"=> intval($row->id),
                "user_id"           => strval($row->user_id),
                "post_id"           => strval($row->post_id),
                "comment_id"        => strval($row->post_comment_id),
                "text"              => $row->text ?? "",
                "created_at"        => $row->date ?? "",
                "username"          => $user->username ?? "",
                "profile_pic"       => $user->profile_pic ?? "",
                "total_reply_count" => "0",
                "total_like_count"  => intval($total_like_count),
                "is_like"           => $is_like ? true : false
            ];
        }

        echo json_encode([
            "response_code"    => "1",
            "message"          => "Reply Found",
            "status"           => "success",
            "current_page"     => $page_no,
            "total_page"       => $total_page,
            "remaining_value"  => $remaining,
            "all_post_comment" => $result
        ]);
    }
}


public function edit_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set('Asia/Kolkata');

    $this->load->model('User_model');

    /* ---------- READ TOKEN ---------- */
    $authHeader = $this->input->get_request_header('Authorization');
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Authorization token missing"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $token = $matches[1];

    /* ---------- VALIDATE TOKEN ---------- */
    $login_user_id = $this->User_model->validate_token_and_get_user($token);
    if (!$login_user_id) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Invalid or expired token"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    /* ---------- READ JSON ---------- */
    $input = json_decode(file_get_contents("php://input"), true);

    if (empty($input['post_id']) || !array_key_exists('text', $input)) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "post_id and text are required"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $post_id = (int)$input['post_id'];
    $text = (string)$input['text'];

    /* ---------- CHECK OWNERSHIP ---------- */
    $post = $this->db
        ->where('post_id', $post_id)
        ->where('user_id', $login_user_id)
        ->where('is_delete', 0)
        ->get('posts')
        ->row();

    if (!$post) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Post not found or not owned by user"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    /* ---------- UPDATE ---------- */
    $this->db->where('post_id', $post_id);
    $this->db->update('posts', [
        'text' => $text,
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    /* ---------- SUCCESS ---------- */
    echo json_encode([
        "response_code" => "1",
        "message" => "Post updated successfully",
        "status" => "success"
       
    ], JSON_UNESCAPED_UNICODE);
}


public function delete_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    /* --------------------------------
       🔹 READ BEARER TOKEN
    ---------------------------------*/
    $authHeader = $this->input->get_request_header('Authorization', TRUE);

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Authorization token missing"
        ]);
        return;
    }

    $token = $matches[1];

    /* --------------------------------
       🔹 VALIDATE TOKEN & GET USER
    ---------------------------------*/
    $user = $this->db
        ->where('token', $token)
        ->where('token_expiry >=', date('Y-m-d H:i:s'))
        ->get('users')
        ->row();

    if (!$user) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Invalid or expired token"
        ]);
        return;
    }

    $user_id = $user->id;

    /* --------------------------------
       🔹 READ INPUT
    ---------------------------------*/
    $input = json_decode(file_get_contents("php://input"), true);

    if (empty($input['post_id'])) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "post_id is required"
        ]);
        return;
    }

    $post_id = (int)$input['post_id'];

    /* --------------------------------
       🔹 CHECK POST EXISTS & OWNERSHIP
    ---------------------------------*/
    $post = $this->db
        ->where('post_id', $post_id)
        ->where('user_id', $user_id)
        ->where('is_delete', 0)
        ->get('posts')
        ->row();

    if (!$post) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Post not found or not owned by user"
        ]);
        return;
    }

    /* --------------------------------
       🔹 SOFT DELETE POST
    ---------------------------------*/
    $this->db->where('post_id', $post_id);
    $this->db->update('posts', [
        'is_delete' => 1,
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    /* --------------------------------
       🔹 DELETE RELATED DATA
    ---------------------------------*/
    $this->db->where('post_id', $post_id)->delete('post_user_tags');
    $this->db->where('post_id', $post_id)->delete('post_like');
    $this->db->where('post_id', $post_id)->delete('post_comment');
    $this->db->where('post_id', $post_id)->delete('post_comment_like');
    $this->db->where('post_id', $post_id)->delete('post_video');
    $this->db->where('post_id', $post_id)->delete('bookmark_post');
    $this->db->where('post_id', $post_id)->delete('user_notifications');
    // $this->db->where('post_id', $post_id)->update('hashtag', ['is_delete' => 1]);

    /* --------------------------------
       🔹 SUCCESS RESPONSE
    ---------------------------------*/
    echo json_encode([
        "response_code" => "1",
        "status" => "success",
        "message" => "Post Successfully Deleted"
    ]);
}

public function reel_on_comment_post()
{
    // ✅ Correct JSON header with UTF-8
    header("Content-Type: application/json; charset=UTF-8");
    date_default_timezone_set("Asia/Kolkata");

    /* -----------------------------
       FORCE UTF8MB4 (EMOJI SAFE)
    ------------------------------*/
    $this->db->query("SET NAMES utf8mb4");
    $this->db->query("SET CHARACTER SET utf8mb4");
    $this->db->query("SET SESSION collation_connection = 'utf8mb4_unicode_ci'");

    /* -----------------------------
       READ JSON INPUT
    ------------------------------*/
    $input = json_decode(file_get_contents("php://input"), true);

    $reel_id    = $input['reel_id'] ?? "";
    $page_no    = $input['page_no'] ?? 1;
    $comment_id = $input['comment_id'] ?? "";

    if (empty($reel_id)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "reel_id is mandatory",
            "status" => "failure"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    /* -----------------------------
       AUTH TOKEN → USER ID
    ------------------------------*/
    // 🔥 Apache / Hosting fallback fix
    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    }

    $authHeader = $this->input->get_request_header("Authorization", TRUE);
    $user_id = "";

    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];

        $user = $this->db
            ->where("token", $token)
            ->get("users")
            ->row();

        if ($user) {
            $user_id = $user->id;
        }
    }

    if (empty($user_id)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Unauthorized user",
            "status" => "failure"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    /* -----------------------------
       PAGINATION
    ------------------------------*/
    $per_page = 10;
    $offset   = ($page_no - 1) * $per_page;
    $all_comments = [];

    /* =====================================================
       SUB COMMENTS (REPLIES)
    ======================================================*/
    if (!empty($comment_id)) {

        $comment_exists = $this->db
            ->where("id", $comment_id)
            ->where("reel_id", $reel_id)
            ->count_all_results("reel_comment");

        if ($comment_exists == 0) {
            echo json_encode([
                "response_code" => "0",
                "message" => "Comment not found",
                "status" => "failure"
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $sub_comments = $this->db
            ->where("reel_id", $reel_id)
            ->where("reel_comment_id", $comment_id)
            ->order_by("created_at", "DESC")
            ->limit($per_page, $offset)
            ->get("reel_sub_comment")
            ->result();

        foreach ($sub_comments as $row) {

            $is_like = $this->db
                ->where("reel_id", $reel_id)
                ->where("reel_sub_comment_id", $row->id)
                ->where("user_id", $user_id)
                ->count_all_results("reel_sub_comment_like") > 0;

            $total_like = $this->db
                ->where("reel_sub_comment_id", $row->id)
                ->count_all_results("reel_sub_comment_like");

            $replyCount = $this->db
                ->where("reel_comment_id", $row->reel_comment_id)
                ->count_all_results("reel_sub_comment");

            $user = $this->db->where("id", $row->user_id)->get("users")->row();

            $all_comments[] = [
                "user_id" => (string)$row->user_id,
                "reel_id" => (string)$row->reel_id,
                "comment_id" => (string)$row->reel_comment_id,
                "reel_sub_comment_id" => (string)$row->id,
                "text" => $row->text, // 🔥 emoji safe
                "created_at" => date("Y-m-d H:i:s", strtotime($row->created_at)),
                "username" => $user->username ?? "",
                "profile_pic" => !empty($user->profile_pic)
                    ? adv_profile_pic_url($user->profile_pic)
                    : "",
                "is_like" => $is_like,
                "total_like_count" => $total_like,
                "total_comment_count" => (string)$replyCount
            ];
        }

        $total_count = count($sub_comments);
    }

    /* =====================================================
       MAIN COMMENTS
    ======================================================*/
    else {

        $comments = $this->db
            ->where("reel_id", $reel_id)
            ->order_by("created_at", "DESC")
            ->limit($per_page, $offset)
            ->get("reel_comment")
            ->result();

        foreach ($comments as $row) {

            $is_like = $this->db
                ->where("reel_id", $reel_id)
                ->where("user_id", $user_id)
                ->count_all_results("reel_like") > 0;

            $total_like = $this->db
                ->where("reel_id", $reel_id)
                ->count_all_results("reel_like");

            $replyCount = $this->db
                ->where("reel_comment_id", $row->id)
                ->count_all_results("reel_sub_comment");

            $user = $this->db->where("id", $row->user_id)->get("users")->row();

            $all_comments[] = [
                "user_id" => (string)$row->user_id,
                "reel_id" => (string)$row->reel_id,
                "comment_id" => (string)$row->id,
                "text" => $row->text, // 🔥 emoji safe
                "created_at" => date("Y-m-d H:i:s", strtotime($row->created_at)),
                "total_comment_count" => (string)$replyCount,
                "username" => $user->username ?? "",
                "profile_pic" => !empty($user->profile_pic)
                    ? adv_profile_pic_url($user->profile_pic)
                    : "",
                "is_like" => $is_like,
                "total_like_count" => $total_like
            ];
        }

        $total_count = $this->db
            ->where("reel_id", $reel_id)
            ->count_all_results("reel_comment");
    }

    /* -----------------------------
       FINAL RESPONSE (EMOJI FIX)
    ------------------------------*/
    echo json_encode([
        "response_code" => !empty($all_comments) ? "1" : "0",
        "message" => !empty($all_comments) ? "Comment Found" : "Comment Not Found",
        "status" => "success",
        "current_page" => (int)$page_no,
        "total_page" => ceil($total_count / $per_page),
        "all_reel_comment" => $all_comments
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

public function reel_add_comment_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set("Asia/Kolkata");

    // ✅ FORCE EMOJI SUPPORT (VERY IMPORTANT)
    $this->db->query("SET NAMES utf8mb4");
    $this->db->query("SET CHARACTER SET utf8mb4");
    $this->db->query("SET collation_connection = utf8mb4_unicode_ci");

    /* -----------------------------
       READ JSON INPUT
    ------------------------------*/
    $input = json_decode(file_get_contents("php://input"), true);

    $reel_id = $input['reel_id'] ?? "";
    $text    = $input['text'] ?? "";

    if (empty($reel_id) || empty($text)) {
        echo json_encode([
            "response_code" => 0,
            "message" => "Failed to add comment",
            "status" => "error"
        ]);
        return;
    }

    /* -----------------------------
       AUTH TOKEN → USER
    ------------------------------*/
    $authHeader = $this->input->get_request_header("Authorization");
    $user_id = "";

    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];

        $user = $this->db
            ->where("token", $token)
            ->where("token_expiry >=", date("Y-m-d H:i:s"))
            ->get("users")
            ->row();

        if ($user) {
            $user_id = $user->id;
        }
    }

    if (empty($user_id)) {
        echo json_encode([
            "response_code" => 0,
            "message" => "Failed to add comment",
            "status" => "error"
        ]);
        return;
    }

    /* -----------------------------
       ADD REEL COMMENT (EMOJI SAFE 😍🔥)
    ------------------------------*/
    $insert_data = [
        "user_id"    => $user_id,
        "reel_id"    => $reel_id,
        "text"       => $text, // ✅ emoji supported
        "date"       => round(microtime(true) * 1000),
        "created_at" => date("Y-m-d H:i:s")
    ];

    if (!$this->db->insert("reel_comment", $insert_data)) {
        echo json_encode([
            "response_code" => 0,
            "message" => "Failed to add comment",
            "status" => "error"
        ]);
        return;
    }

    $comment_id = $this->db->insert_id();

    if (!$comment_id) {
        echo json_encode([
            "response_code" => 0,
            "message" => "Failed to add comment",
            "status" => "error"
        ]);
        return;
    }

    /* -----------------------------
       FETCH REEL OWNER
    ------------------------------*/
    $post = $this->db
        ->where("post_id", $reel_id)
        ->where("post_type", "reel")
        ->get("posts")
        ->row();

    if ($post) {

        $to_user = $post->user_id;

        // ❌ no self notification
        if ($user_id != $to_user) {

            $comment_user = $this->db->where("id", $user_id)->get("users")->row();
            $username = $comment_user->username ?? "Someone";

            $to_user_data = $this->db->where("id", $to_user)->get("users")->row();
            $device_token = $to_user_data->device_token ?? "";

            $notification = $this->db
                ->where("id", "4")
                ->where("status", "1")
                ->get("notification_permissions")
                ->row();

            if ($notification && !empty($device_token)) {

                $message = str_replace(
                    ['[[ username ]]', '[[ time ]]'],
                    [$username, ''],
                    $notification->description
                );

                $push_data = [
                    "title"        => $notification->title,
                    "message"      => $message,
                    "type"         => $notification->type,
                    "post_id"      => $reel_id,
                    "from_user"    => $user_id,
                    "to_user"      => $to_user,
                    "post_user_id" => $to_user
                ];

                // ✅ SAFE PUSH
                if (method_exists($this, 'sendNotification')) {
                    $this->sendNotification($push_data, $device_token);
                }

                // ✅ DB notification (emoji safe)
                $this->db->insert("user_notifications", [
                    "from_user"       => $user_id,
                    "to_user"         => $to_user,
                    "title"           => $notification->title,
                    "post_id"         => $reel_id,
                    "message"         => $message,
                    "notifiable_type" => $notification->type,
                    "post_user_id"    => $to_user,
                    "created_at"      => date("Y-m-d H:i:s")
                ]);
            }
        }
    }

    /* -----------------------------
       FINAL RESPONSE
    ------------------------------*/
    echo json_encode([
        "response_code" => 1,
        "message" => "Comment added successfully 💬😍",
        "status" => "success"
    ]);
}


public function reel_add_subcomment_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");
$this->db->query("SET NAMES utf8mb4");
    /* -----------------------------
       READ JSON INPUT
    ------------------------------*/
    $input = json_decode(file_get_contents("php://input"), true);

    $reel_id         = $input['reel_id'] ?? "";
    $reel_comment_id = $input['reel_comment_id'] ?? "";
    $text            = $input['text'] ?? "";

    if (empty($reel_id) || empty($reel_comment_id) || empty($text)) {
        echo json_encode([
            "response_code" => 0,
            "message" => "Failed to add reply",
            "status" => "error"
        ]);
        return;
    }

    /* -----------------------------
       AUTH TOKEN → USER
    ------------------------------*/
    $authHeader = $this->input->get_request_header("Authorization");
    $user_id = "";

    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];

        $user = $this->db
            ->where("token", $token)
            ->where("token_expiry >=", date("Y-m-d H:i:s"))
            ->get("users")
            ->row();

        if ($user) {
            $user_id = $user->id;
        }
    }

    if (empty($user_id)) {
        echo json_encode([
            "response_code" => 0,
            "message" => "Failed to add reply",
            "status" => "error"
        ]);
        return;
    }

    /* -----------------------------
       ADD SUB COMMENT
    ------------------------------*/
    $insert_data = [
        "user_id"         => $user_id,
        "reel_id"         => $reel_id,
        "reel_comment_id" => $reel_comment_id,
        "text"            => $text,
        "date"            => round(microtime(true) * 1000),
        "created_at"      => date("Y-m-d H:i:s")
    ];

    if (!$this->db->insert("reel_sub_comment", $insert_data)) {
        echo json_encode([
            "response_code" => 0,
            "message" => "Failed to add reply",
            "status" => "error"
        ]);
        return;
    }

    $sub_comment_id = $this->db->insert_id();
    if (!$sub_comment_id) {
        echo json_encode([
            "response_code" => 0,
            "message" => "Failed to add reply",
            "status" => "error"
        ]);
        return;
    }

    /* -----------------------------
       FETCH COMMENT OWNER
    ------------------------------*/
    $comment = $this->db
        ->where("id", $reel_comment_id)
        ->get("reel_comment")
        ->row();

    if ($comment) {

        $to_user = $comment->user_id;

        // same user → no notification
        if ($user_id != $to_user) {

            $comment_user = $this->db->where("id", $user_id)->get("users")->row();
            $username = $comment_user->username ?? "";

            $to_user_data = $this->db->where("id", $to_user)->get("users")->row();
            $device_token = $to_user_data->device_token ?? "";

            $notification = $this->db
                ->where("id", "8")
                ->where("status", "1")
                ->get("notification_permissions")
                ->row();

            if ($notification && !empty($device_token)) {

                $message = str_replace(
                    ['[[ username ]]', '[[ time ]]'],
                    [$username, ''],
                    $notification->description
                );

                $push_data = [
                    "title" => $notification->title,
                    "message" => $message,
                    "type" => $notification->type,
                    "post_id" => $reel_id,
                    "from_user" => $user_id,
                    "to_user" => $to_user,
                    "post_user_id" => $to_user
                ];

                $this->sendNotification($push_data, $device_token);

                $this->db->insert("user_notifications", [
                    "from_user" => $user_id,
                    "to_user" => $to_user,
                    "title" => $notification->title,
                    "post_id" => $reel_id,
                    "message" => $message,
                    "notifiable_type" => $notification->type,
                    "post_user_id" => $to_user,
                    "created_at" => date("Y-m-d H:i:s")
                ]);
            }
        }
    }

    /* -----------------------------
       FINAL RESPONSE (STRICT)
    ------------------------------*/
    echo json_encode([
        "response_code" => 1,
        "message" => "Reply added successfully",
        "status" => "success"
    ]);
}


public function second_user_profile_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    /* -----------------------------
       1️⃣ READ JSON INPUT
    ------------------------------*/
    $input = json_decode(file_get_contents("php://input"), true);
    $to_user_id = $input['to_user_id'] ?? '';
    $post_id    = $input['post_id'] ?? '';

    if (empty($to_user_id)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "to_user_id required",
            "status" => "failure"
        ]);
        return;
    }

    /* -----------------------------
       2️⃣ AUTH TOKEN
    ------------------------------*/
    $authHeader = $this->input->get_request_header("Authorization", TRUE);
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Unauthorized user",
            "status" => "failure"
        ]);
        return;
    }

    $token = $matches[1];

    $loginUser = $this->db
        ->where("token", $token)
        ->where("token_expiry >=", date("Y-m-d H:i:s"))
        ->get("users")
        ->row();

    if (!$loginUser) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Invalid token",
            "status" => "failure"
        ]);
        return;
    }

    $login_user_id = $loginUser->id;

    /* -----------------------------
       3️⃣ PROFILE USER
    ------------------------------*/
    $profileUser = $this->db
        ->where("id", $to_user_id)
        ->get("users")
        ->row();

    if (!$profileUser) {
        echo json_encode([
            "response_code" => "0",
            "message" => "User not found",
            "status" => "failure"
        ]);
        return;
    }

    /* -----------------------------
       4️⃣ BLOCK CHECK
    ------------------------------*/
    $is_block = $this->db
        ->where("(user_id = $login_user_id AND block_user_id = $to_user_id)
              OR (user_id = $to_user_id AND block_user_id = $login_user_id)")
        ->get("profile_block")
        ->num_rows() > 0 ? "1" : "0";

    if ($is_block === "1") {
        echo json_encode([
            "response_code" => "0",
            "message" => "You cannot view this profile",
            "status" => "failure"
        ]);
        return;
    }

    /* -----------------------------
       5️⃣ FOLLOW STATUS
    ------------------------------*/

    // login user → profile user
    $is_followers = $this->db
        ->where("from_user", $login_user_id)
        ->where("to_user", $to_user_id)
        ->where("status", "active")
        ->get("follow")
        ->num_rows() > 0 ? "1" : "0";

    // profile user → login user
    $is_user_following_me = $this->db
        ->where("from_user", $to_user_id)
        ->where("to_user", $login_user_id)
        ->where("status", "active")
        ->get("follow")
        ->num_rows() > 0 ? "1" : "0";

    /* -----------------------------
       6️⃣ FOLLOW COUNTS
    ------------------------------*/

    // Followers → who follows profile user
    $followers = (string) $this->db
        ->where("to_user", $to_user_id)
        ->where("status", "follow")
        ->get("follow")
        ->num_rows();

    // Following → whom profile user follows
    $following = (string) $this->db
        ->where("from_user", $to_user_id)
        ->where("status", "follow")
        ->get("follow")
        ->num_rows();

    /* -----------------------------
       7️⃣ POSTS / REELS / TAGS
    ------------------------------*/
    $total_posts = (string) $this->db
        ->where("user_id", $to_user_id)
        ->where("post_type", "image")
        ->get("posts")
        ->num_rows();

    $total_reels = (string) $this->db
        ->where("user_id", $to_user_id)
        ->where("post_type", "reel")
        ->get("posts")
        ->num_rows();

    $total_tags = (string) $this->db
        ->where("tag_users", $to_user_id)
        ->get("post_user_tags")
        ->num_rows();

    /* -----------------------------
       8️⃣ PROFILE ACTIVITY
    ------------------------------*/
    if (!empty($post_id)) {
        $this->db->insert("profile_activity", [
            "user_id"      => $login_user_id,
            "post_user_id" => $to_user_id,
            "post_id"      => $post_id,
            "created_at"   => date("Y-m-d H:i:s")
        ]);
    }


    /* -----------------------------
       8️⃣ RESPONSE (APP SAFE)
    ------------------------------*/
    $user_data = [
        "id" => (string) $profileUser->id,
        "first_name" => $profileUser->first_name ?? "",
        "last_name" => $profileUser->last_name ?? "",
        "email" => $profileUser->email ?? "",
        "login_type" => $profileUser->login_type ?? "",
        "username" => $profileUser->username ?? "",
        "mobile" => $profileUser->mobile ?? "",
        "country_code" => $profileUser->country_code ?? "",
        "device_token" => $profileUser->device_token ?? "",
        "role" => $profileUser->role ?? "user",
        "profile_pic" => $profileUser->profile_pic ?? "",
        "address" => $profileUser->address ?? "",
        "bio" => $profileUser->bio ?? "",
        "dob" => $profileUser->dob ?? "",
        "gender" => $profileUser->gender ?? "",
        "avtar_id" => $profileUser->avtar_id ?? "",
        "followers" => $followers,
        "following" => $following,
        "is_followers" => $is_followers,
        "is_user_following_me" => $is_user_following_me,
        "is_block" => $is_block,
        "total_posts" => $total_posts,
        "total_reels" => $total_reels,
        "total_tags" => $total_tags
    ];

    echo json_encode([
        "response_code" => "1",
        "message" => "Profile data found",
        "status" => "success",
        "user_data" => $user_data
    ]);
}


public function second_user_all_post_pagination_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    /* ===============================
       1️⃣ AUTH TOKEN
    =============================== */
    $authHeader = $this->input->get_request_header("Authorization", TRUE);

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "status"       => "Failure",
            "message"      => "Unauthorized user",
            "current_page" => 1,
            "last_page"    => 1,
            "post"         => []
        ]);
        return;
    }

    $token = $matches[1];

    $loginUser = $this->db
        ->where("token", $token)
        ->where("token_expiry >=", date("Y-m-d H:i:s"))
        ->get("users")
        ->row();

    if (!$loginUser) {
        echo json_encode([
            "status"       => "Failure",
            "message"      => "Invalid token",
            "current_page" => 1,
            "last_page"    => 1,
            "post"         => []
        ]);
        return;
    }

    $login_user_id = (int)$loginUser->id;

    /* ===============================
       2️⃣ INPUT
    =============================== */
    $input      = json_decode(file_get_contents("php://input"), true);
    $to_user_id = (int)($input['to_user_id'] ?? 0);
    $page_no    = max(1, (int)($input['page_no'] ?? 1));
    $per_page   = max(1, (int)($input['per_page'] ?? 10));

    if ($to_user_id <= 0) {
        echo json_encode([
            "status"       => "Failure",
            "message"      => "to_user_id required",
            "current_page" => $page_no,
            "last_page"    => 1,
            "post"         => []
        ]);
        return;
    }

    $offset = ($page_no - 1) * $per_page;

    /* ===============================
       3️⃣ TOTAL COUNT
    =============================== */
    $total_post = $this->db
        ->where("user_id", $to_user_id)
        ->where("status", "1")
        ->where("is_delete", "0")
        ->count_all_results("posts");

    $last_page = ($total_post > 0) ? ceil($total_post / $per_page) : 1;

    /* ===============================
       4️⃣ FETCH POSTS
    =============================== */
    $posts = $this->db
        ->where("user_id", $to_user_id)
        ->where("status", "1")
        ->where("is_delete", "0")
        ->order_by("post_id", "DESC")
        ->limit($per_page, $offset)
        ->get("posts")
        ->result();

    $postArr = [];

    foreach ($posts as $post) {

        /* ===============================
           USER
        =============================== */
        $postUser = $this->db
            ->where("id", $post->user_id)
            ->get("users")
            ->row();

        /* ===============================
           LIKE / COMMENT / VIEW
        =============================== */
        $is_likes = $this->db
            ->where("post_id", $post->post_id)
            ->where("user_id", $login_user_id)
            ->count_all_results("post_like") > 0 ? "1" : "0";

        $total_likes = $this->db
            ->where("post_id", $post->post_id)
            ->count_all_results("post_like");

        $total_comments = $this->db
            ->where("post_id", $post->post_id)
            ->count_all_results("post_comment");

        $bookmark = $this->db
            ->where("post_id", $post->post_id)
            ->where("user_id", $login_user_id)
            ->count_all_results("bookmark_post") > 0 ? "1" : "0";

        /* ===============================
           FOLLOW / BLOCK (FIXED)
        =============================== */
        $is_follow = $this->db
            ->where("from_user", $login_user_id)
            ->where("to_user", $post->user_id)
            ->count_all_results("follow") > 0 ? "1" : "0";

        $is_blocked = $this->db
            ->where("user_id", $login_user_id)
            ->where("block_user_id", $post->user_id)
            ->count_all_results("profile_block") > 0 ? "1" : "0";

        /* ===============================
           MEDIA (NEVER NULL)
        =============================== */
        $mediaArr = [];

        $images = $this->db
            ->where("post_id", $post->post_id)
            ->get("post_image")
            ->result();

        foreach ($images as $img) {
            $mediaArr[] = [
                "post_image_id"        => (int)$img->id,
                "url"                  => adv_media_url($img->new_post),
                "type"                 => "image",
                "post_video_thumbnail" => ""
            ];
        }

        $videos = $this->db
            ->where("post_id", $post->post_id)
            ->get("post_video")
            ->result();

        foreach ($videos as $vid) {
            $mediaArr[] = [
                "post_image_id"        => (int)$vid->id,
                "url"                  => adv_media_url($vid->post_video),
                "type"                 => "video",
                "post_video_thumbnail" => !empty($vid->post_video_thumbnail)
                    ? adv_media_url($vid->post_video_thumbnail)
                    : ""
            ];
        }

        /* ===============================
           TAG USERS (NEVER NULL)
        =============================== */
        $tag_user_list = [];

        $tags = $this->db
            ->where("post_id", $post->post_id)
            ->get("post_user_tags")
            ->result();

        foreach ($tags as $tag) {
            $tagUser = $this->db
                ->where("id", $tag->tag_users)
                ->get("users")
                ->row();

            if ($tagUser) {
                $tag_user_list[] = [
                    "tag_user_id" => (int)$tagUser->id,
                    "first_name"  => $tagUser->first_name ?? "",
                    "last_name"   => $tagUser->last_name ?? "",
                    "profile_pic" => !empty($tagUser->profile_pic)
                        ? adv_profile_pic_url(basename($tagUser->profile_pic))
                        : ""
                ];
            }
        }

        /* ===============================
           FINAL POST
        =============================== */
        $postArr[] = [
            "post_id"        => (string)$post->post_id,
            "user_id"        => (string)$post->user_id,
            "text"           => $post->text ?? "",
            "type"           => $post->post_type ?? "",
            "location"       => $post->location ?? "",
            "created_at"     => $post->created_at,
            "is_likes"       => $is_likes,
            "total_likes"    => (int)$total_likes,
            "total_comments" => (int)$total_comments,
            "bookmark"       => $bookmark,
            "total_view"     => (int)($post->total_view ?? 0),
            "profile_image"  => !empty($postUser->profile_pic)
                ? adv_profile_pic_url(basename($postUser->profile_pic))
                : "",
            "username"       => $postUser->username ?? "",
            "total_share"    => (string)($post->total_share ?? 0),
            "is_follow"      => $is_follow,
            "is_blocked"     => $is_blocked,
            "isBoostPost"    => "0",
            "image"          => $mediaArr,        // ✅ ALWAYS []
            "tag_user_list"  => $tag_user_list,   // ✅ ALWAYS []
            "comment"        => []
        ];
    }

    /* ===============================
       RESPONSE
    =============================== */
    echo json_encode([
        "status"       => "Success",
        "message"      => "Posts fetched successfully",
        "current_page" => $page_no,
        "last_page"    => $last_page,
        "post"         => $postArr
    ]);
}


public function bookmark_post_list_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    /* ---------------------------------
       AUTH TOKEN
    ----------------------------------*/
    $authHeader = $this->input->get_request_header("Authorization");
    $user_id = "";

    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];

        $user = $this->db
            ->where("token", $token)
            ->where("token_expiry >=", date("Y-m-d H:i:s"))
            ->get("users")
            ->row();

        if ($user) {
            $user_id = $user->id;
        }
    }

    if (empty($user_id)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "User not authenticated",
            "status" => "failure"
        ]);
        return;
    }

    /* ---------------------------------
       FETCH BOOKMARK POSTS
    ----------------------------------*/
    $bookmarks = $this->db
        ->where("user_id", $user_id)
        ->get("bookmark_post")
        ->result();

    if (empty($bookmarks)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Bookmark Posts List Not Found",
            "bookmark_post_list" => [],
            "status" => "success"
        ]);
        return;
    }

    $postDetails = [];

    foreach ($bookmarks as $bookmark) {

        $post = $this->db
            ->where("post_id", $bookmark->post_id)
            ->where("is_delete", "0")
            ->get("posts")
            ->row();

        if (!$post) {
            continue;
        }

        $postData = [
            "bookmark_post_id" => (string) $bookmark->bookmark_id,
            "post_id"          => (string) $post->post_id,
            "user_id"          => (string) $post->user_id,
            "text"             => $post->text ?? "",
            "type"             => $bookmark->type ?? "",
            "location"         => $post->location ?? "",
            "created_at"       => $post->created_at ?? "",
            "updated_at"       => $post->updated_at ?? "",
        ];

        /* ---------------------------------
           POST IMAGES
        ----------------------------------*/
        $images = $this->db
            ->select("id, post_id, new_post")
            ->where("post_id", $post->post_id)
            ->get("post_image")
            ->result();

        $post_images = [];
        foreach ($images as $img) {
            $url = (strpos($img->new_post, 'http') === 0)
                ? $img->post_image
                : base_url($img->new_post);

            $post_images[] = [
                "post_image_id" => $img->id,
                "url" => $url,
                "type" => "image"
            ];
        }
        $postData["post_images"] = $post_images;

        /* ---------------------------------
           POST VIDEOS
        ----------------------------------*/
        $videos = $this->db
            ->select("id, post_id, post_video, post_video_thumbnail")
            ->where("post_id", $post->post_id)
            ->get("post_video")
            ->result();

        $post_videos = [];
        foreach ($videos as $vid) {

            $video_url = (strpos($vid->post_video, 'http') === 0)
                ? $vid->post_video
                : base_url($vid->post_video);

            $thumb_url = "";
            if (!empty($vid->post_video_thumbnail)) {
                $thumb_url = (strpos($vid->post_video_thumbnail, 'http') === 0)
                    ? $vid->post_video_thumbnail
                    : base_url( $vid->post_video_thumbnail);
            }

            $post_videos[] = [
                "post_video_id" => $vid->id,
                "url" => $video_url,
                "type" => "video",
                "post_video_thumbnail" => $thumb_url
            ];
        }
        $postData["post_videos"] = $post_videos;

        /* ---------------------------------
           USER DETAILS
        ----------------------------------*/
        $post_user = $this->db
            ->where("id", $post->user_id)
            ->get("users")
            ->row();

        $postData["username"] = $post_user->username ?? "";
        $postData["profile_pic"] = (!empty($post_user->profile_pic))
            ? adv_profile_pic_url($post_user->profile_pic)
            : "";

        /* ---------------------------------
           LIKE / COMMENT / SHARE COUNTS
        ----------------------------------*/
        $is_like = $this->db
            ->where("post_id", $post->post_id)
            ->where("user_id", $user_id)
            ->get("post_like")
            ->row();

        $post_like = $this->db
            ->where("post_id", $post->post_id)
            ->count_all_results("post_like");

        $post_comment = $this->db
            ->where("post_id", $post->post_id)
            ->count_all_results("post_comment");

        $post_share = $this->db
            ->where("type", "post")
            ->where("reel_id", $post->post_id)
            ->count_all_results("chats");

        $postData["is_liked"] = $is_like ? "1" : "0";
        $postData["total_like"] = (string) $post_like;
        $postData["total_comment"] = (string) $post_comment;
        $postData["total_share"] = (string) $post_share;
        $postData["is_bookmark"] = "1";

        /* ---------------------------------
           TAG USER LIST
        ----------------------------------*/
        $tag_users = $this->db
            ->where("post_id", $post->post_id)
            ->get("post_user_tags")
            ->result();

        $tag_user_list = [];
        foreach ($tag_users as $tag) {

            $tagUser = $this->db
                ->where("id", $tag->tag_users)
                ->get("users")
                ->row();

            if (!$tagUser) continue;

            $tag_user_list[] = [
                "tag_user_id" => $tagUser->id,
                "first_name"  => $tagUser->first_name ?? "",
                "last_name"   => $tagUser->last_name ?? "",
                "profile_pic" => (!empty($tagUser->profile_pic))
                    ? adv_profile_pic_url($tagUser->profile_pic)
                    : ""
            ];
        }

        $postData["tag_user_list"] = $tag_user_list;

        $postDetails[] = $postData;
    }

    /* ---------------------------------
       FINAL RESPONSE
    ----------------------------------*/
    echo json_encode([
        "response_code" => "1",
        "message" => "Bookmark Posts List Found",
        "bookmark_post_list" => $postDetails,
        "status" => "success"
    ]);
}

public function bookmark_post_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    /* -----------------------------
       1️⃣ READ JSON INPUT
    ------------------------------*/
    $input   = json_decode(file_get_contents("php://input"), true);
    $post_id = $input['post_id'] ?? "";
    $type    = $input['type'] ?? "";

    if (empty($post_id) || empty($type)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "post_id and type are required",
            "status" => "failure"
        ]);
        return;
    }

    /* -----------------------------
       2️⃣ AUTH TOKEN
    ------------------------------*/
    $authHeader = $this->input->get_request_header("Authorization", TRUE);
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Unauthorized user",
            "status" => "failure"
        ]);
        return;
    }

    $token = $matches[1];

    $user = $this->db
        ->where("token", $token)
        ->where("token_expiry >=", date("Y-m-d H:i:s"))
        ->get("users")
        ->row();

    if (!$user) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Invalid token",
            "status" => "failure"
        ]);
        return;
    }

    $user_id = $user->id;

    /* -----------------------------
       3️⃣ CHECK BOOKMARK
    ------------------------------*/
    $bookmarkCheck = $this->db
        ->where("user_id", $user_id)
        ->where("post_id", $post_id)
        ->where("type", $type)
        ->count_all_results("bookmark_post");

    if ($bookmarkCheck == 1) {

        $this->db
            ->where("user_id", $user_id)
            ->where("post_id", $post_id)
            ->where("type", $type)
            ->delete("bookmark_post");

        echo json_encode([
            "response_code" => "1",
            "message" => "Bookmark Removed Successfully",
            "status" => "success"
        ]);
        return;
    }

    /* -----------------------------
       4️⃣ INSERT BOOKMARK
    ------------------------------*/
    $data = [
        "user_id"    => $user_id,
        "post_id"    => $post_id,
        "type"       => $type,
        "date"       => date("Y-m-d"),              // ✅ 2025-12-19
        "created_at" => date("Y-m-d H:i:s")         // ✅ Asia/Kolkata datetime
    ];

    if ($this->db->insert("bookmark_post", $data)) {
        echo json_encode([
            "response_code" => "1",
            "message" => "Bookmark Post Added Successfully",
            "status" => "success"
        ]);
    } else {
        echo json_encode([
            "response_code" => "0",
            "message" => "Database Error",
            "status" => "failure"
        ]);
    }
}
public function comment_like_reel_post()
{
    header('Content-Type: application/json');
    date_default_timezone_set('Asia/Kolkata');

    // 🔹 Get Bearer token from Authorization header
    $headers = apache_request_headers();
    $token = null;
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            $token = $matches[1];
        }
    }

    if (empty($token)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Authorization token is missing",
            "status"  => "failed"
        ]);
        return;
    }

    // 🔹 Validate token and get user
    $this->db->where('token', $token);
    $this->db->where('token_expiry >=', date('Y-m-d H:i:s'));
    $userQuery = $this->db->get('users');

    if ($userQuery->num_rows() == 0) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Invalid or expired token",
            "status"  => "failed"
        ]);
        return;
    }

    $user = $userQuery->row();
    $user_id = $user->id;

    // 🔹 Read input
    $input = json_decode(file_get_contents('php://input'), true);
    $reel_id = isset($input['reel_id']) ? $input['reel_id'] : '';
    $reel_comment_id = isset($input['reel_comment_id']) ? $input['reel_comment_id'] : '';
    $post_id = isset($input['post_id']) ? $input['post_id'] : 0;

    if ($reel_id == '' && $reel_comment_id == '') {
        echo json_encode([
            'response_code' => 0,
            'message' => 'Enter Data',
            'status' => 'failure'
        ]);
        return;
    }

    // 🔹 Check if already liked
    $like_count = $this->db
        ->where('user_id', $user_id)
        ->where('reel_id', $reel_id)
        ->where('reel_comment_id', $reel_comment_id)
        ->count_all_results('reel_comment_like');

    $current_date = date('Y-m-d'); // YYYY-MM-DD format

    if ($like_count == 1) {
        // 🔹 Unlike
        $this->db
            ->where('user_id', $user_id)
            ->where('reel_id', $reel_id)
            ->where('reel_comment_id', $reel_comment_id)
            ->delete('reel_comment_like');

        echo json_encode([
            'response_code' => 1,
            'message' => 'Comment UnLiked Reel',
            'status' => 'success'
        ]);
        return;
    }

    if ($like_count == 0) {
        // 🔹 Like
        $this->db->insert('reel_comment_like', [
            'user_id' => $user_id,
            'reel_id' => $reel_id,
            'reel_comment_id' => $reel_comment_id,
            'date' => $current_date
        ]);

        // 🔹 Fetch comment info
        $comment = $this->db
            ->where('reel_id', $reel_id)
            ->where('id', $reel_comment_id)
            ->get('reel_comment')
            ->row();

        $to_user = $comment->user_id ?? 0;

        if ($user_id == $to_user) {
            echo json_encode([
                'response_code' => 1,
                'message' => 'Comment Reel Like successful, but no notification sent for your own post',
                'status' => 'success'
            ]);
            return;
        }

        // 🔹 Notification
        $FcmToken = $this->db->select('device_token')->where('id', $to_user)->get('users')->row()->device_token ?? null;

        $proviver_noti = $this->db->where('id', 6)->where('status', 1)->get('notification_permissions')->row();
        $user_name = $this->db->where('id', $user_id)->get('users')->row();
        $username = $user_name->username ?? '';
        $message = str_replace(['[[ username ]]', '[[ time ]]'], [$username, ''], $proviver_noti->description ?? '');

        if ($FcmToken) {
            $data = [
                'title' => $proviver_noti->title ?? 'Reel Comment Like',
                'message' => $message,
                'type' => $proviver_noti->type ?? 'comment_like_post',
                'post_id' => $reel_id,
                'from_user' => $user_id,
                'to_user' => $to_user,
                'post_user_id' => $to_user
            ];
            $this->sendNotification($data, $FcmToken);
        }

        // 🔹 Insert notification record
        $not_all = [
            'from_user' => $user_id,
            'to_user' => $to_user,
            'title' => $proviver_noti->title ?? 'Reel Comment Like',
            'post_id' => $reel_id,
            'message' => $message,
            'notifiable_type' => $proviver_noti->type ?? 'comment_like_post',
            'created_at' => date('Y-m-d H:i:s'),
            'post_user_id' => $to_user
        ];

        $this->db->insert('user_notifications', $not_all);

        echo json_encode([
            'response_code' => 1,
            'message' => 'Comment Reel Like successful',
            'status' => 'success'
        ]);
        return;
    }

    echo json_encode([
        'response_code' => 1,
        'message' => 'Already Liked Reel',
        'status' => 'success'
    ]);
}


public function sub_comment_like_reel_post()
{
    header('Content-Type: application/json');
    date_default_timezone_set('Asia/Kolkata');

    // 🔹 Get Bearer token from Authorization header
    $headers = apache_request_headers();
    $token = null;
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            $token = $matches[1];
        }
    }

    if (empty($token)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Authorization token is missing",
            "status"  => "failed"
        ]);
        return;
    }

    // 🔹 Validate token and get user
    $this->db->where('token', $token);
    $this->db->where('token_expiry >=', date('Y-m-d H:i:s'));
    $userQuery = $this->db->get('users');

    if ($userQuery->num_rows() == 0) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Invalid or expired token",
            "status"  => "failed"
        ]);
        return;
    }

    $user = $userQuery->row();
    $user_id = $user->id;

    // 🔹 Read input
    $input = json_decode(file_get_contents('php://input'), true);
    $reel_id = isset($input['reel_id']) ? $input['reel_id'] : '';
    $reel_sub_comment_id = isset($input['reel_sub_comment_id']) ? $input['reel_sub_comment_id'] : '';

    if ($reel_id == '' && $reel_sub_comment_id == '') {
        echo json_encode([
            'response_code' => 0,
            'message' => 'Enter Data',
            'status' => 'failure'
        ]);
        return;
    }

    // 🔹 Check if already liked
    $like_count = $this->db
        ->where('user_id', $user_id)
        ->where('reel_id', $reel_id)
        ->where('reel_sub_comment_id', $reel_sub_comment_id)
        ->count_all_results('reel_sub_comment_like');

    $current_date = date('Y-m-d');

    if ($like_count == 1) {
        // 🔹 Unlike
        $this->db
            ->where('user_id', $user_id)
            ->where('reel_id', $reel_id)
            ->where('reel_sub_comment_id', $reel_sub_comment_id)
            ->delete('reel_sub_comment_like');

        echo json_encode([
            'response_code' => 1,
            'message' => 'SubComment UnLiked Reel',
            'status' => 'success'
        ]);
        return;
    }

    if ($like_count == 0) {
        // 🔹 Like
        $this->db->insert('reel_sub_comment_like', [
            'user_id' => $user_id,
            'reel_id' => $reel_id,
            'reel_sub_comment_id' => $reel_sub_comment_id,
            'date' => $current_date
        ]);

        // 🔹 Fetch sub-comment info
        $comment = $this->db
            ->where('id', $reel_sub_comment_id)
            ->where('reel_id', $reel_id)
            ->get('reel_sub_comment')
            ->row();

        $to_user = $comment->user_id ?? 0;

        if ($user_id == $to_user) {
            echo json_encode([
                'response_code' => 1,
                'message' => 'SubComment Reel Like successful, but no notification sent for your own post',
                'status' => 'success'
            ]);
            return;
        }

        // 🔹 Notification
        $FcmToken = $this->db->select('device_token')->where('id', $to_user)->get('users')->row()->device_token ?? null;

        $proviver_noti = $this->db->where('id', 10)->where('status', 1)->get('notification_permissions')->row();
        $user_name = $this->db->where('id', $user_id)->get('users')->row();
        $username = $user_name->username ?? '';
        $message = str_replace(['[[ username ]]', '[[ time ]]'], [$username, ''], $proviver_noti->description ?? '');

        if ($FcmToken) {
            $data = [
                'title' => $proviver_noti->title ?? 'Reel SubComment Like',
                'message' => $message,
                'type' => $proviver_noti->type ?? 'subcomment_reel_like',
                'post_id' => $reel_id,
                'from_user' => $user_id,
                'to_user' => $to_user,
                'post_user_id' => $to_user
            ];
            $this->sendNotification($data, $FcmToken);
        }

        // 🔹 Insert notification record
        $not_all = [
            'from_user' => $user_id,
            'to_user' => $to_user,
            'title' => $proviver_noti->title ?? 'Reel SubComment Like',
            'post_id' => $reel_id,
            'message' => $message,
            'notifiable_type' => $proviver_noti->type ?? 'subcomment_reel_like',
            'created_at' => date('Y-m-d H:i:s'),
            'post_user_id' => $to_user
        ];

        $this->db->insert('user_notifications', $not_all);

        echo json_encode([
            'response_code' => 1,
            'message' => 'SubComment Reel Like successful',
            'status' => 'success'
        ]);
        return;
    }

    echo json_encode([
        'response_code' => 1,
        'message' => 'Already Liked Reel',
        'status' => 'success'
    ]);
}



public function comment_like_post_post()
{
    header('Content-Type: application/json');
    date_default_timezone_set('Asia/Kolkata');

    /* =========================
       1️⃣ TOKEN AUTH
    ========================= */
    $headers = apache_request_headers();
    $token = null;

    if (isset($headers['Authorization']) && preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $m)) {
        $token = $m[1];
    }

    if (!$token) {
        echo json_encode([
            "response_code" => 0,
            "message" => "Authorization token missing",
            "status" => "failure"
        ]);
        return;
    }

    $user = $this->db
        ->where('token', $token)
        ->where('token_expiry >=', date('Y-m-d H:i:s'))
        ->get('users')
        ->row();

    if (!$user) {
        echo json_encode([
            "response_code" => 0,
            "message" => "Invalid or expired token",
            "status" => "failure"
        ]);
        return;
    }

    $user_id = $user->id;

    /* =========================
       2️⃣ INPUT JSON
    ========================= */
    $input = json_decode(file_get_contents("php://input"), true);

    $post_id = $input['post_id'] ?? '';
    $post_comment_id = $input['post_comment_id'] ?? '';

    if ($post_id == '' || $post_comment_id == '') {
        echo json_encode([
            "response_code" => 0,
            "message" => "Enter Data",
            "status" => "failure"
        ]);
        return;
    }

    /* =========================
       3️⃣ LIKE CHECK
    ========================= */
    $like = $this->db
        ->where('user_id', $user_id)
        ->where('post_id', $post_id)
        ->where('post_comment_id', $post_comment_id)
        ->count_all_results('post_comment_like');

    $today = date('Y-m-d'); // y:m:d format

    /* =========================
       4️⃣ UNLIKE
    ========================= */
    if ($like == 1) {
        $this->db
            ->where('user_id', $user_id)
            ->where('post_id', $post_id)
            ->where('post_comment_id', $post_comment_id)
            ->delete('post_comment_like');

        echo json_encode([
            "response_code" => 1,
            "message" => "Comment UnLiked Post",
            "status" => "success"
        ]);
        return;
    }

    /* =========================
       5️⃣ LIKE INSERT
    ========================= */
    $this->db->insert('post_comment_like', [
        'user_id' => $user_id,
        'post_id' => $post_id,
        'post_comment_id' => $post_comment_id,
        'date' => $today
    ]);

    /* =========================
       6️⃣ GET COMMENT OWNER
    ========================= */
    $comment = $this->db
        ->select('user_id')
        ->from('post_comment')
        ->where('id', $post_comment_id)
        ->where('post_id', $post_id)
        ->get()
        ->row();

    if (!$comment || !$comment->user_id) {
        echo json_encode([
            "response_code" => 1,
            "message" => "Like done successful",
            "status" => "success"
        ]);
        return;
    }

    $to_user = $comment->user_id;

    /* =========================
       7️⃣ SELF LIKE CHECK
    ========================= */
    if ($user_id == $to_user) {
        echo json_encode([
            "response_code" => 1,
            "message" => "Comment Post Like successful",
            "status" => "success"
        ]);
        return;
    }

    /* =========================
       8️⃣ NOTIFICATION DATA
    ========================= */
    $provider_noti = $this->db
        ->where('id', 5)
        ->where('status', 1)
        ->get('notification_permissions')
        ->row();

    $username = $user->username ?? '';

    $message = str_replace(
        ['[[ username ]]', '[[ time ]]'],
        [$username, ''],
        $provider_noti->description ?? 'liked your comment on the post'
    );

    /* =========================
       9️⃣ USER_NOTIFICATION INSERT
    ========================= */
    $this->db->insert('user_notifications', [
        'from_user'        => $user_id,
        'to_user'          => $to_user,          // ✅ FIXED
        'post_user_id'     => $to_user,          // ✅ FIXED
        'post_id'          => $post_id,
        'title'            => $provider_noti->title ?? 'Post Comment Like',
        'message'          => $message,
        'notifiable_type'  => $provider_noti->type ?? 'comment_like_post',
        'created_at'       => date('Y-m-d H:i:s')
    ]);

    /* =========================
       🔟 RESPONSE
    ========================= */
    echo json_encode([
        "response_code" => 1,
        "message" => "Comment Post Like successful",
        "status" => "success"
    ]);
}


public function sub_comment_like_post_post()
{
    header('Content-Type: application/json');
    date_default_timezone_set('Asia/Kolkata');

    /* =========================
       1️⃣ TOKEN AUTH
    ========================= */
    $headers = apache_request_headers();
    $token = null;

    if (isset($headers['Authorization']) && preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $m)) {
        $token = $m[1];
    }

    if (!$token) {
        echo json_encode([
            "response_code" => 0,
            "message" => "Authorization token missing",
            "status" => "failure"
        ]);
        return;
    }

    $user = $this->db
        ->where('token', $token)
        ->where('token_expiry >=', date('Y-m-d H:i:s'))
        ->get('users')
        ->row();

    if (!$user) {
        echo json_encode([
            "response_code" => 0,
            "message" => "Invalid or expired token",
            "status" => "failure"
        ]);
        return;
    }

    $user_id = $user->id;

    /* =========================
       2️⃣ READ JSON INPUT
    ========================= */
    $input = json_decode(file_get_contents("php://input"), true);

    $post_id = $input['post_id'] ?? '';
    $post_sub_comment_id = $input['post_sub_comment_id'] ?? '';

    if ($post_id == '' || $post_sub_comment_id == '') {
        echo json_encode([
            "response_code" => 0,
            "message" => "Enter Data",
            "status" => "failure"
        ]);
        return;
    }

    /* =========================
       3️⃣ CHECK LIKE
    ========================= */
    $like = $this->db
        ->where('user_id', $user_id)
        ->where('post_id', $post_id)
        ->where('post_sub_comment_id', $post_sub_comment_id)
        ->count_all_results('post_sub_comment_like');

    $today = date('Y-m-d'); // y:m:d format

    /* =========================
       4️⃣ UNLIKE
    ========================= */
    if ($like == 1) {
        $this->db
            ->where('user_id', $user_id)
            ->where('post_id', $post_id)
            ->where('post_sub_comment_id', $post_sub_comment_id)
            ->delete('post_sub_comment_like');

        echo json_encode([
            "response_code" => 1,
            "message" => "SubComment UnLiked Post",
            "status" => "success"
        ]);
        return;
    }

    /* =========================
       5️⃣ LIKE INSERT
    ========================= */
    $this->db->insert('post_sub_comment_like', [
        'user_id' => $user_id,
        'post_id' => $post_id,
        'post_sub_comment_id' => $post_sub_comment_id,
        'date' => $today
    ]);

    /* =========================
       6️⃣ GET SUB COMMENT OWNER
    ========================= */
    $sub_comment = $this->db
        ->select('user_id')
        ->from('post_sub_comment')
        ->where('id', $post_sub_comment_id)
        ->where('post_id', $post_id)
        ->get()
        ->row();

    if (!$sub_comment || !$sub_comment->user_id) {
        echo json_encode([
            "response_code" => 1,
            "message" => "Like done but sub comment owner not found",
            "status" => "success"
        ]);
        return;
    }

    $to_user = $sub_comment->user_id;

    /* =========================
       7️⃣ SELF LIKE CHECK
    ========================= */
    if ($user_id == $to_user) {
        echo json_encode([
            "response_code" => 1,
            "message" => "SubComment Post Like successful",
            "status" => "success"
        ]);
        return;
    }

    /* =========================
       8️⃣ NOTIFICATION DATA
    ========================= */
    $provider_noti = $this->db
        ->where('id', 9)
        ->where('status', 1)
        ->get('notification_permissions')
        ->row();

    $username = $user->username ?? '';

    $message = str_replace(
        ['[[ username ]]', '[[ time ]]'],
        [$username, ''],
        $provider_noti->description ?? 'liked your reply on the post'
    );

    /* =========================
       9️⃣ INSERT USER_NOTIFICATION
    ========================= */
    $this->db->insert('user_notifications', [
        'from_user'        => $user_id,
        'to_user'          => $to_user,          // ✅ FIXED
        'post_user_id'     => $to_user,          // ✅ FIXED
        'post_id'          => $post_id,
        'title'            => $provider_noti->title ?? 'Post SubComment Like',
        'message'          => $message,
        'notifiable_type'  => $provider_noti->type ?? 'subcomment_post_like',
        'created_at'       => date('Y-m-d H:i:s')
    ]);

    /* =========================
       🔟 RESPONSE
    ========================= */
    echo json_encode([
        "response_code" => 1,
        "message" => "SubComment Post Like successful",
        "status" => "success"
    ]);
}


public function all_tag_post_user_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    /* ===============================
       1️⃣ AUTH TOKEN → USER ID
    =============================== */
    $authHeader = $this->input->get_request_header('Authorization', TRUE);

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            'status' => '0',
            'message' => 'Unauthorized'
        ]);
        return;
    }

    $token = $matches[1];

    $user = $this->db
        ->where('token', $token)
        ->where('token_expiry >=', date('Y-m-d H:i:s'))
        ->get('users')
        ->row();

    if (!$user) {
        echo json_encode([
            'status' => '0',
            'message' => 'Invalid Token'
        ]);
        return;
    }

    $user_id = $user->id;

    /* ===============================
       2️⃣ READ JSON INPUT
    =============================== */
    $rawInput = file_get_contents("php://input");
    $input = json_decode($rawInput, true);

    $post_id = $input['post_id'] ?? '';

    if (empty($post_id)) {
        echo json_encode([
            'status' => '0',
            'message' => 'post_id required'
        ]);
        return;
    }

    /* ===============================
       3️⃣ GET TAG USERS
    =============================== */
    $posts = $this->db
        ->where('post_id', $post_id)
        ->order_by('tag_id', 'DESC')
        ->get('post_user_tags')
        ->result();

    $list_notification = [];

    foreach ($posts as $notification) {

        $row = [];

        $row['tag_id']     = (string)$notification->tag_id;
        $row['user_id']    = (string)($notification->user_id ?? '');
        $row['post_id']    = (string)($notification->post_id ?? '');
        $row['created_at'] = $notification->created_at ?? '';
        $row['tag_users']  = trim($notification->tag_users ?? '');

        /* ===============================
           4️⃣ FOLLOW STATUS
        =============================== */
        $is_follow = $this->db
            ->where('to_user', $notification->tag_users)
            ->where('from_user', $user_id)
            ->get('follow')
            ->row();

        $row['is_follow'] = $is_follow ? '1' : '0';

        $this->db->reset_query();

        $is_following = $this->db
            ->where('from_user', $notification->tag_users)
            ->where('to_user', $user_id)
            ->get('follow')
            ->row();

        $row['is_following'] = $is_following ? '1' : '0';

        /* ===============================
           5️⃣ USER DETAILS
        =============================== */
        $tag_user = $this->db
            ->where('id', $notification->tag_users)
            ->get('users')
            ->row();

        if ($tag_user) {

            $row['first_name'] = $tag_user->first_name ?? '';
            $row['last_name']  = $tag_user->last_name ?? '';
            $row['username']   = $tag_user->username ?? '';

            // 🔥 Profile pic safe URL
            if (!empty($tag_user->profile_pic)) {
                if (preg_match('/^https?:\/\//', $tag_user->profile_pic)) {
                    $row['profile_pic'] = $tag_user->profile_pic;
                } else {
                    $row['profile_pic'] = base_url($tag_user->profile_pic);
                }
            } else {
                $row['profile_pic'] = '';
            }

        } else {
            $row['profile_pic'] = '';
            $row['first_name']  = '';
            $row['last_name']   = '';
            $row['username']    = '';
        }

        $list_notification[] = $row;
    }

    /* ===============================
       6️⃣ RESPONSE
    =============================== */
    echo json_encode([
        'status'  => '1',
        'message' => 'My Tag List Found',
        'post'    => $list_notification
    ]);
}



public function user_add_report_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    /* ===============================
       1️⃣ AUTH TOKEN → USER
    =============================== */
    $authHeader = $this->input->get_request_header('Authorization', TRUE);

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            'response_code' => 0,
            'status' => 'failure',
            'message' => 'Unauthorized'
        ]);
        return;
    }

    $token = $matches[1];

    $user = $this->db
        ->where('token', $token)
        ->where('token_expiry >=', date('Y-m-d H:i:s'))
        ->get('users')
        ->row();

    if (!$user) {
        echo json_encode([
            'response_code' => 0,
            'status' => 'failure',
            'message' => 'Invalid Token'
        ]);
        return;
    }

    $user_id = $user->id;

    /* ===============================
       2️⃣ READ JSON INPUT
    =============================== */
    $input = json_decode(file_get_contents("php://input"), true);

    $to_user_id     = $input['to_user_id'] ?? '';
    $report_text_id = $input['report_text_id'] ?? '';
    $post_id        = $input['post_id'] ?? null;

    if (empty($to_user_id) || empty($report_text_id)) {
        echo json_encode([
            'response_code' => 0,
            'status' => 'failure',
            'message' => 'Enter Data'
        ]);
        return;
    }

    /* ===============================
       3️⃣ INSERT USER REPORT
    =============================== */
    $currentDate = date('Y-m-d H:i:s'); // ✅ FIXED DATE

    $reportData = [
        'user_id'        => $user_id,
        'to_user_id'     => $to_user_id,
        'report_text_id' => $report_text_id,
        'date'           => $currentDate
    ];

    if (!$this->db->insert('user_report', $reportData)) {
        echo json_encode([
            'response_code' => 0,
            'status' => 'failure',
            'message' => 'Database Error'
        ]);
        return;
    }

    /* ===============================
       4️⃣ GET USERNAMES
    =============================== */
    $from_username = $this->db->select('username')->where('id', $user_id)->get('users')->row()->username ?? '';
    $to_username   = $this->db->select('username')->where('id', $to_user_id)->get('users')->row()->username ?? '';

    /* ===============================
       5️⃣ ADD APP NOTIFICATION
    =============================== */
    $notificationData = [
        'from_user' => $user_id,
        'to_user'   => $to_user_id,
        'post_id'   => $post_id,
        'not_type'  => '0',
        'title'     => 'User Report',
        'message'   => "$to_username has been reported by $from_username",
        'date'      => $currentDate
    ];

    $this->db->insert('app_notification', $notificationData);

    /* ===============================
       6️⃣ SUCCESS RESPONSE
    =============================== */
    echo json_encode([
        'response_code' => 1,
        'status' => 'success',
        'message' => 'User Report Add Successfully'
    ]);
}




public function get_report_text_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    /* ===============================
       1️⃣ READ JSON INPUT
    =============================== */
    $rawInput = file_get_contents("php://input");
    $input = json_decode($rawInput, true);

    $type = $input['type'] ?? '';

    /* ===============================
       2️⃣ VALIDATION
    =============================== */
    if (empty($type)) {
        echo json_encode([
            'response_code' => '0',
            'message'       => 'Fields is mandatatry.',
            'status'        => 'failure'
        ]);
        return;
    }

    /* ===============================
       3️⃣ TYPE LOGIC (image → post)
    =============================== */
    if ($type === 'image') {
        $type = 'post';
    }

    /* ===============================
       4️⃣ FETCH REPORT TEXT
    =============================== */
    $category = $this->db
        ->where('type', $type)
        ->get('report_text')
        ->result();

    $all_report_text = [];

    foreach ($category as $row) {
        $all_report_text[] = [
            'id'   => (string)$row->id,
            'text' => (string)$row->text,
            'type' => (string)$row->type
        ];
    }

    /* ===============================
       5️⃣ RESPONSE
    =============================== */
    if (!empty($all_report_text)) {

        echo json_encode([
            'response_code'   => '1',
            'message'         => 'All Report Text Found',
            'status'          => 'success',
            'all_report_text' => $all_report_text
        ]);

    } else {

        echo json_encode([
            'response_code'   => '0',
            'message'         => 'All Report Text List Not Found',
            'status'          => 'failure',
            'all_report_text' => []
        ]);
    }
}


public function post_add_report_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    /* ===============================
       1️⃣ AUTH TOKEN → USER ID
    =============================== */
    $authHeader = $this->input->get_request_header('Authorization', TRUE);

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            'response_code' => '0',
            'message'       => 'Authorization token required',
            'status'        => 'failure'
        ]);
        return;
    }

    $token = $matches[1];

    $tokenRow = $this->db
        ->where('token', $token)
        ->get('users')
        ->row();

    if (!$tokenRow) {
        echo json_encode([
            'response_code' => '0',
            'message'       => 'Invalid token',
            'status'        => 'failure'
        ]);
        return;
    }

    $user_id = (int)$tokenRow->id;

    /* ===============================
       2️⃣ READ JSON INPUT
    =============================== */
    $input = json_decode(file_get_contents("php://input"), true);

    $post_id        = $input['post_id'] ?? '';
    $report_text_id = $input['report_text_id'] ?? '';

    if ($post_id == '' || $report_text_id == '') {
        echo json_encode([
            'response_code' => '0',
            'message'       => 'Enter Data',
            'status'        => 'failure'
        ]);
        return;
    }

    /* ===============================
       3️⃣ INSERT POST REPORT
       ✅ FIXED DATE FORMAT
    =============================== */
    $date = date('Y-m-d H:i:s');

    $this->db->insert('post_report', [
        'user_id'        => $user_id,
        'post_id'        => (int)$post_id,
        'report_text_id' => (int)$report_text_id,
        'date'           => $date
    ]);

    /* ===============================
       4️⃣ FETCH POST OWNER
    =============================== */
    $post = $this->db
        ->where('post_id', $post_id)
        ->get('posts')
        ->row();

    if (!$post) {
        echo json_encode([
            'response_code' => '0',
            'message'       => 'Post not found',
            'status'        => 'failure'
        ]);
        return;
    }

    $to_user = (int)$post->user_id;

    /* ===============================
       5️⃣ USERNAMES
    =============================== */
    $tuser = $this->db->select('username')->where('id', $user_id)->get('users')->row()->username ?? '';
    $fuser = $this->db->select('username')->where('id', $to_user)->get('users')->row()->username ?? '';

    /* ===============================
       6️⃣ NOTIFICATION
       ✅ FIXED DATE FORMAT
    =============================== */
    $this->db->insert('app_notification', [
        'from_user' => $user_id,
        'to_user'   => $to_user,
        'post_id'   => $post_id,
        'not_type'  => '0',
        'message'   => "$tuser's post has been reported by $fuser",
        'title'     => 'Post Report',
        'date'      => date('Y-m-d H:i:s')
    ]);

    echo json_encode([
        'response_code' => 1,
        'message'       => 'Post Report Add Successfully',
        'status'        => 'success'
    ]);
}



public function second_user_all_reel_pagination_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    /* ===============================
       1️⃣ AUTH TOKEN
    ===============================*/
    $authHeader = $this->input->get_request_header("Authorization", TRUE);
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "status" => "Failure",
            "current_page" => 1,
            "last_page" => 1,
            "reels" => []
        ]);
        return;
    }

    $token = $matches[1];

    $loginUser = $this->db
        ->where("token", $token)
        ->where("token_expiry >=", date("Y-m-d H:i:s"))
        ->get("users")
        ->row();

    if (!$loginUser) {
        echo json_encode([
            "status" => "Failure",
            "current_page" => 1,
            "last_page" => 1,
            "reels" => []
        ]);
        return;
    }

    $viewer_id = (int)$loginUser->id;

    /* ===============================
       2️⃣ INPUT
    ===============================*/
    $input = json_decode(file_get_contents("php://input"), true);

    $to_user_id = (int)($input['to_user_id'] ?? 0);
    $page_no    = (int)($input['page_no'] ?? 1);
    $per_page   = (int)($input['per_page'] ?? 10);

    if ($to_user_id <= 0) {
        echo json_encode([
            "status" => "Failure",
            "current_page" => $page_no,
            "last_page" => 1,
            "reels" => []
        ]);
        return;
    }

    $offset = ($page_no - 1) * $per_page;

    /* ===============================
       3️⃣ TOTAL COUNT
    ===============================*/
    $total_reels = $this->db
        ->where("user_id", $to_user_id)
        ->where("post_type", "reel")
        ->where("status", 1)
        ->where("is_delete", 0)
        ->count_all_results("posts");

    $last_page = ($total_reels > 0) ? ceil($total_reels / $per_page) : 1;

    /* ===============================
       4️⃣ FETCH REELS
    ===============================*/
    $posts = $this->db
        ->where("user_id", $to_user_id)
        ->where("post_type", "reel")
        ->where("status", 1)
        ->where("is_delete", 0)
        ->order_by("post_id", "DESC")
        ->limit($per_page, $offset)
        ->get("posts")
        ->result();

    $reels = [];

    foreach ($posts as $post) {

        $is_likes = $this->db
            ->where("reel_id", $post->post_id)
            ->where("user_id", $viewer_id)
            ->count_all_results("reel_like") > 0 ? "1" : "0";

        $total_likes = (int)$this->db
            ->where("reel_id", $post->post_id)
            ->count_all_results("reel_like");

        $total_comments = (int)$this->db
            ->where("reel_id", $post->post_id)
            ->count_all_results("reel_comment");

        $bookmark = $this->db
            ->where("post_id", $post->post_id)
            ->where("user_id", $viewer_id)
            ->count_all_results("bookmark_post") > 0 ? "1" : "0";

        $total_view = (int)$this->db
            ->where("reel_id", $post->post_id)
            ->count_all_results("view_reel");

        /* ===============================
           VIDEO
        ===============================*/
        $videoArr = [];
        $videos = $this->db->where("post_id", $post->post_id)->get("post_video")->result();

        foreach ($videos as $vid) {
            $videoArr[] = [
                "reel_image_id" => (int)$vid->id,
                "reel_video" => !empty($vid->post_video)
                    ? base_url(ltrim($vid->post_video, '/'))
                    : "",
                "type" => "video",
                "reel_video_thumbnail" => !empty($vid->post_video_thumbnail)
                    ? base_url(ltrim($vid->post_video_thumbnail, '/'))
                    : ""
            ];
        }

        /* ===============================
           FINAL REEL OBJECT
        ===============================*/
        $reels[] = [
            "reel_id" => (string)$post->post_id,
            "user_id" => (string)$post->user_id,
            "title" => $post->text ?? "",
            "description" => $post->text ?? "",
            "video" => $videoArr,
            "created_at" => date("Y-m-d", strtotime($post->created_at)),
            "is_likes" => $is_likes,
            "total_likes" => $total_likes,
            "total_comments" => $total_comments,
            "bookmark" => $bookmark,
            "total_view" => $total_view
        ];
    }

    /* ===============================
       5️⃣ FINAL RESPONSE
    ===============================*/
    echo json_encode([
        "status" => "Success",
        "current_page" => $page_no,
        "last_page" => $last_page,
        "reels" => $reels
    ]);
}




public function second_user_tag_post_pagination_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    $BASE_URL = "https://insta.vihaanshika.com/";

    /* ===============================
      1️⃣ AUTH TOKEN
    =============================== */
    $authHeader = $this->input->get_request_header('Authorization', TRUE);

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "status" => "Failed",
            "message" => "Authorization token missing",
            "post" => []
        ]);
        return;
    }

    $token  = $matches[1];
    $viewer = $this->db->get_where('users', ['token' => $token])->row();

    if (!$viewer) {
        echo json_encode([
            "status" => "Failed",
            "message" => "Invalid token",
            "post" => []
        ]);
        return;
    }

    $viewer_id = (int)$viewer->id;

    /* ===============================
      2️⃣ INPUT
    =============================== */
    $input = json_decode(file_get_contents("php://input"), true);

    $to_user_id = (int)($input['to_user_id'] ?? 0);
    $page_no    = (int)($input['page_no'] ?? 1);
    $per_page   = (int)($input['per_page'] ?? 10);

    if ($to_user_id <= 0) {
        echo json_encode([
            "status" => "Failed",
            "message" => "to_user_id required",
            "post" => []
        ]);
        return;
    }

    $offset = ($page_no - 1) * $per_page;

    /* ===============================
      3️⃣ TOTAL COUNT
    =============================== */
    $this->db->from('post_user_tags');
    $this->db->join('posts', 'posts.post_id = post_user_tags.post_id');
    $this->db->where([
        'post_user_tags.tag_users' => $to_user_id,
        'posts.is_delete' => 0,
        'posts.status' => 1
    ]);
    $total_posts = $this->db->count_all_results();
    $last_page   = ceil($total_posts / $per_page);

    /* ===============================
      4️⃣ FETCH POSTS
    =============================== */
    $this->db->select('
        posts.post_id,
        posts.user_id,
        posts.text,
        posts.location,
        posts.post_type,
        posts.created_at,
        users.username,
        users.profile_pic,
        users.avtar_id
    ');
    $this->db->from('post_user_tags');
    $this->db->join('posts', 'posts.post_id = post_user_tags.post_id');
    $this->db->join('users', 'users.id = posts.user_id');
    $this->db->where([
        'post_user_tags.tag_users' => $to_user_id,
        'posts.is_delete' => 0,
        'posts.status' => 1
    ]);
    $this->db->order_by('posts.post_id', 'DESC');
    $this->db->limit($per_page, $offset);

    $posts = $this->db->get()->result_array();

    /* ===============================
      5️⃣ BUILD RESPONSE
    =============================== */
    foreach ($posts as &$post) {

        $post_id  = (int)$post['post_id'];
        $owner_id = (int)$post['user_id'];

        /* ---------- PROFILE IMAGE (PIC ➜ AVATAR) ---------- */
        $profileImage = "";

        if (!empty($post['profile_pic'])) {

            if (filter_var($post['profile_pic'], FILTER_VALIDATE_URL)) {
                $profileImage = $post['profile_pic'];
            } else {
                $profileImage = $BASE_URL . ltrim($post['profile_pic'], '/');
            }

        } elseif (!empty($post['avtar_id'])) {

            $avatar = $this->db
                ->select('image')
                ->where('id', $post['avtar_id'])
                ->get('avtar')
                ->row();

            if (!empty($avatar) && !empty($avatar->image)) {
                $profileImage = $BASE_URL . ltrim($avatar->image, '/');
            }
        }

        $post['profile_image'] = $profileImage;
        unset($post['profile_pic'], $post['avatar_id']);

        /* ---------- FLAGS ---------- */
        $post['is_likes'] = $this->db->where([
            'post_id' => $post_id,
            'user_id' => $viewer_id
        ])->count_all_results('post_like') > 0 ? "1" : "0";

        $post['bookmark'] = $this->db->where([
            'post_id' => $post_id,
            'user_id' => $viewer_id
        ])->count_all_results('bookmark_post') > 0 ? "1" : "0";

        $post['is_follow'] = $this->db->where([
            'from_user' => $viewer_id,
            'to_user' => $owner_id
        ])->count_all_results('follow') > 0 ? "1" : "0";

        $post['is_blocked'] = $this->db->where([
            'user_id' => $owner_id,
            'block_user_id' => $viewer_id
        ])->count_all_results('profile_block') > 0 ? "1" : "0";

        $post['isBoostPost'] = "0";

        /* ---------- COUNTS ---------- */
        $post['total_likes']    = (int)$this->db->where('post_id', $post_id)->count_all_results('post_like');
        $post['total_comments'] = (int)$this->db->where('post_id', $post_id)->count_all_results('post_comment');
        $post['total_share']    = "0";

        /* ---------- MEDIA : IMAGE ---------- */
        $post['image'] = [];

        $images = $this->db->where('post_id', $post_id)->get('post_image')->result_array();
        foreach ($images as $img) {
            $post['image'][] = [
                "post_image_id" => (int)$img['id'],
                "url" => !empty($img['new_post']) ? $BASE_URL . ltrim($img['new_post'], '/') : "",
                "type" => "image",
                "post_video_thumbnail" => ""
            ];
        }

        /* ---------- MEDIA : VIDEO ---------- */
        $videos = $this->db->where('post_id', $post_id)->get('post_video')->result_array();
        foreach ($videos as $vid) {
            $post['image'][] = [
                "post_image_id" => (int)$vid['id'],
                "url" => !empty($vid['post_video']) ? $BASE_URL . ltrim($vid['post_video'], '/') : "",
                "type" => "video",
                "post_video_thumbnail" => !empty($vid['post_video_thumbnail'])
                    ? $BASE_URL . ltrim($vid['post_video_thumbnail'], '/')
                    : ""
            ];
        }

        /* ---------- TAG USERS ---------- */
        $post['tag_user_list'] = $this->db
            ->distinct()
            ->select('u.id as tag_user_id, u.first_name, u.last_name, u.profile_pic')
            ->from('post_user_tags t')
            ->join('users u', 'u.id = t.tag_users')
            ->where('t.post_id', $post_id)
            ->get()
            ->result_array();

        foreach ($post['tag_user_list'] as &$tu) {
            $tu['profile_pic'] = !empty($tu['profile_pic'])
                ? $BASE_URL . ltrim($tu['profile_pic'], '/')
                : "";
        }

        /* ---------- COMMENTS ---------- */
        $post['comment'] = [];
        $comments = $this->db
            ->select('pc.id, pc.text, pc.created_at, u.id as user_id, u.username, u.profile_pic')
            ->from('post_comment pc')
            ->join('users u', 'u.id = pc.user_id')
            ->where('pc.post_id', $post_id)
            ->order_by('pc.id', 'ASC')
            ->get()
            ->result_array();

        foreach ($comments as $c) {
            $post['comment'][] = [
                "comment_id" => (string)$c['id'],
                "user_id" => (string)$c['user_id'],
                "username" => $c['username'],
                "comment" => $c['text'],
                "profile_pic" => !empty($c['profile_pic'])
                    ? $BASE_URL . ltrim($c['profile_pic'], '/')
                    : "",
                "created_at" => $c['created_at']
            ];
        }

        $post['type'] = $post['post_type'];
        unset($post['post_type']);
    }

    /* ===============================
      6️⃣ FINAL RESPONSE
    =============================== */
    echo json_encode([
        "status" => "Success",
        "message" => "Tagged posts fetched successfully",
        "current_page" => $page_no,
        "last_page" => $last_page,
        "post" => $posts
    ]);
}




public function second_user_followers_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    /* ===============================
       1️⃣ READ AUTH TOKEN
    =============================== */
    $authHeader = $this->input->get_request_header('Authorization', TRUE);

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Authorization Token Missing",
            "status" => "failure"
        ]);
        return;
    }

    $token = $matches[1];

    $userToken = $this->db
        ->get_where('users', ['token' => $token])
        ->row();

    if (!$userToken) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Invalid Token",
            "status" => "failure"
        ]);
        return;
    }

    $user_id = $userToken->id;

    /* ===============================
       2️⃣ READ JSON INPUT
    =============================== */
    $input = json_decode(file_get_contents("php://input"), true);

    $to_user_id = $input['to_user_id'] ?? '';

    if (empty($to_user_id)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "to_user_id required",
            "status" => "failure"
        ]);
        return;
    }

    /* ===============================
       3️⃣ FETCH FOLLOWERS (JOIN USERS)
    =============================== */
    $this->db->select('
        follow.follow_id,
        follow.from_user,
        follow.to_user,
        follow.friend_type,
        follow.date,
        follow.status,
        users.first_name,
        users.last_name,
        users.username,
        users.profile_pic,
        users.id AS follow_user_id
    ');
    $this->db->from('follow');
    $this->db->join('users', 'users.id = follow.from_user');
    $this->db->where('follow.to_user', $to_user_id);

    $followers = $this->db->get()->result_array();

    /* ===============================
       4️⃣ FORMAT DATA (LIKE LARAVEL)
    =============================== */
    foreach ($followers as &$user) {

        // Cast to string
        $user['follow_id']      = (string)$user['follow_id'];
        $user['from_user']      = (string)$user['from_user'];
        $user['to_user']        = (string)$user['to_user'];
        $user['friend_type']    = (string)$user['friend_type'];
        $user['status']         = (string)$user['status'];
        $user['follow_user_id'] = (string)$user['follow_user_id'];

        // Profile Pic URL
        if (!empty($user['profile_pic'])) {
            if (filter_var($user['profile_pic'], FILTER_VALIDATE_URL)) {
                $user['profile_pic'] = $user['profile_pic'];
            } else {
                $user['profile_pic'] = adv_profile_pic_url($user['profile_pic']);
            }
        } else {
            $user['profile_pic'] = '';
        }

        $user['first_name'] = $user['first_name'] ?? '';
        $user['last_name']  = $user['last_name'] ?? '';

        // ✅ is_follow (logged user follows this follower)
        $is_follow = $this->db
            ->get_where('follow', [
                'from_user' => $user_id,
                'to_user'   => $user['from_user']
            ])->row();

        $user['is_follow'] = $is_follow ? '1' : '0';

        // ✅ is_me
        $user['is_me'] = ($user['from_user'] == $user_id) ? '1' : '0';
    }

    /* ===============================
       5️⃣ FINAL RESPONSE
    =============================== */
    echo json_encode([
        "response_code" => "1",
        "message"       => "Follower List Successfully",
        "follower"      => $followers,
        "status"        => "success"
    ]);
}



public function second_user_following_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set("Asia/Kolkata");

    /* ===============================
       1️⃣ AUTH TOKEN
    =============================== */
    $authHeader = $this->input->get_request_header('Authorization', TRUE);

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Authorization token missing",
            "follower" => [],
            "status" => "failed"
        ]);
        return;
    }

    $token = $matches[1];

    $viewer = $this->db->get_where('users', ['token' => $token])->row();
    if (!$viewer) {
        echo json_encode([
            "response_code" => "0",
            "message" => "Invalid token",
            "follower" => [],
            "status" => "failed"
        ]);
        return;
    }

    $viewer_id = (int)$viewer->id;

    /* ===============================
       2️⃣ READ JSON BODY
    =============================== */
    $input = json_decode(file_get_contents("php://input"), true);
    $to_user_id = isset($input['to_user_id']) ? (int)$input['to_user_id'] : 0;

    if ($to_user_id === 0) {
        echo json_encode([
            "response_code" => "0",
            "message" => "to_user_id required",
            "follower" => [],
            "status" => "failed"
        ]);
        return;
    }

    /* ===============================
       3️⃣ FETCH FOLLOWING LIST
    =============================== */
    $this->db->select('
        follow.follow_id,
        follow.from_user,
        follow.to_user,
        follow.friend_type,
        follow.date,
        follow.status,
        users.username,
        users.first_name,
        users.last_name,
        users.profile_pic,
        users.id as follow_user_id
    ');
    $this->db->from('follow');
    $this->db->join('users', 'users.id = follow.to_user');
    $this->db->where('follow.from_user', $to_user_id);

    $followers = $this->db->get()->result_array();

    /* ===============================
       4️⃣ BUILD RESPONSE DATA
    =============================== */
    foreach ($followers as &$user) {

        // Cast IDs as string (Laravel-like)
        $user['follow_id'] = (string)$user['follow_id'];
        $user['from_user'] = (string)$user['from_user'];
        $user['to_user'] = (string)$user['to_user'];
        $user['friend_type'] = (string)$user['friend_type'];
        $user['status'] = (string)$user['status'];
        $user['follow_user_id'] = (string)$user['follow_user_id'];

        // Profile pic URL
        if (!empty($user['profile_pic'])) {
            $user['profile_pic'] = filter_var($user['profile_pic'], FILTER_VALIDATE_URL)
                ? $user['profile_pic']
                : base_url('assets/images/avtar/' . $user['profile_pic']);
        } else {
            $user['profile_pic'] = '';
        }

        $user['username']   = $user['username'] ?? '';
        $user['first_name'] = $user['first_name'] ?? '';
        $user['last_name']  = $user['last_name'] ?? '';

        /* ---------- is_follow ---------- */
        $is_follow = $this->db->get_where('follow', [
            'from_user' => $viewer_id,
            'to_user'   => $user['to_user']
        ])->row();

        $user['is_follow'] = $is_follow ? "1" : "0";

        /* ---------- is_me ---------- */
        $user['is_me'] = ($user['to_user'] == $viewer_id) ? "1" : "0";
    }

    /* ===============================
       5️⃣ FINAL RESPONSE
    =============================== */
    echo json_encode([
        "response_code" => "1",
        "message" => "Following List Found",
        "follower" => $followers,
        "status" => "success"
    ]);
}

 public function user_notification_list_post()
    {
        header('Content-Type: application/json');
        date_default_timezone_set('Asia/Kolkata');

        /* ===============================
           🔐 TOKEN VALIDATION
        =============================== */
        $headers = $this->input->request_headers();
        $authHeader = $headers['Authorization'] ?? '';

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $this->unauthorized_response();
        }

        $token = $matches[1];

        // 🔹 Get logged-in user from token
        $user = $this->get_user_from_token($token);

        if (!$user) {
            return $this->unauthorized_response();
        }

        $user_id = (int)$user->id;

        /* ===============================
           🔔 MARK ALL AS READ
        =============================== */
        $this->db->where('to_user', $user_id)
                 ->update('user_notifications', ['read_status' => '1']);

        /* ===============================
           🔔 FETCH NOTIFICATIONS
        =============================== */
        $notifications = $this->db
            ->where('to_user', $user_id)
            ->order_by('not_id', 'DESC')
            ->get('user_notifications')
            ->result();

        if (empty($notifications)) {
            echo json_encode([
                "response_code" => "1",
                "message" => "No notifications found",
                "status" => "success",
                "detail" => []
            ]);
            return;
        }

        $detail = [];

        foreach ($notifications as $n) {

            /* ===============================
               👤 FROM USER DETAILS
            =============================== */
            $fromUser = $this->db
                ->where('id', $n->from_user)
                ->get('users')
                ->row();

            $profile_pic = '';
            if (!empty($fromUser->profile_pic)) {
                $profile_pic = preg_match('/^https?:\/\//', $fromUser->profile_pic)
                    ? $fromUser->profile_pic
                    : base_url('assets/images/user/' . $fromUser->profile_pic);
            }

            /* ===============================
               🖼️ POST DATA (IF ANY)
            =============================== */
            $post_type  = '';
            $post_image = [];

            if (!empty($n->post_id)) {

                $post = $this->db
                    ->where('post_id', $n->post_id)
                    ->get('posts')
                    ->row();

                if ($post) {
                    $post_type = $post->post_type;

                    // Images
                    $images = $this->db
                        ->where('post_id', $post->post_id)
                        ->get('post_image')
                        ->result();

                    foreach ($images as $img) {
                        $post_image[] = [
                            "post_image_id" => (int)$img->id,
                            "url" => preg_match('/^https?:\/\//', $img->new_post)
                                ? $img->post_image
                                : base_url($img->new_post),
                            "type" => "image",
                            "post_video_thumbnail" => null
                        ];
                    }

                    // Videos
                    $videos = $this->db
                        ->where('post_id', $post->post_id)
                        ->get('post_video')
                        ->result();

                    foreach ($videos as $vid) {
                        $post_image[] = [
                            "post_image_id" => (int)$vid->id,
                            "url" => preg_match('/^https?:\/\//', $vid->post_video)
                                ? $vid->post_video
                                : base_url($vid->post_video),
                            "type" => "video",
                            "post_video_thumbnail" => !empty($vid->post_video_thumbnails)
                                ? base_url($vid->post_video_thumbnails)
                                : null
                        ];
                    }
                }
            }

            /* ===============================
               📦 FINAL OBJECT
            =============================== */
            $detail[] = [
                "not_id" => (int)$n->not_id,
                "title" => $n->title ?? '',
                "message" => $n->message ?? '',
                "from_user" => (int)$n->from_user,
                "to_user" => (int)$n->to_user,
                "post_id" => (string)$n->post_id,
                "type" => $n->notifiable_type ?? '',
                "date" => (string)$n->date,
                "is_view" => (string)$n->read_status,
                "not_type" => (string)$n->notifiable_type,
                "post_user_id" => (string)$n->post_user_id,
                "created_at" => $n->created_at,
                "updated_at" => $n->updated_at,
                "first_name" => $fromUser->first_name ?? '',
                "username" => $fromUser->username ?? '',
                "profile_pic" => $profile_pic,
                "post_type" => $post_type,
                "post_image" => $post_image
            ];
        }

        echo json_encode([
            "response_code" => "1",
            "message" => "Notification list fetched successfully",
            "status" => "success",
            "detail" => $detail
        ]);
    }

    /* ===============================
       🔐 TOKEN → USER
    =============================== */
    private function get_user_from_token($token)
    {
        return $this->db
            ->where('token', $token)
            ->get('users')
            ->row();
    }

    private function unauthorized_response()
    {
        echo json_encode([
            "response_code" => "0",
            "message" => "Unauthorized",
            "status" => "failure",
            "detail" => []
        ]);
        exit;
    }

    public function user_read_count_post()
    {
        header('Content-Type: application/json');
        date_default_timezone_set('Asia/Kolkata');

        /* ===============================
           🔐 TOKEN VALIDATION
        =============================== */
        $headers = $this->input->request_headers();
        $authHeader = $headers['Authorization'] ?? '';

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $this->unauthorized_response();
        }

        $token = $matches[1];

        // 🔹 Identify logged-in user from token
        $user = $this->db
            ->where('token', $token)
            ->get('users')
            ->row();

        if (!$user) {
            return $this->unauthorized_response();
        }

        $user_id = (int)$user->id;

        /* ===============================
           📩 UNREAD MESSAGE COUNT
        =============================== */
        $unread_message = $this->db
            ->where('to_user', $user_id)
            ->where('read_message', 0)
            ->count_all_results('chats');

        /* ===============================
           🔔 UNREAD NOTIFICATION COUNT
        =============================== */
        $unread_notification = $this->db
            ->where('to_user', $user_id)
            ->where('read_status', 0)
            ->count_all_results('user_notifications');

        /* ===============================
           📤 RESPONSE (SUCCESS ALWAYS)
        =============================== */
        if ($unread_message > 0 || $unread_notification > 0) {
            echo json_encode([
                "response_code" => "1",
                "message" => "Unread count fetched successfully",
                "unread_message" => (string)$unread_message,
                "unread_notification" => (string)$unread_notification,
                "status" => "success"
            ]);
        } else {
            echo json_encode([
                "response_code" => "1",
                "message" => "No unread data",
                "unread_message" => "0",
                "unread_notification" => "0",
                "status" => "success"
            ]);
        }
    }
    
    
    public function online_user_list()
{
    // OPTIONAL: logged-in user id (if using session)
    $user_id = $this->session->userdata('user_id');

    $this->db->where('is_online', '1');
    $query = $this->db->get('users');

    $users = [];

    if ($query->num_rows() > 0) {
        foreach ($query->result() as $row) {
            $users[] = [
                'user_id'  => $row->id,
                'username' => $row->username
            ];
        }

        echo json_encode([
            'response_code' => '1',
            'message'       => 'Online User List Found',
            'online_user'   => $users,
            'status'        => 'success'
        ]);
    } else {
        echo json_encode([
            'response_code' => '0',
            'message'       => 'Online User List Not Found',
            'online_user'   => [],
            'status'        => 'failure'
        ]);
    }
}

public function new_user_chat_list_post()
{
    header('Content-Type: application/json; charset=utf-8');
    date_default_timezone_set('Asia/Kolkata');
$this->db->query("SET NAMES utf8mb4");
    // ===============================
    // 1. READ AUTHORIZATION HEADER
    // ===============================
    $authHeader = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if (empty($authHeader)) {
        echo json_encode([
            'response_code' => '0',
            'message' => 'Authorization token missing'
        ]);
        return;
    }

    // ===============================
    // 2. EXTRACT TOKEN
    // ===============================
    $token = trim(str_replace('Bearer', '', $authHeader));

    if (empty($token)) {
        echo json_encode([
            'response_code' => '0',
            'message' => 'Invalid token'
        ]);
        return;
    }

    // ===============================
    // 3. GET USER FROM TOKEN
    // ===============================
    $authUser = $this->db
        ->where('token', $token)
        ->get('users')
        ->row();

    if (!$authUser) {
        echo json_encode([
            'response_code' => '0',
            'message' => 'Unauthorized user'
        ]);
        return;
    }

    $user_id = (int)$authUser->id;

    // ===============================
    // 4. FETCH DISTINCT CHAT USERS
    // ===============================
    $this->db->distinct();
    $this->db->select("IF(from_user = {$user_id}, to_user, from_user) AS chat_user", false);
    $this->db->where("(from_user = {$user_id} OR to_user = {$user_id})");
    $chatUsers = $this->db->get('chats')->result_array();

    $chat_list = [];

    foreach ($chatUsers as $row) {

        $other_user_id = (int)$row['chat_user'];

        // ===============================
        // FETCH LAST MESSAGE BETWEEN USERS
        // ===============================
        $this->db->where("
            (from_user = {$user_id} AND to_user = {$other_user_id})
            OR
            (from_user = {$other_user_id} AND to_user = {$user_id})
        ");
        $this->db->order_by('created_at', 'DESC');
        $last_msg = $this->db->get('chats')->row();

        if (!$last_msg) continue;

        // ===============================
        // GET OTHER USER INFO
        // ===============================
        $userInfo = $this->db
            ->where('id', $other_user_id)
            ->get('users')
            ->row();

        if (!$userInfo) continue;

        // ===============================
        // PROFILE PIC
        // ===============================
        $profile_pic = '';
        if (!empty($userInfo->profile_pic)) {
            $profile_pic = filter_var($userInfo->profile_pic, FILTER_VALIDATE_URL)
                ? $userInfo->profile_pic
                : base_url('assets/images/avtar/' . $userInfo->profile_pic);
        }

        // ===============================
        // UNREAD MESSAGES COUNT
        // ===============================
        $unread = $this->db
            ->where('to_user', $user_id)
            ->where('from_user', $other_user_id)
            ->where('read_message', 0)
            ->count_all_results('chats');

        // ===============================
        // STANDARD CHAT OBJECT
        // ===============================
        $chat_list[] = [
            "id"             => (string)$last_msg->id, // Message ID
            "my_id"          => (string)$user_id,
            "second_id"      => (string)$other_user_id,
            "message"        => (string)($last_msg->message ?? ""),
            "message_type"   => (string)($last_msg->type ?? ""),
            "profile_pic"    => (string)$profile_pic,
            "first_name"     => (string)($userInfo->first_name ?? ""),
            "username"       => (string)($userInfo->username ?? ""),
            "last_seen"      => (string)($userInfo->updated_at ?? ""),
            "unread_message" => (string)$unread,
            "user_id"        => (string)$other_user_id,
            "time"           => date('H:i', strtotime($last_msg->created_at))
        ];
    }

    // ===============================
    // 5. SORT CHAT LIST BY LAST MESSAGE TIME
    // ===============================
    usort($chat_list, function ($a, $b) {
        return strtotime($b['time']) <=> strtotime($a['time']);
    });

    // ===============================
    // 6. SOCKET RESULT (FAKE SUCCESS)
    // ===============================
    $socket_result = [
        'status'  => true,
        'message' => 'Socket event success'
    ];

    // ===============================
    // 7. FINAL RESPONSE
    // ===============================
    echo json_encode([
        "response_code" => "1",
        "message"       => "Message list.",
        "chat_list"     => $chat_list,
        "socket_result" => $socket_result,
        "status"        => "success"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}



private function getUserFromToken()
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if (!$authHeader) {
        return false;
    }

    $token = trim(str_replace('Bearer', '', $authHeader));

    if (!$token) {
        return false;
    }

    return $this->db->where('token', $token)->get('users')->row();
}

public function new_message_list_post()
{
    header('Content-Type: application/json; charset=utf-8');
    date_default_timezone_set('Asia/Kolkata');
$this->db->query("SET NAMES utf8mb4");
    // ===============================
    // AUTH USER FROM TOKEN
    // ===============================
    $authUser = $this->getUserFromToken();

    if (!$authUser) {
        echo json_encode([
            'response_code' => "0",
            'message' => 'Unauthorized user',
            'status' => 'failure'
        ]);
        return;
    }

    $from_user = (int)$authUser->id;

    // ===============================
    // INPUT
    // ===============================
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['to_user'])) {
        echo json_encode([
            'response_code' => "0",
            'message' => 'to_user is required',
            'status' => 'failure'
        ]);
        return;
    }

    $to_user = (int)$input['to_user'];

    // ===============================
    // MARK AS READ
    // ===============================
    $this->db->where('to_user', $from_user)
             ->where('from_user', $to_user)
             ->update('chats', ['read_message' => 1]);

    // ===============================
    // FETCH CHATS
    // ===============================
    $this->db->where("(
        (from_user = {$from_user} AND to_user = {$to_user})
        OR
        (from_user = {$to_user} AND to_user = {$from_user})
    )");
    $this->db->order_by('created_at', 'DESC');
    $chats = $this->db->get('chats')->result_array();

    // ===============================
    // COLLECT POST IDS + FROM USER IDS
    // ===============================
    $post_ids = [];
    $from_user_ids = [];

    foreach ($chats as $row) {
        $from_user_ids[] = (int)$row['from_user'];

        if (!empty($row['post_id'])) {
            $post_ids[] = (int)$row['post_id'];
        }
    }

    $post_ids = array_unique($post_ids);
    $from_user_ids = array_unique($from_user_ids);

    // ===============================
    // FETCH FROM USER DETAILS (IMPORTANT)
    // ===============================
    $from_user_map = [];

    if (!empty($from_user_ids)) {
        $from_users = $this->db
            ->select('id, first_name, profile_pic')
            ->where_in('id', $from_user_ids)
            ->get('users')
            ->result_array();

        foreach ($from_users as $fu) {
            $from_user_map[$fu['id']] = $fu;
        }
    }

    // ===============================
    // FETCH POST OWNER DETAILS
    // ===============================
    $post_user_map = [];
    $user_map = [];

    if (!empty($post_ids)) {

        $posts = $this->db
            ->select('post_id, user_id')
            ->where_in('post_id', $post_ids)
            ->get('post_image')
            ->result_array();

        $user_ids = [];
        foreach ($posts as $p) {
            $post_user_map[$p['post_id']] = $p['user_id'];
            $user_ids[] = (int)$p['user_id'];
        }

        if (!empty($user_ids)) {
            $users = $this->db
                ->select('id, username, profile_pic, first_name')
                ->where_in('id', $user_ids)
                ->get('users')
                ->result_array();

            foreach ($users as $u) {
                $user_map[$u['id']] = $u;
            }
        }
    }

    // ===============================
    // BUILD RESPONSE (NO CHANGE IN KEYS)
    // ===============================
    $chat_data = [];

    foreach ($chats as $row) {

        $createdAt = $row['created_at']
            ? date('Y-m-d H:i:s', strtotime($row['created_at']))
            : null;

        // 🔥 FROM USER NAME & PIC (AS REQUESTED)
        $first_name = null;
        $profile_pic = null;

        if (isset($from_user_map[$row['from_user']])) {
            $first_name = $from_user_map[$row['from_user']]['first_name'] ?? null;
            $profile_pic = $from_user_map[$row['from_user']]['profile_pic'] ?? null;
        }

        // POST USER DETAILS (UNCHANGED)
        $post_user_id = null;
        $post_user_name = null;
        $post_user_profile_pic = null;

        if (!empty($row['post_id']) && isset($post_user_map[$row['post_id']])) {
            $uid = $post_user_map[$row['post_id']];
            $post_user_id = (string)$uid;

            if (isset($user_map[$uid])) {
                $post_user_name = $user_map[$uid]['username'] ?? null;
                $post_user_profile_pic = $user_map[$uid]['profile_pic'] ?? null;
            }
        }

        $chat_data[] = [
            "id" => (int)$row['id'],
            "from_user" => (int)$row['from_user'],
            "to_user" => (string)$row['to_user'],
            "message" => $row['message'] ?? null,
            "type" => $row['type'] ?? null,

            "url" => $row['url'] ? (string)$row['url'] : null,
            "video_thumbnail" => $row['video_thumbnail'] ? (string)$row['video_thumbnail'] : null,

            "send_post" => null,
            "send_story" => null,

            "post_id" => $row['post_id'] ? (string)$row['post_id'] : null,
            "reel_id" => $row['reel_id'] ? (string)$row['reel_id'] : null,
            "story_id" => $row['story_id'] ? (string)$row['story_id'] : null,

            "post_user_id" => $post_user_id,
            "post_user_name" => $post_user_name,
            "post_user_profile_pic" => $post_user_profile_pic,

            // 🔥 SAME RESPONSE KEYS
            "first_name" => $first_name,
            "profile_pic" => $profile_pic,

            "created_at" => $createdAt,
            "chat_time" => $createdAt ? date('h:i A', strtotime($createdAt)) : null
        ];
    }

    echo json_encode([
        "response_code" => "1",
        "message" => "Message list",
        "status" => "success",
        "chat" => $chat_data
    ], JSON_PRETTY_PRINT);
}



public function new_chat_api_post()
{
    header('Content-Type: application/json; charset=utf-8');

    /* =====================================================
       1. READ AUTH TOKEN
    ===================================================== */
        $this->db->query("SET NAMES utf8mb4");

    $authHeader = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if (strpos($authHeader, 'Bearer ') !== 0) {
        return $this->json_error('Token missing', 401);
    }

    $token = substr($authHeader, 7);

    /* =====================================================
       2. VALIDATE TOKEN
    ===================================================== */
    $from_user_id = (int) $this->User_model->validate_token_and_get_user($token);

    if ($from_user_id <= 0) {
        return $this->json_error('Invalid or expired token', 401);
    }

    /* =====================================================
       3. READ JSON INPUT
    ===================================================== */
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        return $this->json_error('Invalid JSON body');
    }

    $to_user_id = (int) ($input['to_user'] ?? 0);
    $type       = $input['type'] ?? 'text'; // text | photo | file | video
    $message    = $input['message'] ?? '';

    if ($to_user_id <= 0) {
        return $this->json_error('to_user is required');
    }

    /* =====================================================
       4. HANDLE BASE64 FILE
    ===================================================== */
    $url = '';
    $video_thumbnail = '';

    if (!empty($input['file_base64']) && !empty($input['file_name'])) {

        // clean base64
        $base64 = $input['file_base64'];
        if (strpos($base64, ',') !== false) {
            $base64 = explode(',', $base64)[1];
        }

        $fileData = base64_decode($base64);
        if ($fileData === false) {
            return $this->json_error('Invalid base64 data');
        }

        $fileName = time() . '_' . adv_s3_safe_name($input['file_name']);
        $sub = ($type === 'video') ? 'videos' : 'images';
        $s3Key = adv_user_media_key($from_user_id, 'chat', null, $sub, $fileName);

        $mime = 'application/octet-stream';
        if ($type === 'video') {
            $mime = 'video/mp4';
        } elseif ($type === 'photo' || $type === 'image') {
            $mime = 'image/jpeg';
        }

        $s3Url = adv_s3_upload_bytes($fileData, $s3Key, $mime);
        if ($s3Url === false) {
            return $this->json_error('Chat media S3 upload failed: ' . adv_s3_last_error());
        }

        $url = $s3Url;
    }

    /* =====================================================
       5. INSERT CHAT
    ===================================================== */
    $this->db->insert('chats', [
        'from_user'       => $from_user_id,
        'to_user'         => $to_user_id,
        'message'         => $message,
        'type'            => $type,
        'url'             => $url,
        'video_thumbnail' => $video_thumbnail,
        'created_at'      => date('Y-m-d H:i:s')
    ]);

    $chat_id = (int) $this->db->insert_id();

    if ($chat_id <= 0) {
        return $this->json_error('Message insert failed', 500);
    }

    /* =====================================================
       6. SOCKET EVENT (OPTIONAL)
    ===================================================== */
    $this->db->where_in('user_id', [$from_user_id, $to_user_id]);
    $socket_ids = $this->db->select('socket_id')->get('socket_table')->result_array();
    $socket_ids = array_filter(array_column($socket_ids, 'socket_id'));

    if (!empty($socket_ids)) {
        $payload = [
            'socketId'  => $socket_ids,
            'eventName' => 'new_message',
            'data'      => [
                'message_id' => $chat_id,
                'from_user'  => $from_user_id,
                'to_user'    => $to_user_id,
                'type'       => $type,
                'message'    => $message,
                'url'        => $url,
                'created_at' => date('c')
            ]
        ];

        $ch = curl_init('http://localhost:3002/api/trigger-event');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 2
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    /* =====================================================
       7. FINAL JSON RESPONSE
    ===================================================== */
    http_response_code(200);
    echo json_encode([
        'response_code' => '1',
        'status'        => 'success',
        'message'       => 'Message sent successfully',
        'data'          => [
            'message_id' => $chat_id,
            'from_user'  => $from_user_id,
            'to_user'    => $to_user_id,
            'type'       => $type,
            'message'    => $message,
            'url'        => $url,
            'created_at' => date('c')
        ]
    ]);
    exit;
}

// =========================================================
// JSON ERROR HELPER
// =========================================================
private function json_error($message, $code = 400)
{
    http_response_code($code);
    echo json_encode([
        "response_code" => "0",
        "message" => $message,
        "status" => "failure"
    ]);
    exit;
}


    
    public function get_firebase_token_get()
    {
        // Full path to your Firebase service account JSON file
        $serviceAccountPath = FIREBASE_CREDENTIALS;

        if (!file_exists($serviceAccountPath)) {
            $this->response([
                'status' => 'error',
                'message' => 'Firebase JSON file not found.'
            ], 500);
            return;
        }

        $jsonKey = json_decode(file_get_contents($serviceAccountPath), true);

        if (!isset($jsonKey['client_email']) || !isset($jsonKey['private_key'])) {
            $this->response([
                'status' => 'error',
                'message' => 'Invalid Firebase credentials.'
            ], 500);
            return;
        }

        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];

        // ✅ Proper OAuth2 object with tokenCredentialUri
        $oauth = new OAuth2([
            'audience' => 'https://oauth2.googleapis.com/token',
            'issuer' => $jsonKey['client_email'],
            'signingAlgorithm' => 'RS256',
            'signingKey' => $jsonKey['private_key'],
            'tokenCredentialUri' => 'https://oauth2.googleapis.com/token', // REQUIRED
            'scope' => $scopes,
        ]);

        try {
            $token = $oauth->fetchAuthToken();

            if (isset($token['access_token'])) {
                $this->response([
                    'status' => 'success',
                    'access_token' => $token['access_token'],
                    'expires_in' => $token['expires_in'] ?? 3600
                ], 200);
            } else {
                $this->response([
                    'status' => 'error',
                    'message' => 'Token generation failed.',
                    'response' => $token
                ], 500);
            }
        } catch (Exception $e) {
            $this->response([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


public function add_post_boost_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set('Asia/Kolkata');

    try {

        /* ===============================
           READ BEARER TOKEN
        =============================== */
        $authHeader = $this->input->get_request_header('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $this->output
                ->set_status_header(401)
                ->set_output(json_encode([
                    "status"  => "error",
                    "message" => "Authorization token missing"
                ]));
        }

        $token = $matches[1];

        /* ===============================
           VALIDATE TOKEN
        =============================== */
        $user_id = $this->User_model->validate_token_and_get_user($token);

        if (!$user_id) {
            return $this->output
                ->set_status_header(401)
                ->set_output(json_encode([
                    "status"  => "error",
                    "message" => "Invalid or expired token"
                ]));
        }

        /* ===============================
           READ JSON INPUT
        =============================== */
        $rawInput = json_decode(file_get_contents('php://input'), true);

        if (!is_array($rawInput)) {
            return $this->output
                ->set_status_header(400)
                ->set_output(json_encode([
                    "status"  => "error",
                    "message" => "Invalid JSON payload"
                ]));
        }

        /* ===============================
           ASSIGN VARIABLES
        =============================== */
        $post_id        = $rawInput['post_id'] ?? null;
        $start_date     = $rawInput['start_date'] ?? null;
        $end_date       = $rawInput['end_date'] ?? null;
        $days           = $rawInput['days'] ?? null;
        $price          = (float)($rawInput['price'] ?? 0);
        $tax            = (float)($rawInput['tax'] ?? 0);
        $total_price    = (float)($rawInput['total_price'] ?? 0);
        $payment_method = $rawInput['payment_method'] ?? null;

        $state          = $rawInput['state'] ?? null;
        $district       = $rawInput['district'] ?? null;
        $taluka         = $rawInput['taluka'] ?? null;
        $country        = $rawInput['country'] ?? null;

        /* ===============================
           VALIDATION
        =============================== */
        if (
            empty($post_id) ||
            empty($start_date) ||
            empty($end_date) ||
            empty($days) ||
            empty($price) ||
            empty($state) ||
            empty($district) ||
            empty($taluka) ||
            empty($country)
        ) {
            return $this->output
                ->set_status_header(400)
                ->set_output(json_encode([
                    "status"  => "error",
                    "message" => "Required fields missing"
                ]));
        }

        /* ===============================
           CHECK EXISTING PENDING BOOST
        =============================== */
        $existingBoost = $this->db
            ->where('post_id', $post_id)
            ->where('status', 'pending')
            ->get('post_boost')
            ->row();

        $boostData = [
            'user_id'        => $user_id,
            'post_id'        => $post_id,
            'start_date'     => $start_date,
            'end_date'       => $end_date,
            'days'           => $days,
            'price'          => $price,
            'tax'            => $tax,
            'total_price'    => $total_price,
            'payment_method' => $payment_method,
            'state'          => $state,
            'district'       => $district,
            'taluka'         => $taluka,
            'country'        => $country,
            'status'         => 'pending'
        ];

        /* ===============================
           UPDATE OR INSERT
        =============================== */
        if ($existingBoost) {

            // 🔁 UPDATE pending record
            $boostData['updated_at'] = date('Y-m-d H:i:s');

            $this->db->where('id', $existingBoost->id)
                     ->update('post_boost', $boostData);

            $action = 'updated';

        } else {

            // ➕ INSERT new record
            $boostData['created_at'] = date('Y-m-d H:i:s');

            $this->db->insert('post_boost', $boostData);

            $action = 'inserted';
        }

        if (!$this->db->affected_rows()) {
            throw new Exception('Boost save failed');
        }

        /* ===============================
           SUCCESS RESPONSE
        =============================== */
        return $this->output
            ->set_status_header(200)
            ->set_output(json_encode([
                "status"  => "success",
                "message" => "Post boost {$action} successfully",
                "post_id" => (int)$post_id,
                "user_id" => (int)$user_id
            ]));

    } catch (Exception $e) {

        return $this->output
            ->set_status_header(500)
            ->set_output(json_encode([
                "status"  => "error",
                "message" => "Something went wrong",
                "error"   => $e->getMessage()
            ]));
    }
}


public function fetch_boost_history_post()
{
    header('Content-Type: application/json; charset=utf-8');
    date_default_timezone_set("Asia/Kolkata");

    $this->load->model('Story_model');
    $this->load->model('User_model');

    /* ===============================
       AUTH TOKEN
    ===============================*/
    $authHeader = $this->input->get_request_header("Authorization");

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode([
            'status'  => false,
            'message' => 'Unauthorized',
            'data'    => []
        ]);
        return;
    }

    $token = $matches[1];
    $auth_user_id = $this->User_model->validate_token_and_get_user($token);

    if (!$auth_user_id) {
        echo json_encode([
            'status'  => false,
            'message' => 'Invalid Token',
            'data'    => []
        ]);
        return;
    }

    /* ===============================
       FETCH BOOST HISTORY
    ===============================*/
    $boostRows = $this->Story_model->get_boosts_by_user($auth_user_id);
    $finalData = [];

    foreach ($boostRows as $boost) {

        $post  = $this->Story_model->get_post($boost->post_id);

        // ✅ ALWAYS ARRAY (NEVER NULL)
        $media = [];

        if ($post) {

            /* ===============================
               IMAGE POSTS
            ===============================*/
            if ($post->post_type === 'image') {

                $images = $this->Story_model->get_images_by_post($post->post_id);

                foreach ($images as $img) {
                    $media[] = [
                        "post_video_id"        => (int)$img->id,
                        "url"                  => adv_profile_pic_url($img->new_post),
                        "type"                 => "image",
                        "post_video_thumbnail" => ""   // ✅ REQUIRED BY FLUTTER
                    ];
                }
            }

            /* ===============================
               REEL / VIDEO POSTS
            ===============================*/
            if ($post->post_type === 'reel') {

                $videos = $this->Story_model->get_videos_by_post($post->post_id);

                foreach ($videos as $vid) {
                    $media[] = [
                        "post_video_id"        => (int)$vid->id,
                        "url"                  => adv_profile_pic_url($vid->post_video),
                        "type"                 => "video",
                        "post_video_thumbnail" => !empty($vid->video_thumbnail)
                            ? base_url($vid->video_thumbnail)
                            : ""
                    ];
                }
            }
        }

        /* ===============================
           FINAL OBJECT
        ===============================*/
        $finalData[] = [
            "id"             => (int)$boost->id,
            "post_id"        => (int)$boost->post_id,
            "user_id"        => (int)$boost->user_id,
            "post_type"      => $post->post_type ?? "",
            "start_date"     => $boost->start_date ?? "",
            "end_date"       => $boost->end_date ?? "",
            "days"           => (string)($boost->days ?? "0"),
            "price"          => (string)($boost->price ?? "0"),
            "total_price"    => (string)($boost->total_price ?? "0"),
            "status"         => (string)($boost->status ?? "0"),
            "payment_method" => $boost->payment_method ?? "",
            "created_at"     => $boost->created_at ?? "",
            "media"          => $media // ✅ ALWAYS ARRAY
        ];
    }

    /* ===============================
       FINAL RESPONSE
    ===============================*/
    echo json_encode([
        "status"  => true,
        "message" => "Boost history fetched successfully",
        "data"    => $finalData
    ], JSON_UNESCAPED_SLASHES);
}



  public function all_payment_gateway_key_get()
    {
        header('Content-Type: application/json; charset=utf-8');
        date_default_timezone_set('Asia/Kolkata');

        // ===============================
        // AUTHORIZATION (Bearer Token)
        // ===============================
        $authHeader = $this->input->get_request_header('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized',
                'data'    => []
            ]);
            return;
        }

        $token = $matches[1];
        $userId = $this->User_model->validate_token_and_get_user($token);

        if (!$userId) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid token',
                'data'    => []
            ]);
            return;
        }

        // ===============================
        // FETCH ACTIVE PAYMENT GATEWAYS
        // ===============================
        $gateways = $this->User_model->get_all_keys();

        $responseData = [];

        foreach ($gateways as $row) {

            $responseData[] = [
                'id'            => (int)$row->id,
                'enabled'       => (bool)$row->status,
                'text'          => (string)$row->text,
                'public_key'    => (string)$row->public_key,
                'mode'          => strtolower($row->mode), // test / live
                'country_code'  => $row->country_code ?: null,
                'currency_code' => $row->currency_code ?: null
            ];
        }

        // ===============================
        // FINAL RESPONSE
        // ===============================
        echo json_encode([
            'success' => true,
            'message' => 'Payment gateway list fetched successfully',
            'data'    => $responseData
        ]);
    }
    
public function viewInsights_post()
{
    header('Content-Type: application/json; charset=utf-8');
    date_default_timezone_set('Asia/Kolkata');

    /* ===============================
       AUTHORIZATION
    =============================== */
    $authHeader = $this->input->get_request_header('Authorization');

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        echo json_encode($this->emptyInsightResponse(401, 'Unauthorized'));
        return;
    }

    $token  = $matches[1];
    $userId = $this->User_model->validate_token_and_get_user($token);

    if (!$userId) {
        echo json_encode($this->emptyInsightResponse(401, 'Invalid token'));
        return;
    }

    /* ===============================
       READ JSON BODY
    =============================== */
    $rawInput = json_decode(file_get_contents('php://input'), true);
    $post_id  = $rawInput['post_id'] ?? null;

    if (!$post_id) {
        echo json_encode($this->emptyInsightResponse(200, 'No post selected'));
        return;
    }

    /* ===============================
       FETCH POST
    =============================== */
    $post = $this->db
        ->where('post_id', $post_id)
        ->get('posts')
        ->row_array();

    if (!$post) {
        echo json_encode($this->emptyInsightResponse(200, 'Post not found'));
        return;
    }

    /* ===============================
       COUNTS (IMAGE + REEL SAFE)
    =============================== */

    // 🔹 Likes
    $likeCount = ($post['post_type'] === 'image')
        ? $this->db->where('post_id', $post_id)->count_all_results('post_like')
        : $this->db->where('reel_id', $post_id)->count_all_results('reel_like');

    // 🔹 Comments
    $commentCount = ($post['post_type'] === 'image')
        ? $this->db->where('post_id', $post_id)->count_all_results('post_comment')
        : $this->db->where('reel_id', $post_id)->count_all_results('reel_comment');

    // 🔹 Shares
    $shareCount = $this->db
        ->where('post_id', $post_id)
        ->count_all_results('chats');

    // 🔹 Bookmarks
    $bookmarkCount = $this->db
        ->where('post_id', $post_id)
        ->count_all_results('bookmark_post');

    // 🔹 Views (ONLY REEL / VIDEO)
    $viewsCount = 0;
    if ($post['post_type'] === 'reel' || $post['post_type'] === 'video') {
        $viewsCount = $this->db
            ->where('reel_id', $post_id)
            ->count_all_results('view_reel');
    }

    $interactionCount = $likeCount + $commentCount + $shareCount + $bookmarkCount;

    /* ===============================
       FOLLOWER VIEW (SAFE DEFAULT)
    =============================== */
    $followerViews = 0;
    $nonFollowerViews = $viewsCount;

    $followerPercentage = $viewsCount > 0
        ? number_format(($followerViews / $viewsCount) * 100, 2) . '%'
        : '0%';

    $nonFollowerPercentage = $viewsCount > 0
        ? number_format(($nonFollowerViews / $viewsCount) * 100, 2) . '%'
        : '0%';

    /* ===============================
       MEDIA (IMAGE + REEL/VIDEO)
    =============================== */
    $media = [];

    // 🔹 IMAGE POST
    if ($post['post_type'] === 'image') {

        $images = $this->db
            ->where('post_id', $post_id)
            ->get('post_image')
            ->result_array();

        foreach ($images as $img) {
            $media[] = [
                'media_id' => (int)$img['id'],
                'url'      => base_url($img['new_post']),
                'type'     => 'image'
            ];
        }
    }

    // 🔹 REEL / VIDEO POST
    if ($post['post_type'] === 'reel' || $post['post_type'] === 'video') {

        if (!empty($post['media_file'])) {
            $media[] = [
                'media_id' => (int)$post['post_id'],
                'url'      => base_url($post['media_file']),
                'type'     => 'video'
            ];
        }
    }

    /* ===============================
       FINAL RESPONSE (FLUTTER SAFE)
    =============================== */
    echo json_encode([
        'response_code' => 200,
        'status'        => 'success',
        'message'       => 'Insight data fetched',

        'like_count'        => (int)$likeCount,
        'comment_count'     => (int)$commentCount,
        'share_count'       => (int)$shareCount,
        'bookmark_count'    => (int)$bookmarkCount,
        'views_count'       => (int)$viewsCount,
        'interaction_count' => (int)$interactionCount,

        'follower_view_percentage'     => $followerPercentage,
        'non_follower_view_percentage' => $nonFollowerPercentage,

        'followerCount'      => (int)$followerViews,
        'non_followingCount' => (int)$nonFollowerViews,

        'profile_activity' => 0,
        'media'            => $media,

        'created_at' => (string)$post['created_at'],
        'text'       => (string)($post['text'] ?? ''),
        'post_type'  => (string)$post['post_type']
    ]);
}


/* ===============================
   COMMON EMPTY RESPONSE
=============================== */
private function emptyInsightResponse($code, $message)
{
    return [
        'response_code' => $code,
        'status' => $code === 200 ? 'success' : 'failure',
        'message' => $message,

        'like_count' => 0,
        'comment_count' => 0,
        'share_count' => 0,
        'bookmark_count' => 0,
        'views_count' => 0,
        'interaction_count' => 0,
        'follower_view_percentage' => '0%',
        'non_follower_view_percentage' => '0%',
        'followerCount' => 0,
        'non_followingCount' => 0,
        'profile_activity' => 0,
        'media' => [],
        'created_at' => '',
        'text' => '',
        'post_type' => ''
    ];
}



public function add_product_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set('Asia/Kolkata');

    /* ===============================
       1️⃣ AUTH TOKEN
    =============================== */
    $authHeader = $this->input->get_request_header('Authorization', TRUE);

    if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Unauthorized"
        ]);
        return;
    }

    $token = trim(substr($authHeader, 7));

    $user = $this->db
        ->where("token", $token)
        ->where("token_expiry >=", date("Y-m-d H:i:s"))
        ->get("users")
        ->row();

    if (!$user) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Invalid or expired token"
        ]);
        return;
    }

    /* ===============================
       2️⃣ READ JSON INPUT
    =============================== */
    $requestData = json_decode(file_get_contents("php://input"), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Invalid JSON"
        ]);
        return;
    }

    /* ===============================
       3️⃣ REQUIRED FIELDS
    =============================== */
    $required = ['type', 'title', 'price_type', 'category_id'];

    foreach ($required as $field) {
        if (empty($requestData[$field])) {
            echo json_encode([
                "response_code" => "0",
                "status" => "failed",
                "message" => "$field is required"
            ]);
            return;
        }
    }

    /* ===============================
       4️⃣ IMAGE UPLOAD (MAX 2) → S3
    =============================== */
    if (empty($requestData['images']) || !is_array($requestData['images'])) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Image is required"
        ]);
        return;
    }

    $images = array_slice($requestData['images'], 0, 2);
    $savedImages = [];
    $productUserId = $requestData['user_id'] ?? $user->id;

    foreach ($images as $base64Image) {

        if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {

            $extension = strtolower($type[1]);
            $data = base64_decode(substr($base64Image, strpos($base64Image, ',') + 1));

            if ($data !== false) {
                $fileName = uniqid('product_', true) . '.' . $extension;
                $s3Key = adv_user_media_key($productUserId, 'products', null, null, $fileName);
                $mime = 'image/' . ($extension === 'jpg' ? 'jpeg' : $extension);
                $s3Url = adv_s3_upload_bytes($data, $s3Key, $mime);
                if ($s3Url !== false) {
                    $savedImages[] = $s3Url;
                }
            }
        }
    }

    if (empty($savedImages)) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Image upload to S3 failed: " . adv_s3_last_error()
        ]);
        return;
    }

    /* ===============================
       5️⃣ INSERT PRODUCT (SINGLE ROW)
    =============================== */
    $productData = [
        "user_id"     => $requestData['user_id'] ?? $user->id,
        "type"        => $requestData['type'],
        "title"       => $requestData['title'],
        "description" => $requestData['description'] ?? "",
        "price"       => ($requestData['price_type'] === 'contact')
                            ? null
                            : ($requestData['price'] ?? null),
        "price_type"  => $requestData['price_type'],
        "category_id" => $requestData['category_id'],
        "images"      => implode(',', $savedImages), // 🔥 COMMA SEPARATED
        "is_active"   => $requestData['is_active'] ?? 1,
        "created_at"  => date("Y-m-d H:i:s"),
        "updated_at"  => date("Y-m-d H:i:s")
    ];

    $this->db->insert("products", $productData);

    /* ===============================
       6️⃣ RESPONSE
    =============================== */
    echo json_encode([
        "response_code" => "1",
        "status" => "success",
        "message" => "Product added successfully",
        "images" => $savedImages
    ]);
}


public function get_business_category_post()
{
    header('Content-Type: application/json; charset=utf-8');
    date_default_timezone_set('Asia/Kolkata');

    // Read raw POST body (optional)
    $inputJSON = file_get_contents('php://input');
    $request   = json_decode($inputJSON, true);

    // 🔹 Body completely optional hai
    // Agar empty hai to bhi data aayega

    // Fetch categories
    $categories = $this->db
        ->get('business_categories')
        ->result_array();

    if (!empty($categories)) {
        echo json_encode([
            'response_code' => "1",
            'status'        => 'success',
            'message'       => 'Business categories fetched successfully',
            'data'          => $categories
        ]);
    } else {
        echo json_encode([
            'response_code' => "0",
            'status'        => 'failure',
            'message'       => 'No categories found',
            'data'          => []
        ]);
    }
}



public function get_product_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set('Asia/Kolkata');

    /* ===============================
       1️⃣ AUTH TOKEN
    =============================== */
    $authHeader = $this->input->get_request_header('Authorization', TRUE);

    if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Unauthorized"
        ]);
        return;
    }

    $token = trim(substr($authHeader, 7));

    $user = $this->db
        ->where("token", $token)
        ->where("token_expiry >=", date("Y-m-d H:i:s"))
        ->get("users")
        ->row();

    if (!$user) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Invalid or expired token"
        ]);
        return;
    }

    /* ===============================
       2️⃣ READ JSON INPUT
    =============================== */
    $requestData = json_decode(file_get_contents("php://input"), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Invalid JSON"
        ]);
        return;
    }

    if (empty($requestData['user_id'])) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "user_id is required"
        ]);
        return;
    }

    // 🔥 MAIN CHANGE HERE
    $user_id = $requestData['user_id'];   // user_id ko hi business_id treat kar rahe hain
    $base_url = base_url();

    /* ===============================
       3️⃣ FETCH PRODUCTS (ONLY THAT USER)
    =============================== */
    $products = $this->db
        ->where("user_id", $user_id)   // ✅ FIXED
        ->where("is_active", 1)
        ->order_by("id", "DESC")
        ->get("products")
        ->result_array();

    if (empty($products)) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "No products found for this user"
        ]);
        return;
    }

    /* ===============================
       4️⃣ FORMAT RESPONSE
    =============================== */
    $data = [];

    foreach ($products as $row) {
        $data[] = [
            "id"          => $row['id'],
            "user_id" => $row['user_id'],
            "type"        => $row['type'],
            "title"       => $row['title'],
            "description" => $row['description'],
            "price"       => $row['price'],
            "price_type"  => $row['price_type'],
            "category_id" => $row['category_id'],
            "image"       => !empty($row['images']) ? $base_url . $row['images'] : null,
            "is_active"   => $row['is_active'],
            "created_at"  => $row['created_at']
        ];
    }

    /* ===============================
       5️⃣ RESPONSE
    =============================== */
    echo json_encode([
        "response_code" => "1",
        "status" => "success",
        "message" => "User wise product list fetched successfully",
        "data" => $data
    ]);
}

public function update_product_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set('Asia/Kolkata');

    /* ===============================
       1️⃣ AUTH TOKEN
    =============================== */
    $authHeader = $this->input->get_request_header('Authorization', TRUE);

    if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Unauthorized"
        ]);
        return;
    }

    $token = trim(substr($authHeader, 7));

    $user = $this->db
        ->where("token", $token)
        ->where("token_expiry >=", date("Y-m-d H:i:s"))
        ->get("users")
        ->row();

    if (!$user) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Invalid or expired token"
        ]);
        return;
    }

    /* ===============================
       2️⃣ READ JSON INPUT
    =============================== */
    $requestData = json_decode(file_get_contents("php://input"), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Invalid JSON"
        ]);
        return;
    }

    /* ===============================
       3️⃣ REQUIRED ID
    =============================== */
    if (empty($requestData['id'])) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "id is required"
        ]);
        return;
    }

    /* ===============================
       4️⃣ CHECK PRODUCT
    =============================== */
    $product = $this->db
        ->where("id", $requestData['id'])
        ->where("user_id", $user->id)
        ->get("products")
        ->row();

    if (!$product) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Product not found"
        ]);
        return;
    }

    /* ===============================
       5️⃣ IMAGE UPDATE (OPTIONAL) → S3
    =============================== */
    $imagePath = $product->images; // default old image

    if (!empty($requestData['images']) && is_array($requestData['images'])) {

        // only first image
        $base64Image = $requestData['images'][0];

        if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {

            $extension = strtolower($type[1]);
            $data = base64_decode(substr($base64Image, strpos($base64Image, ',') + 1));

            if ($data !== false) {
                $fileName = uniqid('product_', true) . '.' . $extension;
                $s3Key = adv_user_media_key($user->id, 'products', null, null, $fileName);
                $mime = 'image/' . ($extension === 'jpg' ? 'jpeg' : $extension);
                $s3Url = adv_s3_upload_bytes($data, $s3Key, $mime);
                if ($s3Url !== false) {
                    $imagePath = $s3Url;
                }
            }
        }
    }

    /* ===============================
       6️⃣ UPDATE PRODUCT
    =============================== */
    $updateData = [
        "type"        => $requestData['type']        ?? $product->type,
        "title"       => $requestData['title']       ?? $product->title,
        "description" => $requestData['description'] ?? $product->description,
        "price"       => ($requestData['price_type'] ?? $product->price_type) === 'contact'
                            ? null
                            : ($requestData['price'] ?? $product->price),
        "price_type"  => $requestData['price_type']  ?? $product->price_type,
        "category_id" => $requestData['category_id'] ?? $product->category_id,
        "images"      => $imagePath,
        "is_active"   => $requestData['is_active']   ?? $product->is_active,
        "updated_at"  => date("Y-m-d H:i:s")
    ];

    $this->db->where("id", $product->id)->update("products", $updateData);

    /* ===============================
       7️⃣ RESPONSE
    =============================== */
    echo json_encode([
        "response_code" => "1",
        "status" => "success",
        "message" => "Product updated successfully",
        "image" => $imagePath
    ]);
}


public function delete_product_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set('Asia/Kolkata');

    /* ===============================
       1️⃣ AUTH TOKEN
    =============================== */
    $authHeader = $this->input->get_request_header('Authorization', TRUE);

    if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Unauthorized"
        ]);
        return;
    }

    $token = trim(substr($authHeader, 7));

    $user = $this->db
        ->where("token", $token)
        ->where("token_expiry >=", date("Y-m-d H:i:s"))
        ->get("users")
        ->row();

    if (!$user) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Invalid or expired token"
        ]);
        return;
    }

    /* ===============================
       2️⃣ READ JSON INPUT
    =============================== */
    $requestData = json_decode(file_get_contents("php://input"), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Invalid JSON"
        ]);
        return;
    }

    /* ===============================
       3️⃣ REQUIRED ID
    =============================== */
    if (empty($requestData['id'])) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "id is required"
        ]);
        return;
    }

    /* ===============================
       4️⃣ CHECK PRODUCT OWNERSHIP
    =============================== */
    $product = $this->db
        ->where("id", $requestData['id'])
        ->where("user_id", $user->id)
        ->get("products")
        ->row();

    if (!$product) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Product not found"
        ]);
        return;
    }

    /* ===============================
       5️⃣ DELETE IMAGE FILE
    =============================== */
    if (!empty($product->images)) {
        $imageFile = FCPATH . $product->images;
        if (file_exists($imageFile)) {
            unlink($imageFile);
        }
    }

    /* ===============================
       6️⃣ DELETE PRODUCT
    =============================== */
    $this->db->where("id", $product->id)->delete("products");

    /* ===============================
       7️⃣ RESPONSE
    =============================== */
    echo json_encode([
        "response_code" => "1",
        "status" => "success",
        "message" => "Product deleted successfully"
    ]);
}

public function second_user_product_post()
{
    header("Content-Type: application/json");
    date_default_timezone_set('Asia/Kolkata');

    /* ===============================
       1️⃣ AUTH TOKEN
    =============================== */
    $authHeader = $this->input->get_request_header('Authorization', TRUE);

    if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Unauthorized"
        ]);
        return;
    }

    $token = trim(substr($authHeader, 7));

    $user = $this->db
        ->where("token", $token)
        ->where("token_expiry >=", date("Y-m-d H:i:s"))
        ->get("users")
        ->row();

    if (!$user) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Invalid or expired token"
        ]);
        return;
    }

    /* ===============================
       2️⃣ READ JSON INPUT
    =============================== */
    $requestData = json_decode(file_get_contents("php://input"), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "Invalid JSON"
        ]);
        return;
    }

    // ✅ CHANGE HERE
    if (empty($requestData['to_user_id'])) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "to_user_id is required"
        ]);
        return;
    }

    // 🔥 to_user_id ko hi product owner maan rahe hain
    $to_user_id = $requestData['to_user_id'];
    $base_url = base_url();

    /* ===============================
       3️⃣ FETCH PRODUCTS (SECOND USER)
    =============================== */
    $products = $this->db
        ->where("user_id", $to_user_id)   // ✅ IMPORTANT
        ->where("is_active", 1)
        ->order_by("id", "DESC")
        ->get("products")
        ->result_array();

    if (empty($products)) {
        echo json_encode([
            "response_code" => "0",
            "status" => "failed",
            "message" => "No products found for this user"
        ]);
        return;
    }

    /* ===============================
       4️⃣ FORMAT RESPONSE
    =============================== */
    $data = [];

    foreach ($products as $row) {
        $data[] = [
            "id"          => (int)$row['id'],
            "user_id"     => (int)$row['user_id'],
            "type"        => $row['type'],
            "title"       => $row['title'],
            "description" => $row['description'],
            "price"       => $row['price'],
            "price_type"  => $row['price_type'],
            "category_id" => $row['category_id'],
            "image"       => !empty($row['images']) ? $base_url . $row['images'] : null,
            "is_active"   => (int)$row['is_active'],
            "created_at"  => $row['created_at']
        ];
    }

    /* ===============================
       5️⃣ RESPONSE
    =============================== */
    echo json_encode([
        "response_code" => "1",
        "status" => "success",
        "message" => "Second user product list fetched successfully",
        "data" => $data
    ]);
}


public function location_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set("Asia/Kolkata");

    /* ===============================
       INPUT
    =============================== */
    $input = json_decode(file_get_contents("php://input"), true);
    $search = trim($input['search'] ?? '');

    if ($search == '') {
        echo json_encode([
            "status" => false,
            "message" => "Search keyword required",
            "data" => []
        ]);
        return;
    }

    /* ===============================
       SEARCH QUERY
    =============================== */
    $this->db->select("
        t.id AS taluka_id,
        t.taluka_name,
        d.district_name,
        s.state_name
    ");
    $this->db->from("taluka t");
    $this->db->join("distric d", "d.id = t.district_id");
    $this->db->join("states s", "s.id = d.state_id");
    $this->db->group_start();
        $this->db->like("t.taluka_name", $search);
        $this->db->or_like("d.district_name", $search);
        $this->db->or_like("s.state_name", $search);
    $this->db->group_end();
    $this->db->limit(20);

    $result = $this->db->get()->result_array();

    /* ===============================
       FORMAT RESPONSE
    =============================== */
    $final = [];
    foreach ($result as $row) {
        $final[] = [
            "taluka_id" => (int)$row['taluka_id'],
            "location"  => $row['taluka_name'] . ", " .
                           $row['district_name'] . ", " .
                           $row['state_name'],
            "taluka"    => $row['taluka_name'],
            "district"  => $row['district_name'],
            "state"     => $row['state_name']
        ];
    }

    echo json_encode([
        "status" => true,
        "message" => "Location list",
        "data" => $final
    ]);
}

public function user_blocklist_post()
{
    header("Content-Type: application/json; charset=utf-8");
    date_default_timezone_set("Asia/Kolkata");

    /* ================= AUTHORIZATION ================= */
    $headers = $this->input->request_headers();

    if (!isset($headers['Authorization'])) {
        echo json_encode([
            "response_code" => "0",
            "status" => "error",
            "message" => "Authorization token missing",
            "user_blocklist" => []
        ]);
        return;
    }

    $token = trim(str_replace("Bearer", "", $headers['Authorization']));

    // 🔐 Validate token & get user_id
    $user = $this->db
        ->where('token', $token)
        ->get('users')
        ->row();

    if (!$user) {
        echo json_encode([
            "response_code" => "0",
            "status" => "error",
            "message" => "Invalid token",
            "user_blocklist" => []
        ]);
        return;
    }

    $user_id = $user->id;

    /* ================= FETCH BLOCK LIST ================= */
    $blocked_users = $this->db
        ->select('u.id, u.first_name, u.last_name, u.username, u.email, u.country_code, u.mobile, u.login_type, u.profile_pic')
        ->from('profile_block pb')
        ->join('users u', 'u.id = pb.block_user_id', 'inner')
        ->where('pb.user_id', $user_id)
        ->get()
        ->result();

    if (empty($blocked_users)) {
        echo json_encode([
            "response_code" => "0",
            "status" => "error",
            "message" => "No blocked users found",
            "user_blocklist" => []
        ]);
        return;
    }

    /* ================= RESPONSE BUILD ================= */
    $list = [];

    foreach ($blocked_users as $row) {

        if (!empty($row->profile_pic)) {
            $profile_pic = adv_profile_pic_url($row->profile_pic);
        } else {
            $profile_pic = "";
        }

        $list[] = [
            "user_id"       => (string)$row->id,
            "first_name"    => $row->first_name ?? "",
            "last_name"     => $row->last_name ?? "",
            "username"      => $row->username ?? "",
            "email"         => $row->email ?? "",
            "country_code"  => $row->country_code ?? "",
            "phone"         => $row->phone ?? "",
            "login_type"    => $row->login_type ?? "",
            "is_block"      => "1",
            "profile_pic"   => $profile_pic
        ];
    }

    /* ================= FINAL RESPONSE ================= */
    echo json_encode([
        "response_code" => "1",
        "status" => "success",
        "message" => "Block list fetched successfully",
        "user_blocklist" => $list
    ]);
}

public function add_rate_post()
{
    header('Content-Type: application/json');
    date_default_timezone_set('Asia/Kolkata');

    // 1️⃣ Read JSON
    $json = json_decode(file_get_contents('php://input'), true);

    if (
        !isset($json['rate']) || $json['rate'] === '' ||
        !isset($json['tax'])  || $json['tax'] === ''
    ) {
        echo json_encode([
            "status" => false,
            "message" => "Rate and Tax both are required"
        ]);
        return;
    }

    $rate = $json['rate'];
    $tax  = $json['tax'];

    // 2️⃣ Check if rate already exists (single row table)
    $check = $this->db->get('rate')->row();

    if ($check) {
        // 🔄 UPDATE
        $update = $this->db->update(
            'rate',
            [
                'rate'       => $rate,
                'tax'        => $tax,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            ['id' => $check->id]
        );

        if ($update) {
            echo json_encode([
                "status" => true,
                "message" => "Rate & Tax updated successfully"
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Failed to update rate & tax"
            ]);
        }

    } else {
        // ➕ INSERT (first time only)
        $insert = $this->db->insert('rate', [
            'rate'       => $rate,
            'tax'        => $tax,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        if ($insert) {
            echo json_encode([
                "status" => true,
                "message" => "Rate & Tax added successfully"
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Failed to add rate & tax"
            ]);
        }
    }
}




public function get_rate_post()
{
    header('Content-Type: application/json');
    date_default_timezone_set('Asia/Kolkata');

    // 1️⃣ Get single rate row
    $rate = $this->db
        ->select('id, rate, tax')
        ->from('rate')
        ->limit(1)
        ->get()
        ->row();

    // 2️⃣ Response
    if ($rate) {
        echo json_encode([
            "status" => true,
            "message" => "Rate fetched successfully",
            "data" => $rate
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Rate not found",
            "data" => null
        ]);
    }
}

/**
 * Ingest / upsert AI generation logs from the media service (image/video).
 * Auth: X-Media-Log-Key header (shared secret). No user JWT required.
 *
 * POST index.php/api/PostController/log_generation
 */
public function log_generation_post()
{
    header('Content-Type: application/json');
    date_default_timezone_set('Asia/Kolkata');

    $expected = getenv('MEDIA_LOG_SECRET') ?: 'advpost-media-log-2026';
    $provided = $this->input->get_request_header('X-Media-Log-Key', true);
    if (!$provided) {
        $provided = $this->input->get_request_header('x-media-log-key', true);
    }
    if (!$provided || !hash_equals((string) $expected, (string) $provided)) {
        return $this->response([
            'status' => false,
            'message' => 'Unauthorized',
        ], 401);
    }

    $raw = $this->input->raw_input_stream;
    $body = json_decode($raw ?: '[]', true);
    if (!is_array($body)) {
        $body = $this->post() ?: [];
    }
    if (!is_array($body) || empty($body)) {
        return $this->response([
            'status' => false,
            'message' => 'Invalid JSON body',
        ], 400);
    }

    $jobId = trim((string) ($body['job_id'] ?? ''));
    $userId = (int) ($body['user_id'] ?? 0);
    $mediaKind = strtolower(trim((string) ($body['media_kind'] ?? '')));
    if ($jobId === '' || $userId <= 0 || !in_array($mediaKind, ['image', 'video'], true)) {
        return $this->response([
            'status' => false,
            'message' => 'job_id, user_id and media_kind (image|video) are required',
        ], 400);
    }

    $status = strtolower(trim((string) ($body['status'] ?? 'queued')));
    if (!in_array($status, ['queued', 'processing', 'completed', 'failed'], true)) {
        $status = 'queued';
    }

    $encodeMaybe = function ($value) {
        if ($value === null) {
            return null;
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return (string) $value;
    };

    $row = [
        'job_id' => $jobId,
        'user_id' => $userId,
        'media_kind' => $mediaKind,
        'status' => $status,
        'user_prompt' => isset($body['user_prompt']) ? (string) $body['user_prompt'] : null,
        'language' => isset($body['language']) ? (string) $body['language'] : null,
        'size' => isset($body['size']) ? (string) $body['size'] : null,
        'quality' => isset($body['quality']) ? (string) $body['quality'] : null,
        'duration_seconds' => isset($body['duration_seconds']) ? (int) $body['duration_seconds'] : null,
        'camera_motion' => isset($body['camera_motion']) ? (string) $body['camera_motion'] : null,
        'starting_image_type' => isset($body['starting_image_type']) ? (string) $body['starting_image_type'] : null,
        'final_prompt' => isset($body['final_prompt']) ? (string) $body['final_prompt'] : null,
        'plan_json' => $encodeMaybe($body['plan_json'] ?? null),
        'voiceover_script' => isset($body['voiceover_script']) ? (string) $body['voiceover_script'] : null,
        'scene_prompts' => $encodeMaybe($body['scene_prompts'] ?? null),
        'output_url' => isset($body['output_url']) ? (string) $body['output_url'] : null,
        's3_key' => isset($body['s3_key']) ? (string) $body['s3_key'] : null,
        'filename' => isset($body['filename']) ? (string) $body['filename'] : null,
        'error_message' => isset($body['error_message']) ? (string) $body['error_message'] : null,
        'progress' => isset($body['progress']) ? (string) $body['progress'] : null,
        'meta_json' => $encodeMaybe($body['meta_json'] ?? null),
    ];

    if ($status === 'queued' && empty($body['started_at'])) {
        $row['started_at'] = date('Y-m-d H:i:s');
    }
    if (in_array($status, ['completed', 'failed'], true)) {
        $row['completed_at'] = date('Y-m-d H:i:s');
    }

    // Drop nulls so upsert does not wipe existing columns with NULL.
    $row = array_filter($row, static function ($v) {
        return $v !== null;
    });

    try {
        $id = $this->Common_model->upsert_generation_log($row);
    } catch (Throwable $e) {
        return $this->response([
            'status' => false,
            'message' => 'Failed to save generation log',
            'error' => $e->getMessage(),
        ], 500);
    }

    return $this->response([
        'status' => true,
        'message' => 'Generation log saved',
        'id' => $id,
        'job_id' => $jobId,
    ], 200);
}

}