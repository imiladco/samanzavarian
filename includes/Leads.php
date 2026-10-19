<?php
namespace Samanzavarian_Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Project requests: what the Project Form sends, kept in WordPress.
 *
 * - Stored as a private "Requests" post type (no pages, nothing public),
 *   one request per post, every field in one meta array. Status: new,
 *   replied or archived; the menu shows how many are new.
 * - Received by a REST route (POST szw/v1/lead), no nonce: pages are cached,
 *   and a public, anonymous form has no session to forge. Spam is kept out
 *   without a captcha: a hidden field only bots fill, a minimum time to fill
 *   the form, a few requests per hour per address (stored hashed), the same
 *   request twice in ten minutes counted once, and messages full of links
 *   kept aside as spam (stored, never announced).
 * - Each new request is announced on Telegram (Telegram.php), after the
 *   visitor already has their answer.
 * - The admin: the list (filter by status, export CSV), and each request on
 *   one screen with its details and ways to reply (email, WhatsApp, call)
 *   and to mark it replied or archive it. Archived requests can be deleted
 *   by themselves after a number of days (Requests → Settings).
 */
final class Leads {

	public const TYPE     = 'sz_lead';
	public const META     = '_sz_lead';
	public const STATUS   = '_sz_status';
	public const STATUSES = [ 'new', 'replied', 'archived', 'spam' ];
	public const ROUTE    = 'szw/v1';

	/** Requests per hour from one address. */
	public const RATE = 5;

	/** A form filled faster than this (ms) was not filled by a person. */
	public const MIN_TIME = 2500;

	public const MAX_MESSAGE = 2000;

