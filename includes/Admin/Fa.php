<?php

/**
 * ارقام فارسی، تاریخ شمسی و زمان نسبی — به وقت تهران.
 *
 * منطقه زمانی هاست قابل اعتماد نیست؛ تبدیل‌ها همیشه صریحاً با
 * Asia/Tehran انجام می‌شوند. زمان‌ها در دیتابیس UTC (gmdate) ذخیره می‌شوند.
 */

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class Fa
{
    private const FA = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    private const AR = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    private const EN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    public const MONTHS = [1 => 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    /** ارقام لاتین ← فارسی، برای هر متنی که مدیر می‌خواند */
    public static function digits(string|int $value): string
    {
        return str_replace(self::EN, self::FA, (string) $value);
    }

    /** ارقام فارسی و عربی ← لاتین، برای هر ورودی عددی کاربر */
    public static function latin(string $value): string
    {
        return str_replace(self::AR, self::EN, str_replace(self::FA, self::EN, $value));
    }

    /** فقط ارقام (لاتین‌شده) */
    public static function only(string $value): string
    {
        return preg_replace('/\D+/', '', self::latin($value)) ?? '';
    }

    /** ۱۲٬۳۴۵ */
    public static function num(int|float $n): string
    {
        return self::digits(number_format((float) $n));
    }

    /** «۱۲۰,۰۰۰,۰۰۰ تومان» */
    public static function toman(int $amount): string
    {
        return self::num($amount) . ' تومان';
    }

    /** «۱۴۰۵/۰۷/۱۶» یا با ساعت «۱۴۰۵/۰۷/۱۶ ۱۴:۳۲» */
    public static function date(int $timestamp, bool $withTime = false): string
    {
        if ($timestamp <= 0) {
            return '—';
        }

        [$y, $m, $d] = self::jalali($timestamp);
        $out = self::digits(sprintf('%04d/%02d/%02d', $y, $m, $d));

        return $withTime ? $out . ' ' . self::digits(self::tehran($timestamp)->format('H:i')) : $out;
    }

    /** «۳ مهر» */
    public static function dayMonth(int $timestamp): string
    {
        [, $m, $d] = self::jalali($timestamp);

        return self::digits((string) $d) . ' ' . self::MONTHS[$m];
    }

    /** «۵ دقیقه پیش» */
    public static function ago(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '—';
        }

        $s = max(0, time() - $timestamp);

        return match (true) {
            $s < 60    => 'همین الان',
            $s < 3600  => self::digits(intdiv($s, 60)) . ' دقیقه پیش',
            $s < 86400 => self::digits(intdiv($s, 3600)) . ' ساعت پیش',
            default    => self::digits(intdiv($s, 86400)) . ' روز پیش',
        };
    }

    /** زمان UTC دیتابیس («Y-m-d H:i:s») ← timestamp */
    public static function fromSql(?string $utc): int
    {
        return $utc !== null && $utc !== '' ? (int) strtotime($utc . ' UTC') : 0;
    }

    public static function tehran(int $timestamp): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone('Asia/Tehran'));
    }

    /** @return array{0:int,1:int,2:int} سال، ماه، روز شمسی */
    public static function jalali(int $timestamp): array
    {
        $t  = self::tehran($timestamp);
        $gy = (int) $t->format('Y');
        $gm = (int) $t->format('n');
        $gd = (int) $t->format('j');

        $gDays = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2   = $gm > 2 ? $gy + 1 : $gy;
        $days  = 355666 + 365 * $gy + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400) + $gd + $gDays[$gm - 1];

        $jy    = -1595 + 33 * intdiv($days, 12053);
        $days %= 12053;
        $jy   += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy  += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        return $days < 186
            ? [$jy, 1 + intdiv($days, 31), 1 + $days % 31]
            : [$jy, 7 + intdiv($days - 186, 30), 1 + ($days - 186) % 30];
    }
}
