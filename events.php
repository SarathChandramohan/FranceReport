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
            --fc-button-bg-color: #ffffff;
            --fc-button-text-color: #374151;
            --fc-button-border-color: #e2e8f0;
            --fc-button-hover-bg-color: #f1f5f9;
            --fc-button-active-bg-color: #e2e8f0;
            --fc-button-active-border-color: #d1d5db;
        }

        body {
            background-color: #f8fafc;
        }
        .fc .fc-toolbar.fc-header-toolbar {
            margin-bottom: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        @media (min-width: 768px) {
            .fc .fc-toolbar.fc-header-toolbar {
                flex-direction: row;
                align-items: center;
            }
            #event-list-view {
                display: none;
            }
        }
         @media (max-width: 767px) {
            #calendar {
                display: none;
            }
        }


        .fc .fc-toolbar-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #111827;
        }
        .fc .fc-button {
            transition: all 0.2s ease-in-out;
        }
        .fc .fc-daygrid-day.fc-day-today {
            background-color: var(--fc-today-bg-color);
        }
         .fc-createEvent-button { /* Custom class for the new button */
            background-color: #2563eb !important;
            color: white !important;
            border-color: #2563eb !important;
            font-weight: 500 !important;
        }
        .fc-createEvent-button:hover {
            background-color: #1d4ed8 !important;
            border-color: #1d4ed8 !important;
        }


        /* Modal Styles */
        .modal-backdrop {
            transition: opacity 0.3s ease;
        }
        .modal-content {
            transition: transform 0.3s ease;
        }
        .hidden {
            display: none;
        }
        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
        }
        .btn {
            padding: 0.65rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-primary {
            background-color: #2563eb;
            color: white;
        }
        .btn-primary:hover {
            background-color: #1d4ed8;
        }
        .btn-danger {
            background-color: #dc2626;
            color: white;
        }
        .btn-danger:hover {
            background-color: #b91c1c;
        }
        .btn-secondary {
            background-color: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
        }
        .btn-secondary:hover {
            background-color: #e2e8f0;
        }
    </style>
</head>
<body class="font-sans">

    <div id="calendar-app" class="bg-gray-50 min-h-screen">
        <?php include 'navbar.php'; // Include the navigation bar ?>

        <div class="p-4 sm:p-6 lg:p-8">
            <main class="bg-white p-4 sm:p-6 rounded-lg shadow-sm">
                <div id='calendar'></div>
                <div id='event-list-view'></div>
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
                                <button type="button" id="close-modal-btn" class="p-1 rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                                    <i data-lucide="x" class="w-5 h-5"></i>
                                </button>
                            </div>
                            
                            <div class="mt-6 space-y-5">
                                <input type="hidden" id="event-id" name="event_id">

                                <div id="form-error-message" class="hidden bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-lg text-sm" role="alert"></div>

                                <div>
                                    <input type="text" id="event-title" name="title" placeholder="Ajouter un titre" class="form-input" required />
                                </div>
                               
                                <div class="space-y-4">
                                    <div class="flex items-center space-x-3">
                                        <i data-lucide="clock-4" class="w-5 h-5 text-gray-500"></i>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 flex-1">
                                            <input type="datetime-local" id="event-start" name="start_datetime" class="form-input text-sm" required/>
                                            <input type="datetime-local" id="event-end" name="end_datetime" class="form-input text-sm" required />
                                        </div>
                                    </div>
                                </div>
                               
                                <div>
                                    <textarea id="event-description" name="description" placeholder="Ajouter une description..." rows="4" class="form-input resize-none"></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="event-assigned-users" class="block text-sm font-medium text-gray-700 mb-1">Assigner à</label>
                                    <select class="form-input" id="event-assigned-users" name="assigned_users[]" multiple>
                                        <?php foreach ($usersList as $u): ?>
                                            <option value="<?php echo $u['user_id']; ?>">
                                                <?php echo htmlspecialchars($u['prenom'] . ' ' . $u['nom']); ?>
                                            </option>
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

            const openModal = () => {
                eventModal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
            const closeModal = () => {
                eventModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            };

            [document.getElementById('close-modal-btn'), document.getElementById('cancel-modal-btn'), document.getElementById('modal-backdrop')].forEach(el => {
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
                formErrorMessage.classList.add('hidden');
                formErrorMessage.textContent = '';
                document.getElementById('event-color').value = '#2563eb';
                document.getElementById('event-id').value = '';

                if (mode === 'create') {
                    modalTitle.textContent = 'Créer un nouvel événement';
                    saveButton.classList.remove('hidden');
                    updateButton.classList.add('hidden');
                    deleteButton.classList.add('hidden');

                    document.getElementById('event-start').value = startDate ? formatLocalDateTimeInput(startDate) : '';
                    const defaultEndDate = endDate ? endDate : (startDate ? new Date(startDate.getTime() + 60 * 60 * 1000) : null);
                    document.getElementById('event-end').value = defaultEndDate ? formatLocalDateTimeInput(defaultEndDate) : '';

                    const selectUsers = document.getElementById('event-assigned-users');
                    for (let i = 0; i < selectUsers.options.length; i++) {
                        selectUsers.options[i].selected = false;
                    }

                } else { // view/edit mode
                    modalTitle.textContent = 'Détails de l\'événement';
                    saveButton.classList.add('hidden');
                    updateButton.classList.remove('hidden');
                    deleteButton.classList.remove('hidden');
                }
            }
            
            const isMobile = window.innerWidth <= 768;

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
                   
                },
                 eventDidMount: function(info) {
                    if (isMobile) {
                        const eventEl = document.createElement('div');
                        eventEl.innerHTML = `
                            <div class="p-4 border-b">
                                <h3 class="font-bold">${info.event.title}</h3>
                                <p>${info.event.start.toLocaleString()} - ${info.event.end.toLocaleString()}</p>
                            </div>
                        `;
                        eventListViewEl.appendChild(eventEl);
                    }
                }
            });

            calendar.render();
            lucide.createIcons();

            function showFormError(message) {
                formErrorMessage.textContent = message;
                formErrorMessage.classList.remove('hidden');
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
                 formErrorMessage.classList.add('hidden');
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
