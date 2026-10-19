<?php

/**
 * Settings (the kit's pattern): sections in a side column, each section a
 * form card built from the Schema, a sticky save bar ("Unsaved changes",
 * Ctrl+S, a warning on leaving), and System status with health cards and a
 * connection test.
 *
 * Telegram: the token is checked with Telegram before it is saved (a typo is
 * caught here, not on the first request); the chats are a list edited in
 * place, filled by "Find chats" (who has written to the bot) or by id, each
 * with its own test. Find and test run through admin-ajax, so a chat can be
 * found and tested before anything is saved.
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
    private const AJAX   = 'szw_tg';

    /** What the bot is called, from getMe: [username, name] */
    public const BOT = 'szw_tg_bot';

    /** @var list<array{ok:bool,text:string}> */
    private array $notices = [];

    public static function register(): void
    {
        $page = new self();

        add_action('admin_menu', static function () use ($page): void {
            add_submenu_page(DashboardPage::SLUG, 'Settings', 'Settings', Kit::CAPABILITY, self::SLUG, [$page, 'render'], 2);
        }, 30);

        add_action('wp_ajax_' . self::AJAX, [self::class, 'ajax']);
    }

    public function render(): void
    {
        if (!current_user_can(Kit::CAPABILITY)) {
            wp_die('You are not allowed to see this page.');
        }

        $this->maybeSave();
        $this->maybeTest();

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
        $input   = wp_unslash($_POST);
        $input   = is_array($input) ? $input : [];
        $section = sanitize_key((string) ($_POST['szw_section'] ?? ''));

        if ($section === 'telegram' && !$this->checkToken($input)) {
            $this->notices[] = ['ok' => false, 'text' => 'Nothing was saved. Fix the token first.'];
            return;
        }

        $errors = Settings::save($input, $section);

        if ($errors === []) {
            $this->notices[] = ['ok' => true, 'text' => 'Settings saved.'];
            return;
        }

        foreach ($errors as $error) {
            $this->notices[] = ['ok' => false, 'text' => $error];
        }
        $this->notices[] = ['ok' => false, 'text' => 'Nothing was saved. Fix the points above first.'];
    }

    /**
     * A new token is asked about before it is saved: a wrong one is refused
     * with the reason, a right one leaves the bot's name for the page. If
     * Telegram cannot be reached at all, the token is saved anyway (the host
     * may only block it for now) and a warning says so.
     *
     * @param array<string,mixed> $input
     */
    private function checkToken(array $input): bool
    {
        $token = trim((string) ($input['tg_token'] ?? ''));

        if ($token === '__clear__') {
            delete_option(self::BOT);
            return true;
        }
        if ($token === '') {
            return true;
        }
        if (!preg_match('/^\d{5,}:[A-Za-z0-9_-]{30,}$/', $token)) {
            $this->notices[] = ['ok' => false, 'text' => 'That does not look like a bot token: it is a number, a colon, then about 35 letters and digits (123456789:AAF…). Copy it again from @BotFather.'];
            return false;
        }

        $r = Telegram::api('getMe', [], $token);
        if ($r['ok']) {
            self::rememberBot((array) $r['result']);
            return true;
        }
        if (stripos($r['error'], 'unauthorized') !== false || stripos($r['error'], 'not found') !== false) {
            $this->notices[] = ['ok' => false, 'text' => Telegram::explain($r['error'])];
            return false;
        }

        delete_option(self::BOT);
        $this->notices[] = ['ok' => false, 'text' => 'Saved, but not checked: ' . Telegram::explain($r['error'])];
        return true;
    }

    /** @param array<string,mixed> $me getMe's result */
    private static function rememberBot(array $me): void
    {
        update_option(self::BOT, ['username' => (string) ($me['username'] ?? ''), 'name' => (string) ($me['first_name'] ?? '')], false);
    }

    /** "Test connection" on System status: only asks Telegram who the bot is (getMe); sends no message */
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
        if ($r['ok']) {
            self::rememberBot((array) $r['result']);
        }
        $this->notices[] = $r['ok']
            ? ['ok' => true, 'text' => 'Connected: the bot @' . (string) ($r['result']['username'] ?? '') . ' answered.']
            : ['ok' => false, 'text' => Telegram::explain($r['error'])];
    }

    /**
     * admin-ajax: find (the chats that have written to the bot) and test (a
     * message to one chat). The token typed in the field is used when there
     * is one, so both work before saving.
     */
    public static function ajax(): void
    {
        check_ajax_referer(self::AJAX);

        if (!current_user_can(Kit::CAPABILITY)) {
            wp_send_json_error(['text' => 'You are not allowed to do this.'], 403);
        }

        $typed = trim(wp_unslash((string) ($_POST['token'] ?? '')));
        $token = $typed !== '' ? $typed : Settings::text('tg_token');

        if ($token === '') {
            wp_send_json_error(['text' => 'Paste the bot token first.']);
        }

        if (($_POST['do'] ?? '') === 'test') {
            $chat = preg_replace('/\s+/', '', wp_unslash((string) ($_POST['chat'] ?? ''))) ?? '';
            if (!Telegram::valid_chat($chat)) {
                wp_send_json_error(['text' => 'A chat id is a number (groups and channels start with -100) or a channel\'s @username.']);
            }
            $name = trim(sanitize_text_field(wp_unslash((string) ($_POST['name'] ?? ''))));
            $r    = Telegram::api('sendMessage', ['chat_id' => $chat, 'text' => '✅ ' . get_bloginfo('name') . ': this chat is connected. New project requests will arrive here.'], $token);
            if (!$r['ok']) {
                wp_send_json_error(['text' => Telegram::explain($r['error'])]);
            }
            update_option('szw_tg_last', ['ok' => true, 'at' => time()], false);
            wp_send_json_success(['text' => 'Sent to ' . ($name !== '' ? $name : $chat) . '. Check Telegram.']);
        }

        $me = Telegram::api('getMe', [], $token);
        if (!$me['ok']) {
            wp_send_json_error(['text' => Telegram::explain($me['error'])]);
        }
        if ($typed === '' || $typed === Settings::text('tg_token')) {
            self::rememberBot((array) $me['result']);
        }

        $r = Telegram::api('getUpdates', ['limit' => 100], $token);
        if (!$r['ok']) {
            wp_send_json_error(['text' => Telegram::explain($r['error'])]);
        }

        // newest first, each chat once
        $chats = [];
        foreach (array_reverse((array) $r['result']) as $u) {
            $c = $u['message']['chat'] ?? $u['channel_post']['chat'] ?? $u['my_chat_member']['chat'] ?? null;
            if (!is_array($c) || !isset($c['id']) || isset($chats[(string) $c['id']])) {
                continue;
            }
            // the bot was removed from a group: no use offering it
            if (isset($u['my_chat_member']) && in_array($u['my_chat_member']['new_chat_member']['status'] ?? '', ['left', 'kicked'], true)) {
                $chats[(string) $c['id']] = null;
                continue;
            }
            $kind = match ((string) ($c['type'] ?? 'private')) {
                'group', 'supergroup' => 'group',
                'channel'             => 'channel',
                default               => 'person',
            };
            $name = trim((string) ($c['title'] ?? (($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))));
            $chats[(string) $c['id']] = ['id' => (string) $c['id'], 'name' => $name, 'kind' => $kind, 'handle' => isset($c['username']) ? '@' . $c['username'] : ''];
        }

        wp_send_json_success([
            'bot'   => '@' . (string) ($me['result']['username'] ?? ''),
            'chats' => array_values(array_filter($chats)),
        ]);
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
            $this->renderTelegramGuide();
        }
    }

    /**
     * How to connect: the steps while something is missing (each ticks
     * itself off), and afterwards only how to add someone else.
     */
    private function renderTelegramGuide(): void
    {
        $token = Settings::text('tg_token') !== '';
        $chats = Settings::recipients() !== [];
        $step  = static fn (bool $done, string $title, string $text): string => '<li class="za-step' . ($done ? ' is-done' : '') . '"><i>' . ($done ? Ui::icon('check', 13) : '') . '</i><span><b>' . $title . '</b> ' . $text . '</span></li>';

        $ways = '<ul class="za-ways">'
            . '<li>' . Ui::icon('user', 16) . '<span><b>A person</b> opens the bot in Telegram and presses <b>Start</b>.</span></li>'
            . '<li>' . Ui::icon('users', 16) . '<span><b>A group:</b> add the bot to the group, then send any message in it.</span></li>'
            . '<li>' . Ui::icon('megaphone', 16) . '<span><b>A channel:</b> add the bot as an admin that can post, then post something.</span></li>'
            . '</ul><p class="za-hint">Then press <b>Find chats</b> above and add them.</p>';

        if ($token && $chats) {
            echo '<details class="za-card za-guide"><summary class="za-card__head"><h3>' . Ui::icon('plus', 18) . 'Adding someone else</h3>' . Ui::icon('chevron', 16) . '</summary><div class="za-card__body">' . $ways . '</div></details>';
            return;
        }

        echo '<section class="za-card za-card--steps"><header class="za-card__head"><h3>' . Ui::icon('telegram', 18) . 'Connect in three steps</h3></header><div class="za-card__body"><ol class="za-steps">'
            . $step($token, 'Make a bot.', 'In Telegram, open <b>@BotFather</b>, send <code>/newbot</code> and pick a name. Paste the token it answers with into "Bot token" and save.')
            . $step($chats, 'Say hello to it.', 'Open your new bot and press <b>Start</b>. Then press <b>Find chats</b> and add yourself.')
            . $step(false, 'Test and save.', 'Press <b>Test</b> beside the chat; the message should arrive in a second. Save, and every new request arrives there.')
            . '</ol><p class="za-hint">More people, a group or a channel can be added at any time:</p>' . $ways . '</div></section>';
    }

    /** One row: the label and help on one side, the input on the other */
    private function renderRow(Field $field): void
    {
        $id   = 'szw-' . $field->key;
        $wide = in_array($field->type, [FieldType::Textarea, FieldType::Chats], true);

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
                printf('<textarea class="za-textarea" id="%s" name="%s" rows="%d" dir="auto">%s</textarea>', esc_attr($id), $name, $field->key === 'reply_wa' ? 3 : 5, esc_textarea(Settings::text($field->key)));
                $this->renderPreview($field->key);
                return;

            case FieldType::Chats:
                $this->renderChats($field, $id);
                return;

            case FieldType::Secret:
                // the real value is never in the HTML; left empty means "leave it", cleared only by the tick
                $set = Settings::hasSecret($field->key);
                $bot = get_option(self::BOT, []);
                $bot = is_array($bot) && !empty($bot['username']) ? $bot : null;
                printf('<div class="za-secret"><input type="password" class="za-text" id="%s" name="%s" value="" autocomplete="new-password" placeholder="%s" dir="ltr">',
                    esc_attr($id), $name, esc_attr($set ? 'Type a new one to replace it' : 'Paste it here'));
                echo '<div class="za-secret__state">' . ($set
                    ? ($bot ? '<span class="za-bot">' . Ui::icon('telegram', 14) . '<b>@' . esc_html($bot['username']) . '</b>' . ($bot['name'] !== '' ? '<small>' . esc_html($bot['name']) . '</small>' : '') . '</span>' : Ui::pill('Saved', 'ok', 'lock'))
                        . '<code>' . esc_html(Settings::mask($field->key)) . '</code>'
                        . sprintf('<label class="za-check"><input type="checkbox" name="%s" value="__clear__"> Remove it</label>', $name)
                    : Ui::pill('Not set', 'warn')) . '</div></div>';
                return;

            default:
                printf('<input type="text" class="za-text" id="%s" name="%s" value="%s" placeholder="%s" dir="auto">',
                    esc_attr($id), $name, esc_attr(Settings::text($field->key)), esc_attr($field->placeholder));
        }
    }

    /**
     * The chats: one row each (kind, name you can change, id, on/off, test,
     * remove), "Find chats" and "Add by id". Rows are form fields like any
     * other; they are kept on Save.
     */
    private function renderChats(Field $field, string $id): void
    {
        $name  = esc_attr($field->key);
        $chats = Settings::recipients();

        printf('<div class="za-chats" id="%s" data-chats data-name="%s" data-ajax="%s" data-nonce="%s">', esc_attr($id), $name, esc_url(admin_url('admin-ajax.php')), esc_attr(wp_create_nonce(self::AJAX)));
        echo '<ul class="za-chats__list">';
        foreach ($chats as $i => $c) {
            echo self::chatRow($field->key, (string) $i, $c);
        }
        echo '</ul>';

        echo '<div class="za-chats__empty"' . ($chats !== [] ? ' hidden' : '') . '>' . Ui::icon('telegram', 22) . '<p><b>No chats yet.</b> Press Start in your bot, then Find chats.</p></div>';

        echo '<div class="za-chats__bar">'
            . '<button type="button" class="za-btn za-btn--sm" data-chats-find>' . Ui::icon('search', 14) . '<span>Find chats</span></button>'
            . '<button type="button" class="za-btn za-btn--sm za-btn--ghost" data-chats-manual>' . Ui::icon('plus', 14) . '<span>Add by id</span></button>'
            . '<span class="za-chats__count" aria-live="polite"></span></div>';

        echo '<div class="za-chats__manual" hidden><input type="text" class="za-text" inputmode="text" dir="ltr" placeholder="Chat id, like 123456789 or -1001234567890, or @channel" aria-label="Chat id">'
            . '<button type="button" class="za-btn za-btn--sm za-btn--primary" data-chats-add-manual>' . Ui::icon('plus', 14) . '<span>Add</span></button></div>';

        echo '<div class="za-chats__found" hidden aria-live="polite"></div>';

        echo '<span data-chats-icons hidden><i data-k="person">' . Ui::icon('user', 16) . '</i><i data-k="group">' . Ui::icon('users', 16) . '</i><i data-k="channel">' . Ui::icon('megaphone', 16) . '</i></span>';
        echo '<template data-chats-row>' . self::chatRow($field->key, '__i__', ['id' => '', 'name' => '', 'kind' => '', 'on' => true]) . '</template>';
        echo '</div>';
    }

    /** @param array{id:string,name:string,kind:string,on:bool} $c */
    private static function chatRow(string $key, string $i, array $c): string
    {
        $n    = $key . '[' . $i . ']';
        $kind = ['person' => ['user', 'Person'], 'group' => ['users', 'Group'], 'channel' => ['megaphone', 'Channel']][$c['kind']] ?? (str_starts_with($c['id'], '-') ? ['users', 'Group or channel'] : (str_starts_with($c['id'], '@') ? ['megaphone', 'Channel'] : ['user', 'Person']));

        return '<li class="za-chat' . ($c['on'] ? '' : ' is-off') . '" data-chat>'
            . '<span class="za-chat__ava" aria-hidden="true">' . Ui::icon($kind[0], 16) . '</span>'
            . '<span class="za-chat__text">'
            . '<input type="text" class="za-chat__name" name="' . esc_attr($n . '[name]') . '" value="' . esc_attr($c['name']) . '" placeholder="Name it (only you see this)" aria-label="Name" maxlength="60">'
            . '<small><span data-chat-kind>' . esc_html($kind[1]) . '</span> · <code data-chat-id>' . esc_html($c['id']) . '</code></small>'
            . '<input type="hidden" name="' . esc_attr($n . '[id]') . '" value="' . esc_attr($c['id']) . '">'
            . '<input type="hidden" name="' . esc_attr($n . '[kind]') . '" value="' . esc_attr($c['kind']) . '">'
            . '</span>'
            . '<span class="za-chat__state" aria-live="polite"></span>'
            . '<button type="button" class="za-btn za-btn--sm za-btn--ghost" data-chat-test>' . Ui::icon('send', 14) . '<span>Test</span></button>'
            . '<label class="za-switch za-switch--sm" title="Gets new requests"><input type="checkbox" name="' . esc_attr($n . '[on]') . '" value="1"' . ($c['on'] ? ' checked' : '') . ' aria-label="Gets new requests"><span class="za-switch__track"></span></label>'
            . '<button type="button" class="za-btn za-btn--icon" data-chat-remove aria-label="Remove" title="Remove">' . Ui::icon('x', 16) . '</button>'
            . '</li>';
    }

    /** Under a reply text: how it reads on a real request, as you type */
    private function renderPreview(string $key): void
    {
        if (!in_array($key, ['reply_email', 'reply_wa'], true)) {
            return;
        }

        $sample = ['first' => 'Mina', 'name' => 'Mina Karimi', 'needs' => 'ai visuals and post-production'];
        printf(
            '<div class="za-preview za-preview--%s" data-preview="%s" data-sample="%s" aria-live="polite"><span class="za-preview__label">%s</span><div class="za-preview__bubble"></div></div>',
            $key === 'reply_wa' ? 'wa' : 'mail',
            esc_attr($key),
            esc_attr((string) wp_json_encode($sample)),
            $key === 'reply_wa' ? Ui::icon('whatsapp', 13) . 'How it opens in WhatsApp, for Mina Karimi' : Ui::icon('mail', 13) . 'How it opens in your mail app, for Mina Karimi'
        );
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
        $bot   = get_option(self::BOT, []);
        $all   = Settings::recipients();
        $on    = count(array_filter($all, static fn ($c) => $c['on']));
        $this->statusCard('Telegram', 'telegram', [
            'Notifications' => Settings::bool('tg_enabled') ? ['ok', 'On'] : ['warn', 'Off: requests only wait in this panel'],
            'Bot'           => Settings::text('tg_token') === '' ? ['error', 'No token: add it under Telegram'] : (is_array($bot) && !empty($bot['username']) ? ['ok', '@' . $bot['username']] : ['ok', 'Token saved']),
            'Chats'         => $all === [] ? ['error', 'None: press "Find chats" under Telegram'] : ($on === 0 ? ['warn', 'All ' . count($all) . ' paused'] : ['ok', Fmt::plural($on, 'chat') . ($on < count($all) ? ', ' . (count($all) - $on) . ' paused' : '')]),
            'Last message'  => $last === [] ? ['muted', 'Nothing sent yet'] : (!empty($last['ok']) ? ['ok', 'Delivered ' . Fmt::ago((int) $last['at'])] : ['error', 'Not delivered' . (!empty($last['chat']) ? ' to ' . $last['chat'] : '') . ': ' . Telegram::explain((string) ($last['error'] ?? '')) . ' (' . Fmt::ago((int) ($last['at'] ?? 0)) . ')']),
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
