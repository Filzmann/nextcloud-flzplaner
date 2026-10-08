<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Command;

use OCA\FlzPlaner\Service\PlanerDemoPackService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Zweck: Stellt den sicheren Assistenzplaner-Demo-Pack für automatisierte Staging-Setups bereit. */
final class SeedDemoCommand extends Command {
    public function __construct(private PlanerDemoPackService $demoPack) { parent::__construct(); }
    protected function configure(): void { $this->setName('flzplaner:demo:seed')->setDescription('Erzeugt Team A, Team B und Team C mit synthetischen Demokonten.'); }
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $result = $this->demoPack->install();
        $output->writeln('<info>' . implode(', ', $result['teams']) . ' als Demoteams synchronisiert.</info>');
        return self::SUCCESS;
    }
}
