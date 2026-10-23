<?php
require_once __DIR__ . '/../autoload.php';

use App\Utils\Session;
use App\Models\UserModel;
use App\Models\WorktimeModel;
use App\Models\PtoModel;
use App\ViewModels\WorktimeViewModel;

Session::start();

if (!Session::isAuthenticated()) {
    header('Location: /login.php');
    exit;
}

$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'clock_in') {
        if (WorktimeModel::clockIn()) {
            $message = 'Erfolgreich eingestempelt!';
        } else {
            $error = 'Einstempeln nicht möglich. Sie sind bereits eingestempelt oder haben sich heute bereits ausgetempelt.';
        }
    } elseif ($action === 'clock_out') {
        if (WorktimeModel::clockOut()) {
            $message = 'Erfolgreich ausgestempelt! Feierabend!';
        } else {
            $error = 'Ausstempeln nicht möglich. Sie sind derzeit nicht eingestempelt.';
        }
    } elseif ($action === 'toggle_pto') {
        $date = $_POST['pto_date'] ?? '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            PtoModel::togglePtoDay($date);
            $message = 'Urlaubstag geändert.';
        }
    } elseif ($action === 'change_password') {
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (strlen($newPass) < 4) {
            $error = 'Das neue Passwort muss mindestens 4 Zeichen lang sein.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'Die Passwörter stimmen nicht überein.';
        } else {
            UserModel::updatePassword('admin', $newPass);
            $message = 'Passwort erfolgreich geändert!';
        }
    } elseif ($action === 'logout') {
        Session::logout();
        header('Location: /login.php');
        exit;
    }
}

$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$vm = new WorktimeViewModel($selectedMonth);
$isClockedIn = $vm->isClockedIn();
$activeClockIn = $vm->getActiveClockIn();
$streak = $vm->getCurrentStreak();
$monthlyData = $vm->getMonthlyRecords();
$ptoData = $vm->getPtoMonthData();

// Calendar calculations for PTO section
$dtMonth = new DateTime($selectedMonth . '-01');
$monthNameGerman = [
    '01' => 'Januar', '02' => 'Februar', '03' => 'März', '04' => 'April',
    '05' => 'Mai', '06' => 'Juni', '07' => 'Juli', '08' => 'August',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Dezember'
][$dtMonth->format('m')];

$year = $dtMonth->format('Y');
$daysInMonth = (int)$dtMonth->format('t');
$firstDayOfWeek = (int)$dtMonth->format('N'); // 1 = Mo, 7 = So

