# ⚡ Quick Deployment Setup

**5-Minute Setup for Automated Deployment**

---

## 🎯 Quick Start (Copy-Paste Commands)

### Step 1: Initialize Git Repository

```bash
cd c:\xampp\htdocs\indoxus

# Initialize Git
git init

# Add all files
git add .

# Create first commit
git commit -m "Initial commit - Indoxus Communications with CI/CD"

# Set main as default branch
git branch -M main
```

---

### Step 2: Create GitHub Repository

**Option A - Using GitHub CLI (if installed):**
```bash
gh repo create indoxus --private --source=. --remote=origin --push
```

**Option B - Manual (recommended):**
1. Go to: https://github.com/new
2. Repository name: `indoxus`
3. Privacy: **Private** (recommended)
4. Click "Create repository"

Then run:
```bash
# Replace YOUR_USERNAME with your GitHub username
git remote add origin https://github.com/YOUR_USERNAME/indoxus.git
git push -u origin main
```

---

### Step 3: Get FTP Credentials from Namecheap

1. Login: https://www.namecheap.com
2. Go to: **Hosting List → cPanel**
3. Navigate to: **Files → FTP Accounts**
4. Copy these values:

```
FTP Server: __________________ (e.g., ftp.yourdomain.com)
Username: ____________________ (e.g., user@yourdomain.com)
Password: ____________________ (create new or use existing)
```

---

### Step 4: Add Secrets to GitHub

1. Go to: `https://github.com/YOUR_USERNAME/indoxus/settings/secrets/actions`

2. Click **"New repository secret"** and add:

**Secret 1:**
```
Name: FTP_SERVER
Value: [Your FTP server from Step 3]
```

**Secret 2:**
```
Name: FTP_USERNAME
Value: [Your FTP username from Step 3]
```

**Secret 3:**
```
Name: FTP_PASSWORD
Value: [Your FTP password from Step 3]
```

---

### Step 5: Deploy!

```bash
# Make a small change (add a comment to any file)
echo "<!-- Deployed via GitHub Actions -->" >> index.html

# Commit and push
git add .
git commit -m "First automated deployment"
git push
```

---

## 🎉 Check Deployment Status

1. Go to: `https://github.com/YOUR_USERNAME/indoxus/actions`
2. Click on the running workflow
3. Watch the deployment progress
4. ✅ Success = Your website is now live!

---

## 🔍 Verify Deployment

**Check these URLs:**
```
✅ Main website: https://yourdomain.com
✅ Admin panel: https://yourdomain.com/admin/index.php
✅ Contact form: https://yourdomain.com/#contact
```

---

## 🚨 If Deployment Fails

### Check FTP Credentials:
```bash
# Test FTP connection manually:
# Windows: Use FileZilla
# Mac/Linux:
ftp ftp.yourdomain.com
# Enter username and password
```

### Common Issues:

**Error: "Login incorrect"**
- ✅ Username must include `@domain.com`
- ✅ Password must be exact (no spaces)

**Error: "Cannot change directory"**
- ✅ Check `server-dir` in `.github/workflows/deploy.yml`
- ✅ Default is `/public_html/`

**Error: "Connection timeout"**
- ✅ Check FTP server is accessible
- ✅ Verify Namecheap hosting is active

---

## 📋 Daily Workflow

### Making Updates:

```bash
# 1. Make changes in VS Code
# Edit any file

# 2. Test locally
# Open http://localhost/indoxus

# 3. Commit and push
git add .
git commit -m "Updated [what you changed]"
git push

# 4. Wait 2-5 minutes
# GitHub Actions automatically deploys

# 5. Check live site
# Visit https://yourdomain.com
```

---

## 🎛️ Useful Git Commands

### Check status:
```bash
git status
```

### View commit history:
```bash
git log --oneline
```

### Undo last commit (local only):
```bash
git reset --soft HEAD~1
```

### Create a backup branch:
```bash
git branch backup-$(date +%Y%m%d)
```

### Switch to staging branch:
```bash
git checkout -b staging
git push -u origin staging
```

---

## ⚙️ Customization

### Deploy to Different Directory:

**Edit `.github/workflows/deploy.yml` line 22:**
```yaml
server-dir: /public_html/indoxus/  # Your custom path
```

### Deploy on Different Branch:

**Edit `.github/workflows/deploy.yml` line 5:**
```yaml
branches:
  - production  # Deploy from 'production' branch instead of 'main'
```

### Exclude More Files:

**Edit `.github/workflows/deploy.yml` lines 24-33:**
```yaml
exclude: |
  **/.git*
  **/node_modules/**
  **/test/**
  **/your-folder/**
```

---

## 🔒 Security Reminders

**NEVER commit these files:**
- ❌ `.env` with credentials
- ❌ Database backups (`.sql`)
- ❌ FTP credentials
- ❌ Private keys

**Always use GitHub Secrets for:**
- ✅ FTP credentials
- ✅ Database passwords
- ✅ API keys
- ✅ Any sensitive data

---

## 📚 Next Steps

After deployment works:

1. **Set up staging environment**
   ```bash
   git checkout -b staging
   # Deploy to /public_html/staging/
   ```

2. **Add deployment notifications**
   - Slack webhook
   - Discord webhook
   - Email alerts

3. **Implement testing**
   - Add PHP linting
   - Add automated tests
   - Check for broken links

4. **Monitor deployments**
   - Set up uptime monitoring
   - Enable GitHub notifications
   - Review deployment logs weekly

---

## ✅ Deployment Checklist

**Before First Deployment:**
- [ ] Git repository initialized
- [ ] GitHub repository created
- [ ] FTP credentials obtained
- [ ] GitHub Secrets configured
- [ ] Database created on Namecheap
- [ ] SSL certificate installed
- [ ] Admin password changed

**After First Deployment:**
- [ ] Website loads correctly
- [ ] Images display properly
- [ ] Contact form works
- [ ] Admin panel accessible
- [ ] HTTPS redirect works
- [ ] Mobile responsive

**Ongoing:**
- [ ] Test before pushing to main
- [ ] Review deployment logs
- [ ] Monitor website uptime
- [ ] Backup database weekly
- [ ] Update dependencies monthly

---

## 🎯 Summary

**Your new workflow:**

```
1. Edit code in VS Code
2. git add . && git commit -m "message"
3. git push
4. ☕ Wait 2-5 minutes
5. ✅ Website automatically updated!
```

**No more:**
- ❌ Manual FTP uploads
- ❌ Forgetting which files changed
- ❌ Accidentally overwriting files
- ❌ Inconsistent deployments

---

**Congratulations! You have professional automated deployment! 🚀**

Read [DEPLOYMENT-GUIDE.md](DEPLOYMENT-GUIDE.md) for advanced configuration.

---

**Quick Links:**
- [Full Deployment Guide](DEPLOYMENT-GUIDE.md)
- [Security Documentation](SECURITY-FEATURES.md)
- [Backend Setup](BACKEND-SETUP.md)

**Support:**
- GitHub Actions: https://docs.github.com/en/actions
- Namecheap Hosting: https://www.namecheap.com/support/

---

**Last Updated:** December 3, 2025
