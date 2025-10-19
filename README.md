# HR Connect - Employee Dashboard

A web-based employee management system for SEPTEM INC. that allows HR administrators to manage employee records, payslips, and salary requests.

## Features

- **Employee Management**: Add, view, edit, and delete employee records
- **Payslip Generation**: Create and manage employee payslips with earnings tracking
- **Salary Requests**: Handle employee salary increase requests with approval workflow
- **Dashboard Analytics**: View active records, deleted records, and salary request status
- **Data Recovery**: Restore accidentally deleted employee records

## Tech Stack

- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Backend**: PHP with MySQL
- **Database**: MySQL with structured tables for employees, payslips, and requests

## Database Structure

- `activerecords` - Current employee work records
- `deletedrecords` - Archived/deleted employee records
- `employeesalaryrequests` - Salary increase requests
- `payslip_history` - Generated payslip records

## Setup

1. **Database Setup**:
   ```bash
   mysql -u root -p < setup_test_db.sql
   ```

2. **Configure API**:
   - Update database credentials in `api/config.php`
   - Ensure PHP server is running on localhost:3000

3. **Launch Application**:
   - Open `index.html` in a web browser
   - Admin access available through the Admin login page

## Project Structure

```
├── index.html              # Landing page
├── pages/                  # Application pages
│   ├── dashboard.html      # Admin dashboard
│   ├── add-employee.html   # Employee registration
│   ├── add-payslip.html    # Payslip creation
│   └── admin-login.html    # Admin authentication
├── api/                    # Backend PHP API
│   ├── api.php            # Main API endpoints
│   └── config.php         # Database configuration
└── css/                    # Stylesheets
```

## API Endpoints

- `GET /api/employees` - Fetch employee records
- `POST /api/new-employee` - Add new employee
- `GET /api/salary-requests` - Fetch salary requests
- `POST /api/add-payslip` - Generate payslip
- `GET /api/deleted-records` - Fetch deleted records