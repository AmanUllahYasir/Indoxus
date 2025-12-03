<?php
/**
 * INDOXUS COMMUNICATIONS - CONTACT FORM HANDLER
 * Handles form submissions with email notifications and database storage
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'indoxus_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Email configuration
define('ADMIN_EMAIL', 'info@indoxus.com'); // Change to your email
define('FROM_EMAIL', 'noreply@indoxus.com');
define('FROM_NAME', 'Indoxus Website');

// Security Headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CORS Headers (restrict to your domain in production)
header('Access-Control-Allow-Origin: *'); // Change to your domain in production
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Prevent PHP notices/warnings from being output to the client JSON
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Rate limiting - max 3 submissions per IP per hour
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_limit_file = __DIR__ . '/../cache/rate_limit_' . md5($ip) . '.json';
$rate_limit_max = 3; // max submissions
$rate_limit_window = 3600; // 1 hour in seconds

if (file_exists($rate_limit_file)) {
    $rate_data = json_decode(file_get_contents($rate_limit_file), true);
    $current_time = time();

    // Clean old entries
    $rate_data['attempts'] = array_filter($rate_data['attempts'], function($timestamp) use ($current_time, $rate_limit_window) {
        return ($current_time - $timestamp) < $rate_limit_window;
    });

    // Check if rate limit exceeded
    if (count($rate_data['attempts']) >= $rate_limit_max) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many submissions. Please try again later.']);
        exit;
    }

    // Add current attempt
    $rate_data['attempts'][] = $current_time;
} else {
    $rate_data = ['attempts' => [time()]];
}

// Save rate limit data
file_put_contents($rate_limit_file, json_encode($rate_data), LOCK_EX);

// Get form data
$data = json_decode(file_get_contents('php://input'), true);

// Honeypot check - if website field is filled, it's spam
$honeypot = sanitize($data['website'] ?? '');
if (!empty($honeypot)) {
    // Silently reject spam without informing the bot
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Thank you for contacting us!']);
    exit;
}

// Validate required fields
$name = sanitize($data['name'] ?? '');
$email = sanitize($data['email'] ?? '');
$job_title = sanitize($data['job_title'] ?? '');
$company = sanitize($data['company'] ?? '');
$country = sanitize($data['country'] ?? '');
$message = sanitize($data['message'] ?? '');
$service = sanitize($data['service'] ?? 'General Inquiry');

// Validation
$errors = [];

if (empty($name)) {
    $errors[] = 'Name is required';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required';
}

if (empty($message)) {
    $errors[] = 'Message is required';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Database connection
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
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Insert into database
try {
    $sql = "INSERT INTO contact_submissions (name, email, job_title, company, country, service, message, ip_address, user_agent, submitted_at) 
            VALUES (:name, :email, :job_title, :company, :country, :service, :message, :ip, :user_agent, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':job_title' => $job_title,
        ':company' => $company,
        ':country' => $country,
        ':service' => $service,
        ':message' => $message,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    $submission_id = $pdo->lastInsertId();
    
} catch (PDOException $e) {
    error_log("Database insert failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save submission']);
    exit;
}

// Send email to admin
$emailSent = sendAdminEmail($name, $email, $job_title, $company, $country, $service, $message, $submission_id);

// Send confirmation email to user
sendUserConfirmation($name, $email);

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Thank you for contacting us! We will get back to you soon.',
    'submission_id' => $submission_id,
    'email_sent' => $emailSent
]);

/**
 * Send email to admin
 */
function sendAdminEmail($name, $email, $job_title, $company, $country, $service, $message, $id) {
    $subject = "New Contact Form Submission - Indoxus Communications";
    
    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2C5F6F; color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; margin: 20px 0; }
            .field { margin: 15px 0; }
            .label { font-weight: bold; color: #2C5F6F; }
            .value { margin-top: 5px; padding: 10px; background: white; border-left: 3px solid #2C5F6F; }
            .footer { text-align: center; color: #666; font-size: 12px; padding: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>New Contact Form Submission</h2>
                <p>Submission ID: #$id</p>
            </div>
            <div class='content'>
                <div class='field'>
                    <div class='label'>Name:</div>
                    <div class='value'>" . htmlspecialchars($name) . "</div>
                </div>
                <div class='field'>
                    <div class='label'>Email:</div>
                    <div class='value'>" . htmlspecialchars($email) . "</div>
                </div>
                <div class='field'>
                    <div class='label'>Job Title:</div>
                    <div class='value'>" . htmlspecialchars($job_title) . "</div>
                </div>
                <div class='field'>
                    <div class='label'>Company:</div>
                    <div class='value'>" . htmlspecialchars($company) . "</div>
                </div>
                <div class='field'>
                    <div class='label'>Country:</div>
                    <div class='value'>" . htmlspecialchars($country) . "</div>
                </div>
                <div class='field'>
                    <div class='label'>Service Interest:</div>
                    <div class='value'>" . htmlspecialchars($service) . "</div>
                </div>
                <div class='field'>
                    <div class='label'>Message:</div>
                    <div class='value'>" . nl2br(htmlspecialchars($message)) . "</div>
                </div>
            </div>
            <div class='footer'>
                <p>This email was sent from your Indoxus Communications website contact form.</p>
                <p>Submitted on: " . date('F j, Y, g:i a') . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
        'Reply-To: ' . $email,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    return mail(ADMIN_EMAIL, $subject, $htmlBody, implode("\r\n", $headers));
}

/**
 * Send confirmation email to user
 */
function sendUserConfirmation($name, $email) {
    $subject = "Thank you for contacting Indoxus Communications";
    
    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2C5F6F; color: white; padding: 30px; text-align: center; }
            .content { padding: 30px; background: #f9f9f9; }
            .footer { text-align: center; color: #666; font-size: 12px; padding: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Thank You for Contacting Us!</h2>
            </div>
            <div class='content'>
                <p>Dear " . htmlspecialchars($name) . ",</p>
                <p>Thank you for reaching out to Indoxus Communications. We have received your message and our team will review it shortly.</p>
                <p>We typically respond within 24-48 hours during business days.</p>
                <p>In the meantime, feel free to explore our services at <a href='https://indoxus.com'>indoxus.com</a></p>
                <p>Best regards,<br><strong>Indoxus Communications Team</strong></p>
            </div>
            <div class='footer'>
                <p>Indoxus Communications Private Limited<br>Islamabad - Pakistan</p>
                <p>&copy; Indoxus.com - All Rights Reserved. 2025-26</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    return mail($email, $subject, $htmlBody, implode("\r\n", $headers));
}

/**
 * Sanitize input data
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

