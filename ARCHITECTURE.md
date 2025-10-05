# KK Lite Certificate System - Architecture Diagram

## System Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                         USER INTERACTION                             │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                    ┌─────────────┴─────────────┐
                    │                           │
                    ▼                           ▼
          ┌──────────────────┐        ┌──────────────────┐
          │  /kk/certyfikaty │        │ /kk/weryfikacja  │
          │  (Certificate    │        │  (Verification   │
          │   Panel)         │        │   Panel)         │
          └──────────────────┘        └──────────────────┘
                    │                           │
                    │                           │
                    ▼                           ▼
          ┌──────────────────────────────────────────────┐
          │         REST API LAYER (kk/v1)               │
          ├──────────────────────────────────────────────┤
          │  POST /certificate/issue                     │
          │  GET  /certificate/my?external_id=X          │
          │  GET  /certificate/verify?cert_no=X          │
          └──────────────────────────────────────────────┘
                    │                           │
                    │                           │
                    ▼                           ▼
          ┌─────────────────────────────────────────────┐
          │         BUSINESS LOGIC LAYER                │
          ├─────────────────────────────────────────────┤
          │  • kk_get_user_system_id()                  │
          │  • kklt_generate_cert_no()                  │
          │  • Idempotency check                        │
          │  • Permission validation                    │
          └─────────────────────────────────────────────┘
                    │                           │
                    │                           │
                    ▼                           ▼
          ┌─────────────────────────────────────────────┐
          │         DATABASE LAYER                      │
          ├─────────────────────────────────────────────┤
          │  wp_kk_certificates                         │
          │  ├── id (PK)                                │
          │  ├── cert_no (UNIQUE)                       │
          │  ├── user_id                                │
          │  ├── role                                   │
          │  ├── external_id ★ NEW                      │
          │  ├── issued_at                              │
          │  ├── valid_until                            │
          │  ├── status                                 │
          │  └── meta                                   │
          │                                             │
          │  Indexes:                                   │
          │  • PRIMARY (id)                             │
          │  • UNIQUE (cert_no)                         │
          │  • KEY (user_id)                            │
          │  • KEY (role)                               │
          │  • KEY (role, external_id) ★ NEW           │
          └─────────────────────────────────────────────┘
                              │
                              │
                              ▼
          ┌─────────────────────────────────────────────┐
          │         USER META LAYER                     │
          ├─────────────────────────────────────────────┤
          │  wp_usermeta                                │
          │  • kk_system_id (preferred)                 │
          │  • promoter_id (fallback 1)                 │
          │  • werbeko_id (fallback 2)                  │
          └─────────────────────────────────────────────┘
```

## Certificate Issuance Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                    CERTIFICATE ISSUANCE                              │
└─────────────────────────────────────────────────────────────────────┘

User clicks "Wystaw certyfikat KR/MR/RT"
                │
                ▼
┌───────────────────────────────────────┐
│  Check role type                      │
│  ┌─────────┬─────────┬─────────┐     │
│  │   KR    │   MR    │   RT    │     │
│  └─────────┴─────────┴─────────┘     │
└───────────────────────────────────────┘
                │
                ▼
┌───────────────────────────────────────────────────────┐
│  Determine external_id source                         │
│  ┌───────────────────────────────────────────────┐   │
│  │  KR: Always logged-in user's external_id     │   │
│  │  MR/RT (admin): Can specify external_id      │   │
│  │  MR/RT (non-admin): Use own external_id      │   │
│  └───────────────────────────────────────────────┘   │
└───────────────────────────────────────────────────────┘
                │
                ▼
┌───────────────────────────────────────────────────────┐
│  Get external_id via kk_get_user_system_id()         │
│  ┌───────────────────────────────────────────────┐   │
│  │  1. Try kk_system_id                         │   │
│  │  2. Fallback to promoter_id                  │   │
│  │  3. Fallback to werbeko_id                   │   │
│  │  4. Return NULL if none found                │   │
│  └───────────────────────────────────────────────┘   │
└───────────────────────────────────────────────────────┘
                │
                ▼
┌───────────────────────────────────────────────────────┐
│  Generate cert_no: ROLE-EXTERNAL_ID                  │
│  Example: MR-00000011005                             │
└───────────────────────────────────────────────────────┘
                │
                ▼
┌───────────────────────────────────────────────────────┐
│  Check for existing certificate (IDEMPOTENT)         │
│  WHERE role = ? AND external_id = ? AND status = ?   │
└───────────────────────────────────────────────────────┘
                │
        ┌───────┴───────┐
        │               │
        ▼               ▼
    EXISTS          NOT EXISTS
        │               │
        ▼               ▼
    RETURN          INSERT NEW
    EXISTING        CERTIFICATE
    CERT                │
        │               │
        └───────┬───────┘
                ▼
        ┌───────────────┐
        │  Return cert  │
        │  details to   │
        │  frontend     │
        └───────────────┘
```

## Verification Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                    CERTIFICATE VERIFICATION                          │
└─────────────────────────────────────────────────────────────────────┘

User enters value in verification page
                │
                ▼
┌───────────────────────────────────────────────────────┐
│  Parse input: Check for dash (-)                     │
└───────────────────────────────────────────────────────┘
                │
        ┌───────┴───────┐
        │               │
        ▼               ▼
    HAS DASH        NO DASH
    (full cert)     (external_id)
        │               │
        ▼               ▼
