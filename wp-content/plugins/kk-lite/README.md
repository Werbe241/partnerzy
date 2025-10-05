# KK Lite - Certificate System Upgrade

## Overview
This upgrade migrates the certificate numbering system from the legacy `ROLE-DATE-RANDOM` format to the new `ROLE-EXTERNAL_ID` format, enabling better integration with external business systems.

## Changes Summary

### 1. Database Schema
- **New column**: `external_id` VARCHAR(64) NULL added to `{prefix}kk_certificates`
- **New index**: Composite index on `(role, external_id)` for fast lookups
- **Migration**: Automatic backfilling of `external_id` from existing certificates where possible

### 2. Certificate Number Format
**Old format**: `MR-20251005-6023` (ROLE-DATE-RANDOM)
**New format**: `MR-00000011005` (ROLE-EXTERNAL_ID)

### 3. Helper Function
- `kk_get_user_system_id($user_id)`: Retrieves external System ID with precedence:
  1. `kk_system_id` (user meta)
  2. `promoter_id` (user meta)
  3. `werbeko_id` (user meta)
- Includes filter: `apply_filters('kk_get_user_system_id', $id, $user_id)` for customization

### 4. REST API Updates

#### POST /wp-json/kk/v1/certificate/issue
**Request body**:
```json
{
  "role": "KR|MR|RT",
  "external_id": "00000011005"  // Optional, for MR/RT, admin-only
}
```

**Behavior**:
- **KR**: Always uses the logged-in user's external_id (ignores provided external_id)
- **MR/RT**: 
  - Admin can provide `external_id` to issue for any system ID
  - Non-admin uses their own external_id
- **Idempotent**: Returns existing valid certificate if one exists for same role+external_id

**Response**:
```json
{
  "ok": true,
  "data": {
    "role": "MR",
    "cert_no": "MR-00000011005",
    "external_id": "00000011005",
    "status": "valid",
    "issued_at": "2025-01-05 12:00:00"
  }
}
```

#### GET /wp-json/kk/v1/certificate/my
**Query parameters**:
- `external_id` (optional): Filter certificates by external_id

**Examples**:
- `/certificate/my` - Returns all certificates for logged-in user (backwards compatible)
- `/certificate/my?external_id=00000011005` - Returns all certificates for that external_id

#### GET /wp-json/kk/v1/certificate/verify?cert_no=VALUE
**Behavior**:
- If `cert_no` contains a dash (e.g., `MR-00000011005`): Returns single certificate
- If `cert_no` is digits only (e.g., `00000011005`): Returns array of all certificates for that external_id

**Response for full cert_no**:
```json
{
  "found": true,
  "data": {
    "cert_no": "MR-00000011005",
    "role": "MR",
    "status": "valid",
    "owner": "John Doe",
    "issued_at": "2025-01-05 12:00:00",
    "external_id": "00000011005"
  }
}
```

**Response for raw external_id**:
```json
{
  "found": true,
  "data": [
    {
      "cert_no": "KR-00000011005",
      "role": "KR",
      "status": "valid",
      "owner": "John Doe",
      "issued_at": "2025-01-04 10:00:00",
      "external_id": "00000011005"
    },
    {
      "cert_no": "MR-00000011005",
      "role": "MR",
      "status": "valid",
      "owner": "John Doe",
      "issued_at": "2025-01-05 12:00:00",
      "external_id": "00000011005"
    }
  ]
}
```

### 5. Frontend Updates

#### /kk/certyfikaty (Certificate Panel)
- **Search input**: Filter certificates by typing external_id (e.g., `00000011005`)
- **Issue input**: Admins can provide external_id when issuing MR/RT certificates
- **Behavior**: 
  - Empty search shows user's own certificates (backwards compatible)
  - With external_id, shows only certificates for that ID

#### /kk/weryfikacja (Verification)
- Handles both full certificate numbers (e.g., `KR-00000011005`) and raw external_id (e.g., `00000011005`)
- Shows single certificate or list of all certificates for an external_id

### 6. MU Plugin (kk-safe-view.php)
- Enhanced version of certificate panel with same functionality
- Keeps existing NONCE injection for stability
- Routes: Uses `kk_safe_cert_view` and `kk_safe_verify_view` query vars

## Migration & Backwards Compatibility

### Automatic Migration
The plugin automatically runs database upgrades on activation and when loaded:
1. Checks for `external_id` column
2. Adds column and index if missing
3. Backfills `external_id` from existing `cert_no` where format matches `ROLE-DIGITS`
4. Legacy certificates (e.g., `MR-20251005-6023`) remain unchanged and continue to work

### Backwards Compatibility
- **Legacy certificates**: Continue to exist and verify correctly
- **API**: `/certificate/my` without parameters still shows user's certificates
- **Verification**: Legacy cert numbers still resolve via verify endpoint
- **No data loss**: All existing certificates remain intact

## Usage Examples

### Issue Certificate with Specific External ID (Admin Only)
```javascript
// Admin issuing MR certificate for external_id 00000011005
const response = await fetch('/wp-json/kk/v1/certificate/issue', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': nonce
  },
  body: JSON.stringify({
    role: 'MR',
    external_id: '00000011005'
  }),
  credentials: 'include'
});
```

### Search Certificates by External ID
```javascript
// Get all certificates for external_id 00000011005
const response = await fetch('/wp-json/kk/v1/certificate/my?external_id=00000011005', {
  headers: { 'X-WP-Nonce': nonce },
  credentials: 'include'
});
```

### Verify Certificate
```javascript
// Verify full certificate number
const response1 = await fetch('/wp-json/kk/v1/certificate/verify?cert_no=MR-00000011005');

// Or verify by external_id (returns all certs for this ID)
const response2 = await fetch('/wp-json/kk/v1/certificate/verify?cert_no=00000011005');
```

## Testing

### Manual Testing Checklist
1. ✓ Issue KR certificate - should use logged-in user's external_id
2. ✓ Issue MR/RT as admin with external_id - should create cert with that ID
3. ✓ Issue MR/RT as non-admin - should use their own external_id
4. ✓ Issue duplicate cert - should return existing cert (idempotent)
5. ✓ Search by external_id - should show only certs for that ID
6. ✓ Verify with full cert_no - should show single cert
7. ✓ Verify with raw external_id - should show all certs for that ID
8. ✓ Legacy certs - should still verify and appear in lists

### Required User Meta
For the system to work, users need at least one of these meta fields:
- `kk_system_id` (preferred)
- `promoter_id` (fallback)
- `werbeko_id` (fallback)

Example to set user meta:
```php
update_user_meta(123, 'kk_system_id', '00000011005');
```

## Customization

### Override External ID Retrieval
```php
add_filter('kk_get_user_system_id', function($id, $user_id) {
  // Custom logic to get external_id
  // For example, from a custom table or API
  return $id;
}, 10, 2);
```

## Files Modified
- `wp-content/plugins/kk-lite/kk-lite.php` - Main plugin with all backend logic
- `wp-content/plugins/kk-lite/templates/app.html` - Certificate panel UI
- `wp-content/plugins/kk-lite/templates/verify.html` - Verification UI
- `wp-content/mu-plugins/kk-safe-view.php` - MU plugin safe view (NEW)

## Version
- Previous: 1.0.3
- Current: 1.1.0
