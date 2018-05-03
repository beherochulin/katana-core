<?php
namespace BeeSoft;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\View\Compilers\BladeCompiler;
use Symfony\Component\Finder\SplFileInfo;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Factory;

class MarkdownFileBuilder {
    protected $filesystem;
    protected $viewFactory;
    protected $file;
    protected $data;

    protected $fileContent;
    protected $fileYAML;
    protected $bladeCompiler;
    protected $engine;
    protected $cached;

    public function __construct(Filesystem $filesystem, Factory $viewFactory, SplFileInfo $file, array $data) {
        $this->filesystem = $filesystem;
        $this->viewFactory = $viewFactory;
        $this->file = $file;
        $this->data = $data;

        $parsed = Markdown::parseWithYAML($this->file->getContents());

        $this->fileContent = $parsed[0];

        $this->fileYAML = $parsed[1];

        $this->cached = KATANA_CACHE_DIR.'/'.sha1($this->file->getRelativePathname()).'.php';

        $this->bladeCompiler = $this->getBladeCompiler();

        $this->engine = $this->getEngine();
    }
    public function render() {
        $viewContent = $this->buildBladeViewContent();

        if ( $this->isExpired() ) {
            $this->filesystem->put($this->cached, $this->bladeCompiler->compileString($viewContent));
        }

        $data = $this->getViewData();

        return $this->engine->get($this->cached, $data);
    }
    private function buildBladeViewContent() {
        $sections = '';

        foreach ( $this->fileYAML as $name => $value ) {
            $sections .= "@section('$name', '".addslashes($value)."')\n\r";
        }

        return "@extends('{$this->fileYAML['view::extends']}')
            $sections
            @section('{$this->fileYAML['view::yields']}')
            {$this->fileContent}
            @stop";
    }
    private function getBladeCompiler() {
        return $this->viewFactory->getEngineResolver()->resolve('blade')->getCompiler();
    }
    private function getEngine() {
        return new PhpEngine;
    }
    private function getViewData() {
        $data = array_merge($this->viewFactory->getShared(), $this->data);

        foreach ( $data as $key => $value ) {
            if ( $value instanceof Renderable ) $data[$key] = $value->render();
        }

        return $data;
    }
    private function isExpired() {
        if ( ! $this->filesystem->exists($this->cached) ) return true;

        $lastModified = $this->filesystem->lastModified($this->file->getPath());
        return $lastModified >= $this->filesystem->lastModified($this->cached);
    }
}