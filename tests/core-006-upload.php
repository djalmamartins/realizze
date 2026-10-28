<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';

use Moves\Support\Upload;

function verify(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('FALHOU: ' . $label);
    }
    echo 'OK - ', $label, PHP_EOL;
}

$dir = sys_get_temp_dir() . '/realizze-core006-' . bin2hex(random_bytes(8));
if (!mkdir($dir, 0700)) {
    throw new RuntimeException('Não foi possível criar diretório temporário.');
}
$outside = tempnam(sys_get_temp_dir(), 'core006-outside-');
try {
    $upload = new Upload($dir, 1024);
    verify($upload->file(['error' => UPLOAD_ERR_NO_FILE], 'teste') === null, 'Upload ausente rejeitado');
    verify($upload->message()->getType() === 'error', 'Mensagem de erro registrada');
    $fake = tempnam($dir, 'fake-');
    file_put_contents($fake, '<script>alert(1)</script>');
    verify($upload->image(['tmp_name' => $fake, 'name' => 'malicioso.jpg', 'size' => filesize($fake), 'error' => UPLOAD_ERR_OK], 'teste') === null,
        'Imagem falsa rejeitada');
    unlink($fake);
    $file = tempnam($dir, 'pdf-');
    file_put_contents($file, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n");
    $saved = $upload->file(['tmp_name' => $file, 'name' => 'documento.pdf', 'size' => filesize($file), 'error' => UPLOAD_ERR_OK], 'Documento');
    verify(is_string($saved) && str_starts_with($saved, 'files/'), 'Documento salvo no diretório permitido');
    verify(is_file($dir . '/' . $saved), 'Arquivo armazenado');
    $upload->remove($outside);
    verify(is_file($outside), 'Exclusão externa bloqueada');
    $upload->remove($saved);
    verify(!is_file($dir . '/' . $saved), 'Exclusão interna autorizada');
    echo "TESTE CORE-006: APROVADO\n";
} finally {
    if (isset($file) && is_file($file)) unlink($file);
    if (isset($fake) && is_file($fake)) unlink($fake);
    if (is_file($outside)) unlink($outside);
    // Remove apenas o diretório temporário criado por este teste.
    foreach (glob($dir . '/files/*/*/*') ?: [] as $entry) if (is_file($entry)) unlink($entry);
    foreach (glob($dir . '/files/*/*') ?: [] as $entry) if (is_dir($entry)) rmdir($entry);
    foreach (glob($dir . '/files/*') ?: [] as $entry) if (is_dir($entry)) rmdir($entry);
    if (is_dir($dir . '/files')) rmdir($dir . '/files');
    if (is_dir($dir)) rmdir($dir);
}
