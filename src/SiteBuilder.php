<?php
namespace Katana;

use Symfony\Component\Finder\SplFileInfo;
use Katana\FileHandlers\BlogPostHandler;
use Illuminate\Filesystem\Filesystem;
use Katana\FileHandlers\BaseHandler;
use Illuminate\View\Factory;
use Illuminate\Support\Str;

class SiteBuilder {
    private $filesystem;
    private $viewFactory;
    private $blogPostHandler;
    private $fileHandler;

    private $environment;
    private $configs;
    private $postsData;
    private $viewsData;
    protected $includesDirectory = '_includes';
    protected $blogDirectory = '_blog';
    protected $forceBuild = false;

    public function __construct(Filesystem $filesystem, Factory $viewFactory, $environment, $forceBuild=false) {
        $this->filesystem = $filesystem; // 文件系统
        $this->viewFactory = $viewFactory; // 视图工厂
        $this->environment = $environment; // 环境
        $this->forceBuild = $forceBuild; // 强制

        $this->fileHandler = new BaseHandler($filesystem, $viewFactory);
        $this->blogPostHandler = new BlogPostHandler($filesystem, $viewFactory);

    }
    public function build() {
        $files = $this->getSiteFiles();

        $blogPostsFiles = array_filter($files, function ($file) {
            return str_contains($file->getRelativePath(), '_blog');
        });

        $otherFiles = array_filter($files, function ($file) {
            return ! str_contains($file->getRelativePath(), '_blog');
        });

        $this->readConfigs();

        if (@$this->configs['enableBlog']) {
            $this->readBlogPostsData($blogPostsFiles);
        }

        $this->buildViewsData();

        $this->filesystem->cleanDirectory(KATANA_PUBLIC_DIR);

        if ($this->forceBuild) {
            $this->filesystem->cleanDirectory(KATANA_CACHE_DIR);
        }

        $this->handleSiteFiles($otherFiles);

        if (@$this->configs['enableBlog']) {
            $this->handleBlogPostsFiles($blogPostsFiles);
            $this->buildBlogPagination();
        }
    }

    public function setConfig($key, $value) { // 设置配置
        $this->configs[$key] = $value;
    }
    private function readConfigs() { // 读取配置
        $configs = include getcwd().'/config.php';

        if (
            $this->environment != 'default' &&
            $this->filesystem->exists(getcwd().'/'.$fileName = "config-{$this->environment}.php")
        ) {
            $configs = array_merge($configs, include getcwd().'/'.$fileName);
        }

        $this->configs = array_merge($configs, (array) $this->configs);
    }

    private function handleSiteFiles($files) {
        foreach ($files as $file) {
            $this->fileHandler->handle($file);
        }
    }
    private function handleBlogPostsFiles($files) {
        foreach ($files as $file) {
            $this->blogPostHandler->handle($file);
        }
    }

    private function getSiteFiles() {
        $files = array_filter($this->filesystem->allFiles(KATANA_CONTENT_DIR), function (SplFileInfo $file) {
            return ! Str::startsWith($file->getRelativePathName(), $this->includesDirectory);
        });

        if ($this->filesystem->exists(KATANA_CONTENT_DIR.'/.htaccess')) {
            $files[] = new SplFileInfo(KATANA_CONTENT_DIR.'/.htaccess', '', '.htaccess');
        }

        return $files;
    }
    private function readBlogPostsData($files) {
        foreach ($files as $file) {
            $this->postsData[] = $this->blogPostHandler->getPostData($file);
        }
    }
    private function buildViewsData() {
        $this->viewsData = $this->configs + ['blogPosts' => array_reverse((array) $this->postsData)];

        $this->fileHandler->viewsData = $this->viewsData;

        $this->blogPostHandler->viewsData = $this->viewsData;
    }
    private function buildBlogPagination() {
        $builder = new BlogPaginationBuilder(
            $this->filesystem,
            $this->viewFactory,
            $this->viewsData
        );

        $builder->build();
    }
}
