<?php

/**
 * شِمای تنظیمات — تنها جایی که «چه تنظیمی وجود دارد» تعریف می‌شود.
 *
 * صفحه تنظیمات، مقادیر پیش‌فرض، اعتبارسنجی و ذخیره همه از همین ساخته
 * می‌شوند؛ تنظیم تازه یعنی یک ردیف تازه این‌جا، نه دست زدن به چند جا.
 *
 * قاعده نوشتن label و help: برای مدیری که تازه آمده. بگو این گزینه دقیقاً
 * چه می‌کند و کجا اثر دارد، «۰ / خالی یعنی چه»، و اصطلاح داخلی ننویس.
 */

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class Schema
{
    /** @var list<Section>|null */
    private static ?array $cache = null;

    /** @return list<Section> */
    public static function sections(): array
    {
        return self::$cache ??= [self::telegram(), self::replies(), self::data()];
    }

    public static function section(string $id): ?Section
    {
        foreach (self::sections() as $section) {
            if ($section->id === $id) {
                return $section;
            }
        }

        return null;
    }

    /** @return array<string,Field> */
    public static function fields(): array
    {
        $fields = [];
        foreach (self::sections() as $section) {
            foreach ($section->fields as $field) {
                $fields[$field->key] = $field;
            }
        }

        return $fields;
    }

    public static function field(string $key): ?Field
    {
        return self::fields()[$key] ?? null;
    }

    /** @return array<string,string|int|bool> پیش‌فرض فیلدهای عادی (کلیدهای محرمانه عمداً نیستند) */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::fields() as $key => $field) {
            if (!$field->isSecret()) {
                $out[$key] = $field->default;
            }
        }

        return $out;
    }

     /* ---- بخش‌ها ---------------------------------------------------------- */

    private static function telegram(): Section
    {
        return new Section(
            id: 'telegram',
            title: 'تلگرام',
            icon: 'telegram',
            description: 'هر درخواست تازه فرم پروژه، با نام، نیاز، زمان‌بندی، بودجه و متن پیام، در تلگرام خودتان می‌رسد. درخواست همیشه اول در همین پیشخوان ذخیره می‌شود؛ تلگرام فقط خبر می‌دهد.',
            fields: [
                new Field(
                    key: 'tg_enabled',
                    type: FieldType::Toggle,
                    label: 'خبر دادن در تلگرام',
                    help: 'خاموش = درخواست‌ها فقط در همین پیشخوان می‌مانند و پیامی به تلگرام نمی‌رود.',
                    default: true,
                ),
                new Field(
                    key: 'tg_token',
                    type: FieldType::Secret,
                    label: 'توکن ربات',
                    help: 'همان متنی که BotFather بعد از ساختن ربات می‌دهد (شبیه 123456789:AA…). برای امنیت بیشتر می‌توانید آن را با ثابت SZW_TELEGRAM_TOKEN در wp-config.php تعریف کنید.',
                    constant: 'SZW_TELEGRAM_TOKEN',
                ),
                new Field(
                    key: 'tg_chat',
                    type: FieldType::Text,
                    label: 'شناسه چت',
                    help: 'جایی که پیام‌ها می‌رسند. لازم نیست دستی بنویسید: ربات را در تلگرام Start کنید و دکمه «پیدا کردن چت» را بزنید.',
                    placeholder: '123456789',
                ),
            ],
        );
    }

    private static function replies(): Section
    {
        return new Section(
            id: 'replies',
            title: 'متن پاسخ‌ها',
            icon: 'reply',
            description: 'متن آماده دکمه‌های «پاسخ با ایمیل» و «پاسخ در واتس‌اپ» در صفحه هر درخواست. قبل از فرستادن همیشه قابل ویرایش است.',
            fields: [
                new Field(
                    key: 'reply_subject',
                    type: FieldType::Text,
                    label: 'موضوع ایمیل',
                    help: 'خالی = «Re: your project request».',
                    default: 'Re: your project request',
                ),
                new Field(
                    key: 'reply_email',
                    type: FieldType::Textarea,
                    label: 'متن ایمیل',
                    help: 'جای‌گذاری‌ها: {first} نام کوچک، {needs} نیازهایی که انتخاب کرده. پیام خودش زیر متن نقل می‌شود.',
                    default: "Hi {first},\n\nThanks for your message about {needs}. ",
                ),
                new Field(
                    key: 'reply_wa',
                    type: FieldType::Textarea,
                    label: 'متن واتس‌اپ',
                    help: 'جای‌گذاری‌ها: {first} و {needs}.',
                    default: 'Hi {first}, this is Saman. Thanks for your message about {needs}.',
                ),
            ],
        );
    }

    private static function data(): Section
    {
        return new Section(
            id: 'data',
            title: 'نگه‌داری داده',
            icon: 'shield',
            description: 'فرم به بازدیدکننده قول می‌دهد اطلاعاتش «فقط برای پاسخ» است. این‌جا تعیین می‌کنید درخواست‌های تمام‌شده تا کی بمانند.',
            fields: [
                new Field(
                    key: 'retention',
                    type: FieldType::Number,
                    label: 'حذف درخواست‌های بسته‌شده بعد از',
                    help: 'درخواست‌هایی که «قرارداد» یا «منتفی» شده‌اند، این مدت بعد از بسته شدن خودکار پاک می‌شوند. ۰ = هیچ‌وقت پاک نشوند.',
                    default: 0,
                    unit: 'روز',
                    min: 0,
                    max: 3650,
                ),
                new Field(
                    key: 'spam_days',
                    type: FieldType::Number,
                    label: 'حذف اسپم بعد از',
                    help: 'پیام‌هایی که پر از لینک بودند و کنار گذاشته شدند. ۰ = هیچ‌وقت.',
                    default: 30,
                    unit: 'روز',
                    min: 0,
                    max: 365,
                ),
            ],
        );
    }
}
