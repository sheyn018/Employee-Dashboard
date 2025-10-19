<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

// Get the request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];
$path = parse_url($path, PHP_URL_PATH);

// Parse the path to get the endpoint
$pathParts = explode('/', trim($path, '/'));
$endpoint = end($pathParts);

// Check for query parameter action (for compatibility with existing pages)
$action = isset($_GET['action']) ? $_GET['action'] : null;

// If no specific endpoint or just accessing api.php directly, show connection status
if (($endpoint === 'api.php' || $endpoint === '' || empty($endpoint) || $endpoint === basename(__FILE__, '.php')) && !$action) {
    $connectionStatus = [
        'status' => 'success',
        'message' => 'Database connection successful!',
        'current_database' => 'test',
        'server' => 'localhost',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Get all databases
    $databasesResult = $conn->query("SHOW DATABASES");
    $databases = [];
    while ($row = $databasesResult->fetch_assoc()) {
        $databases[] = $row['Database'];
    }
    $connectionStatus['all_databases'] = $databases;
    
    // Get all tables in current database
    $tablesResult = $conn->query("SHOW TABLES");
    $allTables = [];
    while ($row = $tablesResult->fetch_assoc()) {
        $allTables[] = array_values($row)[0];
    }
    $connectionStatus['all_tables_in_current_db'] = $allTables;
    
    // Test if required tables exist
    $requiredTables = ['activerecords', 'employeesalaryrequests', 'deletedrecords', 'payslip_history', 'leave_requests', 'employee_evaluations', 'attendance_records', 'budget', 'overtime_requests', 'training_programs', 'disciplinary_actions', 'grievances'];
    $tableStatus = [];
    foreach ($requiredTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        $tableStatus[$table] = $result->num_rows > 0 ? 'exists' : 'missing';
    }
    $connectionStatus['required_tables_status'] = $tableStatus;

    sendJsonResponse($connectionStatus);
}

// Handle query parameter actions (for compatibility)
if ($action) {
    switch ($action) {
        case 'get_training':
            $result = $conn->query("SELECT * FROM training_programs ORDER BY date_enrolled DESC");
            $trainings = [];
            while ($row = $result->fetch_assoc()) {
                $trainings[] = $row;
            }
            sendJsonResponse(['success' => true, 'data' => $trainings]);
            break;
            
        case 'add_training':
            // Validate required fields
            if (!isset($_POST['employee_id'], $_POST['program_name'], $_POST['start_date'])) {
                sendJsonResponse(['success' => false, 'message' => 'Missing required fields: employee_id, program_name, start_date'], 400);
            }

            $employeeId = intval($_POST['employee_id']);
            $programName = $_POST['program_name'];
            $startDate = $_POST['start_date'];
            $endDate = isset($_POST['end_date']) && !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $status = isset($_POST['status']) ? $_POST['status'] : 'enrolled';

            // Validate employee_id
            if ($employeeId < 10000 || $employeeId > 99999) {
                sendJsonResponse(['success' => false, 'message' => 'Employee ID must be a 5-digit number'], 400);
            }

            // Get employee name
            $stmt = $conn->prepare("SELECT name FROM activerecords WHERE id = ?");
            $stmt->bind_param("i", $employeeId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if (!$result || $result->num_rows === 0) {
                sendJsonResponse(['success' => false, 'message' => 'Employee ID not found'], 404);
            }
            
            $employee = $result->fetch_assoc();
            $employeeName = $employee['name'];

            // Generate unique random 5-digit ID
            do {
                $randomId = rand(10000, 99999);
                $check = $conn->prepare("SELECT id FROM training_programs WHERE id = ?");
                $check->bind_param("i", $randomId);
                $check->execute();
                $result = $check->get_result();
            } while ($result && $result->num_rows > 0);

            // Insert training record
            if ($endDate) {
                $stmt = $conn->prepare("INSERT INTO training_programs (id, employee_id, employee_name, program_name, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iisssss", $randomId, $employeeId, $employeeName, $programName, $startDate, $endDate, $status);
            } else {
                $stmt = $conn->prepare("INSERT INTO training_programs (id, employee_id, employee_name, program_name, start_date, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iissss", $randomId, $employeeId, $employeeName, $programName, $startDate, $status);
            }
            
            if ($stmt->execute()) {
                sendJsonResponse([
                    'success' => true, 
                    'message' => 'Training record saved successfully!',
                    'id' => $randomId
                ]);
            } else {
                sendJsonResponse(['success' => false, 'message' => 'Failed to create training record', 'details' => $conn->error], 500);
            }
            break;

        case 'get_budget':
            $result = $conn->query("SELECT * FROM budget ORDER BY fiscal_year DESC, department ASC");
            $budgets = [];
            while ($row = $result->fetch_assoc()) {
                $budgets[] = $row;
            }
            sendJsonResponse(['success' => true, 'data' => $budgets]);
            break;
            
        case 'add_budget':
            // Validate required fields
            if (!isset($_POST['department'], $_POST['allocated_amount'], $_POST['fiscal_year'])) {
                sendJsonResponse(['success' => false, 'error' => 'Missing required fields: department, allocated_amount, fiscal_year'], 400);
            }

            $department = $_POST['department'];
            $allocatedAmount = floatval($_POST['allocated_amount']);
            $spentAmount = isset($_POST['spent_amount']) ? floatval($_POST['spent_amount']) : 0.00;
            $fiscalYear = $_POST['fiscal_year'];
            $notes = isset($_POST['notes']) ? $_POST['notes'] : null;

            // Validate amounts
            if ($allocatedAmount < 0 || $spentAmount < 0) {
                sendJsonResponse(['success' => false, 'error' => 'Amounts must be positive numbers'], 400);
            }

            if ($spentAmount > $allocatedAmount) {
                sendJsonResponse(['success' => false, 'error' => 'Spent amount cannot exceed allocated amount'], 400);
            }

            // Validate fiscal year
            if (!preg_match('/^\d{4}$/', $fiscalYear)) {
                sendJsonResponse(['success' => false, 'error' => 'Fiscal year must be a 4-digit year'], 400);
            }

            // Check for duplicate
            $stmt = $conn->prepare("SELECT id FROM budget WHERE department = ? AND fiscal_year = ?");
            $stmt->bind_param("ss", $department, $fiscalYear);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                sendJsonResponse(['success' => false, 'error' => 'Budget record already exists for this department and fiscal year'], 409);
            }

            // Insert budget record
            $stmt = $conn->prepare("
                INSERT INTO budget 
                (department, allocated_amount, spent_amount, fiscal_year, notes) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param("sddss", $department, $allocatedAmount, $spentAmount, $fiscalYear, $notes);
            
            if ($stmt->execute()) {
                sendJsonResponse([
                    'success' => true, 
                    'message' => 'Budget record saved successfully!',
                    'id' => $conn->insert_id
                ]);
            } else {
                sendJsonResponse(['success' => false, 'error' => 'Failed to create budget record', 'details' => $conn->error], 500);
            }
            break;
            
        case 'delete_budget':
            if (!isset($_POST['id'])) {
                sendJsonResponse(['success' => false, 'error' => 'Budget record ID is required'], 400);
            }
            
            $id = intval($_POST['id']);
            
            if ($conn->query("DELETE FROM budget WHERE id = $id")) {
                sendJsonResponse(['success' => true, 'message' => 'Budget record deleted successfully']);
            } else {
                sendJsonResponse(['success' => false, 'error' => 'Failed to delete budget record', 'details' => $conn->error], 500);
            }
            break;
            
        default:
            // Action not recognized, continue to path-based routing
            break;
    }
}

// Route endpoints
switch ($endpoint) {
    case 'employees':
    case 'activerecords':
        handleEmployees($conn, $method);
        break;
    case 'new-employee':
        handleNewEmployee($conn, $method);
        break;
    case 'salary-requests':
    case 'employeesalaryrequests':
        handleSalaryRequests($conn, $method);
        break;
    case 'deleted-records':
    case 'deletedrecords':
        handleDeletedRecords($conn, $method);
        break;
    case 'payslips':
    case 'payslip-history':
        handlePayslips($conn, $method);
        break;
    case 'add-payslip':
        handleAddPayslip($conn, $method);
        break;
    case 'leave-requests':
        handleLeaveRequests($conn, $method);
        break;
    case 'overtime-requests':
        handleOvertimeRequests($conn, $method);
        break;
    case 'evaluations':
    case 'employee-evaluations':
        handleEvaluations($conn, $method);
        break;
    case 'attendance':
        handleAttendance($conn, $method);
        break;
    case 'budget':
        handleBudget($conn, $method);
        break;
    case 'training':
    case 'training-programs':
        handleTrainingPrograms($conn, $method);
        break;
    case 'disciplinary':
    case 'disciplinary-actions':
        handleDisciplinaryActions($conn, $method);
        break;
    case 'grievances':
        handleGrievances($conn, $method);
        break;
    default:
        sendJsonResponse(['error' => 'Endpoint not found', 'available_endpoints' => ['employees', 'new-employee', 'salary-requests', 'deleted-records', 'payslips', 'add-payslip', 'leave-requests', 'overtime-requests', 'evaluations', 'attendance', 'budget', 'training', 'training-programs', 'disciplinary', 'disciplinary-actions', 'grievances', 'activerecords', 'employeesalaryrequests', 'deletedrecords', 'payslip-history']], 404);
}

// Insert-only employee endpoint
function handleNewEmployee($conn, $method) {
    if ($method !== 'POST') {
        sendJsonResponse(['error' => 'Only POST method allowed'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['name'], $input['position'], $input['work_date'], $input['time_in'], $input['time_out'], $input['earnings'])) {
        sendJsonResponse(['error' => 'Missing required fields'], 400);
    }

    // Generate unique random 5-digit ID
    do {
        $randomId = rand(10000, 99999);
        $check = $conn->prepare("SELECT id FROM activerecords WHERE id = ?");
        $check->bind_param("i", $randomId);
        $check->execute();
        $result = $check->get_result();
    } while ($result && $result->num_rows > 0);

    // Insert with custom ID
    $stmt = $conn->prepare("INSERT INTO activerecords (id, name, position, work_date, time_in, time_out, earnings) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssd", $randomId, $input['name'], $input['position'], $input['work_date'], $input['time_in'], $input['time_out'], $input['earnings']);
    
    if ($stmt->execute()) {
        sendJsonResponse(['success' => true, 'id' => $randomId]);
    } else {
        sendJsonResponse(['error' => 'Failed to insert new employee', 'details' => $conn->error], 500);
    }
}

// Employees
function handleEmployees($conn, $method) {
    switch ($method) {
        case 'GET':
            // Check if we need aggregated data for payslip display
            $aggregate = isset($_GET['aggregate']) ? $_GET['aggregate'] === 'true' : false;
            
            if ($aggregate) {
                // Return aggregated data grouped by employee (for payslip table display)
                $result = $conn->query("
                    SELECT 
                        name, 
                        position, 
                        COUNT(*) as task_count,
                        SUM(earnings) as total_earnings,
                        MAX(work_date) as last_work_date
                    FROM activerecords 
                    GROUP BY name, position 
                    ORDER BY last_work_date DESC
                ");
                $employees = [];
                while ($row = $result->fetch_assoc()) {
                    $employees[] = $row;
                }
                sendJsonResponse($employees);
            } else {
                // Return individual records
                $result = $conn->query("SELECT * FROM activerecords ORDER BY id DESC");
                $employees = [];
                while ($row = $result->fetch_assoc()) {
                    $employees[] = $row;
                }
                sendJsonResponse($employees);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO activerecords (name, position, work_date, time_in, time_out, earnings) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssd", $input['name'], $input['position'], $input['work_date'], $input['time_in'], $input['time_out'], $input['earnings']);
            
            if ($stmt->execute()) {
                sendJsonResponse(['success' => true, 'id' => $conn->insert_id]);
            } else {
                sendJsonResponse(['error' => 'Failed to create employee record', 'details' => $conn->error], 500);
            }
            break;

        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id']);

            $result = $conn->query("SELECT * FROM activerecords WHERE id = $id");
            $employee = $result ? $result->fetch_assoc() : null;

            if ($employee) {
                // Insert into deletedrecords with SAME structure
                $stmt = $conn->prepare("
                    INSERT INTO deletedrecords 
                    (id, name, position, work_date, time_in, time_out, earnings) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "isssssd", 
                    $employee['id'], 
                    $employee['name'], 
                    $employee['position'], 
                    $employee['work_date'], 
                    $employee['time_in'], 
                    $employee['time_out'], 
                    $employee['earnings']
                );

                if ($stmt->execute()) {
                    $conn->query("DELETE FROM activerecords WHERE id = $id");
                    sendJsonResponse(['success' => true, 'moved_id' => $id]);
                } else {
                    sendJsonResponse(['error' => 'Failed to insert into deletedrecords', 'details' => $stmt->error], 500);
                }
            } else {
                sendJsonResponse(['error' => 'Employee not found'], 404);
            }
            break;

    }
}

// Salary requests
function handleSalaryRequests($conn, $method) {
    switch ($method) {
        case 'GET':
            $result = $conn->query("SELECT * FROM employeesalaryrequests ORDER BY id DESC");
            $requests = [];
            while ($row = $result->fetch_assoc()) {
                $requests[] = $row;
            }
            sendJsonResponse($requests);
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);

            // Generate unique random 5-digit ID
            do {
                $randomId = rand(10000, 99999);
                $check = $conn->prepare("SELECT id FROM employeesalaryrequests WHERE id = ?");
                $check->bind_param("i", $randomId);
                $check->execute();
                $result = $check->get_result();
            } while ($result && $result->num_rows > 0);

            // Insert with custom ID
            $stmt = $conn->prepare("INSERT INTO employeesalaryrequests (id, employee_name, requested_salary, status, actions) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isdss", $randomId, $input['employee_name'], $input['requested_salary'], $input['status'], $input['actions']);
            
            if ($stmt->execute()) {
                sendJsonResponse(['success' => true, 'id' => $randomId]);
            } else {
                sendJsonResponse(['error' => 'Failed to create salary request', 'details' => $conn->error], 500);
            }
            break;

        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);

            // Ensure status is valid
            $allowed = ['Pending','Approved','Declined'];
            $status = in_array($input['status'], $allowed) ? $input['status'] : 'Pending';

            $id = intval($input['id']);

            $stmt = $conn->prepare("UPDATE employeesalaryrequests SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $id);

            if ($stmt->execute()) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to update salary request', 'details' => $conn->error], 500);
            }
            break;

        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id']);
            
            if ($conn->query("DELETE FROM employeesalaryrequests WHERE id = $id")) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to delete salary request', 'details' => $conn->error], 500);
            }
            break;
    }
}

// Deleted records
function handleDeletedRecords($conn, $method) {
    switch ($method) {
        case 'GET':
            $result = $conn->query("SELECT * FROM deletedrecords ORDER BY id DESC");
            $records = [];
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
            sendJsonResponse($records);
            break;

    case 'POST': // Restore deleted record
        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? intval($input['id']) : 0;

        if ($id <= 0) {
            sendJsonResponse(['error' => 'Invalid ID'], 400);
            break;
        }

    // Fetch the record from deletedrecords
    $stmtSelect = $conn->prepare("SELECT * FROM deletedrecords WHERE id = ?");
    $stmtSelect->bind_param("i", $id);
    $stmtSelect->execute();
    $result = $stmtSelect->get_result();
    $record = $result->fetch_assoc();

    if (!$record) {
        sendJsonResponse(['error' => 'Deleted record not found'], 404);
        break;
    }

    // Check if the ID already exists in activerecords
    $stmtCheck = $conn->prepare("SELECT id FROM activerecords WHERE id = ?");
    $stmtCheck->bind_param("i", $record['id']);
    $stmtCheck->execute();
    $exists = $stmtCheck->get_result()->num_rows > 0;

    if ($exists) {
        // Let MySQL auto-assign a new ID
        $stmtInsert = $conn->prepare("INSERT INTO activerecords (name, position, work_date, time_in, time_out, earnings) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtInsert->bind_param(
            "sssssd",
            $record['name'],
            $record['position'],
            $record['work_date'],
            $record['time_in'],
            $record['time_out'],
            $record['earnings']
        );
    } else {
        // Insert with old ID
        $stmtInsert = $conn->prepare("INSERT INTO activerecords (id, name, position, work_date, time_in, time_out, earnings) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtInsert->bind_param(
            "isssssd",
            $record['id'],
            $record['name'],
            $record['position'],
            $record['work_date'],
            $record['time_in'],
            $record['time_out'],
            $record['earnings']
        );
    }

    // Execute insert
    if ($stmtInsert->execute()) {
        // Delete from deletedrecords only if insert succeeded
        $stmtDelete = $conn->prepare("DELETE FROM deletedrecords WHERE id = ?");
        $stmtDelete->bind_param("i", $id);
        $stmtDelete->execute();

        sendJsonResponse(['success' => true]);
    } else {
        sendJsonResponse(['error' => 'Failed to restore record'], 500);
    }

    break;


        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id']);
            
            if ($conn->query("DELETE FROM deletedrecords WHERE id = $id")) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to permanently delete record', 'details' => $conn->error], 500);
            }
            break;
    }
}

// Payslips
function handlePayslips($conn, $method) {
    switch ($method) {
        case 'GET':
            // Get payslip history, optionally filtered by employee name
            $employeeName = isset($_GET['employee']) ? $_GET['employee'] : null;
            
            if ($employeeName) {
                $stmt = $conn->prepare("SELECT * FROM payslip_history WHERE employee_name = ? ORDER BY date_generated DESC");
                $stmt->bind_param("s", $employeeName);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query("SELECT * FROM payslip_history ORDER BY date_generated DESC");
            }
            
            $payslips = [];
            while ($row = $result->fetch_assoc()) {
                $payslips[] = $row;
            }
            sendJsonResponse($payslips);
            break;

        case 'POST':
            // Generate/create a new payslip by counting tasks and summing earnings
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['employee_name'])) {
                sendJsonResponse(['error' => 'Employee name is required'], 400);
                break;
            }
            
            $employeeName = $input['employee_name'];
            
            // Get employee data by counting tasks and summing earnings from activerecords
            $stmt = $conn->prepare("
                SELECT 
                    name, 
                    position, 
                    COUNT(*) as tasks_completed,
                    SUM(earnings) as total_earnings
                FROM activerecords 
                WHERE name = ? 
                GROUP BY name, position
            ");
            $stmt->bind_param("s", $employeeName);
            $stmt->execute();
            $result = $stmt->get_result();
            $employeeData = $result->fetch_assoc();
            
            if (!$employeeData) {
                sendJsonResponse(['error' => 'No active records found for this employee'], 404);
                break;
            }
            
            // Insert into payslip_history (without tasks_completed if column doesn't exist)
            $stmtInsert = $conn->prepare("INSERT INTO payslip_history (employee_name, position, earnings, date_generated) VALUES (?, ?, ?, NOW())");
            $stmtInsert->bind_param("ssd", $employeeData['name'], $employeeData['position'], $employeeData['total_earnings']);
            
            if ($stmtInsert->execute()) {
                sendJsonResponse([
                    'success' => true, 
                    'id' => $conn->insert_id,
                    'payslip_data' => [
                        'employee_name' => $employeeData['name'],
                        'position' => $employeeData['position'],
                        'total_earnings' => $employeeData['total_earnings'],
                        'tasks_completed' => $employeeData['tasks_completed']
                    ]
                ]);
            } else {
                sendJsonResponse(['error' => 'Failed to create payslip record', 'details' => $conn->error], 500);
            }
            break;

        case 'PUT':
            // Update payslip record (if needed)
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id']);

            $stmt = $conn->prepare("UPDATE payslip_history SET employee_name = ?, position = ?, earnings = ?, tasks_completed = ? WHERE id = ?");
            $stmt->bind_param("ssdii", $input['employee_name'], $input['position'], $input['earnings'], $input['tasks_completed'], $id);

            if ($stmt->execute()) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to update payslip record', 'details' => $conn->error], 500);
            }
            break;

        case 'DELETE':
            // Delete payslip record(s)
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (isset($input['employee_name'])) {
                // Delete all payslips for a specific employee
                $stmt = $conn->prepare("DELETE FROM payslip_history WHERE employee_name = ?");
                $stmt->bind_param("s", $input['employee_name']);
                
                if ($stmt->execute()) {
                    sendJsonResponse(['success' => true, 'message' => 'All payslips deleted for employee']);
                } else {
                    sendJsonResponse(['error' => 'Failed to delete payslips', 'details' => $conn->error], 500);
                }
            } else if (isset($input['id'])) {
                // Delete specific payslip by ID
                $id = intval($input['id']);
                
                if ($conn->query("DELETE FROM payslip_history WHERE id = $id")) {
                    sendJsonResponse(['success' => true, 'message' => 'Payslip deleted']);
                } else {
                    sendJsonResponse(['error' => 'Failed to delete payslip', 'details' => $conn->error], 500);
                }
            } else {
                sendJsonResponse(['error' => 'Either employee_name or id must be provided'], 400);
            }
            break;
    }
}

