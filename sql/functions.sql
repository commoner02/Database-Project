-- ESSENTIAL VIEWS
CREATE OR REPLACE VIEW doctor_performance AS
SELECT 
    d.id, d.name, d.speciality, d.experience,
    COUNT(a.id) as total_appointments,
    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
    COALESCE(SUM(p.amount), 0) as total_revenue,
    COALESCE(AVG(p.amount), 0) as avg_revenue_per_appointment
FROM doctors d
LEFT JOIN appointments a ON d.id = a.doctor_id
LEFT JOIN payments p ON a.id = p.appointment_id AND p.status = 'paid'
GROUP BY d.id;

CREATE OR REPLACE VIEW patient_medical_history AS
SELECT 
    p.id as patient_id, p.name as patient_name, p.blood_group,
    a.appointment_date, a.appointment_time,
    d.name as doctor_name, d.speciality,
    m.diagnosis, m.prescription, m.notes
FROM patients p
JOIN appointments a ON p.id = a.patient_id
JOIN doctors d ON a.doctor_id = d.id
LEFT JOIN medical_records m ON a.id = m.appointment_id
WHERE a.status = 'completed';

CREATE OR REPLACE VIEW todays_appointments AS
SELECT 
    a.id, a.appointment_time,
    p.name as patient_name, p.phone as patient_phone,
    d.name as doctor_name, d.speciality,
    c.chamber_name, c.location
FROM appointments a
JOIN patients p ON a.patient_id = p.id
JOIN doctors d ON a.doctor_id = d.id
LEFT JOIN chambers c ON a.chamber_id = c.id
WHERE a.appointment_date = CURDATE() 
AND a.status = 'scheduled'
ORDER BY a.appointment_time;

CREATE OR REPLACE VIEW pending_payments AS
SELECT 
    p.id as payment_id, p.amount,
    pt.name as patient_name, d.name as doctor_name,
    a.appointment_date, p.payment_method
FROM payments p
JOIN appointments a ON p.appointment_id = a.id
JOIN patients pt ON a.patient_id = pt.id
JOIN doctors d ON a.doctor_id = d.id
WHERE p.status = 'pending';

CREATE OR REPLACE VIEW monthly_revenue_analytics AS
SELECT 
    DATE_FORMAT(a.appointment_date, '%Y-%m') as month,
    d.speciality,
    COUNT(a.id) as appointment_count,
    SUM(p.amount) as total_revenue
FROM appointments a
JOIN doctors d ON a.doctor_id = d.id
JOIN payments p ON a.id = p.appointment_id
WHERE p.status = 'paid'
GROUP BY DATE_FORMAT(a.appointment_date, '%Y-%m'), d.speciality
ORDER BY month DESC, total_revenue DESC;