-- =============================================================================
-- KeepTrack Enterprise Seed
-- =============================================================================
-- Usage:
--   mysql -u root -p < sql/seed.sql
--   or via Docker:
--   docker compose exec db mysql -u root -prootpassword < sql/seed.sql
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `keeptrack`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `keeptrack`;

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- =============================================================================
-- TABLES
-- =============================================================================

CREATE TABLE IF NOT EXISTS `users` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `username` varchar(255) UNIQUE NOT NULL,
  `email` varchar(255) UNIQUE NOT NULL,
  `profession` varchar(255),
  `role_id` integer,
  `password_hash` varchar(255) NOT NULL,
  `accountant_id` integer COMMENT 'ucetni prirazeny osvc',
  `accountant_key` varchar(255) COMMENT 'pozvanka pro ucetni',
  `accountant_key_expires` datetime,
  `is_blocked` boolean DEFAULT false
);

CREATE TABLE IF NOT EXISTS `roles` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `role` varchar(255) NOT NULL COMMENT 'admin, osvc, ucetni'
);

CREATE TABLE IF NOT EXISTS `expenses` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `user_id` integer NOT NULL,
  `total_amount` decimal(10,2),
  `expense_date` datetime NOT NULL COMMENT 'oproti navrzenemu schematu zmena, protoze dava smysl vedet kdy vydaje byly',
  `note` text,
  `receipt_filename` varchar(255) DEFAULT NULL,
  `receipt_original_name` varchar(255) DEFAULT NULL,
  `receipt_mime` varchar(255) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS `client_types` (
  `id` tinyint PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) UNIQUE COMMENT 'firma/fyzicka osoba/...'
);

CREATE TABLE IF NOT EXISTS `clients` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `user_id` integer NOT NULL,
  `contact_email` varchar(255),
  `contact_phone` varchar(255),
  `description` varchar(255),
  `ico` varchar(8),
  `dic` varchar(24),
  `type_id` tinyint
);

CREATE TABLE IF NOT EXISTS `invoices` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `client_id` integer,
  `invoice_number` varchar(255) NOT NULL COMMENT 'dalsi zmena oproti schematu ... faktury by se spravne mely ozacovat jejich cisly (VS)',
  `name` varchar(255) COMMENT 'atribut name bude tedy pouzit pouze jako osobni nazev faktury',
  `date_issued` datetime NOT NULL,
  `date_due` date,
  `total_amount` decimal(10,2) NOT NULL,
  `paid` boolean DEFAULT false
);

CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `quantity` integer,
  `unit_price` decimal(10,2) NOT NULL,
  `invoice_id` integer
);

CREATE TABLE IF NOT EXISTS `payments` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `specific_symbol` varchar(255) NOT NULL COMMENT 'aby sla identifikovat platba',
  `amount` decimal(10,2) NOT NULL,
  `date_paid` datetime NOT NULL,
  `note` text
);

CREATE TABLE IF NOT EXISTS `invoice_payment` (
  `invoice_id` integer,
  `payment_id` integer,
  `amount_assigned` decimal(10,2),
  PRIMARY KEY (`invoice_id`, `payment_id`)
);

-- =============================================================================
-- FOREIGN KEYS
-- =============================================================================

ALTER TABLE `expenses` ADD CONSTRAINT IF NOT EXISTS `fk_expenses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `clients` ADD CONSTRAINT IF NOT EXISTS `fk_clients_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `clients` ADD CONSTRAINT IF NOT EXISTS `fk_clients_type` FOREIGN KEY (`type_id`) REFERENCES `client_types` (`id`) ON DELETE SET NULL;

ALTER TABLE `invoices` ADD CONSTRAINT IF NOT EXISTS `fk_invoices_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL;

ALTER TABLE `invoice_items` ADD CONSTRAINT IF NOT EXISTS `fk_invoice_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

ALTER TABLE `invoice_payment` ADD CONSTRAINT IF NOT EXISTS `fk_invoice_payment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

ALTER TABLE `invoice_payment` ADD CONSTRAINT IF NOT EXISTS `fk_invoice_payment_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE;

ALTER TABLE `users` ADD CONSTRAINT IF NOT EXISTS `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- SEED DATA
-- =============================================================================

-- Roles (required for the application to function)
INSERT IGNORE INTO `roles` (`id`, `role`) VALUES
  (1, 'superadmin'),
  (2, 'admin'),
  (3, 'osvc'),
  (4, 'ucetni');

-- Client types
INSERT IGNORE INTO `client_types` (`id`, `name`) VALUES
  (1, 'Fyzická osoba'),
  (2, 'Firma'),
  (3, 'OSVČ');

