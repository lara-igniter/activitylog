<?php

namespace Laraigniter\Activitylog;

use Elegant\Console\Kernel;
use Elegant\Contracts\Hook\Boot;
use Elegant\Contracts\Hook\PostControllerConstructor;
use Elegant\Support\ServiceProvider;
use Laraigniter\Activitylog\Console\CleanActivitylogCommand;
use Laraigniter\Activitylog\Support\ActivityLogStatus;
use Laraigniter\Activitylog\Support\ActivityLogger;
use Laraigniter\Activitylog\Support\CauserResolver;

final class ActivitylogServiceProvider extends ServiceProvider implements Boot, PostControllerConstructor
{
    public function boot(): void
    {
        if (!is_cli()) {
            return;
        }

        Kernel::registerCommand(CleanActivitylogCommand::class);

        $this->publishes([
            __DIR__ . '/../config/activitylog.php' => base_path('config/activitylog.php'),
        ], 'activitylog-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/20260804000000_create_activity_log_table.php'
                => base_path('database/migrations/20260804000000_create_activity_log_table.php'),
        ], 'activitylog-migrations');
    }

    public function postControllerConstructor(&$params): void
    {
        require_once __DIR__ . '/helpers.php';

        $this->mergeConfigFrom(__DIR__ . '/../config/activitylog.php', 'activitylog');
        app('load')->config('activitylog', true);

        app('activitylog.status', new ActivityLogStatus());
        app('activitylog.causer', new CauserResolver());
        app('activitylog', new ActivityLogger(
            app('activitylog.status'),
            app('activitylog.causer')
        ));
    }
}



