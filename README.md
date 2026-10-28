# HRMS Employee Information Wizard Form

A modern, professional, multi-step employee information form for HRMS (Human Resource Management System) built with vanilla PHP, HTML5, CSS3, and JavaScript.

## Features

- **11-Step Wizard**: Mobile verification → Personal Basic → Address → Aadhaar/PAN → Personal Details → Next of Kin → Referral → Education → Teacher Documents → Work Experience → Employment Details → Review & Submit
- **Employment Details Changes**: Existing employment values are read-only by default; each field can be independently kept or replaced, with updated values validated and submitted only when selected.
- **Mobile-First Responsive**: Works on desktop (1920px, 1440px), laptop (1366px, 1024px), tablet (768px), and mobile (375px+)
- **Conditional Fields**: Dynamic show/hide based on user selections
- **Repeaters**: Dynamic education qualifications, work experience, family members
- **File Uploads**: Secure document upload with validation
- **Verification System**: Correct/Wrong verification for existing data
- **Draft Saving**: Automatically saves form values in this browser and restores them after refresh
- **Accessibility**: WCAG 2.1 AA compliant
- **Security**: CSRF protection, server-side validation, secure file handling

## Technology Stack

- **Frontend**: HTML5, CSS3 (Custom Properties), Vanilla JavaScript (ES6+), Bootstrap 5
- **Backend**: PHP 8.0+, MySQL 5.7+/MariaDB 10.2+
- **Libraries**: Bootstrap 5, Bootstrap Icons
- **Architecture**: Modular, maintainable, separation of concerns

## Project Structure

```
HRMS-form/
├── index.php                          # Main entry point
├── assets/
│   ├── css/
│   │   ├── variables.css              # CSS custom properties
│   │   ├── layout.css                 # Base layout styles
│   │   ├── components.css             # Reusable UI components
│   │   ├── wizard.css                 # Wizard-specific styles
│   │   └── responsive.css             # Responsive breakpoints
│   ├── js/
│   │   ├── api.js                     # API communication layer
│   │   ├── app.js                     # Main application entry
│   │   ├── conditional-fields.js      # Conditional field engine
│   │   ├── education.js               # Education repeater
│   │   ├── file-upload.js             # File upload handling
│   │   ├── form-state.js              # Centralized state management
│   │   ├── referral.js                # Referral/family repeaters
│   │   ├── review.js                  # Review & submit
│   │   ├── validation.js              # Form validation
│   │   ├── wizard.js                  # Wizard navigation
│   │   └── work-experience.js         # Work experience repeater
│   └── images/
├── components/
│   ├── mobile-verification.php        # Step 0: Mobile verification
│   ├── step-personal-basic.php        # Step 1: Personal basic info
│   ├── step-address.php               # Step 2: Address details
│   ├── step-aadhaar-pan.php           # Step 3: Aadhaar & PAN
│   ├── step-personal-details.php      # Step 4: Personal details
│   ├── step-next-of-kin.php           # Step 5: Next of kin
│   ├── step-referral.php              # Step 6: Referral info
│   ├── step-education.php             # Step 7: Education
│   ├── step-teacher-documents.php     # Step 8: Teacher & documents
│   ├── step-work-experience.php       # Step 9: Work experience
│   ├── step-employment-details.php    # Step 10: Employment details
│   └── step-review.php                # Step 11: Review & submit
└── backend-integration/
    ├── config/
    │   └── database.php               # Database configuration
    ├── api/
    │   ├── csrf-token.php             # CSRF token endpoint
    │   ├── verify-mobile.php          # Mobile verification
    │   ├── verify-otp.php             # OTP verification
    │   ├── get-employee.php           # Get employee data
    │   ├── upload-document.php        # Document upload
    │   ├── save-draft.php             # Save draft
    │   ├── get-draft.php              # Get draft
    │   ├── submit-form.php            # Final submission
    │   ├── get-schools.php            # Schools list
    │   └── get-units.php              # Units list
    └── database-schema.sql            # Database schema
```

