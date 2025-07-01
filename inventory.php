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
        .asset-title { font-size: 1.2em; font-weight: 700; color: #343a40; margin-right: 10px; cursor: pointer; transition: color 0.2s; }
        .asset-title:hover { color: #0056b3; }
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
        .category-list { list-style-type: none; padding-left: 0; }
        .category-list li { background: #f8f9fa; padding: 10px 15px; border-radius: 8px; margin-bottom: 8px; font-weight: 500; display: flex; justify-content: space-between; align-items: center; }
        .category-actions button { margin-left: 5px; }
        #categoryFilterContainer { display: flex; flex-wrap: wrap; gap: 10px; }
        #categoryFilterContainer .btn { border-radius: 20px; padding: 5px 15px; font-size: 0.9em; }
        .booking-filters { display: flex; gap: 15px; margin-top: 15px; margin-bottom: 20px; flex-wrap: wrap; }
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
            <div class="tab" data-tab="booking"><i class="fas fa-book-open"></i> Booking</div>
            <div class="tab" data-tab="scanner"><i class="fas fa-barcode"></i> Scanner</div>
            <div class="tab" data-tab="add_asset"><i class="fas fa-plus-circle"></i> Ajouter un Actif</div>
            <div class="tab" data-tab="manage_categories"><i class="fas fa-tags"></i> Gérer les Catégories</div>
        </div>
    </div>

    <div id="inventory" class="tab-content active">
        <div class="card position-relative">
            <h3 class="mb-3">Liste des Actifs</h3>
             <div class="d-flex justify-content-start align-items-center mb-3 flex-wrap">
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
            <div id="categoryFilterContainer" class="mb-4"></div>
            <div id="inventoryGrid" class="inventory-grid"></div>
        </div>
    </div>

    <div id="booking" class="tab-content">
        <div class="card mb-4">
            <h3 class="mb-3"><i class="fas fa-user mr-2"></i>Réservations Individuelles (Actives/Futures)</h3>
            <div class="booking-filters">
                <input type="text" id="individualFilterDate" class="form-control" placeholder="Filtrer par date..." style="max-width: 200px;">
                <select id="individualFilterUser" class="form-control" style="max-width: 200px;"></select>
                <input type="text" id="individualFilterMission" class="form-control" placeholder="Filtrer par mission..." style="max-width: 250px;">
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="thead-dark">
                        <tr><th>Date</th><th>Actif</th><th>Réservé par</th><th>Mission</th><th>Statut</th><th>Action</th></tr>
                    </thead>
                    <tbody id="individual-active-bookings-table"></tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <h3 class="mb-3"><i class="fas fa-users mr-2"></i>Réservations par Mission (Actives/Futures)</h3>
             <div class="booking-filters">
                <input type="text" id="missionFilterDate" class="form-control" placeholder="Filtrer par date..." style="max-width: 200px;">
                <input type="text" id="missionFilterMission" class="form-control" placeholder="Filtrer par mission..." style="max-width: 250px;">
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="thead-dark">
                        <tr><th>Date</th><th>Mission</th><th>Actif</th><th>Statut</th><th>Action</th></tr>
                    </thead>
                    <tbody id="mission-active-bookings-table"></tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h3 class="mb-3"><i class="fas fa-history mr-2"></i>Historique d'Utilisation</h3>
            <div class="booking-filters">
                <input type="text" id="historyFilterDate" class="form-control" placeholder="Filtrer par date..." style="max-width: 200px;">
                 <select id="historyFilterUser" class="form-control" style="max-width: 200px;"></select>
                <input type="text" id="historyFilterMission" class="form-control" placeholder="Filtrer par mission..." style="max-width: 250px;">
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="thead-dark">
                        <tr><th>Date</th><th>Actif</th><th>Utilisé par</th><th>Mission</th><th>Statut</th></tr>
                    </thead>
                    <tbody id="usage-history-table"></tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div id="scanner" class="tab-content">
        <div class="card">
             <h3 class="text-center mb-4">Scanner un Code-barres</h3>
             <div class="scanner-container"><video id="video" autoplay playsinline></video></div>
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
            </form>
        </div>
    </div>

    <div id="manage_categories" class="tab-content">
        <div class="row">
            <div class="col-lg-5">
                <div class="card">
                    <h3><i class="fas fa-plus-circle"></i> Créer une Catégorie</h3>
                    <form id="addCategoryForm">
                        <div class="form-group">
                            <label for="new_category_name">Nom de la Catégorie *</label>
                            <input type="text" class="form-control" id="new_category_name" placeholder="Ex: Perceuses, Fourgonnettes" required>
                        </div>
                        <div class="form-group">
                            <label for="new_category_type">Type de Catégorie *</label>
                            <select id="new_category_type" class="form-control" required>
                                <option value="tool">Outil</option>
                                <option value="vehicle">Véhicule</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Créer</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card">
                    <h3><i class="fas fa-tags"></i> Catégories Existantes</h3>
                    <div class="row">
                        <div class="col-md-6">
                            <h4>Outils</h4>
                            <ul id="toolCategoriesList" class="category-list"></ul>
                        </div>
                        <div class="col-md-6">
                            <h4>Véhicules</h4>
                            <ul id="vehicleCategoriesList" class="category-list"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editAssetModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier l'Actif</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editAssetForm"></form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="historyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Historique pour : <span id="historyModalAssetName"></span></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="historyModalBody">
                </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bookingModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Réserver <span id="bookingModalAssetName"></span></h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <form id="bookingForm">
                    <input type="hidden" id="bookingModalAssetId">
                    <div id="futureBookingsInfo" class="alert alert-info small" style="display: none; padding: 10px;"></div>
                    <div class="form-group"><label for="booking_date">Date de réservation *</label><input type="text" id="booking_date" class="form-control" placeholder="Sélectionnez une date..." required></div>
                    <div class="form-group"><label for="booking_mission">Mission / Motif</label><textarea id="booking_mission" class="form-control" rows="3" placeholder="Description de la mission..."></textarea></div>
                </form>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button><button type="button" class="btn btn-primary" id="saveBookingBtn">Réserver</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="maintenanceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Gérer la Maintenance</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body"><p>Mettre <strong id="maintenanceModalAssetName"></strong> en maintenance ?</p><p class="text-muted small">L'actif ne pourra plus être réservé ou utilisé.</p></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button><button type="button" class="btn btn-warning" id="setMaintenanceBtn">Mettre en Maintenance</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="modifyCategoryModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Modifier la Catégorie</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
      <div class="modal-body">
        <form id="modifyCategoryForm"><input type="hidden" id="modifyCategoryId"><div class="form-group"><label for="modifyCategoryName">Nouveau nom de la catégorie *</label><input type="text" id="modifyCategoryName" class="form-control" required></div></form>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button><button type="button" class="btn btn-primary" id="saveCategoryUpdateBtn">Sauvegarder</button></div>
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
let allBookings = { individual: [], mission: [] };
let usageHistory = [];
let allUsers = [];
let selectedCategoryId = 'all'; 
let codeReader = null;
let datePicker = null;

// --- DOM ELEMENTS & TEMPLATES ---
const inventoryGrid = document.getElementById('inventoryGrid');
const loadingOverlay = document.querySelector('.loading-overlay');
const addAssetFormContent = `<div class="form-row"><div class="form-group col-md-6"><label for="add_asset_type">Type d'actif *</label><select id="add_asset_type" class="form-control" required><option value="tool" selected>🔧 Outil</option><option value="vehicle">🚗 Véhicule</option></select></div><div class="form-group col-md-6"><label for="add_barcode">Code-barres / ID Unique *</label><input type="text" class="form-control" id="add_barcode" placeholder="Ex: TOOL001, 123456789" required></div></div><div class="form-group"><label for="add_asset_name">Nom de l'actif *</label><input type="text" class="form-control" id="add_asset_name" placeholder="Ex: Perceuse sans fil, Renault Master" required></div><div class="form-row"><div class="form-group col-md-6"><label for="add_brand">Marque</label><input type="text" class="form-control" id="add_brand" placeholder="Ex: DeWalt, Renault"></div><div class="form-group col-md-6"><label for="add_category_id">Catégorie</label><select id="add_category_id" class="form-control"></select></div></div><div id="add_tool_fields"><div class="form-row"><div class="form-group col-md-6"><label for="add_serial_or_plate_tool">Numéro de série</label><input type="text" class="form-control" id="add_serial_or_plate_tool" placeholder="Ex: SN12345678"></div><div class="form-group col-md-6"><label for="add_position_or_info_tool">Position / Emplacement</label><input type="text" class="form-control" id="add_position_or_info_tool" placeholder="Ex: Entrepôt A, Étagère B-3"></div></div></div><div id="add_vehicle_fields" style="display: none;"><div class="form-row"><div class="form-group col-md-6"><label for="add_serial_or_plate_vehicle">Plaque d'immatriculation</label><input type="text" class="form-control" id="add_serial_or_plate_vehicle" placeholder="Ex: AA-123-BB"></div><div class="form-group col-md-6"><label for="add_fuel_level">Niveau de carburant</label><select id="add_fuel_level" class="form-control"><option value="">Non spécifié</option><option value="full">Plein</option><option value="three-quarter">3/4</option><option value="half">Moitié</option><option value="quarter">1/4</option><option value="empty">Vide</option></select></div></div></div><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter l'Actif</button>`;
const editAssetFormContent = `<input type="hidden" id="edit_asset_id"><div class="form-row"><div class="form-group col-md-6"><label for="edit_asset_type">Type d'actif *</label><select id="edit_asset_type" class="form-control" required><option value="tool">🔧 Outil</option><option value="vehicle">🚗 Véhicule</option></select></div><div class="form-group col-md-6"><label for="edit_barcode">Code-barres / ID Unique *</label><input type="text" class="form-control" id="edit_barcode" required></div></div><div class="form-group"><label for="edit_asset_name">Nom de l'actif *</label><input type="text" class="form-control" id="edit_asset_name" required></div><div class="form-row"><div class="form-group col-md-6"><label for="edit_brand">Marque</label><input type="text" class="form-control" id="edit_brand"></div><div class="form-group col-md-6"><label for="edit_category_id">Catégorie</label><select id="edit_category_id" class="form-control"></select></div></div><div id="edit_tool_fields"><div class="form-row"><div class="form-group col-md-6"><label for="edit_serial_or_plate_tool">Numéro de série</label><input type="text" class="form-control" id="edit_serial_or_plate_tool"></div><div class="form-group col-md-6"><label for="edit_position_or_info_tool">Position / Emplacement</label><input type="text" class="form-control" id="edit_position_or_info_tool"></div></div></div><div id="edit_vehicle_fields" style="display: none;"><div class="form-row"><div class="form-group col-md-6"><label for="edit_serial_or_plate_vehicle">Plaque d'immatriculation</label><input type="text" class="form-control" id="edit_serial_or_plate_vehicle"></div><div class="form-group col-md-6"><label for="edit_fuel_level">Niveau de carburant</label><select id="edit_fuel_level" class="form-control"><option value="">Non spécifié</option><option value="full">Plein</option><option value="three-quarter">3/4</option><option value="half">Moitié</option><option value="quarter">1/4</option><option value="empty">Vide</option></select></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary" id="saveAssetUpdateBtn"><i class="fas fa-save"></i> Sauvegarder</button></div>`;


// --- INITIALIZATION ---
document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('addAssetForm').innerHTML = addAssetFormContent;
    document.getElementById('editAssetForm').innerHTML = editAssetFormContent;
    setupEventListeners();
    initializeBookingTabFilters();
    loadingOverlay.style.display = 'flex';
    await fetchAllData();
    renderInventoryTab();
    initializeDatePicker();
    loadingOverlay.style.display = 'none';
});

async function fetchAllData() {
    try {
        const [inventoryData, bookingsData, categoriesData, historyData, usersData] = await Promise.all([
            apiCall('get_inventory', 'GET'),
            apiCall('get_all_bookings', 'GET'),
            apiCall('get_categories', 'GET'),
            apiCall('get_booking_history', 'GET'),
            apiCall('get_users', 'GET')
        ]);
        
        inventory = inventoryData.inventory || [];
        allBookings.individual = bookingsData.bookings?.individual || [];
        allBookings.mission = bookingsData.bookings?.mission || [];
        usageHistory = historyData.history || [];
        assetCategories = categoriesData.categories || [];
        allUsers = usersData.users || [];
        
        assetCategories.sort((a, b) => a.category_name.localeCompare(b.category_name));
    } catch (error) {
        console.error("Erreur lors du chargement des données:", error);
        showNotification("Impossible de charger toutes les données.", "error");
    }
}

function renderInventoryTab() {
    renderCategoryFilters();
    renderInventory();
    populateCategoryDropdowns('add');
}

function renderBookingTab() {
    populateUserFilters();
    renderIndividualBookingsTable();
    renderMissionBookingsTable();
    renderUsageHistoryTable();
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
        options.method = 'GET';
        if (body) url += '&' + new URLSearchParams(body).toString();
        delete options.body;
    }
    try {
        const response = await fetch(url, options);
        if (!response.ok) {
            throw new Error(`Erreur réseau: ${response.status} ${response.statusText}`);
        }
        const data = await response.json();
        if (data.status !== 'success') {
            throw new Error(data.message || 'Une erreur inconnue est survenue.');
        }
        return data;
    } catch (error) {
        console.error(`API Error during action '${action}':`, error);
        showNotification(error.message, 'error');
        throw error;
    }
}


