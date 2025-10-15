# Settings Preservation Fix - v2.4.2

## Problem Identified

**User Report:** "When clicking the test connection, the enabled methods were unchecked."

## Root Cause Analysis

The issue occurred when users clicked "Test Connection" and then saved the form. The problem was in the `sanitize_settings()` callback:

### Original Behavior

```php
// Old code - ALWAYS reset service_config to empty array
$sanitized['service_config'] = array();
if ( isset( $input['service_config'] ) && is_array( $input['service_config'] ) ) {
    // populate it
}
```

**Problem:** If `service_config` wasn't present in `$input`, it would be set to an empty array, effectively unchecking all services.

### WordPress Settings API Behavior

- `sanitize_settings()` is called ONLY when form is submitted via Settings API
- Direct `update_option()` calls (like from AJAX test connection) do NOT trigger the sanitize callback
- However, if the user clicks "Test Connection" then submits the form, the sanitize callback runs with the form data

### The Actual Bug

The sanitize function wasn't smart enough to distinguish between:
1. **Full form submission** - where missing checkboxes mean "unchecked"
2. **Partial updates** - where missing values should be preserved from existing settings

## Solution Implemented

### 1. Preserve Existing Settings

```php
// Get existing settings at start of sanitize function
$existing = get_option( 'hp_ss_settings', array() );
```

### 2. Smart Service Config Handling

```php
if ( isset( $input['service_config'] ) && is_array( $input['service_config'] ) ) {
    // Process new service config
    $sanitized['service_config'] = array();
    foreach ( $input['service_config'] as $service_code => $config ) {
        $sanitized['service_config'][ sanitize_text_field( $service_code ) ] = array(
            'enabled' => isset( $config['enabled'] ) && $config['enabled'] === 'yes',
            'name' => isset( $config['name'] ) ? sanitize_text_field( $config['name'] ) : ''
        );
    }
} else {
    // Preserve existing service configuration
    $sanitized['service_config'] = isset( $existing['service_config'] ) ? $existing['service_config'] : array();
}
```

### 3. Detect Full Form vs Partial Update

```php
// Detect if this is a full form submission by checking for default_length (always present in form)
$is_full_form = isset( $input['default_length'] );

if ( $is_full_form ) {
    // Full form submission - checkboxes not present means unchecked
    $sanitized['debug_enabled'] = isset( $input['debug_enabled'] ) ? 'yes' : 'no';
    $sanitized['show_badges'] = isset( $input['show_badges'] ) ? 'yes' : 'no';
    // ... etc
} else {
    // Partial update - preserve existing values
    $sanitized['debug_enabled'] = isset( $existing['debug_enabled'] ) ? $existing['debug_enabled'] : 'no';
    $sanitized['show_badges'] = isset( $existing['show_badges'] ) ? $existing['show_badges'] : 'yes';
    // ... etc
}
```

### 4. Preserve Package Dimensions

```php
$sanitized['default_length'] = isset( $input['default_length'] ) && is_numeric( $input['default_length'] ) 
    ? floatval( $input['default_length'] ) 
    : ( isset( $existing['default_length'] ) ? $existing['default_length'] : 12 );
```

### 5. Simplify Badge Preservation

```php
if ( ! empty( $_FILES['usps_badge']['name'] ) ) {
    $sanitized['usps_badge'] = self::handle_badge_upload( 'usps_badge' );
} else {
    // Keep existing badge
    $sanitized['usps_badge'] = isset( $existing['usps_badge'] ) ? $existing['usps_badge'] : '';
}
```

## Impact

### Before Fix
❌ Test connection → Save form → All service checkboxes unchecked
❌ Partial form updates could lose settings
❌ Badge settings could be lost

### After Fix
✅ Test connection → Save form → All settings preserved
✅ Only values actually submitted in form are changed
✅ AJAX operations don't affect other settings
✅ Badge uploads don't affect service configuration

## Testing Checklist

- [x] Test connection with existing service configuration → Settings preserved
- [x] Change API credentials → Other settings unchanged
- [x] Toggle service checkboxes → Only those services affected
- [x] Upload badge → Service configuration unchanged
- [x] Toggle debug/badges checkboxes → Other settings preserved
- [x] Change package dimensions → Service configuration unchanged

## Technical Notes

**Form Detection Strategy:** Using `isset( $input['default_length'] )` as the indicator for full form submission because:
- The default_length field is always present in the form (even if empty)
- It's not affected by checkbox states
- AJAX calls don't include this field
- More reliable than checking for `service_config` (which might be absent if all checkboxes unchecked)

**Preservation Pattern:**
```php
$value = isset( $input['field'] ) 
    ? sanitize( $input['field'] ) 
    : ( isset( $existing['field'] ) ? $existing['field'] : $default );
```

This three-tier fallback ensures:
1. New value if provided
2. Existing value if not provided but exists
3. Default value as last resort

## Files Changed

- `admin/class-hp-ss-settings.php` - Enhanced `sanitize_settings()` method
- `hp-shipstation-rates.php` - Version bump to 2.4.2

## Deployment

All branches updated:
- ✅ `master` → v2.4.2
- ✅ `dev` → v2.4.2 (auto-deploys to staging)
- ✅ `production` → v2.4.2 (ready for production)

---

**Status:** ✅ RESOLVED
**Version:** 2.4.2
**Date:** 2025-10-15

