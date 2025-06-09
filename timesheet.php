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
    <title>Pointage - Gestion des Ouvriers</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        /* All your existing CSS from the original file goes here */
        body { background-color: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .card { background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); padding: 25px; margin-bottom: 25px; border: 1px solid #e5e5e5; }
        .clock-display { font-size: 56px; font-weight: 300; }
        .btn-success { background-color: #34c759; color: white; border:none; } .btn-success:hover { background-color: #2ca048; }
        .btn-danger { background-color: #ff3b30; color: white; border:none; } .btn-danger:hover { background-color: #d63027; }
        .btn-warning { background-color: #333333; color: white; border:none; } .btn-warning:hover { background-color: #555555; }
        button:disabled { opacity: 0.6; cursor: not-allowed; }
        #location-info { background-color: #f0f0f0; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e0e0e0; text-align: center; color: #6e6e73; font-size: 14px; font-weight: 600; }
        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; font-size: 14px; text-align: center; }
        .alert-success { background-color: rgba(52, 199, 89, 0.1); border-color: rgba(52, 199, 89, 0.3); color: #2ca048; }
        .alert-error { background-color: rgba(255, 59, 48, 0.1); border-color: rgba(255, 59, 48, 0.3); color: #d63027; }
        .alert-info { background-color: rgba(0, 122, 255, 0.1); border-color: rgba(0, 122, 255, 0.3); color: #0056b3; }
        .dropdown { position: relative; display: inline-block; width: 100%; }
        .dropdown-content { display: none; position: absolute; background-color: #f9f9f9; min-width: 100%; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); z-index: 1; border-radius: 8px; overflow: hidden; top: 100%; left: 0; }
        .dropdown-content a { color: black; padding: 12px 16px; text-decoration: none; display: block; font-size: 14px; }
        .dropdown-content a:hover { background-color: #f1f1f1; }
        .dropdown.show .dropdown-content { display: block; }
    </style>
</head>
<body class="timesheet-page">
    <?php include 'navbar.php'; ?>
    <div class="container mt-4">
        <div id="pointage">
            <div id="status-message" class="mt-3" style="display: none;"></div>
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    <div class="card text-center">
                        <div class="card-body">
                            <h2 class="card-title">Pointage</h2>
                            <div class="clock-display my-4" id="current-time">--:--:--</div>
                            <div id="location-info">
                                <div id="location-status-message">Activation de la géolocalisation...</div>
                            </div>
                            <div class="d-grid gap-2">
                                <button class="btn btn-success btn-lg" id="btn-entree" onclick="enregistrerPointage('record_entry')">Enregistrer Entrée</button>
                                <div class="dropdown" id="break-dropdown">
                                    <button class="btn btn-warning btn-lg w-100" id="btn-break">Ajouter Pause</button>
                                    <div class="dropdown-content">
                                        <a href="#" onclick="addBreak(30)">30 min</a>
                                        <a href="#" onclick="addBreak(60)">1 heure</a>
                                    </div>
                                </div>
                                <button class="btn btn-danger btn-lg" id="btn-sortie" onclick="enregistrerPointage('record_exit')" disabled>Enregistrer Sortie</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mt-4">
                <h3 class="card-title">Historique des pointages</h3>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Entrée</th>
                                <th>Distance (Entrée)</th>
                                <th>Sortie</th>
                                <th>Pause</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="timesheet-history">
                            <tr><td colspan="6" class="text-center">Chargement...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php include('footer.php'); ?>
    <script>
        let currentLatitude = null;
        let currentLongitude = null;

        function updateClock() {
            const timeElement = document.getElementById('current-time');
            if (timeElement) timeElement.textContent = new Date().toLocaleTimeString('fr-FR');
        }
        setInterval(updateClock, 1000);
        updateClock();

        function showStatusMessage(message, type = 'info') {
            const statusDiv = document.getElementById('status-message');
            statusDiv.innerHTML = message;
            statusDiv.className = `alert alert-${type}`;
            statusDiv.style.display = 'block';
            setTimeout(() => { statusDiv.style.display = 'none'; }, 5000);
        }

        function makeAjaxRequest(action, data, callback) {
            const formData = new FormData();
            formData.append('action', action);
            for (const key in data) formData.append(key, data[key]);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'timesheet-handler.php', true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            callback(null, JSON.parse(xhr.responseText));
                        } catch (e) {
                            callback('Erreur de parsing JSON: ' + xhr.responseText);
                        }
                    } else {
                        callback('Erreur réseau: ' + xhr.status);
                    }
                }
            };
            xhr.send(formData);
        }

        function updateLocationStatus() {
            const locationStatusMsg = document.getElementById('location-status-message');
            if (!navigator.geolocation) {
                locationStatusMsg.textContent = 'Géolocalisation non supportée.';
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    currentLatitude = position.coords.latitude;
                    currentLongitude = position.coords.longitude;
                    makeAjaxRequest('check_location_status', { latitude: currentLatitude, longitude: currentLongitude }, (error, response) => {
                        locationStatusMsg.textContent = (error || response.status !== 'success') ? "Erreur de vérification du lieu." : response.data.message;
                    });
                },
                () => {
                    locationStatusMsg.textContent = 'Impossible d\'obtenir la position.';
                    currentLatitude = null;
                    currentLongitude = null;
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        }

        function enregistrerPointage(action) {
            if (action === 'record_entry' && (currentLatitude === null || currentLongitude === null)) {
                showStatusMessage("Position GPS non disponible. Veuillez activer la localisation pour pointer.", "error");
                return;
            }
            const data = {
                latitude: currentLatitude,
                longitude: currentLongitude
            };
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
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center">Chargement...</td></tr>';
            makeAjaxRequest('get_history', {}, (error, response) => {
                if (error || response.status !== 'success' || !Array.isArray(response.data)) {
                    tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">Erreur: ${error || response.message}</td></tr>`;
                } else if (response.data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="6" class="text-center">Aucun pointage trouvé</td></tr>';
                } else {
                    tableBody.innerHTML = '';
                    response.data.forEach(entry => {
                        const distanceColor = entry.distance.includes('km') || (parseInt(entry.distance) > 50) ? 'color:red;' : 'color:green;';
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${entry.date}</td>
                            <td>${entry.logon_time}</td>
                            <td style="${distanceColor}">${entry.distance}</td>
                            <td>${entry.logoff_time}</td>
                            <td>${entry.break_minutes > 0 ? entry.break_minutes + ' min' : '--'}</td>
                            <td><strong>${entry.duration || '--'}</strong></td>
                        `;
                        tableBody.appendChild(row);
                    });
                }
                checkLatestEntryStatus();
            });
        }

        function updateButtonStates(hasEntry, hasExit) {
            const btnEntree = document.getElementById('btn-entree');
            const btnSortie = document.getElementById('btn-sortie');
            const btnBreak = document.getElementById('btn-break');
            if (hasEntry && hasExit) {
                btnEntree.disabled = true; btnSortie.disabled = true; btnBreak.disabled = true;
            } else if (hasEntry && !hasExit) {
                btnEntree.disabled = true; btnSortie.disabled = false; btnBreak.disabled = false;
            } else {
                btnEntree.disabled = false; btnSortie.disabled = true; btnBreak.disabled = true;
            }
        }

        function checkLatestEntryStatus() {
            makeAjaxRequest('get_latest_entry_status', {}, (error, response) => {
                if (error || response.status !== 'success') {
                    console.error("Error checking status:", error || response.message);
                    updateButtonStates(false, false);
                } else if (response.data) {
                    updateButtonStates(response.data.has_entry, response.data.has_exit);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadTimesheetHistory();
            updateLocationStatus();
            setInterval(updateLocationStatus, 60000);

            const breakDropdown = document.getElementById('break-dropdown');
            const btnBreak = document.getElementById('btn-break');
            btnBreak.addEventListener('click', (event) => {
                breakDropdown.classList.toggle('show');
                event.stopPropagation();
            });
            window.addEventListener('click', (event) => {
                if (!event.target.matches('#btn-break')) {
                    if (breakDropdown.classList.contains('show')) breakDropdown.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>
