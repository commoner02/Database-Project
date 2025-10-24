<?php
require_once 'config/database.php';

echo "<h1>Manual Setup Test</h1>";

try {
    // Test connection
    echo "<p>✅ Database connected</p>";
    
    // Test tables
    $tables = ['doctors', 'patients', 'chambers', 'appointments', 'payments', 'medical_records'];
    
    foreach ($tables as $table) {
        $result = $db->query("SELECT COUNT(*) as count FROM $table");
        $count = $result->fetch_assoc()['count'];
        echo "<p>✅ $table: $count records</p>";
    }
    
    echo "<h3>Sample Data:</h3>";
    
    // Show some doctors
    $doctors = $db->query("SELECT name, speciality FROM doctors LIMIT 3");
    while($doc = $doctors->fetch_assoc()) {
        echo "<p>👨‍⚕️ Dr. {$doc['name']} - {$doc['speciality']}</p>";
    }
    
    // Show some patients
    $patients = $db->query("SELECT name, age FROM patients LIMIT 3");
    while($pat = $patients->fetch_assoc()) {
        echo "<p>👤 {$pat['name']} - {$pat['age']} years</p>";
    }
    
    echo "<p><a href='index.php' style='background: #40E0D0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Go to Main System</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Please run the SQL commands manually in phpMyAdmin first.</p>";
}
?>