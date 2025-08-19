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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --primary-color: #2563eb;
            --primary-hover-color: #1d4ed8;
            --danger-color: #dc2626;
            --danger-hover-color: #b91c1c;
            --light-gray-color: #f8fafc;
            --border-color: #e2e8f0;
            --text-color-dark: #1f2937;
            --text-color-light: #6b7280;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
            color: var(--text-color-dark);
            margin: 0;
        }

        /* --- Main Layout & Container --- */
        .page-container {
            padding: 1.5rem;
        }
        .calendar-container {
            max-width: 80rem;
            margin: auto;
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        @media (min-width: 1024px) {
            .calendar-container {
                flex-direction: row;
            }
        }

        /* --- Calendar Section --- */
        .calendar-main {
            width: 100%;
            padding: 1.5rem;
        }
        @media (min-width: 1024px) {
            .calendar-main {
                width: 75%;
            }
        }

        .calendar-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .calendar-header .month-year {
            font-size: 1.875rem;
            font-weight: 700;
        }
        .calendar-header .nav-buttons {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .calendar-header .nav-buttons button, .btn-create-event {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            background-color: #f1f5f9;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .calendar-header .nav-buttons button:hover {
            background-color: var(--border-color);
        }
        .btn-create-event {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        .btn-create-event:hover {
            background-color: var(--primary-hover-color);
        }

        /* --- Calendar Grid --- */
        .calendar-grid-container {
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
        }
        .calendar-day-name {
            text-align: center;
            padding: 0.75rem 0;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-color-light);
            background-color: var(--light-gray-color);
            border-bottom: 1px solid var(--border-color);
        }
        .calendar-day {
            min-height: 7rem;
            padding: 0.5rem;
            border-right: 1px solid var(--border-color);
            border-top: 1px solid var(--border-color);
            cursor: pointer;
            transition: background-color 0.2s;
            background-color: white;
        }
        .calendar-day:hover {
            background-color: #f9fafb;
        }
        .calendar-day.other-month {
            background-color: var(--light-gray-color);
            color: #9ca3af;
        }
        .day-number {
            font-weight: 500;
            margin-bottom: 0.25rem;
            display: flex;
            justify-content: flex-end;
        }
        .day-number span {
             width: 1.75rem;
             height: 1.75rem;
             display: flex;
             align-items: center;
             justify-content: center;
             border-radius: 9999px;
        }
        .day-number.today span {
            background-color: var(--primary-color);
            color: white;
        }
        .event-bubbles {
            font-size: 0.75rem;
            space-y: 0.25rem;
        }
        .event-bubble {
            color: white;
            padding: 0.1rem 0.25rem;
            border-radius: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 4px;
        }
        
        /* --- Sidebar --- */
        .sidebar {
            width: 100%;
            padding: 1.5rem;
            background-color: var(--light-gray-color);
        }
        @media (min-width: 1024px) {
            .sidebar {
                width: 25%;
                border-left: 1px solid var(--border-color);
            }
        }
        .sidebar h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-top:0;
            margin-bottom: 1rem;
        }
        .event-list {
            max-height: 60vh;
            overflow-y: auto;
        }
        .event-card {
            background-color: white;
            border: 1px solid var(--border-color);
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 0.75rem;
        }
        .event-card .title { font-weight: 600; }
        .event-card .date { font-size: 0.875rem; color: var(--text-color-light); }
        .event-card .time { font-size: 0.75rem; color: #9ca3af; }

        /* --- Modal --- */
        .modal {
            position: fixed;
            inset: 0;
            z-index: 50;
            overflow-y: auto;
        }
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background-color: rgba(15, 23, 42, 0.5);
        }
        .modal-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }
        .modal-content {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            width: 100%;
            max-width: 32rem;
            margin: 2rem 0;
            text-align: left;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 1.5rem;
        }
        .modal-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }
        .modal-body {
            padding: 0 1.5rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .modal-footer {
            background-color: var(--light-gray-color);
            padding: 1rem 1.5rem;
            display: flex;
            flex-direction: column-reverse;
            gap: 0.75rem;
        }
        @media (min-width: 640px) {
            .modal-footer {
               flex-direction: row;
               justify-content: flex-end;
            }
        }
        
        /* --- Form & Buttons --- */
        .form-input {
            width: 100%;
            padding: 0.65rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
        }
        select[multiple] { height: 100px; }
        .form-error {
            display: none;
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }
        .btn {
            width: 100%;
            padding: 0.6rem 1rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        @media (min-width: 640px) {
            .btn { width: auto; }
        }
        .btn-primary { background-color: var(--primary-color); color: white; }
        .btn-primary:hover { background-color: var(--primary-hover-color); }
        .btn-danger { background-color: var(--danger-color); color: white; }
        .btn-danger:hover { background-color: var(--danger-hover-color); }
        .btn-secondary { background-color: #e5e7eb; color: var(--text-color-dark); }
        .btn-secondary:hover { background-color: #d1d5db; }
        .delete-btn { margin-right: auto; }

        /* --- Utilities --- */
        .hidden { display: none; }
        .loader-container {
            position: fixed;
            inset: 0;
            background-color: rgba(255, 255, 255, 0.75);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }
        .loader {
            width: 3rem; height: 3rem;
            border: 4px solid var(--primary-color);
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="page-container">
        <div class="calendar-container">

            <main class="calendar-main">
                <header class="calendar-header">
                    <div class="nav-buttons">
                        <button id="prev-month" title="Mois précédent"><i class="fas fa-chevron-left"></i></button>
                        <h1 id="month-year" class="month-year"></h1>
                        <button id="next-month" title="Mois suivant"><i class="fas fa-chevron-right"></i></button>
                        <button id="today-btn">Aujourd'hui</button>
                    </div>
                     <button id="create-event-btn" class="btn-create-event">
                        <i class="fas fa-plus"></i> Créer
                    </button>
                </header>
                
                <div class="calendar-grid-container">
                    <div class="calendar-grid">
                        <div class="calendar-day-name">Dim</div>
                        <div class="calendar-day-name">Lun</div>
                        <div class="calendar-day-name">Mar</div>
                        <div class="calendar-day-name">Mer</div>
                        <div class="calendar-day-name">Jeu</div>
                        <div class="calendar-day-name">Ven</div>
                        <div class="calendar-day-name">Sam</div>
                    </div>
                    <div id="calendar-days" class="calendar-grid"></div>
                </div>
            </main>

            <aside class="sidebar">
                <h2>Événements à venir</h2>
                <div id="event-list" class="event-list"></div>
            </aside>
        </div>
    </div>
    
    <div id="event-modal" class="modal hidden">
        <div class="modal-backdrop"></div>
        <div class="modal-container">
            <div class="modal-content">
                <form id="event-form" novalidate>
                    <div class="modal-header">
                        <h3 id="eventModalLabel">Nouvel événement</h3>
                        <button type="button" id="close-modal-btn"><i data-lucide="x"></i></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="event-id" name="event_id">
                        <div id="form-error-message" class="form-error"></div>
                        
                        <input type="text" id="event-title" name="title" placeholder="Ajouter un titre" class="form-input" required />
                        
                        <div>
                            <input type="datetime-local" id="event-start" name="start_datetime" class="form-input" required/>
                            <input type="datetime-local" id="event-end" name="end_datetime" class="form-input" style="margin-top: 0.5rem;" required />
                        </div>
                        
                        <textarea id="event-description" name="description" placeholder="Ajouter une description..." rows="4" class="form-input"></textarea>
                        
                        <div>
                            <label for="event-assigned-users">Assigner à</label>
                            <select class="form-input" id="event-assigned-users" name="assigned_users[]" multiple required>
                                <?php foreach ($usersList as $u): ?>
                                    <option value="<?php echo htmlspecialchars($u['user_id']); ?>">
                                        <?php echo htmlspecialchars($u['prenom'] . ' ' . $u['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="event-color">Couleur</label>
                            <input type="color" class="form-input" id="event-color" name="color" value="#2563eb">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" id="delete-event-btn" class="btn btn-danger delete-btn hidden">Supprimer</button>
                        <button type="button" id="cancel-modal-btn" class="btn btn-secondary">Annuler</button>
                        <button type="submit" id="save-event-btn" class="btn btn-primary">Enregistrer</button>
                        <button type="submit" id="update-event-btn" class="btn btn-primary hidden">Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="loading-spinner" class="loader-container hidden"><div class="loader"></div></div>

    <script>
    // The JavaScript logic remains identical to the previous version.
    // It manipulates the DOM elements and classes defined in the new custom stylesheet.
    document.addEventListener('DOMContentLoaded', function() {
        // --- ELEMENTS ---
        const calendarDaysEl = document.getElementById('calendar-days');
        const monthYearEl = document.getElementById('month-year');
        const eventListEl = document.getElementById('event-list');
        const loadingSpinner = document.getElementById('loading-spinner');
        const modal = document.getElementById('event-modal');
        const form = document.getElementById('event-form');
        const formError = document.getElementById('form-error-message');
        
        // --- BUTTONS ---
        const prevMonthBtn = document.getElementById('prev-month');
        const nextMonthBtn = document.getElementById('next-month');
        const todayBtn = document.getElementById('today-btn');
        const createEventBtn = document.getElementById('create-event-btn');
        const saveBtn = document.getElementById('save-event-btn');
        const updateBtn = document.getElementById('update-event-btn');
        const deleteBtn = document.getElementById('delete-event-btn');

        // --- STATE ---
        let currentDate = new Date();
        let allEvents = [];

        // --- MODAL MANAGEMENT ---
        const openModal = () => modal.classList.remove('hidden');
        const closeModal = () => modal.classList.add('hidden');
        
        [document.getElementById('close-modal-btn'), document.getElementById('cancel-modal-btn'), document.getElementById('modal-backdrop')].forEach(el => el.addEventListener('click', closeModal));

        function formatLocalDateTime(date) {
            if (!date) return '';
            const d = new Date(date);
            d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
            return d.toISOString().slice(0, 16);
        }

        function prepareForm(mode = 'create', data = {}) {
            form.reset();
            form.removeAttribute('data-action');
            formError.style.display = 'none';
            form.querySelector('#event-id').value = '';

            if (mode === 'create') {
                document.getElementById('eventModalLabel').textContent = 'Créer un nouvel événement';
                saveBtn.classList.remove('hidden');
                updateBtn.classList.add('hidden');
                deleteBtn.classList.add('hidden');
                form.dataset.action = 'create_event';

                const start = data.start || new Date();
                const end = data.end || new Date(start.getTime() + 60 * 60 * 1000);
                form.querySelector('#event-start').value = formatLocalDateTime(start);
                form.querySelector('#event-end').value = formatLocalDateTime(end);
                
                const selectUsers = form.querySelector('#event-assigned-users');
                for (let option of selectUsers.options) option.selected = true;

            } else { // Edit mode
                document.getElementById('eventModalLabel').textContent = 'Détails de l\'événement';
                saveBtn.classList.add('hidden');
                updateBtn.classList.remove('hidden');
                deleteBtn.classList.remove('hidden');
                form.dataset.action = 'update_event';

                form.querySelector('#event-id').value = data.id;
                form.querySelector('#event-title').value = data.title;
                form.querySelector('#event-description').value = data.extendedProps.description || '';
                form.querySelector('#event-color').value = data.color || '#2563eb';
                form.querySelector('#event-start').value = formatLocalDateTime(data.start);
                form.querySelector('#event-end').value = formatLocalDateTime(data.end);

                const assignedIds = data.extendedProps.assigned_user_ids || [];
                const select = form.querySelector('#event-assigned-users');
                for (let option of select.options) {
                    option.selected = assignedIds.includes(parseInt(option.value));
                }
            }
            openModal();
        }

        // --- DATA FETCHING ---
        const fetchEvents = (startDate, endDate) => {
            loadingSpinner.classList.remove('hidden');
            const url = `events_handler.php?action=get_events&start=${startDate.toISOString()}&end=${endDate.toISOString()}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    allEvents = data.map(evt => ({ ...evt, start: new Date(evt.start), end: new Date(evt.end) }));
                    renderCalendar();
                    renderSidebarEvents();
                })
                .catch(error => console.error('Error fetching events:', error))
                .finally(() => loadingSpinner.classList.add('hidden'));
        };
        
        const updateCalendarData = () => {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            const firstDay = new Date(year, month, 1);
            const calendarStart = new Date(new Date(firstDay).setDate(firstDay.getDate() - firstDay.getDay()));
            const calendarEnd = new Date(year, month + 2, 0);
            fetchEvents(calendarStart, calendarEnd);
        };

        // --- UI RENDERING ---
        const renderCalendar = () => {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            monthYearEl.textContent = `${currentDate.toLocaleString('fr-FR', { month: 'long' })} ${year}`;
            
            const firstDayOfMonth = new Date(year, month, 1).getDay();
            const lastDateOfMonth = new Date(year, month + 1, 0).getDate();
            const lastDateOfPrevMonth = new Date(year, month, 0).getDate();

            calendarDaysEl.innerHTML = '';
            // Previous month's days
            for (let i = firstDayOfMonth; i > 0; i--) {
                calendarDaysEl.innerHTML += `<div class="calendar-day other-month"><div class="day-number"><span>${lastDateOfPrevMonth - i + 1}</span></div></div>`;
            }

            // Current month's days
            const today = new Date();
            for (let i = 1; i <= lastDateOfMonth; i++) {
                const dayDate = new Date(year, month, i);
                const dayString = dayDate.toISOString().split('T')[0];
                const dayEvents = allEvents.filter(e => e.start.toISOString().split('T')[0] === dayString);
                const isToday = dayString === today.toISOString().split('T')[0];
                
                let dayHtml = `<div class="calendar-day" data-date="${dayString}">
                                <div class="day-number ${isToday ? 'today' : ''}"><span>${i}</span></div>
                                <div class="event-bubbles">`;

                dayEvents.forEach(event => {
                    dayHtml += `<div class="event-bubble" data-event-id="${event.id}" style="background-color: ${event.color};" title="${event.title}">${event.title}</div>`;
                });

                dayHtml += `</div></div>`;
                calendarDaysEl.innerHTML += dayHtml;
            }
            // Next month's days
            const totalCells = firstDayOfMonth + lastDateOfMonth;
            const remainingCells = totalCells > 35 ? 42 - totalCells : 35 - totalCells;
            for (let i = 1; i <= remainingCells; i++) {
                calendarDaysEl.innerHTML += `<div class="calendar-day other-month"><div class="day-number"><span>${i}</span></div></div>`;
            }
        };

        const renderSidebarEvents = () => {
            eventListEl.innerHTML = '';
            const today = new Date();
            today.setHours(0,0,0,0); 
            const upcomingEvents = allEvents.filter(e => e.start >= today).sort((a, b) => a.start - b.start);

            if (upcomingEvents.length === 0) {
                eventListEl.innerHTML = '<p>Aucun événement à venir.</p>'; return;
            }
            upcomingEvents.forEach(event => {
                const timeStr = `${event.start.toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'})} - ${event.end.toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'})}`;
                eventListEl.innerHTML += `<div class="event-card">
                    <p class="title">${event.title}</p>
                    <p class="date">${event.start.toLocaleDateString('fr-FR', { weekday: 'long', month: 'long', day: 'numeric' })}</p>
                    <p class="time">${timeStr}</p></div>`;
            });
        };
        
        // --- FORM SUBMISSION ---
        function handleFormSubmit(e) {
            e.preventDefault();
            const action = form.dataset.action;
            if (!action) return;

            formError.style.display = 'none';
            loadingSpinner.classList.remove('hidden');
            
            const formData = new FormData(form);
            formData.append('action', action);

            fetch('events_handler.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        closeModal();
                        updateCalendarData(); // Refresh calendar
                    } else {
                        formError.textContent = data.message || 'Une erreur est survenue.';
                        formError.style.display = 'block';
                    }
                })
                .catch(err => {
                    formError.textContent = 'Erreur de communication avec le serveur.';
                    formError.style.display = 'block';
                })
                .finally(() => loadingSpinner.classList.add('hidden'));
        }

        // --- EVENT LISTENERS ---
        prevMonthBtn.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); updateCalendarData(); });
        nextMonthBtn.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); updateCalendarData(); });
        todayBtn.addEventListener('click', () => { currentDate = new Date(); updateCalendarData(); });
        createEventBtn.addEventListener('click', () => prepareForm('create'));

        calendarDaysEl.addEventListener('click', e => {
            const dayCell = e.target.closest('.calendar-day');
            const eventBubble = e.target.closest('.event-bubble');

            if (eventBubble) {
                e.stopPropagation(); // Prevent day click from firing
                const eventId = eventBubble.dataset.eventId;
                const eventData = allEvents.find(ev => ev.id == eventId);
                if (eventData) prepareForm('edit', eventData);
            } else if (dayCell && !dayCell.classList.contains('other-month')) {
                const dateStr = dayCell.dataset.date;
                if (dateStr) {
                    const startDate = new Date(dateStr);
                    startDate.setHours(new Date().getHours() + 1, 0, 0);
                    prepareForm('create', { start: startDate });
                }
            }
        });

        form.addEventListener('submit', handleFormSubmit);

        deleteBtn.addEventListener('click', () => {
            if (!confirm("Êtes-vous sûr de vouloir supprimer cet événement ?")) return;
            
            const eventId = form.querySelector('#event-id').value;
            if (!eventId) return;

            loadingSpinner.classList.remove('hidden');
            const formData = new FormData();
            formData.append('action', 'delete_event');
            formData.append('event_id', eventId);

            fetch('events_handler.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        closeModal();
                        updateCalendarData();
                    } else {
                        alert('Erreur lors de la suppression : ' + data.message);
                    }
                }).finally(() => loadingSpinner.classList.add('hidden'));
        });

        // --- INITIALIZATION ---
        updateCalendarData();
        lucide.createIcons();
    });
    </script>
</body>
</html>
