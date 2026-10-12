<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideCancelledByPassenger
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $event = 'RIDE_CANCELLED_BY_PASSENGER';
    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function broadcastAs(): string
    {
        return 'RIDE_CANCELLED_BY_PASSENGER';
    }

    public function broadcastWith(): array
    {
        return [
            'event' => $this->event,
            'data' => $this->data,
        ];
    }
}
