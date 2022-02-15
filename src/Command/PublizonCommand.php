<?php

namespace App\Command;

use App\Service\PublizonStatsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PublizonCommand extends Command
{
    protected static $defaultName = 'app:publizon:stats';
    protected static $defaultDescription = 'Get connection stats for publizon service';

    private PublizonStatsService $publizonService;

    public function __construct(PublizonStatsService $ereolenService)
    {
        $this->publizonService = $ereolenService;

        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $results = $this->publizonService->stats();
            if ($output->isVerbose()) {
                $io->success('Request time: '.$results['request']);
            }
        } catch (\Exception $exception) {
            if ($output->isVerbose()) {
                $io->error('Publizon error: '.$exception->getMessage());

                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
