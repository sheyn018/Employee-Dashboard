-- Create database
CREATE DATABASE IF NOT EXISTS employee_db;
USE employee_db;


-- activerecords

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


-- deletedrecords

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


-- employeesalaryrequests

CREATE TABLE IF NOT EXISTS employeesalaryrequests (
  id INT PRIMARY KEY CHECK (id BETWEEN 10000 AND 99999), -- 5-digit request id
  employee_id INT NULL CHECK (employee_id BETWEEN 10000 AND 99999),
  employee_name VARCHAR(100),
  requested_salary DECIMAL(12,2),
  status VARCHAR(50),
  actions VARCHAR(50),
  CONSTRAINT fk_esr_employee FOREIGN KEY (employee_id) REFERENCES activerecords(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- payslip_history

CREATE TABLE IF NOT EXISTS payslip_history (
  id INT PRIMARY KEY CHECK (id BETWEEN 10000 AND 99999), -- 5-digit payslip id
  employee_id INT NULL CHECK (employee_id BETWEEN 10000 AND 99999),
  employee_name VARCHAR(100), 
  position VARCHAR(100),
  earnings DECIMAL(12,2),
  date_generated DATETIME,
  CONSTRAINT fk_payslip_employee FOREIGN KEY (employee_id) REFERENCES activerecords(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- leave_requests

CREATE TABLE IF NOT EXISTS leave_requests (
  id INT PRIMARY KEY CHECK (id BETWEEN 10000 AND 99999), -- 5-digit leave request id
  employee_id INT NULL CHECK (employee_id BETWEEN 10000 AND 99999),
  employee_name VARCHAR(100) NOT NULL,
  leave_type ENUM('sick_leave', 'vacation_leave', 'personal_leave', 'emergency_leave', 'maternity_leave', 'paternity_leave') NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  reason TEXT,
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  date_requested DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  actions VARCHAR(50) DEFAULT 'Delete',
  CONSTRAINT fk_leave_employee FOREIGN KEY (employee_id) REFERENCES activerecords(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- employee_evaluations

CREATE TABLE IF NOT EXISTS employee_evaluations (
  id INT PRIMARY KEY CHECK (id BETWEEN 10000 AND 99999), -- 5-digit evaluation id
  employee_id INT NULL CHECK (employee_id BETWEEN 10000 AND 99999),
  employee_name VARCHAR(100) NOT NULL,
  evaluator_name VARCHAR(100) NOT NULL,
  evaluation_period VARCHAR(50) NOT NULL, -- e.g., "Annual 2025", "Q1 2025"
  
  -- Simple 1-5 scale ratings for key areas
  technical_skills INT CHECK (technical_skills BETWEEN 1 AND 5),
  communication INT CHECK (communication BETWEEN 1 AND 5),
  teamwork INT CHECK (teamwork BETWEEN 1 AND 5),
  reliability INT CHECK (reliability BETWEEN 1 AND 5),
  problem_solving INT CHECK (problem_solving BETWEEN 1 AND 5),
  
  -- Calculated overall score (1-5)
  overall_score DECIMAL(3,2) CHECK (overall_score BETWEEN 1.00 AND 5.00),
  
  -- Comments
  strengths TEXT,
  areas_for_improvement TEXT,
  goals_next_period TEXT,
  additional_comments TEXT,
  
  -- Status and timestamps
  status ENUM('draft', 'completed', 'acknowledged') DEFAULT 'draft',
  date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_completed DATETIME NULL,
  actions VARCHAR(50) DEFAULT 'Delete',
  
  CONSTRAINT fk_eval_employee FOREIGN KEY (employee_id) REFERENCES activerecords(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- attendance_records

CREATE TABLE IF NOT EXISTS attendance_records (
  id INT PRIMARY KEY CHECK (id BETWEEN 10000 AND 99999), -- 5-digit attendance record id
  employee_id INT NULL CHECK (employee_id BETWEEN 10000 AND 99999),
  employee_name VARCHAR(100) NOT NULL,
  attendance_date DATE NOT NULL,
  attendance_time TIME NOT NULL,
  attendance_type ENUM('check_in', 'check_out') NOT NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  
  CONSTRAINT fk_attendance_employee FOREIGN KEY (employee_id) REFERENCES activerecords(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- budget

CREATE TABLE IF NOT EXISTS budget (
  id INT PRIMARY KEY AUTO_INCREMENT,
  department VARCHAR(100) NOT NULL,
  allocated_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  spent_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  fiscal_year VARCHAR(10) NOT NULL,
  remaining_amount DECIMAL(12,2) GENERATED ALWAYS AS (allocated_amount - spent_amount) STORED,
  percentage_spent DECIMAL(6,2) GENERATED ALWAYS AS (
    CASE 
      WHEN allocated_amount > 0 THEN ROUND((spent_amount / allocated_amount * 100), 2)
      ELSE 0.00
    END
  ) STORED,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  notes TEXT NULL,
  UNIQUE KEY unique_dept_year (department, fiscal_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Sample Data


-- Active employees
INSERT INTO activerecords (id, name, position, work_date, time_in, time_out, earnings, actions) VALUES
(13579, 'William Searl', 'Software Developer', '2025-09-01', '09:00:00', '17:00:00', 50000.00, NULL),
(24680, 'Maria Garcia', 'Waitress',         '2025-09-03', '10:00:00', '18:00:00', 12000.00, NULL),
(35791, 'Michael Lee',   'Chemist',          '2025-09-05', '08:30:00', '16:30:00', 45000.00, NULL),
(46802, 'Emily Davis',   'HR Specialist',    '2025-09-06', '09:00:00', '17:00:00', 38000.00, NULL);

-- Deleted employees (historical)
INSERT INTO deletedrecords (id, name, position, work_date, time_in, time_out, earnings, actions) VALUES
(71543, 'John Doe', 'Cashier',      '2025-08-16', '09:00:00', '17:00:00', 15000.00, NULL),
(71123, 'Alice Brown', 'Receptionist','2025-08-20', '08:00:00', '16:00:00', 20000.00, NULL);

-- Employee salary requests (include employee_name snapshot)
INSERT INTO employeesalaryrequests (id, employee_id, employee_name, requested_salary, status, actions) VALUES
(90011, 13579, 'William Searl', 55000.00, 'Pending', 'Delete'),
(90022, 24680, 'Maria Garcia',     15000.00, 'Approved', 'Delete'),
(90033, 35791, 'Michael Lee',      50000.00, 'Declined', 'Delete');

-- Payslip history (include employee_name snapshot)
INSERT INTO payslip_history (id, employee_id, employee_name, position, earnings, date_generated) VALUES
(80011, 13579, 'William Searl', 'Software Developer', 50000.00, '2025-09-01 18:00:00'),
(80022, 24680, 'Maria Garcia',     'Waitress',           12000.00, '2025-09-03 18:30:00'),
(80033, 35791, 'Michael Lee',      'Chemist',            45000.00, '2025-09-05 17:00:00'),
(80044, 46802, 'Emily Davis',      'HR Specialist',      38000.00, '2025-09-06 17:15:00');

-- Leave requests (sample data)
INSERT INTO leave_requests (id, employee_id, employee_name, leave_type, start_date, end_date, reason, status, date_requested) VALUES
(90111, 13579, 'William Searl', 'vacation_leave', '2025-10-15', '2025-10-19', 'Family vacation trip', 'pending', '2025-10-01 10:30:00'),
(90112, 24680, 'Maria Garcia', 'sick_leave', '2025-10-03', '2025-10-03', 'Flu symptoms', 'approved', '2025-10-02 08:15:00'),
(90113, 35791, 'Michael Lee', 'personal_leave', '2025-10-10', '2025-10-11', 'Personal matters', 'pending', '2025-10-01 14:20:00');

-- Employee evaluations (sample data)
INSERT INTO employee_evaluations (id, employee_id, employee_name, evaluator_name, evaluation_period, technical_skills, communication, teamwork, reliability, problem_solving, overall_score, strengths, areas_for_improvement, goals_next_period, additional_comments, status, date_created, date_completed) VALUES
(95001, 13579, 'William Searl', 'John Manager', 'Annual 2024', 4, 4, 5, 4, 5, 4.40, 'Excellent problem-solving skills and great team collaboration. Shows strong technical expertise in software development.', 'Could improve on communication with clients and presenting technical concepts to non-technical stakeholders.', 'Complete advanced communication training. Lead at least 2 client presentations. Mentor junior developers.', 'Employee shows great potential for leadership roles. Consider for promotion to senior developer.', 'completed', '2025-01-15 09:00:00', '2025-01-20 14:30:00'),
(95002, 24680, 'Maria Garcia', 'Sarah Supervisor', 'Annual 2024', 3, 5, 4, 5, 3, 4.00, 'Outstanding reliability and customer service skills. Always punctual and professional with customers.', 'Technical skills could be enhanced. Could benefit from additional training in POS systems and inventory management.', 'Complete technical training program. Cross-train in kitchen operations. Aim for team lead position.', 'Maria is a valuable team member with excellent work ethic. Training investment will yield great returns.', 'completed', '2025-01-18 11:00:00', '2025-01-25 16:00:00'),
(95003, 35791, 'Michael Lee', 'Dr. Chemistry Head', 'Annual 2024', 5, 3, 3, 4, 5, 4.00, 'Exceptional technical knowledge and analytical skills. Produces high-quality work consistently.', 'Communication and teamwork skills need development. Sometimes works in isolation without consulting team.', 'Participate in more team projects. Improve collaboration skills. Share knowledge through internal presentations.', 'Michael is technically brilliant but needs to work on soft skills. Consider pairing with a mentor for collaboration improvement.', 'completed', '2025-01-10 08:30:00', '2025-01-17 12:00:00');

-- Attendance records (sample data)
INSERT INTO attendance_records (id, employee_id, employee_name, attendance_date, attendance_time, attendance_type, notes) VALUES
(96001, 13579, 'William Searl', '2025-10-02', '08:45:00', 'check_in', 'Early arrival'),
(96002, 24680, 'Maria Garcia', '2025-10-02', '09:00:00', 'check_in', NULL),
(96003, 35791, 'Michael Lee', '2025-10-02', '08:30:00', 'check_in', NULL),
(96004, 46802, 'Emily Davis', '2025-10-02', '09:05:00', 'check_in', 'Slight delay due to traffic'),
(96005, 13579, 'William Searl', '2025-10-02', '17:15:00', 'check_out', 'Completed daily tasks'),
(96006, 24680, 'Maria Garcia', '2025-10-02', '18:00:00', 'check_out', NULL);

-- Budget records (sample data)
INSERT INTO budget (department, allocated_amount, spent_amount, fiscal_year, notes) VALUES
('IT Department', 150000.00, 85000.00, '2025', 'Software licenses and hardware upgrades'),
('Human Resources', 80000.00, 45000.00, '2025', 'Recruitment and training programs'),
('Marketing', 120000.00, 78000.00, '2025', 'Digital marketing and campaigns'),
('Operations', 200000.00, 125000.00, '2025', 'Facility maintenance and supplies'),
('Research & Development', 180000.00, 92000.00, '2025', 'New product development'),
('Customer Service', 60000.00, 38000.00, '2025', 'Support tools and training');
