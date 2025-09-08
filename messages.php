<?php
require_once 'session-management.php';
requireLogin();
$user = getCurrentUser();

// Fetch users for the recipient list
require_once 'db-connection.php';
$usersList = [];
try {
    // Exclude the current user from the list to prevent them from sending a message to themselves
    $stmt = $conn->prepare("SELECT user_id, nom, prenom FROM Users WHERE status = 'Active' AND user_id != ? ORDER BY nom, prenom");
    $stmt->execute([$user['user_id']]);
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
            --priority-urgente-bg: #ff3b30; --priority-importante-bg: #ff9500; --priority-normale-bg: #8e8e93;
        }
        body { background-color: var(--background-light); color: var(--text-dark); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .card { background-color: var(--card-bg); border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 25px; margin-bottom: 25px; border: 1px solid var(--border-color); }
        h2, h3 { color: var(--text-dark); font-weight: 600; }
        .tabs-nav { position: relative; display: flex; border-bottom: 1px solid var(--border-color); margin-bottom: 20px; }
        .tab-button { position: relative; padding: 12px 24px; background: transparent; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-weight: 500; font-size: 14px; color: #6e6e73; transition: all 0.3s ease; }
        .tab-button.active { border-bottom-color: var(--primary-color); color: var(--primary-color); font-weight: 600; }
        .tab-content { display: none; } .tab-content.active { display: block; }
        .notification-dot { position: absolute; top: 8px; right: 8px; width: 8px; height: 8px; background-color: var(--primary-color); border-radius: 50%; display: none; }
        tr.unread { font-weight: bold; background-color: #f0f8ff; }
        .priority-dot { height: 10px; width: 10px; border-radius: 50%; display: inline-block; }
        .priority-urgente { background-color: var(--priority-urgente-bg); }
        .priority-importante { background-color: var(--priority-importante-bg); }
        .priority-normale { background-color: var(--priority-normale-bg); }
        .status-tag { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; color: white; }
        .status-read-by-all { background-color: #34c759; }
        .status-partially-read { background-color: #ff9500; }
        .status-unread { background-color: #8e8e93; }
        .reply-info { background-color: #e9ecef; border-left: 4px solid #ced4da; padding: 10px; margin-bottom: 15px; font-size: 0.9em; border-radius: 4px; display: none; }
        .reply-info button { float: right; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid mt-4">
        <h2>Messages</h2>
        
        <div class="tabs-nav">
            <button class="tab-button active" onclick="openTab('new-message')">Nouveau Message</button>
            <button class="tab-button" onclick="openTab('received-messages')">Boîte de Réception<span id="inbox-notification" class="notification-dot"></span></button>
            <button class="tab-button" onclick="openTab('sent-messages')">Messages Envoyés</button>
        </div>

        <div id="new-message" class="tab-content active">
            <div class="card">
                <h3>Envoyer un message</h3>
                <div id="reply-info-box" class="reply-info">
                    <span id="reply-text"></span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="cancelReply()">Annuler</button>
                </div>
                <form id="message-form" enctype="multipart/form-data">
                    <input type="hidden" name="parent_message_id" id="parent_message_id">
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
                    <div class="form-group" id="individual-recipient-group" style="display:none;">
                        <label for="individual_recipients">Choisir le(s) destinataire(s)</label>
                        <select id="individual_recipients" name="individual_recipients[]" class="form-control" multiple>
                             <?php foreach ($usersList as $u): ?>
                                <option value="<?= $u['user_id']; ?>"><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label for="subject">Sujet</label><input type="text" id="subject" name="subject" class="form-control" required></div>
                    <div class="form-group"><label for="priority">Priorité</label>
                        <select id="priority" name="priority" class="form-control"><option value="normale">Normale</option><option value="importante">Importante</option><option value="urgente">Urgente</option></select>
                    </div>
                    <div class="form-group"><label for="content">Message</label><textarea id="content" name="content" class="form-control" rows="5" required></textarea></div>
                    <button type="submit" class="btn btn-primary">Envoyer</button>
                </form>
            </div>
        </div>

        <div id="received-messages" class="tab-content">
            <div class="card"><h3>Boîte de Réception</h3><div class="table-container"><table class="table table-hover"><thead><tr><th></th><th>De</th><th>Sujet</th><th>Date</th><th>Actions</th></tr></thead><tbody id="received-messages-body"></tbody></table></div></div>
        </div>

        <div id="sent-messages" class="tab-content">
            <div class="card"><h3>Messages Envoyés</h3><div class="table-container"><table class="table"><thead><tr><th>Date</th><th>Destinataire</th><th>Sujet</th><th>Statut de Lecture</th><th>Actions</th></tr></thead><tbody id="sent-messages-body"></tbody></table></div></div>
        </div>
    </div>
    
    <div class="modal fade" id="messageDetailModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Détails du Message</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button></div></div></div></div>
    
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalTitle"></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" id="statusModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <?php include('footer.php'); ?>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB

        function showPopupMessage(message, type) {
            const modal = $('#statusModal');
            const title = $('#statusModalTitle');
            const body = $('#statusModalBody');
            
            title.text(type === 'success' ? 'Succès' : 'Erreur');
            title.removeClass('text-success text-danger').addClass(type === 'success' ? 'text-success' : 'text-danger');
            body.text(message);
            
            modal.modal('show');
        }

        function openTab(tabId) {
            $('.tab-content, .tab-button').removeClass('active');
            $('#' + tabId).addClass('active');
            $(`button[onclick="openTab('${tabId}')"]`).addClass('active');
            if (tabId === 'sent-messages') loadSentMessages();
            if (tabId === 'received-messages') loadReceivedMessages();
        }

        function makeAjaxRequest(formData, callback) {
            const ajaxOptions = {
                url: 'messages-handler.php', type: 'POST', data: formData, dataType: 'json',
                success: response => callback(null, response),
                error: (xhr, status, error) => callback(`Erreur: ${status} - ${error}`)
            };
            if(formData instanceof FormData) {
                ajaxOptions.processData = false;
                ajaxOptions.contentType = false;
            } else {
                // If not FormData, it's a query string, so use GET
                ajaxOptions.type = 'GET';
                ajaxOptions.url += '?' + formData;
                ajaxOptions.data = null;
            }
            $.ajax(ajaxOptions);
        }
        
        $('#recipient_type').change(function(){
            $('#individual-recipient-group').toggle($(this).val() === 'individual');
        });

        $('#message-form').on('submit', function(e){
            e.preventDefault();
            const fileInput = $('#attachment')[0];
            if(fileInput.files.length > 0 && fileInput.files[0].size > MAX_FILE_SIZE) {
                showPopupMessage('Le fichier est trop volumineux. La taille maximale est de 2 Mo.', 'danger');
                return;
            }
            const formData = new FormData(this);
            // Re-enable disabled fields for submission if it's a reply
            if ($('#parent_message_id').val()) {
                $('#recipient_type').prop('disabled', false);
                formData.set('recipient_type', 'individual');
                 // You may need to manually add the recipient if it's disabled
                formData.append('individual_recipients[]', $('#individual_recipients').val());
            }
            
            formData.append('action', 'send_message');
            makeAjaxRequest(formData, (err, res) => {
                 // Re-disable the field after submission
                if ($('#parent_message_id').val()) {
                    $('#recipient_type').prop('disabled', true);
                }
                if(err || res.status !== 'success') {
                    showPopupMessage(err || res.message, 'danger');
                } else {
                    showPopupMessage(res.message, 'success');
                    cancelReply();
                    openTab('sent-messages');
                }
            });
        });

        function loadReceivedMessages() {
            makeAjaxRequest("action=get_received_messages", (err, res) => {
                const tbody = $('#received-messages-body');
                tbody.empty();
                if(err || res.status !== 'success') { showPopupMessage(err || (res ? res.message : 'Erreur de chargement.'), 'danger'); return; }
                if(!res.data || res.data.length === 0) { tbody.html('<tr><td colspan="5" class="text-center">Aucun message reçu.</td></tr>'); return; }
                let unreadCount = 0;
                res.data.forEach(msg => {
                    if(!msg.is_read) unreadCount++;
                    tbody.append(`<tr class="${!msg.is_read ? 'unread' : ''}">
                        <td><span class="priority-dot priority-${msg.priority}" title="Priorité: ${msg.priority}"></span></td>
                        <td>${msg.sender_name}</td><td>${msg.subject}</td><td>${msg.sent_at}</td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="viewMessage(${msg.message_id})">Voir</button>
                            <button class="btn btn-sm btn-primary" onclick="replyToMessage(${msg.message_id})">Répondre</button>
                        </td></tr>`);
                });
                $('#inbox-notification').toggle(unreadCount > 0);
            });
        }
        
        function loadSentMessages() {
            makeAjaxRequest("action=get_sent_messages", (err, res) => {
                const tbody = $('#sent-messages-body');
                tbody.empty();
                 if(err || res.status !== 'success') { showPopupMessage(err || (res ? res.message : 'Erreur de chargement.'), 'danger'); return; }
                if(!res.data || res.data.length === 0) { tbody.html('<tr><td colspan="5" class="text-center">Aucun message envoyé.</td></tr>'); return; }
                res.data.forEach(msg => {
                    let statusClass = 'status-unread';
                    let statusText = `Lu par ${msg.read_recipients} / ${msg.total_recipients}`;
                    if(msg.read_percentage == 100) statusClass = 'status-read-by-all';
                    else if (msg.read_percentage > 0) statusClass = 'status-partially-read';
                    
                    tbody.append(`<tr><td>${msg.sent_at}</td><td>${msg.recipient_display}</td><td>${msg.subject}</td><td><span class="status-tag ${statusClass}">${statusText}</span></td><td><button class="btn btn-sm btn-info" onclick="viewMessage(${msg.message_id}, true)">Détails</button></td></tr>`);
                });
            });
        }

        function viewMessage(messageId, isSentMessage = false) {
            makeAjaxRequest(`action=get_message_details&message_id=${messageId}`, (err, res) => {
                if(err || res.status !== 'success') { showPopupMessage(err || res.message, 'danger'); return; }
                const details = res.data;
                let modalBodyHtml = `<p><strong>De:</strong> ${details.sender_name}</p><p><strong>Sujet:</strong> ${details.subject}</p><hr><div>${details.content.replace(/\n/g, '<br>')}</div>`;
                if(details.attachment_path) modalBodyHtml += `<hr><p><strong>Pièce jointe:</strong> <a href="${details.attachment_path}" target="_blank">Télécharger</a></p>`;

                if(isSentMessage && details.receipts) {
                    modalBodyHtml += '<hr><h5>Confirmations de lecture</h5><ul class="list-group">';
                    if (details.receipts.length > 0) {
                        details.receipts.forEach(r => {
                            modalBodyHtml += `<li class="list-group-item">${r.recipient_name} <span class="badge badge-${r.is_read ? 'success' : 'secondary'} float-right">${r.is_read ? 'Lu le ' + r.read_at : 'Non lu'}</span></li>`;
                        });
                    } else {
                        modalBodyHtml += '<li class="list-group-item">Aucun destinataire pour ce message.</li>';
                    }
                    modalBodyHtml += '</ul>';
                }
                
                $('#messageDetailModal .modal-body').html(modalBodyHtml);
                $('#messageDetailModal').modal('show');
                if(!isSentMessage) loadReceivedMessages();
            });
        }

        function replyToMessage(messageId) {
            makeAjaxRequest(`action=get_message_details&message_id=${messageId}`, (err, res) => {
                if(err || res.status !== 'success') { showPopupMessage(err || res.message, 'danger'); return; }
                const details = res.data;
                
                $('#parent_message_id').val(messageId);
                $('#subject').val(`Re: ${details.subject}`);
                const replyContent = `\n\n----- Message original -----\nDe: ${details.sender_name}\nSujet: ${details.subject}\n\n${details.content}`;
                $('#content').val(replyContent).focus();
                
                $('#recipient_type').val('individual').prop('disabled', true);
                $('#individual-recipient-group').show();
                $('#individual_recipients').val(details.sender_user_id).prop('disabled', true);

                $('#reply-text').text(`Réponse à: ${details.sender_name}`);
                $('#reply-info-box').show();

                openTab('new-message');
                window.scrollTo(0, 0);
            });
        }
        
        function cancelReply() {
            $('#message-form')[0].reset();
            $('#parent_message_id').val('');
            $('#recipient_type').prop('disabled', false);
            $('#individual_recipients').prop('disabled', false);
            $('#individual-recipient-group').hide();
            $('#reply-info-box').hide();
        }

        $(document).ready(() => {
            loadReceivedMessages();
        });
    </script>
</body>
</html>
