<?php

namespace Sterzik\ModStamp;

use Generator;
use Exception;
use ReflectionClass;
use Symfony\Component\Yaml\Yaml;
use IPLib\Factory as IPLibFactory;
use IPLib\Range\RangeInterface;
use IPLib\Address\Type as AddressType;
use Sterzik\ModStamp\Encryptor\AbstractEncryptor;

class SecurityProfile
{
    static ?array $encryptors = null;

    private array $profiles = [];

    public static function loadFromFile(string $filename): self
    {
        $data = @file_get_contents($filename);
        if (!is_string($data)) {
            throw new Exception(sprintf("cannot read file: %s", $filename));
        }
        return self::loadFromString($data);
    }

    public static function loadFromString(string $data): self
    {
        $dataParsed = @json_decode($data, true);
        if (!is_array($dataParsed)) {
            if (class_exists(Yaml::class)) {
                try {
                    $dataParsed = Yaml::parse($data);
                } catch (Exception $e) {
                    $dataParsed = null;
                }
            }
        }
        if (!is_array($dataParsed)) {
            throw new Exception("Cannot parse security profile");
        }

        return self::loadFromArray($dataParsed);
    }

    public static function loadFromArray(array $data): self
    {
        return new self(SecurityProfileChecker::checkSecurityProfile($data));
    }

    public static function loadDefault(): self
    {
        return self::loadFromArray([
            [
                "encryption" => "plain",
                "permission" => "rw",
                "hosts" => ["127.0.0.1", "::"],
            ], [
                "encryption" => "plain",
                "permission" => "ro",
            ]
        ]);
    }

    private function listEncryptors(): array
    {
        if (static::$encryptors === null) {
            static::$encryptors = [];
            foreach (glob(__DIR__ . "/Encryptor/*.php") as $file) {
                $class = "Sterzik\\ModStamp\\Encryptor\\" . basename($file, ".php");
                if (class_exists($class) && is_a($class, AbstractEncryptor::class, true)) {
                    $rc = new ReflectionClass($class);
                    if ($rc->isAbstract()) {
                        continue;
                    }
                    static::$encryptors[$class::getIdentifier()] = $class;
                }
            }
        }
        return static::$encryptors;
    }

    private function __construct(array $profiles)
    {
        $encryptors = static::listEncryptors();
        
        foreach ($profiles as $config) {
            $encryptorClass = $encryptors[$config['encryption']] ?? null;
            if ($encryptorClass !== null) {
                $config['encryptor'] = new $encryptorClass($config);
                $this->profiles[] = $config;
            }
        }

        if (class_exists(IPLibFactory::class)) {
            Log::log(Log::DBG, "enabling extended ip-lib based host match");
            foreach ($this->profiles as &$config) {
                if (isset($config['hosts'])) {
                    $config['hosts'] = array_values(array_filter(
                        array_map(fn($range) => IPLibFactory::parseRangeString($range), $config['hosts']),
                        fn($range) => $range !== null
                    ));
                }
            }
        } else {
            Log::log(Log::DBG, "disabling extended ip-lib based host match, mlocati/ip-lib not found");
        }
        if (Log::enabled(Log::DBG)) {
            foreach ($this->profiles as $id => &$config) {
                $hosts = "";
                if (isset($config['hosts'])) {
                    $hosts = sprintf(" hosts=\"%s\"", implode(",", $config['hosts']));
                }
                Log::log(
                    Log::DBG,
                    "security profile: id=%d encryption=\"%s\" permission=\"%s\"%s",
                    $id,
                    $config['encryption'],
                    $config['permission'] ?? 'none',
                    $hosts
                );
            }
        }
    }

    public function matchFirstForHost(string $peerHost): ?int
    {
        foreach ($this->profiles as $id => $config) {
            if (($config['hosts'] ?? null) !== null && !$this->matchHost($peerHost, $config['hosts'])) {
                continue;
            }
            return $id;
        }
        return null;
    }

    public function getEncryptor(?int $id): ?AbstractEncryptor
    {
        if ($id === null) {
            return null;
        }
        return $this->profiles[$id]['encryptor'] ?? null;
    }

    public function matchEncryptors(string $peerHost, string $encryptionInfo): Generator
    {
        if ($encryptionInfo === "") {
            $signature = "";
            $param = "";
        } else {
            $signature = substr($encryptionInfo, 0, 1);
            $param = substr($encryptionInfo, 1);
        }
        foreach ($this->profiles as $id => $config) {
            if (!$config['encryptor']->matchSignature($signature)) {
                continue;
            }
            if (($config['id'] ?? null) !== null && $config['id'] !== $param) {
                continue;
            }
            if (($config['hosts'] ?? null) !== null && !$this->matchHost($peerHost, $config['hosts'])) {
                continue;
            }
            yield $id;
        }
    }

    private function matchHost(string $host, array $hosts): bool
    {
        $hostIps = null;
        foreach ($hosts as $h) {
            if ($h instanceof RangeInterface) {
                if ($hostIps === null) {
                    $hostIp = IPLibFactory::parseAddressString($host);
                    if ($hostIp === null) {
                        return false;
                    }
                    $hostIps = [$hostIp];
                    if ($hostIp->getAddressType() === AddressType::T_IPv6) {
                        $hostIp = $hostIp->toIPv4();
                        if ($hostIp !== null) {
                            $hostIps[] = $hostIp;
                        }
                    }
                }
                foreach ($hostIps as $hostIp) {
                    if ($h->contains($hostIp)) {
                        return true;
                    }
                }
            } else {
                if ($host === $h) {
                    return true;
                }
            }
        }
        return false;
    }
}
