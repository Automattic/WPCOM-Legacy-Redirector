<?php

namespace Automattic\LegacyRedirector\Tests;

use Automattic\LegacyRedirector\Post_Type;

class PostTypeTest extends TestCase {
	public function test_post_type_is_registered() {
		$this->assertTrue( post_type_exists( Post_Type::POST_TYPE ) );
	}
}