# Quick Start Guide - KK Lite Certificate System

## Installation

### Step 1: Deploy Files
Copy the `wp-content` directory to your WordPress installation:

```bash
# From repository root
cp -r wp-content/* /path/to/wordpress/wp-content/
```

Or upload via FTP/SFTP to your WordPress site's `wp-content` directory.

### Step 2: Activate Plugin
1. Log in to WordPress admin
2. Navigate to **Plugins** → **Installed Plugins**
3. Find "KK Lite - Kurs Koordynatora"
4. Click **Activate**

The plugin will automatically:
- Create database tables (if not exists)
- Add `external_id` column to certificates table
- Create indexes for performance
- Backfill existing certificates
- Set up rewrite rules

### Step 3: Set User External IDs
For each user who will receive certificates, set their external System ID:

**Via WordPress Admin (Users → Edit User → Custom Fields)**:
```
Key: kk_system_id
Value: 00000011005
```

**Or via PHP**:
```php
// In your theme's functions.php or a custom plugin
update_user_meta(123, 'kk_system_id', '00000011005');
```

**Or via WP-CLI**:
```bash
wp user meta update 123 kk_system_id 00000011005
```

## Basic Usage

### Issue Certificate for Yourself (Any User)
Visit: `https://your-site.com/kk/certyfikaty`

1. Click "Wystaw certyfikat KR" (for Koordynator role)
2. Certificate is automatically generated: `KR-00000011005`

### Issue Certificate for Someone Else (Admin Only)
Visit: `https://your-site.com/kk/certyfikaty`

1. Type external_id in the "External ID dla MR/RT" field: `00000011005`
2. Click "Wystaw certyfikat MR" or "Wystaw certyfikat RT"
3. Certificate is generated: `MR-00000011005` or `RT-00000011005`

### Search Certificates by External ID
Visit: `https://your-site.com/kk/certyfikaty`

1. Type external_id in search box: `00000011005`
2. Click "Szukaj"
3. View all certificates for that ID

### Verify Certificate
Visit: `https://your-site.com/kk/weryfikacja/?cert_no=MR-00000011005`

Or verify all certificates for an ID:
Visit: `https://your-site.com/kk/weryfikacja/?cert_no=00000011005`

## API Usage

### Issue Certificate via API

**For yourself (KR)**:
```bash
curl -X POST https://your-site.com/wp-json/kk/v1/certificate/issue \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  --cookie "wordpress_logged_in_HASH=YOUR_COOKIE" \
  -d '{"role":"KR"}'
```

**For someone else (MR, admin only)**:
```bash
curl -X POST https://your-site.com/wp-json/kk/v1/certificate/issue \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  --cookie "wordpress_logged_in_HASH=YOUR_COOKIE" \
  -d '{"role":"MR","external_id":"00000011005"}'
```

### Get Certificates via API

**Your certificates**:
```bash
curl https://your-site.com/wp-json/kk/v1/certificate/my \
  -H "X-WP-Nonce: YOUR_NONCE" \
  --cookie "wordpress_logged_in_HASH=YOUR_COOKIE"
```

**Certificates for specific external_id**:
```bash
curl "https://your-site.com/wp-json/kk/v1/certificate/my?external_id=00000011005" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  --cookie "wordpress_logged_in_HASH=YOUR_COOKIE"
```

### Verify Certificate via API

**Single certificate**:
```bash
curl "https://your-site.com/wp-json/kk/v1/certificate/verify?cert_no=MR-00000011005"
```

**All certificates for external_id**:
```bash
curl "https://your-site.com/wp-json/kk/v1/certificate/verify?cert_no=00000011005"
```

## Configuration

### Custom External ID Source
Add this to your theme's `functions.php`:

```php
add_filter('kk_get_user_system_id', function($id, $user_id) {
    // Custom logic to get external_id
    // For example, from a CRM API or custom table
    
    if (empty($id)) {
        // Fetch from custom source
        $custom_id = my_custom_function($user_id);
        return $custom_id;
    }
    
    return $id;
}, 10, 2);
```

### Bulk Import External IDs
Create a PHP script or WP-CLI command:

```php
// Import from CSV
$csv = fopen('external_ids.csv', 'r');
while (($row = fgetcsv($csv)) !== false) {
    list($user_id, $external_id) = $row;
    update_user_meta($user_id, 'kk_system_id', $external_id);
}
fclose($csv);
```

## Troubleshooting

### "User does not have external_id" Error
**Solution**: Set user meta for the logged-in user:
```php
update_user_meta(get_current_user_id(), 'kk_system_id', '00000011005');
```

### Certificates Not Showing
**Checklist**:
1. User is logged in
2. User has certificates in database
3. If searching by external_id, it matches exactly
4. Check browser console for JavaScript errors

### 404 on /kk/certyfikaty
**Solution**: Flush rewrite rules
```php
// Add to functions.php temporarily, visit any page, then remove
flush_rewrite_rules();
```

Or deactivate and reactivate the plugin.

### Database Not Migrating
**Solution**: Manually trigger upgrade
```php
// Add to functions.php temporarily
add_action('init', function() {
    if (function_exists('kklt_db_upgrade')) {
        kklt_db_upgrade();
        echo "Migration complete!";
    }
});
```

## Testing Checklist

After installation, verify these scenarios:

- [ ] Visit /kk/certyfikaty (page loads)
- [ ] Visit /kk/weryfikacja (page loads)
- [ ] Issue KR certificate for yourself
- [ ] Search by external_id
- [ ] Verify certificate by full cert_no
- [ ] Verify certificate by external_id only
- [ ] (Admin) Issue MR with custom external_id
- [ ] Check database for external_id column
- [ ] Legacy certificates still work

## Support

### Check Plugin Version
In WordPress admin: **Plugins** → find "KK Lite" → should show version 1.1.0

### Check Database Version
```sql
SELECT option_value FROM wp_options WHERE option_name = 'kklt_db_version';
-- Should return: 1.1.0
```

### Verify Database Schema
```sql
DESCRIBE wp_kk_certificates;
-- Should show 'external_id' column
```

### Check Indexes
```sql
SHOW INDEXES FROM wp_kk_certificates;
-- Should show 'role_ext_idx' on (role, external_id)
```

## Resources

- **Plugin Documentation**: `wp-content/plugins/kk-lite/README.md`
- **Implementation Summary**: `IMPLEMENTATION_SUMMARY.md`
- **Architecture Diagram**: `ARCHITECTURE.md`

## Next Steps

1. Set up user external IDs for all users
2. Test certificate issuance
3. Integrate with your external system
4. Consider adding email notifications
5. Set up monitoring for certificate issuance

---

**Version**: 1.1.0  
**Last Updated**: 2025-01-05  
**Author**: Fundacja Werbekoordinator
