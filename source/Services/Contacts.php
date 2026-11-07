<?php
declare(strict_types=1);

namespace Moves\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;

/** Cadastro unificado e isolado por contexto de clientes e fornecedores. */
final class Contacts
{
    private PDO $pdo;

    public function __construct(private readonly int $contextId)
    {
        if ($contextId <= 0) throw new InvalidArgumentException('Contexto financeiro inválido.');
        $this->pdo = Connection::get();
    }

    public function create(array $data, int $userId): int
    {
        $f = $this->validate($data);
        $sql = 'INSERT INTO finance_contacts (financial_context_id, person_type, legal_name, trade_name, social_name,
            document_type, document_value, state_registration, municipal_registration, email, phone, mobile, website,
            birth_date, postal_code, street, address_number, complement, district, city, state, contact_person, notes,
            is_customer, is_supplier, status, created_by, updated_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        try {
            $this->pdo->prepare($sql)->execute([$this->contextId, ...array_values($f), $userId, $userId]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') throw new InvalidArgumentException('Já existe um contato com este documento neste contexto.');
            throw $e;
        }
        $id = (int) $this->pdo->lastInsertId();
        $this->audit($id, 'created', $userId, ['person_type' => $f['person_type'], 'roles' => [$f['is_customer'], $f['is_supplier']]]);
        return $id;
    }

    public function update(int $id, array $data, int $userId): void
    {
        $this->find($id);
        $f = $this->validate($data);
        $sets = implode(' = ?, ', array_keys($f)) . ' = ?';
        try {
            $statement = $this->pdo->prepare("UPDATE finance_contacts SET {$sets}, updated_by = ? WHERE id = ? AND financial_context_id = ?");
            $statement->execute([...array_values($f), $userId, $id, $this->contextId]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') throw new InvalidArgumentException('Já existe um contato com este documento neste contexto.');
            throw $e;
        }
        $this->audit($id, 'updated', $userId, ['person_type' => $f['person_type'], 'roles' => [$f['is_customer'], $f['is_supplier']]]);
    }

    public function setActive(int $id, bool $active, int $userId): void
    {
        $this->find($id);
        $this->pdo->prepare('UPDATE finance_contacts SET status = ?, updated_by = ? WHERE id = ? AND financial_context_id = ?')
            ->execute([$active ? 'active' : 'inactive', $userId, $id, $this->contextId]);
        $this->audit($id, $active ? 'activated' : 'inactivated', $userId, []);
    }

    public function find(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM finance_contacts WHERE id = ? AND financial_context_id = ?');
        $statement->execute([$id, $this->contextId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new InvalidArgumentException('Contato não encontrado.');
        return $row;
    }

    public function listing(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(50, max(5, (int) ($filters['per_page'] ?? 15)));
        $where = ['financial_context_id = ?']; $params = [$this->contextId];
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            // Documento: números ou CNPJ alfanumérico, sem máscara. Só vale como documento se houver algum número.
            $digits = preg_match('/\d/', $search) === 1 && preg_match('/^[0-9A-Za-z.\/\s-]+$/', $search) === 1 ? Organizations::normalizeCnpj($search) : '';
            $like = '%' . mb_substr($search, 0, 180) . '%';
            $where[] = $digits === ''
                ? '(legal_name LIKE ? OR trade_name LIKE ? OR social_name LIKE ?)'
                : '(legal_name LIKE ? OR trade_name LIKE ? OR social_name LIKE ? OR document_value LIKE ?)';
            array_push($params, $like, $like, $like);
            if ($digits !== '') $params[] = '%' . mb_substr($digits, 0, 14) . '%';
        }
        $role = $filters['role'] ?? '';
        if ($role === 'customer') $where[] = 'is_customer = 1 AND is_supplier = 0';
        elseif ($role === 'supplier') $where[] = 'is_supplier = 1 AND is_customer = 0';
        elseif ($role === 'both') $where[] = 'is_customer = 1 AND is_supplier = 1';
        if (in_array($filters['type'] ?? '', ['individual','company'], true)) { $where[] = 'person_type = ?'; $params[] = $filters['type']; }
        if (in_array($filters['status'] ?? '', ['active','inactive'], true)) { $where[] = 'status = ?'; $params[] = $filters['status']; }
        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT *, COUNT(*) OVER() AS total_rows FROM finance_contacts WHERE ' . implode(' AND ', $where)
            . " ORDER BY legal_name, id LIMIT {$perPage} OFFSET {$offset}";
        $statement = $this->pdo->prepare($sql); $statement->execute($params); $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $total = $rows === [] ? 0 : (int) $rows[0]['total_rows'];
        foreach ($rows as &$row) unset($row['total_rows']);
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/$perPage)),'per_page'=>$perPage];
    }

