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
        @media (max-width: 991px) { .navbar-toggler { margin-right: 0; z-index: 1035; } }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"; }
        body { background-color: #f5f5f7; color: #1d1d1f; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; padding-top: 0 !important; margin-top: 0 !important; overflow-x: hidden; }
        .container-fluid { margin-top: 0 !important; padding-top: 20px; }
        .container { max-width: 1100px; margin: 0 auto; padding: 25px; }
        .card { background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); padding: 25px; margin-bottom: 25px; border: 1px solid #e5e5e5; }
        h2 { margin-bottom: 25px; color: #1d1d1f; font-size: 28px; font-weight: 600; }
        h3 { margin-bottom: 20px; font-size: 18px; font-weight: 600; color: #1d1d1f; }
        .clock-section { display: flex; justify-content: center; margin-bottom: 30px; }
        .clock-card { text-align: center; width: 100%; max-width: 450px; }
        .clock-display { font-size: 56px; font-weight: 300; margin-bottom: 25px; color: #1d1d1f; letter-spacing: 1px; }
        .clock-buttons { display: flex; justify-content: center; gap: 15px; flex-wrap: wrap; }
        button, .btn-primary, .btn-success, .btn-danger, .btn-warning { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 15px; transition: background-color 0.2s ease-in-out, opacity 0.2s ease-in-out; line-height: 1.2; flex-grow: 1; flex-basis: 0; text-align: center; min-width: 120px; }
        .btn-success { background-color: #34c759; color: white; } .btn-success:hover { background-color: #2ca048; }
        .btn-danger { background-color: #ff3b30; color: white; } .btn-danger:hover { background-color: #d63027; }
        .btn-warning { background-color: #333333; color: white; } .btn-warning:hover { background-color: #555555; }
        button:disabled, button[disabled] { background-color: #ccc !important; color: #666 !important; opacity: 0.7; cursor: not-allowed; }
        .clock-buttons button { margin-bottom: 10px; }
        .dropdown { position: relative; display: inline-block; width: 100%; }
        .dropdown .btn-warning { width: 100%; margin-bottom: 0; }
        .dropdown-content { display: none; position: absolute; background-color: #f9f9f9; min-width: 100%; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); z-index: 1; border-radius: 8px; overflow: hidden; top: 100%; left: 0; }
        .dropdown-content a { color: black; padding: 12px 16px; text-decoration: none; display: block; font-size: 14px; }
        .dropdown-content a:hover { background-color: #f1f1f1; }
        .dropdown.show .dropdown-content { display: block; }
        .table-container { overflow-x: auto; border: 1px solid #e5e5e5; border-radius: 8px; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; min-width: 650px; }
        table th, table td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #e5e5e5; font-size: 14px; color: #1d1d1f; }
        table td { color: #555; }
        table th { background-color: #f9f9f9; font-weight: 600; color: #333; border-bottom-width: 2px; }
        #location-info { background-color: #f0f0f0; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e0e0e0; text-align: center; color: #6e6e73; font-size: 14px; font-weight: 600; }
        #location-info.in_range { color: #2ca048; border-color: #a3e9b4; background-color: #eaf9ed; }
        #location-info.out_of_range { color: #d63027; border-color: #f5b9b5; background-color: #fdedec; }
        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid transparent; font-size: 14px; text-align: center; }
        .alert-success { background-color: rgba(52, 199, 89, 0.1); border-color: rgba(52, 199, 89, 0.3); color: #2ca048; }
        .alert-error { background-color: rgba(255, 59, 48, 0.1); border-color: rgba(255, 59, 48, 0.3); color: #d63027; }
        .switch { position: relative; display: inline-block; width: 50px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; transition: .4s; }
        input:checked + .slider { background-color: #2ecc71; }
        input:focus + .slider { box-shadow: 0 0 1px #2ecc71; }
        input:checked + .slider:before { transform: translateX(26px); }
        .slider.round { border-radius: 24px; }
        .slider.round:before { border-radius: 50%; }
    </style>
</head>
<body class="timesheet-page">
    <?php include 'navbar.php'; ?>
<div id="page-content">
    <div class="container-fluid">
        <div id="pointage">
            <h2>Pointage</h2>
            <div id="status-message" style="display: none;"></div>
            <div class="clock-section">
                <div class="card clock-card">
                    <div class="clock-display" id="current-time">--:--:--</div>
                    <div style="margin-bottom: 15px; display: flex; justify-content: center; align-items: center; gap: 10px; padding: 10px; background-color: #f8f9fa; border-radius: 5px; border: 1px solid #e9ecef;">
                        <span style="font-weight: bold;">Localisation:</span>
                        <label class="switch" style="margin: 0;">
                            <input type="checkbox" id="toggle-location" checked>
                            <span class="slider round"></span>
                        </label>
                        <span id="location-status-text" style="color: #2ecc71; font-weight: bold;">Activée</span>
                    </div>
                    <div id="location-info">
                        Activation de la géolocalisation...
                    </div>
                    <div class="clock-buttons">
                        <button class="btn-success" id="btn-entree">Enregistrer Entrée</button>
                        <div class="dropdown" id="break-dropdown">
                            <button class="btn-warning" id="btn-break">Ajouter Pause</button>
                            <div class="dropdown-content">
                                <a href="#" onclick="addBreak(30)">30 min</a>
                                <a href="#" onclick="addBreak(60)">1 heure</a>
                            </div>
                        </div>
                        <button class="btn-danger" id="btn-sortie" onclick="enregistrerPointage('record_exit')">Enregistrer Sortie</button>
                    </div>
                </div>
            </div>
            <div class="card">
                <h3>Historique des pointages</h3>
                <div class="table-container">
                    <table>
                        <thead>
    <tr>
        <th>Date</th>
        <th>Entrée</th>
        <th>Lieu (Entrée)</th>
        <th>Sortie</th>
        <th>Lieu (Sortie)</th>
        <th>Pause</th>
        <th>Total</th>
        <th>Mission</th>
        <th>Commentaires</th>
    </tr>
</thead>
                        <tbody id="timesheet-history">
                            <tr><td colspan="7" style="text-align: center;">Chargement...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="missionModal" tabindex="-1" role="dialog" aria-labelledby="missionModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="missionModalLabel">Sélectionner une mission</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="form-group">
                <label for="assignment_id">Mission</label>
                <select class="form-control" id="assignment_id">
                    <option value="">-- Sans mission --</option>
                </select>
            </div>
            <div class="form-group" id="comment-group">
                <label for="logon_comment">Commentaire (si pas de mission)</label>
                <textarea class="form-control" id="logon_comment" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
            <button type="button" class="btn btn-primary" id="confirm-entry">Confirmer l'entrée</button>
          </div>
        </div>
      </div>
    </div>
</div>
    <?php include('footer.php'); ?>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let currentLatitude = null;
    let currentLongitude = null;
    let isInRange = false;
    let timeSheetStatus = { has_entry: false, has_exit: false };

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

    function checkLocationAndSetButtons() {
        if (!document.getElementById('toggle-location').checked) {
            updateButtonStates();
            return;
        }
        const locationInfo = document.getElementById('location-info');
        if (!navigator.geolocation) {
            locationInfo.textContent = 'Géolocalisation non supportée.';
            isInRange = false;
            updateButtonStates();
            return;
        }
        locationInfo.textContent = 'Activation de la géolocalisation...';
        navigator.geolocation.getCurrentPosition(
            (position) => {
                currentLatitude = position.coords.latitude;
                currentLongitude = position.coords.longitude;
                makeAjaxRequest('check_location_status', { latitude: currentLatitude, longitude: currentLongitude }, (error, response) => {
                    if (error || response.status !== 'success') {
                        locationInfo.textContent = "Erreur de vérification du lieu.";
                        isInRange = false;
                    } else {
                        isInRange = response.data.in_range;
                        locationInfo.textContent = response.data.message;
                        locationInfo.className = isInRange ? 'in_range' : 'out_of_range';
                    }
                    updateButtonStates();
                });
            },
            () => {
                locationInfo.textContent = 'Impossible d\'obtenir la position.';
                isInRange = false;
                updateButtonStates();
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    function enregistrerPointage(action, assignment_id = null, logon_comment = null) {
        if (!isInRange) {
            showStatusMessage("Vous devez être à proximité d'un site autorisé pour pointer.", "error");
            return;
        }
        const data = { latitude: currentLatitude, longitude: currentLongitude };
        if (assignment_id) {
            data.assignment_id = assignment_id;
        }
        if (logon_comment) {
            data.logon_comment = logon_comment;
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
        tableBody.innerHTML = '<tr><td colspan="7" class="text-center">Chargement...</td></tr>';
        makeAjaxRequest('get_history', {}, (error, response) => {
            if (error || response.status !== 'success' || !Array.isArray(response.data)) {
                tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Erreur: ${error || response.message}</td></tr>`;
            } else if (response.data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="7" class="text-center">Aucun pointage trouvé</td></tr>';
            } else {
                tableBody.innerHTML = '';
                response.data.forEach(entry => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
    <td>${entry.date}</td>
    <td>${entry.logon_time}</td>
    <td>${entry.logon_location_name}</td>
    <td>${entry.logoff_time}</td>
    <td>${entry.logoff_location_name}</td>
    <td>${entry.break_minutes > 0 ? entry.break_minutes + ' min' : '--'}</td>
    <td><strong>${entry.duration || '--'}</strong></td>
    <td>${entry.mission || '--'}</td>
    <td>${entry.comment || '--'}</td>
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
            checkLocationAndSetButtons();
        });
    }

    function updateButtonStates() {
        const btnEntree = document.getElementById('btn-entree');
        const btnSortie = document.getElementById('btn-sortie');
        const btnBreak = document.getElementById('btn-break');
        
        const hasEntry = timeSheetStatus.has_entry;
        const hasExit = timeSheetStatus.has_exit;

        btnEntree.disabled = !isInRange || hasEntry;
        btnSortie.disabled = !isInRange || !hasEntry || hasExit;
        btnBreak.disabled = !hasEntry || hasExit;
    }
    
    document.getElementById('toggle-location').addEventListener('change', function() {
        const statusText = document.getElementById('location-status-text');
        const locationInfo = document.getElementById('location-info');
        if (this.checked) {
            statusText.textContent = "Activée";
            statusText.style.color = "#2ecc71";
            checkLocationAndSetButtons();
        } else {
            statusText.textContent = "Désactivée";
            statusText.style.color = "#e74c3c";
            locationInfo.textContent = "La localisation doit être activée pour pointer.";
            locationInfo.className = 'out_of_range';
            isInRange = false;
            updateButtonStates();
        }
    });
    
    document.getElementById('btn-entree').addEventListener('click', function() {
        makeAjaxRequest('get_user_assignments', {}, (error, response) => {
            if (error || response.status !== 'success') {
                showStatusMessage("Erreur lors de la récupération des missions.", "error");
                return;
            }
            const select = document.getElementById('assignment_id');
            select.innerHTML = '<option value="">-- Sans mission --</option>';
            response.data.forEach(assignment => {
                const option = document.createElement('option');
                option.value = assignment.assignment_id;
                option.textContent = assignment.mission_text;
                select.appendChild(option);
            });
            $('#missionModal').modal('show');
        });
    });

    document.getElementById('confirm-entry').addEventListener('click', function() {
        const assignment_id = document.getElementById('assignment_id').value;
        const logon_comment = document.getElementById('logon_comment').value;
        enregistrerPointage('record_entry', assignment_id, logon_comment);
        $('#missionModal').modal('hide');
    });

    document.addEventListener('DOMContentLoaded', function() {
        loadTimesheetHistory();
        setInterval(checkLocationAndSetButtons, 30000);

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
