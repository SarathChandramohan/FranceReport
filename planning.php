<?php
// planning.php (Final version with temporary mission logic)
require_once 'session-management.php';
require_once 'db-connection.php';
requireLogin();

$user = getCurrentUser();
if ($user['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$predefined_colors = ['#1877f2', '#34c759', '#ff9500', '#5856d6', '#ff3b30', '#007aff', '#ffcc00', '#8e8e93', '#ff2d55', '#00a096'];
$default_color = $predefined_colors[0];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Planning</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        :root { --primary: #007bff; --light-gray: #f0f2f5; --card-bg: #ffffff; --border-color: #dee2e6; }
        html, body { height: 100%; overflow: hidden; }
        body { background-color: var(--light-gray); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 0.8rem; }
        h4 { font-size: 1rem; } h5, .modal-title { font-size: 0.85rem; }
        .main-container { display: flex; height: calc(100vh - 78px); }
        .workers-list-col, .planning-col { height: 100%; overflow-y: auto; padding: 15px; }
        .workers-list-col { flex: 0 0 280px; background: var(--card-bg); border-right: 1px solid var(--border-color); }
        .planning-col { flex: 1; }
        .worker-item { padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px; margin-bottom: 8px; background-color: #fcfdff; cursor: grab; user-select: none; }
        .worker-item.unavailable { background-color: #f8d7da; border-color: #f5c6cb; }
        .assignment-count { font-size: 0.5rem; color: #fff; background-color: #dc3545; border-radius: 10px; padding: 2px 8px; display: inline-block; margin-top: 5px; }
        .daily-planning-container { display: grid; grid-template-columns: repeat(7, 1fr); gap: 15px; min-height: 100%; }
        .day-column { background-color: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef; display: flex; flex-direction: column; }
        .day-header { padding: 10px; text-align: center; font-weight: 600; border-bottom: 1px solid var(--border-color); background-color: #f1f3f5; display:flex; justify-content:space-between; align-items:center; cursor: pointer; }
        .day-header.selected { background-color: var(--primary); color: white; }
        .add-mission-to-day-btn { background: none; border: none; color: var(--primary); cursor: pointer; font-size: 0.7rem; }
        .day-header.selected .add-mission-to-day-btn { color: white; }
        .mission-card { background-color: #fff; border-left: 5px solid; border-radius: 6px; padding: 10px; margin-bottom: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: relative; }
        .mission-card.temporary { border-style: dashed; }
        .mission-card.conflicting-assignment { border: 2px solid red !important; }
        .mission-card.validated { opacity: 1; background-color: #e6ffed; }
        .mission-card.not-validated { opacity: 0.8; background-color: #fff3cd; }
        .mission-card-body { cursor: pointer; }
        .mission-title { font-weight: 600; font-size: 0.6rem; margin-bottom: 5px; }
        .mission-meta { font-size: 0.5rem; color: #6c757d; margin-bottom: 8px; }
        .assigned-workers-list { list-style: none; padding-left: 0; margin-bottom: 0; font-size: 0.5rem; }
        .assigned-workers-list li { background-color: #e7f1ff; padding: 3px 8px; border-radius: 4px; margin-top: 4px; display: flex; justify-content: space-between; align-items: center; }
        .remove-worker-btn { cursor: pointer; color: #dc3545; }
        .mission-placeholder { font-size: 0.55rem; color: #6c757d; text-align: center; padding: 20px; border: 2px dashed #ced4da; border-radius: 6px; height: 100%; display: flex; align-items: center; justify-content: center;}
        .mission-actions { position: absolute; top: 5px; right: 5px; display: flex; gap: 5px; background: rgba(255,255,255,0.8); border-radius: 5px; padding: 2px;}
        .action-btn { background: none; border: none; color: #6c757d; font-size: 0.5rem; cursor: pointer; padding: 3px; }
        .action-btn.validate-btn.validated { color: #28a545; }
        #loadingOverlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255, 255, 255, 0.7); z-index: 1060; display: none; justify-content: center; align-items: center; }
        .color-swatch { width:25px; height:25px; border-radius:50%; cursor:pointer; display:inline-block; margin:2px; border: 2px solid transparent; }
        .color-swatch.selected { border-color: #333; }
        label.list-group-item.disabled { background-color: #f8f9fa; cursor: not-allowed; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="main-container">
        <div class="workers-list-col">
            <h5><i class="fas fa-users"></i> Ouvriers Emploi</h5>
            <div id="workerList"></div>
        </div>
        <div class="planning-col">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <button class="btn btn-outline-secondary" id="prevWeekBtn"><i class="fas fa-chevron-left"></i></button>
                    <button class="btn btn-outline-secondary" id="nextWeekBtn"><i class="fas fa-chevron-right"></i></button>
                </div>
                <h4 id="currentWeekRange" class="mb-0 mx-3 text-center"></h4>
                <div>
                    <button class="btn btn-success" id="activateAllBtn">Tout Activer</button>
                    <button class="btn btn-primary" id="addMultiDayMissionBtn"><i class="fas fa-calendar-plus"></i> Mission sur plusieurs jours</button>
                </div>
            </div>
            <div id="dailyPlanningContainer" class="daily-planning-container"></div>
        </div>
    </div>

    <div class="modal fade" id="missionFormModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form id="missionForm"><div class="modal-header bg-primary text-white"><h5 class="modal-title" id="missionFormModalLabel">Nouvelle Mission</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div><div class="modal-body" style="max-height: 75vh; overflow-y: auto;"><input type="hidden" id="mission_id_form" name="mission_id"><input type="hidden" id="mission_group_id_form" name="mission_group_id"><input type="hidden" id="assignment_date_form" name="assignment_date"><div class="alert alert-info" id="modalDateDisplay"></div><div class="form-row" id="multi_day_fields" style="display: none;"><div class="form-group col-md-6"><label>Date de début *</label><input type="date" class="form-control" name="start_date"></div><div class="form-group col-md-6"><label>Date de fin *</label><input type="date" class="form-control" name="end_date"></div></div><div class="form-group"><label>Titre de la mission *</label><input type="text" class="form-control" name="mission_text" required></div><div class="form-group"><label>Commentaires</label><textarea class="form-control" name="comments" rows="3"></textarea></div><div class="form-row"><div class="form-group col-md-6"><label>Heure début</label><input type="time" class="form-control" name="start_time"></div><div class="form-group col-md-6"><label>Heure fin</label><input type="time" class="form-control" name="end_time"></div></div><div class="form-group"><label>Lieu</label><input type="text" class="form-control" name="location"></div><div class="form-group"><label>Type</label><div class="btn-group btn-group-toggle d-flex" data-toggle="buttons" id="shift_type_buttons"></div></div><div class="form-group"><label>Couleur</label><div id="mission_color_swatches"></div><input type="hidden" name="color" value="<?= htmlspecialchars($default_color); ?>"></div><hr><div class="form-group" id="asset-management-container"><input type="hidden" name="assigned_asset_ids" id="assigned_asset_ids_hidden"><label>Matériel assigné</label><div id="assigned_assets_display" class="d-flex flex-wrap align-items-center border rounded p-2 mb-2 bg-light" style="min-height: 50px;"><span class="text-muted small p-2">Aucun matériel assigné.</span></div><button type="button" class="btn btn-sm btn-info" id="manageAssetsBtn">Gérer le Matériel</button></div><div id="modal_error_message" class="alert alert-danger mt-3" style="display: none;"></div></div><div class="modal-footer"><button type="button" class="btn btn-danger mr-auto" id="deleteMissionBtnFromModal" style="display: none;">Supprimer</button><button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div></form></div></div></div>
    <div class="modal fade" id="assetAssignmentModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Assigner du Matériel</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body"><input type="text" id="asset_assignment_search" class="form-control mb-3" placeholder="Rechercher..."><div id="asset_assignment_list" class="list-group" style="max-height: 400px; overflow-y: auto;"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button><button type="button" class="btn btn-primary" id="confirmAssetAssignmentBtn">Confirmer</button></div></div></div></div>
    <div class="modal fade" id="confirmationModal" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirmation</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body" id="confirmationModalBody"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button><button type="button" class="btn btn-danger" id="confirmActionBtn">Confirmer</button></div></div></div></div>
    <div id="loadingOverlay"><div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div></div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- STATE & CONFIG ---
    const HANDLER_URL = 'planning-handler.php';
    const PREDEFINED_COLORS = <?php echo json_encode($predefined_colors); ?>;
    const DEFAULT_COLOR = <?php echo json_encode($default_color); ?>;
    let state = {
        staff: [],
        missions: [], // This will hold both saved and temporary missions
        inventory: [],
        bookings: [],
        currentWeekStart: getMonday(new Date()),
        draggedWorker: null,
        selectedDate: null
    };

    // --- DOM ELEMENTS ---
    const $loading = $('#loadingOverlay');
    const $planningContainer = $('#dailyPlanningContainer');
    const $workerList = $('#workerList');
    const $missionModal = $('#missionFormModal');

    // --- HELPERS ---
    const showLoading = (show) => $loading.toggle(show);
    const getLocalDateString = (date) => date.toISOString().split('T')[0];
    const formatTime = (time) => time ? time.substring(0, 5) : '';
    const showModalError = (msg) => $('#modal_error_message').text(msg).show();
    const hideModalError = () => $('#modal_error_message').hide();
    function getMonday(d) { let day = d.getDay() || 7; if (day !== 1) d.setHours(-24 * (day - 1)); d.setHours(0,0,0,0); return d; }

    // --- API CALLS ---
    async function apiCall(action, method = 'POST', data = {}) {
        const options = { method, headers: { 'Content-Type': 'application/json' } };
        let url = `${HANDLER_URL}?action=${action}`;
        if (method === 'GET') {
            url += '&' + new URLSearchParams(data).toString();
        } else {
            options.body = JSON.stringify(data);
        }
        const response = await fetch(url, options);
        if (!response.ok) throw new Error(`Erreur réseau: ${response.statusText}`);
        const result = await response.json();
        if (result.status !== 'success') throw new Error(result.message);
        return result.data;
    }

    // --- DATA FETCHING & STATE MANAGEMENT ---
    async function fetchInitialData() {
        showLoading(true);
        // CRITICAL: Discard temporary missions before fetching new data
        state.missions = state.missions.filter(m => !m.is_temporary);

        try {
            const endDate = new Date(state.currentWeekStart);
            endDate.setDate(endDate.getDate() + 6);
            const data = await apiCall('get_initial_data', 'GET', {
                start: getLocalDateString(state.currentWeekStart),
                end: getLocalDateString(endDate)
            });
            state.staff = data.staff || [];
            state.missions = data.missions || [];
            state.inventory = data.inventory || [];
            state.bookings = data.bookings || [];
            if (state.selectedDate) {
                await refreshWorkerListForDate(state.selectedDate, false);
            }
            renderUI();
        } catch (error) {
            alert(`Erreur de chargement: ${error.message}`);
        } finally {
            showLoading(false);
        }
    }

    // --- UI RENDERING ---
    function renderUI() {
        renderWeekHeader();
        renderPlanningGrid();
        renderWorkerList();
        setupModalStaticContent();
    }

    function renderWeekHeader() {
        const endDate = new Date(state.currentWeekStart);
        endDate.setDate(endDate.getDate() + 6);
        $('#currentWeekRange').text(`${state.currentWeekStart.toLocaleDateString('fr-FR')} - ${endDate.toLocaleDateString('fr-FR')}`);
    }

    function renderPlanningGrid() {
        $planningContainer.empty();
        for (let i = 0; i < 7; i++) {
            const dayDate = new Date(state.currentWeekStart);
            dayDate.setDate(dayDate.getDate() + i);
            const dateStr = getLocalDateString(dayDate);
            const dayLabel = dayDate.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric' });
            const isSelected = dateStr === state.selectedDate;
            const $dayHeader = $(`<div class="day-header ${isSelected ? 'selected' : ''}" data-date="${dateStr}"><span>${dayLabel}</span><button class="add-mission-to-day-btn" title="Ajouter une mission"><i class="fas fa-plus-circle"></i></button></div>`);
            const $dayContent = $(`<div class="day-content p-2" data-date="${dateStr}"></div>`);
            const $dayColumn = $(`<div class="day-column"></div>`).append($dayHeader, $dayContent);
            $planningContainer.append($dayColumn);
            
            const dayMissions = state.missions.filter(m => m.assignment_date === dateStr);
            if (dayMissions.length > 0) {
                dayMissions.forEach(mission => $dayContent.append(createMissionCard(mission)));
            } else {
                $dayContent.append(`<div class="mission-placeholder"><span>Glissez un ouvrier ici</span></div>`);
            }
        }
    }

    function createMissionCard(mission) {
        // Normalize data for both temporary and saved missions
        const assignedIds = Array.isArray(mission.assigned_user_ids) ? mission.assigned_user_ids : (mission.assigned_user_ids || '').split(',').map(id => id.trim());
        const assignedNames = Array.isArray(mission.assigned_user_names) ? mission.assigned_user_names : (mission.assigned_user_names || '').split(', ');
        const isConflicting = Array.isArray(mission.conflicting_assignments) && mission.conflicting_assignments.some(userId => assignedIds.includes(String(userId)));
        const isValidated = mission.is_validated == 1;
        
        let cardClass = 'mission-card';
        let validationClass = '';
        let validationTitle = '';
        if (mission.is_temporary) {
            cardClass += ' temporary';
            validationClass = 'not-validated';
            validationTitle = 'Cliquer pour enregistrer et valider cette mission';
        } else {
            validationClass = isValidated ? 'validated' : 'not-validated';
            validationTitle = isValidated ? 'Dévalider la mission' : 'Valider la mission';
        }

        const workersHtml = assignedIds.map((workerId, i) => {
            const workerName = assignedNames[i] || 'Ouvrier inconnu';
            return `<li>${workerName} <i class="fas fa-times remove-worker-btn" data-worker-id="${workerId}"></i></li>`;
        }).join('');
        
        const assetsHtml = (mission.assigned_asset_names || '') ? `<div class="mission-meta mt-2"><i class="fas fa-tools"></i> ${mission.assigned_asset_names}</div>` : '';
        
        const actionsHtml = `<div class="mission-actions">
            <i class="fas fa-trash-alt action-btn delete-btn" title="Supprimer"></i>
            <i class="fas fa-check-circle action-btn validate-btn ${isValidated ? 'validated' : ''}" title="${validationTitle}"></i>
        </div>`;
        
        return $(`<div class="${cardClass} ${validationClass} ${isConflicting ? 'conflicting-assignment' : ''}" style="border-left-color: ${mission.color || DEFAULT_COLOR};" data-mission-id="${mission.mission_id || mission.temp_id}" data-mission-group-id="${mission.mission_group_id || ''}">
            ${actionsHtml}
            <div class="mission-card-body">
                <div class="mission-title">${mission.mission_text || 'Nouvelle mission'}</div>
                <div class="mission-meta">
                    ${mission.start_time ? `<i class="far fa-clock"></i> ${formatTime(mission.start_time)} - ${formatTime(mission.end_time)}<br>` : ''}
                    ${mission.location ? `<i class="fas fa-map-marker-alt"></i> ${mission.location}` : ''}
                </div>
                <ul class="assigned-workers-list">${workersHtml || '<li class="text-muted small">Aucun ouvrier</li>'}</ul>
                ${assetsHtml}
            </div></div>`);
    }

    function renderWorkerList() {
        $workerList.empty();
        (state.staff || []).forEach(worker => {
            const isUnavailable = worker.status && worker.status !== 'available';
            let statusText = worker.status === 'assigned' ? 'Assigné' : (worker.status === 'on_leave' ? (worker.leave_type || 'En Congé') : '');
            $workerList.append(`<div class="worker-item ${isUnavailable ? 'unavailable' : ''}" draggable="${!isUnavailable}" data-worker-id="${worker.user_id}" data-worker-name="${worker.prenom} ${worker.nom}">
                <div>${worker.prenom} ${worker.nom}</div>
                ${isUnavailable ? `<div class="assignment-count">${statusText}</div>` : ''}
            </div>`);
        });
    }

    async function refreshWorkerListForDate(date, manageLoadingState = true) {
        if (manageLoadingState) showLoading(true);
        try {
            const workerStatuses = await apiCall('get_worker_status_for_date', 'GET', { date });
            (state.staff || []).forEach(worker => {
                const statusInfo = workerStatuses.find(s => s.user_id === worker.user_id);
                worker.status = statusInfo ? statusInfo.status : 'available';
                worker.leave_type = statusInfo ? statusInfo.leave_type : null;
            });
            renderWorkerList();
        } catch (error) {
            alert(`Erreur de rafraîchissement des ouvriers: ${error.message}`);
        } finally {
            if (manageLoadingState) showLoading(false);
        }
    }

    // --- EVENT HANDLERS ---
    $('#prevWeekBtn').on('click', () => { state.currentWeekStart.setDate(state.currentWeekStart.getDate() - 7); fetchInitialData(); });
    $('#nextWeekBtn').on('click', () => { state.currentWeekStart.setDate(state.currentWeekStart.getDate() + 7); fetchInitialData(); });
    
    $planningContainer.on('click', '.day-header', function() {
        state.selectedDate = $(this).data('date');
        renderPlanningGrid(); // Re-render to show selection
        refreshWorkerListForDate(state.selectedDate);
    });

    // --- DRAG & DROP & MISSION CREATION ---
    $workerList.on('dragstart', '.worker-item', (e) => { state.draggedWorker = { id: $(e.currentTarget).data('worker-id'), name: $(e.currentTarget).data('worker-name') }; });
    $planningContainer.on('dragover', '.day-content, .mission-card', (e) => e.preventDefault());

    $planningContainer.on('drop', '.day-content', async function(e) {
        e.preventDefault(); e.stopPropagation();
        if (!state.draggedWorker) return;
        const $target = $(e.target);
        const $missionCard = $target.closest('.mission-card');
        
        // Dropped on existing mission
        if ($missionCard.length > 0) {
            const missionId = $missionCard.data('mission-id');
            const mission = state.missions.find(m => (m.mission_id || m.temp_id) == missionId);
            if (mission.is_temporary) {
                // Add worker to temporary mission locally
                const workerIdStr = String(state.draggedWorker.id);
                if (!mission.assigned_user_ids.includes(workerIdStr)) {
                    mission.assigned_user_ids.push(workerIdStr);
                    mission.assigned_user_names.push(state.draggedWorker.name);
                }
            } else {
                // Add worker to saved mission via API
                showLoading(true);
                try {
                    await apiCall('assign_worker_to_mission', 'POST', { worker_id: state.draggedWorker.id, mission_group_id: mission.mission_group_id });
                    await fetchInitialData();
                } catch (error) { alert(`Erreur: ${error.message}`); showLoading(false); }
            }
        } 
        // Dropped on empty day -> CREATE TEMPORARY MISSION
        else {
            const date = $(this).data('date');
            const newTempMission = {
                temp_id: `temp_${Date.now()}`,
                is_temporary: true,
                mission_text: 'Nouvelle Mission',
                assignment_date: date,
                assigned_user_ids: [String(state.draggedWorker.id)],
                assigned_user_names: [state.draggedWorker.name],
                assigned_assets: [],
                assigned_asset_names: '',
                is_validated: 0,
                color: DEFAULT_COLOR,
                start_time: '', end_time: '', shift_type: 'matin', comments: '', location: ''
            };
            state.missions.push(newTempMission);
        }
        state.draggedWorker = null;
        renderPlanningGrid(); // Re-render to show changes
    });
    
    // --- ACTIONS ON MISSION CARDS ---
    $planningContainer.on('click', '.validate-btn', async function(e){
        e.stopPropagation();
        const $card = $(this).closest('.mission-card');
        const missionId = $card.data('mission-id');
        const mission = state.missions.find(m => (m.mission_id || m.temp_id) == missionId);

        showLoading(true);
        try {
            if (mission.is_temporary) {
                // This is a NEW mission, save it for the first time
                await apiCall('save_mission', 'POST', {
                    mission_text: mission.mission_text,
                    assignment_date: mission.assignment_date,
                    assigned_user_ids: mission.assigned_user_ids,
                    assigned_asset_ids: (mission.assigned_assets || []).map(a => a.id),
                    comments: mission.comments,
                    start_time: mission.start_time,
                    end_time: mission.end_time,
                    location: mission.location,
                    shift_type: mission.shift_type,
                    color: mission.color
                });
            } else {
                // This is an EXISTING mission, just toggle its validation
                await apiCall('toggle_mission_validation', 'POST', { mission_group_id: mission.mission_group_id });
            }
            await fetchInitialData(); // Refresh everything from DB
        } catch (error) {
            alert(`Erreur: ${error.message}`);
            showLoading(false);
        }
    });

    $planningContainer.on('click', '.delete-btn', function(e) {
        e.stopPropagation();
        const missionId = $(this).closest('.mission-card').data('mission-id');
        const mission = state.missions.find(m => (m.mission_id || m.temp_id) == missionId);

        showConfirmation('Voulez-vous vraiment supprimer cette mission ?', async () => {
            if (mission.is_temporary) {
                // Just remove from local state
                state.missions = state.missions.filter(m => m.temp_id !== missionId);
                renderPlanningGrid();
            } else {
                // Call API to delete from DB
                showLoading(true);
                try {
                    await apiCall('delete_mission_group', 'POST', { mission_group_id: mission.mission_group_id });
                    await fetchInitialData();
                } catch (error) {
                    alert(`Erreur: ${error.message}`);
                    showLoading(false);
                }
            }
        });
    });

    $planningContainer.on('click', '.remove-worker-btn', function(e) {
        e.stopPropagation();
        const missionId = $(this).closest('.mission-card').data('mission-id');
        const workerId = $(this).data('worker-id');
        const mission = state.missions.find(m => (m.mission_id || m.temp_id) == missionId);
        
        showConfirmation(`Retirer cet ouvrier de la mission ?`, async () => {
            if (mission.is_temporary) {
                const workerIdStr = String(workerId);
                const index = mission.assigned_user_ids.indexOf(workerIdStr);
                if (index > -1) {
                    mission.assigned_user_ids.splice(index, 1);
                    mission.assigned_user_names.splice(index, 1);
                }
                renderPlanningGrid();
            } else {
                showLoading(true);
                try {
                    await apiCall('remove_worker_from_mission', 'POST', { worker_id: workerId, mission_group_id: mission.mission_group_id });
                    await fetchInitialData();
                } catch (error) { alert(`Erreur: ${error.message}`); showLoading(false); }
            }
        });
    });

    // --- MODAL & FORM LOGIC ---
    function setupModalStaticContent() {
        $('#shift_type_buttons').html(Object.entries({matin:'Matin', 'apres-midi':'Après-midi', nuit:'Nuit', repos:'Repos', custom:'Personnalisé'}).map(([v, l]) => `<label class="btn btn-sm btn-outline-secondary"><input type="radio" name="shift_type" value="${v}" required> ${l}</label>`).join(''));
        $('#mission_color_swatches').html(PREDEFINED_COLORS.map(c => `<div class="color-swatch" style="background-color:${c};" data-color="${c}"></div>`).join(''));
    }

    $planningContainer.on('click', '.mission-card-body', function() {
        const missionId = $(this).closest('.mission-card').data('mission-id');
        const mission = state.missions.find(m => (m.mission_id || m.temp_id) == missionId);
        if (!mission) return;

        $missionModal.find('form')[0].reset(); hideModalError();
        $('#shift_type_buttons label').removeClass('active');
        $missionModal.find('input[name="mission_id"]').val(mission.mission_id || mission.temp_id);
        $missionModal.find('input[name="mission_group_id"]').val(mission.mission_group_id || '');
        
        const dateStr = mission.assignment_date;
        const displayDate = new Date(dateStr + 'T12:00:00').toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'});
        $('#multi_day_fields').hide();
        $('#modalDateDisplay').html(`Mission pour le: <strong>${displayDate}</strong>`).show();
        
        $missionModal.find('input[name="mission_text"]').val(mission.mission_text);
        $missionModal.find('textarea[name="comments"]').val(mission.comments);
        $missionModal.find('input[name="start_time"]').val(mission.start_time);
        $missionModal.find('input[name="end_time"]').val(mission.end_time);
        $missionModal.find('input[name="location"]').val(mission.location);
        $missionModal.find(`input[name="shift_type"][value="${mission.shift_type}"]`).prop('checked', true).parent().addClass('active');
        $missionModal.find('input[name="color"]').val(mission.color || DEFAULT_COLOR);
        $('.color-swatch.selected').removeClass('selected');
        $(`.color-swatch[data-color="${mission.color || DEFAULT_COLOR}"]`).addClass('selected');
        
        // Handle assets
        updateAssignedAssetsDisplay(mission.assigned_assets || []);
        
        $missionModal.find('#deleteMissionBtnFromModal').toggle(!!mission.mission_group_id); // Only show delete for saved missions
        $missionModal.modal('show');
    });

    $('#missionForm').on('submit', async function(e) {
        e.preventDefault();
        const missionId = $('#mission_id_form').val();
        const mission = state.missions.find(m => (m.mission_id || m.temp_id) == missionId);
        if (!mission) return;

        // Collect form data
        const formData = {
            mission_text: $(this).find('[name="mission_text"]').val(),
            comments: $(this).find('[name="comments"]').val(),
            start_time: $(this).find('[name="start_time"]').val(),
            end_time: $(this).find('[name="end_time"]').val(),
            location: $(this).find('[name="location"]').val(),
            shift_type: $(this).find('[name="shift_type"]:checked').val(),
            color: $(this).find('[name="color"]').val(),
            assigned_assets: mission.assigned_assets, // Persist assets from modal state
            assigned_asset_names: (mission.assigned_assets || []).map(a => a.name).join(', ')
        };

        if (mission.is_temporary) {
            // Update the temporary object in local state
            Object.assign(mission, formData);
            $missionModal.modal('hide');
            renderPlanningGrid();
        } else {
            // Update existing mission in DB
            showLoading(true);
            try {
                await apiCall('update_mission', 'POST', {
                    ...formData,
                    mission_group_id: mission.mission_group_id,
                    assigned_asset_ids: (formData.assigned_assets || []).map(a => a.id)
                });
                $missionModal.modal('hide');
                await fetchInitialData();
            } catch (error) {
                showModalError(error.message);
                showLoading(false);
            }
        }
    });
    
    // --- ASSET MODAL LOGIC ---
    function updateAssignedAssetsDisplay(assets) {
        const $display = $('#assigned_assets_display'); $display.empty();
        if (assets && assets.length > 0) {
            assets.forEach(asset => $display.append(`<span class="badge badge-info p-2 m-1">${asset.name}</span>`));
        } else {
            $display.html('<span class="text-muted small p-2">Aucun matériel assigné.</span>');
        }
    }
    
    $('#manageAssetsBtn').on('click', function() {
        const missionId = $('#mission_id_form').val();
        const mission = state.missions.find(m => (m.mission_id || m.temp_id) == missionId);
        populateAssetAssignmentModal(mission);
        $('#assetAssignmentModal').modal('show');
    });
    
    function populateAssetAssignmentModal(mission) {
        const $list = $('#asset_assignment_list'); $list.empty();
        const missionAssets = new Set((mission.assigned_assets || []).map(a => String(a.id)));
        const missionDate = mission.assignment_date;
        const bookedAssetIds = new Set();
        (state.bookings || []).forEach(b => {
            if (b.booking_date === missionDate && b.mission_group_id !== mission.mission_group_id) {
                bookedAssetIds.add(String(b.asset_id));
            }
        });
        const searchTerm = $('#asset_assignment_search').val().toLowerCase();
        (state.inventory || []).filter(a => (a.asset_name + (a.serial_or_plate || '')).toLowerCase().includes(searchTerm)).forEach(asset => {
            const isBooked = bookedAssetIds.has(String(asset.asset_id));
            const isChecked = missionAssets.has(String(asset.asset_id));
            const isDisabled = isBooked && !isChecked;
            $list.append(`<label class="list-group-item list-group-item-action d-flex justify-content-between align-items-center ${isDisabled ? 'disabled' : ''}">
                <span><input type="checkbox" class="mr-3" value="${asset.asset_id}" data-asset-name="${asset.asset_name}" ${isChecked ? 'checked' : ''} ${isDisabled ? 'disabled' : ''}>
                ${asset.asset_name} <small class="text-muted ml-2">${asset.serial_or_plate || ''}</small></span>
                ${isBooked ? `<span class="badge badge-danger">Réservé</span>` : ''}</label>`);
        });
    }

    $('#confirmAssetAssignmentBtn').on('click', function() {
        const missionId = $('#mission_id_form').val();
        const mission = state.missions.find(m => (m.mission_id || m.temp_id) == missionId);
        mission.assigned_assets = [];
        $('#asset_assignment_list input:checked').each(function() {
            mission.assigned_assets.push({ id: $(this).val(), name: $(this).data('asset-name') });
        });
        updateAssignedAssetsDisplay(mission.assigned_assets);
        $('#assetAssignmentModal').modal('hide');
    });
    
    // --- GENERAL UTILITY ---
    const $confirmationModal = $('#confirmationModal'), $confirmBtn = $('#confirmActionBtn');
    function showConfirmation(body, onConfirm) {
        $confirmationModal.find('#confirmationModalBody').text(body);
        $confirmBtn.off('click').on('click', () => { onConfirm(); $confirmationModal.modal('hide'); });
        $confirmationModal.modal('show');
    }
    
    $('#activateAllBtn').on('click', function() {
        const endDate = new Date(state.currentWeekStart);
        endDate.setDate(endDate.getDate() + 6);
        showConfirmation('Activer toutes les planifications pour la semaine en cours ?', async () => {
            showLoading(true);
            try {
                await apiCall('validate_all_for_week', 'POST', { start_date: getLocalDateString(state.currentWeekStart), end_date: getLocalDateString(endDate) });
                await fetchInitialData();
            } catch (error) { alert(`Erreur: ${error.message}`); showLoading(false); }
        });
    });

    // --- INITIALIZE ---
    fetchInitialData();
});
</script>
</body>
</html>
