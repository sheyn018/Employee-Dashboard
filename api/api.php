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
    $requiredTables = ['activerecords', 'employeesalaryrequests', 'deletedrecords'];
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
    case 'salary-requests':
    case 'employeesalaryrequests':
        handleSalaryRequests($conn, $method);
        break;
    case 'deleted-records':
    case 'deletedrecords':
        handleDeletedRecords($conn, $method);
        break;
    default:
        sendJsonResponse(['error' => 'Endpoint not found', 'available_endpoints' => ['employees', 'salary-requests', 'deleted-records', 'activerecords', 'employeesalaryrequests', 'deletedrecords']], 404);
}

// Employees
function handleEmployees($conn, $method) {
    switch ($method) {
        case 'GET':
            $result = $conn->query("SELECT * FROM activerecords ORDER BY id DESC");
            $employees = [];
            while ($row = $result->fetch_assoc()) {
                $employees[] = $row;
            }
            sendJsonResponse($employees);
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
            $stmt = $conn->prepare("INSERT INTO employeesalaryrequests (employee_name, requested_salary, status, actions) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sdss", $input['employee_name'], $input['requested_salary'], $input['status'], $input['actions']);
            
            if ($stmt->execute()) {
                sendJsonResponse(['success' => true, 'id' => $conn->insert_id]);
            } else {
                sendJsonResponse(['error' => 'Failed to create salary request', 'details' => $conn->error], 500);
            }
            break;

        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE employeesalaryrequests SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $input['status'], $input['id']);
            
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

// JSON response helper
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
?>
