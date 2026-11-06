<?php
declare(strict_types=1);

namespace Moves\Services;

use DateTimeImmutable;

/** Endereços de origem para linhas gerenciais; o destino sempre revalida contexto e permissão. */
final class ReportTrace
{
    public static function entry(int $entryId, string $kind, string $dueDate): ?string
    {
        if ($entryId <= 0 || !self::validDate($dueDate)) return null;
        $section = match ($kind) {
            'income' => 'receber',
            'expense' => 'pagar',
            'transfer' => 'movimentacoes',
            default => null,
        };
        if ($section === null) return null;
        $date = new DateTimeImmutable($dueDate);
        $url = AppUrl::section($section, null, [
            'inicio' => $date->modify('first day of this month')->format('Y-m-d'),
            'fim' => $date->modify('last day of this month')->format('Y-m-d'),
        ]);
        return $url . '#lancamento-' . $entryId;
    }

    public static function serviceOrder(int $orderId): ?string
    {
        return $orderId > 0 ? AppUrl::section('ordens-de-servico') . '#ordem-' . $orderId : null;
    }

    public static function obligation(int $obligationId, string $start, string $end): ?string
    {
        if ($obligationId <= 0 || !self::validDate($start) || !self::validDate($end) || $end < $start) return null;
        return AppUrl::section('obrigacoes', null, ['inicio' => $start, 'fim' => $end])
            . '#obrigacao-' . $obligationId;
    }

    public static function reference(string $prefix, int $id): string
    {
        $prefix = strtoupper(trim($prefix));
        return preg_match('/^[A-Z]{2,8}$/D', $prefix) === 1 && $id > 0 ? $prefix . '-' . $id : '';
    }

    private static function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
