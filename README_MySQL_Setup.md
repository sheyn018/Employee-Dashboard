# Employee Dashboard - MySQL Integration Setup Guide

## Prerequisites
1. **XAMPP or WAMP** (includes Apache, MySQL, and PHP)
2. **MySQL Database** running on your local machine

## Setup Instructions

### 1. Install XAMPP (if not already installed)
- Download XAMPP from https://www.apachefriends.org/
- Install and start Apache and MySQL services

### 2. Database Setup
1. Open phpMyAdmin (usually at http://localhost/phpmyadmin)
2. Import the database schema:
   - Click "Import" tab
   - Choose file: `database_schema.sql`
   - Click "Go"

**OR** manually run the SQL commands from `database_schema.sql` in phpMyAdmin SQL tab.

### 3. Configure Database Connection
1. Open `config.php`
2. Update the database credentials:
   ```php
   $servername = "localhost";
   $username = "root";        // Your MySQL username
   $password = "";            // Your MySQL password
   $dbname = "employee_dashboard"; // Your database name
   ```

### 4. Run the Application
1. Copy all project files to your XAMPP htdocs folder:
   ```
   C:\xampp\htdocs\employee-dashboard\
   ```
2. Access the application at: http://localhost/employee-dashboard/dashboard.html

### 5. Test the Database Connection
- Open: http://localhost/employee-dashboard/api.php/employees
- You should see JSON data with employee records

## File Structure
```
employee-dashboard/
├── dashboard.html          # Main dashboard (updated for MySQL)
├── config.php             # Database connection configuration
├── api.php                # PHP API endpoints
├── database_schema.sql    # Database setup script
├── index.html             # Home page
├── admin-login.html       # Admin login
├── style.css              # Styles
└── other HTML files...
```

## API Endpoints
- **GET** `/api.php/employees` - Fetch all employees
- **POST** `/api.php/employees` - Create new employee
- **DELETE** `/api.php/employees` - Delete employee (moves to deleted_records)

- **GET** `/api.php/salary-requests` - Fetch all salary requests
- **PUT** `/api.php/salary-requests` - Update salary request status
- **DELETE** `/api.php/salary-requests` - Delete salary request

- **GET** `/api.php/deleted-records` - Fetch deleted records
- **POST** `/api.php/deleted-records` - Restore deleted record
- **DELETE** `/api.php/deleted-records` - Permanently delete record

## Troubleshooting
1. **Database connection errors**: Check MySQL is running and credentials in config.php
2. **CORS errors**: Make sure you're accessing via http://localhost (not file://)
3. **API not found**: Ensure all files are in the htdocs directory
4. **Permission errors**: Check file permissions in htdocs folder

## Sample Data
The database comes with sample data:
- 3 employee records
- 3 salary requests

You can modify or add more data through the dashboard interface or directly in phpMyAdmin.