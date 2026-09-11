<?php
/**
 * MySQLi helpers. All queries use prepared statements to avoid SQL injection.
 */

/** Singleton connection reused for the request lifetime. */
function db(): mysqli
{
    static $mysqli = null;
    if ($mysqli instanceof mysqli) {
        return $mysqli;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $mysqli->set_charset(DB_CHARSET);
    } catch (mysqli_sql_exception $e) {
        http_response_code(500);
        echo 'Database connection failed. Import sql/schema.sql and sql/seed.sql, then check config/config.php.';
        exit;
    }

    return $mysqli;
}

/** Bind parameters by reference (required by mysqli_stmt::bind_param). */
function db_bind(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '') {
        return;
    }
    $refs = [];
    foreach ($params as $i => $value) {
        $refs[$i] = &$params[$i];
    }
    $stmt->bind_param($types, ...$refs);
}

/** Run a SELECT and return one associative row, or null. */
function db_one(mysqli $db, string $sql, string $types = '', array $params = []): ?array
{
    $stmt = $db->prepare($sql);
    db_bind($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Run a SELECT and return all rows as an array of associative arrays. */
function db_all(mysqli $db, string $sql, string $types = '', array $params = []): array
{
    $stmt = $db->prepare($sql);
    db_bind($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Run INSERT/UPDATE/DELETE; caller may read insert_id then close the statement. */
function db_exec(mysqli $db, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    db_bind($stmt, $types, $params);
    $stmt->execute();
    return $stmt;
}