// Direct payslip insertion endpoint
function handleAddPayslip($conn, $method) {
    if ($method !== 'POST') {
        sendJsonResponse(['error' => 'Only POST method allowed'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['employee_name'], $input['position'], $input['earnings'])) {
        sendJsonResponse(['error' => 'Missing required fields: employee_name, position, earnings'], 400);
    }

    // Validate earnings is a valid number
    $earnings = floatval($input['earnings']);
    if ($earnings < 0) {
        sendJsonResponse(['error' => 'Earnings must be a positive number'], 400);
    }

    // Generate unique random 5-digit ID
    do {
        $randomId = rand(10000, 99999);
        $check = $conn->prepare("SELECT id FROM payslip_history WHERE id = ?");
        $check->bind_param("i", $randomId);
        $check->execute();
        $result = $check->get_result();
    } while ($result && $result->num_rows > 0);

    // Get or create employee_id (optional field)
    $employeeId = isset($input['employee_id']) ? intval($input['employee_id']) : null;

    // Use provided date or current timestamp
    $dateGenerated = isset($input['date_generated']) && !empty($input['date_generated']) 
        ? $input['date_generated'] . ' ' . date('H:i:s') 
        : date('Y-m-d H:i:s');

    // Insert directly into payslip_history with custom ID
    if ($employeeId) {
        $stmt = $conn->prepare("INSERT INTO payslip_history (id, employee_name, position, earnings, date_generated, employee_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdsi", $randomId, $input['employee_name'], $input['position'], $earnings, $dateGenerated, $employeeId);
    } else {
        $stmt = $conn->prepare("INSERT INTO payslip_history (id, employee_name, position, earnings, date_generated) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issds", $randomId, $input['employee_name'], $input['position'], $earnings, $dateGenerated);
    }
    
    if ($stmt->execute()) {
        sendJsonResponse([
            'success' => true, 
            'id' => $randomId,
            'payslip_data' => [
                'employee_name' => $input['employee_name'],
                'position' => $input['position'],
                'earnings' => $earnings,
                'date_generated' => $dateGenerated,
                'employee_id' => $employeeId
            ]
        ]);
    } else {
        sendJsonResponse(['error' => 'Failed to create payslip record', 'details' => $conn->error], 500);
    }
}

// Leave requests
function handleLeaveRequests($conn, $method) {
    switch ($method) {
        case 'GET':
            // Get all leave requests, optionally filtered
            $employeeName = isset($_GET['employee']) ? $_GET['employee'] : null;
            $status = isset($_GET['status']) ? $_GET['status'] : null;
            
            $query = "SELECT * FROM leave_requests";
            $conditions = [];
            $params = [];
            $types = "";
            
            if ($employeeName) {
                $conditions[] = "employee_name = ?";
                $params[] = $employeeName;
                $types .= "s";
            }
            
            if ($status) {
                $conditions[] = "status = ?";
                $params[] = $status;
                $types .= "s";
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $query .= " ORDER BY date_requested DESC";
            
            if (!empty($params)) {
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            $requests = [];
            while ($row = $result->fetch_assoc()) {
                $requests[] = $row;
            }
            sendJsonResponse($requests);
            break;

        case 'POST':
            // Create new leave request
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            if (!isset($input['employee_name'], $input['leave_type'], $input['start_date'], $input['end_date'])) {
                sendJsonResponse(['error' => 'Missing required fields: employee_name, leave_type, start_date, end_date'], 400);
                break;
            }

            // Validate leave type
            $validLeaveTypes = ['sick_leave', 'vacation_leave', 'personal_leave', 'emergency_leave', 'maternity_leave', 'paternity_leave'];
            if (!in_array($input['leave_type'], $validLeaveTypes)) {
                sendJsonResponse(['error' => 'Invalid leave type'], 400);
                break;
            }

            // Validate dates
            $startDate = new DateTime($input['start_date']);
            $endDate = new DateTime($input['end_date']);
            $today = new DateTime();
            $today->setTime(0, 0, 0);

            if ($startDate < $today) {
                sendJsonResponse(['error' => 'Start date cannot be in the past'], 400);
                break;
            }

            if ($endDate < $startDate) {
                sendJsonResponse(['error' => 'End date cannot be before start date'], 400);
                break;
            }

            // Check for reasonable duration (max 1 year)
            $interval = $startDate->diff($endDate);
            if ($interval->days > 365) {
                sendJsonResponse(['error' => 'Leave duration cannot exceed 1 year'], 400);
                break;
            }

            // Generate unique random 5-digit ID
            do {
                $randomId = rand(10000, 99999);
                $check = $conn->prepare("SELECT id FROM leave_requests WHERE id = ?");
                $check->bind_param("i", $randomId);
                $check->execute();
                $result = $check->get_result();
            } while ($result && $result->num_rows > 0);

            // Try to find employee_id from activerecords
            $employeeId = null;
            $stmt = $conn->prepare("SELECT id FROM activerecords WHERE name = ? LIMIT 1");
            $stmt->bind_param("s", $input['employee_name']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $employeeId = $row['id'];
            }

            // Insert leave request
            $stmt = $conn->prepare("
                INSERT INTO leave_requests 
                (id, employee_id, employee_name, leave_type, start_date, end_date, reason, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $status = isset($input['status']) ? $input['status'] : 'pending';
            $reason = isset($input['reason']) ? $input['reason'] : null;
            
            $stmt->bind_param(
                "iissssss", 
                $randomId, 
                $employeeId, 
                $input['employee_name'], 
                $input['leave_type'], 
                $input['start_date'], 
                $input['end_date'], 
                $reason, 
                $status
            );
            
            if ($stmt->execute()) {
                sendJsonResponse([
                    'success' => true, 
                    'id' => $randomId,
                    'leave_request' => [
                        'id' => $randomId,
                        'employee_name' => $input['employee_name'],
                        'leave_type' => $input['leave_type'],
                        'start_date' => $input['start_date'],
                        'end_date' => $input['end_date'],
                        'reason' => $reason,
                        'status' => $status
                    ]
                ]);
            } else {
                sendJsonResponse(['error' => 'Failed to create leave request', 'details' => $conn->error], 500);
            }
            break;

        case 'PUT':
            // Update leave request (mainly for status changes)
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['id'])) {
                sendJsonResponse(['error' => 'Leave request ID is required'], 400);
                break;
            }

            $id = intval($input['id']);
            $updates = [];
            $params = [];
            $types = "";

            // Build dynamic update query
            if (isset($input['status'])) {
                $validStatuses = ['pending', 'approved', 'rejected'];
                if (!in_array($input['status'], $validStatuses)) {
                    sendJsonResponse(['error' => 'Invalid status'], 400);
                    break;
                }
                $updates[] = "status = ?";
                $params[] = $input['status'];
                $types .= "s";
            }

            if (isset($input['reason'])) {
                $updates[] = "reason = ?";
                $params[] = $input['reason'];
                $types .= "s";
            }

            if (empty($updates)) {
                sendJsonResponse(['error' => 'No valid fields to update'], 400);
                break;
            }

            // Add updated timestamp
            $updates[] = "date_updated = CURRENT_TIMESTAMP";
            
            // Add ID parameter
            $params[] = $id;
            $types .= "i";

            $query = "UPDATE leave_requests SET " . implode(", ", $updates) . " WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to update leave request', 'details' => $conn->error], 500);
            }
            break;

        case 'DELETE':
            // Delete leave request
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id'])) {
                sendJsonResponse(['error' => 'Leave request ID is required'], 400);
                break;
            }
            
            $id = intval($input['id']);
            
            if ($conn->query("DELETE FROM leave_requests WHERE id = $id")) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to delete leave request', 'details' => $conn->error], 500);
            }
            break;
    }
}

