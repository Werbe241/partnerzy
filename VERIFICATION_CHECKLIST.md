# KK Lite Enhancement - Verification Checklist

## Pre-deployment Checklist

### Database
- [ ] Backup existing `wp_kk_certificates` table
- [ ] Test database migration on staging environment
- [ ] Verify index creation on `external_id` column
- [ ] Confirm existing certificates remain intact

### Plugin Files
- [ ] Upload `wp-content/plugins/kk-lite/` to server
- [ ] Upload `wp-content/plugins/koordynator-kurs/` to server
- [ ] Upload `wp-content/mu-plugins/kk-safe-view.php` to server
- [ ] Verify file permissions (644 for files, 755 for directories)
- [ ] Activate plugin in WordPress admin

### Configuration
- [ ] Go to WordPress Admin → KK Lite → Kurs
- [ ] Create page with `[kk_course]` shortcode if needed
- [ ] Select course page in settings
- [ ] Add optional welcome/completion text
- [ ] Save settings

### User Profiles
- [ ] Add `kk_system_id` field to user profiles (see README.md)
- [ ] Set `kk_system_id` for test users
- [ ] Verify fallback to `promoter_id` and `werbeko_id` works

### Rewrite Rules
- [ ] Go to Settings → Permalinks
- [ ] Click "Save Changes" to flush rewrite rules
- [ ] Verify `/kk/certyfikaty/` loads
- [ ] Verify `/kk/weryfikacja/` loads
- [ ] Verify `/kk-safe/` loads

## Functional Testing

### Test 1: External ID Helper
```php
// In WordPress console or test script
$user_id = 1; // Replace with actual user ID
$external_id = kk_get_user_system_id($user_id);
echo "External ID: " . $external_id;
```
- [ ] Returns `kk_system_id` if set
- [ ] Falls back to `promoter_id` if `kk_system_id` empty
- [ ] Falls back to `werbeko_id` if both above empty
- [ ] Filter hook works: `add_filter('kk_get_user_system_id', ...)`

### Test 2: Certificate Issuance (Self)
1. [ ] Log in as regular user with `kk_system_id` set
2. [ ] Navigate to `/kk/certyfikaty/`
3. [ ] Click "Wystaw certyfikat KR"
4. [ ] Verify certificate appears in format `KR-{EXTERNAL_ID}`
5. [ ] Verify `external_id` saved in database
6. [ ] Try to issue duplicate → should fail with error message

### Test 3: Certificate Issuance (Admin - MR/RT)
1. [ ] Log in as administrator
2. [ ] Navigate to `/kk/certyfikaty/`
3. [ ] Verify "Panel administratora" section visible
4. [ ] Enter external_id (e.g., `00000011005`)
5. [ ] Click "Wydaj MR po ID"
6. [ ] Verify success message with certificate number
7. [ ] Verify certificate appears in list
8. [ ] Repeat for RT certificate

### Test 4: Course Flow
1. [ ] Log in as user WITHOUT KR certificate
2. [ ] Navigate to course page (with `[kk_course]` shortcode)
3. [ ] Verify 6 modules listed
4. [ ] Click each module → verify content loads in iframe
5. [ ] Verify all modules marked as completed (✓)
6. [ ] Click "Rozpocznij test końcowy" button
7. [ ] Verify 10 questions appear
8. [ ] Select answers for all questions
9. [ ] Click "Zakończ test i wyślij odpowiedzi"
10. [ ] If score < 70%:
    - [ ] Verify "Nie zdałeś" message
    - [ ] Verify can retake test
11. [ ] If score >= 70%:
    - [ ] Verify "Gratulacje" message with certificate number
    - [ ] Verify certificate in format `KR-{EXTERNAL_ID}`
    - [ ] Reload page → verify lock message shows
    - [ ] Verify certificate number and date displayed

### Test 5: Course Locking
1. [ ] Log in as user WITH existing KR certificate
2. [ ] Navigate to course page
3. [ ] Verify lock message: "Posiadasz już certyfikat..."
4. [ ] Verify certificate number displayed
5. [ ] Verify date displayed
6. [ ] Verify links to `/kk/certyfikaty/` and `/kk/weryfikacja/`
7. [ ] Verify course modules NOT visible

### Test 6: Verification (Full Number)
1. [ ] Navigate to `/kk/weryfikacja/?cert_no=KR-00000011005` (use real cert)
2. [ ] Verify certificate found
3. [ ] Verify displays:
    - [ ] Status (valid)
    - [ ] Number (full)
    - [ ] Owner name
    - [ ] Role (KR/MR/RT)
    - [ ] External ID
    - [ ] Issue date
4. [ ] Try invalid number → verify "Nie znaleziono" message

### Test 7: Verification (Raw ID)
1. [ ] Navigate to `/kk/weryfikacja/?cert_no=00000011005` (raw ID)
2. [ ] Verify shows "Znaleziono certyfikaty dla ID: ..."
3. [ ] Verify lists ALL certificates for that ID:
    - [ ] KR certificate (if exists)
    - [ ] MR certificate (if exists)
    - [ ] RT certificate (if exists)
4. [ ] Each certificate shows full details
5. [ ] Try non-existent ID → verify "Nie znaleziono" message

