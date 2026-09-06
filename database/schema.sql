-- =====================================================
-- SESH / SRFMS - Database Schema
-- Session / Exam Resource Management System
-- =====================================================

CREATE DATABASE IF NOT EXISTS sesh
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sesh;

-- -----------------------------------------------------
-- Table: programmes
-- Must be created before `users`, `semesters`, and `bookings`
-- -----------------------------------------------------
CREATE TABLE programmes (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name    VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: users
-- -----------------------------------------------------
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(100)        NOT NULL,
    email           VARCHAR(150)        NOT NULL UNIQUE,
    password_hash   VARCHAR(255)        NOT NULL,
    role            ENUM('Admin', 'Coordinator', 'Faculty') NOT NULL DEFAULT 'Faculty',
    department      VARCHAR(100)        DEFAULT NULL,
    programme_id    INT UNSIGNED        DEFAULT NULL,
    phone           VARCHAR(20)         DEFAULT NULL,
    status          ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_users_programme
        FOREIGN KEY (programme_id) REFERENCES programmes(id)
        ON UPDATE CASCADE ON DELETE SET NULL,

    INDEX idx_users_programme (programme_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: rooms
-- -----------------------------------------------------
CREATE TABLE rooms (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_code       VARCHAR(30)         NOT NULL UNIQUE,
    name            VARCHAR(100)        DEFAULT NULL,
    building        VARCHAR(100)        DEFAULT NULL,
    floor           VARCHAR(50)         DEFAULT NULL,
    wing_block      VARCHAR(100)        DEFAULT NULL,
    notes           TEXT                DEFAULT NULL,
    type            ENUM('Classroom', 'Computer Lab', 'Science Lab', 'Seminar Hall', 'Auditorium', 'Meeting Room', 'Staff Room', 'Other') NOT NULL DEFAULT 'Classroom',
    capacity        SMALLINT UNSIGNED   NOT NULL,
    exam_capacity   SMALLINT UNSIGNED   DEFAULT NULL,
    primary_department VARCHAR(100)     NOT NULL DEFAULT 'Common / Shared',
    equipment       TEXT                DEFAULT NULL,
    amenities       JSON                DEFAULT NULL,
    accessibility   JSON                DEFAULT NULL,
    has_projector   TINYINT(1)          NOT NULL DEFAULT 0,
    has_whiteboard  TINYINT(1)          NOT NULL DEFAULT 1,
    has_ac          TINYINT(1)          NOT NULL DEFAULT 0,
    status          ENUM('Active', 'Under Maintenance', 'Inactive', 'Reserved') NOT NULL DEFAULT 'Active',
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE room_media (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    media_type ENUM('photo', 'floor_plan') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_room_media_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: semesters
-- -----------------------------------------------------
CREATE TABLE semesters (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    programme_id    INT UNSIGNED NOT NULL,
    number          TINYINT UNSIGNED NOT NULL,

    CONSTRAINT fk_semesters_programme
        FOREIGN KEY (programme_id) REFERENCES programmes(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    UNIQUE KEY uq_semesters_programme_number (programme_id, number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: bookings
-- -----------------------------------------------------
CREATE TABLE bookings (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id             INT UNSIGNED        NOT NULL,
    user_id             INT UNSIGNED        NOT NULL,
    programme_id        INT UNSIGNED        NOT NULL,
    semester_id         INT UNSIGNED        NOT NULL,
    date                DATE                NOT NULL,
    start_time          TIME                NOT NULL,
    end_time            TIME                NOT NULL,
    type                ENUM('Class', 'Exam') NOT NULL DEFAULT 'Class',
    purpose             VARCHAR(255)        DEFAULT NULL,
    exam_name           VARCHAR(150)        DEFAULT NULL,
    subject             VARCHAR(100)        DEFAULT NULL,
    invigilator_name    VARCHAR(100)        DEFAULT NULL,
    num_students        SMALLINT UNSIGNED   DEFAULT NULL,
    status              ENUM('Pending', 'Approved', 'Rejected', 'Cancelled', 'Completed') NOT NULL DEFAULT 'Pending',
    rejection_reason    VARCHAR(500)        DEFAULT NULL,
    approved_by         INT UNSIGNED        DEFAULT NULL,
    approved_at         DATETIME            DEFAULT NULL,
    cancelled_by        INT UNSIGNED        DEFAULT NULL,
    cancelled_at        DATETIME            DEFAULT NULL,
    created_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_bookings_room
        FOREIGN KEY (room_id) REFERENCES rooms(id)
        ON UPDATE CASCADE,

    CONSTRAINT fk_bookings_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE,

    CONSTRAINT fk_bookings_programme
        FOREIGN KEY (programme_id) REFERENCES programmes(id)
        ON UPDATE CASCADE,

    CONSTRAINT fk_bookings_semester
        FOREIGN KEY (semester_id) REFERENCES semesters(id)
        ON UPDATE CASCADE,

    CONSTRAINT fk_bookings_approved_by
        FOREIGN KEY (approved_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT fk_bookings_cancelled_by
        FOREIGN KEY (cancelled_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,

    INDEX idx_bookings_date_room (date, room_id),
    INDEX idx_bookings_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: exam_details
-- -----------------------------------------------------
CREATE TABLE exam_details (
    booking_id       INT UNSIGNED PRIMARY KEY,
    exam_name        VARCHAR(150) NOT NULL,
    subject          VARCHAR(100) NOT NULL,
    invigilator_name VARCHAR(100) NOT NULL,
    num_students     SMALLINT UNSIGNED NOT NULL,

    CONSTRAINT fk_exam_details_booking
        FOREIGN KEY (booking_id) REFERENCES bookings(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_audit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED NOT NULL,
    actor_id INT UNSIGNED NOT NULL,
    action ENUM('created', 'approved', 'rejected', 'cancelled', 'edited') NOT NULL,
    notes VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_booking_audit_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_by INT UNSIGNED DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_blackouts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_booking_blackouts_window (starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;