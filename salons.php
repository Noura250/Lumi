<?php
require 'config/security.php';
require 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit;
}

$est_admin = ($_SESSION['role'] === 'admin');

// Nombre de notifications non lues.
$sql = "SELECT COUNT(*) FROM notifications WHERE id_utilisateur = :user_id AND lu = 0";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$notifications_non_lues = (int) $stmt->fetchColumn();

// Créer un nouveau salon
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['nom'])) {
    $nom = $_POST["nom"];
    $visibilite = ($est_admin && isset($_POST['visibilite'])) ? $_POST['visibilite'] : 'public';

    $sql = "INSERT INTO salons (nom, visibilite) VALUES (:nom, :visibilite)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['nom' => $nom, 'visibilite' => $visibilite]);

    $id_nouveau_salon = $pdo->lastInsertId();

    if ($visibilite === 'prive' && !empty($_POST['membres'])) {
        foreach ($_POST['membres'] as $id_membre) {
            $sql2 = "INSERT INTO salon_membres (id_salon, id_utilisateur)
                     VALUES (:id_salon, :id_utilisateur)";
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute([
                    'id_salon' => $id_nouveau_salon,
                    'id_utilisateur' => $id_membre
            ]);
        }
    }
}

// Supprimer un salon : réservé aux administrateurs.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['supprimer_salon'])) {
    if (!$est_admin) {
        http_response_code(403);
        exit('Accès refusé.');
    }

    $id_salon_supprimer = isset($_POST['id_salon']) && ctype_digit($_POST['id_salon'])
            ? (int) $_POST['id_salon']
            : 0;

    if ($id_salon_supprimer > 0) {
        $pdo->beginTransaction();
        try {
            // On supprime d'abord les données liées pour éviter les erreurs de clé étrangère.
            $stmt = $pdo->prepare("DELETE FROM messages WHERE id_salon = :id_salon");
            $stmt->execute(['id_salon' => $id_salon_supprimer]);

            $stmt = $pdo->prepare("DELETE FROM salon_membres WHERE id_salon = :id_salon");
            $stmt->execute(['id_salon' => $id_salon_supprimer]);

            $stmt = $pdo->prepare("DELETE FROM salons WHERE id = :id_salon");
            $stmt->execute(['id_salon' => $id_salon_supprimer]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    header('Location: salons.php');
    exit;
}

// Récupérer les salons visibles par cet utilisateur
$sql = "SELECT DISTINCT salons.* FROM salons
        LEFT JOIN salon_membres ON salons.id = salon_membres.id_salon
        WHERE salons.visibilite = 'public'
        OR salon_membres.id_utilisateur = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$salons = $stmt->fetchAll();

$utilisateurs = [];

if ($est_admin) {
    $utilisateurs = $pdo->query("SELECT id, pseudo FROM utilisateurs")->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salons - Lumi</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
<div class="app-layout">

    <aside class="server-sidebar">
        <a href="salons.php" class="lumi-logo">
            <img src="images/logo_lumi.png" alt="Logo Lumi">
        </a>

        <div class="sidebar-separator"></div>

        <nav class="quick-nav">
            <a href="salons.php" class="quick-nav-item active" title="Accueil">⌂</a>

            <a href="messages.php" class="quick-nav-item" title="Messages">💬</a>

            <a href="amis.php" class="quick-nav-item" title="Amis">👥</a>
        </nav>

        <a href="#creer-salon" class="quick-nav-item add-room" title="Créer un salon">+</a>
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
            <a href="salons.php" class="navigation-item active">
                <span>⌂</span>
                Accueil
            </a>

            <a href="messages.php" class="navigation-item">
                <span>💬</span>
                Messages
            </a>

            <a href="amis.php" class="navigation-item">
                <span>👥</span>
                Amis
            </a>
        </nav>

        <div class="rooms-navigation">
            <div class="rooms-title">
                <span>TES SALONS</span>
                <a href="#creer-salon" title="Créer un salon">+</a>
            </div>

            <div class="rooms-list">
                <?php if (empty($salons)): ?>
                    <p class="no-room">Aucun salon pour le moment.</p>
                <?php else: ?>
                    <?php foreach ($salons as $salon): ?>
                        <div class="room-navigation-wrapper">
                            <a href="messages.php?id_salon=<?= (int)$salon['id'] ?>"
                               class="room-navigation-item">
                                <span class="room-hash">#</span>
                                <span class="room-name">
                                    <?= htmlspecialchars($salon['nom']) ?>
                                </span>

                                <?php if ($salon['visibilite'] === 'prive'): ?>
                                    <span class="room-lock">🔒</span>
                                <?php endif; ?>
                            </a>

                            <a href="creer_invitation.php?id_salon=<?= (int)$salon['id'] ?>"
                               class="room-invite-button"
                               title="Créer un lien d'invitation">🔗</a>

                            <?php if ($est_admin): ?>
                                <form method="POST" class="admin-room-delete" onsubmit="return confirm('Supprimer le salon #<?= htmlspecialchars($salon['nom'], ENT_QUOTES) ?> et tous ses messages ?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id_salon" value="<?= (int)$salon['id'] ?>">
                                    <button type="submit" name="supprimer_salon" title="Supprimer le salon" aria-label="Supprimer le salon">×</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="user-panel">
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['pseudo'], 0, 1)) ?>
            </div>

            <div class="user-info">
                <strong><?= htmlspecialchars($_SESSION['pseudo']) ?></strong>
                <span><?= $est_admin ? "Administrateur" : "En ligne" ?></span>
            </div>

            <a href="deconnexion.php" class="logout-button" title="Se déconnecter">↪</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="welcome-title">
                <span class="header-label">ESPACE COMMUNAUTAIRE</span>
                <h1>
                    Bienvenue, <?= htmlspecialchars($_SESSION['pseudo']) ?>
                    <?php if ($est_admin): ?>
                        <small>admin</small>
                    <?php endif; ?>
                </h1>
            </div>

            <div class="header-actions">
                <a href="notifications.php" class="notification-header-button" title="Notifications">
                    🔔
                    <?php if ($notifications_non_lues > 0): ?>
                        <span class="notification-badge"><?= $notifications_non_lues ?></span>
                    <?php endif; ?>
                </a>
                <button type="button" title="Paramètres">⚙</button>
            </div>
        </header>

        <div class="content-container">
            <section class="welcome-section">
                <div class="welcome-card">
                    <div class="welcome-card-content">
                        <span class="welcome-eyebrow">✦ BIENVENUE SUR LUMI</span>

                        <h2>
                            Ton espace pour <span>échanger.</span>
                        </h2>

                        <p>
                            Rejoins un salon, discute avec les autres utilisateurs
                            et profite de ton espace Lumi.
                        </p>
                    </div>

                    <div class="welcome-decoration">✦</div>
                </div>
            </section>

            <section class="rooms-section">
                <div class="section-heading">
                    <div>
                        <span class="section-label">COMMUNAUTÉ</span>
                        <h2>Tes salons</h2>
                    </div>

                    <span class="room-count">
                        <?= count($salons) ?> salon<?= count($salons) > 1 ? 's' : '' ?>
                    </span>
                </div>

                <div class="rooms-grid">
                    <?php if (empty($salons)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">♡</div>
                            <h3>Aucun salon pour le moment</h3>
                            <p>Crée ton premier salon pour commencer.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($salons as $salon): ?>
                            <a href="messages.php?id_salon=<?= (int)$salon['id'] ?>"
                               class="room-card">
                                <div class="room-card-icon">#</div>

                                <div class="room-card-content">
                                    <h3><?= htmlspecialchars($salon['nom']) ?></h3>
                                    <p>
                                        <?= $salon['visibilite'] === 'prive'
                                                ? 'Salon privé'
                                                : 'Salon public' ?>
                                    </p>
                                </div>

                                <span class="room-arrow">→</span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="create-room-section" id="creer-salon">
                <div class="create-room-header">
                    <div>
                        <span class="section-label">NOUVEAU</span>
                        <h2>Créer un salon</h2>
                        <p>Crée un espace pour discuter avec ta communauté.</p>
                    </div>

                    <div class="create-icon">+</div>
                </div>

                <form method="POST" class="create-room-form">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="nom">Nom du salon</label>
                        <input type="text" id="nom" name="nom"
                               placeholder="ex : discussions" required>
                    </div>

                    <?php if ($est_admin): ?>
                        <div class="form-group">
                            <label for="visibilite-select">Visibilité</label>
                            <select name="visibilite" id="visibilite-select"
                                    onchange="document.getElementById('membres-liste').style.display = this.value === 'prive' ? 'block' : 'none';">
                                <option value="public">Public</option>
                                <option value="prive">Privé</option>
                            </select>
                        </div>

                        <div id="membres-liste" class="members-list" style="display:none;">
                            <p>Choisir les membres autorisés :</p>

                            <?php foreach ($utilisateurs as $u): ?>
                                <label class="member-option">
                                    <input type="checkbox"
                                           name="membres[]"
                                           value="<?= (int)$u['id'] ?>">
                                    <span><?= htmlspecialchars($u['pseudo']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="create-room-button">
                        <span>+</span>
                        Créer le salon
                    </button>
                </form>
            </section>
        </div>
    </main>
</div>
</body>
</html>
