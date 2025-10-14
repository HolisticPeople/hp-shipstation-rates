# AI Agent Development Guide – OneTeam API Fix

Last updated: October 13, 2025

## Purpose

Provide clear instructions for AI agents maintaining the hotfix delivery for the
third‑party plugin `wc-shipstation-shipping-pro` by replacing `includes.phar`
or syncing an `includes/` folder.

---

## Repository Layout

```
OneTeam-api-fix/
├─ includes.phar            # Fixed payload (optional)
├─ includes/                # Expanded payload (optional)
├─ .github/
│  ├─ workflows/deploy.yml  # Deployment to Kinsta
│  └─ deploy-exclude.txt
├─ QUICK-START.md
├─ DEPLOYMENT-SETUP.md
├─ GITHUB-ACTIONS-DEPLOYMENT-GUIDE.md
├─ PRODUCTION-DEPLOYMENT-SETUP.md
└─ AI-AGENT-GUIDE.md
```

Target server path:

```
.../public/wp-content/plugins/wc-shipstation-shipping-pro
```

---

## Deployment Rules

1. Use organization‑level secrets (no per‑repo duplication).
2. Staging deploys automatically from `dev`.
3. Production deploy is manual via Actions.
4. Always back up existing `includes.phar` before overwrite (workflow handles it).
5. Never delete unrelated files on the server.
6. Flush WordPress cache and transients, and reset opcache after deploy.

---

## Payload Choices

- Preferred: `includes.phar` – minimal, preserves plugin bootstrap.
- Alternative: `includes/` – only if plugin can load from folder; ensure loader compatibility.

If switching to folder mode, verify any autoload/require paths within the plugin
are updated accordingly. Otherwise, stay with PHAR replacement.

---

## Quick Commands

```bash
# Manual deploy (scp) if needed
scp -P 12872 includes.phar USER@HOST:public/wp-content/plugins/wc-shipstation-shipping-pro/

# Clear caches
ssh -p 12872 USER@HOST "cd public && wp cache flush --allow-root && wp transient delete --all --allow-root"
```

---

## Safety & Rollback

- Backups are timestamped: `includes.phar.bak.YYYYMMDD-HHMMSS`.
- Rollback: copy the backup back to `includes.phar` and flush caches.



