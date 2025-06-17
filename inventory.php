<?php
require_once 'session-management.php';
requireLogin();
$user = getCurrentUser();

// This page is for administrators only. Redirect if the user is not an admin.
if ($user['role'] !== 'admin') {
    header('Location: dashboard.php'); // Or any other appropriate page
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
        /* UI/Theme inspired by the provided inventory.html and inventory2.html examples */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: #333;
        }
        .main-header {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .main-header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }
        .main-header p { color: #666; font-size: 1.1em; }
        .card-custom {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
        }
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            border-left: 5px solid;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .stat-card.total { border-color: #6c757d; }
        .stat-card.tools { border-color: #28a745; }
        .stat-card.vehicles { border-color: #007bff; }
        .stat-card.available { border-color: #17a2b8; }
        .stat-card.in-use { border-color: #ffc107; }
        .stat-card.maintenance { border-color: #dc3545; }
        .stat-icon { font-size: 2em; margin-bottom: 10px; opacity: 0.8; }
        .stat-number { font-size: 2em; font-weight: bold; }
        .stat-label { color: #6c757d; font-weight: 500; font-size: 0.9em; }
        .asset-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            border-left: 5px solid;
        }
        .asset-card.tool { border-left-color: #28a745; }
        .asset-card.vehicle { border-left-color: #007bff; }
        .asset-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.12); }
        .asset-title { font-size: 1.2em; font-weight: bold; color: #2c3e50; }
        .asset-status { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-available { background: #d4edda; color: #155724; }
        .status-in-use { background: #fff3cd; color: #856404; }
        .status-maintenance { background: #f8d7da; color: #721c24; }
        .btn-gradient {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
        }
        .btn-gradient:hover {
            color: white;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="main-header">
            <h1>📊 Tableau de Bord de l'Inventaire</h1>
            <p>Supervisez et gérez vos outils et véhicules en temps réel</p>
        </div>

        <!-- Statistics Overview -->
        <div class="card-custom">
            <div class="row text-center" id="stats-overview">
                <!-- Stats loaded by JS -->
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                <h3 class="mb-2 mb-md-0">Liste des Actifs</h3>
                <div>
                    <button class="btn btn-info" data-toggle="modal" data-target="#categoriesModal"><i class="fas fa-sitemap"></i> Gérer les Catégories</button>
                    <button class="btn btn-gradient" onclick="prepareAssetModal()"><i class="fas fa-plus"></i> Ajouter un Actif</button>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-8">
                    <input type="text" id="searchInput" class="form-control" placeholder="Rechercher par nom, code-barres, marque, série/plaque...">
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
                <table class="table table-hover align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>Type</th>
                            <th>Nom & Code-barres</th>
                            <th>Catégorie</th>
                            <th>Statut</th>
                            <th>Assigné à</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="assets-table-body">
                        <!-- Asset rows are dynamically loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Asset Modal (Add/Edit) -->
    <div class="modal fade" id="assetModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="assetForm" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="assetModalLabel">Ajouter un Actif</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="asset_id" name="asset_id">
                        <div id="modal-alert" class="alert alert-danger" style="display:none;"></div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="asset_type">Type d'Actif *</label>
                                <select id="asset_type" name="asset_type" class="form-control" required></select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="category_id">Catégorie</label>
                                <select id="category_id" name="category_id" class="form-control"></select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="asset_name">Nom de l'Actif *</label>
                            <input type="text" id="asset_name" name="asset_name" class="form-control" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="barcode">Code-barres *</label>
                                <input type="text" id="barcode" name="barcode" class="form-control" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="brand">Marque</label>
                                <input type="text" id="brand" name="brand" class="form-control">
                            </div>
                        </div>
                        
                        <!-- Tool-specific fields -->
                        <div id="tool-fields-modal" style="display:none;">
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
                        
                        <!-- Vehicle-specific fields -->
                        <div id="vehicle-fields-modal" style="display:none;">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="serial_or_plate_vehicle">Plaque d'immatriculation</label>
                                    <input type="text" id="serial_or_plate_vehicle" name="serial_or_plate_vehicle" class="form-control">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="fuel_level">Niveau de Carburant</label>
                                    <select id="fuel_level" name="fuel_level" class="form-control"></select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                        <button type="submit" class="btn btn-gradient">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Categories Modal -->
    <div class="modal fade" id="categoriesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gérer les Catégories</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="categoryForm" class="form-inline mb-3">
                        <input type="text" id="category_name" class="form-control mr-2 flex-grow-1" placeholder="Nom de la nouvelle catégorie" required>
                        <select id="category_type" class="form-control mr-2"></select>
                        <button type="submit" class="btn btn-success">Ajouter</button>
                    </form>
                    <div id="categories-list" class="list-group">
                        <!-- Category list is loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('footer.php'); ?>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="inventory.js"></script>
</body>
</html>
