<?php
declare(strict_types=1);

namespace Moves\App;

use InvalidArgumentException;
use Moves\Services\Contacts;
use Moves\Services\Csrf;
use Moves\Services\FinanceContext;
use Moves\Services\SessionAuth;
use Throwable;

final class ContactActions
{
    public function handle(string $action): void
    {
        $user = SessionAuth::current();
        if ($user === null || !Csrf::validate($_POST['csrf_token'] ?? null)) { \Moves\Services\AppError::render(403); return; }
        $context = FinanceContext::currentId();
        if (!$context) { \Moves\Services\AppError::render(403); return; }
        try {
            $contacts = new Contacts($context);
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            $message = match ($action) {
                'create' => ($contacts->create($_POST, (int)$user['id']) > 0 ? ['success','Contato cadastrado.'] : ['error','Não foi possível cadastrar.']),
                'update' => $this->update($contacts, $id, (int)$user['id']),
                'status' => $this->status($contacts, $id, (int)$user['id']),
                default => throw new InvalidArgumentException('Ação inválida.'),
            };
        } catch (InvalidArgumentException $e) { $message=['error',$e->getMessage()]; }
        catch (Throwable $e) { error_log('Realizze contacts: '.$e->getMessage()); $message=['error','Não foi possível concluir a operação.']; }
        $_SESSION['contacts_flash']=$message;
        header('Cache-Control: no-store');
        $back = in_array($_POST['back'] ?? null, ['clientes', 'fornecedores'], true) ? $_POST['back'] : 'contatos';
        header('Location: ' . \Moves\Services\AppUrl::section($back), true, 303);
    }
    private function update(Contacts $c, int|false $id, int $user): array { if($id===false)throw new InvalidArgumentException('Contato inválido.'); $c->update($id,$_POST,$user); return ['success','Contato atualizado.']; }
    private function status(Contacts $c, int|false $id, int $user): array { if($id===false)throw new InvalidArgumentException('Contato inválido.'); $active=($_POST['active']??'0')==='1'; $c->setActive($id,$active,$user); return ['success',$active?'Contato reativado.':'Contato inativado.']; }
}
