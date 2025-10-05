# KK Lite Enhancement - Implementation Summary

## Overview
This PR implements comprehensive enhancements to the KK Lite plugin for managing certificates and courses for the Werbekoordinator system. The main focus is on using external System IDs for certificates, adding a course flow for KR (Koordynator Reklamy) role, and providing admin capabilities for issuing MR/RT certificates.

## Goals Achieved

### 1. ✅ External System ID Integration
- **Helper Function**: Added `kk_get_user_system_id($user_id)` with priority fallback:
  - Primary: `kk_system_id`
  - Fallback 1: `promoter_id`
  - Fallback 2: `werbeko_id`
- **Filter Hook**: `apply_filters('kk_get_user_system_id', $system_id, $user_id)` for custom integrations
- **Database**: Added `external_id` column to `kk_certificates` table with index
- **Certificate Format**: New format `{ROLE}-{EXTERNAL_ID}`, e.g., `KR-00000011005`
- **Verification**: Enhanced to accept both full certificate numbers and raw external IDs

### 2. ✅ Course Path for KR
- **Shortcode**: `[kk_course]` renders complete course panel
- **Course Flow**:
  - 6 educational modules (from koordynator-kurs)
  - Interactive final test (10 random questions, 70% passing grade)
  - Automatic KR certificate issuance upon passing
  - Smart locking: users who already have KR cert see congratulations message
- **Admin Settings**: New admin page "KK Lite → Kurs" for course configuration
- **WooCommerce Integration**: Automatic "Zostań Koordynatorem Reklamy" tab in My Account

### 3. ✅ Admin Issuance for MR/RT
- **Panel UI**: Buttons visible only to administrators in `/kk/certyfikaty/`
- **Safe View**: Dedicated admin panel in `/kk-safe/`
- **REST API**: Enhanced `POST /certificate/issue` endpoint accepts:
  ```json
  {
    "role": "MR|RT",
    "external_id": "00000011005"
  }
  ```

### 4. ✅ Improved UX and Diagnostics
- **Error Messages**: Detailed HTTP status codes and error descriptions
- **Certificate Panel**: Shows exact error content when loading fails
- **Verification Page**: 
  - Handles full certificate numbers (with prefix)
  - Handles raw external IDs (without prefix)
  - Shows all certificates for a given external ID when searching by ID alone

### 5. ✅ MU Plugin (kk-safe-view.php)
- Standalone safe view at `/kk-safe/`
- Admin fields for MR/RT issuance by external_id
- Improved error handling and status messages
- Independent of main plugin for stability

### 6. ✅ Comprehensive Documentation
- Complete setup guide in `dist/README.md`
- User profile configuration instructions
- Course setup walkthrough
- Quick test scenarios
- REST API reference
- Troubleshooting guide

## Technical Implementation

### Database Schema Changes
```sql
-- Added to wp_kk_certificates table
ALTER TABLE wp_kk_certificates 
ADD COLUMN external_id VARCHAR(64) NULL,
ADD KEY external_id_idx (external_id);
```

### New REST Endpoints
- `GET /kk/v1/certificate/check-kr` - Check if user has KR certificate
- `POST /kk/v1/certificate/issue` - Enhanced with optional `external_id` parameter (admin only)

### File Structure
```
wp-content/
├── plugins/
│   ├── kk-lite/                    # Main plugin
│   │   ├── kk-lite.php            # Core functionality (534 lines)
│   │   ├── templates/
│   │   │   ├── app.html           # Certificate panel (140 lines)
│   │   │   ├── course.html        # Course interface (240 lines)
│   │   │   └── verify.html        # Verification page (62 lines)
│   │   └── data/
│   │       └── questions.json     # Test questions
│   └── koordynator-kurs/          # Course content
│       └── templates/             # 6 modules + appendices
└── mu-plugins/
    └── kk-safe-view.php           # Safe view (187 lines)
dist/
└── README.md                       # Documentation (326 lines)
```

### Key Features

#### Certificate Number Format
- **Old**: `KR-20251005-1234` (role-date-random)
- **New**: `KR-00000011005` (role-external_id)

#### Verification Modes
1. **Full number**: `/kk/weryfikacja/?cert_no=KR-00000011005` → Shows single certificate
2. **Raw ID**: `/kk/weryfikacja/?cert_no=00000011005` → Shows all certificates for that ID

#### Course Locking
- Checks for existing KR certificate using `external_id`
- If found, displays certificate info and links instead of course
- Prevents duplicate certification

#### Admin Capabilities
- Issue MR/RT certificates without user association
- Specify external_id directly
- See all certificates in system
- Access admin panels in both `/kk/certyfikaty/` and `/kk-safe/`

