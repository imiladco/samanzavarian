<?php

/**
 * پیشخوان درخواست‌ها — ساخته‌شده از روی «کیت پیشخوان».
 *
 * سه صفحه زیر یک منو: نمای کلی، درخواست‌ها (فهرست و جزئیات)، تنظیمات (و
 * وضعیت سیستم). داده همان درخواست‌های فرم پروژه است (Leads)؛ این‌جا فقط
 * دیدن و رسیدگی به آن‌هاست.
 *
 * به PHP 8.1 نیاز دارد (enum، match، readonly)؛ Leads::kit() پیش از بار شدن
 * این کلاس‌ها بررسی می‌کند و روی نسخه‌های پایین‌تر فهرست ساده وردپرس می‌ماند.
 */

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin;

use Samanzavarian_Widgets\Leads;

if (!defined('ABSPATH')) {
    exit;
}

final class Kit
{
    /** پیشوند نامک همه صفحه‌ها؛ admin.js و Ui با همین صفحه‌های پیشخوان را می‌شناسند */
    public const SLUG_PREFIX = 'szw';

    public const CAPABILITY = 'manage_options';

    /** وضعیت ← [برچسب، لحن نشان] */
    public const STATUSES = [
        'new'     => ['تازه', 'info'],
        'talking' => ['در گفتگو', 'warn'],
        'won'     => ['قرارداد', 'ok'],
        'lost'    => ['منتفی', 'muted'],
        'spam'    => ['اسپم', 'error'],
    ];

    /** نماهای فهرست ← برچسب */
    public const VIEWS = ['all' => 'همه', 'new' => 'تازه', 'talking' => 'در گفتگو', 'won' => 'قرارداد', 'lost' => 'منتفی', 'spam' => 'اسپم'];

    /** نام وضعیت در رویدادها: «وضعیت: در گفتگو» */
    public static function statusText(string $status): string
    {
        return 'وضعیت: ' . (self::STATUSES[$status][0] ?? $status);
    }

    public static function boot(): void
    {
        if (!is_admin()) {
            return;
        }

        Ui::register();
        DashboardPage::register();
        RequestsPage::register();
        SettingsPage::register();

        // نشانی‌های قدیمی فهرست وردپرس (پیوندهای تلگرام پیش از این نسخه) به پیشخوان تازه می‌روند
        add_action('load-edit.php', static function (): void {
            if (($_GET['post_type'] ?? '') === Leads::TYPE) {
                wp_safe_redirect(Ui::url(RequestsPage::SLUG));
                exit;
            }
        });
        add_action('load-post.php', static function (): void {
            $id = absint($_GET['post'] ?? 0);
            if ($id && get_post_type($id) === Leads::TYPE) {
                wp_safe_redirect(Ui::url(RequestsPage::SLUG, ['item' => $id]));
                exit;
            }
        });
    }

    /** نشانی صفحه یک درخواست؛ همان که در پیام تلگرام می‌رود */
    public static function requestUrl(int $id): string
    {
        return Ui::url(RequestsPage::SLUG, ['item' => $id]);
    }

    /** «Hi {first}» ← متن آماده با نام و نیازهای همین درخواست */
    public static function fill(string $template, array $r): string
    {
        $first = strtok((string) $r['name'], ' ') ?: (string) $r['name'];
        $needs = $r['services'] ? implode(' and ', array_map('strtolower', (array) $r['services'])) : 'your project';

        return strtr($template, ['{first}' => $first, '{name}' => (string) $r['name'], '{needs}' => $needs]);
    }
}
