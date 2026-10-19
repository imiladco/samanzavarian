<?php
namespace Samanzavarian_Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * The projects library: a "Projects" post type and its "Project types"
 * taxonomy, which the Selected Work widget lists and filters by.
 *
 * Projects are managed like posts (Projects in the admin menu), not inside a
 * widget: a project can belong to several types, types are chosen from a
 * list instead of being typed, and filters with their counts follow from
 * the types. Each project carries its card in one meta box: category label,
 * icon, text colour, gradient, preview video, link and the Featured flag;
 * the featured image is the card's picture.
 *
 * Not public: projects have no pages of their own (they link to Behance or
 * wherever their link points). Saving a project or a type drops Elementor's
 * cached widget HTML, so the change shows at once.
 */
final class Projects {

	public const TYPE = 'sz_project';
	public const TAX  = 'sz_project_type';

	/** All card fields in one meta array; Featured also on its own, to order by. */
	public const META     = '_sz_card';
	public const FEATURED = '_sz_featured';
	public const ORDER    = 'sz_order'; // term meta: a type's place among the filters

	public const PLACEHOLDERS = 8;

	/** The design's eight projects: types, category, title, icon, gradient, light text, featured. */
	public const EXAMPLES = [
		[ [ 'AI' ], 'AI visuals', 'AI product key visuals', 'spark', 2, false, true ],
		[ [ 'Film' ], 'Brand film', 'Scripted brand films', 'video', 1, false, false ],
		[ [ 'Photo' ], 'Industrial', 'Industrial photography', 'factory', 6, false, false ],
		[ [ 'Photo' ], 'Product', 'E-commerce product shoots', 'box', 3, true, false ],
		[ [ 'Post' ], 'Retouching', 'High-end retouching', 'film', 5, true, false ],
		[ [ 'AI' ], 'AI video', 'AI motion and video', 'video', 4, false, false ],
		[ [ 'Photo' ], 'Packaging', 'Packaging and label shoots', 'box', 7, false, false ],
		[ [ 'Post' ], 'Colour grading', 'Colour grading for campaigns', 'film', 8, false, false ],
	];

	public static function boot(): void {
		add_action( 'init', [ self::class, 'register' ] );
		add_action( 'add_meta_boxes_' . self::TYPE, [ self::class, 'add_meta_box' ] );
		add_action( 'save_post_' . self::TYPE, [ self::class, 'save' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'admin_assets' ] );
		add_filter( 'manage_' . self::TYPE . '_posts_columns', [ self::class, 'columns' ] );
		add_action( 'manage_' . self::TYPE . '_posts_custom_column', [ self::class, 'column' ], 10, 2 );
		add_action( 'pre_get_posts', [ self::class, 'admin_order' ] );
		add_action( self::TAX . '_add_form_fields', [ self::class, 'term_field_add' ] );
		add_action( self::TAX . '_edit_form_fields', [ self::class, 'term_field_edit' ] );
		add_action( 'created_' . self::TAX, [ self::class, 'term_save' ] );
		add_action( 'edited_' . self::TAX, [ self::class, 'term_save' ] );
		add_filter( 'manage_edit-' . self::TAX . '_columns', [ self::class, 'term_columns' ] );
		add_filter( 'manage_' . self::TAX . '_custom_column', [ self::class, 'term_column' ], 10, 3 );
		add_action( 'admin_notices', [ self::class, 'empty_notice' ] );
		add_action( 'admin_post_sz_import_projects', [ self::class, 'import' ] );
		add_filter( 'post_updated_messages', [ self::class, 'messages' ] );

		// any change to the library: drop Elementor's cached widget HTML
		foreach ( [ 'save_post_' . self::TYPE, 'created_' . self::TAX, 'edited_' . self::TAX, 'delete_' . self::TAX ] as $hook ) {
			add_action( $hook, [ self::class, 'flush' ] );
		}
		add_action( 'deleted_post', [ self::class, 'flush_if_project' ], 10, 2 );
		add_action( 'trashed_post', [ self::class, 'flush_if_project' ] );
	}

