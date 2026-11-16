-- Uber for Ambulance: normalized relational schema for MySQL/MariaDB.
-- The database follows Option 8A disjoint-total specialization: every registered
-- person is stored once in USER and exactly one application role is represented
-- through PATIENT, DRIVER, or ADMIN. Subtype tables reuse USER.user_id as both
-- primary key and foreign key, which gives a 1:1 relationship and avoids
-- duplicated identity attributes and transitive dependencies.

CREATE DATABASE IF NOT EXISTS uber_for_ambulance
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE uber_for_ambulance;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS ACCEPTS;
DROP TABLE IF EXISTS RIDE_REQUEST;
DROP TABLE IF EXISTS ICU_AMBULANCE;
DROP TABLE IF EXISTS BASIC_AMBULANCE;
DROP TABLE IF EXISTS AMBULANCE;
DROP TABLE IF EXISTS PATIENT;
DROP TABLE IF EXISTS DRIVER;
DROP TABLE IF EXISTS ADMIN;
DROP TABLE IF EXISTS USER_PHONE;
DROP TABLE IF EXISTS `USER`;
SET FOREIGN_KEY_CHECKS = 1;

-- USER is the superclass in the Option 8A specialization hierarchy. It stores
-- attributes that depend only on user_id, satisfying 3NF and preventing the
-- same person name from being repeated in role-specific tables.
CREATE TABLE `USER` (
    user_id VARCHAR(10) PRIMARY KEY,
    username VARCHAR(30) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    CONSTRAINT chk_user_id_nonempty CHECK (CHAR_LENGTH(TRIM(user_id)) > 0),
    CONSTRAINT chk_user_name_nonempty CHECK (CHAR_LENGTH(TRIM(first_name)) > 0 AND CHAR_LENGTH(TRIM(last_name)) > 0)
) ENGINE=InnoDB;

-- USER_PHONE is a multivalued attribute resolved into its own relation. The
-- composite key prevents duplicate phone values for the same user and the FK
-- preserves referential integrity with cascading cleanup.
CREATE TABLE USER_PHONE (
    user_id VARCHAR(10),
    phone VARCHAR(20),
    PRIMARY KEY (user_id, phone),
    FOREIGN KEY (user_id) REFERENCES `USER`(user_id) ON DELETE CASCADE,
    CONSTRAINT chk_phone_nonempty CHECK (CHAR_LENGTH(TRIM(phone)) >= 7)
) ENGINE=InnoDB;

