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
    <style>
        body { background-color: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .card { background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 25px; margin-bottom: 25px; border: 1px solid #e5e5e5; }
        h2 { font-weight: 600; }
        .item-list { list-style-type: none; padding: 0; }
        .item-list li {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.2s ease;
        }
        .item-list li:last-child { border-bottom: none; }
        .item-list li:hover { background-color: #f8f9fa; }
        .item-icon { font-size: 1.8em; margin-right: 20px; color: #007bff; width: 40px; text-align: center; }
        .item-details { flex-grow: 1; }
        .item-name { font-weight: 600; color: #343a40; }
        .item-meta { font-size: 0.85em; color: #6c757d; }
        .item-status { font-size: 0.8em; font-weight: bold; padding: 3px 8px; border-radius: 12px; }
        .status-in-use { background-color: #d4edda; color: #155724; }
        .status-booked { background-color: #d1ecf1; color: #0c5460; }
        .custom-checkbox .custom-control-input:checked~.custom-control-label::before { background-color: #28a745; border-color: #28a745; }
        .action-buttons {
            display: flex;
            gap: 15px; /* Spacing between buttons */
            justify-content: center;
            flex-wrap: wrap; /* Allow buttons to wrap on small screens */
            padding: 20px 0;
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .item-list li { flex-direction: column; align-items: flex-start; gap: 10px; }
            .item-icon { margin-right: 0; margin-bottom: 10px; }
            .action-buttons { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <h2 class="mb-4">Mon Matériel du Jour</h2>
                <div id="assigned-items-section">
                    <ul id="assigned-items-list" class="item-list">
                        </ul>
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

        <div class="col-lg-5">
            <div class="card">
                <h2 class="mb-4">Prendre du Matériel Disponible</h2>
                <div id="available-items-section">
                    <ul id="available-items-list" class="item-list">
                        </ul>
                    <div id="available-items-placeholder" class="text-center p-5" style="display: none;">
                        <i class="fas fa-store-slash fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun matériel disponible pour le moment.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        loadTechnicianData();

        $('#checkout-btn').on('click', handleCheckout);
        $('#return-btn').on('click', handleReturn);
    });

    function showLoading(listElement, message) {
        listElement.html(`<li class="text-center text-muted p-4"><div class="spinner-border spinner-border-sm mr-2"></div>${message}</li>`);
    }

    function showPlaceholder(placeholderElement) {
        placeholderElement.show();
    }

    function loadTechnicianData() {
        showLoading($('#assigned-items-list'), 'Chargement du matériel assigné...');
        showLoading($('#available-items-list'), 'Chargement du matériel disponible...');

        $.ajax({
            url: 'technician-handler.php',
            type: 'GET',
            data: { action: 'get_technician_data' },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    renderAssignedItems(response.data.assigned_items);
                    renderAvailableItems(response.data.available_items);
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
            showPlaceholder($('#assigned-items-placeholder'));
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
                               <input type="checkbox" class="custom-control-input" id="item-${item.asset_id}" value="${item.asset_id}" data-booking-id="${item.booking_id}">
                               <label class="custom-control-label" for="item-${item.asset_id}">Prendre</label>
                           </div>`
                    }
                </li>
            `;
            list.append(itemHtml);
        });
    }

    function renderAvailableItems(items) {
        const list = $('#available-items-list');
        list.empty();
        if (!items || items.length === 0) {
            showPlaceholder($('#available-items-placeholder'));
            return;
        }
        $('#available-items-placeholder').hide();
        items.forEach(item => {
            const iconClass = item.asset_type === 'vehicle' ? 'fa-car' : 'fa-wrench';
            const itemHtml = `
                <li>
                    <i class="fas ${iconClass} item-icon"></i>
                    <div class="item-details">
                        <div class="item-name">${item.asset_name}</div>
                        <div class="item-meta">${item.serial_or_plate || 'N/A'}</div>
                    </div>
                    <button class="btn btn-sm btn-outline-primary pick-item-btn" data-asset-id="${item.asset_id}">
                        <i class="fas fa-hand-paper mr-1"></i>Prendre
                    </button>
                </li>
            `;
            list.append(itemHtml);
        });

        $('.pick-item-btn').on('click', handlePickItem);
    }

    function handleCheckout() {
        const selectedItems = [];
        $('#assigned-items-list input[type="checkbox"]:checked').each(function() {
            selectedItems.push({
                asset_id: $(this).val(),
                booking_id: $(this).data('booking-id')
            });
        });

        if (selectedItems.length === 0) {
            alert("Veuillez sélectionner au moins un article à prendre.");
            return;
        }

        if (!confirm(`Confirmez-vous prendre les ${selectedItems.length} article(s) sélectionné(s) ?`)) {
            return;
        }

        $.ajax({
            url: 'technician-handler.php',
            type: 'POST',
            data: {
                action: 'checkout_items',
                items: JSON.stringify(selectedItems)
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    loadTechnicianData();
                } else {
                    alert('Erreur: ' + response.message);
                }
            },
            error: function() {
                alert('Erreur de communication lors de la prise du matériel.');
            }
        });
    }

    function handleReturn() {
        if (!confirm("Êtes-vous sûr de vouloir retourner tout le matériel que vous avez pris ?")) {
            return;
        }

        $.ajax({
            url: 'technician-handler.php',
            type: 'POST',
            data: { action: 'return_my_items' },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    loadTechnicianData();
                } else {
                    alert('Erreur: ' + response.message);
                }
            },
            error: function() {
                alert('Erreur de communication lors du retour du matériel.');
            }
        });
    }

    function handlePickItem(event) {
        const assetId = $(event.currentTarget).data('asset-id');
        const assetName = $(event.currentTarget).closest('li').find('.item-name').text();

        if (!confirm(`Voulez-vous prendre "${assetName}" maintenant ? L'article sera réservé et sorti à votre nom pour aujourd'hui.`)) {
            return;
        }

        $.ajax({
            url: 'technician-handler.php',
            type: 'POST',
            data: { action: 'pick_item', asset_id: assetId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    loadTechnicianData();
                } else {
                    alert('Erreur: ' + response.message);
                }
            },
            error: function() {
                alert('Erreur de communication lors de la prise de l\'article.');
            }
        });
    }

</script>
</body>
</html>
