<?php

/**
 * The Requests panel, built on the admin kit.
 *
 * Three pages under one menu: Overview, Requests (list and detail), Settings
 * (and System status). The data is the Project Form's requests (Leads); this
 * is where they are read and handled.
 *
 * Needs PHP 8.1 (enum, match, readonly): Leads::kit() checks before any of
 * these classes load, and older PHP keeps the plain WordPress list.
 */

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin;

use Samanzavarian_Widgets\Leads;

if (!defined('ABSPATH')) {
    exit;
}

final class Kit
{
    /** Every page slug starts with this; admin.js and Ui recognise the panel's pages by it */
    public const SLUG_PREFIX = 'szw';

    public const CAPABILITY = 'manage_options';

    /** status → [label, pill tone] */
    public const STATUSES = [
        'new'     => ['New', 'info'],
        'talking' => ['Talking', 'warn'],
        'won'     => ['Won', 'ok'],
        'lost'    => ['Lost', 'muted'],
        'spam'    => ['Spam', 'error'],
    ];

    /** list views → label */
    public const VIEWS = ['all' => 'All', 'new' => 'New', 'talking' => 'Talking', 'won' => 'Won', 'lost' => 'Lost', 'spam' => 'Spam'];

    /** A status in the events: "Status: Talking" */
    public static function statusText(string $status): string
    {
        return 'Status: ' . (self::STATUSES[$status][0] ?? $status);
    }

    public static function boot(): void
    {
        if (!is_admin()) {
            return;
        }

        Ui::register();
        DashboardPage::register();
        RequestsPage::register();
        SettingsPage::register();

        // the old WordPress list URLs (Telegram links from before the panel) land in the panel
        add_action('load-edit.php', static function (): void {
            if (($_GET['post_type'] ?? '') === Leads::TYPE) {
                wp_safe_redirect(Ui::url(RequestsPage::SLUG));
                exit;
            }
        });
        add_action('load-post.php', static function (): void {
            $id = absint($_GET['post'] ?? 0);
            if ($id && get_post_type($id) === Leads::TYPE) {
                wp_safe_redirect(Ui::url(RequestsPage::SLUG, ['item' => $id]));
                exit;
            }
        });
    }

    /** One request's page; the link Telegram messages carry */
    public static function requestUrl(int $id): string
    {
        return Ui::url(RequestsPage::SLUG, ['item' => $id]);
    }

    /** "Hi {first}" → a ready reply with this request's name and needs */
    public static function fill(string $template, array $r): string
    {
        $first = strtok((string) $r['name'], ' ') ?: (string) $r['name'];
        $needs = $r['services'] ? implode(' and ', array_map('strtolower', (array) $r['services'])) : 'your project';

        return strtr($template, ['{first}' => $first, '{name}' => (string) $r['name'], '{needs}' => $needs]);
    }
}
