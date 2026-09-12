<?php

// Sécurité commune de Lumi.
// Ce fichier ne contient aucun secret et peut être publié sur GitHub.

if (session_status() === PHP_SESSION_NONE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'httponly' => true,
        'secure' => $https,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Protection de base des réponses HTTP.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Jeton CSRF unique à la session.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token(): string
{
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') .
        '">';
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'] ?? '', $token)
    ) {
        http_response_code(403);
        exit('Requête refusée. Jeton de sécurité invalide.');
    }
}

// Toute requête POST doit fournir le jeton CSRF.
verify_csrf();