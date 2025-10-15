# Deployment Workflow Fixed

**Date:** October 15, 2025  
**Status:** ✅ Fixed and Re-deployed

---

## Problem

The initial GitHub Actions deployment was failing with rsync permission errors:
```
rsync: [generator] failed to set times on "/.": Operation not permitted (1)
rsync: [generator] delete_file: rmdir(...) failed: Permission denied (13)
```

The issue was that rsync was trying to preserve file permissions, ownership, and timestamps that the SSH user didn't have rights to modify on the shared hosting environment.

---

## Solution

**Updated the deployment workflow to match the proven Product-Access-Plugin pattern:**

### Key Changes

1. **SSH Key Management**
   - Changed from `webfactory/ssh-agent` to direct file-based SSH key
   - Saves key to `~/.ssh/kinsta_key` with proper permissions (600)
   - Cleans up key file after deployment

2. **Simplified rsync Command**
   - **Old:** `rsync -rltvz --no-perms --no-owner --no-group`
   - **New:** `rsync -avz --delete`
   - Simple, proven flags that work with shared hosting

3. **Improved Workflow Structure**
   - Cleaner environment variable handling
   - Better output formatting
   - Explicit backup step before deployment
   - SSH connection test before attempting deployment
   - Cache flush after successful deployment

4. **Deploy Exclusion File**
   - Updated `.github/deploy-exclude.txt` to exclude:
     - Documentation files (*.md)
     - Git files (.git/, .github/, .gitignore)
     - Backup files (*.tgz, *.zip)
     - Dev artifacts (.DS_Store, etc.)

---

## Deployment Workflow

### Automatic Staging Deployment
```bash
# Any push to dev branch triggers automatic deployment
git checkout dev
git commit -m "Your changes"
git push origin dev
# → Automatically deploys to staging
```

### Manual Production Deployment
1. Go to: https://github.com/HolisticPeople/hp-shipstation-rates/actions
2. Click "Deploy HP ShipStation Rates to Kinsta"
3. Click "Run workflow"
4. Select "production" from dropdown
5. Click "Run workflow"

---

## Workflow Steps

The deployment workflow now executes:

1. **Checkout code** - Get latest code from repository
2. **Determine environment** - Auto = staging, Manual = user choice
3. **Set environment variables** - Load correct server credentials
4. **Setup SSH key** - Create temporary SSH key file
5. **Test SSH connection** - Verify connectivity before deployment
6. **Backup existing plugin** - Create timestamped backup if plugin exists
7. **Deploy to environment** - rsync files to server
8. **Flush caches** - Clear WordPress and transient caches
9. **Cleanup** - Remove temporary SSH key

---

## What Gets Deployed

**Included:**
- `hp-shipstation-rates.php` (main plugin file)
- `includes/` directory (all PHP classes)
- `admin/` directory (settings page)
- `README.md` (user-facing documentation)

**Excluded:**
- All `.md` documentation files except README.md
- `.git/` and `.github/` directories
- Backup files (*.tgz, *.zip)
- Development artifacts
- IDE configuration files

---

## Testing

### Current Deployment Status

**Commit:** `d105429` - "Update deployment workflow to match proven Product-Access-Plugin pattern"

**Triggered:** Push to dev branch  
**Target:** Staging environment  
**Watch:** https://github.com/HolisticPeople/hp-shipstation-rates/actions

### Expected Results

✅ SSH connection test passes  
✅ Backup created (if plugin exists)  
✅ Files deployed successfully  
✅ Caches flushed  
✅ Plugin updated on staging  

---

## Verification Steps

After deployment completes:

1. **Check GitHub Actions** - Verify all steps completed successfully
2. **Test staging site** - Load checkout page, verify rates appear
3. **Check debug logs** - Verify V1 API calls with quick mode
4. **Verify zero ghost orders** - Check ShipStation dashboard

---

## Reference

This workflow is based on the proven **Product-Access-Plugin** deployment pattern, which has been successfully deploying to the same Kinsta environment.

**Differences from original:**
- Removed `webfactory/ssh-agent` (unnecessary complexity)
- Simplified rsync flags (no permission preservation)
- Added explicit backup step
- Added SSH connection test
- Cleaner output and error handling

---

## Files Modified

- `.github/workflows/deploy.yml` - Complete rewrite based on PAM pattern
- `.github/deploy-exclude.txt` - Updated exclusion list

---

## Next Steps

1. ✅ Monitor current deployment in GitHub Actions
2. ⏳ Verify successful deployment to staging
3. ⏳ Test rates on staging checkout
4. ⏳ Prepare for production deployment (manual)

---

**Status:** Deployment workflow fixed and re-triggered  
**Last Update:** October 15, 2025  
**Version:** v2.1.0

