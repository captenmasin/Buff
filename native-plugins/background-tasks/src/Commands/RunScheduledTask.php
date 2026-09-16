<?php

namespace Buff\BackgroundTasks\Commands;

use Buff\BackgroundTasks\ScheduledTaskRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

#[Signature('background-task:run {--task=} {--result=}')]
#[Description('Run a Laravel command scheduled by Android WorkManager')]
class RunScheduledTask extends Command
{
    public function handle(ScheduledTaskRegistry $tasks): int
    {
        $taskId = (string) $this->option('task');

        try {
            $this->output->write($tasks->run($taskId));
            $result = "BUFF_BACKGROUND_TASK_OK:{$taskId}";
            $resultPath = $this->option('result');

            if (is_string($resultPath) && $resultPath !== '') {
                File::put($resultPath, $result);
            }

            $this->newLine();
            $this->line($result);
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
