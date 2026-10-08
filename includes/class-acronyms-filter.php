<?php
/**
 * Content filtering engine for the Acronyms plugin.
 *
 * Scans post content for defined acronyms and wraps the first occurrence
 * of each in an <abbr> element with the full meaning as the title attribute.
 *
 * @package Acronyms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles content filtering and front-end script enqueuing.
 */
class Acronyms_Filter {

	/**
	 * Tag names whose descendant text nodes must never be modified.
	 *
	 * @var array
	 */
	private $protected_tags = array( 'a', 'code', 'pre', 'script', 'style', 'abbr', 'textarea', 'input', 'select' );

	/**
	 * Constructor: register content filter and front-end script hooks.
	 */
	public function __construct() {
		add_filter( 'the_content', array( $this, 'filter_content' ), 9999 );
		add_filter( 'the_content_feed', array( $this, 'filter_content' ), 9999 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_script' ) );
	}

	/**
	 * Enqueue the front-end tooltip script on applicable post types.
	 */
	public function enqueue_frontend_script() {
		if ( ! $this->should_filter() ) {
			return;
		}

		wp_enqueue_script(
			'acronyms-frontend',
			ACRONYMS_PLUGIN_URL . 'js/frontend.js',
			array(),
			ACRONYMS_VERSION,
			true
		);
	}

	/**
	 * Main content filter entry point.
	 *
	 * @param string $content The post content.
	 * @return string Filtered content with <abbr> tags.
	 */
	public function filter_content( $content ) {
		if ( empty( trim( $content ) ) ) {
			return $content;
		}

		if ( ! $this->should_filter() ) {
			return $content;
		}

		$acronyms = Acronyms_DB::get_acronyms_for_replacement();

		if ( empty( $acronyms ) ) {
			return $content;
		}

		// Quick check: does the content contain any acronym text at all?
		$found_any = false;
		foreach ( $acronyms as $acr ) {
			$check = $acr->case_sensitive
				? ( false !== strpos( $content, $acr->acronym ) )
				: ( false !== stripos( $content, $acr->acronym ) );
			if ( $check ) {
				$found_any = true;
				break;
			}
		}

		if ( ! $found_any ) {
			return $content;
		}

		return $this->process_content( $content, $acronyms );
	}

	/**
	 * Check if the current post type is configured for filtering.
	 *
	 * @return bool True if filtering should apply.
	 */
	private function should_filter() {
		$post_types = get_option( 'acronyms_post_types', array( 'post', 'page' ) );

		if ( empty( $post_types ) || ! is_array( $post_types ) ) {
			return false;
		}

		$current_type = get_post_type();

		if ( ! $current_type ) {
			return false;
		}

		return in_array( $current_type, $post_types, true );
	}

	/**
	 * Process content: parse HTML, replace acronyms, serialize back.
	 *
	 * @param string $content  The post content HTML.
	 * @param array  $acronyms Array of acronym objects from the database.
	 * @return string Processed content with <abbr> tags.
	 */
	private function process_content( $content, $acronyms ) {
		$dom = new DOMDocument();

		libxml_use_internal_errors( true );

		$wrapped = '<html><head><meta charset="UTF-8"></head><body>' . $content . '</body></html>';
		$dom->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET );

		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );
		$body  = $dom->getElementsByTagName( 'body' )->item( 0 );

		if ( ! $body ) {
			return $content;
		}

		// Detect manually-placed <abbr> elements and collect their text content.
		$manual_abbrs  = array();
		$existing_abbr = $xpath->query( '//body//abbr' );

		if ( false !== $existing_abbr ) {
			foreach ( $existing_abbr as $abbr_node ) {
				$abbr_text = trim( $abbr_node->textContent );
				if ( '' !== $abbr_text ) {
					$manual_abbrs[] = $abbr_text;
				}
			}
		}

