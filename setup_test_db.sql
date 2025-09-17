-- Create database
CREATE DATABASE IF NOT EXISTS employee_db;
USE employee_db;

-- ===========================
-- activerecords (master employee table)
-- ===========================
CREATE TABLE IF NOT EXISTS activerecords (
  id INT PRIMARY KEY CHECK (id BETWEEN 10000 AND 99999), -- 5-digit employee ID
  name VARCHAR(100) NOT NULL,
  position VARCHAR(100),
  work_date DATE,
  time_in TIME,
  time_out TIME,
  earnings DECIMAL(12,2),
  actions VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===========================
-- deletedrecords (keeps removed employee rows)
-- ===========================
CREATE TABLE IF NOT EXISTS deletedrecords (
  id INT PRIMARY KEY CHECK (id BETWEEN 10000 AND 99999),
  name VARCHAR(100),
  position VARCHAR(100),
  work_date DATE,
  time_in TIME,
  time_out TIME,
  earnings DECIMAL(12,2),
  actions VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===========================
-- employeesalaryrequests (stores request + snapshot of employee name)
-- ===========================
CREATE TABLE IF NOT EXISTS employeesalaryrequests (
  id INT PRIMARY KEY CHECK (id BETWEEN 10000 AND 99999), -- 5-digit request id
  employee_id INT NULL CHECK (employee_id BETWEEN 10000 AND 99999),
  employee_name VARCHAR(100),           -- denormalized snapshot of employee name
  requested_salary DECIMAL(12,2),
  status VARCHAR(50),
  actions VARCHAR(50),
  CONSTRAINT fk_esr_employee FOREIGN KEY (employee_id) REFERENCES activerecords(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===========================
-- payslip_history (stores payslip + snapshot of employee name)
-- ===========================
CREATE TABLE IF NOT EXISTS payslip_history (
  id INT PRIMARY KEY CHECK (id BETWEEN 10000 AND 99999), -- 5-digit payslip id
  employee_id INT NULL CHECK (employee_id BETWEEN 10000 AND 99999),
  employee_name VARCHAR(100),   -- denormalized snapshot (optional)
  position VARCHAR(100),
  earnings DECIMAL(12,2),
  date_generated DATETIME,
  CONSTRAINT fk_payslip_employee FOREIGN KEY (employee_id) REFERENCES activerecords(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===========================
-- Sample Data
-- ===========================

-- Active employees
INSERT INTO activerecords (id, name, position, work_date, time_in, time_out, earnings, actions) VALUES
(13579, 'Williiam Searl', 'Software Developer', '2025-09-01', '09:00:00', '17:00:00', 50000.00, NULL),
(24680, 'Maria Garcia', 'Waitress',         '2025-09-03', '10:00:00', '18:00:00', 12000.00, NULL),
(35791, 'Michael Lee',   'Chemist',          '2025-09-05', '08:30:00', '16:30:00', 45000.00, NULL),
(46802, 'Emily Davis',   'HR Specialist',    '2025-09-06', '09:00:00', '17:00:00', 38000.00, NULL);

-- Deleted employees (historical)
INSERT INTO deletedrecords (id, name, position, work_date, time_in, time_out, earnings, actions) VALUES
(71543, 'John Doe', 'Cashier',      '2025-08-16', '09:00:00', '17:00:00', 15000.00, NULL),
(71123, 'Alice Brown', 'Receptionist','2025-08-20', '08:00:00', '16:00:00', 20000.00, NULL);

-- Employee salary requests (include employee_name snapshot)
INSERT INTO employeesalaryrequests (id, employee_id, employee_name, requested_salary, status, actions) VALUES
(90011, 13579, 'Williiam Searl', 55000.00, 'Pending', 'Delete'),
(90022, 24680, 'Maria Garcia',     15000.00, 'Approved', 'Delete'),
(90033, 35791, 'Michael Lee',      50000.00, 'Declined', 'Delete');

-- Payslip history (include employee_name snapshot)
INSERT INTO payslip_history (id, employee_id, employee_name, position, earnings, date_generated) VALUES
(80011, 13579, 'Williiam Searl', 'Software Developer', 50000.00, '2025-09-01 18:00:00'),
(80022, 24680, 'Maria Garcia',     'Waitress',           12000.00, '2025-09-03 18:30:00'),
(80033, 35791, 'Michael Lee',      'Chemist',            45000.00, '2025-09-05 17:00:00'),
(80044, 46802, 'Emily Davis',      'HR Specialist',      38000.00, '2025-09-06 17:15:00');
