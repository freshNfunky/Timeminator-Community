<?php
declare(strict_types=1);

/**
 * Optional, opt-in installation registration (Issue #2c - lead generator).
 *
 * Sends only: contact email, site domain, app version and PHP version, to a
 * configurable endpoint. It is off unless the admin opts in, and it can be
 * turned off again at any time. No time-tracking data is ever transmitted.
 */
final class Registration
{
    public static function endpoint(): string
    {
        return (string) cfg('registration_endpoint', '');
    }

    public static function status(): array
    {
        return (array) Settings::get('registration', [
            'opt_in'    => false,
            'email'     => '',
            'last_sent' => null,
        ]);
    }

    public static function save(bool $optIn, string $email): array
    {
        $state = self::status();
        $state['opt_in'] = $optIn;
        $state['email']  = $email;
        Settings::set('registration', $state);

        if (!$optIn) {
            return ['ok' => true, 'message' => 'Registrierung deaktiviert. Es werden keine Daten gesendet.'];
        }
        return self::send($email);
    }

    public static function send(string $email): array
    {
        $endpoint = self::endpoint();
        if ($endpoint === '') {
            return ['ok' => false, 'message' => 'Kein Registrierungs-Endpoint konfiguriert.'];
        }
        $payload = json_encode([
            'email'       => $email,
            'domain'      => $_SERVER['HTTP_HOST'] ?? '',
            'app'         => 'timeminator',
            'version'     => app_version(),
            'php'         => PHP_VERSION,
            'ts'          => now(),
        ], JSON_UNESCAPED_SLASHES);

        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($endpoint);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $resp = curl_exec($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $err = curl_error($ch);
                curl_close($ch);
                if ($resp === false) {
                    throw new RuntimeException($err ?: 'Verbindung fehlgeschlagen.');
                }
            } else {
                $ctx = stream_context_create(['http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $payload,
                    'timeout' => 15,
                ]]);
                $resp = @file_get_contents($endpoint, false, $ctx);
                if ($resp === false) {
                    throw new RuntimeException('Verbindung fehlgeschlagen (kein Netzzugang?).');
                }
            }
            $state = self::status();
            $state['last_sent'] = now();
            Settings::set('registration', $state);
            return ['ok' => true, 'message' => 'Registrierung gesendet. Danke!'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Registrierung fehlgeschlagen: ' . $e->getMessage()];
        }
    }
}
