<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Content\ContentImporter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Boots the kernel and imports the real content/ directory into the test database.
 * dama/doctrine-test-bundle rolls the import back after every test.
 */
abstract class ContentWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $importer = static::getContainer()->get(ContentImporter::class);
        $result = $importer->import(self::contentDir());
        self::assertTrue($result->isSuccessful(), 'content/ must import cleanly before the web tests can run');
    }

    protected static function contentDir(): string
    {
        return static::getContainer()->getParameter('kernel.project_dir') . '/content';
    }
}
