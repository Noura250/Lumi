<?php

require 'config/security.php';
require 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit;
}

if (!isset($_GET['id_salon']) || !ctype_digit($_GET['id_salon'])) {
    die("Salon introuvable.");
}

$id_salon = (int) $_GET['id_salon'];

/*
 * Pour l'instant, on autorise uniquement
 * l'utilisateur connecté à créer une invitation
 * pour un salon auquel il a accès.
 */

$sql = "SELECT id
        FROM salon_membres
        WHERE id_salon = :id_salon
        AND id_utilisateur = :id_utilisateur";

$stmt = $pdo->prepare($sql);
$stmt->execute([
        'id_salon' => $id_salon,
        'id_utilisateur' => $_SESSION['user_id']
]);

$est_membre = $stmt->fetch();

if (!$est_membre) {
    die("Tu n'as pas accès à ce salon.");
}

$lien = null;

/*
 * Génération de l'invitation uniquement
 * après validation d'un formulaire POST.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
     * Génération d'un code aléatoire sécurisé
     */
    $code = bin2hex(random_bytes(16));

    /*
     * Création de l'invitation
     */
    $sql = "INSERT INTO invitations
            (id_salon, code, id_createur)
            VALUES (:id_salon, :code, :id_createur)";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
            'id_salon' => $id_salon,
            'code' => $code,
            'id_createur' => $_SESSION['user_id']
    ]);

    /*
     * Création du lien
     */
    $lien = "http://localhost:8888/lumi/rejoindre.php?code=" . $code;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Invitation - Lumi</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<h1>Invitation Lumi</h1>

<?php if ($lien !== null): ?>

    <p>Ton lien d'invitation a été créé :</p>

    <input
            type="text"
            value="<?= htmlspecialchars($lien, ENT_QUOTES, 'UTF-8') ?>"
            readonly
            style="width: 500px;"
    >

    <p>
        Partage ce lien avec la personne que tu souhaites inviter.
    </p>

<?php else: ?>

    <p>
        Crée un lien d'invitation pour permettre à une personne de rejoindre ce salon.
    </p>

    <form method="POST">
        <?= csrf_field() ?>

        <button type="submit">
            Créer une invitation
        </button>
    </form>

<?php endif; ?>

<p>
    <a href="salons.php">← Retour aux salons</a>
</p>

</body>
</html>