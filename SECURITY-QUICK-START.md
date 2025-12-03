# 🔒 Security Quick Start Guide

**CRITICAL: Complete these steps before going live!**

---

## ⚠️ BEFORE DEPLOYMENT - MUST DO

### 1. Change Admin Password (2 minutes)

**Current Password**: `indoxus2025` ⚠️ **INSECURE!**

**Steps:**
```bash
# 1. Run this PHP command to generate new hash:
php -r "echo password_hash('YOUR_NEW_PASSWORD', PASSWORD_ARGON2ID);"

# 2. Copy the output hash

# 3. Update admin/index.php line 29 with the new hash
```

**File**: `admin/index.php`
**Line**: 29
**Replace**: The entire hash string

---

### 2. Secure Database (5 minutes)

**Current DB Password**: Empty ⚠️ **INSECURE!**

**Steps:**
1. Set MySQL root password in phpMyAdmin or command line
2. Update these files with new credentials:
   - `api/contact.php` (lines 8-11)
   - `admin/index.php` (lines 22-25)
   - `api/delete_submission.php` (lines 47-50)

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'indoxus_db');
define('DB_USER', 'root');
define('DB_PASS', 'YOUR_STRONG_PASSWORD_HERE'); // ← Change this!
```

---

### 3. Update Email Settings (1 minute)

**File**: `api/contact.php`
**Lines**: 14-15

```php
define('ADMIN_EMAIL', 'your-email@indoxus.com'); // ← Your real email
define('FROM_EMAIL', 'noreply@indoxus.com');
```

---

### 4. Install SSL Certificate (10 minutes)

**Free Option**: Let's Encrypt
```bash
# Using Certbot on cPanel or command line:
sudo certbot --apache -d indoxus.com -d www.indoxus.com
```

**Verify HTTPS**:
- Visit https://indoxus.com (should work)
- Check for padlock icon in browser
- Test at: https://www.ssllabs.com/ssltest/

---

### 5. Enable Secure Cookie Flag (1 minute)

**File**: `admin/index.php`
**Line**: 17

```php
ini_set('session.cookie_secure', 1); // ← Change 0 to 1 (requires HTTPS)
```

⚠️ **Only after SSL is installed!**

---

### 6. Restrict CORS (1 minute)

**File**: `api/contact.php`
**Line**: 26

```php
header('Access-Control-Allow-Origin: *'); // ← Change * to your domain
// Should be:
header('Access-Control-Allow-Origin: https://indoxus.com');
```

---

## ✅ OPTIONAL BUT RECOMMENDED

### Set File Permissions (Linux/Unix only)

```bash
cd /path/to/indoxus

# Make cache directory writable
chmod 755 cache/

# Make all other files read-only
chmod 644 .htaccess
chmod 644 index.html
chmod 644 api/*.php
chmod 644 admin/*.php

# Set correct ownership (replace www-data with your Apache user)
chown -R www-data:www-data .
```

---

## 🧪 TESTING YOUR SECURITY

### Test Admin Panel
1. Go to `/admin/index.php`
2. Try wrong password 6 times → Should lock for 15 minutes ✅
3. Login with correct password → Should work ✅
4. Leave inactive for 30+ minutes → Should logout ✅
5. Click delete on a submission → Should ask for 2 confirmations ✅

### Test Contact Form
1. Submit form 3 times quickly → Should work ✅
2. Submit 4th time → Should show rate limit error ✅
3. Fill honeypot field (console: `document.querySelector('[name="website"]').value = "test"`)
4. Submit → Should be silently rejected ✅

### Test HTTPS
1. Visit http://indoxus.com → Should redirect to https:// ✅
2. Check browser security icon → Should show padlock ✅
3. Test headers: https://securityheaders.com/?q=indoxus.com ✅

---

## 🔐 PASSWORD RECOMMENDATIONS

### Admin Password Requirements:
- ✅ At least 16 characters
- ✅ Mix of uppercase and lowercase
- ✅ Include numbers and symbols
- ✅ No dictionary words
- ✅ Unique (not used elsewhere)

**Good Examples**:
- `Tr0p!cal$unSh!ne2025#Indoxus`
- `Bl@ckC0ff33&Pak!stan*2025`
- `5ecur3_P@$$w0rd!2025#Admin`

### Database Password:
- At least 20 characters
- Completely random
- Store securely (password manager)

**Generate Random Password:**
```bash
# On Linux/Mac:
openssl rand -base64 32

# Or use online generator (secure site only):
# https://passwordsgenerator.net/
```

---

## 📊 SECURITY MONITORING

### Daily Checks
- Check failed login attempts: `cache/admin_login_*.json`
- Review contact submissions for spam patterns
- Monitor Apache error logs

### Weekly Checks
- Review all admin panel activity
- Check for security updates (PHP, Apache, MySQL)
- Backup database

### Monthly Checks
- Full security audit
- Test all security features
- Update SSL certificate if needed
- Review and rotate admin password

---

## 🆘 EMERGENCY CONTACTS

If you suspect a security breach:

1. **Immediately**: Change admin password
2. **Check**: Apache logs for suspicious activity
3. **Review**: Database for unauthorized changes
4. **Contact**: Your hosting provider security team

**Log Locations** (typical):
- Apache: `/var/log/apache2/error.log`
- PHP: `/var/log/php_errors.log`
- Custom: `cache/*.json` files

---

## ✅ DEPLOYMENT CHECKLIST

Print this and check off each item:

- [ ] Changed admin password from default
- [ ] Set strong database password
- [ ] Updated email addresses
- [ ] Installed SSL certificate
- [ ] Enabled secure cookie flag
- [ ] Restricted CORS to your domain only
- [ ] Set correct file permissions
- [ ] Tested admin login rate limiting
- [ ] Tested contact form rate limiting
- [ ] Tested honeypot protection
- [ ] Verified HTTPS redirect works
- [ ] Checked security headers (securityheaders.com)
- [ ] Tested SSL certificate (ssllabs.com)
- [ ] Set up database backups
- [ ] Enabled error logging
- [ ] Documented all passwords (secure location!)

---

## 📚 NEXT STEPS

After deployment, consider:
1. **Two-Factor Authentication** for admin
2. **Google reCAPTCHA** for contact form
3. **Cloudflare** for DDoS protection
4. **Automated backups** to cloud storage
5. **Monitoring service** (UptimeRobot, etc.)

---

**Questions?** Review [SECURITY-AUDIT.md](SECURITY-AUDIT.md) for detailed documentation.

**Last Updated**: December 3, 2025
**Your Security Status**: 🔒 Production-Ready (after completing checklist above)
