<?php


namespace App\Service\Globals;


use App\Enum\ClientSignal;

class ResponseGlobal
{
    protected array $signals = [];

    public function withSignal(ClientSignal ...$signals): void {
        $this->signals = [
            ...$this->signals,
            ...array_map(fn(ClientSignal $s) => $s->value, $signals)
        ];
    }

    public function withConditionalSignal(mixed $condition, ClientSignal ...$signals): void {
        if (!!$condition) $this->withSignal( ...$signals );
    }

    public function getSignals(): array {
        return $this->signals = array_unique($this->signals);
    }
}