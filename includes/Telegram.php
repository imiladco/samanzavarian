<?php
namespace Samanzavarian_Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Telegram: every new project request, on your phone.
 *
 * A bot of your own (made with @BotFather) sends each request to your chat:
 * who, what they need, timeline and budget, the message, and buttons to
 * open it in WordPress or answer on WhatsApp. Requests → Settings holds the
 * bot's token and your chat; "Find my chat" reads it from the bot after you
 * have sent it a message, and "Send a test" proves the connection.
 *
 * Sending happens after the visitor has their answer (the response is
 * flushed first where the server allows), so a slow Telegram never slows
 * the form. A request is stored before anything is sent; if Telegram does
 * not take it, it is tried again a few times, minutes apart, and the
 * request shows what happened.
 */
final class Telegram {

	public const OPTION  = 'szw_telegram';
	public const META    = '_sz_tg';
	public const RETRY   = 'szw_lead_telegram';
	public const TRIES   = 4;
	private const PAGE   = 'sz-requests-settings';

	/** @var int[] requests to announce once the response is out */
	private static array $queue = [];

	public static function boot(): void {
		add_action( self::RETRY, [ self::class, 'send' ] );
		if ( Leads::kit() ) {
			return; // settings live in the Requests panel (Admin\SettingsPage)
		}
		add_action( 'admin_menu', [ self::class, 'menu' ] );
		add_action( 'admin_init', [ self::class, 'settings' ] );
		add_action( 'admin_post_sz_tg_detect', [ self::class, 'detect_action' ] );
		add_action( 'admin_post_sz_tg_test', [ self::class, 'test_action' ] );
	}

	/** Panel setting names for the keys used here. */
	private const KIT_KEYS = [ 'token' => 'tg_token', 'chat' => 'tg_chat', 'retention' => 'retention', 'spam_days' => 'spam_days' ];

	/** @return mixed */
	public static function option( string $key, $default = '' ) {
		if ( Leads::kit() && isset( self::KIT_KEYS[ $key ] ) ) {
			$v = Admin\Settings\Settings::get( self::KIT_KEYS[ $key ] );
			return '' !== $v && null !== $v ? $v : $default;
		}
		$o = get_option( self::OPTION, [] );
		return is_array( $o ) && isset( $o[ $key ] ) && '' !== $o[ $key ] ? $o[ $key ] : $default;
	}

	public static function ready(): bool {
		if ( Leads::kit() && ! Admin\Settings\Settings::bool( 'tg_enabled' ) ) {
			return false;
		}
		return '' !== (string) self::option( 'token' ) && '' !== (string) self::option( 'chat' );
	}

	/* =================================================================
	 * Sending
	 * ================================================================= */

	/** Announce a request once the visitor has their answer. */
	public static function announce_later( int $id ): void {
		if ( ! self::ready() ) {
			return;
		}
		update_post_meta( $id, self::META, [ 'state' => 'pending', 'tries' => 0 ] );
		if ( ! self::$queue ) {
			add_action( 'shutdown', [ self::class, 'flush_queue' ] );
		}
		self::$queue[] = $id;
	}

	public static function flush_queue(): void {
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
		foreach ( self::$queue as $id ) {
			self::send( $id );
		}
		self::$queue = [];
	}