-- ADMIN is a subtype of USER. Reusing user_id as the PK/FK maps the 1:1
-- superclass-subclass relationship without introducing a redundant surrogate.
CREATE TABLE ADMIN (
    user_id VARCHAR(10) PRIMARY KEY,
    office_room_no VARCHAR(10) NOT NULL,
    admin_level VARCHAR(30) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES `USER`(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- DRIVER contains only driver-specific facts. license_no is independently
-- unique because a license identifies one driver, while admin_id records the
-- approving administrator without copying administrator attributes.
CREATE TABLE DRIVER (
    user_id VARCHAR(10) PRIMARY KEY,
    license_no VARCHAR(20) NOT NULL UNIQUE,
    rating DECIMAL(3,2) NOT NULL DEFAULT 5.00,
    admin_id VARCHAR(10) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES `USER`(user_id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES ADMIN(user_id),
    CONSTRAINT chk_driver_rating CHECK (rating >= 0.00 AND rating <= 5.00)
) ENGINE=InnoDB;

-- PATIENT stores facts that functionally depend only on the patient subtype
-- key. General identity facts remain in USER, preserving 3NF normalization.
CREATE TABLE PATIENT (
    user_id VARCHAR(10) PRIMARY KEY,
    emergency_type VARCHAR(50) NOT NULL,
    age INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES `USER`(user_id) ON DELETE CASCADE,
    CONSTRAINT chk_patient_age CHECK (age >= 0 AND age <= 120)
) ENGINE=InnoDB;

-- AMBULANCE is the supertype for vehicle inventory. Passenger fare and admin
-- cost are stored here because each depends directly on reg_no, not on a
-- subtype attribute. ACCEPTS later snapshots these values for each ride.
CREATE TABLE AMBULANCE (
    reg_no VARCHAR(15) PRIMARY KEY,
    hourly_rate INT NOT NULL,
    admin_cost INT NOT NULL DEFAULT 500,
    available_status VARCHAR(20) NOT NULL DEFAULT 'Available',
    vehicle_type VARCHAR(30) NOT NULL,
    admin_id VARCHAR(10) NOT NULL,
    FOREIGN KEY (admin_id) REFERENCES ADMIN(user_id),
    CONSTRAINT chk_hourly_rate_positive CHECK (hourly_rate > 0),
    CONSTRAINT chk_admin_cost_positive CHECK (admin_cost > 0 AND admin_cost < hourly_rate),
    CONSTRAINT chk_ambulance_status CHECK (available_status IN ('Available', 'Busy', 'Maintenance')),
    CONSTRAINT chk_vehicle_type CHECK (vehicle_type IN ('Basic', 'ICU'))
) ENGINE=InnoDB;

-- BASIC_AMBULANCE and ICU_AMBULANCE are disjoint vehicle subtypes. Their
-- primary-key-as-foreign-key mapping enforces at most one subtype row per
-- ambulance and avoids nullable subtype-specific columns in AMBULANCE.
CREATE TABLE BASIC_AMBULANCE (
    reg_no VARCHAR(15) PRIMARY KEY,
    stretcher_type VARCHAR(30) NOT NULL,
    kit_class VARCHAR(5) NOT NULL,
    FOREIGN KEY (reg_no) REFERENCES AMBULANCE(reg_no) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ICU_AMBULANCE (
    reg_no VARCHAR(15) PRIMARY KEY,
    ventilator_count INT NOT NULL,
    has_oxygen BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (reg_no) REFERENCES AMBULANCE(reg_no) ON DELETE CASCADE,
    CONSTRAINT chk_ventilator_count CHECK (ventilator_count >= 0)
) ENGINE=InnoDB;

-- RIDE_REQUEST records one ternary business event's request-side facts. The
-- patient_id and admin_id FKs identify participants without transitive copying
-- of their names; fare and status depend directly on ride_id.
CREATE TABLE RIDE_REQUEST (
    ride_id VARCHAR(10) PRIMARY KEY,
    pickup_location VARCHAR(100) NOT NULL,
    drop_location VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Requested',
    fare INT NOT NULL,
    vehicle_type VARCHAR(30) NOT NULL,
    request_date DATE NOT NULL,
    passenger_rating INT DEFAULT NULL,
    feedback TEXT DEFAULT NULL,
    patient_id VARCHAR(10) NOT NULL,
    admin_id VARCHAR(10) NOT NULL,
    FOREIGN KEY (patient_id) REFERENCES PATIENT(user_id),
    FOREIGN KEY (admin_id) REFERENCES ADMIN(user_id),
    CONSTRAINT chk_ride_fare_positive CHECK (fare > 0),
    CONSTRAINT chk_ride_vehicle_type CHECK (vehicle_type IN ('Basic', 'ICU')),
    CONSTRAINT chk_ride_status CHECK (status IN ('Requested', 'Accepted', 'Completed', 'Cancelled')),
    CONSTRAINT chk_passenger_rating CHECK (passenger_rating IS NULL OR passenger_rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- ACCEPTS resolves the 1:1:N ternary relationship between a ride, a driver,
-- and an ambulance. The composite PK prevents the same participant triple from
-- being stored twice. The settlement columns snapshot the admin cost and
-- leftover driver earning, preventing future fleet edits from changing history.
CREATE TABLE ACCEPTS (
    ride_id VARCHAR(10),
    driver_id VARCHAR(10),
    reg_no VARCHAR(15),
    admin_cost INT NOT NULL DEFAULT 0,
    driver_earning INT NOT NULL DEFAULT 0,
    settlement_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    settled_at DATE DEFAULT NULL,
    PRIMARY KEY (ride_id, driver_id, reg_no),
    FOREIGN KEY (ride_id) REFERENCES RIDE_REQUEST(ride_id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES DRIVER(user_id),
    FOREIGN KEY (reg_no) REFERENCES AMBULANCE(reg_no),
    CONSTRAINT chk_accepts_admin_cost CHECK (admin_cost >= 0),
    CONSTRAINT chk_accepts_driver_earning CHECK (driver_earning >= 0),
    CONSTRAINT chk_settlement_status CHECK (settlement_status IN ('Pending', 'Settled'))
) ENGINE=InnoDB;

-- Seed superclass rows first so every subtype and phone row has a valid parent.
INSERT INTO `USER` (user_id, username, first_name, last_name, password_hash) VALUES
('AD-901', 'maya_rahman', 'Maya', 'Rahman', '$2y$10$b3IJQDvm4Y3QOZlEIrJTDutGvzFxioHgSmUiVgo/JhnihUkfQIvnm'),
('PT-101', 'ariana_chowdhury', 'Ariana', 'Chowdhury', '$2y$10$.ugncFiyFxhk6tkVqhaYBOo.IXvKniBByy6jF8ti7hV6bfARn0rJ.'),
('PT-102', 'nabil_hasan', 'Nabil', 'Hasan', '$2y$10$.ugncFiyFxhk6tkVqhaYBOo.IXvKniBByy6jF8ti7hV6bfARn0rJ.'),
('PT-103', 'sadia_khan', 'Sadia', 'Khan', '$2y$10$.ugncFiyFxhk6tkVqhaYBOo.IXvKniBByy6jF8ti7hV6bfARn0rJ.'),
('DR-201', 'rafi_ahmed', 'Rafi', 'Ahmed', '$2y$10$WWaHuR9PtfS95BbMmxbXjefAxthaodBwkQRxxZtNoEtaZkDTenBoO'),
('DR-202', 'tania_sultana', 'Tania', 'Sultana', '$2y$10$WWaHuR9PtfS95BbMmxbXjefAxthaodBwkQRxxZtNoEtaZkDTenBoO'),
('DR-203', 'imran_hossain', 'Imran', 'Hossain', '$2y$10$WWaHuR9PtfS95BbMmxbXjefAxthaodBwkQRxxZtNoEtaZkDTenBoO');

INSERT INTO USER_PHONE (user_id, phone) VALUES
('AD-901', '+8801700000901'),
('PT-101', '+8801700000101'),
('PT-102', '+8801700000102'),
('PT-103', '+8801700000103'),
('DR-201', '+8801700000201'),
('DR-202', '+8801700000202'),
('DR-203', '+8801700000203');

INSERT INTO ADMIN (user_id, office_room_no, admin_level) VALUES
('AD-901', 'ER-07', 'Fleet Supervisor');

INSERT INTO PATIENT (user_id, emergency_type, age) VALUES
('PT-101', 'Cardiac support', 34),
('PT-102', 'Trauma response', 27),
('PT-103', 'Maternity support', 31);

INSERT INTO DRIVER (user_id, license_no, rating, admin_id) VALUES
('DR-201', 'DHA-AMB-201', 4.80, 'AD-901'),
('DR-202', 'DHA-AMB-202', 4.60, 'AD-901'),
('DR-203', 'DHA-AMB-203', 5.00, 'AD-901');

-- Fixed service charges used consistently by the patient request form,
-- driver queue, admin reports, and all seeded ride records.
INSERT INTO AMBULANCE (reg_no, hourly_rate, admin_cost, available_status, vehicle_type, admin_id) VALUES
('AMB-B01', 1500, 500, 'Available', 'Basic', 'AD-901'),
('AMB-B02', 1500, 500, 'Available', 'Basic', 'AD-901'),
('AMB-I01', 8000, 6000, 'Available', 'ICU', 'AD-901');

INSERT INTO BASIC_AMBULANCE (reg_no, stretcher_type, kit_class) VALUES
('AMB-B01', 'Hydraulic foldable', 'A'),
('AMB-B02', 'Manual scoop', 'B');

INSERT INTO ICU_AMBULANCE (reg_no, ventilator_count, has_oxygen) VALUES
('AMB-I01', 2, TRUE);

-- Five completed trips provide meaningful analytics and rating aggregation.
INSERT INTO RIDE_REQUEST
(ride_id, pickup_location, drop_location, status, fare, vehicle_type, request_date, passenger_rating, feedback, patient_id, admin_id)
VALUES
('RD-1001', 'Dhanmondi 27', 'Square Hospital', 'Completed', 1500, 'Basic', '2026-08-01', 5, 'Fast and calm response.', 'PT-101', 'AD-901'),
('RD-1002', 'Uttara Sector 7', 'Medical College Hospital', 'Completed', 1500, 'Basic', '2026-08-03', 4, 'Professional driving.', 'PT-102', 'AD-901'),
('RD-1003', 'Banani 11', 'Evercare Hospital', 'Completed', 8000, 'ICU', '2026-08-05', 5, 'Excellent ICU support.', 'PT-101', 'AD-901'),
('RD-1004', 'Mirpur 10', 'Birdem Hospital', 'Completed', 1500, 'Basic', '2026-08-08', 5, 'Very reassuring service.', 'PT-103', 'AD-901'),
('RD-1005', 'Mohakhali DOHS', 'United Hospital', 'Completed', 1500, 'Basic', '2026-08-10', 4, 'Good overall experience.', 'PT-102', 'AD-901'),
('RD-1006', 'Gulshan 2', 'LabAid Hospital', 'Requested', 1500, 'Basic', '2026-08-19', NULL, NULL, 'PT-101', 'AD-901'),
('RD-1007', 'Uttara Sector 11', 'Kurmitola General Hospital', 'Requested', 1500, 'Basic', '2026-08-20', NULL, NULL, 'PT-103', 'AD-901');

INSERT INTO ACCEPTS
(ride_id, driver_id, reg_no, admin_cost, driver_earning, settlement_status, settled_at)
VALUES
('RD-1001', 'DR-201', 'AMB-B01', 500, 1000, 'Settled', '2026-08-01'),
('RD-1002', 'DR-202', 'AMB-B02', 500, 1000, 'Settled', '2026-08-03'),
('RD-1003', 'DR-203', 'AMB-I01', 6000, 2000, 'Settled', '2026-08-05'),
('RD-1004', 'DR-201', 'AMB-B01', 500, 1000, 'Settled', '2026-08-08'),
('RD-1005', 'DR-202', 'AMB-B02', 500, 1000, 'Settled', '2026-08-10');

