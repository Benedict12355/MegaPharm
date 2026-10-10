-- MegaPharm - Mega Life Pharmacy Management System
CREATE DATABASE IF NOT EXISTS megapharm_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE megapharm_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs, notifications, return_items, returns, sale_items, sales,
  order_items, customer_orders, delivery_items, deliveries, purchase_order_items,
  purchase_orders, stock_movements, inventory_batches, products, categories,
  suppliers, customers, system_settings, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff','customer','supplier') NOT NULL DEFAULT 'staff',
  status ENUM('active','inactive','locked') NOT NULL DEFAULT 'active',
  last_login DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL UNIQUE,
  customer_code VARCHAR(30) NOT NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  address TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_customers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE suppliers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL UNIQUE,
  supplier_code VARCHAR(30) NOT NULL UNIQUE,
  supplier_name VARCHAR(180) NOT NULL,
  contact_person VARCHAR(150) NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  address TEXT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_suppliers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_code VARCHAR(50) NOT NULL UNIQUE,
  barcode VARCHAR(100) NULL UNIQUE,
  product_name VARCHAR(180) NOT NULL,
  generic_name VARCHAR(180) NULL,
  brand VARCHAR(120) NULL,
  category_id INT UNSIGNED NULL,
  dosage_form VARCHAR(80) NULL,
  strength VARCHAR(80) NULL,
  unit VARCHAR(30) NOT NULL DEFAULT 'piece',
  selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  reorder_level INT UNSIGNED NOT NULL DEFAULT 0,
  description TEXT NULL,
  requires_prescription TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_products_name (product_name),
  INDEX idx_products_status (status)
) ENGINE=InnoDB;

CREATE TABLE inventory_batches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  batch_number VARCHAR(100) NOT NULL,
  expiry_date DATE NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 0,
  purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  received_date DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_batches_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  UNIQUE KEY uq_product_batch (product_id, batch_number),
  INDEX idx_batches_expiry (expiry_date),
  INDEX idx_batches_product_expiry (product_id, expiry_date)
) ENGINE=InnoDB;

