<?php
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
    // Test database connection and show status
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
        $allTables[] = array_values($row)[0]; // Get table name from result
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

// Handle different endpoints
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

// Handle employees endpoint
function handleEmployees($conn, $method) {
    switch ($method) {
        case 'GET':
            $result = $conn->query("SELECT * FROM activerecords ORDER BY record_id DESC");
            $employees = [];
            while ($row = $result->fetch_assoc()) {
                $employees[] = $row;
            }
            sendJsonResponse($employees);
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO activerecords (name, position, date, time_in, time_out, earnings) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssd", $input['name'], $input['position'], $input['date'], $input['time_in'], $input['time_out'], $input['earnings']);
            
            if ($stmt->execute()) {
                sendJsonResponse(['success' => true, 'id' => $conn->insert_id]);
            } else {
                sendJsonResponse(['error' => 'Failed to create employee record'], 500);
            }
            break;
            
        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'];
            
            // First, get the record to move to deletedrecords
            $result = $conn->query("SELECT * FROM activerecords WHERE record_id = $id");
            $employee = $result->fetch_assoc();
            
            if ($employee) {
                // Insert into deletedrecords
                $stmt = $conn->prepare("INSERT INTO deletedrecords (original_id, name, position, work_date, time_in, time_out, earnings) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssssd", $employee['record_id'], $employee['name'], $employee['position'], $employee['work_date'], $employee['time_in'], $employee['time_out'], $employee['earnings']);
                $stmt->execute();
                
                // Delete from activerecords
                $conn->query("DELETE FROM activerecords WHERE record_id = $id");
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Employee not found'], 404);
            }
            break;
    }
}

// Handle salary requests endpoint
function handleSalaryRequests($conn, $method) {
    switch ($method) {
        case 'GET':
            $result = $conn->query("SELECT * FROM employeesalaryrequests ORDER BY request_id DESC");
            $requests = [];
            while ($row = $result->fetch_assoc()) {
                $requests[] = $row;
            }
            sendJsonResponse($requests);
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO employeesalaryrequests (name, salary, status) VALUES (?, ?, ?)");
            $stmt->bind_param("sds", $input['name'], $input['salary'], $input['status']);
            
            if ($stmt->execute()) {
                sendJsonResponse(['success' => true, 'id' => $conn->insert_id]);
            } else {
                sendJsonResponse(['error' => 'Failed to create salary request'], 500);
            }
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE employeesalaryrequests SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $input['status'], $input['id']);
            
            if ($stmt->execute()) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to update salary request'], 500);
            }
            break;
            
        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'];
            
            if ($conn->query("DELETE FROM employeesalaryrequests WHERE id = $id")) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to delete salary request'], 500);
            }
            break;
    }
}

// Handle deleted records endpoint
function handleDeletedRecords($conn, $method) {
    switch ($method) {
        case 'GET':
            $result = $conn->query("SELECT * FROM deletedrecords ORDER BY deleted_id DESC");
            $records = [];
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
            sendJsonResponse($records);
            break;
            
        case 'POST': // Restore record
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'];
            
            // Get the deleted record
            $result = $conn->query("SELECT * FROM deletedrecords WHERE deleted_id = $id");
            $record = $result->fetch_assoc();
            
            if ($record) {
                // Insert back into activerecords
                $stmt = $conn->prepare("INSERT INTO activerecords (name, position, work_date, time_in, time_out, earnings) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssd", $record['name'], $record['position'], $record['work_date'], $record['time_in'], $record['time_out'], $record['earnings']);
                $stmt->execute();
                
                // Remove from deletedrecords
                $conn->query("DELETE FROM deletedrecords WHERE deleted_id = $id");
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Deleted record not found'], 404);
            }
            break;
            
        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'];
            
            if ($conn->query("DELETE FROM deletedrecords WHERE deleted_id = $id")) {
                sendJsonResponse(['success' => true]);
            } else {
                sendJsonResponse(['error' => 'Failed to permanently delete record'], 500);
            }
            break;
    }
}
?>