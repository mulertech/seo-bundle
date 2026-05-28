<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Tests\Controller;

use MulerTech\SeoBundle\Controller\LlmsController;
use MulerTech\SeoBundle\Model\SeoCompanyInfoProviderInterface;
use MulerTech\SeoBundle\Service\LlmsService;
use PHPUnit\Framework\TestCase;

final class LlmsControllerTest extends TestCase
{
    public function testEnabledReturnsMarkdown(): void
    {
        $controller = new LlmsController($this->llmsService(), true);

        $response = $controller();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertStringContainsString('# Acme Corp', $response->getContent());
    }

    public function testDisabledReturnsNotFound(): void
    {
        $controller = new LlmsController($this->llmsService(), false);

        $response = $controller();

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    private function llmsService(): LlmsService
    {
        $companyInfoProvider = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfoProvider->method('getName')->willReturn('Acme Corp');

        return new LlmsService($companyInfoProvider, null, '', '', [], []);
    }
}
