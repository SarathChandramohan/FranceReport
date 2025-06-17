<?php
require_once 'session-management.php';
requireLogin();
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de l'Inventaire</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
        }
        .container-fluid {
            padding-top: 20px;
        }
        .card {
            background-color: #ffffff;
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 25px;
            padding: 25px;
        }
        .tabs {
            display: flex; flex-wrap: wrap; justify-content: center;
            gap: 15px; margin-bottom: 25px; padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        .tab {
            padding: 12px 25px; background: #e9ecef; color: #495057;
            border-radius: 8px; cursor: pointer; font-weight: 600;
            transition: all 0.3s ease; text-align: center;
        }
        .tab.active {
            background: #007bff; color: white;
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.25);
            transform: translateY(-2px);
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
        }
        .scanner-container {
            position: relative; width: 100%; max-width: 500px;
            margin: 0 auto 20px; background: #2c3e50;
            border-radius: 15px; overflow: hidden;
        }
        #video { width: 100%; height: auto; display: none; }
        .scanner-placeholder {
            width: 100%; min-height: 300px; background: #34495e;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 1.2em; text-align: center;
        }
        .scan-overlay {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 80%; max-width: 250px; height: 60%; max-height: 150px;
            border: 3px solid rgba(0, 255, 0, 0.8);
            border-radius: 10px; display: none;
        }
        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 25px;
        }
        .asset-card {
            background: white; border-radius: 15px; padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.07);
            transition: all 0.3s ease; position: relative;
            border-left: 5px solid; display: flex;
            flex-direction: column; justify-content: space-between;
        }
        .asset-card.tool { border-left-color: #28a745; }
        .asset-card.vehicle { border-left-color: #007bff; }
        .asset-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.1);
        }
        .asset-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .asset-title { font-size: 1.2em; font-weight: 700; color: #343a40; }
        .asset-status {
            padding: 5px 12px; border-radius: 20px;
            font-size: 11px; font-weight: bold; text-transform: uppercase;
        }
        .status-available { background: #d4edda; color: #155724; }
        .status-in-use { background: #fff3cd; color: #856404; }
        .status-maintenance { background: #f8d7da; color: #721c24; }
        .asset-details { color: #6c757d; line-height: 1.6; margin-bottom: 20px; font-size: 0.9em; }
        .asset-details strong { color: #495057; }
        .asset-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: auto; }
        .btn-small { padding: 5px 10px; font-size: 12px; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }
        .stat-item { text-align: center; padding: 20px; background: #f8f9fa; border-radius: 10px; }
        .stat-number { font-size: 2.5em; font-weight: bold; color: #007bff; margin-bottom: 5px; }
        .stat-label { color: #6c757d; font-weight: 500; }
        .notification {
            position: fixed; top: 80px; right: 20px;
            padding: 15px 25px; border-radius: 8px;
            color: white; font-weight: 600; z-index: 9999;
            transform: translateX(calc(100% + 30px));
            transition: transform 0.4s ease-in-out;
        }
        .notification.success { background: #28a745; }
        .notification.error { background: #dc3545; }
        .notification.show { transform: translateX(0); }
        .loading-overlay {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255, 255, 255, 0.85);
            display: flex; align-items: center; justify-content: center;
            z-index: 10; border-radius: 15px;
        }
        #empty-inventory-message {
            display: none; text-align: center;
            padding: 50px; color: #6c757d;
        }
        #empty-inventory-message .fa-dolly {
            font-size: 4rem; margin-bottom: 20px;
        }
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
            <div class="tab" data-tab="add_asset"><i class="fas fa-plus-circle"></i> Ajouter un Actif</div>
            <div class="tab" data-tab="scanner"><i class="fas fa-barcode"></i> Scanner</div>
            <div class="tab" data-tab="stats"><i class="fas fa-chart-pie"></i> Statistiques</div>
        </div>
    </div>

    <div id="inventory" class="tab-content active">
        <div class="card position-relative">
             <div class="loading-overlay" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                <h3 class="mb-2">Liste des Actifs</h3>
                <div class="form-inline">
                    <input type="text" class="form-control mr-sm-2 mb-2" id="searchInput" placeholder="Rechercher...">
                    <select id="filterType" class="form-control mr-sm-2 mb-2">
                        <option value="all">Tous les types</option>
                        <option value="tool">Outils</option>
                        <option value="vehicle">Véhicules</option>
                    </select>
                    <select id="filterStatus" class="form-control mb-2">
                        <option value="all">Tous les statuts</option>
                        <option value="available">Disponible</option>
                        <option value="in-use">En cours d'utilisation</option>
                        <option value="maintenance">En maintenance</option>
                    </select>
                </div>
            </div>
            <div id="inventoryGrid" class="inventory-grid"></div>
            <div id="empty-inventory-message">
                <i class="fas fa-dolly"></i>
                <h3>L'inventaire est vide</h3>
                <p>Commencez par ajouter un nouvel actif.</p>
            </div>
        </div>
    </div>

    <div id="add_asset" class="tab-content">
        <div class="card">
            <h3>Ajouter un Nouvel Actif Manuellement</h3>
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
                    <div class="form-group col-md-6" id="category_field">
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

    <div id="scanner" class="tab-content">
        <div class="card">
             <h3 class="text-center mb-4">Scanner un Code-barres</h3>
             <div class="scanner-container">
                <video id="video" autoplay playsinline></video>
                <div id="scannerPlaceholder" class="scanner-placeholder">
                    <div> <i class="fas fa-camera fa-3x mb-3"></i><br> La caméra est inactive </div>
                </div>
                <div id="scanOverlay" class="scan-overlay"></div>
            </div>
            <div class="text-center mt-3">
                <button id="startScanBtn" class="btn btn-success"><i class="fas fa-play"></i> Démarrer</button>
                <button id="stopScanBtn" class="btn btn-danger" style="display: none;"><i class="fas fa-stop"></i> Arrêter</button>
            </div>
        </div>
    </div>

    <div id="stats" class="tab-content">
        <div class="card">
             <h3 class="text-center mb-4">Statistiques de l'Inventaire</h3>
             <div id="statsGrid" class="stats-grid"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="statusModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Changer le Statut de <span id="modalAssetName"></span></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="statusChangeForm">
            <input type="hidden" id="modalAssetId">
            <div class="form-group">
                <label for="newStatus">Nouveau Statut</label>
                <select id="newStatus" class="form-control"></select>
            </div>
            <div id="assignmentFields" style="display: none;">
                 <div class="form-group">
                    <label for="assigned_to_user_id">Assigner à</label>
                    <select id="assigned_to_user_id" class="form-control"></select>
                </div>
                <div class="form-group">
                    <label for="assigned_mission">Mission</label>
                    <input type="text" id="assigned_mission" class="form-control" placeholder="Description de la mission">
                </div>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-primary" id="saveStatusChange">Sauvegarder</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://unpkg.com/@zxing/library@latest/umd/index.min.js"></script>

<script>
// --- CONFIGURATION ---
// **CRITICAL FIX V3**: Using a simple relative path.
// This assumes `inventory.php` and `inventory_handler.php` are in the same directory.
const HANDLER_URL = 'inventory-handler.php';
const IS_ADMIN = <?php echo ($currentUser['role'] === 'admin') ? 'true' : 'false'; ?>;

// --- GLOBAL STATE ---
let inventory = [];
let assetCategories = [];
let userList = [];
let codeReader = null;
let currentStream = null;

// --- DOM ELEMENTS ---
const inventoryGrid = document.getElementById('inventoryGrid');
const loadingOverlay = document.querySelector('.loading-overlay');
const emptyInventoryMessage = document.getElementById('empty-inventory-message');

// --- HELPER FUNCTIONS ---
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

function handleApiError(error) {
    console.error('API Error:', error);
    showNotification(error.message || 'Une erreur de communication est survenue.', 'error');
    loadingOverlay.style.display = 'none';
}

// --- API COMMUNICATION ---
async function apiCall(action, method = 'GET', body = null) {
    const options = { method, headers: { 'Content-Type': 'application/json' } };
    if (body && method !== 'GET') {
        options.body = JSON.stringify(body);
    }

    let url = `${HANDLER_URL}?action=${action}`;
    if (method === 'GET' && body) {
         url += '&' + new URLSearchParams(body).toString();
    }

    try {
        const response = await fetch(url, options);
        // Check if the server responded with an error code
        if (!response.ok) {
             throw new Error(`Le serveur a répondu avec une erreur ${response.status} (Not Found). Vérifiez que le fichier inventory_handler.php existe et est au bon endroit.`);
        }
        const data = await response.json();
        if (data.status !== 'success') {
            throw new Error(data.message);
        }
        return data;
    } catch (error) {
        handleApiError(error);
        throw error;
    }
}

// --- INITIALIZATION ---
document.addEventListener('DOMContentLoaded', async () => {
    setupEventListeners();
    loadingOverlay.style.display = 'flex';
    await fetchInitialData();
    loadingOverlay.style.display = 'none';
    renderInventory();
    updateStatistics();
    populateCategoryDropdown('tool');
    populateUserDropdown();
});

async function fetchInitialData() {
    try {
        const [inventoryData, categoriesData, usersData] = await Promise.all([
            apiCall('get_inventory'),
            apiCall('get_categories'),
            apiCall('get_users')
        ]);
        inventory = inventoryData.inventory || [];
        assetCategories = categoriesData.categories || [];
        userList = usersData.users || [];
    } catch (error) { /* Error already handled by apiCall */ }
}

function setupEventListeners() {
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', (e) => showTab(e.currentTarget.dataset.tab));
    });
    document.getElementById('searchInput').addEventListener('keyup', renderInventory);
    document.getElementById('filterType').addEventListener('change', renderInventory);
    document.getElementById('filterStatus').addEventListener('change', renderInventory);
    document.getElementById('addAssetForm').addEventListener('submit', handleAddAsset);
    document.getElementById('asset_type').addEventListener('change', toggleAssetFields);
    document.getElementById('startScanBtn').addEventListener('click', startScanning);
    document.getElementById('stopScanBtn').addEventListener('click', stopScanning);
    $('#statusModal').on('change', '#newStatus', toggleAssignmentFieldsModal);
    document.getElementById('saveStatusChange').addEventListener('click', handleSaveStatusChange);
}

// --- UI & RENDERING ---
function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById(tabName).classList.add('active');
    document.querySelector(`.tab[data-tab='${tabName}']`).classList.add('active');
    
    if (tabName !== 'scanner' && currentStream) stopScanning();
}

function renderInventory() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const typeFilter = document.getElementById('filterType').value;
    const statusFilter = document.getElementById('filterStatus').value;

    const filtered = inventory.filter(asset => {
        const s = asset.serial_or_plate || '';
        const p = asset.position_or_info || '';
        const an = asset.asset_name || '';
        const b = asset.brand || '';
        const bc = asset.barcode || '';
        const matchesSearch = `${s} ${p} ${an} ${b} ${bc}`.toLowerCase().includes(searchTerm);
        const matchesType = typeFilter === 'all' || asset.asset_type === typeFilter;
        const matchesStatus = statusFilter === 'all' || asset.status === statusFilter;
        return matchesSearch && matchesType && matchesStatus;
    });

    inventoryGrid.innerHTML = '';
    emptyInventoryMessage.style.display = filtered.length > 0 ? 'none' : 'block';
    filtered.forEach(asset => inventoryGrid.appendChild(createAssetCard(asset)));
}

function createAssetCard(asset) {
    const card = document.createElement('div');
    card.className = `asset-card ${asset.asset_type}`;
    card.dataset.id = asset.asset_id;

    const assignedTo = asset.assigned_to_user_id ?
        `<strong>Assigné à:</strong> ${asset.assigned_to_prenom || ''} ${asset.assigned_to_nom || ''}<br>
         <strong>Mission:</strong> ${asset.assigned_mission || 'Non spécifiée'}<br>` : '';

    const details = asset.asset_type === 'tool' ? `
        <strong>N° de série:</strong> ${asset.serial_or_plate || 'N/A'}<br>
        <strong>Emplacement:</strong> ${asset.position_or_info || 'N/A'}<br>
    ` : `
        <strong>Plaque:</strong> ${asset.serial_or_plate || 'N/A'}<br>
        <strong>Carburant:</strong> ${asset.fuel_level || 'N/A'}<br>
    `;

    const deleteBtn = IS_ADMIN ? `<button class="btn btn-danger btn-small" onclick="handleDeleteAsset(${asset.asset_id}, '${asset.asset_name.replace(/'/g, "\\'")}')">Supprimer</button>` : '';

    card.innerHTML = `
        <div>
            <div class="asset-header">
                <span class="asset-title"><i class="fas ${asset.asset_type === 'tool' ? 'fa-wrench' : 'fa-car'} mr-2"></i>${asset.asset_name}</span>
                <span class="asset-status status-${asset.status}">${asset.status.replace('-', ' ')}</span>
            </div>
            <div class="asset-details">
                <strong>Code-barres:</strong> ${asset.barcode}<br>
                <strong>Marque:</strong> ${asset.brand || 'N/A'}<br>
                <strong>Catégorie:</strong> ${asset.category_name || 'N/A'}<br>
                ${details} ${assignedTo}
                <strong>Ajouté le:</strong> ${new Date(asset.date_added).toLocaleDateString()}
            </div>
        </div>
        <div class="asset-actions">
            <button class="btn btn-primary btn-small" onclick="openStatusModal(${asset.asset_id})">Statut</button>
            ${deleteBtn}
        </div>
    `;
    return card;
}

// --- FORM HANDLING & LOGIC ---
function toggleAssetFields() {
    const type = document.getElementById('asset_type').value;
    document.getElementById('tool_fields').style.display = (type === 'tool') ? 'block' : 'none';
    document.getElementById('vehicle_fields').style.display = (type === 'vehicle') ? 'block' : 'none';
    populateCategoryDropdown(type);
}

function populateCategoryDropdown(assetType) {
    const dropdown = document.getElementById('category_id');
    dropdown.innerHTML = '<option value="">-- Sans catégorie --</option>';
    assetCategories.filter(cat => cat.category_type === assetType)
        .forEach(cat => dropdown.add(new Option(cat.category_name, cat.category_id)));
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
    try {
        const data = await apiCall('add_asset', 'POST', assetData);
        inventory.push(data.asset);
        renderInventory();
        updateStatistics();
        showNotification('Actif ajouté avec succès!', 'success');
        e.target.reset();
        toggleAssetFields();
        showTab('inventory');
    } catch (error) { /* Handled by apiCall */ }
}

// --- SCANNER LOGIC ---
function startScanning() {
    codeReader = new ZXing.BrowserMultiFormatReader();
    const elements = {
        start: document.getElementById('startScanBtn'), stop: document.getElementById('stopScanBtn'),
        placeholder: document.getElementById('scannerPlaceholder'), video: document.getElementById('video'),
        overlay: document.getElementById('scanOverlay')
    };
    elements.start.style.display = 'none';
    elements.stop.style.display = 'inline-block';
    elements.placeholder.style.display = 'none';
    elements.video.style.display = 'block';
    elements.overlay.style.display = 'block';

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(stream => {
            currentStream = stream;
            codeReader.decodeFromStream(stream, 'video', (result, err) => {
                if (result) {
                    processBarcode(result.text);
                }
                if (err && !(err instanceof ZXing.NotFoundException)) {
                    showNotification('Erreur de scan.', 'error');
                }
            });
        }).catch(() => { showNotification("Erreur d'accès à la caméra.", 'error'); stopScanning(); });
}

function stopScanning() {
    if (codeReader) { codeReader.reset(); codeReader = null; }
    if (currentStream) { currentStream.getTracks().forEach(track => track.stop()); currentStream = null; }
    document.getElementById('startScanBtn').style.display = 'inline-block';
    document.getElementById('stopScanBtn').style.display = 'none';
    document.getElementById('scannerPlaceholder').style.display = 'flex';
    document.getElementById('video').style.display = 'none';
    document.getElementById('scanOverlay').style.display = 'none';
}

async function processBarcode(barcode) {
    showNotification(`Code-barres détecté : ${barcode}`);
    stopScanning();
    loadingOverlay.style.display = 'flex';
    try {
        const data = await apiCall('check_barcode', 'GET', { barcode });
        if (data.exists) {
            showTab('inventory');
            showNotification('Actif existant trouvé et mis en surbrillance.', 'success');
            setTimeout(() => {
                const card = document.querySelector(`.asset-card[data-id='${data.asset.asset_id}']`);
                if (card) {
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    card.style.transition = 'all 0.3s ease';
                    card.style.transform = 'scale(1.05)';
                    card.style.boxShadow = '0 0 25px rgba(0, 123, 255, 0.5)';
                    setTimeout(() => { card.style.transform = ''; card.style.boxShadow = ''; }, 2000);
                }
            }, 100);
        } else {
            showTab('add_asset');
            document.getElementById('barcode').value = barcode;
            showNotification('Nouvel actif. Veuillez remplir les détails.', 'success');
        }
    } finally {
        loadingOverlay.style.display = 'none';
    }
}

// --- STATUS & DELETE LOGIC ---
function populateUserDropdown() {
    const dropdown = document.getElementById('assigned_to_user_id');
    dropdown.innerHTML = '<option value="">-- Personne --</option>';
    userList.forEach(user => dropdown.add(new Option(`${user.prenom} ${user.nom}`, user.user_id)));
}

function openStatusModal(assetId) {
    const asset = inventory.find(a => a.asset_id == assetId);
    if (!asset) return;
    
    $('#modalAssetName').text(asset.asset_name);
    $('#modalAssetId').val(asset.asset_id);
    
    const statusSelect = document.getElementById('newStatus');
    statusSelect.innerHTML = `
        <option value="available">Disponible</option>
        <option value="in-use">En cours d'utilisation</option>
        <option value="maintenance">En maintenance</option>
    `;
    statusSelect.value = asset.status;
    
    $('#assigned_to_user_id').val(asset.assigned_to_user_id || '');
    $('#assigned_mission').val(asset.assigned_mission || '');
    toggleAssignmentFieldsModal();
    
    $('#statusModal').modal('show');
}

function toggleAssignmentFieldsModal() {
    $('#assignmentFields').css('display', $('#newStatus').val() === 'in-use' ? 'block' : 'none');
}

async function handleSaveStatusChange() {
    const updateData = {
        asset_id: $('#modalAssetId').val(),
        status: $('#newStatus').val(),
        assigned_to_user_id: $('#newStatus').val() === 'in-use' ? $('#assigned_to_user_id').val() || null : null,
        assigned_mission: $('#newStatus').val() === 'in-use' ? $('#assigned_mission').val() : null,
    };
    
    try {
        const data = await apiCall('update_asset_status', 'POST', updateData);
        const index = inventory.findIndex(a => a.asset_id == data.asset.asset_id);
        if (index > -1) inventory[index] = data.asset;
        renderInventory();
        updateStatistics();
        $('#statusModal').modal('hide');
        showNotification('Statut mis à jour avec succès!', 'success');
    } catch (error) { /* Handled by apiCall */ }
}

async function handleDeleteAsset(assetId, assetName) {
    if (!confirm(`Êtes-vous sûr de vouloir supprimer l'actif "${assetName}" ? Cette action est irréversible.`)) return;

    try {
        await apiCall('delete_asset', 'POST', { asset_id: assetId });
        inventory = inventory.filter(a => a.asset_id != assetId);
        renderInventory();
        updateStatistics();
        showNotification('Actif supprimé avec succès!', 'success');
    } catch (error) { /* Handled by apiCall */ }
}

// --- STATISTICS ---
function updateStatistics() {
    const stats = {
        total: inventory.length,
        tools: inventory.filter(item => item.asset_type === 'tool').length,
        vehicles: inventory.filter(item => item.asset_type === 'vehicle').length,
        available: inventory.filter(item => item.status === 'available').length,
        inUse: inventory.filter(item => item.status === 'in-use').length,
        maintenance: inventory.filter(item => item.status === 'maintenance').length
    };
    const grid = document.getElementById('statsGrid');
    grid.innerHTML = `
        <div class="stat-item"><div class="stat-number">${stats.total}</div><div class="stat-label">Actifs Totaux</div></div>
        <div class="stat-item"><div class="stat-number">${stats.tools}</div><div class="stat-label">Outils</div></div>
        <div class="stat-item"><div class="stat-number">${stats.vehicles}</div><div class="stat-label">Véhicules</div></div>
        <div class="stat-item"><div class="stat-number">${stats.available}</div><div class="stat-label">Disponibles</div></div>
        <div class="stat-item"><div class="stat-number">${stats.inUse}</div><div class="stat-label">En Utilisation</div></div>
        <div class="stat-item"><div class="stat-number">${stats.maintenance}</div><div class="stat-label">En Maintenance</div></div>
    `;
}
</script>

</body>
</html>
