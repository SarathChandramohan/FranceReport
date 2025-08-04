<?php
require_once 'session-management.php';
requireLogin();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pointage - <?php echo htmlspecialchars($user['full_name'] ?? 'Employé'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #007bff;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --text-color: #212529;
            --text-muted: #6c757d;
            --bg-color: #eef2f5;
            --card-bg: #ffffff;
            --border-color: #dee2e6;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            --border-radius: 12px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
            padding-top: 0 !important;
            margin-top: 0 !important;
        }

        .container-fluid {
            padding: 20px 15px;
            max-width: 1200px;
            margin: 0 auto;
        }

        h2, h3 {
            color: var(--dark-color);
            font-weight: 600;
        }
        h2 { font-size: 1.75rem; margin-bottom: 20px; }
        h3 { font-size: 1.25rem; margin-bottom: 15px; }

        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 25px;
            margin-bottom: 30px;
        }

        /* Clock-in Section */
        .clock-section {
            display: flex;
            justify-content: center;
        }
        
        .clock-card {
            width: 100%;
            max-width: 480px;
            text-align: center;
        }

        .clock-display {
            font-size: 3.5rem;
            font-weight: 300;
            color: var(--dark-color);
            margin-bottom: 20px;
            letter-spacing: 2px;
        }

        /* Location Info */
        #location-info {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        #location-info.in_range { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; }
        #location-info.out_of_range { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; }
        #location-info.pending { color: #856404; background-color: #fff3cd; border: 1px solid #ffeeba; }
        #refresh-location {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            font-size: 1rem;
            padding: 5px;
        }
        #refresh-location:hover { color: var(--primary-color); }

        /* Mission Selection */
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-muted);
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            font-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: var(--light-color);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }
        #mission-comment {
            resize: vertical;
        }

        /* Buttons */
        .clock-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .btn {
            padding: 15px;
            font-size: 1rem;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: var(--text-muted) !important;
        }
        .btn-entree { background-color: var(--success-color); color: white; grid-column: 1 / -1; }
        .btn-entree:hover:not(:disabled) { background-color: #218838; }
        .btn-pause { background-color: var(--warning-color); color: var(--dark-color); }
        .btn-pause:hover:not(:disabled) { background-color: #e0a800; }
        .btn-sortie { background-color: var(--danger-color); color: white; }
        .btn-sortie:hover:not(:disabled) { background-color: #c82333; }
        
        /* History Table */
        .table-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        th, td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        tbody tr:hover {
            background-color: #f1f5f8;
        }
        .total-duration {
            font-weight: bold;
            color: var(--primary-color);
        }

        /* Responsive Design */
        @media (max-width: 576px) {
            .clock-display { font-size: 2.5rem; }
            .btn { font-size: 0.9rem; padding: 12px; }
            h2 { font-size: 1.5rem; }
            .card { padding: 20px; }
        }

        /* Status Message */
        #status-message {
            display: none;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }
        #status-message.alert-success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; }
        #status-message.alert-error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; }
        #status-message.alert-info { color: #0c5460; background-color: #d1ecf1; border: 1px solid #bee5eb; }

    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid">
        <h2>Pointage</h2>
        <div id="status-message"></div>

        <div class="row">
            <div class="col-lg-5">
                <div class="card clock-card">
                    <div class="clock-display" id="current-time">--:--:--</div>
                    
                    <div id="location-info" class="pending">
                        <i class="fas fa-spinner fa-spin"></i> Vérification du lieu...
                    </div>

                    <div class="form-group">
                        <label for="mission-select"><i class="fas fa-tasks"></i> Mission du jour</label>
                        <select id="mission-select" class="form-control">
                            <option value="">-- Choisissez une mission --</option>
                            <option value="without">Sans mission</option>
                        </select>
                    </div>

                    <div class="form-group" id="comment-group" style="display: none;">
                        <label for="mission-comment"><i class="fas fa-comment"></i> Commentaire</label>
                        <textarea id="mission-comment" class="form-control" placeholder="Raison du pointage sans mission..."></textarea>
                    </div>

                    <div class="clock-buttons">
                        <button class="btn btn-entree" id="btn-entree" onclick="enregistrerPointage('record_entry')">
                            <i class="fas fa-sign-in-alt"></i> Enregistrer l'Entrée
                        </button>
                        <button class="btn btn-pause" id="btn-pause">
                            <i class="fas fa-coffee"></i> Ajouter Pause
                        </button>
                        <button class="btn btn-sortie" id="btn-sortie" onclick="enregistrerPointage('record_exit')">
                            <i class="fas fa-sign-out-alt"></i> Enregistrer la Sortie
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card">
                    <h3><i class="fas fa-history"></i> Historique des Pointages</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Entrée</th>
                                    <th>Sortie</th>
                                    <th>Pause</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody id="timesheet-history">
                                <!-- History rows will be inserted here by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include('footer.php'); ?>
    
<script>
    let currentLatitude = null;
    let currentLongitude = null;
    let isInRange = false;
    let timeSheetStatus = { has_entry: false, has_exit: false };

    function updateClock() {
        document.getElementById('current-time').textContent = new Date().toLocaleTimeString('fr-FR');
    }

    function showStatusMessage(message, type = 'info') {
        const statusDiv = document.getElementById('status-message');
        statusDiv.textContent = message;
        statusDiv.className = `alert-${type}`;
        statusDiv.style.display = 'block';
        setTimeout(() => { statusDiv.style.display = 'none'; }, 5000);
    }

    function makeAjaxRequest(action, data, callback) {
        const formData = new FormData();
        formData.append('action', action);
        for (const key in data) {
            formData.append(key, data[key]);
        }
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'timesheet-handler.php', true);
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    callback(null, JSON.parse(xhr.responseText));
                } catch (e) {
                    callback('Erreur de parsing JSON: ' + xhr.responseText);
                }
            } else {
                callback('Erreur serveur: ' + xhr.statusText);
            }
        };
        xhr.onerror = function() {
            callback('Erreur réseau. Vérifiez votre connexion.');
        };
        xhr.send(formData);
    }

    function updateLocationInfo(className, icon, text) {
        const locationInfo = document.getElementById('location-info');
        locationInfo.className = className;
        locationInfo.innerHTML = `<i class="fas ${icon}"></i> ${text} <button id="refresh-location" onclick="checkLocationAndSetButtons()"><i class="fas fa-sync-alt"></i></button>`;
    }

    function checkLocationAndSetButtons() {
        updateLocationInfo('pending', 'fa-spinner fa-spin', 'Vérification du lieu...');
        if (!navigator.geolocation) {
            updateLocationInfo('out_of_range', 'fa-map-marker-slash', 'Géolocalisation non supportée.');
            isInRange = false;
            updateButtonStates();
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                currentLatitude = position.coords.latitude;
                currentLongitude = position.coords.longitude;
                makeAjaxRequest('check_location_status', { latitude: currentLatitude, longitude: currentLongitude }, (error, response) => {
                    if (error || response.status !== 'success') {
                        updateLocationInfo('out_of_range', 'fa-exclamation-circle', 'Erreur de vérification du lieu.');
                        isInRange = false;
                    } else {
                        isInRange = response.data.in_range;
                        const icon = isInRange ? 'fa-check-circle' : 'fa-times-circle';
                        updateLocationInfo(isInRange ? 'in_range' : 'out_of_range', icon, response.data.message);
                    }
                    updateButtonStates();
                });
            },
            (error) => {
                let message = 'Impossible d\'obtenir la position.';
                if (error.code === error.PERMISSION_DENIED) {
                    message = 'Permission de géolocalisation refusée. Activez-la dans les paramètres.';
                }
                updateLocationInfo('out_of_range', 'fa-map-marker-slash', message);
                isInRange = false;
                updateButtonStates();
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    function enregistrerPointage(action) {
        const missionSelect = document.getElementById('mission-select');
        const selectedMission = missionSelect.value;
        const missionComment = document.getElementById('mission-comment').value;

        if (action === 'record_entry' && !selectedMission) {
            showStatusMessage("Veuillez sélectionner une mission pour pointer.", "error");
            return;
        }
        if (action === 'record_entry' && selectedMission === 'without' && !missionComment.trim()) {
            showStatusMessage("Veuillez entrer un commentaire.", "error");
            return;
        }
        if (!isInRange) {
            showStatusMessage("Vous devez être à proximité d'un site autorisé pour pointer.", "error");
            return;
        }

        const data = { latitude: currentLatitude, longitude: currentLongitude };
        if (action === 'record_entry') {
            data.mission_id = selectedMission === 'without' ? null : selectedMission;
            data.comment = selectedMission === 'without' ? missionComment : null;
        }

        showStatusMessage("Envoi en cours...", "info");
        makeAjaxRequest(action, data, (error, response) => {
            if (error || response.status !== 'success') {
                showStatusMessage("Erreur: " + (error || response.message), "error");
            } else {
                showStatusMessage(response.message, "success");
                loadTimesheetHistory();
            }
        });
    }

    function addBreak(minutes) {
        makeAjaxRequest('add_break', { break_minutes: minutes }, (error, response) => {
            if (error || response.status !== 'success') {
                showStatusMessage("Erreur: " + (error || response.message), "error");
            } else {
                showStatusMessage(response.message, "success");
                loadTimesheetHistory();
            }
        });
    }

    function loadTimesheetHistory() {
        const tableBody = document.getElementById('timesheet-history');
        tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;"><i class="fas fa-spinner fa-spin"></i> Chargement...</td></tr>';
        makeAjaxRequest('get_history', {}, (error, response) => {
            if (error || response.status !== 'success' || !Array.isArray(response.data)) {
                tableBody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:red;">Erreur: ${error || response.message}</td></tr>`;
            } else if (response.data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Aucun pointage trouvé.</td></tr>';
            } else {
                tableBody.innerHTML = '';
                response.data.forEach(entry => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${entry.date}</td>
                        <td>${entry.logon_time}</td>
                        <td>${entry.logoff_time}</td>
                        <td>${entry.break_minutes}</td>
                        <td class="total-duration">${entry.duration}</td>
                    `;
                    tableBody.appendChild(row);
                });
            }
            fetchLatestEntryStatus();
        });
    }

    function fetchLatestEntryStatus() {
        makeAjaxRequest('get_latest_entry_status', {}, (error, response) => {
            if (!error && response.status === 'success' && response.data) {
                timeSheetStatus = response.data;
            } else {
                timeSheetStatus = { has_entry: false, has_exit: false };
            }
            updateButtonStates();
        });
    }

    function updateButtonStates() {
        const btnEntree = document.getElementById('btn-entree');
        const btnSortie = document.getElementById('btn-sortie');
        const btnPause = document.getElementById('btn-pause');
        
        btnEntree.disabled = !isInRange || timeSheetStatus.has_entry;
        btnSortie.disabled = !isInRange || !timeSheetStatus.has_entry || timeSheetStatus.has_exit;
        btnPause.disabled = !isInRange || !timeSheetStatus.has_entry || timeSheetStatus.has_exit;
    }

    function loadUserMissions() {
        makeAjaxRequest('get_user_missions_for_today', {}, (error, response) => {
            if (error || response.status !== 'success') {
                console.error("Erreur de chargement des missions:", error || response.message);
                return;
            }
            const missionSelect = document.getElementById('mission-select');
            const withoutOption = missionSelect.querySelector('option[value="without"]');
            response.data.forEach(mission => {
                const option = document.createElement('option');
                option.value = mission.assignment_id;
                option.textContent = mission.mission_text;
                missionSelect.insertBefore(option, withoutOption);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateClock();
        setInterval(updateClock, 1000);
        
        loadUserMissions();
        loadTimesheetHistory(); // This will trigger the first location check and button state update
        setInterval(checkLocationAndSetButtons, 60000); // Refresh location check every minute

        document.getElementById('mission-select').addEventListener('change', function() {
            document.getElementById('comment-group').style.display = (this.value === 'without') ? 'block' : 'none';
        });

        document.getElementById('btn-pause').addEventListener('click', () => {
            // For simplicity, we can use a prompt. A modal would be better for a final product.
            const breakTime = prompt("Entrez la durée de la pause en minutes (ex: 30, 60):", "30");
            if (breakTime && !isNaN(breakTime)) {
                addBreak(parseInt(breakTime, 10));
            }
        });
    });
</script>

</body>
</html>
