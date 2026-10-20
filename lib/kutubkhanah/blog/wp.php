<?php
/**
 * Kutubkhanah blog: text-formatting helpers ported from WordPress 7.1.3
 * (wp-includes/formatting.php and wp-includes/shortcodes.php), so posts imported
 * from WordPress and shortcodes behave exactly as they did there.
 *
 * WordPress is Copyright 2011-2026 by the contributors, released under the
 * GNU General Public License version 2 or later (see WordPress-license.txt in this folder).
 * Kutubkhanah's changes: functions wrapped as static methods, renamed, and the
 * shortcode registry passed in instead of read from a global. GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah\Blog;

class Wp
{
    /**
     * Replaces double line breaks with paragraph elements.
     *
     * A group of regex replaces used to identify text formatted with newlines and
     * replace double line breaks with HTML paragraph tags. The remaining line breaks
     * after conversion become `<br />` tags, unless `$br` is set to '0' or 'false'.
     *
     * @since 0.71
     *
     * @param string $text The text which has to be formatted.
     * @param bool   $br   Optional. If set, this will convert all remaining line breaks
     *                     after paragraphing. Line breaks within `<script>`, `<style>`,
     *                     and `<svg>` tags are not affected. Default true.
     * @return string Text which has been converted into correct paragraph tags.
     */
    public static function autop( $text, $br = true ) {
    	$pre_tags = array();

    	if ( '' === trim( $text ) ) {
    		return '';
    	}

    	// Just to make things a little easier, pad the end.
    	$text = $text . "\n";

    	/*
    	 * Pre tags shouldn't be touched by autop.
    	 * Replace pre tags with placeholders and bring them back after autop.
    	 */
    	if ( str_contains( $text, '<pre' ) ) {
    		$text_parts = explode( '</pre>', $text );
    		$last_part  = array_pop( $text_parts );
    		$text       = '';
    		$i          = 0;

    		foreach ( $text_parts as $text_part ) {
    			$start = strpos( $text_part, '<pre' );

    			// Malformed HTML?
    			if ( false === $start ) {
    				$text .= $text_part;
    				continue;
    			}

    			$name              = "<pre wp-pre-tag-$i></pre>";
    			$pre_tags[ $name ] = substr( $text_part, $start ) . '</pre>';

    			$text .= substr( $text_part, 0, $start ) . $name;
    			++$i;
    		}

    		$text .= $last_part;
    	}
    	// Change multiple <br>'s into two line breaks, which will turn into paragraphs.
    	$text = preg_replace( '|<br\s*/?>\s*<br\s*/?>|', "\n\n", $text );

    	$allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';

    	// Add a double line break above block-level opening tags.
    	$text = preg_replace( '!(<' . $allblocks . '[\s/>])!', "\n\n$1", $text );

    	// Add a double line break below block-level closing tags.
    	$text = preg_replace( '!(</' . $allblocks . '>)!', "$1\n\n", $text );

    	// Add a double line break after hr tags, which are self closing.
    	$text = preg_replace( '!(<hr\s*?/?>)!', "$1\n\n", $text );

    	// Standardize newline characters to "\n".
    	$text = str_replace( array( "\r\n", "\r" ), "\n", $text );

    	// Find newlines in all elements and add placeholders.
    	$text = self::replaceInHtmlTags( $text, array( "\n" => ' <!-- wpnl --> ' ) );

    	// Collapse line breaks before and after <option> elements so they don't get autop'd.
    	if ( str_contains( $text, '<option' ) ) {
    		$text = preg_replace( '|\s*<option|', '<option', $text );
    		$text = preg_replace( '|</option>\s*|', '</option>', $text );
    	}

    	/*
    	 * Collapse line breaks inside <object> elements, before <param> and <embed> elements
    	 * so they don't get autop'd.
    	 */
    	if ( str_contains( $text, '</object>' ) ) {
    		$text = preg_replace( '|(<object[^>]*>)\s*|', '$1', $text );
    		$text = preg_replace( '|\s*</object>|', '</object>', $text );
    		$text = preg_replace( '%\s*(</?(?:param|embed)[^>]*>)\s*%', '$1', $text );
    	}

    	/*
    	 * Collapse line breaks inside <audio> and <video> elements,
    	 * before and after <source> and <track> elements.
    	 */
    	if ( str_contains( $text, '<source' ) || str_contains( $text, '<track' ) ) {
    		$text = preg_replace( '%([<\[](?:audio|video)[^>\]]*[>\]])\s*%', '$1', $text );
    		$text = preg_replace( '%\s*([<\[]/(?:audio|video)[>\]])%', '$1', $text );
    		$text = preg_replace( '%\s*(<(?:source|track)[^>]*>)\s*%', '$1', $text );
    	}

    	// Collapse line breaks before and after <figcaption> elements.
    	if ( str_contains( $text, '<figcaption' ) ) {
    		$text = preg_replace( '|\s*(<figcaption[^>]*>)|', '$1', $text );
    		$text = preg_replace( '|</figcaption>\s*|', '</figcaption>', $text );
    	}

    	// Remove more than two contiguous line breaks.
    	$text = preg_replace( "/\n\n+/", "\n\n", $text );

    	// Split up the contents into an array of strings, separated by double line breaks.
    	$paragraphs = preg_split( '/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY );

    	// Reset $text prior to rebuilding.
    	$text = '';

    	// Rebuild the content as a string, wrapping every bit with a <p>.
    	foreach ( $paragraphs as $paragraph ) {
    		$text .= '<p>' . trim( $paragraph, "\n" ) . "</p>\n";
    	}

    	// Under certain strange conditions it could create a P of entirely whitespace.
    	$text = preg_replace( '|<p>\s*</p>|', '', $text );

    	// Add a closing <p> inside <div>, <address>, or <form> tag if missing.
    	$text = preg_replace( '!<p>([^<]+)</(div|address|form)>!', '<p>$1</p></$2>', $text );

    	// If an opening or closing block element tag is wrapped in a <p>, unwrap it.
    	$text = preg_replace( '!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', '$1', $text );

    	// In some cases <li> may get wrapped in <p>, fix them.
    	$text = preg_replace( '|<p>(<li.+?)</p>|', '$1', $text );

    	// If a <blockquote> is wrapped with a <p>, move it inside the <blockquote>.
    	$text = preg_replace( '!<p><blockquote((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>!i', '<blockquote$1><p>', $text );
    	$text = str_replace( '</blockquote></p>', '</p></blockquote>', $text );

    	// If an opening or closing block element tag is preceded by an opening <p> tag, remove it.
    	$text = preg_replace( '!<p>\s*(</?' . $allblocks . '[^>]*>)!', '$1', $text );

    	// If an opening or closing block element tag is followed by a closing <p> tag, remove it.
    	$text = preg_replace( '!(</?' . $allblocks . '[^>]*>)\s*</p>!', '$1', $text );

    	// Optionally insert line breaks.
    	if ( $br ) {
    		// Replace newlines that shouldn't be touched with a placeholder.
    		$text = preg_replace_callback( '/<(script|style|svg|math).*?<\/\\1>/s', [self::class, 'autopNewlinePreservationHelper'], $text );

    		// Normalize <br>.
    		$text = str_replace( array( '<br>', '<br/>' ), '<br />', $text );

    		// Replace any new line characters that aren't preceded by a <br /> with a <br />.
    		$text = preg_replace( '|(?<!<br />)\s*\n|', "<br />\n", $text );

    		// Replace newline placeholders with newlines.
    		$text = str_replace( '<WPPreserveNewline />', "\n", $text );
    	}

    	// If a <br /> tag is after an opening or closing block tag, remove it.
    	$text = preg_replace( '!(</?' . $allblocks . '[^>]*>)\s*<br />!', '$1', $text );

    	// If a <br /> tag is before a subset of opening or closing block tags, remove it.
    	$text = preg_replace( '!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $text );
    	$text = preg_replace( "|\n</p>$|", '</p>', $text );

    	// Replace placeholder <pre> tags with their original content.
    	if ( ! empty( $pre_tags ) ) {
    		$text = str_replace( array_keys( $pre_tags ), array_values( $pre_tags ), $text );
    	}

    	// Restore newlines in all elements.
    	if ( str_contains( $text, '<!-- wpnl -->' ) ) {
    		$text = str_replace( array( ' <!-- wpnl --> ', '<!-- wpnl -->' ), "\n", $text );
    	}

    	return $text;
    }

    /**
     * Separates HTML elements and comments from the text.
     *
     * @since 4.2.4
     *
     * @param string $input The text which has to be formatted.
     * @return string[] Array of the formatted text.
     */
    public static function htmlSplit( $input ) {
    	return preg_split( self::htmlSplitRegex(), $input, -1, PREG_SPLIT_DELIM_CAPTURE );
    }

    /**
     * Retrieves the regular expression for an HTML element.
     *
     * @since 4.4.0
     *
     * @return string The regular expression.
     */
    public static function htmlSplitRegex() {
    	static $regex;

    	if ( ! isset( $regex ) ) {
    		// phpcs:disable Squiz.Strings.ConcatenationSpacing.PaddingFound -- don't remove regex indentation
    		$comments =
    			'!'             // Start of comment, after the <.
    			. '(?:'         // Unroll the loop: Consume everything until --> is found.
    			.     '-(?!->)' // Dash not followed by end of comment.
    			.     '[^\-]*+' // Consume non-dashes.
    			. ')*+'         // Loop possessively.
    			. '(?:-->)?';   // End of comment. If not found, match all input.

    		$cdata =
    			'!\[CDATA\['    // Start of comment, after the <.
    			. '[^\]]*+'     // Consume non-].
    			. '(?:'         // Unroll the loop: Consume everything until ]]> is found.
    			.     '](?!]>)' // One ] not followed by end of comment.
    			.     '[^\]]*+' // Consume non-].
    			. ')*+'         // Loop possessively.
    			. '(?:]]>)?';   // End of comment. If not found, match all input.

    		$escaped =
    			'(?='             // Is the element escaped?
    			.    '!--'
    			. '|'
    			.    '!\[CDATA\['
    			. ')'
    			. '(?(?=!-)'      // If yes, which type?
    			.     $comments
    			. '|'
    			.     $cdata
    			. ')';

    		$regex =
    			'/('                // Capture the entire match.
    			.     '<'           // Find start of element.
    			.     '(?'          // Conditional expression follows.
    			.         $escaped  // Find end of escaped element.
    			.     '|'           // ...else...
    			.         '[^>]*>?' // Find end of normal element.
    			.     ')'
    			. ')/';
    		// phpcs:enable
    	}

    	return $regex;
    }

    /**
     * Replaces characters or phrases within HTML elements only.
     *
     * @since 4.2.3
     *
     * @param string $haystack      The text which has to be formatted.
     * @param array  $replace_pairs In the form array('from' => 'to', ...).
     * @return string The formatted text.
     */
    public static function replaceInHtmlTags( $haystack, $replace_pairs ) {
    	// Find all elements.
    	$textarr = self::htmlSplit( $haystack );
    	$changed = false;

    	// Optimize when searching for one item.
    	if ( 1 === count( $replace_pairs ) ) {
    		// Extract $needle and $replace.
    		$needle  = array_key_first( $replace_pairs );
    		$replace = $replace_pairs[ $needle ];

    		// Loop through delimiters (elements) only.
    		for ( $i = 1, $c = count( $textarr ); $i < $c; $i += 2 ) {
    			if ( str_contains( $textarr[ $i ], $needle ) ) {
    				$textarr[ $i ] = str_replace( $needle, $replace, $textarr[ $i ] );
    				$changed       = true;
    			}
    		}
    	} else {
    		// Extract all $needles.
    		$needles = array_keys( $replace_pairs );

    		// Loop through delimiters (elements) only.
    		for ( $i = 1, $c = count( $textarr ); $i < $c; $i += 2 ) {
    			foreach ( $needles as $needle ) {
    				if ( str_contains( $textarr[ $i ], $needle ) ) {
    					$textarr[ $i ] = strtr( $textarr[ $i ], $replace_pairs );
    					$changed       = true;
    					// After one strtr() break out of the foreach loop and look at next element.
    					break;
    				}
    			}
    		}
    	}

    	if ( $changed ) {
    		$haystack = implode( $textarr );
    	}

    	return $haystack;
    }

    /**
     * Newline preservation help function for wpautop().
     *
     * @since 3.1.0
     * @access private
     *
     * @param array $matches preg_replace_callback matches array
     * @return string Text with newlines replaced with placeholders.
     */
    public static function autopNewlinePreservationHelper( $matches ) {
    	return str_replace( "\n", '<WPPreserveNewline />', $matches[0] );
    }

    /**
     * Retrieves the shortcode attributes regex.
     *
     * @since 4.4.0
     *
     * @return string The shortcode attribute regular expression.
     */
    public static function shortcodeAttsRegex() {
    	return '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|\'([^\']*)\'(?:\s|$)|(\S+)(?:\s|$)/';
    }

    /**
     * Retrieves all attributes from the shortcodes tag.
     *
     * The attributes list has the attribute name as the key and the value of the
     * attribute as the value in the key/value pair. This allows for easier
     * retrieval of the attributes, since all attributes have to be known.
     *
     * @since 2.5.0
     * @since 6.5.0 The function now always returns an array,
     *              even if the original arguments string cannot be parsed or is empty.
     *
     * @param string $text Shortcode arguments list.
     * @return array Array of attribute values keyed by attribute name.
     *               Returns empty array if there are no attributes
     *               or if the original arguments string cannot be parsed.
     */
    public static function shortcodeParseAtts( $text ) {
    	$atts    = array();
    	$pattern = self::shortcodeAttsRegex();
    	$text    = preg_replace( "/[\x{00a0}\x{200b}]+/u", ' ', $text );
    	if ( preg_match_all( $pattern, $text, $match, PREG_SET_ORDER ) ) {
    		foreach ( $match as $m ) {
    			if ( ! empty( $m[1] ) ) {
    				$atts[ strtolower( $m[1] ) ] = stripcslashes( $m[2] );
    			} elseif ( ! empty( $m[3] ) ) {
    				$atts[ strtolower( $m[3] ) ] = stripcslashes( $m[4] );
    			} elseif ( ! empty( $m[5] ) ) {
    				$atts[ strtolower( $m[5] ) ] = stripcslashes( $m[6] );
    			} elseif ( isset( $m[7] ) && strlen( $m[7] ) ) {
    				$atts[] = stripcslashes( $m[7] );
    			} elseif ( isset( $m[8] ) && strlen( $m[8] ) ) {
    				$atts[] = stripcslashes( $m[8] );
    			} elseif ( isset( $m[9] ) ) {
    				$atts[] = stripcslashes( $m[9] );
    			}
    		}

    		// Reject any unclosed HTML elements.
    		foreach ( $atts as &$value ) {
    			if ( str_contains( $value, '<' ) ) {
    				if ( 1 !== preg_match( '/^[^<]*+(?:<[^>]*+>[^<]*+)*+$/', $value ) ) {
    					$value = '';
    				}
    			}
    		}
    	}

    	return $atts;
    }

    /**
     * Retrieves the shortcode regular expression for searching.
     *
     * The regular expression combines the shortcode tags in the regular expression
     * in a regex class.
     *
     * The regular expression contains 6 different sub matches to help with parsing.
     *
     * 1 - An extra [ to allow for escaping shortcodes with double [[]]
     * 2 - The shortcode name
     * 3 - The shortcode argument list
     * 4 - The self closing /
     * 5 - The content of a shortcode when it wraps some content.
     * 6 - An extra ] to allow for escaping shortcodes with double [[]]
     *
     * @since 2.5.0
     * @since 4.4.0 Added the `$tagnames` parameter.
     *
     * @global array $shortcode_tags
     *
     * @param array $tagnames Optional. List of shortcodes to find. Defaults to all registered shortcodes.
     * @return string The shortcode search regular expression.
     */
    public static function shortcodeRegex( $tagnames = null ) {
    	$tagnames = (array) $tagnames;
    	$tagregexp = implode( '|', array_map( 'preg_quote', $tagnames ) );

    	/*
    	 * WARNING! Do not change this regex without changing do_shortcode_tag() and strip_shortcode_tag().
    	 * Also, see shortcode_unautop() and shortcode.js.
    	 */

    	// phpcs:disable Squiz.Strings.ConcatenationSpacing.PaddingFound -- don't remove regex indentation
    	return '\\['                             // Opening bracket.
    		. '(\\[?)'                           // 1: Optional second opening bracket for escaping shortcodes: [[tag]].
    		. "($tagregexp)"                     // 2: Shortcode name.
    		. '(?![\\w-])'                       // Not followed by word character or hyphen.
    		. '('                                // 3: Unroll the loop: Inside the opening shortcode tag.
    		.     '[^\\]\\/]*'                   // Not a closing bracket or forward slash.
    		.     '(?:'
    		.         '\\/(?!\\])'               // A forward slash not followed by a closing bracket.
    		.         '[^\\]\\/]*'               // Not a closing bracket or forward slash.
    		.     ')*?'
    		. ')'
    		. '(?:'
    		.     '(\\/)'                        // 4: Self closing tag...
    		.     '\\]'                          // ...and closing bracket.
    		. '|'
    		.     '\\]'                          // Closing bracket.
    		.     '(?:'
    		.         '('                        // 5: Unroll the loop: Optionally, anything between the opening and closing shortcode tags.
    		.             '[^\\[]*+'             // Not an opening bracket.
    		.             '(?:'
    		.                 '\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag.
    		.                 '[^\\[]*+'         // Not an opening bracket.
    		.             ')*+'
    		.         ')'
    		.         '\\[\\/\\2\\]'             // Closing shortcode tag.
    		.     ')?'
    		. ')'
    		. '(\\]?)';                          // 6: Optional second closing bracket for escaping shortcodes: [[tag]].
    	// phpcs:enable
    }
}
