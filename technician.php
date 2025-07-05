<?php
// technician.php

require_once 'session-management.php';
requireLogin();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechStore - Daily Missions</title>
    <style>
        /* All the styles from your provided HTML file are included here */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            position: relative;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 20px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .header .subtitle {
            font-size: 14px;
            opacity: 0.8;
        }

        .back-button {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            display: none;
        }

        .nav-bar {
            display: none;
            background: #34495e;
            border-bottom: 1px solid #2c3e50;
        }

        .nav-bar.show {
            display: flex;
        }

        .nav-item {
            flex: 1;
            padding: 15px;
            text-align: center;
            color: white;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 14px;
            position: relative;
        }

        .nav-item:hover, .nav-item.active {
            background: #2c3e50;
        }

        .nav-item .badge {
            position: absolute;
            top: 8px;
            right: 10px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .screen {
            display: none;
            padding: 20px;
            min-height: calc(100vh - 140px);
        }

        .screen.active {
            display: block;
        }

        /* Missions Screen */
        .date-header {
            text-align: center;
            margin-bottom: 25px;
            color: #2c3e50;
        }

        .date-header .date {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .date-header .day {
            font-size: 16px;
            color: #666;
        }

        .mission-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .mission-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        .mission-priority {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .priority-high { background: #ffebee; color: #c62828; }
        .priority-medium { background: #fff3e0; color: #f57c00; }
        .priority-low { background: #e8f5e8; color: #2e7d32; }

        .mission-id {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .mission-title {
            font-size: 16px;
            color: #34495e;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .mission-details {
            display: flex;
            justify-content: space-between;
            color: #666;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .mission-location {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #666;
        }

        .mission-time {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 13px;
            color: #495057;
            display: inline-block;
            margin-top: 10px;
        }

        /* Tools Screen */
        .mission-info {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
        }

        .mission-info .mission-id {
            font-size: 20px;
            margin-bottom: 5px;
            color: white;
        }

        .mission-info .mission-title {
            font-size: 16px;
            opacity: 0.9;
            color: white;
        }

        .scan-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            text-align: center;
        }

        .scan-section h3 {
            margin-bottom: 15px;
            color: #2c3e50;
        }

        .scan-button {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 16px;
            cursor: pointer;
            margin: 8px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-width: 200px;
            justify-content: center;
        }

        .scan-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(76, 175, 80, 0.3);
        }

        .scan-button.vehicle {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }

        .scan-button.vehicle:hover {
            box-shadow: 0 6px 15px rgba(52, 152, 219, 0.3);
        }

        .checkout-list {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .checkout-list h4 {
            background: #f8f9fa;
            padding: 15px 20px;
            margin: 0;
            border-bottom: 1px solid #e9ecef;
            color: #2c3e50;
        }

        .tool-item {
            display: flex;
            align-items: center;
            padding: 18px 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s;
        }

        .tool-item:last-child {
            border-bottom: none;
        }

        .tool-item:hover {
            background: #f8f9fa;
        }

        .tool-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            margin-right: 15px;
        }

        .tool-info {
            flex: 1;
        }

        .tool-name {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 4px;
            font-size: 16px;
        }

        .tool-code {
            font-size: 13px;
            color: #888;
            font-family: monospace;
            background: #f1f3f4;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
        }

        .remove-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .remove-btn:hover {
            background: #c82333;
            transform: scale(1.05);
        }

        .empty-list {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-list .icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Return Screen */
        .return-section {
            background: #fff5f5;
            border: 2px dashed #ff6b6b;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            text-align: center;
        }

        .return-button {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-width: 200px;
            justify-content: center;
        }

        .return-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 107, 107, 0.3);
        }

        /* Scanner Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 25px;
            border-radius: 15px;
            max-width: 350px;
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .close-btn {
            float: right;
            font-size: 28px;
            cursor: pointer;
            color: #888;
            line-height: 1;
        }

        .scanner-preview {
            width: 100%;
            height: 200px;
            background: #f0f0f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px 0;
            position: relative;
            overflow: hidden;
            border: 2px solid #4CAF50;
        }

        .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80%;
            height: 3px;
            background: #4CAF50;
            animation: scan 2s infinite;
            box-shadow: 0 0 10px #4CAF50;
        }

        @keyframes scan {
            0%, 100% { opacity: 0; }
            50% { opacity: 1; }
        }

        .simulate-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .simulate-btn:hover {
            background: #45a049;
            transform: translateY(-1px);
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            text-align: center;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            text-align: center;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div class="header">
            <button class="back-button" onclick="goBack()">←</button>
            <h1>TechStore</h1>
            <div class="subtitle">Daily Mission Tool Management</div>
        </div>

        <div class="nav-bar" id="nav-bar">
            <div class="nav-item active" onclick="showToolsScreen()">
                📦 Check Out Tools
            </div>
            <div class="nav-item" onclick="showReturnScreen()">
                🔄 Return Tools
                <span class="badge" id="return-badge">0</span>
            </div>
        </div>

        <div id="missions-screen" class="screen active">
            <div class="date-header">
                <div class="date" id="current-date"></div>
                <div class="day">Today's Missions</div>
            </div>
            <div id="missions-list">
                </div>
        </div>

        <div id="tools-screen" class="screen">
            <div class="mission-info" id="selected-mission-info">
                <div class="mission-id" id="mission-id-display">Select a Mission</div>
                <div class="mission-title" id="mission-title-display">Choose your mission first</div>
            </div>

            <div class="scan-section">
                <h3>Add Tools & Vehicles</h3>
                <button class="scan-button" onclick="openScanner('tool')">
                    📷 Scan Tool Barcode
                </button>
                <button class="scan-button vehicle" onclick="openScanner('vehicle')">
                    🚗 Scan Vehicle Code
                </button>
            </div>

            <div class="checkout-list">
                <h4>Checked Out Items <span id="checkout-count">(0)</span></h4>
                <div id="checkout-items">
                    <div class="empty-list">
                        <div class="icon">📦</div>
                        <p>No items checked out yet.<br>Scan barcodes to add tools and vehicles.</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="return-screen" class="screen">
            <div class="return-section">
                <h3 style="color: #dc3545; margin-bottom: 15px;">Return Items</h3>
                <p style="color: #666; margin-bottom: 20px;">Scan items you want to return</p>
                <button class="return-button" onclick="openScanner('return')">
                    📷 Scan to Return
                </button>
            </div>

            <div class="checkout-list">
                <h4>Items Available for Return <span id="return-available-count">(0)</span></h4>
                <div id="return-items">
                    <div class="empty-list">
                        <div class="icon">🔄</div>
                        <p>No items to return.<br>Check out some tools first!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="scanner-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeScanner()">&times;</span>
            <h3 id="scanner-title">Scan Barcode</h3>
            <div class="scanner-preview">
                <div style="text-align: center; color: #666;">
                    📷<br>
                    <strong>Camera Preview</strong><br>
                    <small>Point camera at barcode</small>
                </div>
                <div class="scanner-overlay"></div>
            </div>
            <div style="text-align: center; margin-top: 15px;">
                <button class="simulate-btn" onclick="simulateScan()">
                    🎯 Simulate Scan (Demo)
                </button>
            </div>
            <div id="scan-result"></div>
        </div>
    </div>

    <script>
        let currentMission = null;
        let currentScanType = null;
        let checkedOutItems = [];

        // Sample tool database
        const toolDatabase = {
            'DMM-2025-001': { name: 'Digital Multimeter Pro', type: 'tool', icon: '🔧' },
            'WS-2025-045': { name: 'Professional Wire Strippers', type: 'tool', icon: '⚡' },
            'DRL-2025-012': { name: 'Cordless Drill Set', type: 'tool', icon: '🔨' },
            'LVL-2025-008': { name: 'Digital Level', type: 'tool', icon: '📏' },
            'PLR-2025-003': { name: 'Pipe Wrench Set', type: 'tool', icon: '🔧' },
            'VAN-2025-004': { name: 'Service Van #4', type: 'vehicle', icon: '🚗' },
            'TRK-2025-001': { name: 'Utility Truck #1', type: 'vehicle', icon: '🚛' },
            'VAN-2025-007': { name: 'Service Van #7', type: 'vehicle', icon: '🚐' }
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            // Set current date
            const today = new Date();
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric'
            };
            document.getElementById('current-date').textContent = today.toLocaleDateString('en-US', options);
            
            loadMissions();
        });
        
        function loadMissions() {
            fetch('technician-handler.php?action=get_missions')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderMissions(data.missions);
                    } else {
                        showMessage(data.message, 'error');
                    }
                })
                .catch(error => {
                    showMessage('Error loading missions.', 'error');
                });
        }
        
        function renderMissions(missions) {
            const missionsList = document.getElementById('missions-list');
            missionsList.innerHTML = '';
            if (missions.length === 0) {
                missionsList.innerHTML = '<p>No missions for today.</p>';
                return;
            }
            missions.forEach(mission => {
                const missionCard = `
                    <div class="mission-card" onclick="selectMission('${mission.id}', '${mission.title}', '${mission.location}', '${mission.priority}')">
                        <div class="mission-priority priority-${mission.priority}">${mission.priority.toUpperCase()}</div>
                        <div class="mission-id">${mission.id}</div>
                        <div class="mission-title">${mission.title}</div>
                        <div class="mission-details">
                            <span>⏱️ ${mission.duration} hours</span>
                            <span>👥 ${mission.technicians} technicians</span>
                        </div>
                        <div class="mission-location">
                            📍 ${mission.location}
                        </div>
                        <div class="mission-time">🕘 Scheduled: ${mission.time}</div>
                    </div>
                `;
                missionsList.innerHTML += missionCard;
            });
        }

        function selectMission(missionId, title, location, priority) {
            currentMission = {
                id: missionId,
                title: title,
                location: location,
                priority: priority
            };

            // Update mission display
            document.getElementById('mission-id-display').textContent = missionId;
            document.getElementById('mission-title-display').textContent = title;

            // Show navigation and tools screen
            document.getElementById('nav-bar').classList.add('show');
            document.querySelector('.back-button').style.display = 'block';
            
            showToolsScreen();
            showMessage(`Mission ${missionId} selected!`, 'success');
        }

        function showToolsScreen() {
            showScreen('tools');
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            document.querySelectorAll('.nav-item')[0].classList.add('active');
        }

        function showReturnScreen() {
            showScreen('return');
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            document.querySelectorAll('.nav-item')[1].classList.add('active');
            updateReturnScreen();
        }

        function showScreen(screenName) {
            document.querySelectorAll('.screen').forEach(screen => {
                screen.classList.remove('active');
            });
            document.getElementById(screenName + '-screen').classList.add('active');
        }

        function goBack() {
            // Reset to missions screen
            document.getElementById('nav-bar').classList.remove('show');
            document.querySelector('.back-button').style.display = 'none';
            showScreen('missions');
            currentMission = null;
            
            // Clear checkout items
            checkedOutItems = [];
            updateCheckoutDisplay();
            updateReturnBadge();
        }

        function openScanner(type) {
            if (!currentMission && type !== 'return') {
                showMessage('Please select a mission from the main screen first!', 'error');
                return;
            }

            currentScanType = type;
            document.getElementById('scanner-modal').style.display = 'block';
            
            const titles = {
                'tool': '🔧 Scan Tool Barcode',
                'vehicle': '🚗 Scan Vehicle Code',
                'return': '🔄 Scan Item to Return'
            };
            
            document.getElementById('scanner-title').textContent = titles[type];
        }

        function closeScanner() {
            document.getElementById('scanner-modal').style.display = 'none';
            document.getElementById('scan-result').innerHTML = '';
        }

        function simulateScan() {
            const sampleCodes = {
                'tool': ['DMM-2025-001', 'WS-2025-045', 'DRL-2025-012', 'LVL-2025-008', 'PLR-2025-003'],
                'vehicle': ['VAN-2025-004', 'TRK-2025-001', 'VAN-2025-007'],
                'return': checkedOutItems.map(item => item.code)
            };
            
            const codes = sampleCodes[currentScanType] || sampleCodes['tool'];
            if (codes.length === 0) {
                document.getElementById('scan-result').innerHTML = '<div class="error-message">❌ No items available to scan</div>';
                return;
            }
            
            const randomCode = codes[Math.floor(Math.random() * codes.length)];
            processScannedCode(randomCode);
        }

        function processScannedCode(code) {
            const resultDiv = document.getElementById('scan-result');
            
            if (currentScanType === 'return') {
                const item = checkedOutItems.find(item => item.code === code);
                if (item) {
                    returnItem(item);
                    resultDiv.innerHTML = '<div class="success-message">✅ Item returned successfully!</div>';
                } else {
                    resultDiv.innerHTML = '<div class="error-message">❌ Item not found in your checked out items</div>';
                }
            } else {
                const item = toolDatabase[code];
                if (item) {
                    const isAlreadyCheckedOut = checkedOutItems.some(checkedItem => checkedItem.code === code);
                    if (isAlreadyCheckedOut) {
                        resultDiv.innerHTML = '<div class="error-message">❌ Item already checked out</div>';
                    } else {
                        addItemToCheckout(code, item);
                        resultDiv.innerHTML = '<div class="success-message">✅ Item added successfully!</div>';
                    }
                } else {
                    resultDiv.innerHTML = '<div class="error-message">❌ Item not found in database</div>';
                }
            }
            
            setTimeout(closeScanner, 2000);
        }

        function addItemToCheckout(code, item) {
            checkedOutItems.push({
                code: code,
                name: item.name,
                type: item.type,
                icon: item.icon
            });
            
            updateCheckoutDisplay();
            updateReturnBadge();
        }

        function removeItem(code) {
            checkedOutItems = checkedOutItems.filter(item => item.code !== code);
            updateCheckoutDisplay();
            updateReturnBadge();
            showMessage('Item removed from checkout list', 'success');
        }

        function returnItem(item) {
            checkedOutItems = checkedOutItems.filter(i => i.code !== item.code);
            updateCheckoutDisplay();
            updateReturnScreen();
            updateReturnBadge();
        }

        function updateCheckoutDisplay() {
            const container = document.getElementById('checkout-items');
            const countSpan = document.getElementById('checkout-count');
            
            countSpan.textContent = `(${checkedOutItems.length})`;
            
            if (checkedOutItems.length === 0) {
                container.innerHTML = `
                    <div class="empty-list">
                        <div class="icon">📦</div>
                        <p>No items checked out yet.<br>Scan barcodes to add tools and vehicles.</p>
                    </div>
                `;
            } else {
                container.innerHTML = checkedOutItems.map(item => `
                    <div class="tool-item">
                        <div class="tool-icon">${item.icon}</div>
                        <div class="tool-info">
                            <div class="tool-name">${item.name}</div>
                            <div class="tool-code">${item.code}</div>
                        </div>
                        <button class="remove-btn" onclick="removeItem('${item.code}')">Remove</button>
                    </div>
                `).join('');
            }
        }

        function updateReturnScreen() {
            const container = document.getElementById('return-items');
            const countSpan = document.getElementById('return-available-count');
            
            countSpan.textContent = `(${checkedOutItems.length})`;
            
            if (checkedOutItems.length === 0) {
                container.innerHTML = `
                    <div class="empty-list">
                        <div class="icon">🔄</div>
                        <p>No items to return.<br>Check out some tools first!</p>
                    </div>
                `;
            } else {
                container.innerHTML = checkedOutItems.map(item => `
                    <div class="tool-item">
                        <div class="tool-icon">${item.icon}</div>
                        <div class="tool-info">
                            <div class="tool-name">${item.name}</div>
                            <div class="tool-code">${item.code}</div>
                        </div>
                        <button class="return-button" onclick="returnItem({code:'${item.code}', name:'${item.name}', icon:'${item.icon}'})">Return</button>
                    </div>
                `).join('');
            }
        }

        function updateReturnBadge() {
            document.getElementById('return-badge').textContent = checkedOutItems.length;
        }

        function showMessage(message, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = type === 'success' ? 'success-message' : 'error-message';
            messageDiv.textContent = message;
            messageDiv.style.position = 'fixed';
            messageDiv.style.top = '20px';
            messageDiv.style.left = '50%';
            messageDiv.style.transform = 'translateX(-50%)';
            messageDiv.style.zIndex = '1001';
            messageDiv.style.maxWidth = '350px';
            messageDiv.style.margin = '0 20px';
            
            document.body.appendChild(messageDiv);
            
            setTimeout(() => {
                if (document.body.contains(messageDiv)) {
                    document.body.removeChild(messageDiv);
                }
            }, 3000);
        }
    </script>
</body>
</html>
