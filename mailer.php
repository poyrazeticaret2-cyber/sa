<?php
/**
 * AlmancaPro - SMTP posta gonderimi (harici kutuphane gerektirmez).
 *
 * Composer kurulumu gerekmez. SMTP yapilandirilmamissa gonderim
 * denenmez; cagiran taraf duruma gore kullaniciyi bilgilendirir.
 * Sifreler asla loglanmaz.
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * SMTP gercekten kullanilabilir mi?
 *
 * Ayarlar kurulumda bos dize olarak olusturuldugu icin "null degil" kontrolu
 * yeterli degildir; degerlerin dolu olmasi gerekir. Plesk'te giden posta
 * sunucusu kimlik dogrulamasi zorunlu kildigindan kullanici adi ve sifre de
 * aranir. Eksikse site calismaya devam eder, yalnizca e-posta gonderilemez.
 */
function smtp_is_configured(): bool
{
    foreach (['smtp_host', 'smtp_username', 'smtp_password', 'mail_from'] as $key) {
        if (trim((string)setting($key, '')) === '') {
            return false;
        }
    }
    return (int)setting('smtp_port', '0') > 0;
}

/** Eksik olan SMTP alanlarini yoneticiye gostermek icin listeler. */
function smtp_missing_fields(): array
{
    $labels = [
        'smtp_host'     => 'Sunucu adresi',
        'smtp_username' => 'Kullanıcı adı (noreply@ adresi)',
        'smtp_password' => 'Posta kutusu şifresi',
        'mail_from'     => 'Gönderen adres',
    ];
    $missing = [];
    foreach ($labels as $key => $label) {
        if (trim((string)setting($key, '')) === '') {
            $missing[] = $label;
        }
    }
    if ((int)setting('smtp_port', '0') <= 0) {
        $missing[] = 'Port';
    }
    return $missing;
}

/**
 * @return array{ok:bool,error:string}
 */
function send_mail(string $to, string $subject, string $htmlBody, string $textBody = '', string $template = 'generic'): array
{
    if (!valid_email($to)) {
        return ['ok' => false, 'error' => 'Geçersiz alıcı adresi.'];
    }
    if (!smtp_is_configured()) {
        mail_log_write($to, $subject, $template, false, 'SMTP yapılandırılmadı');
        return ['ok' => false, 'error' => 'SMTP yapılandırılmadı.'];
    }

    if ($textBody === '') {
        $textBody = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody) ?? $htmlBody), ENT_QUOTES, 'UTF-8'));
    }

    $host       = (string)setting('smtp_host');
    $port       = (int)setting('smtp_port', '587');
    $username   = (string)setting('smtp_username', '');
    $password   = (string)setting('smtp_password', '');
    $encryption = strtolower((string)setting('smtp_encryption', 'tls'));
    $fromMail   = (string)setting('mail_from');
    $fromName   = (string)setting('mail_from_name', site_name());

    $result = smtp_send_raw($host, $port, $encryption, $username, $password, $fromMail, $fromName, $to, $subject, $htmlBody, $textBody);
    mail_log_write($to, $subject, $template, $result['ok'], $result['error']);
    return $result;
}

function mail_log_write(string $to, string $subject, string $template, bool $ok, string $error): void
{
    try {
        db_exec(
            'INSERT INTO mail_log (to_email, subject, template, status, error) VALUES (?, ?, ?, ?, ?)',
            [substr($to, 0, 190), substr($subject, 0, 240), substr($template, 0, 48), $ok ? 'sent' : 'failed', $ok ? null : substr(scrub_secrets($error), 0, 500)]
        );
    } catch (Throwable $e) {
        /* yoksay */
    }
}

/**
 * Ham SMTP konusmasi.
 * @return array{ok:bool,error:string}
 */
