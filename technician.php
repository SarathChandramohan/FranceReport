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
    <title>Gestion du Matériel Technicien</title>
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
        .item-status { font-size: 0.8em; font-weight: bold; padding: 3px 8px; border-radius: 12px; }
        .status-in-use { background-color: #d4edda; color: #155724; }
        .custom-checkbox .custom-control-input:checked~.custom-control-label::before { background-color: #28a745; border-color: #28a745; }
        .action-buttons { display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; padding: 20px 0; }
        .scan-section { text-align: center; padding: 20px; background-color: #f8f9fa; border-radius: 8px; }
        
        /* Modal for scanner */
        #scanner-modal .modal-content { padding: 20px; text-align: center; }
        #scanner-preview { width: 100%; max-width: 400px; height: auto; border: 2px solid #007bff; border-radius: 8px; margin: 15px auto; }
        
        @media (max-width: 576px) {
            .item-list li { flex-direction: column; align-items: flex-start; gap: 10px; }
            .action-buttons { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <h2 class="mb-4">Mon Matériel du Jour</h2>
                <div id="assigned-items-section">
                    <ul id="assigned-items-list" class="item-list"></ul>
                    <div id="assigned-items-placeholder" class="text-center p-5" style="display: none;">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun matériel assigné pour aujourd'hui.</p>
                    </div>
                </div>
                <div class="action-buttons mt-4">
                    <button id="checkout-btn" class="btn btn-success btn-lg"><i class="fas fa-check-circle mr-2"></i>Prendre le Matériel Sélectionné</button>
                    <button id="return-btn" class="btn btn-info btn-lg"><i class="fas fa-undo-alt mr-2"></i>Retourner Tout Mon Matériel</button>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <h2 class="mb-4">Prendre du Matériel</h2>
                <div class="scan-section">
                     <p class="text-muted">Scannez un article disponible pour le prendre immédiatement.</p>
                    <button id="scan-to-pick-btn" class="btn btn-primary btn-block btn-lg"><i class="fas fa-barcode mr-2"></i>Scanner pour Prendre</button>
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
                <video id="scanner-preview"></video>
                <div id="scan-feedback" class="mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const codeReader = new ZXing.BrowserMultiFormatReader();
        let currentScanAction = null;

        loadTechnicianData();

        $('#checkout-btn').on('click', handleCheckout);
        $('#return-btn').on('click', handleReturn);
        $('#scan-to-pick-btn').on('click', function() {
            currentScanAction = 'pick_item';
            startScanner();
        });

        $('#scanner-modal').on('hidden.bs.modal', function () {
            codeReader.reset();
            currentScanAction = null;
            $('#scan-feedback').empty();
        });
        
        function startScanner() {
            $('#scanner-modal').modal('show');
            codeReader.decodeFromVideoDevice(undefined, 'scanner-preview', (result, err) => {
                if (result) {
                    codeReader.reset();
                    $('#scanner-modal').modal('hide');
                    if (currentScanAction === 'pick_item') {
                        handlePickItem(result.text);
                    }
                }
                if (err && !(err instanceof ZXing.NotFoundException)) {
                    console.error(err);
                    $('#scan-feedback').html('<div class="alert alert-danger">Erreur de caméra.</div>');
                }
            });
        }
    });

    function loadTechnicianData() {
        showLoading($('#assigned-items-list'), 'Chargement du matériel assigné...');
        $.ajax({
            url: 'technician-handler.php', type: 'GET', data: { action: 'get_technician_data' }, dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    renderAssignedItems(response.data.assigned_items);
                } else {
                    alert('Erreur: ' + response.message);
                }
            },
            error: function() {
                alert('Erreur de communication avec le serveur.');
            }
        });
    }
    
    function renderAssignedItems(items) {
        const list = $('#assigned-items-list');
        list.empty();
        if (!items || items.length === 0) {
            $('#assigned-items-placeholder').show();
            return;
        }
        $('#assigned-items-placeholder').hide();
        items.forEach(item => {
            const iconClass = item.asset_type === 'vehicle' ? 'fa-car' : 'fa-wrench';
            const isCheckedOut = item.status === 'in-use' && item.assigned_to_user_id == <?php echo $user['user_id']; ?>;

            const itemHtml = `
                <li>
                    <i class="fas ${iconClass} item-icon"></i>
                    <div class="item-details">
                        <div class="item-name">${item.asset_name}</div>
                        <div class="item-meta">
                            ${item.serial_or_plate || 'N/A'} | Pour mission: ${item.mission || 'Non spécifiée'}
                        </div>
                    </div>
                    ${ isCheckedOut
                        ? `<span class="item-status status-in-use">PRIS</span>`
                        : `<div class="custom-control custom-checkbox ml-3">
                               <input type="checkbox" class="custom-control-input checkout-checkbox" id="item-${item.booking_id}" value="${item.asset_id}" data-booking-id="${item.booking_id}">
                               <label class="custom-control-label" for="item-${item.booking_id}">Prendre</label>
                           </div>`
                    }
                </li>
            `;
            list.append(itemHtml);
        });
    }

    function handleCheckout() {
        const selectedItems = $('.checkout-checkbox:checked').map(function() {
            return { asset_id: $(this).val(), booking_id: $(this).data('booking-id') };
        }).get();

        if (selectedItems.length === 0) {
            alert("Veuillez sélectionner au moins un article à prendre.");
            return;
        }

        if (!confirm(`Confirmez-vous prendre les ${selectedItems.length} article(s) sélectionné(s) ?`)) return;

        performAjaxCall('checkout_items', { items: JSON.stringify(selectedItems) }, "Prise du matériel en cours...");
    }

    function handleReturn() {
        if (!confirm("Êtes-vous sûr de vouloir retourner tout le matériel que vous avez pris ?")) return;
        performAjaxCall('return_my_items', {}, "Retour du matériel en cours...");
    }

    function handlePickItem(barcode) {
        if (!confirm(`Voulez-vous prendre l'article avec le code-barres "${barcode}" ?`)) return;
        performAjaxCall('pick_item_by_barcode', { barcode: barcode }, "Traitement du scan en cours...");
    }
    
    function performAjaxCall(action, data, loadingMessage) {
        showLoading($('#assigned-items-list'), loadingMessage);
        $.ajax({
            url: 'technician-handler.php', type: 'POST', data: { action, ...data }, dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    loadTechnicianData();
                } else {
                    alert('Erreur: ' + response.message);
                    loadTechnicianData(); // Refresh list even on error
                }
            },
            error: function() {
                alert('Erreur de communication lors de l\'opération.');
                loadTechnicianData();
            }
        });
    }

    function showLoading(element, message) {
        element.html(`<li class="text-center text-muted p-4"><div class="spinner-border spinner-border-sm mr-2"></div>${message}</li>`);
    }

</script>
</body>
</html>
