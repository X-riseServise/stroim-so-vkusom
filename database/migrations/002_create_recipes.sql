CREATE TABLE recipes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    episode_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    ingredients TEXT NOT NULL,
    instructions TEXT NOT NULL,
    image VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recipes_episode_id (episode_id),
    KEY idx_recipes_sort_order (sort_order),
    CONSTRAINT fk_recipes_episode_id
        FOREIGN KEY (episode_id) REFERENCES episodes(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
