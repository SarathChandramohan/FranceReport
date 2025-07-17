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
        :root { --primary: #007bff; --light-gray: #f0f2f5; --card-bg: #ffffff; --border-color: #dee2e6; --dark-yellow: #ffc107; --light-yellow: #fff8e1;}
        html, body { height: 100%; overflow: hidden; }
        body { background-color: var(--light-gray); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 0.8rem; }
        h4 { font-size: 1rem; }
        h5 { font-size: 0.85rem; }
        .main-container { display: flex; height: calc(100vh - 78px); }
        .workers-list-col, .planning-col { height: 100%; padding: 15px; }
        .workers-list-col { flex: 0 0 280px; background: var(--card-bg); border-right: 1px solid var(--border-color); overflow-y: auto; }
        .planning-col { flex: 1; overflow-y: auto; }
        .worker-item { padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px; margin-bottom: 8px; background-color: #fcfdff; cursor: grab; transition: all 0.2s ease; user-select: none; }
        .worker-item.unavailable { background-color: #f8d7da; border-color: #f5c6cb; cursor: not-allowed; }
        .worker-item.on-leave { background-color: var(--light-yellow); border-color: var(--dark-yellow); }
        .assignment-count { font-size: 0.5rem; color: #fff; background-color: #dc3545; border-radius: 10px; padding: 2px 8px; display: inline-block; margin-top: 5px; }
        .planning-view-container { display: flex; flex-direction: column; height: 100%; }
        .daily-planning-container { display: grid; grid-template-columns: repeat(7, 1fr); gap: 15px; flex-grow: 1; }
        .day-column { background-color: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef; display: flex; flex-direction: column; }
        .day-header { padding: 10px; text-align: center; font-weight: 600; border-bottom: 1px solid var(--border-color); background-color: #f1f3f5; display:flex; justify-content:space-between; align-items:center; cursor: pointer; }
        .day-header.selected { background-color: var(--primary); color: white; }
        .add-daily-mission-btn { background-color: var(--primary); color: white; border-radius: 50%; width: 24px; height: 24px; line-height: 24px; text-align: center; padding: 0; font-size: 16px; font-weight: bold; border: none; cursor: pointer; }
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
        .color-swatch { width:22px; height:22px; border-radius:50%; cursor:pointer; display:inline-block; margin:2px; border: 2px solid transparent; }
        .color-swatch.selected { border-color: #333; }
        .mission-placeholder-bottom { padding: 15px; margin-top: 10px; border: 2px dashed #ced4da; border-radius: 6px; text-align: center; color: #6c757d; font-size: 0.7rem; transition: background-color 0.2s ease, border-color 0.2s ease; }
        .mission-placeholder-bottom:hover { background-color: #e9ecef; border-color: #007bff; }
        
        /* Compact Form Styles */
        #missionFormView { background-color: var(--card-bg); border-radius: 8px; padding: 15px; display: flex; flex-direction: column; height: 100%; }
        #missionFormView .form-header { padding-bottom: 10px; margin-bottom: 15px; }
        #missionFormView .form-body { flex-grow: 1; overflow-y: auto; padding-right: 15px; margin-right: -15px; }
        #missionFormView .form-footer { padding-top: 10px; margin-top: 15px; }
        #missionFormView h5 { font-size: 0.75rem; font-weight: 600; }
        #missionFormView label { font-size: 0.65rem; font-weight: 500; margin-bottom: 2px; }
        #missionFormView .form-group { margin-bottom: 0.6rem; }
        #missionFormView .form-control, #missionFormView .btn { font-size: 0.7rem; padding: 0.25rem 0.5rem; height: auto; }
        #missionFormView textarea.form-control { min-height: 40px; }
        #missionFormView .alert { font-size: 0.7rem; padding: 0.4rem 0.8rem; }
        #assigned_workers_drop_zone { border: 2px dashed #ced4da; border-radius: 6px; padding: 10px; background-color: #f8f9fa; min-height: 80px; transition: background-color 0.2s ease; }
        #assigned_workers_drop_zone.drag-over { background-color: #e2e6ea; border-color: var(--primary); }
        #assigned_workers_pills .badge { margin: 2px; font-size: 0.65rem; padding: 0.3em 0.6em; }
        .remove-assigned-worker { cursor: pointer; margin-left: 6px; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="main-container">
        <div class="workers-list-col">
            <h5><i class="fas fa-users"></i> Ouvriers Emploi</h5>
            <div id="workerList"></div>
        </div>

        <div class="planning-col" id="planningView">
             <div class="planning-view-container">
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

        <div class="planning-col" id="missionFormView" style="display: none;">
            <form id="missionForm" class="d-flex flex-column h-100">
                <div class="form-header">
                    <h5 id="missionFormTitle">Nouvelle Mission</h5>
                    <div class="alert alert-info mt-2" id="formDateDisplay" style="display: none;"></div>
                </div>
                <div class="form-body">
                    <input type="hidden" id="mission_id_form" name="mission_id">
                    <input type="hidden" id="assignment_date_form" name="assignment_date">
                    <div class="form-row" id="multi_day_fields_form" style="display: none;">
                        <div class="form-group col-md-6"><label>Date de début *</label><input type="date" class="form-control" name="start_date"></div>
                        <div class="form-group col-md-6"><label>Date de fin *</label><input type="date" class="form-control" name="end_date"></div>
                    </div>
                    <div class="form-group"><label>Titre de la mission *</label><input type="text" class="form-control" name="mission_text" required></div>
                    <div class="form-group"><label>Commentaires</label><textarea class="form-control" name="comments" rows="2"></textarea></div>
                    <div class="form-row">
                        <div class="form-group col-md-6"><label>Heure début</label><input type="time" class="form-control" name="start_time" id="mission_start_time"></div>
                        <div class="form-group col-md-6"><label>Heure fin</label><input type="time" class="form-control" name="end_time" id="mission_end_time"></div>
                    </div>
                    <div class="form-group"><label>Lieu</label><input type="text" class="form-control" name="location"></div>
                    <div class="form-group"><label>Type</label><div class="btn-group btn-group-toggle d-flex" data-toggle="buttons" id="shift_type_buttons_form"></div></div>
                    <div class="form-group"><label>Couleur</label><div id="mission_color_swatches_form"></div><input type="hidden" name="color" value="<?= htmlspecialchars($default_color); ?>"></div>
                    <div id="assign-users-group-form">
                        <hr class="my-2"><input type="hidden" name="assigned_user_ids" id="assigned_user_ids_hidden_form">
                        <label>Ouvriers assignés * (Glissez et déposez)</label>
                        <div id="assigned_workers_drop_zone">
                            <div id="assigned_workers_pills" class="d-flex flex-wrap align-items-center">
                                <span class="text-muted small p-2" id="drop_placeholder_text">Déposez les ouvriers ici.</span>
                            </div>
                        </div>
                    </div>
                    <div id="asset-management-container-form" class="mt-3">
                        <hr class="my-2"><div class="form-group">
                            <input type="hidden" name="assigned_asset_ids" id="assigned_asset_ids_hidden_form">
                            <label>Matériel assigné</label>
                            <div id="assigned_assets_display_form" class="d-flex flex-wrap align-items-center border rounded p-2 mb-2 bg-light" style="min-height: 40px;"><span class="text-muted small p-2">Aucun matériel assigné.</span></div>
                            <button type="button" class="btn btn-sm btn-info" id="manageAssetsBtnForm">Gérer le Matériel</button>
                        </div>
                    </div>
                    <div id="form_error_message" class="alert alert-danger mt-3" style="display: none;"></div>
                </div>
                <div class="form-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-danger" id="deleteMissionBtnForm" style="display: none;">Supprimer</button>
                    <div>
                        <button type="button" class="btn btn-secondary" id="cancelMissionFormBtn">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="assetAssignmentModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Assigner du Matériel</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body"><input type="text" id="asset_assignment_search" class="form-control mb-3" placeholder="Rechercher par nom ou n° de série..."><div id="asset_assignment_list" class="list-group" style="max-height: 400px; overflow-y: auto;"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button><button type="button" class="btn btn-primary" id="confirmAssetAssignmentBtn">Confirmer la Sélection</button></div></div></div></div>
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
    let state = { staff: [], missions: [], inventory: [], bookings: [], currentWeekStart: getMonday(new Date()), draggedWorker: null, selectedDate: null };
    let assignedWorkersInForm = [];
    let assignedAssetsInForm = [];

    // --- DOM ELEMENTS ---
    const $loading = $('#loadingOverlay');
    const $planningView = $('#planningView');
    const $missionFormView = $('#missionFormView');
    const $planningContainer = $('#dailyPlanningContainer');
    const $workerList = $('#workerList');
    const $missionForm = $('#missionForm');

    // --- HELPERS ---
    const showLoading = (show) => $loading.toggle(show);
    const getLocalDateString = (date) => new Date(date.getTime() - (date.getTimezoneOffset() * 60000)).toISOString().split('T')[0];
    const formatTime = (time) => time ? time.substring(0, 5) : '';
    const showFormError = (msg) => $('#form_error_message').text(msg).show();
    const hideFormError = () => $('#form_error_message').hide();
    function getMonday(d) {
        d = new Date(d);
        let day = d.getDay(), diff = d.getDate() - day + (day === 0 ? -6 : 1);
        d.setHours(0, 0, 0, 0);
        return new Date(d.getFullYear(), d.getMonth(), d.getDate());
    }
    function getFormDates() {
        const dates = [];
        if ($('#multi_day_fields_form').is(':visible')) {
            const start = $missionForm.find('input[name="start_date"]').val();
            const end = $missionForm.find('input[name="end_date"]').val();
            if (start && end) {
                for (let d = new Date(start); d <= new Date(end); d.setDate(d.getDate() + 1)) {
                    dates.push(getLocalDateString(d));
                }
            }
        } else {
            const single = $('#assignment_date_form').val();
            if(single) dates.push(single);
        }
        return dates;
    }

    // --- API CALLS ---
    async function apiCall(action, method = 'POST', data = {}) {
        const options = { method, headers: { 'Content-Type': 'application/json' } };
        let url = `${HANDLER_URL}?action=${action}`;
        if (method === 'GET') url += '&' + new URLSearchParams(data).toString();
        else options.body = JSON.stringify(data);
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
            const endDate = new Date(state.currentWeekStart); endDate.setDate(endDate.getDate() + 6);
            const data = await apiCall('get_initial_data', 'GET', { start: getLocalDateString(state.currentWeekStart), end: getLocalDateString(endDate) });
            state.staff = data.staff || [];
            state.missions = data.missions || [];
            state.inventory = data.inventory || [];
            state.bookings = data.bookings || [];
            if (state.selectedDate) await refreshWorkerListForDate(state.selectedDate, false);
            updateUI();
        } catch (error) { alert(`Erreur de chargement: ${error.message}`); }
        finally { if (manageLoadingState) showLoading(false); }
    }

    // --- UI RENDERING (Planning View) ---
    function updateUI() {
        renderWeekHeader();
        renderPlanningGrid();
        renderWorkerList();
        setupFormStaticContent();
    }
    function renderWeekHeader() {
        const endDate = new Date(state.currentWeekStart); endDate.setDate(endDate.getDate() + 6);
        const options = { day: 'numeric', month: 'long', year: 'numeric' };
        $('#currentWeekRange').text(`${state.currentWeekStart.toLocaleDateString('fr-FR', options)} - ${endDate.toLocaleDateString('fr-FR', options)}`);
    }
    function renderPlanningGrid() {
        $planningContainer.empty();
        for (let i = 0; i < 7; i++) {
            const dayDate = new Date(state.currentWeekStart); dayDate.setDate(dayDate.getDate() + i);
            const dateStr = getLocalDateString(dayDate);
            const dayLabel = dayDate.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric' });
            const isSelected = dateStr === state.selectedDate;
            const $dayHeader = $(`<div class="day-header ${isSelected ? 'selected' : ''}" data-date="${dateStr}"><span>${dayLabel}</span><button class="add-daily-mission-btn" data-date="${dateStr}">+</button></div>`);
            const $dayColumn = $(`<div class="day-column"></div>`).append($dayHeader);
            const $dayContent = $(`<div class="day-content p-2"></div>`).appendTo($dayColumn);
            $planningContainer.append($dayColumn);
            const dayMissions = state.missions.filter(m => m.assignment_date === dateStr);
            if (dayMissions.length > 0) {
                dayMissions.forEach(mission => $dayContent.append(createMissionCard(mission)));
                $dayContent.append(`<div class="mission-placeholder-bottom" data-date="${dateStr}"><span>Glissez ici pour ajouter</span></div>`);
            } else {
                $dayContent.append(`<div class="mission-placeholder" data-date="${dateStr}"><span>Glissez un ouvrier ici</span></div>`);
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
            let liClass = isWorkerOnLeave ? 'on-leave' : '';
            let nameStyle = isWorkerConflicting ? 'style="color: red; font-weight: bold;"' : '';
            return `<li class="${liClass}"><span ${nameStyle}>${name}</span> <i class="fas fa-times remove-worker-btn" data-worker-id="${workerId}"></i></li>`;
        }).join('');

        const assetsHtml = mission.assigned_asset_names ? `<div class="mission-meta mt-2" style="font-size: 0.5rem;"><i class="fas fa-tools"></i> ${mission.assigned_asset_names}</div>` : '';
        const actionsHtml = `<div class="mission-actions">
            <i class="fas fa-trash-alt action-btn delete-btn" title="Supprimer"></i>
            <i class="fas fa-check-circle action-btn validate-btn ${mission.is_validated == 1 ? 'validated' : ''}" title="Valider"></i>
        </div>`;
        let missionCardClass = `mission-card ${mission.is_validated == 1 ? 'validated' : ''}`;
        if (isConflicting) missionCardClass += ' conflicting-assignment';
        else if (isOnLeaveAssignment) missionCardClass += ' on-leave-assignment';
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
            let statusText = '', customClass = '', showStatus = false;
            if (worker.status === 'assigned') { statusText = 'Assigné'; customClass = 'unavailable'; showStatus = true; }
            else if (worker.status === 'on_leave' || worker.status === 'on_sick_leave') { statusText = worker.leave_type || 'En Congé'; customClass = 'on-leave'; showStatus = true; }
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
                worker.status = statusInfo ? statusInfo.status : 'available';
                worker.leave_type = statusInfo ? statusInfo.leave_type : null;
            });
            renderWorkerList();
        } catch (error) { alert(`Erreur de rafraîchissement des ouvriers: ${error.message}`); }
        finally { if (manageLoadingState) showLoading(false); }
    }


    // --- UI RENDERING (Form View) ---
    function showMissionFormView(config = {}) {
        $planningView.hide();
        $missionFormView.show();
        resetAndConfigureForm(config);
    }
    function hideMissionFormView() {
        $missionFormView.hide();
        $planningView.show();
    }
    function resetAndConfigureForm(config) {
        $missionForm[0].reset(); hideFormError();
        assignedWorkersInForm = []; assignedAssetsInForm = [];
        $('#shift_type_buttons_form label').removeClass('active');
        $('#deleteMissionBtnForm').hide();
        $('#assign-users-group-form').show();

        const $startDateInput = $missionForm.find('input[name="start_date"]');
        const $endDateInput = $missionForm.find('input[name="end_date"]');

        if (config.isMultiDay) {
            $('#missionFormTitle').text('Nouvelle Mission sur Plusieurs Jours');
            $('#formDateDisplay').hide();
            $('#multi_day_fields_form').show();
            $('#assignment_date_form').val('');
            $startDateInput.prop('required', true); // BUG FIX
            $endDateInput.prop('required', true);   // BUG FIX
        } else { // Single day (create or edit)
            $('#multi_day_fields_form').hide();
            $startDateInput.prop('required', false); // BUG FIX
            $endDateInput.prop('required', false);  // BUG FIX
            
            if (config.mission) { // Editing
                const mission = config.mission;
                $('#missionFormTitle').text('Modifier la Mission');
                $('#formDateDisplay').show();
                setFormDate(mission.assignment_date);
                $('#mission_id_form').val(mission.mission_id);
                $('#assignment_date_form').val(mission.assignment_date);
                $missionForm.find('input[name="mission_text"]').val(mission.mission_text);
                $missionForm.find('textarea[name="comments"]').val(mission.comments);
                $missionForm.find('input[name="start_time"]').val(mission.start_time);
                $missionForm.find('input[name="end_time"]').val(mission.end_time);
                $missionForm.find('input[name="location"]').val(mission.location);
                $missionForm.find(`input[name="shift_type"][value="${mission.shift_type}"]`).prop('checked', true).parent().addClass('active');
                assignedAssetsInForm = mission.assigned_assets || [];
                $('#assign-users-group-form').hide();
                $('#deleteMissionBtnForm').show();
                updateColorSwatches(mission.color || DEFAULT_COLOR);
            } else { // Creating single day
                $('#missionFormTitle').text('Nouvelle Mission');
                $('#formDateDisplay').show();
                setFormDate(config.date);
                $('#assignment_date_form').val(config.date);
                if (config.worker) {
                    assignedWorkersInForm.push(config.worker);
                    $('#missionFormTitle').text(`Nouvelle Mission pour ${config.worker.name}`);
                }
                updateColorSwatches(DEFAULT_COLOR);
            }
        }
        
        renderAssignedWorkersInForm();
        updateAssignedAssetsDisplay();
    }
    function setFormDate(dateStr) {
        const [year, month, day] = dateStr.split('-').map(Number);
        const date = new Date(Date.UTC(year, month - 1, day));
        const displayDate = date.toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' });
        $('#formDateDisplay').html(`Mission pour le: <strong>${displayDate}</strong>`);
    }
    function setupFormStaticContent() {
        $('#shift_type_buttons_form').html(Object.entries({matin:'Matin', 'apres-midi':'Après-midi', nuit:'Nuit', repos:'Repos', custom:'Personnalisé'}).map(([v, l]) => `<label class="btn btn-sm btn-outline-secondary"><input type="radio" name="shift_type" value="${v}"> ${l}</label>`).join(''));
        $('#mission_color_swatches_form').html(PREDEFINED_COLORS.map(c => `<div class="color-swatch" style="background-color:${c};" data-color="${c}"></div>`).join(''));
    }

    // --- WORKER & ASSET ASSIGNMENT LOGIC (in Form) ---
    function renderAssignedWorkersInForm() {
        const $list = $('#assigned_workers_pills');
        const $hidden = $('#assigned_user_ids_hidden_form');
        const $placeholder = $('#drop_placeholder_text');
        $list.find('.badge').remove();
        $placeholder.toggle(assignedWorkersInForm.length === 0);
        assignedWorkersInForm.forEach(w => $list.prepend(`<span class="badge badge-primary">${w.name}<i class="fas fa-times remove-assigned-worker" data-worker-id="${w.id}"></i></span>`));
        $hidden.val(assignedWorkersInForm.map(w => w.id).join(','));
    }
    function updateAssignedAssetsDisplay() {
        const $display = $('#assigned_assets_display_form'), $hiddenInput = $('#assigned_asset_ids_hidden_form');
        $display.empty();
        if (assignedAssetsInForm.length > 0) {
            assignedAssetsInForm.forEach(asset => $display.append(`<span class="badge badge-info p-2 m-1">${asset.serial ? `${asset.name} (${asset.serial})` : asset.name}</span>`));
        } else {
            $display.html('<span class="text-muted small p-2">Aucun matériel assigné.</span>');
        }
        $hiddenInput.val(assignedAssetsInForm.map(a => a.id).join(','));
    }
    function populateAssetAssignmentModal() {
        const $list = $('#asset_assignment_list'); $list.empty();
        const assignedIds = new Set(assignedAssetsInForm.map(a => String(a.id)));
        const missionDates = getFormDates();
        if (!missionDates.length) {
            $list.html('<div class="alert alert-warning">Veuillez sélectionner une date valide.</div>'); return;
        }
        const missionId = $('#mission_id_form').val();
        const currentMission = missionId ? state.missions.find(m => m.mission_id == missionId) : null;
        const bookedAssetIds = new Set();
        state.bookings.forEach(b => {
            if (missionDates.includes(b.booking_date)) {
                if (currentMission && b.mission === currentMission.mission_text) return;
                bookedAssetIds.add(String(b.asset_id));
            }
        });
        const searchTerm = $('#asset_assignment_search').val().toLowerCase();
        state.inventory.filter(asset => (asset.asset_name.toLowerCase() + (asset.serial_or_plate || '')).includes(searchTerm)).forEach(asset => {
            const isBooked = bookedAssetIds.has(String(asset.asset_id));
            const isChecked = assignedIds.has(String(asset.asset_id));
            const serialText = asset.serial_or_plate || '';
            $list.append(`<label class="list-group-item list-group-item-action d-flex justify-content-between align-items-center ${isBooked && !isChecked ? 'disabled' : ''}">
                <span><input type="checkbox" class="mr-3" value="${asset.asset_id}" data-asset-name="${asset.asset_name}" data-asset-serial="${serialText}" ${isChecked ? 'checked' : ''} ${isBooked && !isChecked ? 'disabled' : ''}>
                ${asset.asset_name} <small class="text-muted ml-2">${serialText}</small></span>
                ${isBooked ? `<span class="badge badge-danger">Réservé</span>` : ''}</label>`);
        });
    }
    function updateColorSwatches(color) {
        $('input[name="color"]').val(color);
        $('.color-swatch.selected').removeClass('selected');
        $(`.color-swatch[data-color="${color}"]`).addClass('selected');
    }

    // --- GLOBAL EVENT HANDLERS ---
    $('#prevWeekBtn').on('click', () => { state.currentWeekStart.setDate(state.currentWeekStart.getDate() - 7); fetchInitialData(); });
    $('#nextWeekBtn').on('click', () => { state.currentWeekStart.setDate(state.currentWeekStart.getDate() + 7); fetchInitialData(); });
    $('#addMultiDayMissionBtn').on('click', () => showMissionFormView({ isMultiDay: true }));
    $('#activateAllBtn').on('click', function() { const endDate = new Date(state.currentWeekStart); endDate.setDate(endDate.getDate() + 6); showConfirmation('Voulez-vous activer toutes les planifications pour la semaine en cours ?', async () => { showLoading(true); try { await apiCall('validate_all_for_week', 'POST', { start_date: getLocalDateString(state.currentWeekStart), end_date: getLocalDateString(endDate) }); await fetchInitialData(false); } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); } }); });

    // --- PLANNING VIEW HANDLERS ---
    $planningContainer.on('click', '.add-daily-mission-btn', function(e) { e.stopPropagation(); showMissionFormView({ date: $(this).data('date') }); });
    $planningContainer.on('click', '.day-header', function() { const date = $(this).data('date'); state.selectedDate = date; $('.day-header.selected').removeClass('selected'); $(this).addClass('selected'); refreshWorkerListForDate(date); });
    $planningContainer.on('click', '.mission-card-body', function() { const mission = state.missions.find(m => m.mission_id == $(this).closest('.mission-card').data('mission-id')); if (mission) showMissionFormView({ mission: mission }); });
    $planningContainer.on('click', '.validate-btn', async function(e){ e.stopPropagation(); const missionId = $(this).closest('.mission-card').data('mission-id'); showLoading(true); try { await apiCall('toggle_mission_validation', 'POST', { mission_id: missionId }); await fetchInitialData(false); } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); } });
    $planningContainer.on('click', '.delete-btn', function(e) { e.stopPropagation(); const missionId = $(this).closest('.mission-card').data('mission-id'); showConfirmation('Voulez-vous vraiment supprimer cette mission ?', async () => { showLoading(true); try { await apiCall('delete_mission_group', 'POST', { mission_id: missionId }); await fetchInitialData(false); } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); } }); });
    $planningContainer.on('click', '.remove-worker-btn', function(e) { e.stopPropagation(); const missionId = $(this).closest('.mission-card').data('mission-id'); const workerId = $(this).data('worker-id'); const workerName = $(this).closest('li').text().trim(); showConfirmation(`Retirer ${workerName} de cette mission ?`, async () => { showLoading(true); try { await apiCall('remove_worker_from_mission', 'POST', { mission_id: missionId, worker_id: workerId }); await fetchInitialData(false); } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); } }); });

    // --- Drag and Drop Handlers ---
    $workerList.on('dragstart', '.worker-item', (e) => {
        if ($(e.currentTarget).hasClass('unavailable')) { e.preventDefault(); return; }
        state.draggedWorker = { id: $(e.currentTarget).data('worker-id'), name: $(e.currentTarget).data('worker-name') };
    });
    $workerList.on('dragend', () => state.draggedWorker = null);

    $planningContainer.on('dragover', '.day-content, .mission-placeholder, .mission-placeholder-bottom, .mission-card', (e) => e.preventDefault());
    $planningContainer.on('drop', '.mission-placeholder, .mission-placeholder-bottom', function(e) {
        e.preventDefault(); e.stopPropagation();
        if (state.draggedWorker) showMissionFormView({ date: $(this).data('date'), worker: state.draggedWorker });
    });
    $planningContainer.on('drop', '.mission-card', async function(e) {
        e.preventDefault(); e.stopPropagation(); if (!state.draggedWorker) return; showLoading(true);
        try {
            const missionId = $(this).data('mission-id');
            const mission = state.missions.find(m => m.mission_id == missionId);
            await apiCall('assign_worker_to_mission', 'POST', { worker_id: state.draggedWorker.id, mission_id: missionId, assignment_date: mission.assignment_date });
            await fetchInitialData(false);
        } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); }
    });

    // --- FORM VIEW HANDLERS ---
    const $dropZone = $('#assigned_workers_drop_zone');
    $dropZone.on('dragover', (e) => { e.preventDefault(); $dropZone.addClass('drag-over'); });
    $dropZone.on('dragleave', (e) => { e.preventDefault(); $dropZone.removeClass('drag-over'); });
    $dropZone.on('drop', (e) => {
        e.preventDefault(); $dropZone.removeClass('drag-over');
        if (state.draggedWorker) {
            if (!assignedWorkersInForm.some(w => String(w.id) === String(state.draggedWorker.id))) {
                assignedWorkersInForm.push(state.draggedWorker);
                renderAssignedWorkersInForm();
            }
        }
    });

    $('#cancelMissionFormBtn').on('click', hideMissionFormView);
    $missionForm.on('submit', async function(e) {
        e.preventDefault();
        hideFormError();
        const formData = Object.fromEntries(new FormData(this).entries());
        
        formData.assigned_user_ids = assignedWorkersInForm.map(w => w.id).join(',');
        formData.assigned_asset_ids = assignedAssetsInForm.map(a => a.id).join(',');

        if ($('#assign-users-group-form').is(':visible') && !formData.assigned_user_ids) {
            showFormError("Veuillez assigner au moins un ouvrier."); return;
        }

        showLoading(true);
        try {
            await apiCall('save_mission', 'POST', formData);
            hideMissionFormView();
            await fetchInitialData(false);
        } catch (error) { 
            showFormError(error.message); 
        } finally { 
            showLoading(false); 
        }
    });

    $('#assigned_workers_pills').on('click', '.remove-assigned-worker', function() {
        const workerId = $(this).data('worker-id');
        assignedWorkersInForm = assignedWorkersInForm.filter(w => String(w.id) != String(workerId));
        renderAssignedWorkersInForm();
    });
    $('#shift_type_buttons_form').on('change', 'input[name="shift_type"]', function() { const dis = $(this).val() === 'repos'; $('#mission_start_time, #mission_end_time').prop('disabled', dis).val(dis ? '' : $('#mission_start_time').val()); });
    $('#mission_color_swatches_form').on('click', '.color-swatch', function(){ updateColorSwatches($(this).data('color')); });
    $('#manageAssetsBtnForm').on('click', function() { populateAssetAssignmentModal(); $('#assetAssignmentModal').modal('show'); });
    $('#asset_assignment_search').on('keyup', populateAssetAssignmentModal);
    $('#confirmAssetAssignmentBtn').on('click', function() {
        assignedAssetsInForm = [];
        $('#asset_assignment_list input[type="checkbox"]:checked').each(function() {
            assignedAssetsInForm.push({ id: $(this).val(), name: $(this).data('asset-name'), serial: $(this).data('asset-serial') });
        });
        updateAssignedAssetsDisplay();
        $('#assetAssignmentModal').modal('hide');
    });
    $('#deleteMissionBtnForm').on('click', function() {
        const missionId = $('#mission_id_form').val(); if (!missionId) return;
        hideMissionFormView();
        showConfirmation('Supprimer cette mission, ses affectations, et ses réservations de matériel ?', async () => {
            showLoading(true);
            try { await apiCall('delete_mission_group', 'POST', { mission_id: missionId }); await fetchInitialData(false); } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); }
        });
    });

    const $confirmationModal = $('#confirmationModal'), $confirmBtn = $('#confirmActionBtn');
    function showConfirmation(body, onConfirm) {
        $confirmationModal.find('#confirmationModalBody').text(body);
        $confirmBtn.off('click').on('click', () => { onConfirm(); $confirmationModal.modal('hide'); });
        $confirmationModal.modal('show');
    }

    // --- INITIAL LOAD ---
    fetchInitialData();
});
</script>

</body>
</html>