## Installation

### Prerequisites

- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.2+
- Web server (Apache/Nginx) with mod_rewrite
- Composer (optional, for dependencies)

### Setup

1. **Clone/Extract** the project to your web server directory:
   ```bash
   # Example for XAMPP
   cp -r HRMS-form /xampp/htdocs/
   ```

2. **Import the finalized database**:
   ```powershell
   # Import backend-integration/final_hrms_form.sql using phpMyAdmin,
   # or from a terminal:
   mysql -h 127.0.0.1 -u root -P 3307 < backend-integration/final_hrms_form.sql
   ```
   The SQL file creates/selects `final_temp_db`, imports the finalized HRMS
   tables, and creates the companion OTP, draft/submission, and document tables
   required by the onboarding flow. Do not import the older
   `backend-integration/database-schema.sql` for this database. Import into a
   fresh database only; do not re-import the full dump over an existing copy.

3. **Configure credentials**:
   Copy `.env.example` to `.env` in the project root, then edit the local values.
   The project includes a `.env` configured for XAMPP at `127.0.0.1:3307` with
   database `final_temp_db`, user `root`, and an empty password. Replace the
   username/password if your local MySQL credentials differ. `.env` is ignored
   by git; do not commit it or put production secrets in `.env.example`.

   Supported settings are `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`,
   `DB_CHARSET`, `ADMIN_DB_NAME`, `ADMIN_DB_HOST`, `ADMIN_DB_PORT`,
   `ADMIN_DB_USER`, `ADMIN_DB_PASS`, and `OTP_EXPIRY_MINUTES`. Environment
   variables supplied by Apache/the hosting platform take precedence over values
   from `.env`.

   OTP delivery is currently local-development-only because no SMS provider is
   configured. From a loopback request on `localhost`, the generated OTP is
   returned only to the local browser and shown in the login page's OTP popup.
   It is never returned for requests from other hosts. Those requests are
   rejected until an SMS provider is configured.
   Local development OTPs are stored as plain six-digit values in the
   `otp_verifications.otp` column for convenience; this is not suitable for
   production. Existing `otp_hash` values remain supported, and new local
   requests clear that field while keeping the generated value in `otp`.
   The popup includes Copy OTP and OK actions; selecting OK opens the existing
   OTP entry step. Production SMS delivery requires configuring an SMS provider
   before enabling OTP requests outside local development.
   Locally generated OTPs are stored directly in `otp_verifications.otp` for
   development convenience; existing `otp_hash` values remain supported.
   OTPs expire after five minutes by default, allow at most five verification
   attempts, enforce a 60-second resend cooldown, and limit requests to three
   per 15-minute window.

   Employee data is loaded from the finalized `employees`, `employee_contact`,
   `employee_employment`, `employee_identification`, education, experience, and
   referral tables after mobile verification. Employees can mark incorrect
   values and provide corrections in the existing form. Complete form responses
   are stored as JSON in `employee_form_submissions`; only changed scalar values
   are written to their matching existing `temp_*` tables and linked by
   `submission_id`. A repeatable section is staged only when that section differs
   from the finalized records. All pre-existing temporary rows remain unlinked
   baseline rows. A final submission is marked `pending`; it does not update
   finalized HRMS employee records. Uploaded-file metadata is stored in
   `employee_documents`. New submissions are stored using
   the compact JSON structure produced by
   `backend-integration/config/submission-serializer.php`; document references
   in that JSON are employee-owned relative paths. New and replacement files
   remain under `employees_temp_documents/<category>/<employee-id>/` while HR
   reviews them. After approval, they are promoted to the existing
   `assets/requests/employees/<category>/` folders and the matching
   `employee_identification` or `educational_qualifications` path is updated.
   Rejected changes do not replace the employee's existing document. Existing
   documents are served through an OTP-authenticated endpoint; direct web access
   to the permanent and temporary document folders is denied. Previously saved
   submission JSON is not rewritten automatically.

   Open the shared HR/School Admin workspace at
   `http://localhost/final-employee/admin` (or `hr-review.php` if URL rewriting is
   unavailable). Sign in with an active account from the `careerportal.admins`
   table. The account role is read from `user_roles`; School Admin access is
   limited to the unit assigned through `admins.unit_id`. HR Head, HR Processor,
   and HR Executive accounts can review submissions across units.

   Employee records and staged submissions remain in the employee database
   configured by `DB_NAME` (the local project default is `final`). Admin accounts
   and units are read from `careerportal` using the same connection settings by
   default. Set `ADMIN_DB_NAME`, and optionally `ADMIN_DB_HOST`, `ADMIN_DB_PORT`,
   `ADMIN_DB_USER`, and `ADMIN_DB_PASS`, when the administrator database uses a
   separate connection. Account emails and roles are never hardcoded in the app.
   Existing legacy plaintext passwords are upgraded to `password_hash()` hashes
   after a successful sign-in; subsequent sign-ins use `password_verify()`.
   Inactive accounts and the `candidate` role cannot sign in. Failed sign-ins
   are rate-limited, and successful login/logout rotates the session ID.

   The responsive dashboard searches employee name, email, employee ID, and
   mobile, and filters by status and unit. Counts and table rows use the actual
   `employee_form_submissions` data. A submission opens a detail page showing the
   employee profile, original/requested correction values, status, and uploaded
   documents. Approving a submission applies its staged changes in the existing
   transaction and records the action in the audit log. School Admin unit
   restrictions are checked by the queue, review, and document endpoints.
   The Admin dashboard lists pending and approved submissions only. Any older
   rejected-status rows remain unchanged in the database and are not listed;
   the Admin module no longer offers a rejection or return action.

   Administrator edits to fields included in the open submission update that
   submission's staged value so the employee record changes only when approved.
   Other direct edits apply immediately to finalized employee records. Both are
   recorded with the changed value, actor, role, and timestamp in
   `admin_employee_change_history`. Before using this feature, apply
   `backend-integration/config/migrations/005-admin-employee-change-history.sql`
   to the employee database once. No unchanged value is written to history.

   To enable append-only submission staging on an existing database, apply
   `backend-integration/config/migrations/001-add-submission-version-linkage.sql`
   once, then apply
   `backend-integration/config/migrations/002-allow-sparse-submission-staging.sql`
   once. The first migration adds `submission_id` linkage to the existing
   temporary tables and changes the `temp_employees` row key to support multiple
   versions without replacing baseline records. The second allows unchanged
   scalar columns to remain `NULL`; submission staging inserts only changed
   scalar values plus the employee/submission linkage and audit metadata.
   Apply
   `backend-integration/config/migrations/003-store-family-working-unit-as-text.sql`
   once to store the exact family-member Unit text from `employee_employment`.
   Apply
   `backend-integration/config/migrations/004-store-family-studying-school-as-text.sql`
   once to store the selected family-studying school name in the existing
   referral columns, which previously accepted numeric IDs only.
   Repeatable sections remain staged as a complete section snapshot when that
   section itself changed, because approval replaces that section as a unit.
   New employee document changes use `employees_temp_documents/` until approval
   and are then stored in the existing `assets/requests/employees/` folders.
   Earlier `uploads/documents/` files and employee-ID-only folders remain
   readable for compatibility with older submissions.
   The Admin approval endpoint is protected by the shared Admin/HR session.
   Use `GET /backend-integration/api/review-submission.php?status=pending&page=1`
   with the session CSRF token to list pending submissions, then `POST` JSON to
   that endpoint to approve the complete submission. Corrections can be edited
   from the employee detail page before approval. For example:

   ```json
   {
     "submission_id": 123,
     "decision": "approve"
   }
   ```

   Approval applies all staged scalar fields and changed repeatable sections,
   then records the administrator account and approval timestamp. School Admin
   requests are checked against the assigned unit on every read and write.

