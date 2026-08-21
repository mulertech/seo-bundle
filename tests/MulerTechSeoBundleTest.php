<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Tests;

use MulerTech\SeoBundle\Controller\RobotsController;
use MulerTech\SeoBundle\Controller\SitemapController;
use MulerTech\SeoBundle\Model\LlmsSectionProviderInterface;
use MulerTech\SeoBundle\Model\SitemapUrlProviderInterface;
use MulerTech\SeoBundle\MulerTechSeoBundle;
use MulerTech\SeoBundle\Service\LlmsService;
use MulerTech\SeoBundle\Service\MetaTagService;
use MulerTech\SeoBundle\Service\SchemaOrgService;
use MulerTech\SeoBundle\Service\SitemapService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RouteCollection;

final class MulerTechSeoBundleTest extends TestCase
{
    public function testBundleExtendsAbstractBundle(): void
    {
        $bundle = new MulerTechSeoBundle();

        self::assertInstanceOf(AbstractBundle::class, $bundle);
    }

    public function testBundleHasCorrectAlias(): void
    {
        $bundle = new MulerTechSeoBundle();

        self::assertSame('mulertech_seo', $bundle->getContainerExtension()->getAlias());
    }

    public function testLoadExtensionWithDefaultConfig(): void
    {
        $containerBuilder = $this->loadBundleConfig([]);

        self::assertTrue($containerBuilder->has('mulertech_seo.meta_tag'));
        self::assertTrue($containerBuilder->has('mulertech_seo.schema_org'));
        self::assertTrue($containerBuilder->has('mulertech_seo.sitemap'));
        self::assertTrue($containerBuilder->has('mulertech_seo.controller.sitemap'));
        self::assertTrue($containerBuilder->has('mulertech_seo.controller.robots'));
        self::assertTrue($containerBuilder->hasAlias(MetaTagService::class));
        self::assertTrue($containerBuilder->hasAlias(SchemaOrgService::class));
        self::assertTrue($containerBuilder->hasAlias(SitemapService::class));
    }

    public function testLoadExtensionRegistersMetaTagServiceWithDefaults(): void
    {
        $containerBuilder = $this->loadBundleConfig([]);

        $definition = $containerBuilder->getDefinition('mulertech_seo.meta_tag');
        $args = $definition->getArguments();

        self::assertNull($args['$defaultImage']);
        self::assertSame('fr_FR', $args['$defaultLocale']);
    }

    public function testLoadExtensionRegistersMetaTagServiceWithCustomConfig(): void
    {
        $containerBuilder = $this->loadBundleConfig([
            'default_image' => '/images/og.webp',
            'default_locale' => 'en_US',
        ]);

        $definition = $containerBuilder->getDefinition('mulertech_seo.meta_tag');
        $args = $definition->getArguments();

        self::assertSame('/images/og.webp', $args['$defaultImage']);
        self::assertSame('en_US', $args['$defaultLocale']);
    }

    public function testLoadExtensionRegistersSchemaOrgServiceWithDefaults(): void
    {
        $containerBuilder = $this->loadBundleConfig([]);

        $definition = $containerBuilder->getDefinition('mulertech_seo.schema_org');
        $args = $definition->getArguments();

        self::assertSame('', $args['$organizationDescription']);
        self::assertSame('LocalBusiness', $args['$organizationType']);
        self::assertSame('', $args['$priceRange']);
        self::assertSame('', $args['$addressRegion']);
        self::assertSame([], $args['$areasServed']);
        self::assertSame([], $args['$offerNames']);
        self::assertSame('', $args['$searchActionPathTemplate']);
    }

    public function testLoadExtensionRegistersSchemaOrgServiceWithCustomConfig(): void
    {
        $containerBuilder = $this->loadBundleConfig([
            'schema_org' => [
                'organization_type' => 'Organization',
                'organization_description' => 'Test company',
                'price_range' => '€€€',
                'address_region' => 'Normandie',
                'search_action_path_template' => '/search?q={search_term_string}',
                'areas_served' => [
                    ['type' => 'City', 'name' => 'Caen'],
                ],
                'offer_names' => ['Web Development'],
            ],
        ]);

        $definition = $containerBuilder->getDefinition('mulertech_seo.schema_org');
        $args = $definition->getArguments();

        self::assertSame('Organization', $args['$organizationType']);
        self::assertSame('Test company', $args['$organizationDescription']);
        self::assertSame('€€€', $args['$priceRange']);
        self::assertSame('Normandie', $args['$addressRegion']);
        self::assertSame('/search?q={search_term_string}', $args['$searchActionPathTemplate']);
        self::assertCount(1, $args['$areasServed']);
        self::assertSame(['Web Development'], $args['$offerNames']);
    }