	/** Send one request; on failure, try again later (a few times). */
	public static function send( int $id ): bool {
		$d = Leads::get( $id );
		if ( ! $d || ! self::ready() ) {
			return false;
		}
		$tg   = (array) get_post_meta( $id, self::META, true );
		$try  = (int) ( $tg['tries'] ?? 0 ) + 1;
		$args = [
			'chat_id'                  => self::option( 'chat' ),
			'text'                     => self::message( $id, $d ),
			'parse_mode'               => 'HTML',
			'disable_web_page_preview' => true,
			'reply_markup'             => wp_json_encode( [ 'inline_keyboard' => [ self::buttons( $id, $d ) ] ] ),
		];

		$r = self::api( 'sendMessage', $args );
		// a button Telegram will not take (a local admin address): the message alone still matters
		if ( ! $r['ok'] && false !== stripos( $r['error'], 'button' ) ) {
			unset( $args['reply_markup'] );
			$r = self::api( 'sendMessage', $args );
		}

		if ( $r['ok'] ) {
			update_post_meta( $id, self::META, [ 'state' => 'sent', 'tries' => $try, 'at' => time() ] );
			update_option( 'szw_tg_last', [ 'ok' => true, 'at' => time() ], false );
			Leads::log( $id, __( 'Announced on Telegram', 'samanzavarian-widgets' ) );
			return true;
		}

		update_post_meta( $id, self::META, [ 'state' => 'failed', 'tries' => $try, 'error' => $r['error'], 'at' => time() ] );
		update_option( 'szw_tg_last', [ 'ok' => false, 'at' => time(), 'error' => $r['error'] ], false );
		Leads::log( $id, sprintf( /* translators: %s: error */ __( 'Telegram did not take it: %s', 'samanzavarian-widgets' ), $r['error'] ), 'error' );
		if ( $try < self::TRIES && ! wp_next_scheduled( self::RETRY, [ $id ] ) ) {
			wp_schedule_single_event( time() + 120 * ( 2 ** ( $try - 1 ) ), self::RETRY, [ $id ] ); // 2, 4, 8 minutes
		}
		return false;
	}

	/** @param array<string,mixed> $d */
	public static function message( int $id, array $d ): string {
		$e     = static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$lines = [ '🆕 <b>' . $e( __( 'New project request', 'samanzavarian-widgets' ) ) . '</b>', '' ];
		$lines[] = '👤 <b>' . $e( $d['name'] ) . '</b>';
		$lines[] = '✉️ ' . $e( $d['email'] );
		if ( $d['phone'] ) {
			$lines[] = '📞 ' . $e( $d['phone'] );
		}
		if ( $d['services'] ) {
			$lines[] = '🎯 ' . $e( implode( ' · ', (array) $d['services'] ) );
		}
		$plan = array_filter( [ $d['timeline'] ? '⏱ ' . $e( $d['timeline'] ) : '', $d['budget'] ? '💰 ' . $e( $d['budget'] ) : '' ] );
		if ( $plan ) {
			$lines[] = implode( '   ', $plan );
		}
		$lines[] = '';
		$lines[] = '<blockquote>' . $e( mb_substr( $d['message'], 0, 3000 ) ) . '</blockquote>';
		if ( $d['link'] ) {
			$lines[] = '🔗 ' . $e( $d['link'] );
		}
		return implode( "\n", $lines );
	}

	/** @param array<string,mixed> $d @return array<int,array<string,string>> */
	public static function buttons( int $id, array $d ): array {
		$b = [ [ 'text' => __( 'Open in WordPress', 'samanzavarian-widgets' ), 'url' => Leads::kit() ? Admin\Kit::requestUrl( $id ) : admin_url( 'post.php?post=' . $id . '&action=edit' ) ] ];
		if ( $d['phone'] ) {
			$b[] = [ 'text' => 'WhatsApp', 'url' => 'https://wa.me/' . Leads::wa_digits( (string) $d['phone'] ) ];
		}
		return $b;
	}

	/**
	 * One Bot API call.
	 *
	 * @param array<string,mixed> $args
	 * @return array{ok:bool,error:string,result:mixed}
	 */
	public static function api( string $method, array $args = [], string $token = '' ): array {
		$token = '' !== $token ? $token : (string) self::option( 'token' );
		if ( '' === $token ) {
			return [ 'ok' => false, 'error' => 'no token', 'result' => null ];
		}
		$res = wp_remote_post( 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/' . $method, [ 'timeout' => 8, 'body' => $args ] );
		if ( is_wp_error( $res ) ) {
			return [ 'ok' => false, 'error' => $res->get_error_message(), 'result' => null ];
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $body ) || empty( $body['ok'] ) ) {
			return [ 'ok' => false, 'error' => (string) ( $body['description'] ?? 'HTTP ' . wp_remote_retrieve_response_code( $res ) ), 'result' => null ];
		}
		return [ 'ok' => true, 'error' => '', 'result' => $body['result'] ?? null ];
	}

