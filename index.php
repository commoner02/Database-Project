<?php
require_once 'config/database.php';

// Handle all form actions
if ($_POST) {
    try {
        // Add operations
        if (isset($_POST['add_doctor'])) {
            $stmt = $db->conn->prepare("INSERT INTO doctors (name, email, speciality, phone, experience, consultation_fee) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssid", $_POST['name'], $_POST['email'], $_POST['speciality'], $_POST['phone'], $_POST['experience'], $_POST['fee']);
            $stmt->execute();
            $success = "Doctor added successfully!";
        }

        if (isset($_POST['add_patient'])) {
            $stmt = $db->conn->prepare("INSERT INTO patients (name, email, phone, age, blood_group, address) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssiss", $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['age'], $_POST['blood_group'], $_POST['address']);
            $stmt->execute();
            $success = "Patient added successfully!";
        }

        if (isset($_POST['add_appointment'])) {
            $stmt = $db->conn->prepare("INSERT INTO appointments (patient_id, doctor_id, chamber_id, appointment_date, appointment_time, reason) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisss", $_POST['patient_id'], $_POST['doctor_id'], $_POST['chamber_id'], $_POST['date'], $_POST['time'], $_POST['reason']);
            $stmt->execute();
            
            // Auto-create payment record
            $appointment_id = $db->conn->insert_id;
            $doctor_fee = $db->query("SELECT consultation_fee FROM doctors WHERE id = " . $_POST['doctor_id'])->fetch_assoc()['consultation_fee'];
            $stmt = $db->conn->prepare("INSERT INTO payments (appointment_id, amount, payment_method, status) VALUES (?, ?, 'Cash', 'pending')");
            $stmt->bind_param("id", $appointment_id, $doctor_fee);
            $stmt->execute();
            
            $success = "Appointment booked successfully!";
        }

        if (isset($_POST['add_chamber'])) {
            $stmt = $db->conn->prepare("INSERT INTO chambers (doctor_id, chamber_name, location, chamber_fee, phone, visiting_hours) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issdss", $_POST['doctor_id'], $_POST['name'], $_POST['location'], $_POST['fee'], $_POST['phone'], $_POST['hours']);
            $stmt->execute();
            $success = "Chamber added successfully!";
        }

        // Update Operations
        if (isset($_POST['update_appointment_status'])) {
            $stmt = $db->conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $_POST['status'], $_POST['id']);
            $stmt->execute();
            
            // Auto-update payment when appointment completed
            if ($_POST['status'] == 'completed') {
                $db->query("UPDATE payments SET status = 'paid', payment_date = NOW() WHERE appointment_id = " . $_POST['id'] . " AND status = 'pending'");
            }
            
            $success = "Appointment status updated!";
        }

        if (isset($_POST['update_payment_status'])) {
            $stmt = $db->conn->prepare("UPDATE payments SET status = ?, payment_date = NOW() WHERE id = ?");
            $stmt->bind_param("si", $_POST['status'], $_POST['id']);
            $stmt->execute();
            $success = "Payment status updated!";
        }

        // Edit Operations
        if (isset($_POST['edit_doctor'])) {
            $old_fee = $db->query("SELECT consultation_fee FROM doctors WHERE id = " . $_POST['id'])->fetch_assoc()['consultation_fee'];
            
            $stmt = $db->conn->prepare("UPDATE doctors SET name=?, email=?, speciality=?, phone=?, experience=?, consultation_fee=? WHERE id=?");
            $stmt->bind_param("ssssidi", $_POST['name'], $_POST['email'], $_POST['speciality'], $_POST['phone'], $_POST['experience'], $_POST['fee'], $_POST['id']);
            $stmt->execute();
            
            // Log fee changes
            if ($old_fee != $_POST['fee']) {
                $stmt = $db->conn->prepare("INSERT INTO fee_audit_log (doctor_id, old_fee, new_fee) VALUES (?, ?, ?)");
                $stmt->bind_param("idd", $_POST['id'], $old_fee, $_POST['fee']);
                $stmt->execute();
            }
            
            $success = "Doctor updated successfully!";
        }

        if (isset($_POST['edit_patient'])) {
            $stmt = $db->conn->prepare("UPDATE patients SET name=?, email=?, phone=?, age=?, blood_group=?, address=? WHERE id=?");
            $stmt->bind_param("sssissi", $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['age'], $_POST['blood_group'], $_POST['address'], $_POST['id']);
            $stmt->execute();
            $success = "Patient updated successfully!";
        }

        if (isset($_POST['edit_chamber'])) {
            $stmt = $db->conn->prepare("UPDATE chambers SET doctor_id=?, chamber_name=?, location=?, chamber_fee=?, phone=?, visiting_hours=? WHERE id=?");
            $stmt->bind_param("issdssi", $_POST['doctor_id'], $_POST['name'], $_POST['location'], $_POST['fee'], $_POST['phone'], $_POST['hours'], $_POST['id']);
            $stmt->execute();
            $success = "Chamber updated successfully!";
        }

        // Delete Operations
        if (isset($_POST['delete_record'])) {
            $table = $_POST['table'];
            $id = $_POST['id'];
            
            // Check if record can be deleted (no related appointments)
            if ($table == 'doctors') {
                $has_appointments = $db->query("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = $id")->fetch_assoc()['count'];
                if ($has_appointments > 0) {
                    $error = "Cannot delete doctor with existing appointments!";
                } else {
                    $db->query("DELETE FROM $table WHERE id = $id");
                    $success = "Record deleted successfully!";
                }
            } elseif ($table == 'patients') {
                $has_appointments = $db->query("SELECT COUNT(*) as count FROM appointments WHERE patient_id = $id")->fetch_assoc()['count'];
                if ($has_appointments > 0) {
                    $error = "Cannot delete patient with existing appointments!";
                } else {
                    $db->query("DELETE FROM $table WHERE id = $id");
                    $success = "Record deleted successfully!";
                }
            } else {
                $db->query("DELETE FROM $table WHERE id = $id");
                $success = "Record deleted successfully!";
            }
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch data for all tables
$stats = [
    'doctors' => $db->query("SELECT COUNT(*) as c FROM doctors")->fetch_assoc()['c'],
    'patients' => $db->query("SELECT COUNT(*) as c FROM patients")->fetch_assoc()['c'],
    'appointments' => $db->query("SELECT COUNT(*) as c FROM appointments")->fetch_assoc()['c'],
    'chambers' => $db->query("SELECT COUNT(*) as c FROM chambers")->fetch_assoc()['c'],
    'payments' => $db->query("SELECT COUNT(*) as c FROM payments")->fetch_assoc()['c'],
    'medical_records' => $db->query("SELECT COUNT(*) as c FROM medical_records")->fetch_assoc()['c'],
];

$doctors = $db->query("SELECT * FROM doctors ORDER BY name");
$patients = $db->query("SELECT * FROM patients ORDER BY name");
$chambers = $db->query("SELECT c.*, d.name as doctor_name FROM chambers c JOIN doctors d ON c.doctor_id = d.id ORDER BY c.chamber_name");
$appointments = $db->query("SELECT a.*, p.name as patient_name, d.name as doctor_name, c.chamber_name FROM appointments a JOIN patients p ON a.patient_id = p.id JOIN doctors d ON a.doctor_id = d.id LEFT JOIN chambers c ON a.chamber_id = c.id ORDER BY a.appointment_date DESC");
$payments = $db->query("SELECT p.*, pt.name as patient_name, d.name as doctor_name FROM payments p JOIN appointments a ON p.appointment_id = a.id JOIN patients pt ON a.patient_id = pt.id JOIN doctors d ON a.doctor_id = d.id ORDER BY p.payment_date DESC");
$medical_records = $db->query("SELECT m.*, p.name as patient_name, d.name as doctor_name FROM medical_records m JOIN appointments a ON m.appointment_id = a.id JOIN patients p ON a.patient_id = p.id JOIN doctors d ON a.doctor_id = d.id ORDER BY m.record_date DESC");

// Dropdown data
$doctors_list = $db->query("SELECT id, name, speciality FROM doctors");
$patients_list = $db->query("SELECT id, name FROM patients");
$chambers_list = $db->query("SELECT id, chamber_name FROM chambers");
?>

<?php include 'includes/header.php'; ?>

<!-- Notifications -->
<?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 text-sm">
        <?= $success ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-sm">
        <?= $error ?>
    </div>
<?php endif; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow p-4">
        <h1 class="text-xl font-bold text-gray-800">Hospital Management Dashboard</h1>
        <p class="text-gray-600 text-sm">Manage all hospital data with CRUD operations</p>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white rounded-lg shadow p-4 text-center border-l-4 border-[#20B2AA]">
            <div class="text-xl font-bold text-[#20B2AA]"><?= $stats['doctors'] ?></div>
            <div class="text-xs text-gray-600">Doctors</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center border-l-4 border-[#20B2AA]">
            <div class="text-xl font-bold text-[#20B2AA]"><?= $stats['patients'] ?></div>
            <div class="text-xs text-gray-600">Patients</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center border-l-4 border-[#20B2AA]">
            <div class="text-xl font-bold text-[#20B2AA]"><?= $stats['appointments'] ?></div>
            <div class="text-xs text-gray-600">Appointments</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center border-l-4 border-[#20B2AA]">
            <div class="text-xl font-bold text-[#20B2AA]"><?= $stats['chambers'] ?></div>
            <div class="text-xs text-gray-600">Chambers</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center border-l-4 border-[#20B2AA]">
            <div class="text-xl font-bold text-[#20B2AA]"><?= $stats['payments'] ?></div>
            <div class="text-xs text-gray-600">Payments</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center border-l-4 border-[#20B2AA]">
            <div class="text-xl font-bold text-[#20B2AA]"><?= $stats['medical_records'] ?></div>
            <div class="text-xs text-gray-600">Medical Records</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow p-4">
        <h2 class="font-semibold mb-3 text-gray-800 text-sm">Quick Actions</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            <button onclick="showForm('doctorForm')" class="bg-[#20B2AA] text-white py-2 px-3 rounded text-xs">Add Doctor</button>
            <button onclick="showForm('patientForm')" class="bg-[#20B2AA] text-white py-2 px-3 rounded text-xs">Add Patient</button>
            <button onclick="showForm('appointmentForm')" class="bg-[#20B2AA] text-white py-2 px-3 rounded text-xs">Book Appointment</button>
            <button onclick="showForm('chamberForm')" class="bg-[#20B2AA] text-white py-2 px-3 rounded text-xs">Add Chamber</button>
        </div>
    </div>

    <!-- Add Forms -->
    <div id="formsSection" class="space-y-4">
        <!-- Doctor Form -->
        <div id="doctorForm" class="bg-white rounded-lg shadow p-4 hidden">
            <h3 class="font-semibold mb-3 text-gray-800 text-sm">Add New Doctor</h3>
            <form method="POST" class="space-y-3 text-sm">
                <input type="text" name="name" placeholder="Full Name" required class="border rounded px-3 py-2 w-full text-sm">
                <input type="email" name="email" placeholder="Email" required class="border rounded px-3 py-2 w-full text-sm">
                <input type="text" name="speciality" placeholder="Speciality" required class="border rounded px-3 py-2 w-full text-sm">
                <input type="text" name="phone" placeholder="Phone" required class="border rounded px-3 py-2 w-full text-sm">
                <input type="number" name="experience" placeholder="Experience (years)" required class="border rounded px-3 py-2 w-full text-sm">
                <input type="number" step="0.01" name="fee" placeholder="Consultation Fee" required class="border rounded px-3 py-2 w-full text-sm">
                <div class="flex gap-2">
                    <button type="submit" name="add_doctor" class="bg-[#20B2AA] text-white px-4 py-2 rounded text-sm flex-1">Add Doctor</button>
                    <button type="button" onclick="hideForms()" class="bg-gray-500 text-white px-4 py-2 rounded text-sm flex-1">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Patient Form -->
        <div id="patientForm" class="bg-white rounded-lg shadow p-4 hidden">
            <h3 class="font-semibold mb-3 text-gray-800 text-sm">Add New Patient</h3>
            <form method="POST" class="space-y-3 text-sm">
                <input type="text" name="name" placeholder="Full Name" required class="border rounded px-3 py-2 w-full text-sm">
                <input type="email" name="email" placeholder="Email" required class="border rounded px-3 py-2 w-full text-sm">
                <input type="text" name="phone" placeholder="Phone" required class="border rounded px-3 py-2 w-full text-sm">
                <input type="number" name="age" placeholder="Age" required class="border rounded px-3 py-2 w-full text-sm">
                <select name="blood_group" class="border rounded px-3 py-2 w-full text-sm">
                    <option value="">Blood Group</option>
                    <option value="A+">A+</option><option value="A-">A-</option>
                    <option value="B+">B+</option><option value="B-">B-</option>
                    <option value="O+">O+</option><option value="O-">O-</option>
                    <option value="AB+">AB+</option><option value="AB-">AB-</option>
                </select>
                <textarea name="address" placeholder="Address" class="border rounded px-3 py-2 w-full text-sm" rows="2"></textarea>
                <div class="flex gap-2">
                    <button type="submit" name="add_patient" class="bg-[#20B2AA] text-white px-4 py-2 rounded text-sm flex-1">Add Patient</button>
                    <button type="button" onclick="hideForms()" class="bg-gray-500 text-white px-4 py-2 rounded text-sm flex-1">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Appointment Form -->
        <div id="appointmentForm" class="bg-white rounded-lg shadow p-4 hidden">
            <h3 class="font-semibold mb-3 text-gray-800 text-sm">Book Appointment</h3>
            <form method="POST" class="space-y-3 text-sm">
                <select name="patient_id" required class="border rounded px-3 py-2 w-full text-sm">
                    <option value="">Select Patient</option>
                    <?php while ($p = $patients_list->fetch_assoc()): ?>
                        <option value="<?= $p['id'] ?>"><?= $p['name'] ?></option>
                    <?php endwhile; ?>
                </select>
                <select name="doctor_id" required class="border rounded px-3 py-2 w-full text-sm">
                    <option value="">Select Doctor</option>
                    <?php $doctors_list->data_seek(0); while ($d = $doctors_list->fetch_assoc()): ?>
                        <option value="<?= $d['id'] ?>"><?= $d['name'] ?> - <?= $d['speciality'] ?></option>
                    <?php endwhile; ?>
                </select>
                <select name="chamber_id" class="border rounded px-3 py-2 w-full text-sm">
                    <option value="">Select Chamber (Optional)</option>
                    <?php $chambers_list->data_seek(0); while ($c = $chambers_list->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>"><?= $c['chamber_name'] ?></option>
                    <?php endwhile; ?>
                </select>
                <input type="date" name="date" required class="border rounded px-3 py-2 w-full text-sm">
                <select name="time" required class="border rounded px-3 py-2 w-full text-sm">
                    <option value="">Select Time</option>
                    <option value="09:00">09:00 AM</option><option value="10:00">10:00 AM</option>
                    <option value="11:00">11:00 AM</option><option value="14:00">02:00 PM</option>
                    <option value="15:00">03:00 PM</option><option value="16:00">04:00 PM</option>
                    <option value="17:00">05:00 PM</option>
                </select>
                <textarea name="reason" placeholder="Reason" class="border rounded px-3 py-2 w-full text-sm" rows="2"></textarea>
                <div class="flex gap-2">
                    <button type="submit" name="add_appointment" class="bg-[#20B2AA] text-white px-4 py-2 rounded text-sm flex-1">Book Appointment</button>
                    <button type="button" onclick="hideForms()" class="bg-gray-500 text-white px-4 py-2 rounded text-sm flex-1">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Chamber Form -->
        <div id="chamberForm" class="bg-white rounded-lg shadow p-4 hidden">
            <h3 class="font-semibold mb-3 text-gray-800 text-sm">Add New Chamber</h3>
            <form method="POST" class="space-y-3 text-sm">
                <select name="doctor_id" required class="border rounded px-3 py-2 w-full text-sm">
                    <option value="">Select Doctor</option>
                    <?php $doctors_list->data_seek(0); while ($d = $doctors_list->fetch_assoc()): ?>
                        <option value="<?= $d['id'] ?>"><?= $d['name'] ?></option>
                    <?php endwhile; ?>
                </select>
                <input type="text" name="name" placeholder="Chamber Name" required class="border rounded px-3 py-2 w-full text-sm">
                <input type="text" name="location" placeholder="Location" required class="border rounded px-3 py-2 w-full text-sm">
                <input type="number" step="0.01" name="fee" placeholder="Chamber Fee" required class="border rounded px-3 py-2 w-full text-sm">
                <input type="text" name="phone" placeholder="Phone" class="border rounded px-3 py-2 w-full text-sm">
                <input type="text" name="hours" placeholder="Visiting Hours" class="border rounded px-3 py-2 w-full text-sm">
                <div class="flex gap-2">
                    <button type="submit" name="add_chamber" class="bg-[#20B2AA] text-white px-4 py-2 rounded text-sm flex-1">Add Chamber</button>
                    <button type="button" onclick="hideForms()" class="bg-gray-500 text-white px-4 py-2 rounded text-sm flex-1">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- All Tables with CRUD Operations -->
    <div class="space-y-6">
        <!-- Doctors Table -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-4 py-3 flex justify-between items-center">
                <h2 class="font-semibold text-gray-800 text-sm">Doctors</h2>
                <span class="bg-[#20B2AA] text-white px-2 py-1 rounded text-xs"><?= $stats['doctors'] ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Name</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Speciality</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Experience</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Fee</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Contact</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while ($d = $doctors->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4"> <?= $d['name'] ?></td>
                                <td class="py-2 px-4"><?= $d['speciality'] ?></td>
                                <td class="py-2 px-4"><?= $d['experience'] ?> yrs</td>
                                <td class="py-2 px-4">৳<?= $d['consultation_fee'] ?></td>
                                <td class="py-2 px-4 text-xs">
                                    <div><?= $d['phone'] ?></div>
                                    <div class="text-gray-500"><?= $d['email'] ?></div>
                                </td>
                                <td class="py-2 px-4 space-x-2">
                                    <button onclick="editDoctor(<?= $d['id'] ?>, '<?= $d['name'] ?>', '<?= $d['email'] ?>', '<?= $d['speciality'] ?>', '<?= $d['phone'] ?>', <?= $d['experience'] ?>, <?= $d['consultation_fee'] ?>)" 
                                            class="text-[#20B2AA] text-xs">Edit</button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this doctor?')">
                                        <input type="hidden" name="table" value="doctors">
                                        <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                        <button type="submit" name="delete_record" class="text-red-500 text-xs">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Patients Table -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-4 py-3 flex justify-between items-center">
                <h2 class="font-semibold text-gray-800 text-sm">Patients</h2>
                <span class="bg-[#20B2AA] text-white px-2 py-1 rounded text-xs"><?= $stats['patients'] ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Name</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Age</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Blood Group</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Contact</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while ($p = $patients->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4"><?= $p['name'] ?></td>
                                <td class="py-2 px-4"><?= $p['age'] ?></td>
                                <td class="py-2 px-4"><?= $p['blood_group'] ?: 'N/A' ?></td>
                                <td class="py-2 px-4 text-xs">
                                    <div><?= $p['phone'] ?></div>
                                    <div class="text-gray-500"><?= $p['email'] ?></div>
                                </td>
                                <td class="py-2 px-4 space-x-2">
                                    <button onclick="editPatient(<?= $p['id'] ?>, '<?= $p['name'] ?>', '<?= $p['email'] ?>', '<?= $p['phone'] ?>', <?= $p['age'] ?>, '<?= $p['blood_group'] ?>', '<?= $p['address'] ?>')" 
                                            class="text-[#20B2AA] text-xs">Edit</button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this patient?')">
                                        <input type="hidden" name="table" value="patients">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button type="submit" name="delete_record" class="text-red-500 text-xs">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Appointments Table -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-4 py-3 flex justify-between items-center">
                <h2 class="font-semibold text-gray-800 text-sm">Appointments</h2>
                <span class="bg-[#20B2AA] text-white px-2 py-1 rounded text-xs"><?= $stats['appointments'] ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Patient</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Doctor</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Date/Time</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Status</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while ($a = $appointments->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4"><?= $a['patient_name'] ?></td>
                                <td class="py-2 px-4"> <?= $a['doctor_name'] ?></td>
                                <td class="py-2 px-4 text-xs"><?= $a['appointment_date'] ?> <?= $a['appointment_time'] ?></td>
                                <td class="py-2 px-4">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                        <select name="status" onchange="this.form.submit()" class="text-xs border rounded px-1 py-0.5 
                                            <?= $a['status'] == 'scheduled' ? 'bg-yellow-100' :
                                               ($a['status'] == 'completed' ? 'bg-green-100' : 'bg-red-100') ?>">
                                            <option value="scheduled" <?= $a['status'] == 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                            <option value="completed" <?= $a['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                            <option value="cancelled" <?= $a['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                        <input type="hidden" name="update_appointment_status" value="1">
                                    </form>
                                </td>
                                <td class="py-2 px-4">
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this appointment?')">
                                        <input type="hidden" name="table" value="appointments">
                                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                        <button type="submit" name="delete_record" class="text-red-500 text-xs">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Chambers Table -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-4 py-3 flex justify-between items-center">
                <h2 class="font-semibold text-gray-800 text-sm">Chambers</h2>
                <span class="bg-[#20B2AA] text-white px-2 py-1 rounded text-xs"><?= $stats['chambers'] ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Name</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Location</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Doctor</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Chamber Fee</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while ($c = $chambers->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4"><?= $c['chamber_name'] ?></td>
                                <td class="py-2 px-4 text-xs"><?= $c['location'] ?></td>
                                <td class="py-2 px-4"> <?= $c['doctor_name'] ?></td>
                                <td class="py-2 px-4">৳<?= $c['chamber_fee'] ?></td>
                                <td class="py-2 px-4 space-x-2">
                                    <button onclick="editChamber(<?= $c['id'] ?>, <?= $c['doctor_id'] ?>, '<?= $c['chamber_name'] ?>', '<?= $c['location'] ?>', <?= $c['chamber_fee'] ?>, '<?= $c['phone'] ?>', '<?= $c['visiting_hours'] ?>')" 
                                            class="text-[#20B2AA] text-xs">Edit</button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this chamber?')">
                                        <input type="hidden" name="table" value="chambers">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="submit" name="delete_record" class="text-red-500 text-xs">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-4 py-3 flex justify-between items-center">
                <h2 class="font-semibold text-gray-800 text-sm">Payments</h2>
                <span class="bg-[#20B2AA] text-white px-2 py-1 rounded text-xs"><?= $stats['payments'] ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Patient</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Doctor</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Amount</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Method</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Status</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while ($pay = $payments->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4"><?= $pay['patient_name'] ?></td>
                                <td class="py-2 px-4"> <?= $pay['doctor_name'] ?></td>
                                <td class="py-2 px-4">৳<?= $pay['amount'] ?></td>
                                <td class="py-2 px-4"><?= $pay['payment_method'] ?></td>
                                <td class="py-2 px-4">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?= $pay['id'] ?>">
                                        <select name="status" onchange="this.form.submit()" class="text-xs border rounded px-1 py-0.5 
                                            <?= $pay['status'] == 'paid' ? 'bg-green-100' :
                                               ($pay['status'] == 'pending' ? 'bg-yellow-100' : 'bg-red-100') ?>">
                                            <option value="pending" <?= $pay['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="paid" <?= $pay['status'] == 'paid' ? 'selected' : '' ?>>Paid</option>
                                            <option value="failed" <?= $pay['status'] == 'failed' ? 'selected' : '' ?>>Failed</option>
                                        </select>
                                        <input type="hidden" name="update_payment_status" value="1">
                                    </form>
                                </td>
                                <td class="py-2 px-4">
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this payment?')">
                                        <input type="hidden" name="table" value="payments">
                                        <input type="hidden" name="id" value="<?= $pay['id'] ?>">
                                        <button type="submit" name="delete_record" class="text-red-500 text-xs">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Medical Records Table -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-4 py-3 flex justify-between items-center">
                <h2 class="font-semibold text-gray-800 text-sm">Medical Records</h2>
                <span class="bg-[#20B2AA] text-white px-2 py-1 rounded text-xs"><?= $stats['medical_records'] ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Patient</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Doctor</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Record Date</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Diagnosis</th>
                            <th class="text-left py-2 px-4 font-medium text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while ($m = $medical_records->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4"><?= $m['patient_name'] ?></td>
                                <td class="py-2 px-4"> <?= $m['doctor_name'] ?></td>
                                <td class="py-2 px-4"><?= $m['record_date'] ?></td>
                                <td class="py-2 px-4 text-xs"><?= substr($m['diagnosis'] ?? 'No diagnosis', 0, 50) ?>...</td>
                                <td class="py-2 px-4">
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this medical record?')">
                                        <input type="hidden" name="table" value="medical_records">
                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                        <button type="submit" name="delete_record" class="text-red-500 text-xs">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
        <!-- Edit forms will be injected here -->
    </div>
</div>

<script>
    function showForm(formId) {
        document.getElementById('formsSection').querySelectorAll('div').forEach(form => {
            form.classList.add('hidden');
        });
        document.getElementById(formId).classList.remove('hidden');
    }

    function hideForms() {
        document.getElementById('formsSection').querySelectorAll('div').forEach(form => {
            form.classList.add('hidden');
        });
    }

    function editDoctor(id, name, email, speciality, phone, experience, fee) {
        const form = `
            <h3 class="font-semibold mb-4 text-gray-800">Edit Doctor</h3>
            <form method="POST" class="space-y-3 text-sm">
                <input type="hidden" name="id" value="${id}">
                <input type="text" name="name" value="${name}" required class="w-full border rounded px-3 py-2 text-sm">
                <input type="email" name="email" value="${email}" required class="w-full border rounded px-3 py-2 text-sm">
                <input type="text" name="speciality" value="${speciality}" required class="w-full border rounded px-3 py-2 text-sm">
                <input type="text" name="phone" value="${phone}" required class="w-full border rounded px-3 py-2 text-sm">
                <input type="number" name="experience" value="${experience}" required class="w-full border rounded px-3 py-2 text-sm">
                <input type="number" step="0.01" name="fee" value="${fee}" required class="w-full border rounded px-3 py-2 text-sm">
                <div class="flex gap-2 pt-2">
                    <button type="submit" name="edit_doctor" class="bg-[#20B2AA] text-white px-3 py-2 rounded text-sm flex-1">Update</button>
                    <button type="button" onclick="closeEdit()" class="bg-gray-500 text-white px-3 py-2 rounded text-sm flex-1">Cancel</button>
                </div>
            </form>
        `;
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('editModal').querySelector('.bg-white').innerHTML = form;
    }

    function editPatient(id, name, email, phone, age, bloodGroup, address) {
        const form = `
            <h3 class="font-semibold mb-4 text-gray-800">Edit Patient</h3>
            <form method="POST" class="space-y-3 text-sm">
                <input type="hidden" name="id" value="${id}">
                <input type="text" name="name" value="${name}" required class="w-full border rounded px-3 py-2 text-sm">
                <input type="email" name="email" value="${email}" required class="w-full border rounded px-3 py-2 text-sm">
                <input type="text" name="phone" value="${phone}" required class="w-full border rounded px-3 py-2 text-sm">
                <input type="number" name="age" value="${age}" required class="w-full border rounded px-3 py-2 text-sm">
                <select name="blood_group" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">Blood Group</option>
                    <option value="A+" ${bloodGroup == 'A+' ? 'selected' : ''}>A+</option>
                    <option value="A-" ${bloodGroup == 'A-' ? 'selected' : ''}>A-</option>
                    <option value="B+" ${bloodGroup == 'B+' ? 'selected' : ''}>B+</option>
                    <option value="B-" ${bloodGroup == 'B-' ? 'selected' : ''}>B-</option>
                    <option value="O+" ${bloodGroup == 'O+' ? 'selected' : ''}>O+</option>
                    <option value="O-" ${bloodGroup == 'O-' ? 'selected' : ''}>O-</option>
                    <option value="AB+" ${bloodGroup == 'AB+' ? 'selected' : ''}>AB+</option>
                    <option value="AB-" ${bloodGroup == 'AB-' ? 'selected' : ''}>AB-</option>
                </select>
                <textarea name="address" class="w-full border rounded px-3 py-2 text-sm" rows="2">${address || ''}</textarea>
                <div class="flex gap-2 pt-2">
                    <button type="submit" name="edit_patient" class="bg-[#20B2AA] text-white px-3 py-2 rounded text-sm flex-1">Update</button>
                    <button type="button" onclick="closeEdit()" class="bg-gray-500 text-white px-3 py-2 rounded text-sm flex-1">Cancel</button>
                </div>
            </form>
        `;
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('editModal').querySelector('.bg-white').innerHTML = form;
    }

    function editChamber(id, doctorId, name, location, fee, phone, hours) {
        const form = `
            <h3 class="font-semibold mb-4 text-gray-800">Edit Chamber</h3>
            <form method="POST" class="space-y-3 text-sm">
                <input type="hidden" name="id" value="${id}">
                <select name="doctor_id" required class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">Doctor</option>
                    <?php $doctors_list->data_seek(0); while ($d = $doctors_list->fetch_assoc()): ?>
                        <option value="<?= $d['id'] ?>" ${doctorId == <?= $d['id'] ?> ? 'selected' : ''}><?= $d['name'] ?></option>
                    <?php endwhile; ?>
                </select>
                <input type="text" name="name" value="${name}" required class="w-full border rounded px-3 py-2 text-sm">
                <input type="text" name="location" value="${location}" required class="w-full border rounded px-3 py-2 text-sm">
                <input type="number" step="0.01" name="fee" value="${fee}" required class="w-full border rounded px-3 py-2 text-sm">
                <input type="text" name="phone" value="${phone}" class="w-full border rounded px-3 py-2 text-sm">
                <input type="text" name="hours" value="${hours}" class="w-full border rounded px-3 py-2 text-sm">
                <div class="flex gap-2 pt-2">
                    <button type="submit" name="edit_chamber" class="bg-[#20B2AA] text-white px-3 py-2 rounded text-sm flex-1">Update</button>
                    <button type="button" onclick="closeEdit()" class="bg-gray-500 text-white px-3 py-2 rounded text-sm flex-1">Cancel</button>
                </div>
            </form>
        `;
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('editModal').querySelector('.bg-white').innerHTML = form;
    }

    function closeEdit() {
        document.getElementById('editModal').classList.add('hidden');
    }

    document.getElementById('editModal').addEventListener('click', function (e) {
        if (e.target === this) closeEdit();
    });
</script>

<?php include 'includes/footer.php'; ?>