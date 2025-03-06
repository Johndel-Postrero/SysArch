CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO courses (course_name) VALUES
('BSIT'),
('BSCS'),
('HM'),
('CRIM'),
('CBA');

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    idno INT NOT NULL UNIQUE,
    lastname VARCHAR(50) NOT NULL,
    firstname VARCHAR(50) NOT NULL,
    middlename VARCHAR(50) NOT NULL,
    course ENUM('BSIT', 'BSCS', 'HM', 'CRIM', 'CBA') NOT NULL, 
    level ENUM('1', '2', '3', '4') NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    profile_picture VARCHAR(255) DEFAULT 'default-profile.png',
    role ENUM('student', 'admin', 'staff') NOT NULL DEFAULT 'student',
    session INT DEFAULT 30 CHECK (role = 'student' OR session IS NULL)
);


CREATE TABLE sitin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    idno INT NOT NULL,
    lab_number INT NOT NULL,
    sitin_date DATE NOT NULL,
    time_in TIME NOT NULL,
    time_out TIME NULL,
    purpose TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idno) REFERENCES users(idno) ON DELETE CASCADE
);

CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    attachment VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    admin_id INT NOT NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- Foreign key to link feedback to a user
    sitin_id INT NOT NULL, -- Foreign key to link feedback to a sitin entry
    message TEXT NOT NULL, -- The feedback message
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5), -- Star rating (1 to 5)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Timestamp of when the feedback was created
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, -- Link to the users table
    FOREIGN KEY (sitin_id) REFERENCES sitin(id) ON DELETE CASCADE -- Link to the sitin table
);

CREATE TABLE reservation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    idno INT NOT NULL,
    lab_number INT NOT NULL,
    reserve_date DATE NOT NULL,
    time_in TIME NOT NULL,
    time_out TIME NULL,
    purpose TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    checked_in BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idno) REFERENCES users(idno) ON DELETE CASCADE
);