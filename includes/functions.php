<?php
require_once 'db_connection.php';

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isManagement() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'management';
}

function isResident() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'resident';
}

function isGuard() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'guard';
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit();
    }
}

function redirectBasedOnRole() {
    if (isAdmin()) {
        header('Location: ' . APP_URL . '/admin/dashboard.php');
    } elseif (isManagement()) {
        header('Location: ' . APP_URL . '/management/dashboard.php');
    } elseif (isResident()) {
        header('Location: ' . APP_URL . '/resident/dashboard.php');
    } elseif (isGuard()) {
        header('Location: ' . APP_URL . '/guard/dashboard.php');
    } else {
        header('Location: ' . APP_URL . '/login.php');
    }
    exit();
}

// Check plate authorization
function checkPlateAuthorization($plate_number) {
    global $db;
    
    $conn = $db->getConnection();
    $plate_number = $conn->real_escape_string($plate_number);
    
    $sql = "SELECT p.*, r.unit_number, u.full_name as resident_name 
            FROM preregistered_plates p
            LEFT JOIN residents r ON p.resident_id = r.resident_id
            LEFT JOIN users u ON r.user_id = u.user_id
            WHERE p.plate_number = '$plate_number' 
            AND (p.valid_until IS NULL OR p.valid_until >= CURDATE())";
    
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $plate = $result->fetch_assoc();
        return [
            'authorized' => true,
            'status' => $plate['status'],
            'visitor_name' => $plate['visitor_name'],
            'unit_number' => $plate['unit_number'],
            'plate_id' => $plate['plate_id']
        ];
    }
    
    return ['authorized' => false];
}

// Log vehicle entry/exit
function logVehicle($plate_number, $direction, $guard_id, $image_path = null) {
    global $db;
    
    $conn = $db->getConnection();
    
    // Check if plate is registered
    $auth_check = checkPlateAuthorization($plate_number);
    $status = $auth_check['authorized'] ? 'approved' : 'not_registered';
    $plate_id = $auth_check['authorized'] ? $auth_check['plate_id'] : null;
    
    $plate_number = $conn->real_escape_string($plate_number);
    $direction = $conn->real_escape_string($direction);
    $guard_id = (int)$guard_id;
    $status = $conn->real_escape_string($status);
    $image_path = $image_path ? "'" . $conn->real_escape_string($image_path) . "'" : "NULL";
    $plate_id = $plate_id ? $plate_id : "NULL";
    
    $sql = "INSERT INTO vehicle_logs (plate_number, plate_id, direction, status, guard_id, image_path) 
            VALUES ('$plate_number', $plate_id, '$direction', '$status', $guard_id, $image_path)";
    
    return $conn->query($sql);
}

