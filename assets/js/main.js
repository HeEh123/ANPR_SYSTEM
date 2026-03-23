// Main JavaScript file for ANPR System

// Global variables
let currentUser = null;

// Document ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    initTooltips();
    
    // Initialize file upload preview
    initFileUpload();
    
    // Initialize modal triggers
    initModals();
    
    // Initialize form validation
    initFormValidation();
    
    // Initialize data tables
    initDataTables();
});

// Tooltips initialization
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = e.target.dataset.tooltip;
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
    tooltip.style.left = rect.left + (rect.width - tooltip.offsetWidth) / 2 + 'px';
    
    setTimeout(() => tooltip.classList.add('show'), 10);
    e.target._tooltip = tooltip;
}

function hideTooltip(e) {
    if (e.target._tooltip) {
        e.target._tooltip.remove();
        delete e.target._tooltip;
    }
}

// File upload preview
function initFileUpload() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('plateImage');
    const preview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    
    if (!uploadArea || !fileInput) return;
    
    // Click to upload
    uploadArea.addEventListener('click', () => fileInput.click());
    
    // Drag and drop
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
    
    // File input change
    fileInput.addEventListener('change', function(e) {
        if (this.files.length > 0) {
            handleFileSelect(this.files[0]);
        }
    });
    
    function handleFileSelect(file) {
        if (!file.type.startsWith('image/')) {
            showAlert('Please select an image file', 'danger');
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            showAlert('File size must be less than 5MB', 'danger');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

// Modals
function initModals() {
    document.querySelectorAll('[data-modal]').forEach(button => {
        button.addEventListener('click', function() {
            const modalId = this.dataset.modal;
            openModal(modalId);
        });
    });
    
    document.querySelectorAll('.modal-close, .modal .btn-secondary').forEach(element => {
        element.addEventListener('click', function() {
            const modal = this.closest('.modal');
            closeModal(modal);
        });
    });
    
    // Close modal on outside click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this);
            }
        });
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

// Form validation
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', validateForm);
    });
}

function validateForm(e) {
    const form = e.target;
    const inputs = form.querySelectorAll('[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
            input.classList.add('error');
            showInputError(input, 'This field is required');
        } else {
            input.classList.remove('error');
            clearInputError(input);
        }
        
        // Email validation
        if (input.type === 'email' && input.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value)) {
                isValid = false;
                input.classList.add('error');
                showInputError(input, 'Please enter a valid email');
            }
        }
        
        // Phone validation
        if (input.name === 'phone' && input.value) {
            const phoneRegex = /^[0-9+\-\s]+$/;
            if (!phoneRegex.test(input.value)) {
                isValid = false;
                input.classList.add('error');
                showInputError(input, 'Please enter a valid phone number');
            }
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        showAlert('Please fill in all required fields correctly', 'danger');
    }
}

function showInputError(input, message) {
    let errorDiv = input.nextElementSibling;
    if (!errorDiv || !errorDiv.classList.contains('error-message')) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        input.parentNode.insertBefore(errorDiv, input.nextSibling);
    }
    errorDiv.textContent = message;
}

function clearInputError(input) {
    const errorDiv = input.nextElementSibling;
    if (errorDiv && errorDiv.classList.contains('error-message')) {
        errorDiv.remove();
    }
}

// Data tables
function initDataTables() {
    document.querySelectorAll('.data-table').forEach(table => {
        // Add sorting functionality
        const headers = table.querySelectorAll('th[data-sort]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => sortTable(table, header));
        });
        
        // Add search functionality
        const searchInput = document.getElementById('tableSearch');
        if (searchInput) {
            searchInput.addEventListener('keyup', () => filterTable(table, searchInput.value));
        }
    });
}

function sortTable(table, header) {
    const index = Array.from(header.parentNode.children).indexOf(header);
    const type = header.dataset.sort || 'string';
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    const sortOrder = header.classList.contains('sort-asc') ? 'desc' : 'asc';
    
    rows.sort((a, b) => {
        const aVal = a.children[index].textContent.trim();
        const bVal = b.children[index].textContent.trim();
        
        if (type === 'number') {
            return sortOrder === 'asc' ? 
                parseFloat(aVal) - parseFloat(bVal) : 
                parseFloat(bVal) - parseFloat(aVal);
        } else if (type === 'date') {
            return sortOrder === 'asc' ? 
                new Date(aVal) - new Date(bVal) : 
                new Date(bVal) - new Date(aVal);
        } else {
            return sortOrder === 'asc' ? 
                aVal.localeCompare(bVal) : 
                bVal.localeCompare(aVal);
        }
    });
    
    // Update header classes
    headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
    header.classList.add(sortOrder === 'asc' ? 'sort-asc' : 'sort-desc');
    
    // Reorder rows
    rows.forEach(row => tbody.appendChild(row));
}

