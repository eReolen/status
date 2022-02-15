<?php

namespace App\Command;

use App\Service\DatawellService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DatawellCommand extends Command
{
    protected static $defaultName = 'app:datawell:stats';
    protected static $defaultDescription = 'Get time for data well connection';

    private DatawellService $searchService;

    public function __construct(DatawellService $searchService)
    {
        $this->searchService = $searchService;

        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->searchService->stats();
            if (!empty($result)) {
                $io->success('Request time: '.$result['request'].', Reported time: '.$result['reported']);
            }
        } catch (\Exception $exception) {
            if ($output->isVerbose()) {
                $io->error('Datewell error: '.$exception->getMessage());
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