    public function testLoadExtensionRegistersRobotsControllerWithDefaults(): void
    {
        $containerBuilder = $this->loadBundleConfig([]);

        $definition = $containerBuilder->getDefinition('mulertech_seo.controller.robots');
        $args = $definition->getArguments();

        self::assertSame(['/admin', '/login'], $args['$disallowPaths']);
    }

    public function testLoadExtensionRegistersRobotsControllerWithCustomPaths(): void
    {
        $containerBuilder = $this->loadBundleConfig([
            'robots' => [
                'disallow_paths' => ['/admin', '/api', '/private'],
            ],
        ]);

        $definition = $containerBuilder->getDefinition('mulertech_seo.controller.robots');
        $args = $definition->getArguments();

        self::assertSame(['/admin', '/api', '/private'], $args['$disallowPaths']);
    }

    public function testLoadExtensionRegistersRobotsControllerWithGroups(): void
    {
        $containerBuilder = $this->loadBundleConfig([
            'robots' => [
                'allow_paths' => ['/admin/help'],
                'groups' => [
                    [
                        'user_agents' => ['HTTrack'],
                        'disallow' => ['/'],
                    ],
                ],
            ],
        ]);

        $args = $containerBuilder->getDefinition('mulertech_seo.controller.robots')->getArguments();

        self::assertSame(['/admin/help'], $args['$allowPaths']);
        self::assertEquals([['user_agents' => ['HTTrack'], 'allow' => [], 'disallow' => ['/']]], $args['$groups']);
    }

    public function testLoadExtensionDefaultsToNoAllowPathAndNoGroup(): void
    {
        $containerBuilder = $this->loadBundleConfig([]);

        $args = $containerBuilder->getDefinition('mulertech_seo.controller.robots')->getArguments();

        self::assertSame([], $args['$allowPaths']);
        self::assertSame([], $args['$groups']);
    }

    public function testLoadExtensionRejectsARobotsGroupWithoutRule(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('at least one allow or disallow path');

        $this->loadBundleConfig([
            'robots' => [
                'groups' => [
                    ['user_agents' => ['HTTrack']],
                ],
            ],
        ]);
    }

    public function testLoadExtensionRejectsRootAllowedAndDisallowedAtOnce(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('both allow_paths and disallow_paths');

        $this->loadBundleConfig([
            'robots' => [
                'allow_paths' => ['/'],
                'disallow_paths' => ['/'],
            ],
        ]);
    }

    public function testLoadExtensionRejectsAGroupAllowingAndDisallowingRoot(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"/" in both allow and disallow');

        $this->loadBundleConfig([
            'robots' => [
                'groups' => [
                    [
                        'user_agents' => ['HTTrack'],
                        'allow' => ['/'],
                        'disallow' => ['/'],
                    ],
                ],
            ],
        ]);
    }

    public function testTwigExtensionSharesTheCanonicalIgnoredParameters(): void
    {
        $containerBuilder = $this->loadBundleConfig([
            'canonical_ignored_parameters' => ['s', 'fbclid'],
        ]);

        $args = $containerBuilder->getDefinition('mulertech_seo.twig_extension')->getArguments();

        self::assertSame(['s', 'fbclid'], $args['$ignoredParameters']);
    }

    public function testLoadExtensionRegistersSchemaOrgLogoAndFounder(): void
    {
        $containerBuilder = $this->loadBundleConfig([
            'schema_org' => [
                'logo' => '/images/logo.png',
                'founder_name' => 'Jane Doe',
            ],
        ]);

        $args = $containerBuilder->getDefinition('mulertech_seo.schema_org')->getArguments();

        self::assertSame('/images/logo.png', $args['$logo']);
        self::assertSame('Jane Doe', $args['$founderName']);
    }

    public function testLoadExtensionRegistersLlmsServicesWithDefaults(): void
    {
        $containerBuilder = $this->loadBundleConfig([]);

        self::assertTrue($containerBuilder->has('mulertech_seo.llms'));
        self::assertTrue($containerBuilder->has('mulertech_seo.controller.llms'));
        self::assertTrue($containerBuilder->hasAlias(LlmsService::class));

        $serviceDef = $containerBuilder->getDefinition('mulertech_seo.llms');
        $serviceArgs = $serviceDef->getArguments();

        self::assertNull($serviceArgs['$title']);
        self::assertSame('', $serviceArgs['$summary']);
        self::assertSame('', $serviceArgs['$notes']);
        self::assertSame([], $serviceArgs['$staticSections']);

        $controllerDef = $containerBuilder->getDefinition('mulertech_seo.controller.llms');
        self::assertTrue($controllerDef->getArgument('$enabled'));
    }

