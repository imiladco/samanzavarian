<?php

/**
 * Reading, checking and saving the settings.
 *
 * Three options:
 *   szw_settings  ordinary settings (autoloaded)
 *   szw_secrets   keys and tokens (not autoloaded; read only when calling the service)
 *   szw_tg_chats  the Telegram chats, a list (id, name, kind, on)
 *
 * Apart on purpose: secrets do not show up in debugging dumps of the settings.
 * They could be encrypted before saving too (sodium, with a key derived from
 * the wp-config salts).
 */

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin\Settings;

use Samanzavarian_Widgets\Admin\Fmt;

if (!defined('ABSPATH')) {
    exit;
}

final class Settings
{
    public const OPTION  = 'szw_settings';
    public const SECRETS = 'szw_secrets';
    public const CHATS   = 'szw_tg_chats';

    /** More would be a broadcast, not a notification */
    public const MAX_CHATS = 20;

    /** @var array<string,string|int|bool>|null */
    private static ?array $cache = null;

    public static function get(string $key): string|int|bool
    {
        $field = Schema::field($key);

        if ($field === null) {
            return '';
        }

        if ($field->isLocked()) {
            /** @var string|int|bool $value */
            $value = constant((string) $field->constant);
            return $value;
        }

        if ($field->isSecret()) {
            $secrets = get_option(self::SECRETS, []);
            return is_array($secrets) ? (string) ($secrets[$key] ?? '') : '';
        }

        if ($field->type === FieldType::Chats) {
            return count(self::recipients());
        }

        return self::all()[$key] ?? $field->default;
    }

    public static function text(string $key): string
    {
        $value = self::get($key);
        return is_bool($value) ? ($value ? '1' : '') : (string) $value;
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return (bool) self::get($key);
    }

    public static function hasSecret(string $key): bool
    {
        return self::text($key) !== '';
    }

    /** "1234…AAAA": just enough to tell which key is saved */
    public static function mask(string $key): string
    {
        $value = self::text($key);

        return strlen($value) <= 8 ? str_repeat('•', strlen($value)) : substr($value, 0, 4) . '…' . substr($value, -4);
    }

    /**
     * Settings from before the panel (szw_telegram) move in once: the token
     * to the secrets, the chat and the retention to the settings.
     */
    private static function migrate(): void
    {
        $old = get_option('szw_telegram', null);
        if (!is_array($old) || get_option(self::OPTION, null) !== null) {
            return;
        }
        update_option(self::OPTION, array_filter(['tg_chat' => (string) ($old['chat'] ?? ''), 'retention' => (int) ($old['retention'] ?? 0)], static fn ($v) => $v !== ''), true);
        if (!empty($old['token'])) {
            update_option(self::SECRETS, ['tg_token' => (string) $old['token']], false);
        }
    }

    /**
     * The Telegram chats, in the order they were added.
     *
     * The single chat of earlier versions (tg_chat) becomes the first entry,
     * once.
     *
     * @return list<array{id:string,name:string,kind:string,on:bool}>
     */
    public static function recipients(): array
    {
        $list = get_option(self::CHATS, null);

        if (!is_array($list)) {
            $old  = (string) (self::all()['tg_chat'] ?? '');
            $list = $old !== '' ? [['id' => $old, 'name' => '', 'kind' => '', 'on' => true]] : [];
            if ($list !== []) {
                update_option(self::CHATS, $list, true);
            }
        }

        $out = [];
        foreach ($list as $c) {
            if (is_array($c) && isset($c['id']) && (string) $c['id'] !== '') {
                $out[] = ['id' => (string) $c['id'], 'name' => (string) ($c['name'] ?? ''), 'kind' => (string) ($c['kind'] ?? ''), 'on' => !empty($c['on'])];
            }
        }

        return $out;
    }

