<?php
/**
 * Hermes Quiz Lead Capture
 *
 * Drop this file in your WHMCS webroot (same folder as index.php).
 * The quiz POSTs here; results appear in the Hermes Manager addon under "Quiz Leads".
 */

// ── CORS: allow your quiz page domain (or * for open access) ──────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

// ── Load WHMCS DB config ───────────────────────────────────────────────────
$whmcsRoot = __DIR__;
$configFile = $whmcsRoot . '/configuration.php';

if (!file_exists($configFile)) {
    echo json_encode(['ok' => false, 'error' => 'WHMCS config not found']);
    exit;
}

include $configFile;

// ── Sanitize inputs ────────────────────────────────────────────────────────
function clean($v) {
    return htmlspecialchars(trim((string)($v ?? '')), ENT_QUOTES, 'UTF-8');
}

$name     = clean($_POST['name'] ?? '');
$email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$whatsapp = clean($_POST['whatsapp'] ?? '');
$profile  = clean($_POST['profile'] ?? '');
$answers  = clean($_POST['answers'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid email']);
    exit;
}

$allowed_profiles = ['architect', 'sprinter', 'craftsman', 'explorer'];
if (!in_array($profile, $allowed_profiles, true)) {
    $profile = 'unknown';
}

// ── Connect to WHMCS MySQL ─────────────────────────────────────────────────
try {
    $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_username, $db_password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'DB connection failed']);
    exit;
}

// ── Create table if not exists ─────────────────────────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `mod_hermesagent_quiz_leads` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name`       VARCHAR(120)  NOT NULL DEFAULT '',
        `email`      VARCHAR(200)  NOT NULL,
        `whatsapp`   VARCHAR(30)   NOT NULL DEFAULT '',
        `profile`    VARCHAR(30)   NOT NULL DEFAULT '',
        `answers`    TEXT,
        `status`     VARCHAR(20)   NOT NULL DEFAULT 'new',
        `notes`      TEXT,
        `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_email`   (`email`),
        INDEX `idx_status`  (`status`),
        INDEX `idx_profile` (`profile`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Prevent duplicate email submissions ────────────────────────────────────
$exists = $pdo->prepare("SELECT id FROM mod_hermesagent_quiz_leads WHERE email = ? LIMIT 1");
$exists->execute([$email]);
if ($exists->fetch()) {
    // Update answers/profile in case they retook the quiz
    $upd = $pdo->prepare("
        UPDATE mod_hermesagent_quiz_leads
        SET name=?, whatsapp=?, profile=?, answers=?, updated_at=NOW()
        WHERE email=?
    ");
    $upd->execute([$name, $whatsapp, $profile, $answers, $email]);
    echo json_encode(['ok' => true, 'action' => 'updated']);
    exit;
}

// ── Insert new lead ────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    INSERT INTO mod_hermesagent_quiz_leads (name, email, whatsapp, profile, answers, status, created_at)
    VALUES (?, ?, ?, ?, ?, 'new', NOW())
");
$stmt->execute([$name, $email, $whatsapp, $profile, $answers]);

echo json_encode(['ok' => true, 'action' => 'created', 'id' => $pdo->lastInsertId()]);
