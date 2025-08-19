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
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js'></script>
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --danger-color: #dc2626;
            --danger-hover: #b91c1c;
            --light-gray: #f8fafc;
            --border-color: #e2e8f0;
            --text-dark: #111827;
            --text-light: #374151;
            --white: #ffffff;
            --fc-border-color: #e2e8f0;
            --fc-daygrid-day-number-color: #374151;
            --fc-today-bg-color: rgba(37, 99, 235, 0.05);
        }

        body {
            background-color: var(--light-gray);
            font-family: sans-serif;
            margin: 0;
        }

        .container {
            padding: 1.5rem;
        }

        #calendar-container {
            background-color: var(--white);
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        /* FullCalendar Customizations */
        .fc .fc-toolbar.fc-header-toolbar {
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .fc .fc-toolbar-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .fc .fc-button {
            background-color: var(--white);
            color: var(--text-light);
            border: 1px solid var(--border-color);
            transition: all 0.2s ease-in-out;
        }

        .fc .fc-button:hover {
            background-color: #f1f5f9;
        }

        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .fc-createEvent-button {
            background-color: var(--primary-color) !important;
            color: white !important;
            border-color: var(--primary-color) !important;
            font-weight: 500 !important;
        }
        .fc-createEvent-button:hover {
            background-color: var(--primary-hover) !important;
            border-color: var(--primary-hover) !important;
        }


        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: var(--white);
            margin: 5% auto;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .close-button {
            cursor: pointer;
            font-size: 1.5rem;
            color: #9ca3af;
        }
        .close-button:hover {
            color: var(--text-dark);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-light);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.65rem 1rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: var(--white);
        }
        .btn-danger:hover {
            background-color: var(--danger-hover);
        }

        .btn-secondary {
            background-color: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
        }
        .btn-secondary:hover {
            background-color: #e2e8f0;
        }

        #form-error-message {
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            display: none;
        }

        #loading-spinner {
            display: none;
            position: fixed;
            inset: 0;
            background-color: rgba(255, 255, 255, 0.75);
            align-items: center;
            justify-content: center;
            z-index: 100;
        }
        .spinner {
            width: 3rem;
            height: 3rem;
            border: 4px solid var(--primary-color);
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
        
        #event-list-view {
            display: none;
        }
        
        @media (max-width: 767px) {
            #calendar {
                display: none;
            }
            #event-list-view {
                display: block;
            }
            .modal-content {
                margin: 10% auto;
            }
        }


    </style>
