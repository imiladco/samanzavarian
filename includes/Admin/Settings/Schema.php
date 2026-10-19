<?php

/**
 * The settings schema: the one place that says which settings exist.
 *
 * The settings page, the defaults, the checks and saving all come from here;
 * a new setting is one more entry here, not edits in several places.
 *
 * Labels and help are written for someone new: what the option does, where
 * it shows, and what 0 or empty means.
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

    /** @return array<string,string|int|bool> defaults of the ordinary fields (secrets deliberately not) */
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

     /* ---- Sections ------------------------------------------------------- */

    private static function telegram(): Section
    {
        return new Section(
            id: 'telegram',
            title: 'Telegram',
            icon: 'telegram',
            description: 'Every new Project Form request arrives in your Telegram: name, needs, timeline, budget and the message. The request is always saved here first; Telegram only tells you.',
            fields: [
                new Field(
                    key: 'tg_enabled',
                    type: FieldType::Toggle,
                    label: 'Tell me on Telegram',
                    help: 'Off: requests stay in this panel only and nothing is sent to Telegram.',
                    default: true,
                ),
                new Field(
                    key: 'tg_token',
                    type: FieldType::Secret,
                    label: 'Bot token',
                    help: 'What BotFather gives you after you create the bot (looks like 123456789:AA…). For extra safety it can live in wp-config.php instead, as SZW_TELEGRAM_TOKEN.',
                    constant: 'SZW_TELEGRAM_TOKEN',
                ),
                new Field(
                    key: 'tg_chat',
                    type: FieldType::Text,
                    label: 'Chat ID',
                    help: 'Where messages arrive. No need to type it: press Start in your bot on Telegram, then "Find my chat" below.',
                    placeholder: '123456789',
                ),
            ],
        );
    }

    private static function replies(): Section
    {
        return new Section(
            id: 'replies',
            title: 'Reply templates',
            icon: 'reply',
            description: 'The ready text behind "Reply by email" and "WhatsApp" on each request. You can always edit it before sending.',
            fields: [
                new Field(
                    key: 'reply_subject',
                    type: FieldType::Text,
                    label: 'Email subject',
                    help: 'Empty: "Re: your project request".',
                    default: 'Re: your project request',
                ),
                new Field(
                    key: 'reply_email',
                    type: FieldType::Textarea,
                    label: 'Email',
                    help: 'Placeholders: {first} their first name, {needs} what they picked. Their message is quoted below your text.',
                    default: "Hi {first},\n\nThanks for your message about {needs}. ",
                ),
                new Field(
                    key: 'reply_wa',
                    type: FieldType::Textarea,
                    label: 'WhatsApp',
                    help: 'Placeholders: {first} and {needs}.',
                    default: 'Hi {first}, this is Saman. Thanks for your message about {needs}.',
                ),
            ],
        );
    }

    private static function data(): Section
    {
        return new Section(
            id: 'data',
            title: 'Data',
            icon: 'shield',
            description: 'The form promises visitors their details are "only used to reply". Here you decide how long finished requests stay.',
            fields: [
                new Field(
                    key: 'retention',
                    type: FieldType::Number,
                    label: 'Delete closed requests after',
                    help: 'Requests marked Won or Lost are deleted this long after they were closed. 0 keeps them forever.',
                    default: 0,
                    unit: 'days',
                    min: 0,
                    max: 3650,
                ),
                new Field(
                    key: 'spam_days',
                    type: FieldType::Number,
                    label: 'Delete spam after',
                    help: 'Messages that were full of links and kept aside. 0 keeps them forever.',
                    default: 30,
                    unit: 'days',
                    min: 0,
                    max: 365,
                ),
            ],
        );
    }
}
