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
    role ENUM('student', 'admin', 'staff') NOT NULL DEFAULT 'student'
);



CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    idno INT NOT NULL,
    lastname VARCHAR(50) NOT NULL,
    firstname VARCHAR(50) NOT NULL,
    room_number INT NOT NULL,
    seat_number INT NOT NULL,
    reservation_date DATE NOT NULL,
    time_in TIME NOT NULL,
    purpose TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idno) REFERENCES users(idno) ON DELETE CASCADE
);
