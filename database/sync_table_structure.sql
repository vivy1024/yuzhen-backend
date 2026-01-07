-- Sync table structure between fitness_app and fitness_app_v2
USE fitness_app;

-- Adjust column lengths to match v2
ALTER TABLE exercises MODIFY COLUMN name VARCHAR(255) NOT NULL;
ALTER TABLE exercises MODIFY COLUMN name_zh VARCHAR(255) NULL;
ALTER TABLE exercises MODIFY COLUMN slug VARCHAR(255) NOT NULL;
ALTER TABLE exercises MODIFY COLUMN equipment VARCHAR(255) NULL;
ALTER TABLE exercises MODIFY COLUMN primary_muscle VARCHAR(255) NULL;

SHOW COLUMNS FROM exercises;











