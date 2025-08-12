<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Attribute;
use Bakame\Stackwatch\InvalidArgument;
use PhpToken;
use ReflectionClass;
use RuntimeException;
use SplFileInfo;
use ValueError;

use function array_pop;
use function class_exists;
use function count;
use function explode;
use function in_array;
use function ltrim;
use function str_contains;
use function strcasecmp;
use function strrchr;
use function substr;
use function trim;

use const T_AS;
use const T_ATTRIBUTE;
use const T_CLASS;
use const T_CONST;
use const T_ENUM;
use const T_FUNCTION;
use const T_INTERFACE;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAME_RELATIVE;
use const T_NAMESPACE;
use const T_NS_SEPARATOR;
use const T_STRING;
use const T_TRAIT;
use const T_USE;
use const T_WHITESPACE;

/**
 * Scans PHP code and returns top-level functions and classes where the specified attribute is used.
 */
final class PathInspector
{
    public function __construct(public readonly string $attributeClassName)
    {
        class_exists($attributeClassName) || throw new ValueError('Class "'.$attributeClassName.'" does not exist or is not loadable.');
        (0 < count((new ReflectionClass($attributeClassName))->getAttributes(Attribute::class))) || throw new ValueError('Class "'.$attributeClassName.'" is not an attribute.');
    }

    /**
     * Returns a list of top level functions and classes contained in a PHP file.
     *
     * @return list<array{0:string, 1:string}>
     */
    public function inspect(SplFileInfo $path): array
    {
        $path->isFile() || throw new InvalidArgument('The path "'.$path->getRealPath().'" is not a file.');
        $path->isReadable() || throw new InvalidArgument('The file "'.$path->getRealPath().'" is not readable.');
        $code = $path->openFile()->fread($path->getSize());
        false !== $code || throw new RuntimeException('The file '.$path->getRealPath().' is not readable.');

        return $this->extract($code);
    }

    /**
     * Returns a list of top level functions and classes contained in a PHP file.
     *
     * @return list<array{0:string, 1:string}>
     */
    public function extract(string $code): array
    {
        $tokens = PhpToken::tokenize($code);
        $tokenCount = count($tokens);
        if (0 === $tokenCount) {
            return [];
        }

        //we first collect the aliases uses in the file
        /** @var array<string, string> $aliases */
        $aliases = $this->extractAliases($tokens, $tokenCount);

        //We now scan the file in search of the attribute
        //using the aliases to correctly resolve the attribute name
        //if the attribute is not found we skip the file
        if (!$this->containsAttribute($tokens, $aliases, $tokenCount)) {
            return [];
        }

        //We return a list of tuple with the name of the toplevel functions
        //or classes contained in the file
        return $this->extractFunctionAndClassNames($tokens, $tokenCount);
    }

