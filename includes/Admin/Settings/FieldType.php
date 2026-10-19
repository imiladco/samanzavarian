<?php

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin\Settings;

if (!defined('ABSPATH')) {
    exit;
}

enum FieldType: string
{
    case Text     = 'text';
    case Secret   = 'secret';     // در HTML هرگز نمایش داده نمی‌شود و جدا ذخیره می‌شود
    case Number   = 'number';
    case Toggle   = 'toggle';
    case Select   = 'select';
    case Textarea = 'textarea';
}
