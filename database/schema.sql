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
    name            VARCHAR(100)        NOT NULL UNIQUE,
    type            ENUM('Classroom', 'Laboratory', 'Seminar Hall', 'Conference Room', 'Other') NOT NULL DEFAULT 'Classroom',
    capacity        SMALLINT UNSIGNED   NOT NULL,
    equipment       TEXT                DEFAULT NULL,
    has_projector   TINYINT(1)          NOT NULL DEFAULT 0,
    has_whiteboard  TINYINT(1)          NOT NULL DEFAULT 1,
    has_ac          TINYINT(1)          NOT NULL DEFAULT 0,
    status          ENUM('Available', 'Under Maintenance', 'Unavailable') NOT NULL DEFAULT 'Available',
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
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
    status              ENUM('Pending', 'Approved', 'Rejected', 'Completed') NOT NULL DEFAULT 'Pending',
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

    INDEX idx_bookings_date_room (date, room_id),
    INDEX idx_bookings_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;