# HP ShipStation Rates

A minimal, clean WooCommerce shipping method plugin that fetches real-time USPS and UPS quotes from ShipStation V1 API.

## Features

- **ShipStation V1 API Integration** - Uses V1 rates endpoint with quick mode to prevent ghost orders
- **USPS & UPS Support** - Get real-time rates for both carriers
- **Service Filtering** - Choose which services to display at checkout
- **Smart Packaging** - Automatic unit conversion (pounds/inches) and package building
- **Caching** - 90-second transient cache for performance
- **Debug Logging** - Comprehensive logging for troubleshooting
- **Clean Architecture** - Minimal dependencies, uses WordPress HTTP API only

## Installation

### On Staging/Production

1. **Deactivate the existing 1Team plugin** (`wc-shipstation-shipping-pro`)
   - Go to **Plugins** → Find "WooCommerce ShipStation Shipping Pro" → Deactivate

2. **Deploy this plugin via GitHub Actions**
   - Push to `dev` branch triggers staging deployment
   - Manual workflow dispatch for production

3. **Activate HP ShipStation Rates**
   - Go to **Plugins** → Find "HP ShipStation Rates" → Activate

4. **Configure Settings**
   - Navigate to **WooCommerce** → **ShipStation Rates**
   - Enter your ShipStation API credentials
   - Click "Test Connection" to verify
   - Select which USPS and UPS services to enable
   - Configure default package dimensions and weight
   - Enable debug logging if needed

5. **Add to Shipping Zones**
   - Go to **WooCommerce** → **Settings** → **Shipping** → **Shipping Zones**
   - Edit your zone(s)
   - Click "Add shipping method" → Select "HP ShipStation Rates"

## Configuration

### API Credentials

Get your ShipStation API credentials from:
1. Log in to ShipStation
2. Go to **Settings** → **Account** → **API Settings**
3. Copy your API Key and API Secret

### Service Codes

The plugin uses these service codes:

**USPS:**
- `usps_priority_mail` - Priority Mail
- `usps_first_class_mail` - First Class Mail
- `usps_priority_mail_express` - Priority Mail Express
- `usps_media_mail` - Media Mail
- `usps_parcel_select` - Parcel Select Ground

**UPS:**
- `ups_ground` - Ground
- `ups_3_day_select` - 3 Day Select
- `ups_2nd_day_air` - 2nd Day Air
- `ups_2nd_day_air_am` - 2nd Day Air AM
- `ups_next_day_air_saver` - Next Day Air Saver
- `ups_next_day_air` - Next Day Air
- `ups_next_day_air_early_am` - Next Day Air Early AM

### Default Package Settings

Used when products don't have dimensions or weight:
- **Dimensions:** 12 × 12 × 12 inches (default)
- **Weight:** 1 lb (default)

## How It Works

1. Customer adds products to cart and goes to checkout
2. Plugin calculates total weight and maximum dimensions
3. Makes separate API calls to ShipStation for USPS and UPS rates
4. Filters results based on enabled services
5. Displays rates to customer at checkout
6. Caches rates for 90 seconds to improve performance

## Quick Mode

The plugin uses ShipStation's "quick" rate mode which:
- Returns rates without creating a persistent shipment record
- Prevents "ghost orders" from appearing in ShipStation
- Uses both `rate_options.rate_type` and `rateOptions.rateType` for maximum compatibility

## Debug Logging

When debug logging is enabled, the plugin logs to `wp-content/debug.log`:

```
[HP SS V1] sending carrier=stamps_com to_zip=90210 to_country=US weight=5.5 quick=true
[HP SS V1] request body: {"carrierCode":"stamps_com",...}
[HP SS V1] response status=200 carrier=stamps_com rates_count=5
[HP SS Method] Added 5 rates to checkout
```

## Troubleshooting

### No rates showing at checkout

1. **Check API credentials** - Use the "Test Connection" button
2. **Check service selection** - At least one service must be enabled
3. **Check destination** - Valid postal code and country required
4. **Enable debug logging** - Check `debug.log` for API errors
5. **Check product weight** - All products need weight, or default weight is used

### Test Connection fails

- Verify API Key and Secret are correct
- Check your ShipStation account is active
- Try generating new API credentials in ShipStation

### Rates different from ShipStation

- Verify package weight and dimensions match
- Check which services are enabled
- Compare the API request body in debug logs

## Architecture

```
hp-shipstation-rates.php          # Main plugin file, bootstrap
includes/
  class-hp-ss-client.php          # ShipStation V1 API client
  class-hp-ss-packager.php        # Unit conversion & package building
  class-hp-ss-shipping-method.php # WooCommerce shipping method
admin/
  class-hp-ss-settings.php        # Settings page
  hp-ss-admin.js                  # Admin JavaScript
```

## Version History

### 1.0.0 - Initial Release
- ShipStation V1 API integration with quick mode
- USPS and UPS rate fetching
- Service filtering
- Admin settings page
- Debug logging
- Transient caching

## Support

For issues or questions, contact Holistic People support.

## License

GPL v2 or later

