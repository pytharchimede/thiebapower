CREATE TABLE IF NOT EXISTS development_proposals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(160) NOT NULL,
 description TEXT NOT NULL,
 benefit VARCHAR(1000) NOT NULL,
 scope VARCHAR(2000) NOT NULL,
 priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
 status ENUM('proposed','review','approved','planned','progress','delivered','declined') NOT NULL DEFAULT 'proposed',
 budget INT UNSIGNED NULL,
 timeframe VARCHAR(160) NOT NULL DEFAULT '',
 created_by BIGINT UNSIGNED NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