	public static function register(): void {
		register_post_type(
			self::TYPE,
			[
				'labels'              => [
					'name'               => __( 'Projects', 'samanzavarian-widgets' ),
					'singular_name'      => __( 'Project', 'samanzavarian-widgets' ),
					'add_new_item'       => __( 'Add project', 'samanzavarian-widgets' ),
					'edit_item'          => __( 'Edit project', 'samanzavarian-widgets' ),
					'new_item'           => __( 'New project', 'samanzavarian-widgets' ),
					'search_items'       => __( 'Search projects', 'samanzavarian-widgets' ),
					'not_found'          => __( 'No projects yet.', 'samanzavarian-widgets' ),
					'not_found_in_trash' => __( 'No projects in the trash.', 'samanzavarian-widgets' ),
					'featured_image'     => __( 'Card picture', 'samanzavarian-widgets' ),
					'set_featured_image' => __( 'Set card picture', 'samanzavarian-widgets' ),
					'menu_name'          => __( 'Projects', 'samanzavarian-widgets' ),
					'attributes'         => __( 'Order in the slider', 'samanzavarian-widgets' ),
				],
				'description'         => __( 'The work listed by the Selected Work widget.', 'samanzavarian-widgets' ),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false, // the classic screen: title, picture, types, card
				'menu_icon'           => 'dashicons-portfolio',
				'menu_position'       => 21,
				'supports'            => [ 'title', 'thumbnail', 'page-attributes' ],
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
			]
		);

		register_taxonomy(
			self::TAX,
			self::TYPE,
			[
				'labels'            => [
					'name'          => __( 'Project types', 'samanzavarian-widgets' ),
					'singular_name' => __( 'Project type', 'samanzavarian-widgets' ),
					'add_new_item'  => __( 'Add project type', 'samanzavarian-widgets' ),
					'edit_item'     => __( 'Edit project type', 'samanzavarian-widgets' ),
					'search_items'  => __( 'Search types', 'samanzavarian-widgets' ),
					'not_found'     => __( 'No types yet.', 'samanzavarian-widgets' ),
					'menu_name'     => __( 'Types (filters)', 'samanzavarian-widgets' ),
				],
				'description'       => __( 'Each type is a filter above the slider.', 'samanzavarian-widgets' ),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'hierarchical'      => true, // checkboxes: pick types, never type them
				'rewrite'           => false,
			]
		);
	}

	/* =================================================================
	 * Reading the library (for the widget)
	 * ================================================================= */