	public static function boot(): void {
		add_action( 'init', [ self::class, 'register' ] );
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
		add_action( 'admin_menu', [ self::class, 'bubble' ], 99 );
		add_filter( 'manage_' . self::TYPE . '_posts_columns', [ self::class, 'columns' ] );
		add_action( 'manage_' . self::TYPE . '_posts_custom_column', [ self::class, 'column' ], 10, 2 );
		add_filter( 'views_edit-' . self::TYPE, [ self::class, 'views' ] );
		add_action( 'pre_get_posts', [ self::class, 'filter_list' ] );
		add_filter( 'post_row_actions', [ self::class, 'row_actions' ], 10, 2 );
		add_action( 'add_meta_boxes_' . self::TYPE, [ self::class, 'meta_boxes' ] );
		add_action( 'admin_post_sz_lead_status', [ self::class, 'set_status_action' ] );
		add_action( 'admin_post_sz_leads_csv', [ self::class, 'csv' ] );
		add_action( 'manage_posts_extra_tablenav', [ self::class, 'export_button' ] );
		add_action( 'admin_head', [ self::class, 'admin_css' ] );
		add_action( 'szw_leads_cleanup', [ self::class, 'cleanup' ] );
		add_filter( 'post_updated_messages', [ self::class, 'no_messages' ] );
		if ( ! wp_next_scheduled( 'szw_leads_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'szw_leads_cleanup' );
		}
	}

	public static function register(): void {
		register_post_type(
			self::TYPE,
			[
				'labels'              => [
					'name'          => __( 'Requests', 'samanzavarian-widgets' ),
					'singular_name' => __( 'Request', 'samanzavarian-widgets' ),
					'menu_name'     => __( 'Requests', 'samanzavarian-widgets' ),
					'all_items'     => __( 'All requests', 'samanzavarian-widgets' ),
					'edit_item'     => __( 'Request', 'samanzavarian-widgets' ),
					'search_items'  => __( 'Search requests', 'samanzavarian-widgets' ),
					'not_found'     => __( 'No requests yet. They arrive here from the Project Form.', 'samanzavarian-widgets' ),
				],
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_position'       => 26,
				'menu_icon'           => 'dashicons-email-alt',
				'supports'            => false,
				'capability_type'     => 'post',
				'capabilities'        => [ 'create_posts' => 'do_not_allow' ],
				'map_meta_cap'        => true,
				'rewrite'             => false,
				'query_var'           => false,
			]
		);
	}

	/* =================================================================
	 * Receiving
	 * ================================================================= */

	public static function routes(): void {
		register_rest_route(
			self::ROUTE,
			'/lead',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'receive' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Clean and check what a form sent.
	 *
	 * @param array<string,mixed> $in
	 * @return array{0:array<string,mixed>,1:array<string,string>} the request, and errors by field
	 */
	public static function clean( array $in ): array {
		$str = static fn( $v, int $max ) => mb_substr( trim( sanitize_text_field( is_scalar( $v ) ? (string) $v : '' ) ), 0, $max );

		$d = [
			'name'     => $str( $in['name'] ?? '', 80 ),
			'email'    => sanitize_email( (string) ( $in['email'] ?? '' ) ),
			'phone'    => $str( $in['phone'] ?? '', 32 ),
			'services' => array_values( array_filter( array_map( static fn( $s ) => $str( $s, 60 ), array_slice( (array) ( $in['services'] ?? [] ), 0, 8 ) ) ) ),
			'timeline' => $str( $in['timeline'] ?? '', 60 ),
			'budget'   => $str( $in['budget'] ?? '', 60 ),
			'message'  => mb_substr( trim( sanitize_textarea_field( (string) ( $in['message'] ?? '' ) ) ), 0, self::MAX_MESSAGE ),
			'link'     => '',
			'page'     => esc_url_raw( (string) ( $in['page'] ?? '' ) ),
		];

		$err  = [];
		$link = trim( (string) ( $in['link'] ?? '' ) );
		if ( '' !== $link ) {
			$url = esc_url_raw( $link, [ 'http', 'https' ] );
			if ( '' === $url || ! preg_match( '#^https?://[^\s/]+\.[^\s]+#i', $url ) ) {
				$err['link'] = 'link';
			} else {
				$d['link'] = $url;
			}
		}
		if ( mb_strlen( $d['name'] ) < 2 ) {
			$err['name'] = 'name';
		}
		if ( ! is_email( $d['email'] ) ) {
			$err['email'] = 'email';
		}
		if ( '' !== $d['phone'] ) {
			$digits = preg_replace( '/\D/', '', $d['phone'] );
			if ( ! preg_match( '/^[+0-9 ()\-.]+$/', $d['phone'] ) || strlen( $digits ) < 7 || strlen( $digits ) > 15 ) {
				$err['phone'] = 'phone';
			}
		}
		if ( mb_strlen( $d['message'] ) < 10 ) {
			$err['message'] = 'message';
		}

		return [ $d, $err ];
	}

	/** True for a message that is mostly links: kept, but as spam. */
	public static function looks_spammy( string $message ): bool {
		return preg_match_all( '#https?://|www\.#i', $message ) > 3;
	}

	public static function ip_key(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return 'szw_lead_rate_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 24 );
	}

	/** @return \WP_REST_Response */
	public static function receive( \WP_REST_Request $req ) {
		$in = (array) $req->get_params();

		// a bot: it filled the field people never see, or took no time at all. It hears "sent".
		if ( '' !== trim( (string) ( $in['website'] ?? '' ) ) || (int) ( $in['t'] ?? 0 ) < self::MIN_TIME ) {
			return new \WP_REST_Response( [ 'ok' => true ], 200 );
		}

		[ $d, $err ] = self::clean( $in );
		if ( $err ) {
			return new \WP_REST_Response( [ 'ok' => false, 'errors' => array_keys( $err ) ], 400 );
		}

		$key  = self::ip_key();
		$hits = (int) get_transient( $key );
		if ( $hits >= self::RATE ) {
			return new \WP_REST_Response( [ 'ok' => false, 'errors' => [ 'rate' ] ], 429 );
		}
		set_transient( $key, $hits + 1, HOUR_IN_SECONDS );

		// the same request sent twice (a double tap, a retry): counted once
		$dupe = 'szw_lead_dupe_' . md5( strtolower( $d['email'] ) . '|' . $d['message'] );
		if ( get_transient( $dupe ) ) {
			return new \WP_REST_Response( [ 'ok' => true ], 200 );
		}
		set_transient( $dupe, 1, 10 * MINUTE_IN_SECONDS );

		$id = self::store( $d, self::looks_spammy( $d['message'] ) ? 'spam' : 'new' );
		if ( ! $id ) {
			return new \WP_REST_Response( [ 'ok' => false, 'errors' => [ 'save' ] ], 500 );
		}

		if ( 'new' === self::status( $id ) ) {
			Telegram::announce_later( $id );
		}

		return new \WP_REST_Response( [ 'ok' => true ], 200 );
	}

	/** @param array<string,mixed> $d */
	public static function store( array $d, string $status = 'new' ): int {
		$title = $d['name'] . ( $d['services'] ? ' — ' . implode( ', ', $d['services'] ) : '' );
		$id    = wp_insert_post(
			[
				'post_type'    => self::TYPE,
				'post_status'  => 'private',
				'post_title'   => $title,
				'post_content' => '',
				'meta_input'   => [ self::META => $d, self::STATUS => in_array( $status, self::STATUSES, true ) ? $status : 'new' ],
			],
			true
		);

		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/** @return array<string,mixed> */
	public static function get( int $id ): array {
		$d = get_post_meta( $id, self::META, true );
		return is_array( $d ) ? $d + [ 'name' => '', 'email' => '', 'phone' => '', 'services' => [], 'timeline' => '', 'budget' => '', 'message' => '', 'link' => '', 'page' => '' ] : [];
	}

	public static function status( int $id ): string {
		$s = (string) get_post_meta( $id, self::STATUS, true );
		return in_array( $s, self::STATUSES, true ) ? $s : 'new';
	}

	public static function count( string $status = 'new' ): int {
		$q = new \WP_Query(
			[
				'post_type'      => self::TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
				'meta_key'       => self::STATUS, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $status, // phpcs:ignore WordPress.DB.SlowDBQuery
			]
		);
		return (int) $q->found_posts;
	}

	/** wa.me wants digits with the country code; a local 09… number is read as Iranian. */
	public static function wa_digits( string $phone ): string {
		$p = preg_replace( '/\D/', '', $phone );
		if ( str_starts_with( $phone, '00' ) ) {
			$p = substr( $p, 2 );
		} elseif ( preg_match( '/^0\d{10}$/', $p ) ) {
			$p = '98' . substr( $p, 1 );
		}
		return (string) $p;
	}

	/* =================================================================
	 * The admin
	 * ================================================================= */

	/** How many are new, on the menu item. */
	public static function bubble(): void {
		global $menu;
		$n = self::count( 'new' );
		if ( ! $n || ! is_array( $menu ) ) {
			return;
		}
		foreach ( $menu as $k => $item ) {
			if ( ( $item[2] ?? '' ) === 'edit.php?post_type=' . self::TYPE ) {
				$menu[ $k ][0] .= ' <span class="awaiting-mod count-' . $n . '"><span class="pending-count">' . $n . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			}
		}
	}

	/** @param array<string,string> $cols */
	public static function columns( array $cols ): array {
		return [
			'cb'       => $cols['cb'] ?? '',
			'sz_who'   => __( 'From', 'samanzavarian-widgets' ),
			'sz_needs' => __( 'Needs', 'samanzavarian-widgets' ),
			'sz_when'  => __( 'Timeline · budget', 'samanzavarian-widgets' ),
			'sz_state' => __( 'Status', 'samanzavarian-widgets' ),
			'date'     => __( 'Received', 'samanzavarian-widgets' ),
		];
	}

	public static function column( string $col, int $id ): void {
		$d = self::get( $id );
		switch ( $col ) {
			case 'sz_who':
				echo '<a class="row-title" href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( $d['name'] ) . '</a><br><span class="sz-lead-sub">' . esc_html( $d['email'] ) . ( $d['phone'] ? ' · ' . esc_html( $d['phone'] ) : '' ) . '</span>';
				break;
			case 'sz_needs':
				echo esc_html( implode( ', ', (array) $d['services'] ) ?: '—' );
				echo '<br><span class="sz-lead-sub">' . esc_html( wp_trim_words( $d['message'], 14 ) ) . '</span>';
				break;
			case 'sz_when':
				echo esc_html( implode( ' · ', array_filter( [ $d['timeline'], $d['budget'] ] ) ) ?: '—' );
				break;
			case 'sz_state':
				echo self::pill( self::status( $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from a whitelist
				break;
		}
	}

	private static function status_label( string $s ): string {
		$l = [
			'new'      => __( 'New', 'samanzavarian-widgets' ),
			'replied'  => __( 'Replied', 'samanzavarian-widgets' ),
			'archived' => __( 'Archived', 'samanzavarian-widgets' ),
			'spam'     => __( 'Spam', 'samanzavarian-widgets' ),
		];
		return $l[ $s ] ?? $s;
	}

	private static function pill( string $s ): string {
		return '<span class="sz-lead-pill is-' . esc_attr( $s ) . '">' . esc_html( self::status_label( $s ) ) . '</span>';
	}

	/** The list's views: New, Replied, Archived, Spam, with counts. @param array<string,string> $views */
	public static function views( array $views ): array {
		$base = admin_url( 'edit.php?post_type=' . self::TYPE );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only filter
		$cur = isset( $_GET['sz_status'] ) ? sanitize_key( wp_unslash( $_GET['sz_status'] ) ) : '';
		$out = [ 'all' => '<a href="' . esc_url( $base ) . '"' . ( '' === $cur ? ' class="current"' : '' ) . '>' . esc_html__( 'All', 'samanzavarian-widgets' ) . '</a>' ];
		foreach ( self::STATUSES as $s ) {
			$n = self::count( $s );
			if ( ! $n && 'spam' === $s ) {
				continue;
			}
			$out[ $s ] = '<a href="' . esc_url( add_query_arg( 'sz_status', $s, $base ) ) . '"' . ( $cur === $s ? ' class="current"' : '' ) . '>' . esc_html( self::status_label( $s ) ) . ' <span class="count">(' . $n . ')</span></a>';
		}
		return $out;
	}

	public static function filter_list( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() || self::TYPE !== $q->get( 'post_type' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only filter
		$s = isset( $_GET['sz_status'] ) ? sanitize_key( wp_unslash( $_GET['sz_status'] ) ) : '';
		if ( in_array( $s, self::STATUSES, true ) ) {
			$q->set( 'meta_key', self::STATUS );
			$q->set( 'meta_value', $s );
		} else {
			// "All" leaves spam out
			$q->set( 'meta_query', [ [ 'key' => self::STATUS, 'value' => 'spam', 'compare' => '!=' ] ] );
		}
		$q->set( 'post_status', 'any' );
	}

	private static function status_url( int $id, string $to ): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=sz_lead_status&id=' . $id . '&to=' . $to ), 'sz_lead_status_' . $id );
	}

	/** @param array<string,string> $actions */
	public static function row_actions( array $actions, \WP_Post $post ): array {
		if ( self::TYPE !== $post->post_type ) {
			return $actions;
		}
		$s   = self::status( $post->ID );
		$out = [ 'view' => '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'Open', 'samanzavarian-widgets' ) . '</a>' ];
		foreach ( [ 'replied' => __( 'Mark replied', 'samanzavarian-widgets' ), 'archived' => __( 'Archive', 'samanzavarian-widgets' ), 'new' => __( 'Mark new', 'samanzavarian-widgets' ) ] as $to => $label ) {
			if ( $to !== $s && ! ( 'new' === $to && 'new' === $s ) ) {
				$out[ $to ] = '<a href="' . esc_url( self::status_url( $post->ID, $to ) ) . '">' . esc_html( $label ) . '</a>';
			}
		}
		if ( isset( $actions['trash'] ) ) {
			$out['trash'] = $actions['trash'];
		}
		return $out;
	}

	public static function set_status_action(): void {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$to = isset( $_GET['to'] ) ? sanitize_key( wp_unslash( $_GET['to'] ) ) : '';
		check_admin_referer( 'sz_lead_status_' . $id );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) || self::TYPE !== get_post_type( $id ) || ! in_array( $to, self::STATUSES, true ) ) {
			wp_die( esc_html__( 'That request cannot be changed.', 'samanzavarian-widgets' ) );
		}
		update_post_meta( $id, self::STATUS, $to );
		if ( 'archived' === $to ) {
			update_post_meta( $id, '_sz_archived', time() );
		}
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'edit.php?post_type=' . self::TYPE ) );
		exit;
	}

	/** One request, on one screen: no title box, no editor, no Publish box. */
	public static function meta_boxes(): void {
		remove_meta_box( 'submitdiv', self::TYPE, 'side' );
		remove_meta_box( 'slugdiv', self::TYPE, 'normal' );
		add_meta_box( 'sz_lead', __( 'Project request', 'samanzavarian-widgets' ), [ self::class, 'detail' ], self::TYPE, 'normal', 'high' );
		add_meta_box( 'sz_lead_reply', __( 'Reply', 'samanzavarian-widgets' ), [ self::class, 'reply_box' ], self::TYPE, 'side', 'high' );
	}

	public static function detail( \WP_Post $post ): void {
		$d    = self::get( $post->ID );
		$rows = [
			__( 'Name', 'samanzavarian-widgets' )      => esc_html( $d['name'] ),
			__( 'Email', 'samanzavarian-widgets' )     => '<a href="mailto:' . esc_attr( $d['email'] ) . '">' . esc_html( $d['email'] ) . '</a>',
			__( 'Phone', 'samanzavarian-widgets' )     => $d['phone'] ? '<a href="tel:' . esc_attr( preg_replace( '/[^+0-9]/', '', $d['phone'] ) ) . '">' . esc_html( $d['phone'] ) . '</a>' : '—',
			__( 'Needs', 'samanzavarian-widgets' )     => esc_html( implode( ', ', (array) $d['services'] ) ?: '—' ),
			__( 'Timeline', 'samanzavarian-widgets' )  => esc_html( $d['timeline'] ?: '—' ),
			__( 'Budget', 'samanzavarian-widgets' )    => esc_html( $d['budget'] ?: '—' ),
			__( 'Reference', 'samanzavarian-widgets' ) => $d['link'] ? '<a href="' . esc_url( $d['link'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $d['link'] ) . '</a>' : '—',
			__( 'Sent from', 'samanzavarian-widgets' ) => $d['page'] ? '<a href="' . esc_url( $d['page'] ) . '" target="_blank" rel="noopener">' . esc_html( ( wp_parse_url( $d['page'], PHP_URL_PATH ) ?: '/' ) . ( wp_parse_url( $d['page'], PHP_URL_QUERY ) ? '?' . wp_parse_url( $d['page'], PHP_URL_QUERY ) : '' ) ) . '</a>' : '—',
			__( 'Received', 'samanzavarian-widgets' )  => esc_html( get_the_date( 'j F Y, H:i', $post ) ),
			__( 'Status', 'samanzavarian-widgets' )    => self::pill( self::status( $post->ID ) ),
		];
		echo '<div class="sz-lead"><div class="sz-lead-msg">' . nl2br( esc_html( $d['message'] ) ) . '</div><table class="sz-lead-table">';
		foreach ( $rows as $k => $v ) {
			echo '<tr><th>' . esc_html( $k ) . '</th><td>' . $v . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
		}
		echo '</table>';
		$tg = (array) get_post_meta( $post->ID, Telegram::META, true );
		if ( ! empty( $tg['state'] ) ) {
			$note = 'sent' === $tg['state'] ? __( 'Announced on Telegram.', 'samanzavarian-widgets' ) : ( 'failed' === $tg['state'] ? sprintf( /* translators: %s: error */ __( 'Telegram did not take it: %s', 'samanzavarian-widgets' ), $tg['error'] ?? '' ) : __( 'Waiting to be announced on Telegram.', 'samanzavarian-widgets' ) );
			echo '<p class="sz-lead-sub">' . esc_html( $note ) . '</p>';
		}
		echo '</div>';
	}

	/** Ready-made replies, and the status. */
	public static function reply_box( \WP_Post $post ): void {
		$d     = self::get( $post->ID );
		$first = strtok( $d['name'], ' ' ) ?: $d['name'];
		/* translators: %s: first name */
		$hello   = sprintf( __( 'Hi %s, thanks for your message about your project.', 'samanzavarian-widgets' ), $first );
		$subject = __( 'Re: your project request', 'samanzavarian-widgets' );
		$quote   = "\n\n\n— " . __( 'You wrote:', 'samanzavarian-widgets' ) . "\n> " . str_replace( "\n", "\n> ", $d['message'] );
		echo '<p><a class="button button-primary sz-lead-btn" href="' . esc_url( 'mailto:' . $d['email'] . '?subject=' . rawurlencode( $subject ) . '&body=' . rawurlencode( $hello . $quote ) ) . '">' . esc_html__( 'Reply by email', 'samanzavarian-widgets' ) . '</a></p>';
		if ( $d['phone'] ) {
			echo '<p><a class="button sz-lead-btn" target="_blank" rel="noopener" href="' . esc_url( 'https://wa.me/' . self::wa_digits( $d['phone'] ) . '?text=' . rawurlencode( $hello ) ) . '">' . esc_html__( 'Reply on WhatsApp', 'samanzavarian-widgets' ) . '</a></p>';
			echo '<p><a class="button sz-lead-btn" href="' . esc_url( 'tel:' . preg_replace( '/[^+0-9]/', '', $d['phone'] ) ) . '">' . esc_html__( 'Call', 'samanzavarian-widgets' ) . '</a></p>';
		}
		$s = self::status( $post->ID );
		echo '<hr><p class="sz-lead-state">' . self::pill( $s ) . '</p><p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelist
		foreach ( [ 'replied' => __( 'Mark replied', 'samanzavarian-widgets' ), 'archived' => __( 'Archive', 'samanzavarian-widgets' ), 'new' => __( 'Mark new', 'samanzavarian-widgets' ) ] as $to => $label ) {
			if ( $to !== $s ) {
				echo '<a class="button button-small" style="margin:0 4px 4px 0" href="' . esc_url( self::status_url( $post->ID, $to ) ) . '">' . esc_html( $label ) . '</a>';
			}
		}
		echo '</p>';
	}

	/** @param array<string,array<int,string>> $m */
	public static function no_messages( array $m ): array {
		$m[ self::TYPE ] = array_fill( 0, 11, '' );
		return $m;
	}

	public static function export_button( string $which ): void {
		if ( 'top' !== $which || self::TYPE !== get_current_screen()->post_type ) {
			return;
		}
		echo '<div class="alignleft actions"><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sz_leads_csv' ), 'sz_leads_csv' ) ) . '">' . esc_html__( 'Export CSV', 'samanzavarian-widgets' ) . '</a></div>';
	}

	/** Every request but spam, as a spreadsheet. */
	public static function csv(): void {
		check_admin_referer( 'sz_leads_csv' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'samanzavarian-widgets' ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=requests-' . gmdate( 'Y-m-d' ) . '.csv' );
		echo self::csv_text(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a CSV download
		exit;
	}

	public static function csv_text(): string {
		$ids = get_posts( [ 'post_type' => self::TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC' ] );
		$fh  = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $fh, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- BOM: Excel reads UTF-8
		fputcsv( $fh, [ 'Received', 'Status', 'Name', 'Email', 'Phone', 'Needs', 'Timeline', 'Budget', 'Message', 'Reference', 'Page' ] );
		foreach ( $ids as $id ) {
			$s = self::status( $id );
			if ( 'spam' === $s ) {
				continue;
			}
			$d = self::get( $id );
			// a cell starting with = + - @ is a formula to a spreadsheet: neutralised
			$cell = static fn( $v ) => preg_match( '/^[=+\-@\t\r]/', (string) $v ) ? "'" . $v : (string) $v;
			fputcsv( $fh, array_map( $cell, [ get_the_date( 'Y-m-d H:i', $id ), $s, $d['name'], $d['email'], $d['phone'], implode( ', ', (array) $d['services'] ), $d['timeline'], $d['budget'], $d['message'], $d['link'], $d['page'] ] ) );
		}
		rewind( $fh );
		$out = (string) stream_get_contents( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $out;
	}

	/** Archived requests older than the set number of days, and spam after 30 days, go. */
	public static function cleanup(): int {
		$days = (int) Telegram::option( 'retention', 0 );
		$gone = 0;
		foreach ( [ 'archived' => $days, 'spam' => 30 ] as $status => $d ) {
			if ( $d <= 0 ) {
				continue;
			}
			$ids = get_posts(
				[
					'post_type'   => self::TYPE,
					'post_status' => 'any',
					'numberposts' => 200,
					'fields'      => 'ids',
					'meta_key'    => self::STATUS, // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'  => $status, // phpcs:ignore WordPress.DB.SlowDBQuery
					'date_query'  => [ [ 'before' => gmdate( 'Y-m-d H:i:s', time() - $d * DAY_IN_SECONDS ), 'column' => 'post_date_gmt' ] ],
				]
			);
			foreach ( $ids as $id ) {
				$since = (int) get_post_meta( $id, '_sz_archived', true );
				if ( 'archived' === $status && $since && $since > time() - $d * DAY_IN_SECONDS ) {
					continue;
				}
				wp_delete_post( $id, true );
				++$gone;
			}
		}
		return $gone;
	}

	public static function admin_css(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || self::TYPE !== $screen->post_type ) {
			return;
		}
		echo '<style>
.sz-lead-sub{color:#646970;font-size:12px}
.sz-lead-pill{display:inline-block;padding:2px 9px;border-radius:99px;font-size:12px;font-weight:600;line-height:1.6;background:#F0F0F1;color:#50575E}
.sz-lead-pill.is-new{background:#FFE6DA;color:#B4400F}.sz-lead-pill.is-replied{background:#E9F7EF;color:#17633C}.sz-lead-pill.is-spam{background:#FDEEE8;color:#9B2A07}
.sz-lead-msg{margin:4px 0 16px;padding:14px 16px;border-radius:10px;background:#F6F7F7;font-size:14px;line-height:1.6;white-space:normal}
.sz-lead-table{border-collapse:collapse;width:100%}.sz-lead-table th{width:120px;padding:7px 10px 7px 0;text-align:left;color:#646970;font-weight:500;vertical-align:top}.sz-lead-table td{padding:7px 0}
.sz-lead-btn{width:100%;text-align:center}
.post-type-' . esc_attr( self::TYPE ) . ' #titlediv,.post-type-' . esc_attr( self::TYPE ) . ' .page-title-action{display:none}
.column-sz_state{width:110px}.column-sz_when{width:190px}
</style>';
	}
}
