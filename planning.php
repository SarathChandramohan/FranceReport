<?php
// planning.php (Corrected and Improved)
require_once 'session-management.php';
require_once 'db-connection.php';
requireLogin();

$user = getCurrentUser();
// Admins see the full interactive view, others might see a read-only version or be redirected.
if ($user['role'] !== 'admin') {
    // For this example, we'll allow non-admins to view but not interact.
    // You could also redirect them: header('Location: dashboard.php'); exit();
}

$is_admin_view = ($user['role'] === 'admin');
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
        .main-container { display: flex; height: calc(100vh - 78px); /* Adjust based on navbar height */ }
        .workers-list-col, .planning-col { height: 100%; overflow-y: auto; padding: 15px; }
        .workers-list-col { flex: 0 0 280px; background: var(--card-bg); border-right: 1px solid var(--border-color); }
        .planning-col { flex: 1; }
        .card { background-color: var(--card-bg); border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: none; margin-bottom: 20px; }
        .worker-item { padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px; margin-bottom: 8px; background-color: #fcfdff; cursor: grab; transition: all 0.2s ease; user-select: none; }
        .worker-item.assigned { background-color: #e9ecef; border-color: #ced4da; color: #6c757d; cursor: not-allowed; opacity: 0.7; }
        .worker-item.assigned .assigned-info { font-size: 0.8rem; color: #dc3545; }
        .daily-planning-container { display: grid; grid-template-columns: repeat(7, 1fr); gap: 15px; min-height: 100%; }
        .day-column { background-color: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef; display: flex; flex-direction: column; }
        .day-header { padding: 10px; text-align: center; font-weight: 600; border-bottom: 1px solid var(--border-color); background-color: #f1f3f5; border-radius: 8px 8px 0 0; }
        .day-content { flex-grow: 1; padding: 10px; }
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
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="main-container">
        <?php if ($is_admin_view): ?>
        <div class="workers-list-col">
            <h5><i class="fas fa-users"></i> Ouvriers</h5>
            <div id="workerList"></div>
        </div>
        <?php endif; ?>

        <div class="planning-col">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button class="btn btn-outline-secondary" id="prevWeekBtn"><i class="fas fa-chevron-left"></i></button>
                <h4 id="currentWeekRange" class="mb-0"></h4>
                <div class="d-flex align-items-center">
                    <?php if ($is_admin_view): ?>
                    <button class="btn btn-primary mr-2" id="addMissionBtn"><i class="fas fa-plus"></i> Nouvelle Mission</button>
                    <?php endif; ?>
                    <button class="btn btn-outline-secondary" id="nextWeekBtn"><i class="fas fa-chevron-right"></i></button>
                </div>
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
                        <div class="form-group">
                            <label>Titre de la mission *</label>
                            <input type="text" class="form-control" name="mission_text" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6"><label>Heure début</label><input type="time" class="form-control" name="start_time"></div>
                            <div class="form-group col-md-6"><label>Heure fin</label><input type="time" class="form-control" name="end_time"></div>
                        </div>
                        <div class="form-group"><label>Lieu</label><input type="text" class="form-control" name="location"></div>
                        <div class="form-group">
                            <label>Type</label>
                            <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons" id="shift_type_buttons"></div>
                        </div>
                        <div class="form-group"><label>Couleur</label><div id="mission_color_swatches"></div><input type="hidden" name="color" value="<?= $default_color; ?>"></div>
                        <div class="form-group" id="assign-users-group">
                            <label>Ouvriers assignés *</label>
                            <select class="form-control" name="assigned_user_ids" multiple id="assigned_user_ids_select"></select>
                            <small class="form-text text-muted" id="assign-users-help-text">Sélectionnez un ou plusieurs ouvriers.</small>
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

    <div id="loadingOverlay"><div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div></div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- STATE & CONFIG ---
    const HANDLER_URL = 'planning-handler.php';
    const IS_ADMIN = <?php echo json_encode($is_admin_view); ?>;
    const PREDEFINED_COLORS = <?php echo json_encode($predefined_colors); ?>;
    const DEFAULT_COLOR = <?php echo json_encode($default_color); ?>;
    let state = {
        staff: [],
        missions: [],
        currentWeekStart: getMonday(new Date()),
        draggedWorker: null
    };

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
    async function fetchInitialData() {
        showLoading(true);
        try {
            const endDate = new Date(state.currentWeekStart);
            endDate.setDate(endDate.getDate() + 6);
            const data = await apiCall('get_initial_data', 'GET', { 
                start: formatDate(state.currentWeekStart), 
                end: formatDate(endDate) 
            });
            state.staff = data.staff || [];
            state.missions = data.missions || [];
            updateUI();
        } catch (error) {
            alert(`Erreur de chargement: ${error.message}`);
        } finally {
            showLoading(false);
        }
    }

    // --- UI RENDERING ---
    function updateUI() {
        renderWeekHeader();
        renderPlanningGrid();
        if(IS_ADMIN) {
            renderWorkerList();
            setupModalStaticContent(); // Repopulate dropdown in case staff list changes
        }
    }
    
    function renderWeekHeader() {
        const endDate = new Date(state.currentWeekStart);
        endDate.setDate(endDate.getDate() + 6);
        const options = { day: 'numeric', month: 'long', year: 'numeric' };
        $('#currentWeekRange').text(
            `${state.currentWeekStart.toLocaleDateString('fr-FR', options)} - ${endDate.toLocaleDateString('fr-FR', options)}`
        );
    }
    
    function renderPlanningGrid() {
        $planningContainer.empty();
        for (let i = 0; i < 7; i++) {
            const dayDate = new Date(state.currentWeekStart);
            dayDate.setDate(dayDate.getDate() + i);
            const dateStr = formatDate(dayDate);
            
            const $dayColumn = $(`<div class="day-column"><div class="day-header">${dayDate.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric' })}</div><div class="day-content p-2" data-date="${dateStr}"></div></div>`).appendTo($planningContainer);
            const $dayContent = $dayColumn.find('.day-content');
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
        const workersHtml = assignedNames.map((name, i) => `<li>${name} ${IS_ADMIN ? `<i class="fas fa-times remove-worker-btn" data-worker-id="${assignedIds[i]}"></i>` : ''}</li>`).join('');
        const actionsHtml = IS_ADMIN ? `<div class="mission-actions"><i class="fas fa-check-circle action-btn validate-btn ${mission.is_validated ? 'validated' : ''}" title="Valider"></i></div>` : '';

        return $(`<div class="mission-card ${mission.is_validated ? 'validated' : ''}" style="border-left-color: ${mission.color || '#6c757d'};" data-mission-id="${mission.mission_id}">
                ${actionsHtml}
                <div class="mission-card-body" ${IS_ADMIN ? 'data-toggle="modal" data-target="#missionFormModal"' : ''}>
                    <div class="mission-title">${mission.mission_text}</div>
                    <div class="mission-meta">
                        ${mission.start_time ? `<i class="far fa-clock"></i> ${formatTime(mission.start_time)} - ${formatTime(mission.end_time)}<br>` : ''}
                        ${mission.location ? `<i class="fas fa-map-marker-alt"></i> ${mission.location}` : ''}
                    </div>
                    <ul class="assigned-workers-list">${workersHtml || '<li class="text-muted small">Aucun ouvrier</li>'}</ul>
                </div></div>`);
    }

    function renderWorkerList() {
        const assignedWorkerIds = new Set(state.missions.flatMap(m => m.assigned_user_ids ? m.assigned_user_ids.split(',') : []));
        $workerList.empty();
        state.staff.forEach(worker => {
            const isAssigned = assignedWorkerIds.has(String(worker.user_id));
            $workerList.append(`<div class="worker-item ${isAssigned ? 'assigned' : ''}" draggable="${!isAssigned}" data-worker-id="${worker.user_id}" data-worker-name="${worker.prenom} ${worker.nom}">
                    ${worker.prenom} ${worker.nom}
                    ${isAssigned ? '<div class="assigned-info">Déjà affecté</div>' : ''}
                </div>`);
        });
    }

    // --- EVENT HANDLERS ---
    $('#prevWeekBtn').on('click', () => { state.currentWeekStart.setDate(state.currentWeekStart.getDate() - 7); fetchInitialData(); });
    $('#nextWeekBtn').on('click', () => { state.currentWeekStart.setDate(state.currentWeekStart.getDate() + 7); fetchInitialData(); });
    $('#addMissionBtn').on('click', () => openModalForCreate());

    if (IS_ADMIN) {
        $workerList.on('dragstart', '.worker-item:not(.assigned)', (e) => {
            state.draggedWorker = {
                id: $(e.currentTarget).data('worker-id'),
                name: $(e.currentTarget).data('worker-name')
            };
        });
        
        $planningContainer.on('dragover', '.day-content, .mission-card', (e) => e.preventDefault());
        
        $planningContainer.on('drop', '.day-content', handleDrop);

        $planningContainer.on('click', '.validate-btn', async function(e){ e.stopPropagation(); const missionId = $(this).closest('.mission-card').data('mission-id'); showLoading(true); try { await apiCall('toggle_mission_validation', 'POST', { mission_id: missionId }); await fetchInitialData(); } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); } });
        $planningContainer.on('click', '.remove-worker-btn', async function(e){ e.stopPropagation(); const missionId = $(this).closest('.mission-card').data('mission-id'); const workerId = $(this).data('worker-id'); if (!confirm("Retirer cet ouvrier?")) return; showLoading(true); try { await apiCall('remove_worker_from_mission', 'POST', { worker_id: workerId, mission_id: missionId }); await fetchInitialData(); } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); } });
    }
    
    // --- DRAG & DROP TO CREATE/ASSIGN ---
    async function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        if (!state.draggedWorker) return;

        const $target = $(e.target);
        const $missionCard = $target.closest('.mission-card');
        showLoading(true);
        
        try {
            if ($missionCard.length > 0) { // Dropped on an existing mission
                const missionId = $missionCard.data('mission-id');
                const mission = state.missions.find(m => m.mission_id == missionId);
                await apiCall('assign_worker_to_mission', 'POST', { worker_id: state.draggedWorker.id, mission_id: missionId, assignment_date: mission.assignment_date });
            } else { // Dropped on an empty day column
                const date = $target.closest('.day-content').data('date');
                await apiCall('save_mission', 'POST', {
                    assignment_date: date,
                    mission_text: `Mission pour ${state.draggedWorker.name}`,
                    shift_type: 'custom',
                    color: DEFAULT_COLOR,
                    assigned_user_ids: [state.draggedWorker.id]
                });
            }
            await fetchInitialData();
        } catch (error) {
            alert(`Erreur: ${error.message}`);
        } finally {
            state.draggedWorker = null;
            showLoading(false);
        }
    }


    // --- MODAL & FORM LOGIC ---
    function setupModalStaticContent() {
        $('#shift_type_buttons').html(Object.entries({matin:'Matin', 'apres-midi':'Après-midi', nuit:'Nuit', repos:'Repos', custom:'Personnalisé'}).map(([v, l]) => `<label class="btn btn-sm btn-outline-secondary"><input type="radio" name="shift_type" value="${v}" required> ${l}</label>`).join(''));
        $('#mission_color_swatches').html(PREDEFINED_COLORS.map(c => `<div class="color-swatch" style="background-color:${c};" data-color="${c}"></div>`).join(''));
        $('#assigned_user_ids_select').html(state.staff.map(u => `<option value="${u.user_id}">${u.prenom} ${u.nom}</option>`).join(''));
    }

    function openModalForCreate() {
        $modal.find('form')[0].reset();
        $modal.find('input[name="mission_id"]').val('');
        // Set date to today if no specific date is passed
        $modal.find('input[name="assignment_date"]').val(formatDate(new Date()));
        $modal.find('#missionFormModalLabel').text('Nouvelle Mission');
        $modal.find('#deleteMissionBtn').hide();
        $('#assign-users-group').show();
        $('#assign-users-help-text').text('Sélectionnez un ou plusieurs ouvriers.');
        $modal.modal('show');
    }
    
    $planningContainer.on('click', '.mission-card-body', function(e) {
        if (!IS_ADMIN) return;
        const missionId = $(this).closest('.mission-card').data('mission-id');
        const mission = state.missions.find(m => m.mission_id == missionId);
        if (!mission) return;

        $modal.find('form')[0].reset();
        $modal.find('input[name="mission_id"]').val(mission.mission_id);
        $modal.find('input[name="assignment_date"]').val(mission.assignment_date);
        $modal.find('input[name="mission_text"]').val(mission.mission_text);
        $modal.find('input[name="start_time"]').val(mission.start_time);
        $modal.find('input[name="end_time"]').val(mission.end_time);
        $modal.find('input[name="location"]').val(mission.location);
        $modal.find(`input[name="shift_type"][value="${mission.shift_type}"]`).prop('checked', true).parent().addClass('active');
        $modal.find('input[name="color"]').val(mission.color || DEFAULT_COLOR);
        $modal.find('.color-swatch.selected').removeClass('selected');
        $modal.find(`.color-swatch[data-color="${mission.color || DEFAULT_COLOR}"]`).addClass('selected');
        $modal.find('#missionFormModalLabel').text('Modifier la Mission');
        $modal.find('#deleteMissionBtn').show();
        $('#assign-users-group').hide();
    });
    
    $('#mission_color_swatches').on('click', '.color-swatch', function(){
        const color = $(this).data('color');
        $('input[name="color"]').val(color);
        $('.color-swatch.selected').removeClass('selected');
        $(this).addClass('selected');
    });

    $('#missionForm').on('submit', async function(e) {
        e.preventDefault();
        hideModalError();
        const formData = Object.fromEntries(new FormData(this).entries());
        
        // Client-side validation for new missions
        if (!formData.mission_id) {
            formData.assigned_user_ids = $('#assigned_user_ids_select').val();
            if (!formData.assigned_user_ids || formData.assigned_user_ids.length === 0) {
                showModalError("Veuillez assigner au moins un ouvrier pour une nouvelle mission.");
                return;
            }
        }
        
        showLoading(true);
        try {
            await apiCall('save_mission', 'POST', formData);
            $modal.modal('hide');
            await fetchInitialData();
        } catch (error) {
            showModalError(error.message);
        } finally {
            showLoading(false);
        }
    });

    $('#deleteMissionBtn').on('click', async function() {
        if (!confirm("Supprimer cette mission et toutes ses affectations?")) return;
        const missionId = $('#mission_id_form').val();
        showLoading(true);
        try {
            await apiCall('delete_mission_group', 'POST', { mission_id: missionId });
            $modal.modal('hide');
            await fetchInitialData();
        } catch (error) { showModalError(error.message); } finally { showLoading(false); }
    });
    
    $modal.on('hidden.bs.modal', hideModalError);

    // --- Init ---
    fetchInitialData();
});
</script>
</body>
</html>
