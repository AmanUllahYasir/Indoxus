<?php
/**
 * INDOXUS COMMUNICATIONS - ADMIN DASHBOARD
 * Simple interface to view contact form submissions
 */

session_start();

// Regenerate session ID to prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Session security settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Set to 1 in production with HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'indoxus_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Secure password hashing (CHANGE THE PASSWORD!)
// To generate hash: password_hash('your_password', PASSWORD_ARGON2ID)
$ADMIN_PASSWORD_HASH = '$argon2id$v=19$m=65536,t=4,p=1$YlhpWFJETXRrRmhYQjRhVw$6K8lZg5L3eV8xQXZqGJHvYz1pZLF7ZCT4vQpXw8FJNs'; // Password: indoxus2025

// Rate limiting for login attempts
$login_rate_limit_file = __DIR__ . '/../cache/admin_login_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '.json';
$max_login_attempts = 5;
$login_lockout_time = 900; // 15 minutes

function checkLoginRateLimit($file, $max, $lockout) {
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        $current_time = time();

        // Clean old attempts
        $data['attempts'] = array_filter($data['attempts'], function($timestamp) use ($current_time, $lockout) {
            return ($current_time - $timestamp) < $lockout;
        });

        if (count($data['attempts']) >= $max) {
            return false;
        }

        $data['attempts'][] = $current_time;
        file_put_contents($file, json_encode($data), LOCK_EX);
        return true;
    } else {
        file_put_contents($file, json_encode(['attempts' => [time()]]), LOCK_EX);
        return true;
    }
}

