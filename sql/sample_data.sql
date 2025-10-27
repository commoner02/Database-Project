-- Insert sample doctors
INSERT INTO doctors (name, email, speciality, phone, experience, consultation_fee) VALUES
('Dr. Ahmed Rahman', 'ahmed.rahman@hospital.com', 'Cardiology', '01711223344', 15, 1200.00),
('Dr. Fatima Begum', 'fatima.begum@hospital.com', 'Pediatrics', '01711223355', 12, 800.00),
('Dr. Rajesh Sharma', 'rajesh.sharma@hospital.com', 'Orthopedics', '01711223366', 10, 1000.00),
('Dr. Sunita Chowdhury', 'sunita.chowdhury@hospital.com', 'Dermatology', '01711223377', 8, 900.00),
('Dr. Mohammad Khan', 'mohammad.khan@hospital.com', 'Neurology', '01711223388', 20, 1500.00);

-- Insert sample patients
INSERT INTO patients (name, email, phone, age, blood_group, address) VALUES
('Abdul Karim', 'abdul.karim@email.com', '01712345678', 45, 'B+', 'Mirpur, Dhaka'),
('Rina Akter', 'rina.akter@email.com', '01712345679', 28, 'O+', 'Uttara, Dhaka'),
('Mohammad Ali', 'mohammad.ali@email.com', '01712345680', 65, 'A+', 'Dhanmondi, Dhaka'),
('Sadia Islam', 'sadia.islam@email.com', '01712345681', 32, 'AB+', 'Gulshan, Dhaka'),
('Rahim Uddin', 'rahim.uddin@email.com', '01712345682', 50, 'O-', 'Banani, Dhaka');

-- Insert sample chambers
INSERT INTO chambers (doctor_id, chamber_name, location, chamber_fee, phone, visiting_hours) VALUES
(1, 'Heart Care Center', 'Dhanmondi, Dhaka', 200.00, '028765432', '4PM-8PM'),
(2, 'Child Health Clinic', 'Uttara, Dhaka', 150.00, '028765433', '5PM-9PM'),
(3, 'Bone & Joint Center', 'Mirpur, Dhaka', 180.00, '028765434', '6PM-10PM'),
(4, 'Skin Care Center', 'Gulshan, Dhaka', 160.00, '028765435', '3PM-7PM'),
(5, 'Neuro Care Center', 'Banani, Dhaka', 250.00, '028765436', '4PM-9PM');

-- Insert sample appointments
INSERT INTO appointments (patient_id, doctor_id, chamber_id, appointment_date, reason, status) VALUES
(1, 1, 1, CURDATE(), 'Chest pain and high blood pressure', 'scheduled'),
(2, 2, 2, CURDATE(), 'Child fever and cough', 'scheduled'),
(3, 3, 3, CURDATE(), 'Knee joint pain', 'completed'),
(4, 4, 4, CURDATE(), 'Skin allergy treatment', 'completed'),
(5, 5, 5, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Headache and dizziness', 'scheduled');

-- Insert sample payments
INSERT INTO payments (appointment_id, amount, status, payment_date, created_at) VALUES
(1, 1400.00, 'pending', NULL, NOW()),
(2, 950.00, 'pending', NULL, NOW()),
(3, 1180.00, 'paid', NOW(), NOW()),
(4, 1060.00, 'paid', NOW(), NOW()),
(5, 1750.00, 'pending', NULL, NOW());