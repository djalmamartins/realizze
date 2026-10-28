<?php
declare(strict_types=1);

namespace Moves\Support;

use MovesCode\Pager\Pager as MovesPager;

final class Pager
{
    private MovesPager $pager;

    public function __construct(?string $link = null, ?string $title = null, ?array $first = null, ?array $last = null)
    {
        $this->pager = new MovesPager($link, $title, $first, $last);
    }

    public function pager(int $total, int $limit = 10, int $page = 1, int $range = 3): self
    {
        $this->pager->pager($total, $limit, $page, $range);
        return $this;
    }

    public function limit(): int { return $this->pager->limit(); }
    public function offset(): int { return $this->pager->offset(); }
    public function page(): int { return $this->pager->page(); }
    public function pages(): int { return $this->pager->pages(); }

    public function render(?string $cssClass = null, bool $fixedFirstAndLastPage = true): ?string
    {
        return $this->pager->render($cssClass, $fixedFirstAndLastPage);
    }

    public function firstPage(bool $fixed = true): ?string { return $this->pager->firstPage($fixed); }
    public function lastPage(bool $fixed = true): ?string { return $this->pager->lastPage($fixed); }
    public function engine(): MovesPager { return $this->pager; }
}
