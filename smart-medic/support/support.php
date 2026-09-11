<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$services = [
    ['name' => 'First Aid', 'description' => 'Practical guidance for responding to common injuries and urgent situations.', 'icon' => '+', 'link' => 'first_aid.php'],
    ['name' => 'Child Immunization Tracking System', 'description' => 'Keep children\'s vaccination information and upcoming immunizations organized.', 'icon' => '◎', 'link' => 'immunization.php'],
    ['name' => 'Health Checkup Reminder', 'description' => 'Stay on top of routine checkups, screenings, and preventive care.', 'icon' => '✓', 'link' => 'checkups.php'],
    ['name' => 'Maternal Health Monitoring', 'description' => 'Find supportive resources for pregnancy, birth preparation, and maternal care.', 'icon' => '♡', 'link' => 'maternal_health.php'],
    ['name' => 'Telemedicine Consultation Platform', 'description' => 'Access information about remote consultations and connected care.', 'icon' => '◉', 'link' => 'telemedicine.php'],
    ['name' => 'Nutrition and Diet Platform', 'description' => 'Explore practical resources for balanced nutrition and healthy habits.', 'icon' => '◌', 'link' => 'nutrition.php'],
    ['name' => 'Ambulance / Emergency Dispatch System', 'description' => 'Find emergency assistance guidance and urgent response resources.', 'icon' => '!', 'link' => 'emergency.php'],
    ['name' => 'Mental Health and Wellness', 'description' => 'Discover supportive tools and information for emotional wellbeing.', 'icon' => '☼', 'link' => 'mental_health.php'],
    ['name' => 'Medical Equipment Rental / Booking', 'description' => 'Find resources for accessing and arranging essential medical equipment.', 'icon' => '□', 'link' => 'equipment.php'],
    ['name' => 'Physiotherapy / Rehabilitation Tracking', 'description' => 'Support recovery with rehabilitation and movement-care resources.', 'icon' => '↗', 'link' => 'physiotherapy.php'],
    ['name' => 'Disease Outbreak and Surveillance Tracker', 'description' => 'Learn about health alerts, prevention, and outbreak awareness.', 'icon' => '△', 'link' => 'outbreak.php'],
    ['name' => 'Healthcare Facility Locator and Referral System', 'description' => 'Connect with healthcare facilities and referral information near you.', 'icon' => '⌖', 'link' => 'facilities.php'],
];

$currentInitial = strtoupper(substr((string) ($_SESSION['username'] ?? 'S'), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Healthcare Support | Smart Medic</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="dashboard-body support-page">
    <header class="dashboard-header">
        <a class="brand-mark" href="../dashboard.php" aria-label="Smart Medic home">
            <span class="brand-symbol" aria-hidden="true">+</span>
            <span>Smart Medic</span>
        </a>
        <nav class="desktop-main-nav" aria-label="Main navigation">
            <a href="../dashboard.php">Home</a>
            <a href="../community/community.php">Community</a>
            <a class="is-active" href="support.php">Support</a>
            <a href="../bookings/bookings.php">Bookings</a>
            <a href="../account/account.php">Account</a>
        </nav>
        <a class="header-avatar" href="../profile.php" aria-label="Open profile"><?= e($currentInitial) ?></a>
    </header>

    <main class="support-shell">
        <section class="support-hub-card" aria-labelledby="support-title">
            <header class="support-header">
                <p class="eyebrow">Care, guidance, and connection</p>
                <h1 id="support-title">Healthcare Support</h1>
                <p>Smart Medic brings healthcare information, reminders, consultations, emergency assistance, wellness services, and healthcare resources together in one place.</p>
            </header>

            <div class="support-grid">
                <?php foreach ($services as $service): ?>
                    <article class="support-card">
                        <div class="support-card-icon" aria-hidden="true"><?= e($service['icon']) ?></div>
                        <div class="support-card-content">
                            <h2 class="support-card-title"><?= e($service['name']) ?></h2>
                            <p class="support-card-description"><?= e($service['description']) ?></p>
                            <a class="support-card-button" href="<?= e($service['link']) ?>">Open Service <span aria-hidden="true">&rarr;</span></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <nav class="mobile-bottom-nav" aria-label="Main navigation">
        <a href="../dashboard.php"><span aria-hidden="true">⌂</span><small>Home</small></a>
        <a href="../community/community.php"><span aria-hidden="true">◌</span><small>Community</small></a>
        <a class="is-active" href="support.php"><span aria-hidden="true">✚</span><small>Support</small></a>
        <a href="../bookings/bookings.php"><span aria-hidden="true">□</span><small>Bookings</small></a>
        <a href="../account/account.php"><span aria-hidden="true">◎</span><small>Account</small></a>
    </nav>
</body>

</html>