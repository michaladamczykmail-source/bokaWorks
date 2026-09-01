<?php
/**
 * Endpoint formularza kontaktowego bokaWorks.
 * Przyjmuje POST (JSON) z formularza na stronie, waliduje i wysyła e-mail przez SMTP (PHPMailer).
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // nigdy nie pokazuj błędów PHP użytkownikowi w produkcji

header('Content-Type: application/json; charset=utf-8');
// Ten endpoint jest wywoływany tylko z tej samej domeny (fetch same-origin),
// więc świadomie NIE dodajemy nagłówków Access-Control-Allow-Origin — brak CORS
// oznacza, że żadna inna strona nie odpyta tego endpointu z przeglądarki.

require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function respond(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(int $status, string $error): never {
    respond($status, ['ok' => false, 'error' => $error]);
}

// --- tylko POST ---
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail(405, 'method_not_allowed');
}

// --- konfiguracja ---
$configPath = __DIR__ . '/contact-config.php';
if (!is_file($configPath)) {
    error_log('contact.php: brak pliku contact-config.php');
    fail(500, 'server_misconfigured');
}
$config = require $configPath;

// --- podstawowa ochrona przed użyciem endpointu z obcej strony ---
$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
if ($origin !== '' && !empty($config['allowed_origins'])) {
    $host = parse_url($origin, PHP_URL_HOST) ?? '';
    if (!in_array($host, $config['allowed_origins'], true)) {
        fail(403, 'origin_not_allowed');
    }
}

// --- limit rozmiaru i parsowanie JSON ---
$raw = file_get_contents('php://input', false, null, 0, 20000); // twardy limit ~20KB
$data = json_decode((string) $raw, true);
if (!is_array($data)) {
    fail(400, 'invalid_payload');
}

// --- honeypot: bot wypełnia ukryte pole, człowiek go nie widzi ---
$honeypot = trim((string) ($data['website'] ?? ''));
if ($honeypot !== '') {
    // Udajemy sukces, żeby nie zdradzić botowi, że został wykryty.
    respond(200, ['ok' => true]);
}

// --- ochrona czasowa: formularz wypełniony szybciej niż to możliwe dla człowieka ---
$elapsedMs = (int) ($data['elapsed'] ?? 0);
if ($elapsedMs < 2500) {
    respond(200, ['ok' => true]);
}

// --- rate limiting per IP (plikowy, bez bazy danych) ---
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateDir = __DIR__ . '/.contact-rate';
if (!is_dir($rateDir)) {
    @mkdir($rateDir, 0700, true);
}
$rateFile = $rateDir . '/' . hash('sha256', $ip) . '.json';
$now = time();
$windowSeconds = 3600;
$maxPerWindow = 5;

$fp = @fopen($rateFile, 'c+');
if ($fp !== false) {
    flock($fp, LOCK_EX);
    $content = stream_get_contents($fp);
    $entry = $content ? json_decode($content, true) : null;
    $timestamps = is_array($entry['timestamps'] ?? null) ? $entry['timestamps'] : [];
    $timestamps = array_values(array_filter($timestamps, fn($t) => $now - (int) $t < $windowSeconds));

    if (count($timestamps) >= $maxPerWindow) {
        flock($fp, LOCK_UN);
        fclose($fp);
        fail(429, 'too_many_requests');
    }

    $timestamps[] = $now;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode(['timestamps' => $timestamps]));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

// --- walidacja i sanityzacja pól ---
function cleanText(string $v, int $maxLen): string {
    $v = trim($v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v) ?? ''; // usuń znaki kontrolne (w tym \r\n dla header injection)
    $v = strip_tags($v);
    if (mb_strlen($v) > $maxLen) {
        $v = mb_substr($v, 0, $maxLen);
    }
    return $v;
}

$name = cleanText((string) ($data['name'] ?? ''), 120);
$contact = cleanText((string) ($data['contact'] ?? ''), 150);
$message = cleanText((string) ($data['message'] ?? ''), 3000);
$consent = (bool) ($data['consent'] ?? false);

if ($name === '' || $contact === '') {
    fail(422, 'missing_required_fields');
}
if (!$consent) {
    fail(422, 'consent_required');
}

// "Telefon lub e-mail" — sprawdzamy z grubsza, że to coś sensownego, bez sztywnego reżimu formatu
$looksLikeEmail = (bool) filter_var($contact, FILTER_VALIDATE_EMAIL);
$looksLikePhone = (bool) preg_match('/^[0-9+()\s-]{6,20}$/', $contact);
if (!$looksLikeEmail && !$looksLikePhone) {
    fail(422, 'invalid_contact');
}

// --- wysyłka przez SMTP (PHPMailer robi to bezpiecznie — bez ręcznego budowania nagłówków) ---
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $config['smtp_host'];
    $mail->Port = (int) $config['smtp_port'];
    $mail->SMTPSecure = $config['smtp_secure'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp_user'];
    $mail->Password = $config['smtp_pass'];
    $mail->CharSet = 'UTF-8';

    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($config['to_email'], $config['to_name']);
    // Odpowiedź "reply-to" tylko jeśli kontakt wygląda na e-mail — inaczej zostawiamy domyślny from.
    if ($looksLikeEmail) {
        $mail->addReplyTo($contact, $name);
    }

    $mail->Subject = 'Nowe zapytanie ze strony bokaWorks';
    $mail->isHTML(false);
    $mail->Body = implode("\n", [
        'Imię i nazwisko: ' . $name,
        'Kontakt: ' . $contact,
        'Wiadomość: ' . ($message !== '' ? $message : '-'),
        '',
        'Zgoda na kontakt: tak',
        'IP: ' . $ip,
    ]);

    $mail->send();
} catch (PHPMailerException $e) {
    error_log('contact.php mail error: ' . $mail->ErrorInfo);
    fail(502, 'send_failed');
}

respond(200, ['ok' => true]);
