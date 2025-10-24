-- Book new appointment with validation
DELIMITER $$
CREATE PROCEDURE book_appointment_proc(
    IN p_patient_id INT,
    IN p_doctor_id INT,
    IN p_chamber_id INT,
    IN p_appointment_date DATE,
    IN p_appointment_time TIME,
    IN p_reason TEXT
)
BEGIN
    DECLARE existing_count INT;
    
    -- Check availability using function
    IF NOT is_doctor_available(p_doctor_id, p_appointment_date, p_appointment_time) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Time slot not available';
    ELSE
        INSERT INTO appointments (patient_id, doctor_id, chamber_id, appointment_date, appointment_time, reason)
        VALUES (p_patient_id, p_doctor_id, p_chamber_id, p_appointment_date, p_appointment_time, p_reason);
    END IF;
END$$
DELIMITER ;

-- Cancel appointment and handle refunds
DELIMITER $$
CREATE PROCEDURE cancel_appointment(IN p_appointment_id INT)
BEGIN
    DECLARE payment_status VARCHAR(20);
    
    SELECT status INTO payment_status 
    FROM payments 
    WHERE appointment_id = p_appointment_id;
    
    IF payment_status = 'paid' THEN
        UPDATE payments SET status = 'refund_pending' 
        WHERE appointment_id = p_appointment_id;
    END IF;
    
    UPDATE appointments SET status = 'cancelled' 
    WHERE id = p_appointment_id;
END$$
DELIMITER ;

-- Process bulk payments
DELIMITER $$
CREATE PROCEDURE process_daily_payments()
BEGIN
    UPDATE payments p
    JOIN appointments a ON p.appointment_id = a.id
    SET p.status = 'paid', p.payment_date = NOW()
    WHERE p.status = 'pending'
    AND a.status = 'completed'
    AND a.appointment_date < CURDATE();
END$$
DELIMITER ;
-- 
-- Generate monthly revenue report using function
DELIMITER $$
CREATE PROCEDURE generate_revenue_report(IN p_month INT, IN p_year INT)
BEGIN
    SELECT 
        d.speciality,
        COUNT(a.id) as appointment_count,
        SUM(p.amount) as total_revenue,
        AVG(p.amount) as average_fee
    FROM doctors d
    JOIN appointments a ON d.id = a.doctor_id
    JOIN payments p ON a.id = p.appointment_id
    WHERE p.status = 'paid'
    AND MONTH(a.appointment_date) = p_month
    AND YEAR(a.appointment_date) = p_year
    GROUP BY d.speciality
    ORDER BY total_revenue DESC;
END$$
DELIMITER ;