<?php

namespace App\Command;

use App\Service\EreolenStatsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class EreolenCommand extends Command
{
    protected static $defaultName = 'app:ereolen:stats';
    protected static $defaultDescription = 'Get connection stats for ereolen homepage';

    private EreolenStatsService $ereolenService;

    public function __construct(EreolenStatsService $ereolenService)
    {
        $this->ereolenService = $ereolenService;

        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $results = $this->ereolenService->stats();
            if ($output->isVerbose()) {
                $io->success('Request eReolen time: '.$results['request']['ereolen'].' Request eReolenGO time: '.$results['request']['ereolengo']);
                $io->success('Feed eReolen time: '.$results['feed']['ereolen']['time'].' No. elements: '.$results['feed']['ereolen']['count']);
                $io->success('Feed eReolenGo time: '.$results['feed']['ereolengo']['time'].' No. elements: '.$results['feed']['ereolengo']['count']);
            }
        } catch (\Exception $exception) {
            if ($output->isVerbose()) {
                $io->error('eReolen error: '.$exception->getMessage());

                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
