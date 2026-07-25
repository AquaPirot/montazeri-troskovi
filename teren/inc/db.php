<?php
/**
 * Veza sa bazom i učitavanje podešavanja.
 */
if (!defined('TEREN')) { http_response_code(403); exit('Zabranjen pristup.'); }

function podesavanja(): array
{
    static $p = null;
    if ($p !== null) return $p;

    $putanja = dirname(__DIR__) . '/config.php';
    if (!is_file($putanja)) {
        http_response_code(500);
        exit('Nedostaje config.php. Kopiraj config.primer.php u config.php i upiši podatke o bazi.');
    }
    $p = require $putanja;
    return $p;
}

function baza(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $p = podesavanja();
    $dsn = 'mysql:host=' . $p['db_host'] . ';dbname=' . $p['db_naziv'] . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, $p['db_user'], $p['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('TEREN baza: ' . $e->getMessage());
        http_response_code(500);
        exit('Trenutno nije moguće povezati se sa bazom. Pokušaj ponovo za koji minut.');
    }
    return $pdo;
}

/** Kratke pomoćne funkcije za upite. */
function upit(string $sql, array $par = []): PDOStatement
{
    $s = baza()->prepare($sql);
    $s->execute($par);
    return $s;
}
function red(string $sql, array $par = []): ?array
{
    $r = upit($sql, $par)->fetch();
    return $r === false ? null : $r;
}
function redovi(string $sql, array $par = []): array
{
    return upit($sql, $par)->fetchAll();
}
function vrednost(string $sql, array $par = [])
{
    return upit($sql, $par)->fetchColumn();
}
