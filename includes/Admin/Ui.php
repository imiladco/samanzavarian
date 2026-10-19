<?php

/**
 * The Requests panel's shared parts (all styled by assets/admin/admin.css, from
 * the admin kit): shell and header, page title, card, button, one-button
 * form, pill, alert, empty state, avatar, key-value list, stat tile, dialog,
 * action notices.
 *
 * Rule: every output is escaped, except parameters whose name ends in Html
 * (markup already built by this class).
 */

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin;



if (!defined('ABSPATH')) {
    exit;
}

final class Ui
{
    /** The navigation on every page: slug → [label, icon] */
    private const TABS = [
        DashboardPage::SLUG => ['Overview', 'home'],
        RequestsPage::SLUG  => ['Requests', 'inbox'],
        SettingsPage::SLUG  => ['Settings', 'settings'],
    ];

    /** 24×24 line icons (stroke 1.8); a new icon is one more SVG path */
    private const ICONS = [
        'home'     => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1v-9.5Z',
        'list'     => 'M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01',
        'settings' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z',
        'phone'    => 'M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z',
        'globe'    => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm-9-9h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18',
        'key'      => 'M15 7a3 3 0 1 1-3.6 2.9L4 17.3V20h3v-2h2v-2h2l1.1-1.1A3 3 0 0 1 15 7Z',
        'users'    => 'M16 21v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1M9 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 9v-1a4 4 0 0 0-3-3.9M16 4.1a4 4 0 0 1 0 7.8',
        'user'     => 'M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z',
        'chart'    => 'M4 20V10m6 10V4m6 16v-7m4 7H2',
        'pulse'    => 'M3 12h4l3-8 4 16 3-8h4',
        'search'   => 'M21 21l-4.3-4.3M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z',
        'download' => 'M12 4v11m-5-5 5 5 5-5M5 20h14',
        'external' => 'M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5',
        'trash'    => 'M4 7h16M10 11v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3',
        'refresh'  => 'M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7',
        'copy'     => 'M9 9h11v11H9zM5 15H4V4h11v1',
        'check'    => 'M5 12.5l4.5 4.5L19 7.5',
        'alert'    => 'M12 8v5m0 3.5h.01M10.3 3.9 2.6 17a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
        'info'     => 'M12 16v-4m0-4h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z',
        'x'        => 'M18 6 6 18M6 6l12 12',
        'plus'     => 'M12 5v14M5 12h14',
        'arrow'    => 'M19 12H5m6-6-6 6 6 6',
        'back'     => 'M5 12h14m-6-6 6 6-6 6',
        'shield'   => 'M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z',
        'server'   => 'M4 4h16v6H4zM4 14h16v6H4zM8 7h.01M8 17h.01',
        'queue'    => 'M4 6h16M4 12h10M4 18h6',
        'bell'     => 'M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9Zm4.3 13a1.9 1.9 0 0 0 3.4 0',
        'send'     => 'M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z',
        'clock'    => 'M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'form'     => 'M5 3h14v18H5zM9 8h6M9 12h6M9 16h3',
        'filter'   => 'M3 5h18l-7 8v6l-4 2v-8L3 5Z',
        'chev-r'   => 'M9 6l6 6-6 6',
        'chev-l'   => 'M15 6l-6 6 6 6',
        'lock'     => 'M7 11V7a5 5 0 0 1 10 0v4M5 11h14v10H5z',
        'edit'     => 'M4 20h4L19 9l-4-4L4 16v4ZM13.5 6.5l4 4',
        'box'      => 'M21 8 12 3 3 8v8l9 5 9-5V8ZM3 8l9 5 9-5M12 13v8',
        'mail'     => 'M3 6h18v12H3zM3 7l9 6 9-6',
        'inbox'    => 'M3 13h5l2 3h4l2-3h5M5 5h14l2 8v6a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-6l2-8Z',
        'telegram' => 'M21 4 3 11l6 2.2L18 7l-7 7.5V20l3.2-3.6L18 19z',
        'whatsapp' => 'M4 20l1.3-4A8 8 0 1 1 8 18.7zM9 9.5c.5 2.5 2.5 4.5 5 5l1-1.5 2 1-.5 1.5c-3.5.5-8-4-7.5-7.5L10.5 7l1 2z',
        'reply'    => 'M10 8 4 13l6 5M4 13h11a5 5 0 0 1 5 5v1',
        'trophy'   => 'M8 4h8v5a4 4 0 0 1-8 0V4ZM8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 13v4M8 21h8M10 17h4',
        'spark'    => 'M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9z',
        'note'     => 'M5 3h10l4 4v14H5zM14 3v5h5M9 13h6M9 17h4',
        'link'     => 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1',
        'wallet'   => 'M3 7h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Zm0 0 2-3h11v3M17 13.5h.01',
    ];

