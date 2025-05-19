<?php
// 1. Ensure no output before this PHP block
// No spaces, blank lines, or BOM before <?php

// 2. Include session management, which starts the session and defines requireLogin()
require_once 'session-management.php';

// 3. Require login - This will redirect the user to index.php and exit
// if they are not logged in.
requireLogin();

// 4. If the script reaches this point, the user IS logged in.
// Now you can safely output content.
$user = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Congés - Gestion des Ouvriers</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <!-- Original CSS styles from timesheet.html -->
    <style>
        /* Basic Reset and Font */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            /* Apple-like font stack */
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
        }

        body {
            /* Light gray background */
            background-color: #f5f5f7;
            color: #1d1d1f; /* Default dark text */
            -webkit-font-smoothing: antialiased; /* Smoother fonts on WebKit */
            -moz-osx-font-smoothing: grayscale; /* Smoother fonts on Firefox */
        }

        /* Card Styling */
        .card {
            background-color: #ffffff;
            border-radius: 12px; /* More rounded corners */
            /* Subtle shadow */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 25px; /* Increased padding */
            margin-bottom: 25px; /* Consistent margin */
            border: 1px solid #e5e5e5; /* Very subtle border */
        }

        h2 {
            margin-bottom: 25px; /* Increased margin */
            color: #1d1d1f; /* Dark text */
            font-size: 28px; /* Larger heading */
            font-weight: 600;
        }
        h3 {
             margin-bottom: 20px;
             font-size: 18px;
             font-weight: 600;
             color: #1d1d1f;
        }

        /* Table Styling */
        .table-container {
            overflow-x: auto;
            border: 1px solid #e5e5e5; /* Border around table container */
            border-radius: 8px; /* Rounded corners */
            margin-top: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px; /* Adjusted min-width */
        }

        table th, table td {
            padding: 14px 16px; /* Increased padding */
            text-align: left;
            border-bottom: 1px solid #e5e5e5; /* Lighter border */
            font-size: 14px;
            color: #1d1d1f;
        }
        table td { color: #555; } /* Slightly lighter text for data */

        table th {
            background-color: #f9f9f9; /* Very light gray header */
            font-weight: 600; /* Bolder header */
            color: #333; /* Darker header text */
            border-bottom-width: 2px; /* Thicker bottom border for header */
        }

        table tr:last-child td {
             border-bottom: none; /* Remove border from last row */
        }

        table tr:hover {
            background-color: #f5f5f7; /* Subtle hover */
        }

        /* Form Styling */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #1d1d1f;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d2d2d7;
            border-radius: 8px;
            font-size: 14px;
            color: #1d1d1f;
            background-color: #ffffff;
        }

        .form-group input[type="file"] {
            padding: 8px;
            background-color: #f5f5f7;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #007aff;
            box-shadow: 0 0 0 2px rgba(0, 122, 255, 0.2);
        }

        /* Button Styling */
        button, .btn-primary, .btn-success, .btn-danger, .btn-warning {
            padding: 12px 24px; /* Generous padding */
            border: none;
            border-radius: 8px; /* Rounded corners */
            cursor: pointer;
            font-weight: 600; /* Bolder font */
            font-size: 15px;
            transition: background-color 0.2s ease-in-out, opacity 0.2s ease-in-out;
            margin-bottom: 10px;
            line-height: 1.2; /* Ensure text vertical alignment */
        }

        .btn-primary { background-color: #007aff; color: white; }
        .btn-primary:hover { background-color: #0056b3; } /* Darker blue on hover */

        .btn-success { background-color: #34c759; color: white; } /* Apple green */
        .btn-success:hover { background-color: #2ca048; } /* Darker green */

        .btn-danger { background-color: #ff3b30; color: white; } /* Apple red */
        .btn-danger:hover { background-color: #d63027; } /* Darker red */

        .btn-warning { background-color: #ff9500; color: white; } /* Apple orange */
        .btn-warning:hover { background-color: #d97e00; } /* Darker orange */

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            cursor: pointer;
        }

        .file-input-wrapper input[type="file"] {
            font-size: 100px;
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
        }

        .file-name {
            display: inline-block;
            margin-left: 10px;
            font-size: 14px;
            color: #666;
        }

        /* Badge */
        .badge {
            display: inline-block; min-width: 18px; /* Slightly wider */ padding: 3px 7px; font-size: 11px; font-weight: 700; line-height: 1; color: #fff; text-align: center; white-space: nowrap; vertical-align: baseline; /* Better alignment */ background-color: #ff3b30; /* Apple red badge */ border-radius: 9px; /* Pill shape */ margin-left: 6px;
        }

        /* Status Tags */
        .status-tag {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }
        .status-pending {
            background-color: #ffcc00;
            color: #664d00;
        }
        .status-approved {
            background-color: #34c759;
            color: white;
        }
        .status-rejected {
            background-color: #ff3b30;
            color: white;
        }
        .status-cancelled {
            background-color: #8e8e93;
            color: white;
        }

        /* Alert Messages */
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border: 1px solid transparent;
            font-size: 14px;
            text-align: center;
        }
        .alert-success {
            background-color: rgba(52, 199, 89, 0.1);
            border-color: rgba(52, 199, 89, 0.3);
            color: #2ca048;
        }
        .alert-error {
            background-color: rgba(255, 59, 48, 0.1);
            border-color: rgba(255, 59, 48, 0.3);
            color: #d63027;
        }
        .alert-info {
            background-color: rgba(0, 122, 255, 0.1);
            border-color: rgba(0, 122, 255, 0.3);
            color: #0056b3;
        }

        /* Tab Navigation for Leave History */
        .tabs-container {
            margin-bottom: 20px;
        }

        .tabs-nav {
            display: flex;
            background-color: #f5f5f7;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .tab-button {
            padding: 12px 24px;
            background-color: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            color: #6e6e73;
            flex: 1;
            text-align: center;
            transition: all 0.3s ease;
        }

        .tab-button.active {
            border-bottom-color: #007aff;
            color: #007aff;
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Document Preview */
        .document-preview {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            background-color: #f5f5f7;
            border-radius: 6px;
            font-size: 13px;
            color: #007aff;
            text-decoration: none;
            gap: 8px;
        }

        .document-preview:hover {
            background-color: #e5e5ea;
        }

        .document-icon {
            color: #007aff;
            font-size: 16px;
        }

        /* Modal Styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #ffffff;
            margin: 8% auto;
            padding: 30px;
            border-radius: 14px;
            width: 90%;
            max-width: 700px;
            position: relative;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }

        .close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 30px;
            font-weight: 300;
            cursor: pointer;
            color: #aaa;
            transition: color 0.2s;
        }

        .close:hover {
            color: #333;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .container { padding: 20px; }
            .header-content { flex-direction: column; text-align: center; gap: 10px; }
            .header-content h1 { font-size: 22px; }
            nav ul { justify-content: center; gap: 8px 15px; }
            nav a { font-size: 13px; padding: 5px 10px; }
            h2 { font-size: 24px; }
            .card { padding: 20px; border-radius: 10px; }
            .form-grid { grid-template-columns: 1fr; }
            .modal-content { width: 95%; margin: 5% auto; padding: 20px; }
            .tabs-nav { flex-direction: column; }
            table th, table td { padding: 12px 14px; font-size: 13px; }
        }

        @media (max-width: 480px) {
            .container { padding: 15px; }
            h2 { font-size: 22px; }
            .card { padding: 15px; }
            table th, table td { padding: 10px 12px; font-size: 12px; }
            .modal-content { padding: 15px; }
            nav ul { gap: 5px 10px; }
            nav a { font-size: 12px; padding: 4px 8px; }
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
    <div class="container-fluid">
        <div id="conges">
            <h2>Congés</h2>
            
            <!-- Status messages area -->
            <div id="status-message" style="display: none;"></div>

            <!-- Tabs for Leave Management -->
            <div class="tabs-container">
                <div class="tabs-nav">
                   <?php if ($user['role'] == 'admin'): ?>
        <button class="tab-button" onclick="openTab('admin-approvals')">Administration</button>
        <?php endif; ?> 
        <button class="tab-button active" onclick="openTab('new-leave')">Nouvelle Demande</button>
        <button class="tab-button" onclick="openTab('leave-history')">Historique des Demandes</button>
        
    </div>
                
                <!-- New Leave Request Tab -->
                <div id="new-leave" class="tab-content active">
                    <div class="card">
                        <h3>Nouvelle Demande de Congé</h3>
                        <form id="conge-form" method="POST" enctype="multipart/form-data">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="date-debut">Date de début</label>
                                    <input type="date" id="date-debut" name="date_debut" required>
                                </div>
                                <div class="form-group">
                                    <label for="date-fin">Date de fin</label>
                                    <input type="date" id="date-fin" name="date_fin" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="type-conge">Type de Congé</label>
                                <select id="type-conge" name="type_conge" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="cp">Congés Payés</option>
                                    <option value="rtt">RTT</option>
                                    <option value="sans-solde">Congé Sans Solde</option>
                                    <option value="special">Congé Spécial</option>
                                    <option value="maladie">Congé Maladie</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="commentaire">Commentaire (optionnel)</label>
                                <textarea id="commentaire" name="commentaire" placeholder="Ajouter un commentaire..."></textarea>
                            </div>

                            <div class="form-group">
    <label for="document">Joindre un document (optionnel)</label>
    <div class="file-input-wrapper">
        <button type="button" class="btn-success">Choisir un fichier</button>
        <input type="file" id="document" name="document" accept=".pdf,.jpg,.jpeg,.png">
    </div>
    <div id="file-name" class="file-name"></div>
</div>

                            <button type="submit" class="btn-primary">Soumettre la Demande</button>
                        </form>
                    </div>
                </div>
                
                <!-- Leave History Tab -->
                <div id="leave-history" class="tab-content">
                    <div class="card">
                        <h3>Historique des Demandes de Congés</h3>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Dates</th>
                                        <th>Type de Congé</th>
                                        <th>Durée</th>
                                        <th>Statut</th>
                                        <th>Document</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="conges-history">
                                    <!-- Leave history data will be loaded here via JavaScript -->
                                    <tr>
                                        <td colspan="6" style="text-align: center;">Chargement des données...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php if ($user['role'] == 'admin'): ?>
    <!-- Admin Approvals Tab (Only visible to admins) -->
    <div id="admin-approvals" class="tab-content">
        <div class="card">
            <h3>Demandes en Attente</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Dates</th>
                            <th>Type de Congé</th>
                            <th>Durée</th>
                            <th>Document</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="pending-requests">
                        <!-- Pending requests will be loaded here via JavaScript -->
                        <tr>
                            <td colspan="6" style="text-align: center;">Chargement des données...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
                <!-- Leave Stats Tab -->
                <div id="leave-stats" class="tab-content">
                    <div class="card">
                        <h3>Solde de Congés</h3>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Type de Congé</th>
                                        <th>Acquis</th>
                                        <th>Pris</th>
                                        <th>En Attente</th>
                                        <th>Solde</th>
                                    </tr>
                                </thead>
                                <tbody id="conges-stats">
                                    <!-- Leave stats data will be loaded here via JavaScript -->
                                    <tr>
                                        <td colspan="5" style="text-align: center;">Chargement des données...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Details Modal -->
            <div id="details-modal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="document.getElementById('details-modal').style.display='none'">&times;</span>
                    <h3 id="modal-title">Détails de la Demande</h3>
                    <div id="modal-content-details">
                        <!-- Details will be inserted here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php include('footer.php'); ?>
    <script>
        // Tab navigation functionality
        function openTab(tabId) {
    // Hide all tab contents
    const tabContents = document.getElementsByClassName('tab-content');
    for (let i = 0; i < tabContents.length; i++) {
        tabContents[i].classList.remove('active');
    }
    
    // Deactivate all tab buttons
    const tabButtons = document.getElementsByClassName('tab-button');
    for (let i = 0; i < tabButtons.length; i++) {
        tabButtons[i].classList.remove('active');
    }
    
    // Show the selected tab content
    document.getElementById(tabId).classList.add('active');
    
    // Activate the clicked tab button (find the button that calls this tab)
    const buttons = document.querySelectorAll('.tab-button');
    buttons.forEach(button => {
        if (button.getAttribute('onclick').includes(tabId)) {
            button.classList.add('active');
        }
    });
    
    // Load appropriate data based on tab
    if (tabId === 'leave-history') {
        loadCongesHistory();
    } else if (tabId === 'admin-approvals') {
        loadPendingRequests();
    }
}
        
        // Show status message function
        function showStatusMessage(message, type = 'info') {
            const statusDiv = document.getElementById('status-message');
            if (!statusDiv) return;
            
            // Set message and class
            statusDiv.innerHTML = message;
            statusDiv.className = `alert alert-${type}`;
            statusDiv.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                statusDiv.style.display = 'none';
            }, 5000);
        }
        
        // Function to make AJAX requests
        function makeAjaxRequest(action, data, callback) {
            // Create FormData object
            const formData = new FormData();
            formData.append('action', action);
            
            // Add other data to FormData
            for (const key in data) {
                if (data.hasOwnProperty(key)) {
                    formData.append(key, data[key]);
                }
            }
            
            // Create and send the request
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'conges-handler.php', true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            callback(null, response);
                        } catch (e) {
                            callback('Erreur de parsing JSON: ' + e.message);
                        }
                    } else {
                        callback('Erreur réseau: ' + xhr.status);
                    }
                }
            };
            xhr.send(formData);
        }
        
        // Load leave history data
        function loadCongesHistory() {
            const tableBody = document.getElementById('conges-history');
            if (!tableBody) return;
            
            // Show loading state
            tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Chargement des données...</td></tr>';
            
            // Make AJAX request to get history
            makeAjaxRequest('get_history', {}, function(error, response) {
                if (error) {
                    tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: red;">Erreur: ' + error + '</td></tr>';
                    return;
                }
                
                if (response.status === "success" && Array.isArray(response.data)) {
                    if (response.data.length === 0) {
                        tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Aucune demande de congé trouvée</td></tr>';
                        return;
                    }
                    
                    // Clear the table
                    tableBody.innerHTML = '';
                    
                    // Add rows for each entry
                    response.data.forEach(function(entry) {
                        const row = document.createElement('tr');
                        
                        // Format the document link if exists
                        let documentLink = 'Aucun';
                        if (entry.document && entry.document !== 'null' && entry.document !== null) {
                            documentLink = `<a href="${entry.document}" class="document-preview" target="_blank">
                                <span class="document-icon">📄</span> Voir
                            </a>`;
                        }
                        
                        // Format the status tag
                        let statusTag = '';
                        switch(entry.status) {
                            case 'pending':
                                statusTag = '<span class="status-tag status-pending">En attente</span>';
                                break;
                            case 'approved':
                                statusTag = '<span class="status-tag status-approved">Approuvé</span>';
                                break;
                            case 'rejected':
                                statusTag = '<span class="status-tag status-rejected">Refusé</span>';
                                break;
                            case 'cancelled':
                                statusTag = '<span class="status-tag status-cancelled">Annulé</span>';
                                break;
                            default:
                                statusTag = '<span class="status-tag status-pending">En attente</span>';
                        }
                        
                        // Format the type of leave
                        let typeConge = '';
                        switch(entry.type_conge) {
                            case 'cp':
                                typeConge = 'Congés Payés';
                                break;
                            case 'rtt':
                                typeConge = 'RTT';
                                break;
                            case 'sans-solde':
                                typeConge = 'Sans Solde';
                                break;
                            case 'special':
                                typeConge = 'Congé Spécial';
                                break;
                            case 'maladie':
                                typeConge = 'Congé Maladie';
                                break;
                            default:
                                typeConge = entry.type_conge;
                        }
                        
                        // Format the HTML content for the row
                        row.innerHTML = `
                            <td>${entry.date_debut} au ${entry.date_fin}</td>
                            <td>${typeConge}</td>
                            <td>${entry.duree} jour(s)</td>
                            <td>${statusTag}</td>
                            <td>${documentLink}</td>
                            <td>
                                <button class="btn-primary" onclick="showDetails(${entry.id})">Détails</button>
                                ${entry.status === 'pending' ? `<button class="btn-danger" onclick="cancelRequest(${entry.id})">Annuler</button>` : ''}
                            </td>
                        `;
                        
                        tableBody.appendChild(row);
                    });
                } else {
                    tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: red;">Erreur: ' + response.message + '</td></tr>';
                }
            });
        }
        
        // Load leave statistics
        function loadCongesStats() {
            const tableBody = document.getElementById('conges-stats');
            if (!tableBody) return;
            
            // Show loading state
            tableBody.innerHTML = '<tr><td colspan="5" style="text-align: center;">Chargement des données...</td></tr>';
            
            // Make AJAX request to get stats
            makeAjaxRequest('get_stats', {}, function(error, response) {
                if (error) {
                    tableBody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: red;">Erreur: ' + error + '</td></tr>';
                    return;
                }
                
                if (response.status === "success" && Array.isArray(response.data)) {
                    if (response.data.length === 0) {
                        tableBody.innerHTML = '<tr><td colspan="5" style="text-align: center;">Aucune donnée disponible</td></tr>';
                        return;
                    }
                    
                    // Clear the table
                    tableBody.innerHTML = '';
                    
                    // Add rows for each entry
                    response.data.forEach(function(entry) {
                        const row = document.createElement('tr');
                        
                        // Format the type of leave
                        let typeConge = '';
                        switch(entry.type) {
                            case 'cp':
                                typeConge = 'Congés Payés';
                                break;
                            case 'rtt':
                                typeConge = 'RTT';
                                break;
                            case 'sans-solde':
                                typeConge = 'Sans Solde';
                                break;
                            case 'special':
                                typeConge = 'Congé Spécial';
                                break;
                            case 'maladie':
                                typeConge = 'Congé Maladie';
                                break;
                            default:
                                typeConge = entry.type;
                        }
                        
                        row.innerHTML = `
                            <td>${typeConge}</td>
                            <td>${entry.acquis} jour(s)</td>
                            <td>${entry.pris} jour(s)</td>
                            <td>${entry.pending} jour(s)</td>
                            <td><strong>${entry.solde} jour(s)</strong></td>
                        `;
                        
                        tableBody.appendChild(row);
                    });
                } else {
                    tableBody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: red;">Erreur: ' + response.message + '</td></tr>';
                }
            });
        }
        
        // Show leave request details
        function showDetails(leaveId) {
            // Make AJAX request to get details
            makeAjaxRequest('get_details', { leave_id: leaveId }, function(error, response) {
                if (error) {
                    showStatusMessage('Erreur: ' + error, 'error');
                    return;
                }
                
                if (response.status === "success" && response.data) {
                    const modal = document.getElementById('details-modal');
                    const modalContent = document.getElementById('modal-content-details');
                    
                    // Format the type of leave
                    let typeConge = '';
                    switch(response.data.type_conge) {
                        case 'cp':
                            typeConge = 'Congés Payés';
                            break;
                        case 'rtt':
                            typeConge = 'RTT';
                            break;
                        case 'sans-solde':
                            typeConge = 'Sans Solde';
                            break;
                        case 'special':
                            typeConge = 'Congé Spécial';
                            break;
                        case 'maladie':
                            typeConge = 'Congé Maladie';
                            break;
                        default:
                            typeConge = response.data.type_conge;
                    }
                    
                    // Format the status
                    let status = '';
                    switch(response.data.status) {
                        case 'pending':
                            status = '<span class="status-tag status-pending">En attente</span>';
                            break;
                        case 'approved':
                            status = '<span class="status-tag status-approved">Approuvé</span>';
                            break;
                        case 'rejected':
                            status = '<span class="status-tag status-rejected">Refusé</span>';
                            break;
                        case 'cancelled':
                            status = '<span class="status-tag status-cancelled">Annulé</span>';
                            break;
                        default:
                            status = '<span class="status-tag status-pending">En attente</span>';
                    }
                    
                    // Format document link if exists
                    let documentLink = 'Aucun document joint';
                    if (response.data.document && response.data.document !== 'null' && response.data.document !== null) {
                        documentLink = `<a href="${response.data.document}" class="document-preview" target="_blank">
                            <span class="document-icon">📄</span> Voir le document
                        </a>`;
                    }
                    
                    // Format the content
                    modalContent.innerHTML = `
                        <p><strong>Date de début:</strong> ${response.data.date_debut}</p>
                        <p><strong>Date de fin:</strong> ${response.data.date_fin}</p>
                        <p><strong>Type de congé:</strong> ${typeConge}</p>
                        <p><strong>Durée:</strong> ${response.data.duree} jour(s)</p>
                        <p><strong>Statut:</strong> ${status}</p>
                        <p><strong>Date de demande:</strong> ${response.data.date_demande}</p>
                        <p><strong>Commentaire:</strong> ${response.data.commentaire || 'Aucun commentaire'}</p>
                        <p><strong>Document:</strong> ${documentLink}</p>
                    `;
                    
                    if (response.data.reponse_commentaire) {
                        modalContent.innerHTML += `
                            <p><strong>Commentaire de réponse:</strong> ${response.data.reponse_commentaire}</p>
                            <p><strong>Date de réponse:</strong> ${response.data.date_reponse}</p>
                        `;
                    }
                    
                    // Show the modal
                    modal.style.display = "block";
                } else {
                    showStatusMessage('Erreur: ' + response.message, 'error');
                }
            });
        }
        // Load pending leave requests (for admin)
function loadPendingRequests() {
    const tableBody = document.getElementById('pending-requests');
    if (!tableBody) return;
    
    // Show loading state
    tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Chargement des données...</td></tr>';
    
    // Make AJAX request to get pending requests
    makeAjaxRequest('get_pending_requests', {}, function(error, response) {
        if (error) {
            tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: red;">Erreur: ' + error + '</td></tr>';
            return;
        }
        
        if (response.status === "success" && Array.isArray(response.data)) {
            if (response.data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Aucune demande en attente</td></tr>';
                return;
            }
            
            // Clear the table
            tableBody.innerHTML = '';
            
            // Add rows for each entry
            response.data.forEach(function(entry) {
                const row = document.createElement('tr');
                
                // Format the document link if exists
                let documentLink = 'Aucun';
                if (entry.document && entry.document !== 'null' && entry.document !== null) {
                    documentLink = `<a href="${entry.document}" class="document-preview" target="_blank">
                        <span class="document-icon">📄</span> Voir
                    </a>`;
                }
                
                // Format the type of leave
                let typeConge = '';
                switch(entry.type_conge) {
                    case 'cp':
                        typeConge = 'Congés Payés';
                        break;
                    case 'rtt':
                        typeConge = 'RTT';
                        break;
                    case 'sans-solde':
                        typeConge = 'Sans Solde';
                        break;
                    case 'special':
                        typeConge = 'Congé Spécial';
                        break;
                    case 'maladie':
                        typeConge = 'Congé Maladie';
                        break;
                    default:
                        typeConge = entry.type_conge;
                }
                
                // Format the HTML content for the row
                row.innerHTML = `
                    <td>${entry.employee_name}</td>
                    <td>${entry.date_debut} au ${entry.date_fin}</td>
                    <td>${typeConge}</td>
                    <td>${entry.duree} jour(s)</td>
                    <td>${documentLink}</td>
                    <td>
                        <button class="btn-success" onclick="approveRequest(${entry.id})">Approuver</button>
                        <button class="btn-danger" onclick="rejectRequest(${entry.id})">Refuser</button>
                        <button class="btn-primary" onclick="showAdminDetails(${entry.id})">Détails</button>
                    </td>
                `;
                tableBody.appendChild(row);
            });
        } else {
            tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: red;">Erreur: ' + response.message + '</td></tr>';
        }
    });
}

// Function to approve a leave request
function approveRequest(leaveId) {
    console.log(leaveId);
    const commentaire = prompt("Commentaire pour l'approbation (optionnel):");
    
    // Make AJAX request to approve
    makeAjaxRequest('approve_request', { 
        leave_id: leaveId,
        commentaire: commentaire || ''
    }, function(error, response) {
        if (error) {
            showStatusMessage('Erreur: ' + error, 'error');
            return;
        }
        
        if (response.status === "success") {
            showStatusMessage(response.message, 'success');
            // Reload pending requests
            loadPendingRequests();
        } else {
            showStatusMessage('Erreur: ' + response.message, 'error');
        }
    });
}

// Function to reject a leave request
function rejectRequest(leaveId) {
    const commentaire = prompt("Motif du refus (obligatoire):");
    
    if (!commentaire) {
        showStatusMessage('Un motif de refus est requis.', 'error');
        return;
    }
    
    // Make AJAX request to reject
    makeAjaxRequest('reject_request', { 
        leave_id: leaveId,
        commentaire: commentaire
    }, function(error, response) {
        if (error) {
            showStatusMessage('Erreur: ' + error, 'error');
            return;
        }
        
        if (response.status === "success") {
            showStatusMessage(response.message, 'success');
            // Reload pending requests
            loadPendingRequests();
        } else {
            showStatusMessage('Erreur: ' + response.message, 'error');
        }
    });
}

// Show leave request details for admin
function showAdminDetails(leaveId) {
    // This is similar to showDetails but with potential additional admin information
    showDetails(leaveId);
}
        // Cancel a leave request
        function cancelRequest(leaveId) {
            if (confirm("Êtes-vous sûr de vouloir annuler cette demande de congé ?")) {
                // Make AJAX request to cancel
                makeAjaxRequest('cancel_request', { leave_id: leaveId }, function(error, response) {
                    if (error) {
                        showStatusMessage('Erreur: ' + error, 'error');
                        return;
                    }
                    
                    if (response.status === "success") {
                        showStatusMessage(response.message, 'success');
                        // Reload history table
                        loadCongesHistory();
                    } else {
                        showStatusMessage('Erreur: ' + response.message, 'error');
                    }
                });
            }
        }
        
        // Handle form submission
        document.getElementById('conge-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const dateDebut = document.getElementById('date-debut').value;
            const dateFin = document.getElementById('date-fin').value;
            const typeConge = document.getElementById('type-conge').value;
            const commentaire = document.getElementById('commentaire').value;
            const documentFile = document.getElementById('document').files[0];
            
            // Validate dates (make sure end date is not before start date)
            if (new Date(dateFin) < new Date(dateDebut)) {
                showStatusMessage('La date de fin ne peut pas être antérieure à la date de début.', 'error');
                return;
            }
            
            // Create FormData object
            const formData = new FormData();
            formData.append('action', 'submit_request');
            formData.append('date_debut', dateDebut);
            formData.append('date_fin', dateFin);
            formData.append('type_conge', typeConge);
            formData.append('commentaire', commentaire);
            
            if (documentFile) {
                formData.append('document', documentFile);
            }
            
            // Show loading state
            document.querySelector('#conge-form button[type="submit"]').disabled = true;
            document.querySelector('#conge-form button[type="submit"]').textContent = 'Envoi en cours...';
            
            // Send the request
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'conges-handler.php', true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    // Enable the button again
                    document.querySelector('#conge-form button[type="submit"]').disabled = false;
                    document.querySelector('#conge-form button[type="submit"]').textContent = 'Soumettre la Demande';
                    
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.status === "success") {
                                showStatusMessage(response.message, 'success');
                                // Reset the form
                                document.getElementById('conge-form').reset();
                                document.getElementById('file-name').textContent = '';
                                // Switch to history tab
                                openTab('leave-history');
                            } else {
                                showStatusMessage('Erreur: ' + response.message, 'error');
                            }
                        } catch (e) {
                            showStatusMessage('Erreur de parsing JSON: ' + e.message, 'error');
                        }
                    } else {
                        showStatusMessage('Erreur réseau: ' + xhr.status, 'error');
                    }
                }
            };
            xhr.send(formData);
        });
        
        // Update file name display when file is selected
        document.getElementById('document').addEventListener('change', function() {
            const fileName = this.files[0] ? this.files[0].name : '';
            document.getElementById('file-name').textContent = fileName;
        });
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('details-modal');
            if (event.target === modal) {
                modal.style.display = "none";
            }
        }
        
        // Initialize the page by loading leave history
        // This happens when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Open the first tab by default
            openTab('new-leave');
        });
    </script>
</body>
</html>
