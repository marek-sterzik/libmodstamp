<?php

namespace Sterzik\ModStamp;

use Sterzik\ModStamp\Cache\ServerCache;
use Sterzik\ModStamp\Storage\ServerStorage;
use Sterzik\ModStamp\Message\AbstractMessage;
use Sterzik\ModStamp\Message\QueryModstamp;
use Sterzik\ModStamp\Message\ChangeModstamp;
use Sterzik\ModStamp\Message\ReplyModstamp;
use Sterzik\ModStamp\Message\ModstampModified;

class MessageServer
{
    const HANDLERS = [
        QueryModstamp::class => "queryModstamp",
        ChangeModstamp::class => "changeModstamp",
    ];
    public function __construct(private ServerCache $cache, private ServerStorage $storage)
    {
    }

    public function handleMessage(Client $client, AbstractMessage $message, ?callable $broadcast = null): ?AbstractMessage
    {
        $handler = self::HANDLERS[get_class($message)] ?? null;
        if ($handler === null) {
            return null;
        }
        return $this->$handler($client, $message, $broadcast);
    }

    private function queryModstamp(Client $client, QueryModstamp $message, ?callable $broadcast = null): ReplyModstamp
    {
        $modstamp = $message->getModstamp();
        $this->cache->assignClientToModstamp($client, $modstamp);
        $modstampValue = $this->queryModstampValue($modstamp);
        return new ReplyModstamp($modstamp, $modstampValue);
    }

    private function changeModstamp(Client $client, ChangeModstamp $message, ?callable $broadcast = null): ModstampModified
    {
        $modstamp = $message->getModstamp();
        $modstampValue = $message->getModstampValue();
        if ($this->updateModstampValue($modstamp, $modstampValue) && $broadcast !== null) {
            $signal = new SignalModstamp($modstamp, $modstampValue);
            foreach ($this->cache->getClientsForModstamp($modstamp) as $client) {
                $broadcast($client, $signal);
            }
        }
        return new ModstampModified($modstamp, $modstampValue);
    }

    private function queryModstampValue(Modstamp $modstamp): ?string
    {
        $modstampValue = $this->cache->getModstampValue($modstamp);
        if ($modstampValue === null) {
            $modstampValue = $this->storage->getModstampValue($modstamp);
            $this->cache->setModstampValue($modstamp, $modstampValue ?? '');
        }
        if ($modstampValue === '') {
            $modstampValue = null;
        }
        return $modstampValue;
    }

    private function updateModstampValue(Modstamp $modstamp, ?string $modstampValue): bool
    {
        if ($modstampValue === '') {
            $modstampValue = null;
        }

        $oldModstampValue = $this->queryModstampValue($modstamp);

        $this->storage->setModstampValue($modstamp, $modstampValue);
        $this->cache->setModstampValue($modstamp, $modstampValue ?? '');

        return $oldModstampValue !== $modstampValue;
        
        return $this;
    }
}
