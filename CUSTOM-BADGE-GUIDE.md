# Custom Badge Images Guide

## Recommended Specifications

### Image Format
**Best format: PNG** (with transparency)
- ✅ Supports transparency
- ✅ Crisp at small sizes
- ✅ Universal browser support
- ✅ Small file size
- ❌ SVG also great (scalable, even smaller)

**Also acceptable:**
- SVG (best for scalability)
- WebP (smallest, excellent quality)
- JPG (if no transparency needed)

### Image Dimensions

**Recommended sizes:**
- **Width:** 40-60 pixels
- **Height:** 18-24 pixels
- **Aspect ratio:** ~2.5:1 to 3:1 (wider than tall)

**Current badges are:**
- USPS: 48×20px
- UPS: 42×20px

### Design Guidelines

**For best results:**
1. **High contrast** - Will appear on dark checkout background
2. **Simple design** - Small size, must be readable
3. **Transparent background** - Blends with any theme
4. **2x resolution** - Create at double size (e.g., 96×40px) for retina displays
5. **Padding** - Include small internal padding (design won't touch edges)

### Color Recommendations

**USPS:**
- Official USPS colors: Blue (#003481) or Red/White/Blue
- Current: White text on blue background

**UPS:**
- Official UPS colors: Brown (#351C15) with Gold (#FFB500)
- Current: Gold text on brown background

### File Naming

Please provide files named:
- `usps-badge.png` (or .svg)
- `ups-badge.png` (or .svg)

### Example Specifications

**Option 1: Simple text badges (current style)**
```
usps-badge.png
- Size: 48×20px (or 96×40px @2x)
- Background: USPS Blue (#003481)
- Text: "USPS" in white, bold
- Rounded corners: 3px
- Format: PNG with transparency
```

**Option 2: Logo-based badges**
```
usps-badge.png
- Size: 60×22px
- Contains actual USPS logo
- Transparent or white background
- Format: PNG with transparency
```

**Option 3: Icon + text**
```
usps-badge.png
- Size: 70×24px
- Small USPS icon on left
- "USPS" text on right
- Transparent background
- Format: PNG
```

## How to Create

### Method 1: Use Existing Logos
1. Download official USPS/UPS logos
2. Resize to ~48×20px
3. Add background or keep transparent
4. Save as PNG

### Method 2: Design Tool (Figma, Photoshop, etc.)
1. Create new document: 48×20px (or 96×40px for @2x)
2. Add rounded rectangle (3px corners)
3. Add carrier name text or logo
4. Export as PNG (transparent background)

### Method 3: Online Badge Makers
- shields.io
- badgen.net
- Custom badge generators

### Method 4: AI Image Generators
Use prompt like:
```
"Create a small rectangular badge logo for USPS/UPS shipping, 
48×20 pixels, blue background for USPS / brown for UPS, 
professional, simple, modern, high contrast"
```

## Integration Process

Once you provide the images:

1. **Upload to plugin folder:**
   - Save as `hp-shipstation-rates/assets/usps-badge.png`
   - Save as `hp-shipstation-rates/assets/ups-badge.png`

2. **I'll update the code** to reference them:
```javascript
// Instead of base64 SVG
var badge = '<img src="' + pluginUrl + '/assets/usps-badge.png" alt="USPS" ... />';
```

3. **Plugin will use the images** instead of embedded SVG

## Examples of Good Badge Images

**Simple text badges (like current):**
- Clean, minimal
- High contrast
- Easy to read
- Fast loading

**Logo badges:**
- Official carrier branding
- Instantly recognizable
- Professional appearance

**Icon + text badges:**
- Visual + textual information
- Balanced design
- Good for accessibility

## Quick DIY Option

If you want me to generate simple badges for you, I can create:
- **Style 1:** Solid color rectangles with carrier name text
- **Style 2:** Outlined badges with transparent background
- **Style 3:** Gradient backgrounds with text
- **Style 4:** Icon-style minimal badges

Just let me know which style you prefer and any color preferences!

## Current Implementation (for reference)

Right now v2.2.3 uses:
- Embedded SVG (no external files)
- Base64 encoded
- USPS: Blue (#003481) with white text
- UPS: Brown (#351C15) with gold text (#FFB500)
- 48×20px and 42×20px

Your custom images will replace these while keeping the same functionality.

## What to Send Me

**Option A: Image files**
- Just send me the PNG/SVG files
- I'll integrate them into the plugin

**Option B: Image URLs**
- Provide URLs to the badge images
- I'll download and integrate them

**Option C: Design specs**
- Describe what you want
- I'll create the badges for you

Let me know what works best! 🎨

