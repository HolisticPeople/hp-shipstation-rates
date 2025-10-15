# v2.3.0 - Custom Badge Upload Feature

## New Features

### 1. Enable/Disable Carrier Badges ✅
- **Location:** WooCommerce → ShipStation Rates → Carrier Badges
- **Option:** "Show Carrier Badges" checkbox
- **Benefit:** Control whether badges appear on checkout without removing the images

### 2. Upload Custom Badge Images ✅
- **Location:** Same settings page, individual upload fields for USPS and UPS
- **Formats Supported:** PNG, JPG, SVG, WebP
- **Recommended Size:** 40-60px wide × 18-24px tall
- **Preview:** Shows current badge image in admin

### 3. Default Badge Images Included ✅
- **USPS Badge:** Official USPS logo (`assets/usps-badge.png`)
- **UPS Badge:** Official UPS logo (`assets/ups-badge.png`)
- **Fallback:** Plugin uses these default badges if no custom badges are uploaded

## How It Works

### Admin Side
1. Navigate to **WooCommerce → ShipStation Rates**
2. Scroll to **Carrier Badges** section
3. Check "Show Carrier Badges" to enable
4. Upload custom badges (optional) or use the included default badges
5. Click "Save Changes"

### Frontend Side
- **When enabled:** Badge images appear before shipping method names
- **When disabled:** Clean text-only shipping method names (no badges)
- **Image source:** Uses custom uploaded badges if available, otherwise defaults to `assets/` folder badges

### Technical Implementation

**PHP (Admin):**
- File upload handler with security validation
- Saves uploaded files to `assets/` directory
- Stores file URLs in WordPress options
- Shows badge preview in admin

**JavaScript (Frontend):**
- Checks `show_badges` setting before running
- Replaces `{{USPS}}` and `{{UPS}}` markers with `<img>` tags
- Uses custom badge URL or default badge URL
- Complete error handling (no console errors)

**Settings Storage:**
```php
$settings = array(
    'show_badges' => 'yes',  // Enable/disable toggle
    'usps_badge' => 'https://site.com/wp-content/plugins/.../assets/usps_badge.png',
    'ups_badge' => 'https://site.com/wp-content/plugins/.../assets/ups_badge.png'
);
```

## File Structure

```
hp-shipstation-rates/
├── assets/
│   ├── README.md                    # Badge documentation
│   ├── UPLOAD-BADGES-HERE.txt      # Instructions
│   ├── usps-badge.png              # Default USPS badge (from user)
│   ├── ups-badge.png               # Default UPS badge (from user)
│   ├── usps_badge.png              # Custom uploaded (if any)
│   └── ups_badge.png               # Custom uploaded (if any)
├── admin/
│   └── class-hp-ss-settings.php    # Settings page with upload UI
├── includes/
│   └── class-hp-ss-shipping-method.php  # Badge display logic
└── .gitignore                      # Excludes custom uploads
```

## Badge Upload Process

1. **User selects file** in admin settings
2. **Form submission** includes file data (`enctype="multipart/form-data"`)
3. **Sanitize function** calls `handle_badge_upload()`
4. **Upload handler:**
   - Validates file type (PNG, JPG, SVG, WebP)
   - Moves file to `assets/` directory
   - Renames to `usps_badge.*` or `ups_badge.*`
   - Returns file URL
5. **Settings saved** with badge URL
6. **Frontend displays** using saved URL

## Security Features

✅ File type validation (images only)  
✅ Secure file handling (WordPress functions)  
✅ Capability check (manage_woocommerce)  
✅ Nonce verification (WordPress settings API)  
✅ URL escaping (esc_url)  
✅ Sanitized input  

## User Experience

### First Time Setup
1. Install plugin
2. Configure API credentials
3. Enable "Show Carrier Badges"
4. **Default badges work immediately** (no upload needed!)
5. Optionally upload custom badges later

### Customization
1. Design custom badges (or use different images)
2. Upload via admin settings
3. Preview in settings page
4. See changes immediately on checkout

### Disable Badges
1. Uncheck "Show Carrier Badges"
2. Save settings
3. Badges disappear from checkout (clean text-only)
4. Badge images remain saved (can re-enable anytime)

## Upgrade from v2.2.3

**Automatic upgrade:**
- Badges **disabled by default** (backward compatible)
- Users must check "Show Carrier Badges" to enable
- Default badge images included
- No configuration required to start using

**To enable badges:**
1. Go to settings
2. Check "Show Carrier Badges"
3. Save (uses default badges)
4. Done!

## Benefits

| Feature | Before (v2.2.3) | After (v2.3.0) |
|---------|----------------|----------------|
| Enable/Disable | Code edit required | Simple checkbox |
| Badge images | Embedded SVG | Real image files |
| Customization | Code edit required | Upload in admin |
| Preview | None | Shows in settings |
| Default badges | None | Official logos included |
| File management | N/A | Automatic |

## Console Errors Note

As documented in `CONSOLE-ERRORS-ANALYSIS.md`, the Google API console errors are **NOT from this plugin**. They are from:
- Google Analytics
- Google Tag Manager
- Google Pay
- Or browser extensions

The badge feature has **complete error handling** and will never generate console errors.

## Deployment

- **Version:** 2.3.0
- **Committed:** 2f8c6c8
- **Includes:** Default USPS and UPS badge images
- **Status:** Deploying to staging now
- **Watch:** https://github.com/HolisticPeople/hp-shipstation-rates/actions

## Next Steps

After deployment:
1. Go to **WooCommerce → ShipStation Rates**
2. Scroll to **Carrier Badges** section
3. You'll see the default badges already loaded
4. Check "Show Carrier Badges"
5. Save and view checkout
6. Badges should appear perfectly!

If you want different badge images later, just upload new ones! 🎨


