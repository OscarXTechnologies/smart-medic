<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$userId = (int) $_SESSION['user_id'];
$errors = [];
$successMessage = $_SESSION['maternal_flash'] ?? '';
unset($_SESSION['maternal_flash']);

if (empty($_SESSION['maternal_csrf'])) {
    $_SESSION['maternal_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['maternal_csrf'];

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function validDate(string $value, bool $allowFuture = true): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $valid = $date && $date->format('Y-m-d') === $value;
    return $valid && ($allowFuture || $value <= date('Y-m-d'));
}

function ownedVisit(mysqli $conn, int $visitId, int $userId): ?array
{
    $query = $conn->prepare('SELECT id, checkup_type, scheduled_date, completed_date, status, notes FROM health_checkups WHERE id = ? AND user_id = ? LIMIT 1');
    if (!$query) {
        return null;
    }
    $query->bind_param('ii', $visitId, $userId);
    $query->execute();
    $visit = $query->get_result()->fetch_assoc() ?: null;
    $query->close();
    return $visit;
}

function redirectPage(): void
{
    header('Location: maternal_health.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $recordId = filter_var($_POST['record_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!hash_equals($csrfToken, $postedToken)) {
        $errors[] = 'Your session has expired. Refresh the page and try again.';
    } elseif ($action === 'save_profile') {
        $pregnancyStartDate = trim((string) ($_POST['pregnancy_start_date'] ?? ''));
        $expectedDeliveryDate = trim((string) ($_POST['expected_delivery_date'] ?? ''));
        $currentWeekValue = trim((string) ($_POST['current_week'] ?? ''));
        $healthStatus = trim((string) ($_POST['health_status'] ?? 'active'));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $allowedStatuses = ['active', 'completed', 'paused', 'not_active'];

        if (!validDate($pregnancyStartDate, false)) {
            $errors[] = 'Enter a valid pregnancy start date that is not in the future.';
        }
        if ($expectedDeliveryDate !== '' && !validDate($expectedDeliveryDate)) {
            $errors[] = 'Enter a valid expected delivery date.';
        }
        if ($expectedDeliveryDate !== '' && $expectedDeliveryDate < $pregnancyStartDate) {
            $errors[] = 'Expected delivery date must be after the pregnancy start date.';
        }
        if ($currentWeekValue !== '' && (!ctype_digit($currentWeekValue) || (int) $currentWeekValue > 45)) {
            $errors[] = 'Current tracking week must be a whole number from 0 to 45.';
        }
        if (!in_array($healthStatus, $allowedStatuses, true)) {
            $errors[] = 'Select a valid pregnancy status.';
        }
        if (strlen($notes) > 5000) {
            $errors[] = 'Notes must be 5,000 characters or fewer.';
        }

        if (!$errors) {
            $currentWeek = $currentWeekValue === '' ? null : (int) $currentWeekValue;
            $existing = $conn->prepare('SELECT id FROM maternal_health WHERE user_id = ? ORDER BY id ASC LIMIT 1');
            $existingId = null;
            if ($existing) {
                $existing->bind_param('i', $userId);
                $existing->execute();
                $existingRow = $existing->get_result()->fetch_assoc();
                $existingId = $existingRow ? (int) $existingRow['id'] : null;
                $existing->close();
            }

            if ($existingId) {
                $update = $conn->prepare("UPDATE maternal_health SET pregnancy_start_date = ?, expected_delivery_date = NULLIF(?, ''), current_week = ?, health_status = ?, notes = NULLIF(?, '') WHERE id = ? AND user_id = ?");
                if ($update) {
                    $update->bind_param('ssissii', $pregnancyStartDate, $expectedDeliveryDate, $currentWeek, $healthStatus, $notes, $existingId, $userId);
                    if ($update->execute()) {
                        $_SESSION['maternal_flash'] = 'Maternal health information updated.';
                        redirectPage();
                    }
                    $update->close();
                }
            } else {
                $insert = $conn->prepare("INSERT INTO maternal_health (user_id, pregnancy_start_date, expected_delivery_date, current_week, health_status, notes) VALUES (?, ?, NULLIF(?, ''), ?, ?, NULLIF(?, ''))");
                if ($insert) {
                    $insert->bind_param('ississ', $userId, $pregnancyStartDate, $expectedDeliveryDate, $currentWeek, $healthStatus, $notes);
                    if ($insert->execute()) {
                        $_SESSION['maternal_flash'] = 'Maternal health record created.';
                        redirectPage();
                    }
                    $insert->close();
                }
            }
            $errors[] = 'The maternal health information could not be saved. Please try again later.';
        }
    } elseif ($action === 'delete_profile') {
        $delete = $conn->prepare('DELETE FROM maternal_health WHERE user_id = ?');
        if ($delete) {
            $delete->bind_param('i', $userId);
            if ($delete->execute()) {
                $_SESSION['maternal_flash'] = 'Maternal health record deleted.';
                redirectPage();
            }
            $delete->close();
        }
        $errors[] = 'The maternal health record could not be deleted. Please try again later.';
    } elseif (in_array($action, ['add_visit', 'edit_visit', 'delete_visit'], true)) {
        if ($action === 'delete_visit') {
            $visit = $recordId ? ownedVisit($conn, (int) $recordId, $userId) : null;
            if (!$visit) {
                $errors[] = 'That appointment could not be found.';
            } else {
                $delete = $conn->prepare('DELETE FROM health_checkups WHERE id = ? AND user_id = ?');
                if ($delete) {
                    $delete->bind_param('ii', $recordId, $userId);
                    if ($delete->execute()) {
                        $_SESSION['maternal_flash'] = 'Appointment deleted.';
                        redirectPage();
                    }
                    $delete->close();
                }
                $errors[] = 'The appointment could not be deleted. Please try again later.';
            }
        } else {
            $visitType = trim((string) ($_POST['checkup_type'] ?? ''));
            $scheduledDate = trim((string) ($_POST['scheduled_date'] ?? ''));
            $completedDate = trim((string) ($_POST['completed_date'] ?? ''));
            $status = trim((string) ($_POST['status'] ?? 'scheduled'));
            $notes = trim((string) ($_POST['visit_notes'] ?? ''));

            if ($visitType === '' || strlen($visitType) > 100) {
                $errors[] = 'Enter an appointment type using 100 characters or fewer.';
            }
            if ($scheduledDate !== '' && !validDate($scheduledDate)) {
                $errors[] = 'Enter a valid appointment date.';
            }
            if ($completedDate !== '' && !validDate($completedDate, false)) {
                $errors[] = 'Enter a valid completed date that is not in the future.';
            }
            if (!in_array($status, ['scheduled', 'completed', 'cancelled', 'missed'], true)) {
                $errors[] = 'Select a valid appointment status.';
            }
            if (strlen($notes) > 5000) {
                $errors[] = 'Appointment notes must be 5,000 characters or fewer.';
            }

            if (!$errors && $action === 'add_visit') {
                $insert = $conn->prepare("INSERT INTO health_checkups (user_id, checkup_type, scheduled_date, completed_date, status, notes) VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, NULLIF(?, ''))");
                if ($insert) {
                    $insert->bind_param('isssss', $userId, $visitType, $scheduledDate, $completedDate, $status, $notes);
                    if ($insert->execute()) {
                        $_SESSION['maternal_flash'] = 'Appointment added.';
                        redirectPage();
                    }
                    $insert->close();
                }
                $errors[] = 'The appointment could not be saved. Please try again later.';
            }

            if (!$errors && $action === 'edit_visit' && $recordId) {
                if (!ownedVisit($conn, (int) $recordId, $userId)) {
                    $errors[] = 'That appointment could not be found.';
                } else {
                    $update = $conn->prepare("UPDATE health_checkups SET checkup_type = ?, scheduled_date = NULLIF(?, ''), completed_date = NULLIF(?, ''), status = ?, notes = NULLIF(?, '') WHERE id = ? AND user_id = ?");
                    if ($update) {
                        $update->bind_param('sssssii', $visitType, $scheduledDate, $completedDate, $status, $notes, $recordId, $userId);
                        if ($update->execute()) {
                            $_SESSION['maternal_flash'] = 'Appointment updated.';
                            redirectPage();
                        }
                        $update->close();
                    }
                    $errors[] = 'The appointment could not be updated. Please try again later.';
                }
            }
        }
    } else {
        $errors[] = 'That action is not available.';
    }
}

$profile = null;
$profileQuery = $conn->prepare('SELECT id, pregnancy_start_date, expected_delivery_date, current_week, health_status, notes FROM maternal_health WHERE user_id = ? ORDER BY id ASC LIMIT 1');
if ($profileQuery) {
    $profileQuery->bind_param('i', $userId);
    $profileQuery->execute();
    $profile = $profileQuery->get_result()->fetch_assoc() ?: null;
    $profileQuery->close();
}

$visits = [];
$visitsQuery = $conn->prepare('SELECT id, checkup_type, scheduled_date, completed_date, status, notes FROM health_checkups WHERE user_id = ? ORDER BY COALESCE(scheduled_date, completed_date, created_at) ASC, id ASC');
if ($visitsQuery) {
    $visitsQuery->bind_param('i', $userId);
    $visitsQuery->execute();
    $result = $visitsQuery->get_result();
    while ($row = $result->fetch_assoc()) {
        $visits[] = $row;
    }
    $visitsQuery->close();
}

$upcoming = array_values(array_filter($visits, static function (array $visit): bool {
    return $visit['status'] === 'scheduled' && !empty($visit['scheduled_date']) && $visit['scheduled_date'] >= date('Y-m-d');
}));

$currentInitial = strtoupper(substr((string) ($_SESSION['username'] ?? 'S'), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maternal Health | Smart Medic</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="dashboard-body maternal-page">
    <header class="dashboard-header">
        <a class="brand-mark" href="../dashboard.php" aria-label="Smart Medic home"><span class="brand-symbol" aria-hidden="true">+</span><span>Smart Medic</span></a>
        <nav class="desktop-main-nav" aria-label="Main navigation"><a href="../dashboard.php">Home</a><a href="../community/community.php">Community</a><a class="is-active" href="support.php">Support</a><a href="../bookings/bookings.php">Bookings</a><a href="../account/account.php">Account</a></nav>
        <a class="header-avatar" href="../profile.php" aria-label="Open profile"><?= e($currentInitial) ?></a>
    </header>

    <main class="maternal-shell">
        <a class="back-link maternal-back-link" href="support.php">&larr; Back to Healthcare Support</a>
        <header class="maternal-header">
            <p class="eyebrow">Support module</p>
            <h1>Maternal Health Monitoring</h1>
            <p>Keep pregnancy information and manually entered appointment reminders together in a private tracking space.</p>
        </header>
        <?php if ($successMessage !== ''): ?><div class="alert alert-success" role="status">
                <p><?= e($successMessage) ?></p>
            </div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-error" role="alert">
                <p>Please correct the following:</p>
                <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
            </div><?php endif; ?>

        <aside class="maternal-notice" role="note"><strong>Health and safety notice</strong>
            <p>Smart Medic is a tracking and reminder platform. It does not replace professional antenatal or medical care and does not provide diagnosis or individualized treatment. For urgent concerns, contact a qualified healthcare professional or an appropriate local emergency service.</p>
        </aside>

        <div class="maternal-grid">
            <section class="maternal-panel" aria-labelledby="profile-title">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Private profile</p>
                        <h2 id="profile-title">Pregnancy tracking information</h2>
                    </div>
                </div>
                <form method="post" class="maternal-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="save_profile"><label for="pregnancy-start">Pregnancy start date <span aria-hidden="true">*</span></label><input type="date" id="pregnancy-start" name="pregnancy_start_date" value="<?= e((string) ($profile['pregnancy_start_date'] ?? '')) ?>" max="<?= e(date('Y-m-d')) ?>" required><label for="expected-delivery">Expected delivery date</label><input type="date" id="expected-delivery" name="expected_delivery_date" value="<?= e((string) ($profile['expected_delivery_date'] ?? '')) ?>"><label for="current-week">Current tracking week</label><input type="number" id="current-week" name="current_week" value="<?= e((string) ($profile['current_week'] ?? '')) ?>" min="0" max="45"><small>This is optional user-entered tracking information, not a clinical assessment.</small><label for="health-status">Pregnancy status</label><select id="health-status" name="health_status"><?php foreach (['active' => 'Active', 'completed' => 'Completed', 'paused' => 'Paused', 'not_active' => 'Not active'] as $value => $label): ?><option value="<?= e($value) ?>" <?= ($profile['health_status'] ?? 'active') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><label for="profile-notes">Notes</label><textarea id="profile-notes" name="notes" maxlength="5000" rows="4"><?= e((string) ($profile['notes'] ?? '')) ?></textarea><button class="button-primary" type="submit"><?= $profile ? 'Save changes' : 'Create tracking record' ?></button></form><?php if ($profile): ?><form method="post" class="delete-form" onsubmit="return confirm('Delete your maternal health tracking record?');"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="delete_profile"><button class="button-danger" type="submit">Delete tracking record</button></form><?php endif; ?>
            </section>

            <section class="maternal-panel maternal-summary" aria-labelledby="summary-title">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Overview</p>
                        <h2 id="summary-title">Pregnancy dashboard</h2>
                    </div>
                </div><?php if ($profile): ?><dl class="maternal-facts">
                        <div>
                            <dt>Status</dt>
                            <dd><?= e(ucwords(str_replace('_', ' ', (string) $profile['health_status']))) ?></dd>
                        </div>
                        <div>
                            <dt>Start date</dt>
                            <dd><?= e((string) $profile['pregnancy_start_date']) ?></dd>
                        </div>
                        <div>
                            <dt>Expected delivery</dt>
                            <dd><?= e((string) ($profile['expected_delivery_date'] ?: 'Not provided')) ?></dd>
                        </div>
                        <div>
                            <dt>Tracking week</dt>
                            <dd><?= e($profile['current_week'] !== null ? (string) $profile['current_week'] . ' weeks' : 'Not provided') ?></dd>
                        </div>
                        <div>
                            <dt>Recorded visits</dt>
                            <dd><?= count($visits) ?></dd>
                        </div>
                    </dl><?php if (!empty($profile['expected_delivery_date'])): ?><div class="due-date-card" data-due-date="<?= e((string) $profile['expected_delivery_date']) ?>"><strong>Estimated days remaining</strong><span class="days-remaining">Calculating...</span><small>This is a calendar estimate based on the date you entered.</small></div><?php endif; ?><?php else: ?><p class="empty-state">Create a tracking record to see your pregnancy dashboard.</p><?php endif; ?>
            </section>
        </div>

        <section class="maternal-panel visits-panel" aria-labelledby="visits-title">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Appointments and visits</p>
                    <h2 id="visits-title">Antenatal / health visits</h2>
                </div>
            </div>
            <form method="post" class="maternal-form visit-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="add_visit">
                <h3>Record an appointment</h3>
                <div class="visit-form-grid">
                    <div><label for="checkup-type">Type of visit <span aria-hidden="true">*</span></label><input type="text" id="checkup-type" name="checkup_type" maxlength="100" placeholder="Antenatal checkup" required></div>
                    <div><label for="visit-status">Status</label><select id="visit-status" name="status">
                            <option value="scheduled">Scheduled</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="missed">Missed</option>
                        </select></div>
                    <div><label for="scheduled-date">Appointment date</label><input type="date" id="scheduled-date" name="scheduled_date"></div>
                    <div><label for="completed-date">Completed date</label><input type="date" id="completed-date" name="completed_date" max="<?= e(date('Y-m-d')) ?>"></div>
                </div><label for="visit-notes">Notes</label><textarea id="visit-notes" name="visit_notes" maxlength="5000" rows="3"></textarea><button class="button-primary" type="submit">Add appointment</button>
            </form>
            <?php if (!$visits): ?><p class="empty-state">No appointments or visits have been recorded.</p><?php else: ?><div class="visit-list"><?php foreach ($visits as $visit): ?><article class="visit-card">
                            <div class="visit-card-heading">
                                <div>
                                    <h3><?= e((string) $visit['checkup_type']) ?></h3>
                                    <p><?= e((string) ($visit['scheduled_date'] ?: $visit['completed_date'] ?: 'Date not provided')) ?></p>
                                </div><span class="status-badge status-<?= e((string) $visit['status']) ?>"><?= e(ucfirst((string) $visit['status'])) ?></span>
                            </div><?php if (!empty($visit['completed_date'])): ?><p class="visit-detail">Completed: <?= e((string) $visit['completed_date']) ?></p><?php endif; ?><?php if (!empty($visit['notes'])): ?><p class="visit-detail"><?= nl2br(e((string) $visit['notes'])) ?></p><?php endif; ?><div class="visit-actions">
                                <details>
                                    <summary>Edit</summary>
                                    <form method="post" class="compact-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="edit_visit"><input type="hidden" name="record_id" value="<?= (int) $visit['id'] ?>"><label for="edit-type-<?= (int) $visit['id'] ?>">Type</label><input id="edit-type-<?= (int) $visit['id'] ?>" name="checkup_type" value="<?= e((string) $visit['checkup_type']) ?>" maxlength="100" required><label for="edit-scheduled-<?= (int) $visit['id'] ?>">Appointment date</label><input type="date" id="edit-scheduled-<?= (int) $visit['id'] ?>" name="scheduled_date" value="<?= e((string) ($visit['scheduled_date'] ?: '')) ?>"><label for="edit-completed-<?= (int) $visit['id'] ?>">Completed date</label><input type="date" id="edit-completed-<?= (int) $visit['id'] ?>" name="completed_date" value="<?= e((string) ($visit['completed_date'] ?: '')) ?>"><label for="edit-status-<?= (int) $visit['id'] ?>">Status</label><select id="edit-status-<?= (int) $visit['id'] ?>" name="status"><?php foreach (['scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'missed' => 'Missed'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $visit['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><label for="edit-notes-<?= (int) $visit['id'] ?>">Notes</label><textarea id="edit-notes-<?= (int) $visit['id'] ?>" name="visit_notes" maxlength="5000" rows="3"><?= e((string) ($visit['notes'] ?: '')) ?></textarea><button class="button-primary" type="submit">Save changes</button></form>
                                </details>
                                <form method="post" onsubmit="return confirm('Delete this appointment?');"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="delete_visit"><input type="hidden" name="record_id" value="<?= (int) $visit['id'] ?>"><button class="button-danger" type="submit">Delete</button></form>
                            </div>
                        </article><?php endforeach; ?></div><?php endif; ?>
        </section>

        <section class="maternal-panel reminders-panel" aria-labelledby="reminders-title">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Manual reminders</p>
                    <h2 id="reminders-title">Upcoming Appointment</h2>
                </div>
            </div><?php if ($upcoming): ?><div class="reminder-list"><?php foreach ($upcoming as $visit): ?><article class="reminder-item" data-appointment-date="<?= e((string) $visit['scheduled_date']) ?>">
                            <div>
                                <h3><?= e((string) $visit['checkup_type']) ?></h3>
                                <p>Date: <?= e((string) $visit['scheduled_date']) ?></p>
                            </div><strong class="reminder-status">Scheduled</strong>
                        </article><?php endforeach; ?></div><?php else: ?><p class="empty-state">No upcoming scheduled appointments.</p><?php endif; ?><p class="form-note">These reminders use dates entered by you and do not represent an official antenatal schedule.</p>
        </section>
    </main>

    <nav class="mobile-bottom-nav" aria-label="Main navigation"><a href="../dashboard.php"><span aria-hidden="true">⌂</span><small>Home</small></a><a href="../community/community.php"><span aria-hidden="true">◌</span><small>Community</small></a><a class="is-active" href="support.php"><span aria-hidden="true">✚</span><small>Support</small></a><a href="../bookings/bookings.php"><span aria-hidden="true">□</span><small>Bookings</small></a><a href="../account/account.php"><span aria-hidden="true">◎</span><small>Account</small></a></nav>
    <script>
        (function() {
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            document.querySelectorAll('[data-due-date]').forEach(function(card) {
                var dueDate = new Date(card.getAttribute('data-due-date') + 'T00:00:00');
                var days = Math.round((dueDate - today) / 86400000);
                card.querySelector('.days-remaining').textContent = days >= 0 ? days + (days === 1 ? ' day' : ' days') : 'Date has passed';
            });
            document.querySelectorAll('[data-appointment-date]').forEach(function(item) {
                var appointmentDate = new Date(item.getAttribute('data-appointment-date') + 'T00:00:00');
                var days = Math.round((appointmentDate - today) / 86400000);
                var status = item.querySelector('.reminder-status');
                status.textContent = days === 0 ? 'Today' : 'In ' + days + (days === 1 ? ' day' : ' days');
            });
        }());
    </script>
</body>

</html>