    /** @return array<string,string|int|bool> */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::migrate();
            $stored      = get_option(self::OPTION, []);
            self::$cache = array_merge(Schema::defaults(), is_array($stored) ? $stored : []);
        }

        return self::$cache;
    }

    /**
     * Saves one section's fields only.
     *
     * Each tab sends only its own fields. Without keeping to the section, an
     * unsent checkbox of another section would be saved as off, and unsent
     * text as empty. An empty secret means "leave it"; it is cleared only by
     * ticking "remove".
     *
     * @param array<string,mixed> $input input, already wp_unslash'ed
     * @return list<string> errors (none = saved)
     */
    public static function save(array $input, string $sectionId): array
    {
        $section = Schema::section($sectionId);

        if ($section === null) {
            return ['Unknown settings section.'];
        }

        $values  = self::all();
        $secrets = get_option(self::SECRETS, []);
        $secrets = is_array($secrets) ? $secrets : [];
        $errors  = [];
        $chats   = null;

        foreach ($section->fields as $field) {
            if ($field->isLocked()) {
                continue;
            }

            if ($field->type === FieldType::Chats) {
                [$chats, $chatErrors] = self::sanitizeChats($input[$field->key] ?? []);
                array_push($errors, ...$chatErrors);
                continue;
            }

            if ($field->isSecret()) {
                $raw = trim((string) ($input[$field->key] ?? ''));

                if ($raw === '__clear__') {
                    unset($secrets[$field->key]);
                } elseif ($raw !== '') {
                    $secrets[$field->key] = $raw;
                }
                continue;
            }

            [$value, $error] = self::sanitize($field, $input[$field->key] ?? null);

            if ($error !== null) {
                $errors[] = '"' . $field->label . '": ' . $error;
                continue;
            }

            $values[$field->key] = $value;
        }

        if ($errors !== []) {
            return $errors;
        }

        update_option(self::OPTION, $values, true);
        update_option(self::SECRETS, $secrets, false);
        if ($chats !== null) {
            update_option(self::CHATS, $chats, true);
        }
        self::$cache = null;

        do_action('szw_settings_saved');

        return [];
    }

    /**
     * The chats as posted: tg_chats[n][id|name|kind|on]. A row with no id is
     * dropped (an unfinished "Add"); a second row with the same id too.
     *
     * @return array{0:list<array{id:string,name:string,kind:string,on:bool}>,1:list<string>}
     */
    private static function sanitizeChats(mixed $raw): array
    {
        $out    = [];
        $errors = [];
        $seen   = [];

        foreach (is_array($raw) ? $raw : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = preg_replace('/\s+/', '', (string) ($row['id'] ?? '')) ?? '';
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $name = trim(sanitize_text_field((string) ($row['name'] ?? '')));
            if (!\Samanzavarian_Widgets\Telegram::valid_chat($id)) {
                $errors[] = '"' . ($name !== '' ? $name : $id) . '": a chat id is a number (groups and channels start with -100) or a channel\'s @username.';
                continue;
            }
            $seen[$id] = true;
            $out[]     = ['id' => $id, 'name' => mb_substr($name, 0, 60), 'kind' => sanitize_key((string) ($row['kind'] ?? '')), 'on' => !empty($row['on'])];
        }

        if (count($out) > self::MAX_CHATS) {
            $errors[] = 'At most ' . self::MAX_CHATS . ' chats.';
        }

        return [$out, $errors];
    }

    /** @return array{0:string|int|bool,1:?string} [value, error] */
    private static function sanitize(Field $field, mixed $raw): array
    {
        switch ($field->type) {
            case FieldType::Toggle:
                return [(bool) $raw, null];

            case FieldType::Number:
                $digits = Fmt::only((string) $raw);

                if ($digits === '') {
                    return [(int) $field->default, null];
                }

                $n = (int) $digits;
                if ($field->min !== null && $n < $field->min) {
                    return [0, 'must be at least ' . $field->min . '.'];
                }
                if ($field->max !== null && $n > $field->max) {
                    return [0, 'must be at most ' . $field->max . '.'];
                }

                return [$n, null];

            case FieldType::Select:
                $value = (string) $raw;
                return [array_key_exists($value, $field->choices) ? $value : (string) $field->default, null];

            case FieldType::Textarea:
                $text = str_replace(["\r\n", "\r"], "\n", (string) $raw);
                return [trim(wp_strip_all_tags($text)), null];

            default:
                return [trim(sanitize_text_field((string) $raw)), null];
        }
    }
}
