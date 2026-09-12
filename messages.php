<?php
require 'config/security.php';
require 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$id_utilisateur = (int) $_SESSION['user_id'];
$id_salon = null;
$salon = null;
$messages = [];
$erreur = '';
$est_admin = ($_SESSION['role'] ?? '') === 'admin';

// Si un salon est demandé, vérifier qu'il existe et que l'utilisateur a le droit d'y accéder.
if (isset($_GET['id_salon']) && ctype_digit($_GET['id_salon'])) {
    $id_salon = (int) $_GET['id_salon'];

    $sql = "SELECT id, nom, visibilite FROM salons WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id_salon]);
    $salon = $stmt->fetch();

    if (!$salon) {
        header('Location: messages.php');
        exit;
    }

    // Les salons privés sont strictement réservés à leurs membres.
    if ($salon['visibilite'] === 'prive') {
        $sql = "SELECT id FROM salon_membres
                WHERE id_salon = :id_salon AND id_utilisateur = :id_utilisateur
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
                'id_salon' => $id_salon,
                'id_utilisateur' => $id_utilisateur
        ]);

        if (!$stmt->fetch()) {
            header('Location: messages.php?acces_refuse=1');
            exit;
        }
    }

    // Un administrateur peut supprimer un message depuis n'importe quel salon auquel il a accès.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_message'])) {
        if (!$est_admin) {
            http_response_code(403);
            exit('Accès refusé.');
        }

        $id_message = isset($_POST['id_message']) && ctype_digit($_POST['id_message'])
                ? (int) $_POST['id_message']
                : 0;

        if ($id_message > 0) {
            $sql = "DELETE FROM messages WHERE id = :id_message AND id_salon = :id_salon";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                    'id_message' => $id_message,
                    'id_salon' => $id_salon
            ]);
        }

        header('Location: messages.php?id_salon=' . $id_salon);
        exit;
    }

    // Envoyer un message dans le salon.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $contenu = trim($_POST['contenu'] ?? '');

        if ($contenu !== '') {
            $sql = "INSERT INTO messages
                    (id_utilisateur, id_salon, contenu, date_envoi)
                    VALUES (:id_utilisateur, :id_salon, :contenu, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                    'id_utilisateur' => $id_utilisateur,
                    'id_salon' => $id_salon,
                    'contenu' => $contenu
            ]);

            // Pour un salon privé, prévenir les autres membres.
            if ($salon['visibilite'] === 'prive') {
                $sql = "SELECT id_utilisateur FROM salon_membres
                        WHERE id_salon = :id_salon
                        AND id_utilisateur <> :id_utilisateur";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                        'id_salon' => $id_salon,
                        'id_utilisateur' => $id_utilisateur
                ]);
                $membres = $stmt->fetchAll();

                $pseudo = $_SESSION['pseudo'];
                $contenu_notification = $pseudo . ' a envoyé un message dans #' . $salon['nom'];

                $sql = "INSERT INTO notifications
                        (id_utilisateur, type, contenu, lien, lu, date_creation)
                        VALUES (:id_utilisateur, 'salon', :contenu, :lien, 0, NOW())";
                $stmtNotification = $pdo->prepare($sql);

                foreach ($membres as $membre) {
                    $stmtNotification->execute([
                            'id_utilisateur' => $membre['id_utilisateur'],
                            'contenu' => $contenu_notification,
                            'lien' => 'messages.php?id_salon=' . $id_salon
                    ]);
                }
            }
        }

        header('Location: messages.php?id_salon=' . $id_salon);
        exit;
    }

    // Historique conservé en base.
    $sql = "SELECT messages.id, messages.id_utilisateur, messages.contenu,
                   messages.date_envoi, utilisateurs.pseudo
            FROM messages
            JOIN utilisateurs ON messages.id_utilisateur = utilisateurs.id
            WHERE messages.id_salon = :id_salon
            ORDER BY messages.date_envoi ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_salon' => $id_salon]);
    $messages = $stmt->fetchAll();
}

// Salons accessibles par l'utilisateur connecté.
$sql = "SELECT DISTINCT salons.* FROM salons
        LEFT JOIN salon_membres ON salons.id = salon_membres.id_salon
        WHERE salons.visibilite = 'public'
           OR salon_membres.id_utilisateur = :user_id
        ORDER BY salons.nom ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $id_utilisateur]);
