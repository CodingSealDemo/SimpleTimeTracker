<?php
require_once __DIR__ . '/../autoload.php';

use App\Utils\Session;
use App\Models\UserModel;

Session::start();

if (Session::isAuthenticated()) {
    header('Location: /index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (UserModel::verifyPassword('admin', $password)) {
        Session::login('admin');
        header('Location: /index.php');
        exit;
    } else {
        $error = 'Ungültiges Passwort. Bitte versuchen Sie es erneut.';
    }
}
?>
<!DOCTYPE html>
<html lang="de" class="h-full bg-slate-950 text-pink-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anmeldung - Zeiterfassung</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="min-h-full flex items-center justify-center p-4 bg-slate-950 text-pink-100 font-sans">
    <div class="w-full max-w-md bg-pink-950/70 border border-pink-800/70 rounded-2xl p-8 shadow-2xl backdrop-blur">
        <div class="text-center mb-8">
            <div class="inline-flex bg-gradient-to-tr from-pink-600 to-rose-500 p-4 rounded-2xl shadow-lg shadow-pink-500/30 mb-4">
                <i class="fa-solid fa-clock-rotate-left text-white text-3xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-white">Zeiterfassung</h1>
            <p class="text-sm text-pink-300/80 mt-1">Anmeldung für Ihren Arbeitszeit-Tracker</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm px-4 py-3 rounded-xl mb-6 flex items-center gap-3">
                <i class="fa-solid fa-circle-exclamation text-base"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="/login.php" class="space-y-6">
            <div>
                <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-pink-300/80 mb-2">Passwort</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-pink-400/60">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <input type="password" id="password" name="password" required autofocus
                           placeholder="Passwort eingeben (Standard: admin)"
                           class="w-full pl-10 pr-4 py-3 bg-pink-900/40 border border-pink-800/80 rounded-xl text-pink-100 placeholder-pink-400/50 focus:outline-none focus:border-pink-500 focus:ring-1 focus:ring-pink-500 text-sm transition">
                </div>
            </div>

            <button type="submit"
                    class="w-full py-3 px-4 bg-gradient-to-r from-pink-600 to-rose-600 hover:from-pink-500 hover:to-rose-500 text-white font-semibold rounded-xl shadow-lg shadow-pink-600/30 transition transform active:scale-95 text-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-right-to-bracket"></i> Anmelden
            </button>
        </form>
    </div>
</body>
</html>