	public static function count(): int {
		$counts = wp_count_posts( self::TYPE );

		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * Types in their filter order: their Order number, then name.
	 *
	 * @return \WP_Term[]
	 */
	public static function types( bool $hide_empty = false ): array {
		$terms = get_terms( [ 'taxonomy' => self::TAX, 'hide_empty' => $hide_empty ] );

		if ( ! is_array( $terms ) ) {
			return [];
		}

		usort(
			$terms,
			static function ( $a, $b ) {
				$x = (int) get_term_meta( $a->term_id, self::ORDER, true );
				$y = (int) get_term_meta( $b->term_id, self::ORDER, true );

				return $x === $y ? strcasecmp( $a->name, $b->name ) : $x <=> $y;
			}
		);

		return $terms;
	}

	/** @return array<string,string> slug => name, in filter order */
	public static function type_options(): array {
		$out = [];

		foreach ( self::types() as $t ) {
			$out[ $t->slug ] = $t->name;
		}

		return $out;
	}

	/** @return array<string,mixed> a project's card fields, with defaults */
	public static function card( int $id ): array {
		$raw = get_post_meta( $id, self::META, true );
		$raw = is_array( $raw ) ? $raw : [];
		$ph  = (int) ( $raw['placeholder'] ?? 1 );

		return [
			'category'    => (string) ( $raw['category'] ?? '' ),
			'icon'        => isset( Icon_Library::GLYPHS[ $raw['icon'] ?? '' ] ) ? $raw['icon'] : 'spark',
			'skin'        => 'light' === ( $raw['skin'] ?? 'dark' ) ? 'light' : 'dark',
			'placeholder' => $ph >= 1 && $ph <= self::PLACEHOLDERS ? $ph : 1,
			'video'       => (string) ( $raw['video'] ?? '' ),
			'link'        => (string) ( $raw['link'] ?? '' ),
			'new_tab'     => ! isset( $raw['new_tab'] ) || ! empty( $raw['new_tab'] ),
			'featured'    => '1' === get_post_meta( $id, self::FEATURED, true ),
		];
	}

	/**
	 * Published projects as the widget's cards.
	 *
	 * @param string[] $types slugs to keep; empty: all
	 * @return array<int,array<string,mixed>>
	 */
	public static function cards( array $types = [], string $order = 'menu', int $limit = 12 ): array {
		$args = [
			'post_type'        => self::TYPE,
			'post_status'      => 'publish',
			'posts_per_page'   => max( 1, min( 50, $limit ) ),
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'orderby'          => 'date' === $order ? [ 'date' => 'DESC' ] : [ 'menu_order' => 'ASC', 'date' => 'DESC' ],
		];

		if ( $types ) {
			$args['tax_query'] = [ [ 'taxonomy' => self::TAX, 'field' => 'slug', 'terms' => array_values( $types ) ] ]; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$posts = get_posts( $args );

		if ( 'featured' === $order ) {
			// featured first; each group keeps its manual order (the index breaks ties)
			$rank = static fn( $p ) => '1' === get_post_meta( $p->ID, self::FEATURED, true ) ? 0 : 1;
			$keys = array_map( $rank, $posts );
			array_multisort( $keys, SORT_ASC, array_keys( $posts ), SORT_ASC, $posts );
		}

		$out = [];

		foreach ( $posts as $post ) {
			$c     = self::card( $post->ID );
			$terms = get_the_terms( $post, self::TAX );
			$terms = is_array( $terms ) ? $terms : [];
			$thumb = (int) get_post_thumbnail_id( $post );

			$out[] = [
				'id'          => '',
				'post'        => $post->ID,
				'title'       => get_the_title( $post ),
				'category'    => '' !== $c['category'] ? $c['category'] : ( $terms ? $terms[0]->name : '' ),
				'groups'      => array_map( static fn( $t ) => $t->slug, $terms ),
				'filter'      => implode( ', ', array_map( static fn( $t ) => $t->name, $terms ) ),
				'image'       => $thumb ? [ 'id' => $thumb, 'url' => (string) wp_get_attachment_image_url( $thumb, 'large' ) ] : [],
				'video'       => $c['video'],
				'placeholder' => $c['placeholder'],
				'icon'        => Icon_Library::value( $c['icon'] ),
				'link'        => '' !== $c['link'] ? [ 'url' => $c['link'], 'is_external' => $c['new_tab'] ? 'on' : '', 'nofollow' => '' ] : [],
				'light'       => 'light' === $c['skin'],
				'featured'    => $c['featured'],
			];
		}

		return $out;
	}

	/* =================================================================
	 * Admin: the project screen
	 * ================================================================= */

	public static function add_meta_box(): void {
		add_meta_box( 'sz-project-card', __( 'Card', 'samanzavarian-widgets' ), [ self::class, 'meta_box' ], self::TYPE, 'normal', 'high' );
	}

	public static function meta_box( \WP_Post $post ): void {
		$c = self::card( $post->ID );
		wp_nonce_field( 'sz_project_card', 'sz_project_nonce' );
		?>
		<div class="sz-pcard">
			<p class="sz-pcard__intro"><?php esc_html_e( 'How this project looks in the Selected Work slider. The card picture is the Featured image (right); its types, the filters it shows under.', 'samanzavarian-widgets' ); ?></p>

			<div class="sz-pcard__row">
				<label for="sz-category"><?php esc_html_e( 'Category label', 'samanzavarian-widgets' ); ?></label>
				<div>
					<input type="text" id="sz-category" name="sz_card[category]" class="regular-text" value="<?php echo esc_attr( $c['category'] ); ?>" placeholder="<?php esc_attr_e( 'Brand film', 'samanzavarian-widgets' ); ?>">
					<p class="description"><?php esc_html_e( 'Above the title and in the card\'s bar. Empty: the project\'s first type.', 'samanzavarian-widgets' ); ?></p>
				</div>
			</div>

			<div class="sz-pcard__row">
				<span class="sz-pcard__label"><?php esc_html_e( 'Icon', 'samanzavarian-widgets' ); ?></span>
				<div class="sz-pcard__icons" role="radiogroup" aria-label="<?php esc_attr_e( 'Icon', 'samanzavarian-widgets' ); ?>">
					<?php foreach ( array_keys( Icon_Library::GLYPHS ) as $glyph ) : ?>
						<label title="<?php echo esc_attr( $glyph ); ?>"><input type="radio" name="sz_card[icon]" value="<?php echo esc_attr( $glyph ); ?>" <?php checked( $c['icon'], $glyph ); ?>><span><?php echo Icon_Library::svg( $glyph ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static glyph ?></span></label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="sz-pcard__row">
				<span class="sz-pcard__label"><?php esc_html_e( 'Text on the picture', 'samanzavarian-widgets' ); ?></span>
				<div class="sz-pcard__choice">
					<label><input type="radio" name="sz_card[skin]" value="dark" <?php checked( $c['skin'], 'dark' ); ?>> <?php esc_html_e( 'White, for a dark picture', 'samanzavarian-widgets' ); ?></label>
					<label><input type="radio" name="sz_card[skin]" value="light" <?php checked( $c['skin'], 'light' ); ?>> <?php esc_html_e( 'Dark, for a light picture', 'samanzavarian-widgets' ); ?></label>
				</div>
			</div>

			<div class="sz-pcard__row">
				<span class="sz-pcard__label"><?php esc_html_e( 'Without a picture', 'samanzavarian-widgets' ); ?></span>
				<div class="sz-pcard__swatches" role="radiogroup" aria-label="<?php esc_attr_e( 'Gradient', 'samanzavarian-widgets' ); ?>">
					<?php for ( $n = 1; $n <= self::PLACEHOLDERS; $n++ ) : ?>
						<label><input type="radio" name="sz_card[placeholder]" value="<?php echo (int) $n; ?>" <?php checked( $c['placeholder'], $n ); ?>><span class="sz-work__mesh--<?php echo (int) $n; ?>"></span></label>
					<?php endfor; ?>
				</div>
			</div>

			<div class="sz-pcard__row">
				<label for="sz-video"><?php esc_html_e( 'Preview video', 'samanzavarian-widgets' ); ?></label>
				<div>
					<div class="sz-pcard__media">
						<input type="url" id="sz-video" name="sz_card[video]" class="regular-text" value="<?php echo esc_attr( $c['video'] ); ?>" placeholder="https://…/clip.mp4">
						<button type="button" class="button" data-sz-pick-video><?php esc_html_e( 'Choose video', 'samanzavarian-widgets' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'Optional. Plays without sound while the pointer is on the card. A 5–10 s loop under 3 MB.', 'samanzavarian-widgets' ); ?></p>
				</div>
			</div>

			<div class="sz-pcard__row">
				<label for="sz-link"><?php esc_html_e( 'Link', 'samanzavarian-widgets' ); ?></label>
				<div>
					<input type="url" id="sz-link" name="sz_card[link]" class="regular-text" value="<?php echo esc_attr( $c['link'] ); ?>" placeholder="https://www.behance.net/gallery/…">
					<label class="sz-pcard__inline"><input type="checkbox" name="sz_card[new_tab]" value="1" <?php checked( $c['new_tab'] ); ?>> <?php esc_html_e( 'Open in a new tab', 'samanzavarian-widgets' ); ?></label>
					<p class="description"><?php esc_html_e( 'Empty: the profile link set in the widget.', 'samanzavarian-widgets' ); ?></p>
				</div>
			</div>

			<div class="sz-pcard__row">
				<span class="sz-pcard__label"><?php esc_html_e( 'Featured', 'samanzavarian-widgets' ); ?></span>
				<label class="sz-pcard__inline"><input type="checkbox" name="sz_card[featured]" value="1" <?php checked( $c['featured'] ); ?>> <?php esc_html_e( 'Show the "Featured" label, and lead under "All" as the wide card', 'samanzavarian-widgets' ); ?></label>
			</div>
		</div>
		<?php
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['sz_project_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sz_project_nonce'] ), 'sz_project_card' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$in = isset( $_POST['sz_card'] ) && is_array( $_POST['sz_card'] ) ? wp_unslash( $_POST['sz_card'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitised below

		self::store( $post_id, $in );
	}

	/** Sanitise and save a project's card. @param array<string,mixed> $in */
	public static function store( int $post_id, array $in ): void {
		$ph = (int) ( $in['placeholder'] ?? 1 );

		update_post_meta(
			$post_id,
			self::META,
			[
				'category'    => sanitize_text_field( (string) ( $in['category'] ?? '' ) ),
				'icon'        => isset( Icon_Library::GLYPHS[ $in['icon'] ?? '' ] ) ? (string) $in['icon'] : 'spark',
				'skin'        => 'light' === ( $in['skin'] ?? '' ) ? 'light' : 'dark',
				'placeholder' => $ph >= 1 && $ph <= self::PLACEHOLDERS ? $ph : 1,
				'video'       => esc_url_raw( (string) ( $in['video'] ?? '' ) ),
				'link'        => esc_url_raw( (string) ( $in['link'] ?? '' ) ),
				'new_tab'     => ! empty( $in['new_tab'] ) ? 1 : 0,
			]
		);
		update_post_meta( $post_id, self::FEATURED, ! empty( $in['featured'] ) ? '1' : '0' );
	}

	public static function admin_assets( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || self::TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'sz-projects-admin', SZW_URL . 'assets/css/admin/projects.css', [], SZW_VERSION );

		if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'sz-projects-admin', SZW_URL . 'assets/js/admin/projects.js', [ 'media-editor' ], SZW_VERSION, true );
			wp_localize_script( 'sz-projects-admin', 'szProjects', [ 'title' => __( 'Preview video', 'samanzavarian-widgets' ), 'button' => __( 'Use this video', 'samanzavarian-widgets' ) ] );
		}
	}

	/** "Project updated", not "Post updated". @param array<string,array<int,string>> $m */
	public static function messages( array $m ): array {
		$saved = __( 'Project saved. It shows in Selected Work right away.', 'samanzavarian-widgets' );

		$m[ self::TYPE ] = [ 1 => $saved, 4 => $saved, 6 => $saved, 7 => $saved, 8 => $saved, 10 => __( 'Draft saved. Publish it to show it in Selected Work.', 'samanzavarian-widgets' ) ];

		return $m;
	}

	/* ---------------- the list ---------------- */

	/** @param array<string,string> $cols */
	public static function columns( array $cols ): array {
		$out = [];

		foreach ( $cols as $key => $label ) {
			if ( 'title' === $key ) {
				$out['sz_card'] = '<span class="screen-reader-text">' . esc_html__( 'Card', 'samanzavarian-widgets' ) . '</span>';
			}
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['sz_featured'] = esc_html__( 'Featured', 'samanzavarian-widgets' );
				$out['sz_order']    = esc_html__( 'Order', 'samanzavarian-widgets' );
			}
		}
		unset( $out['date'] );

		return $out;
	}

