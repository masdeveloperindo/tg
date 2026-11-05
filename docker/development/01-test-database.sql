-- Local development only. Keep tests separate from the application's data.
CREATE DATABASE IF NOT EXISTS tg_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON tg_test.* TO 'tg'@'%';