┌───────────────┐   ┌───────────────────┐
│ Query by      │   │ Query all certs   │
│ cert_no       │   │ with external_id  │
│               │   │                   │
│ Return single │   │ Return array of   │
│ certificate   │   │ certificates      │
└───────────────┘   └───────────────────┘
        │               │
        └───────┬───────┘
                ▼
        ┌───────────────┐
        │ Display       │
        │ result(s) to  │
        │ user          │
        └───────────────┘
```

## Data Model

### Certificate Record
```
{
  id: 123,
  cert_no: "MR-00000011005",        // ROLE-EXTERNAL_ID format
  user_id: 456,                      // WordPress user ID (owner)
  role: "MR",                        // KR|MR|RT
  external_id: "00000011005",        // ★ NEW: System ID
  issued_at: "2025-01-05 12:00:00",
  valid_until: null,                 // NULL = unlimited
  status: "valid",                   // valid|revoked|expired
  meta: null                         // JSON metadata
}
```

### User Meta (for external_id)
```
User ID 123:
  meta_key: kk_system_id    meta_value: "00000011005"  ← Preferred
  meta_key: promoter_id     meta_value: "..."          ← Fallback 1
  meta_key: werbeko_id      meta_value: "..."          ← Fallback 2
```

## Security Model

```
┌─────────────────────────────────────────────────────────────────────┐
│                         SECURITY LAYERS                              │
└─────────────────────────────────────────────────────────────────────┘

LAYER 1: Authentication
  ├─ is_user_logged_in() for all certificate operations
  ├─ Public access only for verification endpoint
  └─ Nonce validation (optional but recommended)

LAYER 2: Authorization
  ├─ current_user_can('manage_options') for admin-only features
  ├─ User can only issue certs for self (unless admin)
  └─ KR role always uses logged-in user's external_id

LAYER 3: Input Validation
  ├─ sanitize_text_field() for all inputs
  ├─ intval() for numeric IDs
  ├─ Role whitelist: ['KR', 'MR', 'RT']
  └─ External ID format: digits only

LAYER 4: SQL Injection Prevention
  ├─ $wpdb->prepare() for all queries
  ├─ Parameterized statements
  └─ No direct SQL interpolation

LAYER 5: XSS Prevention
  ├─ esc_html() for output
  ├─ esc_js() for JavaScript
  ├─ esc_url_raw() for URLs
  └─ JSON encoding for data
```

## Migration Strategy

```
┌─────────────────────────────────────────────────────────────────────┐
│                         MIGRATION PROCESS                            │
└─────────────────────────────────────────────────────────────────────┘

Plugin Activation OR Version Check
                │
                ▼
        Check DB version
        (option: kklt_db_version)
                │
                ▼
        Version < 1.1.0?
                │
        ┌───────┴───────┐
        │               │
        NO              YES
        │               │
        ▼               ▼
    Skip            Run Migration
    Migration           │
        │               ▼
        │       Check if external_id
        │       column exists
        │               │
        │       ┌───────┴───────┐
        │       │               │
        │       EXISTS      NOT EXISTS
        │       │               │
        │       ▼               ▼
        │    Skip          ADD COLUMN
        │    Column        external_id
        │       │               │
        │       │               ▼
        │       │          ADD INDEX
        │       │          (role, external_id)
        │       │               │
        │       │               ▼
        │       │          Backfill Data
        │       │          ┌─────────────┐
        │       │          │ For each    │
        │       │          │ certificate │
        │       │          │ matching    │
        │       │          │ ROLE-DIGITS │
        │       │          │ extract ID  │
        │       │          └─────────────┘
        │       │               │
        │       └───────┬───────┘
        │               ▼
        │       Update version
        │       option to 1.1.0
        │               │
        └───────┬───────┘
                ▼
        Migration Complete
```

## API Request/Response Examples

### Issue Certificate
```
POST /wp-json/kk/v1/certificate/issue
Content-Type: application/json
X-WP-Nonce: abc123...

Request:
{
  "role": "MR",
  "external_id": "00000011005"  // Optional, admin only
}

Response (201):
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

### Get Certificates
```
GET /wp-json/kk/v1/certificate/my?external_id=00000011005
X-WP-Nonce: abc123...

Response (200):
{
  "items": [
    {
      "cert_no": "KR-00000011005",
      "role": "KR",
      "issued_at": "2025-01-04 10:00:00",
      "valid_until": null,
      "status": "valid",
      "external_id": "00000011005"
    },
    {
      "cert_no": "MR-00000011005",
      "role": "MR",
      "issued_at": "2025-01-05 12:00:00",
      "valid_until": null,
      "status": "valid",
      "external_id": "00000011005"
    }
  ]
}
```

### Verify Certificate
```
GET /wp-json/kk/v1/certificate/verify?cert_no=MR-00000011005

Response (200) - Single cert:
{
  "found": true,
  "data": {
    "cert_no": "MR-00000011005",
    "role": "MR",
    "issued_at": "2025-01-05 12:00:00",
    "valid_until": null,
    "status": "valid",
    "external_id": "00000011005",
    "owner": "John Doe"
  }
}

GET /wp-json/kk/v1/certificate/verify?cert_no=00000011005

Response (200) - Multiple certs:
{
  "found": true,
  "data": [
    { /* cert 1 */ },
    { /* cert 2 */ }
  ]
}
```
