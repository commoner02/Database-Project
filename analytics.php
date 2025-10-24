<?php
require_once 'config/database.php';

// Check database connection
if (!$db->conn) {
    die("Database connection failed: " . $db->conn->connect_error);
}

// Initialize variables to prevent undefined variable errors
$revenue_by_speciality = $todays_appointments = $doctor_performance = $doctor_monthly_revenue = 
$monthly_revenue = $high_performing_doctors = $appointment_details = $fee_changes = null;

// Real-world analytics using database functions and views
try {
    $revenue_by_speciality = $db->query("
        SELECT d.speciality, 
               COUNT(a.id) as total_appointments,
               SUM(p.amount) as total_revenue
        FROM doctors d 
        LEFT JOIN appointments a ON d.id = a.doctor_id 
        LEFT JOIN payments p ON a.id = p.appointment_id 
        WHERE p.status = 'paid'
        GROUP BY d.speciality 
        ORDER BY total_revenue DESC
    ");

    // Check if views exist before querying them
    $view_check = $db->query("SHOW TABLES LIKE 'todays_appointments'");
    if ($view_check && $view_check->num_rows > 0) {
        $todays_appointments = $db->query("SELECT * FROM todays_appointments");
    } else {
        // Fallback query if view doesn't exist
        $todays_appointments = $db->query("
            SELECT a.id, a.appointment_time,
                   p.name as patient_name, p.phone as patient_phone,
                   d.name as doctor_name, d.speciality,
                   c.chamber_name, c.location
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN doctors d ON a.doctor_id = d.id
            LEFT JOIN chambers c ON a.chamber_id = c.id
            WHERE a.appointment_date = CURDATE() 
            AND a.status = 'scheduled'
            ORDER BY a.appointment_time
        ");
    }

    // Check if doctor_performance view exists
    $view_check2 = $db->query("SHOW TABLES LIKE 'doctor_performance'");
    if ($view_check2 && $view_check2->num_rows > 0) {
        $doctor_performance = $db->query("SELECT * FROM doctor_performance ORDER BY total_revenue DESC");
    } else {
        // Fallback query
        $doctor_performance = $db->query("
            SELECT 
                d.id, d.name, d.speciality, d.experience,
                COUNT(a.id) as total_appointments,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
                COALESCE(SUM(p.amount), 0) as total_revenue,
                COALESCE(AVG(p.amount), 0) as avg_revenue_per_appointment
            FROM doctors d
            LEFT JOIN appointments a ON d.id = a.doctor_id
            LEFT JOIN payments p ON a.id = p.appointment_id AND p.status = 'paid'
            GROUP BY d.id
            ORDER BY total_revenue DESC
        ");
    }

    // Check if function exists
    $func_check = $db->query("
        SELECT COUNT(*) as func_exists 
        FROM information_schema.ROUTINES 
        WHERE ROUTINE_TYPE = 'FUNCTION' 
        AND ROUTINE_NAME = 'calculate_doctor_revenue'
        AND ROUTINE_SCHEMA = 'hospital_db'
    ");
    
    $func_exists = $func_check ? $func_check->fetch_assoc()['func_exists'] : 0;
    
    if ($func_exists) {
        $doctor_monthly_revenue = $db->query("
            SELECT name, speciality, 
                   calculate_doctor_revenue(id, MONTH(CURDATE()), YEAR(CURDATE())) as current_month_revenue,
                   calculate_doctor_revenue(id, MONTH(CURDATE())-1, YEAR(CURDATE())) as last_month_revenue
            FROM doctors
            ORDER BY current_month_revenue DESC
        ");
    } else {
        // Fallback without function
        $doctor_monthly_revenue = $db->query("
            SELECT d.name, d.speciality,
                   COALESCE(SUM(CASE WHEN MONTH(a.appointment_date) = MONTH(CURDATE()) AND YEAR(a.appointment_date) = YEAR(CURDATE()) THEN p.amount ELSE 0 END), 0) as current_month_revenue,
                   COALESCE(SUM(CASE WHEN MONTH(a.appointment_date) = MONTH(CURDATE())-1 AND YEAR(a.appointment_date) = YEAR(CURDATE()) THEN p.amount ELSE 0 END), 0) as last_month_revenue
            FROM doctors d
            LEFT JOIN appointments a ON d.id = a.doctor_id
            LEFT JOIN payments p ON a.id = p.appointment_id AND p.status = 'paid'
            GROUP BY d.id, d.name, d.speciality
            ORDER BY current_month_revenue DESC
        ");
    }

    // Monthly revenue
    $monthly_revenue = $db->query("
        SELECT DATE_FORMAT(a.appointment_date, '%Y-%m') as month,
               d.speciality,
               COUNT(a.id) as appointment_count,
               SUM(p.amount) as total_revenue
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN payments p ON a.id = p.appointment_id
        WHERE p.status = 'paid'
        GROUP BY DATE_FORMAT(a.appointment_date, '%Y-%m'), d.speciality
        ORDER BY month DESC, total_revenue DESC
        LIMIT 6
    ");

    // HAVING clause example - High performing doctors
    $high_performing_doctors = $db->query("
        SELECT d.name, d.speciality,
               COUNT(a.id) as appointment_count,
               SUM(p.amount) as total_revenue
        FROM doctors d 
        JOIN appointments a ON d.id = a.doctor_id 
        JOIN payments p ON a.id = p.appointment_id 
        WHERE p.status = 'paid'
        GROUP BY d.id, d.name, d.speciality 
        HAVING COUNT(a.id) > 0 AND SUM(p.amount) > 0
        ORDER BY total_revenue DESC
        LIMIT 10
    ");

    // Multiple table joins - Complete appointment details
    $appointment_details = $db->query("
        SELECT a.appointment_date, a.appointment_time, 
               p.name as patient_name, p.blood_group, p.age,
               d.name as doctor_name, d.speciality,
               c.chamber_name,
               pay.amount, pay.status as payment_status,
               m.diagnosis
        FROM appointments a
        INNER JOIN patients p ON a.patient_id = p.id
        INNER JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN chambers c ON a.chamber_id = c.id
        LEFT JOIN payments pay ON a.id = pay.appointment_id
        LEFT JOIN medical_records m ON a.id = m.appointment_id
        ORDER BY a.appointment_date DESC 
        LIMIT 10
    ");

    // Fee change audit
    $fee_changes = $db->query("
        SELECT f.*, d.name as doctor_name 
        FROM fee_audit_log f 
        JOIN doctors d ON f.doctor_id = d.id 
        ORDER BY f.changed_at DESC 
        LIMIT 10
    ");

} catch (Exception $e) {
    $error = "Database query error: " . $e->getMessage();
}

// Stats with aggregates (with error handling)
try {
    $stats = [
        'total_revenue' => $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status='paid'")->fetch_assoc()['total'] ?? 0,
        'total_patients' => $db->query("SELECT COUNT(*) as total FROM patients")->fetch_assoc()['total'] ?? 0,
        'total_doctors' => $db->query("SELECT COUNT(*) as total FROM doctors")->fetch_assoc()['total'] ?? 0,
        'today_appointments' => $db->query("SELECT COUNT(*) as total FROM appointments WHERE appointment_date = CURDATE() AND status = 'scheduled'")->fetch_assoc()['total'] ?? 0,
        'pending_payments' => $db->query("SELECT COUNT(*) as total FROM payments WHERE status = 'pending'")->fetch_assoc()['total'] ?? 0,
        'total_fee_changes' => $db->query("SELECT COUNT(*) as total FROM fee_audit_log")->fetch_assoc()['total'] ?? 0,
    ];
} catch (Exception $e) {
    $stats = [
        'total_revenue' => 0,
        'total_patients' => 0,
        'total_doctors' => 0,
        'today_appointments' => 0,
        'pending_payments' => 0,
        'total_fee_changes' => 0,
    ];
    $error = "Stats calculation error: " . $e->getMessage();
}
?>

<?php include 'includes/header.php'; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow p-4">
        <h1 class="text-xl font-bold text-gray-800">Analytics & Advanced Queries</h1>
        <p class="text-gray-600 text-sm">Using Database Functions, Views, Triggers & Advanced SQL Features</p>
    </div>

    <!-- Error Display -->
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-sm">
            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Aggregate Stats -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white rounded-lg shadow p-4 text-center border-l-4 border-[#20B2AA]">
            <div class="text-xl font-bold text-[#20B2AA]">৳<?= number_format($stats['total_revenue'], 2) ?></div>
            <div class="text-xs text-gray-600">Total Revenue</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center border-l-4 border-[#20B2AA]">
            <div class="text-xl font-bold text-[#20B2AA]"><?= $stats['total_patients'] ?></div>
            <div class="text-xs text-gray-600">Total Patients</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center border-l-4 border-[#20B2AA]">
            <div class="text-xl font-bold text-[#20B2AA]"><?= $stats['total_doctors'] ?></div>
            <div class="text-xs text-gray-600">Total Doctors</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center border-l-4 border-[#20B2AA]">
            <div class="text-xl font-bold text-[#20B2AA]"><?= $stats['today_appointments'] ?></div>
            <div class="text-xs text-gray-600">Today's Appointments</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center border-l-4 border-[#20B2AA]">
            <div class="text-xl font-bold text-[#20B2AA]"><?= $stats['pending_payments'] ?></div>
            <div class="text-xs text-gray-600">Pending Payments</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center border-l-4 border-[#20B2AA]">
            <div class="text-xl font-bold text-[#20B2AA]"><?= $stats['total_fee_changes'] ?></div>
            <div class="text-xs text-gray-600">Fee Changes Logged</div>
        </div>
    </div>

    <!-- Doctor Monthly Revenue -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b px-4 py-3">
            <h2 class="font-semibold text-gray-800 text-sm">Doctor Monthly Revenue</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Doctor</th>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Speciality</th>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Current Month</th>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Last Month</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($doctor_monthly_revenue && $doctor_monthly_revenue->num_rows > 0): ?>
                        <?php while($row = $doctor_monthly_revenue->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4"> <?= htmlspecialchars($row['name']) ?></td>
                            <td class="py-2 px-4"><?= htmlspecialchars($row['speciality']) ?></td>
                            <td class="py-2 px-4 font-semibold text-[#20B2AA]">৳<?= number_format($row['current_month_revenue'], 2) ?></td>
                            <td class="py-2 px-4">৳<?= number_format($row['last_month_revenue'], 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="py-4 px-4 text-center text-gray-500">No revenue data available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Fee Change Audit -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b px-4 py-3">
            <h2 class="font-semibold text-gray-800 text-sm">Fee Change History</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Doctor</th>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Old Fee</th>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">New Fee</th>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Change Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($fee_changes && $fee_changes->num_rows > 0): ?>
                        <?php while($row = $fee_changes->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4"> <?= htmlspecialchars($row['doctor_name']) ?></td>
                            <td class="py-2 px-4">৳<?= number_format($row['old_fee'], 2) ?></td>
                            <td class="py-2 px-4 font-semibold <?= $row['new_fee'] > $row['old_fee'] ? 'text-green-600' : 'text-red-600' ?>">
                                ৳<?= number_format($row['new_fee'], 2) ?>
                            </td>
                            <td class="py-2 px-4 text-xs"><?= htmlspecialchars($row['changed_at']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="py-4 px-4 text-center text-gray-500">No fee changes recorded</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Doctor Performance -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b px-4 py-3">
            <h2 class="font-semibold text-gray-800 text-sm">Doctor Performance</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Doctor</th>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Speciality</th>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Total Appointments</th>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Completed</th>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Total Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($doctor_performance && $doctor_performance->num_rows > 0): ?>
                        <?php while($row = $doctor_performance->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4"> <?= htmlspecialchars($row['name']) ?></td>
                            <td class="py-2 px-4"><?= htmlspecialchars($row['speciality']) ?></td>
                            <td class="py-2 px-4"><?= $row['total_appointments'] ?></td>
                            <td class="py-2 px-4"><?= $row['completed_appointments'] ?? 'N/A' ?></td>
                            <td class="py-2 px-4 font-semibold text-[#20B2AA]">৳<?= number_format($row['total_revenue'], 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="py-4 px-4 text-center text-gray-500">No performance data available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Today's Appointments -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b px-4 py-3">
            <h2 class="font-semibold text-gray-800 text-sm">Today's Appointments</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Time</th>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Patient</th>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Doctor</th>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Speciality</th>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Chamber</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($todays_appointments && $todays_appointments->num_rows > 0): ?>
                        <?php while($row = $todays_appointments->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4"><?= htmlspecialchars($row['appointment_time']) ?></td>
                            <td class="py-2 px-4">
                                <div><?= htmlspecialchars($row['patient_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($row['patient_phone'] ?? '') ?></div>
                            </td>
                            <td class="py-2 px-4"> <?= htmlspecialchars($row['doctor_name']) ?></td>
                            <td class="py-2 px-4"><?= htmlspecialchars($row['speciality']) ?></td>
                            <td class="py-2 px-4 text-xs"><?= htmlspecialchars($row['chamber_name'] ?? 'N/A') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="py-4 px-4 text-center text-gray-500">No appointments scheduled for today</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Monthly Revenue Analytics -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b px-4 py-3">
            <h2 class="font-semibold text-gray-800 text-sm">Monthly Revenue Analytics</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Month</th>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Speciality</th>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Appointments</th>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($monthly_revenue && $monthly_revenue->num_rows > 0): ?>
                        <?php while($row = $monthly_revenue->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4"><?= htmlspecialchars($row['month']) ?></td>
                            <td class="py-2 px-4"><?= htmlspecialchars($row['speciality']) ?></td>
                            <td class="py-2 px-4"><?= $row['appointment_count'] ?></td>
                            <td class="py-2 px-4 font-semibold text-[#20B2AA]">৳<?= number_format($row['total_revenue'], 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="py-4 px-4 text-center text-gray-500">No revenue data available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>