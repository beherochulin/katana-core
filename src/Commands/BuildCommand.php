<?php
namespace BeeSoft\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Factory;
use BeeSoft\SiteBuilder;

class BuildCommand extends Command {
    private $filesystem;
    private $viewFactory;

    public function __construct(Factory $viewFactory, Filesystem $filesystem) {
        $this->filesystem = $filesystem;
        $this->viewFactory = $viewFactory;

        parent::__construct();
    }
    protected function configure() {
        $this->setName('build')
            ->setDescription('Generate the site static files.')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Application Environment.', 'default')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Clear the cache before building.');
    }
    protected function execute(InputInterface $input, OutputInterface $output) {
        $siteBuilder = new SiteBuilder(
            $this->filesystem,
            $this->viewFactory,
            $input->getOption('env'),
            $input->getOption('force')
        );

        $siteBuilder->build();

        $output->writeln("<info>Site was generated successfully.</info>");
    }
}
