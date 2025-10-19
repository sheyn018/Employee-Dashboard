# HR Connect - Employee Dashboard

A web-based employee management system for SEPTEM INC. that allows HR administrators to manage employee records, payslips, and salary requests.

## Features

- **Employee Management**: Add, view, edit, and delete employee records
- **Payslip Generation**: Create and manage employee payslips with earnings tracking
- **Salary Requests**: Handle employee salary increase requests with approval workflow
- **Dashboard Analytics**: View active records, deleted records, and salary request status
- **Data Recovery**: Restore accidentally deleted employee records
- **Leave Management**: Track and manage employee leave requests
- **Overtime Management**: Handle overtime requests and approvals
- **Performance Evaluations**: Conduct and track employee performance evaluations
- **Attendance Tracking**: Record employee check-ins and check-outs
- **Budget Planning**: Manage department budgets and spending
- **Training Programs**: Track employee training and certifications
- **Disciplinary Actions**: Document and manage employee disciplinary actions
- **Grievance Handling**: Process and resolve employee grievances and complaints

## Tech Stack

- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Backend**: PHP with MySQL
- **Database**: MySQL with structured tables for employees, payslips, and requests

## Database Structure

- `activerecords` - Current employee work records
- `deletedrecords` - Archived/deleted employee records
- `employeesalaryrequests` - Salary increase requests
- `payslip_history` - Generated payslip records
- `leave_requests` - Employee leave requests and approvals
- `employee_evaluations` - Performance evaluation records
- `attendance_records` - Employee attendance check-in/out logs
- `budget` - Department budget tracking
- `overtime_requests` - Overtime requests and approvals
- `training_programs` - Employee training and certification tracking
- `disciplinary_actions` - Disciplinary action records and tracking
- `grievances` - Employee grievances and complaint resolution

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

### Core Endpoints
- `GET /api/employees` - Fetch employee records
- `POST /api/new-employee` - Add new employee
- `GET /api/salary-requests` - Fetch salary requests
- `POST /api/add-payslip` - Generate payslip
- `GET /api/deleted-records` - Fetch deleted records

### Leave & Attendance
- `GET/POST/PUT/DELETE /api/leave-requests` - Manage leave requests
- `GET/POST/DELETE /api/attendance` - Track attendance

### Performance & Training
- `GET/POST/PUT/DELETE /api/evaluations` - Manage performance evaluations
- `GET/POST/PUT/DELETE /api/training-programs` - Manage training programs

### Time & Budget
- `GET/POST/PUT/DELETE /api/overtime-requests` - Manage overtime requests
- `GET/POST/PUT/DELETE /api/budget` - Manage department budgets

### Disciplinary & Grievances (New)
- `GET/POST/PUT/DELETE /api/disciplinary-actions` - Manage disciplinary actions
- `GET/POST/PUT/DELETE /api/grievances` - Manage employee grievances

For detailed API documentation on disciplinary actions and grievances, see [DISCIPLINARY_GRIEVANCE_API.md](DISCIPLINARY_GRIEVANCE_API.md)