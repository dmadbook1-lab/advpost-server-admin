<?php
error_reporting(0);
require APPPATH . 'libraries/RestController.php';

use chriskacerguis\RestServer\RestController;

require 'vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

defined('BASEPATH') or exit('No direct script access allowed');

class SendNotification extends RestController
{

    function __construct()
    {
        parent::__construct();
        $this->load->library('session');

        $this->load->model('Common_model');
        $this->load->database();
        $this->load->helper('url', 'form', 'html');
        date_default_timezone_set('Asia/Kolkata');
    }


      //send notifications

      public function noti_send_to_doctor_get(){
          $orders = $this->db->where('status','pending')->where('notification IS NULL')->get('order_table')->result();
          $storeOrder =[];
          foreach($orders as $order){
              $majorService = $order->major_service;
              $subService = $order->sub_service;
              $fareDisplay = $order->fare_display;
              $orderId = $order->order_id;
              
              $partnerService = $this->db->get('partner_registration')->result();
              foreach($partnerService as $service){
                  $partnerService = $service->service;
                  $partnerSubService = $service->sub_service;
                  $token = $service->token;
               
               if (strpos($partnerSubService, ',') !== false) {
                // If $partnerSubService is a comma-separated string, convert it into an array
                $partnerSubServiceArray = explode(',', $partnerSubService);
            } else {
                // Otherwise, treat it as a single value
                $partnerSubServiceArray = [$partnerSubService];
            }

            if ($majorService == $partnerService && in_array($subService, $partnerSubServiceArray) && $token != NULL) {
            $this->sendNotification_post($token, $subService,$fareDisplay);
                
                $storeOrder[] = $orderId;
            }
              }
              
          }
          
         
          $uniqueStoreOrder = array_unique($storeOrder);
          // print_r($uniqueStoreOrder); exit;
        if($uniqueStoreOrder){
            foreach($uniqueStoreOrder as $notify){
                $id = $notify;
               
                $notifiUpdate = $this->db->set('notification','Yes')->where('order_id',$id)->update('order_table');
            }
        }
        
        $response = ['message' => 'Notification Sent successfully'];

    return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($response));
         
      }
      
      
      
  
    function sendNotification_post($token,$orderId,$fareDisplay)
    {
        // Firebase JSON फाइल का पथ
        $firebaseJsonFilePath = realpath(__DIR__ . '/../../../firebase-service-account.json');
        // if ($firebaseJsonFilePath === false) {
        //     echo 'File does not exist :' . $firebaseJsonFilePath;
        // } else {
        //     echo 'File path is: ' . $firebaseJsonFilePath;
        // }
        // //  exit;
        // $json = file_get_contents($firebaseJsonFilePath);
        // $serviceAccount = json_decode($json, true);

        // if (json_last_error() !== JSON_ERROR_NONE) {
        //     echo 'Error in decoding JSON: ' . json_last_error_msg();
        // } else {
        //     print_r($serviceAccount);
        // }
        //  exit;

        // Firebase फैक्ट्री इनिशियलाइज़ करें
        $factory = (new Factory)->withServiceAccount($firebaseJsonFilePath);

        // Firebase Messaging सेवा का उपयोग करें
        $messaging = $factory->createMessaging();

        // क्लाइंट डिवाइस का FCM टोकन
       // $token = 'flboMMb4SD2wKuyYlb1Vbj:APA91bEBDnPSM2_cbZlJtUBnm4AtA8duJkLpVsxTY8nOUlOxULeXGklZhz0xXcVdaVds0XYSJ05Ug_9RersjZNWd9how5l8xx-ZeegXTBwZOTElmFrpy2fc';
        // नोटिफिकेशन टाइटल और मैसेज
        // $notificationTitle = $this->input->post('notification');
        // $notificationBody = $this->input->post('message');
       // $token = $tokens;
      $notificationTitle = 'Upashay';
      $notificationBody = 'New order Received for ' . $orderId . ' and Order value Rs'.' '.$fareDisplay;


        // Notification payload
        $notification = [
            'title' => $notificationTitle,
            'body'  => $notificationBody,
        ];

        // Data payload (optional)
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        // Create a message with the notification and data payload
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification($notification)
            ->withData($data);

        // Send the message
        try {
            $response = $messaging->send($message);

            // Response लॉग करें (ऑप्शनल)
            file_put_contents(STORAGEPATH . 'logs/notification_logs.txt', print_r($response, true), FILE_APPEND);

            echo "Notification sent successfully!";
        } catch (\Kreait\Firebase\Exception\MessagingException $e) {
            echo 'Failed to send notification: ' . $e->getMessage();
        } catch (\Kreait\Firebase\Exception\FirebaseException $e) {
            echo 'Firebase error: ' . $e->getMessage();
        }
    }
    
    
      public function noti_send_to_customer_get(){
          $orders = $this->db->where('status','ongoing')->where('notification','Yes')->get('order_table')->result();
          $storeOrder =[];
          foreach($orders as $order){
              $orderId = $order->order_id;
              $customerId = $order->customer_id;
                $customerSub = $order->sub_service;
             // $orderId = $order->order_id;
              
              $customers = $this->db->where('id',$customerId)->where('token IS NOT NULL')->get('customer_registration')->result();
              foreach($customers as $customer){
                  $customer_id = $customer->id;
                 
                  $token = $customer->token;
               

           
             $this->sendNotitoCustomer_post($token, $customerSub);
                
                $storeOrder[] = $orderId;
            
              }
              
          }
          
         
          $uniqueStoreOrder = array_unique($storeOrder);
          // print_r($uniqueStoreOrder); exit;
        if($uniqueStoreOrder){
            foreach($uniqueStoreOrder as $notify){
                $id = $notify;
               
                $notifiUpdate = $this->db->set('notification','Complete')->where('order_id',$id)->update('order_table');
            }
        }
         
      }
      
      
      
  
    function sendNotitoCustomer_post($token,$customerSub)
    {
        // Firebase JSON फाइल का पथ
        $firebaseJsonFilePath = realpath(__DIR__ . '/../../../firebase-service-account.json');
        // if ($firebaseJsonFilePath === false) {
        //     echo 'File does not exist :' . $firebaseJsonFilePath;
        // } else {
        //     echo 'File path is: ' . $firebaseJsonFilePath;
        // }
        // //  exit;
        // $json = file_get_contents($firebaseJsonFilePath);
        // $serviceAccount = json_decode($json, true);

        // if (json_last_error() !== JSON_ERROR_NONE) {
        //     echo 'Error in decoding JSON: ' . json_last_error_msg();
        // } else {
        //     print_r($serviceAccount);
        // }
        //  exit;

        // Firebase फैक्ट्री इनिशियलाइज़ करें
        $factory = (new Factory)->withServiceAccount($firebaseJsonFilePath);

        // Firebase Messaging सेवा का उपयोग करें
        $messaging = $factory->createMessaging();

        // क्लाइंट डिवाइस का FCM टोकन
       // $token = 'flboMMb4SD2wKuyYlb1Vbj:APA91bEBDnPSM2_cbZlJtUBnm4AtA8duJkLpVsxTY8nOUlOxULeXGklZhz0xXcVdaVds0XYSJ05Ug_9RersjZNWd9how5l8xx-ZeegXTBwZOTElmFrpy2fc';
        // नोटिफिकेशन टाइटल और मैसेज
        // $notificationTitle = $this->input->post('notification');
        // $notificationBody = $this->input->post('message');
       // $token = $tokens;
          $notificationTitle = 'mykidvan';
        $notificationBody = $customerSub." "."Order Accepted Successfully";

        // Notification payload
        $notification = [
            'title' => $notificationTitle,
            'body'  => $notificationBody,
        ];

        // Data payload (optional)
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        // Create a message with the notification and data payload
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification($notification)
            ->withData($data);

        // Send the message
        try {
            $response = $messaging->send($message);

            // Response लॉग करें (ऑप्शनल)
            file_put_contents(STORAGEPATH . 'logs/notification_logs.txt', print_r($response, true), FILE_APPEND);

            echo "Notification sent successfully!";
        } catch (\Kreait\Firebase\Exception\MessagingException $e) {
            echo 'Failed to send notification: ' . $e->getMessage();
        } catch (\Kreait\Firebase\Exception\FirebaseException $e) {
            echo 'Firebase error: ' . $e->getMessage();
        }
    }

}