<?php

/**
 * درخواست‌ها — فهرست و جزئیات (الگوی ItemsPage کیت).
 *
 * فهرست:  نماها با شمارنده (تازه، در گفتگو، قرارداد، منتفی، اسپم)، جست‌وجو
 *         با کلید «/»، فیلتر نیاز، بودجه و زمان، انتخاب گروهی، جدول با ردیف
 *         کلیک‌پذیر، صفحه‌بندی، خروجی CSV، اعلان «درخواست تازه رسید».
 * جزئیات: قبلی/بعدی (← →)، سربرگ با پاسخ آماده (ایمیل، واتس‌اپ، تماس)،
 *         پیام، اطلاعات، رویدادها | وضعیت، یادداشت، تلگرام، حذف.
 *
 * همه اقدام‌ها POST با nonce و بررسی دسترسی‌اند و هر تغییر با نام مدیر در
 * رویدادهای همان درخواست ثبت می‌شود.
 */

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin;

use Samanzavarian_Widgets\Leads;
use Samanzavarian_Widgets\Telegram;
use Samanzavarian_Widgets\Admin\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class RequestsPage
{
    public const SLUG = 'szw-requests';

    private const NONCE    = 'szw_requests';
    private const LIVE     = 'szw_requests_live';
    private const CSV      = 'szw_requests_csv';
    private const PER_PAGE = 15;

    private const DELETE_NOTE = 'درخواست و رویدادهایش برای همیشه پاک می‌شوند.';

    /** @var list<array{ok:bool,text:string}> */
    private array $notices = [];

    public static function register(): void
    {
        $page = new self();

        add_action('admin_menu', static function () use ($page): void {
            add_submenu_page(DashboardPage::SLUG, 'درخواست‌ها', 'درخواست‌ها', Kit::CAPABILITY, self::SLUG, [$page, 'render'], 1);
        }, 20);

        add_action('wp_ajax_' . self::LIVE, [self::class, 'live']);
        add_action('admin_post_' . self::CSV, [self::class, 'csv']);
    }

    public function render(): void
    {
        if (!current_user_can(Kit::CAPABILITY)) {
            wp_die('دسترسی ندارید.');
        }

        $deleted = $this->maybeAct();
        $itemId  = isset($_GET['item']) && !$deleted ? absint($_GET['item']) : 0;

        Ui::open(self::SLUG);
        $itemId > 0 ? $this->renderDetail($itemId) : $this->renderList();
        Ui::close();
    }

    private static function me(): string
    {
        return (string) wp_get_current_user()->display_name;
    }

    /* ---- اقدام‌ها -------------------------------------------------------- */

    /** @return bool درخواستِ صفحه جزئیات حذف شد */
    private function maybeAct(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['szw_action'])) {
            return false;
        }

        check_admin_referer(self::NONCE);

        $action = sanitize_key((string) wp_unslash($_POST['szw_action']));
        $id     = absint($_POST['item'] ?? 0);
        $own    = $id > 0 && get_post_type($id) === Leads::TYPE;

        switch ($action) {
            case 'delete':
                $ok = $own && (bool) wp_delete_post($id, true);
                Leads::forget();
                $this->notices[] = $ok ? ['ok' => true, 'text' => 'درخواست حذف شد.'] : ['ok' => false, 'text' => 'این درخواست پیدا نشد؛ شاید پیش‌تر حذف شده.'];
                return $ok;

            case 'status':
                $status = sanitize_key((string) ($_POST['status'] ?? ''));
                $ok     = $own && isset(Kit::STATUSES[$status]) && Leads::set_status($id, $status, self::me(), Kit::statusText($status));
                $this->notices[] = $ok ? ['ok' => true, 'text' => 'وضعیت به «' . Kit::STATUSES[$status][0] . '» تغییر کرد.'] : ['ok' => false, 'text' => 'وضعیت تغییر نکرد.'];
                return false;

            case 'note':
                $note = trim(sanitize_textarea_field(wp_unslash((string) ($_POST['note'] ?? ''))));
                if ($own) {
                    update_post_meta($id, Leads::NOTE, $note);
                    Leads::log($id, $note === '' ? 'یادداشت پاک شد' : 'یادداشت ویرایش شد', 'info', self::me());
                }
                $this->notices[] = $own ? ['ok' => true, 'text' => 'یادداشت ذخیره شد.'] : ['ok' => false, 'text' => 'این درخواست پیدا نشد.'];
                return false;

            case 'telegram':
                $ok = $own && Telegram::ready() && Telegram::send($id);
                $this->notices[] = match (true) {
                    !Telegram::ready() => ['ok' => false, 'text' => 'تلگرام وصل نیست؛ در تنظیمات ← تلگرام وصلش کنید.'],
                    $ok                => ['ok' => true, 'text' => 'دوباره در تلگرام فرستاده شد.'],
                    default            => ['ok' => false, 'text' => 'تلگرام نپذیرفت: ' . (string) (get_post_meta($id, Telegram::META, true)['error'] ?? '')],
                };
                Leads::forget();
                return false;

            case 'bulk_talking':
            case 'bulk_lost':
            case 'bulk_new':
            case 'bulk_delete':
                $ids = array_slice(array_filter(array_map('absint', (array) ($_POST['items'] ?? []))), 0, 200);
                $to  = ['bulk_talking' => 'talking', 'bulk_lost' => 'lost', 'bulk_new' => 'new'][$action] ?? '';
                $n   = 0;
                foreach ($ids as $one) {
                    if (get_post_type($one) !== Leads::TYPE) {
                        continue;
                    }
                    $n += (int) ($action === 'bulk_delete' ? (bool) wp_delete_post($one, true) : Leads::set_status($one, $to, self::me(), Kit::statusText($to) . ' (گروهی)'));
                }
                Leads::forget();
                $this->notices[] = $n > 0
                    ? ['ok' => true, 'text' => Fa::digits($n) . ' درخواست ' . ($action === 'bulk_delete' ? 'حذف شد.' : '«' . Kit::STATUSES[$to][0] . '» شد.')]
                    : ['ok' => false, 'text' => 'درخواستی انتخاب نشده بود.'];
                return false;
        }

        return false;
    }

    /** دکمه حذف با تأیید؛ از جزئیات به فهرست برمی‌گردد */
    private function deleteButton(int $id, string $label, string $variant, bool $stay = false): string
    {
        return Ui::postButton(self::NONCE, ['szw_action' => 'delete', 'item' => $id], $label, $variant, 'trash',
            self::DELETE_NOTE . ' حذف شود؟', $stay ? '' : Ui::url(self::SLUG));
    }

    /* ---- فهرست ----------------------------------------------------------- */

    private function renderList(): void
    {
        $view = isset($_GET['view']) ? sanitize_key((string) $_GET['view']) : 'all';
        $view = isset(Kit::VIEWS[$view]) ? $view : 'all';
        $term = isset($_GET['s']) ? sanitize_text_field((string) wp_unslash($_GET['s'])) : '';
        $page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);

        $filters = self::filters();
        $keep    = array_filter(['s' => $term, 'svc' => $filters['service'] ?? '', 'budget' => $filters['budget'] ?? '', 'days' => (string) ($filters['days'] ?? '')], static fn (string $v): bool => $v !== '');
        $counts  = Leads::counts();
        $result  = Leads::search($view, Fa::latin($term), $page, self::PER_PAGE, $filters);

        $csv = Ui::button('خروجی CSV', wp_nonce_url(admin_url('admin-post.php?action=' . self::CSV), self::CSV), 'ghost', 'download');
        Ui::head('درخواست‌ها', Fa::num($counts['all']) . ' درخواست از فرم پروژه' . ($counts['spam'] > 0 ? ' — ' . Fa::num($counts['spam']) . ' اسپم کنار گذاشته شده' : ''), $csv);
        Ui::notices($this->notices);

        if ($counts['new'] > 0 && $view !== 'new') {
            echo Ui::alert('info', 'درخواست‌هایی که هنوز جوابی نگرفته‌اند. هرچه زودتر، بهتر.', Fa::digits($counts['new']) . ' درخواست تازه منتظر پاسخ است',
                Ui::button('نمایش', Ui::url(self::SLUG, ['view' => 'new']), 'sm'));
        }

        // نوار ابزار: نماها + جست‌وجو
        echo '<div class="za-toolbar"><nav class="za-seg" aria-label="نماها">';
        foreach (Kit::VIEWS as $key => $label) {
            if ($key === 'spam' && $counts['spam'] === 0 && $view !== 'spam') {
                continue;
            }
            printf(
                '<a href="%s" class="za-seg__item%s%s"%s>%s<span class="za-seg__count">%s</span></a>',
                esc_url(Ui::url(self::SLUG, ['view' => $key] + $keep)),
                $key === $view ? ' is-current' : '',
                $key === 'new' && $counts[$key] > 0 ? ' is-alert' : '',
                $key === $view ? ' aria-current="page"' : '',
                esc_html($label),
                esc_html(Fa::num($counts[$key]))
            );
        }
        echo '</nav>';

        printf(
            '<form method="get" class="za-search" role="search"><input type="hidden" name="page" value="%s"><input type="hidden" name="view" value="%s">%s'
            . '%s<input type="search" name="s" value="%s" placeholder="نام، ایمیل، تلفن یا متن پیام" aria-label="جست‌وجو (کلید /)" data-hotkey="/"><kbd class="za-kbd">/</kbd>%s</form>',
            esc_attr(self::SLUG),
            esc_attr($view),
            self::hidden(array_diff_key($keep, ['s' => 1])),
            Ui::icon('search', 16),
            esc_attr($term),
            $term !== '' ? '<a class="za-search__clear" href="' . esc_url(Ui::url(self::SLUG, ['view' => $view] + array_diff_key($keep, ['s' => 1]))) . '" aria-label="پاک کردن">' . Ui::icon('x', 14) . '</a>' : ''
        );
        echo '</div>';

        // فیلترها: با تغییر خودکار اعمال می‌شوند؛ گزینه‌ها همان‌هایی‌اند که در درخواست‌ها آمده
        $services = array_keys(Leads::tally('services'));
        $budgets  = array_keys(Leads::tally('budget'));
        $selects  = [
            'svc'    => ['نیاز', ['' => 'همه نیازها'] + array_combine($services, $services), $filters['service'] ?? ''],
            'budget' => ['بودجه', ['' => 'همه بودجه‌ها'] + array_combine($budgets, $budgets), $filters['budget'] ?? ''],
            'days'   => ['زمان', ['' => 'همه زمان‌ها', '1' => '۲۴ ساعت اخیر', '7' => '۷ روز اخیر', '30' => '۳۰ روز اخیر'], (string) ($filters['days'] ?? '')],
        ];
        echo '<form method="get" class="za-filters" data-autosubmit><input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '"><input type="hidden" name="view" value="' . esc_attr($view) . '">'
            . ($term !== '' ? '<input type="hidden" name="s" value="' . esc_attr($term) . '">' : '') . Ui::icon('filter', 15);
        foreach ($selects as $name => [$label, $options, $current]) {
            if (count($options) < 2) {
                continue;
            }
            echo '<label class="za-filter' . ($current !== '' ? ' is-set' : '') . '"><span class="screen-reader-text">' . esc_html($label) . '</span><select name="' . esc_attr($name) . '">';
            foreach ($options as $value => $text) {
                printf('<option value="%s"%s>%s</option>', esc_attr((string) $value), selected($current, (string) $value, false), esc_html((string) $text));
            }
            echo '</select></label>';
        }
        echo '<span class="za-filters__count">' . esc_html(Fa::num($result['total']) . ($filters !== [] || $term !== '' ? ' نتیجه' : ' درخواست')) . '</span>';
        if ($filters !== []) {
            echo '<a class="za-btn za-btn--link" href="' . esc_url(Ui::url(self::SLUG, ['view' => $view] + ($term !== '' ? ['s' => $term] : []))) . '">' . Ui::icon('x', 13) . '<span>حذف فیلترها</span></a>';
        }
        echo '<noscript><button class="za-btn za-btn--sm">اعمال</button></noscript></form>';

        // «درخواست تازه رسید»: هر ۳۰ ثانیه (admin.js ← data-live)
        if ($page === 1 && $view === 'all' && $filters === [] && $term === '') {
            printf('<div class="za-live" hidden data-live="%s" data-after="%d"></div>', esc_attr(wp_nonce_url(add_query_arg('action', self::LIVE, admin_url('admin-ajax.php')), self::LIVE)), Leads::latest_id());
        }

        echo '<section class="za-card za-card--flush">';

        if ($result['rows'] === []) {
            echo $term !== ''
                ? Ui::empty('search', 'نتیجه‌ای برای «' . $term . '» پیدا نشد', 'نام، ایمیل، شماره تلفن یا بخشی از متن پیام را امتحان کنید.')
                : Ui::empty('inbox', 'درخواستی در این نما نیست', match ($view) {
                    'new'   => 'به همه درخواست‌های تازه پاسخ داده‌اید.',
                    'spam'  => 'پیام اسپمی کنار گذاشته نشده.',
                    'all'   => 'وقتی کسی فرم پروژه را بفرستد، این‌جا می‌آید و در تلگرام خبر داده می‌شود.',
                    default => '',
                });
            echo '</section>';
            return;
        }

        // انتخاب گروهی: فرم جدا و چک‌باکس‌ها با form=؛ چون حذف هر ردیف فرم خودش را دارد
        echo '<form method="post" id="za-bulk" class="za-bulk" data-bulk>' . wp_nonce_field(self::NONCE, '_wpnonce', true, false)
            . '<span class="za-bulk__count"></span>'
            . ($view === 'spam'
                ? '<button type="submit" name="szw_action" value="bulk_new" class="za-btn za-btn--sm">' . Ui::icon('check', 14) . '<span>اسپم نیست</span></button>'
                : '<button type="submit" name="szw_action" value="bulk_talking" class="za-btn za-btn--sm">' . Ui::icon('reply', 14) . '<span>در گفتگو</span></button>'
                    . '<button type="submit" name="szw_action" value="bulk_lost" class="za-btn za-btn--sm">' . Ui::icon('x', 14) . '<span>منتفی</span></button>')
            . '<button type="submit" name="szw_action" value="bulk_delete" class="za-btn za-btn--sm za-btn--danger" data-confirm-click="' . esc_attr('درخواست‌های انتخاب‌شده حذف شوند؟ ' . self::DELETE_NOTE) . '">' . Ui::icon('trash', 14) . '<span>حذف</span></button>'
            . '<button type="button" class="za-btn za-btn--sm za-btn--link" data-bulk-clear>لغو انتخاب</button></form>';

        echo '<div class="za-table-wrap"><table class="za-table za-table--main"><thead><tr>'
            . '<th class="za-check-col"><input type="checkbox" data-bulk-all aria-label="انتخاب همه"></th><th>از طرف</th><th>نیاز</th><th>بودجه و زمان</th><th>وضعیت</th><th>رسیده</th><th><span class="screen-reader-text">عملیات</span></th>'
            . '</tr></thead><tbody>';

        foreach ($result['rows'] as $r) {
            $link = Ui::url(self::SLUG, ['item' => (int) $r['id']]);
            [$state, $tone] = Kit::STATUSES[$r['status']];

            printf(
                '<tr data-href="%1$s"%13$s><td class="za-check-col"><input type="checkbox" name="items[]" value="%2$d" form="za-bulk" aria-label="انتخاب"></td>'
                . '<td><a class="za-who" href="%1$s">%3$s<span><strong dir="auto">%4$s</strong><small dir="ltr">%5$s</small></span></a></td>'
                . '<td>%6$s</td>'
                . '<td><span dir="auto">%7$s</span><small class="za-cell-sub" dir="auto">%8$s</small></td><td>%9$s</td>'
                . '<td>%10$s<small class="za-cell-sub">%11$s</small></td><td class="za-row-actions">%12$s</td></tr>',
                esc_url($link),
                (int) $r['id'],
                Ui::avatar((string) $r['name'], $r['status'] === 'new' ? '' : 'alt'),
                esc_html((string) $r['name']),
                esc_html((string) $r['email']),
                self::needs((array) $r['services'], 2),
                esc_html($r['budget'] !== '' ? (string) $r['budget'] : '—'),
                esc_html((string) $r['timeline']),
                Ui::pill($state, $tone),
                esc_html(Fa::ago((int) $r['ts'])),
                esc_html(Fa::date((int) $r['ts'], true)),
                $this->deleteButton((int) $r['id'], 'حذف', 'icon', true),
                $r['status'] === 'new' ? ' class="is-unread"' : ''
            );
        }

        echo '</tbody></table></div>';

        $pages = (int) ceil($result['total'] / self::PER_PAGE);
        if ($pages > 1) {
            echo '<nav class="za-pager" aria-label="صفحه‌ها"><span class="za-muted">'
                . esc_html('نمایش ' . Fa::num(($page - 1) * self::PER_PAGE + 1) . ' تا ' . Fa::num(min($page * self::PER_PAGE, $result['total'])) . ' از ' . Fa::num($result['total']))
                . '</span><span class="za-pager__links">'
                . self::faLinks((string) paginate_links([
                    'base'      => add_query_arg('paged', '%#%', Ui::url(self::SLUG, ['view' => $view] + $keep)),
                    'format'    => '',
                    'current'   => $page,
                    'total'     => $pages,
                    'prev_text' => '‹ قبلی',
                    'next_text' => 'بعدی ›',
                ]))
                . '</span></nav>';
        }

        echo '</section>';
    }

    /** نیازها به‌صورت نشان؛ بیش از $max ← «+۲» */
    private static function needs(array $services, int $max = 99): string
    {
        if ($services === []) {
            return '<span class="za-muted">—</span>';
        }

        $html = '<span class="za-needs">';
        foreach (array_slice($services, 0, $max) as $s) {
            $html .= '<span class="za-need" dir="auto">' . esc_html((string) $s) . '</span>';
        }
        if (count($services) > $max) {
            $html .= '<span class="za-need za-need--more" title="' . esc_attr(implode('، ', array_slice($services, $max))) . '">+' . esc_html(Fa::digits(count($services) - $max)) . '</span>';
        }

        return $html . '</span>';
    }

    /* ---- جزئیات ---------------------------------------------------------- */

    private function renderDetail(int $id): void
    {
        $r     = Leads::find($id);
        $newer = Leads::neighbour($id, true);
        $older = Leads::neighbour($id, false);

        echo '<div class="za-crumbs"><a class="za-back" href="' . esc_url(Ui::url(self::SLUG)) . '">' . Ui::icon('back', 15) . 'همه درخواست‌ها</a><span class="za-pn">'
            . ($newer !== null ? '<a href="' . esc_url(Ui::url(self::SLUG, ['item' => $newer])) . '" title="تازه‌تر (کلید ←)" data-hotkey="ArrowRight">' . Ui::icon('chev-r', 16) . 'تازه‌تر</a>' : '<span>' . Ui::icon('chev-r', 16) . 'تازه‌تر</span>')
            . ($older !== null ? '<a href="' . esc_url(Ui::url(self::SLUG, ['item' => $older])) . '" title="قدیمی‌تر (کلید →)" data-hotkey="ArrowLeft">قدیمی‌تر' . Ui::icon('chev-l', 16) . '</a>' : '<span>قدیمی‌تر' . Ui::icon('chev-l', 16) . '</span>')
            . '</span></div>';

        if ($r === null) {
            Ui::notices($this->notices);
            echo '<section class="za-card">' . Ui::empty('search', 'درخواست پیدا نشد', 'شاید حذف شده باشد.') . '</section>';
            return;
        }

        [$state, $tone] = Kit::STATUSES[$r['status']];
        $phone   = (string) $r['phone'];
        $wa      = $phone !== '' ? Leads::wa_digits($phone) : '';
        $subject = Settings::text('reply_subject') !== '' ? Settings::text('reply_subject') : 'Re: your project request';
        $body    = Kit::fill(Settings::text('reply_email'), $r) . "\n\n\n— You wrote:\n> " . str_replace("\n", "\n> ", (string) $r['message']);
        $summary = implode(' — ', array_filter([(string) $r['name'], (string) $r['email'], $phone, implode(', ', (array) $r['services']), (string) $r['budget'], (string) $r['timeline']]));
        $dialog  = 'za-note-' . $id;

        $actions = '<a class="za-btn za-btn--primary" href="' . esc_url('mailto:' . $r['email'] . '?subject=' . rawurlencode($subject) . '&body=' . rawurlencode($body)) . '">' . Ui::icon('mail', 16) . '<span>پاسخ با ایمیل</span></a>'
            . ($wa !== '' ? '<a class="za-btn za-btn--ghost" target="_blank" rel="noopener" href="' . esc_url('https://wa.me/' . $wa . '?text=' . rawurlencode(Kit::fill(Settings::text('reply_wa'), $r))) . '">' . Ui::icon('whatsapp', 16) . '<span>واتس‌اپ</span></a>' : '')
            . ($phone !== '' ? '<a class="za-btn za-btn--ghost" href="tel:' . esc_attr(preg_replace('/[^+0-9]/', '', $phone)) . '">' . Ui::icon('phone', 16) . '<span>تماس</span></a>' : '')
            . '<button type="button" class="za-btn za-btn--ghost" data-copy-text="' . esc_attr($summary) . '">' . Ui::icon('copy', 16) . '<span>کپی خلاصه</span></button>';

        echo '<div class="za-profile">' . Ui::avatar((string) $r['name'], $r['status'] === 'new' ? '' : 'alt') . '<div class="za-profile__text">'
            . '<h2 class="za-title" dir="auto">' . esc_html((string) $r['name']) . '</h2><div class="za-profile__meta">'
            . Ui::pill($state, $tone)
            . '<span class="za-muted">' . Ui::icon('clock', 13) . esc_html('رسیده ' . Fa::ago((int) $r['ts']) . ' — ' . Fa::date((int) $r['ts'], true)) . '</span>'
            . '</div></div><div class="za-head__actions">' . $actions . '</div></div>';

        Ui::notices($this->notices);

        if ($r['status'] === 'new') {
            echo Ui::alert('info', 'بعد از پاسخ، وضعیت را «در گفتگو» کنید تا از فهرست «تازه» بیرون برود و زمان پاسخ شما در نمای کلی حساب شود.', 'منتظر پاسخ شماست',
                Ui::postButton(self::NONCE, ['szw_action' => 'status', 'item' => $id, 'status' => 'talking'], 'پاسخ دادم', 'sm', 'check'));
        }

        echo '<div class="za-grid za-grid--detail"><div class="za-stack">';

        // پیام: همان چیزی که نوشته، درشت و خوانا
        Ui::cardOpen('پیام', 'note', '<button type="button" class="za-btn za-btn--sm za-btn--ghost" data-copy-text="' . esc_attr((string) $r['message']) . '">' . Ui::icon('copy', 14) . '<span>کپی</span></button>');
        echo '<blockquote class="za-quote" dir="auto">' . nl2br(esc_html((string) $r['message'])) . '</blockquote>';
        $chips = '';
        foreach ([['spark', $r['services'] ? implode('، ', (array) $r['services']) : ''], ['clock', (string) $r['timeline']], ['wallet', (string) $r['budget']]] as [$icon, $text]) {
            if ($text !== '') {
                $chips .= '<span class="za-fact">' . Ui::icon($icon, 14) . '<span dir="auto">' . esc_html($text) . '</span></span>';
            }
        }
        echo $chips !== '' ? '<div class="za-facts">' . $chips . '</div>' : '';
        Ui::cardClose();

        Ui::cardOpen('اطلاعات', 'user');
        $page = (string) $r['page'];
        echo Ui::kv([
            'نام'          => ['html' => '<span dir="auto">' . esc_html((string) $r['name']) . '</span>'],
            'ایمیل'        => ['html' => '<span dir="ltr" data-copy="' . esc_attr((string) $r['email']) . '" title="کپی">' . esc_html((string) $r['email']) . '</span>'],
            'تلفن'         => $phone !== '' ? ['html' => '<span dir="ltr" data-copy="' . esc_attr($phone) . '" title="کپی">' . esc_html($phone) . '</span>'] : '',
            'نیاز'         => ['html' => self::needs((array) $r['services'])],
            'زمان‌بندی'     => (string) $r['timeline'],
            'بودجه'        => (string) $r['budget'],
            'لینک مرجع'    => $r['link'] !== '' ? ['html' => '<a href="' . esc_url((string) $r['link']) . '" target="_blank" rel="noopener noreferrer" dir="ltr">' . esc_html(preg_replace('#^https?://(www\.)?#', '', (string) $r['link'])) . Ui::icon('external', 13) . '</a>'] : '',
            'از صفحه'      => $page !== '' ? ['html' => '<a href="' . esc_url($page) . '" target="_blank" rel="noopener" dir="ltr">' . esc_html((wp_parse_url($page, PHP_URL_PATH) ?: '/') . (wp_parse_url($page, PHP_URL_QUERY) ? '?' . wp_parse_url($page, PHP_URL_QUERY) : '')) . '</a>'] : '',
            'رسیده'        => Fa::date((int) $r['ts'], true),
            'اولین پاسخ'   => $r['first'] > 0 ? Fa::date((int) $r['first'], true) . ' — بعد از ' . DashboardPage::duration((int) $r['first'] - (int) $r['ts']) : '',
        ]);
        Ui::cardClose();

        $this->renderLog($id);

        echo '</div><div class="za-stack">';

        Ui::cardOpen('وضعیت', 'shield');
        echo '<form method="post" class="za-status-form">' . wp_nonce_field(self::NONCE, '_wpnonce', true, false)
            . '<input type="hidden" name="szw_action" value="status"><input type="hidden" name="item" value="' . $id . '">'
            . '<select class="za-select" name="status" aria-label="وضعیت">';
        foreach (Kit::STATUSES as $key => [$text]) {
            printf('<option value="%s"%s>%s</option>', esc_attr($key), selected($r['status'], $key, false), esc_html($text));
        }
        echo '</select><button type="submit" class="za-btn za-btn--primary">' . Ui::icon('check', 16) . '<span>ثبت</span></button></form>'
            . '<p class="za-hint">تازه ← در گفتگو ← قرارداد یا منتفی. هر تغییر با نام شما در رویدادها ثبت می‌شود.</p>';
        Ui::cardClose();

        $note = Leads::note($id);
        Ui::cardOpen('یادداشت شما', 'edit', Ui::dialogButton($note === '' ? 'نوشتن' : 'ویرایش', $dialog, 'sm', 'edit'));
        echo $note !== '' ? '<p class="za-note" dir="auto">' . nl2br(esc_html($note)) . '</p>' : '<p class="za-hint">فقط در همین پیشخوان دیده می‌شود؛ مثلاً مبلغ پیشنهادی یا قرار بعدی.</p>';
        Ui::cardClose();

        $this->renderTelegram($id);

        Ui::cardOpen('حذف درخواست', 'trash', '', 'za-card--danger');
        echo '<p class="za-hint">' . esc_html(self::DELETE_NOTE) . '</p>' . $this->deleteButton($id, 'حذف این درخواست', 'danger');
        Ui::cardClose();

        echo '</div></div>';

        Ui::dialogOpen($dialog, 'یادداشت', 'برای ' . $r['name'] . ' — فقط شما می‌بینید.', self::NONCE, Ui::url(self::SLUG, ['item' => $id]), (string) $r['name']);
        echo '<input type="hidden" name="item" value="' . $id . '">'
            . '<textarea class="za-textarea" name="note" rows="5" dir="auto" placeholder="مثلاً: پیشنهاد ۲۵ میلیون، تماس دوباره پنج‌شنبه">' . esc_textarea($note) . '</textarea>';
        Ui::dialogClose('ذخیره', 'szw_action', 'note');
    }

    private function renderTelegram(int $id): void
    {
        $tg    = get_post_meta($id, Telegram::META, true);
        $tg    = is_array($tg) ? $tg : [];
        $state = (string) ($tg['state'] ?? '');
        $resend = Telegram::ready()
            ? Ui::postButton(self::NONCE, ['szw_action' => 'telegram', 'item' => $id], $state === 'sent' ? 'دوباره بفرست' : 'بفرست', 'sm', 'telegram')
            : Ui::button('وصل کردن', Ui::url(SettingsPage::SLUG, ['tab' => 'telegram']), 'sm');

        Ui::cardOpen('تلگرام', 'telegram', $resend);
        echo match ($state) {
            'sent'    => Ui::state('ok', 'در تلگرام خبر داده شد' . (!empty($tg['at']) ? ' — ' . Fa::ago((int) $tg['at']) : '')),
            'failed'  => Ui::state('error', 'نرسید: ' . (string) ($tg['error'] ?? '')) . '<p class="za-hint">' . esc_html((int) ($tg['tries'] ?? 0) < Telegram::TRIES ? 'خودکار دوباره امتحان می‌شود.' : 'دیگر خودکار امتحان نمی‌شود؛ دکمه را بزنید.') . '</p>',
            'pending' => Ui::state('warn', 'در صف فرستادن'),
            default   => Ui::state('muted', Leads::status($id) === 'spam' ? 'اسپم به تلگرام فرستاده نمی‌شود' : 'فرستاده نشده (تلگرام آن موقع وصل نبود)'),
        };
        Ui::cardClose();
    }

    /** تایم‌لاین رویدادها، تازه‌ترین بالا */
    private function renderLog(int $id): void
    {
        $entries = array_reverse(Leads::events($id));

        Ui::cardOpen('رویدادها', 'clock', '<span class="za-muted">' . esc_html(Fa::num(count($entries)) . ' رویداد') . '</span>');

        if ($entries === []) {
            echo '<p class="za-hint">رویدادی ثبت نشده.</p>';
            Ui::cardClose();
            return;
        }

        echo '<ol class="za-timeline">';
        foreach ($entries as $e) {
            $tone = match ($e['level'] ?? '') {
                'error'   => 'error',
                'warning' => 'warn',
                default   => 'muted',
            };
            echo '<li class="za-timeline__item za-timeline__item--' . $tone . '"><i></i><div><strong>' . esc_html(self::say((string) $e['message'])) . '</strong>'
                . '<small>' . esc_html(Fa::date(Fa::fromSql((string) $e['at']), true) . (($e['by'] ?? '') !== '' ? ' — ' . $e['by'] : '')) . '</small></div></li>';
        }
        echo '</ol>';

        Ui::cardClose();
    }

    /** رویدادهای ثبت‌شده از سمت سایت (انگلیسی) به فارسی */
    private static function say(string $message): string
    {
        $map = [
            'Received from the form'                              => 'از فرم پروژه رسید',
            'Received, kept aside as spam (a message of links)'   => 'رسید و چون پر از لینک بود، اسپم شد',
            'Announced on Telegram'                               => 'در تلگرام خبر داده شد',
        ];
        if (isset($map[$message])) {
            return $map[$message];
        }
        if (str_starts_with($message, 'Telegram did not take it: ')) {
            return 'تلگرام نپذیرفت: ' . substr($message, 27);
        }
        if (isset(Kit::STATUSES[$message])) {
            return Kit::statusText($message);
        }

        return $message;
    }

    /* ---- کمکی ------------------------------------------------------------ */

    /** پاسخ ajax اعلان «درخواست تازه رسید» */
    public static function live(): never
    {
        check_ajax_referer(self::LIVE);

        if (!current_user_can(Kit::CAPABILITY)) {
            wp_send_json_error(null, 403);
        }

        wp_send_json_success(['count' => Leads::count_after(absint($_GET['after'] ?? 0))]);
    }

    public static function csv(): void
    {
        check_admin_referer(self::CSV);
        if (!current_user_can(Kit::CAPABILITY)) {
            wp_die('دسترسی ندارید.');
        }
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=requests-' . gmdate('Y-m-d') . '.csv');
        echo Leads::csv_text(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a CSV download
        exit;
    }

    /** @return array{service?:string,budget?:string,days?:int} */
    private static function filters(): array
    {
        $out     = [];
        $service = isset($_GET['svc']) ? sanitize_text_field(wp_unslash((string) $_GET['svc'])) : '';
        $budget  = isset($_GET['budget']) ? sanitize_text_field(wp_unslash((string) $_GET['budget'])) : '';
        $days    = isset($_GET['days']) ? absint($_GET['days']) : 0;

        if ($service !== '') {
            $out['service'] = $service;
        }
        if ($budget !== '') {
            $out['budget'] = $budget;
        }
        if (in_array($days, [1, 7, 30], true)) {
            $out['days'] = $days;
        }

        return $out;
    }

    /** ارقام متن پیوندها فارسی شوند، نه نشانی‌ها */
    private static function faLinks(string $html): string
    {
        return preg_replace_callback('/>([^<]+)</u', static fn (array $m): string => '>' . Fa::digits($m[1]) . '<', $html) ?? $html;
    }

    /** @param array<string,string> $fields */
    private static function hidden(array $fields): string
    {
        $html = '';
        foreach ($fields as $name => $value) {
            $html .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
        }

        return $html;
    }
}
