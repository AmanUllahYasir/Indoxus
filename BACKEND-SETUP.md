# 🚀 BACKEND SETUP GUIDE - Email & Database Integration

## ✅ What's Included

Your website now has a **complete backend system** for handling contact forms with:

- ✅ **Email Notifications** - Sends emails to admin AND user confirmation
- ✅ **SQL Database Storage** - All submissions stored permanently
- ✅ **Admin Dashboard** - View and manage all submissions
- ✅ **Status Tracking** - Mark as read, replied, archived
- ✅ **Search & Filter** - Find submissions easily
- ✅ **Security** - Input validation, SQL injection protection

---

## 📁 Backend Files Structure

```
indoxus-exact/
├── api/
│   ├── contact.php          # Main form handler (email + database)
│   └── get_submission.php   # API for admin dashboard
├── admin/
│   └── admin.php            # Admin dashboard to view submissions
├── database/
│   └── schema.sql           # Database setup file
└── BACKEND-SETUP.md         # This file
```

---

## 🔧 SETUP STEPS

### Step 1: Database Setup (5 minutes)

#### Option A: Using phpMyAdmin (Easiest)
1. Login to phpMyAdmin (usually at `yourdomain.com/phpmyadmin`)
2. Click "New" to create database
3. Name it: `indoxus_db`
4. Click "SQL" tab
5. Copy entire contents of `database/schema.sql`
6. Paste and click "Go"
7. Done! Database tables created

#### Option B: Using MySQL Command Line
```bash
mysql -u your_username -p
CREATE DATABASE indoxus_db;
USE indoxus_db;
SOURCE /path/to/database/schema.sql;
EXIT;
```

---

### Step 2: Configure Database Connection (2 minutes)

Update these 3 files with your database credentials:

#### File 1: `api/contact.php` (Lines 10-13)
```php
define('DB_HOST', 'localhost');           // Usually 'localhost'
define('DB_NAME', 'indoxus_db');          // Database name
define('DB_USER', 'your_db_username');    // Your MySQL username
define('DB_PASS', 'your_db_password');    // Your MySQL password
```

#### File 2: `admin/admin.php` (Lines 10-13)
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'indoxus_db');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
```

#### File 3: `api/get_submission.php` (Lines 8-11)
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'indoxus_db');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
```

---

### Step 3: Configure Email Settings (2 minutes)

Edit `api/contact.php` (Lines 16-18):

```php
define('ADMIN_EMAIL', 'info@indoxus.com');     // Where to receive submissions
define('FROM_EMAIL', 'noreply@indoxus.com');   // From address
define('FROM_NAME', 'Indoxus Website');        // From name
```

**IMPORTANT Email Setup:**

Most shared hosting blocks `mail()` function. You have 3 options:

#### Option A: Use PHPMailer with SMTP (Recommended)
1. Install PHPMailer: `composer require phpmailer/phpmailer`
2. Use Gmail/Office365/SendGrid SMTP
3. More reliable delivery

#### Option B: Use Hosting Email
- Works if your hosting allows `mail()` function
- Test first with a simple email

#### Option C: Use Email Service API
- SendGrid, Mailgun, Amazon SES
- Most reliable for production

---

### Step 4: Set Admin Password (1 minute)

Edit `admin/admin.php` (Line 13):

```php
$ADMIN_PASSWORD = 'indoxus2025';  // CHANGE THIS TO YOUR PASSWORD!
```

**⚠️ SECURITY WARNING:**
This is a basic password system. For production, implement proper authentication with:
- Hashed passwords in database
- Session timeouts
- HTTPS only
- IP restrictions

---

### Step 5: Upload Files to Server

Upload these folders to your web server:
```
your-website-root/
├── api/           → Upload to server
├── admin/         → Upload to server  
├── database/      → Keep local (don't upload)
├── assets/        → Already uploaded
└── index.html     → Already uploaded
```

---

## ✅ TESTING

### Test 1: Contact Form
1. Go to your website
2. Fill out contact form
3. Submit
4. Check if you receive email
5. Check admin dashboard for submission

### Test 2: Admin Dashboard
1. Go to `yourdomain.com/admin/admin.php`
2. Enter your admin password
3. You should see all submissions
4. Click "View" to see details

### Test 3: Database
1. Login to phpMyAdmin
2. Select `indoxus_db` database
3. Click on `contact_submissions` table
4. You should see your test submission

---

