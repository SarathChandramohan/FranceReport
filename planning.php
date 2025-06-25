<?php
// planning.php (Final, with Full-screen Mission Editor)
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
        
        /* Main containers for view switching */
        .view-container { height: calc(100vh - 78px); /* Full height minus navbar */ }
        .main-container { display: flex; height: 100%; }

        .workers-list-col, .planning-col, .form-col { height: 100%; overflow-y: auto; padding: 20px; }
        .workers-list-col { flex: 0 0 280px; background: var(--card-bg); border-right: 1px solid var(--border-color); }
        .planning-col, .form-col { flex: 1; }
        
        /* Worker items */
        .worker-item { padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px; margin-bottom: 8px; background-color: #fcfdff; cursor: grab; transition: all 0.2s ease; user-select: none; }
        .assignment-count { font-size: 0.75rem; color: #fff; background-color: #28a745; border-radius: 10px; padding: 2px 8px; display: inline-block; margin-top: 5px; }

        /* Planning Grid */
        .daily-planning-container { display: grid; grid-template-columns: repeat(7, 1fr); gap: 15px; min-height: 100%; }
        .day-column { background-color: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef; display: flex; flex-direction: column; }
        .day-header { padding: 10px; text-align: center; font-weight: 600; border-bottom: 1px solid var(--border-color); background-color: #f1f3f5; display:flex; justify-content:space-between; align-items:center; }
        .day-content { flex-grow: 1; padding: 10px; }
        .add-mission-to-day-btn { background: none; border: none; color: var(--primary); cursor: pointer; font-size: 1.1rem; }
        
        /* Mission Cards */
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

        /* Form Specific */
        .form-col { background-color: var(--card-bg); }
        .color-swatch { width:25px; height:25px; border-radius:50%; cursor:pointer; display:inline-block; margin:2px; border: 2px solid transparent; }
        .color-swatch.selected { border-color: #333; }
        #assigned_workers_list > .badge { margin: 4px; font-size: 0.9rem; }
        #workers_drop_zone { border: 2px dashed #ced4da; border-radius: 6px; padding: 20px; text-align: center; background-color: #f8f9fa; min-height: 100px; }
        #workers_drop_zone.dragover { background-color: #e9ecef; }
        
        /* Utility */
        #loadingOverlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255, 255, 255, 0.7); z-index: 1060; display: none; justify-content: center; align-items: center; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <!-- Main Planning View Container (visible by default) -->
    <div id="planningViewContainer" class="view-container">
        <div class="main-container">
            <div class="workers-list-col">
                <h5><i class="fas fa-users"></i> Ouvriers</h5>
                <div id="mainWorkerList"></div>
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
    </div>

    <!-- Full-screen Mission Creator (hidden by default) -->
    <div id="missionCreatorContainer" class="view-container" style="display: none;">
        <div class="main-container">
            <!-- Left column: Draggable worker list -->
            <div class="workers-list-col">
                <h5><i class="fas fa-user-plus"></i> Assigner un ouvrier</h5>
                <p class="text-muted small">Glissez un ouvrier de cette liste et déposez-le dans la zone d'assignation à droite.</p>
                <div id="creatorWorkerList"></div>
            </div>
            
            <!-- Right column: The form -->
            <div class="form-col">
                <form id="missionForm" novalidate>
                    <h4 id="missionFormTitle">Nouvelle Mission</h4>
                    <hr>
                    
                    <input type="hidden" id="mission_id_form" name="mission_id">
                    <input type="hidden" id="assignment_date_form" name="assignment_date">
                    
                    <div class="alert alert-info" id="formDateDisplay" style="display:none;"></div>
                    
                    <div class="form-row" id="multi_day_fields" style="display: none;">
                        <div class="form-group col-md-6">
                            <label for="start_date_form">Date de début *</label>
                            <input type="date" class="form-control" name="start_date" id="start_date_form">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="end_date_form">Date de fin *</label>
                            <input type="date" class="form-control" name="end_date" id="end_date_form">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="mission_text_form">Titre de la mission *</label>
                        <input type="text" class="form-control" name="mission_text" id="mission_text_form" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="mission_start_time">Heure début</label>
                            <input type="time" class="form-control" name="start_time" id="mission_start_time">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="mission_end_time">Heure fin</label>
                            <input type="time" class="form-control" name="end_time" id="mission_end_time">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="location_form">Lieu</label>
                        <input type="text" class="form-control" name="location" id="location_form">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons" id="shift_type_buttons"></div>
                    </div>
                    <div class="form-group">
                        <label>Couleur</label>
                        <div id="mission_color_swatches"></div>
                        <input type="hidden" name="color" value="<?= htmlspecialchars($default_color); ?>">
                    </div>
                    
                    <div class="form-group" id="assign-users-group">
                        <label>Ouvriers assignés *</label>
                        <input type="hidden" name="assigned_user_ids" id="assigned_user_ids_hidden">
                        <div id="workers_drop_zone">
                            <div id="assigned_workers_list" class="d-flex flex-wrap align-items-start"></div>
                            <p id="drop_zone_placeholder" class="text-muted mt-2 mb-0">Déposez les ouvriers ici.</p>
                        </div>
                    </div>

                    <div id="form_error_message" class="alert alert-danger" style="display: none;"></div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                        <button type="button" class="btn btn-secondary" id="cancelMissionFormBtn">Annuler</button>
                        <button type="button" class="btn btn-danger mr-auto" id="deleteMissionBtn" style="display: none;"><i class="fas fa-trash"></i> Supprimer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Confirmation Modal (still useful for deletions) -->
    <div class="modal fade" id="confirmationModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Confirmation</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body" id="confirmationModalBody"></div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button><button type="button" class="btn btn-danger" id="confirmActionBtn">Confirmer</button></div>
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
    let state = { staff: [], missions: [], currentWeekStart: getMonday(new Date()), draggedWorker: null, confirmationCallback: null };
    let assignedWorkersInForm = []; 

    // --- DOM ELEMENTS ---
    const $loading = $('#loadingOverlay');
    const $planningView = $('#planningViewContainer');
    const $creatorView = $('#missionCreatorContainer');
    const $planningContainer = $('#dailyPlanningContainer');
    const $mainWorkerList = $('#mainWorkerList');
    const $creatorWorkerList = $('#creatorWorkerList');
    const $missionForm = $('#missionForm');
    const $dropZone = $('#workers_drop_zone');
    
    // --- HELPERS ---
    const showLoading = (show) => $loading.toggle(show);
    const formatDate = (date) => date.toISOString().split('T')[0];
    const formatTime = (time) => time ? time.substring(0, 5) : '';
    const showFormError = (msg) => $('#form_error_message').text(msg).show();
    const hideFormError = () => $('#form_error_message').hide();
    function getMonday(d) {
        d = new Date(d);
        let day = d.getDay(), diff = d.getDate() - day + (day === 0 ? -6 : 1);
        d.setHours(0, 0, 0, 0);
        return new Date(diff);
    }

    // --- API CALLS ---
    async function apiCall(action, method = 'POST', data = {}) {
        showLoading(true);
        try {
            const options = { method, headers: { 'Content-Type': 'application/json' } };
            let url = `${HANDLER_URL}?action=${action}`;
            if (method === 'GET') {
                url += '&' + new URLSearchParams(data).toString();
            } else {
                options.body = JSON.stringify(data);
            }
            const response = await fetch(url, options);
            const result = await response.json();
            if (!response.ok || result.status !== 'success') {
                throw new Error(result.message || `Erreur réseau: ${response.statusText}`);
            }
            return result.data;
        } catch (error) {
            alert(`Erreur: ${error.message}`); // Simple feedback for now
            throw error; // Re-throw to be caught by caller
        } finally {
            showLoading(false);
        }
    }
    
    // --- DATA FETCHING & INITIAL LOAD ---
    async function fetchAndRenderAll() {
        showLoading(true);
        try {
            const endDate = new Date(state.currentWeekStart);
            endDate.setDate(endDate.getDate() + 6);
            const data = await apiCall('get_initial_data', 'GET', { start: formatDate(state.currentWeekStart), end: formatDate(endDate) });
            state.staff = data.staff || [];
            state.missions = data.missions || [];
            renderAllViews();
        } catch (error) { 
            console.error("Failed to fetch initial data:", error);
        } finally { 
            showLoading(false);
        }
    }

    // --- UI RENDERING ---
    function renderAllViews() {
        renderWeekHeader();
        renderPlanningGrid();
        renderMainWorkerList();
        // The creator worker list is rendered when the view is opened
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
        const workersHtml = assignedIds.map((id, i) => `<li>${assignedNames[i] || 'N/A'} <i class="fas fa-times remove-worker-btn" data-worker-id="${id}" title="Retirer cet ouvrier"></i></li>`).join('');
        const actionsHtml = `<div class="mission-actions"><i class="fas fa-check-circle action-btn validate-btn ${mission.is_validated == 1 ? 'validated' : ''}" title="Valider/Dévalider"></i></div>`;

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

    function renderMainWorkerList() {
        renderWorkerList($mainWorkerList);
    }
    
    function renderCreatorWorkerList() {
        renderWorkerList($creatorWorkerList);
    }

    function renderWorkerList($container) {
        const workerAssignmentCounts = new Map();
        state.missions.forEach(mission => {
            if (mission.assigned_user_ids) {
                mission.assigned_user_ids.split(',').forEach(id => {
                    workerAssignmentCounts.set(id, (workerAssignmentCounts.get(id) || 0) + 1);
                });
            }
        });

        $container.empty();
        state.staff.forEach(worker => {
            const assignmentCount = workerAssignmentCounts.get(String(worker.user_id)) || 0;
            const countHtml = assignmentCount > 0 
                ? `<div class="assignment-count">Assigné à ${assignmentCount} mission(s)</div>`
                : '';
            
            $container.append(`
                <div class="worker-item" draggable="true" data-worker-id="${worker.user_id}" data-worker-name="${worker.prenom} ${worker.nom}">
                    <div>${worker.prenom} ${worker.nom}</div>
                    ${countHtml}
                </div>
            `);
        });
    }

    // --- VIEW SWITCHING LOGIC ---
    function showPlanningView() {
        $creatorView.hide();
        $planningView.show();
    }

    function showCreatorView(mode, options = {}) {
        // Reset and configure form
        resetAndConfigureForm(mode, options);
        // Render the draggable list of workers for the creator
        renderCreatorWorkerList();
        // Switch views
        $planningView.hide();
        $creatorView.show();
    }
    
    // --- DRAG-DROP LOGIC ---
    // For main grid
    $mainWorkerList.on('dragstart', '.worker-item', (e) => {
        state.draggedWorker = { id: $(e.currentTarget).data('worker-id'), name: $(e.currentTarget).data('worker-name') };
    });
    
    // For creator form
    $creatorWorkerList.on('dragstart', '.worker-item', (e) => {
        state.draggedWorker = { id: $(e.currentTarget).data('worker-id'), name: $(e.currentTarget).data('worker-name') };
    });

    $planningContainer.on('dragover', '.day-content, .mission-card', (e) => e.preventDefault());
    $planningContainer.on('drop', '.day-content', handleDropOnGrid);

    $dropZone.on('dragover', function(e) { e.preventDefault(); $(this).addClass('dragover'); });
    $dropZone.on('dragleave', function(e) { e.preventDefault(); $(this).removeClass('dragover'); });
    $dropZone.on('drop', handleDropOnForm);
    
    async function handleDropOnGrid(e) {
        e.preventDefault(); e.stopPropagation();
        if (!state.draggedWorker) return;
        
        const $target = $(e.target);
        const $missionCard = $target.closest('.mission-card');
        
        try {
            if ($missionCard.length > 0) {
                const missionId = $missionCard.data('mission-id');
                await apiCall('assign_worker_to_mission', 'POST', { worker_id: state.draggedWorker.id, mission_id: missionId });
            } else {
                const date = $target.closest('.day-content').data('date');
                await apiCall('save_mission', 'POST', {
                    assignment_date: date, mission_text: `Mission pour ${state.draggedWorker.name}`,
                    shift_type: 'custom', start_time: '08:00', end_time: '17:00', color: DEFAULT_COLOR,
                    assigned_user_ids: [state.draggedWorker.id]
                });
            }
            await fetchAndRenderAll();
        } catch (error) { console.error("Drop failed:", error); } 
        finally { state.draggedWorker = null; }
    }
    
    function handleDropOnForm(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        if (!state.draggedWorker) return;

        const isAlreadyAdded = assignedWorkersInForm.some(w => w.id == state.draggedWorker.id);
        if (!isAlreadyAdded) {
            assignedWorkersInForm.push({ id: state.draggedWorker.id, name: state.draggedWorker.name });
            renderAssignedWorkersInForm();
        }
        state.draggedWorker = null;
    }
    
    // --- FORM LOGIC ---
    function resetAndConfigureForm(mode, options) {
        $missionForm[0].reset();
        hideFormError();
        assignedWorkersInForm = [];
        renderAssignedWorkersInForm();
        
        // Reset UI elements
        $('#shift_type_buttons label').removeClass('active');
        $('.color-swatch.selected').removeClass('selected');
        $(`.color-swatch[data-color="${DEFAULT_COLOR}"]`).addClass('selected');
        $('input[name="color"]').val(DEFAULT_COLOR);
        
        $('#deleteMissionBtn').hide();
        $('#assign-users-group').show();

        if (mode === 'createMulti') {
            $('#missionFormTitle').text('Nouvelle Mission sur Plusieurs Jours');
            $('#multi_day_fields').show();
            $('#formDateDisplay').hide();
        } else if (mode === 'createSingle') {
            const { date } = options;
            $('#missionFormTitle').text('Nouvelle Mission');
            $('#multi_day_fields').hide();
            const displayDate = new Date(date + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            $('#formDateDisplay').html(`Mission pour le: <strong>${displayDate}</strong>`).show();
            $('#assignment_date_form').val(date);
        } else if (mode === 'edit') {
            const { mission } = options;
            $('#missionFormTitle').text('Modifier la Mission');
            $('#multi_day_fields').hide();
            const displayDate = new Date(mission.assignment_date + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            $('#formDateDisplay').html(`Modification de la mission du: <strong>${displayDate}</strong>`).show();

            $('#mission_id_form').val(mission.mission_id);
            $('#mission_text_form').val(mission.mission_text);
            $('#mission_start_time').val(mission.start_time);
            $('#mission_end_time').val(mission.end_time);
            $('#location_form').val(mission.location);
            
            const $shiftButton = $(`input[name="shift_type"][value="${mission.shift_type}"]`);
            if ($shiftButton.length) $shiftButton.prop('checked', true).parent().addClass('active');

            $('input[name="color"]').val(mission.color || DEFAULT_COLOR);
            $(`.color-swatch[data-color="${mission.color || DEFAULT_COLOR}"]`).addClass('selected');
            
            $('#deleteMissionBtn').show().data('mission-id', mission.mission_id);
            $('#assign-users-group').hide(); // Cannot reassign users from edit screen as per original logic
        }
    }

    function renderAssignedWorkersInForm() {
        const $list = $('#assigned_workers_list');
        const $hiddenInput = $('#assigned_user_ids_hidden');
        $list.empty();
        
        $('#drop_zone_placeholder').toggle(assignedWorkersInForm.length === 0);
        
        if (assignedWorkersInForm.length > 0) {
            assignedWorkersInForm.forEach(worker => {
                $list.append(`
                    <span class="badge badge-primary p-2">
                        ${worker.name}
                        <i class="fas fa-times ml-2 remove-assigned-worker" data-worker-id="${worker.id}" style="cursor: pointer;"></i>
                    </span>`);
            });
        }
        $hiddenInput.val(assignedWorkersInForm.map(w => w.id).join(','));
    }

    $missionForm.on('submit', async function(e) {
        e.preventDefault();
        hideFormError();
        const formData = Object.fromEntries(new FormData(this).entries());
        
        // Validation for new missions
        if (!formData.mission_id && (!formData.assigned_user_ids || formData.assigned_user_ids.length === 0)) {
            showFormError("Veuillez assigner au moins un ouvrier pour une nouvelle mission.");
            return;
        }

        if (formData.assigned_user_ids) {
            formData.assigned_user_ids = formData.assigned_user_ids.split(',');
        }
        
        try {
            await apiCall('save_mission', 'POST', formData);
            await fetchAndRenderAll();
            showPlanningView();
        } catch (error) {
            showFormError(error.message);
        }
    });

    $('#assigned_workers_list').on('click', '.remove-assigned-worker', function() {
        const workerIdToRemove = $(this).data('worker-id');
        assignedWorkersInForm = assignedWorkersInForm.filter(w => w.id != workerIdToRemove);
        renderAssignedWorkersInForm();
    });

    // --- EVENT HANDLERS ---
    $('#prevWeekBtn').on('click', () => { state.currentWeekStart.setDate(state.currentWeekStart.getDate() - 7); fetchAndRenderAll(); });
    $('#nextWeekBtn').on('click', () => { state.currentWeekStart.setDate(state.currentWeekStart.getDate() + 7); fetchAndRenderAll(); });
    
    $('#addMultiDayMissionBtn').on('click', () => showCreatorView('createMulti'));
    $planningContainer.on('click', '.add-mission-to-day-btn', function() {
        const date = $(this).closest('.day-header').data('date');
        showCreatorView('createSingle', { date });
    });
    $planningContainer.on('click', '.mission-card-body', function() {
        const missionId = $(this).closest('.mission-card').data('mission-id');
        const mission = state.missions.find(m => m.mission_id == missionId);
        if (mission) showCreatorView('edit', { mission });
    });
    
    $('#cancelMissionFormBtn').on('click', showPlanningView);

    $planningContainer.on('click', '.validate-btn', async function(e){ 
        e.stopPropagation(); 
        const missionId = $(this).closest('.mission-card').data('mission-id');
        try {
            await apiCall('toggle_mission_validation', 'POST', { mission_id: missionId });
            await fetchAndRenderAll();
        } catch(e) { console.error("Validation toggle failed", e); }
    });

    // Confirmation Modal Logic
    function openConfirmationModal(body, callback) {
        state.confirmationCallback = callback;
        $('#confirmationModalBody').text(body);
        $('#confirmationModal').modal('show');
    }

    $planningContainer.on('click', '.remove-worker-btn', function(e) { 
        e.stopPropagation();
        const missionId = $(this).closest('.mission-card').data('mission-id');
        const workerId = $(this).data('worker-id');
        openConfirmationModal("Êtes-vous sûr de vouloir retirer cet ouvrier de la mission ?", async () => {
            try {
                await apiCall('remove_worker_from_mission', 'POST', { worker_id: workerId, mission_id: missionId });
                await fetchAndRenderAll();
            } catch(e) { console.error("Remove worker failed", e); }
        });
    });

    $('#deleteMissionBtn').on('click', function() {
        const missionId = $('#mission_id_form').val();
        if(!missionId) return;
        openConfirmationModal("Êtes-vous sûr de vouloir supprimer cette mission et toutes ses assignations ?", async () => {
             try {
                await apiCall('delete_mission_group', 'POST', { mission_id: missionId });
                await fetchAndRenderAll();
                showPlanningView();
            } catch(e) { console.error("Delete failed", e); }
        });
    });

    $('#confirmActionBtn').on('click', function() {
        if (typeof state.confirmationCallback === 'function') {
            state.confirmationCallback();
        }
        $('#confirmationModal').modal('hide');
        state.confirmationCallback = null;
    });
    
    // --- STATIC CONTENT SETUP ---
    function setupStaticContent() {
        $('#shift_type_buttons').html(Object.entries({matin:'Matin', 'apres-midi':'Après-midi', nuit:'Nuit', repos:'Repos', custom:'Personnalisé'}).map(([v, l]) => `<label class="btn btn-sm btn-outline-secondary"><input type="radio" name="shift_type" value="${v}" required> ${l}</label>`).join(''));
        $('#mission_color_swatches').html(PREDEFINED_COLORS.map(c => `<div class="color-swatch" style="background-color:${c};" data-color="${c}"></div>`).join(''));
        
        $('#shift_type_buttons').on('change', 'input[name="shift_type"]', function() { 
            const isTimeDisabled = $(this).val() === 'repos'; 
            $('#mission_start_time, #mission_end_time').prop('disabled', isTimeDisabled); 
            if (isTimeDisabled) $('#mission_start_time, #mission_end_time').val(''); 
        });
        $('#mission_color_swatches').on('click', '.color-swatch', function(){ 
            $(this).addClass('selected').siblings().removeClass('selected'); 
            $('input[name="color"]').val($(this).data('color')); 
        });
    }

    // --- INITIALIZATION ---
    setupStaticContent();
    fetchAndRenderAll();
});
</script>
</body>
</html>
