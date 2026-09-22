
CREATE TABLE IF NOT EXISTS school (
    school_id INT PRIMARY KEY AUTO_INCREMENT,
    school_name VARCHAR(100) NOT NULL,
    school_code VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    school_id INT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'school_admin', 'operator') NOT NULL DEFAULT 'school_admin',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES school(school_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS graduate (
    graduate_id INT PRIMARY KEY AUTO_INCREMENT,
    school_id INT NOT NULL,
    student_id VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    suffix VARCHAR(10),
    course VARCHAR(100) NOT NULL,
    major VARCHAR(100) NULL,
    address VARCHAR(255) NOT NULL,
    honors ENUM('none', 'cum laude', 'magna cum laude', 'summa cum laude') NOT NULL DEFAULT 'none',
    graduation_year INT NOT NULL,
    photo VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES school(school_id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS qr_code (
    qr_id INT PRIMARY KEY AUTO_INCREMENT,
    graduate_id INT NOT NULL,
    qr_token VARCHAR(255) NOT NULL UNIQUE,
    qr_status ENUM('active', 'expired', 'used', 'invalidated') NOT NULL DEFAULT 'active',
    issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    used_at DATETIME NULL,
    FOREIGN KEY (graduate_id) REFERENCES graduate(graduate_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS scan_log (
    scan_id INT PRIMARY KEY AUTO_INCREMENT,
    qr_id INT NULL,
    operator_id INT NULL,
    scan_status ENUM('success', 'used', 'expired', 'invalidated') NOT NULL,
    scanned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (qr_id) REFERENCES qr_code(qr_id) ON DELETE SET NULL,
    FOREIGN KEY (operator_id) REFERENCES user(user_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS layout (
    layout_id INT PRIMARY KEY AUTO_INCREMENT,
    school_id INT NULL,
    template_id INT NULL,
    layout_name VARCHAR(100) NOT NULL,
    layout_config TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES school(school_id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES layout(layout_id) ON DELETE SET NULL
);

INSERT INTO layout (school_id, template_id, layout_name, layout_config, is_active)
VALUES (
    NULL,
    NULL,
    'Default Template',
    '{"background_color":"#1e293b","text_color":"#ffffff","accent_color":"#3b82f6","header_text":"Congratulations Graduates!","show_photo":true}',
    1
);