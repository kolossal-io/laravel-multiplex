<?php

namespace Kolossal\MetaRevision\Commands;

use Illuminate\Console\Command;

class MetaRevisionCommand extends Command
{
    public $signature = 'laravel-meta-revision';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