### Test the document approval workflow locally

1. Start Apache and MySQL, configure the local `.env`, and use a registered
   employee mobile number. Complete the local OTP flow. Existing Aadhaar, PAN,
   photo, signature, and qualification certificates should show their filenames
   and a **View Existing** link; missing documents should offer an upload field.
2. Replace one existing document and upload one missing document. Submit the
   form and confirm their files are in
   `employees_temp_documents/<category>/<employee-id>/` and their
   `employee_documents.file_path` values are relative paths under that folder.
3. Sign in to the HR review page using the configured local HR account. Open
   the employee submission and approve it from the bottom of the profile page.
   Confirm the new file is moved to
   `assets/requests/employees/<category>/`, the permanent database path is
   relative, and the original filename/path was not overwritten.
4. Directly opening a file URL under either document folder should be denied;
   viewing existing employee files should work only through the authenticated
   endpoint.

Apache must allow `.htaccess` overrides for the project directories so the
document-folder access rules take effect.

   In-progress form values are saved as browser-local JSON scoped to the
   verified mobile number and restored after a refresh. Selected file contents
   are kept in browser IndexedDB. Neither form drafts nor files are sent to the
   server until final submission; the files are then uploaded and their IDs are
   included with the submitted form. Browser-local drafts and files are cleared
   after successful submission.

