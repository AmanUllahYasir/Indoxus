<?php
/**
 * API: Filter submissions (used by admin dashboard via AJAX)
 * Returns HTML table rows for submissions matching the filter
 */

header('Content-Type: application/json');
ini_set('display_errors', '0');
error_reporting(E_ALL);


// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'indoxus_db');
define('DB_USER', 'root');
define('DB_PASS', '');


$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Simple server-side caching for faster admin filter responses
require_once __DIR__ . '/cache.php';
$cache_ttl = 10; // seconds - adjust for responsiveness vs freshness
$cache_key = 'filter_' . md5("status={$status_filter}|search={$search}");
$cached = cache_get($cache_key);
if ($cached !== null) {
    echo json_encode($cached);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Build query
    $sql = "SELECT * FROM contact_submissions WHERE 1=1";
    $params = [];

    if ($status_filter !== 'all') {
        $sql .= " AND status = :status";
        $params[':status'] = $status_filter;
    }

    if (!empty($search)) {
        $sql .= " AND (name LIKE :search OR email LIKE :search OR message LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $sql .= " ORDER BY submitted_at DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $submissions = $stmt->fetchAll();

    // Get updated stats
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new,
            SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as `read`,
            SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied
        FROM contact_submissions
    ")->fetch();

    // Build HTML for table rows
    $rowsHtml = '';
    if (empty($submissions)) {
        $rowsHtml = '<tr><td colspan="9" style="text-align: center; padding: 40px; color: #999;">No submissions found</td></tr>';
    } else {
        foreach ($submissions as $sub) {
            $rowsHtml .= '<tr data-id="' . $sub['id'] . '">';
            $rowsHtml .= '<td><strong>#' . $sub['id'] . '</strong></td>';
            $rowsHtml .= '<td>' . date('M j, Y g:i A', strtotime($sub['submitted_at'])) . '</td>';
            $rowsHtml .= '<td>' . htmlspecialchars($sub['name']) . '</td>';
            $rowsHtml .= '<td>' . htmlspecialchars($sub['email']) . '</td>';
            $rowsHtml .= '<td>' . htmlspecialchars($sub['company']) . '</td>';
            $rowsHtml .= '<td>' . htmlspecialchars($sub['country']) . '</td>';
            $rowsHtml .= '<td>' . htmlspecialchars($sub['service']) . '</td>';
            $rowsHtml .= '<td><span class="status ' . $sub['status'] . '">' . strtoupper($sub['status']) . '</span></td>';
            $rowsHtml .= '<td><a href="#" class="view-btn" onclick="viewSubmission(' . $sub['id'] . '); return false;">View</a></td>';
            $rowsHtml .= '</tr>';
        }
    }

    $response = [
        'success' => true,
        'rows_html' => $rowsHtml,
        'stats' => $stats
    ];

    // cache the response for a short time
    if (isset($cache_key)) {
        cache_set($cache_key, $response, $cache_ttl);
    }

    echo json_encode($response);

} catch (PDOException $e) {
    error_log('filter_submissions.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
