<?php

/**
 * Overview:
 *
 *   1. title + buttons
 *   2. health: Telegram, cron, the form on the site; each problem an alert with its cause and fix
 *   3. range + tiles: requests, waiting for an answer, response time, won
 *   4. two columns: daily trend (answered / waiting) | statuses
 *   5. two columns: latest requests | needs, budgets, timelines
 */

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin;

use Samanzavarian_Widgets\Leads;
use Samanzavarian_Widgets\Admin\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class DashboardPage
{
    public const SLUG = 'szw';

    private const RANGES = [7, 30, 90];

    public static function register(): void
    {
        add_action('admin_menu', static function (): void {
            $page  = new self();
            $count = Leads::counts()['new'];
            // the menu badge: only what needs you (new requests)
            $badge = $count > 0 ? ' <span class="awaiting-mod">' . esc_html((string) $count) . '</span>' : '';

            add_menu_page('Requests', 'Requests' . $badge, Kit::CAPABILITY, self::SLUG, [$page, 'render'], 'dashicons-email-alt', 26);
            add_submenu_page(self::SLUG, 'Overview', 'Overview', Kit::CAPABILITY, self::SLUG, [$page, 'render'], 0);
        }, 9);
    }

    public function render(): void
    {
        if (!current_user_can(Kit::CAPABILITY)) {
            wp_die('You are not allowed to see this page.');
        }

        $range = isset($_GET['range']) ? absint($_GET['range']) : 30;
        $range = in_array($range, self::RANGES, true) ? $range : 30;
        $label = 'Last ' . $range . ' days';
        $s     = Leads::stats($range);

        Ui::open(self::SLUG);
        Ui::head(
            'Overview',
            'Today, ' . Fmt::date(time()) . ' — Project Form requests and how they are handled',
            Ui::button('Settings', Ui::url(SettingsPage::SLUG), 'ghost', 'settings')
                . Ui::button('All requests', Ui::url(RequestsPage::SLUG), 'primary', 'inbox')
        );

        $this->renderHealth();

        echo '<div class="za-toolbar za-toolbar--range"><span class="za-muted">' . esc_html($label . ', against the ' . $range . ' days before') . '</span><nav class="za-seg" aria-label="Range">';
        foreach (self::RANGES as $r) {
            printf('<a href="%s" class="za-seg__item%s"%s>%s</a>', esc_url(Ui::url(self::SLUG, ['range' => $r])), $r === $range ? ' is-current' : '', $r === $range ? ' aria-current="page"' : '', esc_html($r . ' days'));
        }
        echo '</nav></div>';

        $p      = $s['period'];
        $b      = $s['prev'];
        $counts = Leads::counts();
        $oldest = $this->oldestWaiting();
        $rate   = $p['closed'] > 0 ? (int) round($p['won'] / $p['closed'] * 100) : null;

        echo '<div class="za-tiles">'
            . Ui::tile('Requests', 'inbox', Fmt::num($p['count']), $label, $s['spam'] > 0 ? Fmt::num($s['spam']) . ' spam kept aside' : 'No spam', $p['count'], $b['count'])
            . Ui::tile('Waiting', 'clock', Fmt::num($counts['new']), 'Right now', $oldest > 0 ? 'Oldest: ' . Fmt::ago($oldest) : 'Everyone has an answer')
            . Ui::tile('Response time', 'reply', $p['wait'] >= 0 ? Fmt::duration($p['wait']) : '—', $label, $b['wait'] >= 0 ? 'Before: ' . Fmt::duration($b['wait']) : 'Median, from arrival to first answer')
            . Ui::tile('Won', 'trophy', Fmt::num($p['won']), $label, $rate !== null ? $rate . '% of closed requests' : 'Nothing closed yet', $p['won'], $b['won'])
            . '</div>';

        echo '<div class="za-grid za-grid--main">';
        $this->renderChart($s['days'], $label);
        $this->renderStatuses($s['statuses'], $label);
        echo '</div>';

        echo '<div class="za-grid za-grid--main">';
        $this->renderRecent();
        echo '<div class="za-stack">';
        $this->renderTally('Needs', 'spark', $s['services'], $label, 'One request can have several needs.');
        $this->renderTally('Budget', 'wallet', $s['budgets'], $label);
        $this->renderTally('Timeline', 'clock', $s['timeline'], $label);
        echo '</div></div>';

        Ui::close();
    }

    private function oldestWaiting(): int
    {
        $ts = 0;
        foreach (Leads::rows() as $r) {
            if ($r['status'] === 'new') {
                $ts = $r['ts']; // newest first: the last one seen is the oldest
            }
        }

        return $ts;
    }

    /** Each problem: [tone, title, cause and effect, fix URL, button label] */
    private function renderHealth(): void
    {
        $issues = [];
        $tg     = Ui::url(SettingsPage::SLUG, ['tab' => 'telegram']);
        $status = Ui::url(SettingsPage::SLUG, ['tab' => 'status']);
        $last   = get_option('szw_tg_last', []);

        if (!Settings::bool('tg_enabled')) {
            $issues[] = ['warn', 'Telegram notifications are off', 'Requests only wait here; check in so nothing is missed.', $tg, 'Turn on'];
        } elseif (Settings::text('tg_token') === '') {
            $issues[] = ['error', 'Telegram is not connected', 'Until the bot token is added, new requests are not announced on Telegram.', $tg, 'Connect'];
        } elseif (Settings::text('tg_chat') === '') {
            $issues[] = ['error', 'No Telegram chat yet', 'Press Start in your bot on Telegram, then "Find my chat" in Settings.', $tg, 'Find my chat'];
        } elseif (is_array($last) && isset($last['ok']) && !$last['ok']) {
            $issues[] = ['error', 'The last Telegram message did not arrive', 'Telegram said: ' . ($last['error'] ?? '') . '. The request is saved and will be tried again.', $status, 'System status'];
        }

        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $issues[] = ['warn', 'WordPress scheduled tasks are off', 'Telegram retries and automatic clean-up only run if your host runs the cron itself.', $status, 'System status'];
        }

        if (self::formPages() === 0) {
            $issues[] = ['info', 'The Project Form is on no page', 'Add the "Project Form" widget to your contact page in Elementor so requests can arrive.', admin_url('edit.php?post_type=page'), 'Pages'];
        }

        if ($issues === []) {
            echo '<div class="za-health za-health--ok">' . Ui::icon('check', 18) . '<strong>All good</strong><span>The form is on the site and Telegram is connected.</span></div>';
            return;
        }

        echo '<div class="za-issues">';
        foreach ($issues as [$tone, $title, $text, $url, $action]) {
            echo Ui::alert($tone, $text, $title, Ui::button($action, $url, 'sm'));
        }
        echo '</div>';
    }

    /** Published pages that carry the Project Form widget */
    public static function formPages(): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_elementor_data' AND pm.meta_value LIKE %s AND p.post_status = 'publish'",
                '%' . $wpdb->esc_like('"widgetType":"sz-form"') . '%'
            )
        );
    }

    /**
     * Stacked daily columns: answered (navy) and waiting (orange); the same data as a table below
     *
     * @param list<array{ts:int,a:int,b:int}> $days
     */
    private function renderChart(array $days, string $label): void
    {
        $max   = max(1, ...array_map(static fn (array $d): int => $d['a'] + $d['b'], $days));
        $step  = $max <= 5 ? 1 : ($max <= 20 ? 5 : 10);
        $top   = (int) (ceil($max / $step) * $step);
        $total = array_sum(array_map(static fn (array $d): int => $d['a'] + $d['b'], $days));

        Ui::cardOpen('Requests, ' . strtolower($label), 'chart', '<div class="za-legend"><span><i class="za-sw za-sw--a"></i>Answered</span><span><i class="za-sw za-sw--b"></i>Waiting</span></div>', 'za-card--chart');

        if ($total === 0) {
            echo Ui::empty('chart', 'No requests in this period', 'The chart fills with the first request from the form.');
            Ui::cardClose();
            return;
        }

        $many = count($days) > 31;
        echo '<div class="za-chart' . (count($days) >= 30 ? ' za-chart--30' : '') . ($many ? ' za-chart--90' : '') . '" role="img" aria-label="' . esc_attr('Requests, ' . strtolower($label) . ': ' . $total . ' in all') . '">';
        echo '<div class="za-chart__axis"><span>' . esc_html((string) $top) . '</span><span>' . esc_html((string) intdiv($top, 2)) . '</span><span>0</span></div>';
        echo '<div class="za-chart__plot"><i class="za-chart__grid" style="bottom:50%"></i><i class="za-chart__grid" style="bottom:100%"></i>';

        $last  = count($days) - 1;
        $every = $last < 7 ? 1 : ($many ? 15 : 5);
        foreach ($days as $i => $d) {
            $tip = Fmt::date($d['ts']) . ': ' . $d['a'] . ' answered, ' . $d['b'] . ' waiting';

            echo '<div class="za-chart__col' . ($i === $last ? ' is-today' : '') . '" data-tip="' . esc_attr($tip) . '" tabindex="0" aria-label="' . esc_attr($tip) . '"><div class="za-chart__bars">';
            if ($d['b'] > 0) {
                echo '<i class="za-chart__seg za-chart__seg--b" style="height:' . round($d['b'] / $top * 100, 2) . '%"></i>';
            }
            if ($d['a'] > 0) {
                echo '<i class="za-chart__seg za-chart__seg--a" style="height:' . round($d['a'] / $top * 100, 2) . '%"></i>';
            }
            echo '</div>' . (($last - $i) % $every === 0 ? '<span class="za-chart__x">' . esc_html($i === $last ? 'Today' : ($last < 7 ? Fmt::day($d['ts']) : Fmt::dayMonth($d['ts']))) . '</span>' : '') . '</div>';
        }
        echo '</div></div>';

        echo '<details class="za-tableview"><summary>Show as a table</summary><table class="za-table za-table--compact"><thead><tr><th>Day</th><th>Answered</th><th>Waiting</th></tr></thead><tbody>';
        foreach (array_reverse($days) as $d) {
            if ($d['a'] + $d['b'] === 0) {
                continue;
            }
            printf('<tr><td>%s</td><td>%d</td><td>%d</td></tr>', esc_html(Fmt::date($d['ts'])), $d['a'], $d['b']);
        }
        echo '</tbody></table></details>';

        Ui::cardClose();
    }

    /** @param array<string,int> $statuses horizontal bars in each status's tone */
    private function renderStatuses(array $statuses, string $label): void
    {
        Ui::cardOpen('Statuses', 'shield', '<span class="za-muted">' . esc_html($label) . '</span>');

        $total = array_sum($statuses);
        if ($total === 0) {
            echo Ui::empty('inbox', 'No requests yet');
            Ui::cardClose();
            return;
        }

        $max = max($statuses);
        echo '<ul class="za-bars">';
        foreach ($statuses as $key => $n) {
            [$text, $tone] = Kit::STATUSES[$key];
            $pct = (int) round($n / $total * 100);
            echo '<li data-tip="' . esc_attr($text . ': ' . $n . ' (' . $pct . '%)') . '" tabindex="0">'
                . '<span class="za-bars__k"><a href="' . esc_url(Ui::url(RequestsPage::SLUG, ['view' => $key])) . '">' . Ui::pill($text, $tone) . '</a></span>'
                . '<span class="za-bars__track"><i class="za-bars__fill za-bars__fill--' . esc_attr($tone) . '" style="width:' . ($n > 0 ? max(3, round($n / $max * 100, 1)) : 0) . '%"></i></span>'
                . '<span class="za-bars__n">' . esc_html(Fmt::num($n)) . '</span></li>';
        }
        echo '</ul>';

        Ui::cardClose();
    }

    private function renderRecent(): void
    {
        $rows = Leads::search('all', '', 1, 8)['rows'];

        Ui::cardOpen('Latest requests', 'inbox', Ui::button('All', Ui::url(RequestsPage::SLUG), 'link', 'back'), 'za-card--flush');

        if ($rows === []) {
            echo Ui::empty('inbox', 'No requests yet', 'When someone sends the Project Form, it appears here and on Telegram.');
            Ui::cardClose();
            return;
        }

        echo '<ul class="za-feed">';
        foreach ($rows as $r) {
            [$state, $tone] = Kit::STATUSES[$r['status']];
            $needs = $r['services'] ? implode(', ', (array) $r['services']) : 'No need picked';

            echo '<li><a href="' . esc_url(Ui::url(RequestsPage::SLUG, ['item' => (int) $r['id']])) . '">'
                . Ui::avatar((string) $r['name'], $r['status'] === 'new' ? '' : 'alt')
                . '<span class="za-feed__who"><strong>' . esc_html((string) $r['name']) . '</strong>'
                . '<small>' . esc_html(Fmt::ago((int) $r['ts']) . ' — ' . $needs) . '</small></span>'
                . '<span class="za-feed__meta">' . Ui::pill($state, $tone) . '</span></a></li>';
        }
        echo '</ul>';

        Ui::cardClose();
    }

    /** @param array<string,int> $tally plain bars; each row opens the list filtered by it */
    private function renderTally(string $title, string $icon, array $tally, string $label, string $hint = ''): void
    {
        Ui::cardOpen($title, $icon, '<span class="za-muted">' . esc_html($label) . '</span>');

        if ($tally === []) {
            echo '<p class="za-hint">Nobody picked this in the period.</p>';
            Ui::cardClose();
            return;
        }

        $max   = max($tally);
        $param = $icon === 'spark' ? 'svc' : ($icon === 'wallet' ? 'budget' : '');
        echo '<ul class="za-bars za-bars--plain">';
        foreach (array_slice($tally, 0, 6, true) as $k => $n) {
            $name = esc_html((string) $k);
            echo '<li><span class="za-bars__k">' . ($param !== '' ? '<a href="' . esc_url(Ui::url(RequestsPage::SLUG, [$param => (string) $k])) . '">' . $name . '</a>' : $name) . '</span>'
                . '<span class="za-bars__track"><i class="za-bars__fill za-bars__fill--brand" style="width:' . max(3, round($n / $max * 100, 1)) . '%"></i></span>'
                . '<span class="za-bars__n">' . esc_html(Fmt::num($n)) . '</span></li>';
        }
        echo '</ul>' . ($hint !== '' ? '<p class="za-hint">' . esc_html($hint) . '</p>' : '');

        Ui::cardClose();
    }
}