	/* =================================================================
	 * Requests → Settings
	 * ================================================================= */

	public static function menu(): void {
		add_submenu_page( 'edit.php?post_type=' . Leads::TYPE, __( 'Request settings', 'samanzavarian-widgets' ), __( 'Settings', 'samanzavarian-widgets' ), 'manage_options', self::PAGE, [ self::class, 'page' ] );
	}

	public static function settings(): void {
		register_setting( 'szw_telegram', self::OPTION, [ 'type' => 'array', 'sanitize_callback' => [ self::class, 'sanitize' ] ] );
	}

	/** @param mixed $in */
	public static function sanitize( $in ): array {
		$in  = is_array( $in ) ? $in : [];
		$old = get_option( self::OPTION, [] );
		$tok = trim( (string) ( $in['token'] ?? '' ) );
		return [
			// the token is never printed back; an empty field keeps the saved one
			'token'     => '' === $tok ? (string) ( $old['token'] ?? '' ) : ( preg_match( '/^\d+:[A-Za-z0-9_-]{20,}$/', $tok ) ? $tok : (string) ( $old['token'] ?? '' ) ),
			'chat'      => preg_match( '/^-?\d+$|^@[A-Za-z0-9_]{4,}$/', trim( (string) ( $in['chat'] ?? '' ) ) ) ? trim( (string) $in['chat'] ) : '',
			'retention' => max( 0, min( 3650, (int) ( $in['retention'] ?? 0 ) ) ),
		];
	}

	private static function back( string $notice, string $kind = 'success' ): void {
		wp_safe_redirect( add_query_arg( [ 'sz_note' => rawurlencode( $notice ), 'sz_kind' => $kind ], admin_url( 'edit.php?post_type=' . Leads::TYPE . '&page=' . self::PAGE ) ) );
		exit;
	}

