<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

#[CoversClass(PathInspector::class)]
final class PathInspectorTest extends TestCase
{
    private const ATTRIBUTE_CLASS = 'Test\\Attributes\\TestAttribute';

    private PathInspector $inspector;

    protected function setUp(): void
    {
        // Define the test attribute class once via eval
        if (!class_exists(self::ATTRIBUTE_CLASS)) {
            eval('
                namespace Test\Attributes;
                use Attribute;

                #[Attribute(Attribute::TARGET_ALL)]
                final class TestAttribute {}
            ');
        }

        $this->inspector = new PathInspector(self::ATTRIBUTE_CLASS);
    }

    #[Test]
    public function it_throws_on_nonexistent_class(): void
    {
        $this->expectException(ValueError::class);

        new PathInspector('This\\Does\\Not\\Exist');
    }

    #[Test]
    public function it_throws_on_non_attribute_class(): void
    {
        $this->expectException(ValueError::class);

        new PathInspector(DateTimeImmutable::class);
    }

    #[Test]
    public function it_returns_empty_array_when_attribute_not_used(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            function demo() {}
            class Demo {}
        PHP;

        self::assertSame([], $this->inspector->extract($code));
    }

    #[Test]
    public function it_detects_direct_attribute_usage(): void
    {
        $code = <<<'PHP'
            <?php
            namespace My\Demo;
            use Test\Attributes\TestAttribute;

            #[TestAttribute]
            function doSomething() {}

            #[TestAttribute]
            class Service {}
        PHP;

        self::assertSame([
            ['function', 'My\\Demo\\doSomething'],
            ['class', 'My\\Demo\\Service'],
        ], $this->inspector->extract($code));
    }

    #[Test]
    public function it_detects_aliased_attribute_usage(): void
    {
        $code = <<<'PHP'
            <?php
            namespace My\Demo;
            use Test\Attributes\TestAttribute as Custom;

            #[Custom]
            class Aliased {}
        PHP;

        self::assertSame([
            ['class', 'My\\Demo\\Aliased'],
        ], $this->inspector->extract($code));
    }

    #[Test]
    public function it_skips_use_function_and_use_const(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Example;
            use function strlen;
            use const PHP_VERSION;
            use Test\Attributes\TestAttribute;

            #[TestAttribute]
            class TestClass {}
        PHP;

        self::assertSame([
            ['class', 'Example\\TestClass'],
        ], $this->inspector->extract($code));
    }

    #[Test]
    public function it_ignores_nested_class_methods(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Nested;
            use Test\Attributes\TestAttribute;

            class Foo {
                #[TestAttribute]
                public function bar() {}
            }
        PHP;

        self::assertSame([
            ['class', 'Nested\\Foo'],
        ], $this->inspector->extract($code));
    }

    #[Test]
    public function it_supports_group_use_and_aliases(): void
    {
        $code = <<<'PHP'
        <?php
        namespace My\Example;
        use Test\Attributes\{TestAttribute as A, TestAttribute};

        #[A]
        class First {}

        #[TestAttribute]
        class Second {}
    PHP;

        self::assertSame([
            ['class', 'My\\Example\\First'],
            ['class', 'My\\Example\\Second'],
        ], $this->inspector->extract($code));
    }

    #[Test]
    public function it_resolves_partial_alias_attribute(): void
    {
        $code = <<<'PHP'
        <?php
        namespace My\Demo;
        use Test\Attributes as Attr;

        #[Attr\TestAttribute]
        class Foo {}

        #[Attr\TestAttribute]
        function bar() {}
    PHP;

        self::assertSame([
            ['class', 'My\\Demo\\Foo'],
            ['function', 'My\\Demo\\bar'],
        ], $this->inspector->extract($code));
    }

    #[Test]
    public function it_matches_fully_qualified_attribute(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Perfect\Match;

        #[Test\Attributes\TestAttribute]
        class FqcnClass {}

        #[Test\Attributes\TestAttribute]
        function fqcnFunction() {}
    PHP;

        self::assertSame([
            ['class', 'Perfect\\Match\\FqcnClass'],
            ['function', 'Perfect\\Match\\fqcnFunction'],
        ], $this->inspector->extract($code));
    }

    #[Test]
    public function it_matches_fully_qualified_attribute_with_leading_backslash(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Perfect\Match;

        #[\Test\Attributes\TestAttribute]
        class FqcnClass {}

        #[\Test\Attributes\TestAttribute]
        function fqcnFunction() {}
    PHP;

        self::assertSame([
            ['class', 'Perfect\\Match\\FqcnClass'],
            ['function', 'Perfect\\Match\\fqcnFunction'],
        ], $this->inspector->extract($code));
    }
}
