-- Upgrade an existing OrderDesk database to the owner/customer role model.
-- Fresh installs should import database/schema.sql instead of this file.

USE product_ordering;

ALTER TABLE users
  ADD COLUMN role ENUM('owner', 'customer') NOT NULL DEFAULT 'customer' AFTER full_name,
  ADD INDEX idx_users_role (role);

ALTER TABLE orders
  ADD COLUMN user_id INT UNSIGNED NULL AFTER id,
  ADD INDEX idx_orders_user (user_id),
  ADD CONSTRAINT fk_orders_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- Promote the original staff account to owner. Change the username if your
-- existing administrator account uses a different name.
UPDATE users SET role = 'owner' WHERE username = 'admin';

-- Orders created before the upgrade stay visible to the owner but are not
-- attached to a customer account (user_id is NULL).
