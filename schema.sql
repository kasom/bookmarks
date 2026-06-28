-- Bookmarks System Database Schema
CREATE DATABASE IF NOT EXISTS bookmarks_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bookmarks_db;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    approved TINYINT(1) NOT NULL DEFAULT 0,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    disabled TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS folders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_folders_user_name (user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bookmarks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    folder_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    description TEXT,
    visibility ENUM('private','shared','public') NOT NULL DEFAULT 'private',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_visibility (visibility),
    INDEX idx_bookmarks_user_visibility_created (user_id, visibility, created_at),
    INDEX idx_bookmarks_user_folder_created (user_id, folder_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bookmark_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bookmark_id INT UNSIGNED NOT NULL,
    tag_name VARCHAR(50) NOT NULL,
    FOREIGN KEY (bookmark_id) REFERENCES bookmarks(id) ON DELETE CASCADE,
    INDEX idx_tag (tag_name),
    INDEX idx_bookmark_tags_cover (bookmark_id, tag_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shared_bookmarks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bookmark_id INT UNSIGNED NOT NULL,
    shared_by_user_id INT UNSIGNED NOT NULL,
    shared_with_user_id INT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_share (bookmark_id, shared_with_user_id),
    FOREIGN KEY (bookmark_id) REFERENCES bookmarks(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(20) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    identifier VARCHAR(100) NOT NULL DEFAULT '',
    attempts INT NOT NULL DEFAULT 1,
    last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
    locked_until DATETIME DEFAULT NULL,
    INDEX idx_endpoint_ip (endpoint, ip_address),
    INDEX idx_locked (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
