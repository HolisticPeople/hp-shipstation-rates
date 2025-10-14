# HP ShipStation Rates - Deployment Checklist

## Pre-Deployment

- [ ] Review all code changes
- [ ] Ensure GitHub Actions workflow is updated (`PLUGIN_FOLDER_NAME=hp-shipstation-rates`)
- [ ] Commit all changes to git
- [ ] Push to `dev` branch to trigger staging deployment

## Staging Deployment Steps

### 1. Deactivate 1Team Plugin on Staging
```bash
ssh -p 12872 holisticpeoplecom@35.236.219.140 \
  "cd public && wp plugin deactivate wc-shipstation-shipping-pro --allow-root"
```

### 2. Deploy via GitHub Actions
- Push to `dev` branch triggers automatic staging deployment
- Monitor GitHub Actions workflow for success

### 3. Activate HP ShipStation Rates
```bash
ssh -p 12872 holisticpeoplecom@35.236.219.140 \
  "cd public && wp plugin activate hp-shipstation-rates --allow-root"
```

### 4. Configure Plugin Settings

**Via SSH (automated):**
```bash
ssh -p 12872 holisticpeoplecom@35.236.219.140 \
  "cd public && wp option patch insert hp_ss_settings api_key 'YOUR_API_KEY' --allow-root && \
   wp option patch insert hp_ss_settings api_secret 'YOUR_API_SECRET' --allow-root && \
   wp option patch insert hp_ss_settings debug_enabled 'yes' --allow-root"
```

**Or via WP Admin:**
1. Log in to staging WP Admin
2. Navigate to **WooCommerce** → **ShipStation Rates**
3. Enter API credentials
4. Click "Test Connection" - verify success
5. Select services:
   - USPS: Priority Mail, First Class Mail
   - UPS: Ground, 2nd Day Air
6. Set default dimensions: 12 × 12 × 12
7. Set default weight: 1 lb
8. Enable debug logging
9. Save settings

### 5. Add to Shipping Zone
```bash
ssh -p 12872 holisticpeoplecom@35.236.219.140 \
  "cd public && wp eval 'echo wp_get_environment_type();' --allow-root"
```

**Or via WP Admin:**
1. Go to **WooCommerce** → **Settings** → **Shipping** → **Shipping Zones**
2. Edit your main zone
3. Click "Add shipping method"
4. Select "HP ShipStation Rates"
5. Save

### 6. Clear All Caches
```bash
ssh -p 12872 holisticpeoplecom@35.236.219.140 \
  "cd public && wp cache flush --allow-root && wp transient delete --all --allow-root"
```

## Testing on Staging

### Test Matrix

#### Test 1: US Domestic (Light Package)
- Add 1 product (~1 lb)
- Destination: Los Angeles, CA 90210
- Expected: USPS and UPS rates appear

#### Test 2: US Domestic (Heavy Package)
- Add products totaling ~10 lbs
- Destination: New York, NY 10001
- Expected: USPS and UPS rates appear

#### Test 3: US Domestic (Different ZIP)
- Add 1 product (~2 lbs)
- Destination: Austin, TX 78701
- Expected: USPS and UPS rates appear

#### Test 4: International (Israel)
- Add 1 product (~1 lb)
- Destination: Tel Aviv, Israel
- Expected: International rates appear (or graceful handling if not configured)

### Verification Steps

#### Check Debug Logs
```bash
ssh -p 12872 holisticpeoplecom@35.236.219.140 \
  "tail -100 public/wp-content/debug.log | grep 'HP SS'"
```

Expected log entries:
```
[HP SS Method] calculate_shipping called for package with X items
[HP SS Method] Package data: {"weight":X,"length":X,...}
[HP SS V1] sending carrier=stamps_com to_zip=90210 to_country=US weight=X quick=true
[HP SS V1] request body: {"carrierCode":"stamps_com",...}
[HP SS V1] response status=200 carrier=stamps_com rates_count=X
[HP SS Method] Added X rates to checkout
```

#### Verify No Ghost Orders
1. Log in to ShipStation
2. Check "Awaiting Shipment" queue
3. Verify NO ghost orders from staging tests

#### Test Connection Button
1. Go to **WooCommerce** → **ShipStation Rates**
2. Click "Test Connection"
3. Expected: ✓ Connection successful! Found X carriers.

## Production Deployment

### Prerequisites
- [ ] All staging tests passed
- [ ] No ghost orders on ShipStation from staging
- [ ] Debug logs show successful V1 API calls with quick mode
- [ ] Customer-facing rates display correctly

### Production Steps

**WARNING:** Production deployment is manual and requires careful execution.

### 1. Deactivate 1Team Plugin on Production
```bash
# Use production SSH credentials
ssh -p <PROD_PORT> <PROD_USER>@<PROD_HOST> \
  "cd <PROD_PATH> && wp plugin deactivate wc-shipstation-shipping-pro --allow-root"
```

### 2. Deploy via GitHub Actions
- Go to GitHub → Actions → "Deploy OneTeam fix to Kinsta"
- Click "Run workflow"
- Select "production" environment
- Click "Run workflow"
- Monitor for success

### 3. Activate and Configure
- Repeat staging steps 3-6 on production
- **DISABLE** debug logging on production (unless needed for troubleshooting)

### 4. Production Testing
- Place a real test order to verify rates
- Monitor ShipStation for ghost orders
- Check customer-facing checkout for rate display

### 5. Monitor
- Watch debug logs for errors (if enabled)
- Monitor customer support for shipping issues
- Check ShipStation dashboard daily for ghost orders

## Rollback Plan

If issues occur on staging or production:

### Staging Rollback
```bash
ssh -p 12872 holisticpeoplecom@35.236.219.140 \
  "cd public/wp-content/plugins && \
   wp plugin deactivate hp-shipstation-rates --allow-root && \
   wp plugin activate wc-shipstation-shipping-pro --allow-root"
```

### Production Rollback
```bash
# Use production credentials
ssh -p <PROD_PORT> <PROD_USER>@<PROD_HOST> \
  "cd <PROD_PATH>/wp-content/plugins && \
   wp plugin deactivate hp-shipstation-rates --allow-root && \
   wp plugin activate wc-shipstation-shipping-pro --allow-root"
```

## Post-Deployment

- [ ] Verify rates are displaying at checkout
- [ ] Check ShipStation for ghost orders (daily for 1 week)
- [ ] Monitor error logs
- [ ] Gather customer feedback on shipping options
- [ ] Update documentation if needed

## Emergency Contacts

- ShipStation Support: support@shipstation.com
- Holistic People Dev Team: [contact info]

## Notes

- Plugin slug: `hp-shipstation-rates`
- Main file: `hp-shipstation-rates.php`
- Settings page: WooCommerce → ShipStation Rates
- Debug logs: `wp-content/debug.log` (search for `[HP SS]`)

