<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$topics = [
    [
        'title' => 'Cuts and Minor Wounds',
        'icon' => '+',
        'summary' => 'Basic care for small cuts, scrapes, and shallow wounds.',
        'steps' => ['Wash your hands, then gently rinse the wound with clean running water.', 'Apply gentle pressure with clean gauze or cloth if there is minor bleeding.', 'Cover the area with a clean dressing and keep it dry and protected.'],
        'warning' => 'Get medical help for heavy or persistent bleeding, deep or gaping wounds, embedded objects, loss of feeling, or signs of infection.'
    ],
    [
        'title' => 'Burns',
        'icon' => '~',
        'summary' => 'First steps for a small heat or contact burn.',
        'steps' => ['Move away from the heat source and remove tight items near the burn if they are not stuck.', 'Cool the area with cool running water for several minutes.', 'Cover loosely with a clean, non-fluffy dressing. Do not break blisters or apply creams, butter, or ice.'],
        'warning' => 'Seek urgent medical care for large, deep, chemical, electrical, facial, hand, foot, joint, or airway burns, or any burn in a young child.'
    ],
    [
        'title' => 'Nosebleeds',
        'icon' => '•',
        'summary' => 'A calm position can help manage a common nosebleed.',
        'steps' => ['Sit upright and lean slightly forward so blood does not run down the throat.', 'Pinch the soft part of the nose continuously for about 10 minutes while breathing through the mouth.', 'Rest quietly and avoid blowing or picking the nose immediately afterward.'],
        'warning' => 'Get medical help if bleeding is heavy, follows a head injury, causes weakness or breathing trouble, or does not stop after repeated pressure.'
    ],
    [
        'title' => 'Sprains and Strains',
        'icon' => '↗',
        'summary' => 'Support an injured muscle, ligament, or joint while it settles.',
        'steps' => ['Stop the activity and protect the injured area from further strain.', 'Use a wrapped cool pack for short periods and raise the area when comfortable.', 'Return to gentle movement only as pain allows; avoid forcing the joint.'],
        'warning' => 'Seek medical care for severe pain, major swelling, deformity, numbness, inability to use the limb, or an injury that is not improving.'
    ],
    [
        'title' => 'Fainting',
        'icon' => '↓',
        'summary' => 'Help someone who has briefly lost consciousness safely.',
        'steps' => ['Lay the person flat and check that they are breathing normally.', 'Keep the area clear and loosen tight clothing around the neck.', 'Let them recover slowly and do not give food or drink until they are fully alert.'],
        'warning' => 'Contact emergency services if the person does not quickly wake, is not breathing normally, has a serious injury, chest pain, seizure-like movements, or faints during exercise.'
    ],
    [
        'title' => 'Choking',
        'icon' => '!',
        'summary' => 'A serious airway emergency that needs immediate action.',
        'steps' => ['Ask if the person is choking and encourage forceful coughing if they can breathe or speak.', 'If they cannot breathe, speak, or cough effectively, call local emergency services and follow the dispatcher\'s instructions.', 'If they become unresponsive, begin CPR if trained and use an automated external defibrillator if available.'],
        'warning' => 'Choking can become life-threatening quickly. Get emergency help immediately and do not put fingers into the mouth unless an object is clearly visible and easy to remove.'
    ],
    [
        'title' => 'Fever',
        'icon' => '°',
        'summary' => 'Comfort measures for a raised temperature.',
        'steps' => ['Encourage rest and regular sips of fluid if the person can drink safely.', 'Use light clothing and a comfortable room temperature.', 'Monitor symptoms and follow the label or a clinician\'s advice before giving medicine.'],
        'warning' => 'Seek medical advice for a very young baby, a persistent or worsening fever, dehydration, breathing difficulty, confusion, a stiff neck, seizure, or a concerning rash.'
    ],
    [
        'title' => 'Minor Poisoning',
        'icon' => '×',
        'summary' => 'Respond safely when someone may have swallowed or touched a harmful substance.',
        'steps' => ['Move away from the substance and keep its container or label available for professionals.', 'Contact local poison advice services or emergency services for guidance.', 'Do not make the person vomit or give food, drink, or medicine unless a professional instructs you to.'],
        'warning' => 'Get emergency help immediately for trouble breathing, collapse, seizures, severe drowsiness, burns around the mouth, or exposure to an unknown or highly toxic substance.'
    ],
    [
        'title' => 'Allergic Reactions',
        'icon' => '◇',
        'summary' => 'Recognize worsening allergy symptoms and act early.',
        'steps' => ['Move away from the suspected trigger if it is safe to do so.', 'For mild symptoms, follow the person\'s existing care plan or medicine directions.', 'Watch breathing and alertness closely; stay with the person and seek professional advice.'],
        'warning' => 'Swelling of the lips or tongue, breathing difficulty, wheezing, faintness, or widespread sudden symptoms may indicate anaphylaxis. Use a prescribed auto-injector if available and contact emergency services immediately.'
    ],
    [
        'title' => 'Emergency Situations',
        'icon' => '!',
        'summary' => 'A simple approach for serious or life-threatening situations.',
        'steps' => ['Check that the area is safe, then check whether the person responds and is breathing normally.', 'Contact local emergency services and follow the dispatcher\'s instructions.', 'Provide only care you are trained to give, stay with the person, and keep monitoring them until help arrives.'],
        'warning' => 'For any life-threatening situation, contact local emergency services or seek immediate professional medical assistance. Do not delay emergency care to use this page.'
    ],
];

