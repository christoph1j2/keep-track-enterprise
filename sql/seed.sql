-- =============================================================================
-- KeepTrack Enterprise Seed
-- =============================================================================
-- Usage:
--   mysql -u root -p < sql/seed.sql
--   or via Docker:
--   docker compose exec db mysql -u root -prootpassword < sql/seed.sql
--
-- Poznámka: Testovací data níže (klienti, faktury, platby, výdaje) byla
-- vygenerována pomocí AI pro účely demonstrace a testování aplikace.
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
-- Prohlášení: Testovací/ukázková data v této sekci byla vygenerována pomocí AI.
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
  (3, 'OSVČ'),
  (4, 'Státní instituce'),
  (5, 'Nezisková organizace');

-- Sample users (password for all: "asdfasdf")
INSERT IGNORE INTO `users` (`username`, `email`, `profession`, `role_id`, `password_hash`, `accountant_id`, `is_blocked`) VALUES
  ('superadmin', 'superadmin@kte.cz', NULL, 1, '$2y$10$gql9b.myt0.z7iOR/E0IfOQxuX9eHNJM4iDEz985a3DZD9dDzfc6C', NULL, 0),
  ('admin', 'admin@kte.cz', NULL, 2, '$2y$10$gql9b.myt0.z7iOR/E0IfOQxuX9eHNJM4iDEz985a3DZD9dDzfc6C', NULL, 0),
  ('accountant', 'accountant@kte.cz', NULL, 4, '$2y$10$gql9b.myt0.z7iOR/E0IfOQxuX9eHNJM4iDEz985a3DZD9dDzfc6C', NULL, 0),
  ('user', 'user@kte.cz', 'Vývoj webových aplikací', 3, '$2y$10$gql9b.myt0.z7iOR/E0IfOQxuX9eHNJM4iDEz985a3DZD9dDzfc6C', NULL, 0);

SET @accountant_id = (SELECT `id` FROM `users` WHERE `email` = 'accountant@kte.cz' LIMIT 1);
SET @osvc_id       = (SELECT `id` FROM `users` WHERE `email` = 'user@kte.cz' LIMIT 1);

-- Link demo OSVC user to demo accountant
UPDATE `users`
SET `accountant_id` = @accountant_id
WHERE `id` = @osvc_id AND `accountant_id` IS NULL;

-- Sample clients for demo OSVC (user@kte.cz)
INSERT INTO `clients` (`name`, `user_id`, `contact_email`, `contact_phone`, `description`, `ico`, `dic`, `type_id`) VALUES
  ('ACME Software s.r.o.', @osvc_id, 'fakturace@acme-sw.cz', '+420 222 333 444', 'Vývoj a údržba webového portálu', '12345678', 'CZ12345678', 2);
SET @client1_id = LAST_INSERT_ID();

INSERT INTO `clients` (`name`, `user_id`, `contact_email`, `contact_phone`, `description`, `ico`, `dic`, `type_id`) VALUES
  ('Petr Svoboda', @osvc_id, 'petr.svoboda@email.cz', '+420 603 111 222', 'Konzultace a návrh databáze', '87654321', NULL, 3);
SET @client2_id = LAST_INSERT_ID();

INSERT INTO `clients` (`name`, `user_id`, `contact_email`, `contact_phone`, `description`, `ico`, `dic`, `type_id`) VALUES
  ('Marie Dvořáková', @osvc_id, 'marie.dvorakova@example.cz', '+420 777 888 999', 'Tvorba osobního webu', NULL, NULL, 1);
SET @client3_id = LAST_INSERT_ID();

INSERT INTO `clients` (`name`, `user_id`, `contact_email`, `contact_phone`, `description`, `ico`, `dic`, `type_id`) VALUES
  ('Západočeská univerzita v Plzni', @osvc_id, 'faktury@zcu.cz', '+420 377 631 111', 'Vývoj interního informačního modulu', '49777513', 'CZ49777513', 4);
SET @client4_id = LAST_INSERT_ID();

INSERT INTO `clients` (`name`, `user_id`, `contact_email`, `contact_phone`, `description`, `ico`, `dic`, `type_id`) VALUES
  ('Novák & Partneři s.r.o.', @osvc_id, 'office@novakpartneri.cz', '+420 224 910 320', 'Klientský portál pro advokátní kancelář', '26481920', 'CZ26481920', 2);
