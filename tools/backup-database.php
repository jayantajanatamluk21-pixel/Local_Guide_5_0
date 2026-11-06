<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit("Not found\n"); }
require_once dirname(__DIR__) . '/app/bootstrap.php';

try {
    $pdo = db();
    $rows = $pdo->query('PRAGMA database_list')->fetchAll();
    $mainPath = '';
    foreach ($rows as $row) if (($row['name'] ?? '') === 'main') $mainPath = (string)($row['file'] ?? '');
    if ($mainPath === '' || !is_file($mainPath)) throw new RuntimeException('The configured SQLite database file was not found.');

    $backupDir = APP_ROOT . '/storage/backups';
    if (!is_dir($backupDir) && !mkdir($backupDir, 0750, true) && !is_dir($backupDir)) throw new RuntimeException('Cannot create storage/backups.');
    $backupPath = $backupDir . '/localguide-' . date('Ymd-His') . '.sqlite';
    $quoted = str_replace("'", "''", $backupPath);
    $pdo->exec("VACUUM INTO '{$quoted}'");
    if (!is_file($backupPath) || filesize($backupPath) < 100) throw new RuntimeException('Backup file was not created correctly.');

    $verify = new PDO('sqlite:' . $backupPath, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $check = (string)$verify->query('PRAGMA quick_check')->fetchColumn();
    if (strtolower($check) !== 'ok') { @unlink($backupPath); throw new RuntimeException('Backup integrity check failed: ' . $check); }
    $verify = null;

    $files = glob($backupDir . '/localguide-*.sqlite') ?: [];
    usort($files, static fn($a,$b) => filemtime($b) <=> filemtime($a));
    foreach (array_slice($files, 30) as $old) @unlink($old);

    echo "LocalGuide database backup complete.\n";
    echo 'File: ' . basename($backupPath) . "\n";
    echo 'Size: ' . number_format(filesize($backupPath)) . " bytes\n";
    echo "Integrity: " . $check . "\n";
    echo 'Retention: newest 30 backups are kept.' . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Backup failed: ' . $e->getMessage() . "\n");
    exit(1);
}
