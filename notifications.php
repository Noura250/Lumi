<?php
require 'config/security.php';
require 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$id_utilisateur = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tout_lire'])) {
    $sql = "UPDATE notifications SET lu = 1 WHERE id_utilisateur = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $id_utilisateur]);
    header('Location: notifications.php');
    exit;
}

if (isset($_GET['lire']) && ctype_digit($_GET['lire'])) {
    $sql = "UPDATE notifications SET lu = 1
            WHERE id = :id AND id_utilisateur = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
            'id' => (int) $_GET['lire'],
            'user_id' => $id_utilisateur
    ]);

    $lien = 'notifications.php';
    $sql = "SELECT lien FROM notifications WHERE id = :id AND id_utilisateur = :user_id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => (int) $_GET['lire'], 'user_id' => $id_utilisateur]);
    $notification = $stmt->fetch();
    if ($notification && !empty($notification['lien'])) {
        $lien = $notification['lien'];
    }
    header('Location: ' . $lien);
    exit;
}

$sql = "SELECT id, type, contenu, lien, lu, date_creation
        FROM notifications
        WHERE id_utilisateur = :user_id
        ORDER BY date_creation DESC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $id_utilisateur]);
$notifications = $stmt->fetchAll();

$non_lues = 0;
foreach ($notifications as $notification) {
    if ((int)$notification['lu'] === 0) {
        $non_lues++;
    }
}

$est_admin = ($_SESSION['role'] ?? '') === 'admin';
$initiale = strtoupper(substr($_SESSION['pseudo'], 0, 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — Lumi</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="notifications-page">
<div class="app-layout">
    <aside class="server-sidebar">
        <a href="salons.php" class="lumi-logo" title="Accueil Lumi"><img src="images/logo_lumi.png" alt="Logo Lumi"></a>
        <div class="sidebar-separator"></div>
        <nav class="quick-nav">
            <a href="salons.php" class="quick-nav-item" title="Accueil">⌂</a>
            <a href="messages.php" class="quick-nav-item" title="Messages">💬</a>
            <a href="amis.php" class="quick-nav-item" title="Amis">👥</a>
        </nav>
        <a href="salons.php#creer-salon" class="quick-nav-item add-room" title="Créer un salon">+</a>
    </aside>

    <aside class="navigation-sidebar">
        <div class="navigation-header"><div><strong>Lumi</strong><span>♡</span></div></div>
        <div class="search-box"><input type="text" placeholder="Rechercher..." aria-label="Rechercher"></div>
        <nav class="main-navigation">
            <a href="salons.php" class="navigation-item"><span>⌂</span> Accueil</a>
            <a href="messages.php" class="navigation-item"><span>💬</span> Messages</a>
            <a href="amis.php" class="navigation-item"><span>👥</span> Amis</a>
        </nav>
        <div class="rooms-navigation">
            <div class="notification-nav-card">
                <span>🔔</span>
                <div><strong>Notifications</strong><small><?= $non_lues ?> non lue<?= $non_lues > 1 ? 's' : '' ?></small></div>
            </div>
        </div>
        <div class="user-panel">
            <div class="user-avatar"><?= htmlspecialchars($initiale) ?></div>
            <div class="user-info"><strong><?= htmlspecialchars($_SESSION['pseudo']) ?></strong><span><?= $est_admin ? 'Administrateur' : 'En ligne' ?></span></div>
            <a href="deconnexion.php" class="logout-button" title="Se déconnecter">↪</a>
        </div>
    </aside>

    <main class="main-content notifications-main">
        <header class="content-header">
            <div class="welcome-title"><span class="header-label">LUMI</span><h1>Notifications</h1></div>
            <?php if ($non_lues > 0): ?>
                <form method="POST">
                    <?= csrf_field() ?><button type="submit" name="tout_lire" class="mark-all-read">Tout marquer comme lu</button></form>
            <?php endif; ?>
        </header>

        <div class="content-container notifications-container">
            <div class="notification-intro">
                <span class="section-label">CENTRE DE NOTIFICATIONS</span>
                <h2>Ce qui s'est passé sur Lumi</h2>
                <p>Retrouve ici les nouveaux messages privés et les activités de tes salons privés.</p>
            </div>

            <section class="notifications-list">
                <?php if (empty($notifications)): ?>
                    <div class="notification-empty">
                        <div>♡</div>
                        <h3>Aucune notification</h3>
                        <p>Tu es à jour. Les nouvelles activités apparaîtront ici.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <a href="notifications.php?lire=<?= (int)$notification['id'] ?>" class="notification-item <?= (int)$notification['lu'] === 0 ? 'notification-unread' : '' ?>">
                            <span class="notification-icon"><?= $notification['type'] === 'message_prive' ? '💬' : ($notification['type'] === 'ami' ? '👥' : '🔒') ?></span>
                            <span class="notification-content">
                                <strong><?= htmlspecialchars($notification['contenu']) ?></strong>
                                <small><?= htmlspecialchars(date('d/m/Y à H:i', strtotime($notification['date_creation']))) ?></small>
                            </span>
                            <?php if ((int)$notification['lu'] === 0): ?><span class="notification-dot"></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>
</body>
</html>
