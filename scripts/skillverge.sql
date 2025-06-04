-- SkillVerge Complete Database Schema
-- Version: 2.0 (Web Platform Only)
-- Last Updated: 2024

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Database: skillverge
CREATE DATABASE IF NOT EXISTS `skillverge` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `skillverge`;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `user_type` enum('candidate','company','expert','admin') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_user_type` (`user_type`),
  KEY `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `candidates`
-- --------------------------------------------------------

CREATE TABLE `candidates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `skills` text,
  `experience_level` enum('entry','mid','senior','executive') DEFAULT 'entry',
  `preferred_role` varchar(255) DEFAULT NULL,
  `preferred_domain` varchar(255) DEFAULT NULL,
  `resume_path` varchar(500) DEFAULT NULL,
  `portfolio_url` varchar(500) DEFAULT NULL,
  `linkedin_url` varchar(500) DEFAULT NULL,
  `github_url` varchar(500) DEFAULT NULL,
  `is_premium` tinyint(1) DEFAULT 0,
  `premium_expires_at` timestamp NULL DEFAULT NULL,
  `trial_interviews_used` int(11) DEFAULT 0,
  `total_interviews_taken` int(11) DEFAULT 0,
  `average_score` decimal(3,1) DEFAULT 0.0,
  `profile_completion` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_candidates_premium` (`is_premium`),
  KEY `idx_candidates_domain` (`preferred_domain`),
  CONSTRAINT `fk_candidates_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `companies`
-- --------------------------------------------------------

CREATE TABLE `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `industry` varchar(255) DEFAULT NULL,
  `company_size` enum('1-10','11-50','51-200','201-500','501-1000','1000+') DEFAULT '1-10',
  `website` varchar(500) DEFAULT NULL,
  `description` text,
  `logo_path` varchar(500) DEFAULT NULL,
  `address` text,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'India',
  `postal_code` varchar(20) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_document` varchar(500) DEFAULT NULL,
  `total_job_postings` int(11) DEFAULT 0,
  `active_job_postings` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_companies_verified` (`is_verified`),
  KEY `idx_companies_industry` (`industry`),
  CONSTRAINT `fk_companies_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `experts`
-- --------------------------------------------------------

CREATE TABLE `experts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `specialization` varchar(255) NOT NULL,
  `experience_years` int(11) DEFAULT 0,
  `current_company` varchar(255) DEFAULT NULL,
  `current_position` varchar(255) DEFAULT NULL,
  `bio` text,
  `hourly_rate` decimal(10,2) DEFAULT 0.00,
  `currency` enum('INR','USD','EUR','GBP') DEFAULT 'INR',
  `availability_status` enum('available','busy','offline') DEFAULT 'available',
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_sessions` int(11) DEFAULT 0,
  `total_earnings` decimal(12,2) DEFAULT 0.00,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_document` varchar(500) DEFAULT NULL,
  `linkedin_url` varchar(500) DEFAULT NULL,
  `certifications` text,
  `languages` varchar(255) DEFAULT 'English',
  `timezone` varchar(100) DEFAULT 'Asia/Kolkata',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_experts_specialization` (`specialization`),
  KEY `idx_experts_verified` (`is_verified`),
  KEY `idx_experts_availability` (`availability_status`),
  CONSTRAINT `fk_experts_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `interview_domains`
-- --------------------------------------------------------

CREATE TABLE `interview_domains` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `icon` varchar(100) DEFAULT NULL,
  `color` varchar(7) DEFAULT '#007bff',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `total_questions` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_domains_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `ai_interviews`
-- --------------------------------------------------------

CREATE TABLE `ai_interviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `candidate_id` int(11) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `difficulty_level` enum('beginner','intermediate','advanced') NOT NULL,
  `duration_minutes` int(11) DEFAULT 30,
  `questions` text NOT NULL,
  `responses` text,
  `audio_files` text,
  `overall_score` decimal(3,1) DEFAULT 0.0,
  `technical_score` decimal(3,1) DEFAULT 0.0,
  `communication_score` decimal(3,1) DEFAULT 0.0,
  `confidence_score` decimal(3,1) DEFAULT 0.0,
  `ai_feedback` text,
  `strengths` text,
  `weaknesses` text,
  `recommendations` text,
  `status` enum('in_progress','completed','abandoned') DEFAULT 'in_progress',
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `candidate_id` (`candidate_id`),
  KEY `idx_ai_interviews_domain` (`domain`),
  KEY `idx_ai_interviews_status` (`status`),
  KEY `idx_ai_interviews_completed` (`completed_at`),
  CONSTRAINT `fk_ai_interviews_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `expert_interviews`
