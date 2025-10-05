# Deployment Checklist - KK Lite Certificate System v1.1.0

## Pre-Deployment

### 1. Backup Current System
- [ ] Backup WordPress database
- [ ] Backup wp-content/plugins directory
- [ ] Backup wp-content/mu-plugins directory (if exists)
- [ ] Export existing certificates to CSV (optional, for verification)

### 2. Review Current State
- [ ] Document current plugin version (should be 1.0.3 or earlier)
- [ ] Count existing certificates: `SELECT COUNT(*) FROM wp_kk_certificates;`
- [ ] Note any custom modifications to the plugin
- [ ] List users who need external_id set

### 3. Environment Check
- [ ] PHP version >= 7.0
- [ ] WordPress version >= 5.0
- [ ] MySQL/MariaDB privileges to ALTER TABLE
- [ ] Disk space available (minimal, ~1MB)

## Deployment

### 4. Upload Files
- [ ] Upload `wp-content/plugins/kk-lite/` to server
- [ ] Upload `wp-content/mu-plugins/kk-safe-view.php` to server
- [ ] Verify file permissions (644 for PHP files, 755 for directories)
- [ ] Check file ownership (should match web server user)

### 5. Activate Plugin
- [ ] Log in to WordPress admin
- [ ] Navigate to Plugins → Installed Plugins
- [ ] Find "KK Lite - Kurs Koordynatora"
- [ ] Click "Activate" (or "Reactivate" if already active)
- [ ] Watch for any error messages during activation

### 6. Verify Database Migration
- [ ] Check database version option:
  ```sql
  SELECT option_value FROM wp_options WHERE option_name = 'kklt_db_version';
  -- Should return: 1.1.0
  ```
- [ ] Verify external_id column exists:
  ```sql
  DESCRIBE wp_kk_certificates;
  -- Should show 'external_id' column
  ```
- [ ] Check indexes:
  ```sql
  SHOW INDEXES FROM wp_kk_certificates;
  -- Should show 'role_ext_idx' on (role, external_id)
  ```
- [ ] Verify backfilled data (count non-NULL external_ids):
  ```sql
  SELECT COUNT(*) FROM wp_kk_certificates WHERE external_id IS NOT NULL;
  ```

### 7. Set User External IDs
- [ ] Identify users who need external_ids
- [ ] Set kk_system_id for each user:
  ```php
  update_user_meta($user_id, 'kk_system_id', '00000011005');
  ```
  Or via WP-CLI:
  ```bash
  wp user meta update USER_ID kk_system_id 00000011005
  ```
- [ ] Verify user meta is set:
  ```sql
  SELECT user_id, meta_value FROM wp_usermeta 
  WHERE meta_key = 'kk_system_id';
  ```

## Post-Deployment Testing

### 8. Functional Testing - Certificate Issuance

#### Test KR (Regular User)
- [ ] Log in as a user with `kk_system_id` set
- [ ] Visit `/kk/certyfikaty`
- [ ] Click "Wystaw certyfikat KR"
- [ ] Verify cert_no format: `KR-XXXXXXXXX` (matches external_id)
- [ ] Check database:
  ```sql
  SELECT cert_no, role, external_id FROM wp_kk_certificates 
  ORDER BY id DESC LIMIT 1;
  ```

#### Test MR/RT (Admin)
- [ ] Log in as admin
- [ ] Visit `/kk/certyfikaty`
- [ ] Type `00000011005` in "External ID" input
- [ ] Click "Wystaw certyfikat MR"
- [ ] Verify cert_no: `MR-00000011005`
- [ ] Repeat for RT
- [ ] Verify cert_no: `RT-00000011005`

#### Test Idempotency
- [ ] Try issuing same MR-00000011005 again
- [ ] Should return existing certificate (not create duplicate)
- [ ] Verify database has only one MR-00000011005

### 9. Functional Testing - Search & Filter

#### Test Search by External ID
- [ ] Visit `/kk/certyfikaty`
- [ ] Type `00000011005` in search box
- [ ] Click "Szukaj"
- [ ] Verify only certificates with that external_id are shown
- [ ] Clear search (empty input, click Szukaj)
- [ ] Verify it shows your own certificates (backwards compatible)

### 10. Functional Testing - Verification

#### Test Full Certificate Number
- [ ] Visit `/kk/weryfikacja/?cert_no=MR-00000011005`
- [ ] Verify single certificate details are shown
- [ ] Check: cert_no, role, status, owner, issued_at

#### Test Raw External ID
- [ ] Visit `/kk/weryfikacja/?cert_no=00000011005`
- [ ] Verify ALL certificates for that ID are shown
- [ ] Should display multiple certificates (KR, MR, RT if all exist)

#### Test Legacy Certificate
- [ ] Find a legacy certificate (format: MR-20251005-6023)
- [ ] Visit `/kk/weryfikacja/?cert_no=MR-20251005-6023`
- [ ] Verify it still displays correctly
- [ ] Confirm no "not found" errors

### 11. API Testing

