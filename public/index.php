<?php
require_once __DIR__ . '/../autoload.php';

use App\Utils\Session;
use App\Models\UserModel;
use App\Models\WorktimeModel;
use App\Models\PtoModel;
use App\ViewModels\WorktimeViewModel;

Session::start();

if (!Session::isAuthenticated()) {
    header('Location: login.php');
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
        header('Location: login.php');
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

// Build map of dates worked for quick lookup in calendar
$workedDatesMap = [];
foreach ($monthlyData['entries'] as $entry) {
    if (!empty($entry['end_time'])) {
        $workedDatesMap[$entry['date_date']] = true;
    }
}

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
<html lang="de" class="h-full bg-pink-950 text-pink-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zeiterfassung - Onepager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="min-h-full flex flex-col font-sans bg-gradient-to-br from-pink-950 via-rose-950 to-pink-900 text-pink-50">

    <!-- Header Navigation -->
    <header class="bg-pink-950/80 backdrop-blur border-b border-pink-800/50 sticky top-0 z-40 px-6 py-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="bg-gradient-to-tr from-pink-600 to-rose-500 p-2.5 rounded-xl shadow-lg shadow-pink-500/30">
                    <i class="fa-solid fa-clock-rotate-left text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="font-bold text-lg text-white leading-tight">Zeiterfassung</h1>
                    <p class="text-xs text-pink-300/80">Angemeldet als <span class="text-pink-100 font-medium">admin</span></p>
                </div>
            </div>

            <!-- Header Right Actions & Streak -->
            <div class="flex items-center gap-4">
                <!-- Streak Badge -->
                <div class="flex items-center gap-2 bg-gradient-to-r from-rose-500/20 to-pink-500/20 border border-pink-500/40 px-3.5 py-1.5 rounded-full shadow-inner">
                    <i class="fa-solid fa-fire text-rose-400 text-lg animate-pulse"></i>
                    <span class="font-bold text-pink-300 text-sm"><?= $streak ?> Tag<?= $streak === 1 ? '' : 'e' ?> Streak!</span>
                </div>

                <button onclick="document.getElementById('pwd-modal').classList.remove('hidden')"
                        class="bg-pink-900/60 hover:bg-pink-800/80 text-pink-200 hover:text-white px-3 py-1.5 rounded-lg text-sm border border-pink-700/50 transition flex items-center gap-1.5">
                    <i class="fa-solid fa-key text-xs"></i> Passwort
                </button>

                <form method="POST" action="index.php" class="inline">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 px-3 py-1.5 rounded-lg text-sm transition flex items-center gap-1.5">
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
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-xl text-sm flex items-center gap-3">
                <i class="fa-solid fa-circle-check text-base"></i>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-rose-500/10 border border-rose-500/30 text-rose-300 px-4 py-3 rounded-xl text-sm flex items-center gap-3">
                <i class="fa-solid fa-circle-exclamation text-base"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Top Center Clock In / Clock Out Cards -->
        <section class="max-w-xl mx-auto text-center">
            <div class="bg-pink-950/60 border border-pink-800/60 rounded-2xl p-6 shadow-2xl backdrop-blur">
                <p class="text-xs font-semibold uppercase tracking-wider text-pink-300/80 mb-1">Aktueller Status</p>

                <?php if ($isClockedIn): ?>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 mb-6">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        Eingestempelt seit <?= date('H:i', strtotime($activeClockIn['start_time'])) ?> Uhr
                    </div>
                <?php else: ?>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium bg-pink-900/40 border border-pink-800/50 text-pink-300/70 mb-6">
                        <span class="w-2 h-2 rounded-full bg-pink-500"></span>
                        Nicht eingestempelt
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="grid grid-cols-2 gap-4">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="clock_in">
                        <button type="submit" <?= $isClockedIn ? 'disabled' : '' ?>
                                class="w-full flex flex-col items-center justify-center p-5 rounded-xl transition transform active:scale-95 <?= $isClockedIn ? 'bg-pink-900/20 border border-pink-900 text-pink-700 cursor-not-allowed opacity-50' : 'bg-gradient-to-tr from-pink-600 to-rose-500 hover:from-pink-500 hover:to-rose-400 text-white shadow-lg shadow-pink-500/30 border border-pink-400/30' ?>">
                            <i class="fa-solid fa-play text-2xl mb-2"></i>
                            <span class="font-semibold text-sm">Einstempeln</span>
                            <span class="text-xs opacity-80 mt-1"><?= $isClockedIn ? 'Bereits aktiv' : 'Arbeitsbeginn' ?></span>
                        </button>
                    </form>

                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="clock_out">
                        <button type="submit" <?= !$isClockedIn ? 'disabled' : '' ?>
                                class="w-full flex flex-col items-center justify-center p-5 rounded-xl transition transform active:scale-95 <?= !$isClockedIn ? 'bg-pink-900/20 border border-pink-900 text-pink-700 cursor-not-allowed opacity-50' : 'bg-gradient-to-tr from-rose-700 to-pink-600 hover:from-rose-600 hover:to-pink-500 text-white shadow-lg shadow-rose-600/30 border border-rose-400/30' ?>">
                            <i class="fa-solid fa-stop text-2xl mb-2"></i>
                            <span class="font-semibold text-sm">Ausstempeln</span>
                            <span class="text-xs opacity-80 mt-1"><?= !$isClockedIn ? 'Inaktiv' : 'Jetzt Feierabend' ?></span>
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <!-- PTO / Urlaub Calendar Section -->
        <section class="bg-pink-950/60 border border-pink-800/60 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-umbrella-beach text-pink-400"></i> Urlaubsplanung & Streak-Kalender
                    </h2>
                    <p class="text-xs text-pink-300/80">Klicke auf einen Arbeitstag, um Urlaub einzutragen oder zu entfernen. 🔥 zeigt geleistete Arbeitstage.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="?month=<?= $prevMonth ?>" class="p-2 bg-pink-900/50 hover:bg-pink-800 rounded-lg text-pink-200 text-sm"><i class="fa-solid fa-chevron-left"></i></a>
                    <span class="text-sm font-semibold text-white px-2"><?= $monthNameGerman ?> <?= $year ?></span>
                    <a href="?month=<?= $nextMonth ?>" class="p-2 bg-pink-900/50 hover:bg-pink-800 rounded-lg text-pink-200 text-sm"><i class="fa-solid fa-chevron-right"></i></a>
                </div>
            </div>

            <!-- Mini Calendar Grid -->
            <div class="grid grid-cols-7 gap-2 text-center text-xs">
                <div class="font-semibold text-pink-300/70 py-1">Mo</div>
                <div class="font-semibold text-pink-300/70 py-1">Di</div>
                <div class="font-semibold text-pink-300/70 py-1">Mi</div>
                <div class="font-semibold text-pink-300/70 py-1">Do</div>
                <div class="font-semibold text-pink-300/70 py-1">Fr</div>
                <div class="font-semibold text-amber-300/70 py-1">Sa</div>
                <div class="font-semibold text-amber-300/70 py-1">So</div>

                <!-- Empty offset cells -->
                <?php for ($i = 1; $i < $firstDayOfWeek; $i++): ?>
                    <div class="p-3 bg-pink-950/30 rounded-lg"></div>
                <?php endfor; ?>

                <!-- Calendar Days -->
                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                    <?php
                        $dayStr = sprintf('%s-%02d', $selectedMonth, $day);
                        $dt = new DateTime($dayStr);
                        $dayOfWeek = (int)$dt->format('N');
                        $isWeekend = $dayOfWeek >= 6;
                        $isPto = isset($ptoData[$dayStr]);
                        $hasWorkedStreak = isset($workedDatesMap[$dayStr]);
                    ?>
                    <?php if ($isWeekend): ?>
                        <div class="p-3 bg-amber-950/20 border border-amber-800/30 rounded-lg text-amber-400/60 font-medium">
                            <?= $day ?>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="index.php?month=<?= $selectedMonth ?>">
                            <input type="hidden" name="action" value="toggle_pto">
                            <input type="hidden" name="pto_date" value="<?= $dayStr ?>">
                            <button type="submit"
                                    class="w-full p-3 rounded-lg border transition text-center flex flex-col items-center justify-center <?= $isPto ? 'bg-sky-500/20 border-sky-400/60 text-sky-200 font-bold' : ($hasWorkedStreak ? 'bg-rose-500/20 border-pink-500/60 text-pink-100 font-bold' : 'bg-pink-900/30 hover:bg-pink-800/40 border-pink-800/50 text-pink-200') ?>">
                                <span class="flex items-center gap-1">
                                    <?= $day ?>
                                    <?php if ($hasWorkedStreak): ?>
                                        <i class="fa-solid fa-fire text-rose-400 text-xs"></i>
                                    <?php endif; ?>
                                </span>
                                <?php if ($isPto): ?>
                                    <span class="block text-[9px] font-normal text-sky-300">Urlaub</span>
                                <?php endif; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </section>

        <!-- Worktime Table Section -->
        <section class="bg-pink-950/60 border border-pink-800/60 rounded-2xl p-6 shadow-xl space-y-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-pink-400"></i> Arbeitsstunden Übersicht
                    </h2>
                    <p class="text-xs text-pink-300/80">Monatsansicht deiner Arbeitszeiten.</p>
                </div>

                <!-- Month Filter -->
                <form method="GET" action="index.php" class="flex items-center gap-3">
                    <label for="month-input" class="text-xs font-semibold text-pink-300/80">Monat auswählen:</label>
                    <input type="month" id="month-input" name="month" value="<?= htmlspecialchars($selectedMonth) ?>" onchange="this.form.submit()"
                           class="bg-pink-950 border border-pink-700 text-pink-100 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-pink-500">
                </form>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-xl border border-pink-800/60">
                <table class="w-full text-left text-sm text-pink-200">
                    <thead class="bg-pink-950/80 text-xs font-semibold uppercase text-pink-300/80 border-b border-pink-800/60">
                        <tr>
                            <th class="py-3.5 px-4">Datum</th>
                            <th class="py-3.5 px-4">Arbeitsanfang</th>
                            <th class="py-3.5 px-4">Arbeitsende</th>
                            <th class="py-3.5 px-4 text-right">Geleistete Stunden</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-pink-800/40 bg-pink-950/20">
                        <?php if (empty($monthlyData['entries'])): ?>
                            <tr>
                                <td colspan="4" class="py-8 text-center text-pink-400/60 text-sm">
                                    Keine Arbeitszeiten für den ausgewählten Monat erfasst.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($monthlyData['entries'] as $entry): ?>
                                <tr class="hover:bg-pink-900/30 transition">
                                    <td class="py-3 px-4 font-medium text-pink-100"><?= htmlspecialchars($entry['formatted_date']) ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($entry['formatted_start']) ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($entry['formatted_end']) ?></td>
                                    <td class="py-3 px-4 text-right font-semibold text-pink-300"><?= htmlspecialchars($entry['hours_formatted']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Download CSV Button at Bottom -->
            <div class="pt-4 flex items-center justify-between border-t border-pink-800/60">
                <div class="text-xs text-pink-300/80">
                    Gesamtstunden im <?= $monthNameGerman ?> <?= $year ?>: <span class="text-white font-bold text-sm"><?= $monthlyData['total_hours_formatted'] ?></span>
                </div>
                <a href="export_csv.php?month=<?= htmlspecialchars($selectedMonth) ?>"
                   class="bg-pink-600 hover:bg-pink-500 text-white font-semibold px-4 py-2 rounded-xl text-sm shadow-lg shadow-pink-600/30 border border-pink-400/30 flex items-center gap-2 transition active:scale-95">
                    <i class="fa-solid fa-file-csv text-base"></i> CSV herunterladen
                </a>
            </div>
        </section>

    </main>

    <!-- Password Change Modal -->
    <div id="pwd-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
        <div class="bg-pink-950 border border-pink-800 rounded-2xl p-6 w-full max-w-md shadow-2xl space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-white text-lg">Passwort ändern</h3>
                <button onclick="document.getElementById('pwd-modal').classList.add('hidden')" class="text-pink-300 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form method="POST" action="index.php" class="space-y-4">
                <input type="hidden" name="action" value="change_password">
                <div>
                    <label class="block text-xs font-semibold text-pink-300 mb-1">Neues Passwort</label>
                    <input type="password" name="new_password" required class="w-full bg-pink-900 border border-pink-700 rounded-xl px-3 py-2 text-sm text-pink-100 focus:outline-none focus:border-pink-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-pink-300 mb-1">Passwort bestätigen</label>
                    <input type="password" name="confirm_password" required class="w-full bg-pink-900 border border-pink-700 rounded-xl px-3 py-2 text-sm text-pink-100 focus:outline-none focus:border-pink-500">
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('pwd-modal').classList.add('hidden')" class="px-4 py-2 bg-pink-900 hover:bg-pink-800 text-pink-200 rounded-xl text-sm">Abbrechen</button>
                    <button type="submit" class="px-4 py-2 bg-pink-600 hover:bg-pink-500 text-white font-semibold rounded-xl text-sm shadow-md">Speichern</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
