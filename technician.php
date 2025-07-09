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
        .item-actions { margin-left: 1rem; }
        #scanner-modal .modal-content { text-align: center; }
        #scanner-preview { width: 100%; border-radius: 8px; }
        /* New Notification Style */
        .technician-notification {
            display: none;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            font-weight: 500;
        }
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
                    
                    <div id="notification-area" class="technician-notification"></div>

                    <div id="equipment-list-section">
                        </div>
                    <div id="equipment-placeholder" class="text-center p-5" style="display: none;">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun matériel assigné pour aujourd'hui.</p>
                    </div>
                    <hr>
                    <div class="text-center">
                         <button id="pickup-item-btn" class="btn btn-outline-primary"><i class="fas fa-barcode mr-2"></i>Prendre un Article non Assigné</button>
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let itemToProcess = null;

    document.addEventListener('DOMContentLoaded', function() {
        const codeReader = new ZXing.BrowserMultiFormatReader();

        loadTechnicianEquipment();

        $('#equipment-list-section').on('click', '.action-btn', function() {
            const button = $(this);
            itemToProcess = {
                action: button.data('action'),
                assetId: button.data('asset-id'),
                bookingId: button.data('booking-id'),
                barcode: button.data('barcode'),
                itemName: button.data('item-name')
            };
            $('#scanner-title').text(`Pour ${itemToProcess.action === 'take' ? 'Prendre' : 'Retourner'}: Scannez "${itemToProcess.itemName}"`);
            startScanner();
        });

        $('#pickup-item-btn').on('click', function() {
            itemToProcess = { action: 'pickup' };
            $('#scanner-title').text("Scannez un article disponible");
            startScanner();
        });

        $('#scanner-modal').on('hidden.bs.modal', () => {
            codeReader.reset();
            itemToProcess = null;
        });
        
        function startScanner() {
            $('#scanner-modal').modal('show');
            codeReader.decodeFromVideoDevice(undefined, 'scanner-preview', (result, err) => {
                if (result) {
                    codeReader.reset();
                    $('#scanner-modal').modal('hide');
                    processScanResult(result.text);
                }
            });
        }
    });

    function processScanResult(scannedBarcode) {
        if (!itemToProcess) return;

        let backendAction = '';
        let postData = {};

        if (itemToProcess.action === 'take' || itemToProcess.action === 'return') {
            if (scannedBarcode !== itemToProcess.barcode) {
                showNotification(`Action annulée. Le code-barres scanné ne correspond pas à "${itemToProcess.itemName}".`, 'danger');
                return;
            }
            backendAction = itemToProcess.action === 'take' ? 'checkout_item' : 'return_item';
            postData = { asset_id: itemToProcess.assetId, booking_id: itemToProcess.bookingId };
        } 
        else if (itemToProcess.action === 'pickup') {
            backendAction = 'pickup_unassigned_item';
            postData = { barcode: scannedBarcode };
        }

        if (backendAction) {
            performAjaxCall(backendAction, postData);
        }
    }

    function loadTechnicianEquipment() {
        showLoading($('#equipment-list-section'));
        $.ajax({
            url: 'technician-handler.php', type: 'GET', data: { action: 'get_technician_equipment' }, dataType: 'json',
            success: (response) => renderEquipmentList(response),
            error: () => showNotification('Erreur de communication avec le serveur.', 'danger')
        });
    }

    function renderEquipmentList(response) {
        const listContainer = $('#equipment-list-section');
        listContainer.empty();

        if (response.status !== 'success') {
            showNotification('Erreur: ' + response.message, 'danger');
            $('#equipment-placeholder').show();
            return;
        }
        
        const items = response.data.equipment;

        if (!items || items.length === 0) {
            $('#equipment-placeholder').show();
            return;
        }

        $('#equipment-placeholder').hide();
        items.forEach(item => {
            const iconClass = item.asset_type === 'vehicle' ? 'fa-car' : 'fa-wrench';
            const isTakenByMe = item.status === 'in-use' && item.assigned_to_user_id == <?php echo $user['user_id']; ?>;

            let actionButton = '';
            if (isTakenByMe) {
                actionButton = `<button class="btn btn-info action-btn" data-action="return" data-asset-id="${item.asset_id}" data-barcode="${item.barcode}" data-item-name="${item.asset_name}">Retourner</button>`;
            } else if (item.status === 'available') {
                actionButton = `<button class="btn btn-success action-btn" data-action="take" data-asset-id="${item.asset_id}" data-booking-id="${item.booking_id}" data-barcode="${item.barcode}" data-item-name="${item.asset_name}">Prendre</button>`;
            } else {
                 actionButton = `<button class="btn btn-secondary" disabled>Pris (autre)</button>`;
            }

            const itemCardHtml = `
                <div class="item-card">
                    <div class="item-icon"><i class="fas ${iconClass}"></i></div>
                    <div class="item-details">
                        <div class="item-name">${item.asset_name}</div>
                        <div class="item-meta">
                            <div><strong>Mission:</strong> ${item.mission || 'Non spécifiée'}</div>
                            <div><strong>Code:</strong> ${item.serial_or_plate || 'N/A'}</div>
                            <div><strong>Code-barres:</strong> ${item.barcode || 'N/A'}</div>
                        </div>
                    </div>
                    <div class="item-actions">${actionButton}</div>
                </div>`;
            listContainer.append(itemCardHtml);
        });
    }
    
    function performAjaxCall(action, data) {
        showLoading($('#equipment-list-section'));
        $.ajax({
            url: 'technician-handler.php', type: 'POST', data: { action, ...data }, dataType: 'json',
            success: function(response) {
                showNotification(response.message, response.status);
                loadTechnicianEquipment();
            },
            error: function(xhr) {
                let errorMsg = 'Erreur de communication lors de l\'opération.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showNotification(errorMsg, 'danger');
                loadTechnicianEquipment();
            }
        });
    }

    function showLoading(element) {
        element.html(`<div class="text-center p-5"><div class="spinner-border text-primary"></div><p class="mt-2">Chargement...</p></div>`);
    }

    /**
     * Displays a styled notification message on the page.
     * @param {string} message - The message to display.
     * @param {string} type - 'success', 'danger', or 'info'.
     */
    function showNotification(message, type) {
        const notificationArea = $('#notification-area');
        let alertClass = 'alert-info'; // Default
        if (type === 'success') alertClass = 'alert-success';
        if (type === 'error' || type === 'danger') alertClass = 'alert-danger';

        notificationArea.removeClass('alert-success alert-danger alert-info').addClass(alertClass);
        notificationArea.text(message).slideDown(300);

        // Hide after 5 seconds
        setTimeout(() => {
            notificationArea.slideUp(300);
        }, 5000);
    }
</script>
</body>
</html>
