<?php
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

if (!isResident()) {
    header('Location: ../login.php');
    exit();
}

// Get resident ID
$resident_id = getResidentIdByUserId($_SESSION['user_id']);
$resident_info = getUserById($_SESSION['user_id']);

// Get resident's preregistered plates
$plates = getPreregisteredPlates($resident_id);

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_plate') {
            $data = [
                'plate_number' => strtoupper($_POST['plate_number']),
                'visitor_name' => $_POST['visitor_name'],
                'visitor_contact' => $_POST['visitor_contact'],
                'unit_number' => $_POST['unit_number'],
                'resident_id' => $resident_id,
                'status' => $_POST['status'],
                'valid_from' => $_POST['valid_from'] ?? null,
                'valid_until' => $_POST['valid_until'] ?? null
            ];
            
            if (addPreregisteredPlate($data)) {
                $message = 'Visitor plate registered successfully';
                // Refresh plates
                $plates = getPreregisteredPlates($resident_id);
            } else {
                $error = 'Failed to register visitor plate';
            }
        } elseif ($_POST['action'] === 'delete_plate' && isset($_POST['plate_id'])) {
            if (deletePreregisteredPlate($_POST['plate_id'])) {
                $message = 'Plate deleted successfully';
                $plates = getPreregisteredPlates($resident_id);
            } else {
                $error = 'Failed to delete plate';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3><?php echo APP_NAME; ?></h3>
                <p>Resident Portal</p>
            </div>
            
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item active">
                    <i class="fas fa-dashboard"></i>
                    <span>Dashboard</span>
                </a>
                <a href="../logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1 class="page-title">Resident Dashboard</h1>
                <div class="header-right">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
                            <div class="user-role">Resident</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="content">
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Profile Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user"></i>
                            My Profile
                        </h3>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div>
                            <div style="font-size: 14px; color: var(--gray-color); margin-bottom: 5px;">Full Name</div>
                            <div style="font-weight: 600;"><?php echo $resident_info['full_name']; ?></div>
                        </div>
                        <div>
                            <div style="font-size: 14px; color: var(--gray-color); margin-bottom: 5px;">Unit Number</div>
                            <div style="font-weight: 600;"><?php echo $resident_info['unit_number'] ?? 'Not set'; ?></div>
                        </div>
                        <div>
                            <div style="font-size: 14px; color: var(--gray-color); margin-bottom: 5px;">Email</div>
                            <div style="font-weight: 600;"><?php echo $resident_info['email'] ?? '-'; ?></div>
                        </div>
                        <div>
                            <div style="font-size: 14px; color: var(--gray-color); margin-bottom: 5px;">Phone</div>
                            <div style="font-weight: 600;"><?php echo $resident_info['phone'] ?? '-'; ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Add New Visitor Plate Form -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user-plus"></i>
                            Register Visitor Vehicle
                        </h3>
                    </div>
                    
                    <form method="POST" data-validate>
                        <input type="hidden" name="action" value="add_plate">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="plate_number">Vehicle Plate Number *</label>
                                <input type="text" id="plate_number" name="plate_number" class="form-control" required 
                                       placeholder="e.g., ABC1234" style="text-transform: uppercase;">
                            </div>
                            
                            <div class="form-group">
                                <label for="visitor_name">Visitor Name *</label>
                                <input type="text" id="visitor_name" name="visitor_name" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="visitor_contact">Visitor Contact</label>
                                <input type="text" id="visitor_contact" name="visitor_contact" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="unit_number">Unit Number *</label>
                                <input type="text" id="unit_number" name="unit_number" class="form-control" required 
                                       value="<?php echo $resident_info['unit_number'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="status">Status *</label>
                                <select id="status" name="status" class="form-control" required>
                                    <option value="visitor">Temporary Visitor</option>
                                    <option value="resident">Resident's Vehicle</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="valid_until">Valid Until (Optional)</label>
                                <input type="date" id="valid_until" name="valid_until" class="form-control">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Register Vehicle
                        </button>
                    </form>
                </div>
                
                <!-- My Preregistered Plates -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-clipboard-list"></i>
                            My Registered Vehicles
                        </h3>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Plate Number</th>
                                    <th>Visitor Name</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Valid Until</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($plates)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--gray-color);">
                                        No vehicles registered yet
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($plates as $plate): ?>
                                    <tr>
                                        <td><strong><?php echo $plate['plate_number']; ?></strong></td>
                                        <td><?php echo $plate['visitor_name']; ?></td>
                                        <td><?php echo $plate['visitor_contact'] ?? '-'; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $plate['status'] === 'resident' ? 'active' : 'visitor'; ?>">
                                                <?php echo ucfirst($plate['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($plate['valid_until']) {
                                                echo date('Y-m-d', strtotime($plate['valid_until']));
                                            } else {
                                                echo 'No expiry';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_plate">
                                                <input type="hidden" name="plate_id" value="<?php echo $plate['plate_id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" 
                                                        onclick="return confirm('Are you sure you want to delete this vehicle?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        // Auto uppercase for plate number
        document.getElementById('plate_number').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>