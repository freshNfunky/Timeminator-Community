<?php
declare(strict_types=1);

/**
 * Outbound mail service for self-hosted Community installs (#107).
 *
 * No framework, no Composer — the SMTP path is a lean native client over
 * fsockopen with PLAIN/LOGIN auth and optional STARTTLS. When no SMTP host
 * is configured, falls back to PHP's `mail()`. Configuration lives in
 * `Settings::` under the `mail` key.
 */
final class Mail
{
    private const SETTINGS_KEY = 'mail';

    /** @return array{from:string,from_name:string,host:string,port:int,user:string,pass:string,security:string} */
    public static function config(): array
    {
        $raw = Settings::get(self::SETTINGS_KEY, []);
        $raw = is_array($raw) ? $raw : [];
        return [
            'from'      => (string) ($raw['from']      ?? ''),
            'from_name' => (string) ($raw['from_name'] ?? ''),
            'host'      => (string) ($raw['host']      ?? ''),
            'port'      => (int)    ($raw['port']      ?? 0),
            'user'      => (string) ($raw['user']      ?? ''),
            'pass'      => (string) ($raw['pass']      ?? ''),
            'security'  => (string) ($raw['security']  ?? 'none'),
        ];
    }

    public static function save(array $input): void
    {
        $security = (string) ($input['security'] ?? 'none');
        if (!in_array($security, ['none', 'tls', 'ssl'], true)) {
            $security = 'none';
        }
        Settings::set(self::SETTINGS_KEY, [
            'from'      => trim((string) ($input['from']      ?? '')),
            'from_name' => trim((string) ($input['from_name'] ?? '')),
            'host'      => trim((string) ($input['host']      ?? '')),
            'port'      => (int)         ($input['port']      ?? 0),
            'user'      => trim((string) ($input['user']      ?? '')),
            'pass'      => (string)      ($input['pass']      ?? ''),
            'security'  => $security,
        ]);
    }

    /** True once at least a From address is set — Password-Reset refuses to send otherwise. */
    public static function isConfigured(): bool
    {
        return self::config()['from'] !== '';
    }

    /**
     * Send a single plain-text message. Returns `[ok, errorMessage?]`.
     * The error string is a short reason suitable for an admin flash;
     * detailed SMTP transcripts stay in the PHP error log.
     *
     * @return array{0:bool,1:?string}
     */
    public static function send(string $to, string $subject, string $body): array
    {
        $cfg = self::config();
        if ($cfg['from'] === '') {
            return [false, 'mail_not_configured'];
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [false, 'invalid_recipient'];
        }
        $headers = self::buildHeaders($cfg);
        $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        if ($cfg['host'] === '') {
            return self::sendViaMailFn($to, $subjectEncoded, $body, $headers, $cfg['from']);
        }
        return self::sendViaSmtp($cfg, $to, $subjectEncoded, $body, $headers);
    }

    private static function buildHeaders(array $cfg): array
    {
        $from = $cfg['from_name'] !== ''
            ? sprintf('"%s" <%s>', str_replace('"', '', $cfg['from_name']), $cfg['from'])
            : $cfg['from'];
        return [
            'From'         => $from,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Mailer'     => 'Timeminator-Community/' . app_version(),
        ];
    }

    /** @return array{0:bool,1:?string} */
    private static function sendViaMailFn(string $to, string $subject, string $body, array $headers, string $envelopeFrom): array
    {
        $flat = [];
        foreach ($headers as $k => $v) {
            $flat[] = $k . ': ' . $v;
        }
        $ok = @mail($to, $subject, $body, implode("\r\n", $flat), '-f' . escapeshellarg($envelopeFrom));
        if (!$ok) {
            error_log('[Mail] mail() returned false for ' . $to);
            return [false, 'mail_send_failed'];
        }
        return [true, null];
    }

