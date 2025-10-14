# Quick Start - OneTeam API Fix Deployment

## Overview

This repo delivers a hotfix to a third‑party plugin on your WooCommerce servers.

- Reuses organization‑level secrets you already set up
- Auto‑deploys to STAGING on push to `dev`
- Manual deployment to PRODUCTION via GitHub Actions

Target plugin on server: `wp-content/plugins/wc-shipstation-shipping-pro`

---

## One‑Time Setup

1) Ensure org‑level secrets exist (same as Product Access Manager):

- `KINSTA_HOST`, `KINSTA_PORT`, `KINSTA_USER`, `KINSTA_SSH_KEY`, `KINSTA_PLUGINS_BASE`
- `KINSTAPROD_HOST`, `KINSTAPROD_PORT`, `KINSTAPROD_USER`, `KINSTAPROD_SSH_KEY`, `KINSTAPROD_PLUGINS_BASE`

2) Repo secret (this repo only):

- `PLUGIN_FOLDER_NAME` = `wc-shipstation-shipping-pro`

---

## Daily Workflow

```powershell
# Clone or open the repo

# Put the fixed payload at repo root:
# - EITHER: includes.phar
# - OR:     includes/  (expanded folder)

# Push to staging
git checkout dev
git add .
git commit -m "fix: OneTeam API compatibility in includes.phar"
git push origin dev  # Auto-deploys to staging

# Verify on staging, then deploy to production via Actions (manual)
```

---

## Manual Deployment to Production

1. GitHub → Actions → "Deploy OneTeam fix to Kinsta"
2. Click "Run workflow"
3. Branch: `main` (recommended after merge)
4. Environment: `production`
5. Run and monitor logs

---

## What Gets Deployed

Only the payload files are sent as an overlay to the plugin directory:

- `includes.phar` → copies to `.../wc-shipstation-shipping-pro/includes.phar`
- `includes/`     → rsync to `.../wc-shipstation-shipping-pro/includes/`

The workflow creates a timestamped backup of the existing `includes.phar` before replacing it.

---

## SSH Quick Checks (Windows)

```cmd
ssh -o StrictHostKeyChecking=accept-new wc-staging "ls -la public/wp-content/plugins/wc-shipstation-shipping-pro && ls -la public/wp-content/plugins/wc-shipstation-shipping-pro/includes.phar"
ssh -o StrictHostKeyChecking=accept-new wc-prod     "ls -la public/wp-content/plugins/wc-shipstation-shipping-pro && ls -la public/wp-content/plugins/wc-shipstation-shipping-pro/includes.phar"
```

---

## Cache Busting

The workflow flushes WP object cache and transients, and triggers PHP opcache reset remotely.

If you still see stale behavior, use Kinsta dashboard to clear caches.



