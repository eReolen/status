<?php

namespace App\Command;

use App\Service\PublizonService;
use App\Service\SearchService;
use ItkDev\MetricsBundle\Service\MetricsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Stopwatch\Stopwatch;

class PublizonCommand extends Command
{
    protected static $defaultName = 'app:publizon:stats';
    protected static $defaultDescription = 'Get connection stats for publizon service';


    private MetricsService $metricsService;
    private PublizonService $publizonService;

    public function __construct(PublizonService $publizonService, MetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
        $this->publizonService = $publizonService;

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
            $this->publizonService->getLibraryProfile();
            $event = $stopwatch->stop('request');
            $seconds = $event->getDuration() / 1000;

            $this->metricsService->histogram('publizon_duration_seconds', '', $seconds);
            $this->metricsService->gauge('publizon_up', 'Is Publizon service online', 1);

            if ($output->isVerbose()) {
                $io->success('Request time: ' . $seconds);
            }
        } catch (\Exception $exception) {
            $this->metricsService->gauge('publizon_up', 'Is Publizon service online', 0);
            if ($output->isVerbose()) {
                $io->error('Publizon error: ' . $exception->getMessage());

                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