// --- EVENT LISTENERS ---
function setupEventListeners() {
    document.querySelectorAll('.tab').forEach(tab => tab.addEventListener('click', e => showTab(e.currentTarget.dataset.tab)));
    
    // Inventory Tab Listeners
    document.getElementById('searchInput').addEventListener('input', updateFiltersAndRender);
    document.getElementById('filterType').addEventListener('change', () => {
        selectedCategoryId = 'all'; 
        updateFiltersAndRender();
        renderCategoryFilters(); 
    });
    document.getElementById('filterStatus').addEventListener('change', updateFiltersAndRender);
    document.getElementById('categoryFilterContainer').addEventListener('click', (e) => {
        if (e.target.matches('.btn[data-category-id]')) {
            selectedCategoryId = e.target.dataset.categoryId;
            updateFiltersAndRender();
            renderCategoryFilters(); 
        }
    });
    
    // Forms, Modals, and Scanner Listeners
    document.getElementById('addAssetForm').addEventListener('submit', handleAddAsset);
    document.getElementById('editAssetForm').addEventListener('submit', handleUpdateAsset);
    document.getElementById('add_asset_type').addEventListener('change', () => toggleAssetFields('add'));
    document.getElementById('edit_asset_type').addEventListener('change', () => toggleAssetFields('edit'));
    document.getElementById('startScanBtn').addEventListener('click', startScanning);
    document.getElementById('stopScanBtn').addEventListener('click', stopScanning);
    document.getElementById('saveBookingBtn').addEventListener('click', handleSaveBooking);
    document.getElementById('addCategoryForm').addEventListener('submit', handleCreateCategory);
    document.getElementById('saveCategoryUpdateBtn').addEventListener('click', handleUpdateCategory);

    // Booking Tab Filter Listeners
    document.getElementById('individualFilterDate').addEventListener('change', renderIndividualBookingsTable);
    document.getElementById('individualFilterUser').addEventListener('change', renderIndividualBookingsTable);
    document.getElementById('individualFilterMission').addEventListener('input', renderIndividualBookingsTable);
    document.getElementById('missionFilterDate').addEventListener('change', renderMissionBookingsTable);
    document.getElementById('missionFilterMission').addEventListener('input', renderMissionBookingsTable);
    document.getElementById('historyFilterDate').addEventListener('change', renderUsageHistoryTable);
    document.getElementById('historyFilterUser').addEventListener('change', renderUsageHistoryTable);
    document.getElementById('historyFilterMission').addEventListener('input', renderUsageHistoryTable);
}

