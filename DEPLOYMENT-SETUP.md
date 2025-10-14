# Deployment Setup Guide – OneTeam API Fix

Replicates the Product Access Manager workflow for a targeted hotfix to
`wp-content/plugins/wc-shipstation-shipping-pro`.

---

## Workflow

- Push to `dev` → Auto‑deploy to STAGING
- Manual workflow dispatch → Deploy to STAGING or PRODUCTION

### Requirements

- GitHub organization‑level secrets (`KINSTA_*`, `KINSTAPROD_*`)
- This repo secret: `PLUGIN_FOLDER_NAME=wc-shipstation-shipping-pro`

---

## Steps

### 1) Initialize Git locally

```powershell
cd "C:\DEV\WC Plugins\My Plugins\OneTeam-api-fix"
git init
git add .
git commit -m "init: OneTeam API fix scaffold"
git branch -M main
git checkout -b dev
git remote add origin https://github.com/YOUR-ORG/OneTeam-api-fix.git
git push -u origin main
git push -u origin dev
```

> Pushing `dev` triggers an automatic STAGING deploy.

### 2) Add Payload

Place one of the following at repo root:

- `includes.phar` (preferred minimal change)
- `includes/` folder (expanded source if we must replace the loader path)

### 3) Verify Deployment

Monitor GitHub Actions logs. You should see:

```
Deploying to: staging
Target path: /www/.../public/wp-content/plugins/wc-shipstation-shipping-pro
sending incremental file list
includes.phar
✅ Deployment complete
```

---

## Troubleshooting

### Permission denied (publickey)
- Ensure org secrets contain the full private key

### Path not found
- Confirm `KINSTAPROD_PLUGINS_BASE`/`KINSTA_PLUGINS_BASE`
- SSH and `ls -la` to validate the plugin directory

### Changes not visible
- WordPress and transients cache are flushed by the workflow
- Use Kinsta dashboard to clear server cache if needed

---

## Manual SSH Checks

```cmd
ssh -o StrictHostKeyChecking=accept-new wc-staging "ls -la public/wp-content/plugins/wc-shipstation-shipping-pro && ls -la public/wp-content/plugins/wc-shipstation-shipping-pro/includes.phar"
ssh -o StrictHostKeyChecking=accept-new wc-prod     "ls -la public/wp-content/plugins/wc-shipstation-shipping-pro && ls -la public/wp-content/plugins/wc-shipstation-shipping-pro/includes.phar"
```

---

## Notes

- We keep a timestamped backup of the original `includes.phar` on the server
- Deployment uses rsync overlay; it does not delete unrelated files
- No third‑party version bump; rely on cache flush + opcache reset



