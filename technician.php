<?php
require_once 'session-management.php';
requireLogin();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du Matériel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://unpkg.com/@zxing/library@latest/umd/index.min.js"></script>
    <style>
        body { background-color: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .main-card { background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e5e5e5; }
        h2 { font-weight: 600; }
        .item-card { display: flex; align-items: center; padding: 1rem; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 1rem; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .item-icon { font-size: 2em; color: #007bff; margin-right: 1.5rem; width: 45px; text-align: center; }
        .item-details { flex-grow: 1; }
        .item-name { font-weight: 600; font-size: 1.1rem; color: #343a40; }
        .item-meta { font-size: 0.9em; color: #6c757d; line-height: 1.5; }
        .item-meta strong { color: #495057; }
        .item-actions { margin-left: 1rem; text-align: right; }
        .manual-entry-link { font-size: 0.8em; color: #007bff; cursor: pointer; }
        .manual-entry-link:hover { text-decoration: underline; }
        #scanner-modal .modal-content, #info-modal .modal-content, #manual-entry-modal .modal-content { text-align: center; }
        #scanner-preview { width: 100%; border-radius: 8px; }
        #info-modal .modal-header .fas { font-size: 1.5rem; margin-right: 10px; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="main-card">
                <div class="card-body">
                    <h2 class="card-title mb-4">Mon Matériel de Mission</h2>

                    <div id="equipment-list-section"></div>
                    
                    <div id="equipment-placeholder" class="text-center p-5" style="display: none;">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun matériel assigné pour aujourd'hui.</p>
                    </div>

                    <hr>
                    <div class="text-center">
                         <button id="pickup-item-scan-btn" class="btn btn-primary"><i class="fas fa-barcode mr-2"></i>Prendre un Article (Scan)</button>
                         <button id="pickup-item-manual-btn" class="btn btn-outline-secondary"><i class="fas fa-keyboard mr-2"></i>Prendre un Article (Saisie)</button>
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
                <h5 class="modal-title" id="scanner-title">Scanner un Article</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <video id="scanner-preview" playsinline></video>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="manual-entry-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manual-entry-title">Saisie Manuelle du Code-barres</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p id="manual-entry-prompt"></p>
                <form id="manual-entry-form">
                    <div class="form-group">
                        <input type="text" class="form-control form-control-lg text-center" id="manual-barcode-input" placeholder="Entrez le code-barres ici" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Valider</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="multi-day-booking-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Réserver un Article</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Pour combien de jours souhaitez-vous réserver <strong><span id="multi-day-item-name"></span></strong> ?</p>
                <p class="text-muted small">Les dates déjà réservées sont désactivées. Sélectionnez une ou plusieurs dates.</p>
                <input type="text" id="multi-day-calendar" class="form-control" placeholder="Cliquez pour choisir les dates...">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="confirm-multi-day-booking-btn">Confirmer et Prendre</button>
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
                <p id="info-modal-body" class="lead"></p>
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
    let itemToProcess = null;
    const codeReader = new ZXing.BrowserMultiFormatReader();
    let multiDayPicker = null;

    document.addEventListener('DOMContentLoaded', function() {
        loadTechnicianEquipment();

        $('#equipment-list-section').on('click', '.action-btn', function() {
            prepareAction($(this));
            $('#scanner-title').text(`Pour ${itemToProcess.action === 'take' ? 'Prendre' : 'Retourner'}: Scannez "${itemToProcess.itemName}"`);
            startScanner();
        });

        $('#equipment-list-section').on('click', '.manual-entry-link', function(e) {
            e.preventDefault();
            prepareAction($(this));
            showManualEntryModal(`Pour retourner "${itemToProcess.itemName}", entrez son code-barres.`);
        });

        $('#pickup-item-scan-btn').on('click', function() {
            itemToProcess = { action: 'pickup' };
            $('#scanner-title').text("Scannez un article disponible");
            startScanner();
        });

        $('#pickup-item-manual-btn').on('click', function() {
            itemToProcess = { action: 'pickup' };
            showManualEntryModal("Entrez le code-barres de l'article à prendre.");
        });

        $('#manual-entry-form').on('submit', function(e) {
            e.preventDefault();
            const enteredBarcode = $('#manual-barcode-input').val();
            $('#manual-entry-modal').modal('hide');
            processAction(enteredBarcode);
        });

        $('#scanner-modal').on('hidden.bs.modal', () => codeReader.reset());
        $('#manual-entry-modal, #scanner-modal, #multi-day-booking-modal').on('hidden.bs.modal', () => {
            $('#manual-entry-form')[0].reset();
            itemToProcess = null;
        });
    });
    
    function prepareAction(button) {
        itemToProcess = {
            action: button.data('action'),
            assetId: button.data('asset-id'),
            bookingId: button.data('booking-id'),
            barcode: button.data('barcode'),
            itemName: button.data('item-name')
        };
    }

    function showManualEntryModal(promptText) {
        $('#manual-entry-title').text('Saisie Manuelle');
        $('#manual-entry-prompt').text(promptText);
        $('#manual-entry-modal').modal('show');
    }

    function startScanner() {
        $('#scanner-modal').modal('show');
        codeReader.decodeFromVideoDevice(undefined, 'scanner-preview', (result, err) => {
            if (result) {
                codeReader.reset();
                $('#scanner-modal').modal('hide');
                processAction(result.text);
            }
        });
    }

    function processAction(barcode) {
        if (!itemToProcess) return;

        if (itemToProcess.action === 'pickup') {
            handleItemPickup(barcode);
        } else {
            // This is a regular take/return action
            if (barcode.trim() !== String(itemToProcess.barcode)) {
                showNotification(`Action annulée. Le code-barres ne correspond pas à "${itemToProcess.itemName}".`, 'danger');
                return;
            }
            const backendAction = itemToProcess.action === 'take' ? 'checkout_item' : 'return_item';
            const postData = { asset_id: itemToProcess.assetId, booking_id: itemToProcess.bookingId };
            performAjaxCall(backendAction, postData);
        }
    }
    
    function handleItemPickup(barcode) {
        performAjaxCall('get_item_availability_for_pickup', { barcode: barcode }, (response) => {
            const asset = response.data.asset;
            const disabledDates = response.data.booked_dates;

            $('#multi-day-item-name').text(asset.asset_name);
            $('#multi-day-booking-modal').modal('show');

            if (multiDayPicker) {
                multiDayPicker.destroy();
            }
            multiDayPicker = flatpickr("#multi-day-calendar", {
                mode: "multiple",
                dateFormat: "Y-m-d",
                minDate: "today",
                disable: disabledDates,
                locale: "fr"
            });
            
            $('#confirm-multi-day-booking-btn').off('click').on('click', function() {
                const selectedDates = multiDayPicker.selectedDates.map(date => multiDayPicker.formatDate(date, "Y-m-d"));
                if (selectedDates.length === 0) {
                    showNotification("Veuillez sélectionner au moins une date.", "danger");
                    return;
                }
                
                $('#multi-day-booking-modal').modal('hide');
                performAjaxCall('book_and_pickup_multiple_days', {
                    asset_id: asset.asset_id,
                    dates: selectedDates
                });
            });
        });
    }

    function loadTechnicianEquipment() {
        showLoading($('#equipment-list-section'));
        performAjaxCall('get_technician_equipment', {}, (response) => {
            renderEquipmentList(response);
        }, 'GET');
    }

    function renderEquipmentList(response) {
        const listContainer = $('#equipment-list-section');
        listContainer.empty();
        $('#equipment-placeholder').hide();

        const items = response.data.equipment;
        if (!items || items.length === 0) {
            $('#equipment-placeholder').show();
            return;
        }

        items.forEach(item => {
            const iconClass = item.asset_type === 'vehicle' ? 'fa-car' : 'fa-wrench';
            const isTakenByMe = item.status === 'in-use' && item.assigned_to_user_id == <?php echo $user['user_id']; ?>;

            let actionButtonHtml = '';
            const itemData = `data-action="return" data-asset-id="${item.asset_id}" data-booking-id="${item.booking_id || ''}" data-barcode="${item.barcode}" data-item-name="${item.asset_name}"`;

            if (isTakenByMe) {
                actionButtonHtml = `
                    <button class="btn btn-info action-btn" ${itemData}>Retourner</button>
                    <div class="mt-1"><a href="#" class="manual-entry-link" ${itemData}>(saisie manuelle)</a></div>`;
            } else {
                 actionButtonHtml = `<button class="btn btn-success action-btn" data-action="take" data-asset-id="${item.asset_id}" data-booking-id="${item.booking_id}" data-barcode="${item.barcode}" data-item-name="${item.asset_name}">Prendre</button>`;
            }

            const itemCardHtml = `<div class="item-card">
                    <div class="item-icon"><i class="fas ${iconClass}"></i></div>
                    <div class="item-details">
                        <div class="item-name">${item.asset_name}</div>
                        <div class="item-meta">
                            <div><strong>Mission:</strong> ${item.mission || 'Non spécifiée'}</div>
                            <div><strong>Code:</strong> ${item.serial_or_plate || 'N/A'}</div>
                            <div><strong>Code-barres:</strong> ${item.barcode || 'N/A'}</div>
                        </div>
                    </div>
                    <div class="item-actions">${actionButtonHtml}</div></div>`;
            listContainer.append(itemCardHtml);
        });
    }
    
    function performAjaxCall(action, data, successCallback = null, method = 'POST') {
        if (!successCallback) {
            showLoading($('#equipment-list-section'));
        }
        
        $.ajax({
            url: 'technician-handler.php',
            type: method,
            data: { action, ...data },
            dataType: 'json',
            success: function(response) {
                if (successCallback) {
                    successCallback(response);
                } else {
                    showNotification(response.message, response.status);
                    loadTechnicianEquipment();
                }
            },
            error: function(xhr) {
                const errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Erreur de communication.';
                showNotification(errorMsg, 'danger');
                loadTechnicianEquipment();
            }
        });
    }

    function showLoading(element) {
        element.html(`<div class="text-center p-5"><div class="spinner-border text-primary"></div><p class="mt-2">Chargement...</p></div>`);
    }

    function showNotification(message, type) {
        const modalHeader = $('#info-modal-header');
        const modalIcon = modalHeader.find('.fas');
        
        modalHeader.removeClass('bg-success bg-danger bg-info text-white');
        modalIcon.removeClass('fa-check-circle fa-exclamation-triangle fa-info-circle');

        if (type === 'success') {
            modalHeader.addClass('bg-success text-white');
            modalIcon.addClass('fa-check-circle');
            $('#info-modal-title').text('Succès');
        } else if (type === 'danger' || type === 'error') {
            modalHeader.addClass('bg-danger text-white');
            modalIcon.addClass('fa-exclamation-triangle');
            $('#info-modal-title').text('Erreur');
        } else {
            modalHeader.addClass('bg-info text-white');
            modalIcon.addClass('fa-info-circle');
            $('#info-modal-title').text('Information');
        }

        $('#info-modal-body').text(message);
        $('#info-modal').modal('show');
    }
</script>
</body>
</html>
