CREATE TABLE episodes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    episode_number VARCHAR(4) NOT NULL,
    title VARCHAR(255) NOT NULL,
    guest_name VARCHAR(255) NOT NULL,
    guest_position VARCHAR(255) NULL,
    description TEXT NOT NULL,
    vk_video_url VARCHAR(500) NULL,
    cover_image VARCHAR(255) NULL,
    published_at DATETIME NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_episodes_episode_number (episode_number),
    KEY idx_episodes_episode_number (episode_number),
    KEY idx_episodes_status (status),
    KEY idx_episodes_published_at (published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
