<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Tests\Model;

use MulerTech\SeoBundle\Model\SeoFieldsTrait;
use PHPUnit\Framework\TestCase;

final class SeoFieldsTraitTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $entity = new class {
            use SeoFieldsTrait;
        };

        self::assertNull($entity->getMetaDescription());
        self::assertNull($entity->getMetaKeywords());

        $entity->setMetaDescription('Test description');
        $entity->setMetaKeywords('php, symfony');

        self::assertSame('Test description', $entity->getMetaDescription());
        self::assertSame('php, symfony', $entity->getMetaKeywords());
    }

    public function testFluentSetters(): void
    {
        $entity = new class {
            use SeoFieldsTrait;
        };

        $result = $entity->setMetaDescription('desc')->setMetaKeywords('kw');

        self::assertSame($entity, $result);
    }

    public function testNullableValues(): void
    {
        $entity = new class {
            use SeoFieldsTrait;
        };

        $entity->setMetaDescription('desc');
        $entity->setMetaDescription(null);

        self::assertNull($entity->getMetaDescription());
    }
}
