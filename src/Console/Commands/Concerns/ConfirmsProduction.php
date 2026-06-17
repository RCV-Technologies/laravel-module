<?php

namespace RCV\Core\Console\Commands\Concerns;

use Illuminate\Console\ConfirmableTrait;

trait ConfirmsProduction
{
    use ConfirmableTrait;

    protected function confirmToRunInProduction(): bool
    {
        return $this->confirmToProceed();
    }
}
