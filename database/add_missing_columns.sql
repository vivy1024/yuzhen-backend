USE fitness_app;

ALTER TABLE exercises 
ADD COLUMN smart_tags JSON NULL 
AFTER categories;

ALTER TABLE exercises 
ADD COLUMN rating INT NOT NULL DEFAULT 0 
AFTER smart_tags;

ALTER TABLE exercises 
ADD COLUMN view_count INT NOT NULL DEFAULT 0 
AFTER rating;

CREATE INDEX idx_view_count ON exercises(view_count);

SHOW COLUMNS FROM exercises;
