<?php

namespace Katana;

use Symfony\Component\Console\Application as SymfonyConsole;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\FileViewFinder;
use Illuminate\Events\Dispatcher;
use Katana\Commands\BuildCommand;
use Illuminate\View\Factory;

class Katana
{
    private $application;
    private $viewFactory;
    private $filesystem;

    public function __construct(SymfonyConsole $application)
    {
        $this->registerConstants();

        $this->application = $application;

        $this->filesystem = new Filesystem();
        $this->viewFactory = $this->createViewFactory();
    }

    public function handle()
    {
        $this->registerCommands();

        $this->application->run();
    }
    private function registerCommands()
    {
        $this->application->addCommands([
            new BuildCommand($this->viewFactory, $this->filesystem)
        ]);
    }

    private function createViewFactory()
    {
        $resolver = new EngineResolver();

        $bladeCompiler = $this->createBladeCompiler();

        $resolver->register('blade', function () use ($bladeCompiler) {
            return new CompilerEngine($bladeCompiler);
        });

        $dispatcher = new Dispatcher();

        $dispatcher->listen('creating: *', function () {
            /**
             * On rendering Blade views we will mute error reporting as
             * we don't care about undefined variables or type
             * mistakes during compilation.
             */
            error_reporting(error_reporting() & ~E_NOTICE & ~E_WARNING);
        });

        return new Factory(
            $resolver,
            new FileViewFinder($this->filesystem, [KATANA_CONTENT_DIR]),
            $dispatcher
        );
    }
    private function createBladeCompiler()
    {
        if (! $this->filesystem->isDirectory(KATANA_CACHE_DIR)) {
            $this->filesystem->makeDirectory(KATANA_CACHE_DIR);
        }

        $blade = new Blade(
            new BladeCompiler($this->filesystem, KATANA_CACHE_DIR)
        );

        return $blade->getCompiler();
    }
    private function registerConstants()
    {
        define('KATANA_CACHE_DIR', getcwd().'/_cache');
        define('KATANA_CONTENT_DIR', getcwd().'/content');
        define('KATANA_PUBLIC_DIR', getcwd().'/public');
    }
}