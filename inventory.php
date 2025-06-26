<?php
require_once 'session-management.php';
requireLogin();
$currentUser = getCurrentUser();
$currentUserId = $currentUser['user_id'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de l'Inventaire</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; }
        .container-fluid { padding-top: 20px; }
        .card { background-color: #ffffff; border-radius: 15px; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08); border: none; margin-bottom: 25px; padding: 25px; }
        .tabs { display: flex; flex-wrap: wrap; justify-content: center; gap: 15px; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #e9ecef; }
        .tab { padding: 12px 25px; background: #e9ecef; color: #495057; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; text-align: center; }
        .tab.active { background: #007bff; color: white; box-shadow: 0 4px 12px rgba(0, 123, 255, 0.25); transform: translateY(-2px); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .scanner-container { position: relative; width: 100%; max-width: 500px; margin: 0 auto 20px; background: #2c3e50; border-radius: 15px; overflow: hidden; }
        #video { width: 100%; height: auto; }
        .inventory-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; }
        .asset-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.07); transition: all 0.3s ease; border-left: 5px solid; display: flex; flex-direction: column; justify-content: space-between; }
        .asset-card.tool { border-left-color: #28a745; }
        .asset-card.vehicle { border-left-color: #007bff; }
        .asset-card.maintenance { border-left-color: #ffc107; background-color: #ffc1071a; }
        .asset-card.in-use { border-left-color: #dc3545; background-color: #dc35461a; }
        .asset-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .asset-title { font-size: 1.2em; font-weight: 700; color: #343a40; margin-right: 10px; }
        .asset-status { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; white-space: nowrap; }
        .status-available { background: #d4edda; color: #155724; }
        .status-in-use { background: #f8d7da; color: #721c24; }
        .status-maintenance { background: #fff3cd; color: #856404; }
        .asset-details { color: #6c757d; line-height: 1.6; margin-bottom: 15px; font-size: 0.9em; word-break: break-word; }
        .asset-details strong { color: #495057; }
        .asset-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: auto; }
        .btn-small { padding: 5px 10px; font-size: 12px; }
        .notification { position: fixed; top: 80px; right: 20px; padding: 15px 25px; border-radius: 8px; color: white; font-weight: 600; z-index: 1051; transform: translateX(calc(100% + 30px)); transition: transform 0.4s ease-in-out; }
        .notification.success { background: #28a745; }
        .notification.error { background: #dc3545; }
        .notification.info { background: #17a2b8; }
        .notification.show { transform: translateX(0); }
        .loading-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); display: flex; align-items: center; justify-content: center; z-index: 9999; }
        .booking-info { font-size: 0.85em; color: #e83e8c; font-weight: bold; }
        .modal-body .form-control.flatpickr-input { background-color: #fff !important; }
        #all-bookings-table th { white-space: nowrap; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-fluid">
    <div class="card">
        <h2 class="text-center mb-0">📦 Gestion de l'Inventaire</h2>
    </div>

    <div class="card">
        <div class="tabs">
            <div class="tab active" data-tab="inventory"><i class="fas fa-boxes"></i> Inventaire</div>
            <div class="tab" data-tab="all_bookings"><i class="fas fa-calendar-alt"></i> Planning Réservations</div>
            <div class="tab" data-tab="scanner"><i class="fas fa-barcode"></i> Scanner</div>
            <div class="tab" data-tab="add_asset"><i class="fas fa-plus-circle"></i> Ajouter un Actif</div>
        </div>
    </div>

    <div id="inventory" class="tab-content active">
        <div class="card position-relative">
            <h3 class="mb-3">Liste des Actifs</h3>
             <div class="d-flex justify-content-start align-items-center mb-4 flex-wrap">
                <input type="text" class="form-control mr-sm-2 mb-2" id="searchInput" placeholder="Rechercher..." style="max-width: 300px;">
                <select id="filterType" class="form-control mr-sm-2 mb-2" style="max-width: 150px;">
                    <option value="all">Tous les types</option>
                    <option value="tool">Outils</option>
                    <option value="vehicle">Véhicules</option>
                </select>
                <select id="filterStatus" class="form-control mb-2" style="max-width: 200px;">
                    <option value="all">Tous les statuts</option>
                    <option value="available">Disponible</option>
                    <option value="in-use">En cours d'utilisation</option>
                    <option value="maintenance">En maintenance</option>
                </select>
            </div>
            <div id="inventoryGrid" class="inventory-grid"></div>
        </div>
    </div>

    <div id="all_bookings" class="tab-content">
        <div class="card">
            <h3><i class="fas fa-calendar-alt"></i> Planning des Réservations (À partir d'aujourd'hui)</h3>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="thead-dark">
                        <tr>
                            <th>Date</th>
                            <th>Actif</th>
                            <th>Code-barres</th>
                            <th>Réservé par</th>
                            <th>Mission</th>
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="all-bookings-table">
                        </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="scanner" class="tab-content">
        <div class="card">
             <h3 class="text-center mb-4">Scanner un Code-barres</h3>
             <div class="scanner-container">
                <video id="video" autoplay playsinline></video>
             </div>
             <div class="text-center mt-3">
                <button id="startScanBtn" class="btn btn-success"><i class="fas fa-play"></i> Démarrer le Scan</button>
                <button id="stopScanBtn" class="btn btn-danger" style="display: none;"><i class="fas fa-stop"></i> Arrêter</button>
            </div>
        </div>
    </div>
    
    <div id="add_asset" class="tab-content">
         <div class="card">
            <h3>Ajouter un Nouvel Actif</h3>
            <form id="addAssetForm">
                 <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="asset_type">Type d'actif *</label>
                        <select id="asset_type" class="form-control" required>
                            <option value="tool" selected>🔧 Outil</option>
                            <option value="vehicle">🚗 Véhicule</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="barcode">Code-barres / ID Unique *</label>
                        <input type="text" class="form-control" id="barcode" placeholder="Ex: TOOL001, 123456789" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="asset_name">Nom de l'actif *</label>
                    <input type="text" class="form-control" id="asset_name" placeholder="Ex: Perceuse sans fil, Renault Master" required>
                </div>
                 <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="brand">Marque</label>
                        <input type="text" class="form-control" id="brand" placeholder="Ex: DeWalt, Renault">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="category_id">Catégorie</label>
                        <select id="category_id" class="form-control"></select>
                    </div>
                </div>
                <div id="tool_fields">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="serial_or_plate_tool">Numéro de série</label>
                            <input type="text" class="form-control" id="serial_or_plate_tool" placeholder="Ex: SN12345678">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="position_or_info_tool">Position / Emplacement</label>
                            <input type="text" class="form-control" id="position_or_info_tool" placeholder="Ex: Entrepôt A, Étagère B-3">
                        </div>
                    </div>
                </div>
                <div id="vehicle_fields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="serial_or_plate_vehicle">Plaque d'immatriculation</label>
                            <input type="text" class="form-control" id="serial_or_plate_vehicle" placeholder="Ex: AA-123-BB">
                        </div>
                         <div class="form-group col-md-6">
                            <label for="fuel_level">Niveau de carburant</label>
                            <select id="fuel_level" class="form-control">
                                <option value="">Non spécifié</option>
                                <option value="full">Plein</option>
                                <option value="three-quarter">3/4</option>
                                <option value="half">Moitié</option>
                                <option value="quarter">1/4</option>
                                <option value="empty">Vide</option>
                            </select>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter l'Actif</button>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="bookingModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Réserver <span id="bookingModalAssetName"></span></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form id="bookingForm">
            <input type="hidden" id="bookingModalAssetId">
            <div class="form-group">
                <label for="booking_date">Date de réservation *</label>
                <input type="text" id="booking_date" class="form-control" placeholder="Sélectionnez une date..." required>
            </div>
            <div class="form-group">
                <label for="booking_mission">Mission / Motif</label>
                <textarea id="booking_mission" class="form-control" rows="3" placeholder="Description de la mission..."></textarea>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-primary" id="saveBookingBtn">Réserver</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="maintenanceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gérer la Maintenance</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Mettre <strong id="maintenanceModalAssetName"></strong> en maintenance ?</p>
                <p class="text-muted small">L'actif ne pourra plus être réservé ou utilisé jusqu'à ce qu'il soit à nouveau marqué comme disponible.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-warning" id="setMaintenanceBtn">Mettre en Maintenance</button>
            </div>
        </div>
    </div>
</div>


<div class="loading-overlay" style="display: none;">
    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"></div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://unpkg.com/@zxing/library@latest/umd/index.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/fr.js"></script>

<script>
// --- CONFIG & GLOBAL STATE ---
const HANDLER_URL = 'inventory-handler.php';
const IS_ADMIN = <?php echo ($currentUser['role'] === 'admin') ? 'true' : 'false'; ?>;
const CURRENT_USER_ID = <?php echo $currentUserId; ?>;
let inventory = [];
let assetCategories = [];
let allBookings = [];
let codeReader = null;
let datePicker = null;

// --- DOM ELEMENTS ---
const inventoryGrid = document.getElementById('inventoryGrid');
const loadingOverlay = document.querySelector('.loading-overlay');

// --- INITIALIZATION ---
document.addEventListener('DOMContentLoaded', async () => {
    setupEventListeners();
    loadingOverlay.style.display = 'flex';
    await fetchInitialData();
    loadingOverlay.style.display = 'none';
    renderAll();
    initializeDatePicker();
});

async function fetchInitialData() {
    try {
        const [inventoryData, bookingsData, categoriesData] = await Promise.all([
            apiCall('get_inventory'),
            apiCall('get_all_bookings'),
            apiCall('get_categories')
        ]);
        inventory = inventoryData.inventory || [];
        allBookings = bookingsData.bookings || [];
        assetCategories = categoriesData.categories || [];
    } catch (error) { /* Handled by apiCall */ }
}

function renderAll() {
    renderInventory();
    renderAllBookingsTable();
    populateCategoryDropdown('tool'); // Initial population
}

// --- API & HELPERS ---
function showNotification(message, type = 'success') { 
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    setTimeout(() => notification.classList.add('show'), 10);
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => document.body.contains(notification) && document.body.removeChild(notification), 500);
    }, 4000);
}
async function apiCall(action, method = 'POST', body = null) {
    const options = { method, headers: { 'Content-Type': 'application/json' } };
    if (body) options.body = JSON.stringify(body);
    let url = `${HANDLER_URL}?action=${action}`;
    if (method === 'GET') {
        url = `${HANDLER_URL}?action=${action}`;
        if (body) url += '&' + new URLSearchParams(body).toString();
        delete options.body;
    }
    
    try {
        const response = await fetch(url, options);
        const data = await response.json();
        if (!response.ok || data.status !== 'success') {
            throw new Error(data.message || `Erreur serveur ${response.status}`);
        }
        return data;
    } catch (error) {
        console.error('API Error:', error);
        showNotification(error.message, 'error');
        throw error;
    }
}

// --- EVENT LISTENERS ---
function setupEventListeners() {
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', (e) => showTab(e.currentTarget.dataset.tab));
    });
    // Search and filter
    document.getElementById('searchInput').addEventListener('keyup', renderInventory);
    document.getElementById('filterType').addEventListener('change', renderInventory);
    document.getElementById('filterStatus').addEventListener('change', renderInventory);
    // Add Asset Form
    document.getElementById('addAssetForm').addEventListener('submit', handleAddAsset);
    document.getElementById('asset_type').addEventListener('change', toggleAssetFields);
    // Scanner
    document.getElementById('startScanBtn').addEventListener('click', startScanning);
    document.getElementById('stopScanBtn').addEventListener('click', stopScanning);
    // Booking
    document.getElementById('saveBookingBtn').addEventListener('click', handleSaveBooking);
}

function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById(tabName).classList.add('active');
    document.querySelector(`.tab[data-tab='${tabName}']`).classList.add('active');
    if (tabName !== 'scanner' && codeReader) stopScanning();
    if (tabName === 'all_bookings' || tabName === 'inventory') {
        loadingOverlay.style.display = 'flex';
        fetchInitialData().then(() => {
            renderAll();
            loadingOverlay.style.display = 'none';
        });
    }
}

// --- INVENTORY TAB ---
function renderInventory() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const typeFilter = document.getElementById('filterType').value;
    const statusFilter = document.getElementById('filterStatus').value;

    const filtered = inventory.filter(asset => {
        const s = ((asset.serial_or_plate || '') + (asset.asset_name || '') + (asset.brand || '') + (asset.barcode || '')).toLowerCase();
        const matchesSearch = s.includes(searchTerm);
        const matchesType = typeFilter === 'all' || asset.asset_type === typeFilter;
        const matchesStatus = statusFilter === 'all' || asset.status === statusFilter;
        return matchesSearch && matchesType && matchesStatus;
    });

    inventoryGrid.innerHTML = '';
    if (filtered.length === 0) {
        inventoryGrid.innerHTML = `<div class="col-12 text-center text-muted mt-5"><h4>Aucun actif ne correspond à vos critères.</h4></div>`;
    }
    filtered.forEach(asset => inventoryGrid.appendChild(createAssetCard(asset)));
}

function createAssetCard(asset) {
    const card = document.createElement('div');
    card.className = `asset-card ${asset.asset_type} ${asset.status}`;
    card.dataset.id = asset.asset_id;

    const assignedTo = asset.status === 'in-use' ? `<strong>Assigné à:</strong> ${asset.assigned_to_prenom || ''} ${asset.assigned_to_nom || ''}<br><strong>Mission:</strong> ${asset.assigned_mission || 'N/A'}<br>` : '';
    const bookingInfo = asset.status === 'available' && asset.next_booking_date ? `<div class="booking-info mt-2"><i class="fas fa-calendar-check"></i> Proch. résa: ${new Date(asset.next_booking_date + 'T00:00:00').toLocaleDateString('fr-FR')}</div>` : '';
    
    const details = asset.asset_type === 'tool' ? `
        <strong>N° de série:</strong> ${asset.serial_or_plate || 'N/A'}<br>
        <strong>Emplacement:</strong> ${asset.position_or_info || 'N/A'}<br>
    ` : `
        <strong>Plaque:</strong> ${asset.serial_or_plate || 'N/A'}<br>
        <strong>Carburant:</strong> ${asset.fuel_level || 'N/A'}<br>
    `;

    let buttons = '';
    if (asset.status === 'available') {
        buttons += `<button class="btn btn-success btn-small" onclick="openBookingModal(${asset.asset_id})"><i class="fas fa-calendar-plus"></i> Réserver</button>`;
        buttons += `<button class="btn btn-warning btn-small" onclick="openMaintenanceModal(${asset.asset_id}, '${asset.asset_name.replace(/'/g, "\\'")}')"><i class="fas fa-tools"></i> Maint.</button>`;
    } else if (asset.status === 'maintenance') {
        buttons += `<button class="btn btn-info btn-small" onclick="setAssetAvailable(${asset.asset_id})"><i class="fas fa-check-circle"></i> Rendre Dispo.</button>`;
    }

    if (IS_ADMIN) {
        buttons += `<button class="btn btn-danger btn-small" onclick="handleDeleteAsset(${asset.asset_id}, '${asset.asset_name.replace(/'/g, "\\'")}')"><i class="fas fa-trash"></i></button>`;
    }

    card.innerHTML = `
        <div>
            <div class="asset-header">
                <span class="asset-title"><i class="fas ${asset.asset_type === 'tool' ? 'fa-wrench' : 'fa-car'} mr-2"></i>${asset.asset_name}</span>
                <span class="asset-status status-${asset.status}">${asset.status.replace('-', ' ')}</span>
            </div>
            <div class="asset-details">
                <strong>Code-barres:</strong> ${asset.barcode}<br>
                ${details} ${assignedTo}
            </div>
            ${bookingInfo}
        </div>
        <div class="asset-actions mt-3">${buttons}</div>`;
    return card;
}

// --- BOOKING LOGIC ---
function initializeDatePicker() {
    datePicker = flatpickr("#booking_date", {
        locale: "fr",
        dateFormat: "Y-m-d",
        minDate: "today",
    });
}

async function openBookingModal(assetId) {
    const asset = inventory.find(a => a.asset_id == assetId);
    if (!asset) return;

    $('#bookingModalAssetId').val(asset.asset_id);
    $('#bookingModalAssetName').text(asset.asset_name);
    
    loadingOverlay.style.display = 'flex';
    try {
        const data = await apiCall('get_asset_availability', 'GET', { asset_id: assetId });
        datePicker.set('disable', data.booked_dates);
        $('#bookingModal').modal('show');
    } finally {
        loadingOverlay.style.display = 'none';
    }
}

async function handleSaveBooking() {
    const bookingData = {
        asset_id: $('#bookingModalAssetId').val(),
        booking_date: $('#booking_date').val(),
        mission: $('#booking_mission').val(),
    };
    if (!bookingData.booking_date) {
        showNotification("Veuillez choisir une date.", "error");
        return;
    }

    loadingOverlay.style.display = 'flex';
    try {
        await apiCall('book_asset', 'POST', bookingData);
        showNotification('Réservation enregistrée !', 'success');
        $('#bookingModal').modal('hide');
        document.getElementById('bookingForm').reset();
        await fetchInitialData();
        renderAll();
    } finally {
        loadingOverlay.style.display = 'none';
    }
}

// --- ALL BOOKINGS TAB ---
function renderAllBookingsTable() {
    const tableBody = document.getElementById('all-bookings-table');
    tableBody.innerHTML = '';
    if (allBookings.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="7" class="text-center">Aucune réservation future.</td></tr>';
        return;
    }

    allBookings.forEach(b => {
        const canCancel = (b.status === 'booked' && (IS_ADMIN || b.user_id == CURRENT_USER_ID));
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${new Date(b.booking_date + 'T00:00:00').toLocaleDateString('fr-FR')}</td>
            <td>${b.asset_name}</td>
            <td>${b.barcode}</td>
            <td>${b.prenom} ${b.nom}</td>
            <td>${b.mission || 'N/A'}</td>
            <td><span class="badge badge-pill badge-${b.status === 'booked' ? 'primary' : 'success'}">${b.status}</span></td>
            <td>
                ${canCancel ? `<button class="btn btn-danger btn-sm" onclick="handleCancelBooking(${b.booking_id})">Annuler</button>` : ''}
            </td>`;
        tableBody.appendChild(row);
    });
}

async function handleCancelBooking(bookingId) {
    if (!confirm("Voulez-vous vraiment annuler cette réservation ?")) return;
    loadingOverlay.style.display = 'flex';
    try {
        await apiCall('cancel_booking', 'POST', { booking_id: bookingId });
        showNotification('Réservation annulée.', 'success');
        await fetchInitialData();
        renderAll();
    } finally {
        loadingOverlay.style.display = 'none';
    }
}

// --- SCANNER LOGIC ---
function startScanning() {
    if (codeReader) {
      codeReader.reset();
    }
    codeReader = new ZXing.BrowserMultiFormatReader();
    document.getElementById('startScanBtn').style.display = 'none';
    document.getElementById('stopScanBtn').style.display = 'inline-block';
    
    codeReader.decodeFromVideoDevice(undefined, 'video', (result, err) => {
        if (result) {
            stopScanning();
            processScanResult(result.text);
        }
        if (err && !(err instanceof ZXing.NotFoundException)) {
          console.error(err);
          stopScanning();
        }
    }).catch(err => {
        console.error("Camera Error:", err);
        showNotification("Erreur de caméra. Vérifiez les permissions.", "error");
        stopScanning();
    });
}

function stopScanning() {
    if (codeReader) {
        codeReader.reset();
        codeReader = null;
    }
    document.getElementById('startScanBtn').style.display = 'inline-block';
    document.getElementById('stopScanBtn').style.display = 'none';
}

async function processScanResult(barcode) {
    loadingOverlay.style.display = 'flex';
    try {
        const data = await apiCall('process_scan', 'POST', { barcode });
        showNotification(data.message, 'info');

        switch(data.scan_code) {
            case 'return_success':
            case 'checkout_success':
                await fetchInitialData();
                renderAll();
                showTab('inventory');
                break;

            case 'prompt_booking':
                const asset = data.asset;
                // Use a standard confirm dialog
                if(confirm(`"${asset.asset_name}" n'est pas réservé pour aujourd'hui. Voulez-vous le réserver et le sortir maintenant ?`)) {
                    // Pre-fill booking modal for today and open it
                    $('#bookingModalAssetId').val(asset.asset_id);
                    $('#bookingModalAssetName').text(asset.asset_name);
                    datePicker.set('disable', []); // Reset disabled dates
                    datePicker.setDate(new Date(), true); // Set to today
                    $('#booking_mission').val('Sortie via scan');
                    $('#bookingModal').modal('show');
                    
                    // Special handler that books and then immediately re-scans to checkout
                    $('#saveBookingBtn').off('click').one('click', async function() {
                        const bookingData = { asset_id: $('#bookingModalAssetId').val(), booking_date: $('#booking_date').val(), mission: $('#booking_mission').val() };
                        if (!bookingData.booking_date) { showNotification("Date invalide.", "error"); return; }
                        
                        loadingOverlay.style.display = 'flex';
                        try {
                            await apiCall('book_asset', 'POST', bookingData);
                            $('#bookingModal').modal('hide');
                            document.getElementById('bookingForm').reset();
                            // After successful booking, immediately try to check it out
                            await processScanResult(barcode);
                        } finally {
                            loadingOverlay.style.display = 'none';
                            // Restore the default booking handler after this special action
                             $('#saveBookingBtn').off('click').on('click', handleSaveBooking);
                        }
                    });
                }
                break;
            
            case 'asset_not_found':
                if (confirm(data.message)) {
                    showTab('add_asset');
                    $('#barcode').val(data.barcode);
                }
                break;
        }
    } catch(e) {
        /* error handled by apiCall */
    } finally {
        loadingOverlay.style.display = 'none';
        // Ensure the default booking button handler is restored if modal was cancelled
        $('#bookingModal').on('hidden.bs.modal', function () {
            $('#saveBookingBtn').off('click').on('click', handleSaveBooking);
        });
    }
}

// --- MAINTENANCE & OTHER ACTIONS ---
function openMaintenanceModal(assetId, assetName) {
    $('#maintenanceModalAssetName').text(assetName);
    $('#setMaintenanceBtn').off('click').on('click', () => setMaintenanceStatus(assetId, 'maintenance'));
    $('#maintenanceModal').modal('show');
}

async function setMaintenanceStatus(assetId, status) {
    loadingOverlay.style.display = 'flex';
    try {
        await apiCall('update_maintenance_status', 'POST', { asset_id: assetId, status: status });
        showNotification('Statut mis à jour.', 'success');
        $('#maintenanceModal').modal('hide');
        await fetchInitialData();
        renderAll();
    } finally {
        loadingOverlay.style.display = 'none';
    }
}

async function setAssetAvailable(assetId) {
    await setMaintenanceStatus(assetId, 'available');
}

// --- ADD ASSET & CATEGORIES ---
function toggleAssetFields() {
    const type = document.getElementById('asset_type').value;
    document.getElementById('tool_fields').style.display = (type === 'tool') ? 'block' : 'none';
    document.getElementById('vehicle_fields').style.display = (type === 'vehicle') ? 'block' : 'none';
    populateCategoryDropdown(type);
}

function populateCategoryDropdown(assetType) {
    const dropdown = document.getElementById('category_id');
    dropdown.innerHTML = '<option value="">-- Sans catégorie --</option>';
    if (assetCategories && assetCategories.length > 0) {
        assetCategories.filter(cat => cat.category_type === assetType)
            .forEach(cat => dropdown.add(new Option(cat.category_name, cat.category_id)));
    }
}

async function handleAddAsset(e) {
    e.preventDefault();
    const type = document.getElementById('asset_type').value;
    const assetData = {
        barcode: document.getElementById('barcode').value,
        asset_type: type,
        asset_name: document.getElementById('asset_name').value,
        brand: document.getElementById('brand').value,
        category_id: document.getElementById('category_id').value || null,
        serial_or_plate: type === 'tool' ? document.getElementById('serial_or_plate_tool').value : document.getElementById('serial_or_plate_vehicle').value,
        position_or_info: type === 'tool' ? document.getElementById('position_or_info_tool').value : null,
        fuel_level: type === 'vehicle' ? document.getElementById('fuel_level').value : null,
    };
    loadingOverlay.style.display = 'flex';
    try {
        const data = await apiCall('add_asset', 'POST', assetData);
        showNotification('Actif ajouté avec succès!', 'success');
        document.getElementById('addAssetForm').reset();
        toggleAssetFields();
        await fetchInitialData();
        renderAll();
        showTab('inventory');
    } finally {
        loadingOverlay.style.display = 'none';
    }
}
async function handleDeleteAsset(assetId, assetName) {
    if (!confirm(`Êtes-vous sûr de vouloir supprimer l'actif "${assetName}" ? Cette action est irréversible et supprimera aussi toutes les réservations associées.`)) return;
    loadingOverlay.style.display = 'flex';
    try {
        await apiCall('delete_asset', 'POST', { asset_id: assetId });
        showNotification('Actif supprimé avec succès!', 'success');
        await fetchInitialData();
        renderAll();
    } finally {
        loadingOverlay.style.display = 'none';
    }
}
</script>
</body>
</html>