	/** "Find my chat": the latest chat that wrote to the bot. */
	public static function detect_action(): void {
		check_admin_referer( 'sz_tg_detect' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'samanzavarian-widgets' ) );
		}
		$r = self::api( 'getUpdates', [ 'limit' => 50 ] );
		if ( ! $r['ok'] ) {
			self::back( sprintf( /* translators: %s: error */ __( 'Telegram said: %s', 'samanzavarian-widgets' ), $r['error'] ), 'error' );
		}
		$chat = null;
		foreach ( array_reverse( (array) $r['result'] ) as $u ) {
			$c = $u['message']['chat'] ?? $u['my_chat_member']['chat'] ?? $u['channel_post']['chat'] ?? null;
			if ( $c ) {
				$chat = $c;
				break;
			}
		}
		if ( ! $chat ) {
			self::back( __( 'No messages yet. Open your bot in Telegram, press Start (or send it any message), then try again.', 'samanzavarian-widgets' ), 'warning' );
		}
		$o         = (array) get_option( self::OPTION, [] );
		$o['chat'] = (string) $chat['id'];
		update_option( self::OPTION, $o );
		$who = trim( ( $chat['title'] ?? '' ) . ' ' . ( $chat['first_name'] ?? '' ) . ' ' . ( $chat['last_name'] ?? '' ) );
		/* translators: %s: chat name */
		self::back( sprintf( __( 'Found and saved: %s. Now send a test.', 'samanzavarian-widgets' ), $who ?: $chat['id'] ) );
	}

	public static function test_action(): void {
		check_admin_referer( 'sz_tg_test' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'samanzavarian-widgets' ) );
		}
		$r = self::api( 'sendMessage', [ 'chat_id' => self::option( 'chat' ), 'text' => '✅ ' . sprintf( /* translators: %s: site name */ __( '%s is connected. New project requests will arrive here.', 'samanzavarian-widgets' ), get_bloginfo( 'name' ) ) ] );
		if ( $r['ok'] ) {
			self::back( __( 'Sent. Check Telegram.', 'samanzavarian-widgets' ) );
		}
		self::back( sprintf( /* translators: %s: error */ __( 'Telegram said: %s', 'samanzavarian-widgets' ), $r['error'] ), 'error' );
	}

	public static function page(): void {
		$has_token = '' !== (string) self::option( 'token' );
		$chat      = (string) self::option( 'chat' );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a notice after our own redirect
		$note = isset( $_GET['sz_note'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['sz_note'] ) ) ) : '';
		$kind = isset( $_GET['sz_kind'] ) && in_array( $_GET['sz_kind'], [ 'success', 'error', 'warning' ], true ) ? sanitize_key( $_GET['sz_kind'] ) : 'success';
		// phpcs:enable
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Request settings', 'samanzavarian-widgets' ); ?></h1>
			<?php if ( $note ) : ?><div class="notice notice-<?php echo esc_attr( $kind ); ?> is-dismissible"><p><?php echo esc_html( $note ); ?></p></div><?php endif; ?>

			<h2><?php esc_html_e( 'Telegram', 'samanzavarian-widgets' ); ?></h2>
			<ol style="max-width:680px">
				<li><?php echo wp_kses( __( 'In Telegram, open <b>@BotFather</b>, send <code>/newbot</code>, give it a name. It answers with a token.', 'samanzavarian-widgets' ), [ 'b' => [], 'code' => [] ] ); ?></li>
				<li><?php esc_html_e( 'Paste the token below and save.', 'samanzavarian-widgets' ); ?></li>
				<li><?php esc_html_e( 'Open your new bot in Telegram and press Start, then press "Find my chat" here.', 'samanzavarian-widgets' ); ?></li>
				<li><?php esc_html_e( 'Press "Send a test". From then on every request arrives in that chat.', 'samanzavarian-widgets' ); ?></li>
			</ol>

			<form method="post" action="options.php">
				<?php settings_fields( 'szw_telegram' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sz-tg-token"><?php esc_html_e( 'Bot token', 'samanzavarian-widgets' ); ?></label></th>
						<td>
							<input id="sz-tg-token" class="regular-text code" type="password" autocomplete="off" name="<?php echo esc_attr( self::OPTION ); ?>[token]" placeholder="<?php echo esc_attr( $has_token ? __( 'Saved. Paste a new one to replace it.', 'samanzavarian-widgets' ) : '123456789:AA…' ); ?>">
							<?php if ( $has_token ) : ?><p class="description">✓ <?php esc_html_e( 'A token is saved (kept hidden).', 'samanzavarian-widgets' ); ?></p><?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sz-tg-chat"><?php esc_html_e( 'Chat', 'samanzavarian-widgets' ); ?></label></th>
						<td><input id="sz-tg-chat" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[chat]" value="<?php echo esc_attr( $chat ); ?>" placeholder="<?php esc_attr_e( 'Filled by "Find my chat"', 'samanzavarian-widgets' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="sz-retention"><?php esc_html_e( 'Delete archived requests after', 'samanzavarian-widgets' ); ?></label></th>
						<td><input id="sz-retention" type="number" min="0" max="3650" class="small-text" name="<?php echo esc_attr( self::OPTION ); ?>[retention]" value="<?php echo (int) self::option( 'retention', 0 ); ?>"> <?php esc_html_e( 'days (0 keeps them)', 'samanzavarian-widgets' ); ?>
							<p class="description"><?php esc_html_e( 'The form promises the details are only used to reply. Requests kept as spam go after 30 days.', 'samanzavarian-widgets' ); ?></p></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<?php if ( $has_token ) : ?>
				<p>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sz_tg_detect' ), 'sz_tg_detect' ) ); ?>"><?php esc_html_e( 'Find my chat', 'samanzavarian-widgets' ); ?></a>
					<?php if ( '' !== $chat ) : ?><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sz_tg_test' ), 'sz_tg_test' ) ); ?>"><?php esc_html_e( 'Send a test', 'samanzavarian-widgets' ); ?></a><?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
