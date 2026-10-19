<?php

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * One setting.
 *
 * $constant: if a constant of this name is defined in wp-config.php, its
 * value is used and the field is read-only on the page (the right way to
 * keep API keys out of the database).
 */
final class Field
{
    /** @param array<string,string> $choices */
    public function __construct(
        public readonly string $key,
        public readonly FieldType $type,
        public readonly string $label,
        public readonly string $help = '',
        public readonly string|int|bool $default = '',
        public readonly array $choices = [],
        public readonly ?string $constant = null,
        public readonly string $unit = '',
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly string $placeholder = '',
    ) {}

    public function isSecret(): bool
    {
        return $this->type === FieldType::Secret;
    }

    public function isLocked(): bool
    {
        return $this->constant !== null && defined($this->constant);
    }
}