function initializeBookingTabFilters() {
    const commonConfig = { 
        locale: "fr", 
        dateFormat: "Y-m-d", 
        allowInput: true,
        // The 'clear' functionality is handled by flatpickr's native 'X' button
    };
    flatpickr("#individualFilterDate", commonConfig);
    flatpickr("#missionFilterDate", commonConfig);
    flatpickr("#historyFilterDate", commonConfig);
}


function populateUserFilters() {
    const userFilters = ['individualFilterUser', 'historyFilterUser'];
    userFilters.forEach(filterId => {
        const select = document.getElementById(filterId);
        if (select.options.length > 1) return; // Populate only once
        select.innerHTML = '<option value="">Tous les utilisateurs</option>';
        allUsers.forEach(user => {
            select.add(new Option(`${user.prenom} ${user.nom}`, user.user_id));
        });
    });
}

function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById(tabName).classList.add('active');
    document.querySelector(`.tab[data-tab='${tabName}']`).classList.add('active');

    if (tabName !== 'scanner' && codeReader) stopScanning();
    
    // Use a single data fetch function and then render the specific tab
    const needsDataRefresh = ['inventory', 'booking', 'manage_categories'].includes(tabName);
    if (needsDataRefresh) {
        loadingOverlay.style.display = 'flex';
        fetchAllData().then(() => {
            if (tabName === 'inventory') renderInventoryTab();
            else if (tabName === 'booking') renderBookingTab();
            else if (tabName === 'manage_categories') {
                renderCategoriesList();
            }
            loadingOverlay.style.display = 'none';
        });
    }
}

