-- Insert sample doctors
INSERT INTO doctors (name, email, speciality, phone, experience, consultation_fee) VALUES
('Dr. Ahmed Rahman', 'ahmed.rahman@hospital.com', 'Cardiology', '01711223344', 15, 1200.00),
('Dr. Fatima Begum', 'fatima.begum@hospital.com', 'Pediatrics', '01711223355', 12, 800.00),
('Dr. Rajesh Sharma', 'rajesh.sharma@hospital.com', 'Orthopedics', '01711223366', 10, 1000.00),
('Dr. Sunita Chowdhury', 'sunita.chowdhury@hospital.com', 'Dermatology', '01711223377', 8, 900.00);

-- Insert sample patients
INSERT INTO patients (name, email, phone, age, blood_group, address) VALUES
('Abdul Karim', 'abdul.karim@email.com', '01712345678', 45, 'B+', 'Mirpur, Dhaka'),
('Rina Akter', 'rina.akter@email.com', '01712345679', 28, 'O+', 'Uttara, Dhaka'),
('Mohammad Ali', 'mohammad.ali@email.com', '01712345680', 65, 'A+', 'Dhanmondi, Dhaka'),
('Sadia Islam', 'sadia.islam@email.com', '01712345681', 32, 'AB+', 'Gulshan, Dhaka');

-- Insert sample chambers
INSERT INTO chambers (doctor_id, chamber_name, location, chamber_fee, phone, visiting_hours) VALUES
(1, 'Heart Care Center', 'Dhanmondi, Dhaka', 200.00, '028765432', '4PM-8PM'),
(2, 'Child Health Clinic', 'Uttara, Dhaka', 150.00, '028765433', '5PM-9PM'),
(3, 'Bone & Joint Center', 'Mirpur, Dhaka', 180.00, '028765434', '6PM-10PM'),
(4, 'Skin Care Center', 'Gulshan, Dhaka', 160.00, '028765435', '3PM-7PM');

-- Insert sample appointments
INSERT INTO appointments (patient_id, doctor_id, chamber_id, appointment_date, appointment_time, reason) VALUES
(1, 1, 1, '2024-01-15', '10:00', 'Chest pain and high blood pressure'),
(2, 2, 2, '2024-01-15', '11:00', 'Child fever and cough'),
(3, 3, 3, '2024-01-16', '14:00', 'Knee joint pain'),
(4, 4, 4, '2024-01-16', '15:00', 'Skin allergy treatment');

-- Insert sample payments
INSERT INTO payments (appointment_id, amount, payment_method, status, payment_date) VALUES
(1, 1200.00, 'Cash', 'paid', NOW()),
(2, 800.00, 'bKash', 'paid', NOW()),
(3, 1000.00, 'Card', 'paid', NOW()),
(4, 900.00, 'Nagad', 'pending', NULL);

-- Insert sample medical records
INSERT INTO medical_records (appointment_id, diagnosis, prescription, notes) VALUES
(1, 'Hypertension and mild chest pain', 'Medication A, Medication B', 'Patient needs regular checkup'),
(2, 'Viral fever with cough', 'Paracetamol, Cough syrup', 'Advise rest and hydration'),
(3, 'Osteoarthritis in left knee', 'Pain relievers, Physiotherapy', 'Follow up after 2 weeks'),
(4, 'Allergic contact dermatitis', 'Antihistamines, Topical cream', 'Avoid allergens');