<?php

require 'config/security.php';
require 'config/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = $_POST["login"];
    $mot_de_passe = $_POST["mot_de_passe"];

    $sql = "SELECT * FROM utilisateurs WHERE login = :login";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['login' => $login]);
    $utilisateur = $stmt->fetch();

    if ($utilisateur && password_verify($mot_de_passe, $utilisateur['mot_de_passe'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $utilisateur['id'];
        $_SESSION['pseudo'] = $utilisateur['pseudo'];
        $_SESSION['role'] = $utilisateur['role'];
        header("Location: salons.php");
        exit;
    } else {
        $erreur = "Login ou mot de passe incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Lumi</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">

<div class="login-wrapper">

    <a href="connexion.php" class="login-brand" aria-label="Lumi">
        <img src="images/logo_lumi.png" alt="Logo Lumi">
    </a>

    <div class="login-card">

        <div class="login-heading">
            <span class="login-eyebrow">BIENVENUE SUR LUMI</span>
            <h1>Se connecter</h1>
            <p class="login-subtitle">Connecte-toi pour retrouver tes salons et tes conversations.</p>
        </div>

        <?php if (isset($erreur)): ?>
            <div class="login-error">
                <?= htmlspecialchars($erreur) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="login-form">
            <?= csrf_field() ?>

            <div class="login-field">
                <label for="login">Login</label>
                <input
                        type="text"
                        id="login"
                        name="login"
                        placeholder="Ton login"
                        required
                >
            </div>

            <div class="login-field">
                <label for="mot_de_passe">Mot de passe</label>
                <input
                        type="password"
                        id="mot_de_passe"
                        name="mot_de_passe"
                        placeholder="Ton mot de passe"
                        required
                >
            </div>

            <button type="submit" class="login-button">Se connecter</button>

        </form>

        <p class="login-register">
            Pas encore de compte ?
            <a href="inscription.php">S'inscrire</a>
        </p>

    </div>
</div>

</body>
</html>
