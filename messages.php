<?php
require_once 'session-management.php';
requireLogin();
$user = getCurrentUser();

// Fetch users for the recipient list
require_once 'db-connection.php';
$usersList = [];
try {
    $stmt = $conn->query("SELECT user_id, nom, prenom FROM Users WHERE status = 'Active' ORDER BY nom, prenom");
    $usersList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching users for messages page: " . $e->getMessage());
}
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
            --primary-color: #007aff; --primary-hover: #0056b3; --background-light: #f5f5f7;
            --card-bg: #ffffff; --text-dark: #1d1d1f; --text-light: #555; --border-color: #e5e5e5;
        }
        body { background-color: var(--background-light); color: var(--text-dark); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .card { background-color: var(--card-bg); border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 25px; margin-bottom: 25px; border: 1px solid var(--border-color); }
        h2, h3 { color: var(--text-dark); font-weight: 600; }
        h2 { margin-bottom: 25px; font-size: 28px; }
        h3 { margin-bottom: 20px; font-size: 22px; }
        .tabs-nav { display: flex; border-bottom: 1px solid var(--border-color); margin-bottom: 20px; }
        .tab-button { padding: 12px 24px; background: transparent; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-weight: 500; font-size: 14px; color: #6e6e73; transition: all 0.3s ease; }
        .tab-button.active { border-bottom-color: var(--primary-color); color: var(--primary-color); font-weight: 600; }
        .tab-content { display: none; } .tab-content.active { display: block; }
        .form-group label { font-weight: 500; }
        .table-container { overflow-x: auto; }
        tr.unread { font-weight: bold; background-color: #f0f8ff; }
        .modal-body p { margin-bottom: 0.5rem; } .modal-body strong { color: #333; }
        #individual-recipient-group, .file-input-wrapper { display: none; }
        .file-input-wrapper { margin-top: 10px; }
        .status-tag { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; color: white; }
        .status-read-by-all { background-color: #34c759; }
        .status-partially-read { background-color: #ff9500; }
        .status-unread { background-color: #8e8e93; }

    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid mt-4">
        <h2>Messages</h2>
        <div id="status-message" class="alert" style="display: none;"></div>

        <div class="tabs-nav">
            <button class="tab-button active" onclick="openTab('new-message')">Nouveau Message</button>
            <button class="tab-button" onclick="openTab('received-messages')">Boîte de Réception</button>
            <button class="tab-button" onclick="openTab('sent-messages')">Messages Envoyés</button>
        </div>

        <div id="new-message" class="tab-content active">
            <div class="card">
                <h3>Envoyer un message</h3>
                <form id="message-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="recipient_type">Destinataire</label>
                        <select id="recipient_type" name="recipient_type" class="form-control" required>
                            <option value="">Sélectionner...</option>
                            <option value="all_users">Tous les utilisateurs</option>
                            <option value="rh">Service RH</option>
                            <option value="direction">Direction</option>
                            <option value="individual">Individuel(s)</option>
                        </select>
                    </div>
                    <div class="form-group" id="individual-recipient-group">
                        <label for="individual_recipients">Choisir le(s) destinataire(s)</label>
                        <select id="individual_recipients" name="individual_recipients[]" class="form-control" multiple>
                             <?php foreach ($usersList as $u): ?>
                                <option value="<?= $u['user_id']; ?>"><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label for="subject">Sujet</label><input type="text" id="subject" name="subject" class="form-control" required></div>
                    <div class="form-group"><label for="content">Message</label><textarea id="content" name="content" class="form-control" rows="5" required></textarea></div>
                    <div class="form-group">
                        <label for="attachment">Pièce jointe (Max 2MB)</label><br>
                        <input type="file" id="attachment" name="attachment" class="form-control-file">
                    </div>
                    <button type="submit" class="btn btn-primary">Envoyer</button>
                </form>
            </div>
        </div>

        <div id="received-messages" class="tab-content">
            <div class="card"><h3>Boîte de Réception</h3><div class="table-container"><table class="table table-hover"><thead><tr><th>De</th><th>Sujet</th><th>Date</th><th>Actions</th></tr></thead><tbody id="received-messages-body"></tbody></table></div></div>
        </div>

        <div id="sent-messages" class="tab-content">
            <div class="card"><h3>Messages Envoyés</h3><div class="table-container"><table class="table"><thead><tr><th>Date</th><th>Destinataire</th><th>Sujet</th><th>Statut de Lecture</th><th>Actions</th></tr></thead><tbody id="sent-messages-body"></tbody></table></div></div>
        </div>
    </div>
    
    <div class="modal fade" id="messageDetailModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Détails du Message</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button></div></div></div></div>
    <div class="modal fade" id="readReceiptModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirmation de Lecture</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button></div></div></div></div>

    <?php include('footer.php'); ?>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB

        function openTab(tabId) {
            $('.tab-content, .tab-button').removeClass('active');
            $('#' + tabId).addClass('active');
            $(`button[onclick="openTab('${tabId}')"]`).addClass('active');
            if(tabId === 'sent-messages') loadSentMessages();
            if(tabId === 'received-messages') loadReceivedMessages();
        }

        function showStatusMessage(message, type) {
            const statusDiv = $('#status-message');
            statusDiv.text(message).removeClass('alert-success alert-danger').addClass(`alert alert-${type}`).show();
            setTimeout(() => statusDiv.fadeOut(), 5000);
        }

        function makeAjaxRequest(formData, callback) {
            $.ajax({
                url: 'messages-handler.php', type: 'POST', data: formData, dataType: 'json',
                processData: false, contentType: false,
                success: response => callback(null, response),
                error: (xhr, status, error) => callback(`Erreur: ${status} - ${error}`)
            });
        }
        
        $('#recipient_type').change(function(){
            $('#individual-recipient-group').toggle($(this).val() === 'individual');
        });

        $('#message-form').on('submit', function(e){
            e.preventDefault();

            const fileInput = $('#attachment')[0];
            if(fileInput.files.length > 0 && fileInput.files[0].size > MAX_FILE_SIZE) {
                showStatusMessage('Le fichier est trop volumineux. La taille maximale est de 2 Mo.', 'error');
                return;
            }

            const formData = new FormData(this);
            formData.append('action', 'send_message');
            $('button[type="submit"]').prop('disabled', true).text('Envoi...');

            makeAjaxRequest(formData, (err, res) => {
                $('button[type="submit"]').prop('disabled', false).text('Envoyer');
                if(err || res.status !== 'success') showStatusMessage(err || res.message, 'error');
                else {
                    showStatusMessage(res.message, 'success');
                    $('#message-form')[0].reset();
                    $('#individual-recipient-group').hide();
                    openTab('sent-messages');
                }
            });
        });

        function loadSentMessages() {
            const tbody = $('#sent-messages-body').html('<tr><td colspan="5" class="text-center">Chargement...</td></tr>');
            const formData = new FormData();
            formData.append('action', 'get_sent_messages');
            makeAjaxRequest(formData, (err, res) => {
                tbody.empty();
                if(err || res.status !== 'success' || !res.data) {
                    tbody.html(`<tr><td colspan="5" class="text-center text-danger">${err || (res ? res.message : 'Erreur')}</td></tr>`); return;
                }
                if(res.data.length === 0) { tbody.html('<tr><td colspan="5" class="text-center">Aucun message envoyé.</td></tr>'); return; }
                res.data.forEach(msg => {
                    let statusClass = 'status-unread';
                    if(msg.read_percentage == 100) statusClass = 'status-read-by-all';
                    else if (msg.read_percentage > 0) statusClass = 'status-partially-read';
                    
                    tbody.append(`<tr><td>${msg.sent_at}</td><td>${msg.recipient_display}</td><td>${msg.subject}</td><td><span class="status-tag ${statusClass}">${msg.read_status}</span></td><td><button class="btn btn-sm btn-info" onclick="viewReadReceipts(${msg.message_id})">Reçus</button></td></tr>`);
                });
            });
        }
        
        function loadReceivedMessages() {
            const tbody = $('#received-messages-body').html('<tr><td colspan="4" class="text-center">Chargement...</td></tr>');
            const formData = new FormData();
            formData.append('action', 'get_received_messages');
             makeAjaxRequest(formData, (err, res) => {
                tbody.empty();
                if(err || res.status !== 'success' || !res.data) {
                    tbody.html(`<tr><td colspan="4" class="text-center text-danger">${err || (res ? res.message : 'Erreur')}</td></tr>`); return;
                }
                if(res.data.length === 0) { tbody.html('<tr><td colspan="4" class="text-center">Aucun message reçu.</td></tr>'); return; }
                res.data.forEach(msg => {
                    tbody.append(`<tr class="${!msg.is_read ? 'unread' : ''}"><td>${msg.sender_name}</td><td>${msg.subject}</td><td>${msg.sent_at}</td><td><button class="btn btn-sm btn-info" onclick="viewMessage(${msg.message_id})">Voir</button></td></tr>`);
                });
            });
        }
        
        function viewMessage(messageId) {
            const formData = new FormData();
            formData.append('action', 'get_message_details');
            formData.append('message_id', messageId);
            makeAjaxRequest(formData, (err, res) => {
                if(err || res.status !== 'success') { showStatusMessage(err || res.message, 'error'); return; }
                const details = res.data;
                $('#messageDetailModal .modal-body').html(`
                    <p><strong>De:</strong> ${details.sender_name}</p>
                    <p><strong>Sujet:</strong> ${details.subject}</p><hr>
                    <div>${details.content.replace(/\n/g, '<br>')}</div>
                    ${details.attachment_path ? `<hr><p><strong>Pièce jointe:</strong> <a href="${details.attachment_path}" target="_blank">Télécharger</a></p>` : ''}
                `);
                $('#messageDetailModal').modal('show');
                loadReceivedMessages(); // Refresh list to mark as read
            });
        }

        function viewReadReceipts(messageId) {
             const formData = new FormData();
            formData.append('action', 'get_message_details'); // This now also returns receipts
            formData.append('message_id', messageId);
            makeAjaxRequest(formData, (err, res) => {
                if(err || res.status !== 'success' || !res.data) { showStatusMessage(err || res.message, 'error'); return; }
                const details = res.data;
                let receiptHtml = '<ul class="list-group">';
                if(details.receipts && details.receipts.length > 0) {
                     details.receipts.forEach(r => {
                        receiptHtml += `<li class="list-group-item d-flex justify-content-between align-items-center">
                            ${r.recipient_name} 
                            <span class="badge badge-${r.is_read ? 'success' : 'secondary'}">${r.is_read ? 'Lu le ' + r.read_at : 'Non lu'}</span>
                        </li>`;
                    });
                } else {
                    receiptHtml += '<li class="list-group-item">Aucun destinataire trouvé pour ce message.</li>';
                }
                receiptHtml += '</ul>';
                $('#readReceiptModal .modal-body').html(receiptHtml);
                $('#readReceiptModal').modal('show');
            });
        }

        $(document).ready(() => openTab('new-message'));
    </script>
</body>
</html>
