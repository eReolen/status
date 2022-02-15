<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StatsCommand extends Command
{
    protected static $defaultName = 'app:stats';
    protected static $defaultDescription = 'Get all stats updated';
    private iterable $statsServices;

    public function __construct(iterable $statsServices)
    {
        $this->statsServices = $statsServices;

        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        foreach ($this->statsServices as $service) {
            $name = 'Unknown';
            if ($output->isVerbose()) {
                preg_match('/App\\\Service\\\(.*)StatsService/', get_class($service), $matches, PREG_UNMATCHED_AS_NULL);
                if (!empty($matches[1])) {
                    $name = $matches[1];
                }
            }

            try {
                $service->stats();
                if ($output->isVerbose()) {
                    $io->success('Got stats '.$name);
                }
            } catch (\Exception $exception) {
                if ($output->isVerbose()) {
                    $io->error('Stats error '.$name);
                }
            }
        }

        return Command::SUCCESS;
    }
}
