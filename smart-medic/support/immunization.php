<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$userId = (int) $_SESSION['user_id'];
$errors = [];
$successMessage = $_SESSION['immunization_flash'] ?? '';
unset($_SESSION['immunization_flash']);

if (empty($_SESSION['immunization_csrf'])) {
    $_SESSION['immunization_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['immunization_csrf'];

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

function ownedChild(mysqli $conn, int $childId, int $userId): ?array
{
    $query = $conn->prepare('SELECT id, child_name, date_of_birth, gender FROM children WHERE id = ? AND user_id = ? LIMIT 1');
    if (!$query) {
        return null;
    }
    $query->bind_param('ii', $childId, $userId);
    $query->execute();
    $child = $query->get_result()->fetch_assoc() ?: null;
    $query->close();
    return $child;
}

function ownedRecord(mysqli $conn, int $recordId, int $userId): ?array
{
    $query = $conn->prepare('SELECT i.id, i.child_id, i.vaccine_name, i.scheduled_date, i.administered_date, i.status, i.notes FROM immunizations i INNER JOIN children c ON c.id = i.child_id WHERE i.id = ? AND c.user_id = ? LIMIT 1');
    if (!$query) {
        return null;
    }
    $query->bind_param('ii', $recordId, $userId);
    $query->execute();
    $record = $query->get_result()->fetch_assoc() ?: null;
    $query->close();
    return $record;
}

function redirectToChild(int $childId): void
{
    header('Location: immunization.php?child_id=' . $childId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $childId = filter_var($_POST['child_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!hash_equals($csrfToken, $postedToken)) {
        $errors[] = 'Your session has expired. Refresh the page and try again.';
    } elseif ($action === 'add_child' || $action === 'edit_child') {
        $name = trim((string) ($_POST['child_name'] ?? ''));
        $dateOfBirth = trim((string) ($_POST['date_of_birth'] ?? ''));
        $gender = trim((string) ($_POST['gender'] ?? ''));

        if ($name === '' || strlen($name) > 200) {
            $errors[] = 'Enter the child\'s name using 200 characters or fewer.';
        }
        if (!validDate($dateOfBirth, false)) {
            $errors[] = 'Enter a valid date of birth that is not in the future.';
        }
        if (!in_array($gender, ['', 'female', 'male', 'other', 'prefer_not_to_say'], true)) {
            $errors[] = 'Select a valid gender option.';
        }

        if (!$errors && $action === 'add_child') {
            $insert = $conn->prepare("INSERT INTO children (user_id, child_name, date_of_birth, gender) VALUES (?, ?, ?, NULLIF(?, ''))");
            if ($insert) {
                $insert->bind_param('isss', $userId, $name, $dateOfBirth, $gender);
                if ($insert->execute()) {
                    $_SESSION['immunization_flash'] = 'Child added successfully.';
                    redirectToChild((int) $insert->insert_id);
                }
                $insert->close();
            }
            $errors[] = 'The child could not be saved. Please try again later.';
        }

        if (!$errors && $action === 'edit_child' && $childId) {
            if (!ownedChild($conn, (int) $childId, $userId)) {
                $errors[] = 'That child could not be found.';
            } else {
                $update = $conn->prepare("UPDATE children SET child_name = ?, date_of_birth = ?, gender = NULLIF(?, '') WHERE id = ? AND user_id = ?");
                if ($update) {
                    $update->bind_param('sssii', $name, $dateOfBirth, $gender, $childId, $userId);
                    if ($update->execute()) {
                        $_SESSION['immunization_flash'] = 'Child information updated.';
                        redirectToChild((int) $childId);
                    }
                    $update->close();
                }
                $errors[] = 'The child could not be updated. Please try again later.';
            }
        }
    } elseif (in_array($action, ['add_immunization', 'edit_immunization', 'delete_immunization'], true)) {
        if ($action === 'delete_immunization') {
            $recordId = filter_var($_POST['record_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $record = $recordId ? ownedRecord($conn, (int) $recordId, $userId) : null;
            if (!$record) {
                $errors[] = 'That immunization record could not be found.';
            } else {
                $delete = $conn->prepare('DELETE i FROM immunizations i INNER JOIN children c ON c.id = i.child_id WHERE i.id = ? AND c.user_id = ?');
                if ($delete) {
                    $delete->bind_param('ii', $recordId, $userId);
                    if ($delete->execute()) {
                        $_SESSION['immunization_flash'] = 'Immunization record deleted.';
                        redirectToChild((int) $record['child_id']);
                    }
                    $delete->close();
                }
                $errors[] = 'The immunization record could not be deleted. Please try again later.';
            }
        } else {
            $recordId = filter_var($_POST['record_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $vaccineName = trim((string) ($_POST['vaccine_name'] ?? ''));
            $scheduledDate = trim((string) ($_POST['scheduled_date'] ?? ''));
            $administeredDate = trim((string) ($_POST['administered_date'] ?? ''));
            $status = trim((string) ($_POST['status'] ?? 'scheduled'));
            $notes = trim((string) ($_POST['notes'] ?? ''));

            if (!$childId || !ownedChild($conn, (int) $childId, $userId)) {
                $errors[] = 'That child could not be found.';
            }
            if ($vaccineName === '' || strlen($vaccineName) > 150) {
                $errors[] = 'Enter a vaccine name using 150 characters or fewer.';
            }
            if ($scheduledDate !== '' && !validDate($scheduledDate)) {
                $errors[] = 'Enter a valid scheduled date.';
            }
            if ($administeredDate !== '' && !validDate($administeredDate, false)) {
                $errors[] = 'Enter a valid administered date that is not in the future.';
            }
            if (!in_array($status, ['scheduled', 'administered', 'missed'], true)) {
                $errors[] = 'Select a valid immunization status.';
            }
            if (strlen($notes) > 5000) {
                $errors[] = 'Notes must be 5,000 characters or fewer.';
            }

            if (!$errors && $action === 'add_immunization') {
                $insert = $conn->prepare("INSERT INTO immunizations (child_id, vaccine_name, scheduled_date, administered_date, status, notes) VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, NULLIF(?, ''))");
                if ($insert) {
                    $insert->bind_param('isssss', $childId, $vaccineName, $scheduledDate, $administeredDate, $status, $notes);
                    if ($insert->execute()) {
                        $_SESSION['immunization_flash'] = 'Immunization record added.';
                        redirectToChild((int) $childId);
                    }
                    $insert->close();
                }
                $errors[] = 'The immunization record could not be saved. Please try again later.';
            }

            if (!$errors && $action === 'edit_immunization' && $recordId) {
                if (!ownedRecord($conn, (int) $recordId, $userId)) {
                    $errors[] = 'That immunization record could not be found.';
                } else {
                    $update = $conn->prepare("UPDATE immunizations SET vaccine_name = ?, scheduled_date = NULLIF(?, ''), administered_date = NULLIF(?, ''), status = ?, notes = NULLIF(?, '') WHERE id = ? AND child_id = ?");
                    if ($update) {
                        $update->bind_param('sssssii', $vaccineName, $scheduledDate, $administeredDate, $status, $notes, $recordId, $childId);
                        if ($update->execute()) {
                            $_SESSION['immunization_flash'] = 'Immunization record updated.';
                            redirectToChild((int) $childId);
                        }
                        $update->close();
                    }
                    $errors[] = 'The immunization record could not be updated. Please try again later.';
                }
            }
        }
    } else {
        $errors[] = 'That action is not available.';
    }
}

$selectedChildId = filter_var($_GET['child_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$selectedChild = $selectedChildId ? ownedChild($conn, (int) $selectedChildId, $userId) : null;
$children = [];
$childrenQuery = $conn->prepare("SELECT c.id, c.child_name, c.date_of_birth, c.gender, COUNT(i.id) AS vaccination_count, SUM(i.status = 'administered') AS administered_count, MIN(CASE WHEN i.status = 'scheduled' AND i.scheduled_date >= CURDATE() THEN i.scheduled_date END) AS next_scheduled_date FROM children c LEFT JOIN immunizations i ON i.child_id = c.id WHERE c.user_id = ? GROUP BY c.id, c.child_name, c.date_of_birth, c.gender ORDER BY c.child_name ASC");
if ($childrenQuery) {
    $childrenQuery->bind_param('i', $userId);
    $childrenQuery->execute();
    $result = $childrenQuery->get_result();
    while ($row = $result->fetch_assoc()) {
        $children[] = $row;
    }
    $childrenQuery->close();
}

$records = [];
if ($selectedChild) {
    $recordsQuery = $conn->prepare('SELECT id, vaccine_name, scheduled_date, administered_date, status, notes FROM immunizations WHERE child_id = ? ORDER BY COALESCE(administered_date, scheduled_date, created_at) ASC, id ASC');
    if ($recordsQuery) {
        $selectedId = (int) $selectedChild['id'];
        $recordsQuery->bind_param('i', $selectedId);
        $recordsQuery->execute();
        $result = $recordsQuery->get_result();
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        $recordsQuery->close();
    }
}

$upcoming = [];
$upcomingQuery = $conn->prepare("SELECT c.child_name, i.vaccine_name, i.scheduled_date FROM immunizations i INNER JOIN children c ON c.id = i.child_id WHERE c.user_id = ? AND i.scheduled_date IS NOT NULL AND i.scheduled_date >= CURDATE() AND i.status = 'scheduled' ORDER BY i.scheduled_date ASC, c.child_name ASC");
if ($upcomingQuery) {
    $upcomingQuery->bind_param('i', $userId);
    $upcomingQuery->execute();
    $result = $upcomingQuery->get_result();
    while ($row = $result->fetch_assoc()) {
        $upcoming[] = $row;
    }
    $upcomingQuery->close();
}

function childAge(string $dateOfBirth): string
{
    try {
        return (string) (new DateTimeImmutable($dateOfBirth))->diff(new DateTimeImmutable('today'))->y . ' years';
    } catch (Exception $exception) {
        return 'Age unavailable';
    }
}

$currentInitial = strtoupper(substr((string) ($_SESSION['username'] ?? 'S'), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Immunization | Smart Medic</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="dashboard-body immunization-page">
    <header class="dashboard-header">
        <a class="brand-mark" href="../dashboard.php" aria-label="Smart Medic home"><span class="brand-symbol" aria-hidden="true">+</span><span>Smart Medic</span></a>
        <nav class="desktop-main-nav" aria-label="Main navigation"><a href="../dashboard.php">Home</a><a href="../community/community.php">Community</a><a class="is-active" href="support.php">Support</a><a href="../bookings/bookings.php">Bookings</a><a href="../account/account.php">Account</a></nav>
        <a class="header-avatar" href="../profile.php" aria-label="Open profile"><?= e($currentInitial) ?></a>
    </header>

    <main class="immunization-shell">
        <a class="back-link immunization-back-link" href="support.php">&larr; Back to Healthcare Support</a>
        <header class="immunization-header">
            <p class="eyebrow">Support module</p>
            <h1>Child Immunization Tracking</h1>
            <p>Keep your children\'s vaccination records and manually entered reminder dates organized in one place.</p>
        </header>
        <?php if ($successMessage !== ''): ?><div class="alert alert-success" role="status">
                <p><?= e($successMessage) ?></p>
            </div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-error" role="alert">
                <p>Please correct the following:</p>
                <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
            </div><?php endif; ?>
        <aside class="immunization-disclaimer" role="note"><strong>Record-keeping notice</strong>
            <p>This tracker is for record keeping and reminders only. Scheduled dates are entered by you and are not official medical recommendations. Please follow advice from a qualified healthcare professional.</p>
        </aside>

        <div class="immunization-layout">
            <section class="immunization-panel" aria-labelledby="children-title">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Your records</p>
                        <h2 id="children-title">Children</h2>
                    </div>
                </div>
                <?php if (!$children): ?><p class="empty-state">No children have been added yet.</p><?php endif; ?>
                <div class="children-list">
                    <?php foreach ($children as $child): ?><article class="child-card <?= $selectedChild && (int) $selectedChild['id'] === (int) $child['id'] ? 'is-selected' : '' ?>">
                            <div class="child-card-heading">
                                <div>
                                    <h3><?= e((string) $child['child_name']) ?></h3>
                                    <p><?= e(childAge((string) $child['date_of_birth'])) ?> · Born <?= e((string) $child['date_of_birth']) ?></p>
                                </div><span class="child-count"><?= (int) $child['vaccination_count'] ?> <?= (int) $child['vaccination_count'] === 1 ? 'record' : 'records' ?></span>
                            </div>
                            <dl class="child-facts">
                                <div>
                                    <dt>Gender</dt>
                                    <dd><?= e($child['gender'] ? ucwords(str_replace('_', ' ', (string) $child['gender'])) : 'Not provided') ?></dd>
                                </div>
                                <div>
                                    <dt>Status</dt>
                                    <dd><?= (int) $child['administered_count'] > 0 ? 'Vaccination history recorded' : 'No administered doses recorded' ?></dd>
                                </div>
                            </dl><?php if (!empty($child['next_scheduled_date'])): ?><p class="child-reminder">Next reminder: <?= e((string) $child['next_scheduled_date']) ?></p><?php endif; ?><div class="child-actions"><a class="button-secondary" href="immunization.php?child_id=<?= (int) $child['id'] ?>">View history</a>
                                <details>
                                    <summary>Edit child</summary>
                                    <form method="post" class="compact-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="edit_child"><input type="hidden" name="child_id" value="<?= (int) $child['id'] ?>"><label for="child-name-<?= (int) $child['id'] ?>">Name</label><input id="child-name-<?= (int) $child['id'] ?>" name="child_name" value="<?= e((string) $child['child_name']) ?>" maxlength="200" required><label for="child-dob-<?= (int) $child['id'] ?>">Date of birth</label><input type="date" id="child-dob-<?= (int) $child['id'] ?>" name="date_of_birth" value="<?= e((string) $child['date_of_birth']) ?>" required><label for="child-gender-<?= (int) $child['id'] ?>">Gender</label><select id="child-gender-<?= (int) $child['id'] ?>" name="gender">
                                            <option value="">Prefer not to say</option><?php foreach (['female' => 'Female', 'male' => 'Male', 'other' => 'Other', 'prefer_not_to_say' => 'Prefer not to say'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $child['gender'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                                        </select><button class="button-primary" type="submit">Save changes</button></form>
                                </details>
                            </div>
                        </article><?php endforeach; ?>
                </div>
            </section>
            <section class="immunization-panel" aria-labelledby="add-child-title">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Family profile</p>
                        <h2 id="add-child-title">Add a child</h2>
                    </div>
                </div>
                <form method="post" class="immunization-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="add_child"><label for="new-child-name">Child's name <span aria-hidden="true">*</span></label><input id="new-child-name" name="child_name" maxlength="200" required><label for="new-child-dob">Date of birth <span aria-hidden="true">*</span></label><input type="date" id="new-child-dob" name="date_of_birth" max="<?= e(date('Y-m-d')) ?>" required><label for="new-child-gender">Gender</label><select id="new-child-gender" name="gender">
                        <option value="">Prefer not to say</option>
                        <option value="female">Female</option>
                        <option value="male">Male</option>
                        <option value="other">Other</option>
                    </select><button class="button-primary" type="submit">Add child</button></form>
            </section>
        </div>

        <?php if ($selectedChild): ?><section class="immunization-panel immunization-record-panel" aria-labelledby="records-title">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Selected child</p>
                        <h2 id="records-title"><?= e((string) $selectedChild['child_name']) ?>'s immunization history</h2>
                    </div>
                </div>
                <form method="post" class="immunization-form record-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="add_immunization"><input type="hidden" name="child_id" value="<?= (int) $selectedChild['id'] ?>">
                    <h3>Add immunization record</h3>
                    <div class="record-form-grid">
                        <div><label for="vaccine-name">Vaccine name <span aria-hidden="true">*</span></label><input id="vaccine-name" name="vaccine_name" maxlength="150" required></div>
                        <div><label for="status">Status</label><select id="status" name="status">
                                <option value="scheduled">Scheduled / reminder</option>
                                <option value="administered">Administered</option>
                                <option value="missed">Missed</option>
                            </select></div>
                        <div><label for="scheduled-date">Scheduled date</label><input type="date" id="scheduled-date" name="scheduled_date"><small>Manual reminder date only.</small></div>
                        <div><label for="administered-date">Date given</label><input type="date" id="administered-date" name="administered_date" max="<?= e(date('Y-m-d')) ?>"></div>
                    </div><label for="notes">Notes</label><textarea id="notes" name="notes" maxlength="5000" rows="3"></textarea><button class="button-primary" type="submit">Add record</button>
                </form>
                <div class="history-table-wrap"><?php if ($records): ?><table class="history-table">
                            <thead>
                                <tr>
                                    <th>Vaccine</th>
                                    <th>Date given</th>
                                    <th>Scheduled date</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                    <th><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($records as $record): ?><tr>
                                        <td data-label="Vaccine"><?= e((string) $record['vaccine_name']) ?></td>
                                        <td data-label="Date given"><?= e((string) ($record['administered_date'] ?: '—')) ?></td>
                                        <td data-label="Scheduled date"><?= e((string) ($record['scheduled_date'] ?: '—')) ?></td>
                                        <td data-label="Status"><span class="status-badge status-<?= e((string) $record['status']) ?>"><?= e(ucfirst((string) $record['status'])) ?></span></td>
                                        <td data-label="Notes"><?= nl2br(e((string) ($record['notes'] ?: '—'))) ?></td>
                                        <td class="record-actions">
                                            <details>
                                                <summary>Edit</summary>
                                                <form method="post" class="compact-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="edit_immunization"><input type="hidden" name="record_id" value="<?= (int) $record['id'] ?>"><input type="hidden" name="child_id" value="<?= (int) $selectedChild['id'] ?>"><label for="edit-vaccine-<?= (int) $record['id'] ?>">Vaccine</label><input id="edit-vaccine-<?= (int) $record['id'] ?>" name="vaccine_name" value="<?= e((string) $record['vaccine_name']) ?>" maxlength="150" required><label for="edit-scheduled-<?= (int) $record['id'] ?>">Scheduled date</label><input type="date" id="edit-scheduled-<?= (int) $record['id'] ?>" name="scheduled_date" value="<?= e((string) ($record['scheduled_date'] ?: '')) ?>"><label for="edit-administered-<?= (int) $record['id'] ?>">Date given</label><input type="date" id="edit-administered-<?= (int) $record['id'] ?>" name="administered_date" value="<?= e((string) ($record['administered_date'] ?: '')) ?>"><label for="edit-status-<?= (int) $record['id'] ?>">Status</label><select id="edit-status-<?= (int) $record['id'] ?>" name="status"><?php foreach (['scheduled' => 'Scheduled / reminder', 'administered' => 'Administered', 'missed' => 'Missed'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $record['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><label for="edit-notes-<?= (int) $record['id'] ?>">Notes</label><textarea id="edit-notes-<?= (int) $record['id'] ?>" name="notes" maxlength="5000" rows="3"><?= e((string) ($record['notes'] ?: '')) ?></textarea><button class="button-primary" type="submit">Save changes</button></form>
                                            </details>
                                            <form method="post" onsubmit="return confirm('Delete this immunization record?');"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="delete_immunization"><input type="hidden" name="record_id" value="<?= (int) $record['id'] ?>"><button class="button-danger" type="submit">Delete</button></form>
                                        </td>
                                    </tr><?php endforeach; ?></tbody>
                        </table><?php else: ?><p class="empty-state">No immunization records have been recorded for this child.</p><?php endif; ?></div>
            </section><?php endif; ?>

        <section class="immunization-panel reminders-panel" aria-labelledby="reminders-title">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Manual reminders</p>
                    <h2 id="reminders-title">Upcoming Immunization</h2>
                </div>
            </div><?php if ($upcoming): ?><div class="reminder-list"><?php foreach ($upcoming as $reminder): ?><article class="reminder-item" data-scheduled-date="<?= e((string) $reminder['scheduled_date']) ?>">
                            <div>
                                <h3><?= e((string) $reminder['vaccine_name']) ?></h3>
                                <p><?= e((string) $reminder['child_name']) ?> · Scheduled date: <?= e((string) $reminder['scheduled_date']) ?></p>
                            </div><strong class="reminder-status">Scheduled</strong>
                        </article><?php endforeach; ?></div><?php else: ?><p class="empty-state">No upcoming manually scheduled immunizations.</p><?php endif; ?><p class="form-note">These reminders are based only on dates you enter. They are not official vaccination schedule recommendations.</p>
        </section>
    </main>

    <nav class="mobile-bottom-nav" aria-label="Main navigation"><a href="../dashboard.php"><span aria-hidden="true">⌂</span><small>Home</small></a><a href="../community/community.php"><span aria-hidden="true">◌</span><small>Community</small></a><a class="is-active" href="support.php"><span aria-hidden="true">✚</span><small>Support</small></a><a href="../bookings/bookings.php"><span aria-hidden="true">□</span><small>Bookings</small></a><a href="../account/account.php"><span aria-hidden="true">◎</span><small>Account</small></a></nav>
    <script>
        document.querySelectorAll('[data-scheduled-date]').forEach(function(item) {
            var date = new Date(item.getAttribute('data-scheduled-date') + 'T00:00:00');
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            var days = Math.round((date - today) / 86400000);
            var status = item.querySelector('.reminder-status');
            if (days === 0) status.textContent = 'Due today';
            if (days > 0) status.textContent = 'In ' + days + (days === 1 ? ' day' : ' days');
        });
    </script>
</body>

</html>