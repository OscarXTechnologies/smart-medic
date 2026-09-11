<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$userId = (int) $_SESSION['user_id'];
$errors = [];
$successMessage = $_SESSION['telemedicine_flash'] ?? '';
unset($_SESSION['telemedicine_flash']);

if (empty($_SESSION['telemedicine_csrf'])) {
    $_SESSION['telemedicine_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['telemedicine_csrf'];

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function validDateTimeLocal(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value);
    return $date && $date->format('Y-m-d\\TH:i') === $value;
}

function consultationLabel(array $consultation): string
{
    $name = trim((string) $consultation['first_name'] . ' ' . (string) $consultation['last_name']);
    return $name !== '' ? $name : (string) $consultation['username'];
}

function consultationStatusLabel(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function addConsultationNotification(mysqli $conn, int $recipientId, int $consultationId, string $message): void
{
    $type = 'consultation';
    $duplicateCheck = $conn->prepare('SELECT id FROM notifications WHERE user_id = ? AND type = ? AND reference_id = ? AND message = ? LIMIT 1');
    if (!$duplicateCheck) {
        return;
    }
    $duplicateCheck->bind_param('isis', $recipientId, $type, $consultationId, $message);
    $duplicateCheck->execute();
    $exists = $duplicateCheck->get_result()->num_rows > 0;
    $duplicateCheck->close();

    if (!$exists) {
        $insert = $conn->prepare('INSERT INTO notifications (user_id, type, reference_id, message) VALUES (?, ?, ?, ?)');
        if ($insert) {
            $insert->bind_param('isis', $recipientId, $type, $consultationId, $message);
            $insert->execute();
            $insert->close();
        }
    }
}

function redirectToConsultation(int $consultationId = 0): void
{
    header('Location: telemedicine.php' . ($consultationId > 0 ? '?consultation_id=' . $consultationId : ''));
    exit;
}

$currentUser = null;
$userQuery = $conn->prepare('SELECT id, username, first_name, last_name, role FROM users WHERE id = ? LIMIT 1');
if ($userQuery) {
    $userQuery->bind_param('i', $userId);
    $userQuery->execute();
    $currentUser = $userQuery->get_result()->fetch_assoc() ?: null;
    $userQuery->close();
}
$currentRole = (string) ($currentUser['role'] ?? 'patient');
$isProfessional = in_array($currentRole, ['doctor', 'health_worker'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $consultationId = filter_var($_POST['consultation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!hash_equals($csrfToken, $postedToken)) {
        $errors[] = 'Your session has expired. Refresh the page and try again.';
    } elseif ($action === 'request_consultation') {
        $doctorId = filter_var($_POST['doctor_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $consultationType = trim((string) ($_POST['consultation_type'] ?? ''));
        $appointmentDate = trim((string) ($_POST['appointment_date'] ?? ''));
        $symptoms = trim((string) ($_POST['symptoms'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if (!$doctorId) {
            $errors[] = 'Select an available healthcare professional.';
        }
        if ($consultationType === '' || strlen($consultationType) > 100) {
            $errors[] = 'Enter a consultation reason using 100 characters or fewer.';
        }
        if (!validDateTimeLocal($appointmentDate)) {
            $errors[] = 'Enter a valid preferred date and time.';
        } elseif ($appointmentDate < date('Y-m-d\\TH:i')) {
            $errors[] = 'Preferred date and time must be in the future.';
        }
        if (strlen($symptoms) > 5000 || strlen($notes) > 5000) {
            $errors[] = 'Consultation details must be 5,000 characters or fewer.';
        }

        $professional = null;
        if (!$errors) {
            $professionalQuery = $conn->prepare("SELECT id, first_name, last_name, username FROM users WHERE id = ? AND role IN ('doctor', 'health_worker') LIMIT 1");
            if ($professionalQuery) {
                $professionalQuery->bind_param('i', $doctorId);
                $professionalQuery->execute();
                $professional = $professionalQuery->get_result()->fetch_assoc() ?: null;
                $professionalQuery->close();
            }
            if (!$professional) {
                $errors[] = 'That healthcare professional is not available.';
            }
        }

        if (!$errors) {
            $appointmentForDb = str_replace('T', ' ', $appointmentDate) . ':00';
            $status = 'scheduled';
            $insert = $conn->prepare('INSERT INTO consultations (patient_id, doctor_id, consultation_type, appointment_date, status, symptoms, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
            if ($insert) {
                $insert->bind_param('iisssss', $userId, $doctorId, $consultationType, $appointmentForDb, $status, $symptoms, $notes);
                if ($insert->execute()) {
                    $newConsultationId = (int) $insert->insert_id;
                    $patientName = trim((string) ($currentUser['first_name'] ?? '') . ' ' . (string) ($currentUser['last_name'] ?? ''));
                    if ($patientName === '') {
                        $patientName = (string) ($currentUser['username'] ?? 'A Smart Medic user');
                    }
                    if ((int) $professional['id'] !== $userId) {
                        addConsultationNotification($conn, (int) $professional['id'], $newConsultationId, $patientName . ' requested a telemedicine consultation.');
                    }
                    $_SESSION['telemedicine_flash'] = 'Consultation request submitted.';
                    redirectToConsultation($newConsultationId);
                }
                $insert->close();
            }
            $errors[] = 'The consultation request could not be submitted. Please try again later.';
        }
    } elseif ($action === 'cancel_consultation') {
        $consultation = null;
        if ($consultationId) {
            $consultationQuery = $conn->prepare('SELECT id, doctor_id, appointment_date, status FROM consultations WHERE id = ? AND patient_id = ? LIMIT 1');
            if ($consultationQuery) {
                $consultationQuery->bind_param('ii', $consultationId, $userId);
                $consultationQuery->execute();
                $consultation = $consultationQuery->get_result()->fetch_assoc() ?: null;
                $consultationQuery->close();
            }
        }
        if (!$consultation) {
            $errors[] = 'That consultation could not be found.';
        } elseif (!in_array($consultation['status'], ['scheduled', 'pending', 'approved'], true) || strtotime((string) $consultation['appointment_date']) < time()) {
            $errors[] = 'This consultation is no longer available for cancellation.';
        } else {
            $cancel = $conn->prepare("UPDATE consultations SET status = 'cancelled' WHERE id = ? AND patient_id = ? AND status IN ('scheduled', 'pending', 'approved') AND appointment_date >= NOW()");
            if ($cancel) {
                $cancel->bind_param('ii', $consultationId, $userId);
                if ($cancel->execute() && $cancel->affected_rows === 1) {
                    if ((int) $consultation['doctor_id'] !== $userId) {
                        addConsultationNotification($conn, (int) $consultation['doctor_id'], (int) $consultationId, 'A patient cancelled a telemedicine consultation.');
                    }
                    $_SESSION['telemedicine_flash'] = 'Consultation cancelled.';
                    redirectToConsultation((int) $consultationId);
                }
                $cancel->close();
            }
            $errors[] = 'The consultation could not be cancelled. Please try again later.';
        }
    } elseif ($action === 'update_status' && $isProfessional) {
        $newStatus = trim((string) ($_POST['status'] ?? ''));
        if (!$consultationId || !in_array($newStatus, ['scheduled', 'approved', 'completed', 'cancelled'], true)) {
            $errors[] = 'Select a valid consultation status.';
        } else {
            $consultationQuery = $conn->prepare('SELECT id, patient_id, status FROM consultations WHERE id = ? AND doctor_id = ? LIMIT 1');
            $consultation = null;
            if ($consultationQuery) {
                $consultationQuery->bind_param('ii', $consultationId, $userId);
                $consultationQuery->execute();
                $consultation = $consultationQuery->get_result()->fetch_assoc() ?: null;
                $consultationQuery->close();
            }
            if (!$consultation) {
                $errors[] = 'That assigned consultation could not be found.';
            } else {
                $update = $conn->prepare('UPDATE consultations SET status = ? WHERE id = ? AND doctor_id = ?');
                if ($update) {
                    $update->bind_param('sii', $newStatus, $consultationId, $userId);
                    if ($update->execute()) {
                        if ((int) $consultation['patient_id'] !== $userId) {
                            addConsultationNotification($conn, (int) $consultation['patient_id'], (int) $consultationId, 'Your telemedicine consultation status changed to ' . consultationStatusLabel($newStatus) . '.');
                        }
                        $_SESSION['telemedicine_flash'] = 'Consultation status updated.';
                        redirectToConsultation((int) $consultationId);
                    }
                    $update->close();
                }
                $errors[] = 'The consultation status could not be updated. Please try again later.';
            }
        }
    } elseif ($action === 'update_status') {
        $errors[] = 'Only the assigned healthcare professional can update consultation status.';
    } else {
        $errors[] = 'That action is not available.';
    }
}

$professionals = [];
$professionalList = $conn->prepare("SELECT id, first_name, last_name, username, role FROM users WHERE role IN ('doctor', 'health_worker') ORDER BY role, first_name, last_name, username");
if ($professionalList) {
    $professionalList->execute();
    $result = $professionalList->get_result();
    while ($row = $result->fetch_assoc()) {
        $professionals[] = $row;
    }
    $professionalList->close();
}

$consultations = [];
if ($isProfessional) {
    $listQuery = $conn->prepare('SELECT c.id, c.patient_id, c.doctor_id, c.consultation_type, c.appointment_date, c.status, c.symptoms, c.notes, c.created_at, p.first_name AS patient_first_name, p.last_name AS patient_last_name, p.username AS patient_username, d.first_name, d.last_name, d.username FROM consultations c INNER JOIN users p ON p.id = c.patient_id INNER JOIN users d ON d.id = c.doctor_id WHERE c.doctor_id = ? ORDER BY c.appointment_date ASC, c.id DESC');
    $listParam = $userId;
} else {
    $listQuery = $conn->prepare('SELECT c.id, c.patient_id, c.doctor_id, c.consultation_type, c.appointment_date, c.status, c.symptoms, c.notes, c.created_at, p.first_name AS patient_first_name, p.last_name AS patient_last_name, p.username AS patient_username, d.first_name, d.last_name, d.username FROM consultations c INNER JOIN users p ON p.id = c.patient_id INNER JOIN users d ON d.id = c.doctor_id WHERE c.patient_id = ? ORDER BY c.appointment_date ASC, c.id DESC');
    $listParam = $userId;
}
if ($listQuery) {
    $listQuery->bind_param('i', $listParam);
    $listQuery->execute();
    $result = $listQuery->get_result();
    while ($row = $result->fetch_assoc()) {
        $consultations[] = $row;
    }
    $listQuery->close();
}

$selectedConsultation = null;
$selectedId = filter_var($_GET['consultation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($selectedId) {
    $detailQuery = $conn->prepare('SELECT c.id, c.patient_id, c.doctor_id, c.consultation_type, c.appointment_date, c.status, c.symptoms, c.notes, c.created_at, p.first_name AS patient_first_name, p.last_name AS patient_last_name, p.username AS patient_username, d.first_name, d.last_name, d.username, d.role FROM consultations c INNER JOIN users p ON p.id = c.patient_id INNER JOIN users d ON d.id = c.doctor_id WHERE c.id = ? AND (c.patient_id = ? OR (c.doctor_id = ? AND ? IN (\'doctor\', \'health_worker\'))) LIMIT 1');
    if ($detailQuery) {
        $detailQuery->bind_param('iiis', $selectedId, $userId, $userId, $currentRole);
        $detailQuery->execute();
        $selectedConsultation = $detailQuery->get_result()->fetch_assoc() ?: null;
        $detailQuery->close();
    }
}

$currentInitial = strtoupper(substr((string) ($_SESSION['username'] ?? 'S'), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telemedicine | Smart Medic</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="dashboard-body telemedicine-page">
    <header class="dashboard-header">
        <a class="brand-mark" href="../dashboard.php" aria-label="Smart Medic home"><span class="brand-symbol" aria-hidden="true">+</span><span>Smart Medic</span></a>
        <nav class="desktop-main-nav" aria-label="Main navigation"><a href="../dashboard.php">Home</a><a href="../community/community.php">Community</a><a class="is-active" href="support.php">Support</a><a href="../bookings/bookings.php">Bookings</a><a href="../account/account.php">Account</a></nav>
        <a class="header-avatar" href="../profile.php" aria-label="Open profile"><?= e($currentInitial) ?></a>
    </header>

    <main class="telemedicine-shell">
        <a class="back-link telemedicine-back-link" href="support.php">&larr; Back to Healthcare Support</a>
        <header class="telemedicine-header">
            <p class="eyebrow">Support module</p>
            <h1>Telemedicine Consultations</h1>
            <p>Request a consultation with an available Smart Medic healthcare professional and keep appointment details in one place.</p>
        </header>
        <?php if ($successMessage !== ''): ?><div class="alert alert-success" role="status">
                <p><?= e($successMessage) ?></p>
            </div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-error" role="alert">
                <p>Please correct the following:</p>
                <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
            </div><?php endif; ?>
        <aside class="telemedicine-notice" role="note"><strong>Care notice</strong>
            <p>Smart Medic facilitates consultation requests and scheduling but does not replace professional medical care. For urgent or life-threatening concerns, seek immediate appropriate emergency assistance rather than waiting for a telemedicine consultation.</p>
        </aside>

        <?php if ($selectedConsultation): ?><section class="telemedicine-panel consultation-detail" aria-labelledby="detail-title">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Consultation details</p>
                        <h2 id="detail-title"><?= e((string) $selectedConsultation['consultation_type']) ?></h2>
                    </div><span class="status-badge status-<?= e((string) $selectedConsultation['status']) ?>"><?= e(consultationStatusLabel((string) $selectedConsultation['status'])) ?></span>
                </div>
                <dl class="consultation-facts">
                    <div>
                        <dt>Patient</dt>
                        <dd><?= e(trim((string) $selectedConsultation['patient_first_name'] . ' ' . (string) $selectedConsultation['patient_last_name']) ?: (string) $selectedConsultation['patient_username']) ?></dd>
                    </div>
                    <div>
                        <dt>Healthcare professional</dt>
                        <dd><?= e(consultationLabel(['first_name' => $selectedConsultation['first_name'], 'last_name' => $selectedConsultation['last_name'], 'username' => $selectedConsultation['username']])) ?></dd>
                    </div>
                    <div>
                        <dt>Appointment</dt>
                        <dd><?= e(date('M j, Y g:i A', strtotime((string) $selectedConsultation['appointment_date']))) ?></dd>
                    </div>
                    <div>
                        <dt>Reason</dt>
                        <dd><?= e((string) $selectedConsultation['consultation_type']) ?></dd>
                    </div>
                </dl><?php if (!empty($selectedConsultation['symptoms'])): ?><div class="consultation-copy"><strong>Symptoms or concerns</strong>
                        <p><?= nl2br(e((string) $selectedConsultation['symptoms'])) ?></p>
                    </div><?php endif; ?><?php if (!empty($selectedConsultation['notes'])): ?><div class="consultation-copy"><strong>Additional notes</strong>
                        <p><?= nl2br(e((string) $selectedConsultation['notes'])) ?></p>
                    </div><?php endif; ?><div class="video-placeholder"><strong>Video Consultation</strong>
                    <p>Video consultation will be available when the scheduled consultation begins.</p>
                </div><?php if ($isProfessional && (int) $selectedConsultation['doctor_id'] === $userId): ?><form method="post" class="status-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="update_status"><input type="hidden" name="consultation_id" value="<?= (int) $selectedConsultation['id'] ?>"><label for="detail-status">Update status</label><select id="detail-status" name="status"><?php foreach (['scheduled' => 'Scheduled', 'approved' => 'Approved', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $selectedConsultation['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="button-primary" type="submit">Save status</button></form><?php elseif (!$isProfessional && in_array($selectedConsultation['status'], ['scheduled', 'pending', 'approved'], true) && strtotime((string) $selectedConsultation['appointment_date']) >= time()): ?><form method="post" class="cancel-form" onsubmit="return confirm('Cancel this consultation?');"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="cancel_consultation"><input type="hidden" name="consultation_id" value="<?= (int) $selectedConsultation['id'] ?>"><button class="button-danger" type="submit">Cancel consultation</button></form><?php endif; ?>
            </section><?php endif; ?>

        <?php if (!$isProfessional): ?><section class="telemedicine-panel request-panel" aria-labelledby="request-title">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">New request</p>
                        <h2 id="request-title">Request a consultation</h2>
                    </div>
                </div><?php if ($professionals): ?><form method="post" class="telemedicine-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="request_consultation"><label for="doctor-id">Healthcare professional <span aria-hidden="true">*</span></label><select id="doctor-id" name="doctor_id" required>
                            <option value="">Select a professional</option><?php foreach ($professionals as $professional): ?><option value="<?= (int) $professional['id'] ?>"><?= e(consultationLabel($professional)) ?> · <?= e(ucwords(str_replace('_', ' ', (string) $professional['role']))) ?></option><?php endforeach; ?>
                        </select><label for="consultation-type">Consultation reason <span aria-hidden="true">*</span></label><input type="text" id="consultation-type" name="consultation_type" maxlength="100" placeholder="General consultation" required><label for="appointment-date">Preferred date and time <span aria-hidden="true">*</span></label><input type="datetime-local" id="appointment-date" name="appointment_date" min="<?= e(date('Y-m-d\\TH:i')) ?>" required><label for="symptoms">Symptoms or concerns</label><textarea id="symptoms" name="symptoms" maxlength="5000" rows="4"></textarea><label for="notes">Additional notes</label><textarea id="notes" name="notes" maxlength="5000" rows="3"></textarea><button class="button-primary" type="submit">Submit request</button></form><?php else: ?><p class="empty-state">No healthcare professionals are currently registered. Please check back later.</p><?php endif; ?>
            </section><?php endif; ?>

        <section class="telemedicine-panel consultations-panel" aria-labelledby="consultations-title">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow"><?= $isProfessional ? 'Assigned requests' : 'Your requests' ?></p>
                    <h2 id="consultations-title"><?= $isProfessional ? 'Consultation requests assigned to you' : 'My consultations' ?></h2>
                </div>
            </div><?php if (!$consultations): ?><p class="empty-state"><?= $isProfessional ? 'No consultation requests are currently assigned to you.' : 'You have not submitted any consultation requests yet.' ?></p><?php else: ?><div class="consultation-list"><?php foreach ($consultations as $consultation): ?><article class="consultation-card">
                            <div class="consultation-card-heading">
                                <div>
                                    <h3><?= e((string) $consultation['consultation_type']) ?></h3>
                                    <p><?= e(date('M j, Y g:i A', strtotime((string) $consultation['appointment_date']))) ?></p>
                                </div><span class="status-badge status-<?= e((string) $consultation['status']) ?>"><?= e(consultationStatusLabel((string) $consultation['status'])) ?></span>
                            </div>
                            <p class="consultation-person"><?= $isProfessional ? 'Patient: ' . e(trim((string) $consultation['patient_first_name'] . ' ' . (string) $consultation['patient_last_name']) ?: (string) $consultation['patient_username']) : 'Professional: ' . e(consultationLabel(['first_name' => $consultation['first_name'], 'last_name' => $consultation['last_name'], 'username' => $consultation['username']])) ?></p>
                            <div class="consultation-actions"><a class="button-secondary" href="telemedicine.php?consultation_id=<?= (int) $consultation['id'] ?>">View details</a><?php if (!$isProfessional && in_array($consultation['status'], ['scheduled', 'pending', 'approved'], true) && strtotime((string) $consultation['appointment_date']) >= time()): ?><form method="post" onsubmit="return confirm('Cancel this consultation?');"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="cancel_consultation"><input type="hidden" name="consultation_id" value="<?= (int) $consultation['id'] ?>"><button class="button-danger" type="submit">Cancel</button></form><?php endif; ?></div>
                        </article><?php endforeach; ?></div><?php endif; ?>
        </section>
    </main>

    <nav class="mobile-bottom-nav" aria-label="Main navigation"><a href="../dashboard.php"><span aria-hidden="true">⌂</span><small>Home</small></a><a href="../community/community.php"><span aria-hidden="true">◌</span><small>Community</small></a><a class="is-active" href="support.php"><span aria-hidden="true">✚</span><small>Support</small></a><a href="../bookings/bookings.php"><span aria-hidden="true">□</span><small>Bookings</small></a><a href="../account/account.php"><span aria-hidden="true">◎</span><small>Account</small></a></nav>
</body>

</html>