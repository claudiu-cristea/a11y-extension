<?php

declare(strict_types=1);

namespace Behat\A11yExtension\ServiceContainer;

use Behat\A11yExtension\A11yInitializer;
use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class A11yExtension implements Extension
{
    /**
     * {@inheritdoc}
     */
    public function getConfigKey(): string
    {
        return 'a11y';
    }

    /**
     * {@inheritdoc}
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        $builder
            ->children()
                ->scalarNode('axe_script_src')
                    ->isRequired()->end()
                ->arrayNode('standard_tags')
                    ->useAttributeAsKey('key')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->variablePrototype()
                    ->end()->end()
                ->scalarNode('reports_dir')->end()
            ->end();
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(ExtensionManager $extensionManager)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function load(ContainerBuilder $container, array $config)
    {
        // Track our configuration on the container.
        $container->setParameter('a11y.parameters', $config);
        // Define the context initializer.
        $definition = (new Definition(A11yInitializer::class, ['%a11y.parameters%']))
            ->addTag(ContextExtension::INITIALIZER_TAG, ['priority' => 0]);
        $container->setDefinition('a11y_extension.context_initializer', $definition);
    }
}
