<?php

namespace Tests\Unit;

use App\Support\SafeUrl;
use PHPUnit\Framework\TestCase;

class SafeUrlTest extends TestCase
{
    public function test_image_urls_allow_https_and_local_uploads_but_reject_traversal(): void
    {
        $this->assertTrue(SafeUrl::image('/uploads/products/item.webp'));
        $this->assertTrue(SafeUrl::image('https://cdn.example.com/item.webp'));
        $this->assertFalse(SafeUrl::image('/uploads/%2e%2e/.env'));
        $this->assertFalse(SafeUrl::image('/uploads/..%5csecret.jpg'));
        $this->assertFalse(SafeUrl::image('http://cdn.example.com/item.webp'));
        $this->assertFalse(SafeUrl::image('javascript:alert(1)'));
    }
}
