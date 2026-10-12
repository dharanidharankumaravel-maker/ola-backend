<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $event = 'PAYMENT_RECEIVED';
    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function broadcastAs(): string
    {
        return 'PAYMENT_RECEIVED';
    }

    public function broadcastWith(): array
    {
        return [
            'event' => $this->event,
            'data' => $this->data,
        ];
    }
}
