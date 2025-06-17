<?php
require_once 'session-management.php';
requireLogin();
$user = getCurrentUser();

// We will assume only admins can manage inventory for now
if ($user['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de l'Inventaire</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body { background-color: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .card { border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); border: 1px solid #e5e5e5; }
        .stat-card { text-align: center; padding: 15px; border-left: 5px solid; cursor: pointer; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .stat-icon { font-size: 2em; }
        .stat-card .stat-number { font-size: 1.8em; font-weight: bold; }
        .stat-card .stat-label { font-size: 0.9em; color: #6c757d; }
        .fuel-indicator-small { display: inline-block; width: 20px; height: 20px; border-radius: 50%; border: 1px solid #333; }
        .fuel-full { background-color: #28a745; }
        .fuel-three-quarter { background-color: #ffc107; }
        .fuel-half { background-color: #fd7e14; }
        .fuel-quarter { background-color: #dc3545; }
        .fuel-empty { background-color: #343a40; }
        .table-hover tbody tr:hover { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2><i class="fas fa-boxes"></i> Gestion de l'Inventaire</h2>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row text-center" id="stats-overview">
                    </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 id="asset-list-title">Tous les Actifs</h4>
                    <div>
                        <button class="btn btn-info" data-toggle="modal" data-target="#categoriesModal"><i class="fas fa-sitemap"></i> Gérer les Catégories</button>
                        <button class="btn btn-primary" data-toggle="modal" data-target="#assetModal" onclick="prepareAssetModal()"><i class="fas fa-plus"></i> Ajouter un Actif</button>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-8">
                        <input type="text" id="searchInput" class="form-control" placeholder="Rechercher par nom, code-barres, marque, numéro de série/plaque...">
                    </div>
                    <div class="col-md-4">
                        <select id="typeFilter" class="form-control">
                            <option value="all">Tous les types</option>
                            <option value="tool">Outils</option>
                            <option value="vehicle">Véhicules</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Nom</th>
                                <th>Catégorie</th>
                                <th>Statut</th>
                                <th>Assigné à</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="assets-table-body">
                            </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="assetModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="assetForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="assetModalLabel">Ajouter un Actif</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="asset_id" name="asset_id">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="asset_type">Type d'Actif</label>
                                <select id="asset_type" name="asset_type" class="form-control" required>
                                    <option value="tool">Outil</option>
                                    <option value="vehicle">Véhicule</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="category_id">Catégorie</label>
                                <select id="category_id" name="category_id" class="form-control"></select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="asset_name">Nom de l'Actif</label>
                            <input type="text" id="asset_name" name="asset_name" class="form-control" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="barcode">Code-barres</label>
                                <input type="text" id="barcode" name="barcode" class="form-control" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="brand">Marque</label>
                                <input type="text" id="brand" name="brand" class="form-control">
                            </div>
                        </div>
                        <div id="tool-fields">
                             <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="serial_or_plate_tool">Numéro de Série</label>
                                    <input type="text" id="serial_or_plate_tool" name="serial_or_plate_tool" class="form-control">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="position_or_info_tool">Emplacement</label>
                                    <input type="text" id="position_or_info_tool" name="position_or_info_tool" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div id="vehicle-fields">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="serial_or_plate_vehicle">Plaque d'immatriculation</label>
                                    <input type="text" id="serial_or_plate_vehicle" name="serial_or_plate_vehicle" class="form-control">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="fuel_level">Niveau de Carburant</label>
                                    <select id="fuel_level" name="fuel_level" class="form-control">
                                        <option value="full">Plein</option>
                                        <option value="three-quarter">3/4</option>
                                        <option value="half">Moitié</option>
                                        <option value="quarter">1/4</option>
                                        <option value="empty">Vide</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="categoriesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gérer les Catégories</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="categoryForm" class="form-inline mb-3">
                        <input type="text" id="category_name" class="form-control mr-2 flex-grow-1" placeholder="Nom de la nouvelle catégorie" required>
                        <select id="category_type" class="form-control mr-2">
                            <option value="tool">Outil</option>
                            <option value="vehicle">Véhicule</option>
                        </select>
                        <button type="submit" class="btn btn-success">Ajouter</button>
                    </form>
                    <div id="categories-list" class="list-group">
                        </div>
                </div>
            </div>
        </div>
    </div>


    <?php include('footer.php'); ?>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="inventory-handler.js"></script>
</body>
</html>
