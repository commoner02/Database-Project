<?php
require_once 'config/database.php';

$query_results = [];
$current_query = '';
//$execution_time = 0;

if ($_POST && isset($_POST['query'])) {
    $current_query = $_POST['query'];
    $start_time = microtime(true);
    
    try {
        $result = $db->query($current_query);
        //$end_time = microtime(true);
        //$execution_time = round(($end_time - $start_time) * 1000, 2);
        
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

// Real-world effective queries using the essential functions and views
$predefined_queries = [
    "Database Views" => [
        "Doctor Performance" => "SELECT * FROM doctor_performance ORDER BY total_revenue DESC",
        "Today's Appointments" => "SELECT * FROM todays_appointments",
        "Patient Medical History" => "SELECT * FROM patient_medical_history LIMIT 10",
        "Pending Payments" => "SELECT * FROM pending_payments",
        "Monthly Revenue Analytics" => "SELECT * FROM monthly_revenue_analytics LIMIT 15"
    ],
    
    "Basic Reports" => [
        "All Doctors" => "SELECT * FROM doctors ORDER BY name",
        "All Patients" => "SELECT * FROM patients ORDER BY name",
        "All Appointments" => "SELECT a.*, p.name as patient_name, d.name as doctor_name FROM appointments a JOIN patients p ON a.patient_id = p.id JOIN doctors d ON a.doctor_id = d.id ORDER BY a.appointment_date DESC",
        "Recent Payments" => "SELECT p.*, pt.name as patient_name, d.name as doctor_name FROM payments p JOIN appointments a ON p.appointment_id = a.id JOIN patients pt ON a.patient_id = pt.id JOIN doctors d ON a.doctor_id = d.id ORDER BY p.payment_date DESC LIMIT 10"
    ],
    
    "Business Analytics" => [
        "Revenue by Speciality" => "SELECT d.speciality, COUNT(a.id) as appointments, SUM(p.amount) as revenue FROM doctors d JOIN appointments a ON d.id = a.doctor_id JOIN payments p ON a.id = p.appointment_id WHERE p.status='paid' GROUP BY speciality ORDER BY revenue DESC",
        "Top Earning Doctors" => "SELECT d.name, d.speciality, SUM(p.amount) as total_earnings FROM doctors d JOIN appointments a ON d.id = a.doctor_id JOIN payments p ON a.id = p.appointment_id WHERE p.status='paid' GROUP BY d.id, d.name, d.speciality ORDER BY total_earnings DESC LIMIT 10",
        "Monthly Revenue" => "SELECT DATE_FORMAT(appointment_date, '%Y-%m') as month, COUNT(*) as appointments, SUM(amount) as revenue FROM appointments a JOIN payments p ON a.id = p.appointment_id WHERE p.status='paid' GROUP BY DATE_FORMAT(appointment_date, '%Y-%m') ORDER BY month DESC"
    ],
    
    "Using Database Functions" => [
        "Doctor Revenue This Month" => "SELECT name, speciality, calculate_doctor_revenue(id, MONTH(CURDATE()), YEAR(CURDATE())) as monthly_revenue FROM doctors",
        "Doctor Revenue Last Month" => "SELECT name, speciality, calculate_doctor_revenue(id, MONTH(CURDATE())-1, YEAR(CURDATE())) as monthly_revenue FROM doctors",
        "Fee Change History" => "SELECT f.*, d.name as doctor_name FROM fee_audit_log f JOIN doctors d ON f.doctor_id = d.id ORDER BY f.changed_at DESC LIMIT 10"
    ],
    
    "Operational Queries" => [
        "Upcoming Appointments" => "SELECT a.*, p.name as patient_name, d.name as doctor_name FROM appointments a JOIN patients p ON a.patient_id = p.id JOIN doctors d ON a.doctor_id = d.id WHERE a.appointment_date >= CURDATE() AND a.status = 'scheduled' ORDER BY a.appointment_date, a.appointment_time",
        "Doctor Chamber Details" => "SELECT d.name as doctor, c.chamber_name, c.location, c.visiting_hours FROM doctors d JOIN chambers c ON d.id = c.doctor_id ORDER BY d.name",
        "Appointment Status Summary" => "SELECT status, COUNT(*) as count FROM appointments GROUP BY status ORDER BY count DESC"
    ]
];
?>

<?php include 'includes/header.php'; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow p-4">
        <h1 class="text-xl font-bold text-gray-800">SQL Query Interface</h1>
        <p class="text-gray-600 text-sm">Execute SQL queries and explore database objects</p>
    </div>

    <!-- Query Input -->
    <div class="bg-white rounded-lg shadow p-4">
        <h2 class="font-semibold mb-3 text-gray-800 text-sm">Execute Query</h2>

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

    <!-- Permanent Results Table -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex justify-between items-center mb-3">
            <h2 class="font-semibold text-gray-800 text-sm">Query Results</h2>
            <?php if (!empty($query_results)): ?>
                <div class="flex gap-2 text-xs">
                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded">
                        <?= count($query_results) ?> row(s)
                    </span>
                    <!-- <?php if ($execution_time > 0): ?>
                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">
                            <?= $execution_time ?> ms
                        </span>
                    <?php endif; ?> -->
                </div>
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
                <tbody>
                    <?php if (!empty($query_results)): ?>
                        <?php foreach ($query_results as $row): ?>
                            <tr class="border-t hover:bg-gray-50">
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

    <!-- Predefined Queries -->
    <div class="bg-white rounded-lg shadow p-4">
        <h2 class="font-semibold mb-4 text-gray-800 text-sm">Predefined Queries</h2>
        
        <div class="space-y-4">
            <?php foreach ($predefined_queries as $category => $queries): ?>
                <div>
                    <h3 class="font-semibold text-[#20B2AA] mb-2 text-sm"><?= $category ?></h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <?php foreach ($queries as $title => $query): ?>
                            <form method="POST" class="flex justify-between items-center p-2 border rounded hover:bg-gray-50">
                                <span class="text-sm"><?= $title ?></span>
                                <input type="hidden" name="query" value="<?= htmlspecialchars($query) ?>">
                                <button type="submit" class="bg-[#20B2AA] text-white px-2 py-1 rounded text-xs">Run</button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>