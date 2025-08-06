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
        /* Simple style to handle hidden elements */
        .hidden {
            display: none;
        }
        /* Custom style for line-clamping text */
        .line-clamp-2 {
            overflow: hidden;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }
    </style>
</head>
<body class="font-sans">

    <div id="calendar-app" class="h-screen bg-white flex flex-col">
        <?php include 'navbar.php'; // Include the navigation bar ?>

        <div class="flex flex-1 overflow-hidden">
            <aside class="w-64 bg-white border-r border-gray-200 flex flex-col">
                <div class="p-4">
                    <button id="create-event-btn-sidebar" class="w-full bg-white border border-gray-300 rounded-full py-3 px-6 hover:shadow-md transition-shadow flex items-center space-x-3">
                        <i data-lucide="plus" class="w-5 h-5 text-gray-600"></i>
                        <span class="text-gray-700 font-medium">Créer</span>
                    </button>
                </div>
                <div class="flex-1 p-4">
                    <div class="space-y-2">
                        <div class="flex items-center space-x-3 p-2 rounded hover:bg-gray-100 cursor-pointer">
                            <div class="w-3 h-3 bg-blue-600 rounded-full"></div>
                            <span class="text-sm text-gray-700">Mes calendriers</span>
                        </div>
                        <div class="flex items-center space-x-3 p-2 rounded hover:bg-gray-100 cursor-pointer">
                            <div class="w-3 h-3 bg-green-600 rounded-full"></div>
                            <span class="text-sm text-gray-700">Travail</span>
                        </div>
                        <div class="flex items-center space-x-3 p-2 rounded hover:bg-gray-100 cursor-pointer">
                            <div class="w-3 h-3 bg-red-600 rounded-full"></div>
                            <span class="text-sm text-gray-700">Personnel</span>
                        </div>
                    </div>
                </div>
            </aside>

            <main class="flex-1 flex flex-col">
                <div id='calendar' class="flex-1 bg-white p-4">
                    </div>
            </main>
        </div>

        <div id="event-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-96 max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 id="eventModalLabel" class="text-lg font-medium text-gray-900">Nouvel événement</h3>
                        <button id="close-modal-btn" class="p-1 hover:bg-gray-100 rounded">
                            <i data-lucide="x" class="w-5 h-5 text-gray-500"></i>
                        </button>
                    </div>
                    <form id="event-form" novalidate>
                        <div class="space-y-4">
                            <input type="hidden" id="event-id" name="event_id">

                            <div id="form-error-message" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                              <strong class="font-bold">Erreur!</strong>
                              <span class="block sm:inline"></span>
                            </div>

                            <div>
                                <input type="text" id="event-title" name="title" value="" placeholder="Ajouter un titre" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required />
                            </div>
                           
                            <div class="space-y-3">
                                <div class="flex items-center space-x-3">
                                    <i data-lucide="clock" class="w-5 h-5 text-gray-400"></i>
                                    <span class="text-sm font-medium text-gray-700">Début</span>
                                </div>
                                <div class="flex space-x-2 ml-8">
                                    <input type="datetime-local" id="event-start" name="start_datetime" class="p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent" required/>
                                </div>
                                <div class="flex items-center space-x-3 ml-8">
                                    <span class="text-sm font-medium text-gray-700">Fin</span>
                                </div>
                                <div class="flex space-x-2 ml-8">
                                    <input type="datetime-local" id="event-end" name="end_datetime" class="p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent" required />
                                </div>
                            </div>
                           
                            <div>
                                <textarea id="event-description" name="description" placeholder="Ajouter une description" rows="3" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"></textarea>
                            </div>
                             <div class="form-group">
                                <label for="event-assigned-users">Assigner à <span class="text-danger">*</span></label>
                                <select class="form-control" id="event-assigned-users" name="assigned_users[]" multiple required>
                                    <option value="" disabled>-- Sélectionner --</option> <?php foreach ($usersList as $u): ?>
                                        <option value="<?php echo $u['user_id']; ?>">
                                            <?php echo htmlspecialchars($u['prenom'] . ' ' . $u['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                 <small class="form-text text-muted">Maintenez Ctrl (ou Cmd) pour sélectionner plusieurs.</small>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Couleur</label>
                                <div class="flex space-x-2">
                                    <input type="color" class="form-control" id="event-color" name="color" value="#007bff">
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-3 mt-6">
                            <button type="button" id="update-event-btn" class="hidden px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Mettre à jour</button>
                            <button type="button" id="delete-event-btn" class="hidden px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Supprimer</button>
                            <button type="button" id="cancel-modal-btn" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">Annuler</button>
                            <button type="submit" id="save-event-btn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div id="loading-spinner" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Chargement...</span>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            const eventModal = document.getElementById('event-modal');
            const eventForm = document.getElementById('event-form');
            const modalTitle = document.getElementById('eventModalLabel');
            const formErrorMessage = document.getElementById('form-error-message');
            const loadingSpinner = document.getElementById('loading-spinner');
            const saveButton = document.getElementById('save-event-btn');
            const updateButton = document.getElementById('update-event-btn');
            const deleteButton = document.getElementById('delete-event-btn');
            const createEventButtonSidebar = document.getElementById('create-event-btn-sidebar');
            const closeModalButton = document.getElementById('close-modal-btn');
            const cancelModalButton = document.getElementById('cancel-modal-btn');
            
            const openModal = () => eventModal.classList.remove('hidden');
            const closeModal = () => eventModal.classList.add('hidden');

            // Function to format Date objects for datetime-local input
            function formatLocalDateTimeInput(date) {
                if (!date) return '';
                const localDate = new Date(date);
                localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());
                return localDate.toISOString().slice(0, 16);
             }

            // Function to reset and prepare the modal form
             function resetAndPrepareForm(mode = 'create', startDate = null, endDate = null) {
                 eventForm.reset();
                 formErrorMessage.style.display = 'none';
                 formErrorMessage.textContent = '';
                 document.getElementById('event-color').value = '#007bff';
                 eventForm.classList.remove('was-validated');
                 document.getElementById('event-id').value = '';

                 // Configure modal based on mode
                 if (mode === 'create') {
                     modalTitle.textContent = 'Créer un nouvel événement';
                     saveButton.style.display = 'inline-block';
                     updateButton.style.display = 'none';
                     deleteButton.style.display = 'none';

                     if (startDate) {
                         document.getElementById('event-start').value = formatLocalDateTimeInput(startDate);
                     }
                     if (endDate) {
                         document.getElementById('event-end').value = formatLocalDateTimeInput(endDate);
                     } else if (startDate) {
                          const defaultEndDate = new Date(startDate.getTime() + 60 * 60 * 1000);
                          document.getElementById('event-end').value = formatLocalDateTimeInput(defaultEndDate);
                     }
                 } else { 
                     modalTitle.textContent = 'Détails de l\'événement';
                     saveButton.style.display = 'none';
                     updateButton.style.display = 'none'; 
                     deleteButton.style.display = 'none'; 
                 }
             }

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: window.innerWidth <= 768 ? 'listWeek' : 'timeGridWeek',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                views: {
                     timeGridWeek: {
                         allDaySlot: false,
                          slotLabelFormat: {
                             hour: '2-digit',
                             minute: '2-digit',
                             meridiem: false,
                             hour12: false
                         }
                     },
                     timeGridDay: {
                         allDaySlot: false,
                         slotLabelFormat: {
                             hour: '2-digit',
                             minute: '2-digit',
                             meridiem: false,
                             hour12: false
                         }
                     }
                 },
                scrollTime: '08:00:00',
                locale: 'fr',
                buttonText: {
                    today:    "Aujourd'hui",
                    month:    'Mois',
                    week:     'Semaine',
                    day:      'Jour',
                    list:     'Liste'
                },
                events: {
                    url: 'events_handler.php?action=get_events',
                    method: 'GET',
                    failure: function(error) {
                        console.error("Error fetching events:", error);
                        alert('Erreur lors du chargement des événements.');
                    },
                    color: '#3788d8',
                    textColor: 'white'
                },
                 loading: function(isLoading) {
                    loadingSpinner.style.display = isLoading ? 'flex' : 'none';
                },
                selectable: true,
                editable: false,
                selectMirror: true,
                nowIndicator: true,

                select: function(selectInfo) {
                    resetAndPrepareForm('create', selectInfo.start, selectInfo.end);
                    openModal();
                },

                eventClick: function(eventClickInfo) {
                     resetAndPrepareForm('view');
                     const event = eventClickInfo.event;
                     const extendedProps = event.extendedProps || {};
                     document.getElementById('event-id').value = event.id;
                     document.getElementById('event-title').value = event.title;
                     document.getElementById('event-description').value = extendedProps.description || '';
                     document.getElementById('event-start').value = formatLocalDateTimeInput(event.start);
                     document.getElementById('event-end').value = formatLocalDateTimeInput(event.end);
                     document.getElementById('event-color').value = event.backgroundColor || '#007bff';
                      const assignedUserIds = extendedProps.assigned_user_ids || [];
                      // You might need a library like Select2 or Choices.js to properly handle multi-select, 
                      // but for standard select, you can loop through options
                      const select = document.getElementById('event-assigned-users');
                      for (let i = 0; i < select.options.length; i++) {
                        select.options[i].selected = assignedUserIds.includes(parseInt(select.options[i].value));
                      }
                     openModal();
                },
            });

            calendar.render();
            lucide.createIcons();

            createEventButtonSidebar.addEventListener('click', openModal);
            closeModalButton.addEventListener('click', closeModal);
            cancelModalButton.addEventListener('click', closeModal);
            
             eventForm.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();

                 if (eventForm.checkValidity() === false) {
                    eventForm.classList.add('was-validated');
                    return;
                 }
                 eventForm.classList.remove('was-validated');

                formErrorMessage.style.display = 'none';

                const formData = new FormData(eventForm);
                const startDt = new Date(formData.get('start_datetime'));
                const endDt = new Date(formData.get('end_datetime'));

                 if (endDt <= startDt) {
                     showFormError("La date/heure de fin doit être postérieure à la date/heure de début.");
                     return;
                 }
                 const assignedUsers = formData.getAll('assigned_users[]');
                 if (assignedUsers.length === 0) {
                      showFormError("Veuillez assigner l'événement à au moins un utilisateur.");
                      return;
                 }

                 formData.append('action', 'create_event');
                 loadingSpinner.style.display = 'flex';

                fetch('events_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                     if (!response.ok) {
                         return response.text().then(text => { throw new Error(text) });
                     }
                     return response.json();
                 })
                .then(data => {
                    if (data.status === 'success') {
                        closeModal();
                        calendar.refetchEvents();
                        console.log('Événement créé avec succès !', data);
                    } else {
                        showFormError(data.message || 'Erreur inconnue lors de la création.');
                    }
                })
                .catch(error => {
                    console.error("Form submission error:", error);
                     try {
                         const errorJson = JSON.parse(error.message);
                          showFormError('Erreur lors de la soumission: ' + (errorJson.message || error.message));
                     } catch (e) {
                          showFormError('Erreur lors de la soumission: ' + error.message);
                     }

                })
                .finally(() => {
                     loadingSpinner.style.display = 'none';
                });
            });

             function showFormError(message) {
                  formErrorMessage.textContent = message;
                  formErrorMessage.classList.remove('hidden');
              }
        });
    </script>

</body>
</html>
