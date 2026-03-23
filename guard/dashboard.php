<?php
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

if (!isGuard()) {
    header('Location: ../login.php');
    exit();
}

// Get recent logs
$logs = getVehicleLogs(['limit' => 10]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guard Dashboard - <?php echo APP_NAME; ?></title>
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
                <p>Guard Portal</p>
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
                <h1 class="page-title">Security Guard Dashboard</h1>
                <div class="header-right">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
                            <div class="user-role">Security Guard</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="content">
                <div class="row">
                    <!-- Left Column - Upload Form -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-camera"></i>
                                    Capture Vehicle Plate
                                </h3>
                            </div>
                            
                            <form id="anprForm" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label>Select Direction</label>
                                    <div style="display: flex; gap: 20px;">
                                        <label class="radio-label">
                                            <input type="radio" name="direction" value="IN" checked> 
                                            <i class="fas fa-arrow-right"></i> IN
                                        </label>
                                        <label class="radio-label">
                                            <input type="radio" name="direction" value="OUT"> 
                                            <i class="fas fa-arrow-left"></i> OUT
                                        </label>
                                    </div>
                                </div>
                                
                                <div id="uploadArea" class="upload-area">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <h4>Click or drag image to upload</h4>
                                    <p>Supported formats: JPG, PNG (Max 5MB)</p>
                                    <input type="file" id="plateImage" name="image" accept="image/*" style="display: none;">
                                </div>
                                
                                <div id="imagePreview" class="upload-preview" style="display: none;">
                                    <img id="previewImg" src="" alt="Preview">
                                </div>
                                
                                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px;">
                                    <i class="fas fa-cog fa-spin" style="display: none;" id="processSpinner"></i>
                                    <span id="processText">Process ANPR</span>
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Right Column - Recognition Result -->
                    <div class="col-md-6">
                        <div id="recognitionResult" style="display: none;"></div>
                        
                        <div class="card" id="manualActions" style="display: none;">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-tasks"></i>
                                    Actions
                                </h3>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <button class="btn btn-success" onclick="confirmLog()">
                                    <i class="fas fa-check"></i>
                                    Confirm Log
                                </button>
                                <button class="btn btn-warning" onclick="resetForm()">
                                    <i class="fas fa-redo"></i>
                                    Recapture
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Vehicle Logs -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i>
                            Latest Vehicle Logs
                        </h3>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table" id="vehicleLogs">
                            <thead>
                                <tr>
                                    <th>Plate Number</th>
                                    <th>Date & Time</th>
                                    <th>Direction</th>
                                    <th>Status</th>
                                    <th>Visitor</th>
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
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div id="loadingSpinner" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div class="spinner"></div>
    </div>
    
    <style>
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .col-md-6 {
            width: 100%;
        }
        
        .radio-label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .radio-label:hover {
            border-color: var(--primary-color);
            background-color: #f0f9ff;
        }
        
        input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .result-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .result-plate {
            font-size: 36px;
            font-weight: 700;
            letter-spacing: 2px;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .result-status {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 600;
            margin-bottom: 15px;
            text-align: center;
            width: 100%;
            box-sizing: border-box;
        }
        
        .status-approved-badge {
            background-color: rgba(16, 185, 129, 0.2);
            color: white;
            border: 2px solid #10b981;
        }
        
        .status-not-registered-badge {
            background-color: rgba(239, 68, 68, 0.2);
            color: white;
            border: 2px solid #ef4444;
        }
        
        .result-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .detail-item {
            text-align: center;
        }
        
        .detail-label {
            font-size: 12px;
            opacity: 0.8;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 18px;
            font-weight: 600;
        }
        
        .upload-area {
            border: 3px dashed #e5e7eb;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .upload-area:hover {
            border-color: var(--primary-color);
            background-color: #f0f9ff;
        }
        
        .upload-preview {
            margin-top: 20px;
            text-align: center;
        }
        
        .upload-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 10px;
        }
    </style>
    
    <script>
        // File upload preview
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('plateImage');
        const preview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        
        uploadArea.addEventListener('click', () => fileInput.click());
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#2563eb';
            uploadArea.style.backgroundColor = '#f0f9ff';
        });
        
        uploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#e5e7eb';
            uploadArea.style.backgroundColor = '#f9fafb';
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#e5e7eb';
            uploadArea.style.backgroundColor = '#f9fafb';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(files[0]);
            }
        });
        
        fileInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                handleFileSelect(this.files[0]);
            }
        });
        
        function handleFileSelect(file) {
            if (!file.type.startsWith('image/')) {
                alert('Please select an image file');
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
        
        // Form submission
        document.getElementById('anprForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('plateImage');
            const direction = document.querySelector('input[name="direction"]:checked').value;
            
            if (!fileInput.files || fileInput.files.length === 0) {
                alert('Please select an image first');
                return;
            }
            
            // Show loading
            document.getElementById('processSpinner').style.display = 'inline-block';
            document.getElementById('processText').textContent = 'Processing...';
            
            // Create FormData
            const formData = new FormData();
            formData.append('image', fileInput.files[0]);
            formData.append('direction', direction);
            
            // FIXED: Use correct path - try this first
            const apiUrl = '../api/anpr_api.php?action=process';
            
            console.log('Calling API:', apiUrl); // Check console to see the path
            
            // Send AJAX request
            fetch(apiUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ' - ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                // Hide loading
                document.getElementById('processSpinner').style.display = 'none';
                document.getElementById('processText').textContent = 'Process ANPR';
                
                if (data.success) {
                    displayRecognitionResult(data.data);
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                document.getElementById('processSpinner').style.display = 'none';
                document.getElementById('processText').textContent = 'Process ANPR';
                alert('Error: ' + error.message + '\n\nMake sure the api folder and anpr_api.php file exist!');
            });
        });
        
        function displayRecognitionResult(data) {
            const resultDiv = document.getElementById('recognitionResult');
            const manualActions = document.getElementById('manualActions');
            
            const statusClass = data.status === 'approved' ? 'status-approved-badge' : 'status-not-registered-badge';
            const statusText = data.status === 'approved' ? 'APPROVED' : 'NOT REGISTERED';
            
            let visitorInfo = '';
            if (data.visitor_name) {
                visitorInfo = `
                    <div class="detail-item">
                        <div class="detail-label">Visitor</div>
                        <div class="detail-value">${data.visitor_name}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Unit</div>
                        <div class="detail-value">${data.unit_number || '-'}</div>
                    </div>
                `;
            }
            
            resultDiv.innerHTML = `
                <div class="result-card">
                    <div class="result-plate">${data.plate_number}</div>
                    <div class="result-status ${statusClass}">${statusText}</div>
                    <div class="result-details">
                        <div class="detail-item">
                            <div class="detail-label">Direction</div>
                            <div class="detail-value">${data.direction}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Date & Time</div>
                            <div class="detail-value">${data.date_time}</div>
                        </div>
                        ${visitorInfo}
                    </div>
                </div>
            `;
            
            resultDiv.style.display = 'block';
            manualActions.style.display = 'block';
            
            // Refresh vehicle logs
            refreshVehicleLogs();
        }
        
        function confirmLog() {
            document.getElementById('manualActions').style.display = 'none';
            resetForm();
            alert('Vehicle entry logged successfully');
        }
        
        function resetForm() {
            document.getElementById('anprForm').reset();
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('recognitionResult').style.display = 'none';
            document.getElementById('manualActions').style.display = 'none';
            document.getElementById('processSpinner').style.display = 'none';
            document.getElementById('processText').textContent = 'Process ANPR';
        }
        
        function refreshVehicleLogs() {
            fetch('../api/anpr_api.php?action=get_logs&limit=10')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateLogsTable(data.data);
                    }
                })
                .catch(error => console.error('Error refreshing logs:', error));
        }
        
        function updateLogsTable(logs) {
            const tbody = document.querySelector('#vehicleLogs tbody');
            if (!tbody) return;
            
            tbody.innerHTML = '';
            
            logs.forEach(log => {
                const row = document.createElement('tr');
                const statusText = log.status === 'approved' ? 'Approved' : 'Not Registered';
                
                row.innerHTML = `
                    <td><strong>${log.plate_number}</strong></td>
                    <td>${new Date(log.date_time).toLocaleString()}</td>
                    <td><span class="badge ${log.direction === 'IN' ? 'badge-success' : 'badge-warning'}">${log.direction}</span></td>
                    <td><span class="status-badge status-${log.status}">${statusText}</span></td>
                    <td>${log.visitor_name || '-'}</td>
                `;
                tbody.appendChild(row);
            });
        }
    </script>
</body>
</html>