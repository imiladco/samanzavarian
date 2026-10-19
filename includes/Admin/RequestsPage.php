<?php

/**
 * Requests: the list and one request (the kit's ItemsPage pattern).
 *
 * List:   views with counts (new, talking, won, lost, spam), search with the
 *         "/" key, filters by need, budget and time, bulk actions, clickable
 *         rows, paging, CSV export, a notice when a new request arrives.
 * Detail: newer/older (← →), a header with ready replies (email, WhatsApp,
 *         call), the message, details, events | status, note, Telegram, delete.
 *
 * Every action is a POST with a nonce and a capability check, and every change
 * is recorded in the request's events with the admin's name.
 */

declare(strict_types=1);

namespace Samanzavarian_Widgets\Admin;

use Samanzavarian_Widgets\Leads;
use Samanzavarian_Widgets\Telegram;
use Samanzavarian_Widgets\Admin\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class RequestsPage
{
    public const SLUG = 'szw-requests';

    private const NONCE    = 'szw_requests';
    private const LIVE     = 'szw_requests_live';
    private const CSV      = 'szw_requests_csv';
    private const PER_PAGE = 15;

    private const DELETE_NOTE = 'The request and its events are deleted for good.';

    /** @var list<array{ok:bool,text:string}> */
    private array $notices = [];

    public static function register(): void
    {
        $page = new self();

        add_action('admin_menu', static function () use ($page): void {
            add_submenu_page(DashboardPage::SLUG, 'Requests', 'Requests', Kit::CAPABILITY, self::SLUG, [$page, 'render'], 1);
        }, 20);

        add_action('wp_ajax_' . self::LIVE, [self::class, 'live']);
        add_action('admin_post_' . self::CSV, [self::class, 'csv']);
    }

    public function render(): void
    {
        if (!current_user_can(Kit::CAPABILITY)) {
            wp_die('You are not allowed to do this.');
        }

        $deleted = $this->maybeAct();
        $itemId  = isset($_GET['item']) && !$deleted ? absint($_GET['item']) : 0;

        Ui::open(self::SLUG);
        $itemId > 0 ? $this->renderDetail($itemId) : $this->renderList();
        Ui::close();
    }

    private static function me(): string
    {
        return (string) wp_get_current_user()->display_name;
    }

    /* ---- Actions ---------------------------------------------------------- */

    /** @return bool the request on the detail page was deleted */
    private function maybeAct(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['szw_action'])) {
            return false;
        }

        check_admin_referer(self::NONCE);

        $action = sanitize_key((string) wp_unslash($_POST['szw_action']));
        $id     = absint($_POST['item'] ?? 0);
        $own    = $id > 0 && get_post_type($id) === Leads::TYPE;

        switch ($action) {
            case 'delete':
                $ok = $own && (bool) wp_delete_post($id, true);
                Leads::forget();
                $this->notices[] = $ok ? ['ok' => true, 'text' => 'Request deleted.'] : ['ok' => false, 'text' => 'That request was not found; it may have been deleted already.'];
                return $ok;

            case 'status':
                $status = sanitize_key((string) ($_POST['status'] ?? ''));
                $ok     = $own && isset(Kit::STATUSES[$status]) && Leads::set_status($id, $status, self::me(), Kit::statusText($status));
                $this->notices[] = $ok ? ['ok' => true, 'text' => 'Status changed to ' . Kit::STATUSES[$status][0] . '.'] : ['ok' => false, 'text' => 'The status did not change.'];
                return false;

            case 'note':
                $note = trim(sanitize_textarea_field(wp_unslash((string) ($_POST['note'] ?? ''))));
                if ($own) {
                    update_post_meta($id, Leads::NOTE, $note);
                    Leads::log($id, $note === '' ? 'Note removed' : 'Note edited', 'info', self::me());
                }
                $this->notices[] = $own ? ['ok' => true, 'text' => 'Note saved.'] : ['ok' => false, 'text' => 'That request was not found.'];
                return false;

            case 'telegram':
                // all of it again after a full delivery; otherwise only where it is missing
                $ok = $own && Telegram::ready() && Telegram::send($id, (string) (((array) get_post_meta($id, Telegram::META, true))['state'] ?? '') === 'sent');
                $this->notices[] = match (true) {
                    !Telegram::ready() => ['ok' => false, 'text' => 'Telegram is not connected; connect it in Settings → Telegram.'],
                    $ok                => ['ok' => true, 'text' => 'Sent to Telegram again.'],
                    default            => ['ok' => false, 'text' => Telegram::explain((string) (get_post_meta($id, Telegram::META, true)['error'] ?? ''))],
                };
                Leads::forget();
                return false;

            case 'bulk_talking':
            case 'bulk_lost':
            case 'bulk_new':
            case 'bulk_delete':
                $ids = array_slice(array_filter(array_map('absint', (array) ($_POST['items'] ?? []))), 0, 200);
                $to  = ['bulk_talking' => 'talking', 'bulk_lost' => 'lost', 'bulk_new' => 'new'][$action] ?? '';
                $n   = 0;
                foreach ($ids as $one) {
                    if (get_post_type($one) !== Leads::TYPE) {
                        continue;
                    }
                    $n += (int) ($action === 'bulk_delete' ? (bool) wp_delete_post($one, true) : Leads::set_status($one, $to, self::me(), Kit::statusText($to) . ' (bulk)'));
                }
                Leads::forget();
                $this->notices[] = $n > 0
                    ? ['ok' => true, 'text' => Fmt::plural($n, 'request') . ' ' . ($action === 'bulk_delete' ? 'deleted.' : 'marked ' . Kit::STATUSES[$to][0] . '.')]
                    : ['ok' => false, 'text' => 'No request was selected.'];
                return false;
        }

        return false;
    }

    /** Delete, after a question; from the detail page it returns to the list */
    private function deleteButton(int $id, string $label, string $variant, bool $stay = false): string
    {
        return Ui::postButton(self::NONCE, ['szw_action' => 'delete', 'item' => $id], $label, $variant, 'trash',
            self::DELETE_NOTE . ' Delete it?', $stay ? '' : Ui::url(self::SLUG));
    }

    /* ---- List ------------------------------------------------------------- */

    private function renderList(): void
    {
        $view = isset($_GET['view']) ? sanitize_key((string) $_GET['view']) : 'all';
        $view = isset(Kit::VIEWS[$view]) ? $view : 'all';
        $term = isset($_GET['s']) ? sanitize_text_field((string) wp_unslash($_GET['s'])) : '';
        $page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);

        $filters = self::filters();
        $keep    = array_filter(['s' => $term, 'svc' => $filters['service'] ?? '', 'budget' => $filters['budget'] ?? '', 'days' => (string) ($filters['days'] ?? '')], static fn (string $v): bool => $v !== '');
        $counts  = Leads::counts();
        $result  = Leads::search($view, $term, $page, self::PER_PAGE, $filters);

        $csv = Ui::button('Export CSV', wp_nonce_url(admin_url('admin-post.php?action=' . self::CSV), self::CSV), 'ghost', 'download');
        Ui::head('Requests', Fmt::plural($counts['all'], 'request') . ' from the Project Form' . ($counts['spam'] > 0 ? ' — ' . Fmt::num($counts['spam']) . ' spam kept aside' : ''), $csv);
        Ui::notices($this->notices);

        if ($counts['new'] > 0 && $view !== 'new') {
            echo Ui::alert('info', 'Requests that have not had an answer yet. The sooner, the better.', Fmt::plural($counts['new'], 'new request') . ' waiting for you',
                Ui::button('Show', Ui::url(self::SLUG, ['view' => 'new']), 'sm'));
        }

        // toolbar: views + search
        echo '<div class="za-toolbar"><nav class="za-seg" aria-label="Views">';
        foreach (Kit::VIEWS as $key => $label) {
            if ($key === 'spam' && $counts['spam'] === 0 && $view !== 'spam') {
                continue;
            }
            printf(
                '<a href="%s" class="za-seg__item%s%s"%s>%s<span class="za-seg__count">%s</span></a>',
                esc_url(Ui::url(self::SLUG, ['view' => $key] + $keep)),
                $key === $view ? ' is-current' : '',
                $key === 'new' && $counts[$key] > 0 ? ' is-alert' : '',
                $key === $view ? ' aria-current="page"' : '',
                esc_html($label),
                esc_html(Fmt::num($counts[$key]))
            );
        }
        echo '</nav>';

        printf(
            '<form method="get" class="za-search" role="search"><input type="hidden" name="page" value="%s"><input type="hidden" name="view" value="%s">%s'
            . '%s<input type="search" name="s" value="%s" placeholder="Name, email, phone or message" aria-label="Search (key /)" data-hotkey="/"><kbd class="za-kbd">/</kbd>%s</form>',
            esc_attr(self::SLUG),
            esc_attr($view),
            self::hidden(array_diff_key($keep, ['s' => 1])),
            Ui::icon('search', 16),
            esc_attr($term),
            $term !== '' ? '<a class="za-search__clear" href="' . esc_url(Ui::url(self::SLUG, ['view' => $view] + array_diff_key($keep, ['s' => 1]))) . '" aria-label="Clear">' . Ui::icon('x', 14) . '</a>' : ''
        );
        echo '</div>';

        // filters apply as they change; their options are the values requests actually carry
        $services = array_keys(Leads::tally('services'));
        $budgets  = array_keys(Leads::tally('budget'));
        $selects  = [
            'svc'    => ['Need', ['' => 'All needs'] + array_combine($services, $services), $filters['service'] ?? ''],
            'budget' => ['Budget', ['' => 'All budgets'] + array_combine($budgets, $budgets), $filters['budget'] ?? ''],
            'days'   => ['Time', ['' => 'Any time', '1' => 'Last 24 hours', '7' => 'Last 7 days', '30' => 'Last 30 days'], (string) ($filters['days'] ?? '')],
        ];
        echo '<form method="get" class="za-filters" data-autosubmit><input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '"><input type="hidden" name="view" value="' . esc_attr($view) . '">'
            . ($term !== '' ? '<input type="hidden" name="s" value="' . esc_attr($term) . '">' : '') . Ui::icon('filter', 15);
        foreach ($selects as $name => [$label, $options, $current]) {
            if (count($options) < 2) {
                continue;
            }
            echo '<label class="za-filter' . ($current !== '' ? ' is-set' : '') . '"><span class="screen-reader-text">' . esc_html($label) . '</span><select name="' . esc_attr($name) . '">';
            foreach ($options as $value => $text) {
                printf('<option value="%s"%s>%s</option>', esc_attr((string) $value), selected($current, (string) $value, false), esc_html((string) $text));
            }
            echo '</select></label>';
        }
        echo '<span class="za-filters__count">' . esc_html(Fmt::plural($result['total'], $filters !== [] || $term !== '' ? 'result' : 'request')) . '</span>';
        if ($filters !== []) {
            echo '<a class="za-btn za-btn--link" href="' . esc_url(Ui::url(self::SLUG, ['view' => $view] + ($term !== '' ? ['s' => $term] : []))) . '">' . Ui::icon('x', 13) . '<span>Clear filters</span></a>';
        }
        echo '<noscript><button class="za-btn za-btn--sm">Apply</button></noscript></form>';

        // "a new request arrived": every 30 seconds (admin.js, data-live)
        if ($page === 1 && $view === 'all' && $filters === [] && $term === '') {
            printf('<div class="za-live" hidden data-live="%s" data-after="%d"></div>', esc_attr(wp_nonce_url(add_query_arg('action', self::LIVE, admin_url('admin-ajax.php')), self::LIVE)), Leads::latest_id());
        }

        echo '<section class="za-card za-card--flush">';

        if ($result['rows'] === []) {
            echo $term !== ''
                ? Ui::empty('search', 'Nothing found for "' . $term . '"', 'Try a name, an email, a phone number or words from the message.')
                : Ui::empty('inbox', 'No requests in this view', match ($view) {
                    'new'   => 'Every new request has had an answer.',
                    'spam'  => 'No message has been kept aside as spam.',
                    'all'   => 'When someone sends the Project Form, it appears here and on Telegram.',
                    default => '',
                });
            echo '</section>';
            return;
        }

        // bulk selection: a separate form, checkboxes joined by form=, since each row's delete has its own form
        echo '<form method="post" id="za-bulk" class="za-bulk" data-bulk>' . wp_nonce_field(self::NONCE, '_wpnonce', true, false)
            . '<span class="za-bulk__count"></span>'
            . ($view === 'spam'
                ? '<button type="submit" name="szw_action" value="bulk_new" class="za-btn za-btn--sm">' . Ui::icon('check', 14) . '<span>Not spam</span></button>'
                : '<button type="submit" name="szw_action" value="bulk_talking" class="za-btn za-btn--sm">' . Ui::icon('reply', 14) . '<span>Talking</span></button>'
                    . '<button type="submit" name="szw_action" value="bulk_lost" class="za-btn za-btn--sm">' . Ui::icon('x', 14) . '<span>Lost</span></button>')
            . '<button type="submit" name="szw_action" value="bulk_delete" class="za-btn za-btn--sm za-btn--danger" data-confirm-click="' . esc_attr('Delete the selected requests? ' . self::DELETE_NOTE) . '">' . Ui::icon('trash', 14) . '<span>Delete</span></button>'
            . '<button type="button" class="za-btn za-btn--sm za-btn--link" data-bulk-clear>Clear selection</button></form>';

        echo '<div class="za-table-wrap"><table class="za-table za-table--main"><thead><tr>'
            . '<th class="za-check-col"><input type="checkbox" data-bulk-all aria-label="Select all"></th><th>From</th><th>Needs</th><th>Budget and timeline</th><th>Status</th><th>Received</th><th><span class="screen-reader-text">Actions</span></th>'
            . '</tr></thead><tbody>';

        foreach ($result['rows'] as $r) {
            $link = Ui::url(self::SLUG, ['item' => (int) $r['id']]);
            [$state, $tone] = Kit::STATUSES[$r['status']];

            printf(
                '<tr data-href="%1$s"%13$s><td class="za-check-col"><input type="checkbox" name="items[]" value="%2$d" form="za-bulk" aria-label="Select"></td>'
                . '<td><a class="za-who" href="%1$s">%3$s<span><strong>%4$s</strong><small>%5$s</small></span></a></td>'
                . '<td>%6$s</td>'
                . '<td><span>%7$s</span><small class="za-cell-sub">%8$s</small></td><td>%9$s</td>'
                . '<td>%10$s<small class="za-cell-sub">%11$s</small></td><td class="za-row-actions">%12$s</td></tr>',
                esc_url($link),
                (int) $r['id'],
                Ui::avatar((string) $r['name'], $r['status'] === 'new' ? '' : 'alt'),
                esc_html((string) $r['name']),
                esc_html((string) $r['email']),
                self::needs((array) $r['services'], 2),
                esc_html($r['budget'] !== '' ? (string) $r['budget'] : '—'),
                esc_html((string) $r['timeline']),
                Ui::pill($state, $tone),
                esc_html(Fmt::ago((int) $r['ts'])),
                esc_html(Fmt::date((int) $r['ts'], true)),
                $this->deleteButton((int) $r['id'], 'Delete', 'icon', true),
                $r['status'] === 'new' ? ' class="is-unread"' : ''
            );
        }

        echo '</tbody></table></div>';

        $pages = (int) ceil($result['total'] / self::PER_PAGE);
        if ($pages > 1) {
            echo '<nav class="za-pager" aria-label="Pages"><span class="za-muted">'
                . esc_html('Showing ' . Fmt::num(($page - 1) * self::PER_PAGE + 1) . '–' . Fmt::num(min($page * self::PER_PAGE, $result['total'])) . ' of ' . Fmt::num($result['total']))
                . '</span><span class="za-pager__links">'
                . ((string) paginate_links([
                    'base'      => add_query_arg('paged', '%#%', Ui::url(self::SLUG, ['view' => $view] + $keep)),
                    'format'    => '',
                    'current'   => $page,
                    'total'     => $pages,
                    'prev_text' => '‹ Previous',
                    'next_text' => 'Next ›',
                ]))
                . '</span></nav>';
        }

        echo '</section>';
    }

    /** Needs as chips; beyond $max, "+2" */
    private static function needs(array $services, int $max = 99): string
    {
        if ($services === []) {
            return '<span class="za-muted">—</span>';
        }

        $html = '<span class="za-needs">';
        foreach (array_slice($services, 0, $max) as $s) {
            $html .= '<span class="za-need">' . esc_html((string) $s) . '</span>';
        }
        if (count($services) > $max) {
            $html .= '<span class="za-need za-need--more" title="' . esc_attr(implode(', ', array_slice($services, $max))) . '">+' . esc_html((string) (count($services) - $max)) . '</span>';
        }

        return $html . '</span>';
    }

    /* ---- One request ------------------------------------------------------ */

    private function renderDetail(int $id): void
    {
        $r     = Leads::find($id);
        $newer = Leads::neighbour($id, true);
        $older = Leads::neighbour($id, false);

        echo '<div class="za-crumbs"><a class="za-back" href="' . esc_url(Ui::url(self::SLUG)) . '">' . Ui::icon('arrow', 15) . 'All requests</a><span class="za-pn">'
            . ($newer !== null ? '<a href="' . esc_url(Ui::url(self::SLUG, ['item' => $newer])) . '" title="Newer (← key)" data-hotkey="ArrowLeft">' . Ui::icon('chev-l', 16) . 'Newer</a>' : '<span>' . Ui::icon('chev-l', 16) . 'Newer</span>')
            . ($older !== null ? '<a href="' . esc_url(Ui::url(self::SLUG, ['item' => $older])) . '" title="Older (→ key)" data-hotkey="ArrowRight">Older' . Ui::icon('chev-r', 16) . '</a>' : '<span>Older' . Ui::icon('chev-r', 16) . '</span>')
            . '</span></div>';

        if ($r === null) {
            Ui::notices($this->notices);
            echo '<section class="za-card">' . Ui::empty('search', 'Request not found', 'It may have been deleted.') . '</section>';
            return;
        }

        [$state, $tone] = Kit::STATUSES[$r['status']];
        $phone   = (string) $r['phone'];
        $wa      = $phone !== '' ? Leads::wa_digits($phone) : '';
        $subject = Settings::text('reply_subject') !== '' ? Settings::text('reply_subject') : 'Re: your project request';
        $body    = Kit::fill(Settings::text('reply_email'), $r) . "\n\n\n— You wrote:\n> " . str_replace("\n", "\n> ", (string) $r['message']);
        $summary = implode(' — ', array_filter([(string) $r['name'], (string) $r['email'], $phone, implode(', ', (array) $r['services']), (string) $r['budget'], (string) $r['timeline']]));
        $dialog  = 'za-note-' . $id;

        $actions = '<a class="za-btn za-btn--primary" href="' . esc_url('mailto:' . $r['email'] . '?subject=' . rawurlencode($subject) . '&body=' . rawurlencode($body)) . '">' . Ui::icon('mail', 16) . '<span>Reply by email</span></a>'
            . ($wa !== '' ? '<a class="za-btn za-btn--ghost" target="_blank" rel="noopener" href="' . esc_url('https://wa.me/' . $wa . '?text=' . rawurlencode(Kit::fill(Settings::text('reply_wa'), $r))) . '">' . Ui::icon('whatsapp', 16) . '<span>WhatsApp</span></a>' : '')
            . ($phone !== '' ? '<a class="za-btn za-btn--ghost" href="tel:' . esc_attr(preg_replace('/[^+0-9]/', '', $phone)) . '">' . Ui::icon('phone', 16) . '<span>Call</span></a>' : '')
            . '<button type="button" class="za-btn za-btn--ghost" data-copy-text="' . esc_attr($summary) . '">' . Ui::icon('copy', 16) . '<span>Copy summary</span></button>';

        echo '<div class="za-profile">' . Ui::avatar((string) $r['name'], $r['status'] === 'new' ? '' : 'alt') . '<div class="za-profile__text">'
            . '<h2 class="za-title">' . esc_html((string) $r['name']) . '</h2><div class="za-profile__meta">'
            . Ui::pill($state, $tone)
            . '<span class="za-muted">' . Ui::icon('clock', 13) . esc_html('Received ' . Fmt::ago((int) $r['ts']) . ' — ' . Fmt::date((int) $r['ts'], true)) . '</span>'
            . '</div></div><div class="za-head__actions">' . $actions . '</div></div>';

        Ui::notices($this->notices);

        if ($r['status'] === 'new') {
            echo Ui::alert('info', 'Once you have answered, mark it so it leaves "New" and your response time counts in the overview.', 'Waiting for your answer',
                Ui::postButton(self::NONCE, ['szw_action' => 'status', 'item' => $id, 'status' => 'talking'], 'I replied', 'sm', 'check'));
        }

        echo '<div class="za-grid za-grid--detail"><div class="za-stack">';

        // the message: what they wrote, large and readable
        Ui::cardOpen('Message', 'note', '<button type="button" class="za-btn za-btn--sm za-btn--ghost" data-copy-text="' . esc_attr((string) $r['message']) . '">' . Ui::icon('copy', 14) . '<span>Copy</span></button>');
        echo '<blockquote class="za-quote">' . nl2br(esc_html((string) $r['message'])) . '</blockquote>';
        $chips = '';
        foreach ([['spark', $r['services'] ? implode(', ', (array) $r['services']) : ''], ['clock', (string) $r['timeline']], ['wallet', (string) $r['budget']]] as [$icon, $text]) {
            if ($text !== '') {
                $chips .= '<span class="za-fact">' . Ui::icon($icon, 14) . '<span>' . esc_html($text) . '</span></span>';
            }
        }
        echo $chips !== '' ? '<div class="za-facts">' . $chips . '</div>' : '';
        Ui::cardClose();

        Ui::cardOpen('Details', 'user');
        $page = (string) $r['page'];
        echo Ui::kv([
            'Name'         => (string) $r['name'],
            'Email'        => ['html' => '<span data-copy="' . esc_attr((string) $r['email']) . '" title="Copy">' . esc_html((string) $r['email']) . '</span>'],
            'Phone'        => $phone !== '' ? ['html' => '<span data-copy="' . esc_attr($phone) . '" title="Copy">' . esc_html($phone) . '</span>'] : '',
            'Needs'        => ['html' => self::needs((array) $r['services'])],
            'Timeline'     => (string) $r['timeline'],
            'Budget'       => (string) $r['budget'],
            'Reference'    => $r['link'] !== '' ? ['html' => '<a href="' . esc_url((string) $r['link']) . '" target="_blank" rel="noopener noreferrer">' . esc_html(preg_replace('#^https?://(www\.)?#', '', (string) $r['link'])) . Ui::icon('external', 13) . '</a>'] : '',
            'Sent from'    => $page !== '' ? ['html' => '<a href="' . esc_url($page) . '" target="_blank" rel="noopener">' . esc_html((wp_parse_url($page, PHP_URL_PATH) ?: '/') . (wp_parse_url($page, PHP_URL_QUERY) ? '?' . wp_parse_url($page, PHP_URL_QUERY) : '')) . '</a>'] : '',
            'Received'     => Fmt::date((int) $r['ts'], true),
            'First answer' => $r['first'] > 0 ? Fmt::date((int) $r['first'], true) . ' — after ' . Fmt::duration((int) $r['first'] - (int) $r['ts']) : '',
        ]);
        Ui::cardClose();

        $this->renderLog($id);

        echo '</div><div class="za-stack">';

        Ui::cardOpen('Status', 'shield');
        echo '<form method="post" class="za-status-form">' . wp_nonce_field(self::NONCE, '_wpnonce', true, false)
            . '<input type="hidden" name="szw_action" value="status"><input type="hidden" name="item" value="' . $id . '">'
            . '<select class="za-select" name="status" aria-label="Status">';
        foreach (Kit::STATUSES as $key => [$text]) {
            printf('<option value="%s"%s>%s</option>', esc_attr($key), selected($r['status'], $key, false), esc_html($text));
        }
        echo '</select><button type="submit" class="za-btn za-btn--primary">' . Ui::icon('check', 16) . '<span>Save</span></button></form>'
            . '<p class="za-hint">New → Talking → Won or Lost. Every change is recorded in the events with your name.</p>';
        Ui::cardClose();

        $note = Leads::note($id);
        Ui::cardOpen('Your note', 'edit', Ui::dialogButton($note === '' ? 'Write' : 'Edit', $dialog, 'sm', 'edit'));
        echo $note !== '' ? '<p class="za-note">' . nl2br(esc_html($note)) . '</p>' : '<p class="za-hint">Only seen here: a quote you gave, the next call.</p>';
        Ui::cardClose();

        $this->renderTelegram($id);

        Ui::cardOpen('Delete request', 'trash', '', 'za-card--danger');
        echo '<p class="za-hint">' . esc_html(self::DELETE_NOTE) . '</p>' . $this->deleteButton($id, 'Delete this request', 'danger');
        Ui::cardClose();

        echo '</div></div>';

        Ui::dialogOpen($dialog, 'Note', 'On ' . $r['name'] . ' — only you see it.', self::NONCE, Ui::url(self::SLUG, ['item' => $id]), (string) $r['name']);
        echo '<input type="hidden" name="item" value="' . $id . '">'
            . '<textarea class="za-textarea" name="note" rows="5"  placeholder="e.g. quoted $2,500, call back Thursday">' . esc_textarea($note) . '</textarea>';
        Ui::dialogClose('Save', 'szw_action', 'note');
    }

    private function renderTelegram(int $id): void
    {
        $tg    = get_post_meta($id, Telegram::META, true);
        $tg    = is_array($tg) ? $tg : [];
        $state = (string) ($tg['state'] ?? '');
        $resend = Telegram::ready()
            ? Ui::postButton(self::NONCE, ['szw_action' => 'telegram', 'item' => $id], match ($state) { 'sent' => 'Send again', 'partial', 'failed' => 'Send the missing', default => 'Send' }, 'sm', 'telegram')
            : Ui::button('Connect', Ui::url(SettingsPage::SLUG, ['tab' => 'telegram']), 'sm');

        Ui::cardOpen('Telegram', 'telegram', $resend);
        $chats = (array) ($tg['chats'] ?? []);
        $retry = '<p class="za-hint">' . esc_html((int) ($tg['tries'] ?? 0) < Telegram::TRIES ? 'It will be tried again by itself, only where it is missing.' : 'No more automatic tries; press Send again.') . '</p>';

        if (count($chats) > 1 || ($chats && $state !== 'sent')) {
            // chat by chat
            echo '<ul class="za-deliveries">';
            foreach ($chats as $chat => $c) {
                $who = Telegram::chat_label((string) $chat, (string) ($c['name'] ?? ''));
                echo '<li>' . (!empty($c['ok'])
                    ? Ui::state('ok', $who . (!empty($c['at']) ? ' — ' . Fmt::ago((int) $c['at']) : ''))
                    : Ui::state('error', $who . ': not delivered') . '<p class="za-hint">' . esc_html(Telegram::explain((string) ($c['error'] ?? ''))) . '</p>') . '</li>';
            }
            echo '</ul>' . ($state !== 'sent' ? $retry : '');
        } else {
            echo match ($state) {
                'sent'    => Ui::state('ok', 'Announced on Telegram' . (!empty($tg['at']) ? ' — ' . Fmt::ago((int) $tg['at']) : '')),
                'failed'  => Ui::state('error', 'Not delivered') . '<p class="za-hint">' . esc_html(Telegram::explain((string) ($tg['error'] ?? ''))) . '</p>' . $retry,
                'pending' => Ui::state('warn', 'Waiting to be sent'),
                default   => Ui::state('muted', Leads::status($id) === 'spam' ? 'Spam is never sent to Telegram' : 'Not sent (Telegram was not connected then)'),
            };
        }
        Ui::cardClose();
    }

    /** The events, newest first */
    private function renderLog(int $id): void
    {
        $entries = array_reverse(Leads::events($id));

        Ui::cardOpen('Events', 'clock', '<span class="za-muted">' . esc_html(Fmt::plural(count($entries), 'event')) . '</span>');

        if ($entries === []) {
            echo '<p class="za-hint">No events recorded.</p>';
            Ui::cardClose();
            return;
        }

        echo '<ol class="za-timeline">';
        foreach ($entries as $e) {
            $tone = match ($e['level'] ?? '') {
                'error'   => 'error',
                'warning' => 'warn',
                default   => 'muted',
            };
            echo '<li class="za-timeline__item za-timeline__item--' . $tone . '"><i></i><div><strong>' . esc_html(self::say((string) $e['message'])) . '</strong>'
                . '<small>' . esc_html(Fmt::date(Fmt::fromSql((string) $e['at']), true) . (($e['by'] ?? '') !== '' ? ' — ' . $e['by'] : '')) . '</small></div></li>';
        }
        echo '</ol>';

        Ui::cardClose();
    }

    /** Events recorded by an earlier (Persian) version of the panel, in English */
    private static function say(string $message): string
    {
        static $fa = ['تازه' => 'new', 'در گفتگو' => 'talking', 'قرارداد' => 'won', 'منتفی' => 'lost', 'اسپم' => 'spam'];
        if (preg_match('/^وضعیت: (.+?)( \(گروهی\))?$/u', $message, $m) && isset($fa[$m[1]])) {
            return Kit::statusText($fa[$m[1]]) . (isset($m[2]) ? ' (bulk)' : '');
        }
        $map = ['یادداشت ویرایش شد' => 'Note edited', 'یادداشت پاک شد' => 'Note removed'];
        if (isset($map[$message])) {
            return $map[$message];
        }
        if (isset(Kit::STATUSES[$message])) {
            return Kit::statusText($message);
        }

        return $message;
    }

    /* ---- Helpers ---------------------------------------------------------- */

    /** The ajax answer behind "a new request arrived" */
    public static function live(): never
    {
        check_ajax_referer(self::LIVE);

        if (!current_user_can(Kit::CAPABILITY)) {
            wp_send_json_error(null, 403);
        }

        wp_send_json_success(['count' => Leads::count_after(absint($_GET['after'] ?? 0))]);
    }

    public static function csv(): void
    {
        check_admin_referer(self::CSV);
        if (!current_user_can(Kit::CAPABILITY)) {
            wp_die('You are not allowed to do this.');
        }
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=requests-' . gmdate('Y-m-d') . '.csv');
        echo Leads::csv_text(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a CSV download
        exit;
    }

    /** @return array{service?:string,budget?:string,days?:int} */
    private static function filters(): array
    {
        $out     = [];
        $service = isset($_GET['svc']) ? sanitize_text_field(wp_unslash((string) $_GET['svc'])) : '';
        $budget  = isset($_GET['budget']) ? sanitize_text_field(wp_unslash((string) $_GET['budget'])) : '';
        $days    = isset($_GET['days']) ? absint($_GET['days']) : 0;

        if ($service !== '') {
            $out['service'] = $service;
        }
        if ($budget !== '') {
            $out['budget'] = $budget;
        }
        if (in_array($days, [1, 7, 30], true)) {
            $out['days'] = $days;
        }

        return $out;
    }

    /** @param array<string,string> $fields */
    private static function hidden(array $fields): string
    {
        $html = '';
        foreach ($fields as $name => $value) {
            $html .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
        }

        return $html;
    }
}
