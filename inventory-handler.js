$(document).ready(function() {
    // Initial data load
    loadStats();
    loadAssets();
    loadCategories();

    // Event Listeners
    $('#searchInput, #typeFilter').on('input change', function() {
        loadAssets();
    });

    $('#asset_type').on('change', function() {
        toggleAssetFields();
        filterCategoryDropdown();
    });

    $('#assetForm').on('submit', function(e) {
        e.preventDefault();
        saveAsset();
    });

    $('#categoryForm').on('submit', function(e) {
        e.preventDefault();
        saveCategory();
    });

    // When closing a modal, clear its form
    $('.modal').on('hidden.bs.modal', function () {
        $(this).find('form')[0].reset();
    });
});

function loadStats() {
    $.getJSON('inventory-handler.php?action=get_stats', function(response) {
        if (response.status === 'success') {
            const stats = response.data;
            $('#stats-overview').html(`
                <div class="col-md-2 col-sm-4 mb-2"><div class="stat-card" style="border-color: #007bff;"><div class="stat-icon"><i class="fas fa-boxes"></i></div><div class="stat-number">${stats.total}</div><div class="stat-label">Total</div></div></div>
                <div class="col-md-2 col-sm-4 mb-2"><div class="stat-card" style="border-color: #6f42c1;"><div class="stat-icon"><i class="fas fa-tools"></i></div><div class="stat-number">${stats.tools}</div><div class="stat-label">Outils</div></div></div>
                <div class="col-md-2 col-sm-4 mb-2"><div class="stat-card" style="border-color: #17a2b8;"><div class="stat-icon"><i class="fas fa-car"></i></div><div class="stat-number">${stats.vehicles}</div><div class="stat-label">Véhicules</div></div></div>
                <div class="col-md-2 col-sm-4 mb-2"><div class="stat-card" style="border-color: #28a745;"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-number">${stats.available}</div><div class="stat-label">Disponibles</div></div></div>
                <div class="col-md-2 col-sm-4 mb-2"><div class="stat-card" style="border-color: #ffc107;"><div class="stat-icon"><i class="fas fa-user-cog"></i></div><div class="stat-number">${stats.in_use}</div><div class="stat-label">En Utilisation</div></div></div>
                <div class="col-md-2 col-sm-4 mb-2"><div class="stat-card" style="border-color: #dc3545;"><div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div><div class="stat-number">${stats.maintenance}</div><div class="stat-label">En Maintenance</div></div></div>
            `);
        }
    });
}

function loadAssets() {
    const search = $('#searchInput').val();
    const type = $('#typeFilter').val();

    $.getJSON(`inventory-handler.php?action=get_assets&search=${search}&type=${type}`, function(response) {
        if (response.status === 'success') {
            const tbody = $('#assets-table-body');
            tbody.empty();
            response.data.forEach(asset => {
                let typeIcon = asset.asset_type === 'tool' ? '<i class="fas fa-tools text-secondary"></i> Outil' : '<i class="fas fa-car text-info"></i> Véhicule';
                let statusBadge = getStatusBadge(asset.status);
                let assignedUser = asset.assigned_user || '<i class="text-muted">Personne</i>';
                
                let takeButton = '';
                if (asset.asset_type === 'vehicle' && asset.status === 'available') {
                    takeButton = `<button class="btn btn-sm btn-success" onclick="takeVehicle(${asset.asset_id})">Prendre</button>`;
                }

                tbody.append(`
                    <tr style="border-left: 5px solid ${asset.asset_type === 'tool' ? '#6f42c1' : '#17a2b8'};">
                        <td>${typeIcon}</td>
                        <td><strong>${asset.asset_name}</strong><br><small class="text-muted">${asset.barcode}</small></td>
                        <td>${asset.category_name || '<i class="text-muted">N/A</i>'}</td>
                        <td>${statusBadge}</td>
                        <td>${assignedUser}</td>
                        <td>
                            ${takeButton}
                            <button class="btn btn-sm btn-info" onclick="prepareAssetModal(${asset.asset_id})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="deleteAsset(${asset.asset_id})"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `);
            });
        }
    });
}

function prepareAssetModal(assetId = null) {
    $('#assetForm')[0].reset();
    $('#asset_id').val('');
    toggleAssetFields(); // Set initial state
    
    if (assetId) {
        $('#assetModalLabel').text('Modifier l\'Actif');
        $.getJSON(`inventory-handler.php?action=get_asset_details&asset_id=${assetId}`, function(response) {
            if (response.status === 'success') {
                const asset = response.data;
                $('#asset_id').val(asset.asset_id);
                $('#asset_type').val(asset.asset_type);
                $('#barcode').val(asset.barcode);
                $('#asset_name').val(asset.asset_name);
                $('#brand').val(asset.brand);
                $('#status').val(asset.status);
                
                toggleAssetFields(); // Update fields based on type
                filterCategoryDropdown(); // Filter categories for the asset's type
                $('#category_id').val(asset.category_id); // Set the category

                if(asset.asset_type === 'tool'){
                    $('#serial_or_plate_tool').val(asset.serial_or_plate);
                    $('#position_or_info_tool').val(asset.position_or_info);
                } else {
                    $('#serial_or_plate_vehicle').val(asset.serial_or_plate);
                    $('#fuel_level').val(asset.fuel_level);
                }

                $('#assetModal').modal('show');
            }
        });
    } else {
        $('#assetModalLabel').text('Ajouter un Actif');
        filterCategoryDropdown();
        $('#assetModal').modal('show');
    }
}