    /**
     * Collect the aliases uses in the file
     * the goal is to only execute file that do contain the expected Attribute
     * and to skip all the other files.
     *
     * @param array<PhpToken> $tokens
     *
     * @return array<string, string>
     */
    private function extractAliases(array $tokens, int $tokenCount): array
    {
        /** @var array<string, string> $aliases */
        $aliases = [];
        $i = 0;
        while ($i < $tokenCount) {
            $token = $tokens[$i];
            if ($token->is(T_WHITESPACE)) {
                $i++;
                continue;
            }

            if ($token->is([T_CLASS, T_TRAIT, T_INTERFACE, T_ENUM])) {
                while ($i < $tokenCount && '{' !== $token->text) {
                    $i++;
                }
                $i++; // Skip '{'
                $depth = 1;
                while ($i < $tokenCount && $depth > 0) {
                    if ('{' === $token->text) {
                        $depth++;
                    } elseif ('}' === $token->text) {
                        $depth--;
                    }
                    $i++;
                }
                continue;
            }

            if ($token->is(T_USE)) {
                $j = $i + 1;
                while ($j < $tokenCount && $tokens[$j]->is(T_WHITESPACE)) {
                    $j++;
                }

                if ($j < $tokenCount && $tokens[$j]->is([T_FUNCTION, T_CONST])) {
                    while ($i < $tokenCount && ';' !== $tokens[$i]->text) {
                        $i++;
                    }
                    $i++;
                    continue;
                }

                // Parse normal class use statement (including group use)
                $fqcn = '';
                $alias = null;
                $groupMode = false;
                $groupPrefix = '';

                $i++;
                while ($i < $tokenCount) {
                    $token = $tokens[$i];

                    if ('{' === $token->text) {
                        $groupMode = true;
                        $groupPrefix = rtrim($fqcn, '\\');
                        $fqcn = '';
                        $i++;
                        continue;
                    }

                    if ('}' === $token->text) {
                        if ('' !== $fqcn) {
                            $short = $alias ?? (false !== strrchr($fqcn, '\\') ? substr(strrchr($fqcn, '\\'), 1) : $fqcn);
                            $aliases[$short] = $groupPrefix.'\\'.ltrim($fqcn, '\\');
                        }
                        $i++;
                        continue;
                    }

                    if (';' === $token->text || ($groupMode && ',' === $token->text)) {
                        if ('' !== $fqcn) {
                            $short = $alias ?? (false !== strrchr($fqcn, '\\') ? substr(strrchr($fqcn, '\\'), 1) : $fqcn);
                            $aliases[$short] = '' !== $groupPrefix ? $groupPrefix.'\\'.ltrim($fqcn, '\\') : ltrim($fqcn, '\\');
                        }

                        $fqcn = '';
                        $alias = null;

                        $i++;
                        continue;
                    }

                    if ($token->is(T_AS)) {
                        $alias = '';
                        $i++;
                        while ($i < $tokenCount && $tokens[$i]->is(T_WHITESPACE)) {
                            $i++;
                        }
                        if ($i < $tokenCount && $tokens[$i]->is(T_STRING)) {
                            $alias = $tokens[$i]->text;
                        }
                        $i++;
                        continue;
                    }

                    if ($token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_NS_SEPARATOR])) {
                        $fqcn .= $token->text;
                    }

                    $i++;
                }

                continue;
            }
            $i++;
        }

        return $aliases;
    }

    /**
     * @param array<PhpToken> $tokens
     * @param array<string, string> $aliases
     */
    private function containsAttribute(array $tokens, array $aliases, int $tokenCount): bool
    {
        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];
            if (!$token->is(T_ATTRIBUTE)) {
                continue;
            }

            $attributeNames = $this->extractAttributeNames($tokens, $i + 1);
            foreach ($attributeNames as $name) {
                $normalized = ltrim($name, '\\');

                // Exact match
                if (0 === strcasecmp($normalized, $this->attributeClassName)) {
                    return true;
                }

                // Alias match
                if (isset($aliases[$normalized]) && 0 === strcasecmp($aliases[$normalized], $this->attributeClassName)) {
                    return true;
                }

                // Partial resolution
                if (str_contains($normalized, '\\')) {
                    [$firstSegment, $rest] = explode('\\', $normalized, 2);
                    if (isset($aliases[$firstSegment])) {
                        $resolved = $aliases[$firstSegment].'\\'.$rest;
                        if (0 === strcasecmp($resolved, $this->attributeClassName)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param array<PhpToken> $tokens
     *
     * @return list<string>
     */
    private function extractAttributeNames(array $tokens, int $startIndex): array
    {
        $names = [];
        $name = '';
        $depth = 0;
        $collecting = false;

        for ($i = $startIndex, $len = count($tokens); $i < $len; $i++) {
            $t = $tokens[$i];

            if ('[' === $t->text) {
                $depth++;
                continue;
            }

            if (']' === $t->text) {
                if (0 === $depth) {
                    break;
                }
                $depth--;
                continue;
            }

            if ($t instanceof PhpToken) {
                if ($t->is([T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE])) {
                    if (!$collecting) {
                        $name = '';
                        $collecting = true;
                    }

                    $name .= $t->text;
                } elseif ($collecting) {
                    $names[] = $name;
                    $collecting = false;
                }
            }

            if (in_array($t->text, ['(', ',', ']'], true)) {
                if ($collecting) {
                    $names[] = $name;
                    $collecting = false;
                }
            }
        }

        if ($collecting) {
            $names[] = $name;
        }

        return $names;
    }

    /**
     * @param array<PhpToken> $tokens
     *
     * @return list<array{0:string, 1:string}>
     */
    private function extractFunctionAndClassNames(array $tokens, int $tokenCount): array
    {
        $namespace = '';
        $targets = [];
        $scopeStack = [];
        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];
            if ('{' === $token->text) {
                $scopeStack[] = 1;
            } elseif ('}' === $token->text) {
                array_pop($scopeStack);
            }

            if ($token->is(T_NAMESPACE)) {
                $namespace = '';
                for ($j = $i + 1; $j < $tokenCount; $j++) {
                    if (';' === $tokens[$j]->text || '{' === $tokens[$j]->text) {
                        break;
                    }
                    if ($tokens[$j] instanceof PhpToken) {
                        $namespace .= $tokens[$j]->text;
                    }
                }
                $namespace = trim($namespace).'\\';
            }

            if ($token->is([T_CLASS, T_TRAIT, T_ENUM])) {
                for ($j = $i + 1; $j < $tokenCount; $j++) {
                    if ($tokens[$j]->is(T_STRING)) {
                        $targets[] = ['class', $namespace.$tokens[$j]->text];
                        break;
                    }
                }
            }

            // Detect and skip `use function` and `use const` imports
            if ($token->is(T_USE)) {
                $j = $i + 1;
                while ($j < $tokenCount && $tokens[$j]->is(T_WHITESPACE)) {
                    $j++;
                }

                if ($j < $tokenCount && $tokens[$j]->is([T_FUNCTION, T_CONST])) {
                    // Skip to the next semicolon
                    while ($i < $tokenCount && ';' !== $tokens[$i]->text) {
                        $i++;
                    }
                    continue;
                }
            }

            // Detect top-level functions
            if ($token->is(T_FUNCTION) && [] === $scopeStack) {
                // Only handle actual function declarations (not anonymous)
                for ($j = $i + 1; $j < $tokenCount; $j++) {
                    if ($tokens[$j]->is(T_STRING)) {
                        $targets[] = ['function', $namespace.$tokens[$j]->text];
                        break;
                    }

                    if ('(' === $tokens[$j]->text) {
                        // Likely an anonymous function: skip
                        break;
                    }
                }
            }
        }

        return $targets;
    }
}
