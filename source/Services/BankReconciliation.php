<?php
declare(strict_types=1);
namespace Moves\Services;
use DateTimeImmutable;
use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;

/**
 * Importa extratos (CSV ou OFX) para revisão. Conciliar uma linha só a vincula a um lançamento
 * já existente, da mesma conta, com o mesmo valor e sentido: nada aqui cria lançamento ou mexe em saldo.
 */
final class BankReconciliation
{
    private const STATUSES = ['pending', 'matched', 'ignored', 'reversed'];
    private PDO $pdo;
    public function __construct(private readonly int $contextId) { $this->pdo=Connection::get(); }

    public function lines(array $filters = []): array
    {
        $where = ['l.financial_context_id=?'];
        $args = [$this->contextId];
        if (in_array($filters['status'] ?? '', self::STATUSES, true)) { $where[] = 'l.status=?'; $args[] = $filters['status']; }
        $account = filter_var($filters['account_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($account !== false) { $where[] = 'l.account_id=?'; $args[] = $account; }
        $q=$this->pdo->prepare('SELECT l.*,a.name account,e.description entry_description,e.due_date entry_date FROM finance_reconciliation_lines l INNER JOIN finance_accounts a ON a.id=l.account_id LEFT JOIN finance_entries e ON e.id=l.matched_entry_id WHERE '.implode(' AND ',$where).' ORDER BY l.status=\'pending\' DESC,l.occurred_on DESC,l.id DESC LIMIT 500');
        $q->execute($args);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,int> quantidade de linhas por situação */
    public function counts(): array
    {
        $q=$this->pdo->prepare('SELECT status,COUNT(*) total FROM finance_reconciliation_lines WHERE financial_context_id=? GROUP BY status');
        $q->execute([$this->contextId]);
        return array_map('intval', array_column($q->fetchAll(PDO::FETCH_ASSOC), 'total', 'status')) + array_fill_keys(self::STATUSES, 0);
    }

    /**
     * Lançamentos que podem corresponder a cada linha pendente: mesma conta, mesmo valor e sentido,
     * até 7 dias de diferença e ainda sem outra linha conciliada. Só sugestão; quem decide é a pessoa.
     *
     * @return array<int, list<array<string,mixed>>> por id da linha
     */
    public function suggestions(array $lines): array
    {
        $result = [];
        $find = $this->pdo->prepare(
            "SELECT e.id,e.kind,e.description,e.due_date,e.paid_at,e.amount_cents FROM finance_entries e
             WHERE e.financial_context_id=? AND e.amount_cents=? AND (e.status IS NULL OR e.status<>'cancelled')
               AND ((? > 0 AND ((e.kind='income' AND e.account_id=?) OR (e.kind='transfer' AND e.target_account_id=?)))
                 OR (? < 0 AND (e.kind IN ('expense','transfer') AND e.account_id=?)))
               AND COALESCE(e.paid_at,e.due_date) BETWEEN ? AND ?
               AND NOT EXISTS (SELECT 1 FROM finance_reconciliation_lines x WHERE x.matched_entry_id=e.id AND x.status='matched')
             ORDER BY ABS(DATEDIFF(COALESCE(e.paid_at,e.due_date),?)),e.id LIMIT 5"
        );
        foreach ($lines as $line) {
            if ($line['status'] !== 'pending') continue;
            $amount = (int) $line['amount_cents'];
            $date = new DateTimeImmutable((string) $line['occurred_on']);
            $account = (int) $line['account_id'];
            $find->execute([$this->contextId, abs($amount), $amount, $account, $account, $amount, $account, $date->modify('-7 days')->format('Y-m-d'), $date->modify('+7 days')->format('Y-m-d'), $date->format('Y-m-d')]);
            $result[(int) $line['id']] = $find->fetchAll(PDO::FETCH_ASSOC);
        }
        return $result;
    }

    /** Mantém o nome usado pela ação; aceita CSV e OFX. */
    public function importCsv(array $file,mixed $accountId,int $userId): int { return $this->importStatement($file, $accountId, $userId); }

    public function importStatement(array $file,mixed $accountId,int $userId): int
    {
        $account=$this->account($accountId);
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($file['tmp_name']??'')))throw new InvalidArgumentException('Selecione um arquivo CSV ou OFX.');
        if(($file['size']??0)>5*1024*1024)throw new InvalidArgumentException('O arquivo pode ter até 5 MB.');
        $name=basename((string)$file['name']);
        $extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if(!in_array($extension,['csv','ofx'],true))throw new InvalidArgumentException('Envie um arquivo .csv ou .ofx.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if(!in_array($mime,['text/plain','text/csv','application/csv','application/vnd.ms-excel','application/x-ofx','application/ofx','text/xml','application/xml','text/x-sgml'],true))throw new InvalidArgumentException('Envie um extrato em texto, não um arquivo executável.');
        $hash=hash_file('sha256',$file['tmp_name']);
        $same=$this->pdo->prepare('SELECT 1 FROM finance_reconciliation_imports WHERE financial_context_id=? AND account_id=? AND checksum=?');
        $same->execute([$this->contextId,$account,$hash]);
        if($same->fetchColumn()!==false)throw new InvalidArgumentException('Este arquivo já foi importado para esta conta.');
        $raw=(string)file_get_contents($file['tmp_name']);
        $rows=$extension==='ofx'?$this->parseOfx($raw):$this->parseCsv($raw);
        if($rows===[])throw new InvalidArgumentException('Nenhuma linha válida foi encontrada.');
        $base=BASE_PATH.'/storage/reconciliation';
        if(!is_dir($base)&&!mkdir($base,0750,true)&&!is_dir($base))throw new InvalidArgumentException('Não foi possível preparar o armazenamento.');
        $path=$base.'/'.$this->contextId.'-'.bin2hex(random_bytes(16)).'.'.$extension;
        if(!move_uploaded_file($file['tmp_name'],$path))throw new InvalidArgumentException('Não foi possível guardar o arquivo.');
        $this->pdo->beginTransaction();
        try{
            $q=$this->pdo->prepare('INSERT INTO finance_reconciliation_imports (financial_context_id,account_id,original_name,stored_path,mime_type,checksum,imported_by) VALUES (?,?,?,?,?,?,?)');
            $q->execute([$this->contextId,$account,mb_substr($name,0,180),$path,$mime,$hash,$userId]);
            $import=(int)$this->pdo->lastInsertId();
            // A mesma transação repetida no arquivo entra uma vez só.
            $insert=$this->pdo->prepare('INSERT IGNORE INTO finance_reconciliation_lines (financial_context_id,import_id,account_id,occurred_on,description,amount_cents,external_id) VALUES (?,?,?,?,?,?,?)');
            $count=0;
            foreach($rows as $row){
                $insert->execute([$this->contextId,$import,$account,$row['date'],$row['description'],$row['amount'],$row['id']??hash('sha256',$row['date'].'|'.$row['description'].'|'.$row['amount'])]);
                $count+=$insert->rowCount();
            }
            $this->pdo->commit();
            return $count;
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();@unlink($path);throw $e;}
    }

    public function review(mixed $lineId,mixed $entryId,string $status,int $userId): void
    {
        $line=filter_var($lineId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($line===false)throw new InvalidArgumentException('Linha inválida.');
        if(!in_array($status,self::STATUSES,true))throw new InvalidArgumentException('Situação inválida.');
        $find=$this->pdo->prepare('SELECT * FROM finance_reconciliation_lines WHERE id=? AND financial_context_id=?');
        $find->execute([$line,$this->contextId]);
        $row=$find->fetch(PDO::FETCH_ASSOC);
        if($row===false)throw new InvalidArgumentException('Linha não encontrada.');
        $entry=null;
        if($status==='matched'){
            $entry=filter_var($entryId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $allowed=array_map(static fn(array $s):int=>(int)$s['id'],$this->suggestions([$row])[(int)$row['id']]??[]);
            if($entry===false||!in_array($entry,$allowed,true))throw new InvalidArgumentException('Escolha um lançamento da mesma conta, com o mesmo valor e ainda não conciliado.');
        }
        $q=$this->pdo->prepare('UPDATE finance_reconciliation_lines SET status=?,matched_entry_id=?,reviewed_by=?,reviewed_at=? WHERE id=? AND financial_context_id=?');
        $q->execute([$status,$entry,$status==='pending'?null:$userId,$status==='pending'?null:date('Y-m-d H:i:s'),$line,$this->contextId]);
    }

    /** @return list<array{date:string,description:string,amount:int}> */
    private function parseCsv(string $raw): array
    {
        $raw=preg_replace('/^\xEF\xBB\xBF/','',$raw)??$raw;
        if(!mb_check_encoding($raw,'UTF-8'))$raw=mb_convert_encoding($raw,'UTF-8','ISO-8859-1');
        $lines=preg_split('/\r\n|\r|\n/',trim($raw))?:[];
        $first=(string)array_shift($lines);
        $delimiter=substr_count($first,';')>=substr_count($first,',')?';':',';
        $header=array_map(static fn($v)=>mb_strtolower(trim((string)$v)),str_getcsv($first,$delimiter,'"',''));
        $dateIndex=array_search('data',$header,true);
        $descriptionIndex=array_search('descrição',$header,true);
        if($descriptionIndex===false)$descriptionIndex=array_search('descricao',$header,true);
        $amountIndex=array_search('valor',$header,true);
        if($dateIndex===false||$descriptionIndex===false||$amountIndex===false)throw new InvalidArgumentException('O CSV precisa das colunas Data, Descrição e Valor.');
        $rows=[];
        foreach($lines as $number=>$text){
            if(trim($text)==='')continue;
            $cells=str_getcsv($text,$delimiter,'"','');
            if(count($cells)<=max($dateIndex,$descriptionIndex,$amountIndex))continue;
            $description=mb_substr(trim((string)$cells[$descriptionIndex]),0,255);
            if($description==='')continue;
            try{$rows[]=['date'=>$this->date($cells[$dateIndex]),'description'=>$description,'amount'=>$this->signedCents($cells[$amountIndex])];}
            catch(InvalidArgumentException $e){throw new InvalidArgumentException('Linha '.($number+2).' do CSV: '.$e->getMessage());}
        }
        return $rows;
    }

    /** OFX 1.x (SGML) e 2.x (XML): lê cada STMTTRN pela data, valor, memo/nome e FITID. */
    private function parseOfx(string $raw): array
    {
        if(!mb_check_encoding($raw,'UTF-8'))$raw=mb_convert_encoding($raw,'UTF-8','ISO-8859-1');
        if(!preg_match_all('/<STMTTRN>(.*?)(?=<\/STMTTRN>|<STMTTRN>|<\/BANKTRANLIST>)/is',$raw,$blocks))throw new InvalidArgumentException('O OFX não tem transações.');
        $field=static fn(string $block,string $tag):string=>preg_match('/<'.$tag.'>([^<\r\n]*)/i',$block,$m)?trim(html_entity_decode($m[1],ENT_QUOTES|ENT_XML1,'UTF-8')):'';
        $rows=[];
        foreach($blocks[1] as $block){
            $posted=$field($block,'DTPOSTED');
            $date=DateTimeImmutable::createFromFormat('!Ymd',substr($posted,0,8));
            if($date===false)throw new InvalidArgumentException('Data inválida no OFX.');
            $description=mb_substr($field($block,'MEMO')?:$field($block,'NAME')?:'Sem descrição',0,255);
            $fitid=$field($block,'FITID');
            $rows[]=['date'=>$date->format('Y-m-d'),'description'=>$description,'amount'=>$this->signedCents($field($block,'TRNAMT')),'id'=>$fitid===''?null:mb_substr($fitid,0,160)];
        }
        return $rows;
    }

    private function account(mixed $id): int {$id=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$q=$this->pdo->prepare("SELECT id FROM finance_accounts WHERE id=? AND financial_context_id=? AND type <> 'credit_card' AND archived_at IS NULL AND status='active'");$q->execute([$id,$this->contextId]);if($id===false||$q->fetchColumn()===false)throw new InvalidArgumentException('Conta bancária inválida.');return (int)$id;}
    private function date(mixed $value): string {$value=trim((string)$value);foreach(['!d/m/Y','!Y-m-d'] as $format){$d=DateTimeImmutable::createFromFormat($format,$value);if($d&&$d->format(str_contains($format,'d/m')?'d/m/Y':'Y-m-d')===$value)return $d->format('Y-m-d');}throw new InvalidArgumentException('Data inválida no extrato.');}

    /** Aceita 1.234,56 e -50,00 (CSV brasileiro) ou -1234.56 (OFX). */
    private function signedCents(mixed $value): int
    {
        $v=str_replace(['R$',' '],'',trim((string)$value));
        $negative=str_starts_with($v,'-');
        $v=ltrim($v,'+-');
        $cents=preg_match('/^\d+(\.\d{1,2})?$/',$v)===1&&!str_contains($v,',')&&(strlen(strrchr($v,'.')?:'')!==4)
            ? (int)round(((float)$v)*100)
            : Finance::parseCents($v);
        if($cents===0)throw new InvalidArgumentException('Valor zerado no extrato.');
        return $negative?-$cents:$cents;
    }
}