// Employee evaluations
function handleEvaluations($conn, $method) {
    switch ($method) {
        case 'GET':
            // Get all evaluations, optionally filtered
            $employeeName = isset($_GET['employee']) ? $_GET['employee'] : null;
            $evaluationPeriod = isset($_GET['period']) ? $_GET['period'] : null;
            $status = isset($_GET['status']) ? $_GET['status'] : null;
            
            $query = "SELECT * FROM employee_evaluations";
            $conditions = [];
            $params = [];
            $types = "";
            
            if ($employeeName) {
                $conditions[] = "employee_name = ?";
                $params[] = $employeeName;
                $types .= "s";
            }
            
            if ($evaluationPeriod) {
                $conditions[] = "evaluation_period = ?";
                $params[] = $evaluationPeriod;
                $types .= "s";
            }
            
            if ($status) {
                $conditions[] = "status = ?";
                $params[] = $status;
                $types .= "s";
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $query .= " ORDER BY date_created DESC";
            
            if (!empty($params)) {
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            $evaluations = [];
            while ($row = $result->fetch_assoc()) {
                $evaluations[] = $row;
            }
            sendJsonResponse($evaluations);
            break;

        case 'POST':
            // Create new evaluation
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            $requiredFields = ['employee_name', 'evaluator_name', 'evaluation_period', 'technical_skills', 'communication', 'teamwork', 'reliability', 'problem_solving'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field])) {
                    sendJsonResponse(['error' => "Missing required field: $field"], 400);
                    return;
                }
            }

            // Validate rating values (1-5)
            $ratingFields = ['technical_skills', 'communication', 'teamwork', 'reliability', 'problem_solving'];
            foreach ($ratingFields as $field) {
                $value = intval($input[$field]);
                if ($value < 1 || $value > 5) {
                    sendJsonResponse(['error' => "$field must be between 1 and 5"], 400);
                    return;
                }
            }

            // Validate overall score if provided
            if (isset($input['overall_score'])) {
                $score = floatval($input['overall_score']);
                if ($score < 1.00 || $score > 5.00) {
                    sendJsonResponse(['error' => 'Overall score must be between 1.00 and 5.00'], 400);
                    return;
                }
            }

            // Generate unique random 5-digit ID
            do {
                $randomId = rand(10000, 99999);
                $check = $conn->prepare("SELECT id FROM employee_evaluations WHERE id = ?");
                $check->bind_param("i", $randomId);
                $check->execute();
                $result = $check->get_result();
            } while ($result && $result->num_rows > 0);

            // Try to get employee_id from request first, then lookup by name as fallback
            $employeeId = null;
            
            // Check if employee_id was provided directly in the request
            if (isset($input['employee_id']) && !empty($input['employee_id'])) {
                $employeeId = intval($input['employee_id']);
                
                // Verify this employee_id exists in activerecords
                $stmt = $conn->prepare("SELECT id FROM activerecords WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $employeeId);
                $stmt->execute();
                $result = $stmt->get_result();
                if (!$result || $result->num_rows === 0) {
                    $employeeId = null; // Invalid employee_id provided
                }
            }
            
            // If no valid employee_id from request, try to find by name
            if ($employeeId === null) {
                $stmt = $conn->prepare("SELECT id FROM activerecords WHERE name = ? LIMIT 1");
                $stmt->bind_param("s", $input['employee_name']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $employeeId = $row['id'];
                }
            }

            // Insert evaluation
            if ($employeeId !== null) {
                // Prepare variables for bind_param (must be variables, not expressions)
                $technicalSkills = intval($input['technical_skills']);
                $communication = intval($input['communication']);
                $teamwork = intval($input['teamwork']);
                $reliability = intval($input['reliability']);
                $problemSolving = intval($input['problem_solving']);
                $overallScore = floatval($input['overall_score']);
                $strengths = isset($input['strengths']) ? $input['strengths'] : null;
                $areasForImprovement = isset($input['areas_for_improvement']) ? $input['areas_for_improvement'] : null;
                $goalsNextPeriod = isset($input['goals_next_period']) ? $input['goals_next_period'] : null;
                $additionalComments = isset($input['additional_comments']) ? $input['additional_comments'] : null;
                
                $stmt = $conn->prepare("
                    INSERT INTO employee_evaluations 
                    (id, employee_id, employee_name, evaluator_name, evaluation_period, 
                     technical_skills, communication, teamwork, reliability, problem_solving, 
                     overall_score, strengths, areas_for_improvement, goals_next_period, 
                     additional_comments) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->bind_param(
                    "iisssiiiiidssss", 
                    $randomId,
                    $employeeId,
                    $input['employee_name'],
                    $input['evaluator_name'],
                    $input['evaluation_period'],
                    $technicalSkills,
                    $communication,
                    $teamwork,
                    $reliability,
                    $problemSolving,
                    $overallScore,
                    $strengths,
                    $areasForImprovement,
                    $goalsNextPeriod,
                    $additionalComments
                );
            } else {
                // Prepare variables for bind_param (must be variables, not expressions)
                $technicalSkills = intval($input['technical_skills']);
                $communication = intval($input['communication']);
                $teamwork = intval($input['teamwork']);
                $reliability = intval($input['reliability']);
                $problemSolving = intval($input['problem_solving']);
                $overallScore = floatval($input['overall_score']);
                $strengths = isset($input['strengths']) ? $input['strengths'] : null;
                $areasForImprovement = isset($input['areas_for_improvement']) ? $input['areas_for_improvement'] : null;
                $goalsNextPeriod = isset($input['goals_next_period']) ? $input['goals_next_period'] : null;
                $additionalComments = isset($input['additional_comments']) ? $input['additional_comments'] : null;
                
                $stmt = $conn->prepare("
                    INSERT INTO employee_evaluations 
                    (id, employee_name, evaluator_name, evaluation_period, 
                     technical_skills, communication, teamwork, reliability, problem_solving, 
                     overall_score, strengths, areas_for_improvement, goals_next_period, 
                     additional_comments) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->bind_param(
                    "isssiiiiidssss", 
                    $randomId,
                    $input['employee_name'],
                    $input['evaluator_name'],
                    $input['evaluation_period'],
                    $technicalSkills,
                    $communication,
                    $teamwork,
                    $reliability,
                    $problemSolving,
                    $overallScore,
                    $strengths,
                    $areasForImprovement,
                    $goalsNextPeriod,
                    $additionalComments
                );
            }
            
            if ($stmt->execute()) {
                sendJsonResponse([
                    'success' => true, 
                    'id' => $randomId,
                    'evaluation' => [
                        'id' => $randomId,
                        'employee_name' => $input['employee_name'],
                        'evaluator_name' => $input['evaluator_name'],
                        'evaluation_period' => $input['evaluation_period'],
                        'overall_score' => floatval($input['overall_score'])
                    ]
                ]);
            } else {
                sendJsonResponse(['error' => 'Failed to create evaluation', 'details' => $conn->error], 500);
            }
            break;

        case 'PUT':
            // Update evaluation
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['id'])) {
                sendJsonResponse(['error' => 'Evaluation ID is required'], 400);
                return;
            }

            $id = intval($input['id']);
            $updates = [];
            $params = [];
            $types = "";

            // Build dynamic update query
            if (isset($input['status'])) {
                $validStatuses = ['draft', 'completed', 'acknowledged'];
                if (!in_array($input['status'], $validStatuses)) {
                    sendJsonResponse(['error' => 'Invalid status'], 400);
                    return;
                }
                $updates[] = "status = ?";
                $params[] = $input['status'];
                $types .= "s";
                
                // Set date_completed if status is being set to completed
                if ($input['status'] === 'completed') {
                    $updates[] = "date_completed = CURRENT_TIMESTAMP";
                }
            }

            // Allow updating text fields
            $textFields = ['strengths', 'areas_for_improvement', 'goals_next_period', 'additional_comments'];
            foreach ($textFields as $field) {
                if (isset($input[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $input[$field];
                    $types .= "s";
                }
            }

            // Allow updating ratings
            $ratingFields = ['technical_skills', 'communication', 'teamwork', 'reliability', 'problem_solving'];
            foreach ($ratingFields as $field) {
                if (isset($input[$field])) {
                    $value = intval($input[$field]);
                    if ($value < 1 || $value > 5) {
                        sendJsonResponse(['error' => "$field must be between 1 and 5"], 400);
                        return;
                    }
                    $updates[] = "$field = ?";
                    $params[] = $value;
                    $types .= "i";
                }
            }

            // Update overall score if provided
            if (isset($input['overall_score'])) {
                $score = floatval($input['overall_score']);
                if ($score < 1.00 || $score > 5.00) {
                    sendJsonResponse(['error' => 'Overall score must be between 1.00 and 5.00'], 400);
                    return;
                }
                $updates[] = "overall_score = ?";
                $params[] = $score;
                $types .= "d";
            }

            if (empty($updates)) {
                sendJsonResponse(['error' => 'No valid fields to update'], 400);
                return;
            }

            // Add ID parameter
            $params[] = $id;
            $types .= "i";

            $query = "UPDATE employee_evaluations SET " . implode(", ", $updates) . " WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to update evaluation', 'details' => $conn->error], 500);
            }
            break;

        case 'DELETE':
            // Delete evaluation
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id'])) {
                sendJsonResponse(['error' => 'Evaluation ID is required'], 400);
                return;
            }
            
            $id = intval($input['id']);
            
            if ($conn->query("DELETE FROM employee_evaluations WHERE id = $id")) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to delete evaluation', 'details' => $conn->error], 500);
            }
            break;
    }
}

