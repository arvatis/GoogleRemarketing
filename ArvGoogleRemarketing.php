<?php

namespace ArvGoogleRemarketing;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Shopware-Plugin ArvGoogleRemarketing.
 */
class ArvGoogleRemarketing extends Plugin
{
    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        $container->setParameter('arv_google_remarketing.plugin_dir', $this->getPath());
        parent::build($container);
    }

    public function update(UpdateContext $context)
    {
        $updateFile = __DIR__ . '/ArvGoogleRemarketing.zip';
        if (file_exists($updateFile)) {
            unlink($updateFile);
        }

        parent::update($context);
    }
}
