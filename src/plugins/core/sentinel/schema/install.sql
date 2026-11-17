CREATE TABLE IF NOT EXISTS sentinel_record (
    record_key VARCHAR(100) NOT NULL PRIMARY KEY,
    kind VARCHAR(24) NOT NULL,
    payload LONGTEXT NOT NULL,
    revision INT NOT NULL DEFAULT 1,
    INDEX sentinel_kind (kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
