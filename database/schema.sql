-- AgriMarket – MySQL 8 / MariaDB 10.4+ schema
-- Import:  mysql -u root -p < database/schema.sql     then run:  php database/seed.php

CREATE DATABASE IF NOT EXISTS agrimarket CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agrimarket;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS order_items, orders, products, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role           ENUM('buyer','farmer','admin') NOT NULL DEFAULT 'buyer',
    first_name     VARCHAR(60)  NOT NULL,
    last_name      VARCHAR(60)  NOT NULL,
    email          VARCHAR(160) NOT NULL UNIQUE,
    phone          VARCHAR(15)  NOT NULL,                 -- stored as 2547XXXXXXXX
    password_hash  VARCHAR(255) NOT NULL,
    farm_name      VARCHAR(120) NULL,                     -- farmers only
    county         VARCHAR(80)  NULL,
    specialty      VARCHAR(160) NULL,
    avatar         VARCHAR(160) NULL,                     -- path under assets/images/
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_role (role)
) ENGINE=InnoDB;

CREATE TABLE categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80) NOT NULL,
    slug        VARCHAR(80) NOT NULL UNIQUE,
    icon        VARCHAR(40) NOT NULL DEFAULT 'fa-leaf',   -- Font Awesome icon name
    color       VARCHAR(20) NOT NULL DEFAULT 'green',     -- Tailwind colour family
    sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE products (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farmer_id      INT UNSIGNED NOT NULL,
    category_id    INT UNSIGNED NOT NULL,
    name           VARCHAR(120) NOT NULL,
    description    TEXT NULL,
    price          DECIMAL(10,2) NOT NULL,
    unit           VARCHAR(40)  NOT NULL DEFAULT 'kg',    -- "kg", "2kg bag", "bunch" …
    stock          INT UNSIGNED NOT NULL DEFAULT 0,
    county         VARCHAR(80)  NOT NULL,
    image          VARCHAR(200) NULL,                     -- path under assets/images/ (products/… or uploads/…)
    rating         DECIMAL(2,1) NOT NULL DEFAULT 0,
    reviews_count  INT UNSIGNED NOT NULL DEFAULT 0,
    featured       TINYINT(1)   NOT NULL DEFAULT 0,
    status         ENUM('active','hidden') NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_farmer   FOREIGN KEY (farmer_id)   REFERENCES users(id)      ON DELETE CASCADE,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id),
    INDEX idx_products_filter (status, category_id, county, price),
    FULLTEXT KEY ft_products (name, description)
) ENGINE=InnoDB;

CREATE TABLE orders (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id            INT UNSIGNED NOT NULL,
    subtotal           DECIMAL(12,2) NOT NULL,
    delivery_fee       DECIMAL(10,2) NOT NULL DEFAULT 0,
    total              DECIMAL(12,2) NOT NULL,
    status             ENUM('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
    payment_status     ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
    mpesa_phone        VARCHAR(15) NOT NULL,
    mpesa_checkout_id  VARCHAR(80) NULL,
    mpesa_merchant_id  VARCHAR(80) NULL,
    mpesa_receipt      VARCHAR(30) NULL,
    paid_at            DATETIME NULL,
    delivery_name      VARCHAR(120) NOT NULL,
    address            VARCHAR(255) NOT NULL,
    county             VARCHAR(80)  NOT NULL,
    notes              VARCHAR(500) NULL,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_orders_checkout (mpesa_checkout_id),
    INDEX idx_orders_status (status, payment_status)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id    INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NULL,
    farmer_id   INT UNSIGNED NOT NULL,                    -- lets farmers see only their own lines
    name        VARCHAR(120) NOT NULL,                    -- snapshot at purchase time
    price       DECIMAL(10,2) NOT NULL,
    qty         INT UNSIGNED NOT NULL,
    CONSTRAINT fk_items_order   FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    CONSTRAINT fk_items_farmer  FOREIGN KEY (farmer_id)  REFERENCES users(id),
    INDEX idx_items_farmer (farmer_id)
) ENGINE=InnoDB;
