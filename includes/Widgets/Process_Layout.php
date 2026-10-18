<?php
namespace Samanzavarian_Widgets\Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * The playful "pinned to a board" layout of the Process widget, as CSS
 * custom properties — no JavaScript, no layout reads, no layout shift.
 *
 * Each card needs to know where it sits *within its row* to get its tilt,
 * its vertical stagger, and whether it draws an arrow to its neighbour (the
 * last card of a row must not point off the edge). CSS cannot count columns,
 * so the pattern is unrolled over a cycle of 12 positions — 12 is divisible
 * by every supported column count (1–4) — and a card at position k reads
 * position k's values through :nth-child(12n+k).
 *
 * The per-column numbers are measured from the design file, not invented:
 * 4 and 2 columns are the design's desktop and narrow layouts exactly; 3
 * extends the same rhythm. One column stacks the cards and turns the arrows
 * to point down, into the next card.
 *
 * This class is the single source for both the stylesheet's built-in default
 * (written by tools/build-process-css.php) and the Columns control's per-
 * breakpoint output, so the two can never disagree.
 */
final class Process_Layout {

	public const CYCLE = 12;

	/**
	 * rot: tilt in degrees at intensity 1, per column (cycled per row for 1).
	 * y:   vertical stagger as a fraction of the "Stagger" setting, per column.
	 */
	private const PATTERNS = [
		1 => [ 'rot' => [ -2, 2 ], 'y' => [ 0 ] ],
		2 => [ 'rot' => [ -2, 2 ], 'y' => [ 0, 0.65 ] ],
		3 => [ 'rot' => [ -2, 1.5, -1 ], 'y' => [ 0, 1, 0.15 ] ],
		4 => [ 'rot' => [ -2, 1.5, -1, 2 ], 'y' => [ 0, 1, 0.15, 1.15 ] ],
	];

	/** @return int[] */
	public static function supported_columns(): array {
		return array_keys( self::PATTERNS );
	}

	/**
	 * Values for one position (1-based) at a given column count.
	 *
	 * @return array{r:float,y:float,a:int,s:int,o:float,v:int}
	 *   r tilt (deg), y stagger factor, a 1 if an arrow leaves this card,
	 *   s which of the two drawn arrows (0 above the cards, 1 below —
	 *   alternating, as in the design), o how far the tilt pushes the card's
	 *   corner out (px per unit of intensity), so the arrow clears a tilted
	 *   edge exactly, v 1 when the arrow points down to the card below.
	 */
	public static function position( int $cols, int $k ): array {
		$pattern = self::PATTERNS[ $cols ] ?? self::PATTERNS[4];
		$col     = ( $k - 1 ) % $cols;
		$rot     = (float) $pattern['rot'][ ( $k - 1 ) % count( $pattern['rot'] ) ];
		$stacked = 1 === $cols;

		return [
			'r' => $rot,
			'y' => (float) $pattern['y'][ $col ],
			// Stacked, every card has a next one straight below it.
			'a' => $stacked || $col < $cols - 1 ? 1 : 0,
			's' => ( $stacked ? $k - 1 : $col ) % 2,
			'o' => abs( $rot ) * 2,
			'v' => $stacked ? 1 : 0,
		];
	}

	/**
	 * Every custom property a column count needs, as one declaration list —
	 * the exact string the Columns control writes for its breakpoint.
	 */
	public static function vars( int $cols ): string {
		// --szp-pad: room under the board for the staggered cards (none stacked).
		$out = '--szp-cols:' . $cols . ';--szp-pad:' . ( 1 === $cols ? 0 : 1 ) . ';';

		for ( $k = 1; $k <= self::CYCLE; $k++ ) {
			foreach ( self::position( $cols, $k ) as $key => $value ) {
				$out .= '--szp-' . $key . $k . ':' . self::num( $value ) . ';';
			}
		}

		return $out;
	}

	/** The 12 rules that hand each card its position's values. */
	public static function item_rules(): string {
		$css = '';

		for ( $k = 1; $k <= self::CYCLE; $k++ ) {
			$css .= '.sz-process__item:nth-child(12n+' . $k . '){'
				. '--r:var(--szp-r' . $k . ');--y:var(--szp-y' . $k . ');--a:var(--szp-a' . $k . ');'
				. '--s:var(--szp-s' . $k . ');--o:var(--szp-o' . $k . ');--v:var(--szp-v' . $k . ')}' . "\n";
		}

		return $css;
	}

	/** Compact, locale-proof number formatting: 1.15, -2, 0 — never "1.15E+0" or "1,15". */
	private static function num( float $n ): string {
		$s = rtrim( rtrim( sprintf( '%.4F', $n ), '0' ), '.' );

		return '-0' === $s ? '0' : $s;
	}
}
