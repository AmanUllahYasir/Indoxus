<?php
/**
 * API: Reply to a submission and mark as replied
 * Expects JSON POST: { id: <int>, message: <string>, subject?: <string> }
 */

header('Content-Type: application/json');
ini_set('display_errors', '0');
error_reporting(E_ALL);

// We'll clear server-side filter caches when a reply changes status
require_once __DIR__ . '/cache.php';

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'indoxus_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Basic email configuration - reuse values from contact.php if desired
define('FROM_EMAIL', 'noreply@indoxus.com');
define('FROM_NAME', 'Indoxus Communications');

$data = json_decode(file_get_contents('php://input'), true);
$id = (int)($data['id'] ?? 0);
$replyMessage = trim($data['message'] ?? '');
$subject = trim($data['subject'] ?? 'Reply from Indoxus Communications');

if ($id === 0 || $replyMessage === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare("SELECT * FROM contact_submissions WHERE id = ?");
    $stmt->execute([$id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$submission) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Submission not found']);
        exit;
    }

    $to = $submission['email'];

    // Build reply email (HTML)
    $htmlBody = "<html><body>" .
        "<p>Dear " . htmlspecialchars($submission['name']) . ",</p>" .
        "<div style='padding:10px;background:#f6f6f6;border-left:3px solid #2C5F6F;'>" . nl2br(htmlspecialchars($replyMessage)) . "</div>" .
        "<p>--<br>Indoxus Communications</p>" .
        "</body></html>";

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
        'Reply-To: ' . FROM_EMAIL,
        'X-Mailer: PHP/' . phpversion()
    ];

    $mailSuccess = mail($to, $subject, $htmlBody, implode("\r\n", $headers));

    // Update submission status to replied
    $update = $pdo->prepare("UPDATE contact_submissions SET status = 'replied', replied_at = NOW(), notes = CONCAT(IFNULL(notes,''), :note) WHERE id = ?");
    $noteText = "Reply sent by admin on " . date('Y-m-d H:i:s') . ": " . substr($replyMessage, 0, 1000);
    $update->execute([$noteText, $id]);

    // Insert email log
    $log = $pdo->prepare("INSERT INTO email_logs (submission_id, email_type, recipient_email, subject, sent_at, status, error_message) VALUES (?, 'user_confirmation', ?, ?, NOW(), ?, ?)");
    $logStatus = $mailSuccess ? 'sent' : 'failed';
    $errorMessage = $mailSuccess ? null : 'Mail function returned false';
    $log->execute([$id, $to, $subject, $logStatus, $errorMessage]);

    // Invalidate cached filtered lists so admin UI updates quickly
    if (function_exists('cache_delete_prefix')) {
        cache_delete_prefix('filter_');
    }

    echo json_encode(['success' => (bool)$mailSuccess, 'email_sent' => (bool)$mailSuccess]);

} catch (PDOException $e) {
    error_log('reply_submission.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

 