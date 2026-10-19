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
            if (!$field->isSecret() && $field->type !== FieldType::Chats) {
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
            description: 'Every new Project Form request is sent to Telegram the moment it arrives: who, what they need, timeline, budget, the message, and a button back to it here. One bot of your own sends it, to as many chats as you like: you, a teammate, a group or a channel. Requests are always saved here first; Telegram only tells you.',
            fields: [
                new Field(
                    key: 'tg_enabled',
                    type: FieldType::Toggle,
                    label: 'Send new requests to Telegram',
                    help: 'Off pauses every chat at once; requests then only wait in this panel.',
                    default: true,
                ),
                new Field(
                    key: 'tg_token',
                    type: FieldType::Secret,
                    label: 'Bot token',
                    help: 'From @BotFather when you create the bot; it looks like 123456789:AAF… It is checked with Telegram before it is saved. It can also live in wp-config.php as SZW_TELEGRAM_TOKEN.',
                    constant: 'SZW_TELEGRAM_TOKEN',
                ),
                new Field(
                    key: 'tg_chats',
                    type: FieldType::Chats,
                    label: 'Chats',
                    help: 'Who gets each request. Pause a chat to stop it for a while without losing it.',
                ),
            ],
        );
    }

    private static function replies(): Section
    {
        return new Section(
            id: 'replies',
            title: 'Reply buttons',
            icon: 'reply',
            description: 'Each request has two reply buttons. They are shortcuts, not connections: nothing is sent from the site. "Reply by email" opens your own mail app with the text below; "WhatsApp" opens your own WhatsApp on a chat with their number. You read it, change it if you like, and send it yourself.',
            fields: [
                new Field(
                    key: 'reply_subject',
                    type: FieldType::Text,
                    label: 'Email subject',
                    help: 'The subject line of "Reply by email".',
                    default: 'Re: your project request',
                    placeholder: 'Re: your project request',
                ),
                new Field(
                    key: 'reply_email',
                    type: FieldType::Textarea,
                    label: 'Email opening',
                    help: 'The start of the email; their message is quoted below it. {first} becomes their first name, {needs} what they picked.',
                    default: "Hi {first},\n\nThanks for your message about {needs}. ",
                ),
                new Field(
                    key: 'reply_wa',
                    type: FieldType::Textarea,
                    label: 'WhatsApp opening',
                    help: 'Only on requests that left a phone number. Opens WhatsApp (the app, or WhatsApp Web on a computer) with this already typed. {first} and {needs} work here too.',
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
