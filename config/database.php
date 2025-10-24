<?php
class Database {
    private $host = 'localhost';
    private $user = 'root';
    private $password = '';
    private $database = 'hospital_db';
    public $conn;

    public function __construct() {
        try {
            // Connect directly to the database (assumes it already exists)
            $this->conn = new mysqli($this->host, $this->user, $this->password, $this->database);
            
            if ($this->conn->connect_error) {
                throw new Exception("Database connection failed: " . $this->conn->connect_error . 
                    "\n\nPlease make sure:\n" .
                    "1. MySQL server is running\n" .
                    "2. Database 'hospital_db' exists\n" .
                    "3. All tables, views, functions, procedures, and triggers are created manually\n" .
                    "4. Sample data is inserted");
            }
            
            // Set charset to prevent encoding issues
            $this->conn->set_charset("utf8mb4");
            
        } catch (Exception $e) {
            die("Database Error: " . $e->getMessage());
        }
    }
    
    public function query($sql) {
        $result = $this->conn->query($sql);
        if (!$result) {
            throw new Exception("Query failed: " . $this->conn->error . "\nSQL: " . $sql);
        }
        return $result;
    }
    
    public function prepare($sql) {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error . "\nSQL: " . $sql);
        }
        return $stmt;
    }
    
    public function getError() {
        return $this->conn->error;
    }
    
    public function getInsertId() {
        return $this->conn->insert_id;
    }
    
    public function beginTransaction() {
        $this->conn->begin_transaction();
    }
    
    public function commit() {
        $this->conn->commit();
    }
    
    public function rollback() {
        $this->conn->rollback();
    }
    
    public function escapeString($string) {
        return $this->conn->real_escape_string($string);
    }
    
    public function getDatabaseInfo() {
        $info = [];
        
        // Get table counts
        $tables = ['doctors', 'patients', 'chambers', 'appointments', 'payments', 'medical_records', 'fee_audit_log'];
        foreach ($tables as $table) {
            try {
                $result = $this->query("SELECT COUNT(*) as count FROM $table");
                $info['tables'][$table] = $result->fetch_assoc()['count'];
            } catch (Exception $e) {
                $info['tables'][$table] = 'Not found';
            }
        }
        
        // Get view count
        try {
            $result = $this->query("SELECT COUNT(*) as count FROM information_schema.views WHERE table_schema = '$this->database'");
            $info['views'] = $result->fetch_assoc()['count'];
        } catch (Exception $e) {
            $info['views'] = 'Error';
        }
        
        // Get function count
        try {
            $result = $this->query("SELECT COUNT(*) as count FROM information_schema.routines WHERE routine_schema = '$this->database' AND routine_type = 'FUNCTION'");
            $info['functions'] = $result->fetch_assoc()['count'];
        } catch (Exception $e) {
            $info['functions'] = 'Error';
        }
        
        // Get procedure count
        try {
            $result = $this->query("SELECT COUNT(*) as count FROM information_schema.routines WHERE routine_schema = '$this->database' AND routine_type = 'PROCEDURE'");
            $info['procedures'] = $result->fetch_assoc()['count'];
        } catch (Exception $e) {
            $info['procedures'] = 'Error';
        }
        
        // Get trigger count
        try {
            $result = $this->query("SELECT COUNT(*) as count FROM information_schema.triggers WHERE trigger_schema = '$this->database'");
            $info['triggers'] = $result->fetch_assoc()['count'];
        } catch (Exception $e) {
            $info['triggers'] = 'Error';
        }
        
        return $info;
    }
    
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

// Initialize database connection
try {
    $db = new Database();
} catch (Exception $e) {
    die("<div style='padding: 20px; background: #fee; border: 1px solid red; margin: 20px; font-family: Arial;'>
        <h3 style='color: #d00;'>🚨 Database Connection Failed</h3>
        <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        
        <div style='background: #fff; padding: 15px; border-radius: 5px; margin: 15px 0;'>
            <h4 style='margin-top: 0;'>📋 Manual Setup Required:</h4>
            <ol>
                <li>Open <strong>phpMyAdmin</strong> (http://localhost/phpmyadmin)</li>
                <li>Create database named: <code>hospital_db</code></li>
                <li>Run all SQL files from the <code>sql/</code> folder in this order:
                    <ul>
                        <li>schema.sql (tables)</li>
                        <li>sample_data.sql (sample data)</li>
                        <li>views.sql (database views)</li>
                        <li>functions.sql (stored functions)</li>
                        <li>procedures.sql (stored procedures)</li>
                        <li>triggers.sql (triggers)</li>
                    </ul>
                </li>
                <li>Refresh this page</li>
            </ol>
        </div>
        
        <p><a href='test_connection.php' style='background: #40E0D0; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; display: inline-block;'>🔧 Test Connection</a></p>
    </div>");
}
?>