<?php
require_once 'session-management.php';
requireLogin();
$user = getCurrentUser();

// Fetch users for the recipient list
require_once 'db-connection.php';
$usersList = [];
try {
    // Exclude the current user from the list
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
    <title>Messagerie - Gestion des Ouvriers</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color: #007bff; --primary-hover: #0056b3; --background-light: #f8f9fa;
            --card-bg: #ffffff; --text-dark: #212529; --text-light: #6c757d; --border-color: #dee2e6;
            --priority-urgente-bg: #dc3545; --priority-importante-bg: #ffc107; --priority-normale-bg: #6c757d;
            --unread-bg: #eaf4ff;
        }
        body { background-color: var(--background-light); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .messaging-container { display: flex; height: calc(100vh - 80px); background: var(--card-bg); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        
        /* Sidebar Navigation */
        .sidebar { width: 240px; background: #f5f5f7; border-right: 1px solid var(--border-color); padding: 20px 0; display: flex; flex-direction: column; }
        .compose-btn { margin: 0 20px 20px; }
        .nav-link { color: var(--text-dark); font-weight: 500; padding: 12px 20px; border-left: 3px solid transparent; display: flex; align-items: center; gap: 15px; }
        .nav-link:hover { background-color: #e9e9eb; }
        .nav-link.active { background-color: var(--unread-bg); color: var(--primary-color); border-left-color: var(--primary-color); font-weight: 600; }
        .nav-link .badge { font-size: 0.75rem; }
        
        /* Message List Pane */
        .message-list-pane { width: 350px; border-right: 1px solid var(--border-color); overflow-y: auto; }
        .message-list-header { padding: 20px; border-bottom: 1px solid var(--border-color); }
        .message-list { list-style: none; padding: 0; margin: 0; }
        .message-item { padding: 15px 20px; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: background-color 0.2s; }
        .message-item:hover { background-color: #f8f9fa; }
        .message-item.active { background-color: var(--unread-bg); }
        .message-item.unread .sender, .message-item.unread .subject { font-weight: bold; color: var(--text-dark); }
        .sender { font-size: 0.95rem; display: block; margin-bottom: 2px; }
        .subject { font-size: 0.85rem; color: var(--text-dark); margin-bottom: 4px; }
        .timestamp { font-size: 0.75rem; color: var(--text-light); }
        .priority-dot { height: 8px; width: 8px; border-radius: 50%; display: inline-block; margin-right: 8px; }
        
        /* Content Pane */
        .content-pane { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .content-placeholder, .message-view, .compose-view { padding: 30px; }
        .content-placeholder { text-align: center; margin: auto; color: var(--text-light); }
        .message-header { border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 20px; }
        .message-header h4 { margin: 0; }
        .message-meta { font-size: 0.9rem; color: var(--text-light); }
        .message-body { line-height: 1.6; }
        .message-actions { margin-top: 25px; }
        
        /* Responsive */
        @media (max-width: 992px) {
            .messaging-container { flex-direction: column; height: auto; }
            .sidebar { width: 100%; flex-direction: row; justify-content: space-around; padding: 0; border-right: 0; border-bottom: 1px solid var(--border-color); }
            .compose-btn { display: none; }
            .message-list-pane { width: 100%; border-right: 0; height: 40vh; }
            .content-pane { flex-basis: auto; height: 60vh; }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid my-4">
        <div class="messaging-container">
            <aside class="sidebar">
                <button class="btn btn-primary compose-btn" onclick="showComposeView()"><i class="fas fa-plus mr-2"></i>Nouveau message</button>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#" onclick="loadMessages('inbox', this)">
                            <i class="fas fa-inbox"></i> Boîte de réception
                            <span id="inbox-notification" class="badge badge-primary ml-auto"></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="loadMessages('sent', this)">
                            <i class="fas fa-paper-plane"></i> Messages Envoyés
                        </a>
                    </li>
                </ul>
            </aside>
            
            <section class="message-list-pane">
                <div class="message-list-header">
                    <h3 id="pane-title" class="h5">Boîte de réception</h3>
                </div>
                <ul class="message-list" id="message-list-body">
                    </ul>
            </section>
            
            <main class="content-pane" id="content-pane">
                <div class="content-placeholder" id="content-placeholder">
                    <i class="fas fa-envelope-open-text fa-3x mb-3"></i>
                    <p>Sélectionnez un message pour le lire ou rédigez un nouveau message.</p>
                </div>
                </main>
        </div>
    </div>
    
    <div class="modal fade" id="statusModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="statusModalTitle"></h5><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body" id="statusModalBody"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button></div></div></div></div>
    <div class="modal fade" id="receiptsModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirmations de lecture</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body" id="receiptsModalBody"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button></div></div></div></div>

    <?php include('footer.php'); ?>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentView = 'inbox'; // 'inbox' or 'sent'

        function showPopupMessage(message, type) {
            $('#statusModalTitle').text(type === 'success' ? 'Succès' : 'Erreur');
            $('#statusModalBody').text(message);
            $('#statusModal').modal('show');
        }

        function makeAjaxRequest(params, callback) {
            $.ajax({
                url: 'messages-handler.php',
                type: params instanceof FormData ? 'POST' : 'GET',
                data: params,
                dataType: 'json',
                processData: params instanceof FormData ? false : undefined,
                contentType: params instanceof FormData ? false : undefined,
                success: response => callback(null, response),
                error: (xhr, status, error) => callback(`Erreur: ${status} - ${error}`)
            });
        }
        
        function showPlaceholder(message) {
            $('#content-pane').html(`<div class="content-placeholder" id="content-placeholder">
                <i class="fas fa-info-circle fa-3x mb-3"></i><p>${message}</p></div>`);
        }

        function loadMessages(type, navElement = null) {
            currentView = type;
            const action = type === 'inbox' ? 'get_received_messages' : 'get_sent_messages';
            
            if (navElement) {
                $('.nav-link').removeClass('active');
                $(navElement).addClass('active');
            }
            $('#pane-title').text(type === 'inbox' ? 'Boîte de réception' : 'Messages Envoyés');
            showPlaceholder("Chargement des messages...");

            makeAjaxRequest(`action=${action}`, (err, res) => {
                const listBody = $('#message-list-body');
                listBody.empty();
                if (err || res.status !== 'success') {
                    showPopupMessage(err || (res ? res.message : 'Erreur de chargement.'), 'danger');
                    listBody.html('<li class="text-center p-3">Erreur de chargement.</li>');
                    return;
                }
                if (!res.data || res.data.length === 0) {
                    listBody.html(`<li class="text-center p-3 text-muted">Aucun message ${type === 'inbox' ? 'reçu' : 'envoyé'}.</li>`);
                    showPlaceholder(`Votre boîte de ${type === 'inbox' ? 'réception' : 'messages envoyés'} est vide.`);
                    return;
                }
                
                let unreadCount = 0;
                res.data.forEach(msg => {
                    const isUnread = type === 'inbox' && !msg.is_read;
                    if(isUnread) unreadCount++;

                    const itemHtml = `
                        <li class="message-item ${isUnread ? 'unread' : ''}" onclick="viewMessage(${msg.message_id}, this)">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="sender">${type === 'inbox' ? msg.sender_name : msg.recipient_display}</span>
                                <span class="timestamp">${msg.sent_at}</span>
                            </div>
                            <div class="subject">
                                <span class="priority-dot" style="background-color: var(--priority-${msg.priority}-bg);" title="Priorité: ${msg.priority}"></span>
                                ${msg.subject}
                            </div>
                        </li>
                    `;
                    listBody.append(itemHtml);
                });

                const notificationBadge = $('#inbox-notification');
                if(type === 'inbox') {
                    notificationBadge.text(unreadCount > 0 ? unreadCount : '').toggle(unreadCount > 0);
                } else {
                    notificationBadge.hide();
                }
            });
        }

        function viewMessage(messageId, element) {
            $('.message-item').removeClass('active');
            if (element) $(element).addClass('active');

            makeAjaxRequest(`action=get_message_details&message_id=${messageId}`, (err, res) => {
                if(err || res.status !== 'success') {
                    showPopupMessage(err || res.message, 'danger');
                    return;
                }
                const details = res.data;
                const isSentMessage = currentView === 'sent';
                let attachmentsHtml = '';
                if(details.attachment_path) {
                    attachmentsHtml = `<div class="mt-4">
                        <strong><i class="fas fa-paperclip"></i> Pièce jointe:</strong>
                        <a href="${details.attachment_path}" class="btn btn-sm btn-outline-primary ml-2" target="_blank">Télécharger</a>
                    </div>`;
                }

                const contentHtml = `
                    <div class="message-view">
                        <div class="message-header">
                            <h4>${details.subject}</h4>
                            <div class="message-meta">
                                <strong>De:</strong> ${details.sender_name}<br>
                                <strong>Date:</strong> ${details.sent_at}
                            </div>
                        </div>
                        <div class="message-body">
                            ${details.content.replace(/\n/g, '<br>')}
                        </div>
                        ${attachmentsHtml}
                        <div class="message-actions">
                            ${!isSentMessage ? `<button class="btn btn-primary" onclick="showReplyView(${messageId})"><i class="fas fa-reply mr-1"></i> Répondre</button>` : ''}
                            ${isSentMessage ? `<button class="btn btn-info" onclick="showReadReceipts('${btoa(JSON.stringify(details.receipts))}')"><i class="fas fa-check-double mr-1"></i> Voir les confirmations</button>` : ''}
                        </div>
                    </div>`;
                $('#content-pane').html(contentHtml);
                
                // If it was an unread message from the inbox, refresh the list to update its state
                if(!isSentMessage && $(element).hasClass('unread')) {
                   setTimeout(() => loadMessages('inbox'), 500);
                }
            });
        }
        
        function showReadReceipts(receiptsData) {
            const receipts = JSON.parse(atob(receiptsData));
            let modalBodyHtml = '<ul class="list-group">';
            if (receipts && receipts.length > 0) {
                receipts.forEach(r => {
                    modalBodyHtml += `<li class="list-group-item d-flex justify-content-between align-items-center">
                        ${r.recipient_name} 
                        <span class="badge badge-${r.is_read ? 'success' : 'secondary'}">${r.is_read ? 'Lu le ' + r.read_at : 'Non lu'}</span>
                    </li>`;
                });
            } else {
                modalBodyHtml += '<li class="list-group-item">Aucune information de lecture disponible.</li>';
            }
            modalBodyHtml += '</ul>';
            $('#receiptsModalBody').html(modalBodyHtml);
            $('#receiptsModal').modal('show');
        }

        function showComposeView(isReply = false, originalMessage = null) {
            const formHtml = `
                <div class="compose-view">
                    <h3 id="compose-title">Nouveau Message</h3>
                    <form id="message-form" enctype="multipart/form-data">
                        <input type="hidden" name="parent_message_id" id="parent_message_id">
                        <div id="reply-info" class="alert alert-info" style="display:none;"></div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="recipient_type">Destinataire</label>
                                <select id="recipient_type" name="recipient_type" class="form-control" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="all_users">Tous les utilisateurs</option>
                                    <option value="rh">Service RH</option>
                                    <option value="direction">Direction</option>
                                    <option value="individual">Individuel(s)</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6" id="individual-recipient-group" style="display:none;">
                                <label for="individual_recipients">Choisir le(s) destinataire(s)</label>
                                <select id="individual_recipients" name="individual_recipients[]" class="form-control" multiple>
                                    <?php foreach ($usersList as $u): ?>
                                        <option value="<?= $u['user_id']; ?>"><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                             <div class="form-group col-md-6"><label for="subject">Sujet</label><input type="text" id="subject" name="subject" class="form-control" required></div>
                             <div class="form-group col-md-6"><label for="priority">Priorité</label>
                                <select id="priority" name="priority" class="form-control"><option value="normale">Normale</option><option value="importante">Importante</option><option value="urgente">Urgente</option></select>
                             </div>
                        </div>
                        <div class="form-group"><label for="content">Message</label><textarea id="content" name="content" class="form-control" rows="8" required></textarea></div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane mr-2"></i>Envoyer</button>
                        <button type="button" class="btn btn-secondary" onclick="cancelCompose()">Annuler</button>
                    </form>
                </div>
            `;
            $('#content-pane').html(formHtml);
            
            // Event Listeners for the new form
            $('#recipient_type').change(function() {
                $('#individual-recipient-group').toggle($(this).val() === 'individual');
            });

            $('#message-form').on('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'send_message');
                
                // If it's a reply, ensure disabled fields are added to FormData
                if ($('#parent_message_id').val()) {
                    formData.set('recipient_type', 'individual');
                    formData.set('individual_recipients[]', $('#individual_recipients').val());
                }

                makeAjaxRequest(formData, (err, res) => {
                    if (err || res.status !== 'success') {
                        showPopupMessage(err || res.message, 'danger');
                    } else {
                        showPopupMessage(res.message, 'success');
                        cancelCompose();
                        // Switch to sent messages view to see the new message
                        loadMessages('sent', $('a[onclick*="\'sent\'"]'));
                    }
                });
            });

            if (isReply && originalMessage) {
                $('#compose-title').text("Répondre au message");
                $('#reply-info').html(`Réponse à <strong>${originalMessage.sender_name}</strong>`).show();
                
                $('#parent_message_id').val(originalMessage.message_id);
                $('#subject').val(`Re: ${originalMessage.subject}`);
                const replyContent = `\n\n\n----- Message original le ${originalMessage.sent_at} -----\n> ${originalMessage.content.replace(/\n/g, '\n> ')}`;
                $('#content').val(replyContent).focus();
                
                $('#recipient_type').val('individual').prop('disabled', true).trigger('change');
                $('#individual_recipients').val(originalMessage.sender_user_id).prop('disabled', true);
            }
        }
        
        function showReplyView(messageId) {
            makeAjaxRequest(`action=get_message_details&message_id=${messageId}`, (err, res) => {
                 if(err || res.status !== 'success') { showPopupMessage(err || res.message, 'danger'); return; }
                 res.data.message_id = messageId; // Make sure ID is in the object
                 showComposeView(true, res.data);
            });
        }

        function cancelCompose() {
            showPlaceholder("Sélectionnez un message pour le lire ou rédigez un nouveau message.");
        }

        // Initial Load
        $(document).ready(() => {
            loadMessages('inbox', $('a[onclick*="\'inbox\'"]'));
        });
    </script>
</body>
</html>
