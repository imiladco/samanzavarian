<?php

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/** یک بخش (زبانه) تنظیمات؛ هر بخش جدا ذخیره می‌شود */
final class Section
{
    /** @param list<Field> $fields */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $icon = 'settings',
        public readonly string $description = '',
        public readonly array $fields = [],
    ) {}
}