    public static function register(): void
    {
        add_action('admin_enqueue_scripts', static function (): void {
            if (!self::ours()) {
                return;
            }

            $base = SZW_URL . 'assets/admin/';
            $dir  = SZW_PATH . 'assets/admin/';

            // filemtime: any change to the file breaks the browser cache
            wp_enqueue_style('szw-admin', $base . 'admin.css', [], SZW_VERSION . '.' . (int) @filemtime($dir . 'admin.css'));
            wp_enqueue_script('szw-admin', $base . 'admin.js', [], SZW_VERSION . '.' . (int) @filemtime($dir . 'admin.js'), ['in_footer' => true]);
        });

        add_filter('admin_body_class', static fn (string $c): string => self::ours() ? $c . ' szw-screen' : $c);
    }

    /** Is the current screen one of the panel's? */
    public static function ours(): bool
    {
        return str_starts_with((string) ($_GET['page'] ?? ''), Kit::SLUG_PREFIX);
    }

    public static function icon(string $name, int $size = 18): string
    {
        return sprintf(
            '<svg class="za-ico" viewBox="0 0 24 24" width="%1$d" height="%1$d" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="%2$s"/></svg>',
            $size,
            esc_attr(self::ICONS[$name] ?? '')
        );
    }

    /** Page start: shell, header and navigation */
    public static function open(string $current): void
    {
        $brand = 'Saman Zavarian';

        echo '<div class="wrap szw-admin" dir="ltr"><h1 class="screen-reader-text">' . esc_html($brand) . '</h1>';
        echo '<header class="za-top"><a class="za-brand" href="' . esc_url(self::url(DashboardPage::SLUG)) . '">'
            . '<span class="za-brand__mark">' . esc_html(mb_substr($brand, 0, 1)) . '</span><span><strong>' . esc_html($brand) . '</strong><small>Project requests</small></span></a>';
        echo '<nav class="za-nav" aria-label="Sections">';

        foreach (self::TABS as $slug => [$label, $icon]) {
            printf(
                '<a href="%s" class="za-nav__item%s"%s>%s<span>%s</span></a>',
                esc_url(self::url($slug)),
                $slug === $current ? ' is-current' : '',
                $slug === $current ? ' aria-current="page"' : '',
                self::icon($icon, 17),
                esc_html($label)
            );
        }

        echo '</nav></header>';
        // WordPress and other plugins' notices land here, not among the cards
        echo '<hr class="wp-header-end">';
    }

    public static function close(): void
    {
        echo '</div>';
    }

    /** Page title + one line of explanation + buttons */
    public static function head(string $title, string $sub = '', string $actionsHtml = '', string $beforeHtml = ''): void
    {
        echo '<div class="za-head"><div class="za-head__text">' . $beforeHtml . '<h2 class="za-title">' . esc_html($title) . '</h2>'
            . ($sub !== '' ? '<p class="za-sub">' . esc_html($sub) . '</p>' : '')
            . '</div>' . ($actionsHtml !== '' ? '<div class="za-head__actions">' . $actionsHtml . '</div>' : '') . '</div>';
    }

    /** @param 'ok'|'info'|'warn'|'error'|'muted'|'brand' $tone */
    public static function pill(string $text, string $tone = 'muted', string $icon = ''): string
    {
        return '<span class="za-pill za-pill--' . esc_attr($tone) . '">'
            . ($icon !== '' ? self::icon($icon, 13) : '<i class="za-pill__dot"></i>')
            . esc_html($text) . '</span>';
    }

    /**
     * A link styled as a button. Variants: '' (plain), primary, ghost, warn, danger, sm, link, icon
     *
     * @param array<string,string> $attrs
     */
    public static function button(string $label, string $href, string $variant = '', string $icon = '', array $attrs = []): string
    {
        $extra = '';
        foreach ($attrs as $name => $value) {
            $extra .= ' ' . esc_attr($name) . '="' . esc_attr($value) . '"';
        }

        return '<a class="za-btn' . self::variants($variant) . '" href="' . esc_url($href) . '"' . $extra . '>'
            . ($icon !== '' ? self::icon($icon, 16) : '') . '<span>' . esc_html($label) . '</span></a>';
    }

