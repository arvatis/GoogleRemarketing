<?php

namespace ArvGoogleRemarketing\Tests;

use ArvGoogleRemarketing\ArvGoogleRemarketing as Plugin;
use Shopware\Components\Test\Plugin\TestCase;

class PluginTest extends TestCase
{
    protected static $ensureLoadedPlugins = [
        'ArvGoogleRemarketing' => []
    ];

    public function testCanCreateInstance()
    {
        /** @var Plugin $plugin */
        $plugin = Shopware()->Container()->get('kernel')->getPlugins()['ArvGoogleRemarketing'];

        $this->assertInstanceOf(Plugin::class, $plugin);
    }
}
