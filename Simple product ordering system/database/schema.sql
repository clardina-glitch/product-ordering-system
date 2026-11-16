
CREATE DATABASE IF NOT EXISTS product_ordering
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE product_ordering;

DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(100) NOT NULL,
  role          ENUM('owner', 'customer') NOT NULL DEFAULT 'customer',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_users_role (role)
) ENGINE = InnoDB;

CREATE TABLE products (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(120)  NOT NULL,
  category     VARCHAR(60)   NOT NULL DEFAULT 'General',
  description  VARCHAR(255)  NULL,
  unit_price   DECIMAL(10,2) NOT NULL,
  stock        INT UNSIGNED  NOT NULL DEFAULT 0,
  is_available TINYINT(1)    NOT NULL DEFAULT 1,
  created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_products_name (name),
  INDEX idx_products_category (category)
) ENGINE = InnoDB;

CREATE TABLE orders (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED  NULL,
  customer_name VARCHAR(100)  NOT NULL,
  customer_email VARCHAR(120) NOT NULL,
  customer_phone VARCHAR(30)  NOT NULL,
  customer_address VARCHAR(255) NOT NULL,
  product_id    INT UNSIGNED  NULL,
  product_name  VARCHAR(120)  NOT NULL,
  quantity      INT UNSIGNED  NOT NULL,
  unit_price    DECIMAL(10,2) NOT NULL,
  total_amount  DECIMAL(10,2) NOT NULL,
  payment_method ENUM('Cash', 'GCash') NOT NULL DEFAULT 'Cash',
  status        ENUM('Pending', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_product
    FOREIGN KEY (product_id) REFERENCES products (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_orders_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_orders_user (user_id),
  INDEX idx_orders_customer (customer_name),
  INDEX idx_orders_email (customer_email),
  INDEX idx_orders_phone (customer_phone),
  INDEX idx_orders_status (status),
  INDEX idx_orders_created (created_at)
) ENGINE = InnoDB;

-- Demo accounts. Change these passwords before using the app for real.
-- Owner:    owner / owner12345
-- Customer: customer / customer123
INSERT INTO users (username, password_hash, full_name, role) VALUES
('owner',    '$2y$10$ZwhxAfInnZQfdzJ.e.MCQOjH.xESrs1M5//4d1BwR7nyd/t5y6I42', 'Store Owner',  'owner'),
('customer', '$2y$10$aoTvL/pXPgGJ.L/Tu26K4uMY8SSl2IkP/v5L0Wc/oPoXKoAID5N1e', 'Maria Santos', 'customer');

INSERT INTO products (name, category, description, unit_price, stock, is_available) VALUES
('Wireless Mouse',            'Electronics',  'Silent-click 2.4 GHz wireless mouse with USB receiver.',        799.00, 25, 1),
('Mechanical Keyboard',       'Electronics',  '87-key hot-swappable mechanical keyboard with RGB lighting.',  2499.00, 12, 1),
('USB-C Charger 30W',         'Electronics',  'Fast-charging compact adapter with PD 3.0 support.',           1199.00, 40, 1),
('Noise-Cancelling Headphones','Audio',        'Over-ear headphones with active noise cancellation.',          4599.00,  8, 1),
('Bluetooth Speaker',         'Audio',        'Portable IPX7 waterproof speaker with 12-hour battery life.',  1899.00, 15, 1),
('Laptop Stand',              'Accessories',  'Adjustable aluminium stand for 11" to 17" laptops.',            899.00, 30, 1),
('Ergonomic Desk Mat',        'Accessories',  'Extended non-slip desk mat with stitched edges.',               649.00, 22, 1),
('1080p Webcam',              'Electronics',  'Full HD webcam with built-in microphone and privacy cover.',   1699.00,  6, 1),
('Smart Watch',               'Wearables',    'Fitness tracking, heart-rate monitor, and AMOLED display.',    5999.00,  5, 1),
('Portable SSD 1TB',          'Storage',      'USB-C external solid state drive with 550 MB/s transfer.',     3899.00,  9, 1);

-- user_id 2 is the demo customer (Maria Santos); NULL means a walk-in order
-- recorded by the owner.
INSERT INTO orders (user_id, customer_name, customer_email, customer_phone, customer_address,
                    product_id, product_name, quantity, unit_price, total_amount, payment_method, status, created_at) VALUES
(2,    'Maria Santos',   'maria.santos@example.com',   '+63 917 555 0101', '12 Mabini Street, Barangay Poblacion, Makati City',          4, 'Noise-Cancelling Headphones', 1, 4599.00, 4599.00, 'GCash', 'Completed', NOW() - INTERVAL 7 DAY),
(NULL, 'James Reyes',    'james.reyes@example.com',    '+63 918 555 0102', '45 Rizal Avenue, Barangay San Roque, Quezon City',           1, 'Wireless Mouse',             2,  799.00, 1598.00, 'Cash',  'Pending',   NOW() - INTERVAL 6 DAY),
(NULL, 'Aisha Khan',     'aisha.khan@example.com',     '+63 919 555 0103', '8 Bonifacio Drive, Barangay Bagumbayan, Taguig City',        2, 'Mechanical Keyboard',        1, 2499.00, 2499.00, 'GCash', 'Pending',   NOW() - INTERVAL 5 DAY),
(NULL, 'Carlos Mendoza', 'carlos.mendoza@example.com', '+63 920 555 0104', '23 Del Pilar Street, Barangay Malanday, Valenzuela City',    3, 'USB-C Charger 30W',          3, 1199.00, 3597.00, 'Cash',  'Completed', NOW() - INTERVAL 4 DAY),
(NULL, 'Lena Cruz',      'lena.cruz@example.com',      '+63 921 555 0105', '77 Luna Street, Barangay San Jose, Pasig City',              6, 'Laptop Stand',               2,  899.00, 1798.00, 'GCash', 'Cancelled', NOW() - INTERVAL 3 DAY),
(2,    'Maria Santos',   'maria.santos@example.com',   '+63 917 555 0101', '12 Mabini Street, Barangay Poblacion, Makati City',          7, 'Ergonomic Desk Mat',         1,  649.00,  649.00, 'GCash', 'Completed', NOW() - INTERVAL 2 DAY),
(NULL, 'Daniel Park',    'daniel.park@example.com',    '+63 922 555 0107', '5 Ayala Avenue, Barangay Bel-Air, Makati City',              9, 'Smart Watch',                1, 5999.00, 5999.00, 'Cash',  'Pending',   NOW() - INTERVAL 1 DAY),
(NULL, 'James Reyes',    'james.reyes@example.com',    '+63 918 555 0102', '45 Rizal Avenue, Barangay San Roque, Quezon City',           5, 'Bluetooth Speaker',          1, 1899.00, 1899.00, 'Cash',  'Completed', NOW() - INTERVAL 6 HOUR);
