# KK Lite - Quick Reference Guide

## 🚀 Quick Start (5 Minutes)

### 1. Install Plugin
```bash
# Upload to your WordPress installation
wp-content/plugins/kk-lite/
wp-content/mu-plugins/kk-safe-view.php
```

### 2. Activate
- Go to: Plugins → KK Lite
- Click: Activate

### 3. Add User Field (functions.php or custom plugin)
```php
add_action('show_user_profile', 'kk_add_system_id_field');
add_action('edit_user_profile', 'kk_add_system_id_field');
function kk_add_system_id_field($user) {
  ?>
  <h3>ID Systemowe</h3>
  <table class="form-table">
    <tr>
      <th><label for="kk_system_id">ID systemowe</label></th>
      <td>
        <input type="text" name="kk_system_id" id="kk_system_id" 
               value="<?php echo esc_attr(get_user_meta($user->ID, 'kk_system_id', true)); ?>" 
               class="regular-text" />
      </td>
    </tr>
  </table>
  <?php
}

add_action('personal_options_update', 'kk_save_system_id_field');
add_action('edit_user_profile_update', 'kk_save_system_id_field');
function kk_save_system_id_field($user_id) {
  if (isset($_POST['kk_system_id'])) {
    update_user_meta($user_id, 'kk_system_id', sanitize_text_field($_POST['kk_system_id']));
  }
}
```

### 4. Create Course Page
- Pages → Add New
- Title: "Zostań Koordynatorem Reklamy"
- Content: `[kk_course]`
- Publish

### 5. Configure Settings
- Go to: KK Lite → Kurs
- Select course page
- Save

### 6. Flush Permalinks
- Settings → Permalinks
- Click "Save Changes"

**Done!** ✅

---

## 📋 Essential Commands

### Check User's External ID
```php
$external_id = kk_get_user_system_id($user_id);
```

### Issue Certificate (Admin)
```bash
POST /wp-json/kk/v1/certificate/issue
{
  "role": "MR",
  "external_id": "00000011005"
}
```

### Verify Certificate
```
/kk/weryfikacja/?cert_no=KR-00000011005
```

### Check KR Status
```bash
GET /wp-json/kk/v1/certificate/check-kr
```

---

## 🔗 Important URLs

| URL | Purpose | Access |
|-----|---------|--------|
| `/kk/certyfikaty/` | Certificate panel | Logged-in users |
| `/kk/weryfikacja/` | Public verification | Anyone |
| `/kk-safe/` | Safe view | Logged-in users |
| Admin → KK Lite → Kurs | Settings page | Admins only |
| My Account → Zostań KR | Course (WooCommerce) | Logged-in users |

---

## 🎯 Certificate Formats

### New Format (with external_id)
```
KR-00000011005
MR-00000011005
RT-00000011005
```

### Old Format (fallback)
```
KR-20251005-1234
```

---

## 🧪 Quick Tests

### Test 1: Issue KR via Course (2 min)
1. User logs in
2. Goes to course page
3. Views 6 modules
4. Takes test → scores 70%+
5. Receives `KR-{external_id}`

### Test 2: Admin Issues MR (30 sec)
1. Admin goes to `/kk/certyfikaty/`
2. Enters external_id: `00000011005`
3. Clicks "Wydaj MR po ID"
4. Gets `MR-00000011005`

### Test 3: Verify by ID (15 sec)
1. Go to `/kk/weryfikacja/?cert_no=00000011005`
2. See all certificates for that ID

---

## 🔧 Troubleshooting

### Certificates don't load
```bash
# Check if user is logged in
# Enable debug mode
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
# Check /wp-content/debug.log
```

### 404 on /kk/* routes
```bash
# Flush permalinks
Settings → Permalinks → Save Changes
```

### External ID not found
```php
// Check user meta
$kk_id = get_user_meta($user_id, 'kk_system_id', true);
$pr_id = get_user_meta($user_id, 'promoter_id', true);
$wb_id = get_user_meta($user_id, 'werbeko_id', true);
```

### Course doesn't lock after KR
```bash
# Check if external_id matches
SELECT * FROM wp_kk_certificates 
WHERE external_id = '00000011005' 
AND role = 'KR';
```

---

## 📊 Database Queries

### Find all certificates for user
```sql
SELECT * FROM wp_kk_certificates 
WHERE user_id = 123 
ORDER BY issued_at DESC;
```

