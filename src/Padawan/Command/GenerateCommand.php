<?php

namespace Padawan\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Padawan\Domain\Project;
use Padawan\Domain\Project\Index;
use Padawan\Domain\Generator\IndexGenerator;
use Padawan\Framework\Project\Persister;

class GenerateCommand extends CliCommand
{
    protected function configure()
    {
        $this->setName("generate")
            ->setDescription("Generates new index for the project")
            ->addArgument(
                "path",
                InputArgument::OPTIONAL,
                "Path to the project root. Default: current directory"
            );
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rootDir = $input->getArgument("path");
        if (empty($rootDir)) {
            $rootDir = getcwd();
        }
        try {
            $generator = $this->get(IndexGenerator::class);

            $project = new Project(
                $this->get(Index::class),
                $rootDir
            );

            $generator->generateIndex($project);
            $persister = $this->get(Persister::class);

            $persister->save($project);
            $output->writeln("<info>Index generated</info>");
        } catch (\Exception $e) {
            $output->writeln(sprintf("<error>Error: %s</error>", $e->getMessage()));
        }
    }
}