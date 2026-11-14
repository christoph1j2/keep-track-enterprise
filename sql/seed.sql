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
  `name` varchar(255) NOT NULL,
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

ALTER TABLE `expenses` ADD CONSTRAINT `fk_expenses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `clients` ADD CONSTRAINT `fk_clients_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `clients` ADD CONSTRAINT `fk_clients_type` FOREIGN KEY (`type_id`) REFERENCES `client_types` (`id`) ON DELETE SET NULL;

ALTER TABLE `invoices` ADD CONSTRAINT `fk_invoices_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL;

ALTER TABLE `invoice_items` ADD CONSTRAINT `fk_invoice_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

ALTER TABLE `invoice_payment` ADD CONSTRAINT `fk_invoice_payment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

ALTER TABLE `invoice_payment` ADD CONSTRAINT `fk_invoice_payment_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE;

ALTER TABLE `users` ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;

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

-- Sample users (password for all: "asdfasdf")
INSERT IGNORE INTO `users` (`username`, `email`, `profession`, `role_id`, `password_hash`, `accountant_id`, `is_blocked`) VALUES
  ('superadmin', 'superadmin@asdf.asdf', NULL, 1, '$2y$10$gql9b.myt0.z7iOR/E0IfOQxuX9eHNJM4iDEz985a3DZD9dDzfc6C', NULL, 0),
  ('admin', 'admin@asdf.asdf', NULL, 2, '$2y$10$gql9b.myt0.z7iOR/E0IfOQxuX9eHNJM4iDEz985a3DZD9dDzfc6C', NULL, 0),
  ('accountant', 'accountant@asdf.asdf', NULL, 4, '$2y$10$gql9b.myt0.z7iOR/E0IfOQxuX9eHNJM4iDEz985a3DZD9dDzfc6C', NULL, 0),
  ('user', 'user@asdf.asdf', 'Vývoj webových aplikací', 3, '$2y$10$gql9b.myt0.z7iOR/E0IfOQxuX9eHNJM4iDEz985a3DZD9dDzfc6C', NULL, 0);

SET @accountant_id = (SELECT `id` FROM `users` WHERE `email` = 'accountant@asdf.asdf' LIMIT 1);
SET @osvc_id       = (SELECT `id` FROM `users` WHERE `email` = 'user@asdf.asdf' LIMIT 1);

-- Link demo OSVC user to demo accountant
UPDATE `users`
SET `accountant_id` = @accountant_id
WHERE `id` = @osvc_id AND `accountant_id` IS NULL;

-- Sample clients for demo OSVC (user@asdf.asdf)
INSERT INTO `clients` (`name`, `user_id`, `contact_email`, `contact_phone`, `description`, `ico`, `dic`, `type_id`) VALUES
  ('ACME Software s.r.o.', @osvc_id, 'fakturace@acme-sw.cz', '+420 222 333 444', 'Vývoj a údržba webového portálu', '12345678', 'CZ12345678', 2);
SET @client1_id = LAST_INSERT_ID();

INSERT INTO `clients` (`name`, `user_id`, `contact_email`, `contact_phone`, `description`, `ico`, `dic`, `type_id`) VALUES
  ('Petr Svoboda', @osvc_id, 'petr.svoboda@email.cz', '+420 603 111 222', 'Konzultace a návrh databáze', '87654321', NULL, 3);
SET @client2_id = LAST_INSERT_ID();

INSERT INTO `clients` (`name`, `user_id`, `contact_email`, `contact_phone`, `description`, `ico`, `dic`, `type_id`) VALUES
  ('Marie Dvořáková', @osvc_id, 'marie.dvorakova@example.cz', '+420 777 888 999', 'Tvorba osobního webu', NULL, NULL, 1);
SET @client3_id = LAST_INSERT_ID();

-- Sample invoices & items
INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client1_id, '2026-01', 'Tvorba e-shopu – 1. etapa', '2026-09-01 10:00:00', '2026-09-15', 45000.00, 1);
SET @inv1_id = LAST_INSERT_ID();

INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Analýza a návrh architektury', 1, 15000.00, @inv1_id),
  ('Implementace frontendu a košíku', 30, 1000.00, @inv1_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client2_id, '2026-02', 'Databázová optimalizace', '2026-10-05 14:30:00', '2026-10-19', 18000.00, 1);
SET @inv2_id = LAST_INSERT_ID();

INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Audit SQL dotazů a indexů', 12, 1500.00, @inv2_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client1_id, '2026-03', 'Měsíční správa a SLA', '2026-10-20 09:00:00', '2026-11-03', 20000.00, 0);
SET @inv3_id = LAST_INSERT_ID();

INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Správa serveru a monitoring', 1, 12000.00, @inv3_id),
  ('Rozvoj nových modulů', 8, 1000.00, @inv3_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client3_id, '2026-04', 'Osobní portfolio', '2026-11-10 11:15:00', '2026-11-24', 14500.00, 0);
SET @inv4_id = LAST_INSERT_ID();

INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Grafický návrh a šablony', 1, 8500.00, @inv4_id),
  ('Nasazení na hosting a DNS', 1, 6000.00, @inv4_id);

-- Sample payments & invoice_payment pairing
INSERT INTO `payments` (`specific_symbol`, `amount`, `date_paid`, `note`) VALUES
  ('VS202601', 45000.00, '2026-09-12 08:45:00', 'Úhrada faktury 2026-01');
SET @pay1_id = LAST_INSERT_ID();

INSERT INTO `invoice_payment` (`invoice_id`, `payment_id`, `amount_assigned`) VALUES
  (@inv1_id, @pay1_id, 45000.00);

INSERT INTO `payments` (`specific_symbol`, `amount`, `date_paid`, `note`) VALUES
  ('VS20260203', 28000.00, '2026-10-28 16:20:00', 'Úhrada 2026-02 + částečná úhrada 2026-03');
SET @pay2_id = LAST_INSERT_ID();

INSERT INTO `invoice_payment` (`invoice_id`, `payment_id`, `amount_assigned`) VALUES
  (@inv2_id, @pay2_id, 18000.00),
  (@inv3_id, @pay2_id, 10000.00);

-- Sample expenses
INSERT INTO `expenses` (`user_id`, `name`, `total_amount`, `expense_date`, `note`) VALUES
  (@osvc_id, 'Notebook Lenovo ThinkPad', 32990.00, '2026-09-05 12:00:00', 'Pracovní hardware'),
  (@osvc_id, 'Licence JetBrains PhpStorm', 2490.00, '2026-09-18 09:30:00', 'Roční předplatné IDE'),
  (@osvc_id, 'Hosting a doména VPS', 1850.00, '2026-10-02 15:00:00', 'Provoz testovacích serverů'),
  (@osvc_id, 'Internet a mobilní tarif', 1290.00, '2026-11-01 10:15:00', 'Měsíční konektivita');