// Attendance tracking
function handleAttendance($conn, $method) {
    switch ($method) {
        case 'GET':
            // Get attendance records, optionally filtered by date
            $date = isset($_GET['date']) ? $_GET['date'] : null;
            $employeeId = isset($_GET['employee_id']) ? $_GET['employee_id'] : null;
            
            $query = "SELECT * FROM attendance_records";
            $conditions = [];
            $params = [];
            $types = "";
            
            if ($date) {
                $conditions[] = "attendance_date = ?";
                $params[] = $date;
                $types .= "s";
            }
            
            if ($employeeId) {
                $conditions[] = "employee_id = ?";
                $params[] = $employeeId;
                $types .= "i";
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $query .= " ORDER BY attendance_date DESC, attendance_time DESC";
            
            if (!empty($params)) {
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            $records = [];
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
            sendJsonResponse($records);
            break;

        case 'POST':
            // Record new attendance
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            if (!isset($input['employee_name'], $input['employee_id'], $input['attendance_date'], $input['attendance_type'], $input['attendance_time'])) {
                sendJsonResponse(['error' => 'Missing required fields: employee_name, employee_id, attendance_date, attendance_type, attendance_time'], 400);
                break;
            }

            // Validate attendance type
            $validTypes = ['check_in', 'check_out'];
            if (!in_array($input['attendance_type'], $validTypes)) {
                sendJsonResponse(['error' => 'Invalid attendance type. Must be: check_in or check_out'], 400);
                break;
            }

            // Validate employee_id
            $employeeId = intval($input['employee_id']);
            if ($employeeId < 10000 || $employeeId > 99999) {
                sendJsonResponse(['error' => 'Employee ID must be a 5-digit number'], 400);
                break;
            }

            // Validate employee exists in activerecords
            $stmt = $conn->prepare("SELECT name FROM activerecords WHERE id = ?");
            $stmt->bind_param("i", $employeeId);
            $stmt->execute();
            $result = $stmt->get_result();
            if (!$result || $result->num_rows === 0) {
                sendJsonResponse(['error' => 'Employee ID not found in active records'], 404);
                break;
            }

            // Generate unique random 5-digit ID
            do {
                $randomId = rand(10000, 99999);
                $check = $conn->prepare("SELECT id FROM attendance_records WHERE id = ?");
                $check->bind_param("i", $randomId);
                $check->execute();
                $result = $check->get_result();
            } while ($result && $result->num_rows > 0);

            // Prepare variables for bind_param
            $notes = isset($input['notes']) ? $input['notes'] : null;
            
            // Insert attendance record
            $stmt = $conn->prepare("
                INSERT INTO attendance_records 
                (id, employee_id, employee_name, attendance_date, attendance_time, attendance_type, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "iisssss", 
                $randomId,
                $employeeId,
                $input['employee_name'],
                $input['attendance_date'],
                $input['attendance_time'],
                $input['attendance_type'],
                $notes
            );
            
            if ($stmt->execute()) {
                sendJsonResponse([
                    'success' => true, 
                    'id' => $randomId,
                    'attendance' => [
                        'id' => $randomId,
                        'employee_name' => $input['employee_name'],
                        'employee_id' => $employeeId,
                        'attendance_date' => $input['attendance_date'],
                        'attendance_time' => $input['attendance_time'],
                        'attendance_type' => $input['attendance_type'],
                        'notes' => $notes
                    ]
                ]);
            } else {
                sendJsonResponse(['error' => 'Failed to record attendance', 'details' => $conn->error], 500);
            }
            break;

        case 'DELETE':
            // Delete attendance record
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id'])) {
                sendJsonResponse(['error' => 'Attendance record ID is required'], 400);
                break;
            }
            
            $id = intval($input['id']);
            
            if ($conn->query("DELETE FROM attendance_records WHERE id = $id")) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to delete attendance record', 'details' => $conn->error], 500);
            }
            break;
    }
}

// Budget Planning
function handleBudget($conn, $method) {
    switch ($method) {
        case 'GET':
            // Get all budget records, optionally filtered by fiscal year or department
            $fiscalYear = isset($_GET['fiscal_year']) ? $_GET['fiscal_year'] : null;
            $department = isset($_GET['department']) ? $_GET['department'] : null;
            
            $query = "SELECT * FROM budget";
            $conditions = [];
            $params = [];
            $types = "";
            
            if ($fiscalYear) {
                $conditions[] = "fiscal_year = ?";
                $params[] = $fiscalYear;
                $types .= "s";
            }
            
            if ($department) {
                $conditions[] = "department = ?";
                $params[] = $department;
                $types .= "s";
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $query .= " ORDER BY fiscal_year DESC, department ASC";
            
            if (!empty($params)) {
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            $budgets = [];
            while ($row = $result->fetch_assoc()) {
                $budgets[] = $row;
            }
            sendJsonResponse($budgets);
            break;

        case 'POST':
            // Create new budget record
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            if (!isset($input['department'], $input['allocated_amount'], $input['fiscal_year'])) {
                sendJsonResponse(['error' => 'Missing required fields: department, allocated_amount, fiscal_year'], 400);
                break;
            }

            // Validate amounts
            $allocatedAmount = floatval($input['allocated_amount']);
            if ($allocatedAmount < 0) {
                sendJsonResponse(['error' => 'Allocated amount must be a positive number'], 400);
                break;
            }

            $spentAmount = isset($input['spent_amount']) ? floatval($input['spent_amount']) : 0.00;
            if ($spentAmount < 0) {
                sendJsonResponse(['error' => 'Spent amount must be a positive number'], 400);
                break;
            }

            if ($spentAmount > $allocatedAmount) {
                sendJsonResponse(['error' => 'Spent amount cannot exceed allocated amount'], 400);
                break;
            }

            // Validate fiscal year format (simple validation)
            if (!preg_match('/^\d{4}$/', $input['fiscal_year'])) {
                sendJsonResponse(['error' => 'Fiscal year must be a 4-digit year (e.g., 2025)'], 400);
                break;
            }

            // Check for duplicate department + fiscal year
            $stmt = $conn->prepare("SELECT id FROM budget WHERE department = ? AND fiscal_year = ?");
            $stmt->bind_param("ss", $input['department'], $input['fiscal_year']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                sendJsonResponse(['error' => 'Budget record already exists for this department and fiscal year'], 409);
                break;
            }

            // Prepare variables for bind_param
            $notes = isset($input['notes']) ? $input['notes'] : null;
            
            // Insert budget record
            $stmt = $conn->prepare("
                INSERT INTO budget 
                (department, allocated_amount, spent_amount, fiscal_year, notes) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "sddss", 
                $input['department'],
                $allocatedAmount,
                $spentAmount,
                $input['fiscal_year'],
                $notes
            );
            
            if ($stmt->execute()) {
                sendJsonResponse([
                    'success' => true, 
                    'id' => $conn->insert_id,
                    'budget' => [
                        'id' => $conn->insert_id,
                        'department' => $input['department'],
                        'allocated_amount' => $allocatedAmount,
                        'spent_amount' => $spentAmount,
                        'fiscal_year' => $input['fiscal_year'],
                        'remaining_amount' => $allocatedAmount - $spentAmount,
                        'percentage_spent' => $allocatedAmount > 0 ? ($spentAmount / $allocatedAmount * 100) : 0,
                        'notes' => $notes
                    ]
                ]);
            } else {
                sendJsonResponse(['error' => 'Failed to create budget record', 'details' => $conn->error], 500);
            }
            break;

        case 'PUT':
            // Update budget record
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['id'])) {
                sendJsonResponse(['error' => 'Budget record ID is required'], 400);
                break;
            }

            $id = intval($input['id']);
            $updates = [];
            $params = [];
            $types = "";

            // Build dynamic update query
            if (isset($input['department'])) {
                $updates[] = "department = ?";
                $params[] = $input['department'];
                $types .= "s";
            }

            if (isset($input['allocated_amount'])) {
                $amount = floatval($input['allocated_amount']);
                if ($amount < 0) {
                    sendJsonResponse(['error' => 'Allocated amount must be a positive number'], 400);
                    break;
                }
                $updates[] = "allocated_amount = ?";
                $params[] = $amount;
                $types .= "d";
            }

            if (isset($input['spent_amount'])) {
                $amount = floatval($input['spent_amount']);
                if ($amount < 0) {
                    sendJsonResponse(['error' => 'Spent amount must be a positive number'], 400);
                    break;
                }
                $updates[] = "spent_amount = ?";
                $params[] = $amount;
                $types .= "d";
            }

            if (isset($input['fiscal_year'])) {
                if (!preg_match('/^\d{4}$/', $input['fiscal_year'])) {
                    sendJsonResponse(['error' => 'Fiscal year must be a 4-digit year (e.g., 2025)'], 400);
                    break;
                }
                $updates[] = "fiscal_year = ?";
                $params[] = $input['fiscal_year'];
                $types .= "s";
            }

            if (isset($input['notes'])) {
                $updates[] = "notes = ?";
                $params[] = $input['notes'];
                $types .= "s";
            }

            if (empty($updates)) {
                sendJsonResponse(['error' => 'No valid fields to update'], 400);
                break;
            }

            // Add ID parameter
            $params[] = $id;
            $types .= "i";

            $query = "UPDATE budget SET " . implode(", ", $updates) . " WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to update budget record', 'details' => $conn->error], 500);
            }
            break;

        case 'DELETE':
            // Delete budget record
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id'])) {
                sendJsonResponse(['error' => 'Budget record ID is required'], 400);
                break;
            }
            
            $id = intval($input['id']);
            
            if ($conn->query("DELETE FROM budget WHERE id = $id")) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to delete budget record', 'details' => $conn->error], 500);
            }
            break;
    }
}

