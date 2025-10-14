# GitHub Actions Deployment Guide – OneTeam API Fix

Quick visual guide to deploy the fix to `wc-shipstation-shipping-pro`.

---

## Deploy to Production

1. Go to your repo → Actions
2. Select: "Deploy OneTeam fix to Kinsta"
3. Click "Run workflow"
4. Choose:
   - Branch: `main`
   - Environment: `production`
5. Run and monitor the steps until you see the success message.

---

## Current Deployment Flow

```
Local dev
  └─ git push origin dev
        ↓
Auto deploy to STAGING
  └─ Verify fix
        ↓
Merge to main
  └─ Actions → Run workflow → production
        ↓
Deploy to PRODUCTION
```

---

## What to Look For in Logs

Good signs:
```
Target path: /www/.../wp-content/plugins/wc-shipstation-shipping-pro
sending incremental file list
includes.phar
✅ Deployment complete
```

Warnings:
```
Permission denied (publickey)
No such file or directory
rsync: connection unexpectedly closed
```

---

## Troubleshooting

### Permission Denied
- Verify org‑level SSH keys and that the correct environment is chosen

### Files Not Updating
- Confirm the target path and that payload exists in repo root
- Check that `includes.phar` or `includes/` was uploaded

### Cache Issues
- The workflow flushes WP + transients and resets opcache
- Clear Kinsta cache if behavior persists

---

## Safety

- Backup of `includes.phar` is created automatically with timestamp
- Deployment is overlay‑only; unrelated files remain untouched



