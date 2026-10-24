# FarmReach SaaS

FarmReach is a Laravel-based agricultural marketplace for Surigao farmers and buyers. It includes a public marketplace, recipe-to-product matching, cart and checkout, buyer order tracking, a farmer portal, and an admin portal.

## Requirements

- PHP 8.3 or newer
- Composer
- MySQL 8.x (the local WAMP MySQL 8.4 installation is supported)
- Node.js and npm (only needed for the optional Vite build)
- A terminal with the project directory as the working directory

## Local Setup

1. Install PHP dependencies:

   ```bash
   composer install
   ```

2. Create the local environment file if it does not exist:

   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

3. Create the `farmreach_core` MySQL database in WAMP, then confirm the `DB_*` values in `.env`.

4. Run the schema and demo data:

   ```bash
   php artisan migrate --seed
   ```

   The seeder creates the demo accounts below. It is safe to run again: if the demo administrator already exists, seeding is skipped instead of creating duplicates.

5. Link uploaded product images if the application uses local uploads:

   ```bash
   php artisan storage:link
   ```

6. Start the application:

   ```bash
   php artisan serve
   ```

   Open <http://127.0.0.1:8000>. A WAMP virtual host can be used instead of `artisan serve`.

The main interface uses the checked-in CSS and JavaScript in `public/`. Vite is only used by the default Laravel welcome page, so a frontend build is not required for the normal application pages.

## Mail and Password Recovery

### Local development

FarmReach uses Gmail SMTP for local password-reset emails. Configure it in `.env`:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-gmail-address
MAIL_PASSWORD=your-google-app-password
MAIL_FROM_ADDRESS=your-gmail-address
MAIL_FROM_NAME="${APP_NAME}"
MAIL_URL=null
```

Use a Google App Password, not the normal Gmail account password. Enable 2-Step Verification on the Google account, create an App Password, and remove any spaces when pasting it. Keep `MAIL_URL=null`; an empty value can break Laravel mail transport resolution.

`.env` is ignored by Git. Never commit it, and never put real credentials in `.env.example` or source code.

Password-reset emails are sent synchronously. If delivery fails, the recovery form shows an error instead of claiming the email was sent. Reset tokens expire after 60 minutes, are single-use, and new passwords are hashed.

### Production

The application does not require Postmark, a paid provider, or a newly purchased domain. Production can use any mail transport supported by Laravel, configured through environment variables or the hosting platform's secret manager.

Set production values in the hosting platform, not in Git:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-production-domain

MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=your-provider-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-provider-username
MAIL_PASSWORD=your-provider-password
MAIL_FROM_ADDRESS=no-reply@your-sender-domain
MAIL_FROM_NAME="FarmReach"
MAIL_URL=null
```

For API-based providers, use the corresponding Laravel mailer and credential variables. Examples include:

- Amazon SES: `MAIL_MAILER=ses`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`
- Postmark: `MAIL_MAILER=postmark`, `POSTMARK_API_KEY`
- Resend: `MAIL_MAILER=resend`, `RESEND_API_KEY`

These are examples, not requirements. Some API providers require an additional bridge package and mailer configuration: Postmark uses `symfony/postmark-mailer`, Resend uses `resend/resend-php`, and Amazon SES uses `aws/aws-sdk-php`. Those packages are not installed by default. Install only the one you choose, and only after approval. Choose the provider at deployment time based on budget, volume, and whether you already own a suitable domain.

Sender verification requirements depend on the provider:

- If you already own a domain, verify it and configure SPF, DKIM, and DMARC records.
- If you do not own a domain, use a provider that supports single-sender email verification or an existing domain. Do not buy a domain solely for this feature unless you choose to.
- Do not use a personal Gmail account as the permanent production sender.

After changing production mail settings, rebuild the configuration cache:

```bash
php artisan config:cache
```

Then send a real password-reset email to a test account and check the provider dashboard and recipient inbox. Keep staging and production credentials separate, and rotate any temporary Gmail App Password after the production cutover.

Confirm `.env` remains excluded from Git:

```bash
git check-ignore .env
```

## Demo Accounts

All demo accounts use the password `password`.

| Role | Email | Phone |
| --- | --- | --- |
| Admin | `admin@farmreach.ph` | `09123456789` |
| Farmer | `justin@nabunturanfarm.ph` | `09876543210` |
| Farmer | `maria@surigaogreens.ph` | `09771234567` |
| Farmer | `pedro@caragaorganic.ph` | `09669876543` |
| Buyer | `shery@buyer.ph` | `09151112233` |
| Buyer | `rynard@caragabistro.ph` | `09172223344` |
| Buyer | `ana@buyer.ph` | `09203334455` |

Change these passwords before exposing the application outside a local development environment.

## Running Tests

The test suite uses an in-memory SQLite database. It does not read or change the local MySQL database, and no separate test schema is required.

```bash
php artisan test
```

Run a single file or test:

```bash
php artisan test tests/Feature/FarmReachTest.php
php artisan test --filter=test_farmer_can_confirm_order
```

Feature tests use `LazilyRefreshDatabase` and seed the demo data explicitly for each test. Factories are available in `database/factories/` for tests that need their own records.

## Database Backup and Restore

Use `mysqldump` with the password prompt. Do not put the database password on the command line or commit a dump to the repository.

```powershell
& "C:\wamp64\bin\mysql\mysql8.4.7\bin\mysqldump.exe" -u root -p `
  --single-transaction --quick --routines --triggers --events `
  --no-tablespaces --default-character-set=utf8mb4 `
  --databases farmreach_core `
  --result-file="C:\Users\USER\Documents\FarmReach-Backups\farmreach_core.sql"
```

Back up `storage/app/public` separately if local uploads matter. To restore, use the matching `mysql.exe` client against the same schema name, after taking a fresh backup.

Never commit `.env`, database dumps, or uploaded private files to Git.

## Main Areas

- `/marketplace` - public listings and price comparison
- `/recipes` - recipes and ingredient-to-product matching
- `/cart` and `/checkout` - cart and order placement
- `/buyer/orders` - buyer and peer order tracking
- `/farmer` - farmer product and order management
- `/admin` - administrator dashboard, users, announcements, and recipes
- `/api` - JSON endpoints for products, orders, profiles, sales data, recipes, and farmer locations

## Project Structure

- `app/Http/Controllers/` - request handling and portal workflows
- `app/Models/` - Eloquent models and domain helpers
- `database/migrations/` - schema
- `database/seeders/` - demo data
- `database/factories/` - test data builders
- `resources/views/` - Blade templates
- `public/css/` and `public/js/` - checked-in frontend assets
- `routes/web.php` and `routes/api.php` - route definitions
- `tests/Feature/` - HTTP and workflow tests
