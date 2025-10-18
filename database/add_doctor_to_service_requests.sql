-- Add assigned_doctor_id column to service_requests table
ALTER TABLE `service_requests` 
ADD COLUMN `assigned_doctor_id` INT(11) DEFAULT NULL AFTER `assigned_staff_id`,
ADD COLUMN `service_type` ENUM('general', 'doctor_appointment') DEFAULT 'general' AFTER `service_id`;

-- Add foreign key constraint for doctor
ALTER TABLE `service_requests`
ADD CONSTRAINT `fk_service_requests_doctor` 
FOREIGN KEY (`assigned_doctor_id`) REFERENCES `doctors`(`id`) ON DELETE SET NULL;

-- Add Doctor Appointment service if not exists
INSERT INTO `services` (`service_name`, `base_cost`, `description`, `is_active`) 
SELECT 'Doctor Appointment', 500.00, 'Schedule appointment with facility doctor', 1
WHERE NOT EXISTS (SELECT 1 FROM `services` WHERE `service_name` = 'Doctor Appointment');

-- Add Grocery Shopping service if not exists
INSERT INTO `services` (`service_name`, `base_cost`, `description`, `is_active`) 
SELECT 'Grocery Shopping', 150.00, 'Personal grocery and essentials shopping', 1
WHERE NOT EXISTS (SELECT 1 FROM `services` WHERE `service_name` = 'Grocery Shopping');

-- Deactivate Medical Consultation and Physiotherapy services
UPDATE `services` SET `is_active` = 0 WHERE `service_name` IN ('Medical Consultation', 'Physiotherapy');
