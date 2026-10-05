<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Infrastructure\Providers;

use App\Contexts\SpendManagement\Domain\Repositories\IExpenseRepository;
use App\Contexts\SpendManagement\Infrastructure\Repositories\EloquentExpenseRepository;
use Illuminate\Support\ServiceProvider;

final class SpendManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            IExpenseRepository::class,
            EloquentExpenseRepository::class,
        );
    }
}
