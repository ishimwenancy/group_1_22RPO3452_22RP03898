<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';
use AfricasTalking\SDK\AfricasTalking;

class Sms {
    protected $phone;
    protected $AT;

    function __construct($phone){
        $this->phone = $phone;
        // WARNING: For production, use environment variables to store your API key securely!
        $this->AT = new AfricasTalking("sandbox", "atsk_81447991a2ff7c8ee9e30641bc1b9148482e86fade883a53506cf92583264a3db5014cd4");
    }
    public function sendSMS($message, $recipients){
        $sms = $this->AT->sms();
        try {
            $result = $sms->send([
                'username' => "sandbox", // Change to your live username in production
                'to' => $recipients, // Use international format for phone numbers
                'message' => $message,
                'from' => "M-Money"	// Use your approved sender ID
            ]);
            return $result;
        } catch (Exception $e) {
            error_log("SMS sending failed: " . $e->getMessage());
            return false;
        }
    }
}