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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js'></script>
    <style>
        /* --- Base Styles (Keep relevant ones) --- */
        body {
            background-color: #f5f5f7;
            color: #1d1d1f;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
        }
        .card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #e5e5e5;
        }
        h2 {
            margin-bottom: 25px;
            color: #1d1d1f;
            font-size: 28px;
            font-weight: 600;
        }
         .btn { /* General button styling */
             padding: 10px 20px;
             border: none;
             border-radius: 8px;
             cursor: pointer;
             font-weight: 600;
             font-size: 15px;
             transition: background-color 0.2s ease-in-out, opacity 0.2s ease-in-out;
             margin-right: 5px;
         }
        /* Adjust FullCalendar Toolbar Buttons to match Bootstrap style */
        .fc .fc-button-primary {
            background-color: #007aff;
            border-color: #007aff;
            color: white;
             padding: .375rem .75rem; /* Match Bootstrap padding */
             font-size: 1rem;       /* Match Bootstrap font size */
             line-height: 1.5;     /* Match Bootstrap line height */
             border-radius: .25rem; /* Match Bootstrap border radius */
             font-weight: 400; /* Adjust if needed */
        }
        .fc .fc-button-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
         .fc .fc-button-primary:disabled {
             background-color: #6c757d;
             border-color: #6c757d;
         }

        /* Responsive adjustments for smaller screens */
        @media (max-width: 768px) {
            .fc .fc-toolbar {
                flex-direction: column;
                align-items: center;
            }
            .fc .fc-toolbar .fc-toolbar-chunk {
                margin-bottom: 10px; /* Add space between button groups */
            }
            .fc .fc-button-group {
                margin-bottom: 5px; /* Space between buttons within a group */
            }
             /* Adjust font size for better readability on small screens */
            .fc .fc-col-header-cell span,
            .fc .fc-event .fc-event-title,
            .fc .fc-event .fc-event-time,
            .fc .fc-list-event-title,
            .fc .fc-list-event-time { /* Added list view elements */
                 font-size: 0.85em; /* Adjusted font size */
            }
             /* Potentially adjust list view elements */
             .fc-list-event .fc-list-event-time {
                 display: block; /* or adjust as needed */
                 font-size: 0.85em; /* Ensure consistency */
             }
             .fc-list-event-graphic {
                 display: none; /* Hide the dot in list view on small screens if space is tight */
             }
              .fc-list-event-main {
                  margin-left: 0; /* Remove left margin if graphic is hidden */
              }

             /* Adjust month view on smaller screens */
             .fc-daygrid-day-number {
                 font-size: 0.85em; /* Adjust day number font size */
             }
             .fc-daygrid-event-dot {
                 display: none; /* Hide event dots in month view on small screens */
             }
             .fc-daygrid-event {
                white-space: normal; /* Allow event title to wrap */
                margin-bottom: 2px;
             }
             .fc-daygrid-event .fc-event-time {
                 font-size: 0.75em; /* Smaller time font in month view */
                 display: inline-block;
                 margin-right: 5px;
             }
              .fc-daygrid-event .fc-event-title {
                  font-size: 0.85em; /* Smaller title font in month view */
              }

              /* Hide month and week buttons on mobile */
              .fc-dayGridMonth-button,
              .fc-timeGridWeek-button {
                  display: none !important;
              }
        }

         /* Adjust modal select multiple */
         #event-assigned-users {
             min-height: 120px; /* Ensure it's tall enough */
         }

        /* Loading Spinner Style */
        #loading-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1051; /* Above modal backdrop */
        }

         /* Style for view-only mode in modal */
         .view-mode .form-control {
             background-color: #e9ecef; /* Bootstrap disabled style */
             opacity: 1;
             border: none;
             box-shadow: none;
         }
          .view-mode .form-control[type="color"] {
             padding: 0; /* Remove padding for color input */
         }
          .view-mode select[multiple] {
              pointer-events: none; /* Prevent interaction */
          }
          .view-mode textarea {
               resize: none; /* Prevent resizing */
          }

          /* Style for the new Create Event button */
          #create-event-button {
              margin-bottom: 15px; /* Add some space below the button */
          }
    </style>
</head>
<body>

<?php include 'navbar.php'; // Include the navigation bar ?>

<div class="container-fluid mt-4">
    <h2>Planning des Événements</h2>

    <button type="button" id="create-event-button" class="btn btn-primary">
        <i class="fas fa-plus"></i> Créer un événement
    </button>


    <div class="card">
        <div id='calendar'></div>
    </div>
