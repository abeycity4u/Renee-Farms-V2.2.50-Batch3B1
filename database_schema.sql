-- Renee AgriSuite fresh-install database schema
-- Generated from the certified production schema structure.
-- Schema only: no production rows, credentials or runtime state.
--
-- Historical upgrades remain under migrations/.
-- This file represents the current fresh-install structural baseline.

/*M!999999\- enable the sandbox mode */

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
DROP TABLE IF EXISTS `account_credential_delivery_outbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_credential_delivery_outbox` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `purpose` enum('activation','password_reset') NOT NULL,
  `status` enum('pending','processing','sent','discarded','failed') NOT NULL DEFAULT 'pending',
  `attempt_count` int(10) unsigned NOT NULL DEFAULT 0,
  `available_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  `last_error_code` varchar(80) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_account_credential_outbox_pending` (`status`,`available_at`,`id`),
  KEY `idx_account_credential_outbox_user_purpose` (`user_id`,`purpose`,`status`),
  CONSTRAINT `fk_account_credential_outbox_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `account_credential_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_credential_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `purpose` enum('activation','password_reset') NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_account_credential_token_hash` (`token_hash`),
  KEY `idx_account_credential_tokens_user_purpose` (`user_id`,`purpose`,`consumed_at`),
  KEY `idx_account_credential_tokens_expiry` (`expires_at`),
  CONSTRAINT `fk_account_credential_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `billing_payment_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_payment_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `purpose` varchar(32) NOT NULL DEFAULT 'subscription',
  `commercial_disposition` varchar(24) NOT NULL DEFAULT 'eligible',
  `commercial_superseded_at` datetime DEFAULT NULL,
  `commercial_supersession_verified_at` datetime DEFAULT NULL,
  `commercial_superseded_by_user_id` int(11) DEFAULT NULL,
  `commercial_supersession_reason` varchar(80) DEFAULT NULL,
  `status` enum('initialized','pending','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'initialized',
  `provider` varchar(40) NOT NULL,
  `provider_reference` varchar(150) NOT NULL,
  `provider_transaction_id` varchar(150) DEFAULT NULL,
  `provider_subscription_id` varchar(150) DEFAULT NULL,
  `plan_code` varchar(50) NOT NULL,
  `billing_interval` enum('monthly','annual') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL,
  `modules_snapshot` text NOT NULL,
  `seat_addons_snapshot` text NOT NULL,
  `quote_hash` char(64) NOT NULL,
  `initiated_by_user_id` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `failed_at` datetime DEFAULT NULL,
  `failure_code` varchar(80) DEFAULT NULL,
  `applied_subscription_record_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_billing_provider_reference` (`provider`,`provider_reference`),
  KEY `idx_billing_attempt_farm_status` (`farm_id`,`status`),
  KEY `idx_billing_attempt_provider_transaction` (`provider`,`provider_transaction_id`),
  KEY `idx_billing_attempt_quote_hash` (`quote_hash`),
  KEY `fk_billing_attempt_subscription_record` (`applied_subscription_record_id`),
  KEY `idx_billing_attempt_farm_purpose_status` (`farm_id`,`purpose`,`status`),
  KEY `idx_billing_attempt_farm_purpose_disposition_status` (`farm_id`,`purpose`,`commercial_disposition`,`status`),
  CONSTRAINT `fk_billing_attempt_farm_restrict` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_billing_attempt_subscription_record` FOREIGN KEY (`applied_subscription_record_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `billing_provider_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_provider_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(40) NOT NULL,
  `provider_event_id` varchar(150) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `payload_hash` char(64) NOT NULL,
  `payment_attempt_id` bigint(20) unsigned DEFAULT NULL,
  `processing_status` enum('received','processed','ignored','failed') NOT NULL DEFAULT 'received',
  `error_message` varchar(255) DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_billing_provider_event` (`provider`,`provider_event_id`),
  KEY `idx_billing_event_attempt` (`payment_attempt_id`),
  CONSTRAINT `fk_billing_event_attempt` FOREIGN KEY (`payment_attempt_id`) REFERENCES `billing_payment_attempts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `billing_refund_resolutions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_refund_resolutions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `payment_attempt_id` bigint(20) unsigned NOT NULL,
  `purpose` varchar(32) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending_review',
  `resolution_action` varchar(32) DEFAULT NULL,
  `refund_verified_at` datetime NOT NULL,
  `applied_subscription_record_id` int(11) DEFAULT NULL,
  `seat_change_request_id` bigint(20) unsigned DEFAULT NULL,
  `reversal_subscription_record_id` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by_user_id` int(11) DEFAULT NULL,
  `resolution_reason` varchar(160) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_billing_refund_payment_attempt` (`payment_attempt_id`),
  KEY `idx_billing_refund_farm_status` (`farm_id`,`status`),
  KEY `idx_billing_refund_status_created` (`status`,`created_at`),
  KEY `idx_billing_refund_subscription` (`applied_subscription_record_id`),
  KEY `idx_billing_refund_seat_request` (`seat_change_request_id`),
  KEY `idx_billing_refund_reversal_subscription` (`reversal_subscription_record_id`),
  CONSTRAINT `fk_billing_refund_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_billing_refund_payment_attempt` FOREIGN KEY (`payment_attempt_id`) REFERENCES `billing_payment_attempts` (`id`),
  CONSTRAINT `fk_billing_refund_reversal_subscription` FOREIGN KEY (`reversal_subscription_record_id`) REFERENCES `subscriptions` (`id`),
  CONSTRAINT `fk_billing_refund_seat_request` FOREIGN KEY (`seat_change_request_id`) REFERENCES `billing_seat_change_requests` (`id`),
  CONSTRAINT `fk_billing_refund_subscription` FOREIGN KEY (`applied_subscription_record_id`) REFERENCES `subscriptions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `billing_seat_change_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_seat_change_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `change_kind` varchar(24) NOT NULL,
  `status` varchar(32) NOT NULL,
  `role_code` varchar(50) NOT NULL,
  `from_extra_seats` int(10) unsigned NOT NULL,
  `to_extra_seats` int(10) unsigned NOT NULL,
  `plan_code` varchar(50) NOT NULL,
  `billing_interval` enum('monthly','annual') NOT NULL,
  `modules_snapshot` text NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL,
  `current_period_ends_at` datetime NOT NULL,
  `quoted_at` datetime DEFAULT NULL,
  `lineage_start_at` datetime DEFAULT NULL,
  `segment_start_at` datetime DEFAULT NULL,
  `segment_end_at` datetime DEFAULT NULL,
  `pricing_version` varchar(80) DEFAULT NULL,
  `pricing_hash` char(64) DEFAULT NULL,
  `unit_amount` decimal(12,2) DEFAULT NULL,
  `partial_unit_amount` decimal(12,2) DEFAULT NULL,
  `future_full_periods` int(10) unsigned DEFAULT NULL,
  `per_seat_amount` decimal(12,2) DEFAULT NULL,
  `latest_paid_subscription_id` int(11) DEFAULT NULL,
  `latest_paid_attempt_id` bigint(20) unsigned DEFAULT NULL,
  `effective_at` datetime DEFAULT NULL,
  `payment_attempt_id` bigint(20) unsigned DEFAULT NULL,
  `applied_subscription_record_id` int(11) DEFAULT NULL,
  `initiated_by_user_id` int(11) DEFAULT NULL,
  `request_hash` char(64) NOT NULL,
  `applied_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_billing_seat_change_payment_attempt` (`payment_attempt_id`),
  KEY `idx_billing_seat_change_farm_status` (`farm_id`,`status`),
  KEY `idx_billing_seat_change_farm_role_status` (`farm_id`,`role_code`,`status`),
  KEY `idx_billing_seat_change_effective` (`status`,`effective_at`),
  KEY `idx_billing_seat_change_latest_paid_subscription` (`latest_paid_subscription_id`),
  KEY `idx_billing_seat_change_latest_paid_attempt` (`latest_paid_attempt_id`),
  KEY `idx_billing_seat_change_applied_subscription` (`applied_subscription_record_id`),
  CONSTRAINT `fk_billing_seat_change_applied_subscription` FOREIGN KEY (`applied_subscription_record_id`) REFERENCES `subscriptions` (`id`),
  CONSTRAINT `fk_billing_seat_change_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_billing_seat_change_latest_attempt` FOREIGN KEY (`latest_paid_attempt_id`) REFERENCES `billing_payment_attempts` (`id`),
  CONSTRAINT `fk_billing_seat_change_latest_subscription` FOREIGN KEY (`latest_paid_subscription_id`) REFERENCES `subscriptions` (`id`),
  CONSTRAINT `fk_billing_seat_change_payment_attempt` FOREIGN KEY (`payment_attempt_id`) REFERENCES `billing_payment_attempts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `broiler_daily_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `broiler_daily_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `cycle_id` int(11) DEFAULT NULL,
  `record_date` date NOT NULL,
  `opening_stock` int(11) NOT NULL,
  `mortality` int(11) NOT NULL DEFAULT 0,
  `feed_consumption_bags` decimal(12,2) NOT NULL DEFAULT 0.00,
  `feed_item_id` int(11) DEFAULT NULL,
  `water_consumption_liters` decimal(12,2) NOT NULL DEFAULT 0.00,
  `medications` text DEFAULT NULL,
  `birds_age` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_broiler_cycle_record` (`cycle_id`,`record_date`),
  KEY `idx_broiler_farm` (`farm_id`),
  KEY `idx_broiler_cycle_date` (`cycle_id`,`record_date`),
  KEY `fk_broiler_user` (`user_id`),
  KEY `idx_broiler_daily_feed_item` (`feed_item_id`),
  CONSTRAINT `fk_broiler_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_broiler_daily_feed_item` FOREIGN KEY (`feed_item_id`) REFERENCES `stock_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_broiler_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_broiler_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_ledger_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_ledger_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `entry_date` date NOT NULL,
  `entry_type` enum('sale','payment','adjustment') NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ledger_farm` (`farm_id`),
  KEY `idx_customer_date` (`customer_name`,`entry_date`),
  KEY `idx_entry_type` (`entry_type`),
  KEY `fk_ledger_sale` (`sale_id`),
  KEY `fk_ledger_user` (`user_id`),
  CONSTRAINT `fk_ledger_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_ledger_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales_records` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ledger_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `farm_expense_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `farm_expense_revisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `expense_id` int(11) NOT NULL,
  `revision_no` int(10) unsigned NOT NULL,
  `revision_action` varchar(32) NOT NULL,
  `revision_reason` varchar(500) DEFAULT NULL,
  `previous_revision_id` bigint(20) unsigned DEFAULT NULL,
  `causal_fingerprint` char(64) NOT NULL,
  `causal_manifest_json` longtext NOT NULL,
  `state_fingerprint` char(64) NOT NULL,
  `state_manifest_json` longtext NOT NULL,
  `changed_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_farm_expense_revision` (`farm_id`,`expense_id`,`revision_no`),
  KEY `idx_farm_expense_revision_lookup` (`farm_id`,`expense_id`,`id`),
  KEY `idx_farm_expense_revision_previous` (`previous_revision_id`),
  CONSTRAINT `fk_farm_expense_revisions_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `farm_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `farm_expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_reference` varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `farm_id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `farm_type` enum('poultry','ruminant','both','general') NOT NULL DEFAULT 'both',
  `production_type` varchar(100) DEFAULT NULL,
  `attribution_scope` enum('cycle','production_type','farm') NOT NULL DEFAULT 'farm',
  `poultry_category` enum('broiler','layer') DEFAULT NULL,
  `cycle_id` int(11) DEFAULT NULL,
  `category` enum('feeds','medication','salary','labour','logistic','fuel','processing_materials','misc') NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `unit` decimal(12,2) NOT NULL DEFAULT 1.00,
  `description` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expense_revision_no` int(10) unsigned DEFAULT NULL,
  `expense_causal_fingerprint` char(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_farm_expense_public_reference` (`public_reference`),
  KEY `idx_expenses_farm` (`farm_id`),
  KEY `idx_expense_month` (`expense_date`),
  KEY `idx_expense_cycle_date` (`cycle_id`,`expense_date`),
  KEY `fk_expenses_user` (`user_id`),
  KEY `idx_expense_attribution` (`farm_id`,`farm_type`,`production_type`,`cycle_id`,`expense_date`),
  CONSTRAINT `fk_expenses_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_expenses_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_expenses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `farm_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `farm_modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `module_code` enum('poultry','ruminant','sales') NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `enabled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_farm_module` (`farm_id`,`module_code`),
  KEY `idx_farm_modules_enabled` (`farm_id`,`is_enabled`),
  CONSTRAINT `fk_farm_modules_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `farm_role_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `farm_role_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `role_code` varchar(50) NOT NULL,
  `max_users` int(11) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_farm_role_limit` (`farm_id`,`role_code`),
  KEY `idx_farm_role_limits_farm` (`farm_id`),
  CONSTRAINT `fk_farm_role_limits_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `farm_subscription_seat_addons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `farm_subscription_seat_addons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `role_code` varchar(50) NOT NULL,
  `extra_seats` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_farm_subscription_seat_role` (`farm_id`,`role_code`),
  KEY `idx_farm_subscription_seat_addons_farm` (`farm_id`),
  CONSTRAINT `fk_farm_subscription_seat_addons_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `farms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `farms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `primary_color` varchar(20) NOT NULL DEFAULT '#198754',
  `contact_name` varchar(150) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `subscription_plan` varchar(50) NOT NULL DEFAULT 'starter',
  `subscription_status` enum('trial','active','past_due','suspended','cancelled') NOT NULL DEFAULT 'trial',
  `subscription_starts_at` datetime DEFAULT NULL,
  `trial_ends_at` datetime DEFAULT NULL,
  `subscription_ends_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `financial_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `financial_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `expense_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `allocation_percent` decimal(7,4) NOT NULL,
  `allocated_amount` decimal(14,2) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_financial_allocation` (`expense_id`,`cycle_id`),
  KEY `idx_fin_alloc_farm_cycle` (`farm_id`,`cycle_id`),
  KEY `idx_fin_alloc_expense` (`expense_id`),
  KEY `fk_fin_alloc_cycle` (`cycle_id`),
  KEY `fk_fin_alloc_user` (`created_by`),
  CONSTRAINT `fk_fin_alloc_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fin_alloc_expense` FOREIGN KEY (`expense_id`) REFERENCES `farm_expenses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fin_alloc_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_fin_alloc_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `financial_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `financial_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `feed_costing_method` enum('weighted_average','snapshot') NOT NULL DEFAULT 'weighted_average',
  `default_currency` char(3) NOT NULL DEFAULT 'NGN',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_financial_settings_farm` (`farm_id`),
  CONSTRAINT `fk_fin_settings_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `category_name` varchar(150) NOT NULL,
  `farm_type` enum('poultry','ruminant','both') NOT NULL DEFAULT 'both',
  `unit` varchar(50) NOT NULL DEFAULT 'unit',
  `financial_type` varchar(50) NOT NULL DEFAULT 'other_stock',
  `inventory_role` varchar(40) NOT NULL DEFAULT 'operational',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_categories_farm` (`farm_id`),
  KEY `idx_inventory_categories_financial_type` (`farm_id`,`financial_type`),
  KEY `idx_inventory_category_role` (`farm_id`,`inventory_role`,`farm_type`),
  CONSTRAINT `fk_categories_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `layer_daily_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `layer_daily_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `cycle_id` int(11) DEFAULT NULL,
  `record_date` date NOT NULL,
  `opening_stock` int(11) NOT NULL,
  `mortality` int(11) NOT NULL DEFAULT 0,
  `feed_consumption_bags` decimal(12,2) NOT NULL DEFAULT 0.00,
  `feed_item_id` int(11) DEFAULT NULL,
  `water_consumption_liters` decimal(12,2) NOT NULL DEFAULT 0.00,
  `medications` text DEFAULT NULL,
  `egg_production` int(11) NOT NULL DEFAULT 0,
  `crates_count` int(11) NOT NULL DEFAULT 0,
  `laying_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `birds_age` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_layer_cycle_record` (`cycle_id`,`record_date`),
  KEY `idx_layer_farm` (`farm_id`),
  KEY `idx_layer_cycle_date` (`cycle_id`,`record_date`),
  KEY `fk_layer_user` (`user_id`),
  KEY `idx_layer_daily_feed_item` (`feed_item_id`),
  CONSTRAINT `fk_layer_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_layer_daily_feed_item` FOREIGN KEY (`feed_item_id`) REFERENCES `stock_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_layer_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_layer_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `livestock_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `livestock_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_livestock_type_farm_name` (`farm_id`,`name`),
  UNIQUE KEY `uniq_livestock_type_farm_identity` (`farm_id`,`id`),
  KEY `idx_livestock_type_creator` (`created_by`),
  CONSTRAINT `fk_livestock_types_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_livestock_types_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `chk_livestock_type_name` CHECK (char_length(`name`) = char_length(trim(`name`)) and char_length(`name`) > 0 and lcase(`name`) not in ('cattle','goat','sheep','other','shared')),
  CONSTRAINT `chk_livestock_type_active` CHECK (`is_active` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `management_investigation_followups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `management_investigation_followups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `investigation_type` enum('poultry','ruminant') NOT NULL,
  `subject_id` int(11) NOT NULL,
  `issue_type` varchar(80) NOT NULL,
  `as_of_date` date NOT NULL,
  `episode_key` varchar(120) NOT NULL,
  `status` enum('open','resolved') NOT NULL DEFAULT 'open',
  `outcome` varchar(80) DEFAULT NULL,
  `finding_notes` text DEFAULT NULL,
  `action_taken` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_management_investigation_episode` (`farm_id`,`investigation_type`,`subject_id`,`issue_type`,`episode_key`),
  KEY `idx_management_investigation_status` (`farm_id`,`status`,`as_of_date`),
  KEY `fk_mif_recorded_by` (`recorded_by`),
  KEY `fk_mif_resolved_by` (`resolved_by`),
  KEY `idx_management_investigation_subject` (`farm_id`,`investigation_type`,`subject_id`,`issue_type`,`as_of_date`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL DEFAULT 0,
  `role` varchar(100) NOT NULL,
  `module` varchar(100) NOT NULL,
  `allowed` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_permission_farm_role_module` (`farm_id`,`role`,`module`),
  KEY `idx_permissions_farm` (`farm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `poultry_cycle_acquisitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poultry_cycle_acquisitions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `acquisition_type` varchar(40) NOT NULL,
  `acquisition_date` date NOT NULL,
  `quantity` int(11) NOT NULL,
  `age_days` int(11) NOT NULL,
  `total_cost` decimal(14,2) DEFAULT NULL,
  `source_name` varchar(190) DEFAULT NULL,
  `reference_no` varchar(120) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `request_token` varchar(64) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `voided_at` datetime DEFAULT NULL,
  `voided_by` int(11) DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_poultry_acquisition_request` (`farm_id`,`request_token`),
  KEY `idx_poultry_acquisition_cycle_date` (`farm_id`,`cycle_id`,`acquisition_date`,`id`),
  KEY `idx_poultry_acquisition_type` (`farm_id`,`acquisition_type`,`acquisition_date`),
  KEY `idx_poultry_acquisition_active` (`farm_id`,`cycle_id`,`voided_at`,`acquisition_date`,`id`),
  KEY `fk_poultry_acquisition_cycle` (`cycle_id`),
  KEY `fk_poultry_acquisition_user` (`created_by`),
  CONSTRAINT `fk_poultry_acquisition_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`),
  CONSTRAINT `fk_poultry_acquisition_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_poultry_acquisition_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `poultry_health_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poultry_health_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `cycle_id` int(11) DEFAULT NULL,
  `production_type` enum('layer','broiler') NOT NULL,
  `event_date` date NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `product_name` varchar(150) DEFAULT NULL,
  `dosage` varchar(120) DEFAULT NULL,
  `reason_symptoms` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `stock_item_id` int(11) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_phe_farm_date` (`farm_id`,`event_date`),
  KEY `idx_phe_cycle_date` (`cycle_id`,`event_date`),
  KEY `idx_phe_type_date` (`farm_id`,`production_type`,`event_date`),
  KEY `idx_phe_stock_item` (`stock_item_id`),
  KEY `fk_phe_user` (`recorded_by`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `poultry_production_entry_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poultry_production_entry_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `version_no` int(11) NOT NULL,
  `snapshot_status` enum('original','revised') NOT NULL,
  `entry_model` enum('farm_reared','purchased_point_of_lay') NOT NULL,
  `production_entry_date` date NOT NULL,
  `rearing_start_date` date DEFAULT NULL,
  `rearing_end_date` date DEFAULT NULL,
  `acquisition_basis` decimal(16,2) NOT NULL DEFAULT 0.00,
  `feed_consumed_cost` decimal(16,2) NOT NULL DEFAULT 0.00,
  `operating_inventory_cost` decimal(16,2) NOT NULL DEFAULT 0.00,
  `direct_expenses` decimal(16,2) NOT NULL DEFAULT 0.00,
  `explicit_shared_allocations` decimal(16,2) NOT NULL DEFAULT 0.00,
  `attributed_investment` decimal(16,2) NOT NULL,
  `production_entry_headcount` int(11) NOT NULL,
  `investment_per_entry_bird` decimal(16,2) NOT NULL,
  `unallocated_shared_cost_pool` decimal(16,2) NOT NULL DEFAULT 0.00,
  `source_fingerprint` char(64) NOT NULL,
  `provenance_fingerprint` char(64) DEFAULT NULL,
  `provenance_manifest_json` longtext DEFAULT NULL,
  `provenance_source_count` int(10) unsigned DEFAULT NULL,
  `revision_category` enum('production_entry_confirmation','source_transaction_correction','missing_historical_transaction','allocation_correction','production_entry_headcount_correction','administrative_correction') NOT NULL,
  `revision_reason` varchar(500) DEFAULT NULL,
  `previous_snapshot_id` bigint(20) unsigned DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_poultry_entry_snapshot_version` (`farm_id`,`cycle_id`,`version_no`),
  KEY `idx_poultry_entry_snapshot_current` (`farm_id`,`cycle_id`,`approved_at`),
  KEY `idx_poultry_entry_snapshot_previous` (`previous_snapshot_id`),
  KEY `fk_poultry_entry_snapshot_cycle` (`cycle_id`),
  KEY `fk_poultry_entry_snapshot_user` (`approved_by`),
  CONSTRAINT `fk_poultry_entry_snapshot_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_poultry_entry_snapshot_previous` FOREIGN KEY (`previous_snapshot_id`) REFERENCES `poultry_production_entry_snapshots` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_poultry_entry_snapshot_user` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `poultry_slaughter_batch_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poultry_slaughter_batch_expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `request_token` varchar(64) NOT NULL,
  `request_fingerprint` char(64) NOT NULL,
  `expense_id` int(11) NOT NULL,
  `expense_revision_id` bigint(20) unsigned NOT NULL,
  `expense_revision_no` int(10) unsigned NOT NULL,
  `expense_causal_fingerprint` char(64) NOT NULL,
  `amount_snapshot` decimal(14,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_psbe_request` (`farm_id`,`request_token`),
  UNIQUE KEY `uniq_psbe_expense_revision` (`farm_id`,`batch_id`,`expense_revision_id`),
  KEY `idx_psbe_batch` (`farm_id`,`batch_id`,`id`),
  KEY `idx_psbe_expense` (`farm_id`,`expense_id`,`expense_revision_no`),
  KEY `idx_psbe_expense_revision` (`expense_revision_id`),
  KEY `fk_psbe_batch` (`batch_id`),
  CONSTRAINT `fk_psbe_batch` FOREIGN KEY (`batch_id`) REFERENCES `poultry_slaughter_batches` (`id`),
  CONSTRAINT `fk_psbe_expense_revision` FOREIGN KEY (`expense_revision_id`) REFERENCES `farm_expense_revisions` (`id`),
  CONSTRAINT `fk_psbe_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `chk_psbe_revision` CHECK (`expense_revision_no` > 0),
  CONSTRAINT `chk_psbe_amount` CHECK (`amount_snapshot` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `poultry_slaughter_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poultry_slaughter_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `batch_code` varchar(80) NOT NULL,
  `request_token` varchar(64) NOT NULL,
  `slaughter_date` date NOT NULL,
  `bird_count` int(10) unsigned NOT NULL,
  `live_weight_total_kg` decimal(14,4) DEFAULT NULL,
  `population_before` int(10) unsigned NOT NULL,
  `capital_pool_available_before` decimal(14,2) NOT NULL,
  `operating_pool_available_before` decimal(14,2) NOT NULL,
  `capital_basis_transferred` decimal(14,2) NOT NULL,
  `embedded_operating_basis_transferred` decimal(14,2) NOT NULL,
  `processing_operating_cost` decimal(14,2) NOT NULL DEFAULT 0.00,
  `full_cost_basis_amount` decimal(14,2) NOT NULL,
  `cost_basis_method` varchar(160) NOT NULL,
  `cost_basis_snapshot_at` datetime NOT NULL,
  `cost_basis_finalized_at` datetime DEFAULT NULL,
  `cost_basis_finalized_by` int(11) DEFAULT NULL,
  `cost_basis_provenance_fingerprint` char(64) NOT NULL,
  `cost_basis_provenance_json` longtext NOT NULL,
  `status` enum('open','completed','reversed') NOT NULL DEFAULT 'open',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reversed_by` int(11) DEFAULT NULL,
  `reversed_at` datetime DEFAULT NULL,
  `reversal_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_poultry_slaughter_batch_code` (`farm_id`,`batch_code`),
  UNIQUE KEY `uniq_poultry_slaughter_request` (`farm_id`,`request_token`),
  KEY `idx_poultry_slaughter_cycle` (`farm_id`,`cycle_id`,`slaughter_date`,`id`),
  KEY `idx_poultry_slaughter_status` (`farm_id`,`status`,`slaughter_date`),
  KEY `idx_poultry_slaughter_cost_finalized_by` (`cost_basis_finalized_by`),
  KEY `idx_poultry_slaughter_created_by` (`created_by`),
  KEY `idx_poultry_slaughter_reversed_by` (`reversed_by`),
  KEY `fk_psb_cycle` (`cycle_id`),
  CONSTRAINT `fk_psb_cost_finalized_by` FOREIGN KEY (`cost_basis_finalized_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_psb_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_psb_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`),
  CONSTRAINT `fk_psb_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_psb_reversed_by` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_poultry_slaughter_bird_count` CHECK (`bird_count` > 0),
  CONSTRAINT `chk_poultry_slaughter_population_before` CHECK (`population_before` >= `bird_count`),
  CONSTRAINT `chk_poultry_slaughter_live_weight` CHECK (`live_weight_total_kg` is null or `live_weight_total_kg` > 0),
  CONSTRAINT `chk_poultry_slaughter_capital_pool` CHECK (`capital_pool_available_before` >= 0),
  CONSTRAINT `chk_poultry_slaughter_operating_pool` CHECK (`operating_pool_available_before` >= 0),
  CONSTRAINT `chk_poultry_slaughter_capital_transfer` CHECK (`capital_basis_transferred` >= 0),
  CONSTRAINT `chk_poultry_slaughter_operating_transfer` CHECK (`embedded_operating_basis_transferred` >= 0),
  CONSTRAINT `chk_poultry_slaughter_processing_cost` CHECK (`processing_operating_cost` >= 0),
  CONSTRAINT `chk_poultry_slaughter_full_cost` CHECK (`full_cost_basis_amount` >= 0),
  CONSTRAINT `chk_poultry_slaughter_capital_pool_conservation` CHECK (`capital_basis_transferred` <= `capital_pool_available_before`),
  CONSTRAINT `chk_poultry_slaughter_operating_pool_conservation` CHECK (`embedded_operating_basis_transferred` <= `operating_pool_available_before`),
  CONSTRAINT `chk_poultry_slaughter_full_cost_conservation` CHECK (`full_cost_basis_amount` = `capital_basis_transferred` + `embedded_operating_basis_transferred` + `processing_operating_cost`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `poultry_slaughter_outputs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poultry_slaughter_outputs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `stock_item_id` int(11) NOT NULL,
  `stock_transaction_id` int(11) DEFAULT NULL,
  `initial_quantity` decimal(12,2) NOT NULL,
  `remaining_quantity` decimal(12,2) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `cost_share_percent` decimal(7,4) NOT NULL,
  `allocated_cost` decimal(14,2) NOT NULL,
  `unit_cost_snapshot` decimal(14,4) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_poultry_slaughter_output_item` (`farm_id`,`batch_id`,`stock_item_id`),
  UNIQUE KEY `uniq_poultry_slaughter_output_stock_tx` (`stock_transaction_id`),
  KEY `idx_poultry_slaughter_output_batch` (`farm_id`,`batch_id`),
  KEY `idx_poultry_slaughter_output_item` (`farm_id`,`stock_item_id`),
  KEY `idx_poultry_slaughter_output_created_by` (`created_by`),
  KEY `fk_pso_batch` (`batch_id`),
  KEY `fk_pso_stock_item` (`stock_item_id`),
  CONSTRAINT `fk_pso_batch` FOREIGN KEY (`batch_id`) REFERENCES `poultry_slaughter_batches` (`id`),
  CONSTRAINT `fk_pso_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pso_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_pso_stock_item` FOREIGN KEY (`stock_item_id`) REFERENCES `stock_items` (`id`),
  CONSTRAINT `fk_pso_stock_transaction` FOREIGN KEY (`stock_transaction_id`) REFERENCES `stock_transactions` (`id`),
  CONSTRAINT `chk_poultry_slaughter_output_initial` CHECK (`initial_quantity` > 0),
  CONSTRAINT `chk_poultry_slaughter_output_remaining` CHECK (`remaining_quantity` >= 0 and `remaining_quantity` <= `initial_quantity`),
  CONSTRAINT `chk_poultry_slaughter_output_cost_share` CHECK (`cost_share_percent` > 0 and `cost_share_percent` <= 100),
  CONSTRAINT `chk_poultry_slaughter_output_allocated_cost` CHECK (`allocated_cost` >= 0),
  CONSTRAINT `chk_poultry_slaughter_output_unit_cost` CHECK (`unit_cost_snapshot` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `poultry_slaughter_sale_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poultry_slaughter_sale_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `output_id` bigint(20) unsigned NOT NULL,
  `stock_transaction_id` int(11) DEFAULT NULL,
  `reversal_stock_transaction_id` int(11) DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `unit_cost_snapshot` decimal(14,4) NOT NULL,
  `total_cost_snapshot` decimal(14,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `reversed_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reversed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pssa_stock_tx` (`stock_transaction_id`),
  UNIQUE KEY `uniq_pssa_reversal_stock_tx` (`reversal_stock_transaction_id`),
  KEY `idx_pssa_sale` (`farm_id`,`sale_id`,`is_active`,`id`),
  KEY `idx_pssa_output` (`farm_id`,`output_id`,`is_active`,`id`),
  KEY `idx_pssa_created_by` (`created_by`),
  KEY `idx_pssa_reversed_by` (`reversed_by`),
  KEY `fk_pssa_sale` (`sale_id`),
  KEY `fk_pssa_output` (`output_id`),
  CONSTRAINT `fk_pssa_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pssa_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_pssa_output` FOREIGN KEY (`output_id`) REFERENCES `poultry_slaughter_outputs` (`id`),
  CONSTRAINT `fk_pssa_reversal_stock_tx` FOREIGN KEY (`reversal_stock_transaction_id`) REFERENCES `stock_transactions` (`id`),
  CONSTRAINT `fk_pssa_reversed_by` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pssa_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales_records` (`id`),
  CONSTRAINT `fk_pssa_stock_tx` FOREIGN KEY (`stock_transaction_id`) REFERENCES `stock_transactions` (`id`),
  CONSTRAINT `chk_pssa_quantity` CHECK (`quantity` > 0),
  CONSTRAINT `chk_pssa_unit_cost` CHECK (`unit_cost_snapshot` >= 0),
  CONSTRAINT `chk_pssa_total_cost` CHECK (`total_cost_snapshot` >= 0),
  CONSTRAINT `chk_pssa_active` CHECK (`is_active` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `production_cycle_phases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_cycle_phases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `phase` varchar(40) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_poultry_phase_cycle_start` (`farm_id`,`cycle_id`,`start_date`),
  KEY `idx_poultry_phase_cycle_date` (`farm_id`,`cycle_id`,`start_date`,`end_date`),
  KEY `idx_poultry_phase_current` (`farm_id`,`cycle_id`,`end_date`),
  KEY `fk_poultry_phase_cycle` (`cycle_id`),
  KEY `fk_poultry_phase_user` (`created_by`),
  CONSTRAINT `fk_poultry_phase_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`),
  CONSTRAINT `fk_poultry_phase_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_poultry_phase_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `production_cycles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_cycles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `cycle_code` varchar(100) NOT NULL,
  `farm_type` enum('poultry','ruminant','general') NOT NULL,
  `production_type` varchar(100) NOT NULL,
  `livestock_type_id` int(11) DEFAULT NULL,
  `status` enum('planned','active','closed','archived') NOT NULL DEFAULT 'planned',
  `start_date` date NOT NULL,
  `expected_end_date` date DEFAULT NULL,
  `close_date` date DEFAULT NULL,
  `opening_headcount` int(11) NOT NULL DEFAULT 0,
  `bird_unit_cost` decimal(14,2) DEFAULT NULL,
  `closing_headcount` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_farm_cycle_code` (`farm_id`,`cycle_code`),
  UNIQUE KEY `uniq_production_cycle_farm_identity` (`farm_id`,`id`),
  KEY `idx_cycles_farm` (`farm_id`),
  KEY `idx_cycle_status` (`farm_type`,`production_type`,`status`),
  KEY `idx_cycle_dates` (`start_date`,`close_date`),
  KEY `fk_cycles_user` (`created_by`),
  KEY `idx_production_cycles_livestock_type` (`farm_id`,`livestock_type_id`),
  CONSTRAINT `fk_cycles_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_cycles_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_production_cycles_livestock_type` FOREIGN KEY (`farm_id`, `livestock_type_id`) REFERENCES `livestock_types` (`farm_id`, `id`),
  CONSTRAINT `chk_production_cycles_livestock_type_scope` CHECK (`livestock_type_id` is null or `farm_type` = 'ruminant' and `production_type` = 'other')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `production_population_baselines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_population_baselines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `baseline_date` date NOT NULL,
  `baseline_quantity` int(10) unsigned NOT NULL,
  `baseline_source` varchar(40) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_population_baseline_cycle` (`farm_id`,`cycle_id`),
  KEY `idx_population_baseline_date` (`farm_id`,`baseline_date`),
  KEY `idx_population_baseline_cycle` (`cycle_id`),
  KEY `idx_population_baseline_user` (`created_by`),
  CONSTRAINT `fk_population_baseline_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`),
  CONSTRAINT `fk_population_baseline_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_population_baseline_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `production_population_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_population_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `movement_date` date NOT NULL,
  `movement_type` varchar(40) NOT NULL,
  `quantity_delta` int(11) NOT NULL,
  `source_type` varchar(40) NOT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `source_version` int(10) unsigned NOT NULL DEFAULT 1,
  `request_token` varchar(64) DEFAULT NULL,
  `reversal_of_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_population_movement_identity` (`farm_id`,`cycle_id`,`id`),
  UNIQUE KEY `uniq_population_movement_request` (`farm_id`,`request_token`),
  UNIQUE KEY `uniq_population_movement_reversal` (`farm_id`,`cycle_id`,`reversal_of_id`),
  UNIQUE KEY `uniq_population_movement_source` (`farm_id`,`source_type`,`source_id`,`source_version`),
  KEY `idx_population_movement_cycle_date` (`farm_id`,`cycle_id`,`movement_date`,`id`),
  KEY `idx_population_movement_source` (`farm_id`,`source_type`,`source_id`),
  KEY `idx_population_movement_type_date` (`farm_id`,`movement_type`,`movement_date`),
  KEY `idx_population_movement_cycle_fk` (`cycle_id`),
  KEY `idx_population_movement_user` (`created_by`),
  CONSTRAINT `fk_population_movement_baseline` FOREIGN KEY (`farm_id`, `cycle_id`) REFERENCES `production_population_baselines` (`farm_id`, `cycle_id`),
  CONSTRAINT `fk_population_movement_reversal` FOREIGN KEY (`farm_id`, `cycle_id`, `reversal_of_id`) REFERENCES `production_population_movements` (`farm_id`, `cycle_id`, `id`),
  CONSTRAINT `fk_population_movement_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `production_population_transfer_legs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_population_transfer_legs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `transfer_id` bigint(20) unsigned NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `direction` varchar(8) NOT NULL,
  `population_movement_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_population_transfer_leg_identity` (`farm_id`,`id`),
  UNIQUE KEY `uniq_population_transfer_leg_direction` (`farm_id`,`transfer_id`,`direction`),
  UNIQUE KEY `uniq_population_transfer_leg_movement` (`farm_id`,`cycle_id`,`population_movement_id`),
  KEY `idx_population_transfer_leg_transfer` (`farm_id`,`transfer_id`),
  KEY `idx_population_transfer_leg_cycle` (`farm_id`,`cycle_id`),
  CONSTRAINT `fk_population_transfer_leg_baseline` FOREIGN KEY (`farm_id`, `cycle_id`) REFERENCES `production_population_baselines` (`farm_id`, `cycle_id`),
  CONSTRAINT `fk_population_transfer_leg_movement` FOREIGN KEY (`farm_id`, `cycle_id`, `population_movement_id`) REFERENCES `production_population_movements` (`farm_id`, `cycle_id`, `id`),
  CONSTRAINT `fk_population_transfer_leg_parent` FOREIGN KEY (`farm_id`, `transfer_id`) REFERENCES `production_population_transfers` (`farm_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `production_population_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_population_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `from_cycle_id` int(11) NOT NULL,
  `to_cycle_id` int(11) NOT NULL,
  `transfer_date` date NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `request_token` varchar(64) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reversed_at` datetime DEFAULT NULL,
  `reversed_by` int(11) DEFAULT NULL,
  `reversal_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_population_transfer_identity` (`farm_id`,`id`),
  UNIQUE KEY `uniq_population_transfer_request` (`farm_id`,`request_token`),
  KEY `idx_population_transfer_from_cycle` (`farm_id`,`from_cycle_id`,`transfer_date`),
  KEY `idx_population_transfer_to_cycle` (`farm_id`,`to_cycle_id`,`transfer_date`),
  KEY `idx_population_transfer_created_by` (`created_by`),
  KEY `idx_population_transfer_reversed_by` (`reversed_by`),
  CONSTRAINT `fk_population_transfer_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_population_transfer_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_population_transfer_from_baseline` FOREIGN KEY (`farm_id`, `from_cycle_id`) REFERENCES `production_population_baselines` (`farm_id`, `cycle_id`),
  CONSTRAINT `fk_population_transfer_reversed_by` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_population_transfer_to_baseline` FOREIGN KEY (`farm_id`, `to_cycle_id`) REFERENCES `production_population_baselines` (`farm_id`, `cycle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `profit_loss_summary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `profit_loss_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `month` char(7) NOT NULL,
  `farm_type` enum('poultry','ruminant','both') NOT NULL DEFAULT 'both',
  `total_sales` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_expenses` decimal(14,2) NOT NULL DEFAULT 0.00,
  `profit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tenant_month_farm` (`farm_id`,`month`,`farm_type`),
  CONSTRAINT `fk_profit_loss_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_platform_role` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ruminant_animal_cycle_memberships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruminant_animal_cycle_memberships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `animal_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `closed_by_exit_event_id` int(11) DEFAULT NULL,
  `pre_exit_end_date` date DEFAULT NULL,
  `opened_by_transfer_id` bigint(20) unsigned DEFAULT NULL,
  `closed_by_transfer_id` bigint(20) unsigned DEFAULT NULL,
  `pre_transfer_end_date` date DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ruminant_animal_cycle_start` (`farm_id`,`animal_id`,`cycle_id`,`start_date`),
  KEY `idx_ruminant_membership_animal_dates` (`farm_id`,`animal_id`,`start_date`,`end_date`),
  KEY `idx_ruminant_membership_cycle_dates` (`farm_id`,`cycle_id`,`start_date`,`end_date`),
  KEY `fk_racm_animal` (`animal_id`),
  KEY `fk_racm_cycle` (`cycle_id`),
  KEY `fk_racm_user` (`created_by`),
  KEY `idx_ruminant_membership_exit_boundary` (`farm_id`,`animal_id`,`closed_by_exit_event_id`),
  KEY `idx_racm_opened_transfer` (`farm_id`,`opened_by_transfer_id`,`animal_id`),
  KEY `idx_racm_closed_transfer` (`farm_id`,`closed_by_transfer_id`,`animal_id`),
  CONSTRAINT `fk_racm_animal` FOREIGN KEY (`animal_id`) REFERENCES `ruminant_animals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_racm_closed_transfer` FOREIGN KEY (`farm_id`, `closed_by_transfer_id`) REFERENCES `production_population_transfers` (`farm_id`, `id`),
  CONSTRAINT `fk_racm_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_racm_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_racm_opened_transfer` FOREIGN KEY (`farm_id`, `opened_by_transfer_id`) REFERENCES `production_population_transfers` (`farm_id`, `id`),
  CONSTRAINT `fk_racm_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ruminant_animal_cycle_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruminant_animal_cycle_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `animal_id` int(11) NOT NULL,
  `population_transfer_id` bigint(20) unsigned NOT NULL,
  `from_membership_id` int(11) NOT NULL,
  `to_membership_id` int(11) NOT NULL,
  `source_previous_end_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reversed_at` datetime DEFAULT NULL,
  `reversed_by` int(11) DEFAULT NULL,
  `reversal_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ruminant_animal_transfer_parent` (`farm_id`,`population_transfer_id`),
  KEY `idx_ruminant_animal_transfer_animal` (`farm_id`,`animal_id`,`created_at`),
  KEY `idx_ruminant_animal_transfer_from_membership` (`farm_id`,`from_membership_id`),
  KEY `idx_ruminant_animal_transfer_to_membership` (`farm_id`,`to_membership_id`),
  KEY `idx_ruminant_animal_transfer_created_by` (`created_by`),
  KEY `idx_ruminant_animal_transfer_reversed_by` (`reversed_by`),
  KEY `fk_ract_animal` (`animal_id`),
  CONSTRAINT `fk_ract_animal` FOREIGN KEY (`animal_id`) REFERENCES `ruminant_animals` (`id`),
  CONSTRAINT `fk_ract_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ract_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_ract_population_transfer` FOREIGN KEY (`farm_id`, `population_transfer_id`) REFERENCES `production_population_transfers` (`farm_id`, `id`),
  CONSTRAINT `fk_ract_reversed_by` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ruminant_animal_exit_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruminant_animal_exit_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `animal_id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `exit_date` date NOT NULL,
  `exit_outcome` varchar(40) NOT NULL,
  `previous_status` varchar(30) NOT NULL,
  `resulting_status` varchar(30) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ruminant_exit_sale_animal` (`farm_id`,`sale_id`,`animal_id`),
  KEY `idx_ruminant_exit_animal_date` (`farm_id`,`animal_id`,`exit_date`),
  KEY `idx_ruminant_exit_sale` (`farm_id`,`sale_id`),
  KEY `fk_rae_animal` (`animal_id`),
  KEY `fk_rae_sale` (`sale_id`),
  KEY `fk_rae_user` (`recorded_by`),
  CONSTRAINT `fk_rae_animal` FOREIGN KEY (`animal_id`) REFERENCES `ruminant_animals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rae_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_rae_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rae_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ruminant_animal_weights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruminant_animal_weights` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `animal_id` int(11) NOT NULL,
  `weight_date` date NOT NULL,
  `weight_kg` decimal(10,2) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_weight_animal_date` (`animal_id`,`weight_date`),
  KEY `idx_weight_farm_date` (`farm_id`,`weight_date`),
  KEY `fk_weight_user` (`recorded_by`),
  CONSTRAINT `fk_weight_animal` FOREIGN KEY (`animal_id`) REFERENCES `ruminant_animals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_weight_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_weight_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ruminant_animals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruminant_animals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `tag_no` varchar(100) NOT NULL,
  `species` enum('cattle','goat','sheep','other') NOT NULL DEFAULT 'other',
  `livestock_type_id` int(11) DEFAULT NULL,
  `tag_type_scope_key` int(11) GENERATED ALWAYS AS (case when `species` = 'cattle' then -1 when `species` = 'goat' then -2 when `species` = 'sheep' then -3 else coalesce(`livestock_type_id`,0) end) STORED,
  `breed` varchar(120) DEFAULT NULL,
  `sex` enum('male','female','unknown') NOT NULL DEFAULT 'unknown',
  `birth_date` date DEFAULT NULL,
  `farm_entry_date` date NOT NULL,
  `source` varchar(150) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','sold','dead','culled','slaughtered','transferred') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ruminant_farm_type_tag` (`farm_id`,`tag_type_scope_key`,`tag_no`),
  KEY `idx_ruminant_farm_status` (`farm_id`,`status`),
  KEY `idx_ruminant_species` (`farm_id`,`species`),
  KEY `fk_ruminant_animals_creator` (`created_by`),
  KEY `idx_ruminant_animals_livestock_type` (`farm_id`,`livestock_type_id`),
  CONSTRAINT `fk_ruminant_animals_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ruminant_animals_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_ruminant_animals_livestock_type` FOREIGN KEY (`farm_id`, `livestock_type_id`) REFERENCES `livestock_types` (`farm_id`, `id`),
  CONSTRAINT `chk_ruminant_animals_livestock_type_scope` CHECK (`livestock_type_id` is null or `species` = 'other')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ruminant_daily_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruminant_daily_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `cycle_id` int(11) DEFAULT NULL,
  `record_date` date NOT NULL,
  `animal_type` varchar(100) NOT NULL,
  `opening_stock` int(11) NOT NULL,
  `mortality` int(11) NOT NULL DEFAULT 0,
  `feed_consumption_kg` decimal(12,2) NOT NULL DEFAULT 0.00,
  `feed_item_id` int(11) DEFAULT NULL,
  `feed_consumption_unit` varchar(50) NOT NULL DEFAULT 'kg',
  `water_consumption_liters` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_details` text DEFAULT NULL,
  `tag_no` varchar(100) DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `reproduction_details` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ruminant_cycle_animal` (`record_date`,`animal_type`,`cycle_id`),
  KEY `idx_ruminant_farm` (`farm_id`),
  KEY `idx_ruminant_cycle_date` (`cycle_id`,`record_date`),
  KEY `fk_ruminant_user` (`user_id`),
  KEY `idx_ruminant_daily_feed_item` (`feed_item_id`),
  CONSTRAINT `fk_ruminant_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ruminant_daily_feed_item` FOREIGN KEY (`feed_item_id`) REFERENCES `stock_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ruminant_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_ruminant_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ruminant_expense_animal_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruminant_expense_animal_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `expense_id` int(11) NOT NULL,
  `animal_id` int(11) NOT NULL,
  `allocation_method` enum('equal','custom') NOT NULL DEFAULT 'equal',
  `allocation_percent` decimal(7,4) NOT NULL,
  `allocated_amount` decimal(14,2) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ruminant_expense_animal` (`expense_id`,`animal_id`),
  KEY `idx_reaa_farm_animal` (`farm_id`,`animal_id`),
  KEY `idx_reaa_farm_expense` (`farm_id`,`expense_id`),
  KEY `fk_reaa_animal` (`animal_id`),
  KEY `fk_reaa_user` (`created_by`),
  CONSTRAINT `fk_reaa_animal` FOREIGN KEY (`animal_id`) REFERENCES `ruminant_animals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reaa_expense` FOREIGN KEY (`expense_id`) REFERENCES `farm_expenses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reaa_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_reaa_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ruminant_health_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruminant_health_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `animal_id` int(11) NOT NULL,
  `event_date` date NOT NULL,
  `event_type` enum('vaccination','treatment','diagnosis','vet_visit','deworming','other') NOT NULL DEFAULT 'other',
  `description` text DEFAULT NULL,
  `medicine` varchar(150) DEFAULT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `withdrawal_until` date DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_health_animal_date` (`animal_id`,`event_date`),
  KEY `idx_health_farm_date` (`farm_id`,`event_date`),
  KEY `fk_health_user` (`recorded_by`),
  CONSTRAINT `fk_health_animal` FOREIGN KEY (`animal_id`) REFERENCES `ruminant_animals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_health_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_health_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ruminant_participation_corrections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruminant_participation_corrections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `animal_id` int(11) NOT NULL,
  `membership_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `old_farm_entry_date` date NOT NULL,
  `new_farm_entry_date` date NOT NULL,
  `old_membership_start_date` date NOT NULL,
  `new_membership_start_date` date NOT NULL,
  `reason` varchar(500) NOT NULL,
  `request_token` varchar(64) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rpc_request` (`farm_id`,`request_token`),
  KEY `idx_rpc_animal` (`farm_id`,`animal_id`,`created_at`,`id`),
  KEY `idx_rpc_membership` (`farm_id`,`membership_id`,`created_at`,`id`),
  KEY `idx_rpc_cycle` (`farm_id`,`cycle_id`,`created_at`,`id`),
  KEY `fk_rpc_animal` (`animal_id`),
  KEY `fk_rpc_membership` (`membership_id`),
  KEY `fk_rpc_cycle` (`cycle_id`),
  KEY `fk_rpc_created_by` (`created_by`),
  CONSTRAINT `fk_rpc_animal` FOREIGN KEY (`animal_id`) REFERENCES `ruminant_animals` (`id`),
  CONSTRAINT `fk_rpc_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rpc_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`),
  CONSTRAINT `fk_rpc_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_rpc_membership` FOREIGN KEY (`membership_id`) REFERENCES `ruminant_animal_cycle_memberships` (`id`),
  CONSTRAINT `chk_rpc_entry_before_participation` CHECK (`new_farm_entry_date` <= `new_membership_start_date`),
  CONSTRAINT `chk_rpc_direction_coherent` CHECK (`new_farm_entry_date` <= `old_farm_entry_date` and `new_membership_start_date` <= `old_membership_start_date` or `new_farm_entry_date` >= `old_farm_entry_date` and `new_membership_start_date` >= `old_membership_start_date`),
  CONSTRAINT `chk_rpc_has_change` CHECK (`new_farm_entry_date` <> `old_farm_entry_date` or `new_membership_start_date` <> `old_membership_start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ruminant_sale_animal_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruminant_sale_animal_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `animal_id` int(11) NOT NULL,
  `allocation_method` enum('equal','custom') NOT NULL DEFAULT 'equal',
  `allocation_percent` decimal(7,4) NOT NULL,
  `allocated_amount` decimal(14,2) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ruminant_sale_animal` (`sale_id`,`animal_id`),
  KEY `idx_rsaa_farm_animal` (`farm_id`,`animal_id`),
  KEY `idx_rsaa_farm_sale` (`farm_id`,`sale_id`),
  KEY `fk_rsaa_animal` (`animal_id`),
  KEY `fk_rsaa_user` (`created_by`),
  CONSTRAINT `fk_rsaa_animal` FOREIGN KEY (`animal_id`) REFERENCES `ruminant_animals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rsaa_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_rsaa_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rsaa_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ruminant_slaughter_batch_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruminant_slaughter_batch_expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `request_token` varchar(64) NOT NULL,
  `request_fingerprint` char(64) NOT NULL,
  `expense_id` int(11) NOT NULL,
  `expense_revision_id` bigint(20) unsigned NOT NULL,
  `expense_revision_no` int(10) unsigned NOT NULL,
  `expense_causal_fingerprint` char(64) NOT NULL,
  `amount_snapshot` decimal(14,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rsbe_request` (`farm_id`,`request_token`),
  UNIQUE KEY `uniq_rsbe_expense_revision` (`farm_id`,`batch_id`,`expense_revision_id`),
  KEY `idx_rsbe_batch` (`farm_id`,`batch_id`,`id`),
  KEY `idx_rsbe_expense` (`farm_id`,`expense_id`,`expense_revision_no`),
  KEY `idx_rsbe_expense_revision` (`expense_revision_id`),
  KEY `fk_rsbe_batch` (`batch_id`),
  CONSTRAINT `fk_rsbe_batch` FOREIGN KEY (`batch_id`) REFERENCES `ruminant_slaughter_batches` (`id`),
  CONSTRAINT `fk_rsbe_expense_revision` FOREIGN KEY (`expense_revision_id`) REFERENCES `farm_expense_revisions` (`id`),
  CONSTRAINT `fk_rsbe_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `chk_rsbe_revision` CHECK (`expense_revision_no` > 0),
  CONSTRAINT `chk_rsbe_amount` CHECK (`amount_snapshot` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ruminant_slaughter_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruminant_slaughter_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `animal_id` int(11) NOT NULL,
  `exit_event_id` bigint(20) unsigned NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `batch_code` varchar(80) NOT NULL,
  `slaughter_date` date NOT NULL,
  `status` enum('open','completed') NOT NULL DEFAULT 'open',
  `notes` varchar(255) DEFAULT NULL,
  `cost_basis_amount` decimal(14,2) DEFAULT NULL,
  `cost_basis_purchase` decimal(14,2) DEFAULT NULL,
  `cost_basis_direct_expense` decimal(14,2) DEFAULT NULL,
  `cost_basis_shared` decimal(14,2) DEFAULT NULL,
  `cost_basis_method` varchar(160) DEFAULT NULL,
  `cost_basis_snapshot_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ruminant_slaughter_exit` (`farm_id`,`exit_event_id`),
  UNIQUE KEY `uniq_ruminant_slaughter_batch_code` (`farm_id`,`batch_code`),
  KEY `idx_ruminant_slaughter_animal` (`farm_id`,`animal_id`,`slaughter_date`),
  KEY `idx_ruminant_slaughter_cycle` (`farm_id`,`cycle_id`,`slaughter_date`),
  KEY `idx_ruminant_slaughter_created_by` (`created_by`),
  KEY `fk_rsb_animal` (`animal_id`),
  KEY `fk_rsb_exit` (`exit_event_id`),
  KEY `fk_rsb_cycle` (`cycle_id`),
  CONSTRAINT `fk_rsb_animal` FOREIGN KEY (`animal_id`) REFERENCES `ruminant_animals` (`id`),
  CONSTRAINT `fk_rsb_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rsb_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`),
  CONSTRAINT `fk_rsb_exit` FOREIGN KEY (`exit_event_id`) REFERENCES `ruminant_animal_exit_events` (`id`),
  CONSTRAINT `fk_rsb_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ruminant_slaughter_outputs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruminant_slaughter_outputs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `stock_item_id` int(11) NOT NULL,
  `stock_transaction_id` int(11) DEFAULT NULL,
  `initial_quantity` decimal(12,2) NOT NULL,
  `remaining_quantity` decimal(12,2) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `cost_share_percent` decimal(7,4) DEFAULT NULL,
  `allocated_cost` decimal(14,2) DEFAULT NULL,
  `unit_cost_snapshot` decimal(14,4) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ruminant_slaughter_output_item` (`farm_id`,`batch_id`,`stock_item_id`),
  UNIQUE KEY `uniq_ruminant_slaughter_output_stock_tx` (`stock_transaction_id`),
  KEY `idx_ruminant_slaughter_output_batch` (`farm_id`,`batch_id`),
  KEY `idx_ruminant_slaughter_output_item` (`farm_id`,`stock_item_id`),
  KEY `idx_ruminant_slaughter_output_created_by` (`created_by`),
  KEY `fk_rso_batch` (`batch_id`),
  KEY `fk_rso_stock_item` (`stock_item_id`),
  CONSTRAINT `fk_rso_batch` FOREIGN KEY (`batch_id`) REFERENCES `ruminant_slaughter_batches` (`id`),
  CONSTRAINT `fk_rso_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rso_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_rso_stock_item` FOREIGN KEY (`stock_item_id`) REFERENCES `stock_items` (`id`),
  CONSTRAINT `fk_rso_stock_transaction` FOREIGN KEY (`stock_transaction_id`) REFERENCES `stock_transactions` (`id`),
  CONSTRAINT `chk_ruminant_slaughter_output_initial` CHECK (`initial_quantity` > 0),
  CONSTRAINT `chk_ruminant_slaughter_output_remaining` CHECK (`remaining_quantity` >= 0 and `remaining_quantity` <= `initial_quantity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ruminant_slaughter_sale_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruminant_slaughter_sale_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `output_id` bigint(20) unsigned NOT NULL,
  `stock_transaction_id` int(11) DEFAULT NULL,
  `reversal_stock_transaction_id` int(11) DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `unit_cost_snapshot` decimal(14,4) NOT NULL,
  `total_cost_snapshot` decimal(14,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `reversed_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reversed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rssa_stock_tx` (`stock_transaction_id`),
  UNIQUE KEY `uniq_rssa_reversal_stock_tx` (`reversal_stock_transaction_id`),
  KEY `idx_rssa_sale` (`farm_id`,`sale_id`,`is_active`,`id`),
  KEY `idx_rssa_output` (`farm_id`,`output_id`,`is_active`,`id`),
  KEY `idx_rssa_created_by` (`created_by`),
  KEY `idx_rssa_reversed_by` (`reversed_by`),
  KEY `fk_rssa_sale` (`sale_id`),
  KEY `fk_rssa_output` (`output_id`),
  CONSTRAINT `fk_rssa_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rssa_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_rssa_output` FOREIGN KEY (`output_id`) REFERENCES `ruminant_slaughter_outputs` (`id`),
  CONSTRAINT `fk_rssa_reversal_stock_tx` FOREIGN KEY (`reversal_stock_transaction_id`) REFERENCES `stock_transactions` (`id`),
  CONSTRAINT `fk_rssa_reversed_by` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rssa_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales_records` (`id`),
  CONSTRAINT `fk_rssa_stock_tx` FOREIGN KEY (`stock_transaction_id`) REFERENCES `stock_transactions` (`id`),
  CONSTRAINT `chk_rssa_quantity` CHECK (`quantity` > 0),
  CONSTRAINT `chk_rssa_unit_cost` CHECK (`unit_cost_snapshot` >= 0),
  CONSTRAINT `chk_rssa_total_cost` CHECK (`total_cost_snapshot` >= 0),
  CONSTRAINT `chk_rssa_active` CHECK (`is_active` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sale_population_effects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_population_effects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `population_quantity` int(10) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sale_population_effect_cycle` (`farm_id`,`sale_id`,`cycle_id`),
  KEY `idx_sale_population_effect_sale` (`farm_id`,`sale_id`,`id`),
  KEY `idx_sale_population_effect_cycle` (`farm_id`,`cycle_id`,`id`),
  KEY `idx_sale_population_effect_sale_fk` (`sale_id`),
  KEY `idx_sale_population_effect_cycle_fk` (`cycle_id`),
  KEY `idx_sale_population_effect_created_by` (`created_by`),
  KEY `idx_sale_population_effect_updated_by` (`updated_by`),
  KEY `idx_sale_population_effect_active` (`farm_id`,`sale_id`,`is_active`),
  CONSTRAINT `fk_sale_population_effect_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sale_population_effect_cycle` FOREIGN KEY (`farm_id`, `cycle_id`) REFERENCES `production_cycles` (`farm_id`, `id`),
  CONSTRAINT `fk_sale_population_effect_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_sale_population_effect_sale` FOREIGN KEY (`farm_id`, `sale_id`) REFERENCES `sales_records` (`farm_id`, `id`),
  CONSTRAINT `fk_sale_population_effect_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_sale_population_effect_quantity` CHECK (`population_quantity` > 0),
  CONSTRAINT `chk_sale_population_effect_active` CHECK (`is_active` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_allocation_revision_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_allocation_revision_rows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `revision_id` bigint(20) unsigned NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `allocated_amount` decimal(14,2) NOT NULL,
  `allocation_percent` decimal(7,4) NOT NULL,
  `allocation_basis` varchar(50) NOT NULL DEFAULT 'manual_shared_revenue',
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sales_allocation_revision_cycle` (`revision_id`,`cycle_id`),
  KEY `idx_sales_allocation_revision_row_cycle` (`cycle_id`),
  CONSTRAINT `fk_sales_allocation_revision_row` FOREIGN KEY (`revision_id`) REFERENCES `sales_allocation_revisions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_allocation_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_allocation_revisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `revision_no` int(10) unsigned NOT NULL,
  `revision_action` varchar(32) NOT NULL,
  `revision_reason` varchar(500) DEFAULT NULL,
  `previous_revision_id` bigint(20) unsigned DEFAULT NULL,
  `parent_amount` decimal(14,2) NOT NULL,
  `allocated_amount` decimal(14,2) NOT NULL,
  `unallocated_amount` decimal(14,2) NOT NULL,
  `causal_fingerprint` char(64) NOT NULL,
  `causal_manifest_json` longtext NOT NULL,
  `state_fingerprint` char(64) NOT NULL,
  `state_manifest_json` longtext NOT NULL,
  `changed_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sales_allocation_revision` (`farm_id`,`sale_id`,`revision_no`),
  KEY `idx_sales_allocation_revision_source` (`farm_id`,`sale_id`,`id`),
  KEY `idx_sales_allocation_revision_previous` (`previous_revision_id`),
  KEY `fk_sales_allocation_revision_user` (`changed_by_user_id`),
  CONSTRAINT `fk_sales_allocation_revision_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sales_allocation_revision_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `allocation_percent` decimal(7,4) NOT NULL,
  `allocated_quantity` decimal(14,4) DEFAULT NULL,
  `allocation_unit` varchar(30) DEFAULT NULL,
  `allocated_amount` decimal(14,2) NOT NULL,
  `allocation_basis` varchar(50) NOT NULL DEFAULT 'manual',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sales_allocation` (`sale_id`,`cycle_id`),
  KEY `idx_sales_alloc_farm_cycle` (`farm_id`,`cycle_id`),
  KEY `fk_sales_alloc_cycle` (`cycle_id`),
  KEY `fk_sales_alloc_user` (`created_by`),
  KEY `idx_sales_alloc_sale_qty` (`farm_id`,`sale_id`,`allocated_quantity`),
  CONSTRAINT `fk_sales_alloc_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sales_alloc_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_sales_alloc_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sales_alloc_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_reference` varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `farm_id` int(11) NOT NULL,
  `sale_date` date NOT NULL,
  `farm_type` enum('poultry','ruminant','general') NOT NULL,
  `production_type` varchar(100) DEFAULT NULL,
  `attribution_scope` enum('cycle','production_type','farm') NOT NULL DEFAULT 'farm',
  `cycle_id` int(11) DEFAULT NULL,
  `product_type` varchar(150) NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit_of_measure` varchar(30) DEFAULT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `payment_received` decimal(14,2) DEFAULT NULL,
  `total_amount` decimal(14,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `customer_name` varchar(150) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sales_record_farm_identity` (`farm_id`,`id`),
  UNIQUE KEY `uniq_sale_public_reference` (`public_reference`),
  KEY `idx_sales_farm` (`farm_id`),
  KEY `idx_sale_month` (`sale_date`),
  KEY `idx_sales_cycle_date` (`cycle_id`,`sale_date`),
  KEY `fk_sales_user` (`user_id`),
  KEY `idx_sales_attribution` (`farm_id`,`farm_type`,`production_type`,`cycle_id`,`sale_date`),
  KEY `idx_sales_uom` (`farm_id`,`unit_of_measure`),
  CONSTRAINT `fk_sales_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sales_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_sales_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_schema_migration_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `batch_code` varchar(100) DEFAULT NULL,
  `item_description` varchar(150) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(14,2) NOT NULL DEFAULT 0.00,
  `supplier_name` varchar(150) DEFAULT NULL,
  `received_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_batches_farm` (`farm_id`),
  KEY `idx_batch_cycle_date` (`cycle_id`,`received_date`),
  KEY `fk_batches_user` (`created_by`),
  CONSTRAINT `fk_batches_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`),
  CONSTRAINT `fk_batches_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_batches_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_consumption_allocation_revision_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_consumption_allocation_revision_rows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `revision_id` bigint(20) unsigned NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `allocated_amount` decimal(14,2) NOT NULL,
  `allocation_percent` decimal(7,4) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_stock_consumption_allocation_revision_cycle` (`revision_id`,`cycle_id`),
  KEY `idx_stock_consumption_allocation_revision_row_cycle` (`cycle_id`),
  CONSTRAINT `fk_stock_consumption_allocation_revision_row` FOREIGN KEY (`revision_id`) REFERENCES `stock_consumption_allocation_revisions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_consumption_allocation_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_consumption_allocation_revisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `stock_transaction_id` int(11) NOT NULL,
  `revision_no` int(10) unsigned NOT NULL,
  `revision_action` varchar(32) NOT NULL,
  `revision_reason` varchar(500) DEFAULT NULL,
  `previous_revision_id` bigint(20) unsigned DEFAULT NULL,
  `parent_amount` decimal(14,2) NOT NULL,
  `allocated_amount` decimal(14,2) NOT NULL,
  `unallocated_amount` decimal(14,2) NOT NULL,
  `causal_fingerprint` char(64) NOT NULL,
  `causal_manifest_json` longtext NOT NULL,
  `state_fingerprint` char(64) NOT NULL,
  `state_manifest_json` longtext NOT NULL,
  `changed_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_stock_consumption_allocation_revision` (`farm_id`,`stock_transaction_id`,`revision_no`),
  KEY `idx_stock_consumption_allocation_revision_source` (`farm_id`,`stock_transaction_id`,`id`),
  KEY `idx_stock_consumption_allocation_revision_previous` (`previous_revision_id`),
  KEY `fk_stock_consumption_allocation_revision_user` (`changed_by_user_id`),
  CONSTRAINT `fk_stock_consumption_allocation_revision_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_consumption_allocation_revision_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_consumption_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_consumption_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `stock_transaction_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `allocated_amount` decimal(14,2) NOT NULL,
  `allocation_percent` decimal(7,4) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `allocation_revision_no` int(10) unsigned NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_stock_consumption_allocation` (`farm_id`,`stock_transaction_id`,`cycle_id`),
  KEY `idx_stock_consumption_allocation_source` (`farm_id`,`stock_transaction_id`),
  KEY `idx_stock_consumption_allocation_cycle` (`farm_id`,`cycle_id`),
  KEY `fk_stock_consumption_allocation_cycle` (`cycle_id`),
  KEY `fk_stock_consumption_allocation_created_by` (`created_by`),
  KEY `fk_stock_consumption_allocation_updated_by` (`updated_by`),
  CONSTRAINT `fk_stock_consumption_allocation_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_stock_consumption_allocation_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`),
  CONSTRAINT `fk_stock_consumption_allocation_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_consumption_allocation_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `category_id` int(11) NOT NULL,
  `current_stock` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_cost` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `min_stock_level` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(50) NOT NULL,
  `farm_type` enum('poultry','ruminant','both') NOT NULL DEFAULT 'both',
  `feed_category` enum('general','layer','broiler','ruminant') NOT NULL DEFAULT 'general',
  `default_production_type` varchar(50) NOT NULL DEFAULT 'shared',
  `financial_classification` varchar(50) NOT NULL DEFAULT 'other_stock',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stock_items_farm` (`farm_id`),
  KEY `fk_stock_items_category` (`category_id`),
  KEY `idx_stock_items_financial_classification` (`farm_id`,`financial_classification`,`is_active`),
  KEY `idx_stock_items_default_production` (`farm_id`,`farm_type`,`default_production_type`,`is_active`),
  CONSTRAINT `fk_stock_items_category` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`),
  CONSTRAINT `fk_stock_items_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_receipt_cost_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_receipt_cost_adjustments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `stock_transaction_id` int(11) NOT NULL,
  `amount_delta` decimal(14,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `source_type` varchar(80) NOT NULL,
  `source_id` bigint(20) unsigned NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_stock_receipt_cost_adjustment_source` (`farm_id`,`source_type`,`source_id`),
  KEY `idx_stock_receipt_cost_adjustment_tx` (`farm_id`,`stock_transaction_id`,`id`),
  KEY `fk_stock_receipt_cost_adjustment_tx` (`stock_transaction_id`),
  KEY `fk_stock_receipt_cost_adjustment_user` (`created_by`),
  CONSTRAINT `fk_stock_receipt_cost_adjustment_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_stock_receipt_cost_adjustment_tx` FOREIGN KEY (`stock_transaction_id`) REFERENCES `stock_transactions` (`id`),
  CONSTRAINT `fk_stock_receipt_cost_adjustment_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_reference` varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `farm_id` int(11) NOT NULL,
  `cycle_id` int(11) DEFAULT NULL,
  `stock_item_id` int(11) NOT NULL,
  `transaction_type` enum('received','used') NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit_cost` decimal(14,4) DEFAULT NULL,
  `total_cost` decimal(14,2) DEFAULT NULL,
  `previous_stock` decimal(12,2) NOT NULL,
  `new_stock` decimal(12,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `remarks` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `farm_type` enum('poultry','ruminant','both') NOT NULL,
  `production_type` varchar(100) DEFAULT NULL,
  `financial_classification` varchar(50) NOT NULL DEFAULT 'other_stock',
  `attribution_scope` enum('cycle','production_type','farm') NOT NULL DEFAULT 'farm',
  `source_type` varchar(50) DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `is_reversed` tinyint(1) NOT NULL DEFAULT 0,
  `reversal_of_id` int(11) DEFAULT NULL,
  `reversed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_stock_transaction_public_reference` (`public_reference`),
  KEY `idx_transactions_farm` (`farm_id`),
  KEY `idx_stock_transaction_item_date` (`stock_item_id`,`transaction_date`),
  KEY `fk_transactions_user` (`user_id`),
  KEY `idx_stock_tx_cycle_date` (`cycle_id`,`transaction_date`),
  KEY `idx_stock_tx_source` (`source_type`,`source_id`),
  KEY `idx_stock_tx_reversal` (`reversal_of_id`),
  KEY `idx_stock_tx_active_source` (`farm_id`,`source_type`,`source_id`,`transaction_type`,`is_reversed`),
  KEY `idx_stock_tx_item_event` (`farm_id`,`stock_item_id`,`created_at`,`id`),
  KEY `idx_stock_tx_item_effective` (`farm_id`,`stock_item_id`,`transaction_date`,`created_at`,`id`),
  KEY `idx_stock_tx_attribution` (`farm_id`,`farm_type`,`production_type`,`cycle_id`,`transaction_date`),
  KEY `idx_stock_tx_financial_classification` (`farm_id`,`financial_classification`,`transaction_date`),
  CONSTRAINT `fk_stock_tx_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `production_cycles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_transactions_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_transactions_item` FOREIGN KEY (`stock_item_id`) REFERENCES `stock_items` (`id`),
  CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscription_renewal_reminder_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_renewal_reminder_deliveries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `subscription_ends_at` datetime NOT NULL,
  `days_left` int(11) NOT NULL,
  `recipient` varchar(254) NOT NULL,
  `sent_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_subscription_reminder_delivery` (`farm_id`,`subscription_ends_at`,`days_left`),
  KEY `idx_subscription_reminder_farm` (`farm_id`),
  CONSTRAINT `fk_subscription_reminder_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `plan_code` varchar(50) NOT NULL,
  `status` enum('trial','active','past_due','suspended','cancelled') NOT NULL,
  `billing_interval` enum('monthly','annual') NOT NULL DEFAULT 'monthly',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `provider` varchar(50) DEFAULT NULL,
  `provider_subscription_id` varchar(150) DEFAULT NULL,
  `current_period_ends_at` datetime DEFAULT NULL,
  `subscription_starts_at` datetime DEFAULT NULL,
  `subscription_ends_at` datetime DEFAULT NULL,
  `modules_snapshot` text DEFAULT NULL,
  `seat_addons_snapshot` text DEFAULT NULL,
  `change_reason` varchar(80) DEFAULT NULL,
  `recorded_by_user_id` int(11) DEFAULT NULL,
  `snapshot_hash` char(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_subscription_farm_status` (`farm_id`,`status`),
  KEY `idx_subscription_farm_history` (`farm_id`,`id`),
  CONSTRAINT `fk_subscription_farm_restrict` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `trial_onboarding_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trial_onboarding_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_reference` char(32) NOT NULL,
  `status` enum('pending_review','approved','provisioning','provisioned','activated','rejected','cancelled') NOT NULL DEFAULT 'pending_review',
  `approval_mode` enum('auto','manual') DEFAULT NULL,
  `farm_name` varchar(150) NOT NULL,
  `requested_workspace_id` varchar(100) DEFAULT NULL,
  `admin_full_name` varchar(150) NOT NULL,
  `admin_username` varchar(100) DEFAULT NULL,
  `admin_email` varchar(255) NOT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `requested_modules_snapshot` text DEFAULT NULL,
  `approved_plan_code` varchar(50) DEFAULT NULL,
  `approved_modules_snapshot` text DEFAULT NULL,
  `approved_role_limits_snapshot` text DEFAULT NULL,
  `approved_trial_days` smallint(5) unsigned DEFAULT NULL,
  `review_reason_code` varchar(80) DEFAULT NULL,
  `rejection_reason_code` varchar(80) DEFAULT NULL,
  `source_fingerprint` char(64) DEFAULT NULL,
  `farm_id` int(11) DEFAULT NULL,
  `farm_admin_user_id` int(11) DEFAULT NULL,
  `approved_by_user_id` int(11) DEFAULT NULL,
  `rejected_by_user_id` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `provisioning_started_at` datetime DEFAULT NULL,
  `provisioned_at` datetime DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_trial_onboarding_request_reference` (`request_reference`),
  UNIQUE KEY `uniq_trial_onboarding_request_farm` (`farm_id`),
  UNIQUE KEY `uniq_trial_onboarding_request_admin` (`farm_admin_user_id`),
  KEY `idx_trial_onboarding_status_created` (`status`,`created_at`,`id`),
  KEY `idx_trial_onboarding_admin_email_status` (`admin_email`,`status`),
  KEY `idx_trial_onboarding_workspace_status` (`requested_workspace_id`,`status`),
  KEY `idx_trial_onboarding_fingerprint_status` (`source_fingerprint`,`status`),
  KEY `fk_trial_onboarding_approved_by` (`approved_by_user_id`),
  KEY `idx_trial_onboarding_rejected_by` (`rejected_by_user_id`),
  CONSTRAINT `fk_trial_onboarding_approved_by` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trial_onboarding_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trial_onboarding_farm_admin` FOREIGN KEY (`farm_admin_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trial_onboarding_rejected_by` FOREIGN KEY (`rejected_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `fk_user_roles_role` (`role_id`),
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `credential_state` enum('active','pending_activation') NOT NULL DEFAULT 'active',
  `password` varchar(255) NOT NULL,
  `user_type` enum('platform_owner','farm_admin','poultry_manager','ruminant_manager','sales_rep','viewer') NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_farm_username` (`farm_id`,`username`),
  KEY `idx_users_farm` (`farm_id`),
  CONSTRAINT `fk_users_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `v2_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `v2_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` varchar(100) DEFAULT NULL,
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details_json`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_farm_created` (`farm_id`,`created_at`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_user_created` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;
