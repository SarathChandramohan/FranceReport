<?php
// 1. Session Management & Login Check
require_once 'session-management.php';
requireLogin();
$user = getCurrentUser(); // Get logged-in user details if needed

// 2. DB Connection (needed for fetching users for the dropdown initially)
require_once 'db-connection.php';

// 3. Fetch users for the "Assign To" dropdown
$usersList = [];
try {
    $stmt = $conn->query("SELECT user_id, nom, prenom FROM Users WHERE status = 'Active' ORDER BY nom, prenom");
    $usersList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching users for events page: " . $e->getMessage());
    // Handle error appropriately
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Événements - Gestion des Ouvriers</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js'></script>
    <style>
        :root {
            --fc-border-color: #e2e8f0;
            --fc-daygrid-day-number-color: #374151;
            --fc-today-bg-color: rgba(37, 99, 235, 0.05);
        }
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        /* General Button Styles */
        .btn {
            padding: 0.6rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease-in-out;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
        }
        .btn-primary { background-color: #2563eb; color: white; }
        .btn-primary:hover { background-color: #1d4ed8; }
        .btn-secondary { background-color: #ffffff; color: #334155; border-color: #cbd5e1; }
        .btn-secondary:hover { background-color: #f8fafc; }
        .btn-danger { background-color: #dc2626; color: white; }
        .btn-danger:hover { background-color: #b91c1c; }
        
        /* Custom List View Styles */
        .fc-list-day-cushion { background-color: #f8fafc; }
        .fc-list-event:hover td { background-color: transparent; }
        .fc .fc-list-event-dot { border-color: transparent; }
        .fc-theme-standard .fc-list { border: none; }
        
        /* Professional Event Card Style */
        .event-card {
            display: flex;
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.06);
            margin-bottom: 1rem;
            overflow: hidden;
            transition: box-shadow 0.2s ease;
        }
        .event-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08), 0 2px 6px rgba(0,0,0,0.06); }
        .event-card-color-bar { width: 6px; flex-shrink: 0; }
        .event-card-content { display: flex; flex-direction: column; gap: 0.75rem; padding: 1rem; width: 100%; }
        
        .event-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }
        .event-title { font-size: 1.125rem; font-weight: 600; color: #111827; line-height: 1.4; }
        .event-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #475569;
        }
        .event-description { font-size: 0.9rem; color: #4b5563; line-height: 1.5; }
        
        .event-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.5rem;
        }
        .user-avatars { display: flex; }
        .user-avatar {
            width: 2rem;
            height: 2rem;
            border-radius: 9999px;
            background-color: #e0e7ff;
            color: #3730a3;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.8rem;
            border: 2px solid white;
            margin-left: -0.5rem;
        }
        .user-avatar:first-child { margin-left: 0; }
        
        /* Modal Styles */
        .modal-backdrop { transition: opacity 0.3s ease; }
        .modal-content { transition: transform 0.3s ease; }
        .form-input {
            width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2); }

        .active-toggle {
            background-color: #2563eb !important;
            color: white !important;
            border-color: #2563eb !important;
        }
    </style>
</head>
<body class="font-sans">

    <div id="calendar-app" class="bg-gray-50 min-h-screen">
        <?php include 'navbar.php'; ?>

        <div class="p-4 sm:p-6 lg:p-8">
            <main class="bg-white p-4 sm:p-6 rounded-lg shadow-sm">
                
                <div id="list-view-controls" class="hidden mb-4 flex items-center justify-between">
                     <div class="flex items-center gap-2">
                        <button id="btn-upcoming" class="btn btn-sm">À venir</button>
                        <button id="btn-past" class="btn btn-sm">Passés</button>
                    </div>
                </div>

                <div id='calendar'></div>
            </main>
        </div>

        <div id="event-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div id="modal-backdrop" class="fixed inset-0 bg-gray-900 bg-opacity-50 modal-backdrop" aria-hidden="true"></div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform modal-content my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form id="event-form" novalidate>
                        <div class="px-4 pt-5 pb-4 sm:p-6">
                            <div class="flex items-start justify-between">
                                <h3 id="eventModalLabel" class="text-xl font-semibold text-gray-900">Nouvel événement</h3>
                                <button type="button" id="close-modal-btn" class="p-1 rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
                            </div>
                            <div class="mt-6 space-y-5">
                                <input type="hidden" id="event-id" name="event_id">
                                <div id="form-error-message" class="hidden bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-lg text-sm" role="alert"></div>
                                <div><input type="text" id="event-title" name="title" placeholder="Ajouter un titre" class="form-input" required /></div>
                                <div class="flex items-center space-x-3">
                                    <i data-lucide="clock-4" class="w-5 h-5 text-gray-500"></i>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 flex-1">
                                        <input type="datetime-local" id="event-start" name="start_datetime" class="form-input text-sm" required/>
                                        <input type="datetime-local" id="event-end" name="end_datetime" class="form-input text-sm" required />
                                    </div>
                                </div>
                                <div><textarea id="event-description" name="description" placeholder="Ajouter une description..." rows="4" class="form-input resize-none"></textarea></div>
                                <div class="form-group hidden">
                                    <label for="event-assigned-users">Assigner à</label>
                                    <select id="event-assigned-users" name="assigned_users[]" multiple>
                                        <?php foreach ($usersList as $u): ?>
                                            <option value="<?php echo $u['user_id']; ?>"><?php echo htmlspecialchars($u['prenom'] . ' ' . $u['nom']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="event-color" class="block text-sm font-medium text-gray-700 mb-1">Couleur de l'événement</label>
                                    <input type="color" class="h-10 w-full p-1 border border-gray-300 rounded-md" id="event-color" name="color" value="#2563eb">
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-4 sm:px-6 sm:flex sm:flex-row-reverse items-center gap-3">
                            <button type="submit" id="save-event-btn" class="btn btn-primary w-full sm:w-auto">Enregistrer</button>
                            <button type="button" id="update-event-btn" class="hidden btn btn-primary w-full sm:w-auto">Mettre à jour</button>
                            <button type="button" id="delete-event-btn" class="hidden btn btn-danger w-full sm:w-auto mr-auto">Supprimer</button>
                            <button type="button" id="cancel-modal-btn" class="btn btn-secondary w-full sm:w-auto mt-2 sm:mt-0">Annuler</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div id="loading-spinner" class="hidden fixed inset-0 bg-white bg-opacity-75 flex items-center justify-center z-[100]">
        <div class="w-12 h-12 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Element References ---
        const calendarEl = document.getElementById('calendar');
        const eventModal = document.getElementById('event-modal');
        const eventForm = document.getElementById('event-form');
        const loadingSpinner = document.getElementById('loading-spinner');
        const controls = document.getElementById('list-view-controls');
        const btnUpcoming = document.getElementById('btn-upcoming');
        const btnPast = document.getElementById('btn-past');

        // --- State ---
        let listMode = 'upcoming'; // 'upcoming' or 'past'
        const isMobile = window.innerWidth <= 768;

        // --- Modal & Form Logic (Mostly Unchanged) ---
        const openModal = () => { eventModal.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); }
        const closeModal = () => { eventModal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); };
        [document.getElementById('close-modal-btn'), document.getElementById('cancel-modal-btn'), document.getElementById('modal-backdrop')].forEach(el => el.addEventListener('click', closeModal));
        function formatLocalDateTimeInput(date) {
            if (!date) return '';
            const d = new Date(date);
            d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
            return d.toISOString().slice(0, 16);
        }
        function resetAndPrepareForm(mode = 'create', startDate = null, endDate = null) {
            eventForm.reset();
            document.getElementById('form-error-message').classList.add('hidden');
            document.getElementById('event-color').value = '#2563eb';
            if (mode === 'create') {
                document.getElementById('eventModalLabel').textContent = 'Créer un nouvel événement';
                document.getElementById('save-event-btn').classList.remove('hidden');
                document.getElementById('update-event-btn').classList.add('hidden');
                document.getElementById('delete-event-btn').classList.add('hidden');
                document.getElementById('event-start').value = startDate ? formatLocalDateTimeInput(startDate) : '';
                const defaultEnd = endDate ? endDate : (startDate ? new Date(startDate.getTime() + 3600000) : null);
                document.getElementById('event-end').value = defaultEnd ? formatLocalDateTimeInput(defaultEnd) : '';
                const selectUsers = document.getElementById('event-assigned-users');
                for (let i = 0; i < selectUsers.options.length; i++) {
                    selectUsers.options[i].selected = true;
                }
            } else {
                document.getElementById('eventModalLabel').textContent = 'Détails de l\'événement';
                document.getElementById('save-event-btn').classList.add('hidden');
                document.getElementById('update-event-btn').classList.remove('hidden');
                document.getElementById('delete-event-btn').classList.remove('hidden');
            }
        }
        function showFormError(message) {
            const formErrorMessage = document.getElementById('form-error-message');
            formErrorMessage.textContent = message;
            formErrorMessage.classList.remove('hidden');
        }


        // --- Calendar Definition ---
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: isMobile ? 'list' : 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,list'
            },
            locale: 'fr',
            buttonText: { today: "Aujourd'hui", month: 'Mois', list: 'Liste' },
            navLinks: true, editable: true, selectable: true, dayMaxEvents: true,
            
            // --- DYNAMIC EVENT SOURCE ---
            events: function(fetchInfo, successCallback, failureCallback) {
                // *** FIX: Use fetchInfo.view.type instead of the uninitialized 'calendar' object ***
                const isListView = fetchInfo.view.type === 'list';
                let start, end;

                if (isListView) {
                    const today = new Date();
                    if (listMode === 'upcoming') {
                        start = today;
                        end = new Date(today.getFullYear() + 5, 11, 31); // 5 years future
                    } else { // past
                        start = new Date(today.getFullYear() - 5, 0, 1); // 5 years past
                        end = today;
                    }
                } else {
                    start = fetchInfo.start;
                    end = fetchInfo.end;
                }
                
                const url = `events_handler.php?action=get_events&start=${start.toISOString()}&end=${end.toISOString()}`;
                
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (isListView && listMode === 'past') {
                            data.sort((a, b) => new Date(b.start) - new Date(a.start));
                        }
                        successCallback(data);
                    })
                    .catch(error => failureCallback(error));
            },

            loading: (isLoading) => { loadingSpinner.style.display = isLoading ? 'flex' : 'none'; },

            // --- VIEW MANAGEMENT ---
            viewDidMount: function(info) {
                if (info.view.type === 'list') {
                    controls.classList.remove('hidden');
                    updateToggleButtons();
                } else {
                    controls.classList.add('hidden');
                }
            },
            
            // --- CUSTOM EVENT RENDERING FOR LIST VIEW ---
            eventContent: function(arg) {
                // Apply only to list view, not month view
                if (arg.view.type !== 'list') return; 

                const props = arg.event.extendedProps;
                const assignedUsers = props.assigned_users || [];
                const start = new Date(arg.event.start);
                const end = new Date(arg.event.end);

                const formatDate = (d) => d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
                const formatTime = (d) => d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', hour12: false });
                
                let usersHtml = assignedUsers.slice(0, 5).map(user => {
                    const initials = (user.name || '').split(' ').map(n => n[0]).join('').toUpperCase();
                    const colorValue = (user.user_id * 47) % 360;
                    return `<div title="${user.name}" class="user-avatar" style="background-color: hsl(${colorValue}, 60%, 85%); color: hsl(${colorValue}, 40%, 30%);">${initials}</div>`;
                }).join('');

                if(assignedUsers.length > 5) {
                    usersHtml += `<div class="user-avatar" style="background-color: #e2e8f0; color: #475569;">+${assignedUsers.length - 5}</div>`;
                }

                let html = `
                    <a href="javascript:void(0)" class="event-card-link w-full">
                        <div class="event-card">
                            <div class="event-card-color-bar" style="background-color: ${arg.event.backgroundColor};"></div>
                            <div class="event-card-content">
                                <div class="event-card-header">
                                    <h3 class="event-title">${arg.event.title}</h3>
                                </div>
                                <div class="event-date">
                                    <i data-lucide="calendar" class="w-4 h-4"></i>
                                    <span>${formatDate(start)}</span>
                                </div>
                                <div class="event-date">
                                    <i data-lucide="clock" class="w-4 h-4"></i>
                                    <span>${formatTime(start)} - ${formatTime(end)}</span>
                                </div>
                                ${props.description ? `<p class="event-description">${props.description}</p>` : ''}
                                <div class="event-card-footer">
                                    <div class="user-avatars">${usersHtml}</div>
                                </div>
                            </div>
                        </div>
                    </a>
                `;
                return { domNodes: [new DOMParser().parseFromString(html, 'text/html').body.firstChild] };
            },
            
            // --- INTERACTIONS ---
            select: function(info) { resetAndPrepareForm('create', info.start, info.end); openModal(); },
            eventClick: function(info) {
                info.jsEvent.preventDefault(); // Prevent link navigation
                resetAndPrepareForm('edit');
                const event = info.event;
                const props = event.extendedProps;
                document.getElementById('event-id').value = event.id;
                document.getElementById('event-title').value = event.title;
                document.getElementById('event-description').value = props.description || '';
                document.getElementById('event-start').value = formatLocalDateTimeInput(event.start);
                document.getElementById('event-end').value = formatLocalDateTimeInput(event.end);
                document.getElementById('event-color').value = event.backgroundColor || '#2563eb';
                const assignedUserIds = props.assigned_user_ids || [];
                const select = document.getElementById('event-assigned-users');
                for (let i = 0; i < select.options.length; i++) {
                    select.options[i].selected = assignedUserIds.includes(parseInt(select.options[i].value));
                }
                openModal();
            }
        });

        calendar.render();
        lucide.createIcons();

        // --- Event Form Submission ---
        eventForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(eventForm);
            if (!formData.get('title') || !formData.get('start_datetime') || !formData.get('end_datetime')) {
                showFormError("Veuillez remplir tous les champs obligatoires.");
                return;
            }
            if (new Date(formData.get('end_datetime')) <= new Date(formData.get('start_datetime'))) {
                showFormError("La date de fin doit être postérieure à la date de début.");
                return;
            }
            formData.append('action', 'create_event');
            loadingSpinner.style.display = 'flex';
            fetch('events_handler.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        closeModal();
                        calendar.refetchEvents();
                    } else {
                        showFormError(data.message || 'Une erreur est survenue.');
                    }
                })
                .catch(err => { console.error("Error:", err); showFormError('Erreur de communication.'); })
                .finally(() => { loadingSpinner.style.display = 'none'; });
        });
        
        // --- List View Toggle Logic ---
        function updateToggleButtons() {
            if (listMode === 'upcoming') {
                btnUpcoming.classList.add('active-toggle', 'btn-primary');
                btnUpcoming.classList.remove('btn-secondary');
                btnPast.classList.remove('active-toggle', 'btn-primary');
                btnPast.classList.add('btn-secondary');
            } else {
                btnPast.classList.add('active-toggle', 'btn-primary');
                btnPast.classList.remove('btn-secondary');
                btnUpcoming.classList.remove('active-toggle', 'btn-primary');
                btnUpcoming.classList.add('btn-secondary');
            }
        }

        btnUpcoming.addEventListener('click', () => {
            if (listMode !== 'upcoming') {
                listMode = 'upcoming';
                updateToggleButtons();
                calendar.refetchEvents();
            }
        });

        btnPast.addEventListener('click', () => {
            if (listMode !== 'past') {
                listMode = 'past';
                updateToggleButtons();
                calendar.refetchEvents();
            }
        });
    });
    </script>
</body>
</html>
