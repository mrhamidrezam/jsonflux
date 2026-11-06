<?php

declare(strict_types=1);

// Zero-dependency test runner
spl_autoload_register(function ($class) {
    $prefix = 'JsonFlux\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) === 0) {
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

class Assert
{
    public static int $assertions = 0;

    public static function true(bool $cond, string $msg = 'Expected true'): void
    {
        self::$assertions++;
        if (!$cond) {
            throw new \RuntimeException($msg);
        }
    }

    public static function false(bool $cond, string $msg = 'Expected false'): void
    {
        self::$assertions++;
        if ($cond) {
            throw new \RuntimeException($msg);
        }
    }

    public static function same(mixed $expected, mixed $actual, string $msg = ''): void
    {
        self::$assertions++;
        if ($expected !== $actual) {
            $expectedStr = var_export($expected, true);
            $actualStr = var_export($actual, true);
            throw new \RuntimeException($msg ?: "Expected identical {$expectedStr}, got {$actualStr}");
        }
    }

    public static function equals(mixed $expected, mixed $actual, string $msg = ''): void
    {
        self::$assertions++;
        if ($expected != $actual) {
            $expectedStr = var_export($expected, true);
            $actualStr = var_export($actual, true);
            throw new \RuntimeException($msg ?: "Expected equal {$expectedStr}, got {$actualStr}");
        }
    }

    public static function contains(string $needle, string $haystack, string $msg = ''): void
    {
        self::$assertions++;
        if (!str_contains($haystack, $needle)) {
            throw new \RuntimeException($msg ?: "Expected '{$haystack}' to contain '{$needle}'");
        }
    }

    public static function throws(callable $fn, string $expectedClass = \Throwable::class): void
    {
        self::$assertions++;
        try {
            $fn();
        } catch (\Throwable $e) {
            if ($e instanceof $expectedClass) {
                return;
            }
            throw new \RuntimeException("Expected exception {$expectedClass}, got " . get_class($e));
        }
        throw new \RuntimeException("Expected exception {$expectedClass} was not thrown");
    }
}

$testFiles = glob(__DIR__ . '/*Test.php');
sort($testFiles);

echo "\033[1;36m=== JsonFlux Test Suite ===\033[0m\n";
echo "Found " . count($testFiles) . " test suites.\n\n";

$startTotal = microtime(true);
$passedSuites = 0;
$failedSuites = 0;
$totalTests = 0;

foreach ($testFiles as $file) {
    require_once $file;
    $className = basename($file, '.php');

    if (!class_exists($className)) {
        continue;
    }

    $reflector = new ReflectionClass($className);
    $methods = array_filter(
        $reflector->getMethods(ReflectionMethod::IS_PUBLIC),
        fn($m) => str_starts_with($m->getName(), 'test')
    );

    echo "\033[1;33m► Running {$className}\033[0m\n";
    $instance = new $className();

    foreach ($methods as $method) {
        $testName = $method->getName();
        $totalTests++;
        try {
            $instance->{$testName}();
            echo "  \033[32m✔\033[0m {$testName}\n";
        } catch (\Throwable $e) {
            echo "  \033[31m✖ {$testName}: " . $e->getMessage() . "\033[0m\n";
            echo "    in " . $e->getFile() . ':' . $e->getLine() . "\n";
            $failedSuites++;
            continue 2;
        }
    }

    $passedSuites++;
}

$elapsed = round((microtime(true) - $startTotal) * 1000, 2);

echo "\n--------------------------------------------------\n";
if ($failedSuites === 0) {
    echo "\033[1;32mALL TESTS PASSED!\033[0m ({$totalTests} tests, " . Assert::$assertions . " assertions in {$elapsed}ms)\n";
    exit(0);
} else {
    echo "\033[1;31mSOME TESTS FAILED!\033[0m ({$failedSuites} failed suites)\n";
    exit(1);
}