SET @client5_id = LAST_INSERT_ID();

INSERT INTO `clients` (`name`, `user_id`, `contact_email`, `contact_phone`, `description`, `ico`, `dic`, `type_id`) VALUES
  ('Spolek Dobrý skutek z.s.', @osvc_id, 'kontakt@dobryskutek.cz', '+420 731 445 612', 'Správa dárcovského webu a formulářů', '04129851', NULL, 5);
SET @client6_id = LAST_INSERT_ID();

INSERT INTO `clients` (`name`, `user_id`, `contact_email`, `contact_phone`, `description`, `ico`, `dic`, `type_id`) VALUES
  ('Jan Procházka', @osvc_id, 'jan.prochazka@devmail.cz', '+420 608 992 140', 'Výpomoc s backendem a CI/CD', '09341287', NULL, 3);
SET @client7_id = LAST_INSERT_ID();

INSERT INTO `clients` (`name`, `user_id`, `contact_email`, `contact_phone`, `description`, `ico`, `dic`, `type_id`) VALUES
  ('Karel Černý', @osvc_id, 'karel.cerny@seznam.cz', '+420 721 554 809', 'Jednorázová konzultace a nasazení WP', NULL, NULL, 1);
SET @client8_id = LAST_INSERT_ID();

-- Sample invoices & items (16 invoices -> exercises pagination)
INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client1_id, '2026-01', 'Tvorba e-shopu – 1. etapa', '2026-06-01 10:00:00', '2026-06-15', 45000.00, 1);
SET @inv1_id = LAST_INSERT_ID();
INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Analýza a návrh architektury', 1, 15000.00, @inv1_id),
  ('Implementace frontendu a košíku', 30, 1000.00, @inv1_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client2_id, '2026-02', 'Databázová optimalizace', '2026-06-18 14:30:00', '2026-07-02', 18000.00, 1);
SET @inv2_id = LAST_INSERT_ID();
INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Audit SQL dotazů a indexů', 12, 1500.00, @inv2_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client1_id, '2026-03', 'Měsíční správa a SLA (07/2026)', '2026-07-05 09:00:00', '2026-07-19', 20000.00, 0);
SET @inv3_id = LAST_INSERT_ID();
INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Správa serveru a monitoring', 1, 12000.00, @inv3_id),
  ('Rozvoj nových modulů', 8, 1000.00, @inv3_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client3_id, '2026-04', 'Osobní portfolio', '2026-07-22 11:15:00', '2026-08-05', 14500.00, 1);
SET @inv4_id = LAST_INSERT_ID();
INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Grafický návrh a šablony', 1, 8500.00, @inv4_id),
  ('Nasazení na hosting a DNS', 1, 6000.00, @inv4_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client4_id, '2026-05', 'Modul evidence projektů', '2026-08-10 08:30:00', '2026-08-24', 37000.00, 1);
SET @inv5_id = LAST_INSERT_ID();
INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Datový model a migrace', 1, 25000.00, @inv5_id),
  ('Napojení na SSO autentizaci', 10, 1200.00, @inv5_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client5_id, '2026-06', 'Klientský portál – prototyp', '2026-08-25 13:00:00', '2026-09-08', 28000.00, 1);
SET @inv6_id = LAST_INSERT_ID();
INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Vývoj klientské zóny', 20, 1400.00, @inv6_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client6_id, '2026-07', 'Redesign dárcovského webu', '2026-09-04 10:45:00', '2026-09-18', 13000.00, 1);
SET @inv7_id = LAST_INSERT_ID();
INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Responzivní šablona a formuláře', 1, 9000.00, @inv7_id),
  ('Integrace platební brány', 4, 1000.00, @inv7_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client7_id, '2026-08', 'Dockerizace a CI pipeline', '2026-09-14 16:00:00', '2026-09-28', 18000.00, 1);
SET @inv8_id = LAST_INSERT_ID();
INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Nastavení kontejnerů a GitLab CI', 15, 1200.00, @inv8_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client5_id, '2026-09', 'Klientský portál – ostrý provoz', '2026-09-29 09:20:00', '2026-10-13', 25000.00, 0);
SET @inv9_id = LAST_INSERT_ID();
INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Export dokumentů do PDF', 1, 16000.00, @inv9_id),
  ('Bezpečnostní audit a testy', 6, 1500.00, @inv9_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client8_id, '2026-10', 'Konzultace a migrace hostingu', '2026-10-08 15:10:00', '2026-10-22', 7500.00, 1);
