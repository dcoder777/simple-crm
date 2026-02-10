<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';

const STATUSES = ['New', 'Contacted', 'Won', 'Lost'];

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function validStatus(string $status): bool
{
    return in_array($status, STATUSES, true);
}

function validateCsrf(array $post): bool
{
    return isset($post['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string) $post['csrf_token']);
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST)) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_lead') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $status = trim((string) ($_POST['status'] ?? 'New'));

            if ($name === '') {
                $errors[] = 'Name is required.';
            }

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'Email must be valid.';
            }

            if (!validStatus($status)) {
                $errors[] = 'Invalid status selected.';
            }

            if ($errors === []) {
                $stmt = $pdo->prepare('INSERT INTO leads (name, phone, email, status) VALUES (:name, :phone, :email, :status)');
                $stmt->execute([
                    ':name' => $name,
                    ':phone' => $phone,
                    ':email' => $email,
                    ':status' => $status,
                ]);
                $success = 'Contact/lead added successfully.';
            }
        }

        if ($action === 'update_status') {
            $leadId = filter_input(INPUT_POST, 'lead_id', FILTER_VALIDATE_INT);
            $status = trim((string) ($_POST['status'] ?? ''));

            if (!$leadId) {
                $errors[] = 'Invalid lead selected.';
            }

            if (!validStatus($status)) {
                $errors[] = 'Invalid status selected.';
            }

            if ($errors === []) {
                $stmt = $pdo->prepare('UPDATE leads SET status = :status WHERE id = :id');
                $stmt->execute([
                    ':status' => $status,
                    ':id' => $leadId,
                ]);
                $success = 'Lead status updated.';
            }
        }

        if ($action === 'add_log') {
            $leadId = filter_input(INPUT_POST, 'lead_id', FILTER_VALIDATE_INT);
            $notes = trim((string) ($_POST['notes'] ?? ''));
            $followUpDate = trim((string) ($_POST['follow_up_date'] ?? ''));

            if (!$leadId) {
                $errors[] = 'Invalid lead selected for log.';
            }

            if ($notes === '') {
                $errors[] = 'Interaction note is required.';
            }

            $normalizedFollowUp = null;
            if ($followUpDate !== '') {
                $date = DateTime::createFromFormat('Y-m-d', $followUpDate);
                if (!$date || $date->format('Y-m-d') !== $followUpDate) {
                    $errors[] = 'Follow-up date must be valid.';
                } else {
                    $normalizedFollowUp = $followUpDate;
                }
            }

            if ($errors === []) {
                $stmt = $pdo->prepare('INSERT INTO interaction_logs (lead_id, notes, follow_up_date) VALUES (:lead_id, :notes, :follow_up_date)');
                $stmt->execute([
                    ':lead_id' => $leadId,
                    ':notes' => $notes,
                    ':follow_up_date' => $normalizedFollowUp,
                ]);
                $success = 'Interaction logged successfully.';
            }
        }
    }
}

$leadsStmt = $pdo->query('SELECT id, name, phone, email, status, created_at FROM leads ORDER BY created_at DESC');
$leads = $leadsStmt->fetchAll();

$logsStmt = $pdo->query(
    'SELECT il.id, il.lead_id, il.notes, il.follow_up_date, il.created_at, l.name AS lead_name
     FROM interaction_logs il
     JOIN leads l ON l.id = il.lead_id
     ORDER BY il.created_at DESC
     LIMIT 25'
);
$logs = $logsStmt->fetchAll();

$todayStmt = $pdo->prepare(
    'SELECT il.id, il.lead_id, il.notes, il.follow_up_date, il.created_at, l.name AS lead_name, l.status
     FROM interaction_logs il
     JOIN leads l ON l.id = il.lead_id
     WHERE il.follow_up_date = CURDATE()
     ORDER BY il.created_at DESC'
);
$todayStmt->execute();
$todayFollowUps = $todayStmt->fetchAll();

$overdueStmt = $pdo->prepare(
    'SELECT il.id, il.lead_id, il.notes, il.follow_up_date, il.created_at, l.name AS lead_name, l.status
     FROM interaction_logs il
     JOIN leads l ON l.id = il.lead_id
     WHERE il.follow_up_date < CURDATE() AND l.status NOT IN ("Won", "Lost")
     ORDER BY il.follow_up_date ASC'
);
$overdueStmt->execute();
$overdueFollowUps = $overdueStmt->fetchAll();

