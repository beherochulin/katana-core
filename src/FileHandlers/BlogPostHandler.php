<?php
namespace BeeSoft\FileHandlers;

use Symfony\Component\Finder\SplFileInfo;
use BeeSoft\Markdown;

class BlogPostHandler extends BaseHandler {
    public function getPostData(SplFileInfo $file) {
        $this->file = $file;

        if ($this->file->getExtension() == 'md') {
            $postData = Markdown::parseWithYAML($this->file->getContents())[1];
        } else {
            $view = $this->viewFactory->make(str_replace('.blade.php', '', $this->file->getRelativePathname()));

            $postData = [];

            $view->render(function ($view) use (&$postData) {
                $postData = $view->getFactory()->getSections();
            });
        }

        // Get only values with keys starting with post::
        $postData = array_where($postData, function ($key) {
            return starts_with($key, 'post::');
        });

        // Remove 'post::' from $postData keys
        foreach ($postData as $key => $val) {
            $postData[str_replace('post::', '', $key)] = $val;

            unset($postData[$key]);
        }

        $postData['path'] = str_replace(KATANA_PUBLIC_DIR, '', $this->getDirectoryPrettyName()).'/';

        return json_decode(json_encode($postData), false);
    }
    protected function getDirectoryPrettyName() {
        $pathName = $this->normalizePath($this->file->getPathname());
        
        // If the post is inside a child directory of the _blog directory then
        // we deal with it like regular site files and generate a nested
        // directories based post path with exact file name.
        if ( str_is('*/_blog/*/*', $pathName) ) {
            return str_replace('/_blog', '', parent::getDirectoryPrettyName());
        }

        $fileBaseName = $this->getFileName();

        $fileRelativePath = $this->getBlogPostSlug($fileBaseName);

        return KATANA_PUBLIC_DIR."/$fileRelativePath";
    }
    private function getBlogPostSlug($fileBaseName) {
        preg_match('/^(\d{4}-\d{2}-\d{2})-(.*)/', $fileBaseName, $matches);

        return $matches[2].'-'.str_replace('-', '', $matches[1]);
    }
}