SET @inv10_id = LAST_INSERT_ID();
INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Migrace webu a nastavení SSL', 1, 7500.00, @inv10_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client4_id, '2026-11', 'Rozšíření reportingu pro ZČU', '2026-10-19 10:00:00', '2026-11-02', 23400.00, 0);
SET @inv11_id = LAST_INSERT_ID();
INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Implementace exportů CSV a grafů', 18, 1300.00, @inv11_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client1_id, '2026-12', 'Měsíční správa a SLA (10/2026)', '2026-10-30 09:00:00', '2026-11-13', 23000.00, 0);
SET @inv12_id = LAST_INSERT_ID();
INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Provozní podpora a aktualizace', 1, 12000.00, @inv12_id),
  ('Optimalizace vyhledávání', 10, 1100.00, @inv12_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client2_id, '2026-13', 'Zálohovací skripty a replikace', '2026-11-03 11:40:00', '2026-11-17', 12000.00, 0);
SET @inv13_id = LAST_INSERT_ID();
INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Konfigurace MariaDB replikace', 8, 1500.00, @inv13_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client6_id, '2026-14', 'Roční technická podpora spolku', '2026-11-07 14:00:00', '2026-11-21', 7500.00, 0);
SET @inv14_id = LAST_INSERT_ID();
INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Roční údržba a monitoring', 1, 5500.00, @inv14_id),
  ('Školení editorů obsahu', 2, 1000.00, @inv14_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client7_id, '2026-15', 'Refaktoring REST konektoru', '2026-11-11 10:25:00', '2026-11-25', 12000.00, 0);
SET @inv15_id = LAST_INSERT_ID();
INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Úprava API klienta a unit testy', 10, 1200.00, @inv15_id);

INSERT INTO `invoices` (`client_id`, `invoice_number`, `name`, `date_issued`, `date_due`, `total_amount`, `paid`) VALUES
  (@client3_id, '2026-16', 'Fotogalerie a kontaktní formulář', '2026-11-14 16:45:00', '2026-11-28', 12000.00, 0);
SET @inv16_id = LAST_INSERT_ID();
INSERT INTO `invoice_items` (`name`, `quantity`, `unit_price`, `invoice_id`) VALUES
  ('Implementace galerie', 1, 9500.00, @inv16_id),
  ('Antispam ochrana a SEO meta', 1, 2500.00, @inv16_id);

-- Sample payments & invoice_payment pairing
INSERT INTO `payments` (`specific_symbol`, `amount`, `date_paid`, `note`) VALUES
  ('VS202601', 45000.00, '2026-06-12 08:45:00', 'Úhrada faktury 2026-01');
SET @pay1_id = LAST_INSERT_ID();
INSERT INTO `invoice_payment` (`invoice_id`, `payment_id`, `amount_assigned`) VALUES
  (@inv1_id, @pay1_id, 45000.00);

INSERT INTO `payments` (`specific_symbol`, `amount`, `date_paid`, `note`) VALUES
  ('VS20260203', 28000.00, '2026-07-14 16:20:00', 'Úhrada 2026-02 + záloha na 2026-03');
SET @pay2_id = LAST_INSERT_ID();
INSERT INTO `invoice_payment` (`invoice_id`, `payment_id`, `amount_assigned`) VALUES
  (@inv2_id, @pay2_id, 18000.00),
  (@inv3_id, @pay2_id, 10000.00);

INSERT INTO `payments` (`specific_symbol`, `amount`, `date_paid`, `note`) VALUES
  ('VS202604', 14500.00, '2026-08-03 11:10:00', 'Úhrada faktury 2026-04');
SET @pay3_id = LAST_INSERT_ID();
INSERT INTO `invoice_payment` (`invoice_id`, `payment_id`, `amount_assigned`) VALUES
  (@inv4_id, @pay3_id, 14500.00);

INSERT INTO `payments` (`specific_symbol`, `amount`, `date_paid`, `note`) VALUES
  ('VS202605', 37000.00, '2026-08-21 09:05:00', 'Úhrada faktury ZČU 2026-05');
