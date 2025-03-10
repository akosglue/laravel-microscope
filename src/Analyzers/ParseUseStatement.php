<?php

namespace Imanghafoori\LaravelMicroscope\Analyzers;

class ParseUseStatement
{
    public static function getUseStatementsByPath($namespacedClassName, $absPath)
    {
        return self::parseUseStatements(token_get_all(file_get_contents($absPath)), $namespacedClassName)[1];
    }

    public static function findClassReferences(&$tokens, $absFilePath)
    {
        try {
            $imports = self::parseUseStatements($tokens);
            $imports = $imports[0] ?: [$imports[1]];
            [$classes, $namespace] = ClassReferenceFinder::process($tokens);

            return Expander::expendReferences($classes, $imports, $namespace);
        } catch (\ErrorException $e) {
            self::requestIssue($absFilePath);

            return [null, null];
        }
    }

    /**
     * Parses PHP code.
     *
     * @param $tokens
     * @param  null  $forClass
     *
     * @return array of [class => [alias => class, ...]]
     */
    public static function parseUseStatements($tokens, $forClass = null)
    {
        $namespace = $class = $classLevel = $level = null;
        $output = $uses = [];
        while ($token = \current($tokens)) {
            \next($tokens);
            switch (\is_array($token) ? $token[0] : $token) {
                case T_NAMESPACE:
                    $namespace = ltrim(self::FetchNS($tokens).'\\', '\\');
                    $uses = [];
                    break;

                case T_CLASS:
                case T_INTERFACE:
                case T_TRAIT:
                    if ($name = self::fetch($tokens, T_STRING)) {
                        $class = $namespace.$name;
                        $classLevel = $level + 1;
                        $output[$class] = $uses;
                        if ($class === $forClass) {
                            return [$output, $uses];
                        }
                    }
                    break;

                case T_USE:
                    while (! $class && ($name = self::FetchNS($tokens))) {
                        $name = ltrim($name, '\\');
                        if (self::fetch($tokens, '{')) {
                            while ($suffix = self::FetchNS($tokens)) {
                                if (self::fetch($tokens, T_AS)) {
                                    $uses[self::fetch($tokens, T_STRING)] = [$name.$suffix, $token[2]];
                                } else {
                                    $tmp = \explode('\\', $suffix);
                                    $uses[end($tmp)] = [$name.$suffix, $token[2]];
                                }
                                if (! self::fetch($tokens, ',')) {
                                    break;
                                }
                            }
                        } elseif (self::fetch($tokens, T_AS)) {
                            $uses[self::fetch($tokens, T_STRING)] = [$name, $token[2]];
                        } else {
                            $tmp = \explode('\\', $name);
                            $uses[\end($tmp)] = [$name, $token[2]];
                        }
                        if (! self::fetch($tokens, ',')) {
                            break;
                        }
                    }
                    break;

                case T_CURLY_OPEN:
                case T_DOLLAR_OPEN_CURLY_BRACES:
                case '{':
                    $level++;
                    break;

                case '}':
                    if ($level === $classLevel) {
                        $class = $classLevel = null;
                    }
                    $level--;
            }
        }

        return [$output, $uses];
    }

    public static function fetch(&$tokens, $take)
    {
        $result = null;

        $neutral = [T_DOC_COMMENT, T_WHITESPACE, T_COMMENT];

        while ($token = \current($tokens)) {
            [$token, $s,] = \is_array($token) ? $token : [$token, $token];

            if (\in_array($token, (array) $take, true)) {
                $result .= $s;
            } elseif (! \in_array($token, $neutral, true)) {
                break;
            }
            \next($tokens);
        }

        return $result;
    }

    /**
     * @param $absFilePath
     */
    protected static function requestIssue($absFilePath)
    {
        dump('===========================================================');
        dump('was not able to properly parse the: '.$absFilePath.' file.');
        dump('Please open up an issue on the github repo');
        dump('https://github.com/imanghafoori1/laravel-microscope/issues');
        dump('and also send the content of the file to fix the issue.');
        dump('========================== Thanks ==========================');
        sleep(3);
    }

    private static function FetchNS(&$tokens)
    {
        return self::fetch($tokens, [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED]);
    }
}
