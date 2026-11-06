<?php
/**
 * Converts between the Writing desk's plain text format and WordPress blocks.
 *
 * post_content is always native block markup, so the Writing desk and the block
 * editor work on the same record. The desk's format has only paragraphs and
 * "## " headings. Anything richer is reported as rich content and must be edited
 * in the block editor, so the desk can never silently flatten it.
 *
 * @package Naturedex
 */

namespace Naturedex;

defined( 'ABSPATH' ) || exit;

final class Content_Format {
	/**
	 * Raw HTML in the text is escaped, so it is displayed rather than executed.
	 */
	public static function to_blocks( string $text ): string {
		$text = trim( str_replace( "\r\n", "\n", $text ) );
		if ( '' === $text ) {
			return '';
		}
		$blocks = array();
		foreach ( preg_split( '/\n\s*\n/', $text ) as $part ) {
			$part = trim( $part );
			if ( '' === $part ) {
				continue;
			}
			if ( preg_match( '/^#{1,2} +(.+)$/s', $part, $match ) ) {
				$heading  = self::escape( preg_replace( '/\s*\n\s*/', ' ', trim( $match[1] ) ) );
				$blocks[] = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">{$heading}</h2>\n<!-- /wp:heading -->";
			} else {
				$blocks[] = "<!-- wp:paragraph -->\n<p>" . self::escape( $part ) . "</p>\n<!-- /wp:paragraph -->";
			}
		}
		return implode( "\n\n", $blocks );
	}

	/**
	 * @return array{body: string, rich: bool}
	 */
	public static function from_blocks( string $content ): array {
		$parts = array();
		foreach ( parse_blocks( $content ) as $block ) {
			$html = trim( (string) $block['innerHTML'] );
			if ( null === $block['blockName'] ) {
				if ( '' === $html ) {
					continue;
				}
				return self::rich( $content );
			}
			$text = self::simple_text( $block, $html );
			if ( null === $text ) {
				return self::rich( $content );
			}
			$parts[] = 'core/heading' === $block['blockName'] ? "## {$text}" : $text;
		}
		return array(
			'body' => implode( "\n\n", $parts ),
			'rich' => false,
		);
	}

	/**
	 * The plain text of a paragraph or level-two heading, or null if the block
	 * holds anything the desk's format cannot represent.
	 */
	private static function simple_text( array $block, string $html ): ?string {
		$tag = array(
			'core/paragraph' => 'p',
			'core/heading'   => 'h2',
		)[ $block['blockName'] ] ?? null;
		if ( null === $tag || array() !== $block['attrs'] ) {
			return null;
		}
		$pattern = "#^<{$tag}(?: class=\"wp-block-heading\")?>(.*)</{$tag}>$#s";
		if ( ! preg_match( $pattern, $html, $match ) || str_contains( $match[1], '<' ) ) {
			return null;
		}
		return html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * @return array{body: string, rich: bool}
	 */
	private static function rich( string $content ): array {
		return array(
			'body' => trim( wp_strip_all_tags( $content ) ),
			'rich' => true,
		);
	}

	private static function escape( string $text ): string {
		return htmlspecialchars( $text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}
