-- Copy exercises data from fitness_app_v2 to fitness_app
USE fitness_app;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Clear existing data
TRUNCATE TABLE exercises;

-- 2. Create exercise_v2_media table if not exists (same structure as v2)
CREATE TABLE IF NOT EXISTS exercise_v2_media (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exercise_id BIGINT UNSIGNED NOT NULL,
    media_type VARCHAR(50) NOT NULL,
    local_path VARCHAR(255) NULL,
    cdn_url VARCHAR(255) NULL,
    file_size INT NULL,
    duration INT NULL,
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
    INDEX idx_exercise_media (exercise_id, media_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Clear media table
TRUNCATE TABLE exercise_v2_media;

-- 4. Copy exercises data
INSERT INTO fitness_app.exercises 
SELECT * FROM fitness_app_v2.exercises;

-- 5. Copy media data  
INSERT INTO fitness_app.exercise_v2_media 
SELECT * FROM fitness_app_v2.exercise_v2_media;

SET FOREIGN_KEY_CHECKS = 1;

-- Verify
SELECT COUNT(*) as exercises_count FROM fitness_app.exercises;
SELECT COUNT(*) as media_count FROM fitness_app.exercise_v2_media;
SELECT 
    COUNT(*) as exercises_with_steps 
FROM fitness_app.exercises 
WHERE correct_steps IS NOT NULL;