CREATE TABLE stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  batch_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  movement_type ENUM('stock_in','sale','customer_return','supplier_return','adjustment','damaged','expired') NOT NULL,
  quantity_change INT NOT NULL,
  reference_type VARCHAR(50) NULL,
  reference_id BIGINT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_movements_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_movements_batch FOREIGN KEY (batch_id) REFERENCES inventory_batches(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_movements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_movements_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE purchase_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_order_number VARCHAR(40) NOT NULL UNIQUE,
  supplier_id INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED NULL,
  order_date DATE NOT NULL,
  expected_date DATE NULL,
  status ENUM('draft','pending','approved','ordered','partially_received','received','cancelled') NOT NULL DEFAULT 'pending',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_po_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE purchase_order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity_ordered INT UNSIGNED NOT NULL,
  unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  quantity_received INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_poi_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_poi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE deliveries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  delivery_number VARCHAR(40) NOT NULL UNIQUE,
  purchase_order_id INT UNSIGNED NOT NULL,
  received_by INT UNSIGNED NULL,
  delivery_date DATE NOT NULL,
  status ENUM('pending','partial','delivered','completed','cancelled') NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_deliveries_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_deliveries_user FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE delivery_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  delivery_id INT UNSIGNED NOT NULL,
  purchase_order_item_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  batch_number VARCHAR(100) NULL,
  expiry_date DATE NULL,
  quantity_ordered INT UNSIGNED NOT NULL DEFAULT 0,
  quantity_received INT UNSIGNED NOT NULL DEFAULT 0,
  quantity_damaged INT UNSIGNED NOT NULL DEFAULT 0,
  notes TEXT NULL,
  CONSTRAINT fk_di_delivery FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_di_po_item FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_di_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE customer_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(40) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NOT NULL,
  processed_by INT UNSIGNED NULL,
  order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('pending','confirmed','preparing','ready','completed','cancelled','rejected') NOT NULL DEFAULT 'pending',
  payment_method ENUM('cash','gcash','bank_transfer','card','cash_on_delivery') NULL,
  payment_status ENUM('unpaid','pending','paid','refunded') NOT NULL DEFAULT 'unpaid',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  delivery_address TEXT NULL,
  customer_notes TEXT NULL,
  staff_notes TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_orders_user FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE sales (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_number VARCHAR(40) NOT NULL UNIQUE,
  order_id INT UNSIGNED NULL UNIQUE,
  customer_id INT UNSIGNED NULL,
  cashier_id INT UNSIGNED NULL,
  sale_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  payment_method ENUM('cash','gcash','bank_transfer','card','cash_on_delivery') NOT NULL DEFAULT 'cash',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  change_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status ENUM('completed','voided','refunded','partially_refunded') NOT NULL DEFAULT 'completed',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sales_order FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_sales_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_sales_cashier FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE sale_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  batch_id INT UNSIGNED NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sale_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_sale_items_batch FOREIGN KEY (batch_id) REFERENCES inventory_batches(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE returns (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  return_number VARCHAR(40) NOT NULL UNIQUE,
  sale_id INT UNSIGNED NULL,
  order_id INT UNSIGNED NULL,
  customer_id INT UNSIGNED NULL,
  processed_by INT UNSIGNED NULL,
  return_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reason ENUM('damaged_product','wrong_item','expired_product','customer_request','other') NOT NULL DEFAULT 'other',
  status ENUM('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
  refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  notes TEXT NULL,
  CONSTRAINT fk_returns_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_returns_order FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_returns_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_returns_user FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE return_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  return_id INT UNSIGNED NOT NULL,
  sale_item_id INT UNSIGNED NULL,
  product_id INT UNSIGNED NOT NULL,
  batch_id INT UNSIGNED NULL,
  quantity INT UNSIGNED NOT NULL,
  reason_detail VARCHAR(255) NULL,
  restock TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_return_items_return FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_return_items_sale_item FOREIGN KEY (sale_item_id) REFERENCES sale_items(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_return_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_return_items_batch FOREIGN KEY (batch_id) REFERENCES inventory_batches(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  notification_type ENUM('low_stock','expiry','customer_order','delivery','return','system') NOT NULL DEFAULT 'system',
  title VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  related_type VARCHAR(50) NULL,
  related_id BIGINT UNSIGNED NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  username_snapshot VARCHAR(60) NULL,
  role_snapshot VARCHAR(30) NULL,
  action VARCHAR(80) NOT NULL,
  module VARCHAR(80) NOT NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE system_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Seed data
INSERT INTO categories (category_name, description) VALUES
('Analgesics / Pain Relievers','For pain and fever'),
('Antibiotics','Antibiotic products'),
('Antihistamines / Allergy','Allergy and cold products'),
('Antacids / Digestive','Digestive care'),
('Cough & Cold','Cough and cold remedies'),
('Vitamins & Supplements','Vitamins and supplements'),
('Cosmetics','Personal care and cosmetics'),
('Milks','Milk products');

INSERT INTO system_settings (setting_key, setting_value) VALUES
('pharmacy_name','Mega Life Pharmacy'),
('contact_number','09xx-xxx-xxxx'),
('address','San Vicente, Calapan City, Oriental Mindoro'),
('email','megalife@gmail.com'),
('low_stock_alert','1'),
('expiry_alert','1'),
('default_reorder_level','20'),
('expiry_alert_days','30'),
('require_staff_order_confirmation','1'),
('allow_customer_cancellation','1'),
('customer_cancellation_limit_minutes','30'),
('payment_cod','1'),
('payment_online','1'),
('payment_cash','1'),
('payment_gcash','1'),
('payment_bank_transfer','1'),
('payment_card','0');