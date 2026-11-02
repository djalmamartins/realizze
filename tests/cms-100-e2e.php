<?php
declare(strict_types=1);

/**
 * CMS, site e Helpdesk por HTTP real no Docker, com dados de teste removidos no fim.
 * Execute dentro do dev-apache, como www-data: php tests/cms-100-e2e.php
 * Não envia e-mail nem Telegram: transportes externos são substituídos na fila.
 */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\CmsQuery as Q;
use Moves\Services\CmsSettings;
use Moves\Services\ExternalNotifications;
use MovesCode\Model\Connection;

$failures = 0;
$ok = static function (bool $value, string $message) use (&$failures): void {
    if (!$value) {
        $failures++;
    }
    echo($value ? 'OK' : 'FALHOU') . " - {$message}\n";
};

final class Client
{
    private string $jar;
    public int $status = 0;
    public string $body = '';
    public string $location = '';

    public function __construct(private string $host)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'rzt');
    }

    public function get(string $path): string
    {
        return $this->send($path, null);
    }

    public function post(string $path, array $fields = [], bool $withToken = true): string
    {
        if ($withToken && !isset($fields['csrf_token'])) {
            $fields['csrf_token'] = $this->token();
        }
        return $this->send($path, $fields);
    }

    public function token(): string
    {
        preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $this->body, $m);
        if (!isset($m[1])) {
            foreach ($this->host === 'cms.realizze.lab' ? ['/entrar', '/notificacoes'] : ['/suporte/novo', '/entrar'] as $path) {
                $this->get($path);
                if (preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $this->body, $m)) {
                    break;
                }
            }
        }
        return $m[1] ?? '';
    }

    private function send(string $path, ?array $fields): string
    {
        $curl = curl_init('https://' . $this->host . $path);
        $options = [
            CURLOPT_RESOLVE => [$this->host . ':443:127.0.0.1'], CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 20,
        ];
        if ($fields !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $fields;
        }
        curl_setopt_array($curl, $options);
        $raw = (string) curl_exec($curl);
        $this->status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $size = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);
        $headers = substr($raw, 0, $size);
        $this->location = preg_match('/^location:\s*(\S+)/mi', $headers, $m) ? $m[1] : '';
        $this->body = substr($raw, $size);
        return $this->body;
    }
}

// O limite de login por IP vale para o loopback do teste: reexecuções seguidas não devem esgotá-lo.
foreach (['cms-login-ip', 'login-ip'] as $scope) {
    @unlink(sys_get_temp_dir() . '/realizze-auth-limits/' . hash('sha256', $scope . "\0" . '127.0.0.1') . '.json');
}

$pdo = Connection::get();
$password = 'Teste-' . bin2hex(random_bytes(8));
$hash = password_hash($password, PASSWORD_DEFAULT);
$users = [];
foreach (['super', 'support', 'editor', 'billing', 'common', 'owner', 'member', 'other', 'target'] as $key) {
    $email = 'cms-e2e-' . $key . '-' . bin2hex(random_bytes(4)) . '@example.invalid';
    Q::run("INSERT INTO users (first_name,last_name,email,password,status) VALUES (?,'Teste',?,?,'active')", [ucfirst($key), $email, $hash]);
    $users[$key] = ['id' => (int) $pdo->lastInsertId(), 'email' => $email];
}
foreach (['super' => 'superadmin', 'support' => 'support', 'editor' => 'editor', 'billing' => 'billing'] as $key => $role) {
    Q::run("INSERT INTO cms_admins (user_id,role_id) SELECT ?, id FROM cms_roles WHERE role_key=?", [$users[$key]['id'], $role]);
}
$settingsBackup = Q::all("SELECT * FROM cms_settings WHERE setting_key LIKE 'notify_%'");
$org = null;
$created = ['pages' => [], 'posts' => [], 'plans' => [], 'cats' => []];
$tmpFiles = [];

$login = static function (string $host, string $key) use ($users, $password): Client {
    $client = new Client($host);
    $client->get('/entrar');
    $client->post('/entrar', ['email' => $users[$key]['email'], 'password' => $password]);
    return $client;
};

