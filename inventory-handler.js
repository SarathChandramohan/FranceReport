// This function runs when the entire HTML document has been loaded.
document.addEventListener('DOMContentLoaded', function() {
    // Initial data load
    loadStats();
    loadAssets();
    loadCategories();
    populateStaticDropdowns();

    // Attach event listeners to UI elements
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', debounce(loadAssets, 300));
    }
    document.getElementById('typeFilter')?.addEventListener('change', loadAssets);
    document.getElementById('asset_type')?.addEventListener('change', handleAssetTypeChange);
    document.getElementById('assetForm')?.addEventListener('submit', saveAsset);
    document.getElementById('categoryForm')?.addEventListener('submit', saveCategory);

    // Clear modal forms when they are closed
    $('#assetModal').on('hidden.bs.modal', () => document.getElementById('assetForm').reset());
    $('#categoriesModal').on('hidden.bs.modal', () => document.getElementById('categoryForm').reset());
});

/**
 * A debounce function to limit the rate at which a function gets called.
 * @param {Function} func The function to debounce.
 * @param {number} delay The delay in milliseconds.
 */
function debounce(func, delay) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
}

// --- Data Loading and Rendering Functions ---

/**
 * Fetches and displays the main statistics cards.
 */
function loadStats() {
    fetch('inventory-handler.php?action=get_stats')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const stats = data.data;
                const container = document.getElementById('stats-overview');
                container.innerHTML = `
                    <div class="col-md-2 col-6 mb-3"><div class="stat-card total"><div class="stat-icon">📦</div><div class="stat-number">${stats.total}</div><div class="stat-label">Total</div></div></div>
                    <div class="col-md-2 col-6 mb-3"><div class="stat-card tools"><div class="stat-icon">🔧</div><div class="stat-number">${stats.tools}</div><div class="stat-label">Outils</div></div></div>
                    <div class="col-md-2 col-6 mb-3"><div class="stat-card vehicles"><div class="stat-icon">🚗</div><div class="stat-number">${stats.vehicles}</div><div class="stat-label">Véhicules</div></div></div>
                    <div class="col-md-2 col-6 mb-3"><div class="stat-card available"><div class="stat-icon">✅</div><div class="stat-number">${stats.available}</div><div class="stat-label">Disponibles</div></div></div>
                    <div class="col-md-2 col-6 mb-3"><div class="stat-card in-use"><div class="stat-icon">👤</div><div class="stat-number">${stats.in_use}</div><div class="stat-label">En Utilisation</div></div></div>
                    <div class="col-md-2 col-6 mb-3"><div class="stat-card maintenance"><div class="stat-icon">🛠️</div><div class="stat-number">${stats.maintenance}</div><div class="stat-label">En Maintenance</div></div></div>
                `;
            }
        })
        .catch(error => console.error('Error loading stats:', error));
}

/**
 * Fetches and renders the list of assets based on current filters.
 */
function loadAssets() {
    const search = document.getElementById('searchInput').value;
    const type = document.getElementById('typeFilter').value;
    const tbody = document.getElementById('assets-table-body');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center">Chargement...</td></tr>';

    fetch(`inventory-handler.php?action=get_assets&search=${search}&type=${type}`)
        .then(response => response.json())
        .then(data => {
            tbody.innerHTML = ''; // Clear loading message
            if (data.status === 'success' && data.data.length > 0) {
                data.data.forEach(asset => {
                    tbody.insertAdjacentHTML('beforeend', createAssetRowHtml(asset));
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">Aucun actif trouvé.</td></tr>';
            }
        })
        .catch(error => {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Erreur de chargement des actifs.</td></tr>';
            console.error('Error loading assets:', error);
        });
}

/**
 * Fetches categories and populates the category management modal and asset form dropdown.
 */
function loadCategories() {
    fetch('inventory-handler.php?action=get_categories')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const list = document.getElementById('categories-list');
                const select = document.getElementById('category_id');
                list.innerHTML = '';
                select.innerHTML = '<option value="">-- Sélectionner une catégorie --</option>';

                data.data.forEach(cat => {
                    list.insertAdjacentHTML('beforeend', `
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>${cat.category_name} <span class="badge badge-info">${cat.category_type}</span></span>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCategory(${cat.category_id})"><i class="fas fa-trash"></i></button>
                        </div>
                    `);
                    const option = new Option(cat.category_name, cat.category_id);
                    option.dataset.type = cat.category_type;
                    select.add(option);
                });
            }
        })
        .catch(error => console.error('Error loading categories:', error));
}


