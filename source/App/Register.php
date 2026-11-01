<?php
declare(strict_types=1);
namespace Moves\App;
use Moves\Services\{Csrf,RegistrationFlow};
final class Register
{
    public function submit(): array
    {
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!Csrf::validate($_POST['csrf_token']??null)) throw new \InvalidArgumentException('Atualize a página e tente novamente.');
        $name=preg_split('/\s+/',trim((string)($_POST['name']??'')),2)?:[];
        if(count($name)<2) throw new \InvalidArgumentException('Informe nome e sobrenome.');
        if((string)($_POST['password']??'')!==(string)($_POST['password_confirmation']??'')) throw new \InvalidArgumentException('As senhas não conferem.');
        return (new RegistrationFlow())->registerAndSendWithPhone($name[0],$name[1],(string)($_POST['email']??''),(string)($_POST['phone']??''),(string)($_POST['password']??''),isset($_POST['terms']),isset($_POST['privacy']),(string)($_SERVER['REMOTE_ADDR']??''),(string)($_SERVER['HTTP_USER_AGENT']??''));
    }
}
