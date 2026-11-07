<?php
declare(strict_types=1);

namespace Moves\App;

use InvalidArgumentException;
use Moves\Services\AppError;
use Moves\Services\AppUrl;
use Moves\Services\Csrf;
use Moves\Services\OrganizationContext;
use Moves\Services\Organizations;
use Moves\Services\OrganizationLogos;
use Moves\Services\SessionAuth;
use Throwable;

/** Dados cadastrais da empresa da sessão. A permissão é validada em Organizations::update. */
final class CompanyActions
{
    public function handle(string $action): void
    {
        $user = SessionAuth::current();
        $organization = OrganizationContext::current();
        if ($user === null || $organization === null || !Csrf::validate($_POST['csrf_token'] ?? null)) {
            AppError::render(403);
            return;
        }
        try {
            $message = match ($action) {
                'update' => Organizations::update((int) $organization['id'], (int) $user['id'], $_POST) === []
                    ? ['success', 'Nada foi alterado.']
                    : ['success', 'Dados da empresa salvos.'],
                'logo' => [OrganizationLogos::replace((int) $organization['id'], (int) $user['id'], $_FILES['logo'] ?? null) !== '' ? 'success' : 'error', 'Logotipo atualizado.'],
                'logo_remove' => $this->removeLogo((int) $organization['id'], (int) $user['id']),
                default => throw new InvalidArgumentException('Ação inválida.'),
            };
        } catch (InvalidArgumentException $exception) {
            $message = ['error', $exception->getMessage()];
            $_SESSION['company_form'] = array_intersect_key($_POST, array_flip(['name', 'trade_name', 'cnpj', 'email', 'phone', 'tax_regime']));
        } catch (Throwable $exception) {
            error_log('Realizze company: ' . $exception->getMessage());
            $message = ['error', 'Não foi possível salvar. Tente novamente.'];
        }
        $_SESSION['finance_flash'] = $message;
        header('Cache-Control: no-store');
        header('Location: ' . AppUrl::section('empresa'), true, 303);
    }

    private function removeLogo(int $organizationId, int $userId): array
    {
        OrganizationLogos::remove($organizationId, $userId);
        return ['success', 'Logotipo removido. A identidade padrão voltou a ser usada.'];
    }
}
