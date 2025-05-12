<?php
/*
 * agent.php – Agent Module for USSD SMS Project
 *
 * This file simulates an agent's registration, deposit, and withdraw (with fees) for a mobile money system.
 * In production, you must create a database (e.g. "ussd") and tables (agents, transactions) as follows:
 *
 * (a) Table "agents" (id (auto_increment), name (varchar), code (varchar), balance (decimal), created_at (timestamp)).
 * (b) Table "transactions" (id (auto_increment), agent_id (int), type (enum "deposit","withdraw"), amount (decimal), fee (decimal), total (decimal), created_at (timestamp)).
 *
 * (You can use a DB library (e.g. PDO or mysqli) to insert/update records.)
 *
 * (For demo purposes, we simulate DB operations using a global array (or a file) and log (or echo) the transaction.)
 */

// (Global constants (or config) for fees and initial balance – in production, use a config file or DB.)
define("INITIAL_BALANCE", 1000.00);
define("DEPOSIT_FEE", 20.00);
define("WITHDRAW_FEE", 200.00);

// (Simulated "DB" (global array) – in production, use a real DB.)
global $agents, $transactions;
$agents = [];
$transactions = [];

class Agent {
    public $name;
    public $code;
    public $balance;

    public function __construct($name) {
        $this->name = $name;
        // (Simulate auto-assigning a random 6-digit code – in production, use a DB query.)
        $this->code = sprintf("%06d", rand(100000, 999999));
        $this->balance = INITIAL_BALANCE;
        // (Simulate "insert" into "agents" – in production, use a DB insert.)
        global $agents;
        $agents[$this->code] = $this;
        echo ("Agent '" . $this->name . "' registered with code '" . $this->code . "' (initial balance='" . $this->balance . "').");
    }

    public function deposit($amount) {
         if ($amount <= 0) {
             echo (" Deposit amount must be positive.");
             return false;
         }
         $fee = DEPOSIT_FEE;
         $total = $amount - $fee;
         if ($total <= 0) {
             echo (" Deposit (" . $amount . ") minus fee (" . $fee . ") is not positive.");
             return false;
         }
         $this->balance += $total;
         // (Simulate "insert" into "transactions" – in production, use a DB insert.)
         global $transactions;
         $transactions[] = (object) (array("agent_id" => $this->code, "type" => "deposit", "amount" => $amount, "fee" => $fee, "total" => $total, "created_at" => date("Y-m-d H:i:s")));
         echo (" Agent '" . $this->name . "' (" . $this->code . ") deposited '" . $amount . "' (fee='" . $fee . "', total='" . $total . "'). New balance='" . $this->balance . "'.");
         return true;
    }

    public function withdraw($amount) {
         if ($amount <= 0) {
             echo (" Withdraw amount must be positive.");
             return false;
         }
         $fee = WITHDRAW_FEE;
         $total = $amount + $fee; // (Withdraw total = amount + fee.)
         if ($this->balance < $total) {
             echo (" Agent '" . $this->name . "' (" . $this->code . ") has insufficient balance (" . $this->balance . ") for withdraw '" . $amount . "' (fee='" . $fee . "', total='" . $total . "').");
             return false;
         }
         $this->balance -= $total;
         // (Simulate "insert" into "transactions" – in production, use a DB insert.)
         global $transactions;
         $transactions[] = (object) (array("agent_id" => $this->code, "type" => "withdraw", "amount" => $amount, "fee" => $fee, "total" => $total, "created_at" => date("Y-m-d H:i:s")));
         echo (" Agent '" . $this->name . "' (" . $this->code . ") withdrew '" . $amount . "' (fee='" . $fee . "', total='" . $total . "'). New balance='" . $this->balance . "'.");
         return true;
    }
}

/*
 * Demo (or usage) block – (uncomment to test agent's methods)
 *
 * (In production, you'd call these methods from your USSD (or SMS) endpoint.)
 *
 * (Example:)
 * ( $agent = new Agent("Francois"); )
 * ( $agent->deposit( 1000.00 ); )
 * ( $agent->withdraw( 500.00 ); )
 */ 