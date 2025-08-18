<?php
// planning.php (Final version with all fixes)
require_once 'session-management.php';
require_once 'db-connection.php';
requireLogin();

$user = getCurrentUser();
if ($user['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$predefined_colors = ['#1877f2', '#34c759', '#ff9500', '#5856d6', '#ff3b30', '#6A0DAD', '#ffcc00', '#8e8e93', '#ff2d55', '#00a096'];
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
        :root { --primary: #007bff; --light-gray: #f0f2f5; --card-bg: #ffffff; --border-color: #dee2e6; --dark-yellow: #ffc107; --light-yellow: #fff8e1;}
        html, body { height: 100%; overflow: hidden; }
        body { background-color: var(--light-gray); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 0.8rem; }
        h4 { font-size: 1rem; }
        h5, .modal-title { font-size: 0.85rem; }
        .main-container { display: flex; height: calc(100vh - 78px); }
        .workers-list-col, .planning-col { height: 100%; overflow-y: auto; padding: 15px; }
        .workers-list-col { flex: 0 0 280px; background: var(--card-bg); border-right: 1px solid var(--border-color); }
        .planning-col { flex: 1; }
        .worker-item { padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px; margin-bottom: 8px; background-color: #fcfdff; cursor: grab; transition: all 0.2s ease; user-select: none; }
        .worker-item.unavailable { background-color: #f8d7da; border-color: #f5c6cb; }
        .worker-item.on-leave { background-color: var(--light-yellow); border-color: var(--dark-yellow); }
        .assignment-count { font-size: 0.5rem; color: #fff; background-color: #dc3545; border-radius: 10px; padding: 2px 8px; display: inline-block; margin-top: 5px; }
        .daily-planning-container { display: grid; grid-template-columns: repeat(7, 1fr); gap: 15px; min-height: 100%; }
        .day-column { background-color: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef; display: flex; flex-direction: column; }
        .day-header { padding: 10px; text-align: center; font-weight: 600; border-bottom: 1px solid var(--border-color); background-color: #f1f3f5; display:flex; justify-content:center; align-items:center; cursor: pointer; }
        .day-header.selected { background-color: var(--primary); color: white; }
        
        .mission-card { background-color: #fff; border-left: 5px solid; border-radius: 6px; padding: 10px; margin-bottom: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: relative; }
        .mission-card.conflicting-assignment { border: 2px solid red !important; }
        .mission-card.on-leave-assignment { border: 2px solid var(--dark-yellow) !important; }
        .mission-card.validated { opacity: 0.8; background-color: #e6ffed; }
        .mission-card-body { cursor: pointer; }
        .mission-title { font-weight: 600; font-size: 0.6rem; margin-bottom: 5px; }
        .mission-meta { font-size: 0.5rem; color: #6c757d; margin-bottom: 8px; }
        .assigned-workers-list { list-style: none; padding-left: 0; margin-bottom: 0; font-size: 0.5rem; }
        .assigned-workers-list li { background-color: #e7f1ff; padding: 3px 8px; border-radius: 4px; margin-top: 4px; display: flex; justify-content: space-between; align-items: center; }
        .assigned-workers-list li.on-leave { background-color: var(--light-yellow); color: #856404; font-weight: bold; }
        .remove-worker-btn { cursor: pointer; color: #dc3545; }
        .mission-placeholder { font-size: 0.55rem; color: #6c757d; text-align: center; padding: 20px; border: 2px dashed #ced4da; border-radius: 6px; height: 100%; display: flex; align-items: center; justify-content: center;}
        .mission-actions { position: absolute; top: 5px; right: 5px; display: flex; gap: 5px; background: rgba(255,255,255,0.8); border-radius: 5px; padding: 2px;}
        .action-btn { background: none; border: none; color: #6c757d; font-size: 0.5rem; cursor: pointer; padding: 3px; }
        .action-btn.validate-btn.validated { color: #28a545; }
        #loadingOverlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255, 255, 255, 0.7); z-index: 1060; display: none; justify-content: center; align-items: center; }
        .color-swatch { width:25px; height:25px; border-radius:50%; cursor:pointer; display:inline-block; margin:2px; border: 2px solid transparent; }
        .color-swatch.selected { border-color: #333; }
        #assigned_workers_pills .badge, #assigned_assets_display .badge { margin: 2px; font-size: 0.6rem; }
        .remove-assigned-worker { cursor: pointer; }
        label.list-group-item.disabled { background-color: #f8f9fa; cursor: not-allowed; }
        .mission-placeholder-bottom { padding: 15px; margin-top: 10px; border: 2px dashed #ced4da; border-radius: 6px; text-align: center; color: #6c757d; font-size: 0.7rem; transition: background-color 0.2s ease, border-color 0.2s ease; }
        .mission-placeholder-bottom:hover { background-color: #e9ecef; border-color: #007bff; }
        #missionFormModal.compact-modal .modal-body { padding: 1rem; }
        #missionFormModal.compact-modal .modal-title { font-size: 0.9rem; }
        #missionFormModal.compact-modal .form-group { margin-bottom: 0.5rem; }
        #missionFormModal.compact-modal label { font-size: 0.65rem; margin-bottom: 0.2rem; }
        #missionFormModal.compact-modal .form-control, #missionFormModal.compact-modal .btn { font-size: 0.7rem; padding: 0.25rem 0.5rem; height: auto; }
        #missionFormModal.compact-modal textarea.form-control { min-height: 50px; }
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

    <div class="modal fade" id="missionFormModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="missionForm">
                    <div class="modal-header bg-secondary text-white">
                        <h5 class="modal-title" id="missionFormModalLabel">Nouvelle Mission</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="mission_id_form" name="mission_id">
                        <input type="hidden" id="assignment_date_form" name="assignment_date">
                        <div class="alert alert-info" id="modalDateDisplay"></div>
                        <div class="form-row" id="multi_day_fields" style="display: none;">
                            <div class="form-group col-md-6"><label>Date de début *</label><input type="date" class="form-control" name="start_date"></div>
                            <div class="form-group col-md-6"><label>Date de fin *</label><input type="date" class="form-control" name="end_date"></div>
                        </div>
                        <div class="form-group"><label>Titre de la mission *</label><input type="text" class="form-control" name="mission_text" required></div>
                        <div class="form-group"><label>Commentaires</label><textarea class="form-control" name="comments" rows="3"></textarea></div>
                        <div class="form-row">
                            <div class="form-group col-md-6"><label>Heure début</label><input type="time" class="form-control" name="start_time" id="mission_start_time"></div>
                            <div class="form-group col-md-6"><label>Heure fin</label><input type="time" class="form-control" name="end_time" id="mission_end_time"></div>
                        </div>
                        <div class="form-group"><label>Lieu</label><input type="text" class="form-control" name="location"></div>
                        <div class="form-group">
                            <label>Type</label>
                            <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons" id="shift_type_buttons"></div>
                        </div>
                        <div class="form-group"><label>Couleur</label><div id="mission_color_swatches"></div><input type="hidden" name="color" value="<?= htmlspecialchars($default_color); ?>"></div>
                        <div id="assign-users-group" style="display:none;">
                             <hr>
                            <input type="hidden" name="assigned_user_ids" id="assigned_user_ids_hidden">
                            <label>Ouvriers assignés *</label>
                            <div id="assigned_workers_pills" class="d-flex flex-wrap align-items-center border rounded p-2 mb-3 bg-light" style="min-height: 50px;"><span class="text-muted small p-2" id="no_workers_assigned_text">Aucun ouvrier assigné.</span></div>
                            <label>Cliquer pour assigner un ouvrier :</label>
                            <div id="modal_available_workers" class="list-group" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>
                        <div id="asset-management-container" style="display: none;">
                            <hr>
                            <div class="form-group">
                                <input type="hidden" name="assigned_asset_ids" id="assigned_asset_ids_hidden">
                                <label>Matériel assigné</label>
                                <div id="assigned_assets_display" class="d-flex flex-wrap align-items-center border rounded p-2 mb-2 bg-light" style="min-height: 50px;">
                                    <span class="text-muted small p-2">Aucun matériel assigné.</span>
                                </div>
                                <button type="button" class="btn btn-sm btn-info" id="manageAssetsBtn">Gérer le Matériel</button>
                            </div>
                        </div>
                        <div id="modal_error_message" class="alert alert-danger mt-3" style="display: none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger mr-auto" id="deleteMissionBtn" style="display: none;">Supprimer</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="assetAssignmentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assigner du Matériel</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="text" id="asset_assignment_search" class="form-control mb-3" placeholder="Rechercher par nom ou n° de série...">
                    <div id="asset_assignment_list" class="list-group" style="max-height: 400px; overflow-y: auto;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" id="confirmAssetAssignmentBtn">Confirmer la Sélection</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmationModal" tabindex="-1">
        <div class="modal-dialog modal-sm"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirmation</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body" id="confirmationModalBody"></div><div class="modal-footer"><button type="button" class="btn btn-danger" data-dismiss="modal">Annuler</button><button type="button" class="btn btn-primary" id="confirmActionBtn">Confirmer</button></div></div></div>
    </div>

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
    let state = { staff: [], missions: [], inventory: [], bookings: [], currentWeekStart: getMonday(new Date()), draggedWorker: null, shouldRefreshOnModalClose: false, selectedDate: null };
    let assignedWorkersInModal = [];
    let assignedAssetsInModal = [];
    // --- BUG FIX 2: State variable to track asset selections while modal is open
    let tempSelectedAssetIds = new Set();


    // --- DOM ELEMENTS ---
    const $loading = $('#loadingOverlay');
    const $planningContainer = $('#dailyPlanningContainer');
    const $workerList = $('#workerList');
    const $missionModal = $('#missionFormModal');
    const $assetAssignmentModal = $('#assetAssignmentModal');

    // --- HELPERS ---
    const showLoading = (show) => $loading.toggle(show);
    const getLocalDateString = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };
    const formatTime = (time) => time ? time.substring(0, 5) : '';
    const showModalError = (msg) => $('#modal_error_message').text(msg).show();
    const hideModalError = () => $('#modal_error_message').hide();
    function getMonday(d) {
        d = new Date(d);
        let day = d.getDay(), diff = d.getDate() - day + (day === 0 ? -6 : 1);
        d.setHours(0, 0, 0, 0);
        const newDate = new Date(d.getTime());
        newDate.setDate(diff);
        return newDate;
    }

    function getDatesFromModal() {
        const dates = [];
        const isMulti = $('#multi_day_fields').is(':visible');
        const start = $('#missionForm input[name="start_date"]').val();
        const end = $('#missionForm input[name="end_date"]').val();
        const single = $('#assignment_date_form').val();

        if (isMulti && start && end) {
            for (let d = new Date(start); d <= new Date(end); d.setDate(d.getDate() + 1)) {
                dates.push(getLocalDateString(new Date(d)));
            }
        } else if (single) {
            dates.push(single);
        }
        return dates;
    }

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

    // --- DATA FETCHING ---
    async function fetchInitialData(manageLoadingState = true) {
        if (manageLoadingState) showLoading(true);
        try {
            const endDate = new Date(state.currentWeekStart);
            endDate.setDate(endDate.getDate() + 6);
            const data = await apiCall('get_initial_data', 'GET', { start: getLocalDateString(state.currentWeekStart), end: getLocalDateString(endDate) });
            state.staff = data.staff || [];
            state.missions = data.missions || [];
            state.inventory = data.inventory || [];
            state.bookings = data.bookings || [];
            if (state.selectedDate) {
                await refreshWorkerListForDate(state.selectedDate, false);
            }
            updateUI();
        } catch (error) {
            alert(`Erreur de chargement: ${error.message}`);
        } finally {
            if (manageLoadingState) showLoading(false);
        }
    }

    // --- UI RENDERING ---
    function updateUI() {
        renderWeekHeader();
        renderPlanningGrid();
        renderWorkerList();
        setupModalStaticContent();
    }

    function renderWeekHeader() {
        const endDate = new Date(state.currentWeekStart);
        endDate.setDate(endDate.getDate() + 6);
        const options = { day: 'numeric', month: 'long', year: 'numeric' };
        $('#currentWeekRange').text(`${state.currentWeekStart.toLocaleDateString('fr-FR', options)} - ${endDate.toLocaleDateString('fr-FR', options)}`);
    }

    function renderPlanningGrid() {
        $planningContainer.empty();
        for (let i = 0; i < 7; i++) {
            const dayDate = new Date(state.currentWeekStart);
            dayDate.setDate(dayDate.getDate() + i);
            const dateStr = getLocalDateString(dayDate);
            const dayLabel = dayDate.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric' });
            const isSelected = dateStr === state.selectedDate;

            const plusButton = `<button class="btn ml-2 add-mission-btn" data-date="${dateStr}" style="background-color: #ffffff; color: #007bff; border: 1px solid #dee2e6; border-radius: 50%; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; padding: 0; line-height: 1;"><i class="fas fa-plus" style="font-size: 14px;"></i></button>`;
            const $dayHeader = $(`<div class="day-header ${isSelected ? 'selected' : ''}" data-date="${dateStr}"><span>${dayLabel}</span>${plusButton}</div>`);

            const $dayColumn = $(`<div class="day-column"></div>`).append($dayHeader);
            const $dayContent = $(`<div class="day-content p-2" data-date="${dateStr}"></div>`).appendTo($dayColumn);
            $planningContainer.append($dayColumn);
            const dayMissions = state.missions.filter(m => m.assignment_date === dateStr);
            if (dayMissions.length > 0) {
                dayMissions.forEach(mission => $dayContent.append(createMissionCard(mission)));
                $dayContent.append(`<div class="mission-placeholder-bottom"><span>Glissez ici pour ajouter</span></div>`);
            } else {
                $dayContent.append(`<div class="mission-placeholder"><span>Glissez un ouvrier ici</span></div>`);
            }
        }
    }

    function createMissionCard(mission) {
        const assignedIds = mission.assigned_user_ids ? mission.assigned_user_ids.split(',').map(id => id.trim()) : [];
        const assignedNames = mission.assigned_user_names ? mission.assigned_user_names.split(', ') : [];
        const onLeaveFlags = mission.on_leave_flags ? mission.on_leave_flags.split(',') : [];
        const isConflicting = Array.isArray(mission.conflicting_assignments) && mission.conflicting_assignments.some(userId => assignedIds.includes(String(userId)));
        const isOnLeaveAssignment = mission.is_on_leave_assignment == 1;

        const workersHtml = assignedNames.map((name, i) => {
            const workerId = assignedIds[i];
            const isWorkerOnLeave = onLeaveFlags[i] === '1';
            const isWorkerConflicting = Array.isArray(mission.conflicting_assignments) && mission.conflicting_assignments.includes(workerId);
            let liClass = '';
            let nameStyle = '';
            if (isWorkerConflicting) {
                nameStyle = 'style="color: red; font-weight: bold;"';
            } else if (isWorkerOnLeave) {
                liClass = 'on-leave';
            }
            return `<li class="${liClass}"><span ${nameStyle}>${name}</span> <i class="fas fa-times remove-worker-btn" data-worker-id="${workerId}"></i></li>`;
        }).join('');

        const assetsHtml = mission.assigned_asset_names ? `<div class="mission-meta mt-2" style="font-size: 0.5rem;"><i class="fas fa-tools"></i> ${mission.assigned_asset_names}</div>` : '';
        
        const actionsHtml = `<div class="mission-actions">
            <i class="fas fa-trash-alt action-btn delete-btn" title="Supprimer"></i>
            <i class="fas fa-check-circle action-btn validate-btn ${mission.is_validated == 1 ? 'validated' : ''}" title="Valider"></i>
        </div>`;
        let missionCardClass = `mission-card ${mission.is_validated == 1 ? 'validated' : ''}`;
        if (isConflicting) {
            missionCardClass += ' conflicting-assignment';
        } else if (isOnLeaveAssignment) {
            missionCardClass += ' on-leave-assignment';
        }
        const missionCardStyle = `border-left-color: ${mission.color || '#6c757d'};`;

        return $(`<div class="${missionCardClass}" style="${missionCardStyle}" data-mission-id="${mission.mission_id}">
                ${actionsHtml}
                <div class="mission-card-body">
                    <div class="mission-title">${mission.mission_text}</div>
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
        state.staff.forEach(worker => {
            let statusText = '';
            let customClass = '';
            let showStatus = false;
            if (worker.status === 'assigned') {
                statusText = worker.missions;
                customClass = 'unavailable'; 
                showStatus = true;
            } else if (worker.status === 'on_leave' || worker.status === 'on_sick_leave') {
                statusText = worker.leave_type || 'En Congé';
                customClass = 'on-leave';
                showStatus = true;
            }
            $workerList.append(`<div class="worker-item ${customClass}" draggable="true" data-worker-id="${worker.user_id}" data-worker-name="${worker.prenom} ${worker.nom}">
                <div>${worker.prenom} ${worker.nom}</div>
                ${showStatus ? `<div class="assignment-count">${statusText}</div>` : ''}
            </div>`);
        });
    }

    async function refreshWorkerListForDate(date, manageLoadingState = true) {
        if (manageLoadingState) showLoading(true);
        try {
            const workerStatuses = await apiCall('get_worker_status_for_date', 'GET', { date: date });
            state.staff.forEach(worker => {
                const statusInfo = workerStatuses.find(s => s.user_id === worker.user_id);
                if (statusInfo) {
                    worker.status = statusInfo.status;
                    worker.leave_type = statusInfo.leave_type;
                    worker.missions = statusInfo.missions;
                } else {
                    worker.status = 'available';
                    worker.leave_type = null;
                    worker.missions = null;
                }
            });
            renderWorkerList();
        } catch (error) {
            alert(`Erreur de rafraîchissement des ouvriers: ${error.message}`);
        } finally {
            if (manageLoadingState) showLoading(false);
        }
    }

    // --- MODAL WORKER LOGIC ---
    function renderAssignedWorkersInModal() {
        const $list = $('#assigned_workers_pills'), $hidden = $('#assigned_user_ids_hidden'), $placeholder = $('#no_workers_assigned_text');
        $list.find('.badge').remove();
        $placeholder.toggle(assignedWorkersInModal.length === 0);
        assignedWorkersInModal.forEach(w => $list.append(`<span class="badge badge-primary p-2 m-1">${w.name}<i class="fas fa-times ml-2 remove-assigned-worker" data-worker-id="${w.id}"></i></span>`));
        $hidden.val(assignedWorkersInModal.map(w => w.id).join(','));
    }
    function renderAvailableWorkersInModal() {
        const $list = $('#modal_available_workers'); $list.empty();
        const assignedIds = new Set(assignedWorkersInModal.map(w => String(w.id)));
        state.staff.forEach(w => { if (!assignedIds.has(String(w.user_id))) $list.append(`<a href="#" class="list-group-item list-group-item-action py-2" data-worker-id="${w.user_id}" data-worker-name="${w.prenom} ${w.nom}">${w.prenom} ${w.nom}</a>`); });
    }

    // --- ASSET ASSIGNMENT LOGIC ---
    function updateAssignedAssetsDisplay() {
        const $display = $('#assigned_assets_display');
        const $hiddenInput = $('#assigned_asset_ids_hidden');
        $display.empty();
        if (assignedAssetsInModal.length > 0) {
            assignedAssetsInModal.forEach(asset => {
                const displayText = asset.serial ? `${asset.name} (${asset.serial})` : asset.name;
                $display.append(`<span class="badge badge-info p-2 m-1">${displayText}</span>`);
            });
        } else {
            $display.html('<span class="text-muted small p-2">Aucun matériel assigné.</span>');
        }
        $hiddenInput.val(assignedAssetsInModal.map(a => a.id).join(','));
    }
    
    function populateAssetAssignmentModal() {
        const $list = $('#asset_assignment_list');
        const $searchInput = $('#asset_assignment_search');
        const searchTerm = $searchInput.val().toLowerCase();
        
        $list.empty();
    
        const missionDates = getDatesFromModal();
        if (missionDates.length === 0) {
            $list.html('<div class="alert alert-warning">Veuillez sélectionner une date pour la mission avant de gérer le matériel.</div>');
            return;
        }
    
        const missionId = $('#mission_id_form').val();
        const currentMission = missionId ? state.missions.find(m => m.mission_id == missionId) : null;
    
        const bookedAssetIds = new Set();
        state.bookings.forEach(b => {
            if (missionDates.includes(b.booking_date)) {
                if (currentMission && b.mission_group_id === currentMission.mission_group_id) return;
                bookedAssetIds.add(String(b.asset_id));
            }
        });
    
        state.inventory
            .filter(asset => (asset.asset_name.toLowerCase() + (asset.serial_or_plate || '')).includes(searchTerm))
            .forEach(asset => {
                const assetIdStr = String(asset.asset_id);
                const isBooked = bookedAssetIds.has(assetIdStr);
                // --- BUG FIX 2: Check against the persistent temp set, not the DOM
                const isChecked = tempSelectedAssetIds.has(assetIdStr);
                const serialText = asset.serial_or_plate || '';
                const itemHtml = `<label class="list-group-item list-group-item-action d-flex justify-content-between align-items-center ${isBooked && !isChecked ? 'disabled' : ''}">
                    <span><input type="checkbox" class="mr-3 asset-checkbox" value="${asset.asset_id}" ${isChecked ? 'checked' : ''} ${isBooked && !isChecked ? 'disabled' : ''}>
                    ${asset.asset_name} <small class="text-muted ml-2">${serialText}</small></span>
                    ${isBooked ? `<span class="badge badge-danger">Réservé</span>` : ''}</label>`;
                $list.append(itemHtml);
            });
    }

    // --- EVENT HANDLERS ---
    $('#prevWeekBtn').on('click', () => { state.currentWeekStart.setDate(state.currentWeekStart.getDate() - 7); fetchInitialData(); });
    $('#nextWeekBtn').on('click', () => { state.currentWeekStart.setDate(state.currentWeekStart.getDate() + 7); fetchInitialData(); });

    $('#addMultiDayMissionBtn').on('click', function() {
        $missionModal.removeClass('compact-modal');
        assignedWorkersInModal = []; assignedAssetsInModal = [];
        $missionModal.find('form')[0].reset(); hideModalError();
        $('#shift_type_buttons label').removeClass('active');
        $missionModal.find('input[name="mission_id"]').val('');
        $('#modalDateDisplay').hide();
        $('#multi_day_fields').show();
        $('#assign-users-group').show();
        $('#asset-management-container').show();
        $('#deleteMissionBtn').hide();
        $missionModal.find('#missionFormModalLabel').text('Nouvelle Mission sur Plusieurs Jours');
        renderAssignedWorkersInModal(); renderAvailableWorkersInModal();
        updateAssignedAssetsDisplay();
        $missionModal.modal('show');
    });

    $planningContainer.on('click', '.day-header', function() {
        const date = $(this).data('date');
        state.selectedDate = date;
        $('.day-header.selected').removeClass('selected');
        $(this).addClass('selected');
        refreshWorkerListForDate(date);
    });
    
    $planningContainer.on('click', '.add-mission-btn', function(e) {
        e.stopPropagation();
        const date = $(this).data('date');
        openModalForCreate(date, false);
    });

    $workerList.on('dragstart', '.worker-item', (e) => { state.draggedWorker = { id: $(e.currentTarget).data('worker-id'), name: $(e.currentTarget).data('worker-name') }; });
    $planningContainer.on('dragover', '.day-content, .mission-card, .mission-placeholder-bottom', (e) => e.preventDefault());
    $planningContainer.on('drop', '.day-content', handleDrop);
    
    $planningContainer.on('click', '.validate-btn', async function(e){ e.stopPropagation(); const missionId = $(this).closest('.mission-card').data('mission-id'); showLoading(true); try { await apiCall('toggle_mission_validation', 'POST', { mission_id: missionId }); await fetchInitialData(false); } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); } });
    $planningContainer.on('click', '.delete-btn', function(e) { e.stopPropagation(); const missionId = $(this).closest('.mission-card').data('mission-id'); showConfirmation('Voulez-vous vraiment supprimer cette mission ?', async () => { showLoading(true); try { await apiCall('delete_mission_group', 'POST', { mission_id: missionId }); await fetchInitialData(false); } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); } }); });
    $('#activateAllBtn').on('click', async function() { const endDate = new Date(state.currentWeekStart); endDate.setDate(endDate.getDate() + 6); showConfirmation('Voulez-vous activer toutes les planifications pour la semaine en cours ?', async () => { showLoading(true); try { await apiCall('validate_all_for_week', 'POST', { start_date: getLocalDateString(state.currentWeekStart), end_date: getLocalDateString(endDate) }); await fetchInitialData(false); } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); } }); });

    async function handleDrop(e) {
        e.preventDefault(); e.stopPropagation();
        if (!state.draggedWorker) return;
        const $target = $(e.target);
        const $missionCard = $target.closest('.mission-card');
        if ($missionCard.length > 0) {
            showLoading(true);
            try {
                const missionId = $missionCard.data('mission-id');
                const mission = state.missions.find(m => m.mission_id == missionId);
                await apiCall('assign_worker_to_mission', 'POST', { worker_id: state.draggedWorker.id, mission_id: missionId, assignment_date: mission.assignment_date });
                await fetchInitialData(false);
            } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); state.draggedWorker = null; }
        } else {
            const date = $target.closest('.day-content').data('date');
            openModalForCreate(date, true);
        }
    }

    // --- MODAL & FORM LOGIC ---
    function setupModalStaticContent() {
        $('#shift_type_buttons').html(Object.entries({matin:'Matin', 'apres-midi':'Après-midi', nuit:'Nuit', repos:'Repos', custom:'Personnalisé'}).map(([v, l]) => `<label class="btn btn-sm btn-outline-secondary"><input type="radio" name="shift_type" value="${v}" required> ${l}</label>`).join(''));
        $('#mission_color_swatches').html(PREDEFINED_COLORS.map(c => `<div class="color-swatch" style="background-color:${c};" data-color="${c}"></div>`).join(''));
    }

    function openModalForCreate(date, fromDragDrop = false) {
        assignedWorkersInModal = []; assignedAssetsInModal = [];
        $missionModal.find('form')[0].reset(); hideModalError();
        $('#shift_type_buttons label').removeClass('active');
        $missionModal.find('input[name="mission_id"]').val('');
        $missionModal.find('input[name="assignment_date"]').val(date);
        const [year, month, day] = date.split('-').map(Number);
        const correctDate = new Date(Date.UTC(year, month - 1, day));
        const displayDate = correctDate.toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' });
        $('#multi_day_fields').hide();
        $('#modalDateDisplay').html(`Mission pour le: <strong>${displayDate}</strong>`).show();
        $missionModal.find('#deleteMissionBtn').hide();
        $('#asset-management-container').show();
        updateAssignedAssetsDisplay();

        if (fromDragDrop && state.draggedWorker) {
            $missionModal.addClass('compact-modal');
            $('#assign-users-group').hide();
            $missionModal.find('#missionFormModalLabel').text(`Nouvelle Mission pour ${state.draggedWorker.name}`);
        } else {
            $missionModal.removeClass('compact-modal');
            $('#assign-users-group').show();
            $missionModal.find('#missionFormModalLabel').text('Nouvelle Mission');
            renderAssignedWorkersInModal();
            renderAvailableWorkersInModal();
        }
        $missionModal.modal('show');
    }

    $planningContainer.on('click', '.mission-card-body', function() {
        const mission = state.missions.find(m => m.mission_id == $(this).closest('.mission-card').data('mission-id'));
        if (!mission) return;
        $missionModal.removeClass('compact-modal');
        $missionModal.find('form')[0].reset(); hideModalError();
        assignedWorkersInModal = [];
        assignedAssetsInModal = mission.assigned_assets || [];
        $('#shift_type_buttons label').removeClass('active');
        $missionModal.find('input[name="mission_id"]').val(mission.mission_id);
        $missionModal.find('input[name="assignment_date"]').val(mission.assignment_date);
        const [year, month, day] = mission.assignment_date.split('-').map(Number);
        const correctDate = new Date(Date.UTC(year, month - 1, day));
        const displayDate = correctDate.toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' });
        $('#multi_day_fields').hide();
        $('#modalDateDisplay').html(`Modifier la mission du: <strong>${displayDate}</strong>`).show();
        $missionModal.find('input[name="mission_text"]').val(mission.mission_text);
        $missionModal.find('textarea[name="comments"]').val(mission.comments);
        $missionModal.find('input[name="start_time"]').val(mission.start_time);
        $missionModal.find('input[name="end_time"]').val(mission.end_time);
        $missionModal.find('input[name="location"]').val(mission.location);
        $missionModal.find(`input[name="shift_type"][value="${mission.shift_type}"]`).prop('checked', true).parent().addClass('active');
        $missionModal.find('input[name="color"]').val(mission.color || DEFAULT_COLOR);
        $('.color-swatch.selected').removeClass('selected');
        $(`.color-swatch[data-color="${mission.color || DEFAULT_COLOR}"]`).addClass('selected');
        $missionModal.find('#missionFormModalLabel').text('Modifier la Mission');
        $missionModal.find('#deleteMissionBtn').show();
        $('#assign-users-group').hide();
        $('#asset-management-container').show();
        updateAssignedAssetsDisplay();
        $missionModal.modal('show');
    });

    // --- EVENT LISTENERS CONTINUED ---
    $('#shift_type_buttons').on('change', 'input[name="shift_type"]', function() { const dis = $(this).val() === 'repos'; $('#mission_start_time, #mission_end_time').prop('disabled', dis).val(dis ? '' : $('#mission_start_time').val()); });
    $('#mission_color_swatches').on('click', '.color-swatch', function(){ $(this).addClass('selected').siblings().removeClass('selected'); $('input[name="color"]').val($(this).data('color')); });
    $('#modal_available_workers').on('click', '.list-group-item', function(e) { e.preventDefault(); assignedWorkersInModal.push({ id: $(this).data('worker-id'), name: $(this).data('worker-name') }); renderAssignedWorkersInModal(); $(this).remove(); });
    $('#assigned_workers_pills').on('click', '.remove-assigned-worker', function() { const id = $(this).data('worker-id'); assignedWorkersInModal = assignedWorkersInModal.filter(w => String(w.id) !== String(id)); renderAssignedWorkersInModal(); renderAvailableWorkersInModal(); });
    
    $('#manageAssetsBtn').on('click', function() {
        // --- BUG FIX 2: Initialize the temp set from the current assignments
        tempSelectedAssetIds = new Set(assignedAssetsInModal.map(a => String(a.id)));
        populateAssetAssignmentModal();
        $assetAssignmentModal.modal('show');
    });

    $('#asset_assignment_search').on('keyup', populateAssetAssignmentModal);
    
    // --- BUG FIX 2: Update the temp set whenever a checkbox changes
    $('#asset_assignment_list').on('change', '.asset-checkbox', function() {
        const assetId = $(this).val();
        if (this.checked) {
            tempSelectedAssetIds.add(assetId);
        } else {
            tempSelectedAssetIds.delete(assetId);
        }
    });

    $('#confirmAssetAssignmentBtn').on('click', function() {
        // --- BUG FIX 2: Rebuild the main assignment array from the temp set
        assignedAssetsInModal = [];
        tempSelectedAssetIds.forEach(assetId => {
            const asset = state.inventory.find(a => String(a.asset_id) === assetId);
            if (asset) {
                assignedAssetsInModal.push({ 
                    id: asset.asset_id, 
                    name: asset.asset_name, 
                    serial: asset.serial_or_plate 
                });
            }
        });
        updateAssignedAssetsDisplay();
        $assetAssignmentModal.modal('hide');
    });

    // --- BUG FIX 1: Add this event handler to fix the scrolling issue
    $assetAssignmentModal.on('hidden.bs.modal', function () {
        // When the asset modal is hidden, check if the main mission modal is still visible.
        // If it is, Bootstrap might have incorrectly removed the 'modal-open' class from the body,
        // which disables scrolling. This line adds it back, restoring the scrollbar.
        if ($missionModal.is(':visible')) {
            $('body').addClass('modal-open');
        }
    });

    $('#missionForm').on('submit', async function(e) {
        e.preventDefault();
        hideModalError();
        const formData = Object.fromEntries(new FormData(this).entries());
        if (!formData.mission_id) { 
            if ($missionModal.hasClass('compact-modal')) {
                if (state.draggedWorker) {
                    formData.assigned_user_ids = [state.draggedWorker.id];
                } else {
                    showModalError("Erreur: L'ouvrier n'a pas été trouvé. Veuillez fermer et réessayer.");
                    return;
                }
            } else {
                if (!assignedWorkersInModal.length) {
                    showModalError("Veuillez assigner au moins un ouvrier.");
                    return;
                }
                formData.assigned_user_ids = assignedWorkersInModal.map(w => w.id);
            }
        }
        if (formData.assigned_asset_ids) formData.assigned_asset_ids = formData.assigned_asset_ids.split(',').filter(id => id); else formData.assigned_asset_ids = [];
        showLoading(true);
        try {
            await apiCall('save_mission', 'POST', formData);
            state.shouldRefreshOnModalClose = true;
            $missionModal.modal('hide');
        } catch (error) { showModalError(error.message); } finally { showLoading(false); }
    });

    $missionModal.on('hidden.bs.modal', function () {
        hideModalError();
        assignedAssetsInModal = []; assignedWorkersInModal = [];
        if (state.shouldRefreshOnModalClose) { fetchInitialData(); state.shouldRefreshOnModalClose = false; }
        state.draggedWorker = null;
    });

    const $confirmationModal = $('#confirmationModal'), $confirmBtn = $('#confirmActionBtn');
    function showConfirmation(body, onConfirm) {
        $confirmationModal.find('#confirmationModalBody').text(body);
        $confirmBtn.off('click').on('click', () => { onConfirm(); $confirmationModal.modal('hide'); });
        $confirmationModal.modal('show');
    }

    $planningContainer.on('click', '.remove-worker-btn', function(e) { e.stopPropagation(); const missionId = $(this).closest('.mission-card').data('mission-id'); const workerId = $(this).data('worker-id'); const workerName = $(this).closest('li').text().trim(); showConfirmation(`Retirer ${workerName} de cette mission ?`, async () => { showLoading(true); try { await apiCall('remove_worker_from_mission', 'POST', { mission_id: missionId, worker_id: workerId }); await fetchInitialData(false); } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); } }); });
    $('#deleteMissionBtn').on('click', function() { const missionId = $('#mission_id_form').val(); if (!missionId) return; $missionModal.modal('hide'); showConfirmation('Supprimer cette mission, ses affectations, et ses réservations de matériel ?', async () => { showLoading(true); try { await apiCall('delete_mission_group', 'POST', { mission_id: missionId }); await fetchInitialData(false); } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); } }); });

    fetchInitialData();
});
</script>
</body>
</html>
