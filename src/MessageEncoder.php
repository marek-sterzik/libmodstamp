<?php

namespace Sterzik\ModStamp;

use ReflectionClass;
use ReflectionNamedType;
use Sterzik\ModStamp\Message\AbstractMessage;

class MessageEncoder
{
    private static ?array $messageMap = null;

    public static function encodeMessage(string $descriptor, ?array $strings): ?string
    {
        if ($strings === null || strlen($descriptor) !== 1) {
            return null;
        }

        $message = $descriptor;
        if (count($strings) > 255) {
            return null;
        }
        $message .= chr(count($strings));
        foreach ($strings as $string) {
            $string = (string)$string;
            if (strlen($string) > 255) {
                return null;
            }
            $message .= chr(strlen($string));
            $message .= $string;
        }
        return $message;
    }

    public static function decodeMessages(string $message): ?array
    {
        $reader = new StringReader($message);
        $messages = [];
        while (!$reader->isEof()) {
            $message = $reader->readSingleMessage();
            if ($message === null) {
                return null;
            }
            $message = self::decodeMessage($message);
            if ($message !== null) {
                $messages[] = $message;
            }
        }
        return $messages;
    }

    private static function decodeMessage(array $strings): ?AbstractMessage
    {
        self::buildMessageMap();
        if (empty($strings)) {
            return null;
        }
        $descriptor = array_shift($strings);
        if (!isset(self::$messageMap[$descriptor])) {
            return null;
        }
        $record = self::$messageMap[$descriptor];
        $class = $record['class'];
        $args = $record['args'];
        if (count($args) !== count($strings)) {
            return null;
        }
        
        $constructorArgs = [];
        foreach ($strings as $string) {
            $type = array_shift($args);
            if ($type === null) {
                $constructorArgs[] = $string;
            } else {
                $arg = new $type($string);
                if (($arg instanceof Validable) && !$arg->isValid()) {
                    return null;
                }
                $constructorArgs[] = $arg;
            }
        }
        return new $class(...$constructorArgs);
    }

    private static function buildMessageMap()
    {
        if (self::$messageMap === null) {
            self::$messageMap = [];
            foreach (ClassLister::listClasses('Sterzik\\ModStamp\\Message') as $class) {
                $rc = new ReflectionClass($class);
                if ($rc->isAbstract()) {
                    continue;
                }
                
                self::$messageMap[$class::getDescriptor()] = [
                    "class" => $class,
                    "args" => self::buildArgs($rc),
                ];
            }
        }
    }

    private static function buildArgs(ReflectionClass $rc): array
    {
        $constructor = $rc->getConstructor();
        if ($constructor === null) {
            return [];
        }
        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type === null || !($type instanceof ReflectionNamedType)) {
                $args[] = null;
            } elseif (class_exists($type->getName())) {
                $args[] = $type->getName();
            } else {
                $args[] = null;
            }
        }
        return $args;
    }
}
