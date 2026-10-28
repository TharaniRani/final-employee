CREATE TABLE IF NOT EXISTS `admin_employee_change_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `submission_id` bigint unsigned DEFAULT NULL,
  `field_name` varchar(100) NOT NULL,
  `old_value` longtext DEFAULT NULL,
  `new_value` longtext DEFAULT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_by_role` varchar(40) NOT NULL,
  `changed_by_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_employee_history_employee_created` (`employee_id`, `created_at`),
  KEY `idx_admin_employee_history_submission` (`submission_id`),
  KEY `idx_admin_employee_history_actor` (`changed_by`, `changed_by_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
