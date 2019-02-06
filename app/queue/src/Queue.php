<?php

class Queue extends \Pckg\Framework\Provider
{

    public function providers()
    {
        return [
            \Pckg\Queue\Provider\Queue::class,
        ];
    }

}