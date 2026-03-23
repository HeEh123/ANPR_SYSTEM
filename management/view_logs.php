<?php
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

if (!isManagement()) {
    header('Location: ../login.php');
    exit();
}

// Get filter parameters
$filters = [
    'plate_number' => $_GET['plate'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'direction' => $_GET['direction'] ?? '',
    'status' => $_GET['status'] ?? ''
];

$logs = getVehicleLogs($filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Logs - <?php echo APP_NAME; ?></title>
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
                <p>Management Portal</p>
            </div>
            
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-dashboard"></i>
                    <span>Dashboard</span>
                </a>
                <a href="view_logs.php" class="menu-item active">
                    <i class="fas fa-history"></i>
                    <span>Vehicle Logs</span>
                </a>
                <a href="reports.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
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
                <h1 class="page-title">Vehicle Logs</h1>
                <div class="header-right">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
                            <div class="user-role">Management</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="content">
                <!-- Search Form -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-search"></i>
                            Search Logs
                        </h3>
                    </div>
                    
                    <form method="GET">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Plate Number</label>
                                <input type="text" name="plate" class="form-control" value="<?php echo $filters['plate_number']; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Date From</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $filters['date_from']; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Date To</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $filters['date_to']; ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Direction</label>
                                <select name="direction" class="form-control">
                                    <option value="">All</option>
                                    <option value="IN" <?php echo $filters['direction'] === 'IN' ? 'selected' : ''; ?>>IN</option>
                                    <option value="OUT" <?php echo $filters['direction'] === 'OUT' ? 'selected' : ''; ?>>OUT</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All</option>
                                    <option value="approved" <?php echo $filters['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="not_registered" <?php echo $filters['status'] === 'not_registered' ? 'selected' : ''; ?>>Not Registered</option>
                                </select>
                            </div>
                            
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                    Search
                                </button>
                                <button type="button" class="btn btn-success" onclick="exportCurrentLogs()" style="margin-left: 10px;">
                                    <i class="fas fa-download"></i>
                                    Export
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Logs Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-list"></i>
                            Log Results (<?php echo count($logs); ?> records)
                        </h3>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table" id="logsTable">
                            <thead>
                                <tr>
                                    <th>Plate Number</th>
                                    <th>Date & Time</th>
                                    <th>Direction</th>
                                    <th>Status</th>
                                    <th>Visitor Name</th>
                                    <th>Unit</th>
                                    <th>Guard</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--gray-color);">
                                        No logs found
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><strong><?php echo $log['plate_number']; ?></strong></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($log['date_time'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $log['direction'] === 'IN' ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo $log['direction']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $log['status']; ?>">
                                                <?php echo ucfirst($log['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $log['visitor_name'] ?? '-'; ?></td>
                                        <td><?php echo $log['unit_number'] ?? '-'; ?></td>
                                        <td><?php echo $log['guard_name'] ?? '-'; ?></td>
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
        function exportCurrentLogs() {
            const table = document.getElementById('logsTable');
            const rows = table.querySelectorAll('tbody tr');
            const data = [];
            
            rows.forEach(row => {
                if (row.cells.length >= 7) {
                    data.push({
                        plate_number: row.cells[0].textContent.trim(),
                        date_time: row.cells[1].textContent.trim(),
                        direction: row.cells[2].textContent.trim(),
                        status: row.cells[3].textContent.trim(),
                        visitor_name: row.cells[4].textContent.trim(),
                        unit: row.cells[5].textContent.trim(),
                        guard: row.cells[6].textContent.trim()
                    });
                }
            });
            
            exportToCSV(data, 'vehicle_logs_' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.csv');
        }
    </script>
</body>
</html>