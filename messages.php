<?php
require_once 'session-management.php';
requireLogin();
$user = getCurrentUser();

require_once 'db-connection.php';
$usersList = [];
try {
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
            --primary-hover: #0056b3;
            --background-color: #f7f8fc;
            --sidebar-bg: #ffffff;
            --pane-border: #e9ecef;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --unread-bg: #e9f5ff;
            --priority-urgente: #dc3545;
            --priority-importante: #ffc107;
            --priority-normale: #6c757d;
            --animation-speed: 0.3s;
            --animation-timing: ease-in-out;
        }

        /* --- Base & Layout --- */
        body {
            background-color: var(--background-color);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .messaging-container {
            display: flex;
            height: calc(100vh - 80px);
            background: var(--sidebar-bg);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.07);
            border: 1px solid var(--pane-border);
        }

        /* --- Custom Scrollbar --- */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #aaa; }

        /* --- Animations --- */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes shimmer {
            0% { background-position: -468px 0; }
            100% { background-position: 468px 0; }
        }
        .fade-in { animation: fadeIn var(--animation-speed) var(--animation-timing) forwards; }

        /* --- Sidebar --- */
        .sidebar {
            width: 250px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--pane-border);
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            transition: width var(--animation-speed) var(--animation-timing);
        }
        .compose-btn {
            margin: 0 20px 20px;
            border-radius: 8px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.2);
            transition: all var(--animation-speed) var(--animation-timing);
        }
        .compose-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0, 123, 255, 0.3); }
        .nav-link {
            color: var(--text-secondary);
            font-weight: 500;
            padding: 14px 25px;
            border-left: 4px solid transparent;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all var(--animation-speed) var(--animation-timing);
        }
        .nav-link:hover { background-color: #f8f9fa; color: var(--text-primary); }
        .nav-link.active {
            background-color: var(--unread-bg);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
            font-weight: 600;
        }
        .nav-link .badge { font-size: 0.75rem; transition: transform var(--animation-speed); }
        .nav-link.active .badge { transform: scale(1.1); }

        /* --- Message List Pane --- */
        .message-list-pane {
            width: 380px;
            border-right: 1px solid var(--pane-border);
            overflow-y: auto;
            background-color: #fcfdff;
        }
        .message-list-header { padding: 25px; border-bottom: 1px solid var(--pane-border); }
        .message-list { list-style: none; padding: 0; margin: 0; }
        .message-item {
            padding: 18px 25px;
            border-bottom: 1px solid var(--pane-border);
            cursor: pointer;
            transition: all var(--animation-speed) var(--animation-timing);
            opacity: 0; /* For staggered animation */
        }
        .message-item:hover { background-color: #f8f9fa; transform: translateX(5px); }
        .message-item.active { background-color: var(--unread-bg); border-right: 4px solid var(--primary-color); }
        .message-item.unread .sender, .message-item.unread .subject { font-weight: 600; color: var(--text-primary); }
        .sender { font-size: 1rem; display: block; margin-bottom: 4px; color: var(--text-primary); }
        .subject { font-size: 0.9rem; margin-bottom: 5px; color: var(--text-secondary); }
        .timestamp { font-size: 0.8rem; color: #999; }
        .priority-dot { height: 9px; width: 9px; border-radius: 50%; display: inline-block; margin-right: 8px; vertical-align: middle; }

        /* --- Skeleton Loader for Message List --- */
        .skeleton-item { padding: 18px 25px; border-bottom: 1px solid var(--pane-border); }
        .skeleton-line { height: 16px; border-radius: 4px; background: #e0e0e0; margin-bottom: 8px; }
        .skeleton-line.short { width: 60%; }
        .shimmer {
            background: linear-gradient(to right, #e0e0e0 8%, #f0f0f0 18%, #e0e0e0 33%);
            background-size: 800px 104px;
            animation: shimmer 1.5s linear infinite;
        }

        /* --- Content Pane --- */
        .content-pane {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        #content-pane-inner { padding: 35px; }
        .content-placeholder { text-align: center; margin: auto; color: var(--text-secondary); }
        .message-header { border-bottom: 1px solid var(--pane-border); padding-bottom: 20px; margin-bottom: 25px; }
        .message-header h3 { margin: 0; font-weight: 600; }
        .message-meta { font-size: 0.9rem; color: var(--text-secondary); margin-top: 10px; }
        .message-body { line-height: 1.7; font-size: 1.05rem; color: #333; }
        .message-actions .btn { border-radius: 8px; font-weight: 500; transition: transform 0.2s, box-shadow 0.2s; }
        .message-actions .btn:hover { transform: translateY(-2px); }

        /* --- Responsive Design --- */
        @media (max-width: 992px) {
            .messaging-container { flex-direction: column; height: auto; }
            .sidebar, .message-list-pane { width: 100%; border-right: 0; }
            .sidebar { flex-direction: row; justify-content: space-around; padding: 0; border-bottom: 1px solid var(--pane-border); }
            .compose-btn { display: none; }
            .message-list-pane { height: 45vh; }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid my-4">
        <div class="messaging-container">
            
            <aside class="sidebar">
                <button class="btn btn-primary compose-btn" onclick="showComposeView()"><i class="fas fa-edit mr-2"></i>Composer</button>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#" onclick="loadMessages('inbox', this)">
                            <i class="fas fa-inbox fa-fw"></i> Boîte de réception
                            <span id="inbox-notification" class="badge badge-primary ml-auto"></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="loadMessages('sent', this)">
                            <i class="fas fa-paper-plane fa-fw"></i> Messages Envoyés
                        </a>
                    </li>
                </ul>
            </aside>
            
            <section class="message-list-pane">
                <div class="message-list-header">
                    <h3 id="pane-title" class="h5 mb-0 font-weight-bold">Boîte de réception</h3>
                </div>
                <div id="skeleton-loader" style="display: none;"></div>
                <ul class="message-list" id="message-list-body"></ul>
            </section>
            
            <main class="content-pane" id="content-pane">
                <div id="content-pane-inner">
                    <div class="content-placeholder">
                        <i class="fas fa-envelope-open-text fa-4x mb-4 text-black-50"></i>
                        <h4>Votre centre de messagerie</h4>
                        <p>Sélectionnez un message pour le lire ou composez un nouveau message.</p>
                    </div>
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
        let currentView = 'inbox';
        const SKELETON_TEMPLATE = `
            <div class="skeleton-item">
                <div class="skeleton-line shimmer" style="width: 50%; height: 20px; margin-bottom: 12px;"></div>
                <div class="skeleton-line shimmer" style="width: 80%;"></div>
                <div class="skeleton-line shimmer short"></div>
            </div>`.repeat(5);

        function showPopupMessage(message, type) {
            $('#statusModalTitle').text(type === 'success' ? 'Succès' : 'Erreur');
            $('#statusModalBody').text(message);
            $('#statusModal').modal('show');
        }

        function setContentPane(htmlContent, placeholderMessage = null) {
            const contentPaneInner = $('#content-pane-inner');
            contentPaneInner.fadeOut(150, function() {
                const newHtml = placeholderMessage ?
                    `<div class="content-placeholder">
                        <i class="fas fa-info-circle fa-3x mb-3"></i><p>${placeholderMessage}</p>
                    </div>` :
                    htmlContent;
                $(this).html(newHtml).fadeIn(150);
            });
        }

        function loadMessages(type, navElement = null) {
            currentView = type;
            if (navElement) {
                $('.nav-link').removeClass('active');
                $(navElement).addClass('active');
            }
            $('#pane-title').text(type === 'inbox' ? 'Boîte de réception' : 'Messages Envoyés');
            
            const listBody = $('#message-list-body');
            listBody.empty();
            $('#skeleton-loader').html(SKELETON_TEMPLATE).show();
            setContentPane(null, "Chargement des messages...");

            const action = type === 'inbox' ? 'get_received_messages' : 'get_sent_messages';
            $.ajax({
                url: 'messages-handler.php', type: 'GET', data: { action: action }, dataType: 'json',
                success: res => {
                    $('#skeleton-loader').hide();
                    if (res.status !== 'success' || !res.data || res.data.length === 0) {
                        const message = res.status !== 'success' ? 'Erreur de chargement.' : `Aucun message ${type === 'inbox' ? 'reçu' : 'envoyé'}.`;
                        listBody.html(`<li class="text-center p-4 text-muted">${message}</li>`);
                        setContentPane(null, `Votre boîte de ${type === 'inbox' ? 'réception' : 'messages envoyés'} est vide.`);
                        return;
                    }
                    
                    let unreadCount = 0;
                    res.data.forEach((msg, index) => {
                        const isUnread = type === 'inbox' && !msg.is_read;
                        if (isUnread) unreadCount++;

                        const item = $(`
                            <li class="message-item ${isUnread ? 'unread' : ''}" onclick="viewMessage(${msg.message_id}, this)">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="sender">${type === 'inbox' ? msg.sender_name : msg.recipient_display}</span>
                                    <span class="timestamp">${msg.sent_at}</span>
                                </div>
                                <div class="subject">
                                    <span class="priority-dot" style="background-color: var(--priority-${msg.priority});" title="Priorité: ${msg.priority}"></span>
                                    ${msg.subject}
                                </div>
                            </li>
                        `).appendTo(listBody);

                        // Staggered animation
                        setTimeout(() => item.css({ 'opacity': '1', 'animation': 'fadeIn 0.5s ease forwards' }), index * 70);
                    });

                    $('#inbox-notification').text(unreadCount > 0 ? unreadCount : '').toggle(unreadCount > 0);
                    if ($('.message-item').length > 0) {
                        $('.message-item:first').click();
                    } else {
                        cancelCompose();
                    }
                },
                error: (xhr, status, error) => {
                    $('#skeleton-loader').hide();
                    showPopupMessage(`Erreur: ${status} - ${error}`, 'danger');
                }
            });
        }

        function viewMessage(messageId, element) {
            $('.message-item').removeClass('active');
            if (element) $(element).addClass('active');

            $.get('messages-handler.php', { action: 'get_message_details', message_id: messageId }, res => {
                if (res.status !== 'success') { showPopupMessage(res.message, 'danger'); return; }
                const d = res.data;
                const attachmentsHtml = d.attachment_path ? `<div class="mt-4"><strong><i class="fas fa-paperclip"></i> Pièce jointe:</strong> <a href="${d.attachment_path}" class="btn btn-sm btn-outline-primary ml-2" target="_blank">Télécharger</a></div>` : '';
                const isSent = currentView === 'sent';

                const contentHtml = `
                    <div class="message-view">
                        <div class="message-header">
                            <h3>${d.subject}</h3>
                            <div class="message-meta">
                                <strong>De:</strong> ${d.sender_name}<br>
                                <strong>Date:</strong> ${d.sent_at}
                            </div>
                        </div>
                        <div class="message-body">${d.content.replace(/\n/g, '<br>')}</div>
                        ${attachmentsHtml}
                        <div class="message-actions mt-4">
                            ${!isSent ? `<button class="btn btn-primary" onclick="showReplyView(${messageId})"><i class="fas fa-reply mr-1"></i> Répondre</button>` : ''}
                            ${isSent ? `<button class="btn btn-info" onclick='showReadReceipts(${JSON.stringify(d.receipts)})'><i class="fas fa-check-double mr-1"></i> Voir les confirmations</button>` : ''}
                        </div>
                    </div>`;
                setContentPane(contentHtml);
                if (!isSent && $(element).hasClass('unread')) setTimeout(() => loadMessages('inbox'), 500);
            }, 'json');
        }
        
        function showReadReceipts(receipts) {
            let bodyHtml = receipts && receipts.length > 0 ?
                '<ul class="list-group">' + receipts.map(r => `<li class="list-group-item d-flex justify-content-between align-items-center">${r.recipient_name} <span class="badge badge-${r.is_read ? 'success' : 'secondary'}">${r.is_read ? 'Lu le ' + r.read_at : 'Non lu'}</span></li>`).join('') + '</ul>' :
                '<p>Aucune information de lecture disponible.</p>';
            $('#receiptsModalBody').html(bodyHtml);
            $('#receiptsModal').modal('show');
        }

        function showComposeView(isReply = false, originalMessage = null) {
            const formHtml = `
                <div class="compose-view">
                    <h3 id="compose-title" class="mb-4">${isReply ? 'Répondre au message' : 'Nouveau Message'}</h3>
                    <form id="message-form" enctype="multipart/form-data">
                        <input type="hidden" name="parent_message_id" id="parent_message_id">
                        ${isReply ? `<div class="alert alert-info">Réponse à <strong>${originalMessage.sender_name}</strong></div>` : ''}
                        
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
                                    <?php foreach ($usersList as $u): ?><option value="<?= $u['user_id']; ?>"><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']); ?></option><?php endforeach; ?>
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
            setContentPane(formHtml);
            
            $('#recipient_type').change(function() { $('#individual-recipient-group').toggle($(this).val() === 'individual'); });
            $('#message-form').on('submit', handleFormSubmit);

            if (isReply && originalMessage) {
                $('#parent_message_id').val(originalMessage.message_id);
                $('#subject').val(`Re: ${originalMessage.subject}`);
                const replyContent = `\n\n\n----- Message original le ${originalMessage.sent_at} -----\n> ${originalMessage.content.replace(/\n/g, '\n> ')}`;
                $('#content').val(replyContent).focus();
                $('#recipient_type').val('individual').prop('disabled', true).trigger('change');
                $('#individual_recipients').val(originalMessage.sender_user_id).prop('disabled', true);
            }
        }

        function handleFormSubmit(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'send_message');
            if ($('#parent_message_id').val()) {
                formData.set('recipient_type', 'individual');
                formData.set('individual_recipients[]', $('#individual_recipients').val());
            }

            $.ajax({
                url: 'messages-handler.php', type: 'POST', data: formData, dataType: 'json',
                contentType: false, processData: false,
                success: res => {
                    if (res.status !== 'success') { showPopupMessage(res.message, 'danger'); } 
                    else {
                        showPopupMessage(res.message, 'success');
                        loadMessages('sent', $('a[onclick*="\'sent\'"]'));
                    }
                },
                error: (xhr, status, error) => showPopupMessage(`Erreur: ${status} - ${error}`, 'danger')
            });
        }
        
        function showReplyView(messageId) {
            $.get('messages-handler.php', { action: 'get_message_details', message_id: messageId }, res => {
                 if(res.status !== 'success') { showPopupMessage(res.message, 'danger'); return; }
                 res.data.message_id = messageId;
                 showComposeView(true, res.data);
            }, 'json');
        }

        function cancelCompose() {
            setContentPane(null, "Sélectionnez un message pour le lire ou rédigez un nouveau message.");
        }

        $(document).ready(() => { loadMessages('inbox', $('a[onclick*="\'inbox\'"]')); });
    </script>
</body>
</html>
