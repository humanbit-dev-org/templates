<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
	/**
	 * The application boots and answers the health check route.
	 */
	public function test_the_application_returns_a_successful_response(): void
	{
		$response = $this->get("/up");

		$response->assertStatus(200);
	}
}
