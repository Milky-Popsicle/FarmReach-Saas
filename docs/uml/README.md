# FarmReach UML diagrams

These diagrams describe behavior and structure found in the repository. They
are based on source code and migration files. Deployment-specific observations
are identified explicitly and should be rechecked if the environment changes.

## Diagrams

| File | What it represents |
| --- | --- |
| `use-case-diagram.puml` / `.mmd` | Admin, Farmer, and Buyer interactions that have corresponding routes and controller behavior. |
| `class-diagram.puml` / `.mmd` | The ten application Eloquent models, selected persisted attributes and methods, and their declared relationships. |
| `buyer-order-sequence.puml` / `.mmd` | Public marketplace browsing, session-cart validation, authenticated checkout, per-farmer order creation, and pending payment-record creation. |
| `system-architecture.puml` / `.mmd` | The browser, Laravel web and JSON layers, Eloquent/database access, session cart, file storage, password-reset mail, and notification broadcasting. |

The PlantUML use-case file is the most UML-faithful representation of use
cases. Mermaid does not provide a dedicated use-case diagram grammar, so its
equivalent uses labeled actor nodes, oval use-case nodes, and a system
subgraph. The Mermaid class diagram and sequence diagram use Mermaid's native
diagram grammars.

## Source evidence

### Routes, controllers, and views

- `routes/web.php` — public marketplace, cart, checkout, authentication,
  buyer-order, farmer, and admin route definitions and middleware.
- `routes/api.php` — JSON API routes and the Sanctum-protected `/api/user`
  endpoint.
- `app/Http/Middleware/RoleMiddleware.php` — role authorization.
- `app/Http/Controllers/MarketplaceController.php` and
  `resources/views/marketplace/` — public listing, product detail, and
  price comparison.
- `app/Http/Controllers/CartController.php` and
  `resources/views/cart/` — session cart, checkout validation, transactional
  order creation, stock updates, and notification dispatch.
- `app/Http/Controllers/BuyerOrderController.php` and
  `resources/views/buyer/orders.blade.php` — buyer order listing, cancellation,
  reorder, and deletion.
- `app/Http/Controllers/FarmerProductController.php`,
  `app/Http/Controllers/FarmerOrderController.php`, and
  `app/Http/Controllers/FarmerSalesHistoryController.php` — farmer listings,
  order-status transitions, and sales history.
- `app/Http/Controllers/AdminController.php` and `resources/views/admin/` —
  dashboard data, announcements, and recipe administration.
- `app/Http/Controllers/AuthController.php`,
  `app/Http/Controllers/NotificationController.php`, and
  `resources/views/auth/` — sign-in/registration, profile/password recovery,
  and in-app notifications.
- `app/Http/Controllers/RecipeController.php` and
  `app/Http/Controllers/AnnouncementController.php` — public recipe and
  announcement views, including recipe-to-product matching and batch cart
  support.

### Models, migrations, and application configuration

- `app/Models/User.php`, `Product.php`, `Category.php`, `Order.php`,
  `OrderItem.php`, `Payment.php`, `Recipe.php`, `RecipeIngredient.php`,
  `Announcement.php`, and `ActivityLog.php` — model attributes, casts,
  methods, and Eloquent relationships.
- `database/migrations/0001_01_01_000000_create_users_table.php` and
  `database/migrations/2026_09_04_000001_create_categories_table.php` through
  `2026_09_04_000008_create_activity_logs_table.php` — primary entity schemas.
- `database/migrations/2026_09_25_131009_add_picked_up_to_orders_status_enum.php`,
  `database/migrations/2026_10_21_074001_preserve_order_history_on_user_and_product_deletion.php`,
  and `database/migrations/2026_10_21_080216_convert_tables_to_innodb_and_restore_foreign_keys.php`
  — later order-status and referential-integrity changes.
- `database/migrations/2026_09_04_142516_create_personal_access_tokens_table.php`
  and `database/migrations/2026_10_21_180111_create_notifications_table.php`
  — API-token and database-notification storage.
- `composer.json`, `package.json`, `vite.config.js`, and
  `config/database.php` — declared framework, frontend build tools, and
  database connection configuration.
- `app/Events/NotificationCreated.php` and
  `app/Listeners/BroadcastDatabaseNotification.php` — notification broadcast
  event and listener implementations.
- `bootstrap/app.php`, `bootstrap/providers.php`, `routes/channels.php`, and
  `config/broadcasting.php` — event registration/discovery setup, private
  channel authorization, and broadcaster configuration.

## Render and preview

### PlantUML

- Open a `.puml` file in VS Code with a PlantUML preview extension and choose
  its preview command.
- Alternatively, install PlantUML and run `plantuml path/to/file.puml`.

### Mermaid

- Open an `.mmd` file in a Mermaid-compatible editor or Mermaid preview
  extension.
- For a Markdown preview in VS Code, put the diagram source inside a fenced
  block tagged `mermaid`.
- Alternatively, use Mermaid CLI (`mmdc`) if it is already installed:
  `mmdc -i path/to/file.mmd -o path/to/file.svg`.

No renderer or diagram package was added to the application.

## Accuracy notes and limitations

- Admin, Farmer, and Buyer are values of `users.role` on the `User` model;
  the repository does not define three role subclasses.
- Checkout requires authentication; marketplace browsing and cart operations
  are public. The checkout route is not restricted to the Buyer role; any
  authenticated role may place orders, subject to the own-listing restriction.
  Orders are grouped into separate records by farmer. The transaction locks
  and rechecks selected products, creates order items, decrements stock, and
  creates a pending `Payment` record.
- The payment model and database table store payment-method, reference,
  amount, and status data. No payment gateway request or charge is implemented.
  Delivery/pickup is an order choice and status; delivery tracking is not
  implemented.
- The `Order` model declares a `hasOne` payment relationship. The migration
  defines a foreign key on `payments.order_id` but does not make that column
  unique, so the database itself does not enforce one payment row per order.
- The cart is Laravel session data, not a persisted Eloquent model. Laravel's
  built-in notifications and Sanctum token records are not presented as
  application Eloquent classes in the class diagram.
- Database notifications are sent by the implemented notification classes.
  Reverb and broadcast event/listener code are also present, but no listener
  registration was found in bootstrap, and `config/broadcasting.php` defaults
  to the `null` broadcaster. The diagrams therefore show that real-time path
  as unverified/disconnected, not as an active notification service.
  Password reset calls Laravel's configured mail transport, but no specific
  email provider or production configuration was verified.
- `config/database.php` defaults to SQLite and declares MySQL, MariaDB,
  PostgreSQL, and SQL Server connections. The Railway production environment
  was verified on 2026-11-07 to use MySQL through its `DB_CONNECTION` override.
  This is a deployment-specific observation, not the source-code default;
  recheck it if Railway environment settings change. The deployed schema should
  still be verified before making assumptions about its current structure.
- The frontend stack is Blade, JavaScript, Tailwind CSS 4, and Vite 8 based on
  the checked-in templates and package configuration. The diagrams do not
  imply a deployment topology or evaluation result.