## Testing Scenarios

### Test 1: KR Certificate via Course
1. User with `kk_system_id = "00000011005"` logs in
2. Navigates to page with `[kk_course]` shortcode
3. Reviews all 6 modules
4. Takes final test, scores 70%+
5. Receives certificate `KR-00000011005`
6. Returns to course page → sees lock message

### Test 2: Admin Issues MR Certificate
1. Admin logs into `/kk/certyfikaty/`
2. Sees "Panel administratora" section
3. Enters external_id `00000011005`
4. Clicks "Wydaj MR po ID"
5. Certificate `MR-00000011005` created
6. Success message shows certificate number

### Test 3: Verification by Full Number
1. Navigate to `/kk/weryfikacja/?cert_no=KR-00000011005`
2. See certificate details:
   - Status: valid
   - Number: KR-00000011005
   - Owner: User Name
   - Role: KR
   - External ID: 00000011005
   - Issued: 2025-10-05

### Test 4: Verification by Raw ID
1. Navigate to `/kk/weryfikacja/?cert_no=00000011005`
2. See list of all certificates:
   - KR-00000011005
   - MR-00000011005
3. Each with full details

## Migration Notes

### For Existing Installations
The plugin update is **backwards compatible**:
- Existing certificates without `external_id` continue to work
- Old certificate format still supported
- New features are additive, not breaking

### Database Migration
On activation, the plugin automatically:
1. Creates `external_id` column if it doesn't exist
2. Adds index for performance
3. Preserves all existing data

### User Profile Setup
Add this code to `functions.php` or custom plugin:
```php
// See dist/README.md for complete code
add_action('show_user_profile', 'kk_add_system_id_field');
add_action('edit_user_profile', 'kk_add_system_id_field');
add_action('personal_options_update', 'kk_save_system_id_field');
add_action('edit_user_profile_update', 'kk_save_system_id_field');
```

## WooCommerce Integration

When WooCommerce is active:
- New tab "Zostań Koordynatorem Reklamy" appears in My Account menu
- Tab automatically displays `[kk_course]` content
- No manual configuration needed
- Gracefully degrades if WooCommerce is deactivated

## Security Considerations

### Access Control
- Certificate issuance with `external_id`: **Admin only** (`manage_options` capability)
- Regular users: Can only issue certificates for themselves
- REST API: Properly authenticated with WordPress nonce
- MU Safe View: Separate authentication check

### Data Validation
- All external_ids sanitized with `sanitize_text_field()`
- SQL queries use `$wpdb->prepare()` for injection prevention
- User input validated before database operations

### Error Handling
- Detailed error messages only shown to authenticated users
- Public verification page shows minimal error details
- Failed operations return proper HTTP status codes

## Performance Optimizations

### Database Indexes
- `external_id_idx` on `kk_certificates` table
- Efficient lookups for verification
- Quick duplicate checks during issuance

### Caching
- `nocache_headers()` on all certificate pages
- Prevents stale data in certificate displays
- REST API responses not cached

## Browser Compatibility
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Uses `fetch()` API with credentials
- ES6 JavaScript (arrow functions, const/let)
- No jQuery dependency
- Progressive enhancement

## Future Enhancements

### Potential Improvements
1. Certificate expiration automation
2. Email notifications on certificate issuance
3. Certificate templates customization UI
4. Bulk certificate import/export
5. Advanced reporting dashboard
6. Multi-language support (WPML integration)

### Extension Points
- `kk_get_user_system_id` filter for custom ID logic
- REST API fully documented for third-party integration
- Template files can be overridden in theme
- Modular design for additional certificate types

## Changelog

### Version 1.1.0 (2025-10-05)
- Added external_id support for certificates
- New certificate format: `{ROLE}-{EXTERNAL_ID}`
- Created `[kk_course]` shortcode with full course flow
- WooCommerce My Account integration
- Admin panel for MR/RT issuance by external_id
- Enhanced verification to support raw ID search
- Improved error messages throughout
- Created MU plugin kk-safe-view.php
- Added admin settings page "KK Lite → Kurs"
- Comprehensive documentation in dist/README.md

## Support and Documentation

### Quick Links
- Full documentation: `dist/README.md`
- Plugin settings: WordPress Admin → KK Lite → Kurs
- Certificate panel: `/kk/certyfikaty/`
- Verification: `/kk/weryfikacja/`
- Safe view: `/kk-safe/`

### Troubleshooting
Enable WordPress debugging:
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs at: `/wp-content/debug.log`

## Credits
- **Plugin**: KK Lite - Kurs Koordynatora
- **Author**: Fundacja Werbekoordinator
- **Version**: 1.1.0
- **Implementation**: GitHub Copilot
