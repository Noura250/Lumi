<?php

require 'config/security.php';
require 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit;
}

if (!isset($_GET['code']) || !is_string($_GET['code'])) {
    die("Lien d'invitation invalide.");
}

$code = $_GET['code'];

/*
 * Vérifier le format du code.
 * Les codes Lumi sont générés avec bin2hex(random_bytes(16)),
 * donc ils contiennent 32 caractères hexadécimaux.
 */
if (!preg_match('/^[a-f0-9]{32}$/', $code)) {
    die("Lien d'invitation invalide.");
}

/*
 * Chercher l'invitation
 */
$sql = "SELECT *
        FROM invitations
        WHERE code = :code
        AND actif = 1";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    'code' => $code
]);

$invitation = $stmt->fetch();

if (!$invitation) {
    die("Cette invitation n'existe pas ou n'est plus active.");
}

/*
 * Vérifier l'expiration
 */
if (
    $invitation['date_expiration'] !== null
    && strtotime($invitation['date_expiration']) < time()
) {
    die("Cette invitation a expiré.");
}

/*
 * Vérifier le nombre maximal d'utilisations
 */
if (
    $invitation['utilisations_max'] !== null
    && $invitation['utilisations'] >= $invitation['utilisations_max']
) {
    die("Cette invitation a atteint sa limite d'utilisations.");
}

/*
 * Vérifier si l'utilisateur est déjà membre
 */
$sql = "SELECT id
        FROM salon_membres
        WHERE id_salon = :id_salon
        AND id_utilisateur = :id_utilisateur";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    'id_salon' => $invitation['id_salon'],
    'id_utilisateur' => $_SESSION['user_id']
]);

$deja_membre = $stmt->fetch();

/*
 * Rejoindre le salon uniquement après
 * validation du formulaire POST.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!$deja_membre) {

        /*
         * Ajouter l'utilisateur au salon
         */
        $sql = "INSERT INTO salon_membres
                (id_salon, id_utilisateur)
                VALUES (:id_salon, :id_utilisateur)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'id_salon' => $invitation['id_salon'],
            'id_utilisateur' => $_SESSION['user_id']
        ]);

        /*
         * Ajouter une utilisation
         */
        $sql = "UPDATE invitations
                SET utilisations = utilisations + 1
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'id' => $invitation['id']
        ]);
    }

    /*
     * Envoyer l'utilisateur dans le salon
     */
    header(
        "Location: messages.php?id_salon="
        . $invitation['id_salon']
    );

    exit;
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Rejoindre un salon - Lumi</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<h1>Invitation Lumi</h1>

<?php if ($deja_membre): ?>

    <p>
        Tu es déjà membre de ce salon.
    </p>

    <p>
        <a href="messages.php?id_salon=<?= (int)$invitation['id_salon'] ?>">
            Accéder au salon
        </a>
    </p>

<?php else: ?>

    <p>
        Tu as été invité à rejoindre un salon Lumi.
    </p>

    <form method="POST">

        <?= csrf_field() ?>

        <button type="submit">
            Rejoindre le salon
        </button>

    </form>

<?php endif; ?>

<p>
    <a href="salons.php">← Retour aux salons</a>
</p>

</body>

</html>