<?php

/**
 * تنظیمات — الگو: فهرست بخش‌ها در ستون کناری، هر بخش یک کارت فرم که از
 * Schema ساخته می‌شود، نوار ذخیره چسبان («تغییرات ذخیره نشده»، Ctrl+S،
 * هشدار هنگام ترک صفحه)، و بخش «وضعیت سیستم» با کارت‌های سلامت و دکمه آزمایش.
 */

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin;

use Samanzavarian_Widgets\Leads;
use Samanzavarian_Widgets\Telegram;
use Samanzavarian_Widgets\Admin\Settings\Field;
use Samanzavarian_Widgets\Admin\Settings\FieldType;
use Samanzavarian_Widgets\Admin\Settings\Schema;
use Samanzavarian_Widgets\Admin\Settings\Section;
use Samanzavarian_Widgets\Admin\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsPage
{
    public const SLUG = 'szw-settings';

    private const NONCE  = 'szw_settings';
    private const STATUS = 'status';

    /** @var list<array{ok:bool,text:string}> */
    private array $notices = [];

    public static function register(): void
    {
        $page = new self();

        add_action('admin_menu', static function () use ($page): void {
            add_submenu_page(DashboardPage::SLUG, 'تنظیمات', 'تنظیمات', Kit::CAPABILITY, self::SLUG, [$page, 'render'], 2);
        }, 30);
    }

    public function render(): void
    {
        if (!current_user_can(Kit::CAPABILITY)) {
            wp_die('دسترسی ندارید.');
        }

        $this->maybeSave();
        $this->maybeTest();
        $this->maybeTelegram();

        $current = $this->current();

        Ui::open(self::SLUG);
        Ui::head('تنظیمات', 'هر بخش جدا ذخیره می‌شود. کلیدها و رمزها بعد از ذخیره در صفحه نمایش داده نمی‌شوند.');
        Ui::notices($this->notices);

        echo '<div class="za-settings">';
        $this->renderNav($current);
        echo '<div class="za-settings__main">';
        $current === null ? $this->renderStatus() : $this->renderForm($current);
        echo '</div></div>';

        Ui::close();
    }

    /* ---- ذخیره و آزمایش -------------------------------------------------- */

    private function maybeSave(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['szw_save'])) {
            return;
        }

        check_admin_referer(self::NONCE);

        // وردپرس همه ورودی‌ها را اسلش‌دار می‌کند؛ بدون wp_unslash هر نقل‌قول با هر ذخیره یک بک‌اسلش می‌گیرد
        $input  = wp_unslash($_POST);
        $errors = Settings::save(is_array($input) ? $input : [], sanitize_key((string) ($_POST['szw_section'] ?? '')));

        if ($errors === []) {
            $this->notices[] = ['ok' => true, 'text' => 'تنظیمات ذخیره شد.'];
            return;
        }

        foreach ($errors as $error) {
            $this->notices[] = ['ok' => false, 'text' => $error];
        }
        $this->notices[] = ['ok' => false, 'text' => 'هیچ تغییری ذخیره نشد — اول موارد بالا را اصلاح کنید.'];
    }

    /** «آزمایش اتصال»: فقط از تلگرام می‌پرسد ربات کیست (getMe)؛ پیامی نمی‌فرستد */
    private function maybeTest(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['szw_test'])) {
            return;
        }

        check_admin_referer(self::NONCE);

        if (Settings::text('tg_token') === '') {
            $this->notices[] = ['ok' => false, 'text' => 'توکن ربات وارد نشده؛ در «تلگرام» واردش کنید.'];
            return;
        }

        $r = Telegram::api('getMe', [], Settings::text('tg_token'));
        $this->notices[] = $r['ok']
            ? ['ok' => true, 'text' => 'اتصال برقرار است: ربات @' . (string) ($r['result']['username'] ?? '') . ' پاسخ داد.']
            : ['ok' => false, 'text' => 'تلگرام پاسخ نداد: ' . $r['error'] . ' — توکن را بررسی کنید، یا اگر هاست به تلگرام دسترسی ندارد با پشتیبانی هاست تماس بگیرید.'];
    }

    /** «پیدا کردن چت» و «پیام آزمایشی» در بخش تلگرام */
    private function maybeTelegram(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['szw_tg'])) {
            return;
        }

        check_admin_referer(self::NONCE);
        $what = sanitize_key((string) $_POST['szw_tg']);

        if (Settings::text('tg_token') === '') {
            $this->notices[] = ['ok' => false, 'text' => 'اول توکن ربات را وارد و ذخیره کنید.'];
            return;
        }

        if ($what === 'detect') {
            $r = Telegram::api('getUpdates', ['limit' => 50], Settings::text('tg_token'));
            if (!$r['ok']) {
                $this->notices[] = ['ok' => false, 'text' => 'تلگرام گفت: ' . $r['error']];
                return;
            }
            $chat = null;
            foreach (array_reverse((array) $r['result']) as $u) {
                $chat = $u['message']['chat'] ?? $u['my_chat_member']['chat'] ?? $u['channel_post']['chat'] ?? null;
                if ($chat) {
                    break;
                }
            }
            if (!$chat) {
                $this->notices[] = ['ok' => false, 'text' => 'هنوز پیامی به ربات نرسیده. ربات را در تلگرام باز کنید، Start را بزنید (یا هر پیامی بفرستید) و دوباره امتحان کنید.'];
                return;
            }
            $errors = Settings::save(['tg_chat' => (string) $chat['id'], 'tg_enabled' => Settings::bool('tg_enabled') ? '1' : ''], 'telegram');
            $who    = trim(($chat['title'] ?? '') . ' ' . ($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? ''));
            $this->notices[] = $errors === []
                ? ['ok' => true, 'text' => 'پیدا و ذخیره شد: ' . ($who !== '' ? $who : (string) $chat['id']) . '. حالا «پیام آزمایشی» را بزنید.']
                : ['ok' => false, 'text' => implode(' ', $errors)];
            return;
        }

        if ($what === 'test') {
            if (Settings::text('tg_chat') === '') {
                $this->notices[] = ['ok' => false, 'text' => 'شناسه چت خالی است؛ اول «پیدا کردن چت» را بزنید.'];
                return;
            }
            $r = Telegram::api('sendMessage', ['chat_id' => Settings::text('tg_chat'), 'text' => '✅ ' . get_bloginfo('name') . ' is connected. New project requests will arrive here.'], Settings::text('tg_token'));
            update_option('szw_tg_last', $r['ok'] ? ['ok' => true, 'at' => time()] : ['ok' => false, 'at' => time(), 'error' => $r['error']], false);
            $this->notices[] = $r['ok'] ? ['ok' => true, 'text' => 'فرستاده شد؛ تلگرام را ببینید.'] : ['ok' => false, 'text' => 'تلگرام نپذیرفت: ' . $r['error']];
        }
    }

    /* ---- ناوبری و فرم ---------------------------------------------------- */

    /** @return Section|null null = وضعیت سیستم */
    private function current(): ?Section
    {
        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : '';

        if ($tab === self::STATUS) {
            return null;
        }

        return Schema::section($tab) ?? Schema::sections()[0];
    }

    private function url(string $tab): string
    {
        return Ui::url(self::SLUG, ['tab' => $tab]);
    }

    private function renderNav(?Section $current): void
    {
        echo '<nav class="za-side" aria-label="بخش‌های تنظیمات">';

        foreach (Schema::sections() as $section) {
            $on = $current !== null && $section->id === $current->id;
            printf('<a href="%s" class="za-side__item%s"%s>%s<span>%s</span></a>', esc_url($this->url($section->id)), $on ? ' is-current' : '', $on ? ' aria-current="page"' : '', Ui::icon($section->icon, 17), esc_html($section->title));
        }

        echo '<span class="za-side__sep"></span>';
        printf('<a href="%s" class="za-side__item%s"%s>%s<span>وضعیت سیستم</span></a>', esc_url($this->url(self::STATUS)), $current === null ? ' is-current' : '', $current === null ? ' aria-current="page"' : '', Ui::icon('pulse', 17));

        echo '</nav>';
    }

    private function renderForm(Section $section): void
    {
        echo '<form method="post" class="za-card za-form" action="' . esc_url($this->url($section->id)) . '" data-dirty-guard>';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="szw_section" value="' . esc_attr($section->id) . '">';
        echo '<header class="za-card__head"><h3>' . Ui::icon($section->icon, 18) . esc_html($section->title) . '</h3></header>';

        if ($section->description !== '') {
            echo '<p class="za-form__intro">' . esc_html($section->description) . '</p>';
        }

        echo '<div class="za-fields">';
        foreach ($section->fields as $field) {
            $this->renderRow($field);
        }
        echo '</div>';

        echo '<footer class="za-savebar"><span class="za-savebar__state" aria-live="polite"></span>'
            . '<button type="submit" name="szw_save" value="1" class="za-btn za-btn--primary">' . Ui::icon('check', 16) . '<span>ذخیره تنظیمات</span></button></footer>';
        echo '</form>';

        if ($section->id === 'telegram') {
            $this->renderTelegramSteps();
        }
    }

    /** راه‌اندازی تلگرام در چهار قدم، با دو دکمه‌ای که کار را تمام می‌کنند */
    private function renderTelegramSteps(): void
    {
        $token = Settings::text('tg_token') !== '';
        $chat  = Settings::text('tg_chat') !== '';
        $step  = static fn (bool $done, string $text): string => '<li class="za-step' . ($done ? ' is-done' : '') . '"><i>' . ($done ? Ui::icon('check', 13) : '') . '</i><span>' . $text . '</span></li>';
        $btn   = static fn (string $what, string $label, string $icon, bool $on): string => '<button type="submit" name="szw_tg" value="' . $what . '" class="za-btn za-btn--sm' . ($on ? '' : ' is-disabled') . '"' . ($on ? '' : ' disabled') . '>' . Ui::icon($icon, 14) . '<span>' . $label . '</span></button>';

        echo '<section class="za-card za-card--steps"><header class="za-card__head"><h3>' . Ui::icon('telegram', 18) . 'راه‌اندازی در چهار قدم</h3></header><div class="za-card__body"><ol class="za-steps">'
            . $step($token, 'در تلگرام <b>@BotFather</b> را باز کنید، <code dir="ltr">/newbot</code> را بفرستید و اسمی بدهید. توکنی که می‌دهد را بالا در «توکن ربات» بگذارید و ذخیره کنید.')
            . $step($chat, 'ربات تازه را در تلگرام باز کنید و <b>Start</b> را بزنید، بعد «پیدا کردن چت» را بزنید.')
            . $step($chat && !empty(get_option('szw_tg_last', [])['ok']), '«پیام آزمایشی» را بزنید؛ باید در تلگرام برسد.')
            . $step(false, 'تمام. از این به بعد هر درخواست تازه با دکمه‌های «باز کردن در وردپرس» و «واتس‌اپ» در تلگرام می‌رسد.')
            . '</ol><form method="post" class="za-steps__actions" action="' . esc_url($this->url('telegram')) . '">' . wp_nonce_field(self::NONCE, '_wpnonce', true, false)
            . $btn('detect', 'پیدا کردن چت', 'search', $token)
            . $btn('test', 'پیام آزمایشی', 'send', $token && $chat)
            . '</form></div></section>';
    }

    /** یک ردیف: عنوان و توضیح در یک سو، ورودی در سوی دیگر */
    private function renderRow(Field $field): void
    {
        $id   = 'szw-' . $field->key;
        $wide = $field->type === FieldType::Textarea;

        echo '<div class="za-field' . ($wide ? ' za-field--wide' : '') . ($field->type === FieldType::Toggle ? ' za-field--toggle' : '') . '">';
        printf('<div class="za-field__label"><label for="%s">%s</label>', esc_attr($id), esc_html($field->label));
        if ($field->help !== '') {
            echo '<p>' . esc_html($field->help) . '</p>';
        }
        echo '</div><div class="za-field__control">';

        if ($field->isLocked()) {
            printf(
                '<div class="za-locked">%s<code dir="ltr">%s</code><small>از <code>wp-config.php</code> خوانده می‌شود (<code>%s</code>)؛ این‌جا قابل تغییر نیست.</small></div>',
                Ui::icon('lock', 15),
                esc_html($field->isSecret() ? Settings::mask($field->key) : (string) Settings::get($field->key)),
                esc_html((string) $field->constant)
            );
        } else {
            $this->renderInput($field, $id);
        }

        echo '</div></div>';
    }

    private function renderInput(Field $field, string $id): void
    {
        $name = esc_attr($field->key);

        switch ($field->type) {
            case FieldType::Toggle:
                printf('<label class="za-switch"><input type="checkbox" id="%s" name="%s" value="1"%s><span class="za-switch__track"></span><span class="za-switch__text" data-on="فعال" data-off="خاموش"></span></label>',
                    esc_attr($id), $name, checked(Settings::bool($field->key), true, false));
                return;

            case FieldType::Number:
                printf('<div class="za-input za-input--num"><input type="text" inputmode="numeric" dir="ltr" id="%s" name="%s" value="%s">%s</div>',
                    esc_attr($id), $name, esc_attr((string) Settings::int($field->key)),
                    $field->unit !== '' ? '<span class="za-input__unit">' . esc_html($field->unit) . '</span>' : '');
                return;

            case FieldType::Select:
                printf('<select class="za-select" id="%s" name="%s">', esc_attr($id), $name);
                foreach ($field->choices as $value => $label) {
                    printf('<option value="%s"%s>%s</option>', esc_attr($value), selected(Settings::text($field->key), $value, false), esc_html($label));
                }
                echo '</select>';
                return;

            case FieldType::Textarea:
                printf('<textarea class="za-textarea" id="%s" name="%s" rows="6" dir="auto">%s</textarea>', esc_attr($id), $name, esc_textarea(Settings::text($field->key)));
                return;

            case FieldType::Secret:
                // مقدار واقعی هرگز در HTML نیست؛ خالی گذاشتن = «دست نزن»، پاک کردن فقط با تیک
                $set = Settings::hasSecret($field->key);
                printf('<div class="za-secret"><input type="password" class="za-text" id="%s" name="%s" value="" autocomplete="new-password" placeholder="%s" dir="ltr">',
                    esc_attr($id), $name, esc_attr($set ? 'برای تغییر، مقدار جدید را وارد کنید' : 'وارد کنید'));
                echo '<div class="za-secret__state">' . ($set
                    ? Ui::pill('ذخیره شده', 'ok', 'lock') . '<code dir="ltr">' . esc_html(Settings::mask($field->key)) . '</code>'
                        . sprintf('<label class="za-check"><input type="checkbox" name="%s" value="__clear__"> حذف این کلید</label>', $name)
                    : Ui::pill('وارد نشده', 'warn')) . '</div></div>';
                return;

            default:
                printf('<input type="text" class="za-text" id="%s" name="%s" value="%s" placeholder="%s" dir="auto">',
                    esc_attr($id), $name, esc_attr(Settings::text($field->key)), esc_attr($field->placeholder));
        }
    }

    /* ---- وضعیت سیستم ----------------------------------------------------- */

    private function renderStatus(): void
    {
        echo '<p class="za-form__intro za-form__intro--bare">این صفحه چیزی را تغییر نمی‌دهد؛ فقط وضعیت فعلی را نشان می‌دهد و برای فرستادن به پشتیبانی امن است — کلید و رمزی در آن نیست.</p>';

        $test = '<form method="post" class="za-inline" action="' . esc_url($this->url(self::STATUS)) . '">' . wp_nonce_field(self::NONCE, '_wpnonce', true, false)
            . '<button type="submit" name="szw_test" value="1" class="za-btn za-btn--sm" title="فقط می‌پرسد ربات کیست؛ پیامی فرستاده نمی‌شود.">' . Ui::icon('pulse', 14) . '<span>آزمایش اتصال</span></button></form>';

        // چیدمان دوستونه هم‌تراز (admin.js ← masonry)
        echo '<div class="za-status" data-masonry>';

        $last = get_option('szw_tg_last', []);
        $last = is_array($last) ? $last : [];
        $this->statusCard('تلگرام', 'telegram', [
            'خبر دادن'     => Settings::bool('tg_enabled') ? ['ok', 'روشن'] : ['warn', 'خاموش — درخواست‌ها فقط در پیشخوان می‌مانند'],
            'توکن ربات'    => Settings::text('tg_token') !== '' ? ['ok', 'ذخیره شده'] : ['error', 'وارد نشده — در «تلگرام» واردش کنید'],
            'چت'           => Settings::text('tg_chat') !== '' ? ['ok', Settings::text('tg_chat')] : ['error', 'معلوم نیست — «پیدا کردن چت»'],
            'آخرین پیام'   => $last === [] ? ['muted', 'هنوز چیزی فرستاده نشده'] : (!empty($last['ok']) ? ['ok', 'رسید — ' . Fa::ago((int) $last['at'])] : ['error', 'نرسید — ' . (string) ($last['error'] ?? '') . ' (' . Fa::ago((int) ($last['at'] ?? 0)) . ')']),
        ], $test);

        $pages  = DashboardPage::formPages();
        $latest = Leads::latest_id();
        $this->statusCard('فرم پروژه', 'form', [
            'روی سایت'      => $pages > 0 ? ['ok', 'در ' . Fa::num($pages) . ' صفحه'] : ['warn', 'در هیچ صفحه‌ای نیست'],
            'نشانی دریافت'  => rest_url(Leads::ROUTE . '/lead'),
            'آخرین درخواست' => $latest > 0 ? Fa::ago((int) Leads::find($latest)['ts']) : '—',
            'ضد اسپم'       => ['ok', 'فیلد تله، حداقل زمان، ' . Fa::digits(Leads::RATE) . ' درخواست در ساعت'],
        ]);

        $counts = Leads::counts();
        $this->statusCard('داده', 'inbox', [
            'همه درخواست‌ها'   => Fa::num($counts['all']),
            'منتظر پاسخ'       => $counts['new'] > 0 ? ['warn', Fa::num($counts['new']) . ' درخواست'] : ['ok', 'هیچ'],
            'اسپم کنار گذاشته' => Fa::num($counts['spam']),
            'پاک کردن خودکار'  => Settings::int('retention') > 0 ? ['ok', 'بسته‌شده‌ها بعد از ' . Fa::num(Settings::int('retention')) . ' روز'] : ['muted', 'خاموش — همه می‌مانند'],
        ]);

        $cron = wp_next_scheduled('szw_leads_cleanup');
        $this->statusCard('محیط', 'server', [
            'نسخه افزونه' => SZW_VERSION,
            'نسخه PHP'    => PHP_VERSION,
            'نسخه وردپرس' => get_bloginfo('version'),
            'WP-Cron'     => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? ['warn', 'خاموش (DISABLE_WP_CRON) — تلاش دوباره و پاک‌سازی به کرون هاست نیاز دارند'] : ['ok', 'روشن' . ($cron ? ' — پاک‌سازی بعدی ' . Fa::date((int) $cron, true) : '')],
        ]);

        echo '</div>';
    }

    /**
     * کارت وضعیت: هر ردیف [لحن، متن] یا متن ساده؛ بدترین لحن به‌صورت نشان کنار عنوان
     *
     * @param array<string,string|array{0:string,1:string}> $rows
     */
    private function statusCard(string $title, string $icon, array $rows, string $actionsHtml = ''): void
    {
        $worst = 'ok';
        foreach ($rows as $value) {
            $tone = is_array($value) ? $value[0] : 'muted';
            if ($tone === 'error' || ($tone === 'warn' && $worst !== 'error')) {
                $worst = $tone;
            }
        }

        Ui::cardOpen($title, $icon, $actionsHtml . Ui::pill(['ok' => 'سالم', 'warn' => 'هشدار', 'error' => 'نیاز به اقدام'][$worst], $worst), 'za-card--status');

        echo '<dl class="za-kv za-kv--status">';
        foreach ($rows as $label => $value) {
            printf('<div><dt>%s</dt><dd>%s</dd></div>', esc_html($label), is_array($value) ? Ui::state($value[0], $value[1]) : '<span dir="auto">' . esc_html($value) . '</span>');
        }
        echo '</dl>';

        Ui::cardClose();
    }
}
