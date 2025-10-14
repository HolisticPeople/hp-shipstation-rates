# Production Deployment Setup – OneTeam API Fix

## How it Works

- Auto deploy to STAGING on push to `dev`
- Manual deployment to PRODUCTION via Actions → Run workflow
- Uses organization‑level secrets already configured

---

## Configure (if not already)

Ensure the following secrets exist at org level:

- Staging: `KINSTA_HOST`, `KINSTA_PORT`, `KINSTA_USER`, `KINSTA_SSH_KEY`, `KINSTA_PLUGINS_BASE`
- Production: `KINSTAPROD_HOST`, `KINSTAPROD_PORT`, `KINSTAPROD_USER`, `KINSTAPROD_SSH_KEY`, `KINSTAPROD_PLUGINS_BASE`

Repo‑level:

- `PLUGIN_FOLDER_NAME` = `wc-shipstation-shipping-pro`

---

## Run a Production Deployment

1. Merge tested changes to `main`
2. GitHub → Actions → "Deploy OneTeam fix to Kinsta"
3. Run workflow with:
   - Branch: `main`
   - Environment: `production`
4. Monitor steps; success message appears at end

---

## Verification

```bash
# On server
ls -la /www/.../public/wp-content/plugins/wc-shipstation-shipping-pro
ls -la /www/.../public/wp-content/plugins/wc-shipstation-shipping-pro/includes.phar
```

Expect a `.bak.YYYYMMDD-HHMMSS` file alongside the new `includes.phar`.

---

## Rollback

```bash
ssh -p PORT USER@HOST
cd /www/.../public/wp-content/plugins/wc-shipstation-shipping-pro
cp -f includes.phar.bak.YYYYMMDD-HHMMSS includes.phar
```

Then clear caches:

```bash
cd /www/.../public
wp cache flush --allow-root
wp transient delete --all --allow-root
```

---

## Notes

- If the plugin requires folder expansion instead of PHAR, place an `includes/` folder in the repo; the workflow rsyncs it to the same path
- No deletion of unrelated files; safe overlay strategy



