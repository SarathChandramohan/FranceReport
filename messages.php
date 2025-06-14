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
    <title>Messages - Gestion des Ouvriers</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        :root {
            --primary-color: #007aff;
            --primary-hover: #0056b3;
            --background-light: #f5f5f7;
            --card-bg: #ffffff;
            --text-dark: #1d1d1f;
            --text-light: #555;
            --border-color: #e5e5e5;
        }
        body { background-color: var(--background-light); color: var(--text-dark); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .card { background-color: var(--card-bg); border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 25px; margin-bottom: 25px; border: 1px solid var(--border-color); }
        h2 { margin-bottom: 25px; font-size: 28px; font-weight: 600; }
        .btn-primary { background-color: var(--primary-color); color: white; border: none; }
        .btn-primary:hover { background-color: var(--primary-hover); }
        .tabs-nav { display: flex; border-bottom: 1px solid var(--border-color); margin-bottom: 20px; }
        .tab-button { padding: 12px 24px; background-color: transparent; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-weight: 500; font-size: 14px; color: #6e6e73; transition: all 0.3s ease; }
        .tab-button.active { border-bottom-color: var(--primary-color); color: var(--primary-color); font-weight: 600; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .form-group label { font-weight: 500; font-size: 14px; }
        .form-control, .form-control:focus { background-color: var(--card-bg); border: 1px solid #d2d2d7; border-radius: 8px; }
        .form-control:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(0, 122, 255, 0.2); }
        .table-container { overflow-x: auto; border: 1px solid var(--border-color); border-radius: 8px; margin-top: 15px; }
        table { width: 100%; min-width: 650px; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 14px; }
        th { background-color: #f9f9f9; font-weight: 600; }
        .status-tag { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .status-sent { background-color: #ffcc00; color: #664d00; }
        .status-read { background-color: #34c759; color: white; }
        .status-answered { background-color: #007aff; color: white; }
        .priority-normale { color: #555; }
        .priority-importante { color: #ff9500; font-weight: bold; }
        .priority-urgente { color: #ff3b30; font-weight: bold; }
        .alert { text-align: center; }
        .file-input-wrapper { position: relative; overflow: hidden; display: inline-block; cursor: pointer; }
        .file-input-wrapper input[type="file"] { font-size: 100px; position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer; }
        .file-name { display: inline-block; margin-left: 10px; font-size: 14px; color: #666; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid mt-4">
        <h2>Messages RH/Direction</h2>
        <div id="status-message" style="display: none;"></div>

        <div class="tabs-nav">
            <button class="tab-button active" onclick="openTab('new-message')">Nouveau Message</button>
            <button class="tab-button" onclick="openTab('sent-messages')">Messages Envoyés</button>
            <button class="tab-button" onclick="openTab('received-messages')">Messages Reçus</button>
        </div>

        <div id="new-message" class="tab-content active">
            <div class="card">
                <h3>Envoyer un message</h3>
                <form id="message-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="recipient">Destinataire</label>
                        <select id="recipient" name="recipient_type" class="form-control" required>
                            <option value="">Sélectionner...</option>
                            <option value="rh">Service RH</option>
                            <option value="direction">Direction</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="subject">Sujet</label>
                        <input type="text" id="subject" name="subject" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="priority">Priorité</label>
                        <select id="priority" name="priority" class="form-control">
                            <option value="normale">Normale</option>
                            <option value="importante">Importante</option>
                            <option value="urgente">Urgente</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="content">Message</label>
                        <textarea id="content" name="content" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="attachment">Pièce jointe (optionnel)</label>
                         <div class="file-input-wrapper">
                            <button type="button" class="btn btn-secondary">Choisir un fichier</button>
                            <input type="file" id="attachment" name="attachment">
                        </div>
                        <span id="file-name" class="file-name"></span>
                    </div>
                    <button type="submit" class="btn btn-primary">Envoyer</button>
                </form>
            </div>
        </div>

        <div id="sent-messages" class="tab-content">
            <div class="card">
                <h3>Messages Envoyés</h3>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th><th>Destinataire</th><th>Sujet</th><th>Priorité</th><th>Statut</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="sent-messages-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div id="received-messages" class="tab-content">
             <div class="card">
                <h3>Messages Reçus</h3>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th><th>Expéditeur</th><th>Sujet</th><th>Priorité</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="received-messages-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('footer.php'); ?>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openTab(tabId) {
            $('.tab-content').removeClass('active');
            $('#' + tabId).addClass('active');
            $('.tab-button').removeClass('active');
            $(`button[onclick="openTab('${tabId}')"]`).addClass('active');
            
            if(tabId === 'sent-messages') loadSentMessages();
            if(tabId === 'received-messages') loadReceivedMessages();
        }

        function showStatusMessage(message, type) {
            const statusDiv = $('#status-message');
            statusDiv.text(message).removeClass('alert-success alert-error').addClass(`alert alert-${type}`).show();
            setTimeout(() => statusDiv.fadeOut(), 5000);
        }

        function makeAjaxRequest(action, formData, callback, hasFile = false) {
            const ajaxOptions = {
                url: 'messages-handler.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: response => callback(null, response),
                error: (xhr, status, error) => callback(`Erreur: ${status} - ${error}`)
            };
            if(hasFile) {
                ajaxOptions.processData = false;
                ajaxOptions.contentType = false;
            }
            formData.append('action', action);
            $.ajax(ajaxOptions);
        }

        $('#message-form').on('submit', function(e){
            e.preventDefault();
            const formData = new FormData(this);
            const hasFile = $('#attachment')[0].files.length > 0;
            
            $('button[type="submit"]').prop('disabled', true).text('Envoi...');

            makeAjaxRequest('send_message', formData, (err, res) => {
                 $('button[type="submit"]').prop('disabled', false).text('Envoyer');
                if(err || res.status !== 'success') {
                    showStatusMessage(err || res.message, 'error');
                } else {
                    showStatusMessage(res.message, 'success');
                    $('#message-form')[0].reset();
                    $('#file-name').text('');
                    openTab('sent-messages');
                }
            }, hasFile);
        });

        $('#attachment').on('change', function(){
            const fileName = this.files[0] ? this.files[0].name : '';
            $('#file-name').text(fileName);
        });

        function loadSentMessages() {
            const tbody = $('#sent-messages-body').html('<tr><td colspan="6" class="text-center">Chargement...</td></tr>');
            makeAjaxRequest('get_sent_messages', new FormData(), (err, res) => {
                tbody.empty();
                if(err || res.status !== 'success' || !res.data) {
                    tbody.html(`<tr><td colspan="6" class="text-center text-danger">${err || res.message}</td></tr>`);
                    return;
                }
                if(res.data.length === 0) {
                     tbody.html('<tr><td colspan="6" class="text-center">Aucun message envoyé.</td></tr>');
                     return;
                }
                res.data.forEach(msg => {
                    tbody.append(`
                        <tr>
                            <td>${msg.sent_at}</td>
                            <td>${msg.recipient_display}</td>
                            <td>${msg.subject}</td>
                            <td><span class="priority-${msg.priority}">${msg.priority}</span></td>
                            <td><span class="status-tag status-${msg.status}">${msg.status}</span></td>
                            <td><button class="btn btn-sm btn-info">Voir</button></td>
                        </tr>
                    `);
                });
            });
        }
        
        function loadReceivedMessages() {
            const tbody = $('#received-messages-body').html('<tr><td colspan="5" class="text-center">Chargement...</td></tr>');
            // This part requires backend logic to determine which messages a user can see
            // (e.g., if user role is 'rh' or 'direction')
            // For now, it will show a placeholder.
            tbody.html('<tr><td colspan="5" class="text-center">La réception des messages est en cours de développement.</td></tr>');
        }

        $(document).ready(function() {
            // Initial load
        });
    </script>
</body>
</html>