		foreach ( $acronyms as $acr ) {
			// Skip if the author has manually tagged this acronym.
			if ( $this->is_manually_tagged( $acr, $manual_abbrs ) ) {
				continue;
			}

			$pattern    = $this->build_regex( $acr->acronym, (bool) $acr->case_sensitive );
			$text_nodes = $xpath->query( '//body//text()' );

			if ( false === $text_nodes ) {
				continue;
			}

			foreach ( $text_nodes as $text_node ) {
				if ( $this->is_inside_protected_element( $text_node ) ) {
					continue;
				}

				if ( preg_match( $pattern, $text_node->nodeValue ) ) {
					$this->replace_first_in_text_node( $dom, $text_node, $pattern, $acr );
					break; // Only replace the first occurrence per acronym.
				}
			}
		}

		// Serialize back to HTML.
		$output = '';
		foreach ( $body->childNodes as $child ) {
			$output .= $dom->saveHTML( $child );
		}

		return $output;
	}

	/**
	 * Check if an acronym has been manually tagged by the author.
	 *
	 * @param object $acr          Acronym object with acronym and case_sensitive properties.
	 * @param array  $manual_abbrs Array of text content from existing <abbr> elements.
	 * @return bool True if a manual <abbr> for this acronym exists.
	 */
	private function is_manually_tagged( $acr, $manual_abbrs ) {
		foreach ( $manual_abbrs as $abbr_text ) {
			if ( $acr->case_sensitive ) {
				if ( $abbr_text === $acr->acronym ) {
					return true;
				}
			} elseif ( mb_strtolower( $abbr_text ) === mb_strtolower( $acr->acronym ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a node is inside a protected element.
	 *
	 * @param DOMNode $node The node to check.
	 * @return bool True if the node is inside a protected element.
	 */
	private function is_inside_protected_element( $node ) {
		$parent = $node->parentNode;

		while ( $parent instanceof DOMElement ) {
			if ( in_array( strtolower( $parent->tagName ), $this->protected_tags, true ) ) {
				return true;
			}
			$parent = $parent->parentNode;
		}

		return false;
	}

	/**
	 * Build a word-boundary regex for an acronym.
	 *
	 * @param string $acronym        The acronym text.
	 * @param bool   $case_sensitive Whether matching is case-sensitive.
	 * @return string The regex pattern.
	 */
	private function build_regex( $acronym, $case_sensitive ) {
		$escaped = preg_quote( $acronym, '/' );

		$first_char = mb_substr( $acronym, 0, 1 );
		$last_char  = mb_substr( $acronym, -1 );

		// Use \b for word characters, lookbehind/lookahead for non-word characters.
		$left_boundary  = preg_match( '/\w/u', $first_char ) ? '\b' : '(?<=\s|^)';
		$right_boundary = preg_match( '/\w/u', $last_char ) ? '\b' : '(?=\s|$)';

		$flags = $case_sensitive ? '' : 'i';

		return '/' . $left_boundary . $escaped . $right_boundary . '/u' . $flags;
	}

	/**
	 * Replace the first regex match in a text node with an <abbr> element.
	 *
	 * Splits the text node into before-text, <abbr> element, and after-text.
	 *
	 * @param DOMDocument $dom       The document.
	 * @param DOMText     $text_node The text node containing the match.
	 * @param string      $pattern   The regex pattern.
	 * @param object      $acr       The acronym object with title property.
	 */
	private function replace_first_in_text_node( $dom, $text_node, $pattern, $acr ) {
		$text = $text_node->nodeValue;

		if ( ! preg_match( $pattern, $text, $matches, PREG_OFFSET_CAPTURE ) ) {
			return;
		}

		$match_text   = $matches[0][0];
		$match_offset = $matches[0][1];

		$before_text = substr( $text, 0, $match_offset );
		$after_text  = substr( $text, $match_offset + strlen( $match_text ) );

		$parent = $text_node->parentNode;

		if ( '' !== $before_text ) {
			$before_node = $dom->createTextNode( $before_text );
			$parent->insertBefore( $before_node, $text_node );
		}

		$abbr = $dom->createElement( 'abbr' );
		$abbr->setAttribute( 'title', $acr->title );
		$abbr->appendChild( $dom->createTextNode( $match_text ) );
		$parent->insertBefore( $abbr, $text_node );

		if ( '' !== $after_text ) {
			$after_node = $dom->createTextNode( $after_text );
			$parent->insertBefore( $after_node, $text_node );
		}

		$parent->removeChild( $text_node );
	}
}