    /** @return array{0:bool,1:?string} */
    private static function sendViaSmtp(array $cfg, string $to, string $subject, string $body, array $headers): array
    {
        $host = $cfg['host'];
        $port = $cfg['port'] > 0 ? $cfg['port'] : ($cfg['security'] === 'ssl' ? 465 : 587);
        $connectHost = $cfg['security'] === 'ssl' ? 'ssl://' . $host : $host;
        $errno = 0; $errstr = '';
        $fp = @stream_socket_client("$connectHost:$port", $errno, $errstr, 10);
        if (!$fp) {
            error_log(sprintf('[Mail] SMTP connect failed to %s:%d (%d %s)', $host, $port, $errno, $errstr));
            return [false, 'smtp_connect_failed'];
        }
        stream_set_timeout($fp, 10);

        try {
            if (!self::smtpExpect($fp, 220)) { throw new RuntimeException('greeting'); }
            self::smtpSend($fp, 'EHLO ' . self::ehloName());
            if (!self::smtpExpect($fp, 250)) { throw new RuntimeException('ehlo'); }

            if ($cfg['security'] === 'tls') {
                self::smtpSend($fp, 'STARTTLS');
                if (!self::smtpExpect($fp, 220)) { throw new RuntimeException('starttls'); }
                $crypto = (defined('STREAM_CRYPTO_METHOD_TLS_CLIENT') ? STREAM_CRYPTO_METHOD_TLS_CLIENT : 0);
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) { $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT; }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) { $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT; }
                if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
                    throw new RuntimeException('tls_upgrade');
                }
                self::smtpSend($fp, 'EHLO ' . self::ehloName());
                if (!self::smtpExpect($fp, 250)) { throw new RuntimeException('ehlo_after_tls'); }
            }

            if ($cfg['user'] !== '') {
                self::smtpSend($fp, 'AUTH LOGIN');
                if (!self::smtpExpect($fp, 334)) { throw new RuntimeException('auth_login'); }
                self::smtpSend($fp, base64_encode($cfg['user']));
                if (!self::smtpExpect($fp, 334)) { throw new RuntimeException('auth_user'); }
                self::smtpSend($fp, base64_encode($cfg['pass']));
                if (!self::smtpExpect($fp, 235)) { throw new RuntimeException('auth_pass'); }
            }

            self::smtpSend($fp, 'MAIL FROM:<' . $cfg['from'] . '>');
            if (!self::smtpExpect($fp, 250)) { throw new RuntimeException('mail_from'); }
            self::smtpSend($fp, 'RCPT TO:<' . $to . '>');
            if (!self::smtpExpect($fp, [250, 251])) { throw new RuntimeException('rcpt_to'); }
            self::smtpSend($fp, 'DATA');
            if (!self::smtpExpect($fp, 354)) { throw new RuntimeException('data'); }

            $headers['To']      = $to;
            $headers['Subject'] = $subject;
            $headers['Date']    = date('r');
            $msg = '';
            foreach ($headers as $k => $v) {
                $msg .= $k . ': ' . $v . "\r\n";
            }
            $msg .= "\r\n" . self::dotStuff($body) . "\r\n.";
            self::smtpSend($fp, $msg);
            if (!self::smtpExpect($fp, 250)) { throw new RuntimeException('data_end'); }

            self::smtpSend($fp, 'QUIT');
            fclose($fp);
            return [true, null];
        } catch (Throwable $e) {
            error_log('[Mail] SMTP error at step ' . $e->getMessage());
            @fclose($fp);
            return [false, 'smtp_send_failed'];
        }
    }

    private static function smtpSend($fp, string $line): void
    {
        fwrite($fp, $line . "\r\n");
    }

    /** @param int|array<int,int> $expected */
    private static function smtpExpect($fp, int|array $expected): bool
    {
        $expected = is_array($expected) ? $expected : [$expected];
        while (!feof($fp)) {
            $line = fgets($fp, 4096);
            if ($line === false) { return false; }
            $code = (int) substr($line, 0, 3);
            // Multi-line responses have a '-' after the code; final line uses a space.
            if (strlen($line) > 3 && $line[3] === '-') { continue; }
            return in_array($code, $expected, true);
        }
        return false;
    }

    private static function dotStuff(string $body): string
    {
        $body = str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $body);
        return preg_replace('/^\./m', '..', $body) ?? $body;
    }

    private static function ehloName(): string
    {
        $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $host = preg_replace('/[^a-zA-Z0-9\.\-]/', '', (string) $host) ?: 'localhost';
        return $host;
    }
}
