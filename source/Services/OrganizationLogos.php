<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use MovesCode\Model\Connection;
use RuntimeException;

/** Logotipos empresariais privados, normalizados em JPEG e sem metadados. */
final class OrganizationLogos
{
    private const MAX_BYTES = 5242880;
    private const MAX_PIXELS = 40000000;
    private const MAX_SIDE = 1200;

    public static function ready(): bool
    {
        return Connection::get()->query("SHOW COLUMNS FROM organizations LIKE 'logo_file'")->fetchColumn() !== false;
    }

    public static function url(array $organization): ?string
    {
        return self::ready() && !empty($organization['logo_file'])
            ? '/minha-conta/empresa/logo/' . (int) $organization['id'] . '?v=' . rawurlencode((string) $organization['logo_file'])
            : null;
    }

    /** @param array<string,mixed>|null $file */
    public static function replace(int $organizationId, int $actorId, ?array $file): string
    {
        if (!OrganizationTeam::can($actorId, $organizationId, 'team.manage')) throw new InvalidArgumentException('Você não tem permissão para alterar o logotipo.');
        if (!self::ready()) throw new RuntimeException('A estrutura de logotipos ainda não foi aplicada.');
        $name = self::store($organizationId, $file);
        $pdo = Connection::get();
        $find = $pdo->prepare('SELECT logo_file FROM organizations WHERE id = ? AND status = \'active\'');
        $find->execute([$organizationId]); $old = $find->fetchColumn();
        if ($old === false) { self::removeFile($organizationId, $name); throw new InvalidArgumentException('Empresa não encontrada.'); }
        $pdo->prepare('UPDATE organizations SET logo_file = ? WHERE id = ?')->execute([$name, $organizationId]);
        if (is_string($old) && $old !== $name) self::removeFile($organizationId, $old);
        OrganizationAudit::record($organizationId, $actorId, 'company.logo_updated', 'organization', $organizationId);
        return $name;
    }

    public static function remove(int $organizationId, int $actorId): void
    {
        if (!OrganizationTeam::can($actorId, $organizationId, 'team.manage')) throw new InvalidArgumentException('Você não tem permissão para remover o logotipo.');
        if (!self::ready()) return;
        $pdo=Connection::get(); $q=$pdo->prepare('SELECT logo_file FROM organizations WHERE id=? FOR UPDATE');
        $pdo->beginTransaction();
        try { $q->execute([$organizationId]); $old=$q->fetchColumn(); $pdo->prepare('UPDATE organizations SET logo_file=NULL WHERE id=?')->execute([$organizationId]); $pdo->commit(); }
        catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
        if (is_string($old)) self::removeFile($organizationId,$old);
        OrganizationAudit::record($organizationId,$actorId,'company.logo_removed','organization',$organizationId);
    }

    public static function streamAuthorized(int $organizationId, int $userId): void
    {
        if (OrganizationAccess::authorizedOrganization($organizationId) === null || $userId <= 0 || !self::ready()) { AppError::render(404); return; }
        $q=Connection::get()->prepare('SELECT logo_file FROM organizations WHERE id=? AND status=\'active\'');$q->execute([$organizationId]);$name=$q->fetchColumn();
        $path=is_string($name)?self::path($organizationId,$name):null;
        if ($path===null||!is_file($path)) { AppError::render(404); return; }
        header('Content-Type: image/jpeg'); header('X-Content-Type-Options: nosniff'); header('Content-Security-Policy: sandbox'); header('Cache-Control: private, max-age=604800, immutable'); header('Content-Length: '.filesize($path)); readfile($path);
    }

    /** @param array<string,mixed>|null $file */
    private static function store(int $organizationId, ?array $file): string
    {
        if ($file===null||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||(int)($file['size']??0)<1||(int)$file['size']>self::MAX_BYTES) throw new InvalidArgumentException('Envie um logotipo JPG ou PNG de até 5 MB.');
        $tmp=(string)$file['tmp_name'];$info=@getimagesize($tmp);$mime=(string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if($info===false||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG],true)||!in_array($mime,['image/jpeg','image/png'],true)) throw new InvalidArgumentException('O arquivo não é uma imagem JPG ou PNG válida.');
        if($info[0]<40||$info[1]<40||$info[0]*$info[1]>self::MAX_PIXELS) throw new InvalidArgumentException('Use uma imagem entre 40×40 e 40 megapixels.');
        $source=@imagecreatefromstring((string)file_get_contents($tmp));if($source===false)throw new InvalidArgumentException('Não foi possível ler a imagem.');
        $scale=min(1,self::MAX_SIDE/max(imagesx($source),imagesy($source)));$w=max(1,(int)round(imagesx($source)*$scale));$h=max(1,(int)round(imagesy($source)*$scale));
        $canvas=imagecreatetruecolor($w,$h);imagefill($canvas,0,0,(int)imagecolorallocate($canvas,255,255,255));imagecopyresampled($canvas,$source,0,0,0,0,$w,$h,imagesx($source),imagesy($source));
        $name=bin2hex(random_bytes(16)).'.jpg';$directory=self::directory($organizationId);if(!imagejpeg($canvas,$directory.'/'.$name,86))throw new RuntimeException('Não foi possível gravar o logotipo.');return $name;
    }
    private static function directory(int $organizationId): string { $root=BASE_PATH.'/storage/organizations/'.$organizationId.'/identity';if(!is_dir($root)&&!mkdir($root,0750,true)&&!is_dir($root))throw new RuntimeException('Armazenamento empresarial indisponível.');return $root; }
    private static function path(int $organizationId,string $name): ?string { return preg_match('/^[a-f0-9]{32}\.jpg$/',$name)?self::directory($organizationId).'/'.$name:null; }
    private static function removeFile(int $organizationId,string $name): void { $path=self::path($organizationId,$name);if($path!==null&&is_file($path))unlink($path); }
}
