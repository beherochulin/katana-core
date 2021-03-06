<?php
namespace BeeSoft;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Factory;

class BlogPaginationBuilder {
    private $filesystem;
    private $viewFactory;
    private $viewsData;
    private $pagesData;

    public function __construct(Filesystem $filesystem, Factory $viewFactory, array $viewsData) {
        $this->filesystem = $filesystem;
        $this->viewFactory = $viewFactory;
        $this->viewsData = $viewsData;
    }
    public function build() {
        $view = $this->getPostsListView();

        $postsPerPage = @$this->viewsData['postsPerPage'] ?: 5;

        $this->pagesData = array_chunk($this->viewsData['blogPosts'], $postsPerPage);

        foreach ( $this->pagesData as $pageIndex => $posts ) {
            $this->buildPage($pageIndex, $view, $posts);
        }
    }
    private function getPostsListView() {
        if ( ! isset($this->viewsData['postsListView']) ) {
            throw new \Exception('The postsListView config value is missing.');
        }

        if (! $this->viewFactory->exists($this->viewsData['postsListView'])) {
            throw new \Exception(sprintf('The "%s" view is not found. Make sure the postsListView configuration key is correct.', $this->viewsData['postsListView']));
        }

        return $this->viewsData['postsListView'];
    }
    private function buildPage($pageIndex, $view, $posts) {
        $viewData = array_merge(
            $this->viewsData,
            [
                'paginatedBlogPosts' => $posts,
                'previousPage' => $this->getPreviousPageLink($pageIndex),
                'nextPage' => $this->getNextPageLink($pageIndex),
            ]
        );

        $pageContent = $this->viewFactory->make($view, $viewData)->render();

        $directory = sprintf('%s/blog-page/%d', KATANA_PUBLIC_DIR, $pageIndex + 1);

        $this->filesystem->makeDirectory($directory, 0755, true);

        $this->filesystem->put(
            sprintf('%s/%s', $directory, 'index.html'),
            $pageContent
        );
    }
    private function getPreviousPageLink($currentPageIndex) {
        if ( ! isset($this->pagesData[$currentPageIndex - 1]) ) {
            return null;
        } elseif ($currentPageIndex == 1) {
            // If the current page is the second, then the first page's
            // link should point to the blog's main view.
            return '/'.$this->getBlogListPagePath();
        }

        return '/blog-page/'.$currentPageIndex.'/';
    }
    private function getNextPageLink($currentPageIndex) {
        if ( ! isset($this->pagesData[$currentPageIndex + 1]) ) return null;

        return '/blog-page/'.($currentPageIndex + 2).'/';
    }
    private function getBlogListPagePath() {
        $path = str_replace('.', '/', $this->viewsData['postsListView']);

        if ( ends_with($path, 'index') ) return rtrim($path, '/index');

        return $path;
    }
}