    /** A button that opens a dialog */
    public static function dialogButton(string $label, string $dialogId, string $variant = '', string $icon = ''): string
    {
        return '<button type="button" class="za-btn' . self::variants($variant) . '" data-dialog="' . esc_attr($dialogId) . '">'
            . ($icon !== '' ? self::icon($icon, 16) : '') . '<span>' . esc_html($label) . '</span></button>';
    }

    /**
     * A one-button POST form with a nonce; $confirm = a question asked before sending
     *
     * @param array<string,string|int> $fields
     */
    public static function postButton(string $nonce, array $fields, string $label, string $variant = '', string $icon = '', string $confirm = '', string $action = ''): string
    {
        $html = '<form method="post" class="za-inline"' . ($action !== '' ? ' action="' . esc_url($action) . '"' : '')
            . ($confirm !== '' ? ' data-confirm="' . esc_attr($confirm) . '"' : '') . '>'
            . wp_nonce_field($nonce, '_wpnonce', true, false);

        foreach ($fields as $name => $value) {
            $html .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '">';
        }

        return $html . '<button type="submit" class="za-btn' . self::variants($variant) . '"' . ($variant === 'icon' ? ' aria-label="' . esc_attr($label) . '" title="' . esc_attr($label) . '"' : '') . '>'
            . ($icon !== '' ? self::icon($icon, 16) : '') . ($variant === 'icon' ? '' : '<span>' . esc_html($label) . '</span>') . '</button></form>';
    }

    public static function cardOpen(string $title = '', string $icon = '', string $actionsHtml = '', string $class = ''): void
    {
        echo '<section class="za-card' . ($class !== '' ? ' ' . esc_attr($class) : '') . '">';

        if ($title !== '') {
            echo '<header class="za-card__head"><h3>' . ($icon !== '' ? self::icon($icon, 18) : '') . esc_html($title) . '</h3>'
                . ($actionsHtml !== '' ? '<div class="za-card__actions">' . $actionsHtml . '</div>' : '') . '</header>';
        }

        echo '<div class="za-card__body">';
    }

    public static function cardClose(): void
    {
        echo '</div></section>';
    }

    /**
     * Key-value list; a value is a string (escaped) or ['html' => …]; empty ones are left out
     *
     * @param array<string,string|array{html:string}> $rows
     */
    public static function kv(array $rows, string $class = ''): string
    {
        $html = '<dl class="za-kv' . ($class !== '' ? ' ' . esc_attr($class) : '') . '">';

        foreach ($rows as $label => $value) {
            if ($value === '') {
                continue;
            }

            $html .= '<div><dt>' . esc_html((string) $label) . '</dt><dd>' . (is_array($value) ? $value['html'] : esc_html($value)) . '</dd></div>';
        }

        return $html . '</dl>';
    }

    /** @param 'ok'|'warn'|'error'|'muted'|'info' $tone a coloured dot + text (status rows) */
    public static function state(string $tone, string $text): string
    {
        return '<span class="za-state za-state--' . esc_attr($tone) . '"><i></i><span>' . esc_html($text) . '</span></span>';
    }

    /** Empty state: always say why it is empty and what fills it */
    public static function empty(string $icon, string $title, string $text = ''): string
    {
        return '<div class="za-empty">' . self::icon($icon, 28) . '<strong>' . esc_html($title) . '</strong>'
            . ($text !== '' ? '<p>' . esc_html($text) . '</p>' : '') . '</div>';
    }

    /** @param 'ok'|'info'|'warn'|'error' $tone alert: short title + cause/fix + action button */
    public static function alert(string $tone, string $text, string $title = '', string $actionsHtml = ''): string
    {
        $icon = match ($tone) {
            'ok'    => 'check',
            'info'  => 'info',
            default => 'alert',
        };

        return '<div class="za-alert za-alert--' . esc_attr($tone) . '" role="' . ($tone === 'error' ? 'alert' : 'status') . '">'
            . self::icon($icon, 18) . '<div class="za-alert__body">'
            . ($title !== '' ? '<strong>' . esc_html($title) . '</strong>' : '')
            . '<span>' . esc_html($text) . '</span></div>'
            . ($actionsHtml !== '' ? '<div class="za-alert__actions">' . $actionsHtml . '</div>' : '') . '</div>';
    }

