<?php

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/** One settings section (tab); each section is saved on its own */
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