4. **Create Document Directories**:
   ```bash
   mkdir -p /xampp/htdocs/HRMS-form/uploads/documents
   mkdir -p /xampp/htdocs/HRMS-form/employees_temp_documents
   chmod -R 755 /xampp/htdocs/HRMS-form/uploads
   # On Linux: chown -R www-data:www-data /xampp/htdocs/HRMS-form/uploads
   ```

5. **Configure Web Server**:
   - Ensure `index.php` is the directory index
   - Enable URL rewriting if using clean URLs
   - Set document root to the project directory

6. **Test Installation**:
   Open `http://localhost/HRMS-form/` in your browser

## Configuration

### Environment Variables

Create a `.env` file in the project root (not committed to git):

```env
DB_HOST=localhost
DB_NAME=hrms
DB_USER=your_username
DB_PASS=your_password
APP_BASE_PATH=/HRMS-form
SESSION_LIFETIME=3600
OTP_EXPIRY_MINUTES=5
MAX_FILE_SIZE=5242880
ALLOWED_FILE_TYPES=application/pdf,image/jpeg,image/png
```

### Customization

#### Colors & Theme
Edit `assets/css/variables.css` to customize:
- Primary colors (`--color-primary`, `--color-primary-light`, etc.)
- Spacing scale (`--spacing-1` through `--spacing-16`)
- Typography (`--font-family`, `--font-size-*`)
- Border radius (`--radius-*`)
- Shadows (`--shadow-*`)

#### Form Fields
To add/modify fields:
1. Update the component file in `components/`
2. Add validation rules in `assets/js/validation.js`
3. Update state structure in `assets/js/form-state.js`
4. Add conditional rules in `assets/js/conditional-fields.js` if needed
5. Update review rendering in `assets/js/review.js`
6. Add server-side validation in `backend-integration/api/submit-form.php`