// --- Form and Modal Handling ---

/**
 * Prepares the asset modal for either adding a new asset or editing an existing one.
 * @param {number|null} assetId The ID of the asset to edit, or null to add a new one.
 */
function prepareAssetModal(assetId = null) {
    const form = document.getElementById('assetForm');
    form.reset();
    document.getElementById('asset_id').value = '';
    document.getElementById('modal-alert').style.display = 'none';

    handleAssetTypeChange(); // Show/hide correct fields

    if (assetId) {
        document.getElementById('assetModalLabel').textContent = 'Modifier l\'Actif';
        fetch(`inventory-handler.php?action=get_asset_details&asset_id=${assetId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const asset = data.data;
                    document.getElementById('asset_id').value = asset.asset_id;
                    document.getElementById('asset_type').value = asset.asset_type;
                    document.getElementById('barcode').value = asset.barcode;
                    document.getElementById('asset_name').value = asset.asset_name;
                    document.getElementById('brand').value = asset.brand;
                    
                    handleAssetTypeChange();
                    filterCategoryDropdown();
                    document.getElementById('category_id').value = asset.category_id;
                    
                    if (asset.asset_type === 'tool') {
                        document.getElementById('serial_or_plate_tool').value = asset.serial_or_plate;
                        document.getElementById('position_or_info_tool').value = asset.position_or_info;
                    } else {
                        document.getElementById('serial_or_plate_vehicle').value = asset.serial_or_plate;
                        document.getElementById('fuel_level').value = asset.fuel_level;
                    }
                    $('#assetModal').modal('show');
                } else {
                    alert(data.message);
                }
            });
    } else {
        document.getElementById('assetModalLabel').textContent = 'Ajouter un Actif';
        filterCategoryDropdown();
        $('#assetModal').modal('show');
    }
}

/**
 * Shows or hides asset-specific fields in the modal based on the selected asset type.
 */
function handleAssetTypeChange() {
    const type = document.getElementById('asset_type').value;
    document.getElementById('tool-fields-modal').style.display = (type === 'tool') ? 'block' : 'none';
    document.getElementById('vehicle-fields-modal').style.display = (type === 'vehicle') ? 'block' : 'none';
    filterCategoryDropdown();
}

/**
 * Filters the category dropdown to show only options relevant to the selected asset type.
 */
function filterCategoryDropdown() {
    const assetType = document.getElementById('asset_type').value;
    const categorySelect = document.getElementById('category_id');
    for (const option of categorySelect.options) {
        if (option.value === "" || option.dataset.type === assetType) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    }
    categorySelect.value = ""; // Reset selection
}

/**
 * Populates dropdowns that have static options.
 */
function populateStaticDropdowns() {
    const assetTypeSelect = document.getElementById('asset_type');
    const fuelLevelSelect = document.getElementById('fuel_level');
    const categoryTypeSelect = document.getElementById('category_type');

    if (assetTypeSelect) {
        assetTypeSelect.innerHTML = `
            <option value="tool">Outil</option>
            <option value="vehicle">Véhicule</option>
        `;
    }
    if (fuelLevelSelect) {
        fuelLevelSelect.innerHTML = `
            <option value="full">Plein</option>
            <option value="three-quarter">3/4</option>
            <option value="half">Moitié</option>
            <option value="quarter">1/4</option>
            <option value="empty">Vide</option>
        `;
    }
    if (categoryTypeSelect) {
        categoryTypeSelect.innerHTML = `
            <option value="tool">Outil</option>
            <option value="vehicle">Véhicule</option>
        `;
    }
}


// --- CRUD and Action Functions ---

/**
 * Handles the form submission for saving an asset (create or update).
 * @param {Event} event The form submission event.
 */
function saveAsset(event) {
    event.preventDefault();
    const form = document.getElementById('assetForm');
    const formData = new FormData(form);
    
    // Add the correct serial/plate based on type
    const assetType = formData.get('asset_type');
    if (assetType === 'tool') {
        formData.append('serial_or_plate', formData.get('serial_or_plate_tool'));
    } else {
        formData.append('serial_or_plate', formData.get('serial_or_plate_vehicle'));
    }

    fetch('inventory-handler.php?action=save_asset', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            $('#assetModal').modal('hide');
            loadAssets();
            loadStats();
        } else {
            const modalAlert = document.getElementById('modal-alert');
            modalAlert.textContent = data.message || 'Une erreur est survenue.';
            modalAlert.style.display = 'block';
        }
    })
    .catch(error => {
        const modalAlert = document.getElementById('modal-alert');
        modalAlert.textContent = 'Erreur de communication.';
        modalAlert.style.display = 'block';
        console.error('Error saving asset:', error);
    });
}

/**
 * Deletes an asset after confirmation.
 * @param {number} assetId The ID of the asset to delete.
 */
function deleteAsset(assetId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet actif ? Cette action est irréversible.')) {
        const formData = new FormData();
        formData.append('asset_id', assetId);

        fetch('inventory-handler.php?action=delete_asset', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                loadAssets();
                loadStats();
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => alert('Erreur de communication.'));
    }
}

/**
 * Saves a new category.
 * @param {Event} event The form submission event.
 */
function saveCategory(event) {
    event.preventDefault();
    const form = document.getElementById('categoryForm');
    const formData = new FormData(form);
    
    fetch('inventory-handler.php?action=save_category', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            loadCategories();
            form.reset();
        } else {
            alert('Erreur: ' + data.message);
        }
    })
    .catch(error => alert('Erreur de communication.'));
}

/**
 * Deletes a category after confirmation.
 * @param {number} categoryId The ID of the category to delete.
 */
function deleteCategory(categoryId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?')) {
        const formData = new FormData();
        formData.append('category_id', categoryId);

        fetch('inventory-handler.php?action=delete_category', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                loadCategories();
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => alert('Erreur de communication.'));
    }
}

/**
 * Assigns an available vehicle to the current user.
 * @param {number} assetId The ID of the vehicle to take.
 */
function takeVehicle(assetId) {
    if (confirm('Êtes-vous sûr de vouloir prendre ce véhicule ? Il sera assigné à votre nom.')) {
        const formData = new FormData();
        formData.append('asset_id', assetId);

        fetch('inventory-handler.php?action=take_vehicle', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                loadAssets();
                loadStats();
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => alert('Erreur de communication.'));
    }
}


// --- HTML Generation and UI Helpers ---

/**
 * Creates the HTML string for a single row in the asset table.
 * @param {object} asset The asset data object.
 * @returns {string} The HTML string for the table row.
 */
function createAssetRowHtml(asset) {
    const typeIcon = asset.asset_type === 'tool' ? '<i class="fas fa-tools text-secondary"></i>' : '<i class="fas fa-car text-info"></i>';
    const statusBadge = getStatusBadge(asset.status);
    const assignedUser = asset.assigned_user || '<i class="text-muted">Personne</i>';
    
    let takeButton = '';
    if (asset.asset_type === 'vehicle' && asset.status === 'available') {
        takeButton = `<button class="btn btn-sm btn-success" onclick="takeVehicle(${asset.asset_id})" title="Prendre ce véhicule"><i class="fas fa-key"></i> Prendre</button>`;
    }

    return `
        <tr>
            <td>${typeIcon} ${asset.asset_type.charAt(0).toUpperCase() + asset.asset_type.slice(1)}</td>
            <td><strong>${asset.asset_name}</strong><br><small class="text-muted">${asset.barcode}</small></td>
            <td>${asset.category_name || '<i class="text-muted">N/A</i>'}</td>
            <td>${statusBadge}</td>
            <td>${assignedUser}</td>
            <td class="text-right">
                <div class="btn-group">
                    ${takeButton}
                    <button class="btn btn-sm btn-info" onclick="prepareAssetModal(${asset.asset_id})" title="Modifier"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="deleteAsset(${asset.asset_id})" title="Supprimer"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>
    `;
}

/**
 * Generates an HTML badge for a given asset status.
 * @param {string} status The status string ('available', 'in-use', 'maintenance').
 * @returns {string} The HTML string for the badge.
 */
function getStatusBadge(status) {
    const statuses = {
        'available': { class: 'badge-success', text: 'Disponible' },
        'in-use': { class: 'badge-warning', text: 'En utilisation' },
        'maintenance': { class: 'badge-danger', text: 'En maintenance' }
    };
    const statusInfo = statuses[status] || { class: 'badge-secondary', text: status };
    return `<span class="badge ${statusInfo.class}">${statusInfo.text}</span>`;
}
