<?php
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

if (!isManagement()) {
    header('Location: ../login.php');
    exit();
}

$stats = getDashboardStats();
$logs = getVehicleLogs();
$plates = getPreregisteredPlates();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Dashboard - <?php echo APP_NAME; ?></title>
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
                <a href="dashboard.php" class="menu-item active">
                    <i class="fas fa-dashboard"></i>
                    <span>Dashboard</span>
                </a>
                <a href="view_logs.php" class="menu-item">
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
                <h1 class="page-title">Management Dashboard</h1>
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
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-car"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_today']; ?></h3>
                            <p>Entries Today</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon yellow">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['unknown_today']; ?></h3>
                            <p>Unknown Vehicles</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_registered']; ?></h3>
                            <p>Registered Plates</p>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Bar -->
                <div class="filter-bar">
                    <div class="filter-group">
                        <label>Date From</label>
                        <input type="date" id="filterDateFrom" class="form-control">
                    </div>
                    <div class="filter-group">
                        <label>Date To</label>
                        <input type="date" id="filterDateTo" class="form-control">
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select id="filterStatus" class="form-control">
                            <option value="">All</option>
                            <option value="approved">Approved</option>
                            <option value="not_registered">Not Registered</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Direction</label>
                        <select id="filterDirection" class="form-control">
                            <option value="">All</option>
                            <option value="IN">IN</option>
                            <option value="OUT">OUT</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" onclick="applyFilters()">
                        <i class="fas fa-search"></i>
                        Filter
                    </button>
                    <button class="btn btn-success" onclick="exportLogs()">
                        <i class="fas fa-download"></i>
                        Export CSV
                    </button>
                </div>
                
                <!-- Vehicle Logs Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i>
                            Vehicle Entry/Exit Logs
                        </h3>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table" id="logsTable">
                            <thead>
                                <tr>
                                    <th data-sort="string">Plate Number</th>
                                    <th data-sort="date">Date & Time</th>
                                    <th data-sort="string">Direction</th>
                                    <th data-sort="string">Status</th>
                                    <th data-sort="string">Visitor</th>
                                    <th data-sort="string">Unit</th>
                                    <th data-sort="string">Guard</th>
                                </tr>
                            </thead>
                            <tbody>
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
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        function applyFilters() {
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;
            const status = document.getElementById('filterStatus').value;
            const direction = document.getElementById('filterDirection').value;
            
            // Build query string
            const params = new URLSearchParams();
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            if (status) params.append('status', status);
            if (direction) params.append('direction', direction);
            
            // Reload with filters
            window.location.href = 'dashboard.php?' + params.toString();
        }
        
        function exportLogs() {
            // Collect table data
            const table = document.getElementById('logsTable');
            const rows = table.querySelectorAll('tbody tr');
            const data = [];
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                data.push({
                    plate_number: cells[0].textContent.trim(),
                    date_time: cells[1].textContent.trim(),
                    direction: cells[2].textContent.trim(),
                    status: cells[3].textContent.trim(),
                    visitor: cells[4].textContent.trim(),
                    unit: cells[5].textContent.trim(),
                    guard: cells[6].textContent.trim()
                });
            });
            
            // Export to CSV
            exportToCSV(data, 'vehicle_logs_' + new Date().toISOString().slice(0,10) + '.csv');
        }
    </script>
</body>
</html>