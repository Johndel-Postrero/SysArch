# CCS-SITIN Monitoring System

## Overview
The CCS-SITIN Monitoring System is a web-based application designed for the College of Computer Studies to manage laboratory access, resources, and sit-in monitoring. This system helps track student attendance, manage laboratory resources, and improve overall laboratory management.

## Features
- User authentication and role-based access control
- Laboratory rules and regulations
- Sit-in monitoring and tracking
- Resource management and sharing
- Leaderboard for student engagement
- Announcement system
- Laboratory scheduling
- Reservation management
- Student history tracking

## Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server
- XAMPP (or similar package)

## Installation

### Step 1: Set up the environment
1. Download and install [XAMPP](https://www.apachefriends.org/download.html) if you don't have it already
2. Start the Apache and MySQL services from the XAMPP control panel

### Step 2: Set up the database
1. Open your web browser and navigate to `http://localhost/phpmyadmin`
2. Create a new database named `sitin`
3. Import the database from the `database/sitin.sql` file

### Step 3: Set up the application
1. Clone the repository into your XAMPP htdocs folder:
   ```
   git clone https://github.com/GelaPostrero/CCS-SITIN.git
   ```
   Alternatively, you can download the ZIP file from https://github.com/GelaPostrero/CCS-SITIN and extract it to `C:\xampp\htdocs\CCS-SITIN` or `/Applications/XAMPP/htdocs/CCS-SITIN` depending on your OS
2. Make sure the file permissions are set correctly (readable for the web server)

## Usage

1. Start XAMPP and ensure both Apache and MySQL services are running
2. Open your browser and navigate to `http://localhost/CCS-SITIN/login.php`
3. Log in using the default admin credentials:
   - Username: admin
   - Password: admin
4. After login, you'll be directed to the dashboard where you can access all system features

## System Structure
- `public/`: Contains all public-facing PHP files
- `config/`: Configuration files including database connection
- `database/`: SQL files for database setup
- `inc/`: Includes and library files
- `upload/`: Directory for uploaded files
- `resources/`: Educational resources for students

## Security Notes
- Change the default admin password after first login
- Regularly update the system and its dependencies
- Backup the database regularly

## Additional Information
- The system includes a leaderboard to encourage student engagement
- The resources section provides a way to share educational materials with students
- Laboratory rules and sit-in policies are accessible within the system

## Troubleshooting
- If you cannot access the login page, make sure your XAMPP services are running
- If you encounter database errors, verify that the database was imported correctly
- For file upload issues, check that the appropriate directories have write permissions

---

© College of Computer Studies - University of Cebu