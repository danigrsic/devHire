-- Felhasználók

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    bio TEXT NULL,
    cv_path VARCHAR(255),
    user_type ENUM('user','company','admin') DEFAULT 'user',
    is_active BOOLEAN DEFAULT FALSE,
    is_approved BOOLEAN DEFAULT TRUE,
    activation_token VARCHAR(64),
    reset_token VARCHAR(64),
    reset_token_expires DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cégek

CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    website VARCHAR(255),
    address TEXT,
    description TEXT,
    logo_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_companies_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Munkakategóriák

CREATE TABLE job_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Álláshirdetések

CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    category_id INT NOT NULL,

    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255),

    application_deadline DATE,

    is_active BOOLEAN DEFAULT FALSE,
    is_approved BOOLEAN DEFAULT FALSE,

    views INT DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_jobs_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_jobs_category
        FOREIGN KEY (category_id)
        REFERENCES job_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Üzenetek / Jelentkezések

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,

    job_id INT NOT NULL,
    user_id INT NOT NULL,
    company_id INT NOT NULL,

    sender_id INT NULL,
    sender_type ENUM('user','company') NOT NULL DEFAULT 'user',

    message TEXT NOT NULL,

    is_application TINYINT(1) NOT NULL DEFAULT 0,
    is_rejected TINYINT(1) NOT NULL DEFAULT 0,
    is_read BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_messages_job
        FOREIGN KEY (job_id)
        REFERENCES jobs(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_messages_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_messages_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Felvett jelentkezők

CREATE TABLE hires (
    id INT AUTO_INCREMENT PRIMARY KEY,

    job_id INT NOT NULL,
    user_id INT NOT NULL,
    company_id INT NOT NULL,

    hired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_job_user (job_id, user_id),

    CONSTRAINT fk_hires_job
        FOREIGN KEY (job_id)
        REFERENCES jobs(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_hires_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_hires_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Elutasított / bezárt jelentkezések

CREATE TABLE application_dismissals (
    user_id INT NOT NULL,
    job_id INT NOT NULL,

    dismissed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, job_id),

    CONSTRAINT fk_dismissals_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_dismissals_job
        FOREIGN KEY (job_id)
        REFERENCES jobs(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
