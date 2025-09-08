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
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <style>
        :root {
            --primary-color: #007aff;
            --primary-hover: #0056b3;
            --secondary-color: #6c757d;
            --background-light: #f5f5f7;
            --card-bg: #ffffff;
            --text-dark: #1d1d1f;
            --text-medium: #495057;
            --text-light: #6e6e73;
            --border-color: #e5e5e5;
            --shadow-light: rgba(0, 0, 0, 0.05);
            --shadow-medium: rgba(0, 0, 0, 0.10);
        }

        body {
            background-color: var(--background-light);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
            color: var(--text-dark);
        }

        .main-card {
            background-color: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 8px 24px var(--shadow-medium);
            border: 1px solid var(--border-color);
            margin-top: 25px;
            padding: 30px;
        }

        h2.card-title {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 1.8rem;
            margin-bottom: 30px;
        }

        .item-card {
            display: flex;
            align-items: center;
            padding: 1.2rem 1.5rem;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 15px;
            background-color: var(--card-bg);
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px var(--shadow-light);
        }

        .item-card:hover {
            box-shadow: 0 6px 20px var(--shadow-medium);
            transform: translateY(-3px);
        }

        .item-card.overdue {
            background-color: #fff4e5;
            border-left: 5px solid #ff9500;
        }

        .item-icon {
            font-size: 2.5em;
            color: var(--primary-color);
            margin-right: 1.8rem;
            width: 60px;
            text-align: center;
            flex-shrink: 0;
        }

        .item-details { flex-grow: 1; }

        .item-name {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .item-meta {
            font-size: 0.95em;
            color: var(--text-light);
            line-height: 1.5;
        }

        .item-meta strong { color: var(--text-dark); }

        .item-actions {
            margin-left: 1.5rem;
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 130px;
            flex-shrink: 0;
        }

        /* Consistent Button Styling */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease-in-out;
            white-space: nowrap;
        }

        .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); color: white; }
        .btn-primary:hover { background-color: var(--primary-hover); border-color: var(--primary-hover); }
        .btn-info { background-color: #34aadc; border-color: #34aadc; color: white; }
        .btn-info:hover { background-color: #2795c6; border-color: #2795c6; }
        .btn-success { background-color: #34c759; border-color: #34c759; color: white; }
        .btn-success:hover { background-color: #2ca048; border-color: #2ca048; }
        .btn-warning { background-color: #ff9500; border-color: #ff9500; color: white; }
        .btn-warning:hover { background-color: #d97e00; border-color: #d97e00; }
        .btn-outline-secondary { border-color: var(--secondary-color); color: var(--secondary-color); background-color: transparent; }
        .btn-outline-secondary:hover { background-color: var(--secondary-color); color: white; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        
        .manual-entry-link {
            font-size: 0.85em;
            color: var(--primary-color);
            cursor: pointer;
            display: block;
            margin-top: 5px;
            text-align: center;
        }

        #scanner-preview {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background-color: #000;
        }
        
        /* Placeholder styles */
        #equipment-placeholder {
            padding: 60px;
            border: 2px dashed #ced4da;
            border-radius: 12px;
            background-color: #f8f9fa;
        }
        #equipment-placeholder .fas { font-size: 3.5em; color: #b0b0b0; }
        #equipment-placeholder p { font-size: 1.1rem; color: var(--text-light); margin-top: 20px; }

        /* Fuel Indicator */
        .fuel-indicator { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: var(--text-medium); }
        .fuel-icon { font-size: 1.5em; position: relative; color: #d2d2d7; }
        .fuel-icon .fuel-fill { position: absolute; left: 0; top: 0; width: 100%; height: var(--fill-percent, 0%); overflow: hidden; color: #34c759; transition: height 0.3s; }
        .fuel-icon.low .fuel-fill { color: #ff3b30; }


        /* =============================================== */
        /* =========== MOBILE OPTIMIZATIONS ============ */
        /* =============================================== */

        @media (max-width: 767px) {
            body {
                font-size: 15px;
            }

            .container-fluid {
                padding: 0;
            }

            .main-card {
                margin-top: 0;
                padding: 15px;
                box-shadow: none;
                border-radius: 0;
                border: none;
                border-bottom: 1px solid var(--border-color);
            }

            h2.card-title {
                font-size: 1.5rem;
                margin-bottom: 15px;
                text-align: center;
            }
            
            #equipment-list-section {
                padding: 0 10px;
            }

            .item-card {
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem;
            }

            /* Wrapper for icon and details for better vertical layout */
            .item-header {
                display: flex;
                align-items: center;
                width: 100%;
                margin-bottom: 15px;
            }

            .item-icon {
                font-size: 1.8em;
                margin-right: 1rem;
                width: 45px;
            }
            
            .item-name {
                font-size: 1.1rem;
                line-height: 1.3;
            }

            .item-details {
                width: 100%;
            }

            .item-meta {
                padding-left: 0; /* Remove desktop indent */
                font-size: 0.9em;
                border-top: 1px solid var(--border-color);
                padding-top: 15px;
            }

            .item-actions {
                width: 100%;
                margin-left: 0;
                margin-top: 15px;
                flex-direction: column;
                gap: 10px;
            }
            
            .item-actions .manual-entry-link {
                margin-top: -5px;
                margin-bottom: 5px;
            }
            
            /* Main pickup action buttons at the bottom */
            #pickup-container {
                display: flex;
                flex-direction: column;
                gap: 10px;
                padding: 15px;
                background-color: var(--card-bg);
            }
            
            #pickup-container .btn {
                width: 100%;
                margin-left: 0 !important; /* Override bootstrap margin helper */
            }
        }

    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="main-card">
                <div class="card-body p-md-4 p-0">
                    <h2 class="card-title mb-4">Mon Matériel de Mission</h2>

                    <div id="equipment-list-section"></div>

                    <div id="equipment-placeholder" class="text-center p-5" style="display: none;">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun matériel assigné ou en votre possession.</p>
                    </div>

                    <hr class="my-4 d-none d-md-block">
                    <div id="pickup-container" class="text-center">
                         <h5 class="text-secondary mb-3 d-none d-md-block">Prendre un nouvel article</h5>
                         <button id="pickup-item-scan-btn" class="btn btn-primary btn-lg"><i class="fas fa-barcode mr-2"></i>Scanner un Article</button>
                         <button id="pickup-item-manual-btn" class="btn btn-outline-secondary"><i class="fas fa-keyboard mr-2"></i>Saisie Manuelle</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- All Modals remain unchanged -->
<div class="modal fade" id="scanner-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scanner-title"><i class="fas fa-barcode"></i>Scanner un Code-barres</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body text-center">
                <video id="scanner-preview" playsinline></video>
                <canvas id="qr-canvas" hidden></canvas>
                <p class="text-muted mt-2">Veuillez aligner le code-barres avec la caméra.</p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="manual-entry-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manual-entry-title"><i class="fas fa-keyboard"></i>Saisie Manuelle</h5>
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
                <h5 class="modal-title"><i class="fas fa-calendar-check"></i>Réserver un Article</h5>
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

<div class="modal fade" id="fuel-level-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-gas-pump"></i>Niveau de Carburant au Retour</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Veuillez indiquer le niveau de carburant actuel pour <strong><span id="fuel-modal-item-name"></span></strong>.</p>
                <div class="d-flex flex-wrap justify-content-center fuel-level-options">
                    <button type="button" class="btn btn-outline-secondary" data-fuel-level="full">Plein <i class="fas fa-gas-pump"></i></button>
                    <button type="button" class="btn btn-outline-secondary" data-fuel-level="three-quarter">3/4</button>
                    <button type="button" class="btn btn-outline-secondary" data-fuel-level="half">Moitié</button>
                    <button type="button" class="btn btn-outline-secondary" data-fuel-level="quarter">1/4</button>
                    <button type="button" class="btn btn-outline-secondary" data-fuel-level="empty">Vide</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="report-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-flag"></i>Signaler un problème</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="report-form">
                    <input type="hidden" id="report-asset-id">
                    <p>Vous signalez un problème pour : <strong><span id="report-item-name"></span></strong>.</p>
                    <div class="form-group">
                        <label for="report-type">Type de problème *</label>
                        <select id="report-type" class="form-control" required>
                            <option value="">Sélectionner...</option>
                            <option value="missing">manquant</option>
                            <option value="repair">à réparer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="report-comments">Commentaires</label>
                        <textarea id="report-comments" class="form-control" rows="3" placeholder="Décrivez le problème..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="submit-report-btn">Envoyer le rapport</button>
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
    let selectedFuelLevel = null;
    let actionAfterFuelModal = null;
    let returnDatePicker = null;
    const currentUserId = <?php echo $user['user_id']; ?>;

    // This function runs when the page is fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        loadTechnicianEquipment();

        $('#equipment-list-section').on('click', '.action-btn, .manual-entry-link, .report-btn', function(e) {
            e.preventDefault();
            prepareAction($(this));

            if ($(this).hasClass('report-btn')) {
                $('#report-asset-id').val(itemToProcess.assetId);
                $('#report-item-name').text(itemToProcess.itemName);
                $('#report-modal').modal('show');
                return;
            }
            
            const isManual = $(this).hasClass('manual-entry-link');

            if (itemToProcess.action === 'return' && itemToProcess.itemType === 'vehicle') {
                actionAfterFuelModal = isManual ? 'manual' : 'scan';
                selectedFuelLevel = null; // Reset
                $('#fuel-modal-item-name').text(itemToProcess.itemName);
                $('#fuel-level-modal').modal('show');
            } else if (itemToProcess.action === 'return') { // This is a tool return
                if (isManual) {
                    showManualEntryModal(`Pour retourner "${itemToProcess.itemName}", entrez son code-barres.`);
                } else {
                    $('#scanner-title').text(`Pour retourner: Scannez "${itemToProcess.itemName}"`);
                    startScanner();
                }
            } else { // This is any 'take' action
                 if (isManual) {
                    showManualEntryModal(`Pour prendre "${itemToProcess.itemName}", entrez son code-barres.`);
                } else {
                    $('#scanner-title').text(`Pour Prendre: Scannez "${itemToProcess.itemName}"`);
                    startScanner();
                }
            }
        });
        
        $('.fuel-level-options .btn').on('click', function() {
            selectedFuelLevel = $(this).data('fuel-level');
            $('#fuel-level-modal').modal('hide');

            if (actionAfterFuelModal === 'manual') {
                showManualEntryModal(`Pour retourner "${itemToProcess.itemName}", entrez son code-barres.`);
            } else {
                $('#scanner-title').text(`Pour retourner: Scannez "${itemToProcess.itemName}"`);
                startScanner();
            }
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

        $('#submit-report-btn').on('click', function() {
            const assetId = $('#report-asset-id').val();
            const reportType = $('#report-type').val();
            const comments = $('#report-comments').val();

            if (!reportType) {
                showNotification("Veuillez sélectionner un type de problème.", "danger");
                return;
            }

            performAjaxCall('report_item', {
                asset_id: assetId,
                report_type: reportType,
                comments: comments
            }, null, 'POST', true);

            $('#report-modal').modal('hide');
        });
        
        $('body').on('hidden.bs.modal', function () {
            setTimeout(function() {
                if (!$('.modal').is(':visible')) {
                    if(typeof codeReader !== 'undefined' && codeReader) codeReader.reset();
                    $('#manual-entry-form')[0].reset();
                    $('#report-form')[0].reset();
                    itemToProcess = null;
                    selectedFuelLevel = null;
                    actionAfterFuelModal = null;
                }
            }, 500);
        });
    });

    function prepareAction(button) {
        itemToProcess = {
            action: button.data('action'),
            assetId: button.data('asset-id'),
            bookingId: button.data('booking-id'),
            barcode: button.data('barcode'),
            itemName: button.data('item-name'),
            itemType: button.data('item-type')
        };
    }

    function showManualEntryModal(promptText) {
        $('#manual-entry-title').text('Saisie Manuelle');
        $('#manual-entry-prompt').text(promptText);
        $('#manual-entry-modal').modal('show');
    }

    function startScanner() {
        $('#scanner-modal').modal('show');
        const video = document.getElementById("scanner-preview");
        const canvasElement = document.getElementById("qr-canvas");
        const canvas = canvasElement.getContext("2d");

        navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } }).then(function(stream) {
            video.srcObject = stream;
            video.setAttribute("playsinline", true);
            video.play();
            requestAnimationFrame(tick);
        }).catch(function(err) {
            console.error("Error accessing camera:", err);
            $('#scanner-modal').modal('hide');
            showNotification("Impossible d'accéder à la caméra. Veuillez vérifier les permissions.", "danger");
        });

        function tick() {
            if (!$('#scanner-modal').is(':visible')) {
                video.srcObject.getTracks().forEach(track => track.stop());
                return;
            }
            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                canvasElement.height = video.videoHeight;
                canvasElement.width = video.videoWidth;
                canvas.drawImage(video, 0, 0, canvasElement.width, canvasElement.height);
                var imageData = canvas.getImageData(0, 0, canvasElement.width, canvasElement.height);
                var code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: "dontInvert" });

                if (code) {
                    video.srcObject.getTracks().forEach(track => track.stop());
                    $('#scanner-modal').modal('hide');
                    processAction(code.data);
                    return;
                }
            }
            requestAnimationFrame(tick);
        }
    }

    function processAction(barcode) {
        if (!itemToProcess) return;

        if (itemToProcess.action === 'pickup') {
            handleItemPickup(barcode);
        } else {
            if (barcode.trim() !== String(itemToProcess.barcode)) {
                showNotification(`Action annulée. Le code-barres ne correspond pas à "${itemToProcess.itemName}".`, 'danger');
                return;
            }
            const backendAction = itemToProcess.action === 'take' ? 'checkout_item' : 'return_item';
            const postData = { asset_id: itemToProcess.assetId, booking_id: itemToProcess.bookingId };
            
            if (selectedFuelLevel && backendAction === 'return_item') {
                postData.fuel_level = selectedFuelLevel;
            }
            
            performAjaxCall(backendAction, postData);
        }
    }

    function handleItemPickup(barcode) {
        performAjaxCall('get_item_availability_for_pickup', { barcode: barcode }, (response) => {
            const asset = response.data.asset;
            const nextBookingDate = response.data.next_booking_date;

            if (response.data.booked_today) {
                showNotification(`Cet article (${asset.asset_name}) est déjà réservé pour aujourd'hui et ne peut pas être pris.`, 'danger');
                return;
            }

            $('#range-booking-item-name').text(asset.asset_name);
            $('#range-booking-modal').modal('show');

            if (returnDatePicker) returnDatePicker.destroy();

            let flatpickrConfig = {
                locale: "fr",
                minDate: "today",
                dateFormat: "Y-m-d",
            };

            if (nextBookingDate) {
                let maxDate = new Date(nextBookingDate);
                maxDate.setDate(maxDate.getDate() - 1);
                flatpickrConfig.maxDate = maxDate;
            }

            returnDatePicker = flatpickr("#return-date-calendar", flatpickrConfig);

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
                performAjaxCall('book_and_pickup_range', {
                    asset_id: asset.asset_id,
                    return_date: returnDateStr,
                    assignment_name: assignmentName
                });
            });
        });
    }

    function loadTechnicianEquipment() {
        showLoading($('#equipment-list-section'));
        performAjaxCall('get_technician_equipment', {}, renderEquipmentList, 'GET');
    }

    function generateFuelIndicatorHTML(fuelLevel) {
        if (!fuelLevel) return 'N/A';
        const fuelLevels = {
            'full': { percent: 100, text: 'Plein', low: false }, 'three-quarter': { percent: 75, text: '3/4', low: false },
            'half': { percent: 50, text: 'Moitié', low: false }, 'quarter': { percent: 25, text: '1/4', low: true },
            'empty': { percent: 5, text: 'Vide', low: true }
        };
        const levelData = fuelLevels[fuelLevel] || { percent: 0, text: 'N/A', low: false };
        const iconClass = levelData.low ? 'fuel-icon low' : 'fuel-icon';
        return `<div class="fuel-indicator"><span class="${iconClass}" style="--fill-percent: ${levelData.percent}%"><i class="fas fa-gas-pump"></i><span class="fuel-fill"><i class="fas fa-gas-pump"></i></span></span><span>${levelData.text}</span></div>`;
    }

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
            const isVehicle = item.asset_type === 'vehicle';
            const iconClass = isVehicle ? 'fa-car' : 'fa-tools';
            const isTakenByMe = item.status === 'in-use' && item.assigned_to_user_id == currentUserId;

            let actionButtonHtml = '';
            const itemDataReturn = `data-action="return" data-asset-id="${item.asset_id}" data-booking-id="${item.booking_id || ''}" data-barcode="${item.barcode}" data-item-name="${item.asset_name}" data-item-type="${item.asset_type}"`;
            const itemDataTake = `data-action="take" data-asset-id="${item.asset_id}" data-booking-id="${item.booking_id}" data-barcode="${item.barcode}" data-item-name="${item.asset_name}" data-item-type="${item.asset_type}"`;
            const itemDataReport = `data-action="report" data-asset-id="${item.asset_id}" data-item-name="${item.asset_name}"`;

            if (isTakenByMe) {
                actionButtonHtml = `
                    <button class="btn btn-info action-btn" ${itemDataReturn}>Retourner</button>
                    <a href="#" class="manual-entry-link" ${itemDataReturn}>(saisie manuelle)</a>
                    <button class="btn btn-warning btn-sm report-btn" ${itemDataReport}>Signaler</button>`;
            } else {
                actionButtonHtml = `
                    <button class="btn btn-success action-btn" ${itemDataTake}>Prendre</button>
                    <a href="#" class="manual-entry-link" ${itemDataTake}>(saisie manuelle)</a>
                    <button class="btn btn-warning btn-sm report-btn" ${itemDataReport}>Signaler</button>`;
            }

            let returnDateStr = 'N/A';
            let cardClass = 'item-card';
            if (item.return_date) {
                const returnDate = new Date(item.return_date);
                returnDateStr = returnDate.toLocaleDateString('fr-FR');
                if (isTakenByMe && returnDate < today) cardClass += ' overdue';
            }
            
            const fuelIndicatorHtml = isVehicle ? `<div><strong>Carburant:</strong> ${generateFuelIndicatorHTML(item.fuel_level)}</div>` : '';

            // MODIFIED HTML STRUCTURE FOR RESPONSIVENESS
            const itemCardHtml = `
                <div class="${cardClass}">
                    <div class="item-header">
                        <div class="item-icon"><i class="fas ${iconClass}"></i></div>
                        <div class="item-name">${item.asset_name}</div>
                    </div>
                    <div class="item-details">
                        <div class="item-meta">
                            <div><strong>Mission:</strong> ${item.mission || 'N/A'}</div>
                            <div><strong>Date de retour:</strong> ${returnDateStr}</div>
                            <div><strong>Code:</strong> ${item.serial_or_plate || 'N/A'} | <strong>Code-barres:</strong> ${item.barcode || 'N/A'}</div>
                            ${fuelIndicatorHtml}
                        </div>
                    </div>
                    <div class="item-actions">${actionButtonHtml}</div>
                </div>`;
            listContainer.append(itemCardHtml);
        });
    }

    function performAjaxCall(action, data, successCallback = null, method = 'POST', isJson = false) {
        if (!successCallback) showLoading($('#equipment-list-section'));

        const ajaxOptions = {
            url: action === 'report_item' ? 'inventory-handler.php' : 'technician-handler.php',
            type: method,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    if (successCallback) {
                        successCallback(response);
                    } else {
                        showNotification(response.message, 'success');
                        loadTechnicianEquipment();
                    }
                } else {
                    showNotification(response.message, 'danger');
                    if (!successCallback) loadTechnicianEquipment();
                }
            },
            error: function(xhr) {
                const errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Erreur de communication avec le serveur.';
                showNotification(errorMsg, 'danger');
                if (!successCallback) loadTechnicianEquipment();
            }
        };

        if (isJson) {
            ajaxOptions.contentType = 'application/json';
            ajaxOptions.data = JSON.stringify({ action, ...data });
            ajaxOptions.processData = false;
        } else {
            ajaxOptions.data = { action, ...data };
        }
        $.ajax(ajaxOptions);
    }

    function showLoading(element) {
        element.html(`<div class="text-center p-5"><div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div><p class="mt-2 text-muted">Chargement...</p></div>`);
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
        } else {
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