	public static function column( string $col, int $post_id ): void {
		$c = self::card( $post_id );

		if ( 'sz_card' === $col ) {
			$thumb = get_the_post_thumbnail( $post_id, [ 48, 64 ], [ 'class' => 'sz-pthumb' ] );
			echo $thumb ? $thumb : '<span class="sz-pthumb sz-work__mesh--' . (int) $c['placeholder'] . '"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup
		} elseif ( 'sz_featured' === $col ) {
			echo $c['featured'] ? '<span class="sz-pstar" aria-label="' . esc_attr__( 'Featured', 'samanzavarian-widgets' ) . '">★</span>' : '<span aria-hidden="true">—</span>';
		} elseif ( 'sz_order' === $col ) {
			echo (int) get_post_field( 'menu_order', $post_id );
		}
	}

	/** The list in slider order by default. */
	public static function admin_order( \WP_Query $q ): void {
		if ( is_admin() && $q->is_main_query() && self::TYPE === $q->get( 'post_type' ) && ! $q->get( 'orderby' ) ) {
			$q->set( 'orderby', [ 'menu_order' => 'ASC', 'date' => 'DESC' ] );
		}
	}

	/* ---------------- types: their order among the filters ---------------- */

	public static function term_field_add(): void {
		?>
		<div class="form-field">
			<label for="sz-order"><?php esc_html_e( 'Order among the filters', 'samanzavarian-widgets' ); ?></label>
			<input type="number" id="sz-order" name="sz_order" value="0" step="1">
			<p><?php esc_html_e( 'Lower comes first. Equal numbers go by name.', 'samanzavarian-widgets' ); ?></p>
		</div>
		<?php
	}

