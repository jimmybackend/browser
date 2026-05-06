<?php

use Browser\Core\Auth;
use Browser\Core\Csrf;
use Browser\Core\Session;
use Browser\Core\View;

$appName = $_ENV['APP_NAME'] ?? 'Browser';
$user = Auth::user();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= View::e($title ?? $appName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Browser es una plataforma web independiente para búsqueda, correo, privacidad y marketing digital.">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>
<nav class="nav">
    <div class="container nav-inner">
        <a href="/" class="brand"><?= View::e($appName) ?></a>
        <div class="nav-links" aria-label="Menú principal">
            <a href="/">Inicio</a>
            <a href="/search">Buscar</a>
            <a href="/mail">Correo</a>
            <a href="/marketing">Marketing</a>
            <?php if ($user): ?>
                <a href="/dashboard">Dashboard</a>
                <form method="post" action="/logout" class="logout-form" style="display:inline">
                    <?= Csrf::field() ?>
                    <button class="button secondary" type="submit">Salir</button>
                </form>
            <?php else: ?>
                <a href="/login">Login</a>
                <a href="/register">Registro</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="container main-content">
    <?php if ($message = Session::flash('error')): ?>
        <div class="alert error"><?= View::e($message) ?></div>
    <?php endif; ?>

    <?php if ($message = Session::flash('success')): ?>
        <div class="alert success"><?= View::e($message) ?></div>
    <?php endif; ?>

    <?= $content ?>
</main>

<footer class="footer">
    <div class="container footer-content">
        <p><strong>Browser MVP</strong></p>
        <p>Licencia Apache 2.0</p>
        <p>Proyecto en fase inicial MVP</p>
    </div>
</footer>

<script src="/assets/js/app.js"></script>
</body>
</html>