-- --------------------------------------------------------

CREATE TABLE `expert_interviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `candidate_id` int(11) NOT NULL,
  `expert_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `scheduled_at` timestamp NOT NULL,
  `duration_minutes` int(11) DEFAULT 60,
  `meeting_url` varchar(500) DEFAULT NULL,
  `meeting_id` varchar(255) DEFAULT NULL,
  `meeting_password` varchar(255) DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled','no_show') DEFAULT 'scheduled',
  `expert_notes` text,
  `candidate_feedback` text,
  `expert_rating` int(11) DEFAULT NULL,
  `candidate_rating` int(11) DEFAULT NULL,
  `recording_url` varchar(500) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT 0.00,
  `currency` enum('INR','USD','EUR','GBP') DEFAULT 'INR',
  `payment_status` enum('pending','paid','refunded') DEFAULT 'pending',
  `started_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `candidate_id` (`candidate_id`),
  KEY `expert_id` (`expert_id`),
  KEY `idx_expert_interviews_scheduled` (`scheduled_at`),
  KEY `idx_expert_interviews_status` (`status`),
  CONSTRAINT `fk_expert_interviews_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_expert_interviews_expert` FOREIGN KEY (`expert_id`) REFERENCES `experts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `job_postings`
-- --------------------------------------------------------

CREATE TABLE `job_postings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `requirements` text,
  `location` varchar(255) NOT NULL,
  `job_type` enum('full-time','part-time','contract','internship') NOT NULL,
  `experience_level` enum('entry','mid','senior','executive') NOT NULL,
  `salary_min` decimal(12,2) DEFAULT NULL,
  `salary_max` decimal(12,2) DEFAULT NULL,
  `currency` enum('INR','USD','EUR','GBP') DEFAULT 'INR',
  `skills_required` text,
  `benefits` text,
  `application_deadline` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `views_count` int(11) DEFAULT 0,
  `applications_count` int(11) DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `idx_job_postings_active` (`is_active`),
  KEY `idx_job_postings_location` (`location`),
  KEY `idx_job_postings_type` (`job_type`),
  KEY `idx_job_postings_level` (`experience_level`),
  CONSTRAINT `fk_job_postings_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `job_applications`
-- --------------------------------------------------------

CREATE TABLE `job_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `cover_letter` text,
  `resume_path` varchar(500) DEFAULT NULL,
  `status` enum('applied','reviewed','interview','hired','rejected') DEFAULT 'applied',
  `notes` text,
  `interview_scheduled_at` timestamp NULL DEFAULT NULL,
  `interview_feedback` text,
  `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_application` (`job_id`,`candidate_id`),
  KEY `job_id` (`job_id`),
  KEY `candidate_id` (`candidate_id`),
  KEY `idx_job_applications_status` (`status`),
  KEY `idx_job_applications_applied` (`applied_at`),
  CONSTRAINT `fk_job_applications_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_job_applications_job` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `subscriptions`
-- --------------------------------------------------------

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan_type` enum('monthly','annual') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` enum('INR','USD','EUR','GBP') DEFAULT 'INR',
  `razorpay_order_id` varchar(255) DEFAULT NULL,
  `razorpay_payment_id` varchar(255) DEFAULT NULL,
  `razorpay_signature` varchar(255) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `starts_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `auto_renew` tinyint(1) DEFAULT 1,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_subscriptions_status` (`payment_status`),
  KEY `idx_subscriptions_active` (`is_active`),
  KEY `idx_subscriptions_expires` (`expires_at`),
  CONSTRAINT `fk_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `coupon_codes`
-- --------------------------------------------------------

