<?php
// index page
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'util.php';
require_once 'sms.php';
require_once 'db.php';
require_once 'agent.php';
include 'menu.php';

$sessionId = filter_input(INPUT_POST, 'sessionId', FILTER_DEFAULT);
$phoneNumber = filter_input(INPUT_POST, 'phoneNumber', FILTER_DEFAULT);
$serviceCode = filter_input(INPUT_POST, 'serviceCode', FILTER_DEFAULT);
$text = filter_input(INPUT_POST, 'text', FILTER_DEFAULT);

if (!$sessionId || !$phoneNumber || !$serviceCode) {
    echo "END Invalid request parameters";
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    echo "END System error. Please try again later.";
    exit;
}

$isRegistered = false;
try {
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone_number = ?");
    $stmt->execute([$phoneNumber]);
    $isRegistered = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    error_log("Database query failed: " . $e->getMessage());
    echo "END System error. Please try again later.";
    exit;
}

$menu = new Menu($phoneNumber, $conn);
$text = $menu->middleware($text);

if($text == "" && !$isRegistered){
    $menu->mainMenuUnregistered();
} else if($text == "" && $isRegistered){
    $menu->mainMenuRegistered();
} else if(!$isRegistered){
    $textArray = explode("*", $text);
    switch($textArray[0]){
        case 1:
            $menu->menuRegister($textArray);
            break;
        case 2:
            $menu->menuAgentRegistration($textArray);
            break;
        default:
            echo "END Invalid option, Retry";
    }
} else {
    $textArray = explode("*", $text);
    switch($textArray[0]){
        case 1:
            $menu->menuSendMoney($textArray);
            break;
        case 2:
            $menu->menuWithdrawMoney($textArray);
            break;
        case 3:
            $menu->menuCheckBalance($textArray);
            break;
        case 4:
            $menu->menuAgentOperations($textArray);
            break;
        default:
            echo "END Invalid choice\n";
    }
}
?>