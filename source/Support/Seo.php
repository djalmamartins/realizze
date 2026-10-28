<?php
declare(strict_types=1);

namespace Moves\Support;

use MovesCode\Seo\Seo as MovesSeo;

final class Seo
{
    private MovesSeo $seo;

    public function __construct(string $schema = 'article', ?string $siteName = null, string $locale = 'pt_BR')
    {
        $this->seo = new MovesSeo();
        $this->seo->openGraph($siteName ?: (getenv('APP_NAME') ?: 'Realizze'), $locale, $schema);
    }

    public function render(string $title, string $description, string $url, string $image, bool $follow = true): string
    {
        return $this->seo->optimize($title, $description, $url, $image, $follow)->render();
    }

    public function data(?string $title = null, ?string $desc = null, ?string $url = null, ?string $image = null): ?object
    {
        return $this->seo->data($title, $desc, $url, $image);
    }

    public function optimizer(): MovesSeo { return $this->seo; }

    public function __get(string $name): ?string
    {
        return $this->seo->__get($name);
    }
}
