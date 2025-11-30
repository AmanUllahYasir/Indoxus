<?php
/**
 * Get submission details API
 */

header('Content-Type: application/json');

// Hide PHP warnings from being sent to client
ini_set('display_errors', '0');
error_reporting(E_ALL);

// invalidate admin filter cache when marking items read
require_once __DIR__ . '/cache.php';

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'indoxus_db');
define('DB_USER', 'root');
define('DB_PASS', '');

$id = (int)($_GET['id'] ?? 0);

if ($id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Fetch submission
    $stmt = $pdo->prepare("SELECT * FROM contact_submissions WHERE id = ?");
    $stmt->execute([$id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($submission) {
        // If not already 'read', mark it as read and set read_at

        if (($submission['status'] ?? '') !== 'read') {
            $update = $pdo->prepare("UPDATE contact_submissions SET status = 'read', read_at = NOW() WHERE id = ?");
            $update->execute([$id]);

            // clear cached filter lists so the admin UI shows updated stats immediately
            if (function_exists('cache_delete_prefix')) {
                cache_delete_prefix('filter_');
            }

            // refresh submission data
            $stmt = $pdo->prepare("SELECT * FROM contact_submissions WHERE id = ?");
            $stmt->execute([$id]);
            $submission = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'submission' => $submission
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Submission not found'
        ]);
    }
    
} catch (PDOException $e) {
    error_log('get_submission.php error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}

