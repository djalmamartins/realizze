<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;
use RuntimeException;
use Throwable;

final class RegistrationFlow
{
    public function __construct(
        private readonly Registration $registration = new Registration(),
        private readonly EmailVerification $verification = new EmailVerification()
    ) {
    }

    public function registerAndSend(
        string $firstName,
        string $lastName,
        string $email,
        string $password,
        bool $acceptedTerms,
        bool $acceptedPrivacy,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?VerificationMailer $mailer = null
    ): array {
        $result = $this->register(
            $firstName,
            $lastName,
            $email,
            $password,
            $acceptedTerms,
            $acceptedPrivacy,
            $ipAddress,
            $userAgent
        );

        // O cadastro e o token já foram persistidos.
        // Uma falha SMTP não ativa nem remove a conta.
        ($mailer ?? new VerificationMailer())->send(
            $email,
            $firstName,
            $result['verification_token']
        );

        return [
            'user_id' => $result['user_id'],
            'status' => $result['status'],
        ];
    }

    public function registerAndSendWithPhone(string $firstName,string $lastName,string $email,string $phone,string $password,bool $acceptedTerms,bool $acceptedPrivacy,?string $ipAddress=null,?string $userAgent=null,?VerificationMailer $mailer=null): array
    {
        $result=$this->registerWithPhone($firstName,$lastName,$email,$phone,$password,$acceptedTerms,$acceptedPrivacy,$ipAddress,$userAgent);
        ($mailer??new VerificationMailer())->send($email,$firstName,$result['verification_token']);
        return ['user_id'=>$result['user_id'],'status'=>$result['status']];
    }

    /**
     * Cadastro público: a resposta é a mesma com e-mail novo ou já usado,
     * e uma falha de envio não trava a conta, que pode pedir novo link.
     */
    public function signUp(string $firstName,string $lastName,string $email,string $phone,string $password,bool $acceptedTerms,bool $acceptedPrivacy,?string $ipAddress=null,?string $userAgent=null,?VerificationMailer $mailer=null): void
    {
        $mailer??=new VerificationMailer();
        try{
            $result=$this->registerWithPhone($firstName,$lastName,$email,$phone,$password,$acceptedTerms,$acceptedPrivacy,$ipAddress,$userAgent);
        }catch(EmailAlreadyRegistered){
            $this->notifyExisting($email,$mailer);
            return;
        }
        try{$mailer->send($email,$firstName,$result['verification_token']);}
        catch(Throwable){error_log('Realizze cadastro: falha ao enviar a confirmação.');}
    }

    /** Novo link de confirmação. Só contas aguardando confirmação recebem. */
    public function resend(string $email,?VerificationMailer $mailer=null): void
    {
        $user=$this->findByEmail($email);
        if($user!==null&&$user['status']==='registered')$this->sendNewLink($user,$mailer??new VerificationMailer());
    }

    private function notifyExisting(string $email,VerificationMailer $mailer): void
    {
        $user=$this->findByEmail($email);
        if($user===null)return;
        if($user['status']==='registered'){$this->sendNewLink($user,$mailer);return;}
        if($user['status']!=='active')return;
        try{$mailer->sendAlreadyRegistered($email,(string)$user['first_name']);}
        catch(Throwable){error_log('Realizze cadastro: falha ao avisar conta existente.');}
    }

    private function sendNewLink(array $user,VerificationMailer $mailer): void
    {
        try{$mailer->send((string)$user['email'],(string)$user['first_name'],$this->verification->issue((int)$user['id']));}
        catch(Throwable){error_log('Realizze cadastro: falha ao reenviar a confirmação.');}
    }

    private function findByEmail(string $email): ?array
    {
        $statement=Connection::get()->prepare('SELECT id,first_name,email,status FROM users WHERE email=? LIMIT 1');
        $statement->execute([trim($email)]);
        return $statement->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public function registerWithPhone(string $firstName,string $lastName,string $email,string $phone,string $password,bool $acceptedTerms,bool $acceptedPrivacy,?string $ipAddress=null,?string $userAgent=null): array
    {
        $pdo=Connection::get();
        if($pdo->inTransaction()) throw new RuntimeException('RegistrationFlow exige uma transação própria.');
        $pdo->beginTransaction();
        try{
            $userId=$this->registration->register($firstName,$lastName,$email,$password,$acceptedTerms,$acceptedPrivacy,'v1','v1',$ipAddress,$userAgent,$phone);
            $token=$this->verification->issue($userId);$pdo->commit();
            return ['user_id'=>$userId,'verification_token'=>$token,'status'=>'registered'];
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function register(
        string $firstName,
        string $lastName,
        string $email,
        string $password,
        bool $acceptedTerms,
        bool $acceptedPrivacy,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $pdo = Connection::get();

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'RegistrationFlow exige uma transação própria.'
            );
        }

        $pdo->beginTransaction();

        try {
            $userId = $this->registration->register(
                $firstName,
                $lastName,
                $email,
                $password,
                $acceptedTerms,
                $acceptedPrivacy,
                'v1',
                'v1',
                $ipAddress,
                $userAgent
            );

            $token = $this->verification->issue($userId);

            $pdo->commit();

            return [
                'user_id' => $userId,
                'verification_token' => $token,
                'status' => 'registered',
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