    public function testLoadExtensionRegistersLlmsServicesWithCustomConfig(): void
    {
        $containerBuilder = $this->loadBundleConfig([
            'llms' => [
                'enabled' => false,
                'title' => 'My Site',
                'summary' => 'A summary',
                'notes' => 'Some notes',
                'sections' => [
                    'Docs' => [
                        'priority' => 5,
                        'links' => [
                            ['url' => '/docs', 'title' => 'Docs', 'description' => 'Guides'],
                        ],
                    ],
                ],
            ],
        ]);

        $serviceDef = $containerBuilder->getDefinition('mulertech_seo.llms');
        $serviceArgs = $serviceDef->getArguments();

        self::assertSame('My Site', $serviceArgs['$title']);
        self::assertSame('A summary', $serviceArgs['$summary']);
        self::assertSame('Some notes', $serviceArgs['$notes']);
        self::assertArrayHasKey('Docs', $serviceArgs['$staticSections']);
        self::assertSame(5, $serviceArgs['$staticSections']['Docs']['priority']);

        $controllerDef = $containerBuilder->getDefinition('mulertech_seo.controller.llms');
        self::assertFalse($controllerDef->getArgument('$enabled'));
    }

    public function testLoadExtensionRegistersLlmsSectionProviderAutoconfiguration(): void
    {
        $containerBuilder = $this->loadBundleConfig([]);

        $autoconfigured = $containerBuilder->getAutoconfiguredInstanceof();
        self::assertArrayHasKey(LlmsSectionProviderInterface::class, $autoconfigured);
    }

    public function testLoadExtensionRegistersTwigExtension(): void
    {
        $containerBuilder = $this->loadBundleConfig([]);

        self::assertTrue($containerBuilder->has('mulertech_seo.twig_extension'));

        $definition = $containerBuilder->getDefinition('mulertech_seo.twig_extension');
        self::assertTrue($definition->hasTag('twig.extension'));
    }

    public function testLoadExtensionRegistersSitemapUrlProviderAutoconfiguration(): void
    {
        $containerBuilder = $this->loadBundleConfig([]);

        $autoconfigured = $containerBuilder->getAutoconfiguredInstanceof();
        self::assertArrayHasKey(SitemapUrlProviderInterface::class, $autoconfigured);
    }

    public function testControllerServicesHaveControllerTag(): void
    {
        $containerBuilder = $this->loadBundleConfig([]);

        $sitemapDef = $containerBuilder->getDefinition('mulertech_seo.controller.sitemap');
        self::assertTrue($sitemapDef->hasTag('controller.service_arguments'));
        self::assertSame(SitemapController::class, $sitemapDef->getClass());

        $robotsDef = $containerBuilder->getDefinition('mulertech_seo.controller.robots');
        self::assertTrue($robotsDef->hasTag('controller.service_arguments'));
        self::assertSame(RobotsController::class, $robotsDef->getClass());
    }

    public function testLoadRoutesImportsRoutesYaml(): void
    {
        $bundle = new MulerTechSeoBundle();
        $routeCollection = new RouteCollection();
        $fileLocator = new FileLocator();
        $phpLoader = new PhpFileLoader($fileLocator);
        $yamlLoader = new YamlFileLoader($fileLocator);
        $resolver = new LoaderResolver([$phpLoader, $yamlLoader]);
        $phpLoader->setResolver($resolver);
        $routes = new RoutingConfigurator($routeCollection, $phpLoader, __DIR__, 'test');

        $bundle->loadRoutes($routes);

        self::assertNotNull($routeCollection->get('mulertech_seo_sitemap'));
        self::assertNotNull($routeCollection->get('mulertech_seo_robots'));
        self::assertNotNull($routeCollection->get('mulertech_seo_llms'));
        self::assertSame('/sitemap.xml', $routeCollection->get('mulertech_seo_sitemap')->getPath());
        self::assertSame('/robots.txt', $routeCollection->get('mulertech_seo_robots')->getPath());
        self::assertSame('/llms.txt', $routeCollection->get('mulertech_seo_llms')->getPath());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function loadBundleConfig(array $config): ContainerBuilder
    {
        $bundle = new MulerTechSeoBundle();
        $extension = $bundle->getContainerExtension();
        $containerBuilder = new ContainerBuilder();

        $containerBuilder->setParameter('kernel.environment', 'test');
        $containerBuilder->setParameter('kernel.build_dir', sys_get_temp_dir());
        $containerBuilder->setParameter('kernel.debug', true);

        $extension->load([$config], $containerBuilder);

        return $containerBuilder;
    }
}
