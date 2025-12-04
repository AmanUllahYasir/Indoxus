# 🚀 Deployment Guide - Namecheap Hosting

**Automated CI/CD Pipeline with GitHub Actions**

---

## 📋 What This Guide Covers

This guide shows you how to set up **automatic deployment** from GitHub to Namecheap hosting. Every time you push code to GitHub, it automatically updates your live website!

---

## 🎯 How It Works

```
Local Development → Git Push → GitHub Actions → FTP Upload → Namecheap Hosting → Live Website
```

1. You make changes locally in VS Code
2. Commit and push to GitHub
3. GitHub Actions automatically runs
4. Files are uploaded via FTP to Namecheap
5. Your website is live!

---

## ✅ Prerequisites

- [ ] Namecheap hosting active with cPanel access
- [ ] GitHub account
- [ ] Git installed on your computer
- [ ] FTP credentials from Namecheap

---

## 🚀 Setup Instructions

### Step 1: Get Namecheap FTP Credentials

1. **Login to Namecheap**
   - Go to: https://www.namecheap.com
   - Login to your account

2. **Access cPanel**
   - Hosting List → Manage
   - Click "Go to cPanel"

3. **Get FTP Credentials**
   ```
   Files → FTP Accounts

   Note down:
   - FTP Server: ftp.yourdomain.com (or IP address)
   - Username: yourusername@yourdomain.com
   - Password: (create new or use existing)
   - Port: 21
   ```

---

### Step 2: Create GitHub Repository

1. **Create repo on GitHub**
   ```
   Go to: https://github.com/new

   Repository name: indoxus
   Description: Indoxus Communications Website
   Privacy: Private (recommended) or Public

   Click "Create repository"
   ```

2. **Initialize Git locally** (if not already done)
   ```bash
   cd c:\xampp\htdocs\indoxus
   git init
   git add .
   git commit -m "Initial commit - Indoxus Communications"
   git branch -M main
   git remote add origin https://github.com/YOUR_USERNAME/indoxus.git
   git push -u origin main
   ```

---

### Step 3: Add Secrets to GitHub

**IMPORTANT: Never commit FTP credentials to Git!**

1. **Go to your repository on GitHub**
   - https://github.com/YOUR_USERNAME/indoxus

2. **Add secrets**
   ```
   Settings → Secrets and variables → Actions → New repository secret

   Add these 3 secrets:

   Name: FTP_SERVER
   Value: ftp.yourdomain.com

   Name: FTP_USERNAME
   Value: yourusername@yourdomain.com

   Name: FTP_PASSWORD
   Value: your_ftp_password
   ```

---

### Step 4: Deploy!

The workflow file (`.github/workflows/deploy.yml`) is already created!

**To deploy:**

```bash
# Make any change to your website
# Then commit and push:

git add .
git commit -m "Update website"
git push
```

**GitHub Actions will automatically:**
1. Detect the push
2. Run the deployment workflow
3. Upload files to Namecheap via FTP
4. Your website is updated!

---

## 📊 Monitor Deployments

**View deployment status:**
```
1. Go to your GitHub repository
2. Click "Actions" tab
3. See all deployment runs
4. Click any run to see details
```

**Deployment log shows:**
- ✅ Which files were uploaded
- ✅ Upload progress
- ✅ Success/failure status
- ✅ Any errors

---

## 🔧 Workflow Configuration

### Current Settings:

**Triggers:**
- ✅ Automatic on push to `main` branch
- ✅ Manual trigger available (workflow_dispatch)

**Upload Directory:**
- Target: `/public_html/` (Namecheap default)
- Change in `deploy.yml` if your directory is different

**Excluded Files:**
```yaml
- Documentation files (*.md)
- Git files (.git, .github)
- Cache files (*.json in cache/)
- Development files (.vscode, .idea)
```

### Customize Upload Directory:

If your files go to a different directory:

**Edit `.github/workflows/deploy.yml` line 22:**
```yaml
server-dir: /public_html/indoxus/  # Example: subdirectory
```

---

## 🎛️ Advanced Configuration

### Deploy Only Specific Files

Add to `exclude` section in `deploy.yml`:
```yaml
exclude: |
  **/test/**
  **/backup/**
  **/*.backup
```

### Deploy to Staging First

Create separate workflow for staging:

**`.github/workflows/deploy-staging.yml`:**
```yaml
name: Deploy to Staging

on:
  push:
    branches:
      - develop  # Deploy develop branch to staging

jobs:
  deploy:
    # ... same as production but different server-dir
    server-dir: /staging/
```

### Deploy on Schedule

Add scheduled deployment:
```yaml
on:
  schedule:
    - cron: '0 2 * * *'  # Deploy daily at 2 AM UTC
  push:
    branches:
      - main
```

---

## 🐛 Troubleshooting

### ❌ Deployment Fails

**Check:**
1. FTP credentials are correct in GitHub Secrets
2. FTP server is accessible (not blocked by firewall)
3. Namecheap hosting is active
4. Directory path is correct (`/public_html/`)

**View error logs:**
```
GitHub → Actions → Failed deployment → View logs
```

### ❌ Files Not Uploading

**Common causes:**
1. FTP username missing `@domain.com`
2. Wrong directory path
3. File permissions on server
4. Files excluded by `.gitignore` or workflow exclude