function renderCategoriesList() {
    const toolList = document.getElementById('toolCategoriesList');
    const vehicleList = document.getElementById('vehicleCategoriesList');
    toolList.innerHTML = ''; 
    vehicleList.innerHTML = '';
    assetCategories.forEach(cat => {
        const li = document.createElement('li');
        li.innerHTML = `<span>${cat.category_name}</span><div class="category-actions"><button class="btn btn-outline-primary btn-sm" onclick="openModifyCategoryModal(${cat.category_id}, '${escapeSingleQuotes(cat.category_name)}')"><i class="fas fa-pencil-alt"></i></button><button class="btn btn-outline-danger btn-sm" onclick="handleDeleteCategory(${cat.category_id}, '${escapeSingleQuotes(cat.category_name)}')"><i class="fas fa-trash"></i></button></div>`;
        if (cat.category_type === 'tool') toolList.appendChild(li); else vehicleList.appendChild(li);
    });
    if (toolList.innerHTML === '') toolList.innerHTML = '<li>Aucune catégorie d\'outil.</li>';
    if (vehicleList.innerHTML === '') vehicleList.innerHTML = '<li>Aucune catégorie de véhicule.</li>';
}

async function handleCreateCategory(e) {
    e.preventDefault();
    const form = e.target;
    const categoryData = {
        category_name: document.getElementById('new_category_name').value,
        category_type: document.getElementById('new_category_type').value
    };
    loadingOverlay.style.display = 'flex';
    try {
        await apiCall('add_category', 'POST', categoryData);
        showNotification('Catégorie créée !', 'success');
        form.reset();
        await fetchAllData();
        renderCategoriesList();
        renderInventoryTab();
    } finally {
        loadingOverlay.style.display = 'none';
    }
}

function openModifyCategoryModal(categoryId, currentName) {
    document.getElementById('modifyCategoryId').value = categoryId;
    document.getElementById('modifyCategoryName').value = currentName;
    $('#modifyCategoryModal').modal('show');
}

async function handleUpdateCategory() {
    const categoryData = {
        category_id: document.getElementById('modifyCategoryId').value,
        category_name: document.getElementById('modifyCategoryName').value
    };
    if (!categoryData.category_name.trim()) {
        showNotification("Le nom ne peut pas être vide.", "error");
        return;
    }
    loadingOverlay.style.display = 'flex';
    try {
        await apiCall('update_category', 'POST', categoryData);
        showNotification('Catégorie mise à jour !', 'success');
        $('#modifyCategoryModal').modal('hide');
        await fetchAllData();
        renderCategoriesList();
        renderInventoryTab();
    } finally {
        loadingOverlay.style.display = 'none';
    }
}

