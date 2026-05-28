<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Tests\Service;

use MulerTech\SeoBundle\Model\LlmsLink;
use MulerTech\SeoBundle\Model\LlmsSection;
use MulerTech\SeoBundle\Model\LlmsSectionProviderInterface;
use MulerTech\SeoBundle\Model\SeoCompanyInfoProviderInterface;
use MulerTech\SeoBundle\Service\LlmsService;
use PHPUnit\Framework\TestCase;

final class LlmsServiceTest extends TestCase
{
    public function testTitleFallsBackToCompanyName(): void
    {
        $service = new LlmsService($this->companyInfoProvider(), null, '', '', [], []);

        self::assertStringStartsWith("# Acme Corp\n", $service->generate());
    }

    public function testCustomTitleOverridesCompanyName(): void
    {
        $service = new LlmsService($this->companyInfoProvider(), 'My Site', '', '', [], []);

        self::assertStringStartsWith("# My Site\n", $service->generate());
    }

    public function testSummaryRenderedAsBlockquote(): void
    {
        $service = new LlmsService($this->companyInfoProvider(), null, 'We build apps.', '', [], []);

        self::assertStringContainsString("\n> We build apps.\n", $service->generate());
    }

    public function testNotesRendered(): void
    {
        $service = new LlmsService($this->companyInfoProvider(), null, '', 'Some context.', [], []);

        self::assertStringContainsString("\nSome context.\n", $service->generate());
    }

    public function testStaticSectionsRendered(): void
    {
        $service = new LlmsService($this->companyInfoProvider(), null, '', '', [
            'Documentation' => [
                'priority' => 0,
                'links' => [
                    ['url' => '/docs', 'title' => 'Docs', 'description' => 'Guides'],
                    ['url' => '/api', 'title' => 'API', 'description' => ''],
                ],
            ],
        ], []);

        $content = $service->generate();

        self::assertStringContainsString("## Documentation\n", $content);
        self::assertStringContainsString('- [Docs](/docs): Guides', $content);
        self::assertStringContainsString('- [API](/api)', $content);
        self::assertStringNotContainsString('- [API](/api):', $content);
    }

    public function testSectionsOrderedByPriorityDescending(): void
    {
        $provider = new class implements LlmsSectionProviderInterface {
            public function getSections(): iterable
            {
                yield new LlmsSection('Middle', [new LlmsLink('/m', 'M')], 10);
            }
        };

        $service = new LlmsService($this->companyInfoProvider(), null, '', '', [
            'Top' => ['priority' => 20, 'links' => [['url' => '/t', 'title' => 'T', 'description' => '']]],
            'Bottom' => ['priority' => 0, 'links' => [['url' => '/b', 'title' => 'B', 'description' => '']]],
        ], [$provider]);

        $content = $service->generate();

        self::assertSame(
            ['## Top', '## Middle', '## Bottom'],
            array_values(array_filter(
                explode("\n", $content),
                static fn (string $line): bool => str_starts_with($line, '## '),
            )),
        );
    }

    public function testEqualPriorityKeepsConfigBeforeProviders(): void
    {
        $provider = new class implements LlmsSectionProviderInterface {
            public function getSections(): iterable
            {
                yield new LlmsSection('FromProvider', [new LlmsLink('/p', 'P')]);
            }
        };

        $service = new LlmsService($this->companyInfoProvider(), null, '', '', [
            'FromConfig' => ['priority' => 0, 'links' => [['url' => '/c', 'title' => 'C', 'description' => '']]],
        ], [$provider]);

        $content = $service->generate();

        self::assertLessThan(strpos($content, '## FromProvider'), strpos($content, '## FromConfig'));
    }

    public function testDynamicProviderSectionsAppended(): void
    {
        $provider = new class implements LlmsSectionProviderInterface {
            public function getSections(): iterable
            {
                yield new LlmsSection('Blog', [new LlmsLink('/blog/post', 'Post', 'A post')]);
            }
        };

        $service = new LlmsService($this->companyInfoProvider(), null, '', '', [], [$provider]);

        $content = $service->generate();

        self::assertStringContainsString("## Blog\n", $content);
        self::assertStringContainsString('- [Post](/blog/post): A post', $content);
    }

    private function companyInfoProvider(): SeoCompanyInfoProviderInterface
    {
        $provider = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $provider->method('getName')->willReturn('Acme Corp');

        return $provider;
    }
}
