<?php
declare(strict_types=1);

namespace Moves\Services;

/** Contrato de um provedor de IA. Explica números; nunca calcula, paga ou contrata. */
interface AiProvider
{
    /**
     * @param array<string,mixed> $minimizedSummary saída de AiAdvisor::minimizedSummary()
     * @return string texto explicativo, exibido como orientação e não como cálculo validado
     */
    public function explain(array $minimizedSummary, string $question): string;
}
