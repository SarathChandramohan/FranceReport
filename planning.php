<?php
// planning.php (Revised & Final)

require_once 'session-management.php';
require_once 'db-connection.php'; // Ensure this path is correct
requireLogin();

$user = getCurrentUser();
$user_id_logged_in = $user['user_id'];
$user_role = $user['role'];

// Define predefined colors
$predefined_colors = [
    '#1877f2', '#34c759', '#ff9500', '#5856d6', '#ff3b30',
    '#007aff', '#ffcc00', '#8e8e93', '#ff2d55', '#00a096'
];
$default_color = $predefined_colors[0];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Planning - <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        /* Common Styles */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f0f2f5; color: #1c1e21; }
        .main-container { display: flex; min-height: calc(100vh - 70px); padding-top: 15px; padding-bottom: 20px; }
        .card { background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1), 0 8px 16px rgba(0, 0, 0, 0.1); margin-bottom: 20px; border: none; }
        .card-header { background-color: #f7f7f7; font-weight: 600; border-bottom: 1px solid #dddfe2; padding: 0.75rem 1.25rem; font-size: 1.1rem; }
        .btn-primary { background-color: #1877f2; border-color: #1877f2; }
        .btn-primary:hover { background-color: #166fe5; border-color: #166fe5; }
        
        /* Left Column: Workers List */
        .workers-list-col { width: 280px; flex-shrink: 0; margin-right: 20px; position: sticky; top: 70px; max-height: calc(100vh - 90px); }
        .workers-list-card .card-body { max-height: calc(100vh - 200px); overflow-y: auto; padding: 15px; }
        .worker-item { padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 8px; background-color: #fcfdff; cursor: grab; transition: all 0.2s ease; }
        .worker-item:hover { background-color: #e6f7ff; border-color: #91d5ff; }
        .worker-item.assigned { background-color: #f0f0f0; border-color: #d0d0d0; color: #808080; cursor: not-allowed; opacity: 0.7; }
        .worker-item.assigned .assigned-info { font-size: 0.85em; color: #d9534f; margin-top: 5px; }
        
        /* Right Column: Daily Planning */
        .daily-planning-col { flex-grow: 1; overflow-x: auto; padding-right: 15px; }
        .daily-planning-container { display: flex; min-width: 100%; width: fit-content; gap: 15px; padding-bottom: 10px; }
        .day-column { flex-shrink: 0; width: 300px; background-color: #f9f9f9; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); border: 1px solid #e5e5e5; min-height: 600px; }
        .day-header { padding: 15px; background-color: #f0f2f5; border-bottom: 1px solid #e0e0e0; border-radius: 12px 12px 0 0; text-align: center; }
        .day-header h3 { font-size: 1.1em; font-weight: 600; color: #343a40; margin-bottom: 5px; }
        .day-content { padding: 15px; min-height: 500px; border-radius: 0 0 12px 12px; }
        .add-mission-btn { width: 100%; padding: 8px 15px; margin-top: 10px; font-size: 0.9em; font-weight: 600; }
        
        .mission-card { background-color: #fff; border-left: 5px solid; border-radius: 8px; padding: 12px; margin-bottom: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); position: relative; transition: all 0.2s ease; }
        .mission-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .mission-card.validated { opacity: 0.8; border-color: #28a745 !important; background-color: #f0fff0; }
        .mission-card .mission-title { font-weight: 600; font-size: 1em; color: #333; margin-bottom: 5px; padding-right: 80px; }
        .mission-card .mission-meta { font-size: 0.8em; color: #666; margin-bottom: 5px; }
        .mission-card .mission-meta i { margin-right: 5px; }
        .mission-card .mission-comment { font-size: 0.8em; color: #555; margin-top: 8px; padding-top: 8px; border-top: 1px solid #f0f0f0; white-space: pre-wrap; word-wrap: break-word; }
        .mission-card .worker-assigned-list { list-style: none; padding: 0; margin: 8px 0 0; max-height: 80px; overflow-y: auto; border-top: 1px dashed #eee; padding-top: 8px; }
        .mission-card .worker-assigned-list li { font-size: 0.8rem; background-color: #e9f5ff; padding: 3px 8px; border-radius: 4px; margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center; }
        .mission-card .worker-assigned-list li .remove-worker-btn { background: none; border: none; color: #d9534f; font-size: 1.1em; cursor: pointer; padding: 0 3px; }
        .mission-card .drag-worker-placeholder { font-size: 0.8em; color: #999; text-align: center; padding: 10px 0; border: 2px dashed #ddd; border-radius: 5px; }
        .mission-actions { position: absolute; top: 5px; right: 5px; display: flex; gap: 5px; }
        .mission-actions button { background: none; border: none; color: #999; font-size: 1em; cursor: pointer; padding: 3px; transition: color 0.2s; }
        .mission-actions button:hover { color: #333; }
        .mission-actions .validate-btn.active { color: #28a745; }
        
        /* Modal and Overlays */
        #missionFormModal .modal-content { border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        #missionFormModal .modal-header { background-color: #007bff; color: white; border-top-left-radius: 15px; border-top-right-radius: 15px; }
        #loadingOverlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255, 255, 255, 0.8); z-index: 1060; display: flex; justify-content: center; align-items: center; }

        /* Responsive */
        @media (max-width: 991px) { .main-container { flex-direction: column; } .workers-list-col { width: 100%; position: static; margin-bottom: 20px; } .daily-planning-container { flex-wrap: wrap; justify-content: center; } .day-column { width: 95%; max-width: 400px; margin-bottom: 15px; } }
    </style>
</head>
<body class="body-with-staff-nav">
    <?php include 'navbar.php'; ?>

    <div class="container-fluid main-container">
        <div class="workers-list-col">
            <div class="card workers-list-card">
                <div class="card-header bg-primary text-white"><i class="fas fa-users mr-2"></i>Ouvriers Disponibles</div>
                <div class="card-body">
                    <div id="workerList" class="list-group list-group-flush"></div>
                </div>
            </div>
        </div>

        <div class="daily-planning-col">
            <div class="card">
                <div class="card-header"><i class="fas fa-calendar-alt mr-2"></i>Planning Hebdomadaire</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <button class="btn btn-outline-secondary btn-sm" id="prevWeekBtn"><i class="fas fa-arrow-left"></i></button>
                        <h2 class="h4 mb-0" id="currentWeekRange"></h2>
                        <button class="btn btn-outline-secondary btn-sm" id="nextWeekBtn"><i class="fas fa-arrow-right"></i></button>
                    </div>
                    <div id="dailyPlanningContainer" class="daily-planning-container"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="missionFormModal" tabindex="-1" aria-labelledby="missionFormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="missionForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="missionFormModalLabel">Nouvelle Mission</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="mission_date_form" name="mission_date">
                        <input type="hidden" id="original_mission_id_form" name="original_mission_id">
                        <input type="hidden" id="is_group_mission_edit" name="is_group_mission_edit" value="0">
                        <div class="form-group"><label for="mission_title">Titre de la mission *</label><input type="text" class="form-control" id="mission_title" name="title" required></div>
                        <div class="form-row">
                            <div class="form-group col-md-6"><label for="mission_start_time">Heure début</label><input type="time" class="form-control" id="mission_start_time" name="start_time"></div>
                            <div class="form-group col-md-6"><label for="mission_end_time">Heure fin</label><input type="time" class="form-control" id="mission_end_time" name="end_time"></div>
                        </div>
                        <div class="form-group"><label for="mission_location">Lieu</label><input type="text" class="form-control" id="mission_location" name="location"></div>
                        <div class="form-group"><label for="mission_comment">Commentaire</label><textarea class="form-control" id="mission_comment" name="comment" rows="3"></textarea></div>
                        <div class="form-group"><label>Type</label><div class="btn-group btn-group-toggle d-flex" data-toggle="buttons" id="shift_type_buttons"></div></div>
                        <div class="form-group"><label>Couleur</label><div id="mission_color_swatches" class="d-flex flex-wrap"></div><input type="hidden" id="mission_color" name="color" value="<?php echo $default_color; ?>"></div>
                        <div id="modal_error_message" class="alert alert-danger mt-3" style="display: none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-danger" id="deleteMissionBtn" style="display: none;">Supprimer</button>
                        <button type="submit" class="btn btn-primary" id="saveMissionBtn">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="loadingOverlay"><div class="spinner-border text-primary" role="status"></div></div>

    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    try { // Wrap all JS in a try-catch for better error detection
        const HANDLER_URL = 'planning-handler.php';
        let staffUsers = [];
        let missions = {}; 
        let draggedOuvrier = null;
        let currentWeekStart = new Date();
        const defaultMissionColor = '<?php echo $default_color; ?>';
        const predefinedColors = <?php echo json_encode($predefined_colors); ?>;
        
        // --- UTILITY FUNCTIONS ---
        const showLoading = (show) => $('#loadingOverlay').css('display', show ? 'flex' : 'none');
        const showModalError = (message) => $('#modal_error_message').text(message).show().delay(5000).fadeOut();
        const formatDateToYMD = (date) => date.toISOString().split('T')[0];
        const formatTimeHM = (timeStr) => timeStr ? String(timeStr).substring(0, 5) : '';

        // --- INITIALIZATION ---
        $(document).ready(function() {
            setWeekRange(new Date());
            loadInitialData();
            attachEventListeners();
            renderStaticComponents();
        });

        function loadInitialData() {
            showLoading(true);
            Promise.all([
                apiCall('get_staff_users'),
                loadPlanningData()
            ]).then(([staffResponse]) => {
                staffUsers = staffResponse.data.users || [];
                renderWorkerList();
            }).catch(error => {
                console.error("Error during initial data load:", error);
                alert("Erreur critique lors du chargement des données initiales. Vérifiez la console (F12).");
            }).finally(() => showLoading(false));
        }

        function renderStaticComponents() {
            const shiftTypes = { matin: 'Matin', 'apres-midi': 'Après-midi', nuit: 'Nuit', repos: 'Repos', custom: 'Personnalisé' };
            const shiftButtons = Object.entries(shiftTypes).map(([value, label]) => `<label class="btn btn-sm btn-outline-secondary"><input type="radio" name="shift_type" value="${value}">${label}</label>`).join('');
            $('#shift_type_buttons').html(shiftButtons);
            const colorSwatches = predefinedColors.map(color => `<div class="color-swatch" style="background-color: ${color};" data-color="${color}" title="${color}"></div>`).join('');
            $('#mission_color_swatches').html(colorSwatches);
        }

        function attachEventListeners() {
            $('#prevWeekBtn').on('click', () => changeWeek(-7));
            $('#nextWeekBtn').on('click', () => changeWeek(7));
            $('#missionForm').on('submit', handleMissionFormSubmit);
            $('#deleteMissionBtn').on('click', handleDeleteMissionFromModal);
            $('#missionFormModal').on('hidden.bs.modal', resetMissionForm);
            
            $('#dailyPlanningContainer').on('click', '.add-mission-btn', function() { openMissionFormForCreate($(this).data('date')); });
            $('#dailyPlanningContainer').on('click', '.edit-btn', function(e) { e.stopPropagation(); openMissionFormForEdit($(this).closest('.mission-card')); });
            $('#dailyPlanningContainer').on('click', '.delete-btn', function(e) { e.stopPropagation(); handleDeleteMissionFromCard($(this).closest('.mission-card')); });
            $('#dailyPlanningContainer').on('click', '.remove-worker-btn', function(e) { e.stopPropagation(); removeWorkerFromMission($(this)); });
            $('#dailyPlanningContainer').on('click', '.validate-btn', function(e) { e.stopPropagation(); toggleMissionValidation($(this).closest('.mission-card')); });
            
            $('#mission_color_swatches').on('click', '.color-swatch', function() {
                $('#mission_color_swatches .color-swatch').removeClass('selected');
                $(this).addClass('selected');
                $('#mission_color').val($(this).data('color'));
            });

            $('#shift_type_buttons').on('change', 'input[type="radio"]', function() {
                const isTimeDisabled = $(this).val() === 'repos';
                $('#mission_start_time, #mission_end_time').prop('disabled', isTimeDisabled).val(isTimeDisabled ? '' : $('#mission_start_time').val());
            });
        }

        // --- WEEK NAVIGATION ---
        function setWeekRange(date) {
            currentWeekStart = new Date(date);
            const dayOfWeek = currentWeekStart.getDay(); // Sunday=0, Monday=1
            currentWeekStart.setDate(currentWeekStart.getDate() - (dayOfWeek === 0 ? 6 : dayOfWeek - 1));
            const weekEnd = new Date(currentWeekStart);
            weekEnd.setDate(weekEnd.getDate() + 6);
            const options = { day: 'numeric', month: 'long' };
            $('#currentWeekRange').text(`${currentWeekStart.toLocaleDateString('fr-FR', options)} - ${weekEnd.toLocaleDateString('fr-FR', options)}`);
        }

        function changeWeek(days) {
            currentWeekStart.setDate(currentWeekStart.getDate() + days);
            setWeekRange(currentWeekStart);
            loadPlanningData().then(renderWorkerList);
        }

        // --- DATA LOADING & RENDERING ---
        async function loadPlanningData() {
            showLoading(true);
            const endDate = new Date(currentWeekStart);
            endDate.setDate(endDate.getDate() + 6);
            try {
                const missionsData = await apiCall('get_assignments', 'GET', { start: formatDateToYMD(currentWeekStart), end: formatDateToYMD(endDate) });
                missions = {};
                (missionsData.data.missions || []).forEach(mission => {
                    const dateKey = mission.start_date_group;
                    if (!missions[dateKey]) missions[dateKey] = [];
                    missions[dateKey].push(mission);
                });
                renderPlanning();
            } catch (error) {
                console.error("Error loading planning data:", error);
                alert("Erreur lors du chargement du planning. Vérifiez la console (F12).");
            } finally {
                showLoading(false);
            }
        }

        function renderWorkerList() {
            const listEl = $('#workerList').empty();
            const assignedUsersThisWeek = new Set();
            Object.values(missions).flat().forEach(m => {
                if(m.user_ids_list) m.user_ids_list.split(',').forEach(id => assignedUsersThisWeek.add(parseInt(id)));
            });

            staffUsers.forEach(ouvrier => {
                const isAssigned = assignedUsersThisWeek.has(ouvrier.user_id);
                const item = $(`<div class="worker-item ${isAssigned ? 'assigned' : ''}" draggable="${!isAssigned}" data-id="${ouvrier.user_id}" data-nom="${ouvrier.nom}" data-prenom="${ouvrier.prenom}">
                    <div class="font-weight-bold">${ouvrier.prenom} ${ouvrier.nom}</div>
                    ${isAssigned ? '<div class="assigned-info">Affecté cette semaine</div>' : ''}
                </div>`);
                if (!isAssigned) {
                    item.on('dragstart', (e) => {
                        draggedOuvrier = $(e.currentTarget).data();
                        e.originalEvent.dataTransfer.effectAllowed = 'move';
                    });
                }
                listEl.append(item);
            });
        }

        function renderPlanning() {
            const container = $('#dailyPlanningContainer').empty();
            let currentDate = new Date(currentWeekStart);
            for (let i = 0; i < 7; i++) {
                const dateKey = formatDateToYMD(currentDate);
                const dayColumn = $(`<div class="day-column">
                    <div class="day-header"><h3>${currentDate.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'short' })}</h3><button class="btn btn-sm btn-success add-mission-btn" data-date="${dateKey}"><i class="fas fa-plus"></i> Mission</button></div>
                    <div class="day-content" data-date="${dateKey}"></div>
                </div>`);
                dayColumn.find('.day-content').on('dragover', (e) => e.preventDefault()).on('drop', handleDrop);
                container.append(dayColumn);
                renderMissionsForDay(dateKey);
                currentDate.setDate(currentDate.getDate() + 1);
            }
        }

        function renderMissionsForDay(dateKey) {
            const dayContent = $(`.day-content[data-date="${dateKey}"]`).empty();
            const missionsForDay = (missions[dateKey] || []).sort((a,b) => (a.start_time || '99').localeCompare(b.start_time || '99'));

            if (missionsForDay.length === 0) {
                 dayContent.html('<div class="drag-worker-placeholder">Glissez un ouvrier ici pour créer une mission</div>');
                 return;
            }
            missionsForDay.forEach(mission => {
                const workerNames = (mission.user_names_list || '').split(', ');
                const workerIds = (mission.user_ids_list || '').split(',');
                const workersHtml = mission.user_names_list ? workerNames.map((name, i) => `<li><span>${name}</span><button class="remove-worker-btn" data-worker-id="${workerIds[i]}" title="Retirer"><i class="fas fa-times-circle"></i></button></li>`).join('') : '';
                const missionCard = $(`<div class="mission-card ${mission.is_validated ? 'validated' : ''}" data-date="${dateKey}" data-id="${mission.representative_assignment_id}" style="border-left-color: ${mission.color || defaultMissionColor};">
                    <div class="mission-actions">
                        <button class="validate-btn ${mission.is_validated ? 'active' : ''}" title="Valider"><i class="fas fa-check"></i></button>
                        <button class="edit-btn" title="Modifier"><i class="fas fa-edit"></i></button>
                        <button class="delete-btn" title="Supprimer"><i class="fas fa-trash-alt"></i></button>
                    </div>
                    <div class="mission-title">${mission.title || 'Mission sans titre'}</div>
                    <div class="mission-meta">${mission.start_time ? `<i class="fas fa-clock"></i> ${formatTimeHM(mission.start_time)} - ${formatTimeHM(mission.end_time)}` : ''} ${mission.location ? `<br><i class="fas fa-map-marker-alt"></i> ${mission.location}` : ''}</div>
                    ${mission.comment ? `<div class="mission-comment"><i class="fas fa-info-circle"></i> ${mission.comment}</div>` : ''}
                    <ul class="worker-assigned-list">${workersHtml}</ul>
                </div>`);
                dayContent.append(missionCard);
            });
        }
        
        // --- DRAG & DROP ---
        async function handleDrop(e) {
            e.preventDefault();
            if (!draggedOuvrier) return;

            const dayContent = $(e.currentTarget);
            const dateKey = dayContent.data('date');
            const targetCard = $(e.target).closest('.mission-card');
            
            showLoading(true);
            try {
                if (targetCard.length) { // Drop on existing mission
                    await apiCall('assign_worker_to_mission', 'POST', { worker_id: draggedOuvrier.id, mission_date: dateKey, original_mission_id: targetCard.data('id') });
                } else { // Drop on empty day column to create mission
                    await apiCall('save_assignment', 'POST', {
                        title: `Mission pour ${draggedOuvrier.prenom}`, mission_date: dateKey, shift_type: 'custom',
                        start_time: '08:00', end_time: '17:00', color: defaultMissionColor,
                        assigned_user_ids: [draggedOuvrier.id]
                    });
                }
                await loadPlanningData();
                renderWorkerList();
            } catch (error) {
                alert(`Erreur: ${error.message}`);
            } finally {
                draggedOuvrier = null;
                showLoading(false);
            }
        }
        
        // --- MISSION ACTIONS (CRUD) ---
        function openMissionFormForCreate(dateKey) {
            resetMissionForm();
            $('#missionFormModalLabel').text('Nouvelle Mission');
            $('#mission_date_form').val(dateKey);
            $('#missionFormModal').modal('show');
        }

        async function openMissionFormForEdit(card) {
            resetMissionForm();
            showLoading(true);
            try {
                const response = await apiCall('get_mission_details_for_edit', 'GET', { original_mission_id: card.data('id') });
                const m = response.data.mission;
                if (m) {
                    $('#missionFormModalLabel').text('Modifier Mission');
                    $('#mission_date_form').val(card.data('date'));
                    $('#original_mission_id_form').val(card.data('id'));
                    $('#is_group_mission_edit').val('1');
                    $('#mission_title').val(m.title);
                    $('#mission_start_time').val(formatTimeHM(m.start_time));
                    $('#mission_end_time').val(formatTimeHM(m.end_time));
                    $('#mission_location').val(m.location);
                    $('#mission_comment').val(m.comment);
                    $(`#shift_type_buttons input[value="${m.shift_type}"]`).prop('checked', true).closest('label').addClass('active');
                    $(`.color-swatch[data-color="${m.color}"]`).addClass('selected');
                    $('#mission_color').val(m.color);
                    $('#deleteMissionBtn').show();
                    $('#missionFormModal').modal('show');
                }
            } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); }
        }

        async function handleMissionFormSubmit(e) {
            e.preventDefault();
            const formData = Object.fromEntries(new FormData(e.target).entries());
            formData.is_group_edit = formData.is_group_mission_edit === '1';
            
            if (!formData.title || !$('#shift_type_buttons input:checked').val()) {
                showModalError('Titre et type de service sont requis.');
                return;
            }
            showLoading(true);
            try {
                const response = await apiCall('save_assignment', 'POST', formData);
                $('#missionFormModal').modal('hide');
                await loadPlanningData();
                renderWorkerList();
            } catch (error) { showModalError(error.message); } finally { showLoading(false); }
        }

        function handleDeleteMissionFromCard(card) {
            if (!confirm('Supprimer cette mission et toutes ses affectations?')) return;
            deleteMissionGroup(card.data('date'), card.data('id'));
        }

        function handleDeleteMissionFromModal() {
            if (!confirm('Supprimer cette mission et toutes ses affectations?')) return;
            deleteMissionGroup($('#mission_date_form').val(), $('#original_mission_id_form').val());
            $('#missionFormModal').modal('hide');
        }

        async function deleteMissionGroup(missionDate, missionId) {
            showLoading(true);
            try {
                await apiCall('delete_assignment', 'POST', { mission_date: missionDate, original_mission_id: missionId });
                await loadPlanningData();
                renderWorkerList();
            } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); }
        }

        async function removeWorkerFromMission(button) {
            const card = button.closest('.mission-card');
            if (!confirm('Retirer cet ouvrier de la mission?')) return;
            showLoading(true);
            try {
                await apiCall('remove_worker_from_assignment', 'POST', { worker_id: button.data('worker-id'), mission_date: card.data('date'), original_mission_id: card.data('id') });
                await loadPlanningData();
                renderWorkerList();
            } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); }
        }
        
        async function toggleMissionValidation(card) {
            showLoading(true);
            try {
                await apiCall('toggle_mission_validation', 'POST', { mission_date: card.data('date'), original_mission_id: card.data('id') });
                await loadPlanningData();
            } catch (error) { alert(`Erreur: ${error.message}`); } finally { showLoading(false); }
        }

        function resetMissionForm() {
            $('#missionForm')[0].reset();
            $('#is_group_mission_edit').val('0');
            $('#modal_error_message').hide();
            $('#deleteMissionBtn').hide();
            $('#shift_type_buttons label').removeClass('active');
            $('#mission_color_swatches .color-swatch').removeClass('selected');
            $(`.color-swatch[data-color="${defaultMissionColor}"]`).addClass('selected');
            $('#mission_color').val(defaultMissionColor);
        }

        // --- API HELPER ---
        async function apiCall(action, method = 'GET', data = null) {
            const options = { method };
            let url = `${HANDLER_URL}?action=${action}`;
            if (data) {
                if (method === 'GET') url += '&' + new URLSearchParams(data).toString();
                else { options.headers = { 'Content-Type': 'application/json' }; options.body = JSON.stringify(data); }
            }

            try {
                const response = await fetch(url, options);
                const responseData = await response.json();
                if (!response.ok || responseData.status === 'error') {
                    console.error('API Error Response:', responseData);
                    throw new Error(responseData.message || `Erreur serveur (${response.status})`);
                }
                return responseData;
            } catch (error) {
                console.error(`API call to ${action} failed:`, error);
                throw error;
            }
        }
    } catch(e) {
        console.error("A critical JavaScript error occurred on the page:", e);
        alert("Une erreur JavaScript critique a empêché le chargement de la page. Vérifiez la console (F12) pour les détails.");
        $('#loadingOverlay').hide();
    }
    </script>
</body>
</html>