CREATE TABLE `coupon_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `discount_type` enum('percentage','fixed') DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `max_uses` int(11) DEFAULT 1,
  `used_count` int(11) DEFAULT 0,
  `is_used` tinyint(1) DEFAULT 0,
  `used_by` int(11) DEFAULT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `used_by` (`used_by`),
  KEY `created_by` (`created_by`),
  KEY `idx_coupon_codes_active` (`is_active`),
  KEY `idx_coupon_codes_expires` (`expires_at`),
  CONSTRAINT `fk_coupon_codes_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_coupon_codes_used_by` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `notifications`
-- --------------------------------------------------------

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `action_url` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_notifications_read` (`is_read`),
  KEY `idx_notifications_created` (`created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `email_templates`
-- --------------------------------------------------------

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `variables` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_email_templates_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `system_settings`
-- --------------------------------------------------------

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` enum('string','number','boolean','json') DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_system_settings_public` (`is_public`),
  CONSTRAINT `fk_system_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `audit_logs`
-- --------------------------------------------------------

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text,
  `new_values` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_audit_logs_action` (`action`),
  KEY `idx_audit_logs_table` (`table_name`),
  KEY `idx_audit_logs_created` (`created_at`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `user_activities`
-- --------------------------------------------------------

CREATE TABLE `user_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `metadata` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_user_activities_type` (`activity_type`),
  KEY `idx_user_activities_created` (`created_at`),
  CONSTRAINT `fk_user_activities_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `performance_metrics`
-- --------------------------------------------------------

CREATE TABLE `performance_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `metric_name` varchar(100) NOT NULL,
  `metric_value` decimal(15,4) NOT NULL,
  `metric_type` enum('counter','gauge','histogram') DEFAULT 'gauge',
  `tags` text,
  `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_performance_metrics_name` (`metric_name`),
  KEY `idx_performance_metrics_recorded` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `system_analytics`
-- --------------------------------------------------------

CREATE TABLE `system_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `total_users` int(11) DEFAULT 0,
  `new_registrations` int(11) DEFAULT 0,
  `active_users` int(11) DEFAULT 0,
  `total_interviews` int(11) DEFAULT 0,
  `ai_interviews` int(11) DEFAULT 0,
  `expert_interviews` int(11) DEFAULT 0,
  `total_revenue` decimal(12,2) DEFAULT 0.00,
  `new_subscriptions` int(11) DEFAULT 0,
  `cancelled_subscriptions` int(11) DEFAULT 0,
  `job_applications` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `date` (`date`),
  KEY `idx_system_analytics_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `candidate_briefs`
-- --------------------------------------------------------

CREATE TABLE `candidate_briefs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `candidate_id` int(11) NOT NULL,
  `expert_id` int(11) DEFAULT NULL,
  `brief_data` text NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_candidate_expert` (`candidate_id`,`expert_id`),
  KEY `candidate_id` (`candidate_id`),
  KEY `expert_id` (`expert_id`),
  CONSTRAINT `fk_candidate_briefs_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_candidate_briefs_expert` FOREIGN KEY (`expert_id`) REFERENCES `experts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Insert default data
-- --------------------------------------------------------

-- Insert default admin user
INSERT INTO `users` (`email`, `password`, `full_name`, `user_type`, `is_active`, `email_verified`) VALUES
('admin@skillverge.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 1, 1);

-- Insert sample users for testing
INSERT INTO `users` (`email`, `password`, `full_name`, `user_type`, `is_active`, `email_verified`) VALUES
('candidate@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Candidate', 'candidate', 1, 1),
('company@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Tech Company', 'company', 1, 1),
('expert@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Expert', 'expert', 1, 1);

-- Insert sample candidate
INSERT INTO `candidates` (`user_id`, `skills`, `experience_level`, `preferred_role`, `preferred_domain`) VALUES
(2, 'JavaScript, React, Node.js, Python', 'mid', 'Full Stack Developer', 'Software Development');

-- Insert sample company
INSERT INTO `companies` (`user_id`, `company_name`, `industry`, `company_size`, `description`) VALUES
(3, 'TechCorp Solutions', 'Technology', '51-200', 'Leading software development company specializing in web and mobile applications.');

-- Insert sample expert
INSERT INTO `experts` (`user_id`, `specialization`, `experience_years`, `current_company`, `current_position`, `hourly_rate`) VALUES
(4, 'Software Development', 8, 'Google', 'Senior Software Engineer', 2500.00);

-- Insert interview domains
INSERT INTO `interview_domains` (`name`, `description`, `icon`, `color`, `is_active`) VALUES
('Software Development', 'Programming, algorithms, system design, and software engineering concepts', 'fas fa-code', '#007bff', 1),
('Data Science', 'Machine learning, statistics, data analysis, and visualization', 'fas fa-chart-line', '#28a745', 1),
('Digital Marketing', 'SEO, SEM, social media marketing, and content strategy', 'fas fa-bullhorn', '#ffc107', 1),
('Finance', 'Financial analysis, accounting, investment, and risk management', 'fas fa-dollar-sign', '#17a2b8', 1),
('Product Management', 'Product strategy, roadmap planning, and stakeholder management', 'fas fa-tasks', '#6f42c1', 1),
('Human Resources', 'Talent acquisition, employee relations, and organizational development', 'fas fa-users', '#e83e8c', 1),
('Sales', 'Sales strategy, customer relationship management, and business development', 'fas fa-handshake', '#fd7e14', 1),
('Design', 'UI/UX design, graphic design, and user experience research', 'fas fa-paint-brush', '#20c997', 1);

-- Insert default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`) VALUES
('site_name', 'SkillVerge', 'string', 'Website name', 1),
('site_description', 'AI-powered interview preparation platform', 'string', 'Website description', 1),
('maintenance_mode', 'false', 'boolean', 'Enable maintenance mode', 0),
('free_trial_interviews', '2', 'number', 'Number of free AI interviews', 1),
('max_interview_duration', '60', 'number', 'Maximum interview duration in minutes', 1),
('enable_notifications', 'true', 'boolean', 'Enable email notifications', 0),
('from_email', 'noreply@skillverge.com', 'string', 'Default sender email', 0),
('smtp_host', '', 'string', 'SMTP server hostname', 0),
('smtp_port', '587', 'number', 'SMTP server port', 0),
('smtp_username', '', 'string', 'SMTP username', 0),
('smtp_password', '', 'string', 'SMTP password', 0),
('razorpay_key_id', '', 'string', 'Razorpay API Key ID', 0),
('razorpay_key_secret', '', 'string', 'Razorpay API Secret', 0),
('monthly_price_inr', '100', 'number', 'Monthly plan price in INR', 1),
('annual_price_inr', '1200', 'number', 'Annual plan price in INR', 1),
('monthly_price_usd', '2', 'number', 'Monthly plan price in USD', 1),
('annual_price_usd', '15', 'number', 'Annual plan price in USD', 1),
('ai_engine', 'local', 'string', 'AI analysis engine type', 0),
('openai_api_key', '', 'string', 'OpenAI API key', 0),
('video_platform', 'jitsi', 'string', 'Default video platform', 0),
('zoom_api_key', '', 'string', 'Zoom API key', 0),
('upload_limit', '10', 'number', 'File upload limit in MB', 0),
('recording_storage', 'local', 'string', 'Recording storage type', 0),
('session_timeout', '60', 'number', 'Session timeout in minutes', 0),
('password_min_length', '6', 'number', 'Minimum password length', 0),
('enable_2fa', 'false', 'boolean', 'Enable two-factor authentication', 0),
('login_attempt_limit', '5', 'number', 'Maximum login attempts', 0),
('lockout_duration', '30', 'number', 'Account lockout duration in minutes', 0),
('enable_audit_log', 'true', 'boolean', 'Enable audit logging', 0),
('enable_caching', 'true', 'boolean', 'Enable caching', 0),
('cache_duration', '24', 'number', 'Cache duration in hours', 0),
('db_pool_size', '10', 'number', 'Database connection pool size', 0),
('enable_compression', 'true', 'boolean', 'Enable GZIP compression', 0),
('cdn_url', '', 'string', 'CDN URL for static assets', 0),
('debug_mode', 'false', 'boolean', 'Enable debug mode', 0);

-- Insert default email templates
INSERT INTO `email_templates` (`name`, `subject`, `body`, `variables`, `is_active`) VALUES
('welcome_candidate', 'Welcome to SkillVerge - Start Your Interview Journey!', 
'<h2>Welcome to SkillVerge, {{name}}!</h2>
<p>Thank you for joining SkillVerge, the AI-powered interview preparation platform.</p>
<p>As a candidate, you can:</p>
<ul>
<li>Take AI-powered mock interviews</li>
<li>Book sessions with industry experts</li>
<li>Track your progress with detailed analytics</li>
<li>Apply for jobs through our job board</li>
</ul>
<p>Get started by taking your first AI mock interview!</p>
<p><a href="{{login_url}}" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Login to Dashboard</a></p>
<p>Best regards,<br>The SkillVerge Team</p>', 
'name,login_url', 1),

('welcome_company', 'Welcome to SkillVerge - Find Top Talent!', 
'<h2>Welcome to SkillVerge, {{name}}!</h2>
<p>Thank you for joining SkillVerge as a company partner.</p>
<p>With SkillVerge, you can:</p>
<ul>
<li>Post job openings</li>
<li>Review candidate applications</li>
<li>Conduct interviews</li>
<li>Access pre-screened candidates</li>
</ul>
<p>Start by posting your first job opening!</p>
<p><a href="{{login_url}}" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Login to Dashboard</a></p>
<p>Best regards,<br>The SkillVerge Team</p>', 
'name,login_url', 1),

('welcome_expert', 'Welcome to SkillVerge - Share Your Expertise!', 
'<h2>Welcome to SkillVerge, {{name}}!</h2>
<p>Thank you for joining SkillVerge as an expert interviewer.</p>
<p>As an expert, you can:</p>
<ul>
<li>Conduct mock interviews</li>
<li>Provide valuable feedback to candidates</li>
<li>Set your own rates and schedule</li>
<li>Earn money while helping others</li>
</ul>
<p>Complete your profile to start receiving interview requests!</p>
<p><a href="{{login_url}}" style="background: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Login to Dashboard</a></p>
<p>Best regards,<br>The SkillVerge Team</p>', 
'name,login_url', 1),

('interview_reminder', 'Interview Reminder - {{date}} at {{time}}', 
'<h2>Interview Reminder</h2>
<p>Hi {{name}},</p>
<p>This is a reminder that you have an interview scheduled for:</p>
<p><strong>Date:</strong> {{date}}<br>
<strong>Time:</strong> {{time}}</p>
<p>Please make sure you are prepared and have a stable internet connection.</p>
<p>Good luck with your interview!</p>
<p>Best regards,<br>The SkillVerge Team</p>', 
'name,date,time', 1),

('payment_success', 'Payment Successful - Welcome to Premium!', 
'<h2>Payment Successful!</h2>
<p>Hi {{name}},</p>
<p>Your payment of {{amount}} for the {{plan}} plan has been processed successfully.</p>
<p>You now have access to all premium features including:</p>
<ul>
<li>Unlimited AI mock interviews</li>
<li>Expert interview sessions</li>
<li>Advanced analytics and insights</li>
<li>Priority support</li>
</ul>
<p>Thank you for choosing SkillVerge Premium!</p>
<p>Best regards,<br>The SkillVerge Team</p>', 
'name,amount,plan', 1);

-- Insert sample coupon codes
INSERT INTO `coupon_codes` (`code`, `description`, `discount_type`, `discount_value`, `max_uses`, `expires_at`, `is_active`) VALUES
('WELCOME2024', 'Welcome bonus for new users', 'percentage', 50.00, 100, '2024-12-31 23:59:59', 1),
('STUDENT50', 'Student discount', 'percentage', 50.00, 500, '2024-12-31 23:59:59', 1),
('PREMIUM100', 'Free premium access', 'percentage', 100.00, 50, '2024-12-31 23:59:59', 1);

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_user_type ON users(user_type);
CREATE INDEX idx_users_active ON users(is_active);
CREATE INDEX idx_candidates_user_id ON candidates(user_id);
CREATE INDEX idx_candidates_premium ON candidates(is_premium);
CREATE INDEX idx_candidates_domain ON candidates(preferred_domain);
CREATE INDEX idx_companies_user_id ON companies(user_id);
CREATE INDEX idx_companies_verified ON companies(is_verified);
CREATE INDEX idx_companies_industry ON companies(industry);
CREATE INDEX idx_experts_user_id ON experts(user_id);
CREATE INDEX idx_experts_specialization ON experts(specialization);
CREATE INDEX idx_experts_verified ON experts(is_verified);
CREATE INDEX idx_experts_availability ON experts(availability_status);
CREATE INDEX idx_ai_interviews_candidate_id ON ai_interviews(candidate_id);
CREATE INDEX idx_ai_interviews_domain ON ai_interviews(domain);
CREATE INDEX idx_ai_interviews_status ON ai_interviews(status);
CREATE INDEX idx_ai_interviews_completed ON ai_interviews(completed_at);
CREATE INDEX idx_expert_interviews_candidate_id ON expert_interviews(candidate_id);
CREATE INDEX idx_expert_interviews_expert_id ON expert_interviews(expert_id);
CREATE INDEX idx_expert_interviews_scheduled ON expert_interviews(scheduled_at);
CREATE INDEX idx_expert_interviews_status ON expert_interviews(status);
CREATE INDEX idx_job_postings_company_id ON job_postings(company_id);
CREATE INDEX idx_job_postings_active ON job_postings(is_active);
CREATE INDEX idx_job_postings_location ON job_postings(location);
CREATE INDEX idx_job_postings_type ON job_postings(job_type);
CREATE INDEX idx_job_postings_level ON job_postings(experience_level);
CREATE INDEX idx_job_applications_job_id ON job_applications(job_id);
CREATE INDEX idx_job_applications_candidate_id ON job_applications(candidate_id);
CREATE INDEX idx_job_applications_status ON job_applications(status);
CREATE INDEX idx_job_applications_applied ON job_applications(applied_at);
CREATE INDEX idx_subscriptions_user_id ON subscriptions(user_id);
CREATE INDEX idx_subscriptions_status ON subscriptions(payment_status);
CREATE INDEX idx_subscriptions_active ON subscriptions(is_active);
CREATE INDEX idx_subscriptions_expires ON subscriptions(expires_at);
CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_notifications_read ON notifications(is_read);
CREATE INDEX idx_notifications_created ON notifications(created_at);
CREATE INDEX idx_coupon_codes_active ON coupon_codes(is_active);
CREATE INDEX idx_coupon_codes_expires ON coupon_codes(expires_at);
CREATE INDEX idx_email_templates_active ON email_templates(is_active);
CREATE INDEX idx_system_settings_public ON system_settings(is_public);
CREATE INDEX idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX idx_audit_logs_action ON audit_logs(action);
CREATE INDEX idx_audit_logs_table ON audit_logs(table_name);
CREATE INDEX idx_audit_logs_created ON audit_logs(created_at);
CREATE INDEX idx_user_activities_user_id ON user_activities(user_id);
CREATE INDEX idx_user_activities_type ON user_activities(activity_type);
CREATE INDEX idx_user_activities_created ON user_activities(created_at);
CREATE INDEX idx_performance_metrics_name ON performance_metrics(metric_name);
CREATE INDEX idx_performance_metrics_recorded ON performance_metrics(recorded_at);
CREATE INDEX idx_system_analytics_date ON system_analytics(date);
CREATE INDEX idx_candidate_briefs_candidate_id ON candidate_briefs(candidate_id);
CREATE INDEX idx_candidate_briefs_expert_id ON candidate_briefs(expert_id);

-- Create views for common queries
CREATE VIEW candidate_stats AS
SELECT 
    c.id,
    c.user_id,
    u.full_name,
    u.email,
    c.experience_level,
    c.preferred_domain,
    c.is_premium,
    c.trial_interviews_used,
    c.total_interviews_taken,
    c.average_score,
    COUNT(ai.id) as ai_interviews_count,
    COUNT(ei.id) as expert_interviews_count,
    COUNT(ja.id) as job_applications_count
FROM candidates c
JOIN users u ON c.user_id = u.id
LEFT JOIN ai_interviews ai ON c.id = ai.candidate_id
LEFT JOIN expert_interviews ei ON c.id = ei.candidate_id
LEFT JOIN job_applications ja ON c.id = ja.candidate_id
GROUP BY c.id;

CREATE VIEW company_stats AS
SELECT 
    c.id,
    c.user_id,
    u.full_name,
    u.email,
    c.company_name,
    c.industry,
    c.company_size,
    c.is_verified,
    c.total_job_postings,
    c.active_job_postings,
    COUNT(jp.id) as current_job_postings,
    COUNT(ja.id) as total_applications_received
FROM companies c
JOIN users u ON c.user_id = u.id
LEFT JOIN job_postings jp ON c.id = jp.company_id AND jp.is_active = 1
LEFT JOIN job_applications ja ON jp.id = ja.job_id
GROUP BY c.id;

CREATE VIEW expert_stats AS
SELECT 
    e.id,
    e.user_id,
    u.full_name,
    u.email,
    e.specialization,
    e.experience_years,
    e.hourly_rate,
    e.currency,
    e.availability_status,
    e.rating,
    e.total_sessions,
    e.total_earnings,
    e.is_verified,
    COUNT(ei.id) as scheduled_interviews,
    COUNT(CASE WHEN ei.status = 'completed' THEN 1 END) as completed_interviews
FROM experts e
JOIN users u ON e.user_id = u.id
LEFT JOIN expert_interviews ei ON e.id = ei.expert_id
GROUP BY e.id;

-- Enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

COMMIT;

-- End of SkillVerge Database Schema
