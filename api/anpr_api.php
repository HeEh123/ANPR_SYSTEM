<?php
require_once '../includes/functions.php';

// Set header for JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'process':
        handleProcess();
        break;
        
    case 'get_logs':
        handleGetLogs();
        break;
        
    case 'check_plate':
        handleCheckPlate();
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

function handleProcess() {
    // Check if user is guard
    if (!isGuard()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized - Guard access required']);
        return;
    }
    
    // Check if image was uploaded
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'No image uploaded']);
        return;
    }
    
    $file = $_FILES['image'];
    $direction = $_POST['direction'] ?? 'IN';
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type']);
        return;
    }
    
    // Validate file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'File too large']);
        return;
    }
    
    // Create upload directory
    $uploadDir = '../assets/uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Save file
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save file']);
        return;
    }
    
    // Call Python script
    $pythonScript = '../anpr/anpr_processor.py';
    $absolutePath = realpath($filepath);
    
    // Try different Python commands based on OS
    $pythonCommands = ['python', 'python3', 'py'];
    $output = null;
    $commandUsed = '';
    
    foreach ($pythonCommands as $cmd) {
        // Use 2>&1 to capture stderr as well
        $command = escapeshellcmd("$cmd \"$pythonScript\" \"$absolutePath\" 2>&1");
        $output = shell_exec($command);
        $commandUsed = $cmd;
        
        // If we got output, break
        if ($output !== null && $output !== '') {
            break;
        }
    }
    
    // Debug: Log the output for troubleshooting
    error_log("Python command used: $commandUsed");
    error_log("Python output: " . $output);
    
    if ($output === null || $output === '') {
        unlink($filepath);
        echo json_encode(['success' => false, 'error' => 'Failed to execute ANPR script - No output']);
        return;
    }
    
    // Clean the output - remove any non-JSON content
    // Find the first { and last } to extract just the JSON part
    $jsonStart = strpos($output, '{');
    $jsonEnd = strrpos($output, '}');
    
    if ($jsonStart !== false && $jsonEnd !== false) {
        $jsonString = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);
    } else {
        // If no JSON found, use the whole output
        $jsonString = $output;
    }
    
    // Trim whitespace
    $jsonString = trim($jsonString);
    
    // Parse JSON output
    $result = json_decode($jsonString, true);
    
    // Check if JSON parsing failed
    if ($result === null) {
        unlink($filepath);
        error_log("JSON decode error. Raw output: " . $output);
        error_log("JSON string attempted: " . $jsonString);
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid ANPR output - JSON decode failed',
            'debug' => substr($output, 0, 500) // Send first 500 chars for debugging
        ]);
        return;
    }
    
    if (!isset($result['success'])) {
        unlink($filepath);
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid ANPR output structure',
            'debug' => $result
        ]);
        return;
    }
    
    if (!$result['success']) {
        unlink($filepath);
        echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Recognition failed']);
        return;
    }
    
    // Make sure plate_number is set
    if (!isset($result['plate_number']) || empty($result['plate_number'])) {
        unlink($filepath);
        echo json_encode(['success' => false, 'error' => 'No plate number detected']);
        return;
    }
    
    // Check authorization
    $authCheck = checkPlateAuthorization($result['plate_number']);
    
    // Log the vehicle
    logVehicle(
        $result['plate_number'],
        $direction,
        $_SESSION['user_id'],
        $filename
    );
    
    // Return response
    echo json_encode([
        'success' => true,
        'data' => [
            'plate_number' => $result['plate_number'],
            'direction' => $direction,
            'status' => $authCheck['authorized'] ? 'approved' : 'not_registered',
            'date_time' => date('Y-m-d H:i:s'),
            'visitor_name' => $authCheck['visitor_name'] ?? null,
            'unit_number' => $authCheck['unit_number'] ?? null,
            'image_path' => $filename
        ]
    ]);
}

function handleGetLogs() {
    $limit = $_GET['limit'] ?? 10;
    $filters = [];
    
    $logs = getVehicleLogs($filters);
    
    // Return only last $limit logs
    $logs = array_slice($logs, 0, $limit);
    
    echo json_encode([
        'success' => true,
        'data' => $logs
    ]);
}

function handleCheckPlate() {
    $plate_number = $_GET['plate'] ?? '';
    
    if (empty($plate_number)) {
        echo json_encode(['success' => false, 'error' => 'Plate number required']);
        return;
    }
    
    $authCheck = checkPlateAuthorization($plate_number);
    
    echo json_encode([
        'success' => true,
        'data' => $authCheck
    ]);
}
?>