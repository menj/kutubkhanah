<?php
/**
 * Kutubkhanah: ISBN helpers (cleaning, checksum validation, 10 <-> 13 conversion).
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

namespace SLiMS\Kutubkhanah;

class Isbn
{
    public static function clean($raw): string
    {
        return strtoupper(preg_replace('/[^0-9Xx]/', '', (string)$raw));
    }

    public static function isValid13(string $s): bool
    {
        if (!preg_match('/^\d{13}$/', $s)) return false;
        $sum = 0;
        for ($i = 0; $i < 12; $i++) $sum += (int)$s[$i] * ($i % 2 ? 3 : 1);
        return ((10 - ($sum % 10)) % 10) === (int)$s[12];
    }

    public static function isValid10(string $s): bool
    {
        if (!preg_match('/^\d{9}[\dX]$/', $s)) return false;
        $sum = 0;
        for ($i = 0; $i < 10; $i++) $sum += ($s[$i] === 'X' ? 10 : (int)$s[$i]) * (10 - $i);
        return $sum % 11 === 0;
    }

    public static function to13(string $isbn10): string
    {
        $core = '978' . substr($isbn10, 0, 9);
        $sum = 0;
        for ($i = 0; $i < 12; $i++) $sum += (int)$core[$i] * ($i % 2 ? 3 : 1);
        return $core . ((10 - ($sum % 10)) % 10);
    }

    public static function to10(string $isbn13): string
    {
        if (strlen($isbn13) !== 13 || strpos($isbn13, '978') !== 0) return '';
        $core = substr($isbn13, 3, 9);
        $sum = 0;
        for ($i = 0; $i < 9; $i++) $sum += (int)$core[$i] * (10 - $i);
        $check = (11 - ($sum % 11)) % 11;
        return $core . ($check === 10 ? 'X' : (string)$check);
    }

    /** A valid ISBN-13 for any valid ISBN-10/13 input, or '' when invalid. */
    public static function normalize($raw): string
    {
        $c = self::clean($raw);
        if (strlen($c) === 13 && self::isValid13($c)) return $c;
        if (strlen($c) === 10 && self::isValid10($c)) return self::to13($c);
        return '';
    }
}