## 📊 USING THE ADMIN DASHBOARD

### Access Dashboard:
**URL:** `https://yourdomain.com/admin/admin.php`
**Password:** Whatever you set in Step 4

### Features:
- **View all submissions** - See complete list
- **Filter by status** - New, Read, Replied
- **Search** - Find by name, email, or message
- **View details** - Click to see full submission
- **Auto mark as read** - When viewing details

### Submission Statuses:
- 🔴 **NEW** - Just received
- 🔵 **READ** - Opened and viewed
- 🟢 **REPLIED** - You've responded
- ⚫ **ARCHIVED** - Completed/old

---

## 🔒 SECURITY CHECKLIST

- [ ] Changed default admin password
- [ ] Database credentials not in public folder
- [ ] Using HTTPS (SSL certificate)
- [ ] Regular database backups
- [ ] Updated PHP to latest version
- [ ] Disabled error display in production
- [ ] Added rate limiting (optional)
- [ ] Restricted admin folder by IP (optional)

---

## 📧 EMAIL TROUBLESHOOTING

### Emails not sending?

**Check 1: Test basic mail function**
Create `test-email.php`:
```php
<?php
$result = mail('your@email.com', 'Test', 'Testing email');
echo $result ? 'Email sent!' : 'Email failed!';
?>
```

**Check 2: Use SMTP instead**
Install PHPMailer and use SMTP:
```php
composer require phpmailer/phpmailer
```

**Check 3: Check spam folder**
- Admin emails might go to spam
- Add sender to contacts
- Use professional FROM address

**Check 4: Contact your host**
- Ask if `mail()` function is enabled
- Request SMTP credentials
- Some hosts require verification

---

## 🗄️ DATABASE MANAGEMENT

### View all submissions (SQL):
```sql
SELECT * FROM contact_submissions ORDER BY submitted_at DESC;
```

### Count by status:
```sql
SELECT status, COUNT(*) 
FROM contact_submissions 
GROUP BY status;
```

### Export submissions:
```sql
SELECT name, email, phone, message, submitted_at 
FROM contact_submissions 
INTO OUTFILE '/tmp/submissions.csv' 
FIELDS TERMINATED BY ',' 
ENCLOSED BY '"' 
LINES TERMINATED BY '\n';
```

### Backup database:
```bash
mysqldump -u username -p indoxus_db > backup.sql
```

---

## 🚀 OPTIONAL ENHANCEMENTS

### 1. Email Notifications via Webhook
- Set up Zapier/Make automation
- Get Slack/Discord notifications
- Auto-add to CRM

### 2. Auto-Responder
- Send detailed confirmation email
- Include service information
- Add expected response time

### 3. Analytics Integration
- Track form conversion rate
- Monitor submission sources
- A/B test different forms

### 4. CRM Integration
- Auto-create leads in Salesforce
- Sync with HubSpot
- Add to Google Sheets

---

## ⚠️ COMMON ISSUES

### Issue: "Database connection failed"
**Solution:** Check database credentials in all 3 PHP files

### Issue: "403 Forbidden" on API calls
**Solution:** Check .htaccess file, ensure PHP files can execute

### Issue: Emails not received
**Solution:** Check spam, verify FROM email, use SMTP

### Issue: Form submits but no data saved
**Solution:** Check database connection, verify table exists

### Issue: Admin password not working
**Solution:** Check `admin.php` line 13, ensure no extra spaces

---

## 📞 NEED HELP?

### Quick Tests:
1. **Test database:** Run query in phpMyAdmin
2. **Test email:** Use test-email.php script
3. **Test form:** Submit with browser console open
4. **Check logs:** Look at server error logs

### Debug Mode:
Add to top of `contact.php`:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

---

## ✨ CONGRATULATIONS!

Your website now has:
- ✅ Working contact form
- ✅ Email notifications
- ✅ Database storage
- ✅ Admin dashboard
- ✅ Complete submission tracking

**Test everything before going live!**

---

## 📝 CREDENTIALS SUMMARY

Save these somewhere safe:

```
DATABASE:
Host: localhost
Name: indoxus_db
User: your_db_username
Pass: your_db_password

ADMIN DASHBOARD:
URL: yourdomain.com/admin/admin.php
Password: [your_admin_password]

EMAIL:
Admin: info@indoxus.com
From: noreply@indoxus.com
```

---

**Ready to receive inquiries!** 🎉

For any issues, check the troubleshooting section above.
