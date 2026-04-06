CREATE DATABASE IF NOT EXISTS asistencia_fiei CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE asistencia_fiei;

CREATE TABLE IF NOT EXISTS schools (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    email VARCHAR(120) NULL,
    role ENUM('superadmin', 'head', 'teacher') NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_enabled (is_enabled)
);

CREATE TABLE IF NOT EXISTS students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    student_code VARCHAR(30) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(120) NULL,
    semester VARCHAR(20) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_students_school FOREIGN KEY (school_id) REFERENCES schools(id),
    INDEX idx_students_school (school_id)
);

CREATE TABLE IF NOT EXISTS courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(150) NOT NULL,
    section VARCHAR(20) NOT NULL DEFAULT '',
    cycle VARCHAR(30) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_courses_school FOREIGN KEY (school_id) REFERENCES schools(id),
    CONSTRAINT uq_course UNIQUE (school_id, code, section),
    INDEX idx_courses_school (school_id)
);

CREATE TABLE IF NOT EXISTS head_school_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    head_user_id INT UNSIGNED NOT NULL,
    school_id INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hsa_head FOREIGN KEY (head_user_id) REFERENCES users(id),
    CONSTRAINT fk_hsa_school FOREIGN KEY (school_id) REFERENCES schools(id),
    CONSTRAINT uq_hsa UNIQUE (head_user_id, school_id)
);

CREATE TABLE IF NOT EXISTS teacher_school_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_user_id INT UNSIGNED NOT NULL,
    school_id INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tsa_teacher FOREIGN KEY (teacher_user_id) REFERENCES users(id),
    CONSTRAINT fk_tsa_school FOREIGN KEY (school_id) REFERENCES schools(id),
    CONSTRAINT uq_tsa UNIQUE (teacher_user_id, school_id)
);

CREATE TABLE IF NOT EXISTS teacher_course_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_user_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    period_label VARCHAR(30) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tca_teacher FOREIGN KEY (teacher_user_id) REFERENCES users(id),
    CONSTRAINT fk_tca_course FOREIGN KEY (course_id) REFERENCES courses(id),
    CONSTRAINT uq_tca UNIQUE (teacher_user_id, course_id)
);

CREATE TABLE IF NOT EXISTS course_enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    period_label VARCHAR(30) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    enrolled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ce_course FOREIGN KEY (course_id) REFERENCES courses(id),
    CONSTRAINT fk_ce_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT uq_ce UNIQUE (course_id, student_id)
);

CREATE TABLE IF NOT EXISTS attendance_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    teacher_user_id INT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_as_course FOREIGN KEY (course_id) REFERENCES courses(id),
    CONSTRAINT fk_as_teacher FOREIGN KEY (teacher_user_id) REFERENCES users(id),
    CONSTRAINT uq_attendance_session UNIQUE (course_id, attendance_date)
);

CREATE TABLE IF NOT EXISTS attendance_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    status ENUM('present', 'late', 'justified', 'absent') NOT NULL DEFAULT 'present',
    recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ar_session FOREIGN KEY (session_id) REFERENCES attendance_sessions(id),
    CONSTRAINT fk_ar_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT uq_attendance_record UNIQUE (session_id, student_id),
    INDEX idx_ar_status (status)
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT UNSIGNED NULL,
    school_id INT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    table_name VARCHAR(80) NOT NULL,
    record_id BIGINT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
    CONSTRAINT fk_audit_school FOREIGN KEY (school_id) REFERENCES schools(id),
    INDEX idx_audit_created_at (created_at),
    INDEX idx_audit_school (school_id)
);

INSERT INTO schools (code, name, is_active)
SELECT '140', 'Escuela de Electrónica', 1
WHERE NOT EXISTS (SELECT 1 FROM schools WHERE code = '140');

INSERT INTO schools (code, name, is_active)
SELECT '141', 'Escuela de Informática', 1
WHERE NOT EXISTS (SELECT 1 FROM schools WHERE code = '141');

INSERT INTO schools (code, name, is_active)
SELECT '142', 'Escuela de Mecatrónica', 1
WHERE NOT EXISTS (SELECT 1 FROM schools WHERE code = '142');

INSERT INTO schools (code, name, is_active)
SELECT '143', 'Escuela de Telecomunicaciones', 1
WHERE NOT EXISTS (SELECT 1 FROM schools WHERE code = '143');
