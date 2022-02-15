<?php

namespace App\Command;

use App\Service\SearchService;
use ItkDev\MetricsBundle\Service\MetricsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Stopwatch\Stopwatch;

class DatawellCommand extends Command
{
    protected static $defaultName = 'app:datawell:stats';
    protected static $defaultDescription = 'Get time for data well connection';

    private SearchService $searchService;
    private MetricsService $metricsService;

    public function __construct(SearchService $searchService, MetricsService $metricsService)
    {
        $this->searchService = $searchService;
        $this->metricsService = $metricsService;

        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $stopwatch = new Stopwatch(true);
            $stopwatch->start('request');
            $result = $this->searchService->search('facet.type=ebog');
            $event = $stopwatch->stop('request');
            $seconds = $event->getDuration() / 1000;

            $this->metricsService->histogram('datawell_search_duration_seconds', '', $seconds);
            $this->metricsService->histogram('datawell_reported_duration_seconds', '', $result[3]);
            $this->metricsService->gauge('datawell_up', 'Is datawell service online', 1);

            if ($output->isVerbose()) {
                $io->success('Request time: '.$seconds.', Reported time: '.$result[3]);
            }
        } catch (\Exception $exception) {
            $this->metricsService->gauge('datawell_up', 'Is datawell service online', 0);
            if ($output->isVerbose()) {
                $io->error('Datewell error: '.$exception->getMessage());

                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}