function toggleAssetFields() {
    const type = $('#asset_type').val();
    if (type === 'tool') {
        $('#tool-fields').show();
        $('#vehicle-fields').hide();
    } else {
        $('#tool-fields').hide();
        $('#vehicle-fields').show();
    }
}

function saveAsset() {
    // Correctly choose serial/plate based on type
    const assetType = $('#asset_type').val();
    let formData = $('#assetForm').serialize();
    if (assetType === 'tool') {
        formData += '&serial_or_plate=' + $('#serial_or_plate_tool').val();
    } else {
        formData += '&serial_or_plate=' + $('#serial_or_plate_vehicle').val();
    }

    $.post('inventory-handler.php?action=save_asset', formData, function(response) {
        if (response.status === 'success') {
            $('#assetModal').modal('hide');
            loadAssets();
            loadStats();
        } else {
            alert('Erreur: ' + response.message);
        }
    }, 'json');
}

function deleteAsset(assetId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet actif ? Cette action est irréversible.')) {
        $.post('inventory-handler.php?action=delete_asset', { asset_id: assetId }, function(response) {
            if (response.status === 'success') {
                loadAssets();
                loadStats();
            } else {
                alert('Erreur: ' + response.message);
            }
        }, 'json');
    }
}

function getStatusBadge(status) {
    let badgeClass = 'badge-secondary';
    let statusText = status;
    if (status === 'available') { badgeClass = 'badge-success'; statusText = 'Disponible'; }
    if (status === 'in-use') { badgeClass = 'badge-warning'; statusText = 'En utilisation'; }
    if (status === 'maintenance') { badgeClass = 'badge-danger'; statusText = 'En maintenance'; }
    return `<span class="badge ${badgeClass}">${statusText}</span>`;
}


function loadCategories() {
    $.getJSON('inventory-handler.php?action=get_categories', function(response) {
        if (response.status === 'success') {
            const list = $('#categories-list');
            const catSelect = $('#category_id');
            list.empty();
            catSelect.empty();
            catSelect.append('<option value="">Sélectionner une catégorie</option>');

            response.data.forEach(cat => {
                let typeText = cat.category_type === 'tool' ? 'Outil' : 'Véhicule';
                list.append(`
                    <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${cat.category_name}</strong>
                            <span class="badge badge-pill badge-info ml-2">${typeText}</span>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteCategory(${cat.category_id})"><i class="fas fa-trash"></i></button>
                    </div>
                `);
                catSelect.append(`<option value="${cat.category_id}" data-type="${cat.category_type}">${cat.category_name}</option>`);
            });
            filterCategoryDropdown();
        }
    });
}

function filterCategoryDropdown() {
    const assetType = $('#asset_type').val();
    let hasVisibleOptions = false;
    $('#category_id option').each(function() {
        if ($(this).val() === "") {
            $(this).show();
            return;
        }
        if ($(this).data('type') === assetType) {
            $(this).show();
            hasVisibleOptions = true;
        } else {
            $(this).hide();
        }
    });
     $('#category_id').val($('#category_id').find('option:visible:first').val());
}

function saveCategory() {
    const categoryData = {
        category_name: $('#category_name').val(),
        category_type: $('#category_type').val()
    };
    if(!categoryData.category_name) {
        alert("Le nom de la catégorie ne peut pas être vide.");
        return;
    }
    $.post('inventory-handler.php?action=save_category', categoryData, function(response) {
        if (response.status === 'success') {
            loadCategories();
            $('#categoryForm')[0].reset();
        } else {
            alert('Erreur: ' + response.message);
        }
    }, 'json');
}

function deleteCategory(categoryId) {
    if (confirm('Êtes-vous sûr ? La suppression d\'une catégorie la retirera de tous les actifs associés.')) {
        $.post('inventory-handler.php?action=delete_category', { category_id: categoryId }, function(response) {
            if (response.status === 'success') {
                loadCategories();
            } else {
                alert('Erreur: ' + response.message);
            }
        }, 'json');
    }
}

function takeVehicle(assetId) {
    if (confirm('Êtes-vous sûr de vouloir prendre ce véhicule ? Il sera assigné à votre nom.')) {
        $.post('inventory-handler.php?action=take_vehicle', { asset_id: assetId }, function(response) {
            if (response.status === 'success') {
                loadAssets();
                loadStats();
            } else {
                alert('Erreur: ' + response.message);
            }
        }, 'json');
    }
}