// Overtime requests
function handleOvertimeRequests($conn, $method) {
    switch ($method) {
        case 'GET':
            // Get all overtime requests, optionally filtered
            $employeeId = isset($_GET['employee_id']) ? $_GET['employee_id'] : null;
            $status = isset($_GET['status']) ? $_GET['status'] : null;
            $date = isset($_GET['date']) ? $_GET['date'] : null;
            
            $query = "SELECT * FROM overtime_requests";
            $conditions = [];
            $params = [];
            $types = "";
            
            if ($employeeId) {
                $conditions[] = "employee_id = ?";
                $params[] = $employeeId;
                $types .= "i";
            }
            
            if ($status) {
                $conditions[] = "status = ?";
                $params[] = $status;
                $types .= "s";
            }
            
            if ($date) {
                $conditions[] = "ot_date = ?";
                $params[] = $date;
                $types .= "s";
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $query .= " ORDER BY date_requested DESC";
            
            if (!empty($params)) {
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            $requests = [];
            while ($row = $result->fetch_assoc()) {
                $requests[] = $row;
            }
            sendJsonResponse($requests);
            break;

        case 'POST':
            // Create new overtime request
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            if (!isset($input['employee_id'], $input['ot_date'], $input['hours'])) {
                sendJsonResponse(['error' => 'Missing required fields: employee_id, ot_date, hours'], 400);
                break;
            }

            // Validate employee_id
            $employeeId = intval($input['employee_id']);
            if ($employeeId <= 0) {
                sendJsonResponse(['error' => 'Invalid employee ID'], 400);
                break;
            }

            // Validate hours
            $hours = floatval($input['hours']);
            if ($hours <= 0 || $hours > 24) {
                sendJsonResponse(['error' => 'Hours must be between 0 and 24'], 400);
                break;
            }

            // Validate date
            $otDate = $input['ot_date'];
            if (!strtotime($otDate)) {
                sendJsonResponse(['error' => 'Invalid date format'], 400);
                break;
            }

            // Generate unique random 5-digit ID
            do {
                $randomId = rand(10000, 99999);
                $check = $conn->prepare("SELECT id FROM overtime_requests WHERE id = ?");
                $check->bind_param("i", $randomId);
                $check->execute();
                $result = $check->get_result();
            } while ($result && $result->num_rows > 0);

            // Try to get employee name from activerecords
            $employeeName = null;
            $stmt = $conn->prepare("SELECT name FROM activerecords WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $employeeId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $employeeName = $row['name'];
            }

            // Insert overtime request
            $stmt = $conn->prepare("
                INSERT INTO overtime_requests 
                (id, employee_id, employee_name, ot_date, hours, reason, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $status = isset($input['status']) ? $input['status'] : 'pending';
            $reason = isset($input['reason']) ? $input['reason'] : null;
            
            $stmt->bind_param(
                "iissdss", 
                $randomId, 
                $employeeId, 
                $employeeName, 
                $otDate, 
                $hours, 
                $reason, 
                $status
            );
            
            if ($stmt->execute()) {
                sendJsonResponse([
                    'success' => true, 
                    'id' => $randomId,
                    'overtime_request' => [
                        'id' => $randomId,
                        'employee_id' => $employeeId,
                        'employee_name' => $employeeName,
                        'ot_date' => $otDate,
                        'hours' => $hours,
                        'reason' => $reason,
                        'status' => $status
                    ]
                ]);
            } else {
                sendJsonResponse(['error' => 'Failed to create overtime request', 'details' => $conn->error], 500);
            }
            break;

        case 'PUT':
            // Update overtime request (mainly for status changes)
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['id'])) {
                sendJsonResponse(['error' => 'Overtime request ID is required'], 400);
                break;
            }

            $id = intval($input['id']);
            $updates = [];
            $params = [];
            $types = "";

            // Build dynamic update query
            if (isset($input['status'])) {
                $validStatuses = ['pending', 'approved', 'rejected'];
                if (!in_array($input['status'], $validStatuses)) {
                    sendJsonResponse(['error' => 'Invalid status'], 400);
                    break;
                }
                $updates[] = "status = ?";
                $params[] = $input['status'];
                $types .= "s";
            }

            if (isset($input['hours'])) {
                $hours = floatval($input['hours']);
                if ($hours <= 0 || $hours > 24) {
                    sendJsonResponse(['error' => 'Hours must be between 0 and 24'], 400);
                    break;
                }
                $updates[] = "hours = ?";
                $params[] = $hours;
                $types .= "d";
            }

            if (isset($input['reason'])) {
                $updates[] = "reason = ?";
                $params[] = $input['reason'];
                $types .= "s";
            }

            if (empty($updates)) {
                sendJsonResponse(['error' => 'No valid fields to update'], 400);
                break;
            }

            // Add updated timestamp
            $updates[] = "date_updated = CURRENT_TIMESTAMP";
            
            // Add ID parameter
            $params[] = $id;
            $types .= "i";

            $query = "UPDATE overtime_requests SET " . implode(", ", $updates) . " WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to update overtime request', 'details' => $conn->error], 500);
            }
            break;

        case 'DELETE':
            // Delete overtime request
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id'])) {
                sendJsonResponse(['error' => 'Overtime request ID is required'], 400);
                break;
            }
            
            $id = intval($input['id']);
            
            if ($conn->query("DELETE FROM overtime_requests WHERE id = $id")) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to delete overtime request', 'details' => $conn->error], 500);
            }
            break;
    }
}