function filterTable(table, searchText) {
    const rows = table.querySelectorAll('tbody tr');
    const searchLower = searchText.toLowerCase();
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchLower)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Alert system
function showAlert(message, type = 'success', duration = 5000) {
    const alertContainer = document.getElementById('alertContainer') || createAlertContainer();
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    alertContainer.appendChild(alert);
    
    if (duration > 0) {
        setTimeout(() => alert.remove(), duration);
    }
}

function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alertContainer';
    container.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 400px;
    `;
    document.body.appendChild(container);
    return container;
}

// AJAX functions
function ajaxRequest(url, method = 'GET', data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        if (data instanceof FormData) {
            // Don't set Content-Type for FormData
        } else if (data) {
            xhr.setRequestHeader('Content-Type', 'application/json');
            data = JSON.stringify(data);
        }
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    resolve(JSON.parse(xhr.responseText));
                } catch (e) {
                    resolve(xhr.responseText);
                }
            } else {
                reject(new Error(xhr.statusText));
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('Network error'));
        };
        
        xhr.send(data);
    });
}

// ANPR Processing
function processANPR(imageFile, direction) {
    const formData = new FormData();
    formData.append('image', imageFile);
    formData.append('direction', direction);
    
    showLoading();
    
    ajaxRequest('../api/anpr_api.php?action=process', 'POST', formData)        .then(response => {
            hideLoading();
            if (response.success) {
                displayRecognitionResult(response.data);
            } else {
                showAlert(response.error || 'Failed to process image', 'danger');
            }
        })
        .catch(error => {
            hideLoading();
            showAlert('Error processing image: ' + error.message, 'danger');
        });
}

function showLoading() {
    const loader = document.getElementById('loadingSpinner');
    if (loader) {
        loader.style.display = 'flex';
    }
}

function hideLoading() {
    const loader = document.getElementById('loadingSpinner');
    if (loader) {
        loader.style.display = 'none';
    }
}

function displayRecognitionResult(data) {
    const resultDiv = document.getElementById('recognitionResult');
    if (!resultDiv) return;
    
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
    
    // Refresh vehicle logs
    refreshVehicleLogs();
}

function refreshVehicleLogs() {
    const logsTable = document.getElementById('vehicleLogs');
    if (logsTable) {
        ajaxRequest('api/anpr_api.php?action=get_logs')
            .then(response => {
                if (response.success) {
                    updateLogsTable(response.data);
                }
            })
            .catch(error => console.error('Error refreshing logs:', error));
    }
}

function updateLogsTable(logs) {
    const tbody = document.querySelector('#vehicleLogs tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    logs.forEach(log => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${log.plate_number}</td>
            <td>${new Date(log.date_time).toLocaleString()}</td>
            <td><span class="badge ${log.direction === 'IN' ? 'badge-success' : 'badge-warning'}">${log.direction}</span></td>
            <td><span class="badge ${log.status === 'approved' ? 'badge-success' : 'badge-danger'}">${log.status}</span></td>
            <td>${log.guard_name || '-'}</td>
        `;
        tbody.appendChild(row);
    });
}

// Export functions
function exportToCSV(data, filename) {
    const csv = convertToCSV(data);
    downloadFile(csv, filename, 'text/csv');
}

function exportToExcel(data, filename) {
    // Simple CSV export (can be enhanced with actual Excel library)
    exportToCSV(data, filename.replace(/\.xlsx?$/, '.csv'));
}

function convertToCSV(data) {
    if (!data || !data.length) return '';
    
    const headers = Object.keys(data[0]);
    const csvRows = [];
    
    csvRows.push(headers.join(','));
    
    for (const row of data) {
        const values = headers.map(header => {
            const value = row[header] || '';
            return `"${value.toString().replace(/"/g, '""')}"`;
        });
        csvRows.push(values.join(','));
    }
    
    return csvRows.join('\n');
}

function downloadFile(content, filename, type) {
    const blob = new Blob([content], { type });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}