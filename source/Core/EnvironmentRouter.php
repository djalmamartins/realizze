<?php
declare(strict_types=1);

namespace Moves\Core;

use InvalidArgumentException;
use RuntimeException;

/**
 * Seleciona rotas por hostname explícito, nunca por caminho fornecido na requisição.
 * Ambientes adicionais só ficam ativos quando seu hostname é configurado.
 */
final class EnvironmentRouter
{
    private const TYPES = ['web', 'app', 'cms', 'erp', 'send'];

    public static function resolve(string $host, array $config, string $root): array
    {
        $host = self::hostname($host);
        foreach ($config as $type => $settings) {
            if (!in_array($type, self::TYPES, true) || !is_array($settings)) {
                throw new InvalidArgumentException('Ambiente desconhecido.');
            }
            $hostname = self::hostname((string) ($settings['host'] ?? ''));
            if ($hostname !== $host) {
                continue;
            }
            $theme = (string) ($settings['theme'] ?? '');
            $version = (string) ($settings['version'] ?? '');
            if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $theme)
                || !preg_match('/^v[0-9]+$/D', $version)) {
                throw new InvalidArgumentException('Tema ou versão inválidos.');
            }
            $path = rtrim($root, '/') . '/container/' . $type . '/' . $theme . '/' . $version . '/routes.php';
            if (!is_file($path)) {
                throw new RuntimeException('Rotas do ambiente não encontradas.');
            }
            return ['type' => $type, 'theme' => $theme, 'version' => $version, 'path' => $path];
        }
        throw new RuntimeException('Hostname não configurado.');
    }

    public static function hostname(string $host): string
    {
        $host = strtolower(trim($host));
        if (!preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/D', $host)) {
            throw new InvalidArgumentException('Hostname inválido.');
        }
        return preg_replace('/:[0-9]{1,5}$/', '', $host);
    }

    public static function configuration(): array
    {
        $webHost = parse_url((string) (getenv('APP_URL') ?: 'https://www.realizze.lab'), PHP_URL_HOST);
        if (!is_string($webHost) || $webHost === '') {
            throw new RuntimeException('APP_URL inválida.');
        }
        $config = ['web' => ['host' => $webHost, 'theme' => 'realizze', 'version' => 'v1']];
        foreach (['app', 'cms', 'erp', 'send'] as $type) {
            $prefix = strtoupper($type);
            $host = getenv('ROUTE_' . $prefix . '_HOST');
            if (!is_string($host) || $host === '') {
                continue;
            }
            $config[$type] = [
                'host' => $host,
                'theme' => getenv('ROUTE_' . $prefix . '_THEME') ?: 'realizze',
                'version' => getenv('ROUTE_' . $prefix . '_VERSION') ?: 'v1',
            ];
        }
        $hosts = array_map(static fn(array $item): string => self::hostname($item['host']), $config);
        if (count($hosts) !== count(array_unique($hosts))) {
            throw new RuntimeException('Hostnames duplicados entre ambientes.');
        }
        return $config;
    }
}
