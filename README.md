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
   `ADMIN_DB_USER`, `ADMIN_DB_PASS`, `OTP_EXPIRY_MINUTES`, `OTP_DELIVERY_MODE`,
   `OMNI_PORTAL_API_KEY`, `OMNI_WHATSAPP_API_URL`,
   `OMNI_WHATSAPP_OTP_TEMPLATE`, and `OMNI_WHATSAPP_OTP_LANGUAGE`. Environment
   variables supplied by Apache/the hosting platform take precedence over values
   from `.env`.

   WhatsApp OTP delivery uses Tata Tele Business's WhatsApp Cloud endpoint. To
   enable it, set `OTP_DELIVERY_MODE=whatsapp` and configure the provider-issued
   `OMNI_PORTAL_API_KEY` in the server environment or ignored project-root
   `.env`. The default endpoint is
   `https://wb.omni.tatatelebusiness.com/whatsapp-cloud/messages`; the default
   approved template is `otp_service` in English. Override the endpoint/template
   settings only with values supplied for your provider account. PHP's cURL
   extension must be enabled. Never put provider credentials in browser
   JavaScript, source control, or logs.

   For a local XAMPP `.env`, add the following settings using the rotated key:

   ```dotenv
   OTP_DELIVERY_MODE=whatsapp
   OMNI_PORTAL_API_KEY=REPLACE_WITH_ROTATED_PROVIDER_KEY
   OMNI_WHATSAPP_OTP_TEMPLATE=otp_service
   OMNI_WHATSAPP_OTP_LANGUAGE=en
   ```

   When `OTP_DELIVERY_MODE` is omitted, loopback requests use local development
   mode and non-local requests are disabled. Local mode requires localhost and
   shows a development OTP popup; WhatsApp mode sends the code to the registered
   Indian mobile number and does not return the OTP to the browser. Both modes
   store only a password hash in `otp_verifications.otp_hash`. OTPs expire after
   five minutes by default, allow at most five verification attempts, enforce a
   60-second resend cooldown, and limit requests to three per 15-minute window.

   Employee data is loaded from the finalized `employees`, `employee_contact`,
   `employee_employment`, `employee_identification`, education, experience, and
   referral tables after mobile verification. Employees can mark incorrect
   values and provide corrections in the existing form. Complete form responses
   are stored as JSON in `employee_form_submissions`; only changed scalar values
   are written to their matching existing `temp_*` tables and linked by
   `submission_id`. A repeatable section is staged only when that section differs
   from the finalized records. All pre-existing temporary rows remain unlinked
   baseline rows. A final submission is marked `pending`; it does not update
   finalized HRMS employee records. Uploaded files are stored on disk and their
   filenames are written to the relevant existing `temp_*` document columns;
   no separate document registry table is used. New submissions are stored using
   the compact JSON structure produced by
   `backend-integration/config/submission-serializer.php`; document references
   in that JSON are validated, session-bound references resolved to safe paths.
   New and replacement files
   remain under `employees_temp_documents/<category>/<original-filename>` while
   HR reviews them. The category folder is shared, so duplicate filenames in
   that category are rejected rather than overwritten. Uploads accept any file
   type except executable and active web-script formats, up to 5 MB; other types
   are downloaded as attachments rather than displayed inline. After approval,
   files are promoted to the existing
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
   `employees_temp_documents/<category>/<original-filename>` and the matching
   `temp_*` document columns contain the original filename only.
3. Sign in to the HR review page using the configured local HR account. Open
   the employee submission and approve it from the bottom of the profile page.
   Confirm the new file is moved to
   `assets/requests/employees/<category>/`, and the corresponding permanent
   employee document column contains the permanent relative path.
4. Directly opening a file URL under either document folder should be denied;
   viewing existing employee files should work only through the authenticated
   endpoint.

Apache must allow `.htaccess` overrides for the project directories so the
document-folder access rules take effect.

   In-progress form values are saved as browser-local JSON scoped to the
   verified mobile number and restored after a refresh. Selected file contents
   are kept in browser IndexedDB. The files are uploaded to temporary storage
   when the employee submits; the submission includes signed references to
   those uploads. Browser-local drafts and files are cleared after successful
   submission.

4. **Create Document Directories**:
   ```bash
   mkdir -p /xampp/htdocs/HRMS-form/employees_temp_documents
   mkdir -p /xampp/htdocs/HRMS-form/assets/requests/employees
   chmod -R 755 /xampp/htdocs/HRMS-form/employees_temp_documents
   # On Linux: chown -R www-data:www-data /xampp/htdocs/HRMS-form/employees_temp_documents
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
```

Uploads accept any non-executable file format up to 5 MB. Files are stored using
their sanitized original filename directly inside the matching
`employees_temp_documents/<category>/` folder; duplicate names within a category
must be renamed before uploading. Executable and active web-script formats are
blocked for safety.

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
  - OTPs are stored as password hashes and never returned to the browser in
    WhatsApp mode
  - Expires after five minutes by default and is marked used after successful
    verification
  - Maximum 5 verification attempts
  - 60-second resend cooldown and maximum 3 requests per 15-minute window
  - Supports local development preview or server-side WhatsApp delivery
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

**OTP not delivered**: Check that `OTP_DELIVERY_MODE=whatsapp` and the
provider-issued `OMNI_PORTAL_API_KEY` are configured in the server environment,
PHP cURL is enabled, and the provider account has approved the configured
template. Without an explicit delivery mode, only localhost requests use the
local OTP popup; other hosts have delivery disabled.

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