<?php

/**
 * خواندن، اعتبارسنجی و ذخیره تنظیمات.
 *
 * دو ردیف option:
 *   szw_settings  تنظیمات عادی (autoload)
 *   szw_secrets   کلیدهای API (autoload نه — فقط هنگام تماس با سرویس خوانده می‌شود)
 *
 * جدا بودنشان عمدی است: کلیدها در خروجی‌های عیب‌یابی ظاهر نمی‌شوند. برای
 * امنیت بیشتر می‌توانید پیش از ذخیره رمزنگاری‌شان کنید (مثلاً sodium با
 * کلیدی مشتق از نمک‌های wp-config).
 */

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin\Settings;

use Samanzavarian_Widgets\Admin\Fa;

if (!defined('ABSPATH')) {
    exit;
}

final class Settings
{
    public const OPTION  = 'szw_settings';
    public const SECRETS = 'szw_secrets';

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

    /** «sk_l…9f2c» — فقط برای اینکه مدیر بفهمد کدام کلید ذخیره است */
    public static function mask(string $key): string
    {
        $value = self::text($key);

        return strlen($value) <= 8 ? str_repeat('•', strlen($value)) : substr($value, 0, 4) . '…' . substr($value, -4);
    }

    /**
     * تنظیمات نسخه‌های پیش از این پیشخوان (szw_telegram) یک بار به این‌جا می‌آیند:
     * توکن به کلیدهای محرمانه، چت و مدت نگه‌داری به تنظیمات.
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
     * ذخیره فقط فیلدهای یک بخش.
     *
     * ⚠️ هر زبانه فقط فیلدهای خودش را می‌فرستد. بدون محدود کردن به بخش،
     * چک‌باکس ارسال‌نشده بخش دیگر «خاموش» و متن ارسال‌نشده «خالی» ذخیره می‌شد.
     * فیلد محرمانه خالی یعنی «دست نزن»؛ پاک کردنش فقط با تیک «حذف».
     *
     * @param array<string,mixed> $input ورودی wp_unslash شده
     * @return list<string> خطاها (خالی = ذخیره شد)
     */
    public static function save(array $input, string $sectionId): array
    {
        $section = Schema::section($sectionId);

        if ($section === null) {
            return ['بخش تنظیمات ناشناخته است.'];
        }

        $values  = self::all();
        $secrets = get_option(self::SECRETS, []);
        $secrets = is_array($secrets) ? $secrets : [];
        $errors  = [];

        foreach ($section->fields as $field) {
            if ($field->isLocked()) {
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
                $errors[] = '«' . $field->label . '»: ' . $error;
                continue;
            }

            $values[$field->key] = $value;
        }

        if ($errors !== []) {
            return $errors;
        }

        update_option(self::OPTION, $values, true);
        update_option(self::SECRETS, $secrets, false);
        self::$cache = null;

        do_action('szw_settings_saved');

        return [];
    }

    /** @return array{0:string|int|bool,1:?string} [مقدار، خطا] */
    private static function sanitize(Field $field, mixed $raw): array
    {
        switch ($field->type) {
            case FieldType::Toggle:
                return [(bool) $raw, null];

            case FieldType::Number:
                $digits = Fa::only((string) $raw);

                if ($digits === '') {
                    return [(int) $field->default, null];
                }

                $n = (int) $digits;
                if ($field->min !== null && $n < $field->min) {
                    return [0, 'نباید کمتر از ' . Fa::digits($field->min) . ' باشد.'];
                }
                if ($field->max !== null && $n > $field->max) {
                    return [0, 'نباید بیشتر از ' . Fa::digits($field->max) . ' باشد.'];
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