// Training Programs
function handleTrainingPrograms($conn, $method) {
    switch ($method) {
        case 'GET':
            // Get all training programs, optionally filtered
            $employeeId = isset($_GET['employee_id']) ? $_GET['employee_id'] : null;
            $status = isset($_GET['status']) ? $_GET['status'] : null;
            $programType = isset($_GET['program_type']) ? $_GET['program_type'] : null;
            
            $query = "SELECT * FROM training_programs";
            $conditions = [];
            $params = [];
            $types = "";
            
            if ($employeeId) {
                $conditions[] = "employee_id = ?";
                $params[] = $employeeId;
                $types .= "i";
            }
            
            if ($status) {
                $conditions[] = "status = ?";
                $params[] = $status;
                $types .= "s";
            }
            
            if ($programType) {
                $conditions[] = "program_type = ?";
                $params[] = $programType;
                $types .= "s";
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $query .= " ORDER BY date_enrolled DESC";
            
            if (!empty($params)) {
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            $trainings = [];
            while ($row = $result->fetch_assoc()) {
                $trainings[] = $row;
            }
            sendJsonResponse($trainings);
            break;

        case 'POST':
            // Create new training program enrollment
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            if (!isset($input['employee_id'], $input['program_name'], $input['start_date'])) {
                sendJsonResponse(['error' => 'Missing required fields: employee_id, program_name, start_date'], 400);
                break;
            }

            // Validate employee_id
            $employeeId = intval($input['employee_id']);
            if ($employeeId < 10000 || $employeeId > 99999) {
                sendJsonResponse(['error' => 'Employee ID must be a 5-digit number'], 400);
                break;
            }

            // Validate employee exists
            $stmt = $conn->prepare("SELECT name FROM activerecords WHERE id = ?");
            $stmt->bind_param("i", $employeeId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if (!$result || $result->num_rows === 0) {
                sendJsonResponse(['error' => 'Employee ID not found in active records'], 404);
                break;
            }
            
            $employee = $result->fetch_assoc();
            $employeeName = $employee['name'];

            // Validate dates
            $startDate = $input['start_date'];
            if (!strtotime($startDate)) {
                sendJsonResponse(['error' => 'Invalid start date format'], 400);
                break;
            }

            if (isset($input['end_date']) && !empty($input['end_date'])) {
                $endDate = $input['end_date'];
                if (!strtotime($endDate)) {
                    sendJsonResponse(['error' => 'Invalid end date format'], 400);
                    break;
                }
                
                if (strtotime($endDate) < strtotime($startDate)) {
                    sendJsonResponse(['error' => 'End date cannot be before start date'], 400);
                    break;
                }
            } else {
                $endDate = null;
            }

            // Generate unique random 5-digit ID
            do {
                $randomId = rand(10000, 99999);
                $check = $conn->prepare("SELECT id FROM training_programs WHERE id = ?");
                $check->bind_param("i", $randomId);
                $check->execute();
                $result = $check->get_result();
            } while ($result && $result->num_rows > 0);

            // Prepare optional fields
            $programType = isset($input['program_type']) ? $input['program_type'] : null;
            $durationHours = isset($input['duration_hours']) ? intval($input['duration_hours']) : null;
            $status = isset($input['status']) ? $input['status'] : 'enrolled';
            $completionPercentage = isset($input['completion_percentage']) ? intval($input['completion_percentage']) : 0;
            $trainerName = isset($input['trainer_name']) ? $input['trainer_name'] : null;
            $location = isset($input['location']) ? $input['location'] : null;
            $cost = isset($input['cost']) ? floatval($input['cost']) : 0.00;
            $certificationObtained = isset($input['certification_obtained']) ? ($input['certification_obtained'] ? 1 : 0) : 0;
            $certificationName = isset($input['certification_name']) ? $input['certification_name'] : null;
            $notes = isset($input['notes']) ? $input['notes'] : null;

            // Validate status
            $validStatuses = ['enrolled', 'ongoing', 'completed', 'cancelled'];
            if (!in_array($status, $validStatuses)) {
                sendJsonResponse(['error' => 'Invalid status. Must be: enrolled, ongoing, completed, or cancelled'], 400);
                break;
            }

            // Validate completion percentage
            if ($completionPercentage < 0 || $completionPercentage > 100) {
                sendJsonResponse(['error' => 'Completion percentage must be between 0 and 100'], 400);
                break;
            }

            // Insert training program
            $stmt = $conn->prepare("
                INSERT INTO training_programs 
                (id, employee_id, employee_name, program_name, program_type, start_date, end_date, 
                 duration_hours, status, completion_percentage, trainer_name, location, cost, 
                 certification_obtained, certification_name, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "iisssssisisdisss", 
                $randomId,
                $employeeId,
                $employeeName,
                $input['program_name'],
                $programType,
                $startDate,
                $endDate,
                $durationHours,
                $status,
                $completionPercentage,
                $trainerName,
                $location,
                $cost,
                $certificationObtained,
                $certificationName,
                $notes
            );
            
            if ($stmt->execute()) {
                sendJsonResponse([
                    'success' => true, 
                    'id' => $randomId,
                    'training_program' => [
                        'id' => $randomId,
                        'employee_id' => $employeeId,
                        'employee_name' => $employeeName,
                        'program_name' => $input['program_name'],
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'status' => $status
                    ]
                ]);
            } else {
                sendJsonResponse(['error' => 'Failed to create training program enrollment', 'details' => $conn->error], 500);
            }
            break;

        case 'PUT':
            // Update training program
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['id'])) {
                sendJsonResponse(['error' => 'Training program ID is required'], 400);
                break;
            }

            $id = intval($input['id']);
            $updates = [];
            $params = [];
            $types = "";

            // Build dynamic update query
            if (isset($input['status'])) {
                $validStatuses = ['enrolled', 'ongoing', 'completed', 'cancelled'];
                if (!in_array($input['status'], $validStatuses)) {
                    sendJsonResponse(['error' => 'Invalid status'], 400);
                    break;
                }
                $updates[] = "status = ?";
                $params[] = $input['status'];
                $types .= "s";
                
                // Set date_completed if status is being set to completed
                if ($input['status'] === 'completed' && !isset($input['date_completed'])) {
                    $updates[] = "date_completed = CURRENT_TIMESTAMP";
                }
            }

            if (isset($input['completion_percentage'])) {
                $percentage = intval($input['completion_percentage']);
                if ($percentage < 0 || $percentage > 100) {
                    sendJsonResponse(['error' => 'Completion percentage must be between 0 and 100'], 400);
                    break;
                }
                $updates[] = "completion_percentage = ?";
                $params[] = $percentage;
                $types .= "i";
            }

            if (isset($input['end_date'])) {
                $updates[] = "end_date = ?";
                $params[] = $input['end_date'];
                $types .= "s";
            }

            if (isset($input['duration_hours'])) {
                $updates[] = "duration_hours = ?";
                $params[] = intval($input['duration_hours']);
                $types .= "i";
            }

            if (isset($input['trainer_name'])) {
                $updates[] = "trainer_name = ?";
                $params[] = $input['trainer_name'];
                $types .= "s";
            }

            if (isset($input['location'])) {
                $updates[] = "location = ?";
                $params[] = $input['location'];
                $types .= "s";
            }

            if (isset($input['cost'])) {
                $updates[] = "cost = ?";
                $params[] = floatval($input['cost']);
                $types .= "d";
            }

            if (isset($input['certification_obtained'])) {
                $updates[] = "certification_obtained = ?";
                $params[] = $input['certification_obtained'] ? 1 : 0;
                $types .= "i";
            }

            if (isset($input['certification_name'])) {
                $updates[] = "certification_name = ?";
                $params[] = $input['certification_name'];
                $types .= "s";
            }

            if (isset($input['notes'])) {
                $updates[] = "notes = ?";
                $params[] = $input['notes'];
                $types .= "s";
            }

            if (isset($input['program_type'])) {
                $updates[] = "program_type = ?";
                $params[] = $input['program_type'];
                $types .= "s";
            }

            if (empty($updates)) {
                sendJsonResponse(['error' => 'No valid fields to update'], 400);
                break;
            }

            // Add ID parameter
            $params[] = $id;
            $types .= "i";

            $query = "UPDATE training_programs SET " . implode(", ", $updates) . " WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to update training program', 'details' => $conn->error], 500);
            }
            break;

        case 'DELETE':
            // Delete training program
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id'])) {
                sendJsonResponse(['error' => 'Training program ID is required'], 400);
                break;
            }
            
            $id = intval($input['id']);
            
            if ($conn->query("DELETE FROM training_programs WHERE id = $id")) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to delete training program', 'details' => $conn->error], 500);
            }
            break;
    }
}

