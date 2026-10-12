<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideRequestIncoming
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $event = 'RIDE_REQUEST_INCOMING';
    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function broadcastAs(): string
    {
        return 'RIDE_REQUEST_INCOMING';
    }

    public function broadcastWith(): array
    {
        return [
            'event' => $this->event,
            'data' => $this->data,
        ];
    }
}