async function handleDeleteCategory(categoryId, categoryName) {
    if (!confirm(`Voulez-vous vraiment supprimer la catégorie "${categoryName}" ? Tous les actifs de cette catégorie seront non-catégorisés.`)) return;
    loadingOverlay.style.display = 'flex';
    try {
        await apiCall('delete_category', 'POST', { category_id: categoryId });
        showNotification('Catégorie supprimée.', 'success');
        await fetchAllData();
        renderCategoriesList();
        renderInventoryTab();
    } finally {
        loadingOverlay.style.display = 'none';
    }
}

// --- INVENTORY TAB (FILTER LOGIC IMPROVED) ---
function renderCategoryFilters() {
    const container = document.getElementById('categoryFilterContainer');
    const typeFilter = document.getElementById('filterType').value;
    const relevantCategories = assetCategories.filter(cat => typeFilter === 'all' || cat.category_type === typeFilter);
    if (relevantCategories.length < 1) { container.innerHTML = ''; container.style.display = 'none'; return; }
    container.style.display = 'flex';
    let buttonsHTML = `<button class="btn ${selectedCategoryId === 'all' ? 'btn-primary' : 'btn-outline-secondary'}" data-category-id="all">Toutes les catégories</button>`;
    relevantCategories.forEach(cat => {
        buttonsHTML += `<button class="btn ${String(selectedCategoryId) === String(cat.category_id) ? 'btn-primary' : 'btn-outline-secondary'}" data-category-id="${cat.category_id}">${cat.category_name}</button>`;
    });
    container.innerHTML = buttonsHTML;
}

function updateFiltersAndRender() {
    renderInventory();
}

function renderInventory() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const typeFilter = document.getElementById('filterType').value;
    const statusFilter = document.getElementById('filterStatus').value;

    const filtered = inventory.filter(asset => {
        const s = ((asset.serial_or_plate || '') + (asset.asset_name || '') + (asset.brand || '') + (asset.barcode || '')).toLowerCase();
        const matchesSearch = s.includes(searchTerm);
        const matchesType = typeFilter === 'all' || asset.asset_type === typeFilter;
        const matchesCategory = selectedCategoryId === 'all' || String(asset.category_id) === selectedCategoryId;
        const matchesStatus = statusFilter === 'all' || asset.status === statusFilter;

        return matchesSearch && matchesType && matchesStatus && matchesCategory;
    });

    inventoryGrid.innerHTML = '';
    if (filtered.length === 0) {
        inventoryGrid.innerHTML = `<div class="col-12 text-center text-muted mt-5"><h4>Aucun actif ne correspond à vos critères.</h4></div>`;
    }
    filtered.forEach(asset => inventoryGrid.appendChild(createAssetCard(asset)));
}

function createAssetCard(asset) {
    const card = document.createElement('div');
    
    let assignedToText = '';
    let statusText = '';
    let cardStatusClass = asset.status;

    if (asset.status === 'in-use') {
        statusText = 'En cours d\'utilisation';
        assignedToText = `<strong>Assigné à:</strong> ${asset.assigned_to_prenom || ''} ${asset.assigned_to_nom || ''}<br><strong>Mission:</strong> ${asset.assigned_mission || 'N/A'}<br>`;
    } else if (asset.status === 'available') {
        const isBookedForToday = asset.todays_booking_user_id !== null && asset.todays_booking_user_id !== undefined;
        if(isBookedForToday) {
            statusText = 'Réservé aujourd\'hui';
            cardStatusClass = 'in-use'; // Visually treat as in-use
            assignedToText = `<strong>Réservé par:</strong> ${asset.todays_booking_prenom || 'Équipe'}<br><strong>Mission:</strong> ${asset.todays_booking_mission || 'N/A'}<br>`;
        } else {
            statusText = 'Disponible';
        }
    } else if (asset.status === 'maintenance') {
        statusText = 'En maintenance';
    }

    card.className = `asset-card ${asset.asset_type} ${cardStatusClass}`;
    card.dataset.id = asset.asset_id;

    const bookingInfo = asset.status === 'available' && asset.next_future_booking_date 
        ? `<div class="booking-info mt-2"><i class="fas fa-calendar-check"></i> Prochaine résa: ${new Date(asset.next_future_booking_date + 'T00:00:00').toLocaleDateString('fr-FR')}</div>` 
        : '';

    const details = asset.asset_type === 'tool' 
        ? `<strong>N° de série:</strong> ${asset.serial_or_plate || 'N/A'}<br><strong>Emplacement:</strong> ${asset.position_or_info || 'N/A'}<br>` 
        : `<strong>Plaque:</strong> ${asset.serial_or_plate || 'N/A'}<br><strong>Carburant:</strong> ${asset.fuel_level || 'N/A'}<br>`;

    let buttons = '';
    if (asset.status === 'available') {
        buttons += `<button class="btn btn-success btn-small" onclick="openBookingModal(${asset.asset_id})"><i class="fas fa-calendar-plus"></i> Réserver</button>`;
        buttons += `<button class="btn btn-warning btn-small" onclick="openMaintenanceModal(${asset.asset_id}, '${escapeSingleQuotes(asset.asset_name)}')"><i class="fas fa-tools"></i> Maint.</button>`;
    } else if (asset.status === 'maintenance') {
        buttons += `<button class="btn btn-info btn-small" onclick="setAssetAvailable(${asset.asset_id})"><i class="fas fa-check-circle"></i> Rendre Dispo.</button>`;
    }

    if (IS_ADMIN) {
        buttons += `<button class="btn btn-primary btn-small" onclick="openEditModal(${asset.asset_id})"><i class="fas fa-pencil-alt"></i></button>`;
        buttons += `<button class="btn btn-danger btn-small" onclick="handleDeleteAsset(${asset.asset_id}, '${escapeSingleQuotes(asset.asset_name)}')"><i class="fas fa-trash"></i></button>`;
    }

    card.innerHTML = `
        <div>
            <div class="asset-header">
                <span class="asset-title" onclick="openHistoryModal(${asset.asset_id}, '${escapeSingleQuotes(asset.asset_name)}')"><i class="fas ${asset.asset_type === 'tool' ? 'fa-wrench' : 'fa-car'} mr-2"></i>${asset.asset_name}</span>
                <span class="asset-status status-${cardStatusClass}">${statusText}</span>
            </div>
            <div class="asset-details">
                <strong>Code-barres:</strong> ${asset.barcode}<br>
                ${details} ${assignedToText}
            </div>
            ${bookingInfo}
        </div>
        <div class="asset-actions mt-3">${buttons}</div>`;
    return card;
}