$prevMonth = (clone $dtMonth)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $dtMonth)->modify('+1 month')->format('Y-m');
?>
<!DOCTYPE html>
<html lang="de" class="h-full bg-slate-900 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zeiterfassung - Onepager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="min-h-full flex flex-col font-sans bg-slate-900 text-slate-100">

    <!-- Header Navigation -->
    <header class="bg-slate-800/80 backdrop-blur border-b border-slate-700/60 sticky top-0 z-40 px-6 py-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="bg-gradient-to-tr from-blue-600 to-indigo-500 p-2.5 rounded-xl shadow-lg shadow-indigo-500/20">
                    <i class="fa-solid fa-clock-rotate-left text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="font-bold text-lg text-white leading-tight">Zeiterfassung</h1>
                    <p class="text-xs text-slate-400">Angemeldet als <span class="text-slate-200 font-medium">admin</span></p>
                </div>
            </div>

            <!-- Header Right Actions & Streak -->
            <div class="flex items-center gap-4">
                <!-- Streak Badge -->
                <div class="flex items-center gap-2 bg-gradient-to-r from-amber-500/10 to-orange-500/10 border border-orange-500/30 px-3.5 py-1.5 rounded-full shadow-inner">
                    <i class="fa-solid fa-fire text-amber-500 text-lg animate-pulse"></i>
                    <span class="font-bold text-orange-400 text-sm"><?= $streak ?> Tag<?= $streak === 1 ? '' : 'e' ?> Streak!</span>
                </div>

                <button onclick="document.getElementById('pwd-modal').classList.remove('hidden')"
                        class="bg-slate-700/60 hover:bg-slate-700 text-slate-300 hover:text-white px-3 py-1.5 rounded-lg text-sm border border-slate-600/50 transition flex items-center gap-1.5">
                    <i class="fa-solid fa-key text-xs"></i> Passwort
                </button>

                <form method="POST" action="/index.php" class="inline">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/30 px-3 py-1.5 rounded-lg text-sm transition flex items-center gap-1.5">
                        <i class="fa-solid fa-right-from-bracket"></i> Abmelden
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-6 space-y-8">

        <!-- Alerts -->
        <?php if ($message): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-4 py-3 rounded-xl text-sm flex items-center gap-3">
                <i class="fa-solid fa-circle-check text-base"></i>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-xl text-sm flex items-center gap-3">
                <i class="fa-solid fa-circle-exclamation text-base"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Top Center Clock In / Clock Out Cards -->
        <section class="max-w-xl mx-auto text-center">
            <div class="bg-slate-800/90 border border-slate-700/80 rounded-2xl p-6 shadow-2xl backdrop-blur">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Aktueller Status</p>

                <?php if ($isClockedIn): ?>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 mb-6">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        Eingestempelt seit <?= date('H:i', strtotime($activeClockIn['start_time'])) ?> Uhr
                    </div>
                <?php else: ?>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium bg-slate-700/60 border border-slate-600/50 text-slate-400 mb-6">
                        <span class="w-2 h-2 rounded-full bg-slate-500"></span>
                        Nicht eingestempelt
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="grid grid-cols-2 gap-4">
                    <form method="POST" action="/index.php">
                        <input type="hidden" name="action" value="clock_in">
                        <button type="submit" <?= $isClockedIn ? 'disabled' : '' ?>
                                class="w-full flex flex-col items-center justify-center p-5 rounded-xl transition transform active:scale-95 <?= $isClockedIn ? 'bg-slate-700/30 border border-slate-700 text-slate-500 cursor-not-allowed opacity-50' : 'bg-gradient-to-tr from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white shadow-lg shadow-emerald-500/25 border border-emerald-400/30' ?>">
                            <i class="fa-solid fa-play text-2xl mb-2"></i>
                            <span class="font-semibold text-sm">Einstempeln</span>
                            <span class="text-xs opacity-80 mt-1"><?= $isClockedIn ? 'Bereits aktiv' : 'Arbeitsbeginn' ?></span>
                        </button>
                    </form>

                    <form method="POST" action="/index.php">
                        <input type="hidden" name="action" value="clock_out">
                        <button type="submit" <?= !$isClockedIn ? 'disabled' : '' ?>
                                class="w-full flex flex-col items-center justify-center p-5 rounded-xl transition transform active:scale-95 <?= !$isClockedIn ? 'bg-slate-700/30 border border-slate-700 text-slate-500 cursor-not-allowed opacity-50' : 'bg-gradient-to-tr from-rose-600 to-red-500 hover:from-rose-500 hover:to-red-400 text-white shadow-lg shadow-rose-500/25 border border-rose-400/30' ?>">
                            <i class="fa-solid fa-stop text-2xl mb-2"></i>
                            <span class="font-semibold text-sm">Ausstempeln</span>
                            <span class="text-xs opacity-80 mt-1"><?= !$isClockedIn ? 'Inaktiv' : 'Jetzt Feierabend' ?></span>
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <!-- PTO / Urlaub Calendar Section -->
        <section class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-umbrella-beach text-sky-400"></i> Urlaubsplanung (PTO)
                    </h2>
                    <p class="text-xs text-slate-400">Klicke auf einen Arbeitstag, um Urlaub einzutragen oder zu entfernen.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="?month=<?= $prevMonth ?>" class="p-2 bg-slate-700/50 hover:bg-slate-700 rounded-lg text-slate-300 text-sm"><i class="fa-solid fa-chevron-left"></i></a>
                    <span class="text-sm font-semibold text-white px-2"><?= $monthNameGerman ?> <?= $year ?></span>
                    <a href="?month=<?= $nextMonth ?>" class="p-2 bg-slate-700/50 hover:bg-slate-700 rounded-lg text-slate-300 text-sm"><i class="fa-solid fa-chevron-right"></i></a>
                </div>
            </div>

            <!-- Mini Calendar Grid -->
            <div class="grid grid-cols-7 gap-2 text-center text-xs">
                <div class="font-semibold text-slate-400 py-1">Mo</div>
                <div class="font-semibold text-slate-400 py-1">Di</div>
                <div class="font-semibold text-slate-400 py-1">Mi</div>
                <div class="font-semibold text-slate-400 py-1">Do</div>
                <div class="font-semibold text-slate-400 py-1">Fr</div>
                <div class="font-semibold text-amber-400/80 py-1">Sa</div>
                <div class="font-semibold text-amber-400/80 py-1">So</div>

                <!-- Empty offset cells -->
                <?php for ($i = 1; $i < $firstDayOfWeek; $i++): ?>
                    <div class="p-3 bg-slate-900/30 rounded-lg"></div>
                <?php endfor; ?>

                <!-- Calendar Days -->
                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                    <?php
                        $dayStr = sprintf('%s-%02d', $selectedMonth, $day);
                        $dt = new DateTime($dayStr);
                        $dayOfWeek = (int)$dt->format('N');
                        $isWeekend = $dayOfWeek >= 6;
                        $isPto = isset($ptoData[$dayStr]);
                    ?>
                    <?php if ($isWeekend): ?>
                        <div class="p-3 bg-amber-950/20 border border-amber-900/30 rounded-lg text-amber-500/60 font-medium">
                            <?= $day ?>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="/index.php?month=<?= $selectedMonth ?>">
                            <input type="hidden" name="action" value="toggle_pto">
                            <input type="hidden" name="pto_date" value="<?= $dayStr ?>">
                            <button type="submit"
                                    class="w-full p-3 rounded-lg border transition text-center <?= $isPto ? 'bg-sky-500/20 border-sky-500/60 text-sky-300 font-bold' : 'bg-slate-700/40 hover:bg-sky-500/20 hover:border-sky-500/50 border-slate-700 text-slate-200' ?>">
                                <?= $day ?>
                                <?php if ($isPto): ?>
                                    <span class="block text-[9px] font-normal text-sky-400">Urlaub</span>
                                <?php endif; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </section>

        <!-- Worktime Table Section -->
        <section class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-6 shadow-xl space-y-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-indigo-400"></i> Arbeitsstunden Übersicht
                    </h2>
                    <p class="text-xs text-slate-400">Monatsansicht deiner Arbeitszeiten.</p>
                </div>

                <!-- Month Filter -->
                <form method="GET" action="/index.php" class="flex items-center gap-3">
                    <label for="month-input" class="text-xs font-semibold text-slate-400">Monat auswählen:</label>
                    <input type="month" id="month-input" name="month" value="<?= htmlspecialchars($selectedMonth) ?>" onchange="this.form.submit()"
                           class="bg-slate-900 border border-slate-700 text-slate-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500">
                </form>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-xl border border-slate-700/70">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900/80 text-xs font-semibold uppercase text-slate-400 border-b border-slate-700/70">
                        <tr>
                            <th class="py-3.5 px-4">Datum</th>
                            <th class="py-3.5 px-4">Arbeitsanfang</th>
                            <th class="py-3.5 px-4">Arbeitsende</th>
                            <th class="py-3.5 px-4 text-right">Geleistete Stunden</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50 bg-slate-800/40">
                        <?php if (empty($monthlyData['entries'])): ?>
                            <tr>
                                <td colspan="4" class="py-8 text-center text-slate-500 text-sm">
                                    Keine Arbeitszeiten für den ausgewählten Monat erfasst.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($monthlyData['entries'] as $entry): ?>
                                <tr class="hover:bg-slate-700/30 transition">
                                    <td class="py-3 px-4 font-medium text-slate-200"><?= htmlspecialchars($entry['formatted_date']) ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($entry['formatted_start']) ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($entry['formatted_end']) ?></td>
                                    <td class="py-3 px-4 text-right font-semibold text-indigo-300"><?= htmlspecialchars($entry['hours_formatted']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Download CSV Button at Bottom -->
            <div class="pt-4 flex items-center justify-between border-t border-slate-700/60">
                <div class="text-xs text-slate-400">
                    Gesamtstunden im <?= $monthNameGerman ?> <?= $year ?>: <span class="text-white font-bold text-sm"><?= $monthlyData['total_hours_formatted'] ?></span>
                </div>
                <a href="/export_csv.php?month=<?= htmlspecialchars($selectedMonth) ?>"
                   class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-4 py-2 rounded-xl text-sm shadow-lg shadow-emerald-600/20 border border-emerald-500/30 flex items-center gap-2 transition active:scale-95">
                    <i class="fa-solid fa-file-csv text-base"></i> CSV herunterladen
                </a>
            </div>
        </section>

    </main>

    <!-- Password Change Modal -->
    <div id="pwd-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6 w-full max-w-md shadow-2xl space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-white text-lg">Passwort ändern</h3>
                <button onclick="document.getElementById('pwd-modal').classList.add('hidden')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form method="POST" action="/index.php" class="space-y-4">
                <input type="hidden" name="action" value="change_password">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Neues Passwort</label>
                    <input type="password" name="new_password" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Passwort bestätigen</label>
                    <input type="password" name="confirm_password" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('pwd-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-xl text-sm">Abbrechen</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl text-sm shadow-md">Speichern</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
