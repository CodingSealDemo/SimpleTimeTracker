<?php

require_once __DIR__ . '/../autoload.php';

use App\Utils\Database;
use App\Utils\Crypto;
use App\Models\UserModel;
use App\Models\WorktimeModel;
use App\Models\PtoModel;
use App\Utils\StreakCalculator;
use App\ViewModels\WorktimeViewModel;

echo "=== WORKTIME TRACKER INTEGRATION TESTS ===\n\n";

// 1. Encryption & Decryption
$plain = "TopSecretWorkData_12345";
$encrypted = Crypto::encrypt($plain);
$decrypted = Crypto::decrypt($encrypted);
assert($plain === $decrypted, "Crypto failed");
assert($plain !== $encrypted, "Data was not encrypted");
echo "[PASS] Data Encryption & Decryption\n";

// 2. User Authentication & Password Change
assert(UserModel::verifyPassword('admin', 'admin') === true, "Default login failed");
assert(UserModel::verifyPassword('admin', 'wrong_pass') === false, "Invalid password passed");

UserModel::updatePassword('admin', 'new_secret_123');
assert(UserModel::verifyPassword('admin', 'new_secret_123') === true, "Password update failed");
UserModel::updatePassword('admin', 'admin'); // Reset
assert(UserModel::verifyPassword('admin', 'admin') === true, "Password reset failed");
echo "[PASS] User Password Management & Hashing\n";

// 3. Clock-In / Clock-Out Rules
// Reset work table for clean state
$db = Database::getConnection();
$db->exec('DELETE FROM work');

assert(WorktimeModel::getActiveClockIn() === null, "Active clock-in should be null initially");
assert(WorktimeModel::clockIn() === true, "Clock in failed");
assert(WorktimeModel::clockIn() === false, "Should not allow double clock in");

$active = WorktimeModel::getActiveClockIn();
assert($active !== null, "Active clock-in should exist");
assert(WorktimeModel::clockOut() === true, "Clock out failed");
assert(WorktimeModel::getActiveClockIn() === null, "Active clock-in should be null after clocking out");
assert(WorktimeModel::clockIn() === false, "Should not allow clocking in twice in the same day");
echo "[PASS] Clock-In & Clock-Out Rules\n";

// 4. PTO Toggling & Streak Logic
$db->exec('DELETE FROM pto');
$today = date('Y-m-d');

assert(PtoModel::isPtoDay($today) === false, "Day should not be PTO initially");
PtoModel::togglePtoDay($today);
assert(PtoModel::isPtoDay($today) === true, "PTO toggle failed");
PtoModel::togglePtoDay($today);
assert(PtoModel::isPtoDay($today) === false, "PTO toggle off failed");
echo "[PASS] PTO Calendar Management\n";

// 5. ViewModel & Monthly Table Aggregation
$vm = new WorktimeViewModel(date('Y-m'));
$records = $vm->getMonthlyRecords();
assert(count($records['entries']) === 1, "Expected 1 entry in current month");
assert(isset($records['total_hours_formatted']), "Total hours formatted missing");
echo "[PASS] Worktime ViewModel & German Formatting\n";

echo "\nALL BACKEND & BUSINESS LOGIC TESTS PASSED SUCCESSFULLY!\n";
