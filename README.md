# Employee Dashboard - SEPTEM INC.

A web-based HR management system for employee records, payroll, and administration.

## Features

- **Employee Management**: View, edit, and delete employee records
- **Admin Dashboard**: Administrative interface for HR operations
- **Salary Requests**: Handle employee salary request workflows
- **Authentication**: Secure admin login system

## Project Structure

```
├── index.html              # Landing page
├── api/                    # Backend API
│   ├── api.php            # Main API endpoints
│   └── config.php         # Database configuration
├── css/                   # Stylesheets
│   ├── style.css          # Main styles
│   ├── dashboard.css      # Dashboard-specific styles
│   └── payslip.css        # Payslip styles
├── pages/                 # Application pages
│   ├── login.html         # Employee login
│   ├── admin-login.html   # Admin login
│   ├── dashboard.html     # Employee records dashboard
│   ├── payslip.html       # Payslip management
│   ├── service.html       # Services page
│   └── contact.html       # Contact page
├── images/                # Profile images and assets
├── proto/                 # Form prototypes
└── docs/                  # Documentation
```

## Technologies Used

- **Frontend**: HTML5, CSS3, JavaScript
- **Backend**: PHP
- **Database**: MySQL
- **API**: RESTful API with CORS support

## Setup

1. Place files in a web server directory (Apache/Nginx)
2. Configure database settings in `api/config.php`
3. Ensure PHP and MySQL are installed and running
4. Access via web browser at your server URL
