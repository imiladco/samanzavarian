<?php

/**
 * Numbers, dates and "5 min ago" for the Requests panel, in the site's own
 * time zone (Settings → General). Stored times are UTC.
 */

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class Fmt
{
    /** Digits only, from any input (Persian and Arabic digits read as Latin). */
    public static function only(string $value): string
    {
        $value = str_replace(['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], range(0, 9), $value);
        $value = str_replace(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], range(0, 9), $value);

        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /** 12,345 */
    public static function num(int|float $n): string
    {
        return number_format((float) $n);
    }

    /** "19 Oct 2026", or with the time "19 Oct 2026, 14:32" */
    public static function date(int $timestamp, bool $withTime = false): string
    {
        if ($timestamp <= 0) {
            return '—';
        }

        return self::local($timestamp)->format($withTime ? 'j M Y, H:i' : 'j M Y');
    }

    /** "3 Oct" */
    public static function dayMonth(int $timestamp): string
    {
        return self::local($timestamp)->format('j M');
    }

    /** Day of the month: "3" */
    public static function day(int $timestamp): string
    {
        return self::local($timestamp)->format('j');
    }

    /** "just now", "5 min ago", "3 hours ago", "2 days ago" */
    public static function ago(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '—';
        }

        $s = max(0, time() - $timestamp);

        return match (true) {
            $s < 60    => 'just now',
            $s < 3600  => intdiv($s, 60) . ' min ago',
            $s < 7200  => '1 hour ago',
            $s < 86400 => intdiv($s, 3600) . ' hours ago',
            $s < 172800 => '1 day ago',
            default    => intdiv($s, 86400) . ' days ago',
        };
    }

    /** "2 days", "5 hours", "15 min" */
    public static function duration(int $seconds): string
    {
        return match (true) {
            $seconds < 3600  => max(1, intdiv($seconds, 60)) . ' min',
            $seconds < 86400 => self::plural((int) round($seconds / 3600), 'hour'),
            default          => self::plural((int) round($seconds / 86400), 'day'),
        };
    }

    public static function plural(int $n, string $word): string
    {
        return self::num($n) . ' ' . $word . ($n === 1 ? '' : 's');
    }

    /** A stored UTC time ("Y-m-d H:i:s") as a timestamp */
    public static function fromSql(?string $utc): int
    {
        return $utc !== null && $utc !== '' ? (int) strtotime($utc . ' UTC') : 0;
    }

    public static function local(int $timestamp): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone(wp_timezone());
    }
}
