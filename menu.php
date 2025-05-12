<?php
require_once 'sms.php';
require_once 'util.php';

class Menu {
    protected $phoneNumber;
    protected $conn;
    protected $sms;

    function __construct($phoneNumber, $conn) {
        $this->phoneNumber = $phoneNumber;
        $this->conn = $conn;
        $this->sms = new Sms($phoneNumber);
    }

    public function mainMenuUnregistered() {
        $response = "CON Welcome to XYZ MOMO \n"; 
        $response .= "1. Register as User\n"; 
        $response .= "2. Register as Agent\n";
        echo $response;
    }

    public function mainMenuRegistered() {
        $response = "CON Welcome back to XYZ MOMO.\n";
        $response .= "1. Send Money\n";
        $response .= "2. Withdraw Money\n";
        $response .= "3. Check Balance\n";
        $response .= "4. Agent Operations\n";
        echo $response;
    }

    public function menuRegister($textArray) {
        $level = count($textArray);
        
        if($level == 1) {
            echo "CON Enter your full name\n";
        } else if($level == 2) {
            echo "CON Enter your PIN\n";
        } else if($level == 3) {
            echo "CON Re-enter your PIN\n";
        } else if($level == 4) {
            $name = $textArray[1];
            $pin = $textArray[2];
            $confirmPin = $textArray[3];
            
            if($pin != $confirmPin) {
                echo "END PINs do not match. Please try again.";
                return;
            }
            
            try {
                $stmt = $this->conn->prepare("INSERT INTO users (name, phone_number, pin, balance) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $this->phoneNumber, password_hash($pin, PASSWORD_DEFAULT), Util::$USER_BALANCE]);
                
                $msg = "Welcome $name! Your account has been created with " . Util::$USER_BALANCE . " RWF.";
                $this->sms->sendSMS($msg, $this->phoneNumber);
                
                echo "END Registration successful! You will receive an SMS with your account details.";
            } catch (PDOException $e) {
                error_log("User registration failed: " . $e->getMessage());
                echo "END Registration failed. Please try again later.";
            }
        }
    }

    public function menuAgentRegistration($textArray) {
        $level = count($textArray);
        
        if($level == 1) {
            echo "CON Enter your full name\n";
        } else if($level == 2) {
            echo "CON Enter your business name\n";
        } else if($level == 3) {
            echo "CON Enter your PIN\n";
        } else if($level == 4) {
            echo "CON Re-enter your PIN\n";
        } else if($level == 5) {
            $name = $textArray[1];
            $businessName = $textArray[2];
            $pin = $textArray[3];
            $confirmPin = $textArray[4];
            
            if($pin != $confirmPin) {
                echo "END PINs do not match. Please try again.";
                return;
            }
            
            try {
                $stmt = $this->conn->prepare("INSERT INTO agents (name, business_name, phone_number, pin, balance) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $businessName, $this->phoneNumber, password_hash($pin, PASSWORD_DEFAULT), Util::$AGENT_INITIAL_BALANCE]);
                
                $msg = "Welcome $name! You have been registered as an agent. Your initial balance is " . Util::$AGENT_INITIAL_BALANCE . " RWF.";
                $this->sms->sendSMS($msg, $this->phoneNumber);
                
                echo "END Registration successful! You will receive an SMS with your agent details.";
            } catch (PDOException $e) {
                error_log("Agent registration failed: " . $e->getMessage());
                echo "END Registration failed. Please try again later.";
            }
        }
    }

    public function menuSendMoney($textArray) {
        $level = count($textArray);
        
        if($level == 1) {
            echo "CON Enter recipient phone number\n";
        } else if($level == 2) {
            echo "CON Enter amount\n";
        } else if($level == 3) {
            echo "CON Enter your PIN\n";
        } else if($level == 4) {
            $recipientPhone = $textArray[1];
            $amount = floatval($textArray[2]);
            $pin = $textArray[3];
            
            if($amount <= 0) {
                echo "END Invalid amount";
                return;
            }
            
            try {
                // Verify sender's PIN and balance
                $stmt = $this->conn->prepare("SELECT id, balance, pin FROM users WHERE phone_number = ?");
                $stmt->execute([$this->phoneNumber]);
                $sender = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if(!$sender || !password_verify($pin, $sender['pin'])) {
                    echo "END Invalid PIN";
                    return;
                }
                
                if($sender['balance'] < $amount) {
                    echo "END Insufficient balance";
                    return;
                }
                
                // Verify recipient exists
                $stmt = $this->conn->prepare("SELECT id FROM users WHERE phone_number = ?");
                $stmt->execute([$recipientPhone]);
                $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if(!$recipient) {
                    echo "END Recipient not found";
                    return;
                }
                
                // Perform transaction
                $this->conn->beginTransaction();
                
                // Deduct from sender
                $stmt = $this->conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$amount, $sender['id']]);
                
                // Add to recipient
                $stmt = $this->conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$amount, $recipient['id']]);
                
                // Record transaction
                $stmt = $this->conn->prepare("INSERT INTO transactions (sender_id, recipient_id, amount, type) VALUES (?, ?, ?, 'transfer')");
                $stmt->execute([$sender['id'], $recipient['id'], $amount]);
                
                $this->conn->commit();
                
                // Send SMS notifications
                $senderMsg = "You have sent $amount RWF to $recipientPhone. New balance: " . ($sender['balance'] - $amount) . " RWF";
                $recipientMsg = "You have received $amount RWF from " . $this->phoneNumber . ". New balance: " . ($recipient['balance'] + $amount) . " RWF";
                
                $this->sms->sendSMS($senderMsg, $this->phoneNumber);
                $this->sms->sendSMS($recipientMsg, $recipientPhone);
                
                echo "END Transaction successful. You will receive an SMS with details.";
                
            } catch (PDOException $e) {
                if($this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
                error_log("Send money failed: " . $e->getMessage());
                echo "END Transaction failed. Please try again later.";
            }
        }
    }

    public function menuWithdrawMoney($textArray) {
        $level = count($textArray);
        
        if($level == 1) {
            echo "CON Enter amount\n";
        } else if($level == 2) {
            echo "CON Enter agent code\n";
        } else if($level == 3) {
            echo "CON Enter your PIN\n";
        } else if($level == 4) {
            $amount = floatval($textArray[1]);
            $agentCode = $textArray[2];
            $pin = $textArray[3];
            
            if($amount <= 0) {
                echo "END Invalid amount";
                return;
            }
            
            try {
                // Verify user's PIN and balance
                $stmt = $this->conn->prepare("SELECT id, balance, pin FROM users WHERE phone_number = ?");
                $stmt->execute([$this->phoneNumber]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if(!$user || !password_verify($pin, $user['pin'])) {
                    echo "END Invalid PIN";
                    return;
                }
                
                $total = $amount + Util::$WITHDRAW_FEE;
                if($user['balance'] < $total) {
                    echo "END Insufficient balance";
                    return;
                }
                
                // Verify agent exists
                $stmt = $this->conn->prepare("SELECT id, balance FROM agents WHERE code = ?");
                $stmt->execute([$agentCode]);
                $agent = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if(!$agent) {
                    echo "END Invalid agent code";
                    return;
                }
                
                // Perform transaction
                $this->conn->beginTransaction();
                
                // Deduct from user
                $stmt = $this->conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$total, $user['id']]);
                
                // Add to agent
                $stmt = $this->conn->prepare("UPDATE agents SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$amount, $agent['id']]);
                
                // Record transaction
                $stmt = $this->conn->prepare("INSERT INTO transactions (user_id, agent_id, amount, fee, type) VALUES (?, ?, ?, ?, 'withdrawal')");
                $stmt->execute([$user['id'], $agent['id'], $amount, Util::$WITHDRAW_FEE]);
                
                $this->conn->commit();
                
                // Send SMS notifications
                $userMsg = "You have withdrawn $amount RWF. Fee: " . Util::$WITHDRAW_FEE . " RWF. New balance: " . ($user['balance'] - $total) . " RWF";
                $agentMsg = "You have received $amount RWF from " . $this->phoneNumber . ". New balance: " . ($agent['balance'] + $amount) . " RWF";
                
                $this->sms->sendSMS($userMsg, $this->phoneNumber);
                $this->sms->sendSMS($agentMsg, $agent['phone_number']);
                
                echo "END Withdrawal successful. You will receive an SMS with details.";
                
            } catch (PDOException $e) {
                if($this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
                error_log("Withdrawal failed: " . $e->getMessage());
                echo "END Withdrawal failed. Please try again later.";
            }
        }
    }

    public function menuCheckBalance($textArray) {
        $level = count($textArray);
        
        if($level == 1) {
            echo "CON Enter your PIN\n";
        } else if($level == 2) {
            $pin = $textArray[1];
            
            try {
                $stmt = $this->conn->prepare("SELECT balance, pin FROM users WHERE phone_number = ?");
                $stmt->execute([$this->phoneNumber]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if(!$user || !password_verify($pin, $user['pin'])) {
                    echo "END Invalid PIN";
                    return;
                }
                
                $msg = "Your current balance is " . $user['balance'] . " RWF";
                $this->sms->sendSMS($msg, $this->phoneNumber);
                echo "END You will receive an SMS with your balance details.";
                
            } catch (PDOException $e) {
                error_log("Balance check failed: " . $e->getMessage());
                echo "END Balance check failed. Please try again later.";
            }
        }
    }

    public function menuAgentOperations($textArray) {
        $level = count($textArray);
        
        if($level == 1) {
            $response = "CON Agent Operations\n";
            $response .= "1. Make Deposit\n";
            $response .= "2. Make Withdrawal\n";
            $response .= "3. Check Agent Balance\n";
            echo $response;
        } else if($level == 2) {
            switch($textArray[1]) {
                case 1:
                    echo "CON Enter amount to deposit\n";
                    break;
                case 2:
                    echo "CON Enter amount to withdraw\n";
                    break;
                case 3:
                    $this->checkAgentBalance();
                    break;
                default:
                    echo "END Invalid option";
            }
        } else if($level == 3) {
            $amount = floatval($textArray[2]);
            if($amount <= 0) {
                echo "END Invalid amount";
                return;
            }
            
            try {
                $stmt = $this->conn->prepare("SELECT id, balance FROM agents WHERE phone_number = ?");
                $stmt->execute([$this->phoneNumber]);
                $agent = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if(!$agent) {
                    echo "END Agent account not found";
                    return;
                }
                
                if($textArray[1] == 1) { // Deposit
                    $newBalance = $agent['balance'] + $amount;
                    $stmt = $this->conn->prepare("UPDATE agents SET balance = ? WHERE id = ?");
                    $stmt->execute([$newBalance, $agent['id']]);
                    
                    // Record transaction
                    $stmt = $this->conn->prepare("INSERT INTO transactions (agent_id, type, amount, fee, total) VALUES (?, 'deposit', ?, ?, ?)");
                    $stmt->execute([$agent['id'], $amount, Util::$DEPOSIT_FEE, $amount + Util::$DEPOSIT_FEE]);
                    
                    $msg = "Deposit of $amount RWF successful. New balance: $newBalance RWF";
                } else { // Withdrawal
                    $total = $amount + Util::$WITHDRAW_FEE;
                    if($agent['balance'] < $total) {
                        echo "END Insufficient balance";
                        return;
                    }
                    
                    $newBalance = $agent['balance'] - $total;
                    $stmt = $this->conn->prepare("UPDATE agents SET balance = ? WHERE id = ?");
                    $stmt->execute([$newBalance, $agent['id']]);
                    
                    // Record transaction
                    $stmt = $this->conn->prepare("INSERT INTO transactions (agent_id, type, amount, fee, total) VALUES (?, 'withdraw', ?, ?, ?)");
                    $stmt->execute([$agent['id'], $amount, Util::$WITHDRAW_FEE, $total]);
                    
                    $msg = "Withdrawal of $amount RWF successful. Fee: " . Util::$WITHDRAW_FEE . " RWF. New balance: $newBalance RWF";
                }
                
                $this->sms->sendSMS($msg, $this->phoneNumber);
                echo "END Transaction successful. You will receive an SMS with details.";
                
            } catch (PDOException $e) {
                error_log("Agent operation failed: " . $e->getMessage());
                echo "END Operation failed. Please try again later.";
            }
        }
    }

    protected function checkAgentBalance() {
        try {
            $stmt = $this->conn->prepare("SELECT balance FROM agents WHERE phone_number = ?");
            $stmt->execute([$this->phoneNumber]);
            $agent = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(!$agent) {
                echo "END Agent account not found";
                return;
            }
            
            $msg = "Your current balance is " . $agent['balance'] . " RWF";
            $this->sms->sendSMS($msg, $this->phoneNumber);
            echo "END You will receive an SMS with your balance details.";
            
        } catch (PDOException $e) {
            error_log("Balance check failed: " . $e->getMessage());
            echo "END Balance check failed. Please try again later.";
        }
    }

    public function middleware($text) {
        return $this->goBack($this->goMainMenu($text));
    }

    public function goBack($text) {
        $explodeText = explode("*", $text);
        while(array_search(Util::$GO_BACK, $explodeText) !== false) {
            $firstIndex = array_search(Util::$GO_BACK, $explodeText);
            array_splice($explodeText, $firstIndex-1, 2);
        }
        return join("*", $explodeText);
    }
    
    public function goMainMenu($text) {
        $explodeText = explode("*", $text);
        while(array_search(Util::$GO_MAIN_MENU, $explodeText) !== false) {
            $firstIndex = array_search(Util::$GO_MAIN_MENU, $explodeText);
            $explodeText = array_slice($explodeText, $firstIndex+1);
        }
        return join("*", $explodeText);
    }
}
?>