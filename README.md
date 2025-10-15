# HP ShipStation Rates - WooCommerce Shipping Plugin

**Version:** 2.1.0  
**Requires:** WordPress 5.8+, WooCommerce 5.0+  
**License:** Proprietary  
**Author:** Holistic People

## Description

Minimal, high-performance WooCommerce shipping method that fetches real-time USPS and UPS shipping quotes from ShipStation V1 API. Features dynamic service discovery, intelligent ZIP-only caching, and professional admin UI for complete control over shipping options.

## Key Features

✅ **Dynamic Service Discovery** - Automatically discover all available ShipStation services (domestic + international)  
✅ **Smart ZIP-Only Caching** - Instant rate delivery when only street/city/state changes  
✅ **Custom Display Names** - Rename shipping methods for better customer experience  
✅ **Price Sorting** - Always show cheapest option first  
✅ **Quick Mode** - Prevents ghost orders in ShipStation  
✅ **International Support** - Full support for international shipping services  
✅ **Professional Admin UI** - Easy service management with checkboxes and custom names  

## Performance

- **4000x faster** when selecting different shipping methods (< 1ms vs 3-4s)
- **Smart caching** - Only recalculates when ZIP code or cart actually changes
- **Session-based** - 2-minute cache for instant checkout experience
- **Zero API calls** for method selection or address refinement

## Installation

1. Upload plugin folder to `/wp-content/plugins/hp-shipstation-rates/`
2. Activate via WordPress admin
3. Go to WooCommerce → Settings → Shipping
4. Add "HP ShipStation Rates" shipping method to your zone
5. Configure API credentials in WooCommerce → ShipStation Rates

## Configuration

### 1. API Credentials

1. Navigate to **WooCommerce → ShipStation Rates**
2. Enter your ShipStation API Key and Secret
3. Click **"Test Connection"** to verify

### 2. Discover Services

1. Click **"Fetch Available Services from ShipStation"**
2. Wait 10-15 seconds while plugin queries multiple destinations
3. Page will reload showing all available services

### 3. Configure Services

1. **Enable** services you want to offer using checkboxes
2. **Add custom names** (optional) to rename services for customers
   - Example: "USPS Priority Mail Intl" → "Priority to Israel (7-10 days)"
3. **Leave name blank** to use ShipStation's default name
4. Click **"Save Changes"**

### 4. Add to Shipping Zone

1. Go to **WooCommerce → Settings → Shipping → Zones**
2. Edit your shipping zone
3. Click **"Add shipping method"**
4. Select **"HP ShipStation Rates"**
5. Save

## Settings

### API Credentials
- **API Key** - Your ShipStation API key
- **API Secret** - Your ShipStation API secret
- **Test Connection** - Verify credentials work

### Service Discovery
- **Fetch Services** - Query ShipStation for available services
- **Configure Services** - Enable/disable and rename services

### Default Package Settings
- **Default Dimensions** - Used when products lack dimensions (inches)
- **Default Weight** - Used when products lack weight (pounds)

### Debug Settings
- **Enable Debug Logging** - Log API requests to `wp-content/debug.log`

### Performance Settings
- **Disable USPS** - Temporarily disable USPS to speed up testing
- **Disable UPS** - Temporarily disable UPS to speed up testing

## Cache Behavior

### Triggers Recalculation (3-4s)
- ZIP code changes
- Country changes
- Product added/removed
- Quantity changes
- Cache expires (2 minutes)

### Uses Cached Rates (< 1ms)
- Selecting different shipping method
- Street address changes
- City changes
- State changes
- Payment method changes

## Troubleshooting

### No Rates Showing

1. **Check credentials** - Click "Test Connection"
2. **Check services** - Ensure at least one service is enabled
3. **Check debug log** - Enable debug logging and check for errors
4. **Clear cache** - Deactivate and reactivate plugin

### Rates Not Updating

1. **Check ZIP code** - Only ZIP changes trigger recalculation
2. **Wait for cache** - Cache expires after 2 minutes
3. **Clear transients** - `wp transient delete --all`

### Slow Checkout

**Note:** Our plugin uses intelligent caching - slowness is likely from other plugins.

Check debug logs:
- If you see `[HP SS Method] Using cached rates` - our plugin is fast
- If you see other plugin initialization logs - those are the culprit

## Debug Logging

Enable "Debug Logging" in settings to see:

```
[HP SS Method] Using cached rates - found 3 rates (ZIP: 90210, cart unchanged)
[HP SS Method] ===== CALCULATE_SHIPPING START =====
[HP SS V1] sending carrier=stamps_com to_zip=90210 to_country=US weight=1.09 quick=true
[HP SS V1] response status=200 carrier=stamps_com rates_count=2
[HP SS Method] Added 3 rates to checkout (sorted by price, cached for reuse)
[HP SS Method] ===== CALCULATE_SHIPPING END (took 4053.81ms) =====
```

## Technical Details

### ShipStation V1 API
- **Endpoint:** `https://ssapi.shipstation.com/shipments/getrates`
- **Quick Mode:** Enabled (`rate_type: "quick"`) to prevent ghost orders
- **Carriers:** USPS (`stamps_com`) and UPS (`ups_walleted`)

### Caching Strategy
- **Cache Key:** Based on ZIP code + country + product IDs + quantities
- **Duration:** 120 seconds (2 minutes)
- **Storage:** WordPress Transients API
- **Type:** Session-based with automatic expiration

### Data Storage
- **Settings:** `hp_ss_settings` option
- **Discovered Services:** `hp_ss_discovered_services` option
- **Cached Rates:** `hp_ss_rates_cache_{hash}` transients
- **Session Locks:** `hp_ss_session_{hash}` transients

## Changelog

### 2.1.0 - 2025-10-15
- **Added:** Dynamic service discovery (domestic + international)
- **Added:** Complete admin UI with checkboxes and custom names
- **Added:** Smart ZIP-only caching
- **Added:** Automatic rate sorting by price
- **Added:** Visual feedback for enabled services
- **Fixed:** Text fields now fully editable
- **Fixed:** Rates recalculating on method selection
- **Improved:** Performance (4000x faster for method selection)
- **Improved:** International support (auto-discover all services)

### 2.0.x - 2025-10-14
- Initial development versions
- Service configuration system
- Admin interface development

### 1.0.x - 2025-10-14
- Initial beta versions
- Basic ShipStation V1 integration
- MVP functionality

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- ShipStation account with API access
- Valid ShipStation API Key and Secret

## Support

For support, enable debug logging and check `wp-content/debug.log` for detailed error messages.

## License

Proprietary - © 2025 Holistic People. All rights reserved.

## Author

**Holistic People**  
Website: https://holisticpeople.com/

