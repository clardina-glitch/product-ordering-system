# OrderDesk — Simple Product Ordering System

A small, self-contained product ordering system with a PHP backend and MySQL database.
The app has two separate areas:

- **Owner workspace** — dashboard, product catalogue and every customer order
- **Customer portal** — browse products, place an order and track your own orders

No frameworks, no CDN, no build step — plain PHP, CSS and JavaScript.

## How it works

1. A customer signs in (or registers a customer account).
2. The customer browses the available products, picks one, sets a quantity and
   submits the order. Stock is checked again in a database transaction.
3. The order is linked to that customer's account and appears in **My Orders**.
4. The owner signs in to the owner workspace to review the dashboard, manage the
   product catalogue, and search, edit, complete, cancel or delete customer orders.

Each role only sees its own navigation and can only open pages that belong to it.
Trying to open the other area redirects the account back to its own home page.

## Features

### Customer portal

- **Place Order page**
  - Displays available products with category, description, price and stock
  - Product search by name, category or description
  - Customer details pre-filled from the last order (name, email, phone, address)
  - Payment method selection: **Cash** or **GCash**
  - Quantity selector with live unit price, payment method and total calculation
  - Customer, contact and quantity validation
  - Server-side stock check in a database transaction before saving
- **My Orders page**
  - Shows only the signed-in customer's orders
  - Order ID, product, quantity, unit price, total, payment method, status and date
  - Search by order ID or product and filter by status
  - Totals for orders placed and amount spent

### Owner workspace

- **Dashboard** with order, revenue, product and customer statistics, recent
  orders, top products and low-stock alerts
- **Products page**
  - Add, edit, delete, hide/unhide products
  - Search the catalogue
  - Stock and price validation
- **Customer Orders page**
  - Shows every order with customer name, email, phone, address, product, quantity,
    unit price, total amount, payment method, status and date
  - Search by customer, email, phone, product or order ID (supports `ORD-0001` and `1`)
  - Filter by status
  - Edit and delete orders (deleting restores reserved stock)

### Shared

- Login and customer registration with hashed passwords, sessions and CSRF protection
- Role-based access control for the owner and customer areas
- Responsive interface — desktop sidebar becomes an off-canvas menu on mobile,
  tables become stacked cards
- Prepared SQL statements and escaped output throughout

## Demo accounts

| Role     | Username   | Password      |
|----------|------------|---------------|
| Owner    | `owner`    | `owner12345`  |
| Customer | `customer` | `customer123` |

The demo customer is Maria Santos and two seeded orders belong to her. Other
seeded orders are walk-in orders recorded by the owner. **Change the demo
passwords before using the app for real.**

### Upgrading an existing database

`database/migrate_roles.sql` does not create the demo accounts above. Instead it
promotes the existing `admin` account to owner and makes every other existing
account a customer. In the database bundled with this project, the original owner
login is `admin` / `admin123`.

To promote a different account:

```sql
UPDATE users SET role = 'owner' WHERE username = 'your_username';
```

Orders that existed before the upgrade keep `user_id = NULL`, so they remain
visible to the owner but do not appear in any customer's **My Orders** page.

## Tech stack

| Layer     | Technology                       |
|-----------|----------------------------------|
| Frontend  | HTML5, custom CSS, vanilla JS     |
| Backend   | PHP 8 (PDO, sessions)             |
| Database  | MySQL / MariaDB — `product_ordering` |

## Project structure

```
.
├── index.php                  # Sends each signed-in account to its own area
├── login.php                  # Sign in + customer registration
├── logout.php                 # Sign out (POST + CSRF)
├── owner/
│   ├── dashboard.php          # Owner dashboard
│   ├── products.php           # Product catalogue + add/edit/delete
│   ├── orders.php             # Every customer order + search/filter
│   ├── edit_order.php         # Edit an existing order
│   └── delete_order.php       # Delete an order
├── customer/
│   ├── order.php              # Place an order
│   └── my_orders.php          # The signed-in customer's own orders
├── config/
│   └── db.php                 # Database credentials and currency
├── database/
│   ├── schema.sql             # Fresh database, tables, demo accounts and data
│   └── migrate_roles.sql      # Upgrade an existing database to owner/customer roles
├── includes/
│   ├── bootstrap.php          # Session + shared includes
│   ├── functions.php          # Helpers, roles, URLs, CSRF, validation, formatting
│   ├── header.php             # Role-aware layout / sidebar / topbar
│   └── footer.php
└── assets/
    ├── css/style.css
    ├── js/app.js
    └── favicon.svg
```

