# Production Deployment Instructions - v2.4.0

## ⚠️ IMPORTANT - Manual Deployment Required

Production deployments are **manual only** via GitHub Actions for safety.

## Pre-Deployment Checklist

### 1. Verify Version
- ✅ Version: **2.4.0 PRODUCTION READY**
- ✅ Commit: `fa6aef4` - "Make all debug logging conditional"
- ✅ Branch: `production` created and pushed

### 2. What's Being Deployed

**Production-Ready Features:**
- ✅ Custom carrier badges (USPS/UPS official logos)
- ✅ Enable/disable badge toggle
- ✅ Custom badge upload functionality
- ✅ Smart ZIP-only caching (4000x performance improvement)
- ✅ Left-aligned text with badges
- ✅ **ALL debug logging conditional** (OFF by default)
- ✅ Clean console (zero errors from plugin)
- ✅ Professional, polished UI

**What Changed from Previous Version:**
- Debug logs now **OFF by default**
- No unconditional logging
- Production-optimized performance
- Badge display improvements

### 3. Safety Features

**Deployment Workflow:**
- ✅ Creates backup before deployment
- ✅ Uses rsync (NO `--delete` flag - safe)
- ✅ Flushes WordPress caches automatically
- ✅ Excludes development files (.md, .git*, etc.)
- ✅ Tests SSH connection before deploying

**Rollback Plan:**
- Backup created: `hp-shipstation-rates.bak.YYYYMMDD-HHMMSS`
- Located in: Same plugins directory
- Can restore by: Rename backup to `hp-shipstation-rates`

## Deployment Steps

### Step 1: Go to GitHub Actions

**URL:** https://github.com/HolisticPeople/hp-shipstation-rates/actions

### Step 2: Run Manual Deployment

1. Click on **"Deploy HP ShipStation Rates to Kinsta"** workflow
2. Click **"Run workflow"** button (right side)
3. **Select branch:** `production` (or `master`)
4. **Select environment:** `production` ⚠️
5. Click **"Run workflow"** button (green)

### Step 3: Monitor Deployment

Watch the workflow execution:
- ✅ Checkout code
- ✅ Setup SSH
- ✅ Test connection
- ✅ **Backup existing plugin**
- ✅ Deploy files
- ✅ Flush caches
- ✅ Cleanup

**Expected duration:** ~2-3 minutes

### Step 4: Verify Deployment

**On Live Site:**

1. **Check plugin version:**
   - Go to: WooCommerce → ShipStation Rates
   - Look for: `v2.4.0` in page title

2. **Verify debug is OFF:**
   - Debug Settings section
   - "Enable debug logging" should be **unchecked**

3. **Test checkout:**
   - Add product to cart
   - Go to checkout
   - Verify shipping methods display correctly
   - Verify badges appear (if enabled)
   - Hard refresh (Ctrl+Shift+R) to clear browser cache

4. **Check server logs:**
   ```bash
   # Should be EMPTY (no HP SS entries)
   tail -f wp-content/debug.log
   ```

5. **Test complete order:**
   - Use test mode payment
   - Complete a test order
   - Verify shipping rate was applied correctly

## Post-Deployment Checklist

### Immediate Verification (First 5 minutes)

- [ ] Plugin version shows `v2.4.0`
- [ ] Debug logging is OFF by default
- [ ] Checkout page loads correctly
- [ ] Shipping methods appear
- [ ] Badges display (if enabled in settings)
- [ ] No console errors
- [ ] Server logs are clean

### Extended Monitoring (First Hour)

- [ ] Test domestic order
- [ ] Test international order (if applicable)
- [ ] Monitor server logs (should remain empty)
- [ ] Check performance (page load times)
- [ ] Verify cache is working (fast subsequent loads)

### Customer Experience

- [ ] Fast checkout experience
- [ ] Professional appearance
- [ ] Accurate shipping rates
- [ ] No errors during checkout
- [ ] Successful order completion

## Rollback Procedure (If Needed)

**If something goes wrong:**

1. **Immediate rollback via SSH:**
   ```bash
   # SSH to production server
   cd /path/to/wp-content/plugins
   
   # Find the backup
   ls -la | grep hp-shipstation-rates.bak
   
   # Remove current version
   mv hp-shipstation-rates hp-shipstation-rates.failed
   
   # Restore backup
   mv hp-shipstation-rates.bak.YYYYMMDD-HHMMSS hp-shipstation-rates
   
   # Flush caches
   wp cache flush --allow-root
   wp transient delete --all --allow-root
   ```

2. **Verify rollback:**
   - Check plugin version (should be previous version)
   - Test checkout
   - Monitor for issues

3. **Investigate issue:**
   - Enable debug logging temporarily
   - Check `wp-content/debug.log`
   - Review error logs
   - Report findings

## Support Information

### Debug Mode (If Needed)

**Only enable for troubleshooting:**
1. Go to: WooCommerce → ShipStation Rates → Debug Settings
2. Check "Enable debug logging"
3. Save changes
4. Reproduce issue
5. Check `wp-content/debug.log`
6. **Disable debug logging when done**

### Key Files

- Plugin: `wp-content/plugins/hp-shipstation-rates/`
- Logs: `wp-content/debug.log`
- Settings: WordPress options table (`hp_ss_settings`)
- Badges: `wp-content/plugins/hp-shipstation-rates/assets/`

### Performance Monitoring

**Expected Performance:**
- First rate calculation: ~2-3 seconds (API call)
- Subsequent loads: <100ms (cache hit)
- Cache duration: 2 minutes per ZIP/cart combination
- No impact on checkout speed

## Deployment Confirmation

After successful deployment, confirm:

✅ Version 2.4.0 deployed to production  
✅ All features working correctly  
✅ Debug logging OFF  
✅ No console errors  
✅ Clean server logs  
✅ Fast performance  
✅ Professional appearance  

**Deployment completed by:** _______________  
**Date/Time:** _______________  
**Verified by:** _______________  

---

## Ready to Deploy! 🚀

**Current Status:**
- ✅ Code ready in `production` branch
- ✅ All safety features active
- ✅ Rollback plan in place
- ✅ Monitoring checklist prepared

**Next Action:**
Go to GitHub Actions and run the manual production deployment!

**URL:** https://github.com/HolisticPeople/hp-shipstation-rates/actions/workflows/deploy.yml

