<?php
declare(strict_types=1);
namespace Moves\App;
use Moves\Services\{AuthRateLimiter,Csrf,RegistrationFlow,VerificationMailer};
use MovesCode\View\Engine;
final class Register
{
    public function __construct(private readonly RegistrationFlow $flow=new RegistrationFlow(),private readonly ?VerificationMailer $mailer=null){}

    public static function handle(Engine $view): void
    {
        header('Cache-Control: no-store');
        try{(new self())->submit();header('Location: /cadastrar?enviado=1',true,303);return;}
        catch(\InvalidArgumentException $e){http_response_code(422);$error=$e->getMessage();}
        catch(\Throwable $e){error_log('Realizze cadastro: '.$e->getMessage());http_response_code(500);$error='Não foi possível concluir o cadastro. Tente novamente em instantes.';}
        echo $view->render('pages/app-register',['registerError'=>$error,'registerSuccess'=>false]);
    }

    public function submit(): void
    {
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!Csrf::validate($_POST['csrf_token']??null)) throw new \InvalidArgumentException('Atualize a página e tente novamente.');
        // Campo oculto: robôs preenchem, pessoas não. Responde como sucesso e não grava.
        if(($_POST['website']??'')!=='')return;
        $fullName=self::text('name');$email=mb_strtolower(trim(self::text('email')));$password=self::text('password');
        $name=preg_split('/\s+/',trim($fullName),2)?:[];
        if(count($name)<2||mb_strlen($fullName)>160) throw new \InvalidArgumentException('Informe nome e sobrenome.');
        if(strlen($email)>254) throw new \InvalidArgumentException('E-mail inválido.');
        if(strlen($password)>4096) throw new \InvalidArgumentException('Senha muito longa.');
        if($password!==self::text('password_confirmation')) throw new \InvalidArgumentException('As senhas não conferem.');
        $limiter=new AuthRateLimiter();
        $ipAllowed=$limiter->hit('register-ip',(string)($_SERVER['REMOTE_ADDR']??'cli'),20,3600);
        $emailAllowed=$limiter->hit('register-account',$email,10,3600);
        if(!$ipAllowed||!$emailAllowed) throw new \InvalidArgumentException('Muitas tentativas em pouco tempo. Tente novamente mais tarde.');
        $this->flow->signUp($name[0],$name[1],$email,self::text('phone'),$password,isset($_POST['terms']),isset($_POST['privacy']),(string)($_SERVER['REMOTE_ADDR']??''),mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255),$this->mailer);
    }

    /** Resposta sempre neutra: não revela se o e-mail existe nem o limite atingido. */
    public function resend(): void
    {
        $email=mb_strtolower(trim(self::text('email')));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)return;
        try{
            $limiter=new AuthRateLimiter();
            $ipAllowed=$limiter->hit('resend-ip',(string)($_SERVER['REMOTE_ADDR']??'cli'),15,1800);
            $emailAllowed=$limiter->hit('resend-account',$email,3,1800);
            if($ipAllowed&&$emailAllowed)$this->flow->resend($email,$this->mailer);
        }catch(\Throwable){error_log('Realizze cadastro: falha ao reenviar a confirmação.');}
    }

    private static function text(string $key): string
    {
        return is_string($_POST[$key]??null)?$_POST[$key]:'';
    }
}
