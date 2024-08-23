<?php

class Message extends App\Services\Kernel\Message
{
    public function getOriginalAttributes(): array
    {
        $attributes = json_decode($this->getOriginalContents(), true);

        return is_array($attributes) ? $attributes : [];
    }

    public function getEventType(): ?string
    {
        $eventType = $this->getOriginalAttributes()['event_type'];

        if (!is_string($eventType)) {
            throw new RuntimeException('Invalid event type.');
        }

        return $eventType;
    }
}