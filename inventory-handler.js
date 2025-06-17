$(document).ready(function() {
    loadStats();
    loadAssets();

    $('#searchInput, #typeFilter').on('input change', function() {
        loadAssets();
    });

    $('#asset_type').on('change', function() {
        toggleAssetFields();
    });

    $('#assetForm').on('submit', function(e) {
        e.preventDefault();
        saveAsset();
    });
});

function loadStats() {
    $.getJSON('inventory-handler.php?action=get_stats', function(response) {
        if (response.status === 'success') {
            const stats = response.data;
            $('#stats-overview').html(`
                <div class="col-md-2"><div class="stat-card" style="border-color: #007bff;"><div class="stat-icon"><i class="fas fa-boxes"></i></div><div class="stat-number">${stats.total}</div><div class="stat-label">Total</div></div></div>
                <div class="col-md-2"><div class="stat-card" style="border-color: #6f42c1;"><div class="stat-icon"><i class="fas fa-tools"></i></div><div class="stat-number">${stats.tools}</div><div class="stat-label">Outils</div></div></div>
                <div class="col-md-2"><div class="stat-card" style="border-color: #17a2b8;"><div class="stat-icon"><i class="fas fa-car"></i></div><div class="stat-number">${stats.vehicles}</div><div class="stat-label">Véhicules</div></div></div>
                <div class="col-md-2"><div class="stat-card" style="border-color: #28a745;"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-number">${stats.available}</div><div class="stat-label">Disponibles</div></div></div>
                <div class="col-md-2"><div class="stat-card" style="border-color: #ffc107;"><div class="stat-icon"><i class="fas fa-user-cog"></i></div><div class="stat-number">${stats.in_use}</div><div class="stat-label">En Utilisation</div></div></div>
                <div class="col-md-2"><div class="stat-card" style="border-color: #dc3545;"><div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div><div class="stat-number">${stats.maintenance}</div><div class="stat-label">En Maintenance</div></div></div>
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
                let typeIcon = asset.asset_type === 'tool' ? '<i class="fas fa-tool"></i> Outil' : '<i class="fas fa-car"></i> Véhicule';
                let statusBadge = getStatusBadge(asset.status);
                tbody.append(`
                    <tr class="asset-row" style="border-left: 5px solid ${asset.asset_type === 'tool' ? '#6f42c1' : '#17a2b8'};">
                        <td>${typeIcon}</td>
                        <td>${asset.asset_name}</td>
                        <td>${asset.barcode}</td>
                        <td>${asset.brand || ''}</td>
                        <td>${asset.serial_or_plate || ''}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="editAsset(${asset.asset_id})"><i class="fas fa-edit"></i></button>
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
    $('#assetModalLabel').text('Ajouter un Actif');
    toggleAssetFields();

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
                
                toggleAssetFields();

                if(asset.asset_type === 'tool'){
                    $('#serial_or_plate_tool').val(asset.serial_or_plate);
                    $('#position_or_info_tool').val(asset.position_or_info);
                } else {
                    $('#serial_or_plate_vehicle').val(asset.serial_or_plate);
                    $('#fuel_level').val(asset.fuel_level);
                }
            }
        });
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
    const formData = $('#assetForm').serialize();
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

function editAsset(assetId) {
    prepareAssetModal(assetId);
    $('#assetModal').modal('show');
}

function deleteAsset(assetId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet actif ?')) {
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
    if (status === 'available') badgeClass = 'badge-success';
    if (status === 'in-use') badgeClass = 'badge-warning';
    if (status === 'maintenance') badgeClass = 'badge-danger';
    return `<span class="badge ${badgeClass}">${status}</span>`;
}
