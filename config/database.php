<?php
class Database {
    public $conn;
    
    public function __construct() {
        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "hospital_db";
        
        $this->conn = new mysqli($servername, $username, $password, $dbname);
        
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }
    
    public function query($sql) {
        $result = $this->conn->query($sql);
        if (!$result) {
            error_log("Query failed: " . $this->conn->error);
        }
        return $result;
    }
    
    public function escape($value) {
        return $this->conn->real_escape_string($value);
    }
}

$db = new Database();
?>