try {
    // ---- Acesso ----
    $anon = new Client('cms.realizze.lab');
    $anon->get('/');
    $ok($anon->status === 302 && $anon->location === '/entrar', 'Visitante é enviado ao login');
    $anon->get('/usuarios');
    $ok($anon->status === 302, 'Rota interna exige login');
    $common = $login('cms.realizze.lab', 'common');
    $ok($common->status === 401, 'Usuário comum com senha certa não entra no CMS');
    $common->get('/');
    $ok($common->status === 302, 'Usuário comum continua fora');
    $badCsrf = new Client('cms.realizze.lab');
    $badCsrf->get('/entrar');
    $badCsrf->post('/entrar', ['email' => $users['super']['email'], 'password' => $password, 'csrf_token' => str_repeat('0', 64)]);
    $ok($badCsrf->status === 401, 'Login sem CSRF válido é recusado');

    $super = $login('cms.realizze.lab', 'super');
    $ok($super->status === 303, 'Administrador autentica');
    foreach (['/', '/paginas', '/paginas/novo', '/conteudos', '/conteudos/novo', '/conteudos/categorias', '/conteudos/faq', '/midia', '/menus', '/usuarios', '/empresas', '/planos', '/planos/novo', '/assinaturas', '/cobrancas', '/mensagens', '/chamados', '/chamados/fila', '/chamados/categorias', '/chamados/respostas', '/chamados/equipe', '/chamados/relatorios', '/chamados/notificacoes', '/notificacoes', '/relatorios', '/comunicacoes', '/destaques', '/destaques/novo', '/depoimentos', '/base-de-conhecimento', '/versoes', '/log', '/administradores', '/permissoes', '/auditoria', '/configuracoes'] as $path) {
        $super->get($path);
        $ok($super->status === 200 && str_contains($super->body, 'id="cms-content"') && !str_contains($super->body, 'Warning:') && !str_contains($super->body, 'Fatal'), "Tela {$path}");
    }
    $super->get('/usuarios/' . $users['common']['id']);
    $ok($super->status === 200 && !str_contains($super->body, $hash), 'Detalhe do usuário abre sem expor senha');
    $ok(Q::value("SELECT COUNT(*) FROM cms_audit_logs WHERE actor_user_id=? AND event_type='cms.login'", [$users['super']['id']]) > 0, 'Login auditado');

    // ---- Permissões ----
    $editor = $login('cms.realizze.lab', 'editor');
    $editor->get('/planos');
    $ok($editor->status === 403, 'Editor não abre planos');
    $editor->get('/paginas');
    $ok($editor->status === 200, 'Editor abre páginas');
    $token = $editor->token();
    $editor->post('/acao/plan-save', ['csrf_token' => $token, 'name' => 'Invasor', 'monthly_price' => '1', 'annual_price' => '1']);
    $ok($editor->status === 403 && Q::value("SELECT COUNT(*) FROM plans WHERE name='Invasor'") == 0, 'Editor não altera plano por POST direto');
    $editor->post('/acao/permissions-save', ['csrf_token' => $token]);
    $ok($editor->status === 403, 'Editor não altera permissões');
    $supportStaff = $login('cms.realizze.lab', 'support');
    $supportStaff->get('/usuarios');
    $ok($supportStaff->status === 403, 'Suporte não abre usuários');
    $supportStaff->get('/cobrancas');
    $ok($supportStaff->status === 403, 'Suporte não abre cobranças');

    // ---- CSRF ----
    $super->post('/acao/settings-save', ['csrf_token' => str_repeat('a', 64)]);
    $ok($super->status === 403, 'Ação sem CSRF válido é bloqueada');

    // ---- Páginas ----
    $slug = 'teste-e2e-' . bin2hex(random_bytes(3));
    $super->get('/paginas/novo');
    $super->post('/acao/page-save', ['title' => 'Página E2E', 'slug' => $slug, 'content' => '<p>Texto seguro</p><script>alert(1)</script><a href="javascript:alert(1)" onclick="x()">link</a><img src="x" onerror="y()">', 'seo_title' => 'SEO E2E', 'seo_description' => 'Descrição E2E', 'robots_index' => '0']);
    $pageId = (int) Q::value('SELECT id FROM cms_pages WHERE slug=?', [$slug]);
    $created['pages'][] = $pageId;
    $content = (string) Q::value('SELECT content FROM cms_pages WHERE id=?', [$pageId]);
    $ok($pageId > 0 && str_contains($content, 'Texto seguro'), 'Página criada');
    $ok(!str_contains($content, '<script') && !str_contains($content, 'javascript:') && !str_contains($content, 'onclick') && !str_contains($content, 'onerror'), 'HTML perigoso removido');
    $web = new Client('www.realizze.lab');
    $web->get('/' . $slug);
    $ok($web->status === 404, 'Rascunho não aparece no site');
    $super->get('/paginas/' . $pageId . '/previa');
    $ok($super->status === 200 && str_contains($super->body, 'Texto seguro'), 'Prévia interna do rascunho');
    $super->post('/acao/page-publish', ['id' => $pageId, 'publish' => '1', 'back' => '/paginas']);
    $web->get('/' . $slug);
    $ok($web->status === 200 && str_contains($web->body, 'Texto seguro') && str_contains($web->body, '<title>SEO E2E') && str_contains($web->body, 'noindex'), 'Publicada no site com SEO');
    $super->get('/paginas/' . $pageId);
    $super->post('/acao/page-save', ['id' => $pageId, 'title' => 'Página E2E 2', 'slug' => $slug, 'content' => '<p>Versão 2</p>']);
    $ok((int) Q::value('SELECT COUNT(*) FROM cms_page_revisions WHERE page_id=?', [$pageId]) >= 3, 'Histórico de versões registrado');
    $super->post('/acao/page-publish', ['id' => $pageId, 'publish' => '0']);
    $web->get('/' . $slug);
    $ok($web->status === 404, 'Despublicação retira do site');
    $super->post('/acao/page-save', ['title' => 'Reservado', 'slug' => 'blog', 'content' => 'x']);
    $ok(Q::value("SELECT COUNT(*) FROM cms_pages WHERE slug='blog'") == 0, 'Slug reservado recusado');

    // ---- Conteúdos e FAQ ----
    $uri = 'artigo-e2e-' . bin2hex(random_bytes(3));
    $super->post('/acao/post-save', ['title' => 'Artigo E2E', 'uri' => $uri, 'subtitle' => 'Resumo', 'content' => '<p>Corpo do artigo</p>', 'post_type' => 'article']);
    $postId = (int) Q::value('SELECT id FROM posts WHERE uri=?', [$uri]);
    $created['posts'][] = $postId;
    $super->post('/acao/post-publish', ['id' => $postId, 'publish' => '1']);
    $web->get('/blog');
    $ok(str_contains($web->body, 'Artigo E2E'), 'Artigo publicado aparece na listagem');
    $web->get('/blog/' . $uri);
    $ok($web->status === 200 && str_contains($web->body, 'Corpo do artigo'), 'Artigo abre no site');
    $super->get('/conteudos?q=Artigo+E2E&type=article');
    $ok(str_contains($super->body, 'Artigo E2E'), 'Pesquisa e filtro de conteúdos');

    $futureUri = 'agendado-e2e-' . bin2hex(random_bytes(3));
    $super->get('/conteudos/novo');
    $super->post('/acao/post-save', ['title' => 'Agendado E2E', 'uri' => $futureUri, 'subtitle' => 'x', 'content' => '<p>Futuro</p>', 'post_at' => date('Y-m-d\\TH:i', time() + 86400), 'video' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'author' => $users['editor']['id'], 'publish_after' => '1']);
    $future = Q::one('SELECT * FROM posts WHERE uri=?', [$futureUri]);
    $created['posts'][] = (int) $future['id'];
    $ok($future['status'] === 'published' && $future['video'] === 'dQw4w9WgXcQ' && (int) $future['author'] === $users['editor']['id'], 'Salvar e publicar guarda autor e ID do vídeo');
    $web->get('/blog/' . $futureUri);
    $ok($web->status === 404, 'Publicação agendada ainda não aparece no site');
    Q::run('UPDATE posts SET post_at=NOW() - INTERVAL 1 MINUTE WHERE id=?', [$future['id']]);
    $web->get('/blog/' . $futureUri);
    $ok($web->status === 200 && str_contains($web->body, 'youtube-nocookie.com/embed/dQw4w9WgXcQ'), 'Quando chega a data, o artigo aparece com o vídeo');
    $super->post('/acao/post-save', ['title' => 'Vídeo ruim', 'uri' => 'video-ruim-e2e', 'subtitle' => 'x', 'content' => '<p>x</p>', 'video' => 'javascript:alert(1)']);
    $ok(Q::value("SELECT COUNT(*) FROM posts WHERE uri='video-ruim-e2e'") == 0, 'Vídeo inválido recusado');
    $super->post('/acao/post-save', ['title' => 'Autor ruim', 'uri' => 'autor-ruim-e2e', 'subtitle' => 'x', 'content' => '<p>x</p>', 'author' => $users['common']['id']]);
    $ok(Q::value("SELECT COUNT(*) FROM posts WHERE uri='autor-ruim-e2e'") == 0, 'Autor sem acesso ao CMS recusado');
    $png = tempnam(sys_get_temp_dir(), 'rzt') . '.png';
    $image = imagecreatetruecolor(40, 30);
    imagepng($image, $png);
    $tmpFiles[] = $png;
    $coverUri = 'capa-e2e-' . bin2hex(random_bytes(3));
    $super->get('/conteudos/novo');
    $super->post('/acao/post-save', ['title' => 'Capa E2E', 'uri' => $coverUri, 'subtitle' => 'x', 'content' => '<p>x</p>', 'cover_file' => new CURLFile($png, 'image/png', 'capa.png')]);
    $coverPost = Q::one('SELECT id,cover FROM posts WHERE uri=?', [$coverUri]);
    $created['posts'][] = (int) $coverPost['id'];
    $mediaRow = Q::one('SELECT id,stored_path FROM cms_media WHERE original_name=? ORDER BY id DESC LIMIT 1', ['capa.png']);
    $ok($mediaRow !== null && $coverPost['cover'] === '/uploads/cms/' . $mediaRow['stored_path'] && is_file(BASE_PATH . '/public' . $coverPost['cover']), 'Capa enviada entra na biblioteca e no artigo');
    Q::run('UPDATE posts SET cover=NULL WHERE id=?', [$coverPost['id']]);
    \Moves\Services\CmsMedia::delete((int) $mediaRow['id'], $users['super']['id']);
    $ok(!is_file(BASE_PATH . '/public/uploads/cms/' . $mediaRow['stored_path']), 'Exclusão remove o arquivo de mídia');

    // SEO automático: nenhum campo de SEO preenchido
    $autoSlug = 'auto-seo-' . bin2hex(random_bytes(3));
    $super->get('/paginas/novo');
    $super->post('/acao/page-save', ['title' => 'Página Automática', 'slug' => $autoSlug, 'content' => '<h2>Olá</h2><p>Texto da <b>página</b> ' . str_repeat('palavra ', 40) . '</p>', 'publish_after' => '1']);
    $autoId = (int) Q::value('SELECT id FROM cms_pages WHERE slug=?', [$autoSlug]);
    $created['pages'][] = $autoId;
    $web->get('/' . $autoSlug);
    $ok(str_contains($web->body, '<title>Página Automática | Realizze'), 'Título SEO automático');
    $ok(preg_match('/name="description" content="(Olá Texto da página [^"]+)"/u', $web->body, $autoDesc) === 1 && mb_strlen(html_entity_decode($autoDesc[1])) <= 156, 'Descrição automática vem do conteúdo, sem HTML e com limite');
    $ok(str_contains($web->body, 'rel="canonical" href="https://www.realizze.lab/' . $autoSlug . '"') && !str_contains($web->body, 'noindex'), 'URL canônica automática e página indexável');
    $web->get('/sitemap.xml');
    $ok(str_contains($web->body, '/' . $autoSlug . '</loc>') && str_contains($web->body, '<loc>'), 'Página publicada entra no sitemap');
    $super->post('/acao/page-publish', ['id' => $autoId, 'publish' => '0']);
    $web->get('/sitemap.xml');
    $ok(!str_contains($web->body, '/' . $autoSlug . '</loc>'), 'Rascunho sai do sitemap');
    $web->get('/robots.txt');
    $ok(str_contains($web->body, 'Sitemap: https://www.realizze.lab/sitemap.xml'), 'Robots aponta o sitemap');

    // ---- Menus e configurações ----
    $super->post('/acao/menu-save', ['location' => 'footer', 'label' => 'Link E2E', 'url' => '/teste-e2e', 'is_visible' => '1']);
    $web->get('/');
    $ok(str_contains($web->body, 'Link E2E'), 'Menu do rodapé publicado no site');
    $super->post('/acao/menu-save', ['location' => 'footer', 'label' => 'XSS', 'url' => 'javascript:alert(1)', 'is_visible' => '1']);
    $ok(Q::value("SELECT COUNT(*) FROM cms_menu_items WHERE label='XSS'") == 0, 'Destino perigoso recusado');
    Q::run("DELETE FROM cms_menu_items WHERE label='Link E2E'");

    // ---- Planos ----
    $key = 'plano-e2e-' . bin2hex(random_bytes(3));
    $super->get('/planos/novo');
    $super->post('/acao/plan-save', ['name' => 'Plano E2E', 'plan_key' => $key, 'monthly_price' => '49,90', 'annual_price' => '499,00', 'benefits' => "Benefício A\nBenefício B", 'is_public' => '1', 'status' => 'active']);
    $planId = (int) Q::value('SELECT id FROM plans WHERE plan_key=?', [$key]);
    $created['plans'][] = $planId;
    $priceRow = Q::one('SELECT monthly_price, annual_price FROM plan_prices WHERE plan_id=? AND valid_to IS NULL', [$planId]);
    $ok($priceRow !== null && $priceRow['monthly_price'] === '49.90' && $priceRow['annual_price'] === '499.00', 'Preço salvo em decimal exato');
    $web->get('/planos');
    $ok(str_contains($web->body, 'Plano E2E') && str_contains($web->body, 'R$ 49,90'), 'Site exibe preço publicado no CMS');
    $super->post('/acao/subscription-create', ['owner_email' => $users['common']['email'], 'plan_id' => $planId, 'billing_cycle' => 'monthly']);
    $sub = Q::one('SELECT * FROM subscriptions WHERE user_id=?', [$users['common']['id']]);
    $ok($sub !== null && $sub['status'] === 'pending' && $sub['contracted_amount'] === '49.90', 'Assinatura nasce pendente com valor contratado');
    $charge = Q::one('SELECT * FROM platform_charges WHERE subscription_id=?', [$sub['id']]);
    $ok($charge !== null && $charge['status'] === 'pending', 'Primeira cobrança pendente');
    $super->post('/acao/plan-save', ['id' => $planId, 'name' => 'Plano E2E', 'plan_key' => $key, 'monthly_price' => '59,90', 'annual_price' => '599,00', 'is_public' => '1', 'status' => 'active']);
    $ok(Q::value('SELECT monthly_price FROM plan_prices WHERE plan_id=? AND valid_to IS NULL', [$planId]) === '49.90', 'Preço não muda sem motivo');
    $super->post('/acao/plan-save', ['id' => $planId, 'name' => 'Plano E2E', 'plan_key' => $key, 'monthly_price' => '59,90', 'annual_price' => '599,00', 'change_reason' => 'Reajuste de teste', 'is_public' => '1', 'status' => 'active']);
    $ok((int) Q::value('SELECT COUNT(*) FROM plan_prices WHERE plan_id=?', [$planId]) === 2, 'Histórico de preços preservado');
    $ok(Q::value('SELECT contracted_amount FROM subscriptions WHERE id=?', [$sub['id']]) === '49.90', 'Reajuste não altera contrato existente');
    $web->get('/planos');
    $ok(str_contains($web->body, 'R$ 59,90') && !str_contains($web->body, 'R$ 49,90'), 'Site mostra o preço novo');
    $billing = $login('cms.realizze.lab', 'billing');
    $billing->get('/cobrancas/' . $charge['id']);
    $billing->post('/acao/charge-paid', ['id' => $charge['id'], 'reference' => '']);
    $ok(Q::value('SELECT status FROM subscriptions WHERE id=?', [$sub['id']]) === 'pending', 'Sem comprovante a assinatura não é ativada');
    $proof = 'COMP-' . bin2hex(random_bytes(4));
    $billing->post('/acao/charge-paid', ['id' => $charge['id'], 'reference' => $proof]);
    $paid = Q::one('SELECT s.status, s.renews_at, c.status AS charge_status FROM subscriptions s JOIN platform_charges c ON c.subscription_id=s.id WHERE s.id=?', [$sub['id']]);
    $ok($paid['status'] === 'active' && $paid['charge_status'] === 'paid' && $paid['renews_at'] !== null, 'Pagamento confirmado ativa assinatura');
    $renewsBefore = $paid['renews_at'];
    $billing->post('/acao/charge-paid', ['id' => $charge['id'], 'reference' => $proof]);
    $ok(Q::value('SELECT renews_at FROM subscriptions WHERE id=?', [$sub['id']]) === $renewsBefore, 'Repetir a confirmação não renova de novo');
    $ok(Q::value('SELECT COUNT(*) FROM platform_charge_attempts WHERE charge_id=?', [$charge['id']]) == 1, 'Tentativa registrada uma vez');
    $billing->post('/acao/subscription-cancel', ['id' => $sub['id'], 'reason' => '']);
    $ok(Q::value('SELECT status FROM subscriptions WHERE id=?', [$sub['id']]) === 'active', 'Cancelamento exige motivo');
    $billing->post('/acao/subscription-cancel', ['id' => $sub['id'], 'reason' => 'Teste']);
    $ok(Q::value('SELECT status FROM subscriptions WHERE id=?', [$sub['id']]) === 'canceled', 'Cancelamento autorizado');
    $ok((int) Q::value('SELECT COUNT(*) FROM subscription_events WHERE subscription_id=?', [$sub['id']]) >= 3, 'Histórico da assinatura');

    // ---- Suspensão ----
    $target = $login('app.realizze.lab', 'target');
    $target->get('/suporte');
    $ok($target->status === 200, 'Cliente acessa o suporte');
    $super->get('/usuarios/' . $users['target']['id']);
    $super->post('/acao/user-suspend', ['id' => $users['target']['id'], 'suspend' => '1', 'reason' => 'Teste']);
    $target->get('/suporte');
    $ok($target->status === 302, 'Suspensão encerra a sessão do usuário');
    $super->post('/acao/user-suspend', ['id' => $users['super']['id'], 'suspend' => '1', 'reason' => 'x']);
    $ok(Q::value('SELECT status FROM users WHERE id=?', [$users['super']['id']]) === 'active', 'Administrador não suspende a si mesmo');

    // ---- Helpdesk ----
    Q::run("INSERT INTO organizations (owner_user_id,name) VALUES (?, 'Empresa E2E')", [$users['owner']['id']]);
    $org = (int) $pdo->lastInsertId();
    Q::run("INSERT INTO organization_roles (organization_id,role_key,name) VALUES (?,'owner','Dono'),(?,'member','Membro')", [$org, $org]);
    $roles = [];
    foreach (Q::all('SELECT id, role_key FROM organization_roles WHERE organization_id=?', [$org]) as $r) { $roles[$r['role_key']] = (int) $r['id']; }
    Q::run("INSERT INTO organization_members (organization_id,user_id,role_id,joined_at) VALUES (?,?,?,NOW()),(?,?,?,NOW())", [$org, $users['owner']['id'], $roles['owner'], $org, $users['member']['id'], $roles['member']]);
    foreach (['telegram', 'email'] as $channel) {
        Q::run("INSERT INTO cms_settings (setting_key,setting_value) VALUES (?, '1') ON DUPLICATE KEY UPDATE setting_value='1'", ['notify_' . $channel . '_enabled']);
    }
    CmsSettings::forget();
    $category = (int) Q::value('SELECT id FROM helpdesk_categories WHERE is_active=1 ORDER BY id LIMIT 1');
    $customer = $login('app.realizze.lab', 'common');
    $customer->get('/suporte/novo');
    $ok($customer->status === 200 && str_contains($customer->body, 'name="subject"'), 'Formulário de abertura no App');
    $idem = bin2hex(random_bytes(16));
    $file = tempnam(sys_get_temp_dir(), 'rzt') . '.pdf';
    file_put_contents($file, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    $tmpFiles[] = $file;
    $customer->post('/suporte/abrir', ['subject' => 'Erro CPF 529.982.247-25 no recebimento', 'body' => 'Descrição privada do cliente', 'category_id' => $category, 'priority' => 'high', 'idempotency_key' => $idem, 'requester_user_id' => (string) $users['other']['id'], 'attachments[0]' => new CURLFile($file, 'application/pdf', 'prova.pdf')]);
    $ticket = Q::one('SELECT * FROM helpdesk_tickets WHERE requester_user_id=? ORDER BY id DESC LIMIT 1', [$users['common']['id']]);
    $ok($ticket !== null && preg_match('/^RZ-\d{6,}$/', $ticket['protocol']) === 1, 'Chamado aberto com protocolo único');
    $ok(Q::value('SELECT COUNT(*) FROM helpdesk_tickets WHERE requester_user_id=?', [$users['other']['id']]) == 0, 'Identificador do navegador é ignorado');
    $customer->get('/suporte/novo');
    $customer->post('/suporte/abrir', ['subject' => 'Repetido', 'body' => 'Repetido', 'category_id' => $category, 'idempotency_key' => $idem]);
    $ok(Q::value('SELECT COUNT(*) FROM helpdesk_tickets WHERE requester_user_id=?', [$users['common']['id']]) == 1, 'Reenvio do formulário não duplica');
    $tid = (int) $ticket['id'];
    $proto = $ticket['protocol'];
    $attachment = (int) Q::value('SELECT id FROM helpdesk_attachments WHERE ticket_id=?', [$tid]);
    $ok($attachment > 0, 'Anexo salvo');
    $stored = (string) Q::value('SELECT stored_path FROM helpdesk_attachments WHERE id=?', [$attachment]);
    $ok(!str_contains(realpath(BASE_PATH . '/storage/helpdesk/' . $stored) ?: '', '/public/'), 'Anexo fora da área pública');
    $evil = tempnam(sys_get_temp_dir(), 'rzt') . '.php';
    file_put_contents($evil, "<?php echo 1;");
    $tmpFiles[] = $evil;
    $customer->get('/suporte/' . $proto);
    $customer->post('/suporte/' . $proto . '/responder', ['body' => 'Com arquivo executável', 'attachments[0]' => new CURLFile($evil, 'application/x-php', 'ataque.php')]);
    $ok(Q::value("SELECT COUNT(*) FROM helpdesk_attachments WHERE ticket_id=? AND original_name='ataque.php'", [$tid]) == 0, 'Arquivo executável recusado');
    $disguised = tempnam(sys_get_temp_dir(), 'rzt') . '.pdf';
    file_put_contents($disguised, "<?php echo 1;");
    $tmpFiles[] = $disguised;
    $customer->get('/suporte/' . $proto);
    $customer->post('/suporte/' . $proto . '/responder', ['body' => 'Falso PDF', 'attachments[0]' => new CURLFile($disguised, 'application/pdf', 'falso.pdf')]);
    $ok(Q::value("SELECT COUNT(*) FROM helpdesk_attachments WHERE ticket_id=? AND original_name='falso.pdf'", [$tid]) == 0, 'Falso PDF recusado pelo MIME real');
    Q::run("UPDATE helpdesk_tickets SET status='open' WHERE id=?", [$tid]);

    // Isolamento
    $other = $login('app.realizze.lab', 'other');
    $other->get('/suporte/' . $proto);
    $ok($other->status === 404 && !str_contains($other->body, 'Descrição privada'), 'Outro cliente não abre o chamado (IDOR)');
    $other->get('/suporte/anexo/' . $attachment);
    $ok($other->status === 404, 'Outro cliente não baixa o anexo');
    $other->get('/suporte/novo');
    $other->post('/suporte/' . $proto . '/responder', ['body' => 'Invasão']);
    $ok($other->status === 404 && Q::value("SELECT COUNT(*) FROM helpdesk_messages WHERE body='Invasão'") == 0, 'Outro cliente não responde');
    $customer->get('/suporte/anexo/' . $attachment);
    $ok($customer->status === 200 && str_starts_with($customer->body, '%PDF'), 'Dono baixa o próprio anexo');
    $anonApp = new Client('app.realizze.lab');
    $anonApp->get('/suporte/anexo/' . $attachment);
    $ok($anonApp->status === 302, 'Anexo exige login');

    // Empresa: membro não vê sem autorização específica
    $orgKey = bin2hex(random_bytes(16));
    $orgTicket = \Moves\Services\Helpdesk::open($users['owner']['id'], $org, ['subject' => 'Chamado da empresa', 'body' => 'Mensagem empresarial', 'category_id' => $category, 'idempotency_key' => $orgKey]);
    $member = $login('app.realizze.lab', 'member');
    $member->get('/suporte/' . $orgTicket['protocol']);
    $ok($member->status === 404, 'Membro da empresa não vê chamado sem autorização');
    $outsider = $other;
    $outsider->get('/suporte/' . $orgTicket['protocol']);
    $ok($outsider->status === 404, 'Pessoa de fora não vê chamado da empresa');
    $super->get('/empresas/' . $org);
    $super->post('/acao/company-support-access', ['id' => $org, 'user_id' => $users['member']['id'], 'grant' => '1']);
    $member->get('/suporte/' . $orgTicket['protocol']);
    $ok($member->status === 200, 'Autorização específica libera o membro');
    $super->post('/acao/company-support-access', ['id' => $org, 'user_id' => $users['member']['id'], 'grant' => '0']);
    $member->get('/suporte/' . $orgTicket['protocol']);
    $ok($member->status === 404, 'Remoção da autorização bloqueia de novo');

    // Atendimento no CMS
    $supportStaff->get('/chamados?q=' . $proto);
    $ok(str_contains($supportStaff->body, $proto), 'Chamado aparece no CMS e na pesquisa');
    $supportStaff->get('/chamados/fila');
    $ok($supportStaff->status === 200 && str_contains($supportStaff->body, $proto), 'Chamado aparece na fila');
    $supportStaff->get('/chamados/' . $tid);
    $ok(str_contains($supportStaff->body, 'Descrição privada'), 'Atendente lê a conversa');
    $supportStaff->post('/acao/ticket-assign', ['id' => $tid, 'assignee_user_id' => $users['common']['id']]);
    $ok(Q::value('SELECT assignee_user_id FROM helpdesk_tickets WHERE id=?', [$tid]) === null, 'Cliente não pode receber atribuição');
    $supportStaff->post('/acao/ticket-assign', ['id' => $tid, 'assignee_user_id' => $users['support']['id']]);
    $ok((int) Q::value('SELECT assignee_user_id FROM helpdesk_tickets WHERE id=?', [$tid]) === $users['support']['id'], 'Atribuição');
    $supportStaff->post('/acao/ticket-assign', ['id' => $tid, 'assignee_user_id' => $users['super']['id']]);
    $ok(Q::value("SELECT COUNT(*) FROM helpdesk_events WHERE ticket_id=? AND event_type='transferred'", [$tid]) == 1, 'Transferência registrada');
    $supportStaff->post('/acao/ticket-priority', ['id' => $tid, 'priority' => 'urgent']);
    $ok(Q::value('SELECT priority FROM helpdesk_tickets WHERE id=?', [$tid]) === 'urgent', 'Prioridade revisada pela equipe');
    $supportStaff->post('/acao/ticket-reply', ['id' => $tid, 'body' => 'Nota secreta da equipe', 'internal' => '1']);
    $supportStaff->post('/acao/ticket-reply', ['id' => $tid, 'body' => 'Resposta pública ao cliente', 'internal' => '0']);
    $state = Q::one('SELECT status, first_response_at FROM helpdesk_tickets WHERE id=?', [$tid]);
    $ok($state['status'] === 'waiting_customer' && $state['first_response_at'] !== null, 'Resposta registra primeira resposta e aguarda cliente');
    $supportStaff->post('/acao/ticket-status', ['id' => $tid, 'status' => 'closed']);
    $ok(Q::value('SELECT status FROM helpdesk_tickets WHERE id=?', [$tid]) === 'waiting_customer', 'Transição inválida recusada');
    $customer->get('/suporte/' . $proto);
    $ok(str_contains($customer->body, 'Resposta pública ao cliente'), 'Cliente vê a resposta no App');
    $ok(!str_contains($customer->body, 'Nota secreta'), 'Nota interna não aparece ao cliente');
    $internalMsg = (int) Q::value("SELECT id FROM helpdesk_messages WHERE ticket_id=? AND visibility='internal' LIMIT 1", [$tid]);
    Q::run("INSERT INTO helpdesk_attachments (ticket_id,message_id,stored_path,original_name,mime_type,size_bytes,created_at) VALUES (?,?,'x/none.pdf','interno.pdf','application/pdf',1,NOW())", [$tid, $internalMsg]);
    $internalFile = (int) $pdo->lastInsertId();
    $customer->get('/suporte/anexo/' . $internalFile);
    $ok($customer->status === 404, 'Anexo de nota interna é bloqueado ao cliente');
    $customer->get('/suporte/' . $proto);
    $customer->post('/suporte/' . $proto . '/responder', ['body' => 'Resposta do cliente']);
    $ok(Q::value('SELECT status FROM helpdesk_tickets WHERE id=?', [$tid]) === 'waiting_agent', 'Resposta do cliente volta para a fila');
    $customer->get('/suporte/' . $proto);
    $customer->post('/suporte/' . $proto . '/avaliar', ['rating' => 'satisfied']);
    $ok(Q::value('SELECT COUNT(*) FROM helpdesk_ratings WHERE ticket_id=?', [$tid]) == 0, 'Sem avaliação antes da resolução');
    $supportStaff->get('/chamados/' . $tid);
    $supportStaff->post('/acao/ticket-status', ['id' => $tid, 'status' => 'resolved']);
    $ok(Q::one('SELECT resolved_at FROM helpdesk_tickets WHERE id=?', [$tid])['resolved_at'] !== null, 'Resolução registra data');
    $customer->get('/suporte/' . $proto);
    $customer->post('/suporte/' . $proto . '/avaliar', ['rating' => 'satisfied', 'comment' => 'Ótimo']);
    $customer->get('/suporte/' . $proto);
    $customer->post('/suporte/' . $proto . '/avaliar', ['rating' => 'unsatisfied']);
    $ok(Q::value('SELECT rating FROM helpdesk_ratings WHERE ticket_id=?', [$tid]) === 'satisfied' && Q::value('SELECT COUNT(*) FROM helpdesk_ratings WHERE ticket_id=?', [$tid]) == 1, 'Avaliação única');
    $customer->get('/suporte/' . $proto);
    $customer->post('/suporte/' . $proto . '/reabrir');
    $row = Q::one('SELECT status, reopened_count, resolved_at FROM helpdesk_tickets WHERE id=?', [$tid]);
    $ok($row['status'] === 'waiting_agent' && (int) $row['reopened_count'] === 1 && $row['resolved_at'] === null, 'Reabertura');
    $supportStaff->post('/acao/ticket-status', ['id' => $tid, 'status' => 'resolved']);
    $customer->get('/suporte/' . $proto);
    $customer->post('/suporte/' . $proto . '/confirmar');
    $ok(Q::value('SELECT status FROM helpdesk_tickets WHERE id=?', [$tid]) === 'closed', 'Cliente confirma e encerra');
    $customer->get('/suporte/' . $proto);
    $customer->post('/suporte/' . $proto . '/responder', ['body' => 'Depois de fechado']);
    $ok(Q::value("SELECT COUNT(*) FROM helpdesk_messages WHERE body='Depois de fechado'") == 0, 'Chamado fechado não recebe resposta');
    $ok(Q::value("SELECT COUNT(*) FROM cms_audit_logs WHERE resource_type='ticket' AND resource_id=?", [(string) $tid]) >= 5, 'Ações do chamado auditadas');

    // Fila, lote e respostas rápidas
    $super->get('/chamados');
    $ok(str_contains($super->body, 'cms-tabs') && str_contains($super->body, 'name="ids[]"'), 'Fila com abas e seleção em lote');
    $supportStaff->get('/chamados');
    $supportStaff->post('/acao/quick-reply-save', ['title' => 'Bloqueada', 'body' => 'Sem permissão']);
    $ok($supportStaff->status === 403, 'Suporte não administra respostas rápidas');
    $quickTitle = 'Rápida ' . bin2hex(random_bytes(3));
    $super->get('/chamados/respostas');
    $super->post('/acao/quick-reply-save', ['title' => $quickTitle, 'body' => 'Texto pronto de teste']);
    $super->get('/chamados/' . $orgTicket['id']);
    $ok(str_contains($super->body, $quickTitle), 'Resposta rápida disponível no chamado');
    Q::run('DELETE FROM helpdesk_quick_replies WHERE title=?', [$quickTitle]);
    $super->post('/acao/ticket-bulk-status', ['ids' => '0', 'status' => 'resolved']);
    $bulk = \Moves\Services\Helpdesk::bulkStatus([$tid, $orgTicket['id']], 'resolved', ['id' => $users['super']['id']]);
    $ok($bulk === [1, 1] && Q::value('SELECT status FROM helpdesk_tickets WHERE id=?', [$tid]) === 'closed', 'Lote respeita a máquina de estados');

    // Atendimento avançado
    $super->get('/chamados/novo');
    $super->post('/acao/ticket-create', ['requester_email' => $users['other']['email'], 'subject' => 'Aberto pela equipe', 'body' => 'Contato por telefone', 'category_id' => $category]);
    $staffTicket = Q::one('SELECT * FROM helpdesk_tickets WHERE requester_user_id=? ORDER BY id DESC LIMIT 1', [$users['other']['id']]);
    $ok($staffTicket !== null && (int) $staffTicket['opened_by_user_id'] === $users['super']['id'], 'Equipe abre chamado em nome do cliente');
    $sid = (int) $staffTicket['id'];
    $other->get('/suporte/' . $staffTicket['protocol']);
    $ok($other->status === 200, 'Cliente vê o chamado aberto pela equipe');
    $super->get('/chamados/' . $sid);
    $super->post('/acao/ticket-triage', ['id' => $sid, 'team' => 'Suporte técnico', 'tags' => 'Acesso, urgente, acesso']);
    $ok(\Moves\Services\Helpdesk::tags($sid) === ['acesso', 'urgente'], 'Tags normalizadas');
    $super->get('/chamados?tag=acesso');
    $ok(str_contains($super->body, $staffTicket['protocol']), 'Filtro por tag');
    $other->get('/suporte/' . $staffTicket['protocol']);
    $ok(!str_contains($other->body, 'Suporte técnico') && !str_contains($other->body, 'urgente'), 'Equipe e tags não aparecem ao cliente');
    $supportStaff->get('/chamados/' . $sid);
    $supportStaff->post('/acao/ticket-time', ['id' => $sid, 'minutes' => '0']);
    $supportStaff->post('/acao/ticket-time', ['id' => $sid, 'minutes' => '25', 'note' => 'Análise']);
    $ok((int) Q::value('SELECT SUM(minutes) FROM helpdesk_time_entries WHERE ticket_id=?', [$sid]) === 25, 'Tempo dedicado validado e somado');
    $other->get('/suporte/' . $staffTicket['protocol']);
    $ok(!str_contains($other->body, 'Análise'), 'Tempo não aparece ao cliente');

    // Base de conhecimento
    $helpUri = 'ajuda-e2e-' . bin2hex(random_bytes(3));
    $super->get('/conteudos/novo?type=help');
    $super->post('/acao/post-save', ['title' => 'Como emitir relatório', 'uri' => $helpUri, 'subtitle' => 'Passo a passo', 'content' => '<p>Corpo da ajuda</p>', 'post_type' => 'help']);
    $helpId = (int) Q::value('SELECT id FROM posts WHERE uri=?', [$helpUri]);
    $created['posts'][] = $helpId;
    $other->get('/suporte/ajuda');
    $ok(!str_contains($other->body, 'Como emitir relatório'), 'Rascunho de ajuda fica oculto');
    $super->post('/acao/post-publish', ['id' => $helpId, 'publish' => '1']);
    $other->get('/suporte/ajuda?q=relat%C3%B3rio');
    $ok(str_contains($other->body, 'Como emitir relatório'), 'Pesquisa na base de conhecimento do App');
    $other->get('/suporte/ajuda/' . $helpUri);
    $ok($other->status === 200 && str_contains($other->body, 'Corpo da ajuda'), 'Artigo de ajuda abre no App');
    $supportStaff->get('/ajuda');
    $ok($supportStaff->status === 200 && str_contains($supportStaff->body, 'Como emitir relatório'), 'Ajuda disponível à equipe');
    $web->get('/blog/' . $helpUri);
    $ok($web->status === 404, 'Ajuda não entra no blog');

    // Assinatura no App
    $other->get('/minha-assinatura');
    $ok($other->status === 200 && str_contains($other->body, 'Sem assinatura'), 'App informa ausência de assinatura');
    $billing->get('/assinaturas');
    $billing->post('/acao/subscription-create', ['owner_email' => $users['other']['email'], 'plan_id' => $planId, 'billing_cycle' => 'annual']);
    $other->get('/minha-assinatura');
    $ok(str_contains($other->body, 'Plano E2E') && str_contains($other->body, 'Pendente') && str_contains($other->body, 'R$ 599,00'), 'App mostra plano, situação e valor contratado');
    $member->get('/minha-assinatura');
    $ok(!str_contains($member->body, 'Plano E2E'), 'Assinatura de outra pessoa não aparece');

    // Models
    $ok((new \Moves\Models\HelpdeskTicket())->findById($tid)?->protocol === $proto, 'Model de chamado lê o banco');
    $ok((new \Moves\Models\Plan())->findById($planId)?->plan_key === $key, 'Model de plano lê o banco');

    // Destaques e depoimentos
    $super->get('/destaques/novo');
    $slideTitle = 'Destaque ' . bin2hex(random_bytes(3));
    $super->post('/acao/slide-save', ['title' => $slideTitle, 'content' => '<p>Chamada</p><script>x</script>', 'link_url' => 'javascript:alert(1)', 'status' => 'published']);
    $ok(Q::value('SELECT COUNT(*) FROM cms_slides WHERE title=?', [$slideTitle]) == 0, 'Destino perigoso do destaque recusado');
    $super->post('/acao/slide-save', ['title' => $slideTitle, 'content' => '<p>Chamada</p><script>x</script>', 'link_url' => '/planos', 'link_label' => 'Ver planos', 'status' => 'published']);
    $web->get('/');
    $ok(str_contains($web->body, $slideTitle) && !str_contains($web->body, '<script>x'), 'Destaque publicado aparece no site, sanitizado');
    $editor->get('/depoimentos');
    $quote = 'Depoimento ' . bin2hex(random_bytes(3));
    $editor->post('/acao/testimonial-save', ['person_name' => 'Pessoa Teste', 'body' => $quote, 'rating' => '9', 'status' => 'published']);
    $ok(Q::value('SELECT COUNT(*) FROM cms_testimonials WHERE body=?', [$quote]) == 0, 'Nota fora de 1 a 5 recusada');
    $editor->post('/acao/testimonial-save', ['person_name' => 'Pessoa Teste', 'body' => $quote, 'rating' => '4', 'status' => 'published']);
    $web->get('/');
    $ok(str_contains($web->body, $quote), 'Depoimento publicado aparece no site');
    Q::run('DELETE FROM cms_slides WHERE title=?', [$slideTitle]);
    Q::run('DELETE FROM cms_testimonials WHERE body=?', [$quote]);

    // Notificações para os usuários
    $supportStaff->get('/chamados');
    $supportStaff->post('/acao/broadcast-send', ['title' => 'Sem permissão', 'body' => 'x']);
    $ok($supportStaff->status === 403, 'Suporte não envia comunicação');
    $super->get('/comunicacoes');
    $notice = 'Aviso ' . bin2hex(random_bytes(3));
    $super->post('/acao/broadcast-send', ['title' => $notice, 'body' => 'Manutenção programada', 'audience' => 'customers', 'channel' => 'internal']);
    $broadcast = Q::one('SELECT * FROM cms_broadcasts WHERE title=?', [$notice]);
    $ok($broadcast['status'] === 'sent' && (int) $broadcast['recipients'] >= 8, 'Comunicação entregue a todos os clientes ativos');
    $other->get('/suporte/notificacoes');
    $ok(str_contains($other->body, $notice) && str_contains($other->body, 'Manutenção programada'), 'Cliente lê a comunicação no App');
    $repeat = (int) Q::value('SELECT COUNT(*) FROM notifications WHERE broadcast_id=?', [$broadcast['id']]);
    \Moves\Services\CmsBroadcasts::dispatchDue();
    $ok((int) Q::value('SELECT COUNT(*) FROM notifications WHERE broadcast_id=?', [$broadcast['id']]) === $repeat, 'Processar de novo não duplica');
    $later = 'Agendado ' . bin2hex(random_bytes(3));
    $super->post('/acao/broadcast-send', ['title' => $later, 'body' => 'Depois', 'scheduled_at' => date('Y-m-d\\TH:i', time() + 7200)]);
    $scheduled = Q::one('SELECT * FROM cms_broadcasts WHERE title=?', [$later]);
    $ok($scheduled['status'] === 'scheduled' && Q::value('SELECT COUNT(*) FROM notifications WHERE broadcast_id=?', [$scheduled['id']]) == 0, 'Agendada não é entregue antes da hora');
    $super->post('/acao/broadcast-cancel', ['id' => $scheduled['id']]);
    $ok(Q::value('SELECT status FROM cms_broadcasts WHERE id=?', [$scheduled['id']]) === 'canceled', 'Agendada pode ser cancelada');
    Q::run('DELETE FROM cms_broadcasts WHERE id IN (?,?)', [$broadcast['id'], $scheduled['id']]);

    // Versões
    $super->get('/versoes');
    $number = '9.' . random_int(100, 999) . '.0';
    $super->post('/acao/version-publish', ['product' => 'app', 'version' => $number, 'name' => 'Teste', 'notes' => 'Notas de teste']);
    $super->post('/acao/version-publish', ['product' => 'app', 'version' => $number, 'name' => 'Duplicada', 'notes' => 'x']);
    $ok(Q::value('SELECT COUNT(*) FROM cms_versions WHERE product=? AND version=?', ['app', $number]) == 1, 'Versão publicada sem duplicar');
    $other->get('/suporte/atualizacoes');
    $ok(str_contains($other->body, $number), 'Atualização aparece no App');
    Q::run('DELETE FROM cms_versions WHERE product=? AND version=?', ['app', $number]);

    // Log técnico
    $logMessage = 'Falha de teste ' . bin2hex(random_bytes(3)) . ' usuario@example.com password=abc123';
    \Moves\Services\SystemLog::record('error', $logMessage, 'tests/x.php:1');
    \Moves\Services\SystemLog::record('error', $logMessage, 'tests/x.php:1');
    $logRow = Q::one('SELECT * FROM cms_system_logs WHERE source=? ORDER BY id DESC LIMIT 1', ['tests/x.php:1']);
    $ok((int) $logRow['occurrences'] === 2, 'Log agrupa ocorrências iguais');
    $ok(!str_contains($logRow['message'], 'usuario@example.com') && !str_contains($logRow['message'], 'abc123'), 'Log não guarda e-mail nem senha');
    $editor->get('/log');
    $ok($editor->status === 403, 'Editor não abre o log');
    $super->get('/log');
    $super->post('/acao/system-log-resolve', ['ids[0]' => $logRow['id']]);
    $ok(Q::value('SELECT status FROM cms_system_logs WHERE id=?', [$logRow['id']]) === 'resolved', 'Ocorrência marcada como resolvida');
    Q::run('DELETE FROM cms_system_logs WHERE id=?', [$logRow['id']]);

    // Acessos ao site
    $viewPath = '/teste-acesso-' . bin2hex(random_bytes(3));
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 teste'; $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['REMOTE_ADDR'] = '198.51.100.7';
    \Moves\Services\CmsAnalytics::record($viewPath);
    $view = Q::one('SELECT * FROM cms_page_views WHERE path=?', [$viewPath]);
    $ok($view !== null && !str_contains(json_encode($view), '198.51.100.7'), 'Acesso registrado sem guardar IP');
    $_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1';
    \Moves\Services\CmsAnalytics::record($viewPath);
    $ok(Q::value('SELECT COUNT(*) FROM cms_page_views WHERE path=?', [$viewPath]) == 1, 'Robôs não contam como acesso');
    $report = \Moves\Services\CmsAnalytics::report(30);
    $ok(count($report['series']) === 30 && $report['views'] >= 1, 'Relatório calcula os 30 dias');
    Q::run('DELETE FROM cms_page_views WHERE path=?', [$viewPath]);
    unset($_SERVER['HTTP_USER_AGENT'], $_SERVER['REQUEST_METHOD'], $_SERVER['REMOTE_ADDR']);

    // Exportação e voto na ajuda
    $super->get('/usuarios-exportar');
    $ok($super->status === 200 && str_contains($super->body, 'Situação') && !str_contains($super->body, $hash), 'Exportação de usuários sem senha');
    $editor->get('/usuarios-exportar');
    $ok($editor->status === 403, 'Editor não exporta usuários');
    $other->get('/suporte/ajuda/' . $helpUri);
    $other->post('/suporte/ajuda/' . $helpUri . '/votar', ['helpful' => '1']);
    $other->get('/suporte/ajuda/' . $helpUri);
    $other->post('/suporte/ajuda/' . $helpUri . '/votar', ['helpful' => '0']);
    $ok(Q::value('SELECT COUNT(*) FROM help_article_votes WHERE post_id=?', [$helpId]) == 1 && Q::value('SELECT helpful FROM help_article_votes WHERE post_id=?', [$helpId]) == 0, 'Voto único por pessoa');
    $ok((int) Q::value('SELECT views FROM posts WHERE id=?', [$helpId]) >= 1, 'Visualizações da ajuda contadas');

    // Operações restantes de cada módulo no banco
    $backup = Q::all('SELECT setting_key,setting_value FROM cms_settings');
    $super->get('/configuracoes');
    $super->post('/acao/settings-save', ['company_name' => 'Nome E2E', 'contact_email' => 'contato@example.invalid']);
    $ok(Q::value("SELECT setting_value FROM cms_settings WHERE setting_key='company_name'") === 'Nome E2E', 'Configurações salvas no banco');
    $super->post('/acao/settings-save', ['contact_email' => 'invalido']);
    $ok(Q::value("SELECT setting_value FROM cms_settings WHERE setting_key='contact_email'") === 'contato@example.invalid', 'E-mail inválido nas configurações recusado');
    Q::run('DELETE FROM cms_settings');
    foreach ($backup as $row) { Q::run('INSERT INTO cms_settings (setting_key,setting_value) VALUES (?,?)', [$row['setting_key'], $row['setting_value']]); }
    CmsSettings::forget();
    $mark = bin2hex(random_bytes(3));
    $super->get('/conteudos/faq');
    $super->post('/acao/faq-save', ['question' => 'Pergunta ' . $mark, 'answer' => 'Resposta', 'status' => 'published']);
    $faqId = (int) Q::value('SELECT id FROM faqs WHERE question=?', ['Pergunta ' . $mark]);
    $web->get('/');
    $ok($faqId > 0 && str_contains($web->body, 'Pergunta ' . $mark), 'FAQ criada aparece no site');
    $super->post('/acao/faq-save', ['id' => $faqId, 'question' => 'Editada ' . $mark, 'answer' => 'Resposta', 'status' => 'draft']);
    $ok(Q::value('SELECT question FROM faqs WHERE id=?', [$faqId]) === 'Editada ' . $mark, 'FAQ editada');
    $super->post('/acao/faq-delete', ['id' => $faqId]);
    $ok(Q::value('SELECT COUNT(*) FROM faqs WHERE id=?', [$faqId]) == 0, 'FAQ excluída');
    $super->post('/acao/category-save', ['title' => 'Categoria ' . $mark]);
    $catId = (int) Q::value('SELECT id FROM categories WHERE title=?', ['Categoria ' . $mark]);
    $super->post('/acao/category-save', ['id' => $catId, 'title' => 'Categoria Nova ' . $mark]);
    $ok(Q::value('SELECT title FROM categories WHERE id=?', [$catId]) === 'Categoria Nova ' . $mark, 'Categoria criada e editada');
    $super->post('/acao/category-delete', ['id' => $catId]);
    $ok(Q::value('SELECT COUNT(*) FROM categories WHERE id=?', [$catId]) == 0, 'Categoria excluída');
    $super->post('/acao/menu-save', ['location' => 'header', 'label' => 'Menu ' . $mark, 'url' => '/sobre', 'is_visible' => '1']);
    $menuId = (int) Q::value('SELECT id FROM cms_menu_items WHERE label=?', ['Menu ' . $mark]);
    $super->post('/acao/menu-save', ['id' => $menuId, 'location' => 'header', 'label' => 'Menu ' . $mark, 'url' => '/contato', 'is_visible' => '0']);
    $ok(Q::value('SELECT url FROM cms_menu_items WHERE id=?', [$menuId]) === '/contato', 'Item de menu editado');
    $super->post('/acao/menu-delete', ['id' => $menuId]);
    $ok(Q::value('SELECT COUNT(*) FROM cms_menu_items WHERE id=?', [$menuId]) == 0, 'Item de menu excluído');
    $mediaPng = tempnam(sys_get_temp_dir(), 'rzt') . '.png';
    $mediaImage = imagecreatetruecolor(30, 30);
    imagepng($mediaImage, $mediaPng);
    $tmpFiles[] = $mediaPng;
    $super->get('/midia');
    $super->post('/acao/media-upload', ['title' => 'Mídia ' . $mark, 'file' => new CURLFile($mediaPng, 'image/png', 'midia-' . $mark . '.png')]);
    $mediaId = (int) Q::value('SELECT id FROM cms_media WHERE title=?', ['Mídia ' . $mark]);
    $ok($mediaId > 0, 'Mídia enviada');
    $super->post('/acao/media-update', ['id' => $mediaId, 'title' => 'Mídia ' . $mark, 'alt_text' => 'Descrição']);
    $ok(Q::value('SELECT alt_text FROM cms_media WHERE id=?', [$mediaId]) === 'Descrição', 'Metadados da mídia salvos');
    $super->post('/acao/media-delete-bulk', ['ids[0]' => $mediaId]);
    $ok(Q::value('SELECT COUNT(*) FROM cms_media WHERE id=?', [$mediaId]) == 0, 'Mídia excluída em lote');
    $evilMedia = tempnam(sys_get_temp_dir(), 'rzt') . '.php';
    file_put_contents($evilMedia, '<?php echo 1;');
    $tmpFiles[] = $evilMedia;
    $super->post('/acao/media-upload', ['title' => 'Ruim ' . $mark, 'file' => new CURLFile($evilMedia, 'image/png', 'ruim.png')]);
    $ok(Q::value('SELECT COUNT(*) FROM cms_media WHERE title=?', ['Ruim ' . $mark]) == 0, 'Upload de arquivo disfarçado recusado');
    $super->get('/administradores');
    $supportRole = (int) Q::value("SELECT id FROM cms_roles WHERE role_key='support'");
    $super->post('/acao/admin-grant', ['email' => $users['member']['email'], 'role_id' => $supportRole]);
    $adminId = (int) Q::value('SELECT id FROM cms_admins WHERE user_id=?', [$users['member']['id']]);
    $ok($adminId > 0, 'Administrador concedido');
    $super->post('/acao/admin-update', ['id' => $adminId, 'role_id' => $supportRole, 'status' => 'inactive']);
    $ok(Q::value('SELECT status FROM cms_admins WHERE id=?', [$adminId]) === 'inactive', 'Administrador inativado');
    Q::run('DELETE FROM cms_admins WHERE id=?', [$adminId]);
    $matrix = Q::all('SELECT role_id,permission_key FROM cms_role_permissions');
    $super->get('/permissoes');
    $super->post('/acao/permissions-save', ['matrix[' . $supportRole . '][tickets.view]' => '1']);
    $ok((int) Q::value('SELECT COUNT(*) FROM cms_role_permissions WHERE role_id=?', [$supportRole]) === 1, 'Matriz de permissões salva');
    Q::run('DELETE FROM cms_role_permissions');
    foreach ($matrix as $row) { Q::run('INSERT INTO cms_role_permissions (role_id,permission_key) VALUES (?,?)', [$row['role_id'], $row['permission_key']]); }
    $super->get('/empresas/' . $org);
    $super->post('/acao/company-status', ['id' => $org, 'active' => '0', 'reason' => 'Teste']);
    $ok(Q::value('SELECT status FROM organizations WHERE id=?', [$org]) === 'inactive', 'Empresa inativada');
    $super->post('/acao/company-status', ['id' => $org, 'active' => '1']);
    $super->get('/chamados/categorias');
    $super->post('/acao/ticket-category-save', ['name' => 'Tipo ' . $mark, 'is_active' => '1']);
    $typeId = (int) Q::value('SELECT id FROM helpdesk_categories WHERE name=?', ['Tipo ' . $mark]);
    $super->post('/acao/ticket-category-save', ['id' => $typeId, 'name' => 'Tipo ' . $mark, 'is_active' => '0']);
    $ok($typeId > 0 && Q::value('SELECT is_active FROM helpdesk_categories WHERE id=?', [$typeId]) == 0, 'Categoria de chamado criada e desativada');
    Q::run('DELETE FROM helpdesk_categories WHERE id=?', [$typeId]);
    $slaBackup = Q::all('SELECT * FROM helpdesk_sla_targets');
    $super->get('/chamados/notificacoes');
    $super->post('/acao/sla-save', ['first[urgent]' => '30', 'resolution[urgent]' => '240']);
    $ok((int) Q::value("SELECT first_response_minutes FROM helpdesk_sla_targets WHERE priority='urgent'") === 30, 'Metas de atendimento salvas');
    foreach ($slaBackup as $row) { Q::run('UPDATE helpdesk_sla_targets SET first_response_minutes=?,resolution_minutes=? WHERE priority=?', [$row['first_response_minutes'], $row['resolution_minutes'], $row['priority']]); }
    $super->post('/acao/slide-save', ['title' => 'Slide ' . $mark, 'status' => 'draft']);
    $slideId = (int) Q::value('SELECT id FROM cms_slides WHERE title=?', ['Slide ' . $mark]);
    $super->post('/acao/slide-save', ['id' => $slideId, 'title' => 'Slide Novo ' . $mark, 'status' => 'draft']);
    $ok(Q::value('SELECT title FROM cms_slides WHERE id=?', [$slideId]) === 'Slide Novo ' . $mark, 'Destaque editado');
    $super->post('/acao/slide-delete', ['id' => $slideId]);
    $ok(Q::value('SELECT COUNT(*) FROM cms_slides WHERE id=?', [$slideId]) == 0, 'Destaque excluído');
    $super->post('/acao/testimonial-save', ['person_name' => 'Pessoa ' . $mark, 'body' => 'Texto', 'rating' => '5']);
    $quoteId = (int) Q::value('SELECT id FROM cms_testimonials WHERE person_name=?', ['Pessoa ' . $mark]);
    $super->post('/acao/testimonial-save', ['id' => $quoteId, 'person_name' => 'Pessoa ' . $mark, 'body' => 'Novo', 'rating' => '3']);
    $ok(Q::value('SELECT rating FROM cms_testimonials WHERE id=?', [$quoteId]) == 3, 'Depoimento editado');
    $super->post('/acao/testimonial-delete', ['id' => $quoteId]);
    $ok(Q::value('SELECT COUNT(*) FROM cms_testimonials WHERE id=?', [$quoteId]) == 0, 'Depoimento excluído');
    $super->post('/acao/post-delete', ['id' => $postId]);
    $ok(Q::value('SELECT deleted_at FROM posts WHERE id=?', [$postId]) !== null, 'Conteúdo excluído sem apagar o histórico');
    $billing->get('/assinaturas');
    $billing->post('/acao/subscription-create', ['owner_email' => $users['owner']['email'], 'plan_id' => $planId, 'billing_cycle' => 'monthly']);
    $flowSub = Q::one('SELECT * FROM subscriptions WHERE user_id=?', [$users['owner']['id']]);
    $flowCharge = Q::one('SELECT * FROM platform_charges WHERE subscription_id=?', [$flowSub['id']]);
    $billing->post('/acao/charge-paid', ['id' => $flowCharge['id'], 'reference' => 'FLUXO-' . $mark]);
    $billing->post('/acao/subscription-renewal', ['id' => $flowSub['id']]);
    $billing->post('/acao/subscription-renewal', ['id' => $flowSub['id']]);
    $ok((int) Q::value('SELECT COUNT(*) FROM platform_charges WHERE subscription_id=?', [$flowSub['id']]) === 2, 'Renovação emite uma única cobrança');
    $renewal = Q::one("SELECT * FROM platform_charges WHERE subscription_id=? AND status='pending'", [$flowSub['id']]);
    $billing->post('/acao/charge-failed', ['id' => $renewal['id'], 'note' => 'Cartão recusado']);
    $ok(Q::value('SELECT status FROM subscriptions WHERE id=?', [$flowSub['id']]) === 'past_due', 'Falha de pagamento marca assinatura em atraso');
    $billing->post('/acao/subscription-plan', ['id' => $flowSub['id'], 'plan_id' => $planId, 'billing_cycle' => 'annual']);
    $ok(Q::value('SELECT billing_cycle FROM subscriptions WHERE id=?', [$flowSub['id']]) === 'annual', 'Alteração de plano da assinatura');
    $super->get('/usuarios/' . $users['target']['id']);
    $super->post('/acao/user-suspend', ['id' => $users['target']['id'], 'suspend' => '0']);
    $ok(Q::value('SELECT status FROM users WHERE id=?', [$users['target']['id']]) === 'active', 'Usuário reativado');

    // Central de Suporte pública
    $public = new Client('www.realizze.lab');
    $public->get('/suporte');
    $ok($public->status === 200 && str_contains($public->body, 'Como podemos ajudar'), 'Central de Suporte abre sem login');
    $ok(str_contains($public->body, 'Como abrir um chamado') && !str_contains($public->body, 'Como alterar preços de um plano'), 'Base pública sem os guias internos da equipe');
    $public->get('/suporte?q=' . rawurlencode('anexar'));
    $ok(str_contains($public->body, 'Quais arquivos posso anexar') && !str_contains($public->body, 'Onde vejo meu plano'), 'Busca na base pública');
    $public->get('/suporte/artigo/abrir-chamado');
    $ok($public->status === 200 && str_contains($public->body, 'protocolo'), 'Artigo público abre');
    $public->get('/suporte/artigo/alterar-precos-plano');
    $ok($public->status === 404, 'Guia interno não abre no site');
    $other->get('/suporte/ajuda/alterar-precos-plano');
    $ok($other->status === 404, 'Guia interno não abre no App');
    $supportStaff->get('/ajuda');
    $ok(str_contains($supportStaff->body, 'Como alterar preços de um plano'), 'Equipe vê os guias internos');
    $contactMark = 'Assunto ' . bin2hex(random_bytes(3));
    $public->get('/suporte');
    $publicToken = $public->token();
    $public->post('/suporte/mensagem', ['csrf_token' => str_repeat('0', 64), 'name' => 'A', 'email' => 'a@example.invalid', 'subject' => $contactMark, 'message' => 'Mensagem']);
    $ok($public->status === 403, 'Formulário público exige CSRF');
    $public->post('/suporte/mensagem', ['csrf_token' => $publicToken, 'name' => 'Robô', 'email' => 'r@example.invalid', 'subject' => $contactMark, 'message' => 'Propaganda', 'website' => 'http://spam']);
    $ok(Q::value('SELECT COUNT(*) FROM contact_messages WHERE subject=?', [$contactMark]) == 0, 'Robô no campo oculto não grava');
    $public->post('/suporte/mensagem', ['csrf_token' => $publicToken, 'name' => 'Pessoa', 'email' => 'invalido', 'subject' => $contactMark, 'message' => 'Mensagem']);
    $ok(Q::value('SELECT COUNT(*) FROM contact_messages WHERE subject=?', [$contactMark]) == 0, 'E-mail inválido recusado');
    $public->post('/suporte/mensagem', ['csrf_token' => $publicToken, 'name' => '<b>Pessoa</b>', 'phone' => '(11) 99999-0000', 'email' => 'pessoa@example.invalid', 'subject' => $contactMark, 'message' => '<script>alert(1)</script> Preciso de ajuda', 'whatsapp_ok' => '1']);
    $contact = Q::one('SELECT * FROM contact_messages WHERE subject=?', [$contactMark]);
    $ok($contact !== null && (int) $contact['whatsapp_ok'] === 1, 'Mensagem gravada no banco');
    $supportStaff->get('/mensagens');
    $ok(str_contains($supportStaff->body, $contactMark) && str_contains($supportStaff->body, '&lt;script&gt;') && !str_contains($supportStaff->body, '<script>alert'), 'Equipe lê a mensagem, com conteúdo escapado');
    $ok(Q::value('SELECT COUNT(*) FROM notifications WHERE user_id=? AND dedupe_key=?', [$users['support']['id'], 'contact:' . $contact['id']]) == 1, 'Equipe é notificada da mensagem');
    $editor->get('/mensagens');
    $ok($editor->status === 403, 'Editor não lê mensagens do site');
    $supportStaff->get('/mensagens');
    $supportStaff->post('/acao/contact-status', ['id' => $contact['id'], 'answered' => '1']);
    $ok(Q::value('SELECT status FROM contact_messages WHERE id=?', [$contact['id']]) === 'answered', 'Mensagem marcada como respondida');
    Q::run('DELETE FROM notifications WHERE dedupe_key=?', ['contact:' . $contact['id']]);
    Q::run('DELETE FROM contact_messages WHERE id=?', [$contact['id']]);
    @unlink(sys_get_temp_dir() . '/realizze-auth-limits/' . hash('sha256', 'support-contact' . "\0" . '127.0.0.1') . '.json');

    // Privacidade
    $supportStaff->get('/chamados/' . $tid);
    $ok(!str_contains($supportStaff->body, 'finance_') && !str_contains($supportStaff->body, 'Saldo'), 'Atendente não recebe dados financeiros do cliente');

    // Indicadores
    $report = \Moves\Services\Helpdesk::report();
    $ok($report['total'] >= 2 && $report['first_response_avg'] !== null && $report['reopened'] >= 1, 'Indicadores calculados');
    $dash = \Moves\Services\PlatformDirectory::dashboard();
    $ok(($dash['tickets']['closed'] ?? 0) >= 1 && $dash['users_total'] >= 9, 'Dashboard lê o banco');

    // ---- Notificações ----
    $ok(Q::value("SELECT COUNT(*) FROM notifications WHERE user_id=? AND audience='customer' AND ticket_id=?", [$users['common']['id'], $tid]) >= 2, 'Cliente recebeu notificações internas');
    $ok(Q::value("SELECT COUNT(*) FROM notifications WHERE user_id=? AND audience='staff' AND ticket_id=?", [$users['support']['id'], $tid]) >= 1, 'Equipe recebeu notificação interna');
    $count = (int) Q::value("SELECT COUNT(*) FROM notifications WHERE ticket_id=?", [$tid]);
    \Moves\Services\HelpdeskNotifier::ticketCreated($tid);
    $ok((int) Q::value('SELECT COUNT(*) FROM notifications WHERE ticket_id=?', [$tid]) === $count, 'Repetir o evento não duplica notificação');
    $deliveries = (int) Q::value('SELECT COUNT(*) FROM notification_deliveries WHERE ticket_id=?', [$tid]);
    \Moves\Services\HelpdeskNotifier::ticketCreated($tid);
    $ok((int) Q::value('SELECT COUNT(*) FROM notification_deliveries WHERE ticket_id=?', [$tid]) === $deliveries && $deliveries > 0, 'Repetir o evento não duplica entrega externa');
    $tg = json_decode((string) Q::value("SELECT payload_json FROM notification_deliveries WHERE ticket_id=? AND channel='telegram' LIMIT 1", [$tid]), true)['text'];
    $ok(str_contains($tg, $proto) && !str_contains($tg, 'Descrição privada') && !str_contains($tg, '529.982.247-25'), 'Telegram sem descrição nem CPF');
    Q::run("UPDATE notification_deliveries SET status='skipped' WHERE status='pending' AND ticket_id<>?", [$tid]);
    $sent = [];
    $result = ExternalNotifications::process(['telegram' => static function (array $p): void { throw new RuntimeException('Falha simulada'); }, 'email' => static function (array $p) use (&$sent): void { $sent[] = $p; }]);
    $ok($result['failed'] > 0 && Q::value("SELECT COUNT(*) FROM notification_deliveries WHERE ticket_id=? AND channel='telegram' AND status='pending' AND attempts=1 AND last_error='Falha simulada'", [$tid]) > 0, 'Falha externa registrada para nova tentativa');
    $ok($sent !== [] && Q::value("SELECT status FROM helpdesk_tickets WHERE id=?", [$tid]) === 'closed', 'Falha externa não afeta o chamado; e-mail simulado segue');
    Q::run("UPDATE notification_deliveries SET attempts=4, next_attempt_at=NOW() WHERE ticket_id=? AND channel='telegram'", [$tid]);
    ExternalNotifications::process(['telegram' => static function (array $p): void { throw new RuntimeException('Falha simulada'); }, 'email' => static function (array $p): void {}]);
    $ok(Q::value("SELECT COUNT(*) FROM notification_deliveries WHERE ticket_id=? AND channel='telegram' AND status='failed'", [$tid]) > 0, 'Retentativas têm limite');
    $customerRead = $customer;
    $customerRead->get('/suporte');
    $customerRead->post('/suporte/notificacoes/lidas');
    $ok(\Moves\Services\HelpdeskNotifier::unread($users['common']['id'], 'customer') === 0, 'Marcar notificações como lidas');

    // ---- Site e App seguem funcionando ----
    foreach (['/', '/sobre', '/solucoes', '/contato', '/termos', '/privacidade'] as $path) {
        $web->get($path);
        $ok($web->status === 200, "Site {$path}");
    }
    $customer->get('/minha-conta');
    if ($customer->status === 302) { $customer->get(parse_url($customer->location, PHP_URL_PATH)); }
    $ok($customer->status === 200 && str_contains($customer->body, 'id="sp-tab"') && str_contains($customer->body, 'id="sp-panel"'), 'Painel financeiro abre com a aba de suporte');
    $customer->get('/minha-conta?pagina=receber');
    if ($customer->status === 302) { $customer->get(parse_url($customer->location, PHP_URL_PATH)); }
    $ok($customer->status === 200, 'Módulo Receber continua abrindo');
} finally {
    $ids = array_column($users, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $tickets = array_column(Q::all("SELECT id FROM helpdesk_tickets WHERE requester_user_id IN ({$in})", $ids), 'id');
    foreach ($tickets as $ticketId) {
        foreach (Q::all('SELECT stored_path FROM helpdesk_attachments WHERE ticket_id=?', [$ticketId]) as $a) {
            @unlink(BASE_PATH . '/storage/helpdesk/' . $a['stored_path']);
        }
        foreach (['helpdesk_attachments', 'helpdesk_messages', 'helpdesk_events', 'helpdesk_ratings', 'notification_deliveries', 'notifications'] as $table) {
            Q::run("DELETE FROM {$table} WHERE ticket_id=?", [$ticketId]);
        }
        Q::run('DELETE FROM helpdesk_tickets WHERE id=?', [$ticketId]);
    }
    Q::run("DELETE a FROM platform_charge_attempts a JOIN platform_charges c ON c.id=a.charge_id JOIN subscriptions s ON s.id=c.subscription_id WHERE s.user_id IN ({$in})", $ids);
    Q::run("DELETE c FROM platform_charges c JOIN subscriptions s ON s.id=c.subscription_id WHERE s.user_id IN ({$in})", $ids);
    Q::run("DELETE e FROM subscription_events e JOIN subscriptions s ON s.id=e.subscription_id WHERE s.user_id IN ({$in})", $ids);
    Q::run("DELETE FROM subscriptions WHERE user_id IN ({$in})", $ids);
    foreach ($created['plans'] as $id) { Q::run('DELETE FROM plan_prices WHERE plan_id=?', [$id]); Q::run('DELETE FROM plans WHERE id=?', [$id]); }
    foreach ($created['pages'] as $id) { Q::run('DELETE FROM cms_pages WHERE id=?', [$id]); }
    foreach ($created['posts'] as $id) { Q::run('DELETE FROM posts WHERE id=?', [$id]); }
    if ($org !== null) {
        Q::run('DELETE FROM organization_audit_logs WHERE organization_id=?', [$org]);
        Q::run('DELETE FROM organization_members WHERE organization_id=?', [$org]);
        Q::run('DELETE FROM organizations WHERE id=?', [$org]);
    }
    Q::run("DELETE FROM cms_settings WHERE setting_key LIKE 'notify_%'");
    foreach ($settingsBackup as $row) {
        Q::run('INSERT INTO cms_settings (setting_key,setting_value) VALUES (?,?)', [$row['setting_key'], $row['setting_value']]);
    }
    Q::run("DELETE FROM cms_audit_logs WHERE actor_user_id IN ({$in})", $ids);
    Q::run("DELETE FROM users WHERE id IN ({$in})", $ids);
    foreach ($tmpFiles as $path) { @unlink($path); }
}
echo $failures === 0 ? "CMS-100: APROVADO.\n" : "CMS-100: {$failures} FALHA(S).\n";
exit($failures === 0 ? 0 : 1);
