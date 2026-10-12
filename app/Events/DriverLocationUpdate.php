<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdate
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $event = 'DRIVER_LOCATION_UPDATE';
    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function broadcastAs(): string
    {
        return 'DRIVER_LOCATION_UPDATE';
    }

    public function broadcastWith(): array
    {
        return [
            'event' => $this->event,
            'data' => $this->data,
        ];
    }
}
