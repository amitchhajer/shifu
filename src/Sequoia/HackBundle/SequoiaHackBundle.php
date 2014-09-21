<?php

namespace Sequoia\HackBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SequoiaHackBundle extends Bundle
{
    /**
     * Build
     *
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $extension = $container->getExtension('security');
        //$extension->addSecurityListenerFactory(new PractoFactory());
    }
}
