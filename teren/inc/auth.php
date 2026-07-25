<?php
/**
 * Prijava, sesija, uloge i CSRF zaštita.
 */
if (!defined('TEREN')) { http_response_code(403); exit('Zabranjen pristup.'); }

function pokreni_sesiju(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $sigurno = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $sigurno,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('TERENSID');
    session_start();
}

function ja(): ?array
{
    static $k = null;
    if ($k !== null) return $k;
    if (empty($_SESSION['korisnik_id'])) return null;
    $k = red('SELECT * FROM korisnici WHERE id = ? AND aktivan = 1', [$_SESSION['korisnik_id']]);
    if (!$k) { unset($_SESSION['korisnik_id']); return null; }
    return $k;
}

function prijavljen(): bool { return ja() !== null; }
function admin(): bool      { $k = ja(); return $k && $k['uloga'] === 'admin'; }

function trazi_prijavu(): array
{
    $k = ja();
    if (!$k) idi('prijava');
    return $k;
}

function trazi_admina(): array
{
    $k = trazi_prijavu();
    if ($k['uloga'] !== 'admin') {
        postavi_poruku('Nemaš pristup tom delu aplikacije.', 'greska');
        idi('pocetna');
    }
    return $k;
}

function prijavi(string $korisnicko, string $lozinka): bool
{
    $k = red('SELECT * FROM korisnici WHERE korisnicko_ime = ? AND aktivan = 1', [$korisnicko]);
    if (!$k || !password_verify($lozinka, $k['lozinka_hash'])) {
        // Ujednačeno trajanje odgovora da se ne otkriva postojanje naloga.
        if (!$k) password_verify($lozinka, '$2y$12$' . str_repeat('a', 53));
        return false;
    }
    if (password_needs_rehash($k['lozinka_hash'], PASSWORD_DEFAULT)) {
        upit('UPDATE korisnici SET lozinka_hash = ? WHERE id = ?',
             [password_hash($lozinka, PASSWORD_DEFAULT), $k['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['korisnik_id'] = (int)$k['id'];
    return true;
}

function odjavi(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ---------------- CSRF ---------------- */
function csrf(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_polje(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
}

function proveri_csrf(): void
{
    $poslat = $_POST['csrf'] ?? '';
    if (!is_string($poslat) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $poslat)) {
        http_response_code(400);
        exit('Sesija je istekla. Vrati se nazad i pokušaj ponovo.');
    }
}

function je_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}
