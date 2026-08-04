<?php

namespace Laraigniter\Activitylog\Console;

use Elegant\Console\Command;
use Laraigniter\Activitylog\Actions\CleanActivityLogAction;

class CleanActivitylogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'activitylog:clean
                                    {log? : Optional log name to clean}
                                    {--days= : Delete records older than this number of days}';

    /**
     * The default name used by the console router.
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'activitylog:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Clean up old records from the activity log.';

    public function handle(): int
    {
        $days = $this->option('days');

        if ($days !== null && (filter_var($days, FILTER_VALIDATE_INT) === false || (int) $days < 1)) {
            $this->error('The days option must be a positive integer.');

            return 1;
        }

        $this->comment('Cleaning activity log...');

        $deleted = (new CleanActivityLogAction())->execute(
            $days === null ? null : (int) $days,
            $this->argument('log')
        );

        $deletedCount = is_array($deleted) ? count($deleted) : (int) $deleted;

        $this->info("Deleted {$deletedCount} record(s) from the activity log.");
        $this->comment('All done!');

        return 0;
    }
}