### Find by external_id
```sql
SELECT * FROM wp_kk_certificates 
WHERE external_id = '00000011005';
```

### Check for duplicates
```sql
SELECT external_id, role, COUNT(*) as cnt 
FROM wp_kk_certificates 
WHERE status = 'valid' 
GROUP BY external_id, role 
HAVING cnt > 1;
```

### Recent test results
```sql
SELECT * FROM wp_kk_course_results 
WHERE user_id = 123 
ORDER BY created_at DESC 
LIMIT 10;
```

---

## 🎨 Customization Hooks

### Override external_id logic
```php
add_filter('kk_get_user_system_id', function($system_id, $user_id) {
  // Your custom logic
  $custom_id = get_user_meta($user_id, 'my_custom_id', true);
  return $custom_id ?: $system_id;
}, 10, 2);
```

### Modify certificate number format
```php
// In kk-lite.php, edit kklt_generate_cert_no() function
function kklt_generate_cert_no($role, $external_id = null) {
  if ($external_id) {
    return "{$role}-CUSTOM-{$external_id}";
  }
  // fallback
}
```

---

## 📦 REST API Cheat Sheet

### Authentication
```javascript
fetch('/wp-json/kk/v1/certificate/my', {
  credentials: 'include',
  headers: {
    'X-WP-Nonce': wpApiSettings.nonce
  }
})
```

### All Endpoints
| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| GET | `/certificate/my` | Required | Get user's certificates |
| POST | `/certificate/issue` | Required | Issue certificate |
| GET | `/certificate/verify` | Public | Verify certificate |
| GET | `/certificate/check-kr` | Required | Check KR status |
| POST | `/test-result` | Required | Save test result |

---

## 🔐 Security Checklist

- [x] SQL injection protection (`$wpdb->prepare()`)
- [x] XSS prevention (output escaping)
- [x] REST API authentication
- [x] Admin-only endpoints
- [x] Nonce validation
- [x] Input sanitization

---

## 📈 Performance Tips

### Database Indexes
```sql
-- Already created by plugin
KEY external_id_idx (external_id)
KEY user_idx (user_id)
KEY role_idx (role)
```

### Caching
```php
// Certificates are not cached (always fresh)
nocache_headers();
```

### Query Optimization
- Use `external_id` index for lookups
- Limit results where possible
- Use prepared statements

---

## 📚 Related Documentation

- **Full Guide**: `dist/README.md`
- **Technical Details**: `IMPLEMENTATION_SUMMARY.md`
- **QA Checklist**: `VERIFICATION_CHECKLIST.md`

---

## 🆘 Support

### Common Issues

**Q: External ID not working?**
A: Check user meta: `get_user_meta($user_id, 'kk_system_id', true)`

**Q: Can't issue MR/RT?**
A: Must be admin with `manage_options` capability

**Q: Course not locking?**
A: Verify KR cert exists with matching external_id

**Q: WooCommerce tab missing?**
A: Flush permalinks and check if WooCommerce is active

**Q: REST API errors?**
A: Check authentication and nonce validation

---

## 🎓 Example Workflow

### Complete User Journey

1. **User Registration**
   - Admin sets `kk_system_id = "00000011005"` in profile

2. **Take Course**
   - User visits course page
   - Completes 6 modules
   - Takes test → scores 85%

3. **Get Certificate**
   - System issues `KR-00000011005`
   - User sees success message
   - Course locks

4. **Admin Issues MR**
   - Admin goes to `/kk/certyfikaty/`
   - Enters `00000011005`
   - Issues `MR-00000011005`

5. **Public Verification**
   - Anyone visits `/kk/weryfikacja/?cert_no=00000011005`
   - Sees both KR and MR certificates

---

## 📝 Version Info

- **Plugin**: KK Lite v1.1.0
- **Required PHP**: 7.4+
- **Required WordPress**: 5.8+
- **Optional**: WooCommerce 5.0+

---

## ✨ New in v1.1.0

- External System ID support
- `[kk_course]` shortcode
- WooCommerce integration
- Admin MR/RT issuance
- Enhanced verification
- MU Safe View plugin
- Comprehensive docs

---

**Last Updated**: October 5, 2025
**Maintained By**: Fundacja Werbekoordinator