// Disciplinary Actions
function handleDisciplinaryActions($conn, $method) {
    switch ($method) {
        case 'GET':
            // Get all disciplinary actions, optionally filtered
            $employeeId = isset($_GET['employee_id']) ? $_GET['employee_id'] : null;
            $status = isset($_GET['status']) ? $_GET['status'] : null;
            $actionType = isset($_GET['action_type']) ? $_GET['action_type'] : null;
            $severity = isset($_GET['severity']) ? $_GET['severity'] : null;
            
            $query = "SELECT * FROM disciplinary_actions";
            $conditions = [];
            $params = [];
            $types = "";
            
            if ($employeeId) {
                $conditions[] = "employee_id = ?";
                $params[] = $employeeId;
                $types .= "i";
            }
            
            if ($status) {
                $conditions[] = "status = ?";
                $params[] = $status;
                $types .= "s";
            }
            
            if ($actionType) {
                $conditions[] = "action_type = ?";
                $params[] = $actionType;
                $types .= "s";
            }
            
            if ($severity) {
                $conditions[] = "severity = ?";
                $params[] = $severity;
                $types .= "s";
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $query .= " ORDER BY incident_date DESC, date_created DESC";
            
            if (!empty($params)) {
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            $actions = [];
            while ($row = $result->fetch_assoc()) {
                $actions[] = $row;
            }
            sendJsonResponse($actions);
            break;

        case 'POST':
            // Create new disciplinary action
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            $requiredFields = ['employee_name', 'action_type', 'incident_date', 'description', 'action_taken', 'reported_by'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    sendJsonResponse(['error' => "Missing required field: $field"], 400);
                    return;
                }
            }

            // Validate action_type
            $validActionTypes = ['verbal_warning', 'written_warning', 'suspension', 'termination', 'other'];
            if (!in_array($input['action_type'], $validActionTypes)) {
                sendJsonResponse(['error' => 'Invalid action_type. Must be: verbal_warning, written_warning, suspension, termination, or other'], 400);
                return;
            }

            // Validate severity if provided
            $severity = isset($input['severity']) ? $input['severity'] : 'minor';
            $validSeverities = ['minor', 'moderate', 'major', 'critical'];
            if (!in_array($severity, $validSeverities)) {
                sendJsonResponse(['error' => 'Invalid severity. Must be: minor, moderate, major, or critical'], 400);
                return;
            }

            // Validate date
            $incidentDate = $input['incident_date'];
            if (!strtotime($incidentDate)) {
                sendJsonResponse(['error' => 'Invalid incident date format'], 400);
                return;
            }

            // Validate status if provided
            $status = isset($input['status']) ? $input['status'] : 'open';
            $validStatuses = ['open', 'in_progress', 'resolved', 'closed'];
            if (!in_array($status, $validStatuses)) {
                sendJsonResponse(['error' => 'Invalid status. Must be: open, in_progress, resolved, or closed'], 400);
                return;
            }

            // Generate unique random 5-digit ID
            do {
                $randomId = rand(10000, 99999);
                $check = $conn->prepare("SELECT id FROM disciplinary_actions WHERE id = ?");
                $check->bind_param("i", $randomId);
                $check->execute();
                $result = $check->get_result();
            } while ($result && $result->num_rows > 0);

            // Try to get employee_id from activerecords
            $employeeId = null;
            if (isset($input['employee_id']) && !empty($input['employee_id'])) {
                $employeeId = intval($input['employee_id']);
            } else {
                $stmt = $conn->prepare("SELECT id FROM activerecords WHERE name = ? LIMIT 1");
                $stmt->bind_param("s", $input['employee_name']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $employeeId = $row['id'];
                }
            }

            // Prepare optional fields
            $violationType = isset($input['violation_type']) ? $input['violation_type'] : null;
            $witnessNames = isset($input['witness_names']) ? $input['witness_names'] : null;
            $followUpRequired = isset($input['follow_up_required']) ? ($input['follow_up_required'] ? 1 : 0) : 0;
            $followUpDate = isset($input['follow_up_date']) && !empty($input['follow_up_date']) ? $input['follow_up_date'] : null;
            $followUpNotes = isset($input['follow_up_notes']) ? $input['follow_up_notes'] : null;
            $resolutionNotes = isset($input['resolution_notes']) ? $input['resolution_notes'] : null;
            $createdBy = isset($input['created_by']) ? $input['created_by'] : null;

            // Insert disciplinary action
            $stmt = $conn->prepare("
                INSERT INTO disciplinary_actions 
                (id, employee_id, employee_name, action_type, severity, violation_type, 
                 incident_date, description, action_taken, reported_by, witness_names, 
                 follow_up_required, follow_up_date, follow_up_notes, status, 
                 resolution_notes, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "iisssssssssisssss", 
                $randomId,
                $employeeId,
                $input['employee_name'],
                $input['action_type'],
                $severity,
                $violationType,
                $incidentDate,
                $input['description'],
                $input['action_taken'],
                $input['reported_by'],
                $witnessNames,
                $followUpRequired,
                $followUpDate,
                $followUpNotes,
                $status,
                $resolutionNotes,
                $createdBy
            );
            
            if ($stmt->execute()) {
                sendJsonResponse([
                    'success' => true, 
                    'id' => $randomId,
                    'disciplinary_action' => [
                        'id' => $randomId,
                        'employee_id' => $employeeId,
                        'employee_name' => $input['employee_name'],
                        'action_type' => $input['action_type'],
                        'severity' => $severity,
                        'incident_date' => $incidentDate,
                        'status' => $status
                    ]
                ]);
            } else {
                sendJsonResponse(['error' => 'Failed to create disciplinary action', 'details' => $conn->error], 500);
            }
            break;

        case 'PUT':
            // Update disciplinary action
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['id'])) {
                sendJsonResponse(['error' => 'Disciplinary action ID is required'], 400);
                return;
            }

            $id = intval($input['id']);
            $updates = [];
            $params = [];
            $types = "";

            // Build dynamic update query
            if (isset($input['status'])) {
                $validStatuses = ['open', 'in_progress', 'resolved', 'closed'];
                if (!in_array($input['status'], $validStatuses)) {
                    sendJsonResponse(['error' => 'Invalid status'], 400);
                    return;
                }
                $updates[] = "status = ?";
                $params[] = $input['status'];
                $types .= "s";
            }

            if (isset($input['severity'])) {
                $validSeverities = ['minor', 'moderate', 'major', 'critical'];
                if (!in_array($input['severity'], $validSeverities)) {
                    sendJsonResponse(['error' => 'Invalid severity'], 400);
                    return;
                }
                $updates[] = "severity = ?";
                $params[] = $input['severity'];
                $types .= "s";
            }

            if (isset($input['action_type'])) {
                $validActionTypes = ['verbal_warning', 'written_warning', 'suspension', 'termination', 'other'];
                if (!in_array($input['action_type'], $validActionTypes)) {
                    sendJsonResponse(['error' => 'Invalid action_type'], 400);
                    return;
                }
                $updates[] = "action_type = ?";
                $params[] = $input['action_type'];
                $types .= "s";
            }

            // Text fields
            $textFields = ['description', 'action_taken', 'violation_type', 'witness_names', 
                          'follow_up_notes', 'resolution_notes', 'reported_by', 'created_by'];
            foreach ($textFields as $field) {
                if (isset($input[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $input[$field];
                    $types .= "s";
                }
            }

            // Date fields
            if (isset($input['incident_date'])) {
                $updates[] = "incident_date = ?";
                $params[] = $input['incident_date'];
                $types .= "s";
            }

            if (isset($input['follow_up_date'])) {
                $updates[] = "follow_up_date = ?";
                $params[] = $input['follow_up_date'];
                $types .= "s";
            }

            // Boolean field
            if (isset($input['follow_up_required'])) {
                $updates[] = "follow_up_required = ?";
                $params[] = $input['follow_up_required'] ? 1 : 0;
                $types .= "i";
            }

            if (empty($updates)) {
                sendJsonResponse(['error' => 'No valid fields to update'], 400);
                return;
            }

            // Add ID parameter
            $params[] = $id;
            $types .= "i";

            $query = "UPDATE disciplinary_actions SET " . implode(", ", $updates) . " WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to update disciplinary action', 'details' => $conn->error], 500);
            }
            break;

        case 'DELETE':
            // Delete disciplinary action
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id'])) {
                sendJsonResponse(['error' => 'Disciplinary action ID is required'], 400);
                return;
            }
            
            $id = intval($input['id']);
            
            if ($conn->query("DELETE FROM disciplinary_actions WHERE id = $id")) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to delete disciplinary action', 'details' => $conn->error], 500);
            }
            break;
    }
}

