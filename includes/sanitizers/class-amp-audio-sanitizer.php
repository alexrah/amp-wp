<?php
/**
 * Class AMP_Audio_Sanitizer
 *
 * @package AMP
 */

/**
 * Class AMP_Audio_Sanitizer
 *
 * Converts <audio> tags to <amp-audio>
 */
class AMP_Audio_Sanitizer extends AMP_Base_Sanitizer {

	/**
	 * Tag.
	 *
	 * @var string HTML audio tag to identify and replace with AMP version.
	 * @since 0.2
	 */
	public static $tag = 'audio';

	/**
	 * Get mapping of HTML selectors to the AMP component selectors which they may be converted into.
	 *
	 * @return array Mapping.
	 */
	public function get_selector_conversion_mapping() {
		return array(
			'audio' => array( 'amp-audio' ),
		);
	}

	/**
	 * Sanitize the <audio> elements from the HTML contained in this instance's DOMDocument.
	 *
	 * @since 0.2
	 */
	public function sanitize() {
		$nodes     = $this->dom->getElementsByTagName( self::$tag );
		$num_nodes = $nodes->length;
		if ( 0 === $num_nodes ) {
			return;
		}

		for ( $i = $num_nodes - 1; $i >= 0; $i-- ) {
			$node = $nodes->item( $i );

			// Skip element if already inside of an AMP element as a noscript fallback.
			if ( 'noscript' === $node->parentNode->nodeName && $node->parentNode->parentNode && 'amp-' === substr( $node->parentNode->parentNode->nodeName, 0, 4 ) ) {
				continue;
			}

			$old_attributes = AMP_DOM_Utils::get_node_attributes_as_assoc_array( $node );

			// For amp-audio, the default width and height are inferred from browser.
			$sources        = array();
			$new_attributes = $this->filter_attributes( $old_attributes );
			if ( ! empty( $new_attributes['src'] ) ) {
				$sources[] = $new_attributes['src'];
			}

			/**
			 * Original node.
			 *
			 * @var DOMElement $old_node
			 */
			$old_node = $node->cloneNode( false );

			// Gather all child nodes and supply empty video dimensions from sources.
			$fallback    = null;
			$child_nodes = array();
			while ( $node->firstChild ) {
				$child_node = $node->removeChild( $node->firstChild );
				if ( $child_node instanceof DOMElement && 'source' === $child_node->nodeName && $child_node->hasAttribute( 'src' ) ) {
					$src = $this->maybe_enforce_https_src( $child_node->getAttribute( 'src' ), true );
					if ( ! $src ) {
						// @todo $this->remove_invalid_child( $child_node ), but this will require refactoring the while loop since it uses firstChild.
						continue; // Skip adding source.
					}
					$sources[] = $src;
					$child_node->setAttribute( 'src', $src );
					$new_attributes = $this->filter_attributes( $new_attributes );
				}

				if ( ! $fallback && $child_node instanceof DOMElement && ! ( 'source' === $child_node->nodeName || 'track' === $child_node->nodeName ) ) {
					$fallback = $child_node;
					$fallback->setAttribute( 'fallback', '' );
				}

				$child_nodes[] = $child_node;
			}

			/*
			 * Add fallback for audio shortcode which is not present by default since wp_mediaelement_fallback()
			 * is not called when wp_audio_shortcode_library is filtered from mediaelement to amp.
			 */
			if ( ! $fallback && ! empty( $sources ) ) {
				$fallback = $this->dom->createElement( 'a' );
				$fallback->setAttribute( 'href', $sources[0] );
				$fallback->setAttribute( 'fallback', '' );
				$fallback->appendChild( $this->dom->createTextNode( $sources[0] ) );
				$child_nodes[] = $fallback;
			}

			/*
			 * Audio in WordPress is responsive with 100% width, so this infers fixed-layout.
			 * In AMP, the amp-audio's default height is inferred from the browser.
			 */
			$new_attributes['width'] = 'auto';

			// @todo Make sure poster and artwork attributes are HTTPS.
			$new_node = AMP_DOM_Utils::create_node( $this->dom, 'amp-audio', $new_attributes );
			foreach ( $child_nodes as $child_node ) {
				$new_node->appendChild( $child_node );
				if ( ! ( $child_node instanceof DOMElement ) || ! $child_node->hasAttribute( 'fallback' ) ) {
					$old_node->appendChild( $child_node->cloneNode( true ) );
				}
			}

			// Make sure the updated src and poster are applied to the original.
			foreach ( array( 'src', 'poster', 'artwork' ) as $attr_name ) {
				if ( $new_node->hasAttribute( $attr_name ) ) {
					$old_node->setAttribute( $attr_name, $new_node->getAttribute( $attr_name ) );
				}
			}

			/*
			 * If the node has at least one valid source, replace the old node with it.
			 * Otherwise, just remove the node.
			 *
			 * @todo Add a fallback handler.
			 * See: https://github.com/ampproject/amphtml/issues/2261
			 */
			if ( empty( $sources ) ) {
				$this->remove_invalid_child( $node );
			} else {
				$noscript = $this->dom->createElement( 'noscript' );
				$new_node->appendChild( $noscript );
				$node->parentNode->replaceChild( $new_node, $node );
				$noscript->appendChild( $old_node );
			}

			$this->did_convert_elements = true;
		}
	}

	/**
	 * "Filter" HTML attributes for <amp-audio> elements.
	 *
	 * @since 0.2
	 *
	 * @param string[] $attributes {
	 *      Attributes.
	 *
	 *      @type string $src Audio URL - Empty if HTTPS required per $this->args['require_https_src']
	 *      @type int $width <audio> attribute - Set to numeric value if px or %
	 *      @type int $height <audio> attribute - Set to numeric value if px or %
	 *      @type string $class <audio> attribute - Pass along if found
	 *      @type bool $loop <audio> attribute - Convert 'false' to empty string ''
	 *      @type bool $muted <audio> attribute - Convert 'false' to empty string ''
	 *      @type bool $autoplay <audio> attribute - Convert 'false' to empty string ''
	 * }
	 * @return array Returns HTML attributes; removes any not specifically declared above from input.
	 */
	private function filter_attributes( $attributes ) {
		$out = array();

		foreach ( $attributes as $name => $value ) {
			switch ( $name ) {
				case 'src':
					$out[ $name ] = $this->maybe_enforce_https_src( $value );
					break;

				case 'width':
				case 'height':
					$out[ $name ] = $this->sanitize_dimension( $value, $name );
					break;

				case 'class':
					$out[ $name ] = $value;
					break;
				case 'loop':
				case 'muted':
				case 'autoplay':
					if ( 'false' !== $value ) {
						$out[ $name ] = '';
					}
					break;

				case 'data-amp-layout':
					$out['layout'] = $value;
					break;

				case 'data-amp-noloading':
					$out['noloading'] = $value;
					break;

				default:
					$out[ $name ] = $value;
			}
		}

		return $out;
	}
}
