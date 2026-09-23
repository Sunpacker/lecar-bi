<?php

declare(strict_types=1);

namespace NotificationService\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class DomainArchitectureTest extends TestCase
{
    public function test_domain_layer_does_not_depend_on_laravel_or_analytics_namespaces(): void
    {
        $domainDir = dirname(__DIR__, 2).'/app/Notification/Domain';

        if (! is_dir($domainDir)) {
            $this->markTestSkipped('Domain directory does not exist yet');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($domainDir)
        );

        $violations = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());

            // Check for forbidden App\ (analytics) namespace
            if (preg_match('/use\s+App\\\\/i', $content) || preg_match('/\\\\App\\\\/i', $content)) {
                $violations[] = $file->getFilename().' imports or uses analytics App\\ namespace';
            }

            // Check for forbidden Illuminate\ (Laravel framework) namespace
            if (preg_match('/use\s+Illuminate\\\\/i', $content) || preg_match('/\\\\Illuminate\\\\/i', $content)) {
                $violations[] = $file->getFilename().' imports or uses Laravel Illuminate\\ namespace';
            }
        }

        $this->assertEmpty(
            $violations,
            "Domain architecture violations found:\n".implode("\n", $violations)
        );
    }
}