$currentInitial = strtoupper(substr((string) ($_SESSION['username'] ?? 'S'), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>First Aid | Smart Medic</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="dashboard-body first-aid-page">
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

    <main class="first-aid-shell">
        <a class="back-link first-aid-back-link" href="support.php">&larr; Back to Healthcare Support</a>
        <section class="first-aid-header" aria-labelledby="first-aid-title">
            <p class="eyebrow">Support module</p>
            <h1 id="first-aid-title">First Aid</h1>
            <p>Simple, safety-focused information for common first-aid situations.</p>
        </section>

        <aside class="first-aid-warning" role="note">
            <strong>Important safety notice</strong>
            <p>This module provides general information and is not a replacement for professional medical care. For serious or life-threatening situations, contact local emergency services or seek immediate professional medical assistance.</p>
        </aside>

        <div class="first-aid-tools">
            <label for="first-aid-search">Search first-aid topics</label>
            <input type="search" id="first-aid-search" placeholder="Search topics, such as burn..." autocomplete="off">
            <p id="first-aid-result" class="first-aid-result" aria-live="polite"></p>
        </div>

        <section class="first-aid-list" aria-label="First-aid topics">
            <?php foreach ($topics as $index => $topic): ?>
                <article class="first-aid-topic" data-search-text="<?= e(strtolower($topic['title'] . ' ' . $topic['summary'] . ' ' . implode(' ', $topic['steps']) . ' ' . $topic['warning'])) ?>">
                    <div class="first-aid-topic-heading">
                        <span class="first-aid-topic-icon" aria-hidden="true"><?= e($topic['icon']) ?></span>
                        <div>
                            <h2><?= e($topic['title']) ?></h2>
                            <p><?= e($topic['summary']) ?></p>
                        </div>
                    </div>
                    <button type="button" class="first-aid-toggle" aria-expanded="false" aria-controls="first-aid-info-<?= $index ?>">
                        <span>View first-aid information</span><span class="first-aid-toggle-mark" aria-hidden="true">▾</span>
                    </button>
                    <div class="first-aid-info" id="first-aid-info-<?= $index ?>" hidden>
                        <h3>General steps</h3>
                        <ol>
                            <?php foreach ($topic['steps'] as $step): ?>
                                <li><?= e($step) ?></li>
                            <?php endforeach; ?>
                        </ol>
                        <div class="first-aid-help-note"><strong>When to get help:</strong> <?= e($topic['warning']) ?></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
        <p id="first-aid-no-results" class="first-aid-no-results" hidden>No first-aid topics match your search.</p>
    </main>

    <nav class="mobile-bottom-nav" aria-label="Main navigation">
        <a href="../dashboard.php"><span aria-hidden="true">⌂</span><small>Home</small></a>
        <a href="../community/community.php"><span aria-hidden="true">◌</span><small>Community</small></a>
        <a class="is-active" href="support.php"><span aria-hidden="true">✚</span><small>Support</small></a>
        <a href="../bookings/bookings.php"><span aria-hidden="true">□</span><small>Bookings</small></a>
        <a href="../account/account.php"><span aria-hidden="true">◎</span><small>Account</small></a>
    </nav>

    <script>
        (function() {
            var search = document.getElementById('first-aid-search');
            var topics = Array.prototype.slice.call(document.querySelectorAll('.first-aid-topic'));
            var result = document.getElementById('first-aid-result');
            var noResults = document.getElementById('first-aid-no-results');

            search.addEventListener('input', function() {
                var query = search.value.trim().toLowerCase();
                var visibleCount = 0;

                topics.forEach(function(topic) {
                    var matches = query === '' || topic.getAttribute('data-search-text').indexOf(query) !== -1;
                    topic.hidden = !matches;
                    if (matches) {
                        visibleCount += 1;
                    }
                });

                noResults.hidden = visibleCount !== 0;
                result.textContent = query === '' ? '' : visibleCount + (visibleCount === 1 ? ' topic found.' : ' topics found.');
            });

            document.querySelectorAll('.first-aid-toggle').forEach(function(toggle) {
                toggle.addEventListener('click', function() {
                    var information = document.getElementById(toggle.getAttribute('aria-controls'));
                    var expanded = toggle.getAttribute('aria-expanded') === 'true';
                    toggle.setAttribute('aria-expanded', String(!expanded));
                    information.hidden = expanded;
                    toggle.querySelector('span:first-child').textContent = expanded ? 'View first-aid information' : 'Hide first-aid information';
                    toggle.querySelector('.first-aid-toggle-mark').textContent = expanded ? '▾' : '▴';
                });
            });
        }());
    </script>
</body>

</html>