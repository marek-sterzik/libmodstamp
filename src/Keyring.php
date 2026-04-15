<?php

namespace Sterzik\ModStamp;

use ReflectionClass;
use Sterzik\ModStamp\Encryptor\AbstractEncryptor;

class Keyring
{
    public function __construct(private array $encryptionConfig)
    {
        if (empty($this->encryptionConfig)) {
            $this->encryptionConfig[] = [
                "encryption" => "plain",
                "permission" => "rw",
                "hosts" => ["127.0.0.1", "::"],
            ];
            $this->encryptionConfig[] = [
                "encryption" => "plain",
                "permission" => "ro",
            ];
        }

        $encryptors = $this->listEncryptors();
        

        foreach ($this->encryptionConfig as &$config) {
            $encryptorClass = $encryptors[$config['encryption']] ?? null;
            if ($encryptorClass !== null) {
                $config['encryptor'] = new $encryptorClass($config);
            } else {
                $config['encryptor'] = null;
            }
        }
    }

    private function listEncryptors(): array
    {
        $encryptors = [];
        foreach (glob(__DIR__ . "/Encryptor/*.php") as $file) {
            $class = "Sterzik\\ModStamp\\Encryptor\\" . basename($file, ".php");
            if (class_exists($class) && is_a($class, AbstractEncryptor::class, true)) {
                $rc = new ReflectionClass($class);
                if ($rc->isAbstract()) {
                    continue;
                }
                $encryptors[$class::getIdentifier()] = $class;
            }
        }
        return $encryptors;
    }

    public function getClientEncryptionInfo(string $peerHost): string
    {
        foreach ($this->encryptionConfig as $config) {
            if (($config['hosts'] ?? null) !== null && !$this->matchHost($peerHost, $config['hosts'])) {
                continue;
            }
            return $config['encryptor']?->getEncryptionInfo($config['id'] ?? '');
        }
    }


    public function getEncryptor(string $peerHost, string $encryptionInfo): ?AbstractEncryptor
    {
        if ($encryptionInfo === "") {
            $signature = "";
            $param = "";
        } else {
            $signature = substr($encryptionInfo, 0, 1);
            $param = substr($encryptionInfo, 1);
        }
        foreach ($this->encryptionConfig as $config) {
            if (!$config['encryptor']?->matchSignature($signature)) {
                continue;
            }
            if (($config['id'] ?? null) !== null && $config['id'] !== $param) {
                continue;
            }
            if (($config['hosts'] ?? null) !== null && !$this->matchHost($peerHost, $config['hosts'])) {
                continue;
            }
            return $config['encryptor'];
        }
        return null;
    }

    private function matchHost(string $host, array $hosts): bool
    {
        foreach ($hosts as $h) {
            if ($host === $h) {
                return true;
            }
        }
        return false;
    }
}