### Test 8: Safe View (/kk-safe/)
1. [ ] Log in as regular user
2. [ ] Navigate to `/kk-safe/`
3. [ ] Verify certificates load
4. [ ] Verify admin panel NOT visible
5. [ ] Log out and log in as admin
6. [ ] Navigate to `/kk-safe/`
7. [ ] Verify admin panel IS visible
8. [ ] Test MR issuance:
    - [ ] Enter external_id
    - [ ] Click "Wydaj MR"
    - [ ] Verify success message
    - [ ] Verify certificate in list
9. [ ] Test RT issuance (same as MR)

### Test 9: WooCommerce Integration
**Prerequisites**: WooCommerce plugin active
1. [ ] Navigate to My Account page
2. [ ] Verify "Zostań Koordynatorem Reklamy" tab in menu
3. [ ] Click the tab
4. [ ] Verify course interface loads
5. [ ] Verify same functionality as standalone page
6. [ ] Deactivate WooCommerce
7. [ ] Verify course still accessible via direct page
8. [ ] Reactivate WooCommerce
9. [ ] Verify tab reappears

### Test 10: Admin Settings Page
1. [ ] Log in as administrator
2. [ ] Navigate to WordPress Admin → KK Lite → Kurs
3. [ ] Verify page loads
4. [ ] Verify course page dropdown lists all pages
5. [ ] Select a page
6. [ ] Enter welcome text
7. [ ] Enter completion text
8. [ ] Click "Zapisz ustawienia"
9. [ ] Verify "Ustawienia zapisane!" message
10. [ ] Verify settings persist after page reload
11. [ ] Check information section:
    - [ ] Shortcode info displayed
    - [ ] URL links clickable and working
    - [ ] WooCommerce status correct

### Test 11: Error Handling
1. [ ] Try to issue certificate without logging in
    - [ ] Verify proper error message
2. [ ] Try to access `/certificate/my` API without auth
    - [ ] Verify 401/403 error
3. [ ] Try to issue MR/RT with external_id as regular user
    - [ ] Verify "Tylko admin może..." error
4. [ ] Try to verify non-existent certificate
    - [ ] Verify "Nie znaleziono" message
5. [ ] Simulate network error in browser console
    - [ ] Verify "Błąd połączenia" message

### Test 12: REST API
Using REST client (Postman, curl, etc.):

**GET /wp-json/kk/v1/certificate/my**
- [ ] With authentication → returns user's certificates
- [ ] Without authentication → returns error

**POST /wp-json/kk/v1/certificate/issue**
```json
{ "role": "KR" }
```
- [ ] As logged-in user → issues certificate with their external_id
- [ ] Without external_id set → issues with fallback format

```json
{ "role": "MR", "external_id": "00000011005" }
```
- [ ] As admin → issues MR certificate
- [ ] As regular user → returns permission error

**GET /wp-json/kk/v1/certificate/verify?cert_no=KR-00000011005**
- [ ] With full number → returns single certificate
- [ ] With raw ID → returns multiple certificates
- [ ] Public endpoint (no auth required)

**GET /wp-json/kk/v1/certificate/check-kr**
- [ ] Returns `has_kr: true` if user has KR cert
- [ ] Returns certificate details
- [ ] Returns `has_kr: false` if no cert

**POST /wp-json/kk/v1/test-result**
```json
{
  "module_id": 999,
  "score": 85,
  "passed": 1
}
```
- [ ] Saves test result to database
- [ ] Returns `ok: true` with insert ID

## Performance Testing

### Load Testing
- [ ] Test with 100 certificates in database
- [ ] Verify `/certificate/my` response time < 500ms
- [ ] Verify verification page loads quickly
- [ ] Check database query efficiency

### Browser Testing
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Safari (iOS)
- [ ] Chrome Mobile (Android)

## Security Testing

### Authentication
- [ ] Verify REST endpoints require proper authentication
- [ ] Verify admin-only endpoints reject regular users
- [ ] Verify nonce validation works
- [ ] Verify credentials: 'include' in fetch calls

### SQL Injection
- [ ] Test external_id with SQL injection attempts
- [ ] Verify all queries use `$wpdb->prepare()`
- [ ] Test cert_no parameter with special characters

### XSS Prevention
- [ ] Test certificate display with HTML in owner name
- [ ] Verify all output is escaped
- [ ] Test error messages with script tags

## Documentation Review

- [ ] README.md is complete and accurate
- [ ] Code comments are helpful
- [ ] Function names are descriptive
- [ ] No sensitive information in code
- [ ] Examples work as described

## Post-Deployment

### Monitoring
- [ ] Check error logs for PHP warnings/errors
- [ ] Monitor database performance
- [ ] Check for 404 errors on certificate routes
- [ ] Verify no JavaScript console errors

### User Acceptance
- [ ] Test with real users
- [ ] Collect feedback on course flow
- [ ] Verify certificate numbers are correct
- [ ] Ensure admin panel is intuitive

### Rollback Plan
- [ ] Database backup location: __________
- [ ] Previous plugin version saved: [ ]
- [ ] Rollback steps documented: [ ]

## Sign-off

- [ ] Developer: _________________ Date: _______
- [ ] QA Tester: _________________ Date: _______
- [ ] Product Owner: _____________ Date: _______
- [ ] Deployment to Production: _____________ Date: _______

## Notes
_Use this space for any issues found during testing or additional notes:_

---