function escapeSingleQuotes(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/'/g, "\\'");
}

// --- BOOKING TAB & HISTORY MODAL RENDERING ---
function renderIndividualBookingsTable() {
    const tableBody = document.getElementById('individual-active-bookings-table');
    const dateFilter = document.getElementById('individualFilterDate')._flatpickr.input.value;
    const userFilter = document.getElementById('individualFilterUser').value;
    const missionFilter = document.getElementById('individualFilterMission').value.toLowerCase();

    const filtered = allBookings.individual.filter(b => 
        (!dateFilter || b.booking_date === dateFilter) &&
        (!userFilter || b.user_id == userFilter) &&
        (!missionFilter || (b.mission && b.mission.toLowerCase().includes(missionFilter)))
    );

    tableBody.innerHTML = '';
    if (filtered.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center">Aucune réservation correspondante.</td></tr>';
        return;
    }

    filtered.forEach(b => {
        const row = tableBody.insertRow();
        row.innerHTML = `
            <td>${new Date(b.booking_date + 'T00:00:00').toLocaleDateString('fr-FR')}</td>
            <td>${b.asset_name || '(Actif supprimé)'}</td>
            <td>${(b.prenom && b.nom) ? `${b.prenom} ${b.nom}` : '(Utilisateur supprimé)'}</td>
            <td>${b.mission || 'N/A'}</td>
            <td><span class="badge badge-pill badge-${b.status === 'booked' ? 'primary' : 'success'}">${b.status}</span></td>
            <td>${(b.status === 'booked' && (IS_ADMIN || b.user_id == CURRENT_USER_ID)) ? `<button class="btn btn-danger btn-sm" onclick="handleCancelBooking(${b.booking_id})">Annuler</button>` : ''}</td>
        `;
    });
}

function renderMissionBookingsTable() {
    const tableBody = document.getElementById('mission-active-bookings-table');
    const dateFilter = document.getElementById('missionFilterDate')._flatpickr.input.value;
    const missionFilter = document.getElementById('missionFilterMission').value.toLowerCase();

    const filtered = allBookings.mission.filter(b => 
        (!dateFilter || b.booking_date === dateFilter) &&
        (!missionFilter || (b.mission && b.mission.toLowerCase().includes(missionFilter)))
    );
    
    tableBody.innerHTML = '';
    if (filtered.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="5" class="text-center">Aucune réservation correspondante.</td></tr>';
        return;
    }

    filtered.forEach(b => {
        const row = tableBody.insertRow();
        row.innerHTML = `
            <td>${new Date(b.booking_date + 'T00:00:00').toLocaleDateString('fr-FR')}</td>
            <td>${b.mission || 'N/A'}</td>
            <td>${b.asset_name || '(Actif supprimé)'}</td>
            <td><span class="badge badge-pill badge-${b.status === 'booked' ? 'info' : 'success'}">${b.status}</span></td>
            <td>${(b.status === 'booked' && IS_ADMIN) ? `<button class="btn btn-danger btn-sm" onclick="handleCancelBooking(${b.booking_id})">Annuler</button>` : ''}</td>
        `;
    });
}

function renderUsageHistoryTable() {
    const tableBody = document.getElementById('usage-history-table');
    const dateFilter = document.getElementById('historyFilterDate')._flatpickr.input.value;
    const userFilter = document.getElementById('historyFilterUser').value;
    const missionFilter = document.getElementById('historyFilterMission').value.toLowerCase();

    const filtered = usageHistory.filter(h => 
        (!dateFilter || h.booking_date === dateFilter) &&
        (!userFilter || h.user_id == userFilter) &&
        (!missionFilter || (h.mission && h.mission.toLowerCase().includes(missionFilter)))
    );

    tableBody.innerHTML = '';
    if (filtered.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="5" class="text-center">Aucun historique correspondant.</td></tr>';
        return;
    }

    const statusBadges = {
        completed: 'badge-secondary',
        cancelled: 'badge-danger'
    };

    filtered.forEach(h => {
        const row = tableBody.insertRow();
        row.innerHTML = `
            <td>${new Date(h.booking_date + 'T00:00:00').toLocaleDateString('fr-FR')}</td>
            <td>${h.asset_name || '(Actif supprimé)'}</td>
            <td>${(h.prenom && h.nom) ? `${h.prenom} ${h.nom}` : 'N/A'}</td>
            <td>${h.mission || 'N/A'}</td>
            <td><span class="badge badge-pill ${statusBadges[h.status] || 'badge-light'}">${h.status}</span></td>
        `;
    });
}

