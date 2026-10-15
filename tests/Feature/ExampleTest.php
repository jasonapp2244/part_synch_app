<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The site has no home page (`/` is unrouted), so the smoke test is the
     * admin sign-in page every visit to the panel starts from.
     */
    public function test_the_admin_login_page_loads(): void
    {
        $this->get('/login')->assertOk();
    }
}