**Fix:**
```bash
# Check what's in your Git:
git ls-files

# Force add a file if ignored:
git add -f path/to/file.php
```

### ❌ Connection Timeout

**Solutions:**
1. Use passive FTP mode (already configured in workflow)
2. Check Namecheap FTP server is not blocking GitHub IPs
3. Contact Namecheap support if persistent

---

## 🔒 Security Best Practices

### ✅ DO:
- ✅ Store FTP credentials in GitHub Secrets
- ✅ Use `.gitignore` to exclude sensitive files
- ✅ Use private repository for extra security
- ✅ Regularly rotate FTP password
- ✅ Review deployment logs

### ❌ DON'T:
- ❌ Commit FTP credentials to Git
- ❌ Commit database passwords to Git
- ❌ Push cache files or backups
- ❌ Use public repository for production code (unless intentional)

---

## 📝 Workflow Commands

### Manual Deployment

```
1. Go to GitHub → Actions
2. Select "Deploy to Namecheap via FTP"
3. Click "Run workflow"
4. Select branch
5. Click "Run workflow"
```

### Check Last Deployment

```bash
# View latest commit that was deployed:
git log -1
```

### Rollback Deployment

```bash
# Revert to previous commit:
git revert HEAD
git push

# Or reset to specific commit:
git reset --hard COMMIT_HASH
git push --force
```

---

## 🎯 Deployment Checklist

Before first deployment:

- [ ] GitHub repository created
- [ ] FTP credentials added to GitHub Secrets
- [ ] `.gitignore` configured
- [ ] Database credentials updated in PHP files
- [ ] Admin password hash updated
- [ ] SSL certificate installed on Namecheap
- [ ] Test deployment to staging (optional)
- [ ] Backup current website (if exists)

After first deployment:

- [ ] Verify website loads: https://yourdomain.com
- [ ] Test contact form submission
- [ ] Test admin panel login: https://yourdomain.com/admin/
- [ ] Check all images load
- [ ] Verify HTTPS is working
- [ ] Test on mobile devices

---

## 🚀 Alternative Deployment Methods

If GitHub Actions doesn't work for you:

### Option 1: Git FTP (Command Line)
```bash
# Install git-ftp
git ftp init -u username -p password ftp://ftp.yourdomain.com/public_html/

# Future deployments:
git ftp push
```

### Option 2: FileZilla (Manual FTP)
```
1. Download FileZilla
2. Connect to FTP server
3. Drag and drop files
4. Manual but simple
```

### Option 3: cPanel File Manager
```
1. Zip your files
2. Upload zip to cPanel File Manager
3. Extract on server
4. Delete zip
```

---

## 📊 Deployment Workflow Diagram

```
┌─────────────────┐
│ Local Changes   │
│ (VS Code)       │
└────────┬────────┘
         │
         │ git commit
         │ git push
         ▼
┌─────────────────┐
│ GitHub Repo     │
│ (main branch)   │
└────────┬────────┘
         │
         │ Triggers
         ▼
┌─────────────────┐
│ GitHub Actions  │
│ (Deploy Job)    │
└────────┬────────┘
         │
         │ FTP Upload
         ▼
┌─────────────────┐
│ Namecheap       │
│ /public_html/   │
└────────┬────────┘
         │
         │ Serves
         ▼
┌─────────────────┐
│ Live Website    │
│ yourdomain.com  │
└─────────────────┘
```

---

## 💡 Pro Tips

1. **Branch Strategy:**
   ```
   main → Production (auto-deploy)
   develop → Staging (manual review)
   feature/* → Local testing only
   ```

2. **Pre-deployment Checks:**
   - Run local tests first
   - Check PHP syntax: `php -l file.php`
   - Test on local XAMPP

3. **Deployment Notifications:**
   - GitHub sends email on failed deployments
   - Enable Slack/Discord webhooks for notifications

4. **Database Migrations:**
   - Deploy schema changes manually first
   - Then deploy code
   - Never auto-deploy database changes

5. **Zero-Downtime Deployment:**
   - Upload to `/public_html_new/`
   - Test thoroughly
   - Rename directories atomically

---

## 🆘 Need Help?

**Common Issues:**

1. **"Permission denied" error**
   - Check FTP user has write permissions
   - Contact Namecheap support

2. **"Files not showing up"**
   - Wrong directory path
   - Check cPanel File Manager
   - Verify FTP uploaded to correct location

3. **"Deployment takes too long"**
   - Exclude large files from Git
   - Use `.gitignore` properly
   - Compress images before committing

**Get Support:**
- Namecheap Support: https://www.namecheap.com/support/
- GitHub Actions Docs: https://docs.github.com/en/actions
- This repository Issues tab

---

## ✅ Success Checklist

Your CI/CD is working if:

- ✅ Push to GitHub triggers automatic deployment
- ✅ Files appear on live server within 2-5 minutes
- ✅ Website updates reflect your latest code
- ✅ No errors in GitHub Actions logs
- ✅ FTP credentials remain secure in Secrets

---

**Congratulations! You now have automated deployment! 🎉**

Every code change you push to GitHub will automatically go live on your website!

---

**Last Updated:** December 3, 2025
**Tested With:** Namecheap cPanel Hosting, GitHub Actions
**Deployment Time:** ~2-5 minutes per deployment