// Grievances
function handleGrievances($conn, $method) {
    switch ($method) {
        case 'GET':
            // Get all grievances, optionally filtered
            $employeeId = isset($_GET['employee_id']) ? $_GET['employee_id'] : null;
            $status = isset($_GET['status']) ? $_GET['status'] : null;
            $grievanceType = isset($_GET['grievance_type']) ? $_GET['grievance_type'] : null;
            $priority = isset($_GET['priority']) ? $_GET['priority'] : null;
            $assignedTo = isset($_GET['assigned_to']) ? $_GET['assigned_to'] : null;
            
            $query = "SELECT * FROM grievances";
            $conditions = [];
            $params = [];
            $types = "";
            
            if ($employeeId) {
                $conditions[] = "employee_id = ?";
                $params[] = $employeeId;
                $types .= "i";
            }
            
            if ($status) {
                $conditions[] = "status = ?";
                $params[] = $status;
                $types .= "s";
            }
            
            if ($grievanceType) {
                $conditions[] = "grievance_type = ?";
                $params[] = $grievanceType;
                $types .= "s";
            }
            
            if ($priority) {
                $conditions[] = "priority = ?";
                $params[] = $priority;
                $types .= "s";
            }
            
            if ($assignedTo) {
                $conditions[] = "assigned_to = ?";
                $params[] = $assignedTo;
                $types .= "s";
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $query .= " ORDER BY priority DESC, date_filed DESC";
            
            if (!empty($params)) {
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            $grievances = [];
            while ($row = $result->fetch_assoc()) {
                $grievances[] = $row;
            }
            sendJsonResponse($grievances);
            break;

        case 'POST':
            // Create new grievance
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            $requiredFields = ['employee_name', 'grievance_type', 'subject', 'description', 'date_filed'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    sendJsonResponse(['error' => "Missing required field: $field"], 400);
                    return;
                }
            }

            // Validate grievance_type
            $validGrievanceTypes = ['harassment', 'discrimination', 'workplace_safety', 'compensation', 
                                    'workload', 'management_issue', 'other'];
            if (!in_array($input['grievance_type'], $validGrievanceTypes)) {
                sendJsonResponse(['error' => 'Invalid grievance_type. Must be: harassment, discrimination, workplace_safety, compensation, workload, management_issue, or other'], 400);
                return;
            }

            // Validate priority if provided
            $priority = isset($input['priority']) ? $input['priority'] : 'medium';
            $validPriorities = ['low', 'medium', 'high', 'urgent'];
            if (!in_array($priority, $validPriorities)) {
                sendJsonResponse(['error' => 'Invalid priority. Must be: low, medium, high, or urgent'], 400);
                return;
            }

            // Validate date
            $dateFiled = $input['date_filed'];
            if (!strtotime($dateFiled)) {
                sendJsonResponse(['error' => 'Invalid date_filed format'], 400);
                return;
            }

            // Validate status if provided
            $status = isset($input['status']) ? $input['status'] : 'submitted';
            $validStatuses = ['submitted', 'under_review', 'investigation', 'mediation', 'resolved', 'closed', 'rejected'];
            if (!in_array($status, $validStatuses)) {
                sendJsonResponse(['error' => 'Invalid status. Must be: submitted, under_review, investigation, mediation, resolved, closed, or rejected'], 400);
                return;
            }

            // Generate unique random 5-digit ID
            do {
                $randomId = rand(10000, 99999);
                $check = $conn->prepare("SELECT id FROM grievances WHERE id = ?");
                $check->bind_param("i", $randomId);
                $check->execute();
                $result = $check->get_result();
            } while ($result && $result->num_rows > 0);

            // Try to get employee_id from activerecords
            $employeeId = null;
            if (isset($input['employee_id']) && !empty($input['employee_id'])) {
                $employeeId = intval($input['employee_id']);
            } else if (!isset($input['is_anonymous']) || !$input['is_anonymous']) {
                // Only lookup employee_id if not anonymous
                $stmt = $conn->prepare("SELECT id FROM activerecords WHERE name = ? LIMIT 1");
                $stmt->bind_param("s", $input['employee_name']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $employeeId = $row['id'];
                }
            }

            // Prepare optional fields
            $desiredOutcome = isset($input['desired_outcome']) ? $input['desired_outcome'] : null;
            $againstPerson = isset($input['against_person']) ? $input['against_person'] : null;
            $againstDepartment = isset($input['against_department']) ? $input['against_department'] : null;
            $witnesses = isset($input['witnesses']) ? $input['witnesses'] : null;
            $supportingDocuments = isset($input['supporting_documents']) ? $input['supporting_documents'] : null;
            $assignedTo = isset($input['assigned_to']) ? $input['assigned_to'] : null;
            $investigationNotes = isset($input['investigation_notes']) ? $input['investigation_notes'] : null;
            $resolutionDetails = isset($input['resolution_details']) ? $input['resolution_details'] : null;
            $resolutionDate = isset($input['resolution_date']) && !empty($input['resolution_date']) ? $input['resolution_date'] : null;
            $isAnonymous = isset($input['is_anonymous']) ? ($input['is_anonymous'] ? 1 : 0) : 0;
            $confidential = isset($input['confidential']) ? ($input['confidential'] ? 1 : 0) : 1;

            // Insert grievance
            $stmt = $conn->prepare("
                INSERT INTO grievances 
                (id, employee_id, employee_name, grievance_type, priority, subject, 
                 description, date_filed, desired_outcome, against_person, against_department, 
                 witnesses, supporting_documents, status, assigned_to, investigation_notes, 
                 resolution_details, resolution_date, is_anonymous, confidential) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "iisssssssssssssssii", 
                $randomId,
                $employeeId,
                $input['employee_name'],
                $input['grievance_type'],
                $priority,
                $input['subject'],
                $input['description'],
                $dateFiled,
                $desiredOutcome,
                $againstPerson,
                $againstDepartment,
                $witnesses,
                $supportingDocuments,
                $status,
                $assignedTo,
                $investigationNotes,
                $resolutionDetails,
                $resolutionDate,
                $isAnonymous,
                $confidential
            );
            
            if ($stmt->execute()) {
                sendJsonResponse([
                    'success' => true, 
                    'id' => $randomId,
                    'grievance' => [
                        'id' => $randomId,
                        'employee_id' => $employeeId,
                        'employee_name' => $input['employee_name'],
                        'grievance_type' => $input['grievance_type'],
                        'priority' => $priority,
                        'subject' => $input['subject'],
                        'date_filed' => $dateFiled,
                        'status' => $status,
                        'is_anonymous' => $isAnonymous,
                        'confidential' => $confidential
                    ]
                ]);
            } else {
                sendJsonResponse(['error' => 'Failed to create grievance', 'details' => $conn->error], 500);
            }
            break;

        case 'PUT':
            // Update grievance
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['id'])) {
                sendJsonResponse(['error' => 'Grievance ID is required'], 400);
                return;
            }

            $id = intval($input['id']);
            $updates = [];
            $params = [];
            $types = "";

            // Build dynamic update query
            if (isset($input['status'])) {
                $validStatuses = ['submitted', 'under_review', 'investigation', 'mediation', 'resolved', 'closed', 'rejected'];
                if (!in_array($input['status'], $validStatuses)) {
                    sendJsonResponse(['error' => 'Invalid status'], 400);
                    return;
                }
                $updates[] = "status = ?";
                $params[] = $input['status'];
                $types .= "s";
                
                // Auto-set resolution_date when status is resolved or closed
                if (($input['status'] === 'resolved' || $input['status'] === 'closed') && !isset($input['resolution_date'])) {
                    $updates[] = "resolution_date = CURDATE()";
                }
            }

            if (isset($input['priority'])) {
                $validPriorities = ['low', 'medium', 'high', 'urgent'];
                if (!in_array($input['priority'], $validPriorities)) {
                    sendJsonResponse(['error' => 'Invalid priority'], 400);
                    return;
                }
                $updates[] = "priority = ?";
                $params[] = $input['priority'];
                $types .= "s";
            }

            if (isset($input['grievance_type'])) {
                $validGrievanceTypes = ['harassment', 'discrimination', 'workplace_safety', 'compensation', 
                                        'workload', 'management_issue', 'other'];
                if (!in_array($input['grievance_type'], $validGrievanceTypes)) {
                    sendJsonResponse(['error' => 'Invalid grievance_type'], 400);
                    return;
                }
                $updates[] = "grievance_type = ?";
                $params[] = $input['grievance_type'];
                $types .= "s";
            }

            // Text fields
            $textFields = ['subject', 'description', 'desired_outcome', 'against_person', 
                          'against_department', 'witnesses', 'supporting_documents', 
                          'assigned_to', 'investigation_notes', 'resolution_details'];
            foreach ($textFields as $field) {
                if (isset($input[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $input[$field];
                    $types .= "s";
                }
            }

            // Date fields
            if (isset($input['date_filed'])) {
                $updates[] = "date_filed = ?";
                $params[] = $input['date_filed'];
                $types .= "s";
            }

            if (isset($input['resolution_date'])) {
                $updates[] = "resolution_date = ?";
                $params[] = $input['resolution_date'];
                $types .= "s";
            }

            // Boolean fields
            if (isset($input['is_anonymous'])) {
                $updates[] = "is_anonymous = ?";
                $params[] = $input['is_anonymous'] ? 1 : 0;
                $types .= "i";
            }

            if (isset($input['confidential'])) {
                $updates[] = "confidential = ?";
                $params[] = $input['confidential'] ? 1 : 0;
                $types .= "i";
            }

            if (empty($updates)) {
                sendJsonResponse(['error' => 'No valid fields to update'], 400);
                return;
            }

            // Add ID parameter
            $params[] = $id;
            $types .= "i";

            $query = "UPDATE grievances SET " . implode(", ", $updates) . " WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to update grievance', 'details' => $conn->error], 500);
            }
            break;

        case 'DELETE':
            // Delete grievance
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id'])) {
                sendJsonResponse(['error' => 'Grievance ID is required'], 400);
                return;
            }
            
            $id = intval($input['id']);
            
            if ($conn->query("DELETE FROM grievances WHERE id = $id")) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to delete grievance', 'details' => $conn->error], 500);
            }
            break;
    }
}

// JSON response helper
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
?>
