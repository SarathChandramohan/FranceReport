<?php
// 1. Session Management & Login Check
require_once 'session-management.php';
requireLogin();
$user = getCurrentUser();

// 2. DB Connection
require_once 'db-connection.php';

// 3. Fetch users for the modal dropdown
$usersList = [];
try {
    $stmt = $conn->query("SELECT user_id, nom, prenom FROM Users WHERE status = 'Active' ORDER BY nom, prenom");
    $usersList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching users for events page: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendrier - Gestion des Ouvriers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-brand-color: #4f46e5;
            --primary-brand-hover: #4338ca;
            --danger-color: #ef4444;
            --danger-hover-color: #dc2626;
            --bg-light: #f8fafc;
            --bg-base: #ffffff;
            --bg-gray: #f1f5f9;
            --border-color: #e2e8f0;
            --text-heading: #1e293b;
            --text-body: #334155;
            --text-muted: #64748b;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --border-radius: 0.75rem;
            --transition-speed: 0.2s;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-gray);
            color: var(--text-body);
            margin: 0;
            -webkit-font-smoothing: antialiased;
        }

        /* --- Main Layout --- */
        .page-wrapper { padding: 1rem; }
        .calendar-layout {
            max-width: 90rem;
            margin: auto;
            background-color: var(--bg-base);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        @media (min-width: 1024px) {
            .page-wrapper { padding: 2rem; }
            .calendar-layout { flex-direction: row; }
        }

        /* --- Calendar Pane --- */
        .calendar-main { flex-grow: 1; padding: 1.5rem; }
        .calendar-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .calendar-nav { display: flex; align-items: center; gap: 0.5rem; }
        .month-year-display { font-size: 1.5rem; font-weight: 600; color: var(--text-heading); margin: 0 0.5rem; }
        .nav-btn, .today-btn, .create-btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            border: 1px solid var(--border-color);
            background-color: var(--bg-base);
            cursor: pointer;
            transition: all var(--transition-speed) ease;
        }
        .nav-btn:hover, .today-btn:hover { background-color: var(--bg-gray); box-shadow: var(--shadow-sm); }
        .create-btn { background-color: var(--primary-brand-color); color: white; border-color: var(--primary-brand-color); gap: 0.5rem; }
        .create-btn:hover { background-color: var(--primary-brand-hover); box-shadow: var(--shadow-md); }
        
        /* --- Calendar Grid --- */
        .calendar-grid-wrapper { border-radius: var(--border-radius); overflow: hidden; border: 1px solid var(--border-color); }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); }
        .day-header { text-align: center; padding: 0.75rem 0; font-weight: 500; font-size: 0.75rem; color: var(--text-muted); background-color: var(--bg-light); border-bottom: 1px solid var(--border-color); }
        .day-cell { position: relative; min-height: 8rem; padding: 0.5rem; border-top: 1px solid var(--border-color); border-left: 1px solid var(--border-color); transition: background-color var(--transition-speed) ease; cursor: pointer; }
        .day-cell:hover { background-color: var(--bg-light); }
        .day-cell.other-month { background-color: var(--bg-light); color: #9ca3af; cursor: default; }
        .day-number { font-weight: 500; font-size: 0.875rem; display: flex; justify-content: flex-end; }
        .day-number span { width: 1.75rem; height: 1.75rem; display: flex; align-items: center; justify-content: center; border-radius: 9999px; transition: all var(--transition-speed) ease; }
        .day-number.is-today span { background-color: var(--primary-brand-color); color: white; font-weight: 700; }
        .event-wrapper { margin-top: 0.5rem; display: flex; flex-direction: column; gap: 4px; }
        .event-pill { font-size: 0.75rem; color: white; padding: 0.2rem 0.5rem; border-radius: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        /* --- Sidebar --- */
        .sidebar-pane { width: 100%; padding: 1.5rem; background-color: var(--bg-light); }
        @media (min-width: 1024px) { .sidebar-pane { width: 25rem; border-left: 1px solid var(--border-color); } }
        .sidebar-header { font-size: 1.25rem; font-weight: 600; margin: 0 0 1rem 0; color: var(--text-heading); }
        .event-list { max-height: 70vh; overflow-y: auto; padding-right: 0.5rem; }
        .event-list-card { background-color: var(--bg-base); border: 1px solid var(--border-color); padding: 1rem; border-radius: 0.5rem; margin-bottom: 0.75rem; box-shadow: var(--shadow-sm); }
        .event-list-card-title { font-weight: 600; color: var(--text-heading); }
        .event-list-card-date { font-size: 0.875rem; color: var(--text-muted); margin: 0.25rem 0; }
        
        /* --- Modal --- */
        .modal { position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center; padding: 1rem; visibility: hidden; opacity: 0; transition: visibility 0s var(--transition-speed), opacity var(--transition-speed) ease; }
        .modal.is-visible { visibility: visible; opacity: 1; transition-delay: 0s; }
        .modal-backdrop { position: fixed; inset: 0; background-color: rgba(30, 41, 59, 0.5); }
        .modal-content { background-color: var(--bg-base); border-radius: var(--border-radius); box-shadow: var(--shadow-lg); width: 100%; max-width: 36rem; transform: scale(0.95); transition: transform var(--transition-speed) ease; }
        .modal.is-visible .modal-content { transform: scale(1); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); }
        .modal-title { font-size: 1.25rem; font-weight: 600; margin: 0; color: var(--text-heading); }
        .modal-body { padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; }
        .modal-footer { background-color: var(--bg-light); padding: 1rem 1.5rem; display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 0.75rem; border-bottom-left-radius: var(--border-radius); border-bottom-right-radius: var(--border-radius); }

        /* --- Forms & Utilities --- */
        .form-group > label { display: block; font-weight: 500; font-size: 0.875rem; margin-bottom: 0.5rem; }
        .form-input { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem; background-color: var(--bg-base); transition: all var(--transition-speed) ease; }
        .form-input:focus { outline: none; border-color: var(--primary-brand-color); box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2); }
        select[multiple] { height: 120px; }
        .form-error { display: none; background-color: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 1rem; border-radius: 0.5rem; }
        .btn-modal { padding: 0.6rem 1.25rem; }
        .btn-modal-danger { background-color: var(--danger-color); color: white; border-color: var(--danger-color); }
        .btn-modal-danger:hover { background-color: var(--danger-hover-color); }
        .btn-modal-delete { margin-right: auto; }
        .hidden { display: none; }
        .loader-wrapper { position: fixed; inset: 0; z-index: 200; display: flex; align-items: center; justify-content: center; background-color: rgba(255, 255, 255, 0.8); }
        .loader { width: 3rem; height: 3rem; border: 4px solid var(--primary-brand-color); border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="page-wrapper">
        <div class="calendar-layout">
            <main class="calendar-main">
                <header class="calendar-header">
                    <div class="calendar-nav">
                        <button id="prev-month" class="nav-btn" title="Mois précédent"><i class="fas fa-chevron-left"></i></button>
                        <h1 id="month-year" class="month-year-display"></h1>
                        <button id="next-month" class="nav-btn" title="Mois suivant"><i class="fas fa-chevron-right"></i></button>
                        <button id="today-btn" class="today-btn">Aujourd'hui</button>
                    </div>
                    <button id="create-event-btn" class="create-btn"><i class="fas fa-plus"></i> Créer un Événement</button>
                </header>
                <div class="calendar-grid-wrapper">
                    <div class="calendar-grid">
                        <div class="day-header">Dim</div> <div class="day-header">Lun</div> <div class="day-header">Mar</div> <div class="day-header">Mer</div> <div class="day-header">Jeu</div> <div class="day-header">Ven</div> <div class="day-header">Sam</div>
                    </div>
                    <div id="calendar-days" class="calendar-grid"></div>
                </div>
            </main>
            <aside class="sidebar-pane">
                <h2 class="sidebar-header">Événements à venir</h2>
                <div id="event-list" class="event-list"></div>
            </aside>
        </div>
    </div>
    
    <div id="event-modal" class="modal">
        <div id="modal-backdrop" class="modal-backdrop"></div>
        <div class="modal-content">
            <form id="event-form" novalidate>
                <div class="modal-header">
                    <h3 id="eventModalLabel" class="modal-title">Nouvel événement</h3>
                    <button type="button" id="close-modal-btn" class="nav-btn">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="form-error-message" class="form-error"></div>
                    <div class="form-group"><label for="event-title">Titre</label><input type="text" id="event-title" name="title" class="form-input" required /></div>
                    <div class="form-group"><label>Début et Fin</label><input type="datetime-local" id="event-start" name="start_datetime" class="form-input" required/><input type="datetime-local" id="event-end" name="end_datetime" class="form-input" style="margin-top: 0.5rem;" required /></div>
                    <div class="form-group"><label for="event-description">Description</label><textarea id="event-description" name="description" rows="3" class="form-input"></textarea></div>
                    <div class="form-group"><label for="event-assigned-users">Assigner à</label><select class="form-input" id="event-assigned-users" name="assigned_users[]" multiple required>
                        <?php foreach ($usersList as $u): ?><option value="<?php echo htmlspecialchars($u['user_id']); ?>"><?php echo htmlspecialchars($u['prenom'] . ' ' . $u['nom']); ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="form-group"><label for="event-color">Couleur</label><input type="color" class="form-input" id="event-color" name="color" value="#4f46e5"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="delete-event-btn" class="today-btn btn-modal btn-modal-danger btn-modal-delete hidden">Supprimer</button>
                    <button type="button" id="cancel-modal-btn" class="today-btn btn-modal">Annuler</button>
                    <input type="hidden" id="event-id" name="event_id">
                    <button type="submit" id="save-event-btn" class="create-btn btn-modal">Enregistrer</button>
                    <button type="submit" id="update-event-btn" class="create-btn btn-modal hidden">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>

    <div id="loading-spinner" class="loader-wrapper hidden"><div class="loader"></div></div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- DOM Elements ---
        const modal = document.getElementById('event-modal');
        const form = document.getElementById('event-form');
        const loadingSpinner = document.getElementById('loading-spinner');
        const calendarDaysEl = document.getElementById('calendar-days');
        const eventListEl = document.getElementById('event-list');
        const monthYearEl = document.getElementById('month-year');
        
        let currentDate = new Date();
        let allEvents = [];

        const openModal = () => modal.classList.add('is-visible');
        const closeModal = () => modal.classList.remove('is-visible');
        
        [document.getElementById('close-modal-btn'), document.getElementById('cancel-modal-btn'), document.getElementById('modal-backdrop')]
            .forEach(trigger => trigger?.addEventListener('click', closeModal));

        const prepareForm = (mode = 'create', data = {}) => {
            form.reset();
            form.removeAttribute('data-action');
            document.getElementById('form-error-message').style.display = 'none';
            document.getElementById('event-id').value = '';

            const saveBtn = document.getElementById('save-event-btn');
            const updateBtn = document.getElementById('update-event-btn');
            const deleteBtn = document.getElementById('delete-event-btn');

            if (mode === 'create') {
                document.getElementById('eventModalLabel').textContent = 'Créer un nouvel événement';
                saveBtn.classList.remove('hidden'); updateBtn.classList.add('hidden'); deleteBtn.classList.add('hidden');
                form.dataset.action = 'create_event';
                const start = data.start || new Date();
                const end = data.end || new Date(start.getTime() + 60 * 60 * 1000);
                form.querySelector('#event-start').value = formatLocalDateTime(start);
                form.querySelector('#event-end').value = formatLocalDateTime(end);
                const selectUsers = form.querySelector('#event-assigned-users');
                for (let option of selectUsers.options) option.selected = true;
            } else {
                document.getElementById('eventModalLabel').textContent = 'Détails de l\'événement';
                saveBtn.classList.add('hidden'); updateBtn.classList.remove('hidden'); deleteBtn.classList.remove('hidden');
                form.dataset.action = 'update_event';
                form.querySelector('#event-id').value = data.id;
                form.querySelector('#event-title').value = data.title;
                form.querySelector('#event-description').value = data.extendedProps.description || '';
                form.querySelector('#event-color').value = data.color || '#4f46e5';
                form.querySelector('#event-start').value = formatLocalDateTime(data.start);
                form.querySelector('#event-end').value = formatLocalDateTime(data.end);
                const assignedIds = data.extendedProps.assigned_user_ids || [];
                const select = form.querySelector('#event-assigned-users');
                for (let option of select.options) { option.selected = assignedIds.includes(parseInt(option.value)); }
            }
            openModal();
        };

        const fetchEvents = (startDate, endDate) => {
            loadingSpinner.classList.remove('hidden');
            const url = `events_handler.php?action=get_events&start=${startDate.toISOString()}&end=${endDate.toISOString()}`;
            fetch(url)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    // ### CRITICAL BUG FIX ###
                    // Check if the server returned an error object instead of an array of events.
                    // This prevents the ".map is not a function" crash.
                    if (!Array.isArray(data)) {
                        console.error("Server returned an error:", data.message || 'Unknown error');
                        alert("An error occurred while loading events: " + (data.message || 'Please try again.'));
                        allEvents = []; // Clear events to avoid showing stale data
                    } else {
                        allEvents = data.map(evt => ({ ...evt, start: new Date(evt.start), end: new Date(evt.end) }));
                    }

                    renderCalendar();
                    renderSidebarEvents();
                })
                .catch(error => {
                    console.error('Error fetching events:', error)
                    alert('A critical error occurred. Please check the console and refresh the page.');
                })
                .finally(() => {
                    loadingSpinner.classList.add('hidden');
                });
        };
        
        const updateCalendarData = () => {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            const firstDay = new Date(year, month, 1);
            const calendarStart = new Date(new Date(firstDay).setDate(firstDay.getDate() - firstDay.getDay()));
            const calendarEnd = new Date(year, month + 2, 0);
            fetchEvents(calendarStart, calendarEnd);
        };

        const renderCalendar = () => {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            monthYearEl.textContent = `${currentDate.toLocaleString('fr-FR', { month: 'long' })} ${year}`;
            
            const firstDayOfMonth = new Date(year, month, 1).getDay();
            const lastDateOfMonth = new Date(year, month + 1, 0).getDate();
            const lastDateOfPrevMonth = new Date(year, month, 0).getDate();

            let calendarHtml = '';
            for (let i = firstDayOfMonth; i > 0; i--) { calendarHtml += `<div class="day-cell other-month"><div class="day-number"><span>${lastDateOfPrevMonth - i + 1}</span></div></div>`; }

            const today = new Date();
            for (let i = 1; i <= lastDateOfMonth; i++) {
                const dayDate = new Date(year, month, i);
                const dayString = dayDate.toISOString().split('T')[0];
                const dayEvents = allEvents.filter(e => e.start.toISOString().split('T')[0] === dayString);
                const isToday = dayString === today.toISOString().split('T')[0];
                
                calendarHtml += `<div class="day-cell" data-date="${dayString}">
                                <div class="day-number ${isToday ? 'is-today' : ''}"><span>${i}</span></div>
                                <div class="event-wrapper">`;
                dayEvents.forEach(event => { calendarHtml += `<div class="event-pill" data-event-id="${event.id}" style="background-color: ${event.color};" title="${event.title}">${event.title}</div>`; });
                calendarHtml += `</div></div>`;
            }
            
            const totalCells = firstDayOfMonth + lastDateOfMonth;
            const remainingCells = totalCells > 35 ? 42 - totalCells : 35 - totalCells;
            for (let i = 1; i <= remainingCells; i++) { calendarHtml += `<div class="day-cell other-month"><div class="day-number"><span>${i}</span></div></div>`; }
            calendarDaysEl.innerHTML = calendarHtml;
        };

        const renderSidebarEvents = () => {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const upcomingEvents = allEvents.filter(e => e.end >= today).sort((a, b) => a.start - b.start);

            let sidebarHtml = '';
            if (upcomingEvents.length === 0) {
                sidebarHtml = '<p>Aucun événement à venir.</p>';
            } else {
                upcomingEvents.forEach(event => {
                    const timeStr = `${event.start.toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'})} - ${event.end.toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'})}`;
                    sidebarHtml += `<div class="event-list-card"><p class="event-list-card-title">${event.title}</p><p class="event-list-card-date">${event.start.toLocaleDateString('fr-FR', { weekday: 'long', month: 'long', day: 'numeric' })} | ${timeStr}</p></div>`;
                });
            }
            eventListEl.innerHTML = sidebarHtml;
        };
        
        const handleFormSubmit = e => {
            e.preventDefault();
            const action = form.dataset.action; if (!action) return;
            const formError = document.getElementById('form-error-message');
            formError.style.display = 'none';
            loadingSpinner.classList.remove('hidden');
            const formData = new FormData(form);
            formData.append('action', action);

            fetch('events_handler.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') { closeModal(); updateCalendarData(); }
                    else { formError.textContent = data.message || 'Une erreur est survenue.'; formError.style.display = 'block'; }
                })
                .catch(err => { formError.textContent = 'Erreur de communication avec le serveur.'; formError.style.display = 'block'; })
                .finally(() => loadingSpinner.classList.add('hidden'));
        };

        // --- Event Listeners ---
        document.getElementById('prev-month').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); updateCalendarData(); });
        document.getElementById('next-month').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); updateCalendarData(); });
        document.getElementById('today-btn').addEventListener('click', () => { currentDate = new Date(); updateCalendarData(); });
        document.getElementById('create-event-btn').addEventListener('click', () => prepareForm('create'));

        calendarDaysEl.addEventListener('click', e => {
            const eventPill = e.target.closest('.event-pill');
            const dayCell = e.target.closest('.day-cell');
            if (eventPill) {
                e.stopPropagation();
                const eventId = eventPill.dataset.eventId;
                const eventData = allEvents.find(ev => ev.id == eventId);
                if (eventData) prepareForm('edit', eventData);
            } else if (dayCell && !dayCell.classList.contains('other-month')) {
                const dateStr = dayCell.dataset.date;
                if (dateStr) { const startDate = new Date(dateStr); startDate.setHours(new Date().getHours() + 1, 0, 0); prepareForm('create', { start: startDate }); }
            }
        });

        form.addEventListener('submit', handleFormSubmit);

        document.getElementById('delete-event-btn').addEventListener('click', () => {
            if (!confirm("Êtes-vous sûr de vouloir supprimer cet événement ?")) return;
            const eventId = form.querySelector('#event-id').value; if (!eventId) return;
            loadingSpinner.classList.remove('hidden');
            const formData = new FormData();
            formData.append('action', 'delete_event');
            formData.append('event_id', eventId);
            fetch('events_handler.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => { if (data.status === 'success') { closeModal(); updateCalendarData(); } else { alert('Erreur : ' + data.message); } })
                .finally(() => loadingSpinner.classList.add('hidden'));
        });
        
        const formatLocalDateTime = date => {
            if (!date) return ''; const d = new Date(date); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 16);
        };

        updateCalendarData();
    });
    </script>
</body>
</html>
