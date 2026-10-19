<?php
/**
 * Kutubkhanah: tiny HTTP GET helper (cURL when available, streams otherwise).
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Http
{
    const UA = 'Kutubkhanah/1.0 (+library catalogue)';

    /** @return array{0:int,1:string} [status code, body] */
    public static function get(string $url, int $timeout = 12): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_USERAGENT      => self::UA,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            ]);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return [$body === false ? 0 : $code, $body === false ? '' : (string)$body];
        }
        $ctx = stream_context_create(['http' => [
            'timeout' => $timeout, 'user_agent' => self::UA, 'ignore_errors' => true, 'follow_location' => 1,
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1];
        }
        return [$code, $body === false ? '' : $body];
    }

    public static function json(string $url): ?array
    {
        [$code, $body] = self::get($url);
        if ($code !== 200) return null;
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }
}
