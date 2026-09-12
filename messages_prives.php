<?php
require 'config/security.php';
require 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$id_utilisateur = (int) $_SESSION['user_id'];

if (!isset($_GET['user_id']) || !ctype_digit($_GET['user_id'])) {
    header('Location: messages.php');
    exit;
}

$id_interlocuteur = (int) $_GET['user_id'];

if ($id_interlocuteur === $id_utilisateur) {
    header('Location: messages.php');
    exit;
}

// Vérifier que l'interlocuteur existe.
$sql = "SELECT id, pseudo FROM utilisateurs WHERE id = :id LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $id_interlocuteur]);
$interlocuteur = $stmt->fetch();

if (!$interlocuteur) {
    header('Location: messages.php');
    exit;
}

// Envoyer un message privé.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contenu = trim($_POST['contenu'] ?? '');

    if ($contenu !== '') {
        $sql = "INSERT INTO messages_prives
                (expediteur_id, destinataire_id, contenu, date_envoi, lu)
                VALUES (:expediteur_id, :destinataire_id, :contenu, NOW(), 0)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'expediteur_id' => $id_utilisateur,
            'destinataire_id' => $id_interlocuteur,
            'contenu' => $contenu
        ]);

        // Notification uniquement pour le destinataire.
        $sql = "INSERT INTO notifications
                (id_utilisateur, type, contenu, lien, lu, date_creation)
                VALUES (:id_utilisateur, 'message_prive', :contenu, :lien, 0, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_utilisateur' => $id_interlocuteur,
            'contenu' => $_SESSION['pseudo'] . ' t’a envoyé un message privé.',
            'lien' => 'messages_prives.php?user_id=' . $id_utilisateur
        ]);
    }

    header('Location: messages_prives.php?user_id=' . $id_interlocuteur);
    exit;
}

// Dès que la conversation est ouverte, les messages reçus sont considérés comme lus.
$sql = "UPDATE messages_prives
        SET lu = 1
        WHERE expediteur_id = :interlocuteur
          AND destinataire_id = :utilisateur";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    'interlocuteur' => $id_interlocuteur,
    'utilisateur' => $id_utilisateur
]);

// Historique strictement limité aux deux personnes de cette conversation.
$sql = "SELECT mp.id, mp.expediteur_id, mp.destinataire_id, mp.contenu,
               mp.date_envoi, u.pseudo
        FROM messages_prives mp
        JOIN utilisateurs u ON u.id = mp.expediteur_id
        WHERE (mp.expediteur_id = :user_id_1 AND mp.destinataire_id = :other_id_1)
           OR (mp.expediteur_id = :other_id_2 AND mp.destinataire_id = :user_id_2)
        ORDER BY mp.date_envoi ASC, mp.id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    'user_id_1' => $id_utilisateur,
    'other_id_1' => $id_interlocuteur,
    'other_id_2' => $id_interlocuteur,
    'user_id_2' => $id_utilisateur
]);
$messages = $stmt->fetchAll();

// Liste des salons et conversations pour la navigation.
$sql = "SELECT DISTINCT salons.* FROM salons
        LEFT JOIN salon_membres ON salons.id = salon_membres.id_salon
        WHERE salons.visibilite = 'public'
           OR salon_membres.id_utilisateur = :user_id
        ORDER BY salons.nom ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $id_utilisateur]);
$salons = $stmt->fetchAll();

$sql = "SELECT u.id, u.pseudo,
               (SELECT mp.contenu FROM messages_prives mp
                WHERE (mp.expediteur_id = :u1 AND mp.destinataire_id = u.id)
                   OR (mp.expediteur_id = u.id AND mp.destinataire_id = :u2)
                ORDER BY mp.date_envoi DESC, mp.id DESC LIMIT 1) AS dernier_message,
               (SELECT COUNT(*) FROM messages_prives mp
                WHERE mp.expediteur_id = u.id AND mp.destinataire_id = :u3 AND mp.lu = 0) AS non_lus
        FROM utilisateurs u
        WHERE u.id <> :u4
          AND EXISTS (SELECT 1 FROM messages_prives mp
                      WHERE (mp.expediteur_id = :u5 AND mp.destinataire_id = u.id)
                         OR (mp.expediteur_id = u.id AND mp.destinataire_id = :u6))
        ORDER BY u.pseudo ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    'u1' => $id_utilisateur,
    'u2' => $id_utilisateur,
    'u3' => $id_utilisateur,
    'u4' => $id_utilisateur,
    'u5' => $id_utilisateur,
    'u6' => $id_utilisateur
]);
$conversations = $stmt->fetchAll();

