<?php

namespace Katana;

use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Factory;

class Blade
{
    private $bladeCompiler;

    public function __construct(BladeCompiler $bladeCompiler)
    {
        $this->bladeCompiler = $bladeCompiler;

        $this->registerMarkdownDirective();

        $this->registerURLDirective();
    }

    public function getCompiler()
    {
        return $this->bladeCompiler;
    }
    private function registerMarkdownDirective()
    {
        $this->bladeCompiler->directive('markdown', function () {
            return "<?php echo \\Katana\\Markdown::parse(<<<'EOT'";
        });

        $this->bladeCompiler->directive('endmarkdown', function () {
            return "\nEOT\n); ?>";
        });
    }
    private function registerURLDirective()
    {
        $this->bladeCompiler->directive('url', function ($expression) {
            $expression = substr($expression, 1, - 1);

            $trailingSlash = ! str_contains($expression, '.') ? '/' : '';

            return "<?php echo str_replace(['///', '//'], '/', \$base_url.'/'.trim({$expression}, '/').'{$trailingSlash}');  ?>";
        });
    }
}
