<?php

namespace Sterzik\ModStamp;

use Exception;
use Generator;

class ClassLister
{
    public static function listClasses(string $namespace): Generator
    {
        $namespace = trim($namespace, '\\');
        $namespaceParsed = explode('\\', $namespace);

        if (count($namespaceParsed) < 2) {
            throw new Exception(sprintf("Cannot get classes for namepsace %s", $namespace));
        }
        if (array_shift($namespaceParsed) !== 'Sterzik') {
            throw new Exception(sprintf("Cannot get classes for namepsace %s", $namespace));
        }
        if (array_shift($namespaceParsed) !== 'ModStamp') {
            throw new Exception(sprintf("Cannot get classes for namepsace %s", $namespace));
        }

        $dir = __DIR__ . "/" . implode("/", $namespaceParsed);
        $dd = opendir($dir);
        if ($dd) {
            while ($file = readdir($dd)) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                if (preg_match('/\.php$/', $file)) {
                    $class = $namespace . "\\" . basename($file, ".php");
                    if (class_exists($class)) {
                        yield $class;
                    }
                }
            }
            closedir($dd);
        }
    }
}
