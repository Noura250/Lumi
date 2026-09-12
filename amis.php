<?php
require 'config/security.php';
require 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$id_utilisateur = (int) $_SESSION['user_id'];
$message = '';
$erreur = '';

// Actions liées aux amis.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id_cible = isset($_POST['id_utilisateur']) && ctype_digit($_POST['id_utilisateur'])
        ? (int) $_POST['id_utilisateur']
        : 0;

    if ($action === 'envoyer_demande' && $id_cible > 0 && $id_cible !== $id_utilisateur) {
        // Vérifier que l'utilisateur existe.
        $stmt = $pdo->prepare("SELECT id, pseudo FROM utilisateurs WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id_cible]);
        $cible = $stmt->fetch();

        if (!$cible) {
            $erreur = "Cet utilisateur n'existe pas.";
        } else {
            // Vérifier qu'il n'existe pas déjà une relation dans un sens ou dans l'autre.
            $stmt = $pdo->prepare("SELECT id, statut, demandeur_id, receveur_id
                                   FROM amis
                                   WHERE (demandeur_id = :moi1 AND receveur_id = :cible1)
                                      OR (demandeur_id = :cible2 AND receveur_id = :moi2)
                                   LIMIT 1");
            $stmt->execute([
                'moi1' => $id_utilisateur,
                'cible1' => $id_cible,
                'cible2' => $id_cible,
                'moi2' => $id_utilisateur
            ]);
            $relation = $stmt->fetch();

            if ($relation) {
                if ($relation['statut'] === 'acceptee') {
                    $erreur = "Vous êtes déjà amis.";
                } elseif ((int)$relation['receveur_id'] === $id_utilisateur) {
                    // L'autre personne avait déjà envoyé une demande : on l'accepte directement.
                    $stmt = $pdo->prepare("UPDATE amis
                                           SET statut = 'acceptee', date_acceptation = NOW()
                                           WHERE id = :id AND receveur_id = :receveur");
                    $stmt->execute([
                        'id' => $relation['id'],
                        'receveur' => $id_utilisateur
                    ]);
                    $message = "Vous êtes maintenant amis avec " . $cible['pseudo'] . ".";
                } else {
                    $erreur = "Une demande est déjà en attente.";
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO amis (demandeur_id, receveur_id, statut)
                                       VALUES (:demandeur, :receveur, 'en_attente')");
                $stmt->execute([
                    'demandeur' => $id_utilisateur,
                    'receveur' => $id_cible
                ]);

                // Notification de demande d'ami.
                $stmt = $pdo->prepare("INSERT INTO notifications
                                       (id_utilisateur, type, contenu, lien, lu, date_creation)
                                       VALUES (:id_utilisateur, 'ami', :contenu, 'amis.php', 0, NOW())");
                $stmt->execute([
                    'id_utilisateur' => $id_cible,
                    'contenu' => $_SESSION['pseudo'] . ' t’a envoyé une demande d’ami.'
                ]);

                $message = "Demande d’ami envoyée à " . $cible['pseudo'] . ".";
            }
        }
    }

    if ($action === 'accepter' && $id_cible > 0) {
        $stmt = $pdo->prepare("SELECT a.id, a.demandeur_id, u.pseudo
                               FROM amis a
                               JOIN utilisateurs u ON u.id = a.demandeur_id
                               WHERE a.id = :id
                                 AND a.receveur_id = :moi
                                 AND a.statut = 'en_attente'
                               LIMIT 1");
        $stmt->execute([
            'id' => $id_cible,
            'moi' => $id_utilisateur
        ]);
        $demande = $stmt->fetch();

        if ($demande) {
            $stmt = $pdo->prepare("UPDATE amis
                                   SET statut = 'acceptee', date_acceptation = NOW()
                                   WHERE id = :id AND receveur_id = :moi");
            $stmt->execute([
                'id' => $id_cible,
                'moi' => $id_utilisateur
            ]);

            $stmt = $pdo->prepare("INSERT INTO notifications
                                   (id_utilisateur, type, contenu, lien, lu, date_creation)
                                   VALUES (:id_utilisateur, 'ami', :contenu, 'amis.php', 0, NOW())");
            $stmt->execute([
                'id_utilisateur' => (int)$demande['demandeur_id'],
                'contenu' => $_SESSION['pseudo'] . ' a accepté ta demande d’ami.'
            ]);

            $message = "Demande acceptée. Vous êtes maintenant amis avec " . $demande['pseudo'] . ".";
        }
    }

    if ($action === 'refuser' && $id_cible > 0) {
        $stmt = $pdo->prepare("DELETE FROM amis
                               WHERE id = :id AND receveur_id = :moi AND statut = 'en_attente'");
        $stmt->execute([
            'id' => $id_cible,
            'moi' => $id_utilisateur
        ]);
        $message = "Demande refusée.";
    }

    if ($action === 'supprimer' && $id_cible > 0) {
        $stmt = $pdo->prepare("DELETE FROM amis
                               WHERE id = :id
                                 AND statut = 'acceptee'
                                 AND (demandeur_id = :moi1 OR receveur_id = :moi2)");
        $stmt->execute([
            'id' => $id_cible,
            'moi1' => $id_utilisateur,
            'moi2' => $id_utilisateur
        ]);
        $message = "Ami supprimé.";
    }

    // Évite de renvoyer le formulaire au rafraîchissement.
    $params = [];
    if ($message !== '') {
        $params['message'] = $message;
    }
    if ($erreur !== '') {
        $params['erreur'] = $erreur;
    }
    header('Location: amis.php' . (!empty($params) ? '?' . http_build_query($params) : ''));
    exit;
}

$message = $_GET['message'] ?? '';
$erreur = $_GET['erreur'] ?? '';

// Amis acceptés.
$sql = "SELECT a.id, u.id AS user_id, u.pseudo
        FROM amis a
        JOIN utilisateurs u
          ON u.id = CASE
                WHEN a.demandeur_id = :moi1 THEN a.receveur_id
                ELSE a.demandeur_id
             END
        WHERE a.statut = 'acceptee'
          AND (a.demandeur_id = :moi2 OR a.receveur_id = :moi3)
        ORDER BY u.pseudo ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    'moi1' => $id_utilisateur,
    'moi2' => $id_utilisateur,
    'moi3' => $id_utilisateur
]);
$amis = $stmt->fetchAll();

// Demandes reçues.
$sql = "SELECT a.id, u.id AS user_id, u.pseudo
        FROM amis a
        JOIN utilisateurs u ON u.id = a.demandeur_id
        WHERE a.receveur_id = :moi
          AND a.statut = 'en_attente'
        ORDER BY a.date_demande DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['moi' => $id_utilisateur]);
$demandes_recues = $stmt->fetchAll();

// Demandes envoyées.
$sql = "SELECT a.id, u.id AS user_id, u.pseudo
        FROM amis a
        JOIN utilisateurs u ON u.id = a.receveur_id
        WHERE a.demandeur_id = :moi
          AND a.statut = 'en_attente'
        ORDER BY a.date_demande DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['moi' => $id_utilisateur]);
$demandes_envoyees = $stmt->fetchAll();

// Utilisateurs qui ne sont pas encore liés à l'utilisateur connecté.
$sql = "SELECT u.id, u.pseudo
        FROM utilisateurs u
        WHERE u.id <> :moi
          AND NOT EXISTS (
              SELECT 1 FROM amis a
              WHERE (a.demandeur_id = :moi1 AND a.receveur_id = u.id)
                 OR (a.demandeur_id = u.id AND a.receveur_id = :moi2)
          )
        ORDER BY u.pseudo ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    'moi' => $id_utilisateur,
    'moi1' => $id_utilisateur,
    'moi2' => $id_utilisateur
]);
$utilisateurs_disponibles = $stmt->fetchAll();

// Notifications non lues.
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_utilisateur = :moi AND lu = 0");
$stmt->execute(['moi' => $id_utilisateur]);
$notifications_non_lues = (int) $stmt->fetchColumn();

$est_admin = ($_SESSION['role'] ?? '') === 'admin';
$initiale = strtoupper(substr($_SESSION['pseudo'], 0, 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amis — Lumi</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .friends-main { overflow-y: auto; }
        .friends-container { max-width: 1180px; }
        .friends-hero {
            background: linear-gradient(135deg, #f0eaff, #faf7ff);
            border: 1px solid rgba(124, 92, 255, .10);
            border-radius: 24px;
            padding: 30px 34px;
            margin-bottom: 24px;
        }
        .friends-hero h2 { margin: 8px 0 8px; font-size: 28px; }
        .friends-hero p { margin: 0; color: #77758d; }
        .friends-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }
        .friends-card {
            background: #fff;
            border: 1px solid #eeeaf8;
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 8px 28px rgba(70, 55, 120, .05);
        }
        .friends-card.full { grid-column: 1 / -1; }
        .friends-card-header { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:18px; }
        .friends-card h3 { margin: 4px 0 0; font-size: 19px; }
        .friends-count { color:#8b83a7; font-size:13px; }
        .friend-list { display:flex; flex-direction:column; gap:10px; }
        .friend-row {
            display:flex; align-items:center; gap:12px; padding:12px;
            border:1px solid #f0edf8; border-radius:14px; background:#fcfbff;
        }
        .friend-avatar {
            width:42px; height:42px; border-radius:13px; display:grid; place-items:center;
            background:#eee7ff; color:#7757ff; font-weight:800; flex:0 0 42px;
        }
        .friend-info { min-width:0; flex:1; }
        .friend-info strong { display:block; color:#29263f; }
        .friend-info small { color:#8a869b; }
        .friend-actions { display:flex; gap:7px; }
        .friend-action {
            border:0; border-radius:10px; padding:8px 12px; cursor:pointer; font-weight:700;
            background:#eee8ff; color:#7455ff;
        }
        .friend-action.refuse { background:#f7eef3; color:#b96c89; }
        .friend-action.delete { background:#fff0f3; color:#c76880; }
        .add-friend-form { display:flex; gap:10px; }
        .add-friend-form select {
            flex:1; min-width:0; border:1px solid #e8e3f3; border-radius:12px; padding:12px 14px;
            background:#faf9fd; color:#403b55; outline:none;
        }
        .add-friend-form button {
            border:0; border-radius:12px; padding:0 18px; background:#8b63ff; color:white;
            font-weight:700; cursor:pointer; box-shadow:0 8px 18px rgba(139,99,255,.18);
        }
        .friends-empty { text-align:center; padding:24px 10px; color:#8c889d; }
        .friends-empty strong { display:block; color:#4a465d; margin-bottom:5px; }
        .friends-alert { margin-bottom:18px; padding:12px 15px; border-radius:12px; background:#edf9f1; color:#3d8a58; }
        .friends-error { margin-bottom:18px; padding:12px 15px; border-radius:12px; background:#fff0f3; color:#b95e78; }
        .friends-nav-badge { margin-left:auto; min-width:20px; padding:2px 6px; border-radius:20px; background:#eee6ff; color:#795bff; font-size:11px; text-align:center; }
        @media (max-width: 850px) { .friends-grid { grid-template-columns: 1fr; } .friends-card.full { grid-column:auto; } }
        @media (max-width: 600px) { .add-friend-form { flex-direction:column; } .add-friend-form button { padding:12px; } .friend-row { align-items:flex-start; } .friend-actions { flex-direction:column; } }
    </style>
</head>
<body class="friends-page">
<div class="app-layout">
    <aside class="server-sidebar">
        <a href="salons.php" class="lumi-logo" title="Accueil Lumi"><img src="images/logo_lumi.png" alt="Logo Lumi"></a>
        <div class="sidebar-separator"></div>
        <nav class="quick-nav">
            <a href="salons.php" class="quick-nav-item" title="Accueil">⌂</a>
            <a href="messages.php" class="quick-nav-item" title="Messages">💬</a>
            <a href="amis.php" class="quick-nav-item active" title="Amis">👥</a>
        </nav>
        <a href="salons.php#creer-salon" class="quick-nav-item add-room" title="Créer un salon">+</a>
    </aside>

    <aside class="navigation-sidebar">
        <div class="navigation-header"><div><strong>Lumi</strong><span>♡</span></div></div>
        <div class="search-box"><input type="text" placeholder="Rechercher..." aria-label="Rechercher"></div>
        <nav class="main-navigation">
            <a href="salons.php" class="navigation-item"><span>⌂</span> Accueil</a>
            <a href="messages.php" class="navigation-item"><span>💬</span> Messages</a>
            <a href="amis.php" class="navigation-item active"><span>👥</span> Amis</a>
        </nav>

        <div class="rooms-navigation">
            <div class="rooms-title"><span>AMIS</span><span class="friends-nav-badge"><?= count($amis) ?></span></div>
            <div class="rooms-list">
                <p class="no-room">Retrouve ici tes amis et tes demandes.</p>
            </div>
        </div>

        <div class="user-panel">
            <div class="user-avatar"><?= htmlspecialchars($initiale) ?></div>
            <div class="user-info"><strong><?= htmlspecialchars($_SESSION['pseudo']) ?></strong><span><?= $est_admin ? 'Administrateur' : 'En ligne' ?></span></div>
            <a href="deconnexion.php" class="logout-button" title="Se déconnecter">↪</a>
        </div>
    </aside>

    <main class="main-content friends-main">
        <header class="content-header">
            <div class="welcome-title"><span class="header-label">LUMI</span><h1>Amis</h1></div>
            <div class="header-actions">
                <a href="notifications.php" class="notification-header-button" title="Notifications">
                    🔔
                    <?php if ($notifications_non_lues > 0): ?><span class="notification-badge"><?= $notifications_non_lues ?></span><?php endif; ?>
                </a>
            </div>
        </header>

        <div class="content-container friends-container">
            <?php if ($message !== ''): ?><div class="friends-alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($erreur !== ''): ?><div class="friends-error"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>

            <section class="friends-hero">
                <span class="section-label">COMMUNAUTÉ</span>
                <h2>Garde le contact avec tes amis.</h2>
                <p>Ajoute des utilisateurs, accepte les demandes et retrouve toutes tes relations au même endroit.</p>
            </section>

            <div class="friends-grid">
                <section class="friends-card full">
                    <div class="friends-card-header">
                        <div><span class="section-label">NOUVEAU</span><h3>Ajouter un ami</h3></div>
                    </div>
                    <?php if (empty($utilisateurs_disponibles)): ?>
                        <div class="friends-empty"><strong>Aucun utilisateur disponible</strong><span>Tu es déjà lié à tous les utilisateurs.</span></div>
                    <?php else: ?>
                        <form method="POST" class="add-friend-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="envoyer_demande">
                            <select name="id_utilisateur" required>
                                <option value="">Choisir un utilisateur...</option>
                                <?php foreach ($utilisateurs_disponibles as $utilisateur): ?>
                                    <option value="<?= (int)$utilisateur['id'] ?>"><?= htmlspecialchars($utilisateur['pseudo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit">Envoyer la demande</button>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="friends-card">
                    <div class="friends-card-header">
                        <div><span class="section-label">TES AMIS</span><h3>Amis</h3></div>
                        <span class="friends-count"><?= count($amis) ?></span>
                    </div>
                    <div class="friend-list">
                        <?php if (empty($amis)): ?>
                            <div class="friends-empty"><strong>Pas encore d'amis</strong><span>Ajoute quelqu’un pour commencer.</span></div>
                        <?php else: ?>
                            <?php foreach ($amis as $ami): ?>
                                <div class="friend-row">
                                    <div class="friend-avatar"><?= htmlspecialchars(strtoupper(substr($ami['pseudo'], 0, 1))) ?></div>
                                    <div class="friend-info"><strong><?= htmlspecialchars($ami['pseudo']) ?></strong><small>Ton ami sur Lumi</small></div>
                                    <div class="friend-actions">
                                        <a href="messages_prives.php?user_id=<?= (int)$ami['user_id'] ?>" class="friend-action" style="text-decoration:none;">Message</a>
                                        <form method="POST" onsubmit="return confirm('Supprimer cet ami ?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="supprimer">
                                            <input type="hidden" name="id_utilisateur" value="<?= (int)$ami['id'] ?>">
                                            <button type="submit" class="friend-action delete">Supprimer</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="friends-card">
                    <div class="friends-card-header">
                        <div><span class="section-label">À TRAITER</span><h3>Demandes reçues</h3></div>
                        <span class="friends-count"><?= count($demandes_recues) ?></span>
                    </div>
                    <div class="friend-list">
                        <?php if (empty($demandes_recues)): ?>
                            <div class="friends-empty"><strong>Aucune demande</strong><span>Tu n’as aucune demande en attente.</span></div>
                        <?php else: ?>
                            <?php foreach ($demandes_recues as $demande): ?>
                                <div class="friend-row">
                                    <div class="friend-avatar"><?= htmlspecialchars(strtoupper(substr($demande['pseudo'], 0, 1))) ?></div>
                                    <div class="friend-info"><strong><?= htmlspecialchars($demande['pseudo']) ?></strong><small>Souhaite devenir ton ami</small></div>
                                    <div class="friend-actions">
                                        <form method="POST">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="accepter">
                                            <input type="hidden" name="id_utilisateur" value="<?= (int)$demande['id'] ?>">
                                            <button type="submit" class="friend-action">Accepter</button>
                                        </form>
                                        <form method="POST">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="refuser">
                                            <input type="hidden" name="id_utilisateur" value="<?= (int)$demande['id'] ?>">
                                            <button type="submit" class="friend-action refuse">Refuser</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="friends-card full">
                    <div class="friends-card-header">
                        <div><span class="section-label">EN ATTENTE</span><h3>Demandes envoyées</h3></div>
                        <span class="friends-count"><?= count($demandes_envoyees) ?></span>
                    </div>
                    <div class="friend-list">
                        <?php if (empty($demandes_envoyees)): ?>
                            <div class="friends-empty"><strong>Aucune demande envoyée</strong><span>Tes nouvelles demandes apparaîtront ici.</span></div>
                        <?php else: ?>
                            <?php foreach ($demandes_envoyees as $demande): ?>
                                <div class="friend-row">
                                    <div class="friend-avatar"><?= htmlspecialchars(strtoupper(substr($demande['pseudo'], 0, 1))) ?></div>
                                    <div class="friend-info"><strong><?= htmlspecialchars($demande['pseudo']) ?></strong><small>Demande en attente</small></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>
</body>
</html>
