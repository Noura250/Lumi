<?php

require 'config/security.php';
require 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit;
}

$est_admin = ($_SESSION['role'] === 'admin');

// Créer un nouveau salon
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['nom'])) {

    $nom = trim($_POST["nom"]);

    /*
     * Vérification du nom du salon
     */
    if ($nom === '') {
        die("Le nom du salon est obligatoire.");
    }

    /*
     * Un salon est public par défaut.
     * Seul un administrateur peut créer un salon privé.
     */
    $visibilite = ($est_admin && isset($_POST['visibilite']))
            ? $_POST['visibilite']
            : 'public';

    /*
     * Vérifier que la visibilité est une valeur autorisée.
     */
    if (!in_array($visibilite, ['public', 'prive'], true)) {
        die("Visibilité du salon invalide.");
    }

    $sql = "INSERT INTO salons (nom, visibilite)
            VALUES (:nom, :visibilite)";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
            'nom' => $nom,
            'visibilite' => $visibilite
    ]);

    $id_nouveau_salon = $pdo->lastInsertId();

    /*
     * Si le salon est privé, l'administrateur
     * peut choisir les membres autorisés.
     */
    if ($visibilite === 'prive' && !empty($_POST['membres'])) {

        foreach ($_POST['membres'] as $id_membre) {

            /*
             * Vérifier que l'identifiant est bien numérique.
             */
            if (!ctype_digit((string) $id_membre)) {
                continue;
            }

            $sql2 = "INSERT INTO salon_membres
                     (id_salon, id_utilisateur)
                     VALUES (:id_salon, :id_utilisateur)";

            $stmt2 = $pdo->prepare($sql2);

            $stmt2->execute([
                    'id_salon' => $id_nouveau_salon,
                    'id_utilisateur' => (int) $id_membre
            ]);
        }
    }

    /*
     * Éviter qu'un rafraîchissement du navigateur
     * recrée le salon.
     */
    header("Location: salons.php");
    exit;
}

// Récupérer les salons visibles par cet utilisateur
$sql = "SELECT DISTINCT salons.*
        FROM salons
        LEFT JOIN salon_membres
            ON salons.id = salon_membres.id_salon
        WHERE salons.visibilite = 'public'
        OR salon_membres.id_utilisateur = :user_id";

$stmt = $pdo->prepare($sql);

$stmt->execute([
        'user_id' => $_SESSION['user_id']
]);

$salons = $stmt->fetchAll();

/*
 * Si l'utilisateur est administrateur,
 * récupérer la liste des utilisateurs
 * pour pouvoir créer des salons privés.
 */
if ($est_admin) {
    $utilisateurs = $pdo->query(
            "SELECT id, pseudo FROM utilisateurs"
    )->fetchAll();
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Salons - Lumi</title>

    <link rel="stylesheet" href="css/style.css">

</head>

<body>

<h1>
    Bienvenue
    <?= htmlspecialchars($_SESSION['pseudo'], ENT_QUOTES, 'UTF-8') ?>
    <?= $est_admin ? " (admin)" : "" ?>
</h1>

<p>
    <a href="deconnexion.php">Se déconnecter</a>
</p>

<h2>Salons disponibles</h2>

<ul>

    <?php foreach ($salons as $salon): ?>

        <li>

            <a href="messages.php?id_salon=<?= (int) $salon['id'] ?>">
                <?= htmlspecialchars($salon['nom'], ENT_QUOTES, 'UTF-8') ?>
            </a>

            <?= $salon['visibilite'] === 'prive' ? " (privé)" : "" ?>

        </li>

    <?php endforeach; ?>

</ul>

<h2>Créer un nouveau salon</h2>

<form method="POST">

    <?= csrf_field() ?>

    <label>
        Nom du salon :
        <input
                type="text"
                name="nom"
                maxlength="100"
                required
        >
    </label>

    <br>

    <?php if ($est_admin): ?>

        <label>
            Visibilité :

            <select
                    name="visibilite"
                    id="visibilite-select"
                    onchange="document.getElementById('membres-liste').style.display = this.value === 'prive' ? 'block' : 'none';"
            >

                <option value="public">
                    Public
                </option>

                <option value="prive">
                    Privé
                </option>

            </select>

        </label>

        <div
                id="membres-liste"
                style="display: none;"
        >

            <p>
                Choisir les membres autorisés :
            </p>

            <?php foreach ($utilisateurs as $u): ?>

                <label>

                    <input
                            type="checkbox"
                            name="membres[]"
                            value="<?= (int) $u['id'] ?>"
                    >

                    <?= htmlspecialchars($u['pseudo'], ENT_QUOTES, 'UTF-8') ?>

                </label>

                <br>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <button type="submit">
        Créer
    </button>

</form>

</body>

</html>