<?php

require 'config/security.php';
require 'config/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = $_POST["login"];
    $pseudo = $_POST["pseudo"];
    $mot_de_passe = $_POST["mot_de_passe"];
    $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

    $sql = "INSERT INTO utilisateurs (login, pseudo, mot_de_passe, role) 
            VALUES (:login, :pseudo, :mot_de_passe, 'user')";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
            'login' => $login,
            'pseudo' => $pseudo,
            'mot_de_passe' => $mot_de_passe_hash
    ]);

    header("Location: connexion.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Inscription - Lumi</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<h1>Créer un compte</h1>

<form method="POST">

    <?= csrf_field() ?>

    <label>
        Login :
        <input type="text" name="login" required>
    </label>

    <br>

    <label>
        Pseudo :
        <input type="text" name="pseudo" required>
    </label>

    <br>

    <label>
        Mot de passe :
        <input type="password" name="mot_de_passe" required>
    </label>

    <br>

    <button type="submit">S'inscrire</button>

</form>

</body>
</html>