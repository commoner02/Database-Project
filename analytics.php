<?php
require_once 'config/database.php';

// Fetch analytics data
$total_revenue = $db->query("SELECT SUM(amount) as total FROM payments WHERE status = 'paid'")->fetch_assoc()['total'] ?? 0;
$platform_revenue = $db->query("SELECT SUM(c.chamber_fee) as total FROM payments p JOIN appointments a ON p.appointment_id = a.id JOIN chambers c ON a.chamber_id = c.id WHERE p.status = 'paid'")->fetch_assoc()['total'] ?? 0;
$total_appointments = $db->query("SELECT COUNT(*) as total FROM appointments")->fetch_assoc()['total'];
$completed_appointments = $db->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'completed'")->fetch_assoc()['total'];

// Monthly revenue
$monthly_revenue = $db->query("
    SELECT YEAR(payment_date) as year, MONTH(payment_date) as month, SUM(amount) as revenue
    FROM payments 
    WHERE status = 'paid' AND payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(payment_date), MONTH(payment_date)
    ORDER BY year DESC, month DESC
    LIMIT 6
");

// Doctor performance
$doctor_performance = $db->query("
    SELECT d.name, d.speciality, COUNT(a.id) as appointments, SUM(p.amount) as revenue
    FROM doctors d 
    LEFT JOIN appointments a ON d.id = a.doctor_id 
    LEFT JOIN payments p ON a.id = p.appointment_id AND p.status = 'paid'
    GROUP BY d.id, d.name, d.speciality
    ORDER BY revenue DESC
");

// Speciality-wise revenue
$speciality_revenue = $db->query("
    SELECT d.speciality, SUM(p.amount) as revenue
    FROM doctors d 
    JOIN appointments a ON d.id = a.doctor_id 
    JOIN payments p ON a.id = p.appointment_id 
    WHERE p.status = 'paid'
    GROUP BY d.speciality
    ORDER BY revenue DESC
");

// Chamber revenue breakdown
$chamber_revenue = $db->query("
    SELECT c.chamber_name, d.name as doctor_name, SUM(c.chamber_fee) as platform_revenue
    FROM chambers c 
    JOIN doctors d ON c.doctor_id = d.id
    JOIN appointments a ON c.id = a.chamber_id
    JOIN payments p ON a.id = p.appointment_id AND p.status = 'paid'
    GROUP BY c.id, c.chamber_name, d.name
    ORDER BY platform_revenue DESC
");
?>

<?php include 'includes/header.php'; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow p-4">
        <h1 class="text-xl font-bold text-gray-800">Analytics Dashboard</h1>
        <p class="text-gray-600 text-sm">Revenue analysis and platform performance metrics</p>
    </div>

    <!-- Key Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
            <div class="text-xl font-bold text-green-600">৳<?= number_format($total_revenue, 2) ?></div>
            <div class="text-xs text-gray-600">Total Revenue</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
            <div class="text-xl font-bold text-blue-600">৳<?= number_format($platform_revenue, 2) ?></div>
            <div class="text-xs text-gray-600">Platform Revenue</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
            <div class="text-xl font-bold text-purple-600"><?= $total_appointments ?></div>
            <div class="text-xs text-gray-600">Total Appointments</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
            <div class="text-xl font-bold text-orange-600"><?= $completed_appointments ?></div>
            <div class="text-xs text-gray-600">Completed Appointments</div>
        </div>
    </div>

    <!-- Revenue Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Doctor Performance -->
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-semibold mb-4 text-gray-800">Doctor Performance</h3>
            <div class="space-y-3 text-sm">
                <?php while ($doc = $doctor_performance->fetch_assoc()): ?>
                    <div class="flex justify-between items-center border-b pb-2">
                        <div>
                            <div class="font-medium"><?= $doc['name'] ?></div>
                            <div class="text-gray-500 text-xs"><?= $doc['speciality'] ?></div>
                        </div>
                        <div class="text-right">
                            <div class="font-medium">৳<?= number_format($doc['revenue'] ?? 0, 2) ?></div>
                            <div class="text-gray-500 text-xs"><?= $doc['appointments'] ?> apps</div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Speciality Revenue -->
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-semibold mb-4 text-gray-800">Revenue by Speciality</h3>
            <div class="space-y-3 text-sm">
                <?php while ($spec = $speciality_revenue->fetch_assoc()): ?>
                    <div class="flex justify-between items-center border-b pb-2">
                        <div class="font-medium"><?= $spec['speciality'] ?></div>
                        <div class="text-[#20B2AA] font-medium">৳<?= number_format($spec['revenue'], 2) ?></div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Platform Revenue Breakdown -->
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-semibold mb-4 text-gray-800">Platform Revenue by Chamber</h3>
            <div class="space-y-3 text-sm">
                <?php while ($chamber = $chamber_revenue->fetch_assoc()): ?>
                    <div class="flex justify-between items-center border-b pb-2">
                        <div>
                            <div class="font-medium"><?= $chamber['chamber_name'] ?></div>
                            <div class="text-gray-500 text-xs"><?= $chamber['doctor_name'] ?></div>
                        </div>
                        <div class="text-[#20B2AA] font-medium">৳<?= number_format($chamber['platform_revenue'], 2) ?></div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Monthly Revenue Trend -->
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-semibold mb-4 text-gray-800">Monthly Revenue Trend</h3>
            <div class="space-y-3 text-sm">
                <?php while ($month = $monthly_revenue->fetch_assoc()): ?>
                    <div class="flex justify-between items-center border-b pb-2">
                        <div class="font-medium">
                            <?= date('F Y', mktime(0, 0, 0, $month['month'], 1, $month['year'])) ?>
                        </div>
                        <div class="text-green-600 font-medium">৳<?= number_format($month['revenue'], 2) ?></div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Fee Structure Explanation -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3 text-gray-800">Fee Structure</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div class="border rounded-lg p-4">
                <h4 class="font-medium text-[#20B2AA] mb-2">Consultation Fee</h4>
                <p class="text-gray-600">Doctor's professional fee for medical consultation</p>
                <div class="mt-2 text-lg font-bold">৳500 - ৳1,500</div>
            </div>
            <div class="border rounded-lg p-4">
                <h4 class="font-medium text-[#20B2AA] mb-2">Chamber Fee</h4>
                <p class="text-gray-600">Platform fee for chamber facility and management</p>
                <div class="mt-2 text-lg font-bold">৳150 - ৳250</div>
            </div>
            <div class="border rounded-lg p-4">
                <h4 class="font-medium text-[#20B2AA] mb-2">Total Fee</h4>
                <p class="text-gray-600">Sum of consultation fee and chamber fee</p>
                <div class="mt-2 text-lg font-bold">Consultation + Chamber</div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>