async function handleCancelBooking(bookingId) {
    if (!confirm("Voulez-vous vraiment annuler cette réservation ?")) return;
    loadingOverlay.style.display = 'flex';
    try {
        await apiCall('cancel_booking', 'POST', { booking_id: bookingId });
        showNotification('Réservation annulée.', 'success');
        await fetchAllData();
        renderBookingTab();
        renderInventoryTab();
    } finally {
        loadingOverlay.style.display = 'none';
    }
}

// --- MODALS & FORMS LOGIC ---
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
    const futureBookingsDiv = document.getElementById('futureBookingsInfo');
    futureBookingsDiv.style.display = 'none';
    futureBookingsDiv.innerHTML = '';
    loadingOverlay.style.display = 'flex';
    try {
        const data = await apiCall('get_asset_availability', 'GET', { asset_id: assetId });
        datePicker.set('disable', data.booked_dates);
        if (data.booked_dates && data.booked_dates.length > 0) {
            const todayStr = new Date().toISOString().split('T')[0];
            const futureDates = data.booked_dates
                .filter(d => d > todayStr)
                .map(d => new Date(d + 'T00:00:00').toLocaleDateString('fr-FR'));
            if (futureDates.length > 0) {
                futureBookingsDiv.innerHTML = `<strong>Déjà réservé le:</strong> ${futureDates.join(', ')}`;
                futureBookingsDiv.style.display = 'block';
            }
        }
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
        await fetchAllData();
        renderInventoryTab();
        renderBookingTab();
    } finally {
        loadingOverlay.style.display = 'none';
    }
}

async function openHistoryModal(assetId, assetName) {
    $('#historyModalAssetName').text(assetName);
    const modalBody = $('#historyModalBody');
    modalBody.html('<div class="text-center"><div class="spinner-border text-primary"></div></div>');
    $('#historyModal').modal('show');
    try {
        const data = await apiCall('get_asset_history', 'GET', { asset_id: assetId });
        if (data.history.length === 0) {
            modalBody.html('<p class="text-muted text-center">Aucun historique d\'utilisation pour cet actif.</p>');
            return;
        }
        let tableHtml = '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Date</th><th>Utilisateur</th><th>Mission</th><th>Statut</th></tr></thead><tbody>';
        data.history.forEach(rec => {
            tableHtml += `
                <tr>
                    <td>${new Date(rec.booking_date + 'T00:00:00').toLocaleDateString('fr-FR')}</td>
                    <td>${rec.prenom} ${rec.nom}</td>
                    <td>${rec.mission || 'N/A'}</td>
                    <td><span class="badge badge-info">${rec.status}</span></td>
                </tr>`;
        });
        tableHtml += '</tbody></table></div>';
        modalBody.html(tableHtml);
    } catch (error) {
        modalBody.html('<p class="text-danger text-center">Erreur lors du chargement de l\'historique.</p>');
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
    const videoElem = document.getElementById('video');
    if (videoElem && videoElem.srcObject) {
        videoElem.srcObject.getTracks().forEach(track => track.stop());
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
                await fetchAllData();
                renderInventoryTab();
                renderBookingTab();
                showTab('inventory');
                break;

            case 'prompt_booking':
                const asset = data.asset;
                if(confirm(`"${asset.asset_name}" n'est pas réservé pour aujourd'hui. Voulez-vous le réserver et le sortir maintenant ?`)) {
                    $('#bookingModalAssetId').val(asset.asset_id);
                    $('#bookingModalAssetName').text(asset.asset_name);
                    datePicker.set('disable', []);
                    datePicker.setDate(new Date(), true);
                    $('#booking_mission').val('Sortie via scan');
                    $('#bookingModal').modal('show');
                    
                    $('#saveBookingBtn').off('click').one('click', async function() {
                        const bookingData = { asset_id: $('#bookingModalAssetId').val(), booking_date: $('#booking_date').val(), mission: $('#booking_mission').val() };
                        if (!bookingData.booking_date) { showNotification("Date invalide.", "error"); return; }
                        
                        loadingOverlay.style.display = 'flex';
                        try {
                            await apiCall('book_asset', 'POST', bookingData);
                            $('#bookingModal').modal('hide');
                            document.getElementById('bookingForm').reset();
                            await processScanResult(barcode);
                        } finally {
                            loadingOverlay.style.display = 'none';
                             $('#saveBookingBtn').off('click').on('click', handleSaveBooking);
                        }
                    });
                }
                break;
            
            case 'asset_not_found':
                if (confirm(data.message)) {
                    showTab('add_asset');
                    $('#add_barcode').val(data.barcode);
                }
                break;
        }
    } catch(e) {
        /* error handled by apiCall */
    } finally {
        loadingOverlay.style.display = 'none';
        $('#bookingModal').on('hidden.bs.modal', function () {
            $('#saveBookingBtn').off('click').on('click', handleSaveBooking);
        });
    }
}