## Setup (XAMPP on Windows)

1. **Start MySQL** from the XAMPP Control Panel. Apache is optional.
2. **Import the database.**
   - **Fresh install:** open <http://localhost/phpmyadmin>, click **Import**, choose
     `database/schema.sql`, then **Go**; or run from this folder:
     ```powershell
     Get-Content -Raw .\database\schema.sql | C:\xampp\mysql\bin\mysql.exe -u root
     ```
   - **Existing install from before the role split:** import
     `database/migrate_roles.sql` instead. It adds the `role` and `user_id` columns
     and promotes the existing `admin` account to owner.
3. **Run the app.** Easiest option is PHP's built-in server from this folder:
   ```powershell
   C:\xampp\php\php.exe -S localhost:8000
   ```
   Then open <http://localhost:8000>.

   For later launches, double-click `start-server.bat` in this folder. Keep its
   terminal window open while using the app; closing it stops the server.

   Alternative: copy the project folder into `C:\xampp\htdocs\` (for example
   `C:\xampp\htdocs\product-ordering`), start Apache, and open
   <http://localhost/product-ordering>.
4. **Sign in.** Use the demo owner or customer account above, or select
   **Create account** to register a new customer. Self-registration always
   creates a customer account; owner accounts are created directly in the
   database.

### Database configuration

Defaults match a standard XAMPP installation (`root` with no password, port `3306`).
Edit `config/db.php` if your setup differs:

```php
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'product_ordering';
const DB_USER = 'root';
const DB_PASS = '';

const CURRENCY = '₱'; // change to '$', '€', etc.
```

### Resetting demo data

Re-importing `database/schema.sql` drops and recreates the three tables, restoring
the demo accounts, 10 demo products and 8 demo orders. **This deletes any orders
and user accounts you have created.**

## Database design

**users** — `id`, `username`, `password_hash`, `full_name`, `role`
(`owner` or `customer`), `created_at`

**products** — `id`, `name`, `category`, `description`, `unit_price`, `stock`,
`is_available`, `created_at`

**orders** — `id` (Order ID), `user_id` (the customer account that placed it, or
`NULL` for a walk-in order), `customer_name` (Customer Name), `customer_email`,
`customer_phone`, `customer_address`, `product_id`, `product_name` (Product Name),
`quantity` (Quantity), `unit_price` (Unit Price), `total_amount` (Total Amount),
`payment_method` (Cash or GCash), `status`, `created_at`

The order row stores a snapshot of the product name and unit price, so historical
orders remain readable even if a product is renamed or deleted. Stock is reserved
when an order is placed and returned when an order is cancelled or deleted.
`My Orders` filters on `orders.user_id`, so a customer can only see their own orders.

## Role access rules

| Page                          | Owner | Customer |
|-------------------------------|:-----:|:--------:|
| `owner/dashboard.php`         |  Yes  |    No    |
| `owner/products.php`          |  Yes  |    No    |
| `owner/orders.php`            |  Yes  |    No    |
| `owner/edit_order.php`        |  Yes  |    No    |
| `owner/delete_order.php`      |  Yes  |    No    |
| `customer/order.php`          |  No   |   Yes    |
| `customer/my_orders.php`      |  No   |   Yes    |

Unauthorised requests are redirected to the account's own home page with an error
message. All pages also require a signed-in session.

## Validation rules

- Customer name: required, 2–100 characters, letters/spaces/`.`/`'`/`-`
- Customer email: required, valid email address, up to 120 characters
- Customer phone: required, 7–15 digits, allows spaces, `+`, `(`, `)`, `.` and `-`
- Delivery address: required, 5–255 characters
- Payment method: required, either Cash or GCash
- Full name: required, 2–100 characters
- Username: required, 3–50 characters using letters, numbers, dots, underscores or hyphens
- Password: required, at least 8 characters, with a matching confirmation
- Quantity: whole number, at least 1, not greater than available stock
- Product name: required, 2–120 characters
- Category: required, up to 60 characters
- Unit price: required, positive amount with up to 2 decimal places
- Stock: whole number, 0 or greater
- All POST requests require a valid CSRF token
- Login compares passwords with `password_verify()` and regenerates the session ID
