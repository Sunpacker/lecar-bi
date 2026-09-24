<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ArchitectureTest extends TestCase
{
    private const FORBIDDEN_NAMESPACE_PREFIXES = [
        'Illuminate\\',
        'Laravel\\',
    ];

    private const FORBIDDEN_LAYER_SEGMENTS = [
        '\\Application\\',
        '\\Infrastructure\\',
        '\\Presentation\\',
    ];

    private const FORBIDDEN_HELPERS = [
        'app',
        'auth',
        'cache',
        'config',
        'dispatch',
        'event',
        'logger',
        'now',
        'redirect',
        'request',
        'response',
        'session',
        'validator',
    ];

    #[Test]
    public function domain_layers_do_not_depend_on_framework_or_infrastructure(): void
    {
        $domainDirectories = glob(dirname(__DIR__, 2).'/app/Modules/*/Domain', GLOB_ONLYDIR);
        $sharedDomainDirectory = dirname(__DIR__, 2).'/app/Shared/Domain';

        self::assertIsArray($domainDirectories);
        self::assertCount(9, $domainDirectories, 'Every bounded context must expose a Domain layer.');
        self::assertDirectoryExists($sharedDomainDirectory);

        $domainDirectories[] = $sharedDomainDirectory;

        foreach ($this->phpFiles($domainDirectories) as $phpFile) {
            $this->assertDomainFileHasNoForbiddenDependencies($phpFile);
        }
    }

    #[Test]
    public function dependency_rule_detects_forbidden_namespaces(): void
    {
        $php = '<?php namespace App\\Modules\\SalesAnalytics\\Domain; use Illuminate\\Database\\Eloquent\\Model; use App\\Modules\\InventoryAnalytics\\Domain\\Stock; use App\\Providers\\AppServiceProvider; config("app.name");';

        self::assertSame(
            [
                'Illuminate\\Database\\Eloquent\\Model',
                'App\\Modules\\InventoryAnalytics\\Domain\\Stock',
                'App\\Providers\\AppServiceProvider',
                'helper:config',
            ],
            $this->architectureViolations($php, 'SalesAnalytics'),
        );
    }

    #[Test]
    public function shared_domain_cannot_depend_on_a_bounded_context(): void
    {
        $php = '<?php namespace App\\Shared\\Domain; use App\\Modules\\Workspace\\Domain\\Workspace;';

        self::assertSame(
            ['App\\Modules\\Workspace\\Domain\\Workspace'],
            $this->architectureViolations($php, 'Shared'),
        );
    }

    /**
     * @param  list<string>  $domainDirectories
     * @return list<SplFileInfo>
     */
    private function phpFiles(array $domainDirectories): array
    {
        $phpFiles = [];

        foreach ($domainDirectories as $domainDirectory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($domainDirectory));

            foreach ($files as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $phpFiles[] = $file;
                }
            }
        }

        return $phpFiles;
    }

    private function assertDomainFileHasNoForbiddenDependencies(SplFileInfo $phpFile): void
    {
        $contents = file_get_contents($phpFile->getPathname());
        $contextName = $this->contextName($phpFile);

        self::assertNotFalse($contents, "Unable to read {$phpFile->getPathname()}.");
        self::assertSame(
            [],
            $this->architectureViolations($contents, $contextName),
            "Domain file {$phpFile->getPathname()} has forbidden dependencies.",
        );
    }

    /** @return list<string> */
    private function architectureViolations(string $php, string $contextName): array
    {
        $tokens = token_get_all($php);

        return array_merge(
            $this->forbiddenNamespaceReferences($tokens, $contextName),
            $this->forbiddenHelperCalls($tokens),
        );
    }

    /**
     * @param  array<int, array{int, string, int}|string>  $tokens
     * @return list<string>
     */
    private function forbiddenNamespaceReferences(array $tokens, string $contextName): array
    {
        $references = [];

        foreach ($tokens as $token) {
            if (! is_array($token) || ! $this->isQualifiedNameToken($token[0])) {
                continue;
            }

            $reference = ltrim($token[1], '\\');

            if ($this->isForbiddenReference($reference, $contextName)) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    private function isQualifiedNameToken(int $tokenId): bool
    {
        return in_array($tokenId, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true);
    }

    private function isForbiddenReference(string $reference, string $contextName): bool
    {
        foreach (self::FORBIDDEN_NAMESPACE_PREFIXES as $prefix) {
            if (str_starts_with($reference, $prefix)) {
                return true;
            }
        }

        foreach (self::FORBIDDEN_LAYER_SEGMENTS as $segment) {
            if (str_contains($reference, $segment)) {
                return true;
            }
        }

        if (str_starts_with($reference, 'App\\Modules\\')) {
            if ($contextName === 'Shared') {
                return true;
            }

            $allowedNamespace = "App\\Modules\\{$contextName}\\Domain";

            return $reference !== $allowedNamespace && ! str_starts_with($reference, $allowedNamespace.'\\');
        }

        if (! str_starts_with($reference, 'App\\')) {
            return false;
        }

        $sharedDomainNamespace = 'App\\Shared\\Domain';

        return $reference !== $sharedDomainNamespace && ! str_starts_with($reference, $sharedDomainNamespace.'\\');
    }

    /**
     * @param  array<int, array{int, string, int}|string>  $tokens
     * @return list<string>
     */
    private function forbiddenHelperCalls(array $tokens): array
    {
        $helperCalls = [];

        foreach ($tokens as $index => $token) {
            if (! is_array($token) || $token[0] !== T_STRING || ! in_array($token[1], self::FORBIDDEN_HELPERS, true)) {
                continue;
            }

            if ($this->nextSignificantToken($tokens, $index) !== '(') {
                continue;
            }

            if ($this->isMemberCall($this->previousSignificantToken($tokens, $index))) {
                continue;
            }

            $helperCalls[] = "helper:{$token[1]}";
        }

        return $helperCalls;
    }

    /** @param array<int, array{int, string, int}|string> $tokens */
    private function nextSignificantToken(array $tokens, int $index): array|string|false
    {
        return $this->significantToken($tokens, $index + 1, 1);
    }

    /** @param array<int, array{int, string, int}|string> $tokens */
    private function previousSignificantToken(array $tokens, int $index): array|string|false
    {
        return $this->significantToken($tokens, $index - 1, -1);
    }

    /** @param array<int, array{int, string, int}|string> $tokens */
    private function significantToken(array $tokens, int $index, int $direction): array|string|false
    {
        while (array_key_exists($index, $tokens)) {
            $token = $tokens[$index];

            if (! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $token;
            }

            $index += $direction;
        }

        return false;
    }

    private function isMemberCall(array|string|false $token): bool
    {
        if (! is_array($token)) {
            return false;
        }

        return in_array($token[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true);
    }

    private function contextName(SplFileInfo $phpFile): string
    {
        $path = str_replace('\\', '/', $phpFile->getPathname());

        if (str_contains($path, '/Shared/Domain/')) {
            return 'Shared';
        }

        $matched = preg_match('~/Modules/([^/]+)/Domain(?:/|$)~', $path, $matches);

        self::assertSame(1, $matched, "Unable to resolve bounded context for {$phpFile->getPathname()}.");

        return $matches[1];
    }
}