function smtp_send_raw(
    string $host,
    int $port,
    string $encryption,
    string $username,
    string $password,
    string $fromMail,
    string $fromName,
    string $to,
    string $subject,
    string $htmlBody,
    string $textBody
): array {
    $timeout = 20;
    $transport = ($encryption === 'ssl') ? 'ssl://' : '';
    /* Bazi paylasimli sunucular soket fonksiyonlarini kapatir. Bu durumda
       "Call to undefined function" fatal hatasi olusup sayfa 500 dondurur;
       onun yerine anlasilir bir hata mesaji don. */
    foreach (['stream_socket_client', 'stream_context_create', 'stream_socket_enable_crypto'] as $fn) {
        if (!function_exists($fn)) {
            return [
                'ok' => false,
                'error' => 'Sunucuda ' . $fn . '() kapatılmış; SMTP ile e-posta gönderilemiyor. '
                    . 'Hosting sağlayıcınızdan disable_functions listesinden çıkarmasını isteyin.',
            ];
        }
    }

    $context = stream_context_create([
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true],
    ]);

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if ($socket === false) {
        return ['ok' => false, 'error' => 'SMTP bağlantısı kurulamadı (' . $errno . ').'];
    }
    stream_set_timeout($socket, $timeout);

    $read = static function ($sock): string {
        $data = '';
        while (($line = fgets($sock, 615)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = static function ($sock, string $command) use ($read): string {
        fwrite($sock, $command . "\r\n");
        return $read($sock);
    };
    $codeOf = static fn (string $r): int => (int)substr(trim($r), 0, 3);

    try {
        $greet = $read($socket);
        if ($codeOf($greet) !== 220) {
            throw new RuntimeException('SMTP karşılama başarısız.');
        }

        $ehloHost = (string)(parse_url(site_url(), PHP_URL_HOST) ?: 'localhost');
        $r = $cmd($socket, 'EHLO ' . $ehloHost);
        if ($codeOf($r) !== 250) {
            $r = $cmd($socket, 'HELO ' . $ehloHost);
            if ($codeOf($r) !== 250) {
                throw new RuntimeException('SMTP EHLO reddedildi.');
            }
        }

        if ($encryption === 'tls') {
            $r = $cmd($socket, 'STARTTLS');
            if ($codeOf($r) !== 220) {
                throw new RuntimeException('STARTTLS reddedildi.');
            }
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (!@stream_socket_enable_crypto($socket, true, $crypto)) {
                throw new RuntimeException('TLS el sıkışması başarısız.');
            }
            $r = $cmd($socket, 'EHLO ' . $ehloHost);
            if ($codeOf($r) !== 250) {
                throw new RuntimeException('TLS sonrası EHLO reddedildi.');
            }
        }

        if ($username !== '') {
            $r = $cmd($socket, 'AUTH LOGIN');
            if ($codeOf($r) !== 334) {
                throw new RuntimeException('SMTP kimlik doğrulama başlatılamadı.');
            }
            $r = $cmd($socket, base64_encode($username));
            if ($codeOf($r) !== 334) {
                throw new RuntimeException('SMTP kullanıcı adı reddedildi.');
            }
            $r = $cmd($socket, base64_encode($password));
            if ($codeOf($r) !== 235) {
                throw new RuntimeException('SMTP kimlik doğrulama başarısız.');
            }
        }

        $r = $cmd($socket, 'MAIL FROM:<' . $fromMail . '>');
        if ($codeOf($r) !== 250) {
            throw new RuntimeException('Gönderen adres reddedildi.');
        }
        $r = $cmd($socket, 'RCPT TO:<' . $to . '>');
        if (!in_array($codeOf($r), [250, 251], true)) {
            throw new RuntimeException('Alıcı adres reddedildi.');
        }
        $r = $cmd($socket, 'DATA');
        if ($codeOf($r) !== 354) {
            throw new RuntimeException('DATA reddedildi.');
        }

        $boundary = 'ap_' . bin2hex(random_bytes(12));
        $headers = [
            'Date: ' . date('r'),
            'From: ' . smtp_encode_header($fromName) . ' <' . $fromMail . '>',
            'To: <' . $to . '>',
            'Subject: ' . smtp_encode_header($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $ehloHost . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: AlmancaPro',
        ];
        $body = implode("\r\n", $headers) . "\r\n\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($textBody)) . "\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $body .= '--' . $boundary . "--\r\n";

        /* Nokta kacisi */
        $body = preg_replace('/^\./m', '..', $body) ?? $body;

        fwrite($socket, $body . "\r\n.\r\n");
        $r = $read($socket);
        if ($codeOf($r) !== 250) {
            throw new RuntimeException('Mesaj kabul edilmedi.');
        }
        $cmd($socket, 'QUIT');
        fclose($socket);
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        if (is_resource($socket)) {
            @fclose($socket);
        }
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function smtp_encode_header(string $value): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $value)) {
        return $value;
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

/* ------------------------------------------------------------------
 * Sablonlar
 * ------------------------------------------------------------------ */

function mail_layout(string $title, string $contentHtml, string $footerNote = ''): string
{
    $site = e(site_name());
    $url = e(site_url());
    $footer = $footerNote !== '' ? '<p style="margin:24px 0 0;font-size:12px;color:#77746B;">' . e($footerNote) . '</p>' : '';
    return '<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>' . e($title) . '</title></head>'
        . '<body style="margin:0;padding:24px;background:#F4F2ED;font-family:Helvetica,Arial,sans-serif;color:#111110;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
        . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border:1px solid #DCD8CF;">'
        . '<tr><td style="padding:24px 28px;border-bottom:1px solid #DCD8CF;">'
        . '<span style="font-weight:700;letter-spacing:0.06em;text-transform:uppercase;font-size:14px;">ALMANCA<span style="color:#8C6608;">PRO</span></span>'
        . '</td></tr>'
        . '<tr><td style="padding:28px;font-size:15px;line-height:1.6;color:#44443F;">'
        . '<h1 style="margin:0 0 16px;font-size:20px;color:#111110;">' . e($title) . '</h1>'
        . $contentHtml . $footer
        . '</td></tr>'
        . '<tr><td style="padding:18px 28px;border-top:1px solid #DCD8CF;font-size:12px;color:#77746B;">'
        . $site . ' · <a href="' . $url . '" style="color:#8C6608;">' . $url . '</a>'
        . '</td></tr></table></td></tr></table></body></html>';
}

function mail_send_verification(array $user, string $code): array
{
    $html = mail_layout(
        'E-posta adresini doğrula',
        '<p>Merhaba ' . e((string)$user['name']) . ',</p>'
        . '<p>AlmancaPro hesabını kullanmaya başlamak için aşağıdaki 6 haneli kodu doğrulama ekranına gir:</p>'
        . '<p style="font-size:32px;font-weight:700;letter-spacing:0.2em;font-family:monospace;margin:24px 0;">' . e($code) . '</p>'
        . '<p>Kod 10 dakika geçerlidir ve yalnızca bir kez kullanılabilir.</p>',
        'Bu isteği sen yapmadıysan bu e-postayı yok sayabilirsin.'
    );
    $text = "Merhaba " . $user['name'] . ",\n\nDoğrulama kodun: " . $code . "\nKod 10 dakika geçerlidir.\n";
    return send_mail((string)$user['email'], 'AlmancaPro doğrulama kodun: ' . $code, $html, $text, 'verification');
}

function mail_send_password_reset(array $user, string $token): array
{
    $link = site_url('reset-password.php?token=' . $token);
    $html = mail_layout(
        'Şifre sıfırlama',
        '<p>Merhaba ' . e((string)$user['name']) . ',</p>'
        . '<p>Şifreni sıfırlamak için aşağıdaki bağlantıya tıkla. Bağlantı 60 dakika geçerlidir ve bir kez kullanılabilir.</p>'
        . '<p style="margin:24px 0;"><a href="' . e($link) . '" style="background:#111110;color:#FFFFFF;padding:14px 22px;text-decoration:none;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;font-size:13px;">Şifremi sıfırla</a></p>'
        . '<p style="font-size:13px;word-break:break-all;">' . e($link) . '</p>',
        'Bu isteği sen yapmadıysan şifren değişmez; bu e-postayı yok sayabilirsin.'
    );
    $text = "Şifre sıfırlama baglantisi (60 dakika gecerli):\n" . $link . "\n";
    return send_mail((string)$user['email'], 'AlmancaPro şifre sıfırlama', $html, $text, 'password_reset');
}

function mail_send_security_notice(array $user, string $eventText): array
{
    $html = mail_layout(
        'Hesap güvenlik bildirimi',
        '<p>Merhaba ' . e((string)$user['name']) . ',</p><p>' . e($eventText) . '</p>'
        . '<p>Bu işlemi sen yapmadıysan hemen şifreni değiştir.</p>'
    );
    return send_mail((string)$user['email'], 'AlmancaPro güvenlik bildirimi', $html, $eventText, 'security');
}
