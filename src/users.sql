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

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE notifications 
ADD COLUMN user_id INT NULL,
ADD COLUMN notification_type ENUM('student', 'admin') NOT NULL DEFAULT 'student',
ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idno` int(11) NOT NULL,
  `lab_number` int(11) NOT NULL,
  `reservation_date` date NOT NULL,
  `time_in` time NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `status` enum('pending','approved','declined') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idno` (`idno`),
  CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`idno`) REFERENCES `users` (`idno`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resources table
CREATE TABLE `resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `is_folder` tinyint(1) DEFAULT 0,
  `parent_id` int(11) DEFAULT NULL COMMENT 'For subfolders',
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `resources_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`),
  CONSTRAINT `resources_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    idno INT NOT NULL,
    lastname VARCHAR(50) NOT NULL,
    firstname VARCHAR(50) NOT NULL,
    points INT DEFAULT 1,
    sitin_id INT NOT NULL,
    rewarded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idno) REFERENCES users(idno) ON DELETE CASCADE,
    FOREIGN KEY (sitin_id) REFERENCES sitin(id) ON DELETE CASCADE,
    FOREIGN KEY (rewarded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Lab schedule table
CREATE TABLE lab_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lab_number VARCHAR(10) NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    status ENUM('available', 'unavailable') NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add index for better performance
CREATE INDEX idx_lab_schedule ON lab_schedule (lab_number, start_datetime, end_datetime);

ALTER TABLE lab_schedule 
ADD COLUMN schedule_type ENUM('recurring', 'specific') NOT NULL AFTER lab_number,
ADD COLUMN weekday TINYINT(1) NULL AFTER schedule_type,
ADD COLUMN specific_date DATE NULL AFTER weekday;

ALTER TABLE lab_schedule 
MODIFY COLUMN start_datetime TIME,
MODIFY COLUMN end_datetime TIME;

ALTER TABLE lab_schedule
ADD COLUMN start_time TIME,
ADD COLUMN end_time TIME;

-- Then update your data
UPDATE lab_schedule SET start_time = TIME(start_datetime), end_time = TIME(end_datetime);

-- Then you can drop the old columns if you want
ALTER TABLE lab_schedule
DROP COLUMN start_datetime,
DROP COLUMN end_datetime;