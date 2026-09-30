<?php
/**
 * Optional CLI installer: php install.php
 * Creates database schema using config credentials.
 */
declare(strict_types=1);

require __DIR__ . '/includes/config.php';

$sqlFile = __DIR__ . '/database/database.sql';
if (!is_file($sqlFile)) {
    fwrite(STDERR, "Missing database/database.sql\n");
    exit(1);
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET),
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    fwrite(STDERR, "Cannot connect to MySQL: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Update includes/config.php or includes/config.local.php\n");
    exit(1);
}

$sql = file_get_contents($sqlFile);
// Split on semicolons carefully enough for this schema file
$statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: []));
foreach ($statements as $stmt) {
    if ($stmt === '' || str_starts_with($stmt, '--')) {
        continue;
    }
    // Skip pure comment blocks
    $stripped = preg_replace('/^--.*$/m', '', $stmt);
    if (trim((string) $stripped) === '') {
        continue;
    }
    $pdo->exec($stmt);
}

echo "Database imported successfully into " . DB_NAME . "\n";
echo "Open " . APP_URL . "\n";
echo "DEMO admin: admin / Admin@ChangeMe1\n";
echo "DEMO user: alice / Demo@12345\n";
