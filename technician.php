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
        .card { background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e5e5e5; }
        h2 { font-weight: 600; }
        .item-list { list-style-type: none; padding: 0; }
        .item-list li { display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #e9ecef; }
        .item-list li:last-child { border-bottom: none; }
        .item-icon { font-size: 1.8em; margin-right: 20px; color: #007bff; width: 40px; text-align: center; }
        .item-details { flex-grow: 1; }
        .item-name { font-weight: 600; color: #343a40; }
        .item-meta { font-size: 0.85em; color: #6c757d; }
        #scanner-modal .modal-content { text-align: center; }
        #scanner-preview { width: 100%; border-radius: 8px; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title mb-4">Mon Matériel de Mission</h2>
                    <div id="equipment-list-section">
                        <ul id="equipment-list" class="item-list"></ul>
                        <div id="equipment-placeholder" class="text-center p-5" style="display: none;">
                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Aucun matériel assigné pour aujourd'hui.</p>
                        </div>
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
                <div id="scan-feedback" class="mt-2"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const codeReader = new ZXing.BrowserMultiFormatReader();
        let itemToProcess = null; // Holds data for the item being taken/returned

        loadTechnicianEquipment();

        // Use event delegation for dynamically created buttons
        $('#equipment-list').on('click', '.action-btn', function() {
            const button = $(this);
            itemToProcess = {
                action: button.data('action'),
                assetId: button.data('asset-id'),
                bookingId: button.data('booking-id'),
                barcode: button.data('barcode'),
                itemName: button.data('item-name')
            };
            
            $('#scanner-title').text(`Scannez "${itemToProcess.itemName}"`);
            startScanner();
        });

        $('#scanner-modal').on('hidden.bs.modal', () => {
            codeReader.reset();
            itemToProcess = null; // Clear the item after modal closes
        });
        
        function startScanner() {
            $('#scanner-modal').modal('show');
            codeReader.decodeFromVideoDevice(undefined, 'scanner-preview', (result, err) => {
                if (result) {
                    codeReader.reset();
                    $('#scanner-modal').modal('hide');
                    confirmActionWithScan(result.text);
                }
                if (err && !(err instanceof ZXing.NotFoundException)) {
                    $('#scan-feedback').html('<div class="alert alert-danger">Erreur de caméra.</div>');
                }
            });
        }
    });

    function confirmActionWithScan(scannedBarcode) {
        if (!itemToProcess) return;

        // Check if the scanned barcode matches the item we intend to process
        if (scannedBarcode !== itemToProcess.barcode) {
            alert(`Action annulée. Mauvais article scanné. Vous avez scanné un article différent de "${itemToProcess.itemName}".`);
            itemToProcess = null;
            return;
        }

        // Barcode matches, proceed with the action
        let backendAction = '';
        let postData = {};

        if (itemToProcess.action === 'take') {
            backendAction = 'checkout_item';
            postData = { asset_id: itemToProcess.assetId, booking_id: itemToProcess.bookingId };
        } else if (itemToProcess.action === 'return') {
            backendAction = 'return_item';
            postData = { asset_id: itemToProcess.assetId };
        }
        
        performAjaxCall(backendAction, postData, "Traitement en cours...");
    }

    function loadTechnicianEquipment() {
        showLoading($('#equipment-list'), 'Chargement de votre matériel...');
        $.ajax({
            url: 'technician-handler.php', type: 'GET', data: { action: 'get_technician_equipment' }, dataType: 'json',
            success: (response) => renderEquipmentList(response),
            error: () => alert('Erreur de communication avec le serveur.')
        });
    }

    function renderEquipmentList(response) {
        if (response.status !== 'success') {
            alert('Erreur: ' + response.message);
            return;
        }
        const items = response.data.equipment;
        const list = $('#equipment-list');
        list.empty();

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
                actionButton = `<button class="btn btn-info action-btn" 
                                        data-action="return" 
                                        data-asset-id="${item.asset_id}" 
                                        data-barcode="${item.barcode}"
                                        data-item-name="${item.asset_name}">Retourner</button>`;
            } else if (item.status === 'available') {
                actionButton = `<button class="btn btn-success action-btn" 
                                        data-action="take" 
                                        data-asset-id="${item.asset_id}" 
                                        data-booking-id="${item.booking_id}"
                                        data-barcode="${item.barcode}"
                                        data-item-name="${item.asset_name}">Prendre</button>`;
            } else {
                 actionButton = `<button class="btn btn-secondary" disabled>Pris (autre)</button>`;
            }

            const itemHtml = `
                <li>
                    <i class="fas ${iconClass} item-icon"></i>
                    <div class="item-details">
                        <div class="item-name">${item.asset_name}</div>
                        <div class="item-meta">
                           Code: ${item.barcode || 'N/A'} | Mission: ${item.mission || 'Non spécifiée'}
                        </div>
                    </div>
                    <div class="ml-auto">${actionButton}</div>
                </li>`;
            list.append(itemHtml);
        });
    }
    
    function performAjaxCall(action, data, loadingMessage) {
        showLoading($('#equipment-list'), loadingMessage);
        $.ajax({
            url: 'technician-handler.php', type: 'POST', data: { action, ...data }, dataType: 'json',
            success: function(response) {
                alert(response.message);
                loadTechnicianEquipment();
            },
            error: function() {
                alert('Erreur de communication lors de l\'opération.');
                loadTechnicianEquipment();
            }
        });
    }

    function showLoading(element, message) {
        element.html(`<li class="text-center text-muted p-4"><div class="spinner-border spinner-border-sm mr-2"></div>${message}</li>`);
    }
</script>
</body>
</html>
