<?php

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin\Settings;

if (!defined('ABSPATH')) {
    exit;
}

enum FieldType: string
{
    case Text     = 'text';
    case Secret   = 'secret';     // never printed in HTML, and stored apart
    case Number   = 'number';
    case Toggle   = 'toggle';
    case Select   = 'select';
    case Textarea = 'textarea';
}