// Get user by ID
function getUserById($user_id) {
    global $db;
    
    $conn = $db->getConnection();
    $user_id = (int)$user_id;
    
    $sql = "SELECT * FROM users WHERE user_id = $user_id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Get all users
function getAllUsers() {
    global $db;
    
    $conn = $db->getConnection();
    $sql = "SELECT u.*, r.unit_number 
            FROM users u 
            LEFT JOIN residents r ON u.user_id = r.user_id 
            ORDER BY u.created_at DESC";
    
    $result = $conn->query($sql);
    $users = [];
    
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    return $users;
}

// Create user (without password hashing)
function createUser($username, $password, $full_name, $role, $email = null, $phone = null, $unit_number = null) {
    global $db;
    
    $conn = $db->getConnection();
    
    $username = $conn->real_escape_string($username);
    $password = $conn->real_escape_string($password); // Store as plain text
    $full_name = $conn->real_escape_string($full_name);
    $role = $conn->real_escape_string($role);
    $email = $email ? "'" . $conn->real_escape_string($email) . "'" : "NULL";
    $phone = $phone ? "'" . $conn->real_escape_string($phone) . "'" : "NULL";
    
    // Insert user with plain password
    $sql = "INSERT INTO users (username, password, full_name, email, phone, role) 
            VALUES ('$username', '$password', '$full_name', $email, $phone, '$role')";
    
    if ($conn->query($sql)) {
        $user_id = $conn->insert_id;
        
        // If resident, insert into residents table
        if ($role === 'resident' && $unit_number) {
            $unit_number = $conn->real_escape_string($unit_number);
            $sql = "INSERT INTO residents (user_id, unit_number) VALUES ($user_id, '$unit_number')";
            $conn->query($sql);
        }
        
        return $user_id;
    }
    
    return false;
}

// Update user
function updateUser($user_id, $data) {
    global $db;
    
    $conn = $db->getConnection();
    $user_id = (int)$user_id;
    
    $updates = [];
    foreach ($data as $key => $value) {
        if ($key !== 'user_id' && $key !== 'password') {
            $value = $conn->real_escape_string($value);
            $updates[] = "$key = '$value'";
        }
    }
    
    if (isset($data['password']) && !empty($data['password'])) {
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        $updates[] = "password = '$hashed_password'";
    }
    
    if (empty($updates)) {
        return false;
    }
    
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = $user_id";
    
    return $conn->query($sql);
}

// Delete user
function deleteUser($user_id) {
    global $db;
    
    $conn = $db->getConnection();
    $user_id = (int)$user_id;
    
    $sql = "DELETE FROM users WHERE user_id = $user_id";
    return $conn->query($sql);
}

// Get vehicle logs
function getVehicleLogs($filters = []) {
    global $db;
    
    $conn = $db->getConnection();
    
    $sql = "SELECT v.*, u.full_name as guard_name, p.visitor_name, p.unit_number 
            FROM vehicle_logs v
            LEFT JOIN users u ON v.guard_id = u.user_id
            LEFT JOIN preregistered_plates p ON v.plate_id = p.plate_id
            WHERE 1=1";
    
    // Apply filters
    if (!empty($filters['plate_number'])) {
        $plate = $conn->real_escape_string($filters['plate_number']);
        $sql .= " AND v.plate_number LIKE '%$plate%'";
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(v.date_time) >= '" . $conn->real_escape_string($filters['date_from']) . "'";
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(v.date_time) <= '" . $conn->real_escape_string($filters['date_to']) . "'";
    }
    
    if (!empty($filters['direction'])) {
        $direction = $conn->real_escape_string($filters['direction']);
        $sql .= " AND v.direction = '$direction'";
    }
    
    if (!empty($filters['status'])) {
        $status = $conn->real_escape_string($filters['status']);
        $sql .= " AND v.status = '$status'";
    }
    
    $sql .= " ORDER BY v.date_time DESC";
    
    $result = $conn->query($sql);
    $logs = [];
    
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    
    return $logs;
}

// Get preregistered plates
function getPreregisteredPlates($resident_id = null) {
    global $db;
    
    $conn = $db->getConnection();
    
    $sql = "SELECT p.*, r.unit_number, u.full_name as resident_name 
            FROM preregistered_plates p
            LEFT JOIN residents r ON p.resident_id = r.resident_id
            LEFT JOIN users u ON r.user_id = u.user_id";
    
    if ($resident_id) {
        $resident_id = (int)$resident_id;
        $sql .= " WHERE p.resident_id = $resident_id";
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    
    $result = $conn->query($sql);
    $plates = [];
    
    while ($row = $result->fetch_assoc()) {
        $plates[] = $row;
    }
    
    return $plates;
}

// Add preregistered plate
function addPreregisteredPlate($data) {
    global $db;
    
    $conn = $db->getConnection();
    
    $plate_number = $conn->real_escape_string($data['plate_number']);
    $visitor_name = $conn->real_escape_string($data['visitor_name']);
    $visitor_contact = $conn->real_escape_string($data['visitor_contact']);
    $unit_number = $conn->real_escape_string($data['unit_number']);
    $resident_id = (int)$data['resident_id'];
    $status = $conn->real_escape_string($data['status'] ?? 'visitor');
    $valid_from = !empty($data['valid_from']) ? "'" . $conn->real_escape_string($data['valid_from']) . "'" : "NULL";
    $valid_until = !empty($data['valid_until']) ? "'" . $conn->real_escape_string($data['valid_until']) . "'" : "NULL";
    
    $sql = "INSERT INTO preregistered_plates (plate_number, visitor_name, visitor_contact, unit_number, resident_id, status, valid_from, valid_until) 
            VALUES ('$plate_number', '$visitor_name', '$visitor_contact', '$unit_number', $resident_id, '$status', $valid_from, $valid_until)";
    
    return $conn->query($sql);
}

// Delete preregistered plate
function deletePreregisteredPlate($plate_id) {
    global $db;
    
    $conn = $db->getConnection();
    $plate_id = (int)$plate_id;
    
    $sql = "DELETE FROM preregistered_plates WHERE plate_id = $plate_id";
    return $conn->query($sql);
}

// Get resident ID by user ID
function getResidentIdByUserId($user_id) {
    global $db;
    
    $conn = $db->getConnection();
    $user_id = (int)$user_id;
    
    $sql = "SELECT resident_id FROM residents WHERE user_id = $user_id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['resident_id'];
    }
    
    return null;
}

// Get dashboard statistics
function getDashboardStats() {
    global $db;
    
    $conn = $db->getConnection();
    $stats = [];
    
    // Total entries today
    $sql = "SELECT COUNT(*) as count FROM vehicle_logs WHERE DATE(date_time) = CURDATE()";
    $result = $conn->query($sql);
    $stats['total_today'] = $result->fetch_assoc()['count'];
    
    // Unknown vehicles today
    $sql = "SELECT COUNT(*) as count FROM vehicle_logs WHERE DATE(date_time) = CURDATE() AND status = 'not_registered'";
    $result = $conn->query($sql);
    $stats['unknown_today'] = $result->fetch_assoc()['count'];
    
    // Total registered plates
    $sql = "SELECT COUNT(*) as count FROM preregistered_plates";
    $result = $conn->query($sql);
    $stats['total_registered'] = $result->fetch_assoc()['count'];
    
    // Total users
    $sql = "SELECT COUNT(*) as count FROM users";
    $result = $conn->query($sql);
    $stats['total_users'] = $result->fetch_assoc()['count'];
    
    return $stats;
}
?>