</div>

<div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventModalLabel">Détails de l'événement</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="event-form" novalidate>
                <div class="modal-body">
                    <input type="hidden" id="event-id" name="event_id">

                    <div id="form-error-message" class="alert alert-danger" style="display: none;"></div>

                    <div class="form-group">
                        <label for="event-title">Titre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="event-title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="event-description">Description</label>
                        <textarea class="form-control" id="event-description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                         <div class="col-md-6 form-group">
                             <label for="event-start">Début <span class="text-danger">*</span></label>
                             <input type="datetime-local" class="form-control" id="event-start" name="start_datetime" required>
                         </div>
                         <div class="col-md-6 form-group">
                             <label for="event-end">Fin <span class="text-danger">*</span></label>
                             <input type="datetime-local" class="form-control" id="event-end" name="end_datetime" required>
                         </div>
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
                    <div class="form-group">
                        <label for="event-color">Couleur</label>
                        <input type="color" class="form-control" id="event-color" name="color" value="#007bff">
                    </div>
                </div>
                <div class="modal-footer">
                     <button type="button" id="update-event-btn" class="btn btn-info" style="display: none;">Mettre à jour</button>
                    <button type="button" id="delete-event-btn" class="btn btn-danger" style="display: none;">Supprimer</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" id="save-event-btn" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="loading-spinner" style="display: none;">
    <div class="spinner-border text-primary" role="status">
        <span class="sr-only">Chargement...</span>
    </div>
</div>