$salons = $stmt->fetchAll();

// Conversations privées existantes : dernière activité par interlocuteur.
$sql = "SELECT u.id, u.pseudo,
               (SELECT mp.contenu
                FROM messages_prives mp
                WHERE (mp.expediteur_id = :user_id_1 AND mp.destinataire_id = u.id)
                   OR (mp.expediteur_id = u.id AND mp.destinataire_id = :user_id_2)
                ORDER BY mp.date_envoi DESC, mp.id DESC
                LIMIT 1) AS dernier_message,
               (SELECT mp.date_envoi
                FROM messages_prives mp
                WHERE (mp.expediteur_id = :user_id_3 AND mp.destinataire_id = u.id)
                   OR (mp.expediteur_id = u.id AND mp.destinataire_id = :user_id_4)
                ORDER BY mp.date_envoi DESC, mp.id DESC
                LIMIT 1) AS derniere_date,
               (SELECT COUNT(*)
                FROM messages_prives mp
                WHERE mp.expediteur_id = u.id
                  AND mp.destinataire_id = :user_id_5
                  AND mp.lu = 0) AS non_lus
        FROM utilisateurs u
        WHERE u.id <> :user_id_6
          AND EXISTS (
              SELECT 1 FROM messages_prives mp
              WHERE (mp.expediteur_id = :user_id_7 AND mp.destinataire_id = u.id)
                 OR (mp.expediteur_id = u.id AND mp.destinataire_id = :user_id_8)
          )
        ORDER BY derniere_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([
        'user_id_1' => $id_utilisateur,
        'user_id_2' => $id_utilisateur,
        'user_id_3' => $id_utilisateur,
        'user_id_4' => $id_utilisateur,
        'user_id_5' => $id_utilisateur,
        'user_id_6' => $id_utilisateur,
        'user_id_7' => $id_utilisateur,
        'user_id_8' => $id_utilisateur
]);
$conversations = $stmt->fetchAll();

// Utilisateurs disponibles pour démarrer une nouvelle conversation privée.
$sql = "SELECT id, pseudo FROM utilisateurs WHERE id <> :user_id ORDER BY pseudo ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $id_utilisateur]);
$utilisateurs = $stmt->fetchAll();

// Notifications non lues.
$sql = "SELECT COUNT(*) FROM notifications WHERE id_utilisateur = :user_id AND lu = 0";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $id_utilisateur]);
$notifications_non_lues = (int) $stmt->fetchColumn();

