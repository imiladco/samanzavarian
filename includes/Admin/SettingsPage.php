<?php

/**
 * Settings (the kit's pattern): sections in a side column, each section a
 * form card built from the Schema, a sticky save bar ("Unsaved changes",
 * Ctrl+S, a warning on leaving), and System status with health cards and a
 * connection test.
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
            add_submenu_page(DashboardPage::SLUG, 'Settings', 'Settings', Kit::CAPABILITY, self::SLUG, [$page, 'render'], 2);
        }, 30);
    }

    public function render(): void
    {
        if (!current_user_can(Kit::CAPABILITY)) {
            wp_die('You are not allowed to see this page.');
        }

        $this->maybeSave();
        $this->maybeTest();
        $this->maybeTelegram();

        $current = $this->current();

        Ui::open(self::SLUG);
        Ui::head('Settings', 'Each section saves on its own. Tokens are never shown again once saved.');
        Ui::notices($this->notices);

        echo '<div class="za-settings">';
        $this->renderNav($current);
        echo '<div class="za-settings__main">';
        $current === null ? $this->renderStatus() : $this->renderForm($current);
        echo '</div></div>';

        Ui::close();
    }

    /* ---- Saving and testing ----------------------------------------------- */

    private function maybeSave(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['szw_save'])) {
            return;
        }

        check_admin_referer(self::NONCE);

        // WordPress slashes all input; without wp_unslash every quote would gain a backslash on each save
        $input  = wp_unslash($_POST);
        $errors = Settings::save(is_array($input) ? $input : [], sanitize_key((string) ($_POST['szw_section'] ?? '')));

        if ($errors === []) {
            $this->notices[] = ['ok' => true, 'text' => 'Settings saved.'];
            return;
        }

        foreach ($errors as $error) {
            $this->notices[] = ['ok' => false, 'text' => $error];
        }
        $this->notices[] = ['ok' => false, 'text' => 'Nothing was saved. Fix the points above first.'];
    }

    /** "Test connection": only asks Telegram who the bot is (getMe); sends no message */
    private function maybeTest(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['szw_test'])) {
            return;
        }

        check_admin_referer(self::NONCE);

        if (Settings::text('tg_token') === '') {
            $this->notices[] = ['ok' => false, 'text' => 'No bot token yet; add it under Telegram.'];
            return;
        }

        $r = Telegram::api('getMe', [], Settings::text('tg_token'));
        $this->notices[] = $r['ok']
            ? ['ok' => true, 'text' => 'Connected: the bot @' . (string) ($r['result']['username'] ?? '') . ' answered.']
            : ['ok' => false, 'text' => 'Telegram did not answer: ' . $r['error'] . '. Check the token; if your host cannot reach Telegram, ask your host.'];
    }

    /** "Find my chat" and "Test message" in the Telegram section */
    private function maybeTelegram(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['szw_tg'])) {
            return;
        }

        check_admin_referer(self::NONCE);
        $what = sanitize_key((string) $_POST['szw_tg']);

        if (Settings::text('tg_token') === '') {
            $this->notices[] = ['ok' => false, 'text' => 'Add and save the bot token first.'];
            return;
        }

        if ($what === 'detect') {
            $r = Telegram::api('getUpdates', ['limit' => 50], Settings::text('tg_token'));
            if (!$r['ok']) {
                $this->notices[] = ['ok' => false, 'text' => 'Telegram said: ' . $r['error']];
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
                $this->notices[] = ['ok' => false, 'text' => 'Your bot has had no message yet. Open it in Telegram, press Start (or send it anything), then try again.'];
                return;
            }
            $errors = Settings::save(['tg_chat' => (string) $chat['id'], 'tg_enabled' => Settings::bool('tg_enabled') ? '1' : ''], 'telegram');
            $who    = trim(($chat['title'] ?? '') . ' ' . ($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? ''));
            $this->notices[] = $errors === []
                ? ['ok' => true, 'text' => 'Found and saved: ' . ($who !== '' ? $who : (string) $chat['id']) . '. Now press "Test message".']
                : ['ok' => false, 'text' => implode(' ', $errors)];
            return;
        }

        if ($what === 'test') {
            if (Settings::text('tg_chat') === '') {
                $this->notices[] = ['ok' => false, 'text' => 'No chat ID yet; press "Find my chat" first.'];
                return;
            }
            $r = Telegram::api('sendMessage', ['chat_id' => Settings::text('tg_chat'), 'text' => '✅ ' . get_bloginfo('name') . ' is connected. New project requests will arrive here.'], Settings::text('tg_token'));
            update_option('szw_tg_last', $r['ok'] ? ['ok' => true, 'at' => time()] : ['ok' => false, 'at' => time(), 'error' => $r['error']], false);
            $this->notices[] = $r['ok'] ? ['ok' => true, 'text' => 'Sent. Check Telegram.'] : ['ok' => false, 'text' => 'Telegram did not take it: ' . $r['error']];
        }
    }

    /* ---- Navigation and form ---------------------------------------------- */

    /** @return Section|null null = System status */
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
        echo '<nav class="za-side" aria-label="Settings sections">';

        foreach (Schema::sections() as $section) {
            $on = $current !== null && $section->id === $current->id;
            printf('<a href="%s" class="za-side__item%s"%s>%s<span>%s</span></a>', esc_url($this->url($section->id)), $on ? ' is-current' : '', $on ? ' aria-current="page"' : '', Ui::icon($section->icon, 17), esc_html($section->title));
        }

        echo '<span class="za-side__sep"></span>';
        printf('<a href="%s" class="za-side__item%s"%s>%s<span>System status</span></a>', esc_url($this->url(self::STATUS)), $current === null ? ' is-current' : '', $current === null ? ' aria-current="page"' : '', Ui::icon('pulse', 17));

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
            . '<button type="submit" name="szw_save" value="1" class="za-btn za-btn--primary">' . Ui::icon('check', 16) . '<span>Save settings</span></button></footer>';
        echo '</form>';

        if ($section->id === 'telegram') {
            $this->renderTelegramSteps();
        }
    }

    /** Telegram in four steps, with the two buttons that finish the job */
    private function renderTelegramSteps(): void
    {
        $token = Settings::text('tg_token') !== '';
        $chat  = Settings::text('tg_chat') !== '';
        $step  = static fn (bool $done, string $text): string => '<li class="za-step' . ($done ? ' is-done' : '') . '"><i>' . ($done ? Ui::icon('check', 13) : '') . '</i><span>' . $text . '</span></li>';
        $btn   = static fn (string $what, string $label, string $icon, bool $on): string => '<button type="submit" name="szw_tg" value="' . $what . '" class="za-btn za-btn--sm' . ($on ? '' : ' is-disabled') . '"' . ($on ? '' : ' disabled') . '>' . Ui::icon($icon, 14) . '<span>' . $label . '</span></button>';

        echo '<section class="za-card za-card--steps"><header class="za-card__head"><h3>' . Ui::icon('telegram', 18) . 'Set up in four steps</h3></header><div class="za-card__body"><ol class="za-steps">'
            . $step($token, 'In Telegram, open <b>@BotFather</b>, send <code>/newbot</code> and give it a name. Paste the token it gives you into "Bot token" above and save.')
            . $step($chat, 'Open your new bot in Telegram and press <b>Start</b>, then press "Find my chat".')
            . $step($chat && !empty(get_option('szw_tg_last', [])['ok']), 'Press "Test message"; it should arrive in Telegram.')
            . $step(false, 'Done. From then on every new request arrives in Telegram, with "Open in WordPress" and "WhatsApp" buttons.')
            . '</ol><form method="post" class="za-steps__actions" action="' . esc_url($this->url('telegram')) . '">' . wp_nonce_field(self::NONCE, '_wpnonce', true, false)
            . $btn('detect', 'Find my chat', 'search', $token)
            . $btn('test', 'Test message', 'send', $token && $chat)
            . '</form></div></section>';
    }

    /** One row: the label and help on one side, the input on the other */
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
                '<div class="za-locked">%s<code>%s</code><small>Read from <code>wp-config.php</code> (<code>%s</code>); it cannot be changed here.</small></div>',
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
                printf('<label class="za-switch"><input type="checkbox" id="%s" name="%s" value="1"%s><span class="za-switch__track"></span><span class="za-switch__text" data-on="On" data-off="Off"></span></label>',
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
                // the real value is never in the HTML; left empty means "leave it", cleared only by the tick
                $set = Settings::hasSecret($field->key);
                printf('<div class="za-secret"><input type="password" class="za-text" id="%s" name="%s" value="" autocomplete="new-password" placeholder="%s" dir="ltr">',
                    esc_attr($id), $name, esc_attr($set ? 'Type a new one to replace it' : 'Paste it here'));
                echo '<div class="za-secret__state">' . ($set
                    ? Ui::pill('Saved', 'ok', 'lock') . '<code>' . esc_html(Settings::mask($field->key)) . '</code>'
                        . sprintf('<label class="za-check"><input type="checkbox" name="%s" value="__clear__"> Remove it</label>', $name)
                    : Ui::pill('Not set', 'warn')) . '</div></div>';
                return;

            default:
                printf('<input type="text" class="za-text" id="%s" name="%s" value="%s" placeholder="%s" dir="auto">',
                    esc_attr($id), $name, esc_attr(Settings::text($field->key)), esc_attr($field->placeholder));
        }
    }

    /* ---- System status ---------------------------------------------------- */

    private function renderStatus(): void
    {
        echo '<p class="za-form__intro za-form__intro--bare">This page changes nothing; it only shows how things stand, and is safe to send to support: no token is on it.</p>';

        $test = '<form method="post" class="za-inline" action="' . esc_url($this->url(self::STATUS)) . '">' . wp_nonce_field(self::NONCE, '_wpnonce', true, false)
            . '<button type="submit" name="szw_test" value="1" class="za-btn za-btn--sm" title="Only asks who the bot is; no message is sent.">' . Ui::icon('pulse', 14) . '<span>Test connection</span></button></form>';

        // two balanced columns (admin.js, masonry)
        echo '<div class="za-status" data-masonry>';

        $last = get_option('szw_tg_last', []);
        $last = is_array($last) ? $last : [];
        $this->statusCard('Telegram', 'telegram', [
            'Notifications' => Settings::bool('tg_enabled') ? ['ok', 'On'] : ['warn', 'Off: requests only wait in this panel'],
            'Bot token'     => Settings::text('tg_token') !== '' ? ['ok', 'Saved'] : ['error', 'Not set: add it under Telegram'],
            'Chat'          => Settings::text('tg_chat') !== '' ? ['ok', Settings::text('tg_chat')] : ['error', 'Unknown: press "Find my chat"'],
            'Last message'  => $last === [] ? ['muted', 'Nothing sent yet'] : (!empty($last['ok']) ? ['ok', 'Delivered ' . Fmt::ago((int) $last['at'])] : ['error', 'Not delivered: ' . (string) ($last['error'] ?? '') . ' (' . Fmt::ago((int) ($last['at'] ?? 0)) . ')']),
        ], $test);

        $pages  = DashboardPage::formPages();
        $latest = Leads::latest_id();
        $this->statusCard('Project Form', 'form', [
            'On the site'   => $pages > 0 ? ['ok', 'On ' . Fmt::plural($pages, 'page')] : ['warn', 'On no page'],
            'Endpoint'      => rest_url(Leads::ROUTE . '/lead'),
            'Last request'  => $latest > 0 ? Fmt::ago((int) Leads::find($latest)['ts']) : '—',
            'Spam guard'    => ['ok', 'Bot trap, minimum time, ' . Leads::RATE . ' requests an hour'],
        ]);

        $counts = Leads::counts();
        $this->statusCard('Data', 'inbox', [
            'All requests'     => Fmt::num($counts['all']),
            'Waiting'          => $counts['new'] > 0 ? ['warn', Fmt::plural($counts['new'], 'request')] : ['ok', 'None'],
            'Spam kept aside'  => Fmt::num($counts['spam']),
            'Clean-up'         => Settings::int('retention') > 0 ? ['ok', 'Closed requests after ' . Fmt::plural(Settings::int('retention'), 'day')] : ['muted', 'Off: everything stays'],
        ]);

        $cron = wp_next_scheduled('szw_leads_cleanup');
        $this->statusCard('Server', 'server', [
            'Plugin'    => SZW_VERSION,
            'PHP'       => PHP_VERSION,
            'WordPress' => get_bloginfo('version'),
            'WP-Cron'     => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? ['warn', 'Off (DISABLE_WP_CRON): retries and clean-up need the host\'s cron'] : ['ok', 'On' . ($cron ? '; next clean-up ' . Fmt::date((int) $cron, true) : '')],
        ]);

        echo '</div>';
    }

    /**
     * A status card: each row [tone, text] or plain text; the worst tone as a pill by the title
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

        Ui::cardOpen($title, $icon, $actionsHtml . Ui::pill(['ok' => 'Healthy', 'warn' => 'Warning', 'error' => 'Needs action'][$worst], $worst), 'za-card--status');

        echo '<dl class="za-kv za-kv--status">';
        foreach ($rows as $label => $value) {
            printf('<div><dt>%s</dt><dd>%s</dd></div>', esc_html($label), is_array($value) ? Ui::state($value[0], $value[1]) : '<span>' . esc_html($value) . '</span>');
        }
        echo '</dl>';

        Ui::cardClose();
    }
}