<?php include('footer.php'); // Optional: if you have a footer file ?>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@popperjs/core@2"></script>
<script src="https://unpkg.com/tippy.js@6"></script>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');
        const eventModal = $('#eventModal'); // jQuery object for modal control
        const eventForm = document.getElementById('event-form');
        const modalTitle = document.getElementById('eventModalLabel');
        const formErrorMessage = document.getElementById('form-error-message');
        const loadingSpinner = document.getElementById('loading-spinner');
        const saveButton = document.getElementById('save-event-btn');
        const updateButton = document.getElementById('update-event-btn'); // Placeholder
        const deleteButton = document.getElementById('delete-event-btn'); // Placeholder
        const createEventButton = document.getElementById('create-event-button'); // New button

        // Function to format Date objects for datetime-local input
        function formatLocalDateTimeInput(date) {
            if (!date) return '';
            // Create a new Date object to avoid modifying the original FullCalendar date
            const localDate = new Date(date);
            // Adjust for local timezone offset before formatting
            localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());
            // Return in 'YYYY-MM-DDTHH:mm' format
            return localDate.toISOString().slice(0, 16);
         }

        // Function to reset and prepare the modal form
         function resetAndPrepareForm(mode = 'create', startDate = null, endDate = null) {
             eventForm.reset(); // Reset form fields
             formErrorMessage.style.display = 'none'; // Hide error message
             formErrorMessage.textContent = '';
             document.getElementById('event-color').value = '#007bff'; // Reset color
             eventForm.classList.remove('was-validated'); // Remove validation states if using Bootstrap validation
             eventForm.classList.remove('view-mode'); // Ensure not in view mode
             document.getElementById('event-id').value = ''; // Clear event ID

             // Reset multi-select
             $('#event-assigned-users').val(null); //.trigger('change'); // Use jQuery for multi-select reset

             // Configure modal based on mode
             if (mode === 'create') {
                 modalTitle.textContent = 'Créer un nouvel événement';
                 saveButton.style.display = 'inline-block';
                 updateButton.style.display = 'none';
                 deleteButton.style.display = 'none';
                 // Ensure fields are editable
                  $('#event-form :input').prop('disabled', false);
                  $('#event-assigned-users').prop('disabled', false); // Ensure multi-select is enabled

                 // Pre-fill dates if provided
                 if (startDate) {
                     document.getElementById('event-start').value = formatLocalDateTimeInput(startDate);
                 }
                 if (endDate) {
                     document.getElementById('event-end').value = formatLocalDateTimeInput(endDate);
                 } else if (startDate) {
                      // Default end time: Add 1 hour if only start is provided
                      const defaultEndDate = new Date(startDate.getTime() + 60 * 60 * 1000);
                      document.getElementById('event-end').value = formatLocalDateTimeInput(defaultEndDate);
                 }


             } else { // 'view' or 'edit' mode (currently just view)
                 modalTitle.textContent = 'Détails de l\'événement';
                 saveButton.style.display = 'none'; // Hide Save for view/edit initially
                 updateButton.style.display = 'none'; // Hide Update initially
                 deleteButton.style.display = 'none'; // Hide Delete initially
                 // TODO: Add logic to show Update/Delete buttons if implementing editing
                 // Make fields read-only for viewing
                 eventForm.classList.add('view-mode');
                 $('#event-form :input:not([type=hidden]):not(.btn)').prop('disabled', true);
                 $('#event-assigned-users').prop('disabled', true); // Ensure multi-select is disabled
             }
         }


        const calendar = new FullCalendar.Calendar(calendarEl, {
            // --- Core Plugins ---
            // No need to explicitly list standard plugins like interaction, dayGrid, timeGrid, list in v6 index.global.min.js

            // --- View Options ---
            // Determine initial view based on screen width
            initialView: window.innerWidth <= 768 ? 'listWeek' : 'timeGridWeek',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' // View switcher
            },
             // You can customize views further if needed, e.g., for mobile-specific column formats
            views: {
                 timeGridWeek: {
                     // configuration for timeGridWeek view
                     allDaySlot: false, // Hide the "all day" row
                      slotLabelFormat: { // Use 24-hour format
                         hour: '2-digit',
                         minute: '2-digit',
                         meridiem: false, // Hide AM/PM
                         hour12: false // Use 24-hour format
                     }
                 },
                 timeGridDay: {
                     // configuration for timeGridDay view
                     allDaySlot: false, // Hide the "all day" row
                     slotLabelFormat: { // Use 24-hour format
                         hour: '2-digit',
                         minute: '2-digit',
                         meridiem: false, // Hide AM/PM
                         hour12: false // Use 24-hour format
                     }
                 },
                 listWeek: {
                     // configuration for listWeek view
                 },
                 dayGridMonth: {
                      // configuration for dayGridMonth view
                 }
             },
            // Removed slotMinTime and slotMaxTime to show all 24 hours
            // slotMinTime: '08:00:00', // Start time displayed
            // slotMaxTime: '18:00:00', // End time displayed

            // Set the default scroll position for timeGrid views
            scrollTime: '08:00:00',

            // --- Localization ---
            locale: 'fr', // Set language to French
            buttonText: { // Optional: Customize button text if locale doesn't cover everything
                today:    "Aujourd'hui",
                month:    'Mois',
                week:     'Semaine',
                day:      'Jour',
                list:     'Liste'
            },
            // --- Event Data ---
            events: {
                url: 'events_handler.php?action=get_events',
                method: 'GET', // Default, but good to be explicit
                failure: function(error) {
                    console.error("Error fetching events:", error);
                    alert('Erreur lors du chargement des événements.');
                    // Optionally display error on the page
                },
                color: '#3788d8', // Default event color if not specified by event
                textColor: 'white' // Default text color
            },
            // --- Loading Indicator ---
             loading: function(isLoading) {
                if (isLoading) {
                    loadingSpinner.style.display = 'block';
                } else {
                    loadingSpinner.style.display = 'none';
                }
            },
            // --- Interaction ---
            selectable: true,  // Allow selecting time slots
            editable: false,   // Disable drag-and-drop/resizing for now (requires update handler)
            selectMirror: true, // Show placeholder event while selecting
            nowIndicator: true, // Show current time marker

            // --- Callbacks ---
            select: function(selectInfo) {
                 // When a time slot is selected, open the modal for creation
                resetAndPrepareForm('create', selectInfo.start, selectInfo.end);
                eventModal.modal('show');
            },

            eventClick: function(eventClickInfo) {
                 resetAndPrepareForm('view'); // Prepare form for viewing

                 const event = eventClickInfo.event;
                 const extendedProps = event.extendedProps || {};

                 // Populate the form with event data
                 document.getElementById('event-id').value = event.id; // Store event ID
                 document.getElementById('event-title').value = event.title;
                 document.getElementById('event-description').value = extendedProps.description || '';
                 document.getElementById('event-start').value = formatLocalDateTimeInput(event.start);
                 document.getElementById('event-end').value = formatLocalDateTimeInput(event.end);
                 document.getElementById('event-color').value = event.backgroundColor || '#007bff'; // Use backgroundColor

                  // Select assigned users in the multi-select dropdown
                  const assignedUserIds = extendedProps.assigned_user_ids || [];
                  $('#event-assigned-users').val(assignedUserIds); // Use jQuery
                   // Manually trigger change if needed for styling libraries, though not strictly necessary for basic select
                  // $('#event-assigned-users').trigger('change');


                 eventModal.modal('show');
            },

             eventDidMount: function(info) {
                // Add tooltip for description using Tippy.js
                if (info.event.extendedProps.description) {
                    tippy(info.el, {
                        content: info.event.extendedProps.description,
                        placement: 'top', // Or 'auto', 'bottom', etc.
                         // Improve mobile tooltip visibility if needed
                         appendTo: document.body, // Helps with positioning
                    });
                }
                 // Optional: Add assigned users to the event display itself if needed
                 // const users = info.event.extendedProps.assigned_users || [];
                 // if (users.length > 0) {
                 //     const userNames = users.map(u => u.name).join(', ');
                 //     // Find a place inside info.el to append this, e.g., info.el.querySelector('.fc-event-title')
                 // }
            }

        });

        calendar.render();

         // Optional: Handle window resize to potentially change view - disabled for now
         // This might be too aggressive, consider if truly needed or if CSS is enough
         // let currentView = calendar.getView().type;
         // window.addEventListener('resize', function() {
         //      const newView = window.innerWidth <= 768 ? 'listWeek' : 'timeGridWeek';
         //      if (newView !== currentView) {
         //          calendar.changeView(newView);
         //          currentView = newView;
         //      }
         // });


         // --- Modal Form Submission Logic ---
         eventForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            e.stopPropagation(); // Stop propagation

             // Basic client-side validation (using Bootstrap classes)
             if (eventForm.checkValidity() === false) {
                eventForm.classList.add('was-validated');
                return;
             }
             eventForm.classList.remove('was-validated'); // Remove if valid so far

            formErrorMessage.style.display = 'none'; // Hide previous errors

            const formData = new FormData(eventForm);
            const startDt = new Date(formData.get('start_datetime'));
            const endDt = new Date(formData.get('end_datetime'));

             // Additional Validation (e.g., end date after start date)
             if (endDt <= startDt) {
                 showFormError("La date/heure de fin doit être postérieure à la date/heure de début.");
                 return;
             }
             // Check if at least one user is assigned
             const assignedUsers = formData.getAll('assigned_users[]');
             if (assignedUsers.length === 0) {
                  showFormError("Veuillez assigner l'événement à au moins un utilisateur.");
                  return;
             }

            // Determine action (only create for now)
             formData.append('action', 'create_event');
             // TODO: Add logic for update_event if implementing editing

             // Show spinner
             loadingSpinner.style.display = 'block';

            // Send data using Fetch API
            fetch('events_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                 if (!response.ok) {
                     // Handle HTTP errors
                     return response.text().then(text => { throw new Error(text) });
                 }
                 return response.json();
             })
            .then(data => {
                if (data.status === 'success') {
                    eventModal.modal('hide'); // Hide modal on success
                    calendar.refetchEvents(); // <<< Refresh FullCalendar events
                    // Optional: Show success message (e.g., using a toast notification library)
                    console.log('Événement créé avec succès !', data);
                } else {
                    showFormError(data.message || 'Erreur inconnue lors de la création.');
                }
            })
            .catch(error => {
                console.error("Form submission error:", error);
                 // Try to parse the error message if it's JSON, otherwise display as text
                 try {
                     const errorJson = JSON.parse(error.message);
                      showFormError('Erreur lors de la soumission: ' + (errorJson.message || error.message));
                 } catch (e) {
                      showFormError('Erreur lors de la soumission: ' + error.message);
                 }

            })
            .finally(() => {
                // Hide spinner
                 loadingSpinner.style.display = 'none';
            });
        });

         function showFormError(message) {
              formErrorMessage.textContent = message;
              formErrorMessage.style.display = 'block';
          }

         // Handle Modal Close - Reset form
         eventModal.on('hidden.bs.modal', function () {
              resetAndPrepareForm('create'); // Reset to default create state
         });

        // Event listener for the new Create Event button
        createEventButton.addEventListener('click', function() {
            resetAndPrepareForm('create'); // Prepare form for creation
            // Optionally pre-fill dates with current date/time
            const now = new Date();
            document.getElementById('event-start').value = formatLocalDateTimeInput(now);
            const defaultEndDate = new Date(now.getTime() + 60 * 60 * 1000); // 1 hour later
            document.getElementById('event-end').value = formatLocalDateTimeInput(defaultEndDate);
            eventModal.modal('show');
        });


    });
</script>

</body>
</html>