$initiale = strtoupper(substr($_SESSION['pseudo'], 0, 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $salon ? '#' . htmlspecialchars($salon['nom']) : 'Messages' ?> — Lumi</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="chat-page">
<div class="app-layout">
    <aside class="server-sidebar">
        <a href="salons.php" class="lumi-logo" title="Accueil Lumi">
            <img src="images/logo_lumi.png" alt="Logo Lumi">
        </a>
        <div class="sidebar-separator"></div>
        <nav class="quick-nav">
            <a href="salons.php" class="quick-nav-item" title="Accueil">⌂</a>
            <a href="messages.php" class="quick-nav-item active" title="Messages">💬</a>
            <a href="amis.php" class="quick-nav-item" title="Amis">👥</a>
        </nav>
        <a href="salons.php#creer-salon" class="quick-nav-item add-room" title="Créer un salon">+</a>
    </aside>

    <aside class="navigation-sidebar">
        <div class="navigation-header">
            <div>
                <strong>Lumi</strong>
                <span>♡</span>
            </div>
        </div>

        <div class="search-box">
            <input type="text" placeholder="Rechercher..." aria-label="Rechercher">
        </div>

        <nav class="main-navigation">
            <a href="salons.php" class="navigation-item">
                <span>⌂</span> Accueil
            </a>
            <a href="messages.php" class="navigation-item active">
                <span>💬</span> Messages
            </a>
            <a href="amis.php" class="navigation-item">
                <span>👥</span> Amis
            </a>
        </nav>

        <div class="rooms-navigation message-navigation-block">
            <div class="rooms-title">
                <span>TES SALONS</span>
                <a href="salons.php#creer-salon" title="Créer un salon">+</a>
            </div>
            <div class="rooms-list">
                <?php if (empty($salons)): ?>
                    <p class="no-room">Aucun salon pour le moment.</p>
                <?php else: ?>
                    <?php foreach ($salons as $s): ?>
                        <div class="room-navigation-wrapper <?= $id_salon === (int)$s['id'] ? 'current-room' : '' ?>">
                            <a href="messages.php?id_salon=<?= (int)$s['id'] ?>" class="room-navigation-item">
                                <span class="room-hash">#</span>
                                <span class="room-name"><?= htmlspecialchars($s['nom']) ?></span>
                                <?php if ($s['visibilite'] === 'prive'): ?>
                                    <span class="room-lock">🔒</span>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="private-navigation-title">MESSAGES PRIVÉS</div>
            <div class="private-conversations-list">
                <?php if (empty($conversations)): ?>
                    <p class="no-room">Aucune conversation.</p>
                <?php else: ?>
                    <?php foreach ($conversations as $conversation): ?>
                        <a href="messages_prives.php?user_id=<?= (int)$conversation['id'] ?>" class="private-conversation-item">
                            <span class="private-avatar"><?= htmlspecialchars(strtoupper(substr($conversation['pseudo'], 0, 1))) ?></span>
                            <span class="private-conversation-text">
                                <strong><?= htmlspecialchars($conversation['pseudo']) ?></strong>
                                <small><?= htmlspecialchars($conversation['dernier_message']) ?></small>
                            </span>
                            <?php if ((int)$conversation['non_lus'] > 0): ?>
                                <span class="unread-badge"><?= (int)$conversation['non_lus'] ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="user-panel">
            <div class="user-avatar"><?= htmlspecialchars($initiale) ?></div>
            <div class="user-info">
                <strong><?= htmlspecialchars($_SESSION['pseudo']) ?></strong>
                <span><?= $est_admin ? 'Administrateur' : 'En ligne' ?></span>
            </div>
            <a href="deconnexion.php" class="logout-button" title="Se déconnecter">↪</a>
        </div>
    </aside>

    <main class="main-content chat-main">
        <?php if ($salon): ?>
            <header class="chat-header">
                <div class="chat-room-title">
                    <span class="chat-hash">#</span>
                    <div>
                        <h1><?= htmlspecialchars($salon['nom']) ?></h1>
                        <p><?= $salon['visibilite'] === 'prive' ? 'Salon privé · membres autorisés uniquement' : 'Salon public' ?></p>
                    </div>
                </div>
                <div class="chat-header-actions">
                    <a href="notifications.php" class="chat-notification-link" title="Notifications">
                        🔔
                        <?php if ($notifications_non_lues > 0): ?><span><?= $notifications_non_lues ?></span><?php endif; ?>
                    </a>
                    <a href="messages.php" class="chat-back">Messages</a>
                </div>
            </header>

            <section class="chat-messages" aria-label="Messages">
                <?php if (empty($messages)): ?>
                    <div class="chat-empty">
                        <div class="chat-empty-icon">✦</div>
                        <h2>Bienvenue dans #<?= htmlspecialchars($salon['nom']) ?></h2>
                        <p>C'est le début de cette conversation. Envoie le premier message.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <?php $est_moi = (int)$msg['id_utilisateur'] === $id_utilisateur; ?>
                        <article class="message <?= $est_moi ? 'message-own' : '' ?>">
                            <div class="message-avatar">
                                <?= htmlspecialchars(strtoupper(substr($msg['pseudo'], 0, 1))) ?>
                            </div>
                            <div class="message-body">
                                <div class="message-meta">
                                    <strong><?= htmlspecialchars($msg['pseudo']) ?></strong>
                                    <?php if ($est_moi): ?><span class="message-you">toi</span><?php endif; ?>
                                    <time><?= htmlspecialchars(date('d/m/Y à H:i', strtotime($msg['date_envoi']))) ?></time>
                                </div>
                                <p><?= nl2br(htmlspecialchars($msg['contenu'])) ?></p>
                            </div>
                            <?php if ($est_admin): ?>
                                <form method="POST" class="admin-message-delete" onsubmit="return confirm('Supprimer ce message ?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id_message" value="<?= (int)$msg['id'] ?>">
                                    <button type="submit" name="supprimer_message" title="Supprimer ce message" aria-label="Supprimer ce message">×</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <form method="POST" class="message-composer">
                <?= csrf_field() ?>
                <input type="text" name="contenu" placeholder="Écrire un message dans #<?= htmlspecialchars($salon['nom']) ?>..." autocomplete="off" maxlength="2000" required>
                <button type="submit" title="Envoyer">→</button>
            </form>
        <?php else: ?>
            <header class="chat-header">
                <div class="chat-room-title">
                    <span class="chat-hash">💬</span>
                    <div>
                        <h1>Messages</h1>
                        <p>Tes salons et tes conversations privées au même endroit.</p>
                    </div>
                </div>
                <a href="notifications.php" class="chat-notification-link" title="Notifications">
                    🔔
                    <?php if ($notifications_non_lues > 0): ?><span><?= $notifications_non_lues ?></span><?php endif; ?>
                </a>
            </header>

            <section class="messages-home">
                <?php if (isset($_GET['acces_refuse'])): ?>
                    <div class="access-denied">🔒 Ce salon est privé. Tu n'es pas autorisé à consulter ses messages.</div>
                <?php endif; ?>

                <div class="messages-home-header">
                    <div>
                        <span class="section-label">LUMI</span>
                        <h2>Choisis une conversation</h2>
                    </div>
                    <span class="message-count-pill"><?= count($salons) ?> salon<?= count($salons) > 1 ? 's' : '' ?></span>
                </div>

                <div class="message-columns">
                    <section class="message-list-card">
                        <div class="message-list-card-header">
                            <div>
                                <span class="section-label">COMMUNAUTÉ</span>
                                <h3>Salons</h3>
                            </div>
                            <a href="salons.php#creer-salon" class="small-action">+</a>
                        </div>
                        <div class="message-list">
                            <?php foreach ($salons as $s): ?>
                                <a href="messages.php?id_salon=<?= (int)$s['id'] ?>" class="message-list-item">
                                    <span class="message-list-icon">#</span>
                                    <span>
                                        <strong><?= htmlspecialchars($s['nom']) ?></strong>
                                        <small><?= $s['visibilite'] === 'prive' ? 'Salon privé' : 'Salon public' ?></small>
                                    </span>
                                    <?php if ($s['visibilite'] === 'prive'): ?><span>🔒</span><?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="message-list-card">
                        <div class="message-list-card-header">
                            <div>
                                <span class="section-label">PRIVÉ</span>
                                <h3>Messages privés</h3>
                            </div>
                        </div>
                        <div class="message-list">
                            <?php if (empty($conversations)): ?>
                                <div class="message-list-empty">
                                    <span>♡</span>
                                    <p>Aucune conversation privée pour le moment.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($conversations as $conversation): ?>
                                    <a href="messages_prives.php?user_id=<?= (int)$conversation['id'] ?>" class="message-list-item">
                                        <span class="message-list-icon private-list-avatar"><?= htmlspecialchars(strtoupper(substr($conversation['pseudo'], 0, 1))) ?></span>
                                        <span class="message-list-item-content">
                                            <strong><?= htmlspecialchars($conversation['pseudo']) ?></strong>
                                            <small><?= htmlspecialchars($conversation['dernier_message']) ?></small>
                                        </span>
                                        <?php if ((int)$conversation['non_lus'] > 0): ?><span class="unread-badge"><?= (int)$conversation['non_lus'] ?></span><?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <section class="new-private-card">
                    <div>
                        <span class="section-label">NOUVELLE CONVERSATION</span>
                        <h3>Envoyer un message privé</h3>
                        <p>Choisis un utilisateur pour démarrer une conversation personnelle.</p>
                    </div>
                    <form method="GET" action="messages_prives.php" class="new-private-form">
                        <select name="user_id" required>
                            <option value="">Choisir un utilisateur...</option>
                            <?php foreach ($utilisateurs as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['pseudo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit">Ouvrir</button>
                    </form>
                </section>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