SET @pay4_id = LAST_INSERT_ID();
INSERT INTO `invoice_payment` (`invoice_id`, `payment_id`, `amount_assigned`) VALUES
  (@inv5_id, @pay4_id, 37000.00);

INSERT INTO `payments` (`specific_symbol`, `amount`, `date_paid`, `note`) VALUES
  ('VS20260607', 41000.00, '2026-09-16 14:50:00', 'Souhrnná úhrada faktur 2026-06 a 2026-07');
SET @pay5_id = LAST_INSERT_ID();
INSERT INTO `invoice_payment` (`invoice_id`, `payment_id`, `amount_assigned`) VALUES
  (@inv6_id, @pay5_id, 28000.00),
  (@inv7_id, @pay5_id, 13000.00);

INSERT INTO `payments` (`specific_symbol`, `amount`, `date_paid`, `note`) VALUES
  ('VS20260809', 33000.00, '2026-10-09 10:30:00', 'Úhrada 2026-08 + záloha na 2026-09');
SET @pay6_id = LAST_INSERT_ID();
INSERT INTO `invoice_payment` (`invoice_id`, `payment_id`, `amount_assigned`) VALUES
  (@inv8_id, @pay6_id, 18000.00),
  (@inv9_id, @pay6_id, 15000.00);

INSERT INTO `payments` (`specific_symbol`, `amount`, `date_paid`, `note`) VALUES
  ('VS202610', 7500.00, '2026-10-20 17:15:00', 'Úhrada faktury 2026-10');
SET @pay7_id = LAST_INSERT_ID();
INSERT INTO `invoice_payment` (`invoice_id`, `payment_id`, `amount_assigned`) VALUES
  (@inv10_id, @pay7_id, 7500.00);

-- Sample expenses (16 expenses -> exercises pagination)
INSERT INTO `expenses` (`user_id`, `name`, `total_amount`, `expense_date`, `note`) VALUES
  (@osvc_id, 'Notebook Lenovo ThinkPad', 32990.00, '2026-06-05 12:00:00', 'Pracovní hardware'),
  (@osvc_id, 'Monitor Dell UltraSharp 27"', 11490.00, '2026-06-12 10:20:00', 'Externí displej pro vývoj'),
  (@osvc_id, 'Kancelářská židle Herman Miller', 18500.00, '2026-06-24 14:00:00', 'Ergonomické vybavení pracovny'),
  (@osvc_id, 'Licence JetBrains PhpStorm', 2490.00, '2026-07-03 09:30:00', 'Roční předplatné IDE'),
  (@osvc_id, 'Hosting a doména VPS (07/2026)', 1850.00, '2026-07-15 15:00:00', 'Provoz testovacích serverů'),
  (@osvc_id, 'Internet a mobilní tarif (07/2026)', 1290.00, '2026-07-31 10:15:00', 'Měsíční konektivita'),
  (@osvc_id, 'Pronájem coworkingu (08/2026)', 4500.00, '2026-08-04 08:45:00', 'Sdílená kancelář v centru'),
  (@osvc_id, 'Konference WebExpo vstupenka', 5900.00, '2026-08-19 13:10:00', 'Odborné vzdělávání'),
  (@osvc_id, 'Cestovné vlakem – jednání Praha', 840.00, '2026-09-02 07:30:00', 'Schůzka se zákazníkem'),
  (@osvc_id, 'Odborná literatura (Clean Architecture)', 1150.00, '2026-09-11 16:40:00', 'Knihy pro profesní rozvoj'),
  (@osvc_id, 'Cloud zálohování a S3 storage', 690.00, '2026-09-22 11:00:00', 'Šifrované zálohy projektů'),
  (@osvc_id, 'Účetní a daňové poradenství', 3500.00, '2026-10-01 09:00:00', 'Konzultace k přiznání'),
  (@osvc_id, 'Internet a mobilní tarif (10/2026)', 1290.00, '2026-10-14 10:15:00', 'Měsíční konektivita'),
  (@osvc_id, 'Hosting a doména VPS (10/2026)', 1850.00, '2026-10-25 15:00:00', 'Provoz produkčních a staging VPS'),
  (@osvc_id, 'Mechanická klávesnice a myš', 3890.00, '2026-11-02 12:30:00', 'Periferie k pracovní stanici'),
  (@osvc_id, 'Internet a mobilní tarif (11/2026)', 1290.00, '2026-11-10 10:15:00', 'Měsíční konektivita');