// Handle login
if (isset($_POST['login'])) {
    if (!checkLoginRateLimit($login_rate_limit_file, $max_login_attempts, $login_lockout_time)) {
        $error = "Too many login attempts. Please try again in 15 minutes.";
    } elseif (password_verify($_POST['password'], $ADMIN_PASSWORD_HASH)) {
        // Clear login attempts on successful login
        @unlink($login_rate_limit_file);

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_time'] = time();
        $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Regenerate session ID after login
        session_regenerate_id(true);
    } else {
        $error = "Invalid password";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../index.html');
    exit;
}

// Session timeout (30 minutes of inactivity)
$session_timeout = 1800;
if (isset($_SESSION['admin_logged_in'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
        session_unset();
        session_destroy();
        session_start();
        $error = "Session expired. Please login again.";
    } else {
        $_SESSION['last_activity'] = time();

        // Verify session IP to prevent session hijacking
        if (isset($_SESSION['admin_ip']) && $_SESSION['admin_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
            session_unset();
            session_destroy();
            session_start();
            $error = "Security violation detected. Please login again.";
        }
    }
}

// Check if logged in
if (!isset($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - Indoxus</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; background: #1A2332; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
            .login-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); max-width: 400px; width: 100%; }
            h2 { color: #2C5F6F; margin-bottom: 20px; }
            input { width: 100%; padding: 12px; margin: 10px 0; border: 2px solid #ddd; border-radius: 5px; font-size: 16px; }
            button { width: 100%; padding: 12px; background: #2C5F6F; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
            button:hover { background: #1A2332; }
            .error { color: red; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>Admin Login</h2>
            <form method="POST">
                <input type="password" name="password" placeholder="Enter admin password" required>
                <button type="submit" name="login">Login</button>
                <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection with security options
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please contact administrator.");
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

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

// Get statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new,
        SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as `read`,
        SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied
    FROM contact_submissions
")->fetch();

// Mark as read if viewing
if (isset($_GET['view'])) {
    $id = (int)$_GET['view'];
    $pdo->prepare("UPDATE contact_submissions SET status = 'read', read_at = NOW() WHERE id = ? AND status = 'new'")->execute([$id]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Submissions - Indoxus Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        /* Header Styling */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            color: #1a202c;
            padding: 18px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-bottom: 1px solid rgba(102, 126, 234, 0.2);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left { display: flex; align-items: center; }
        .admin-logo { height: 50px; width: auto; transition: transform 0.3s; }
        .admin-logo:hover { transform: scale(1.05); }

        .header-right { display: flex; gap: 12px; align-items: center; }

        .header-btn {
            padding: 11px 22px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .export-btn {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
        }
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .password-btn {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            color: white;
        }
        .password-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
        }

        .logout-btn {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            color: white;
            display: inline-block;
        }
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* Statistics Cards */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.85) 100%);
            backdrop-filter: blur(10px);
            padding: 28px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            transition: all 0.4s ease;
            border: 1px solid rgba(255,255,255,0.3);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0,0,0,0.15);
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-card h3 {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-card .number {
            font-size: 42px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }

        /* Color variations for stat numbers */
        #stat-new {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        #stat-read {
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        #stat-replied {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Filters */
        .filters {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            gap: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            flex-wrap: wrap;
            align-items: center;
        }

        .filters input, .filters select {
            padding: 12px 16px;
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
            font-family: inherit;
        }

        .filters input:focus, .filters select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filters button {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .filters button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .filters a {
            padding: 12px 24px;
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.3);
        }

        .filters a:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(107, 114, 128, 0.4);
        }

        /* Table Container */
        .table-container {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.3);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 18px 15px;
            border-bottom: 1px solid rgba(102, 126, 234, 0.1);
            font-size: 14px;
            color: #374151;
        }

        tbody tr {
            transition: all 0.3s ease;
            background: white;
        }

        tbody tr:nth-child(even) {
            background: rgba(102, 126, 234, 0.02);
        }

        tbody tr:hover {
            background: rgba(102, 126, 234, 0.08);
            transform: scale(1.01);
        }

        /* Status Badges */
        .status {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .status.new {
            background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%);
            color: #991B1B;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.2);
        }

        .status.read {
            background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%);
            color: #1E40AF;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
        }

        .status.replied {
            background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%);
            color: #065F46;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
        }

        /* View Button */
        .view-btn {
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }

        .view-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        /* Delete Button */
        .delete-btn {
            padding: 8px 12px;
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-left: 8px;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }

        .delete-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.5);
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            margin: 50px auto;
            max-width: 650px;
            padding: 35px;
            border-radius: 20px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid rgba(102, 126, 234, 0.2);
        }

        .modal-header h2 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 24px;
            font-weight: 700;
        }

        .close {
            font-size: 32px;
            cursor: pointer;
            color: #9ca3af;
            transition: all 0.3s ease;
            line-height: 1;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            transform: rotate(90deg);
        }

        .field {
            margin: 18px 0;
        }

        .field label {
            font-weight: 600;
            color: #374151;
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .field .value {
            padding: 14px 16px;
            background: rgba(102, 126, 234, 0.05);
            border-left: 4px solid #667eea;
            border-radius: 8px;
            color: #1f2937;
            line-height: 1.6;
        }

        /* Custom scrollbar for webkit browsers */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                padding: 15px 20px;
            }

            .header-right {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .filters {
                flex-direction: column;
            }

            .filters input, .filters select, .filters button, .filters a {
                width: 100%;
            }

            .table-container {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <img src="../assets/images/logo-full.svg" alt="Indoxus Communications" class="admin-logo">
        </div>
        <div class="header-right">
            <button onclick="exportToCSV()" class="header-btn export-btn">📥 Export CSV</button>
            <button onclick="showChangePasswordModal()" class="header-btn password-btn">🔑 Change Password</button>
            <a href="?logout" class="header-btn logout-btn">🚪 Logout</a>
        </div>
    </div>

    <div class="container">
        <!-- Statistics -->
        <div class="stats">
            <div class="stat-card">
                <h3>Total Submissions</h3>
                <div id="stat-total" class="number"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <h3>New</h3>
                <div id="stat-new" class="number" style="color: #C00;"><?php echo $stats['new']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Read</h3>
                <div id="stat-read" class="number" style="color: #00C;"><?php echo $stats['read']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Replied</h3>
                <div id="stat-replied" class="number" style="color: #0C0;"><?php echo $stats['replied']; ?></div>
            </div>
        </div>

        <!-- Filters -->
        <form class="filters" method="GET" id="filterForm" onsubmit="applyFilters(event); return false;">
            <select name="status" id="statusFilter">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>New</option>
                <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Read</option>
                <option value="replied" <?php echo $status_filter === 'replied' ? 'selected' : ''; ?>>Replied</option>
            </select>
            <input type="text" name="search" id="searchInput" placeholder="Search name, email, message..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Filter</button>
            <?php if($status_filter !== 'all' || !empty($search)): ?>
                <a href="#" onclick="clearFilters(event)" style="padding: 10px 20px; background: #666; color: white; text-decoration: none; border-radius: 5px;">Clear</a>
            <?php endif; ?>
        </form>

        <!-- Submissions Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Company</th>
                        <th>Country</th>
                        <th>Service</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($submissions)): ?>
                        <tr><td colspan="9" style="text-align: center; padding: 40px; color: #999;">No submissions found</td></tr>
                    <?php else: ?>
                        <?php foreach($submissions as $sub): ?>
                            <tr data-id="<?php echo $sub['id']; ?>">
                                <td><strong>#<?php echo $sub['id']; ?></strong></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($sub['submitted_at'])); ?></td>
                                <td><?php echo htmlspecialchars($sub['name']); ?></td>
                                <td><?php echo htmlspecialchars($sub['email']); ?></td>
                                <td><?php echo htmlspecialchars($sub['company']); ?></td>
                                <td><?php echo htmlspecialchars($sub['country']); ?></td>
                                <td><?php echo htmlspecialchars($sub['service']); ?></td>
                                <td><span class="status <?php echo $sub['status']; ?>"><?php echo strtoupper($sub['status']); ?></span></td>
                                <td>
                                    <a href="#" class="view-btn" onclick="viewSubmission(<?php echo $sub['id']; ?>); return false;">View</a>
                                    <button onclick="deleteSubmission(<?php echo $sub['id']; ?>)" class="delete-btn" title="Delete submission">🗑️</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal for viewing submission details -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Submission Details</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="modal-body"></div>
        </div>
    </div>

    <script>
        // CSRF Token for secure requests
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';

        // In-memory client-side cache for filter results to reduce requests and make UI snappy.
        // Entries expire after `clientCacheTtl` ms.
        window.filterCache = new Map();
        const clientCacheTtl = 8000; // 8 seconds

        function applyFilters(e) {
            e.preventDefault();
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('searchInput').value.trim();

            const cacheKey = status + '|' + search;
            const cached = window.filterCache.get(cacheKey);
            if (cached && (Date.now() - cached.ts) < clientCacheTtl) {
                const res = cached.data;
                if (res.success) {
                    document.querySelector('tbody').innerHTML = res.rows_html;
                    document.getElementById('stat-total').textContent = res.stats.total;
                    document.getElementById('stat-new').textContent = res.stats.new;
                    document.getElementById('stat-read').textContent = res.stats.read;
                    document.getElementById('stat-replied').textContent = res.stats.replied;
                    window.history.replaceState({}, '', 'index.php');
                    return;
                }
            }

            // Fetch filtered results via AJAX
            fetch('../api/filter_submissions.php?status=' + encodeURIComponent(status) + '&search=' + encodeURIComponent(search))
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        // cache client-side copy
                        try { window.filterCache.set(cacheKey, { ts: Date.now(), data: res }); } catch (e) { /* ignore */ }

                        // Update table rows
                        document.querySelector('tbody').innerHTML = res.rows_html;

                        // Update stats
                        document.getElementById('stat-total').textContent = res.stats.total;
                        document.getElementById('stat-new').textContent = res.stats.new;
                        document.getElementById('stat-read').textContent = res.stats.read;
                        document.getElementById('stat-replied').textContent = res.stats.replied;

                        // Keep URL clean (no query params)
                        window.history.replaceState({}, '', 'index.php');
                    }
                })
                .catch(err => console.error('Filter error:', err));
        }

        function clearFilters(e) {
            e.preventDefault();
            document.getElementById('statusFilter').value = 'all';
            document.getElementById('searchInput').value = '';
            applyFilters({ preventDefault: () => {} });
        }

        function viewSubmission(id) {
                // Fetch submission details (use correct relative path from admin/ to api/)
                fetch('../api/get_submission.php?id=' + id)
                    .then(response => response.text())
                    .then(text => {
                        let data;
                        try {
                            data = JSON.parse(text);
                        } catch (err) {
                            console.error('Unexpected response from server when fetching submission:', text);
                            alert('Failed to load submission details. See console for server response.');
                            return;
                        }

                        if (data.success) {
                            const sub = data.submission;
                        
                        // Update table row status and dashboard stats without refresh
                        (function updateRowAndStats() {
                            try {
                                const row = document.querySelector('tr[data-id="' + sub.id + '"]');
                                if (row) {
                                    const statusEl = row.querySelector('.status');
                                    const oldStatus = statusEl ? statusEl.className.split(' ').find(c => c !== 'status') : '';

                                    // Determine status for display and for stats mapping
                                    const newStatus = sub.status || '';
                                    const statsStatus = (newStatus === 'opened') ? 'read' : newStatus; // treat 'opened' as 'read' in counters

                                    if (statusEl) {
                                        statusEl.className = 'status ' + (statsStatus || '');
                                        statusEl.textContent = (statsStatus || newStatus).toUpperCase();
                                    }

                                    // Update counters if present
                                    function changeCounter(id, delta) {
                                        const el = document.getElementById(id);
                                        if (!el) return;
                                        const n = parseInt(el.textContent || '0', 10) + delta;
                                        el.textContent = Math.max(0, n);
                                    }

                                    const oldForStats = (oldStatus === 'opened') ? 'read' : oldStatus;
                                    if (oldForStats !== statsStatus) {
                                        if (oldForStats) changeCounter('stat-' + oldForStats, -1);
                                        if (statsStatus) changeCounter('stat-' + statsStatus, 1);
                                    }
                                }
                            } catch (e) {
                                console.error('Error updating row/stats:', e);
                            }
                        })();

                            // Clear client-side filter cache so subsequent filters fetch fresh data
                            try { if (window.filterCache) window.filterCache.clear(); } catch(e) { console.error(e); }

                            document.getElementById('modal-body').innerHTML = `
                                <div class="field"><label>ID:</label><div class="value">#${sub.id}</div></div>
                                <div class="field"><label>Name:</label><div class="value">${escapeHtml(sub.name)}</div></div>
                                <div class="field"><label>Email:</label><div class="value"><a href="mailto:${escapeHtml(sub.email)}">${escapeHtml(sub.email)}</a></div></div>
                                <div class="field"><label>Job Title:</label><div class="value">${escapeHtml(sub.job_title) || 'N/A'}</div></div>
                                <div class="field"><label>Company:</label><div class="value">${escapeHtml(sub.company) || 'N/A'}</div></div>
                                <div class="field"><label>Country:</label><div class="value">${escapeHtml(sub.country) || 'N/A'}</div></div>
                                <div class="field"><label>Service:</label><div class="value">${escapeHtml(sub.service)}</div></div>
                                <div class="field"><label>Message:</label><div class="value">${escapeHtml(sub.message).replace(/\n/g, '<br>')}</div></div>
                                <div class="field"><label>Submitted:</label><div class="value">${escapeHtml(sub.submitted_at)}</div></div>
                                <div class="field"><label>IP Address:</label><div class="value">${escapeHtml(sub.ip_address)}</div></div>
                                <div class="field"><label>Status:</label><div class="value"><span class="status ${escapeHtml(sub.status)}">${escapeHtml(sub.status).toUpperCase()}</span></div></div>
                                ` + (
                                    (sub.status && sub.status.toLowerCase() === 'replied') ?
                                    `<div style="margin-top:12px;color:#0a0;font-weight:600">This submission has been replied to.</div>` :
                                    `<div style="margin-top:16px;border-top:1px solid #eee;padding-top:12px">
                                        <h3 style="margin:0 0 8px 0">Reply to Submitter</h3>
                                        <textarea id="reply-message" rows="5" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px" placeholder="Write your reply here"></textarea>
                                        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">
                                            <button type="button" onclick="sendReply(${sub.id})" style="padding:8px 12px;border-radius:6px;border:none;background:#10B981;color:#fff;cursor:pointer">Send Reply</button>
                                            <button type="button" onclick="closeModal()" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;background:#fff;cursor:pointer">Cancel</button>
                                        </div>
                                    </div>`
                                );
                            document.getElementById('modal').style.display = 'block';
                        } else {
                            alert(data.message || 'Submission not found');
                        }
                    })
                    .catch(err => {
                        console.error('Error fetching submission:', err);
                        alert('Failed to load submission details. See console for details.');
                    });
        }

        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }

        function escapeHtml(str) {
            return String(str || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modal');
            if (event.target == modal) {
                closeModal();
            }
        }

        function sendReply(id) {
            const textarea = document.getElementById('reply-message');
            if (!textarea) return alert('Reply textarea not found');
            const message = textarea.value.trim();
            if (!message) return alert('Please write a reply before sending');

            // Disable UI while sending
            textarea.disabled = true;

            fetch('../api/reply_submission.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, message: message })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    alert('Reply sent successfully');

                    // Update row and stats (make it replied)
                    try {
                        const row = document.querySelector('tr[data-id="' + id + '"]');
                        if (row) {
                            const statusEl = row.querySelector('.status');
                            if (statusEl) {
                                statusEl.className = 'status replied';
                                statusEl.textContent = 'REPLIED';
                            }
                        }

                        // adjust counters: decrement 'new' or 'read' and increment 'replied'
                        ['new','read'].forEach(key => {
                            const el = document.getElementById('stat-' + key);
                            if (!el) return;
                            const n = parseInt(el.textContent || '0', 10);
                            if (n > 0) el.textContent = n - 1;
                        });
                        const rep = document.getElementById('stat-replied');
                        if (rep) rep.textContent = (parseInt(rep.textContent || '0',10) + 1);
                    } catch (e) { console.error(e); }

                    // Clear client-side filter cache so admin sees fresh lists
                    try { if (window.filterCache) window.filterCache.clear(); } catch(e) { console.error(e); }

                    closeModal();
                } else {
                    alert((res.message) || 'Failed to send reply');
                    textarea.disabled = false;
                }
            })
            .catch(err => {
                console.error('Reply error:', err);
                alert('Failed to send reply. See console for details.');
                textarea.disabled = false;
            });
        }

        // Export to CSV function
        function exportToCSV() {
            fetch('../api/filter_submissions.php?status=all&search=')
                .then(r => r.json())
                .then(res => {
                    // Get all submissions data
                    fetch('../api/filter_submissions.php?status=all&search=')
                        .then(response => response.text())
                        .then(html => {
                            alert('Export CSV feature will be implemented soon!');
                            // TODO: Implement actual CSV export
                        });
                })
                .catch(err => console.error('Export error:', err));
        }

        // Show change password modal
        function showChangePasswordModal() {
            const newPassword = prompt('Enter new admin password:');
            if (newPassword && newPassword.length >= 6) {
                alert('Password change feature will be implemented soon!\nRequested password: ' + newPassword);
                // TODO: Implement actual password change functionality
            } else if (newPassword) {
                alert('Password must be at least 6 characters long.');
            }
        }

        // Delete submission function with confirmation
        function deleteSubmission(id) {
            if (!confirm('Are you sure you want to delete this submission? This action cannot be undone.')) {
                return;
            }

            // Double confirmation for safety
            if (!confirm('FINAL WARNING: This will permanently delete submission #' + id + '. Continue?')) {
                return;
            }

            fetch('../api/delete_submission.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: id,
                    csrf_token: CSRF_TOKEN
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    alert('Submission deleted successfully');

                    // Remove the row from the table
                    const row = document.querySelector('tr[data-id="' + id + '"]');
                    if (row) {
                        row.remove();
                    }

                    // Update statistics
                    const totalEl = document.getElementById('stat-total');
                    if (totalEl) {
                        const currentTotal = parseInt(totalEl.textContent || '0', 10);
                        totalEl.textContent = Math.max(0, currentTotal - 1);
                    }

                    // Clear cache to refresh data
                    try {
                        if (window.filterCache) window.filterCache.clear();
                    } catch(e) {
                        console.error(e);
                    }
                } else {
                    alert('Error: ' + (res.message || 'Failed to delete submission'));
                }
            })
            .catch(err => {
                console.error('Delete error:', err);
                alert('Failed to delete submission. See console for details.');
            });
        }
    </script>
</body>
</html>
