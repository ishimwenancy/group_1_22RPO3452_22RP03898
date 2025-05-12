-- (db.sql – SQL script to create the database and tables for the USSD SMS project (agent module).)

-- (Drop (if exists) and create the database "ussd".)
DROP DATABASE IF EXISTS ussd;
CREATE DATABASE ussd;

-- (Use the "ussd" database.)
USE ussd;

-- (Create table "agents" (id (auto_increment), name (varchar), code (varchar), balance (decimal), created_at (timestamp)).)
REATE TABLE agents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR ( 255 ) NOT NULL,
  code VARCHAR ( 6 ) NOT NULL UNIQUE,
  balance DECIMAL ( 10, 2 ) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);C

-- (Create table "transactions" (id (auto_increment), agent_id (int), type (enum "deposit","withdraw"), amount (decimal), fee (decimal), total (decimal), created_at (timestamp)). (Also, add a foreign key (agent_id) on transactions referencing agents (id).)
CREATE TABLE transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  agent_id INT NOT NULL,
  type ENUM ( "deposit", "withdraw" ) NOT NULL,
  amount DECIMAL ( 10, 2 ) NOT NULL,
  fee DECIMAL ( 10, 2 ) NOT NULL,
  total DECIMAL ( 10, 2 ) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY ( agent_id ) REFERENCES agents ( id ) ON DELETE CASCADE
);

-- (End of db.sql.) 