</head>
<body>

    <div id="calendar-app">
        <?php include 'navbar.php'; ?>

        <div class="container">
            <div id="calendar-container">
                <div id='calendar'></div>
                <div id='event-list-view'></div>
            </div>
        </div>

        <div id="event-modal" class="modal">
            <div class="modal-content">
                <form id="event-form">
                    <div class="modal-header">
                        <h3 id="eventModalLabel" class="modal-title">Nouvel événement</h3>
                        <span id="close-modal-btn" class="close-button">&times;</span>
                    </div>
                    
                    <div id="form-error-message"></div>

                    <input type="hidden" id="event-id" name="event_id">

                    <div class="form-group">
                        <label for="event-title" class="form-label">Titre</label>
                        <input type="text" id="event-title" name="title" class="form-input" required />
                    </div>
                   
                    <div class="form-group">
                        <label for="event-start" class="form-label">Début</label>
                        <input type="datetime-local" id="event-start" name="start_datetime" class="form-input" required/>
                    </div>

                    <div class="form-group">
                        <label for="event-end" class="form-label">Fin</label>
                        <input type="datetime-local" id="event-end" name="end_datetime" class="form-input" required />
                    </div>
                   
                    <div class="form-group">
                        <label for="event-description" class="form-label">Description</label>
                        <textarea id="event-description" name="description" rows="4" class="form-input"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="event-assigned-users" class="form-label">Assigner à</label>
                        <select class="form-input" id="event-assigned-users" name="assigned_users[]" multiple>
                            <?php foreach ($usersList as $u): ?>
                                <option value="<?php echo $u['user_id']; ?>">
                                    <?php echo htmlspecialchars($u['prenom'] . ' ' . $u['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="event-color" class="form-label">Couleur de l'événement</label>
                        <input type="color" class="form-input" id="event-color" name="color" value="#2563eb">
                    </div>

                    <div class="modal-footer">
                        <button type="button" id="delete-event-btn" class="btn btn-danger" style="display:none; margin-right: auto;">Supprimer</button>
                        <button type="button" id="cancel-modal-btn" class="btn btn-secondary">Annuler</button>
                        <button type="submit" id="save-event-btn" class="btn btn-primary">Enregistrer</button>
                        <button type="button" id="update-event-btn" class="btn btn-primary" style="display:none;">Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div id="loading-spinner">
        <div class="spinner"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            const eventListViewEl = document.getElementById('event-list-view');
            const eventModal = document.getElementById('event-modal');
            const eventForm = document.getElementById('event-form');
            const modalTitle = document.getElementById('eventModalLabel');
            const formErrorMessage = document.getElementById('form-error-message');
            const loadingSpinner = document.getElementById('loading-spinner');
            
            const saveButton = document.getElementById('save-event-btn');
            const updateButton = document.getElementById('update-event-btn');
            const deleteButton = document.getElementById('delete-event-btn');

            const openModal = () => eventModal.style.display = 'block';
            const closeModal = () => eventModal.style.display = 'none';

            [document.getElementById('close-modal-btn'), document.getElementById('cancel-modal-btn')].forEach(el => {
                el.addEventListener('click', closeModal);
            });
            
            function formatLocalDateTimeInput(date) {
                if (!date) return '';
                const localDate = new Date(date);
                localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());
                return localDate.toISOString().slice(0, 16);
            }

            function resetAndPrepareForm(mode = 'create', startDate = null, endDate = null) {
                eventForm.reset();
                formErrorMessage.style.display = 'none';
                formErrorMessage.textContent = '';
                document.getElementById('event-color').value = '#2563eb';
                document.getElementById('event-id').value = '';

                if (mode === 'create') {
                    modalTitle.textContent = 'Créer un nouvel événement';
                    saveButton.style.display = 'inline-flex';
                    updateButton.style.display = 'none';
                    deleteButton.style.display = 'none';

                    document.getElementById('event-start').value = startDate ? formatLocalDateTimeInput(startDate) : '';
                    const defaultEndDate = endDate ? endDate : (startDate ? new Date(startDate.getTime() + 60 * 60 * 1000) : null);
                    document.getElementById('event-end').value = defaultEndDate ? formatLocalDateTimeInput(defaultEndDate) : '';

                    const selectUsers = document.getElementById('event-assigned-users');
                    for (let i = 0; i < selectUsers.options.length; i++) {
                        selectUsers.options[i].selected = false;
                    }

                } else { // view/edit mode
                    modalTitle.textContent = 'Détails de l\'événement';
                    saveButton.style.display = 'none';
                    updateButton.style.display = 'inline-flex';
                    deleteButton.style.display = 'inline-flex';
                }
            }
            
            const isMobile = window.innerWidth <= 767;

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'createEventButton dayGridMonth,listWeek'
                },
                customButtons: {
                    createEventButton: {
                        text: 'Créer un événement',
                        click: function() {
                            resetAndPrepareForm('create', new Date());
                            openModal();
                        },
                        'class': 'fc-createEvent-button' 
                    }
                },
                locale: 'fr',
                buttonText: {
                    today: "Aujourd'hui",
                    month: 'Mois',
                    list:  'Liste'
                },
                navLinks: true,
                editable: true,
                selectable: true,
                dayMaxEvents: true, 
                events: {
                    url: 'events_handler.php?action=get_events',
                    failure: function() {
                        alert('Erreur lors du chargement des événements.');
                    }
                },
                loading: function(isLoading) {
                    loadingSpinner.style.display = isLoading ? 'flex' : 'none';
                },
                select: function(info) {
                    resetAndPrepareForm('create', info.start, info.end);
                    openModal();
                },
                eventClick: function(info) {
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
                },
                eventDrop: function(info) {
                   // Handle event drop if needed
                },
                 eventDidMount: function(info) {
                    if (isMobile) {
                        const eventEl = document.createElement('div');
                        eventEl.style.padding = '1rem';
                        eventEl.style.borderBottom = '1px solid var(--border-color)';
                        eventEl.innerHTML = `
                            <h3 style="font-weight: bold;">${info.event.title}</h3>
                            <p>${info.event.start.toLocaleString()} - ${info.event.end.toLocaleString()}</p>
                        `;
                        eventListViewEl.appendChild(eventEl);
                    }
                }
            });

            calendar.render();

            function showFormError(message) {
                formErrorMessage.textContent = message;
                formErrorMessage.style.display = 'block';
            }

            eventForm.addEventListener('submit', function(e) {
                e.preventDefault();
                handleFormSubmit('create_event');
            });
            updateButton.addEventListener('click', function(e) {
                e.preventDefault();
                handleFormSubmit('update_event');
            });
             deleteButton.addEventListener('click', function(e) {
                e.preventDefault();
                if(confirm("Are you sure you want to delete this event?")) {
                  handleFormSubmit('delete_event');
                }
            });

            function handleFormSubmit(action) {
                formErrorMessage.style.display = 'none';
                const formData = new FormData(eventForm);
                const startDt = new Date(formData.get('start_datetime'));
                const endDt = new Date(formData.get('end_datetime'));

                if (!formData.get('title') || !formData.get('start_datetime') || !formData.get('end_datetime')) {
                    showFormError("Veuillez remplir le titre et les dates de début et de fin.");
                    return;
                }
                if (endDt <= startDt) {
                    showFormError("La date de fin doit être postérieure à la date de début.");
                    return;
                }
                
                formData.append('action', action);
                loadingSpinner.style.display = 'flex';

                fetch('events_handler.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        closeModal();
                        calendar.refetchEvents();
                    } else {
                        showFormError(data.message || 'Une erreur est survenue.');
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    showFormError('Erreur de communication avec le serveur.');
                })
                .finally(() => {
                    loadingSpinner.style.display = 'none';
                });

            }

        });
    </script>

</body>
</html>
