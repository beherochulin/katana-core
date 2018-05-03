<?php
namespace BeeSoft\FileHandlers;

use BeeSoft\MarkdownFileBuilder;
use Symfony\Component\Finder\SplFileInfo;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Factory;

class BaseHandler {
    protected $filesystem;
    protected $viewFactory;
    protected $file;
    protected $viewPath;
    protected $directory;
    public $viewsData = [];

    public function __construct(Filesystem $filesystem, Factory $viewFactory) {
        $this->filesystem = $filesystem;
        $this->viewFactory = $viewFactory;
    }

    public function handle(SplFileInfo $file) {
        $this->file = $file;

        $this->viewPath = $this->getViewPath();

        $this->directory = $this->getDirectoryPrettyName();

        $this->appendViewInformationToData();

        if (@$this->viewsData['enableBlog'] && @$this->viewsData['postsListView'] == $this->viewPath) {
            $this->prepareBlogIndexViewData();
        }

        $content = $this->getFileContent();

        $this->filesystem->put(
            sprintf(
                '%s/%s',
                $this->prepareAndGetDirectory(),
                ends_with($file->getFilename(), ['.blade.php', 'md']) ? 'index.html' : $file->getFilename()
            ),
            $content
        );
    }
    private function getFileContent() {
        if (ends_with($this->file->getFilename(), '.blade.php')) {
            return $this->renderBlade();
        } elseif (ends_with($this->file->getFilename(), '.md')) {
            return $this->renderMarkdown();
        }

        return $this->file->getContents();
    }
    protected function renderBlade() {
        return $this->viewFactory->make($this->viewPath, $this->viewsData)->render();
    }
    protected function renderMarkdown() {
        $markdownFileBuilder = new MarkdownFileBuilder($this->filesystem, $this->viewFactory, $this->file, $this->viewsData);

        return $markdownFileBuilder->render();
    }
    private function prepareAndGetDirectory() {
        if (! $this->filesystem->isDirectory($this->directory)) {
            $this->filesystem->makeDirectory($this->directory, 0755, true);
        }

        return $this->directory;
    }
    protected function getDirectoryPrettyName() {
        $fileBaseName = $this->getFileName();

        $fileRelativePath = $this->normalizePath($this->file->getRelativePath());

        if (in_array($this->file->getExtension(), ['php', 'md']) && $fileBaseName != 'index') {
            $fileRelativePath .= $fileRelativePath ? "/$fileBaseName" : $fileBaseName;
        }

        return KATANA_PUBLIC_DIR.($fileRelativePath ? "/$fileRelativePath" : '');
    }
    private function getViewPath() {
        return str_replace(['.blade.php', '.md'], '', $this->file->getRelativePathname());
    }
    private function prepareBlogIndexViewData() {
        $postsPerPage = @$this->viewsData['postsPerPage'] ?: 5;

        $this->viewsData['nextPage'] = count($this->viewsData['blogPosts']) > $postsPerPage ? '/blog-page/2' : null;

        $this->viewsData['previousPage'] = null;

        $this->viewsData['paginatedBlogPosts'] = array_slice($this->viewsData['blogPosts'], 0, $postsPerPage, true);
    }
    protected function getFileName(SplFileInfo $file = null) {
        $file = $file ?: $this->file;

        return str_replace(['.blade.php', '.php', '.md'], '', $file->getBasename());
    }
    private function appendViewInformationToData() {
        $this->viewsData['currentViewPath'] = $this->viewPath;

        $this->viewsData['currentUrlPath'] = ($path = str_replace(KATANA_PUBLIC_DIR, '', $this->directory)) ? $path : '/';
    }
    protected function normalizePath($path) {
        return str_replace("\\", '/', $path);
    }
}
