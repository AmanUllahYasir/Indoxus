<?php
/**
 * INDOXUS COMMUNICATIONS - ADMIN DASHBOARD
 * Simple interface to view contact form submissions
 */

session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'indoxus_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Simple authentication (improve this for production!)
$ADMIN_PASSWORD = 'indoxus2025'; // CHANGE THIS!

// Handle login
if (isset($_POST['login'])) {
    if ($_POST['password'] === $ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = "Invalid password";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
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

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
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
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .header { background: #2C5F6F; color: white; padding: 20px; }
        .header h1 { font-size: 24px; }
        .header .logout { float: right; color: white; text-decoration: none; padding: 8px 16px; background: rgba(255,255,255,0.2); border-radius: 5px; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .stat-card h3 { font-size: 14px; color: #666; margin-bottom: 10px; }
        .stat-card .number { font-size: 32px; font-weight: bold; color: #2C5F6F; }
        .filters { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 15px; }
        .filters input, .filters select { padding: 10px; border: 2px solid #ddd; border-radius: 5px; }
        .filters button { padding: 10px 20px; background: #2C5F6F; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .table-container { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #2C5F6F; color: white; padding: 15px; text-align: left; font-weight: 600; }
        td { padding: 15px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f9f9f9; }
        .status { padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: bold; }
        .status.new { background: #FEE; color: #C00; }
        .status.read { background: #EEF; color: #00C; }
        .status.replied { background: #EFE; color: #0C0; }
        .view-btn { padding: 6px 12px; background: #2C5F6F; color: white; text-decoration: none; border-radius: 5px; font-size: 12px; }
        .view-btn:hover { background: #1A2332; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { background: white; margin: 50px auto; max-width: 600px; padding: 30px; border-radius: 10px; max-height: 80vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close { font-size: 28px; cursor: pointer; color: #666; }
        .field { margin: 15px 0; }
        .field label { font-weight: bold; color: #2C5F6F; display: block; margin-bottom: 5px; }
        .field .value { padding: 10px; background: #f9f9f9; border-left: 3px solid #2C5F6F; }
    </style>
</head>
<body>
    <div class="header">
        <h1>📧 Contact Submissions Dashboard</h1>
        <a href="?logout" class="logout">Logout</a>
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
        function applyFilters(e) {
            e.preventDefault();
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('searchInput').value.trim();

            // Fetch filtered results via AJAX
            fetch('../api/filter_submissions.php?status=' + encodeURIComponent(status) + '&search=' + encodeURIComponent(search))
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        // Update table rows
                        document.querySelector('tbody').innerHTML = res.rows_html;

                        // Update stats
                        document.getElementById('stat-total').textContent = res.stats.total;
                        document.getElementById('stat-new').textContent = res.stats.new;
                        document.getElementById('stat-read').textContent = res.stats.read;
                        document.getElementById('stat-replied').textContent = res.stats.replied;

                        // Keep URL clean (no query params)
                        window.history.replaceState({}, '', 'admin.php');
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
    </script>
</body>
</html>
