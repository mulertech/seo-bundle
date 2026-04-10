<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle;

use MulerTech\SeoBundle\Controller\RobotsController;
use MulerTech\SeoBundle\Controller\SitemapController;
use MulerTech\SeoBundle\Model\SitemapUrlProviderInterface;
use MulerTech\SeoBundle\Service\MetaTagService;
use MulerTech\SeoBundle\Service\SchemaOrgService;
use MulerTech\SeoBundle\Service\SitemapService;
use MulerTech\SeoBundle\Twig\SeoExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extension\AbstractExtension;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

final class MulerTechSeoBundle extends AbstractBundle
{
    protected string $extensionAlias = 'mulertech_seo';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('default_image')
                    ->defaultNull()
                    ->info('Default OG/Twitter image URL (absolute or relative to base URL)')
                ->end()
                ->scalarNode('default_locale')
                    ->defaultValue('fr_FR')
                    ->info('Default og:locale value')
                ->end()
                ->arrayNode('schema_org')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('organization_type')
                            ->defaultValue('LocalBusiness')
                        ->end()
                        ->scalarNode('organization_description')
                            ->defaultValue('')
                        ->end()
                        ->scalarNode('price_range')
                            ->defaultValue('')
                        ->end()
                        ->scalarNode('address_region')
                            ->defaultValue('')
                        ->end()
                        ->scalarNode('search_action_path_template')
                            ->defaultValue('')
                            ->info('Path appended to site URL for SearchAction (e.g. /blog?q={search_term_string})')
                        ->end()
                        ->arrayNode('areas_served')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('type')->isRequired()->end()
                                    ->scalarNode('name')->isRequired()->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('offer_names')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('robots')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('disallow_paths')
                            ->scalarPrototype()->end()
                            ->defaultValue(['/admin', '/login'])
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(SitemapUrlProviderInterface::class)
            ->addTag('mulertech_seo.sitemap_url_provider');

        /** @var array<string, mixed> $schemaOrg */
        $schemaOrg = $config['schema_org'];

        /** @var array<string, mixed> $robots */
        $robots = $config['robots'];

        $container->services()
            ->set('mulertech_seo.meta_tag', MetaTagService::class)
            ->args([
                '$requestStack' => new Reference('request_stack'),
                '$companyInfoProvider' => new Reference(Model\SeoCompanyInfoProviderInterface::class),
                '$defaultImage' => $config['default_image'],
                '$defaultLocale' => $config['default_locale'],
            ]);

        $container->services()
            ->alias(MetaTagService::class, 'mulertech_seo.meta_tag');

        $container->services()
            ->set('mulertech_seo.schema_org', SchemaOrgService::class)
            ->args([
                '$companyInfoProvider' => new Reference(Model\SeoCompanyInfoProviderInterface::class),
                '$requestStack' => new Reference('request_stack'),
                '$organizationDescription' => $schemaOrg['organization_description'],
                '$organizationType' => $schemaOrg['organization_type'],
                '$priceRange' => $schemaOrg['price_range'],
                '$addressRegion' => $schemaOrg['address_region'],
                '$areasServed' => $schemaOrg['areas_served'],
                '$offerNames' => $schemaOrg['offer_names'],
                '$searchActionPathTemplate' => $schemaOrg['search_action_path_template'],
            ]);

        $container->services()
            ->alias(SchemaOrgService::class, 'mulertech_seo.schema_org');

        $container->services()
            ->set('mulertech_seo.sitemap', SitemapService::class)
            ->args([
                '$urlProviders' => tagged_iterator('mulertech_seo.sitemap_url_provider'),
            ]);

        $container->services()
            ->alias(SitemapService::class, 'mulertech_seo.sitemap');

        $container->services()
            ->set('mulertech_seo.controller.sitemap', SitemapController::class)
            ->args([
                '$sitemapService' => new Reference('mulertech_seo.sitemap'),
            ])
            ->tag('controller.service_arguments');

        $container->services()
            ->set('mulertech_seo.controller.robots', RobotsController::class)
            ->args([
                '$urlGenerator' => new Reference('router'),
                '$environment' => '%kernel.environment%',
                '$disallowPaths' => $robots['disallow_paths'],
            ])
            ->tag('controller.service_arguments');

        if (class_exists(AbstractExtension::class)) {
            $container->services()
                ->set('mulertech_seo.twig_extension', SeoExtension::class)
                ->args([
                    '$schemaOrgService' => new Reference('mulertech_seo.schema_org'),
                    '$requestStack' => new Reference('request_stack'),
                ])
                ->tag('twig.extension');
        }
    }

    public function loadRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../config/routes.yaml');
    }
}
