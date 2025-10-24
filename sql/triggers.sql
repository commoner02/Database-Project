DELIMITER $$

CREATE TRIGGER prevent_double_booking
BEFORE INSERT ON appointments
FOR EACH ROW
BEGIN
    DECLARE existing_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO existing_count
    FROM appointments 
    WHERE doctor_id = NEW.doctor_id 
    AND appointment_date = NEW.appointment_date 
    AND appointment_time = NEW.appointment_time
    AND status != 'cancelled';
    
    IF existing_count > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Doctor already booked at this timeslot';
    END IF;
END$$

CREATE TRIGGER after_appointment_scheduled
AFTER INSERT ON appointments
FOR EACH ROW
BEGIN
    DECLARE doctor_fee DECIMAL(10,2);
    
    SELECT consultation_fee INTO doctor_fee 
    FROM doctors WHERE id = NEW.doctor_id;
    
    INSERT INTO payments (appointment_id, amount, payment_method, status)
    VALUES (NEW.id, doctor_fee, 'Cash', 'pending');
END$$

CREATE TRIGGER after_appointment_completed
AFTER UPDATE ON appointments
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        UPDATE payments SET status = 'paid', payment_date = NOW() 
        WHERE appointment_id = NEW.id AND status = 'pending';
    END IF;
END$$

CREATE TRIGGER log_fee_changes
BEFORE UPDATE ON doctors
FOR EACH ROW
BEGIN
    IF OLD.consultation_fee != NEW.consultation_fee THEN
        INSERT INTO fee_audit_log (doctor_id, old_fee, new_fee)
        VALUES (OLD.id, OLD.consultation_fee, NEW.consultation_fee);
    END IF;
END$$

DELIMITER ;
