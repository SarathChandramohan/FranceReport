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
        
        /* New Item Card Style */
        .item-card {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 1rem;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .item-icon {
            font-size: 2em;
            color: #007bff;
            margin-right: 1.5rem;
            width: 45px;
            text-align: center;
        }
        .item-details {
            flex-grow: 1;
        }
        .item-name {
            font-weight: 600;
            font-size: 1.1rem;
            color: #343a40;
        }
        .item-meta {
            font-size: 0.9em;
            color: #6c757d;
            line-height: 1.5;
        }
        .item-meta strong {
            color: #495057;
        }
        .item-actions {
            margin-left: 1rem;
        }

        #scanner-modal .modal-content { text-align: center; }
        #scanner-preview { width: 100%; border-radius: 8px; }
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
    document.addEventListener('DOMContentLoaded', function() {
        const codeReader = new ZXing.BrowserMultiFormatReader();
        let itemToProcess = null; 

        loadTechnicianEquipment();

        // Event handler for all action buttons using delegation
        $('#equipment-list-section').on('click', '.action-btn', function() {
            const button = $(this);
            itemToProcess = {
                action: button.data('action'), // 'take' or 'return'
                assetId: button.data('asset-id'),
                bookingId: button.data('booking-id'),
                barcode: button.data('barcode'),
                itemName: button.data('item-name')
            };
            $('#scanner-title').text(`Pour ${itemToProcess.action === 'take' ? 'Prendre' : 'Retourner'}: Scannez "${itemToProcess.itemName}"`);
            startScanner();
        });

        // Event handler for the new global pickup button
        $('#pickup-item-btn').on('click', function() {
            itemToProcess = { action: 'pickup' }; // Special action type
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

        // Case 1 & 2: Taking or Returning an ASSIGNED item
        if (itemToProcess.action === 'take' || itemToProcess.action === 'return') {
            if (scannedBarcode !== itemToProcess.barcode) {
                alert(`Action annulée. Mauvais article scanné.`);
                return;
            }
            backendAction = itemToProcess.action === 'take' ? 'checkout_item' : 'return_item';
            postData = { asset_id: itemToProcess.assetId, booking_id: itemToProcess.bookingId };
        } 
        // Case 3: Picking up an UNASSIGNED item
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
            error: () => alert('Erreur de communication avec le serveur.')
        });
    }

    function renderEquipmentList(response) {
        if (response.status !== 'success') {
            alert('Erreur: ' + response.message);
            return;
        }
        const items = response.data.equipment;
        const listContainer = $('#equipment-list-section');
        listContainer.empty();

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

            // New Card Layout
            const itemCardHtml = `
                <div class="item-card">
                    <div class="item-icon">
                        <i class="fas ${iconClass}"></i>
                    </div>
                    <div class="item-details">
                        <div class="item-name">${item.asset_name}</div>
                        <div class="item-meta">
                            <div><strong>Mission:</strong> ${item.mission || 'Non spécifiée'}</div>
                            <div><strong>Code:</strong> ${item.serial_or_plate || 'N/A'}</div>
                            <div><strong>Code-barres:</strong> ${item.barcode || 'N/A'}</div>
                        </div>
                    </div>
                    <div class="item-actions">
                        ${actionButton}
                    </div>
                </div>`;
            listContainer.append(itemCardHtml);
        });
    }
    
    function performAjaxCall(action, data) {
        showLoading($('#equipment-list-section'));
        $.ajax({
            url: 'technician-handler.php', type: 'POST', data: { action, ...data }, dataType: 'json',
            success: function(response) {
                alert(response.message);
                loadTechnicianEquipment(); // Always refresh the list
            },
            error: function() {
                alert('Erreur de communication lors de l\'opération.');
                loadTechnicianEquipment();
            }
        });
    }

    function showLoading(element) {
        element.html(`<div class="text-center p-5"><div class="spinner-border text-primary"></div><p class="mt-2">Chargement...</p></div>`);
    }
</script>
</body>
</html>
