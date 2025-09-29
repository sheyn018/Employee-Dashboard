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

// If no specific endpoint or just accessing api.php directly, show connection status
if ($endpoint === 'api.php' || $endpoint === '' || empty($endpoint) || $endpoint === basename(__FILE__, '.php')) {
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
    $requiredTables = ['activerecords', 'employeesalaryrequests', 'deletedrecords', 'payslip_history'];
    $tableStatus = [];
    foreach ($requiredTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        $tableStatus[$table] = $result->num_rows > 0 ? 'exists' : 'missing';
    }
    $connectionStatus['required_tables_status'] = $tableStatus;

    sendJsonResponse($connectionStatus);
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
    default:
        sendJsonResponse(['error' => 'Endpoint not found', 'available_endpoints' => ['employees', 'new-employee', 'salary-requests', 'deleted-records', 'payslips', 'add-payslip', 'activerecords', 'employeesalaryrequests', 'deletedrecords', 'payslip-history']], 404);
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

// JSON response helper
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
?>