    /** The name's first letter as an avatar; $tone = 'alt' for the second colour */
    public static function avatar(string $name, string $tone = ''): string
    {
        $name    = trim($name);
        $initial = $name !== '' ? mb_substr($name, 0, 1) : '';

        return '<span class="za-avatar' . ($tone !== '' ? ' za-avatar--' . esc_attr($tone) : '') . '">'
            . ($initial !== '' ? esc_html($initial) : self::icon('user', 16)) . '</span>';
    }

    /**
     * Stat tile: label and period, big number, a sub line and the change in percent
     */
    public static function tile(string $label, string $icon, string $value, string $period, string $sub, int $now = 0, int $before = -1): string
    {
        $delta = '';
        if ($before >= 0 && !($before === 0 && $now === 0)) {
            if ($before === 0) {
                $delta = '<span class="za-delta za-delta--up">new</span>';
            } else {
                $pct   = (int) round(($now - $before) / $before * 100);
                $delta = '<span class="za-delta za-delta--' . ($pct >= 0 ? 'up' : 'down') . '">' . ($pct >= 0 ? '▲ ' : '▼ ') . esc_html((string) abs($pct)) . '%</span>';
            }
        }

        return '<div class="za-tile"><div class="za-tile__top"><span class="za-tile__icon">' . self::icon($icon, 18) . '</span>'
            . '<span class="za-tile__label">' . esc_html($label) . '<small>' . esc_html($period) . '</small></span></div>'
            . '<div class="za-tile__value">' . esc_html($value) . '</div>'
            . '<div class="za-tile__sub"><span>' . esc_html($sub) . '</span>' . $delta . '</div></div>';
    }

    /**
     * Opens a native <dialog> holding a POST form; write the body between dialogOpen and dialogClose
     */
    public static function dialogOpen(string $id, string $title, string $sub, string $nonce, string $action, string $avatarName = ''): void
    {
        echo '<dialog class="za-dialog" id="' . esc_attr($id) . '" aria-labelledby="' . esc_attr($id . '-title') . '">';
        echo '<form method="post" action="' . esc_url($action) . '" class="za-dialog__form">' . wp_nonce_field($nonce, '_wpnonce', true, false);
        echo '<header class="za-dialog__head">' . ($avatarName !== '' ? self::avatar($avatarName) : '')
            . '<span><h2 id="' . esc_attr($id . '-title') . '">' . esc_html($title) . '</h2>' . ($sub !== '' ? '<small>' . esc_html($sub) . '</small>' : '') . '</span>'
            . '<button type="button" class="za-btn za-btn--icon" data-dialog-close aria-label="Close">' . self::icon('x', 18) . '</button></header>';
        echo '<div class="za-dialog__body">';
    }

    public static function dialogClose(string $submitLabel, string $submitName, string $submitValue = '1'): void
    {
        echo '</div><footer class="za-dialog__foot"><button type="button" class="za-btn" data-dialog-close>Cancel</button>'
            . '<button type="submit" name="' . esc_attr($submitName) . '" value="' . esc_attr($submitValue) . '" class="za-btn za-btn--primary">' . self::icon('check', 16) . '<span>' . esc_html($submitLabel) . '</span></button></footer>';
        echo '</form></dialog>';
    }

    /** @param array<string,int|string> $args */
    public static function url(string $page, array $args = []): string
    {
        return add_query_arg(['page' => $page] + $args, admin_url('admin.php'));
    }

    /**
     * An action's result at the top of the page; admin.js also shows it as a toast
     *
     * @param list<array{ok:bool,text:string}> $notices
     */
    public static function notices(array $notices): void
    {
        foreach ($notices as $notice) {
            echo str_replace('<div class="za-alert ', '<div data-flash="' . ($notice['ok'] ? 'ok' : 'error') . '" class="za-alert ', self::alert($notice['ok'] ? 'ok' : 'error', $notice['text']));
        }
    }

    private static function variants(string $variant): string
    {
        $out = '';
        foreach (array_filter(explode(' ', $variant)) as $v) {
            $out .= ' za-btn--' . esc_attr($v);
        }

        return $out;
    }
}
