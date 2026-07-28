<?php
defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . 'libraries/RestController.php';
require VENDORPATH . 'autoload.php';

use chriskacerguis\RestServer\RestController;
use Google\Auth\OAuth2;

class FirebaseToken extends RestController
{
    public function __construct()
    {
        parent::__construct();
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
}
