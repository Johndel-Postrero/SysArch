# CCS-SITIN Monitoring System

## Overview
The **CCS-SITIN Monitoring System** is a comprehensive, web-based application developed for the College of Computer Studies (University of Cebu). It is designed to streamline and digitalize the management of laboratory access, resource sharing, and student sit-in monitoring.

By replacing traditional manual logbooks, the system provides a more efficient, accurate, and interactive way to track student laboratory usage, enforce rules, and manage lab resources.

---

## Key Features

### 1. User Management & Authentication
*   **Role-Based Access Control:** Differentiates between Administrative users (Lab Custodians/Faculty) and standard Users (Students).
*   **Secure Authentication:** Login and registration modules ensure that only enrolled students and authorized staff can access the system.
*   **Profile Management:** Students can view and update their personal information and track their accumulated lab usage.

### 2. Sit-in Monitoring & Session Tracking
*   **Session Management:** Tracks the total number of allotted lab sessions per student and automatically deducts sessions as they sit in.
*   **Time Tracking:** Logs the exact time a student enters and exits the laboratory.
*   **Usage Analytics:** Provides a visual dashboard utilizing Chart.js to show students their lab usage over the last 7, 14, or 30 days.

### 3. Laboratory Scheduling & Reservations
*   **Real-time PC Availability:** Students can check the current status of laboratory computers.
*   **Reservation System:** Allows students to reserve a specific PC or time slot in advance to guarantee their spot.
*   **History Logs:** Maintains a detailed, unalterable history of all past sit-ins and reservations for auditing purposes.

### 4. Engagement & Communication
*   **Leaderboard System:** A gamification feature that tracks accumulated points based on lab usage and participation, encouraging student engagement.
*   **Announcement Board:** Admins can post updates, guidelines, and announcements (including image/file attachments) that are immediately visible on the student dashboard.
*   **Resource Management:** A centralized hub where educational materials, lab manuals, and resources can be shared and downloaded.

---

## Technology Stack

The project is built using a classic, robust LAMP/WAMP stack (Linux/Windows, Apache, MySQL, PHP) with modern frontend utilities.

### Backend
*   **Language:** PHP (v7.4 or higher) - Core server-side logic, session management, and routing.
*   **Database:** MySQL (v5.7 or higher) - Relational database management system for storing user data, logs, and system states.
*   **Architecture:** Procedural/Custom MVC pattern using raw PHP scripts separated by concerns (Views in `public/`, DB logic in `config/`).

### Frontend
*   **Styling:** Tailwind CSS (via CDN) - A utility-first CSS framework used for rapid, responsive, and modern UI development.
*   **Icons:** FontAwesome (v5.15.3) - Scalable vector icons used throughout the sidebar and UI components.
*   **Data Visualization:** Chart.js - Used on the dashboard to render interactive line graphs representing daily sit-in activity.
*   **Interactivity:** Vanilla JavaScript - Used for DOM manipulation, chart initialization, and basic frontend logic.

### Environment & Tools
*   **Web Server:** Apache (typically deployed via XAMPP).
*   **Version Control:** Git / GitHub.

---

## Project Structure

*   `public/` - The web root containing all accessible PHP pages (`index.php`, `login.php`, `dashboard`, etc.).
*   `config/` - Contains sensitive configuration files, primarily `db.php` for MySQL connections.
*   `database/` - Contains the `sitin.sql` file needed to scaffold the database schema.
*   `inc/` & `includes/` - Reusable PHP components (like sidebars and headers).
*   `upload/` & `announce/` - Directories for handling user-uploaded files and announcement attachments.

---

## How to Run Locally

1.  **Environment:** Ensure XAMPP is installed and running (Apache and MySQL active).
2.  **Database Setup:** 
    *   Go to `http://localhost/phpmyadmin`
    *   Create a database named `sitin`
    *   Import the `database/sitin.sql` file.
3.  **Deployment:** Place the project folder inside your XAMPP `htdocs` directory.
4.  **Access:** Open your browser and navigate to the project's public directory (e.g., `http://localhost/CCS-SITIN/public/login.php` or `http://localhost/SysArch/TempSysArch/public/`).
5.  **Default Admin Credentials:** Username: `admin`, Password: `admin`.

*(Note: If running via PHP's built-in server, navigate to the `public/` directory in the terminal and run `php -S localhost:8000`)*
