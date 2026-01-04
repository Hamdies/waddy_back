-- Insert Places Module into modules table
-- Run this SQL on your production database

-- First, insert the module
INSERT INTO `modules` (`module_name`, `module_type`, `thumbnail`, `status`, `stores_count`, `icon`, `theme_id`, `description`, `all_zone_service`, `created_at`, `updated_at`)
VALUES ('Places to Visit', 'places', NULL, 1, 0, NULL, 1, 'Discover and vote for the best local places to visit', 1, NOW(), NOW());

-- Get the last inserted module ID (you'll need to replace @module_id with the actual ID after running the above)
SET @module_id = LAST_INSERT_ID();

-- Insert English translations
INSERT INTO `translations` (`translationable_type`, `translationable_id`, `locale`, `key`, `value`, `created_at`, `updated_at`)
VALUES 
('App\\Models\\Module', @module_id, 'en', 'module_name', 'Places to Visit', NOW(), NOW()),
('App\\Models\\Module', @module_id, 'en', 'description', 'Discover and vote for the best local places to visit', NOW(), NOW());

-- Insert Arabic translations
INSERT INTO `translations` (`translationable_type`, `translationable_id`, `locale`, `key`, `value`, `created_at`, `updated_at`)
VALUES 
('App\\Models\\Module', @module_id, 'ar', 'module_name', 'أماكن للزيارة', NOW(), NOW()),
('App\\Models\\Module', @module_id, 'ar', 'description', 'اكتشف وصوت لأفضل الأماكن المحلية للزيارة', NOW(), NOW());

-- Verify the insertion
SELECT * FROM `modules` WHERE `module_type` = 'places';