#### Test Issue Endpoint
```bash
curl -X POST https://your-site.com/wp-json/kk/v1/certificate/issue \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  --cookie "wordpress_logged_in_HASH=COOKIE" \
  -d '{"role":"KR"}' | jq .
```
- [ ] Response includes `ok: true`
- [ ] Response includes `data.cert_no` in new format
- [ ] Response includes `data.external_id`

#### Test My Certificates Endpoint
```bash
curl "https://your-site.com/wp-json/kk/v1/certificate/my?external_id=00000011005" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  --cookie "wordpress_logged_in_HASH=COOKIE" | jq .
```
- [ ] Returns array of certificates
- [ ] Each item has `external_id` field
- [ ] Filtering works correctly

#### Test Verify Endpoint
```bash
# Full cert_no
curl "https://your-site.com/wp-json/kk/v1/certificate/verify?cert_no=MR-00000011005" | jq .

# Raw external_id
curl "https://your-site.com/wp-json/kk/v1/certificate/verify?cert_no=00000011005" | jq .
```
- [ ] Full cert_no returns single object
- [ ] Raw external_id returns array
- [ ] Both include all expected fields

### 12. Security Testing
- [ ] Try issuing MR with external_id as non-admin (should fail or use own ID)
- [ ] Try accessing /certificate/my without login (should fail)
- [ ] Verify /certificate/verify works without login (public endpoint)
- [ ] Test XSS in cert_no parameter (should be sanitized)
- [ ] Verify SQL injection protection in external_id queries

### 13. Performance Testing
- [ ] Issue 10 certificates rapidly (test idempotency)
- [ ] Search for external_id with 100+ certificates (test index)
- [ ] Verify page load times are acceptable
- [ ] Check database query log for slow queries

### 14. Browser Compatibility
- [ ] Test in Chrome/Edge
- [ ] Test in Firefox
- [ ] Test in Safari (if applicable)
- [ ] Test mobile view (responsive)

## Post-Deployment Validation

### 15. Data Integrity Check
- [ ] Count certificates before and after (should match):
  ```sql
  SELECT COUNT(*) FROM wp_kk_certificates;
  ```
- [ ] Verify no duplicate cert_no values:
  ```sql
  SELECT cert_no, COUNT(*) FROM wp_kk_certificates 
  GROUP BY cert_no HAVING COUNT(*) > 1;
  -- Should return 0 rows
  ```
- [ ] Check for orphaned records:
  ```sql
  SELECT COUNT(*) FROM wp_kk_certificates c 
  LEFT JOIN wp_users u ON c.user_id = u.ID 
  WHERE u.ID IS NULL;
  -- Should return 0
  ```

### 16. Documentation Review
- [ ] Review README.md for accuracy
- [ ] Verify QUICKSTART.md matches your deployment
- [ ] Check ARCHITECTURE.md aligns with implementation
- [ ] Update any custom documentation you have

### 17. User Communication
- [ ] Notify users of upgrade
- [ ] Explain new certificate format
- [ ] Provide instructions for searching by external_id
- [ ] Share verification URL format

## Rollback Plan (If Needed)

### 18. Emergency Rollback Steps
If critical issues are found:

- [ ] Deactivate plugin via WordPress admin
- [ ] Or via database:
  ```sql
  UPDATE wp_options SET option_value = 'a:0:{}' 
  WHERE option_name = 'active_plugins';
  ```
- [ ] Restore plugin files from backup
- [ ] Restore database from backup (if data corruption occurred)
- [ ] Investigate and document issue
- [ ] Plan corrective action

### 19. Partial Rollback (Keep Data)
To rollback but keep new data:

- [ ] Keep external_id column (harmless, may be useful)
- [ ] Restore old plugin files
- [ ] Update version option:
  ```sql
  UPDATE wp_options SET option_value = '1.0.3' 
  WHERE option_name = 'kklt_db_version';
  ```
- [ ] Document reason for rollback

## Monitoring (First 7 Days)

### 20. Ongoing Monitoring
- [ ] Monitor error logs: `wp-content/debug.log`
- [ ] Check database slow query log
- [ ] Review user feedback/support tickets
- [ ] Monitor certificate issuance rate
- [ ] Track API error rates
- [ ] Check for duplicate certificates

### 21. Success Metrics
After 1 week, verify:
- [ ] No critical errors in logs
- [ ] User complaints < 1%
- [ ] All new certificates in new format
- [ ] Legacy certificates still working
- [ ] API endpoints responding < 500ms
- [ ] Database queries using indexes

## Sign-Off

### 22. Deployment Completion
- [ ] All tests passed
- [ ] No critical errors
- [ ] Users notified
- [ ] Documentation updated
- [ ] Monitoring in place
- [ ] Team briefed on new features

**Deployed by**: ___________________________  
**Date**: ___________________________  
**Version**: 1.1.0  
**Sign-off**: ___________________________  

---

## Support

If you encounter issues:
1. Check `wp-content/debug.log`
2. Review QUICKSTART.md troubleshooting section
3. Verify database migration completed
4. Check user external_id is set
5. Test API endpoints directly
6. Contact support with error details

**Emergency Contact**: support@werbekoordinator.pl  
**Documentation**: See README.md, QUICKSTART.md, ARCHITECTURE.md
