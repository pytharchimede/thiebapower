CREATE TABLE IF NOT EXISTS integration_settings (
 name VARCHAR(32) NOT NULL PRIMARY KEY,
 mode VARCHAR(32) NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT IGNORE INTO integration_settings(name,mode) VALUES ('paiementpro','sandbox'),('heycharge','simulation');
