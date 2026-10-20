<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Plugin as Elementor;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Hero Title — the name in the display face: first word navy, an app-icon
 * tile between, second word orange.
 *
 * The words rise into place one after another when the page opens; the
 * tile tips the other way under the pointer. On a phone the tile steps out
 * and the two words stay on one line.
 */
class Hero_Title extends Widget_Base {

	public function get_name(): string {
		return 'sz-htitle';
	}

	public function get_title(): string {
		return esc_html__( 'Hero Title', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-heading';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'hero', 'title', 'name', 'heading' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-hero' ];
	}

	public function get_script_depends(): array {
		return [ 'sz-hero' ];
	}

	protected function is_dynamic_content(): bool {
		return false;
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Elementor::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'section_title', [ 'label' => esc_html__( 'Title', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'first', [ 'label' => esc_html__( 'First word', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Saman' ] );
		$this->add_control( 'second', [ 'label' => esc_html__( 'Second word (orange)', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Zavarian' ] );
		$this->add_control( 'icon', [ 'label' => esc_html__( 'Tile between', 'samanzavarian-widgets' ), 'type' => Controls_Manager::ICONS, 'default' => Icon_Library::value( 'lens' ), 'skin' => 'inline', 'label_block' => false, 'description' => esc_html__( 'Leave empty for no tile.', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'entrance', [ 'label' => esc_html__( 'Entrance', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SELECT, 'default' => 'words', 'options' => [ 'words' => esc_html__( 'Word by word', 'samanzavarian-widgets' ), 'letters' => esc_html__( 'Letter by letter', 'samanzavarian-widgets' ), 'none' => esc_html__( 'None', 'samanzavarian-widgets' ) ] ] );
		$this->add_control( 'tag', [ 'label' => esc_html__( 'HTML tag', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SELECT, 'default' => 'h1', 'options' => [ 'h1' => 'H1', 'h2' => 'H2', 'div' => 'div' ], 'description' => esc_html__( 'One H1 per page: the hero\'s title is usually it.', 'samanzavarian-widgets' ) ] );
		$this->add_responsive_control( 'size', [ 'label' => esc_html__( 'Size', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'px', 'vw' ], 'range' => [ 'px' => [ 'min' => 32, 'max' => 220 ] ], 'selectors' => [ '{{WRAPPER}} .sz-htitle' => 'font-size: {{SIZE}}{{UNIT}};' ] ] );
		$this->add_control( 'fit_m', [ 'label' => esc_html__( 'Fill the width on phones', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'description' => esc_html__( 'On a phone the name sits on one line from edge to edge, whatever the screen, font or words. Elsewhere it only gets smaller when a word would not fit.', 'samanzavarian-widgets' ) ] );
		$this->add_responsive_control( 'align', [ 'label' => esc_html__( 'Alignment', 'samanzavarian-widgets' ), 'type' => Controls_Manager::CHOOSE, 'options' => [ 'flex-start' => [ 'title' => esc_html__( 'Left', 'samanzavarian-widgets' ), 'icon' => 'eicon-text-align-left' ], 'center' => [ 'title' => esc_html__( 'Center', 'samanzavarian-widgets' ), 'icon' => 'eicon-text-align-center' ], 'flex-end' => [ 'title' => esc_html__( 'Right', 'samanzavarian-widgets' ), 'icon' => 'eicon-text-align-right' ] ], 'default' => 'center', 'selectors' => [ '{{WRAPPER}} .sz-htitle' => 'justify-content: {{VALUE}};' ] ] );
		$this->end_controls_section();
		$this->register_style();
	}

	private function register_style(): void {
		$this->start_controls_section( 'st_layout', [ 'label' => esc_html__( 'Layout', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'gap', [ 'label' => esc_html__( 'Space between', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'em', 'px' ], 'range' => [ 'em' => [ 'min' => 0, 'max' => .6, 'step' => .01 ] ], 'selectors' => [ '{{WRAPPER}} .sz-htitle' => 'gap: {{SIZE}}{{UNIT}};' ] ] );
		$this->end_controls_section();
		Style_Controls::text( $this, 'st_all', esc_html__( 'Title', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-htitle' );
		Style_Controls::text( $this, 'st_first', esc_html__( 'First word', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-htitle__w:not(.is-o)', [ 'no_margin' => true ] );
		Style_Controls::text( $this, 'st_second', esc_html__( 'Second word', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-htitle__w.is-o', [ 'no_margin' => true ] );
		Style_Controls::tile( $this, 'st_tile', esc_html__( 'Tile', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-htitle__app' );
		Style_Controls::motion( $this, [ 'magnet' => [ 'label' => esc_html__( 'Tile leans to the pointer', 'samanzavarian-widgets' ) ] ] );
	}

	protected function render(): void {
		$s    = $this->get_settings_for_display();
		$tag  = in_array( $s['tag'] ?? 'h1', [ 'h1', 'h2', 'div' ], true ) ? $s['tag'] : 'h1';
		$icon = ! empty( $s['icon']['value'] ) ? Icon_Library::html( $s['icon'] ) : '';
		$mode = in_array( $s['entrance'] ?? 'words', [ 'words', 'letters', 'none' ], true ) ? $s['entrance'] : 'words';
		printf( '<%s class="sz-htitle sz-w is-%s"%s>', esc_attr( $tag ), esc_attr( $mode ), 'yes' === ( $s['fit_m'] ?? 'yes' ) ? ' data-fit="1"' : '' );
		$n    = 0;
		// letter by letter: each letter its own span, counted across both words (read aloud as the word: aria-label)
		$word = static function ( string $text, string $cls ) use ( $mode, &$n ): string {
			if ( 'letters' !== $mode ) {
				return '<span class="' . $cls . '">' . esc_html( $text ) . '</span>';
			}
			$out = '';
			foreach ( preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
				$out .= '<span class="sz-htitle__c" style="--i:' . ( $n++ ) . '" aria-hidden="true">' . esc_html( $ch ) . '</span>';
			}
			return '<span class="' . $cls . '" aria-label="' . esc_attr( $text ) . '" role="text">' . $out . '</span>';
		};
		if ( '' !== trim( (string) $s['first'] ) ) {
			echo $word( trim( (string) $s['first'] ), 'sz-htitle__w' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
		}
		if ( '' !== $icon ) {
			echo '<span class="sz-htitle__app sz-app" aria-hidden="true"' . ( 'yes' === ( $s['magnet'] ?? 'yes' ) ? ' data-sz-magnet="10"' : '' ) . '>' . $icon . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup
		}
		if ( '' !== trim( (string) $s['second'] ) ) {
			echo $word( trim( (string) $s['second'] ), 'sz-htitle__w is-o' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
		}
		printf( '</%s>', esc_attr( $tag ) );
		if ( 'yes' === ( $s['fit_m'] ?? 'yes' ) ) {
			self::render_fit_probe();
		}
	}

	/**
	 * Phones: the name fills its box from the first paint. This runs while the page is still
	 * being read, right after the title, and sets the size that makes the name exactly as wide
	 * as the room it has (hero.js does it again only on a real resize). While the site's font
	 * is still loading the title waits unseen (two and a half seconds at most), so the swap from the fallback
	 * font is not seen either. Delayed by an optimiser, currentScript is null: the try swallows
	 * it and hero.js measures instead.
	 */
	private static function render_fit_probe(): void {
		?>
		<script>(function(me){try{var fit=window.szwFitTitle||(window.szwFitTitle=function(t){if(!t||!window.matchMedia('(max-width:767px)').matches)return;t.style.fontSize='';var w=t.querySelectorAll(':scope>.sz-htitle__w'),l=1e9,r=-1e9,i,k;for(k=0;k<3;k++){l=1e9;r=-1e9;for(i=0;i<w.length;i++){l=Math.min(l,w[i].offsetLeft);r=Math.max(r,w[i].offsetLeft+w[i].offsetWidth)}var wd=t.closest('.elementor-widget')||t,p=wd.parentElement||t,cs=getComputedStyle(p),room=Math.max(t.clientWidth,p.clientWidth-parseFloat(cs.paddingLeft)-parseFloat(cs.paddingRight));if(!(r>l)||!room||Math.abs(r-l-room)<=1)break;t.style.fontSize=(parseFloat(getComputedStyle(t).fontSize)*room/(r-l)*.995).toFixed(2)+'px'}});var t=me.previousElementSibling;fit(t);if(document.fonts&&document.fonts.status!=='loaded'&&window.matchMedia('(max-width:767px)').matches){t.classList.add('is-fitting');var go=function(){fit(t);t.classList.remove('is-fitting')};document.fonts.ready.then(go);setTimeout(go,2500)}}catch(e){}})(document.currentScript);</script>
		<?php
	}
}
