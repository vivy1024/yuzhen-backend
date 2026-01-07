-- Copy exercises with explicit column mapping
USE fitness_app;

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE exercises;

INSERT INTO fitness_app.exercises (
    id, name, name_zh, slug, description, description_zh,
    correct_steps, primary_muscle, equipment, difficulty,
    force_type, mechanic_type, grips, categories, smart_tags,
    rating, view_count, created_at, updated_at, secondary_muscles
)
SELECT 
    id, name, name_zh, slug, description, description_zh,
    correct_steps, primary_muscle, equipment, difficulty,
    force_type, mechanic_type, grips, categories, smart_tags,
    rating, view_count, created_at, updated_at, secondary_muscles
FROM fitness_app_v2.exercises;

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

TRUNCATE TABLE exercise_v2_media;

INSERT INTO fitness_app.exercise_v2_media 
SELECT * FROM fitness_app_v2.exercise_v2_media;

SET FOREIGN_KEY_CHECKS = 1;

SELECT COUNT(*) as exercises_count FROM exercises;
SELECT COUNT(*) as media_count FROM exercise_v2_media;
SELECT COUNT(*) as with_correct_steps FROM exercises WHERE correct_steps IS NOT NULL;











