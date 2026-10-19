<?php

/**
 * نمای کلی درخواست‌ها:
 *
 *   ۱. عنوان + دکمه‌ها
 *   ۲. سلامت: تلگرام، کرون، فرم روی سایت؛ هر مشکل یک هشدار با علت و دکمه رفع
 *   ۳. بازه + کاشی‌ها: درخواست، منتظر پاسخ، زمان پاسخ، قرارداد
 *   ۴. دوستونه: روند روزانه (پاسخ‌داده / منتظر) | وضعیت‌ها
 *   ۵. دوستونه: تازه‌ترین‌ها | نیازها، بودجه و زمان‌بندی
 */

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin;

use Samanzavarian_Widgets\Leads;
use Samanzavarian_Widgets\Telegram;
use Samanzavarian_Widgets\Admin\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class DashboardPage
{
    public const SLUG = 'szw';

    private const RANGES = [7, 30, 90];

    public static function register(): void
    {
        add_action('admin_menu', static function (): void {
            $page  = new self();
            $count = Leads::counts()['new'];
            // نشان کنار منو: فقط چیزی که کار دارد (درخواست تازه)
            $badge = $count > 0 ? ' <span class="awaiting-mod">' . esc_html(Fa::digits($count)) . '</span>' : '';

            add_menu_page('درخواست‌ها', 'درخواست‌ها' . $badge, Kit::CAPABILITY, self::SLUG, [$page, 'render'], 'dashicons-email-alt', 26);
            add_submenu_page(self::SLUG, 'نمای کلی', 'نمای کلی', Kit::CAPABILITY, self::SLUG, [$page, 'render'], 0);
        }, 9);
    }

    public function render(): void
    {
        if (!current_user_can(Kit::CAPABILITY)) {
            wp_die('دسترسی ندارید.');
        }

        $range = isset($_GET['range']) ? absint($_GET['range']) : 30;
        $range = in_array($range, self::RANGES, true) ? $range : 30;
        $label = Fa::digits($range) . ' روز اخیر';
        $s     = Leads::stats($range);

        Ui::open(self::SLUG);
        Ui::head(
            'نمای کلی',
            'امروز ' . Fa::date(time()) . ' — درخواست‌های فرم پروژه و رسیدگی به آن‌ها',
            Ui::button('تنظیمات', Ui::url(SettingsPage::SLUG), 'ghost', 'settings')
                . Ui::button('همه درخواست‌ها', Ui::url(RequestsPage::SLUG), 'primary', 'inbox')
        );

        $this->renderHealth();

        echo '<div class="za-toolbar za-toolbar--range"><span class="za-muted">' . esc_html('آمار ' . $label . ' — در مقایسه با ' . Fa::digits($range) . ' روز پیش از آن') . '</span><nav class="za-seg" aria-label="بازه">';
        foreach (self::RANGES as $r) {
            printf('<a href="%s" class="za-seg__item%s"%s>%s</a>', esc_url(Ui::url(self::SLUG, ['range' => $r])), $r === $range ? ' is-current' : '', $r === $range ? ' aria-current="page"' : '', esc_html(Fa::digits($r) . ' روز'));
        }
        echo '</nav></div>';

        $p      = $s['period'];
        $b      = $s['prev'];
        $counts = Leads::counts();
        $oldest = $this->oldestWaiting();
        $rate   = $p['closed'] > 0 ? (int) round($p['won'] / $p['closed'] * 100) : null;

        echo '<div class="za-tiles">'
            . Ui::tile('درخواست', 'inbox', Fa::num($p['count']), $label, $s['spam'] > 0 ? 'اسپم کنار گذاشته: ' . Fa::num($s['spam']) : 'بدون اسپم', $p['count'], $b['count'])
            . Ui::tile('منتظر پاسخ', 'clock', Fa::num($counts['new']), 'همین الان', $oldest > 0 ? 'قدیمی‌ترین: ' . Fa::ago($oldest) : 'همه پاسخ گرفته‌اند')
            . Ui::tile('زمان پاسخ', 'reply', $p['wait'] >= 0 ? self::duration($p['wait']) : '—', $label, $b['wait'] >= 0 ? 'دوره قبل: ' . self::duration($b['wait']) : 'میانه، از رسیدن تا اولین پاسخ')
            . Ui::tile('قرارداد', 'trophy', Fa::num($p['won']), $label, $rate !== null ? Fa::digits($rate) . '٪ از بسته‌شده‌ها' : 'هنوز چیزی بسته نشده', $p['won'], $b['won'])
            . '</div>';

        echo '<div class="za-grid za-grid--main">';
        $this->renderChart($s['days'], $label);
        $this->renderStatuses($s['statuses'], $label);
        echo '</div>';

        echo '<div class="za-grid za-grid--main">';
        $this->renderRecent();
        echo '<div class="za-stack">';
        $this->renderTally('نیازها', 'spark', $s['services'], $label, 'هر درخواست می‌تواند چند نیاز داشته باشد.');
        $this->renderTally('بودجه', 'wallet', $s['budgets'], $label);
        $this->renderTally('زمان‌بندی', 'clock', $s['timeline'], $label);
        echo '</div></div>';

        Ui::close();
    }

    /** «۳ ساعت»، «۲ روز»، «۱۵ دقیقه» */
    public static function duration(int $seconds): string
    {
        return match (true) {
            $seconds < 3600  => Fa::digits(max(1, intdiv($seconds, 60))) . ' دقیقه',
            $seconds < 86400 => Fa::digits((int) round($seconds / 3600)) . ' ساعت',
            default          => Fa::digits((int) round($seconds / 86400)) . ' روز',
        };
    }

    private function oldestWaiting(): int
    {
        $ts = 0;
        foreach (Leads::rows() as $r) {
            if ($r['status'] === 'new') {
                $ts = $r['ts']; // newest first: the last one seen is the oldest
            }
        }

        return $ts;
    }

    /** هر مشکل: [لحن، عنوان، علت و اثر، نشانی رفع، برچسب دکمه] */
    private function renderHealth(): void
    {
        $issues = [];
        $tg     = Ui::url(SettingsPage::SLUG, ['tab' => 'telegram']);
        $last   = get_option('szw_tg_last', []);

        if (!Settings::bool('tg_enabled')) {
            $issues[] = ['warn', 'خبر تلگرام خاموش است', 'درخواست‌ها فقط در همین پیشخوان می‌مانند؛ سر بزنید تا چیزی جا نماند.', $tg, 'روشن کردن'];
        } elseif (Settings::text('tg_token') === '') {
            $issues[] = ['error', 'ربات تلگرام وصل نشده', 'تا توکن ربات را وارد نکنید، درخواست تازه در تلگرام خبر داده نمی‌شود.', $tg, 'وصل کردن'];
        } elseif (Settings::text('tg_chat') === '') {
            $issues[] = ['error', 'چت تلگرام معلوم نیست', 'ربات را در تلگرام Start کنید و در تنظیمات «پیدا کردن چت» را بزنید.', $tg, 'پیدا کردن چت'];
        } elseif (is_array($last) && isset($last['ok']) && !$last['ok']) {
            $issues[] = ['error', 'آخرین پیام به تلگرام نرسید', 'تلگرام گفت: ' . ($last['error'] ?? '') . ' — درخواست ذخیره است و دوباره امتحان می‌شود.', Ui::url(SettingsPage::SLUG, ['tab' => 'status']), 'وضعیت سیستم'];
        }

        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $issues[] = ['warn', 'کارهای زمان‌بندی‌شده وردپرس خاموش است', 'تلاش دوباره برای تلگرام و پاک‌کردن خودکار انجام نمی‌شود مگر اینکه هاست خودش آن‌ها را اجرا کند.', Ui::url(SettingsPage::SLUG, ['tab' => 'status']), 'وضعیت سیستم'];
        }

        if (self::formPages() === 0) {
            $issues[] = ['info', 'فرم پروژه در هیچ صفحه‌ای نیست', 'ویجت «Project Form» را در المنتور روی صفحه تماس بگذارید تا درخواست‌ها برسند.', admin_url('edit.php?post_type=page'), 'صفحه‌ها'];
        }

        if ($issues === []) {
            echo '<div class="za-health za-health--ok">' . Ui::icon('check', 18) . '<strong>همه‌چیز سالم است</strong><span>فرم روی سایت است و تلگرام وصل است.</span></div>';
            return;
        }

        echo '<div class="za-issues">';
        foreach ($issues as [$tone, $title, $text, $url, $action]) {
            echo Ui::alert($tone, $text, $title, Ui::button($action, $url, 'sm'));
        }
        echo '</div>';
    }

    /** صفحه‌های منتشرشده‌ای که ویجت فرم پروژه دارند */
    public static function formPages(): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_elementor_data' AND pm.meta_value LIKE %s AND p.post_status = 'publish'",
                '%' . $wpdb->esc_like('"widgetType":"sz-form"') . '%'
            )
        );
    }

    /**
     * نمودار ستونی دوبخشی: پاسخ‌داده (سرمه‌ای) و منتظر پاسخ (نارنجی)؛ نمای جدولی همان داده زیرش
     *
     * @param list<array{ts:int,a:int,b:int}> $days
     */
    private function renderChart(array $days, string $label): void
    {
        $max   = max(1, ...array_map(static fn (array $d): int => $d['a'] + $d['b'], $days));
        $step  = $max <= 5 ? 1 : ($max <= 20 ? 5 : 10);
        $top   = (int) (ceil($max / $step) * $step);
        $total = array_sum(array_map(static fn (array $d): int => $d['a'] + $d['b'], $days));

        Ui::cardOpen('روند ' . $label, 'chart', '<div class="za-legend"><span><i class="za-sw za-sw--a"></i>پاسخ‌داده</span><span><i class="za-sw za-sw--b"></i>منتظر پاسخ</span></div>', 'za-card--chart');

        if ($total === 0) {
            echo Ui::empty('chart', 'در این بازه درخواستی نرسیده', 'با اولین درخواست از فرم، نمودار پر می‌شود.');
            Ui::cardClose();
            return;
        }

        $many = count($days) > 31;
        echo '<div class="za-chart' . (count($days) >= 30 ? ' za-chart--30' : '') . ($many ? ' za-chart--90' : '') . '" role="img" aria-label="' . esc_attr('تعداد درخواست در ' . $label . '؛ جمع ' . Fa::digits($total)) . '">';
        echo '<div class="za-chart__axis"><span>' . esc_html(Fa::digits($top)) . '</span><span>' . esc_html(Fa::digits(intdiv($top, 2))) . '</span><span>۰</span></div>';
        echo '<div class="za-chart__plot"><i class="za-chart__grid" style="bottom:50%"></i><i class="za-chart__grid" style="bottom:100%"></i>';

        $last  = count($days) - 1;
        $every = $last < 7 ? 1 : ($many ? 15 : 5);
        foreach ($days as $i => $d) {
            $tip = Fa::date($d['ts']) . ' — پاسخ‌داده: ' . Fa::digits($d['a']) . '، منتظر: ' . Fa::digits($d['b']);

            echo '<div class="za-chart__col' . ($i === $last ? ' is-today' : '') . '" data-tip="' . esc_attr($tip) . '" tabindex="0" aria-label="' . esc_attr($tip) . '"><div class="za-chart__bars">';
            if ($d['b'] > 0) {
                echo '<i class="za-chart__seg za-chart__seg--b" style="height:' . round($d['b'] / $top * 100, 2) . '%"></i>';
            }
            if ($d['a'] > 0) {
                echo '<i class="za-chart__seg za-chart__seg--a" style="height:' . round($d['a'] / $top * 100, 2) . '%"></i>';
            }
            echo '</div>' . (($last - $i) % $every === 0 ? '<span class="za-chart__x">' . esc_html($i === $last ? 'امروز' : ($last < 7 ? Fa::digits(Fa::jalali($d['ts'])[2]) : Fa::dayMonth($d['ts']))) . '</span>' : '') . '</div>';
        }
        echo '</div></div>';

        echo '<details class="za-tableview"><summary>نمایش جدول</summary><table class="za-table za-table--compact"><thead><tr><th>روز</th><th>پاسخ‌داده</th><th>منتظر پاسخ</th></tr></thead><tbody>';
        foreach (array_reverse($days) as $d) {
            if ($d['a'] + $d['b'] === 0) {
                continue;
            }
            printf('<tr><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html(Fa::date($d['ts'])), esc_html(Fa::digits($d['a'])), esc_html(Fa::digits($d['b'])));
        }
        echo '</tbody></table></details>';

        Ui::cardClose();
    }

    /** @param array<string,int> $statuses میله‌های افقی با رنگ لحن هر وضعیت */
    private function renderStatuses(array $statuses, string $label): void
    {
        Ui::cardOpen('وضعیت درخواست‌ها', 'shield', '<span class="za-muted">' . esc_html($label) . '</span>');

        $total = array_sum($statuses);
        if ($total === 0) {
            echo Ui::empty('inbox', 'هنوز درخواستی نیست');
            Ui::cardClose();
            return;
        }

        $max = max($statuses);
        echo '<ul class="za-bars">';
        foreach ($statuses as $key => $n) {
            [$text, $tone] = Kit::STATUSES[$key];
            $pct = (int) round($n / $total * 100);
            echo '<li data-tip="' . esc_attr($text . ': ' . Fa::digits($n) . ' درخواست — ' . Fa::digits($pct) . '٪') . '" tabindex="0">'
                . '<span class="za-bars__k"><a href="' . esc_url(Ui::url(RequestsPage::SLUG, ['view' => $key])) . '">' . Ui::pill($text, $tone) . '</a></span>'
                . '<span class="za-bars__track"><i class="za-bars__fill za-bars__fill--' . esc_attr($tone) . '" style="width:' . ($n > 0 ? max(3, round($n / $max * 100, 1)) : 0) . '%"></i></span>'
                . '<span class="za-bars__n">' . esc_html(Fa::num($n)) . '</span></li>';
        }
        echo '</ul>';

        Ui::cardClose();
    }

    private function renderRecent(): void
    {
        $rows = Leads::search('all', '', 1, 8)['rows'];

        Ui::cardOpen('تازه‌ترین درخواست‌ها', 'inbox', Ui::button('همه', Ui::url(RequestsPage::SLUG), 'link', 'arrow'), 'za-card--flush');

        if ($rows === []) {
            echo Ui::empty('inbox', 'هنوز درخواستی نرسیده', 'وقتی کسی فرم پروژه را بفرستد، این‌جا می‌آید و در تلگرام خبر داده می‌شود.');
            Ui::cardClose();
            return;
        }

        echo '<ul class="za-feed">';
        foreach ($rows as $r) {
            [$state, $tone] = Kit::STATUSES[$r['status']];
            $needs = $r['services'] ? implode('، ', (array) $r['services']) : 'بدون نیاز مشخص';

            echo '<li><a href="' . esc_url(Ui::url(RequestsPage::SLUG, ['item' => (int) $r['id']])) . '">'
                . Ui::avatar((string) $r['name'], $r['status'] === 'new' ? '' : 'alt')
                . '<span class="za-feed__who"><strong>' . esc_html((string) $r['name']) . '</strong>'
                . '<small>' . esc_html(Fa::ago((int) $r['ts']) . ' — ' . $needs) . '</small></span>'
                . '<span class="za-feed__meta">' . Ui::pill($state, $tone) . '</span></a></li>';
        }
        echo '</ul>';

        Ui::cardClose();
    }

    /** @param array<string,int> $tally میله‌های افقی ساده؛ هر ردیف به فهرست فیلترشده می‌رود */
    private function renderTally(string $title, string $icon, array $tally, string $label, string $hint = ''): void
    {
        Ui::cardOpen($title, $icon, '<span class="za-muted">' . esc_html($label) . '</span>');

        if ($tally === []) {
            echo '<p class="za-hint">در این بازه کسی این را انتخاب نکرده.</p>';
            Ui::cardClose();
            return;
        }

        $max   = max($tally);
        $param = $icon === 'spark' ? 'svc' : ($icon === 'wallet' ? 'budget' : '');
        echo '<ul class="za-bars za-bars--plain">';
        foreach (array_slice($tally, 0, 6, true) as $k => $n) {
            $name = '<span dir="auto">' . esc_html((string) $k) . '</span>';
            echo '<li><span class="za-bars__k">' . ($param !== '' ? '<a href="' . esc_url(Ui::url(RequestsPage::SLUG, [$param => (string) $k])) . '">' . $name . '</a>' : $name) . '</span>'
                . '<span class="za-bars__track"><i class="za-bars__fill za-bars__fill--brand" style="width:' . max(3, round($n / $max * 100, 1)) . '%"></i></span>'
                . '<span class="za-bars__n">' . esc_html(Fa::num($n)) . '</span></li>';
        }
        echo '</ul>' . ($hint !== '' ? '<p class="za-hint">' . esc_html($hint) . '</p>' : '');

        Ui::cardClose();
    }
}
