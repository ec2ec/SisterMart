<?php

declare(strict_types=1);

/**
 * M27：訂閱郵箱名單 + 系統消息批次元數據 + 郵件出站日誌。
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use think\facade\Db;

function tableExists(string $table): bool
{
    $db = (string) env('DB_NAME', 'sistermart');
    $rows = Db::query(
        'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [$db, $table],
    );

    return (int) ($rows[0]['c'] ?? 0) > 0;
}

if (!tableExists('newsletter_subscribers')) {
    Db::execute(<<<'SQL'
CREATE TABLE newsletter_subscribers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(191) NOT NULL,
  member_id BIGINT UNSIGNED NULL,
  status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=有效 0=已關閉',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_newsletter_email (email),
  KEY idx_newsletter_member (member_id),
  KEY idx_newsletter_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}

if (!tableExists('system_message_batches')) {
    Db::execute(<<<'SQL'
CREATE TABLE system_message_batches (
  batch_id VARCHAR(36) NOT NULL PRIMARY KEY,
  title_zh VARCHAR(191) NOT NULL,
  title_en VARCHAR(191) NOT NULL,
  body_zh MEDIUMTEXT NOT NULL,
  body_en MEDIUMTEXT NOT NULL,
  scope VARCHAR(16) NOT NULL DEFAULT 'all' COMMENT 'all|selected',
  member_ids_json JSON NULL,
  send_email TINYINT UNSIGNED NOT NULL DEFAULT 0,
  recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
  email_queued INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}

if (!tableExists('mail_outbox')) {
    Db::execute(<<<'SQL'
CREATE TABLE mail_outbox (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  to_email VARCHAR(191) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'queued' COMMENT 'queued|sent|failed|mock',
  ref_type VARCHAR(32) NULL,
  ref_id VARCHAR(64) NULL,
  error_message VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  KEY idx_mail_outbox_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}

echo "m27 seed ok (newsletter + message batches + mail_outbox)\n";
