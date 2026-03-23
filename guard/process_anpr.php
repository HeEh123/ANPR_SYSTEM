<?php
require_once '../includes/functions.php';

// Check if user is logged in and is guard
if (!isLoggedIn() || !isGuard()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Check if image was uploaded
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No image uploaded or upload failed']);
    exit();
}

$file = $_FILES['image'];
$direction = $_POST['direction'] ?? 'IN';

// Validate file type
$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Please upload JPG or PNG']);
    exit();
}

// Validate file size (5MB max)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 5MB']);
    exit();
}

// Create upload directory if it doesn't exist
$uploadDir = '../assets/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '_' . time() . '.' . $extension;
$filepath = $uploadDir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
    exit();
}

// Call Python ANPR script
$pythonScript = '../anpr/anpr_processor.py';
$absolutePath = realpath($filepath);

// Execute Python script
$command = escapeshellcmd("python3 $pythonScript " . escapeshellarg($absolutePath) . " 2>&1");
$output = shell_exec($command);

// Parse JSON output
$result = json_decode($output, true);

if (!$result || !isset($result['success'])) {
    // Delete uploaded file if processing failed
    unlink($filepath);
    echo json_encode(['success' => false, 'error' => 'ANPR processing failed']);
    exit();
}

if (!$result['success']) {
    // Delete uploaded file if recognition failed
    unlink($filepath);
    echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed to recognize plate']);
    exit();
}

// Check plate authorization
$authCheck = checkPlateAuthorization($result['plate_number']);

// Log the vehicle
$logResult = logVehicle(
    $result['plate_number'],
    $direction,
    $_SESSION['user_id'],
    $filename
);

if (!$logResult) {
    // Log failed but we still return the result
    error_log("Failed to log vehicle: " . $result['plate_number']);
}

// Prepare response
$response = [
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
];

echo json_encode($response);
exit();
?>