	public static function term_field_edit( \WP_Term $term ): void {
		?>
		<tr class="form-field">
			<th scope="row"><label for="sz-order"><?php esc_html_e( 'Order among the filters', 'samanzavarian-widgets' ); ?></label></th>
			<td><input type="number" id="sz-order" name="sz_order" value="<?php echo (int) get_term_meta( $term->term_id, self::ORDER, true ); ?>" step="1">
			<p class="description"><?php esc_html_e( 'Lower comes first. Equal numbers go by name.', 'samanzavarian-widgets' ); ?></p></td>
		</tr>
		<?php
	}

	public static function term_save( int $term_id ): void {
		if ( isset( $_POST['sz_order'] ) && current_user_can( 'manage_categories' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- core verifies the term form's nonce
			update_term_meta( $term_id, self::ORDER, (int) $_POST['sz_order'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
	}

	/** @param array<string,string> $cols */
	public static function term_columns( array $cols ): array {
		unset( $cols['description'] );
		$cols['sz_order'] = esc_html__( 'Order', 'samanzavarian-widgets' );

		return $cols;
	}

	/** @param string $out */
	public static function term_column( $out, string $col, int $term_id ): string {
		return 'sz_order' === $col ? (string) (int) get_term_meta( $term_id, self::ORDER, true ) : (string) $out;
	}

	/* ---------------- an empty library: offer the design's examples ---------------- */

	public static function empty_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'edit-' . self::TYPE !== $screen->id || self::count() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		?>
		<div class="notice notice-info sz-pnotice">
			<p><strong><?php esc_html_e( 'Start from the design?', 'samanzavarian-widgets' ); ?></strong> <?php esc_html_e( 'Adds the design\'s eight example projects and their four types (AI, Film, Photo, Post), ready to replace with your own pictures, links and videos.', 'samanzavarian-widgets' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sz_import_projects' ), 'sz_import_projects' ) ); ?>"><?php esc_html_e( 'Add the 8 example projects', 'samanzavarian-widgets' ); ?></a></p>
		</div>
		<?php
	}

	public static function import(): void {
		if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'sz_import_projects' ) ) {
			wp_die( esc_html__( 'You cannot add projects.', 'samanzavarian-widgets' ) );
		}

		self::add_examples();
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . self::TYPE ) );
		exit;
	}

