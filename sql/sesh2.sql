-- filename: sql/sesh2.sql
CREATE DATABASE IF NOT EXISTS sesh2;
USE sesh2;

CREATE TABLE role (role_id INT AUTO_INCREMENT PRIMARY KEY, role_name VARCHAR(30) NOT NULL UNIQUE);
CREATE TABLE department (department_id INT AUTO_INCREMENT PRIMARY KEY, department_name VARCHAR(100) NOT NULL UNIQUE);
CREATE TABLE user (user_id INT AUTO_INCREMENT PRIMARY KEY, role_id INT NOT NULL, department_id INT NULL, full_name VARCHAR(120) NOT NULL, email VARCHAR(150) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (role_id) REFERENCES role(role_id), FOREIGN KEY (department_id) REFERENCES department(department_id) ON DELETE SET NULL);
CREATE TABLE room (room_id INT AUTO_INCREMENT PRIMARY KEY, room_name VARCHAR(80) NOT NULL UNIQUE, location VARCHAR(120), capacity INT NOT NULL DEFAULT 0, status ENUM('Available','Maintenance') NOT NULL DEFAULT 'Available');
CREATE TABLE asset (asset_id INT AUTO_INCREMENT PRIMARY KEY, room_id INT NOT NULL, asset_type ENUM('Projector','AC','Whiteboard') NOT NULL, quantity INT NOT NULL DEFAULT 1, FOREIGN KEY (room_id) REFERENCES room(room_id) ON DELETE CASCADE, UNIQUE KEY room_asset (room_id, asset_type));
CREATE TABLE timeslot (slot_id INT AUTO_INCREMENT PRIMARY KEY, slot_name VARCHAR(30) NOT NULL UNIQUE, start_time TIME NOT NULL, end_time TIME NOT NULL);
CREATE TABLE booking (booking_id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, room_id INT NOT NULL, slot_id INT NOT NULL, booking_date DATE NOT NULL, purpose VARCHAR(255) NOT NULL, status ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending', reviewed_by INT NULL, reviewed_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES user(user_id), FOREIGN KEY (room_id) REFERENCES room(room_id), FOREIGN KEY (slot_id) REFERENCES timeslot(slot_id), FOREIGN KEY (reviewed_by) REFERENCES user(user_id) ON DELETE SET NULL);
CREATE TABLE feedback (feedback_id INT AUTO_INCREMENT PRIMARY KEY, booking_id INT NOT NULL, user_id INT NOT NULL, rating TINYINT NOT NULL, comments TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (booking_id) REFERENCES booking(booking_id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES user(user_id));
CREATE TABLE systemlog (log_id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, action VARCHAR(150) NOT NULL, details TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE SET NULL);

DELIMITER $$
CREATE TRIGGER booking_approved_conflict BEFORE INSERT ON booking FOR EACH ROW
BEGIN
  IF NEW.status = 'Approved' AND EXISTS (SELECT 1 FROM booking WHERE room_id=NEW.room_id AND booking_date=NEW.booking_date AND slot_id=NEW.slot_id AND status='Approved') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Approved room conflict';
  END IF;
END$$
CREATE TRIGGER booking_approved_update_conflict BEFORE UPDATE ON booking FOR EACH ROW
BEGIN
  IF NEW.status = 'Approved' AND EXISTS (SELECT 1 FROM booking WHERE booking_id <> NEW.booking_id AND room_id=NEW.room_id AND booking_date=NEW.booking_date AND slot_id=NEW.slot_id AND status='Approved') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Approved room conflict';
  END IF;
END$$
DELIMITER ;
