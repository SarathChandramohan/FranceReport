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
        .card { background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 25px; margin-bottom: 25px; border: 1px solid #e5e5e5; }
        h2 { font-weight: 600; }
        .item-list { list-style-type: none; padding: 0; }
        .item-list li { display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #e9ecef; }
        .item-list li:last-child { border-bottom: none; }
        .item-icon { font-size: 1.8em; margin-right: 20px; color: #007bff; width: 40px; text-align: center; }
        .item-details { flex-grow: 1; }
        .item-name { font-weight: 600; color: #343a40; }
        .item-meta { font-size: 0.85em; color: #6c757d; }
        .scan-section { text-align: center; padding: 20px; background-color: #f8f9fa; border-radius: 8px; }

        /* Modal for scanner */
        #scanner-modal .modal-content { padding: 20px; text-align: center; }
        #scanner-preview { width: 100%; max-width: 400px; height: auto; border: 2px solid #007bff; border-radius: 8px; margin: 15px auto; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">Mon Matériel</h2>
                    <button id="scan-to-pick-btn" class="btn btn-primary"><i class="fas fa-barcode mr-2"></i>Scanner un Article</button>
                </div>
                <div id="equipment-list-section">
                    <ul id="equipment-list" class="item-list"></ul>
                    <div id="equipment-placeholder" class="text-center p-5" style="display: none;">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun matériel assigné pour aujourd'hui.</p>
                        <p>Utilisez le bouton "Scanner un Article" pour prendre du matériel disponible.</p>
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
                <h5 class="modal-title">Scanner un Code-barres</h5>
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

        loadTechnicianEquipment();

        $('#scan-to-pick-btn').on('click', function() {
            startScanner();
        });

        $('#scanner-modal').on('hidden.bs.modal', function () {
            codeReader.reset();
            $('#scan-feedback').empty();
        });
        
        function startScanner() {
            $('#scanner-modal').modal('show');
            codeReader.decodeFromVideoDevice(undefined, 'scanner-preview', (result, err) => {
                if (result) {
                    codeReader.reset();
                    $('#scanner-modal').modal('hide');
                    handleScannedBarcode(result.text);
                }
                if (err && !(err instanceof ZXing.NotFoundException)) {
                    console.error(err);
                    $('#scan-feedback').html('<div class="alert alert-danger">Erreur de caméra.</div>');
                }
            });
        }
        
        // Use event delegation for dynamically created buttons
        $('#equipment-list').on('click', '.take-item-btn', function() {
            const button = $(this);
            const bookingId = button.data('booking-id');
            const assetId = button.data('asset-id');
            const itemName = button.closest('li').find('.item-name').text();
            if (confirm(`Confirmez-vous prendre "${itemName}" ?`)) {
                performAjaxCall('checkout_item', { booking_id: bookingId, asset_id: assetId }, "Prise du matériel...");
            }
        });

        $('#equipment-list').on('click', '.return-item-btn', function() {
            const button = $(this);
            const assetId = button.data('asset-id');
            const itemName = button.closest('li').find('.item-name').text();
            if (confirm(`Confirmez-vous retourner "${itemName}" ?`)) {
                performAjaxCall('return_item', { asset_id: assetId }, "Retour du matériel...");
            }
        });
    });

    function loadTechnicianEquipment() {
        showLoading($('#equipment-list'), 'Chargement de votre matériel...');
        $.ajax({
            url: 'technician-handler.php', type: 'GET', data: { action: 'get_technician_equipment' }, dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    renderEquipmentList(response.data.equipment);
                } else {
                    alert('Erreur: ' + response.message);
                }
            },
            error: function() {
                alert('Erreur de communication avec le serveur.');
            }
        });
    }
    
    function renderEquipmentList(items) {
        const list = $('#equipment-list');
        list.empty();
        if (!items || items.length === 0) {
            $('#equipment-placeholder').show();
            return;
        }
        $('#equipment-placeholder').hide();
        items.forEach(item => {
            const iconClass = item.asset_type === 'vehicle' ? 'fa-car' : 'fa-wrench';
            const isCheckedOut = item.status === 'in-use' && item.assigned_to_user_id == <?php echo $user['user_id']; ?>;
            
            let actionButton = '';
            if (isCheckedOut) {
                actionButton = `<button class="btn btn-info return-item-btn" data-asset-id="${item.asset_id}">Retourner</button>`;
            } else {
                actionButton = `<button class="btn btn-success take-item-btn" data-booking-id="${item.booking_id}" data-asset-id="${item.asset_id}">Prendre</button>`;
            }

            const itemHtml = `
                <li>
                    <i class="fas ${iconClass} item-icon"></i>
                    <div class="item-details">
                        <div class="item-name">${item.asset_name}</div>
                        <div class="item-meta">
                            ${item.serial_or_plate || 'N/A'} | Mission: ${item.mission || 'Non spécifiée'}
                        </div>
                    </div>
                    <div class="ml-auto">${actionButton}</div>
                </li>
            `;
            list.append(itemHtml);
        });
    }
    
    function handleScannedBarcode(barcode) {
        if (!confirm(`Vous avez scanné le code-barres "${barcode}". Voulez-vous prendre cet article ?`)) return;
        performAjaxCall('pick_item_by_barcode', { barcode: barcode }, "Traitement du scan...");
    }
    
    function performAjaxCall(action, data, loadingMessage) {
        showLoading($('#equipment-list'), loadingMessage);
        $.ajax({
            url: 'technician-handler.php', type: 'POST', data: { action, ...data }, dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                } else {
                    alert('Erreur: ' + response.message);
                }
                loadTechnicianEquipment(); // Always refresh the list
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
