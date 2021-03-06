<?php


class AMP_Playbuzz_Sanitizer_Test extends WP_UnitTestCase {

	/**
	 * Data for converter test.
	 *
	 * @return array Data.
	 */
	public function get_data() {
		return array(
			'no_playbuzz_item'                           => array(
				'<h1>Im Not A Playbuzz Embed</h1>',
				'<h1>Im Not A Playbuzz Embed</h1>',
			),

			'playbuzz_item_without_sorce'                => array(
				'<div class="pb_feed"></div>',
				'<div class="pb_feed"></div>',
			),

			'playbuzz_item_with_data_item'               => array(
				'<div class="pb_feed" data-item="226dd4c0-ef13-4fee-850b-7be32bf6d121"></div>',
				'<amp-playbuzz class="pb_feed" data-item="226dd4c0-ef13-4fee-850b-7be32bf6d121" height="500"></amp-playbuzz>',
			),

			'playbuzz_item_with_data_game'               => array(
				'<div class="pb_feed" data-game="https://www.playbuzz.com/jessiemills10/donald-trump-hits-out-at-rachel-maddow-and-accuses-nbc-of-being-fake-news-is-he-right"></div>',
				'<amp-playbuzz class="pb_feed" src="https://www.playbuzz.com/jessiemills10/donald-trump-hits-out-at-rachel-maddow-and-accuses-nbc-of-being-fake-news-is-he-right" height="500"></amp-playbuzz>',
			),

			'playbuzz_item_with_data_game_and_data_item' => array(
				'<div class="pb_feed" data-item="226dd4c0-ef13-4fee-850b-7be32bf6d121" data-game="https://www.playbuzz.com/jessiemills10/donald-trump-hits-out-at-rachel-maddow-and-accuses-nbc-of-being-fake-news-is-he-right"></div>',
				'<amp-playbuzz class="pb_feed" data-item="226dd4c0-ef13-4fee-850b-7be32bf6d121" src="https://www.playbuzz.com/jessiemills10/donald-trump-hits-out-at-rachel-maddow-and-accuses-nbc-of-being-fake-news-is-he-right" height="500"></amp-playbuzz>',
			),

			'playbuzz_item_with_data_game_info'          => array(
				'<div class="pb_feed" data-game-info="true" data-item="226dd4c0-ef13-4fee-850b-7be32bf6d121"></div>',
				'<amp-playbuzz class="pb_feed" data-game-info="true" data-item="226dd4c0-ef13-4fee-850b-7be32bf6d121" height="500"></amp-playbuzz>',
			),

			'playbuzz_item_with_data_shares_info'        => array(
				'<div class="pb_feed" data-shares="true" data-item="226dd4c0-ef13-4fee-850b-7be32bf6d121"></div>',
				'<amp-playbuzz class="pb_feed" data-share-buttons="true" data-item="226dd4c0-ef13-4fee-850b-7be32bf6d121" height="500"></amp-playbuzz>',
			),

			'playbuzz_item_with_data_comments'           => array(
				'<div class="pb_feed" data-comments="true" data-item="226dd4c0-ef13-4fee-850b-7be32bf6d121"></div>',
				'<amp-playbuzz class="pb_feed" data-comments="true" data-item="226dd4c0-ef13-4fee-850b-7be32bf6d121" height="500"></amp-playbuzz>',
			),
		);
	}

	/**
	 * @dataProvider get_data
	 */
	public function test_converter( $source, $expected ) {
		$dom       = AMP_DOM_Utils::get_dom_from_content( $source );
		$sanitizer = new AMP_Playbuzz_Sanitizer( $dom );
		$sanitizer->sanitize();
		$content = AMP_DOM_Utils::get_content_from_dom( $dom );
		$this->assertEquals( $expected, $content );
	}


	public function test_get_scripts__data_item_or_data_game_required() {
		$source   = '<div class="pb_feed"></div>';
		$expected = array();

		$dom       = AMP_DOM_Utils::get_dom_from_content( $source );
		$sanitizer = new AMP_Playbuzz_Sanitizer( $dom );
		$sanitizer->sanitize();

		$whitelist_sanitizer = new AMP_Tag_And_Attribute_Sanitizer( $dom );
		$whitelist_sanitizer->sanitize();

		$scripts = array_merge(
			$sanitizer->get_scripts(),
			$whitelist_sanitizer->get_scripts()
		);
		$this->assertEquals( $expected, $scripts );
	}

	public function test_get_scripts__didnt_convert() {
		$source   = '<h1>Im A Not Playbuzz Embed</h1>';
		$expected = array();

		$dom       = AMP_DOM_Utils::get_dom_from_content( $source );
		$sanitizer = new AMP_Playbuzz_Sanitizer( $dom );
		$sanitizer->sanitize();

		$whitelist_sanitizer = new AMP_Tag_And_Attribute_Sanitizer( $dom );
		$whitelist_sanitizer->sanitize();

		$scripts = array_merge(
			$sanitizer->get_scripts(),
			$whitelist_sanitizer->get_scripts()
		);
		$this->assertEquals( $expected, $scripts );
	}

	/**
	 * Test that get_scripts() did convert.
	 */
	public function test_get_scripts__did_convert() {
		$source   = '<div class="pb_feed" data-item="226dd4c0-ef13-4fee-850b-7be32bf6d121"></div>';
		$expected = array( 'amp-playbuzz' => true );

		$dom       = AMP_DOM_Utils::get_dom_from_content( $source );
		$sanitizer = new AMP_Playbuzz_Sanitizer( $dom );
		$sanitizer->sanitize();
		$whitelist_sanitizer = new AMP_Tag_And_Attribute_Sanitizer( $dom );
		$whitelist_sanitizer->sanitize();

		$scripts = array_merge(
			$sanitizer->get_scripts(),
			$whitelist_sanitizer->get_scripts()
		);
		$this->assertEquals( $expected, $scripts );
	}
}
