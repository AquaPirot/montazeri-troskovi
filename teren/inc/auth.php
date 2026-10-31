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

/** Koliko ima uključenih administratorskih naloga. */
function broj_aktivnih_admina(): int
{
    return (int)vrednost('SELECT COUNT(*) FROM korisnici WHERE uloga = "admin" AND aktivan = 1');
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
    uloguj($k);
    return true;
}

function uloguj(array $k): void
{
    session_regenerate_id(true);
    $_SESSION['korisnik_id'] = (int)$k['id'];
}

/* ============================================================
   PIN OD 4 BROJA
   PIN je ujedno i identitet: svaki aktivan korisnik ima drugačiji.
   Zato PIN dodeljuje samo administrator – kada bi korisnik sam
   birao, poruka "taj PIN je zauzet" bi mu otkrila tuđi PIN.
   ============================================================ */
const PIN_POKUSAJA = 8;     // dozvoljeno pogrešnih pokušaja ...
const PIN_MINUTA   = 15;    // ... u ovoliko minuta, po IP adresi

function pin_tajna(): string
{
    static $t = null;
    if ($t !== null) return $t;
    try {
        $t = (string)vrednost('SELECT vrednost FROM podesavanja WHERE kljuc = "pin_tajna"');
    } catch (PDOException $e) {
        $t = '';
    }
    if ($t === '') baza_nije_nadogradjena();
    return $t;
}

/** Jasna poruka umesto greške 500 kada SQL nadogradnja 03 nije pokrenuta. */
function baza_nije_nadogradjena(): never
{
    http_response_code(500);
    exit('Baza nije nadograđena za prijavu PIN-om. Pokreni sql/nadogradnja-03-pin.sql u phpMyAdmin-u, '
       . 'u bazi čiji je naziv upisan u config.php.');
}

function pin_hash(string $pin): string
{
    return hash('sha256', pin_tajna() . '|' . $pin);
}

function pin_ispravan_oblik(string $pin): bool
{
    return (bool)preg_match('/^\d{4}$/', $pin);
}

/** 1111, 1234, 4321 i slični se lako pogode. */
function pin_slab(string $pin): bool
{
    if (preg_match('/^(\d)\1{3}$/', $pin)) return true;
    return str_contains('0123456789', $pin) || str_contains('9876543210', $pin);
}

function pin_zauzet(string $pin, int $osimKorisnika = 0): bool
{
    return (bool)vrednost('SELECT COUNT(*) FROM korisnici WHERE pin_hash = ? AND id <> ?',
                          [pin_hash($pin), $osimKorisnika]);
}

/** Vraća poruku o grešci ili null ako je PIN prihvatljiv. */
function proveri_novi_pin(string $pin, int $korisnikId = 0): ?string
{
    if (!pin_ispravan_oblik($pin)) return 'PIN mora da ima tačno 4 broja.';
    if (pin_slab($pin))            return 'Taj PIN se lako pogodi (npr. 1111 ili 1234). Izaberi drugi.';
    if (pin_zauzet($pin, $korisnikId)) return 'Taj PIN već koristi drugi korisnik. Izaberi drugi.';
    return null;
}

/** Slobodan, ne-slab PIN – predlog administratoru. */
function predlozi_pin(): string
{
    for ($i = 0; $i < 200; $i++) {
        $pin = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        if (!pin_slab($pin) && !pin_zauzet($pin)) return $pin;
    }
    return '';
}

function prijavi_pinom(string $pin): bool
{
    if (!pin_ispravan_oblik($pin)) return false;
    $k = red('SELECT * FROM korisnici WHERE pin_hash = ? AND aktivan = 1', [pin_hash($pin)]);
    if (!$k) return false;
    uloguj($k);
    return true;
}

/* ---------------- zaštita od pogađanja ---------------- */
function ip_klijenta(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** Koliko minuta je još blokiran ovaj IP (null = nije blokiran). */
function blokada_minuta(): ?int
{
    $od = date('Y-m-d H:i:s', time() - PIN_MINUTA * 60);
    try {
        $r = red('SELECT COUNT(*) AS br, MIN(vreme) AS prvi FROM pokusaji_prijave WHERE ip = ? AND vreme > ?',
                 [ip_klijenta(), $od]);
    } catch (PDOException $e) {
        baza_nije_nadogradjena();
    }
    if (!$r || (int)$r['br'] < PIN_POKUSAJA) return null;
    $kraj = strtotime($r['prvi']) + PIN_MINUTA * 60;
    return max(1, (int)ceil(($kraj - time()) / 60));
}

function zabelezi_neuspeh(): void
{
    upit('INSERT INTO pokusaji_prijave (ip, vreme) VALUES (?, ?)', [ip_klijenta(), date('Y-m-d H:i:s')]);
    // stari zapisi ne trebaju
    upit('DELETE FROM pokusaji_prijave WHERE vreme < ?', [date('Y-m-d H:i:s', time() - 86400)]);
}

function ocisti_neuspehe(): void
{
    upit('DELETE FROM pokusaji_prijave WHERE ip = ?', [ip_klijenta()]);
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
