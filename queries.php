<?php
require_once 'config/database.php';

$query_results = [];
$current_query = '';

if ($_POST && isset($_POST['query'])) {
    $current_query = $_POST['query'];
    
    try {
        $result = $db->query($current_query);
        
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $query_results[] = $row;
            }
        } else {
            $query_results = [['message' => 'Query executed successfully. Affected rows: ' . $db->conn->affected_rows]];
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Real-world effective queries organized by syllabus topics
$predefined_queries = [
    "Aggregate Functions and Group By" => [
        "COUNT appointments per doctor" => "SELECT d.name, COUNT(a.id) as appointment_count FROM doctors d LEFT JOIN appointments a ON d.id = a.doctor_id GROUP BY d.id, d.name ORDER BY appointment_count DESC",
        "SUM revenue by speciality" => "SELECT d.speciality, SUM(p.amount) as total_revenue FROM doctors d JOIN appointments a ON d.id = a.doctor_id JOIN payments p ON a.id = p.appointment_id WHERE p.status = 'paid' GROUP BY d.speciality ORDER BY total_revenue DESC",
        "AVG consultation fee by speciality" => "SELECT speciality, AVG(consultation_fee) as avg_fee, MIN(consultation_fee) as min_fee, MAX(consultation_fee) as max_fee FROM doctors GROUP BY speciality ORDER BY avg_fee DESC",
        "COUNT patients by age group" => "SELECT CASE WHEN age < 20 THEN 'Under 20' WHEN age BETWEEN 20 AND 35 THEN '20-35' WHEN age BETWEEN 36 AND 50 THEN '36-50' WHEN age > 50 THEN 'Over 50' ELSE 'Unknown' END as age_group, COUNT(*) as patient_count FROM patients GROUP BY age_group ORDER BY patient_count DESC",
        "SUM platform revenue by chamber" => "SELECT c.chamber_name, d.name as doctor_name, SUM(c.chamber_fee) as platform_revenue FROM chambers c JOIN doctors d ON c.doctor_id = d.id JOIN appointments a ON c.id = a.chamber_id JOIN payments p ON a.id = p.appointment_id WHERE p.status = 'paid' GROUP BY c.id, c.chamber_name, d.name ORDER BY platform_revenue DESC"
    ],
    
    "Having Clause and Subqueries" => [
        "Doctors with more than 2 appointments" => "SELECT d.name, COUNT(a.id) as appointment_count FROM doctors d JOIN appointments a ON d.id = a.doctor_id GROUP BY d.id, d.name HAVING COUNT(a.id) > 2 ORDER BY appointment_count DESC",
        "Specialities with high revenue" => "SELECT d.speciality, SUM(p.amount) as total_revenue FROM doctors d JOIN appointments a ON d.id = a.doctor_id JOIN payments p ON a.id = p.appointment_id WHERE p.status = 'paid' GROUP BY d.speciality HAVING SUM(p.amount) > 2000 ORDER BY total_revenue DESC",
        "Patients with multiple appointments" => "SELECT p.name, COUNT(a.id) as appointment_count FROM patients p JOIN appointments a ON p.id = a.patient_id GROUP BY p.id, p.name HAVING COUNT(a.id) > 1 ORDER BY appointment_count DESC",
        "Doctors earning above average" => "SELECT name, consultation_fee FROM doctors WHERE consultation_fee > (SELECT AVG(consultation_fee) FROM doctors) ORDER BY consultation_fee DESC",
        "Subquery - Doctors without appointments" => "SELECT name, speciality FROM doctors WHERE id NOT IN (SELECT DISTINCT doctor_id FROM appointments WHERE status != 'cancelled')",
        "Correlated subquery - Patient last appointment" => "SELECT p.name, (SELECT MAX(appointment_date) FROM appointments a WHERE a.patient_id = p.id) as last_appointment FROM patients p ORDER BY last_appointment DESC"
    ],
    
    "Joining Multiple Tables" => [
        "INNER JOIN - Complete appointment details" => "SELECT a.id, p.name as patient_name, d.name as doctor_name, a.appointment_date, a.appointment_time, a.status, py.amount FROM appointments a INNER JOIN patients p ON a.patient_id = p.id INNER JOIN doctors d ON a.doctor_id = d.id INNER JOIN payments py ON a.id = py.appointment_id ORDER BY a.appointment_date DESC",
        "LEFT JOIN - All doctors with appointments" => "SELECT d.name, d.speciality, COUNT(a.id) as appointment_count FROM doctors d LEFT JOIN appointments a ON d.id = a.doctor_id GROUP BY d.id, d.name, d.speciality ORDER BY appointment_count DESC",
        "Multiple INNER JOINs - Chamber details" => "SELECT c.chamber_name, c.location, d.name as doctor_name, d.speciality, c.visiting_hours FROM chambers c INNER JOIN doctors d ON c.doctor_id = d.id ORDER BY d.name",
        "LEFT JOIN with aggregation" => "SELECT p.name as patient_name, COUNT(a.id) as total_appointments, SUM(py.amount) as total_paid FROM patients p LEFT JOIN appointments a ON p.id = a.patient_id LEFT JOIN payments py ON a.id = py.appointment_id AND py.status = 'paid' GROUP BY p.id, p.name HAVING total_appointments > 0 ORDER BY total_paid DESC",
        "Three table JOIN with conditions" => "SELECT p.name as patient_name, d.name as doctor_name, c.chamber_name, a.appointment_date, py.amount FROM appointments a JOIN patients p ON a.patient_id = p.id JOIN doctors d ON a.doctor_id = d.id LEFT JOIN chambers c ON a.chamber_id = c.id JOIN payments py ON a.id = py.appointment_id WHERE py.status = 'paid' ORDER BY py.amount DESC"
    ],
    
    "Conditions using Multiple Columns" => [
        "Multiple column WHERE conditions" => "SELECT name, age, blood_group FROM patients WHERE age > 40 AND blood_group IN ('A+', 'O+') ORDER BY age DESC",
        "Complex appointment filtering" => "SELECT p.name as patient_name, d.name as doctor_name, a.appointment_date, a.status FROM appointments a JOIN patients p ON a.patient_id = p.id JOIN doctors d ON a.doctor_id = d.id WHERE a.status = 'completed' AND a.appointment_date >= '2024-01-01' AND d.speciality = 'Cardiology' ORDER BY a.appointment_date DESC",
        "Multiple condition revenue analysis" => "SELECT d.speciality, d.experience, AVG(p.amount) as avg_revenue FROM doctors d JOIN appointments a ON d.id = a.doctor_id JOIN payments p ON a.id = p.appointment_id WHERE p.status = 'paid' AND d.experience > 5 AND p.amount > 500 GROUP BY d.speciality, d.experience HAVING AVG(p.amount) > 800 ORDER BY avg_revenue DESC",
        "Time and status based query" => "SELECT appointment_date, appointment_time, status, COUNT(*) as count FROM appointments WHERE appointment_time IN ('14:00', '15:00', '16:00') AND status = 'scheduled' GROUP BY appointment_date, appointment_time, status ORDER BY appointment_date, appointment_time",
        "Chamber and doctor combined conditions" => "SELECT c.chamber_name, d.name as doctor_name, d.speciality, c.chamber_fee, d.consultation_fee, (c.chamber_fee + d.consultation_fee) as total_fee FROM chambers c JOIN doctors d ON c.doctor_id = d.id WHERE c.chamber_fee > 150 AND d.consultation_fee > 800 ORDER BY total_fee DESC"
    ],
    
    "Natural Join Examples" => [
        "Natural JOIN equivalent" => "SELECT a.id, p.name, d.name as doctor_name, a.appointment_date FROM appointments a, patients p, doctors d WHERE a.patient_id = p.id AND a.doctor_id = d.id ORDER BY a.appointment_date DESC LIMIT 10",
        "Using USING clause" => "SELECT a.id, p.name as patient_name, a.appointment_date FROM appointments a JOIN patients p USING (id) WHERE a.id = p.id",
        "Multi-table natural join" => "SELECT p.name as patient_name, d.name as doctor_name, a.appointment_date, py.amount FROM appointments a, patients p, doctors d, payments py WHERE a.patient_id = p.id AND a.doctor_id = d.id AND a.id = py.appointment_id AND py.status = 'paid' ORDER BY py.amount DESC LIMIT 10"
    ]
];
?>

<?php include 'includes/header.php'; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow p-4">
        <h1 class="text-xl font-bold text-gray-800">Advanced SQL Queries</h1>
        <p class="text-gray-600 text-sm">Demonstrating Database Concepts from Lab Syllabus</p>
    </div>

    <!-- Query Input -->
    <div class="bg-white rounded-lg shadow p-4">
        <h2 class="font-semibold mb-3 text-gray-800 text-sm">Execute Custom Query</h2>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-3 py-2 rounded mb-4 text-sm">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-3">
            <textarea name="query" rows="4" class="border rounded px-3 py-2 w-full text-sm font-mono"
                placeholder="SELECT * FROM doctors..."><?= htmlspecialchars($current_query) ?></textarea>
            <button type="submit" class="bg-[#20B2AA] text-white px-4 py-2 rounded text-sm">Execute Query</button>
        </form>
    </div>

    <!-- Fixed Query Results Table -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex justify-between items-center mb-3">
            <h2 class="font-semibold text-gray-800 text-sm">Query Results</h2>
            <?php if (!empty($query_results)): ?>
                <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">
                    <?= count($query_results) ?> row(s) returned
                </span>
            <?php endif; ?>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <?php if (!empty($query_results)): ?>
                            <?php foreach (array_keys($query_results[0]) as $column): ?>
                                <th class="text-left py-2 px-4 font-medium text-gray-700"><?= $column ?></th>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">No results to display</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (!empty($query_results)): ?>
                        <?php foreach ($query_results as $row): ?>
                            <tr class="hover:bg-gray-50">
                                <?php foreach ($row as $value): ?>
                                    <td class="py-2 px-4"><?= htmlspecialchars($value) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td class="py-8 px-4 text-center text-gray-500 text-sm">
                                Run a query to see results here
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Syllabus Topics Queries -->
    <div class="space-y-6">
        <?php foreach ($predefined_queries as $category => $queries): ?>
            <div class="bg-white rounded-lg shadow p-4">
                <h2 class="font-semibold mb-4 text-gray-800 text-sm border-b pb-2"><?= $category ?></h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php foreach ($queries as $title => $query): ?>
                        <div class="border rounded-lg p-3 hover:bg-gray-50 transition-colors">
                            <div class="flex justify-between items-center">
                                <h3 class="font-medium text-gray-800 text-sm"><?= $title ?></h3>
                                <form method="POST">
                                    <input type="hidden" name="query" value="<?= htmlspecialchars($query) ?>">
                                    <button type="submit" class="bg-[#20B2AA] text-white px-3 py-1 rounded text-xs hover:bg-[#1a9c95] transition-colors">
                                        Run
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>