#### Adding Steps
1. Create new component in `components/step-new-step.php`
2. Add step key to `config.stepKeys` in `index.php`
3. Add step title to `config.steps` in `index.php`
4. Update wizard navigation logic if needed

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/csrf-token.php` | GET | Get fresh CSRF token |
| `/api/verify-mobile.php` | POST | Initiate mobile verification |
| `/api/verify-otp.php` | POST | Verify OTP |
| `/api/get-employee.php` | GET | Get employee data |
| `/api/upload-document.php` | POST | Upload staged files during final submission |
| `/api/save-draft.php` | POST | Legacy server-side draft endpoint (not used by the browser wizard) |
| `/api/get-draft.php` | GET | Legacy server-side draft endpoint (not used by the browser wizard) |
| `/api/submit-form.php` | POST | Submit final form |
| `/api/get-schools.php` | GET | Get schools list |
| `/api/get-units.php` | GET | Get units list |

## Security Features

- **CSRF Protection**: All state-changing requests require valid CSRF token
- **Server-Side Validation**: All data validated on backend before saving
- **File Upload Security**:
  - MIME type verification using `finfo`
  - File size limits enforced
  - Secure filename generation
  - Files stored outside web root (configured)
- **OTP Security**:
  - Generated server-side with crypto-secure random
  - Local OTP values are stored in plain text for development convenience and
    must not be used in production; legacy `otp_hash` values remain supported
  - Expires after five minutes by default and is marked used after successful
    verification
  - Maximum 5 verification attempts
  - 60-second resend cooldown and maximum 3 requests per 15-minute window
  - Local-only OTP popup; no SMS provider is currently configured
- **Session Management**: HttpOnly/SameSite cookies and session ID regeneration
  after OTP verification
- **SQL Injection Prevention**: Prepared statements throughout
- **XSS Prevention**: Output escaping, CSP-ready

## Accessibility

- Semantic HTML5 structure
- ARIA labels and roles
- Keyboard navigation support
- Focus management
- Screen reader announcements
- Sufficient color contrast
- Reduced motion support
- High contrast mode support

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile Safari (iOS 14+)
- Chrome for Android

## Testing Checklist

### Desktop (1920px, 1440px, 1366px, 1024px)
- [ ] All steps render correctly
- [ ] Two-column layouts work
- [ ] Horizontal stepper displays properly
- [ ] Modals center correctly
- [ ] File uploads work
- [ ] Conditional fields animate smoothly

### Tablet (768px)
- [ ] Layout adjusts to single/two columns
- [ ] Stepper remains usable
- [ ] Touch targets are adequate
- [ ] No horizontal scrolling

### Mobile (375px, 390px, 430px)
- [ ] Single column layout
- [ ] Vertical stepper works
- [ ] Full-width buttons
- [ ] OTP inputs are usable
- [ ] File upload zones work
- [ ] Modals fit screen
- [ ] No horizontal scrolling

### Functional Testing
- [ ] Mobile verification flow
- [ ] OTP verification (valid/invalid/expired)
- [ ] Draft save/load
- [ ] Conditional field visibility
- [ ] Education add/remove
- [ ] Work experience add/remove
- [ ] Family member add/remove
- [ ] Document upload/replace/remove
- [ ] Verification Correct/Wrong
- [ ] Form validation (client & server)
- [ ] Review screen accuracy
- [ ] Final submission
- [ ] Success screen display

## Deployment

### Production Checklist
- [ ] Set `display_errors = Off` in php.ini
- [ ] Configure HTTPS
- [ ] Set secure session cookies
- [ ] Configure proper file upload permissions
- [ ] Set up database backups
- [ ] Configure error logging
- [ ] Set up monitoring
- [ ] Run security audit

### Docker (Optional)
```dockerfile
FROM php:8.2-apache
RUN docker-php-ext-install pdo_mysql
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html/uploads
```

## Troubleshooting

### Common Issues

**Local OTP not visible**: Use `http://localhost/...` (or `http://127.0.0.1/...`)
on the development machine. The OTP popup is intentionally unavailable to
requests from other hosts; external OTP delivery requires a configured SMS
provider.

**File upload fails**: Check `upload_max_filesize` and `post_max_size` in php.ini

**CSRF token mismatch**: Ensure session is started before any output

**Database connection failed**: Verify `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`,
and `DB_PASS` in the project-root `.env` file.

**Styles not loading**: Check file paths and web server configuration

### Debug Mode
Enable debug logging by setting in `database.php`:
```php
'options' => [
    // ...
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
],
```

## Contributing

1. Follow existing code style
- Use CSS custom properties for styling
- Write modular, reusable JavaScript
- Add validation for new fields
- Update documentation

## License

Proprietary - Madras Seva Sadan HRMS

## Support

For technical support, contact the HRMS development team.