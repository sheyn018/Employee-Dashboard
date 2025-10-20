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


-- overtime_requests

CREATE TABLE IF NOT EXISTS overtime_requests (
  id INT PRIMARY KEY CHECK (id BETWEEN 10000 AND 99999), -- 5-digit overtime request id
  employee_id INT NULL CHECK (employee_id BETWEEN 10000 AND 99999),
  employee_name VARCHAR(100) NOT NULL,
  ot_date DATE NOT NULL,
  hours DECIMAL(5,2) NOT NULL CHECK (hours > 0),
  reason TEXT,
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  date_requested DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_overtime_employee FOREIGN KEY (employee_id) REFERENCES activerecords(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  KEY idx_status (status),
  KEY idx_ot_date (ot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- training_programs

CREATE TABLE IF NOT EXISTS training_programs (
  id INT PRIMARY KEY CHECK (id BETWEEN 10000 AND 99999), -- 5-digit training program id
  employee_id INT NULL CHECK (employee_id BETWEEN 10000 AND 99999),
  employee_name VARCHAR(100) NOT NULL,
  program_name VARCHAR(200) NOT NULL,
  program_type VARCHAR(100), -- e.g., 'Technical', 'Soft Skills', 'Leadership', 'Compliance'
  start_date DATE NOT NULL,
  end_date DATE,
  duration_hours INT, -- Total hours for the program
  status ENUM('enrolled', 'ongoing', 'completed', 'cancelled') DEFAULT 'enrolled',
  completion_percentage INT DEFAULT 0 CHECK (completion_percentage BETWEEN 0 AND 100),
  trainer_name VARCHAR(100),
  location VARCHAR(200), -- Physical location or 'Online' or 'Hybrid'
  cost DECIMAL(10,2) DEFAULT 0.00,
  certification_obtained BOOLEAN DEFAULT FALSE,
  certification_name VARCHAR(200),
  notes TEXT,
  date_enrolled DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_completed DATETIME NULL,
  date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_training_employee FOREIGN KEY (employee_id) REFERENCES activerecords(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  KEY idx_status (status),
  KEY idx_program_name (program_name),
  KEY idx_start_date (start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- disciplinary_actions

CREATE TABLE IF NOT EXISTS disciplinary_actions (
  id INT PRIMARY KEY CHECK (id BETWEEN 10000 AND 99999), -- 5-digit disciplinary action id
  employee_id INT NULL CHECK (employee_id BETWEEN 10000 AND 99999),
  employee_name VARCHAR(100) NOT NULL,
  action_type ENUM('verbal_warning', 'written_warning', 'suspension', 'termination', 'other') NOT NULL,
  severity ENUM('minor', 'moderate', 'major', 'critical') DEFAULT 'minor',
  violation_type VARCHAR(100), -- e.g., 'Attendance', 'Conduct', 'Performance', 'Policy Violation'
  incident_date DATE NOT NULL,
  description TEXT NOT NULL,
  action_taken TEXT NOT NULL,
  reported_by VARCHAR(100) NOT NULL,
  witness_names TEXT, -- Comma-separated list or JSON
  follow_up_required BOOLEAN DEFAULT FALSE,
  follow_up_date DATE NULL,
  follow_up_notes TEXT,
  status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
  resolution_notes TEXT,
  date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by VARCHAR(100), -- Admin or HR personnel who created the record
  CONSTRAINT fk_disciplinary_employee FOREIGN KEY (employee_id) REFERENCES activerecords(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  KEY idx_action_type (action_type),
  KEY idx_status (status),
  KEY idx_incident_date (incident_date),
  KEY idx_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- grievances

CREATE TABLE IF NOT EXISTS grievances (
  id INT PRIMARY KEY CHECK (id BETWEEN 10000 AND 99999), -- 5-digit grievance id
  employee_id INT NULL CHECK (employee_id BETWEEN 10000 AND 99999),
  employee_name VARCHAR(100) NOT NULL,
  grievance_type ENUM('harassment', 'discrimination', 'workplace_safety', 'compensation', 'workload', 'management_issue', 'other') NOT NULL,
  priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
  subject VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  date_filed DATE NOT NULL,
  desired_outcome TEXT, -- What resolution the employee is seeking
  against_person VARCHAR(100), -- Name of person the grievance is against (if applicable)
  against_department VARCHAR(100), -- Department the grievance is against (if applicable)
  witnesses TEXT, -- Comma-separated list or JSON
  supporting_documents VARCHAR(255), -- File paths or references
  status ENUM('submitted', 'under_review', 'investigation', 'mediation', 'resolved', 'closed', 'rejected') DEFAULT 'submitted',
  assigned_to VARCHAR(100), -- HR personnel or manager assigned to handle
  investigation_notes TEXT,
  resolution_details TEXT,
  resolution_date DATE NULL,
  is_anonymous BOOLEAN DEFAULT FALSE,
  confidential BOOLEAN DEFAULT TRUE,
  date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_grievance_employee FOREIGN KEY (employee_id) REFERENCES activerecords(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  KEY idx_grievance_type (grievance_type),
  KEY idx_status (status),
  KEY idx_priority (priority),
  KEY idx_date_filed (date_filed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- benefits

CREATE TABLE IF NOT EXISTS benefits (
  id INT PRIMARY KEY CHECK (id BETWEEN 10000 AND 99999), -- 5-digit benefit id
  employee_id INT NULL CHECK (employee_id BETWEEN 10000 AND 99999),
  employee_name VARCHAR(100) NOT NULL,
  benefit_type VARCHAR(100) NOT NULL, -- e.g., 'Health Insurance', 'Dental', 'Vision', 'Retirement 401k', 'Life Insurance', 'Bonus', 'Allowance'
  description TEXT,
  amount DECIMAL(10,2) DEFAULT 0.00,
  start_date DATE NOT NULL,
  end_date DATE NULL,
  status ENUM('active', 'inactive', 'expired', 'cancelled') DEFAULT 'active',
  notes TEXT,
  date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_benefit_employee FOREIGN KEY (employee_id) REFERENCES activerecords(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  KEY idx_benefit_type (benefit_type),
  KEY idx_status (status),
  KEY idx_start_date (start_date)
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

-- Overtime requests (sample data)
INSERT INTO overtime_requests (id, employee_id, employee_name, ot_date, hours, reason, status, date_requested) VALUES
(97001, 13579, 'William Searl', '2025-10-18', 3.50, 'Project deadline for new feature', 'pending', '2025-10-17 15:30:00'),
(97002, 24680, 'Maria Garcia', '2025-10-19', 4.00, 'Special event catering', 'pending', '2025-10-18 10:00:00'),
(97003, 35791, 'Michael Lee', '2025-10-15', 2.50, 'Lab experiment completion', 'approved', '2025-10-14 14:20:00'),
(97004, 46802, 'Emily Davis', '2025-10-16', 5.00, 'Urgent recruitment drive', 'approved', '2025-10-15 09:45:00');

-- Training programs (sample data)
INSERT INTO training_programs (id, employee_id, employee_name, program_name, program_type, start_date, end_date, duration_hours, status, completion_percentage, trainer_name, location, cost, certification_obtained, certification_name, notes, date_enrolled, date_completed) VALUES
(98001, 13579, 'William Searl', 'Advanced Python Programming', 'Technical', '2025-09-15', '2025-11-15', 40, 'ongoing', 65, 'Dr. Python Expert', 'Online', 599.00, FALSE, NULL, 'Excellent progress, very engaged in all sessions', '2025-09-10 10:00:00', NULL),
(98002, 13579, 'William Searl', 'AWS Cloud Certification', 'Technical', '2025-01-10', '2025-03-10', 60, 'completed', 100, 'AWS Certified Trainer', 'Online', 899.00, TRUE, 'AWS Solutions Architect Associate', 'Successfully completed with distinction', '2025-01-05 09:00:00', '2025-03-10 16:00:00'),
(98003, 24680, 'Maria Garcia', 'Customer Service Excellence', 'Soft Skills', '2025-08-01', '2025-09-01', 24, 'completed', 100, 'Sarah Johnson', 'Main Office', 299.00, TRUE, 'Customer Service Professional Certificate', 'Outstanding performance and practical application', '2025-07-28 11:00:00', '2025-09-01 15:00:00'),
(98004, 24680, 'Maria Garcia', 'Food Safety and Hygiene', 'Compliance', '2025-10-01', '2025-10-15', 16, 'ongoing', 80, 'Health Inspector Mike', 'Training Center', 199.00, FALSE, NULL, 'Nearly complete, final exam scheduled', '2025-09-25 10:00:00', NULL),
(98005, 35791, 'Michael Lee', 'Leadership Development Program', 'Leadership', '2025-07-01', '2025-12-31', 100, 'ongoing', 50, 'Executive Coach Linda', 'Hybrid', 1500.00, FALSE, 'Certified Team Leader', 'Showing good leadership potential, needs more practice in team dynamics', '2025-06-25 09:00:00', NULL),
(98006, 46802, 'Emily Davis', 'HR Analytics and Data-Driven Decisions', 'Technical', '2025-09-01', '2025-10-30', 32, 'ongoing', 75, 'Data Scientist John', 'Online', 699.00, FALSE, NULL, 'Applying learnings immediately to current projects', '2025-08-28 14:00:00', NULL),
(98007, 46802, 'Emily Davis', 'Diversity and Inclusion Training', 'Compliance', '2025-06-15', '2025-07-01', 8, 'completed', 100, 'DEI Consultant Maria', 'Main Office', 149.00, TRUE, 'DEI Champion Certificate', 'Excellent insights and immediate implementation of best practices', '2025-06-10 13:00:00', '2025-07-01 12:00:00');

-- Disciplinary actions (sample data)
INSERT INTO disciplinary_actions (id, employee_id, employee_name, action_type, severity, violation_type, incident_date, description, action_taken, reported_by, witness_names, follow_up_required, follow_up_date, status, created_by, date_created) VALUES
(99001, 24680, 'Maria Garcia', 'verbal_warning', 'minor', 'Attendance', '2025-09-15', 'Employee arrived 45 minutes late without prior notification. This is the second occurrence this month.', 'Verbal warning issued. Employee counseled about importance of punctuality and proper notification procedures.', 'Sarah Supervisor', NULL, TRUE, '2025-10-15', 'resolved', 'Emily Davis', '2025-09-15 14:30:00'),
(99002, 35791, 'Michael Lee', 'written_warning', 'moderate', 'Policy Violation', '2025-08-20', 'Employee found using laboratory equipment for unauthorized personal project after hours. Potential safety hazard.', 'Written warning issued. Employee required to review lab safety protocols and sign acknowledgment. Mandatory retraining scheduled.', 'Dr. Chemistry Head', 'Lab Technician Jane', TRUE, '2025-09-20', 'closed', 'Emily Davis', '2025-08-21 09:00:00'),
(99003, 13579, 'William Searl', 'verbal_warning', 'minor', 'Performance', '2025-10-01', 'Missed project deadline without communication. Client deliverable delayed by 2 days.', 'Verbal counseling provided. Discussed time management and communication expectations. Employee committed to improved project tracking.', 'John Manager', NULL, FALSE, NULL, 'closed', 'Emily Davis', '2025-10-01 16:00:00');

-- Grievances (sample data)
INSERT INTO grievances (id, employee_id, employee_name, grievance_type, priority, subject, description, date_filed, desired_outcome, against_person, against_department, status, assigned_to, investigation_notes, resolution_details, date_created) VALUES
(99101, 24680, 'Maria Garcia', 'workload', 'high', 'Excessive overtime requirements without adequate compensation', 'Over the past 3 months, I have been consistently required to work 10-15 hours of overtime per week. While overtime requests are being tracked, the compensation does not match the actual hours worked. This is affecting my work-life balance and health.', '2025-09-25', 'Fair compensation for all overtime hours worked, and a review of staffing levels to reduce excessive overtime requirements.', NULL, 'Restaurant Operations', 'under_review', 'Emily Davis', 'Initial review conducted. Time records being audited for the past 3 months. Manager interview scheduled.', NULL, '2025-09-25 10:15:00'),
(99102, 35791, 'Michael Lee', 'management_issue', 'medium', 'Lack of communication and unclear performance expectations', 'My supervisor has not provided clear performance objectives for this quarter. Multiple requests for one-on-one meetings have been declined or rescheduled. I am uncertain about project priorities and feel unsupported in my role.', '2025-10-05', 'Regular one-on-one meetings with supervisor, clear written performance objectives, and improved communication channels.', 'Dr. Chemistry Head', 'Research & Development', 'investigation', 'Emily Davis', 'Met with employee. Concerns appear valid. Scheduling meeting with supervisor to address communication breakdown. Will implement structured check-in schedule.', NULL, '2025-10-05 14:00:00'),
(99103, 46802, 'Emily Davis', 'workplace_safety', 'urgent', 'Inadequate ergonomic setup causing physical strain', 'Current desk setup does not meet ergonomic standards. Despite multiple requests for an adjustable chair and monitor stand, no action has been taken. Experiencing back and neck pain that is worsening.', '2025-10-10', 'Immediate provision of ergonomic office furniture including adjustable chair, monitor stand, and keyboard tray. Ergonomic assessment of workstation.', NULL, 'HR Department', 'resolved', 'John Manager', 'Urgent priority due to health concerns. Ergonomic assessment completed. New furniture ordered and installed within 48 hours.', 'Ergonomic workstation setup completed. Employee reports significant improvement. Follow-up scheduled in 2 weeks to ensure continued comfort.', '2025-10-10 09:00:00');

-- Benefits (sample data)
INSERT INTO benefits (id, employee_id, employee_name, benefit_type, description, amount, start_date, end_date, status, notes, date_created) VALUES
(99201, 13579, 'William Searl', 'Health Insurance', 'Comprehensive health coverage including medical, dental, and vision', 450.00, '2025-01-01', '2025-12-31', 'active', 'Premium plan with low deductible', '2025-01-01 09:00:00'),
(99202, 13579, 'William Searl', 'Retirement 401k', 'Company match up to 6% of salary', 3000.00, '2025-01-01', NULL, 'active', 'Employer contributes 50% match up to 6%', '2025-01-01 09:00:00'),
(99203, 24680, 'Maria Garcia', 'Health Insurance', 'Basic health coverage', 250.00, '2025-01-01', '2025-12-31', 'active', 'Standard plan', '2025-01-01 09:00:00'),
(99204, 24680, 'Maria Garcia', 'Performance Bonus', 'Quarterly performance bonus', 500.00, '2025-09-01', '2025-09-30', 'active', 'Q3 2025 bonus for excellent customer service', '2025-09-01 10:00:00'),
(99205, 35791, 'Michael Lee', 'Health Insurance', 'Premium health coverage', 600.00, '2025-01-01', '2025-12-31', 'active', 'Premium plan with specialist coverage', '2025-01-01 09:00:00'),
(99206, 35791, 'Michael Lee', 'Research Allowance', 'Annual research and development allowance', 2000.00, '2025-01-01', '2025-12-31', 'active', 'For professional development and research materials', '2025-01-01 09:00:00'),
(99207, 46802, 'Emily Davis', 'Health Insurance', 'Comprehensive health coverage', 500.00, '2025-01-01', '2025-12-31', 'active', 'Premium plan', '2025-01-01 09:00:00'),
(99208, 46802, 'Emily Davis', 'Professional Development', 'Annual training and certification budget', 1500.00, '2025-01-01', '2025-12-31', 'active', 'For HR certifications and professional development', '2025-01-01 09:00:00');

