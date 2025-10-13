<?php

namespace Check\Commands;

use Check\Utils\Logger;
use Check\Services\CommandRunner;

class AnalyzeCommand
{
    protected Logger $logger;
    protected CommandRunner $commandRunner;

    public function __construct(Logger $logger, CommandRunner $commandRunner)
    {
        $this->logger = $logger;
        $this->commandRunner = $commandRunner;
    }

    public function execute(): void
    {
        $this->logger->section("STATIC ANALYSIS (LARASTAN)");
        
        $command = 'vendor/bin/phpstan analyse --level=5';
        
        $this->logger->info("Running: $command");
        
        $result = $this->commandRunner->runShellCommand($command);

        if (!empty($result['output'])) {
            // The output from exec is an array of lines.
            $this->logger->log(implode("\n", $result['output']));
        }

        if ($result['success']) {
            $this->logger->success("Static analysis completed successfully.");
        } else {
            $this->logger->error("Static analysis finished with errors.");
        }
        
        $this->logger->section("ANALYSIS COMPLETE");
    }
}