    public function selectableCustomers(): array
    {
        $s = $this->pdo->prepare("SELECT id, legal_name, trade_name FROM finance_contacts WHERE financial_context_id = ? AND status = 'active' AND is_customer = 1 ORDER BY legal_name");
        $s->execute([$this->contextId]); return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function selectableSuppliers(): array
    {
        $s = $this->pdo->prepare("SELECT id, legal_name, trade_name FROM finance_contacts WHERE financial_context_id = ? AND status = 'active' AND is_supplier = 1 ORDER BY legal_name");
        $s->execute([$this->contextId]); return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function financialSummary(int $id): array
    {
        $this->find($id);
        $s = $this->pdo->prepare("SELECT COUNT(*) entries, COALESCE(SUM(amount_cents),0) total_cents FROM finance_entries WHERE financial_context_id = ? AND contact_id = ? AND kind = 'income'");
        $s->execute([$this->contextId, $id]); return array_map('intval', $s->fetch(PDO::FETCH_ASSOC));
    }

    public static function validCpf(string $cpf): bool
    {
        if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1+$/', $cpf)) return false;
        for ($t = 9; $t < 11; $t++) { $sum=0; for($i=0;$i<$t;$i++) $sum += (int)$cpf[$i] * (($t+1)-$i); $digit=(10*$sum)%11; if($digit===10)$digit=0; if((int)$cpf[$t]!==$digit)return false; }
        return true;
    }

    private function validate(array $d): array
    {
        $type = in_array($d['person_type'] ?? '', ['individual','company'], true) ? $d['person_type'] : throw new InvalidArgumentException('Escolha pessoa física ou jurídica.');
        $name = trim((string)($d['legal_name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 180) throw new InvalidArgumentException('Informe o nome ou razão social.');
        $customer = isset($d['is_customer']) ? 1 : 0; $supplier = isset($d['is_supplier']) ? 1 : 0;
        if (!$customer && !$supplier) throw new InvalidArgumentException('Marque cliente, fornecedor ou ambos.');
        // CPF só tem números; CNPJ pode ter letras nas 12 primeiras posições (formato alfanumérico).
        $document = $type === 'company' ? Organizations::normalizeCnpj((string)($d['document'] ?? '')) : (preg_replace('/\D+/', '', (string)($d['document'] ?? '')) ?? '');
        if ($document !== '' && (($type === 'individual' && !self::validCpf($document)) || ($type === 'company' && !Organizations::validCnpj($document)))) throw new InvalidArgumentException($type === 'individual' ? 'CPF inválido.' : 'CNPJ inválido.');
        $email = $this->text($d['email'] ?? null, 254);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) throw new InvalidArgumentException('E-mail inválido.');
        $birth = $this->date($d['birth_date'] ?? null);
        $state = strtoupper(trim((string)($d['state'] ?? ''))); if ($state !== '' && !preg_match('/^[A-Z]{2}$/',$state)) throw new InvalidArgumentException('Estado inválido.');
        return [
            'person_type'=>$type,'legal_name'=>$name,'trade_name'=>$this->text($d['trade_name']??null,180),'social_name'=>$this->text($d['social_name']??null,180),
            'document_type'=>$document===''?null:($type==='individual'?'cpf':'cnpj'),'document_value'=>$document===''?null:$document,
            'state_registration'=>$this->text($d['state_registration']??null,30),'municipal_registration'=>$this->text($d['municipal_registration']??null,30),
            'email'=>$email,'phone'=>$this->text($d['phone']??null,24),'mobile'=>$this->text($d['mobile']??null,24),'website'=>$this->text($d['website']??null,255),
            'birth_date'=>$birth,'postal_code'=>($v=preg_replace('/\D+/','',(string)($d['postal_code']??'')))===''?null:mb_substr($v,0,8),
            'street'=>$this->text($d['street']??null,180),'address_number'=>$this->text($d['address_number']??null,30),'complement'=>$this->text($d['complement']??null,100),
            'district'=>$this->text($d['district']??null,100),'city'=>$this->text($d['city']??null,100),'state'=>$state===''?null:$state,
            'contact_person'=>$this->text($d['contact_person']??null,180),'notes'=>$this->text($d['notes']??null,5000),
            'is_customer'=>$customer,'is_supplier'=>$supplier,'status'=>in_array($d['status']??'active',['active','inactive'],true)?($d['status']??'active'):'active',
        ];
    }

    private function text(mixed $v, int $max): ?string { $v=trim((string)$v); if($v==='')return null; if(mb_strlen($v)>$max)throw new InvalidArgumentException('Campo excede o limite permitido.'); return $v; }
    private function date(mixed $v): ?string { if($v===null||$v==='')return null; $x=is_string($v)?DateTimeImmutable::createFromFormat('!Y-m-d',$v):false; if(!$x||$x->format('Y-m-d')!==$v)throw new InvalidArgumentException('Data inválida.'); return $v; }
    private function audit(int $id,string $action,int $user,array $meta):void { $this->pdo->prepare('INSERT INTO finance_contact_audit_logs (financial_context_id,contact_id,action,actor_user_id,metadata_json) VALUES (?,?,?,?,?)')->execute([$this->contextId,$id,$action,$user,json_encode($meta,JSON_THROW_ON_ERROR)]); }
}
