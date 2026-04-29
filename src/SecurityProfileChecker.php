<?php

namespace Sterzik\ModStamp;

use Exception;

class SecurityProfileChecker
{
    public static function checkSecurityProfile(array $config): array
    {
        if (!self::checkOrdinaryArray($config)) {
            $config = $config['profiles'] ?? [];
            if (!self::checkOrdinaryArray($array)) {
                throw new Exception("profile list must be an array");
            }
        }
        foreach ($config as $id => &$profile) {
            self::checkProfile($id, $profile);
        }
        return $config;
    }

    private static function checkProfile(int $id, array &$profile): void
    {
        if (!isset($profile['encryption'])) {
            throw new Exception(sprintf("missing encryption in profile %d", $id));
        }
        if (!is_string($profile['encryption'])) {
            throw new Exception(sprintf("invalid encryption in profile %d", $id));
        }
        if (isset($profile['permission'])) {
            if (!is_string($profile['permission'])) {
                throw new Exception(sprintf("invalid permission set in profile %d", $id));
            }
            $profile['permission'] = strtolower($profile['permission']);
            if (!in_array($profile['permission'], ['none', 'ro', 'rw', 'default'])) {
                throw new Exception(sprintf("invalid permission set in profile %d", $id));
            }
            if ($profile['permission'] === 'default') {
                unset($profile['permission']);
            }
        } else {
            unset($profile['permission']);
        }
        if (isset($profile['id'])) {
            if (!is_string($profile['id'])) {
                throw new Exception(sprintf("invalid profile identifier in profile %d", $id));
            }
        } else {
            unset($profile['id']);
        }
        if (isset($profile['hosts'])) {
            if (!is_array($profile['hosts'])) {
                $profile['hosts'] = [$profile['hosts']];
            }
            if (!self::checkOrdinaryArray($profile['hosts'])) {
                throw new Exception(sprintf("invalid profile hosts in profile %d: array required", $id));
            }
            foreach ($profile['hosts'] as &$host) {
                if (!is_string($host)) {
                    throw new Exception(sprintf("invalid profile hosts in profile %d: host is not a string", $id));
                }
            }
        } else {
            unset($profile['hosts']);
        }
    }

    private static function checkOrdinaryArray(array $array): bool
    {
        $keys = array_keys($array);
        if ($keys === array_keys($keys)) {
            return true;
        }
        return false;
    }
}