$sql = "SELECT COUNT(*) FROM notifications WHERE id_utilisateur = :user_id AND lu = 0";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $id_utilisateur]);
$notifications_non_lues = (int) $stmt->fetchColumn();

$est_admin = ($_SESSION['role'] ?? '') === 'admin';
$initiale = strtoupper(substr($_SESSION['pseudo'], 0, 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message avec <?= htmlspecialchars($interlocuteur['pseudo']) ?> — Lumi</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="chat-page">
<div class="app-layout">
    <aside class="server-sidebar">
        <a href="salons.php" class="lumi-logo" title="Accueil Lumi"><img src="images/logo_lumi.png" alt="Logo Lumi"></a>
        <div class="sidebar-separator"></div>
        <nav class="quick-nav">
            <a href="salons.php" class="quick-nav-item" title="Accueil">⌂</a>
            <a href="messages.php" class="quick-nav-item active" title="Messages">💬</a>
            <a href="amis.php" class="quick-nav-item" title="Amis">👥</a>
        </nav>
        <a href="salons.php#creer-salon" class="quick-nav-item add-room" title="Créer un salon">+</a>
    </aside>

    <aside class="navigation-sidebar">
        <div class="navigation-header"><div><strong>Lumi</strong><span>♡</span></div></div>
        <div class="search-box"><input type="text" placeholder="Rechercher..." aria-label="Rechercher"></div>
        <nav class="main-navigation">
            <a href="salons.php" class="navigation-item"><span>⌂</span> Accueil</a>
            <a href="messages.php" class="navigation-item active"><span>💬</span> Messages</a>
            <a href="amis.php" class="navigation-item"><span>👥</span> Amis</a>
        </nav>

        <div class="rooms-navigation message-navigation-block">
            <div class="rooms-title"><span>TES SALONS</span><a href="salons.php#creer-salon" title="Créer un salon">+</a></div>
            <div class="rooms-list">
                <?php foreach ($salons as $s): ?>
                    <div class="room-navigation-wrapper">
                        <a href="messages.php?id_salon=<?= (int)$s['id'] ?>" class="room-navigation-item">
                            <span class="room-hash">#</span>
                            <span class="room-name"><?= htmlspecialchars($s['nom']) ?></span>
                            <?php if ($s['visibilite'] === 'prive'): ?><span class="room-lock">🔒</span><?php endif; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="private-navigation-title">MESSAGES PRIVÉS</div>
            <div class="private-conversations-list">
                <?php if (empty($conversations)): ?>
                    <p class="no-room">Aucune conversation.</p>
                <?php else: ?>
                    <?php foreach ($conversations as $conversation): ?>
                        <a href="messages_prives.php?user_id=<?= (int)$conversation['id'] ?>" class="private-conversation-item <?= $id_interlocuteur === (int)$conversation['id'] ? 'current-private' : '' ?>">
                            <span class="private-avatar"><?= htmlspecialchars(strtoupper(substr($conversation['pseudo'], 0, 1))) ?></span>
                            <span class="private-conversation-text"><strong><?= htmlspecialchars($conversation['pseudo']) ?></strong><small><?= htmlspecialchars($conversation['dernier_message']) ?></small></span>
                            <?php if ((int)$conversation['non_lus'] > 0): ?><span class="unread-badge"><?= (int)$conversation['non_lus'] ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="user-panel">
            <div class="user-avatar"><?= htmlspecialchars($initiale) ?></div>
            <div class="user-info"><strong><?= htmlspecialchars($_SESSION['pseudo']) ?></strong><span><?= $est_admin ? 'Administrateur' : 'En ligne' ?></span></div>
            <a href="deconnexion.php" class="logout-button" title="Se déconnecter">↪</a>
        </div>
    </aside>

    <main class="main-content chat-main">
        <header class="chat-header private-chat-header">
            <div class="chat-room-title">
                <div class="private-profile-avatar">
                    <?= htmlspecialchars(strtoupper(substr($interlocuteur['pseudo'], 0, 1))) ?>
                    <span class="online-dot"></span>
                </div>
                <div class="private-profile-info">
                    <div class="private-name-line">
                        <h1><?= htmlspecialchars($interlocuteur['pseudo']) ?></h1>
                        <span class="private-badge">PRIVÉ</span>
                    </div>
                    <p><span class="status-dot"></span> Conversation sécurisée entre vous deux</p>
                </div>
            </div>
            <div class="chat-header-actions">
                <a href="notifications.php" class="chat-notification-link" title="Notifications">
                    🔔
                    <?php if ($notifications_non_lues > 0): ?>
                        <span><?= $notifications_non_lues ?></span>
                    <?php endif; ?>
                </a>
                <a href="messages.php" class="private-close-button" title="Retour aux messages">×</a>
            </div>
        </header>

        <section class="chat-messages private-chat-messages" aria-label="Conversation privée">
            <div class="private-chat-intro">
                <div class="private-chat-intro-avatar">
                    <?= htmlspecialchars(strtoupper(substr($interlocuteur['pseudo'], 0, 1))) ?>
                </div>
                <h2><?= htmlspecialchars($interlocuteur['pseudo']) ?></h2>
                <p>Vous êtes maintenant connectés en privé.</p>
                <span>🔒 Seuls vous deux pouvez voir cette conversation</span>
            </div>

            <?php if (empty($messages)): ?>
                <div class="chat-empty private-empty">
                    <div class="chat-empty-icon">✦</div>
                    <h2>Écris ton premier message</h2>
                    <p>Commence la conversation avec <?= htmlspecialchars($interlocuteur['pseudo']) ?>.</p>
                </div>
            <?php else: ?>
                <?php $date_precedente = null; ?>
                <?php foreach ($messages as $msg): ?>
                    <?php
                    $est_moi = (int)$msg['expediteur_id'] === $id_utilisateur;
                    $date_message = date('Y-m-d', strtotime($msg['date_envoi']));
                    ?>

                    <?php if ($date_message !== $date_precedente): ?>
                        <div class="chat-date-separator">
                            <span><?= date('d/m/Y', strtotime($msg['date_envoi'])) ?></span>
                        </div>
                        <?php $date_precedente = $date_message; ?>
                    <?php endif; ?>

                    <article class="private-message <?= $est_moi ? 'private-message-own' : 'private-message-other' ?>">
                        <?php if (!$est_moi): ?>
                            <div class="message-avatar private-message-avatar">
                                <?= htmlspecialchars(strtoupper(substr($msg['pseudo'], 0, 1))) ?>
                            </div>
                        <?php endif; ?>

                        <div class="private-message-content">
                            <div class="private-message-bubble">
                                <p><?= nl2br(htmlspecialchars($msg['contenu'])) ?></p>
                            </div>
                            <time><?= htmlspecialchars(date('H:i', strtotime($msg['date_envoi']))) ?></time>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <form method="POST" class="message-composer private-message-composer">
            <?= csrf_field() ?>
            <button type="button" class="composer-icon" title="Ajouter">＋</button>
            <input type="text" name="contenu" placeholder="Écris à <?= htmlspecialchars($interlocuteur['pseudo']) ?>..." autocomplete="off" maxlength="2000" required>
            <span class="composer-hint">Entrée ↵</span>
            <button type="submit" class="composer-send" title="Envoyer">➤</button>
        </form>
    </main>
</div>
<script>
    const input = document.querySelector('.private-message-composer input');
    if (input) {
        input.focus();
    }
</script>
</body>
</html>
