<?php

namespace Buff\BackgroundTasks\Commands;

use Buff\BackgroundTasks\ScheduledTaskRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('background-task:run {task}')]
#[Description('Run a Laravel command scheduled by Android WorkManager')]
class RunScheduledTask extends Command
{
    public function handle(ScheduledTaskRegistry $tasks): int
    {
        $taskId = (string) $this->argument('task');

        try {
            $this->output->write($tasks->run($taskId));
            $this->newLine();
            $this->line("BUFF_BACKGROUND_TASK_OK:{$taskId}");
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
