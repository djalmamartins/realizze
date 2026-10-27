<?php
declare(strict_types=1);

namespace Moves\Core;

use InvalidArgumentException;
use MovesCode\View\Engine;

/**
 * Adaptador para o motor de templates já utilizado no Realizze.
 */
final class View
{
    private Engine $engine;

    public function __construct(string $path, string $ext = 'php')
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException('Diretório de templates inexistente.');
        }
        $this->engine = new Engine($path, $ext);
    }

    public function path(string $name, string $path): self
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException('Diretório de componentes inexistente.');
        }
        $this->engine->addFolder($name, $path);
        return $this;
    }

    public function render(string $templateName, array $data = []): string
    {
        return $this->engine->render($templateName, $data);
    }

    public function engine(): Engine
    {
        return $this->engine;
    }
}
