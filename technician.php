<?php
require_once 'session-management.php';
// Ensure the user is logged in before they can access this page
requireLogin();
// Get the current user's details to personalize the page
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du Matériel Technicien</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://unpkg.com/@zxing/library@latest/umd/index.min.js"></script>
    <style>
        body {
            background-color: #f5f5f7;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .main-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.08);
            border: none;
            margin-top: 20px;
        }
        h2.card-title {
            font-weight: 600;
            color: #1d1d1f;
        }
        .item-card {
            display: flex;
            align-items: center;
            padding: 1rem 1.25rem;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 1rem;
            background-color: #fff;
            transition: box-shadow 0.2s ease-in-out;
        }
        .item-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .item-icon {
            font-size: 2.2em;
            color: #007bff;
            margin-right: 1.5rem;
            width: 50px;
            text-align: center;
        }
        .item-details { flex-grow: 1; }
        .item-name {
            font-weight: 600;
            font-size: 1.15rem;
            color: #343a40;
        }
        .item-meta {
            font-size: 0.9em;
            color: #6c757d;
            line-height: 1.6;
        }
        .item-meta strong { color: #495057; }
        .item-actions {
            margin-left: 1rem;
            text-align: right;
            min-width: 120px;
        }
        .manual-entry-link {
            font-size: 0.8em;
            color: #007bff;
            cursor: pointer;
            display: block;
            margin-top: 5px;
        }
        .manual-entry-link:hover { text-decoration: underline; }
        #scanner-preview {
            width: 100%;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .modal-header .fas {
            font-size: 1.5rem;
            margin-right: 10px;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0069d9;
            border-color: #0062cc;
        }
        .overdue {
            background-color: #fff3cd; /* Yellow highlight for overdue items */
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="main-card">
                <div class="card-body p-4">
                    <h2 class="card-title mb-4">Mon Matériel de Mission</h2>

                    <div id="equipment-list-section"></div>

                    <div id="equipment-placeholder" class="text-center p-5" style="display: none;">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun matériel assigné ou en votre possession.</p>
                    </div>

                    <hr class="my-4">
                    <div class="text-center">
                         <h5 class="text-secondary mb-3">Prendre un nouvel article</h5>
                         <button id="pickup-item-scan-btn" class="btn btn-primary btn-lg"><i class="fas fa-barcode mr-2"></i>Scanner un Article</button>
                         <button id="pickup-item-manual-btn" class="btn btn-outline-secondary ml-2"><i class="fas fa-keyboard mr-2"></i>Saisie Manuelle</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="scanner-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scanner-title">Scanner un Code-barres</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body text-center">
                <video id="scanner-preview" playsinline></video>
                <p class="text-muted mt-2">Veuillez aligner le code-barres avec la caméra.</p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="manual-entry-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manual-entry-title">Saisie Manuelle</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p id="manual-entry-prompt" class="text-center"></p>
                <form id="manual-entry-form">
                    <div class="form-group">
                        <input type="text" class="form-control form-control-lg text-center" id="manual-barcode-input" placeholder="Entrez le code-barres" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Valider</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="range-booking-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Réserver un Article</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Veuillez choisir la <strong>date de retour</strong> pour <strong><span id="range-booking-item-name"></span></strong>.</p>
                <p class="text-muted small">L'article sera réservé pour tous les jours consécutifs à partir d'aujourd'hui jusqu'à la date de retour incluse. Les dates déjà réservées par d'autres ne sont pas sélectionnables.</p>
                <div class="form-group">
                    <label for="assignment-name"><strong>Nom de la mission *</strong></label>
                    <input type="text" id="assignment-name" class="form-control" placeholder="Entrez le nom de la mission" required>
                </div>
                <div class="form-group">
                    <input type="text" id="return-date-calendar" class="form-control" placeholder="Cliquez pour choisir la date de retour...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="confirm-range-booking-btn">Confirmer et Prendre</button>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="info-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="info-modal-header">
                <i class="fas"></i>
                <h5 class="modal-title" id="info-modal-title">Notification</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p id="info-modal-body" class="lead text-center"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/fr.js"></script>

<script>
    // Global state variables
    let itemToProcess = null;
    const codeReader = new ZXing.BrowserMultiFormatReader();
    let returnDatePicker = null;
    const currentUserId = <?php echo $user['user_id']; ?>;

    // This function runs when the page is fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Load the technician's equipment list on page start
        loadTechnicianEquipment();

        // Event listener for "Take" or "Return" buttons on the equipment list
        $('#equipment-list-section').on('click', '.action-btn', function() {
            prepareAction($(this));
            $('#scanner-title').text(`Pour ${itemToProcess.action === 'take' ? 'Prendre' : 'Retourner'}: Scannez "${itemToProcess.itemName}"`);
            startScanner();
        });

        // Event listener for manual entry links under the return buttons
        $('#equipment-list-section').on('click', '.manual-entry-link', function(e) {
            e.preventDefault();
            prepareAction($(this));
            showManualEntryModal(`Pour retourner "${itemToProcess.itemName}", entrez son code-barres.`);
        });

        // Event listeners for the main "Pickup New Item" buttons
        $('#pickup-item-scan-btn').on('click', function() {
            itemToProcess = { action: 'pickup' }; // Set a generic pickup action
            $('#scanner-title').text("Scannez un article disponible");
            startScanner();
        });

        $('#pickup-item-manual-btn').on('click', function() {
            itemToProcess = { action: 'pickup' };
            showManualEntryModal("Entrez le code-barres de l'article à prendre.");
        });

        // Handle the submission of the manual entry form
        $('#manual-entry-form').on('submit', function(e) {
            e.preventDefault();
            const enteredBarcode = $('#manual-barcode-input').val();
            $('#manual-entry-modal').modal('hide');
            processAction(enteredBarcode);
        });

        // Reset scanner and state when modals are closed
        $('#scanner-modal').on('hidden.bs.modal', () => codeReader.reset());
        $('#manual-entry-modal, #scanner-modal, #range-booking-modal').on('hidden.bs.modal', () => {
            $('#manual-entry-form')[0].reset();
            itemToProcess = null; // Clear the state
        });
    });

    // Stores the details of the item being acted upon
    function prepareAction(button) {
        itemToProcess = {
            action: button.data('action'),
            assetId: button.data('asset-id'),
            bookingId: button.data('booking-id'),
            barcode: button.data('barcode'),
            itemName: button.data('item-name')
        };
    }

    // Shows the manual entry modal with a specific prompt
    function showManualEntryModal(promptText) {
        $('#manual-entry-title').text('Saisie Manuelle');
        $('#manual-entry-prompt').text(promptText);
        $('#manual-entry-modal').modal('show');
    }

    // Initializes and shows the barcode scanner modal
    function startScanner() {
        $('#scanner-modal').modal('show');
        codeReader.decodeFromVideoDevice(undefined, 'scanner-preview', (result, err) => {
            if (result) {
                codeReader.reset();
                $('#scanner-modal').modal('hide');
                processAction(result.text); // Process the scanned barcode
            }
            // No action on error, allows user to keep trying
        });
    }

    // Main logic to decide what to do with a scanned/entered barcode
    function processAction(barcode) {
        if (!itemToProcess) return;

        if (itemToProcess.action === 'pickup') {
            // This is for picking up a new item not on the list
            handleItemPickup(barcode);
        } else {
            // This is for taking/returning an item already on the list
            // Verify that the scanned barcode matches the expected one
            if (barcode.trim() !== String(itemToProcess.barcode)) {
                showNotification(`Action annulée. Le code-barres ne correspond pas à "${itemToProcess.itemName}".`, 'danger');
                return;
            }
            const backendAction = itemToProcess.action === 'take' ? 'checkout_item' : 'return_item';
            const postData = { asset_id: itemToProcess.assetId, booking_id: itemToProcess.bookingId };
            performAjaxCall(backendAction, postData);
        }
    }

    // Handles the flow for picking up a new item
    function handleItemPickup(barcode) {
        // First, ask the backend for the item's availability
        performAjaxCall('get_item_availability_for_pickup', { barcode: barcode }, (response) => {
            const asset = response.data.asset;
            const nextBookingDate = response.data.next_booking_date;

            // Check if the item is already booked for today
            if (response.data.booked_today) {
                showNotification(`Cet article (${asset.asset_name}) est déjà réservé pour aujourd'hui et ne peut pas être pris.`, 'danger');
                return;
            }

            // If available, show the booking modal
            $('#range-booking-item-name').text(asset.asset_name);
            $('#range-booking-modal').modal('show');

            // Destroy any previous calendar instance to avoid conflicts
            if (returnDatePicker) {
                returnDatePicker.destroy();
            }

            // Configure the Flatpickr calendar
            let flatpickrConfig = {
                locale: "fr",
                minDate: "today", // Can't book in the past
                dateFormat: "Y-m-d",
            };

            // If there's a future booking, prevent selection of that date or any date after it
            if (nextBookingDate) {
                let maxDate = new Date(nextBookingDate);
                maxDate.setDate(maxDate.getDate() - 1); // Set max selectable date to the day *before* the next booking
                flatpickrConfig.maxDate = maxDate;
            }

            returnDatePicker = flatpickr("#return-date-calendar", flatpickrConfig);

            // Set up the confirmation button inside the booking modal
            $('#confirm-range-booking-btn').off('click').on('click', function() {
                const selectedReturnDate = returnDatePicker.selectedDates[0];
                if (!selectedReturnDate) {
                    showNotification("Veuillez sélectionner une date de retour.", "danger");
                    return;
                }
                const assignmentName = $('#assignment-name').val();
                if (!assignmentName.trim()) {
                    showNotification("Veuillez entrer un nom de mission.", "danger");
                    return;
                }

                const returnDateStr = returnDatePicker.formatDate(selectedReturnDate, "Y-m-d");

                $('#range-booking-modal').modal('hide');
                // Call the backend to create the bookings and check out the item
                performAjaxCall('book_and_pickup_range', {
                    asset_id: asset.asset_id,
                    return_date: returnDateStr,
                    assignment_name: assignmentName
                });
            });
        });
    }

    // Fetches the equipment list from the backend
    function loadTechnicianEquipment() {
        showLoading($('#equipment-list-section'));
        performAjaxCall('get_technician_equipment', {}, renderEquipmentList, 'GET');
    }

    // Renders the equipment list HTML from the server response
    function renderEquipmentList(response) {
        const listContainer = $('#equipment-list-section');
        listContainer.empty();
        $('#equipment-placeholder').hide();

        let items = response.data.equipment;
        if (!items || items.length === 0) {
            $('#equipment-placeholder').show();
            return;
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Sort items: overdue items first, then by name
        items.sort((a, b) => {
            const a_return_date = a.return_date ? new Date(a.return_date) : null;
            const b_return_date = b.return_date ? new Date(b.return_date) : null;

            const a_is_overdue = a.status === 'in-use' && a_return_date && a_return_date < today;
            const b_is_overdue = b.status === 'in-use' && b_return_date && b_return_date < today;

            if (a_is_overdue && !b_is_overdue) return -1;
            if (!a_is_overdue && b_is_overdue) return 1;

            return a.asset_name.localeCompare(b.asset_name);
        });


        items.forEach(item => {
            const iconClass = item.asset_type === 'vehicle' ? 'fa-car' : 'fa-tools';
            const isTakenByMe = item.status === 'in-use' && item.assigned_to_user_id == currentUserId;

            let actionButtonHtml = '';
            const itemDataReturn = `data-action="return" data-asset-id="${item.asset_id}" data-booking-id="${item.booking_id || ''}" data-barcode="${item.barcode}" data-item-name="${item.asset_name}"`;
            const itemDataTake = `data-action="take" data-asset-id="${item.asset_id}" data-booking-id="${item.booking_id}" data-barcode="${item.barcode}" data-item-name="${item.asset_name}"`;

            if (isTakenByMe) {
                actionButtonHtml = `
                    <button class="btn btn-info action-btn" ${itemDataReturn}>Retourner</button>
                    <a href="#" class="manual-entry-link" ${itemDataReturn}>(saisie manuelle)</a>`;
            } else {
                 actionButtonHtml = `<button class="btn btn-success action-btn" ${itemDataTake}>Prendre</button>`;
            }

            let returnDateStr = 'N/A';
            let cardClass = 'item-card';
            if (item.return_date) {
                const returnDate = new Date(item.return_date);
                returnDateStr = returnDate.toLocaleDateString('fr-FR');
                if (isTakenByMe && returnDate < today) {
                    cardClass += ' overdue'; // Add yellow highlight for overdue items
                }
            }

            const itemCardHtml = `
                <div class="${cardClass}">
                    <div class="item-icon"><i class="fas ${iconClass}"></i></div>
                    <div class="item-details">
                        <div class="item-name">${item.asset_name}</div>
                        <div class="item-meta">
                            <div><strong>Mission:</strong> ${item.mission || 'N/A'}</div>
                            <div><strong>Date de retour:</strong> ${returnDateStr}</div>
                            <div><strong>Code:</strong> ${item.serial_or_plate || 'N/A'} | <strong>Code-barres:</strong> ${item.barcode || 'N/A'}</div>
                        </div>
                    </div>
                    <div class="item-actions">${actionButtonHtml}</div>
                </div>`;
            listContainer.append(itemCardHtml);
        });
    }

    // Generic function for making AJAX calls to the backend
    function performAjaxCall(action, data, successCallback = null, method = 'POST') {
        // If no specific success callback is provided, assume it's a simple action that requires a page reload
        if (!successCallback) {
            showLoading($('#equipment-list-section'));
        }

        $.ajax({
            url: 'technician-handler.php',
            type: method,
            data: { action, ...data }, // Combine action with other data
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    if (successCallback) {
                        successCallback(response); // Execute custom success logic
                    } else {
                        showNotification(response.message, 'success');
                        loadTechnicianEquipment(); // Reload list on success
                    }
                } else {
                    showNotification(response.message, 'danger');
                    loadTechnicianEquipment(); // Also reload on error to get the latest state
                }
            },
            error: function(xhr) {
                const errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Erreur de communication avec le serveur.';
                showNotification(errorMsg, 'danger');
                loadTechnicianEquipment();
            }
        });
    }

    // Shows a loading spinner in a given element
    function showLoading(element) {
        element.html(`<div class="text-center p-5"><div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div><p class="mt-2 text-muted">Chargement...</p></div>`);
    }

    // Shows a styled notification modal
    function showNotification(message, type) {
        const modalHeader = $('#info-modal-header');
        const modalIcon = modalHeader.find('.fas');

        modalHeader.removeClass('bg-success bg-danger bg-info text-white');
        modalIcon.removeClass('fa-check-circle fa-exclamation-triangle fa-info-circle');

        if (type === 'success') {
            modalHeader.addClass('bg-success text-white');
            modalIcon.addClass('fa-check-circle');
            $('#info-modal-title').text('Succès');
        } else { // 'danger' or 'error'
            modalHeader.addClass('bg-danger text-white');
            modalIcon.addClass('fa-exclamation-triangle');
            $('#info-modal-title').text('Erreur');
        }

        $('#info-modal-body').text(message);
        $('#info-modal').modal('show');
    }
</script>
</body>
</html>
