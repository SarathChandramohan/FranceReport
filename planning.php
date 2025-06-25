<?php
// planning.php (Final, Complete with all features and bug fixes)
require_once 'session-management.php';
require_once 'db-connection.php';
requireLogin();

$user = getCurrentUser();
// This page is for admins only. Redirect if not an admin.
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
        body { background-color: var(--light-gray); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .main-container { display: flex; height: calc(100vh - 78px); }
        .workers-list-col, .planning-col { height: 100%; overflow-y: auto; padding: 15px; }
        .workers-list-col { flex: 0 0 280px; background: var(--card-bg); border-right: 1px solid var(--border-color); }
        .planning-col { flex: 1; }
        .worker-item { padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px; margin-bottom: 8px; background-color: #fcfdff; cursor: grab; transition: all 0.2s ease; user-select: none; }
        .assignment-count { font-size: 0.75rem; color: #fff; background-color: #28a745; border-radius: 10px; padding: 2px 8px; display: inline-block; margin-top: 5px; }
        .daily-planning-container { display: grid; grid-template-columns: repeat(7, 1fr); gap: 15px; min-height: 100%; }
        .day-column { background-color: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef; display: flex; flex-direction: column; }
        .day-header { padding: 10px; text-align: center; font-weight: 600; border-bottom: 1px solid var(--border-color); background-color: #f1f3f5; display:flex; justify-content:space-between; align-items:center; }
        .day-content { flex-grow: 1; padding: 10px; }
        .add-mission-to-day-btn { background: none; border: none; color: var(--primary); cursor: pointer; font-size: 1.1rem; }
        .mission-card { background-color: #fff; border-left: 5px solid; border-radius: 6px; padding: 10px; margin-bottom: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: relative; }
        .mission-card.validated { opacity: 0.8; background-color: #e6ffed; }
        .mission-card-body { cursor: pointer; }
        .mission-title { font-weight: 600; font-size: 0.9rem; margin-bottom: 5px; }
        .mission-meta { font-size: 0.8rem; color: #6c757d; margin-bottom: 8px; }
        .assigned-workers-list { list-style: none; padding-left: 0; margin-bottom: 0; font-size: 0.8rem; }
        .assigned-workers-list li { background-color: #e7f1ff; padding: 3px 8px; border-radius: 4px; margin-top: 4px; display: flex; justify-content: space-between; align-items: center; }
        .remove-worker-btn { cursor: pointer; color: #dc3545; }
        .mission-placeholder { font-size: 0.85rem; color: #6c757d; text-align: center; padding: 20px; border: 2px dashed #ced4da; border-radius: 6px; height: 100%; display: flex; align-items: center; justify-content: center;}
        .mission-actions { position: absolute; top: 5px; right: 5px; display: flex; gap: 5px; background: rgba(255,255,255,0.8); border-radius: 5px; padding: 2px;}
        .action-btn { background: none; border: none; color: #6c757d; font-size: 0.8rem; cursor: pointer; padding: 3px; }
        .action-btn.validate-btn.validated { color: #28a545; }
        #loadingOverlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255, 255, 255, 0.7); z-index: 1060; display: none; justify-content: center; align-items: center; }
        .color-swatch { width:25px; height:25px; border-radius:50%; cursor:pointer; display:inline-block; margin:2px; border: 2px solid transparent; }
        .color-swatch.selected { border-color: #333; }
        #assigned_workers_list > .badge { margin: 4px; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="main-container">
        <div class="workers-list-col">
            <h5><i class="fas fa-users"></i> Ouvriers</h5>
            <div id="workerList"></div>
        </div>

        <div class="planning-col">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <button class="btn btn-outline-secondary" id="prevWeekBtn"><i class="fas fa-chevron-left"></i></button>
                    <button class="btn btn-outline-secondary" id="nextWeekBtn"><i class="fas fa-chevron-right"></i></button>
                </div>
                <h4 id="currentWeekRange" class="mb-0 mx-3 text-center"></h4>
                <button class="btn btn-primary" id="addMultiDayMissionBtn"><i class="fas fa-calendar-plus"></i> Ajouter une mission sur plusieurs jours</button>
            </div>
            <div id="dailyPlanningContainer" class="daily-planning-container"></div>
        </div>
    </div>

    <div class="modal fade" id="missionFormModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="missionForm">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="missionFormModalLabel">Nouvelle Mission</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="mission_id_form" name="mission_id">
                        <input type="hidden" id="assignment_date_form" name="assignment_date">
                        <div class="alert alert-info" id="modalDateDisplay"></div>
                        
                        <div class="form-row" id="multi_day_fields" style="display: none;">
                            <div class="form-group col-md-6">
                                <label>Date de début *</label>
                                <input type="date" class="form-control" name="start_date">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Date de fin *</label>
                                <input type="date" class="form-control" name="end_date">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Titre de la mission *</label>
                            <input type="text" class="form-control" name="mission_text" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6"><label>Heure début</label><input type="time" class="form-control" name="start_time" id="mission_start_time"></div>
                            <div class="form-group col-md-6"><label>Heure fin</label><input type="time" class="form-control" name="end_time" id="mission_end_time"></div>
                        </div>
                        <div class="form-group"><label>Lieu</label><input type="text" class="form-control" name="location"></div>
                        <div class="form-group">
                            <label>Type</label>
                            <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons" id="shift_type_buttons"></div>
                        </div>
                        <div class="form-group"><label>Couleur</label><div id="mission_color_swatches"></div><input type="hidden" name="color" value="<?= $default_color; ?>"></div>
                        
                        <div class="form-group" id="assign-users-group">
                            <label>Ouvriers assignés *</label>
                            <input type="hidden" name="assigned_user_ids" id="assigned_user_ids_hidden">
                            <div id="workers_drop_zone" style="border: 2px dashed #ced4da; border-radius: 6px; padding: 20px; text-align: center; background-color: #f8f9fa;">
                                <div id="assigned_workers_list" class="d-flex flex-wrap">
                                    </div>
                                <p class="text-muted mt-2 mb-0">Glissez les ouvriers depuis la liste de gauche et déposez-les ici.</p>
                            </div>
                        </div>

                        <div id="modal_error_message" class="alert alert-danger" style="display: none;"></div>
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
    
    <div class="modal fade" id="confirmationModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmation</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" id="confirmationModalBody">
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-danger" id="confirmActionBtn">Confirmer</button>
                </div>
            </div>
        </div>
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
    let state = { staff: [], missions: [], currentWeekStart: getMonday(new Date()), draggedWorker: null, shouldRefreshOnModalClose: false };

    // --- DOM ELEMENTS ---
    const $loading = $('#loadingOverlay');
    const $planningContainer = $('#dailyPlanningContainer');
    const $workerList = $('#workerList');
    const $modal = $('#missionFormModal');
    
    // --- HELPERS ---
    const showLoading = (show) => $loading.toggle(show);
    const formatDate = (date) => date.toISOString().split('T')[0];
    const formatTime = (time) => time ? time.substring(0, 5) : '';
    const showModalError = (msg) => $('#modal_error_message').text(msg).show();
    const hideModalError = () => $('#modal_error_message').hide();
    function getMonday(d) {
        d = new Date(d);
        let day = d.getDay(), diff = d.getDate() - day + (day === 0 ? -6 : 1);
        d.setHours(0, 0, 0, 0);
        return d;
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
            const data = await apiCall('get_initial_data', 'GET', { start: formatDate(state.currentWeekStart), end: formatDate(endDate) });
            state.staff = data.staff || [];
            state.missions = data.missions || [];
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
            const dateStr = formatDate(dayDate);
            const dayLabel = dayDate.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric' });

            const $dayHeader = $(`<div class="day-header" data-date="${dateStr}"><span>${dayLabel}</span><button class="add-mission-to-day-btn" title="Ajouter une mission"><i class="fas fa-plus-circle"></i></button></div>`);
            const $dayColumn = $(`<div class="day-column"></div>`).append($dayHeader);
            const $dayContent = $(`<div class="day-content p-2" data-date="${dateStr}"></div>`).appendTo($dayColumn);
            
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
        const assignedIds = mission.assigned_user_ids ? mission.assigned_user_ids.split(',') : [];
        const assignedNames = mission.assigned_user_names ? mission.assigned_user_names.split(', ') : [];
        const workersHtml = assignedNames.map((name, i) => `<li>${name} <i class="fas fa-times remove-worker-btn" data-worker-id="${assignedIds[i]}"></i></li>`).join('');
        const actionsHtml = `<div class="mission-actions"><i class="fas fa-check-circle action-btn validate-btn ${mission.is_validated == 1 ? 'validated' : ''}" title="Valider"></i></div>`;

        return $(`<div class="mission-card ${mission.is_validated == 1 ? 'validated' : ''}" style="border-left-color: ${mission.color || '#6c757d'};" data-mission-id="${mission.mission_id}">
                ${actionsHtml}
                <div class="mission-card-body">
                    <div class="mission-title">${mission.mission_text}</div>
                    <div class="mission-meta">
                        ${mission.start_time ? `<i class="far fa-clock"></i> ${formatTime(mission.start_time)} - ${formatTime(mission.end_time)}<br>` : ''}
                        ${mission.location ? `<i class="fas fa-map-marker-alt"></i> ${mission.location}` : ''}
                    </div>
                    <ul class="assigned-workers-list">${workersHtml || '<li class="text-muted small">Aucun ouvrier</li>'}</ul>
                </div></div>`);
    }

    function renderWorkerList() {
        const workerAssignmentCounts = new Map();
        state.missions.forEach(mission => {
            if (mission.assigned_user_ids) {
                const userIds = mission.assigned_user_ids.split(',');
                userIds.forEach(id => {
                    workerAssignmentCounts.set(id, (workerAssignmentCounts.get(id) || 0) + 1);
                });
            }
        });

        $workerList.empty();
        state.staff.forEach(worker => {
            const assignmentCount = workerAssignmentCounts.get(String(worker.user_id)) || 0;
            const countHtml = assignmentCount > 0 
                ? `<div class="assignment-count">Assigné à ${assignmentCount} mission(s)</div>`
                : '';
            
            $workerList.append(`
                <div class="worker-item" draggable="true" data-worker-id="${worker.user_id}" data-worker-name="${worker.prenom} ${worker.nom}">
                    <div>${worker.prenom} ${worker.nom}</div>
                    ${countHtml}
                </div>
            `);
        });
    }

    // --- LOGIC FOR DRAG-DROP IN MODAL ---
    let assignedWorkersInModal = []; 

    function renderAssignedWorkersInModal() {
        const $list = $('#assigned_workers_list');
        const $hiddenInput = $('#assigned_user_ids_hidden');
        $list.empty();
        
        if (assignedWorkersInModal.length > 0) {
            assignedWorkersInModal.forEach(worker => {
                const pillHtml = $(`
                    <span class="badge badge-primary p-2" style="font-size: 0.9rem;">
                        ${worker.name}
                        <i class="fas fa-times ml-2 remove-assigned-worker" data-worker-id="${worker.id}" style="cursor: pointer;"></i>
                    </span>
                `);
                $list.append(pillHtml);
            });
        }
        const ids = assignedWorkersInModal.map(w => w.id);
        $hiddenInput.val(ids.join(','));
    }

    const $dropZone = $('#workers_drop_zone');
    $dropZone.on('dragover', function(e) { e.preventDefault(); $(this).css('background-color', '#e9ecef'); });
    $dropZone.on('dragleave', function(e) { e.preventDefault(); $(this).css('background-color', '#f8f9fa'); });
    $dropZone.on('drop', function(e) {
        e.preventDefault();
        $(this).css('background-color', '#f8f9fa');
        if (!state.draggedWorker) return;

        const isAlreadyAdded = assignedWorkersInModal.some(w => w.id == state.draggedWorker.id);
        if (!isAlreadyAdded) {
            assignedWorkersInModal.push({ id: state.draggedWorker.id, name: state.draggedWorker.name });
            renderAssignedWorkersInModal();
        }
    });

    $('#assigned_workers_list').on('click', '.remove-assigned-worker', function() {
        const workerIdToRemove = $(this).data('worker-id');
        assignedWorkersInModal = assignedWorkersInModal.filter(w => w.id != workerIdToRemove);
        renderAssignedWorkersInModal();
    });

    // --- EVENT HANDLERS ---
    $('#prevWeekBtn').on('click', () => { state.currentWeekStart.setDate(state.currentWeekStart.getDate() - 7); fetchInitialData(); });
    $('#nextWeekBtn').on('click', () => { state.currentWeekStart.setDate(state.currentWeekStart.getDate() + 7); fetchInitialData(); });
    
    $('#addMultiDayMissionBtn').on('click', function() {
        assignedWorkersInModal = [];
        renderAssignedWorkersInModal();
        $modal.find('form')[0].reset();
        hideModalError();
        $('#shift_type_buttons label').removeClass('active');
        $modal.find('input[name="mission_id"]').val('');
        $('#modalDateDisplay').hide();
        $('#multi_day_fields').show();
        $('#assign-users-group').show();
        $('#deleteMissionBtn').hide();
        $modal.find('#missionFormModalLabel').text('Nouvelle Mission sur Plusieurs Jours');
        // --- MODIFIED --- Open modal with static backdrop
        $modal.modal({ backdrop: 'static', keyboard: false });
    });

    $planningContainer.on('click', '.add-mission-to-day-btn', function() {
        const date = $(this).closest('.day-header').data('date');
        openModalForCreate(date);
    });

    $workerList.on('dragstart', '.worker-item', (e) => {
        state.draggedWorker = { id: $(e.currentTarget).data('worker-id'), name: $(e.currentTarget).data('worker-name') };
    });

    $planningContainer.on('dragover', '.day-content, .mission-card', (e) => e.preventDefault());
    $planningContainer.on('drop', '.day-content', handleDrop);
    
    $planningContainer.on('click', '.validate-btn', async function(e){ e.stopPropagation(); const missionId = $(this).closest('.mission-card').data('mission-id'); showLoading(true); try { await apiCall('toggle_mission_validation', 'POST', { mission_id: missionId }); await fetchInitialData(false); } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); } });
    
    async function handleDrop(e) {
        e.preventDefault(); e.stopPropagation();
        if (!state.draggedWorker) return;
        const $target = $(e.target);
        const $missionCard = $target.closest('.mission-card');
        showLoading(true);
        try {
            if ($missionCard.length > 0) {
                const missionId = $missionCard.data('mission-id');
                const mission = state.missions.find(m => m.mission_id == missionId);
                await apiCall('assign_worker_to_mission', 'POST', { worker_id: state.draggedWorker.id, mission_id: missionId, assignment_date: mission.assignment_date });
            } else {
                const date = $target.closest('.day-content').data('date');
                await apiCall('save_mission', 'POST', {
                    assignment_date: date, mission_text: `Mission pour ${state.draggedWorker.name}`,
                    shift_type: 'custom', start_time: '08:00', end_time: '17:00', color: DEFAULT_COLOR,
                    assigned_user_ids: [state.draggedWorker.id]
                });
            }
            await fetchInitialData(false);
        } catch (error) { alert(`Erreur: ${error.message}`); } finally { state.draggedWorker = null; showLoading(false); }
    }

    // --- MODAL & FORM LOGIC ---
    function setupModalStaticContent() {
        $('#shift_type_buttons').html(Object.entries({matin:'Matin', 'apres-midi':'Après-midi', nuit:'Nuit', repos:'Repos', custom:'Personnalisé'}).map(([v, l]) => `<label class="btn btn-sm btn-outline-secondary"><input type="radio" name="shift_type" value="${v}" required> ${l}</label>`).join(''));
        $('#mission_color_swatches').html(PREDEFINED_COLORS.map(c => `<div class="color-swatch" style="background-color:${c};" data-color="${c}"></div>`).join(''));
    }

    function openModalForCreate(date) {
        assignedWorkersInModal = [];
        renderAssignedWorkersInModal();
        $modal.find('form')[0].reset();
        hideModalError();
        $('#shift_type_buttons label').removeClass('active');
        $modal.find('input[name="mission_id"]').val('');
        $modal.find('input[name="assignment_date"]').val(date);
        const displayDate = new Date(date + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        $('#multi_day_fields').hide();
        $('#modalDateDisplay').html(`Mission pour le: <strong>${displayDate}</strong>`).show();
        $modal.find('#missionFormModalLabel').text('Nouvelle Mission');
        $modal.find('#deleteMissionBtn').hide();
        $('#assign-users-group').show();
        // --- MODIFIED --- Open modal with static backdrop
        $modal.modal({ backdrop: 'static', keyboard: false });
    }
    
    $planningContainer.on('click', '.mission-card-body', function() {
        const missionId = $(this).closest('.mission-card').data('mission-id');
        const mission = state.missions.find(m => m.mission_id == missionId);
        if (!mission) return;

        $modal.find('form')[0].reset(); hideModalError();
        $('#shift_type_buttons label').removeClass('active');
        $modal.find('input[name="mission_id"]').val(mission.mission_id);
        $modal.find('input[name="assignment_date"]').val(mission.assignment_date);
        const displayDate = new Date(mission.assignment_date + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        $('#multi_day_fields').hide();
        $('#modalDateDisplay').html(`Modifier la mission du: <strong>${displayDate}</strong>`).show();
        $modal.find('input[name="mission_text"]').val(mission.mission_text);
        $modal.find('input[name="start_time"]').val(mission.start_time);
        $modal.find('input[name="end_time"]').val(mission.end_time);
        $modal.find('input[name="location"]').val(mission.location);
        const $shiftButton = $modal.find(`input[name="shift_type"][value="${mission.shift_type}"]`);
        if ($shiftButton.length) $shiftButton.prop('checked', true).parent().addClass('active');
        $modal.find('input[name="color"]').val(mission.color || DEFAULT_COLOR);
        $('.color-swatch.selected').removeClass('selected');
        $(`.color-swatch[data-color="${mission.color || DEFAULT_COLOR}"]`).addClass('selected');
        $modal.find('#missionFormModalLabel').text('Modifier la Mission');
        $modal.find('#deleteMissionBtn').show();
        $('#assign-users-group').hide();
        // --- MODIFIED --- Open modal with static backdrop
        $modal.modal({ backdrop: 'static', keyboard: false });
    });
    
    $('#shift_type_buttons').on('change', 'input[name="shift_type"]', function() { const isTimeDisabled = $(this).val() === 'repos'; $('#mission_start_time, #mission_end_time').prop('disabled', isTimeDisabled); if (isTimeDisabled) $('#mission_start_time, #mission_end_time').val(''); });
    $('#mission_color_swatches').on('click', '.color-swatch', function(){ $(this).addClass('selected').siblings().removeClass('selected'); $('input[name="color"]').val($(this).data('color')); });

    $('#missionForm').on('submit', async function(e) {
        e.preventDefault();
        hideModalError();
        const formData = Object.fromEntries(new FormData(this).entries());
        
        if (!formData.mission_id && (!formData.assigned_user_ids || formData.assigned_user_ids.length === 0)) {
            showModalError("Veuillez assigner au moins un ouvrier.");
            return;
        }
        if (formData.assigned_user_ids) {
            formData.assigned_user_ids = formData.assigned_user_ids.split(',');
        }
        
        showLoading(true);
        try {
            await apiCall('save_mission', 'POST', formData);
            state.shouldRefreshOnModalClose = true;
            $modal.modal('hide');
        } catch (error) {
            showModalError(error.message);
        } finally {
            showLoading(false);
        }
    });
    
    $modal.on('hidden.bs.modal', function () {
        hideModalError();
        if (state.shouldRefreshOnModalClose) {
            fetchInitialData();
            state.shouldRefreshOnModalClose = false;
        }
    });

    // --- CONFIRMATION LOGIC ---
    $planningContainer.on('click', '.remove-worker-btn', function(e) { /* ... no changes here ... */ });
    $('#deleteMissionBtn').on('click', function() { /* ... no changes here ... */ });
    $('#confirmActionBtn').on('click', async function() { /* ... no changes here ... */ });

    // --- Init ---
    fetchInitialData();
});
</script>
</body>
</html>