	/** Create the design's projects and types (only into an empty library). @return int how many were added */
	public static function add_examples(): int {
		if ( self::count() ) {
			return 0;
		}

		$order = 0;

		foreach ( [ 'AI', 'Film', 'Photo', 'Post' ] as $name ) {
			$term = term_exists( $name, self::TAX );
			$term = $term ? $term : wp_insert_term( $name, self::TAX );
			if ( ! is_wp_error( $term ) ) {
				update_term_meta( (int) $term['term_id'], self::ORDER, $order++ * 10 );
			}
		}

		$added = 0;

		foreach ( self::EXAMPLES as $i => $p ) {
			$id = wp_insert_post(
				[
					'post_type'   => self::TYPE,
					'post_status' => 'publish',
					'post_title'  => $p[2],
					'menu_order'  => ( $i + 1 ) * 10,
				]
			);
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			wp_set_object_terms( $id, $p[0], self::TAX );
			self::store( $id, [ 'category' => $p[1], 'icon' => $p[3], 'placeholder' => $p[4], 'skin' => $p[5] ? 'light' : 'dark', 'featured' => $p[6], 'new_tab' => 1 ] );
			++$added;
		}

		self::flush();

		return $added;
	}

	/* ---------------- Elementor's cached widget HTML ---------------- */

	public static function flush(): void {
		delete_post_meta_by_key( '_elementor_element_cache' );
	}

	/** @param \WP_Post|null $post */
	public static function flush_if_project( int $post_id, $post = null ): void {
		if ( self::TYPE === get_post_type( $post_id ) || ( $post instanceof \WP_Post && self::TYPE === $post->post_type ) ) {
			self::flush();
		}
	}
}