// --- MAINTENANCE & ASSET MANAGEMENT ---
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
        await fetchAllData();
        renderInventoryTab();
    } finally {
        loadingOverlay.style.display = 'none';
    }
}

async function setAssetAvailable(assetId) {
    await setMaintenanceStatus(assetId, 'available');
}

function toggleAssetFields(formPrefix) {
    const type = document.getElementById(`${formPrefix}_asset_type`).value;
    document.getElementById(`${formPrefix}_tool_fields`).style.display = (type === 'tool') ? 'block' : 'none';
    document.getElementById(`${formPrefix}_vehicle_fields`).style.display = (type === 'vehicle') ? 'block' : 'none';
    populateCategoryDropdowns(formPrefix);
}

function populateCategoryDropdowns(formPrefix, selectedCategoryId = null) {
    const assetTypeElement = document.getElementById(`${formPrefix}_asset_type`);
    if (!assetTypeElement) return;
    const assetType = assetTypeElement.value;
    const dropdown = document.getElementById(`${formPrefix}_category_id`);
    dropdown.innerHTML = '<option value="">-- Sans catégorie --</option>';
    if (assetCategories && assetCategories.length > 0) {
        assetCategories
            .filter(cat => cat.category_type === assetType)
            .forEach(cat => dropdown.add(new Option(cat.category_name, cat.category_id)));
    }
    if (selectedCategoryId) {
        dropdown.value = selectedCategoryId;
    }
}

async function handleAddAsset(e) {
    e.preventDefault();
    const type = document.getElementById('add_asset_type').value;
    const assetData = {
        barcode: document.getElementById('add_barcode').value,
        asset_type: type,
        asset_name: document.getElementById('add_asset_name').value,
        brand: document.getElementById('add_brand').value,
        category_id: document.getElementById('add_category_id').value || null,
        serial_or_plate: type === 'tool' ? document.getElementById('add_serial_or_plate_tool').value : document.getElementById('add_serial_or_plate_vehicle').value,
        position_or_info: type === 'tool' ? document.getElementById('add_position_or_info_tool').value : null,
        fuel_level: type === 'vehicle' ? document.getElementById('add_fuel_level').value : null,
    };
    loadingOverlay.style.display = 'flex';
    try {
        await apiCall('add_asset', 'POST', assetData);
        showNotification('Actif ajouté avec succès!', 'success');
        document.getElementById('addAssetForm').reset();
        toggleAssetFields('add');
        await fetchAllData();
        renderInventoryTab();
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
        await fetchAllData();
        renderInventoryTab();
        renderBookingTab();
    } finally {
        loadingOverlay.style.display = 'none';
    }
}

function openEditModal(assetId) {
    const asset = inventory.find(a => a.asset_id == assetId);
    if (!asset) { showNotification("Actif non trouvé.", "error"); return; }
    $('#edit_asset_id').val(asset.asset_id);
    $('#edit_asset_type').val(asset.asset_type);
    $('#edit_asset_name').val(asset.asset_name);
    $('#edit_barcode').val(asset.barcode);
    $('#edit_brand').val(asset.brand);
    toggleAssetFields('edit');
    populateCategoryDropdowns('edit', asset.category_id);
    if (asset.asset_type === 'tool') {
        $('#edit_serial_or_plate_tool').val(asset.serial_or_plate);
        $('#edit_position_or_info_tool').val(asset.position_or_info);
    } else { 
        $('#edit_serial_or_plate_vehicle').val(asset.serial_or_plate);
        $('#edit_fuel_level').val(asset.fuel_level);
    }
    $('#editAssetModal').modal('show');
}

async function handleUpdateAsset(e) {
    e.preventDefault();
    const type = document.getElementById('edit_asset_type').value;
    const assetData = {
        asset_id: document.getElementById('edit_asset_id').value,
        barcode: document.getElementById('edit_barcode').value,
        asset_type: type,
        asset_name: document.getElementById('edit_asset_name').value,
        brand: document.getElementById('edit_brand').value,
        category_id: document.getElementById('edit_category_id').value || null,
        serial_or_plate: type === 'tool' ? document.getElementById('edit_serial_or_plate_tool').value : document.getElementById('edit_serial_or_plate_vehicle').value,
        position_or_info: type === 'tool' ? document.getElementById('edit_position_or_info_tool').value : null,
        fuel_level: type === 'vehicle' ? document.getElementById('edit_fuel_level').value : null,
    };
    loadingOverlay.style.display = 'flex';
    try {
        await apiCall('update_asset', 'POST', assetData);
        showNotification('Actif mis à jour avec succès!', 'success');
        await fetchAllData();
        renderInventoryTab();
        $('#editAssetModal').modal('hide');
    } finally {
        loadingOverlay.style.display = 'none';
    }
}
</script>
</body>
</html>