$pipeline = [];
foreach (STATUSES as $status) {
    $pipeline[$status] = array_values(array_filter(
        $leads,
        static fn(array $lead): bool => $lead['status'] === $status
    ));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Simple CRM for Small Business</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<main class="container">
    <header class="page-header">
        <h1>Simple CRM for Small Business</h1>
        <p>Manage contacts, leads, and follow-ups with minimal clicks.</p>
    </header>

    <?php if ($errors !== []): ?>
        <section class="alert error">
            <?php foreach ($errors as $error): ?>
                <p><?= h($error) ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <section class="alert success">
            <p><?= h($success) ?></p>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Add Contact / Lead</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="add_lead">

            <label>
                Name
                <input type="text" name="name" required maxlength="120">
            </label>

            <label>
                Phone
                <input type="tel" name="phone" maxlength="40" placeholder="+1 555 0100">
            </label>

            <label>
                Email
                <input type="email" name="email" maxlength="190" placeholder="name@business.com">
            </label>

            <label>
                Status
                <select name="status">
                    <?php foreach (STATUSES as $status): ?>
                        <option value="<?= h($status) ?>"><?= h($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button type="submit">Save Lead</button>
        </form>
    </section>

    <section class="card">
        <h2>Pipeline</h2>
        <div class="kanban-grid">
            <?php foreach ($pipeline as $status => $items): ?>
                <article class="column">
                    <h3><?= h($status) ?> <span>(<?= count($items) ?>)</span></h3>
                    <?php if ($items === []): ?>
                        <p class="muted">No leads.</p>
                    <?php endif; ?>
                    <?php foreach ($items as $lead): ?>
                        <div class="lead-card">
                            <strong><?= h($lead['name']) ?></strong>
                            <p><?= h($lead['phone'] ?: 'No phone') ?></p>
                            <p><?= h($lead['email'] ?: 'No email') ?></p>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>">
                                <select name="status" aria-label="Update status for <?= h($lead['name']) ?>">
                                    <?php foreach (STATUSES as $option): ?>
                                        <option value="<?= h($option) ?>" <?= $option === $lead['status'] ? 'selected' : '' ?>>
                                            <?= h($option) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit">Move</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <h2>Add Interaction Log</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="add_log">

            <label>
                Lead
                <select name="lead_id" required>
                    <option value="">Select lead</option>
                    <?php foreach ($leads as $lead): ?>
                        <option value="<?= (int) $lead['id'] ?>"><?= h($lead['name']) ?> (<?= h($lead['status']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="full-width">
                Notes
                <textarea name="notes" rows="3" maxlength="1000" required placeholder="Called and discussed pricing..."></textarea>
            </label>

            <label>
                Next follow-up date
                <input type="date" name="follow_up_date">
            </label>

            <button type="submit">Save Interaction</button>
        </form>
    </section>

    <section class="card split">
        <div>
            <h2>Today's Follow-ups</h2>
            <?php if ($todayFollowUps === []): ?>
                <p class="muted">No follow-ups due today.</p>
            <?php endif; ?>
            <ul>
                <?php foreach ($todayFollowUps as $item): ?>
                    <li>
                        <strong><?= h($item['lead_name']) ?></strong> (<?= h($item['status']) ?>) —
                        <?= h($item['notes']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div>
            <h2>Overdue Leads</h2>
            <?php if ($overdueFollowUps === []): ?>
                <p class="muted">No overdue follow-ups.</p>
            <?php endif; ?>
            <ul>
                <?php foreach ($overdueFollowUps as $item): ?>
                    <li>
                        <strong><?= h($item['lead_name']) ?></strong> —
                        due <?= h((string) $item['follow_up_date']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <section class="card">
        <h2>Recent Interaction Logs</h2>
        <?php if ($logs === []): ?>
            <p class="muted">No interactions logged yet.</p>
        <?php else: ?>
            <ul class="logs">
                <?php foreach ($logs as $log): ?>
                    <li>
                        <strong><?= h($log['lead_name']) ?></strong>
                        <span class="timestamp"><?= h((string) $log['created_at']) ?></span>
                        <p><?= nl2br(h($log['notes'])) ?></p>
                        <?php if ($log['follow_up_date'] !== null): ?>
                            <small>Follow-up: <?= h((string) $log['follow_up_date']) ?></small>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
