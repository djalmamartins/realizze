<?php
declare(strict_types=1);

use MovesCode\Router\Router;
use MovesCode\View\Engine;
use Moves\Services\AppUrl;

return [
    'type' => 'app',
    'name' => 'realizze',
    'version' => 'v1',
    'controller' => null,
    'routes' => static function (Router $router): void {
        $previewEnabled = getenv('APP_ENV') === 'local'
            ? getenv('REALIZZE_APP_LOCAL_PREVIEW') === '1'
            : getenv('REALIZZE_APP_ENABLED') === '1';
        $webView = new Engine(BASE_PATH . '/container/web/realizze/v1');
        $webView->addFolder('components', BASE_PATH . '/container/web/realizze/v1/components');

        $unavailable = static function (): void {
            http_response_code(503);
            header('Cache-Control: no-store');
            header('Retry-After: 3600');
            require __DIR__ . '/default.php';
        };
        // Um envio vale para a empresa da tela. Se outra aba trocou a empresa da sessão, nada é gravado.
        $sameContext = static function (): bool {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_string($_POST['context_organization_id'] ?? null)) {
                return true;
            }
            $posted = (int) $_POST['context_organization_id'];
            if ($posted === (int) (\Moves\Services\OrganizationContext::current()['id'] ?? 0)) {
                return true;
            }
            $organization = $posted > 0 ? \Moves\Services\OrganizationAccess::authorizedOrganization($posted) : null;
            if ($posted > 0 && $organization === null) {
                \Moves\Services\AppError::render(403);
                return false;
            }
            $_SESSION['finance_flash'] = ['error', 'Nada foi salvo: outra aba trocou a área ativa. Confira os dados e envie novamente.'];
            header('Cache-Control: no-store');
            header('Location: ' . ($organization === null ? '/p' : \Moves\Services\AppUrl::base($organization)), true, 303);
            return false;
        };
        $requirePreview = static function () use ($previewEnabled, $unavailable, $sameContext): bool {
            if ($previewEnabled) {
                return $sameContext();
            }
            $unavailable();
            return false;
        };
        $renderLogin = static function (bool $loginError = false) use ($webView): void {
            echo $webView->render('pages/login', ['loginError' => $loginError]);
        };
        $renderError = static function (int $status): void {
            \Moves\Services\AppError::render($status);
        };
        $renderDashboard = static function () use ($requirePreview): void {
            if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) {
                return;
            }
            require __DIR__ . '/pages/dashboard.php';
        };

        $organizationSections = ['contatos', 'clientes', 'fornecedores', 'equipe', 'empresa', 'fluxo-de-caixa', 'faturas', 'conciliacao', 'centros-de-custo', 'dre', 'obrigacoes', 'ordens-de-servico', 'permissoes', 'seguranca', 'auditoria'];
        $renderContextDashboard = static function (string $context, string $section = 'overview', ?string $organizationSlug = null, ?array $period = null) use ($renderDashboard, $renderError, $organizationSections): void {
            if (\Moves\Services\SessionAuth::current() === null) {
                header('Location: /entrar', true, 302);
                return;
            }
            if ($context === 'personal') {
                \Moves\Services\OrganizationContext::clear();
                if ($section === 'perfil') \Moves\Services\PersonalAccessContext::clear();
                $organization = null;
            } else {
                $organization = is_string($organizationSlug) ? \Moves\Services\OrganizationAccess::authorizedBySlug($organizationSlug) : null;
                if ($organization === null || !\Moves\Services\OrganizationContext::selectFromRoute((int) $organization['id'])) {
                    $renderError(404);
                    return;
                }
            }
            // Faturas atendem PF e PJ; os demais itens desta lista são exclusivos da empresa.
            if (((in_array($section, $organizationSections, true) && $section !== 'faturas') || $section === 'contato-detalhe') && $organization === null) {
                $renderError(404);
                return;
            }
            if ($organization !== null) {
                $sectionPermission = ['receber' => 'finance.view', 'pagar' => 'finance.view', 'movimentacoes' => 'finance.view', 'recorrencias' => 'finance.view', 'fluxo-de-caixa' => 'finance.view', 'faturas' => 'finance.view', 'conciliacao' => 'finance.view', 'centros-de-custo' => 'finance.view', 'contas' => 'accounts.view', 'clientes' => 'contacts.view', 'fornecedores' => 'contacts.view', 'contatos' => 'contacts.view', 'categorias' => 'finance.view', 'contato-detalhe' => 'contacts.view', 'relatorios' => 'reports.view', 'dre' => 'reports.view', 'planejamento' => 'planning.view', 'metas' => 'planning.view', 'overview' => 'finance.view', 'auditoria' => 'team.manage'][$section] ?? null;
                $sessionUser = \Moves\Services\SessionAuth::current();
                if ($sectionPermission !== null && !\Moves\Services\OrganizationTeam::can((int) $sessionUser['id'], (int) $organization['id'], $sectionPermission)) {
                    $renderError(403);
                    return;
                }
            }
            // Prévias antigas que ficaram fora do menu da empresa levam à tela que as substituiu.
            $replaced = ['empresarial' => 'overview', 'configuracoes' => 'empresa', 'assistente' => 'overview', 'contatos' => 'clientes'];
            if ($organization !== null && isset($replaced[$section])) {
                header('Cache-Control: no-store');
                header('Location: ' . AppUrl::section($replaced[$section], $organization), true, 302);
                return;
            }
            // Sair das Dívidas é um módulo PF: não existe dentro de uma empresa.
            if ($section === 'sair-das-dividas' && $organization !== null) {
                $renderError(404);
                return;
            }
            if ($organization === null && \Moves\Services\PersonalAccessContext::ownerId() !== null) {
                $permission = ['overview'=>'dashboard.view','receber'=>'income.view','pagar'=>'expenses.view','contas'=>'accounts.view','relatorios'=>'reports.view','metas'=>'goals.manage','categorias'=>'categories.manage'][$section] ?? null;
                if ($permission === null || !\Moves\Services\PersonalAccessContext::can($permission)) { $renderError(403); return; }
            }
            if ($period !== null) {
                // A URL define o período; a tela continua lendo os mesmos parâmetros.
                // Em Fluxo de caixa, visao=dia|semana é agrupamento, não período, e continua valendo.
                $stale = ['mes' => 1, 'ano' => 1, 'inicio' => 1, 'fim' => 1] + (($_GET['visao'] ?? '') === 'anual' ? ['visao' => 1] : []);
                $_GET = $period + array_diff_key($_GET, $stale);
            } elseif (AppUrl::hasPeriodQuery($section, $_GET)) {
                header('Cache-Control: no-store');
                header('Location: ' . AppUrl::section($section, $organization, array_diff_key($_GET, ['pagina' => 1])), true, 302);
                return;
            }
            $_GET['pagina'] = $section;
            if ($section === 'clientes') $_GET['papel'] = 'customer';
            if ($section === 'fornecedores') $_GET['papel'] = 'supplier';
            $renderDashboard();
        };

        $router->get('/', static function (): void {
            header('Location: ' . (\Moves\Services\SessionAuth::current() === null ? '/entrar' : AppUrl::base()), true, 302);
        }, 'app.index');
        $router->get('/minha-conta', static function () use ($requirePreview): void {
            if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) return;
            $page = is_string($_GET['pagina'] ?? null) ? $_GET['pagina'] : 'overview';
            $query = $_GET;
            unset($query['pagina']);
            header('Cache-Control: no-store');
            header('Location: ' . AppUrl::section($page, null, $query), true, 302);
        }, 'app.account');

        $semantic = ['' => 'overview', 'notificacoes' => 'notificacoes', 'integracoes' => 'integracoes', 'perfil' => 'perfil', 'receber' => 'receber', 'pagar' => 'pagar', 'movimentacoes' => 'movimentacoes', 'cartoes' => 'contas', 'faturas' => 'faturas', 'conciliacao' => 'conciliacao', 'relatorios' => 'relatorios', 'planejamento' => 'planejamento', 'metas' => 'metas', 'categorias' => 'categorias', 'recorrencias' => 'recorrencias', 'assistente' => 'assistente', 'sair-das-dividas' => 'sair-das-dividas', 'configuracoes' => 'configuracoes', 'empresarial' => 'empresarial'];
        foreach ($semantic as $slug => $section) {
            $router->get('/p' . ($slug === '' ? '' : '/' . $slug), static fn () => $renderContextDashboard('personal', $section), 'app.personal.' . $section);
            $router->get('/e/{organization}' . ($slug === '' ? '' : '/' . $slug), static fn (array $params) => $renderContextDashboard('organization', $section, (string) ($params['organization'] ?? '')), 'app.organization.' . $section);
        }
        foreach ($organizationSections as $section) {
            $router->get('/e/{organization}/' . $section, static fn (array $params) => $renderContextDashboard('organization', $section, (string) ($params['organization'] ?? '')), 'app.organization.' . $section);
        }
        foreach (['clientes' => 'customer', 'fornecedores' => 'supplier'] as $listSlug => $listRole) {
            $router->get('/e/{organization}/' . $listSlug . '/{contact}', static function (array $params) use ($renderContextDashboard, $renderError, $listRole): void {
                $contact = filter_var($params['contact'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($contact === false) {
                    $renderError(404);
                    return;
                }
                $_GET['contato_id'] = $contact;
                $_GET['papel'] = $listRole;
                $renderContextDashboard('organization', 'contato-detalhe', (string) ($params['organization'] ?? ''));
            }, 'app.organization.' . $listSlug . '.detail');
        }
        foreach ($semantic as $slug => $section) {
            foreach (['/pf/pessoal', '/pj/minha-empresa'] as $oldBase) {
                $router->get($oldBase . ($slug === '' ? '' : '/' . $slug), static function () use ($section): void {
                    header('Location: ' . AppUrl::section($section, null, $_GET), true, 302);
                });
            }
        }
        foreach ($organizationSections as $section) {
            $router->get('/pj/minha-empresa/' . $section, static function () use ($section): void {
                header('Location: ' . AppUrl::section($section, null, $_GET), true, 302);
            });
        }

        $router->get('/entrar', static function () use ($requirePreview, $renderLogin): void {
            if (!$requirePreview()) {
                return;
            }
            if (\Moves\Services\SessionAuth::current() !== null) {
                header('Location: /p', true, 302);
                return;
            }
            $renderLogin();
        }, 'app.login');

        $router->get('/cadastrar', static function () use ($requirePreview,$webView): void {
            if(!$requirePreview())return;
            if(\Moves\Services\SessionAuth::current()!==null){header('Location: /p',true,302);return;}
            header('Cache-Control: no-store');
            echo $webView->render('pages/app-register',['registerError'=>null,'registerSuccess'=>isset($_GET['enviado'])]);
        },'app.register');

        $router->post('/cadastrar', static function () use ($requirePreview,$webView): void {
            if(!$requirePreview())return;
            \Moves\App\Register::handle($webView);
        },'app.register.submit');

        // Estas páginas ficam no site; os links do acesso continuam funcionando no App.
        foreach (['/recuperar-senha', '/reenviar-confirmacao'] as $sitePath) {
            $router->get($sitePath, static function () use ($sitePath): void {
                header('Cache-Control: no-store');
                header('Location: ' . rtrim((string) getenv('APP_URL'), '/') . $sitePath, true, 302);
            });
        }

        $router->post('/entrar', static function () use ($requirePreview, $renderLogin): void {
            if (!$requirePreview()) {
                return;
            }
            // O login recria a sessão; o convite de empresa aberto antes de entrar é guardado e retomado.
            $pendingInvite = is_string($_SESSION['team_pending_invite'] ?? null) ? $_SESSION['team_pending_invite'] : null;
            if ((new \Moves\App\Login())->submit()) {
                if ($pendingInvite !== null) $_SESSION['team_pending_invite'] = $pendingInvite;
                header('Location: ' . (\Moves\App\Login::pendingSecondFactor() ? '/entrar/2fa' : ($pendingInvite !== null ? '/convite-empresa' : '/p')), true, 302);
                return;
            }
            http_response_code(401);
            $renderLogin(true);
        }, 'app.login.submit');

        $router->get('/entrar/2fa', static function () use ($requirePreview, $webView): void {
            if (!$requirePreview()) return;
            if (!\Moves\App\Login::pendingSecondFactor()) { header('Location: /entrar', true, 302); return; }
            echo $webView->render('pages/login-2fa', ['twoFactorError' => false]);
        }, 'app.login.2fa');

        $router->post('/entrar/2fa', static function () use ($requirePreview, $webView): void {
            if (!$requirePreview()) return;
            $pendingInvite = is_string($_SESSION['team_pending_invite'] ?? null) ? $_SESSION['team_pending_invite'] : null;
            if ((new \Moves\App\Login())->submitSecondFactor()) {
                if ($pendingInvite !== null) $_SESSION['team_pending_invite'] = $pendingInvite;
                header('Location: ' . ($pendingInvite !== null ? '/convite-empresa' : '/p'), true, 302);
                return;
            }
            http_response_code(401);
            echo $webView->render('pages/login-2fa', ['twoFactorError' => true]);
        }, 'app.login.2fa.submit');

        $router->post('/minha-conta/contexto', static function () use ($requirePreview, $renderError): void {
            if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) {
                return;
            }
            if (!\Moves\Services\Csrf::validate($_POST['csrf_token'] ?? null)) {
                $renderError(403);
                return;
            }
            $type = $_POST['context_type'] ?? null;
            if ($type === 'personal') {
                $changed = \Moves\Services\OrganizationContext::selectPersonal($_POST['csrf_token']);
                \Moves\Services\PersonalAccessContext::clear();
            } elseif ($type === 'shared') {
                $rawId = $_POST['owner_user_id'] ?? null;
                $id = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) : false;
                $changed = $id !== false && \Moves\Services\PersonalAccessContext::select((int)$id, $_POST['csrf_token']);
            } elseif ($type === 'organization') {
                $rawId = $_POST['organization_id'] ?? null;
                $id = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]) : false;
                if ($id === false) {
                    http_response_code(422);
                    return;
                }
                $changed = \Moves\Services\OrganizationContext::select($id, $_POST['csrf_token']);
            } else {
                http_response_code(422);
                return;
            }
            if (!$changed) {
                $renderError(403);
                return;
            }
            header('Cache-Control: no-store');
            header('Location: ' . AppUrl::base(), true, 303);
        }, 'app.context.switch');

        if (getenv('APP_ENV') === 'local') {
            $router->get('/__teste/erro-500', static function () use ($requirePreview): void {
                if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) return;
                throw new \RuntimeException('Falha controlada para validar a resposta 500 do App.');
            }, 'app.error.test');
        }

        $router->get('/minha-conta/financeiro/exportar', static function () use ($requirePreview): void {
            if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) {
                return;
            }
            if (!\Moves\Services\PersonalAccessContext::can('reports.view')) { \Moves\Services\AppError::render(403); return; }
            (new \Moves\App\FinanceActions())->export();
        }, 'app.finance.export');

        $router->get('/minha-conta/financeiro/anexo', static function () use ($requirePreview): void {
            if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) {
                return;
            }
            (new \Moves\App\FinanceActions())->attachment();
        }, 'app.finance.attachment');

        $router->get('/minha-conta/financeiro/meta-foto', static function () use ($requirePreview): void {
            if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) {
                return;
            }
            (new \Moves\App\FinanceActions())->goalPhoto();
        }, 'app.finance.goal_photo');

        foreach (['create' => 'lancamento', 'update' => 'lancamento-editar', 'settle' => 'baixa', 'delete' => 'excluir', 'account' => 'conta', 'account_update' => 'conta-editar', 'account_archive' => 'conta-arquivar', 'transfer' => 'transferencia', 'company' => 'empresa', 'category' => 'categoria', 'category_delete' => 'categoria-excluir', 'budget' => 'orcamento', 'budget_delete' => 'orcamento-excluir', 'budget_copy' => 'orcamento-copiar', 'goal' => 'meta', 'goal_update' => 'meta-editar', 'goal_add' => 'meta-valor', 'goal_contribution_delete' => 'meta-aporte-excluir', 'goal_delete' => 'meta-excluir', 'goal_reserve' => 'meta-guardar', 'goal_release' => 'meta-resgatar', 'goal_transfer' => 'meta-transferir', 'goal_pay' => 'meta-pagar', 'goal_reverse' => 'meta-estornar', 'goal_status' => 'meta-situacao', 'goal_schedule' => 'meta-aporte-programado', 'goal_schedule_status' => 'meta-aporte-programado-situacao'] as $action => $slug) {
            $router->post('/minha-conta/financeiro/' . $slug, static function () use ($requirePreview, $action): void {
                if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) {
                    return;
                }
                if (\Moves\Services\PersonalAccessContext::ownerId() !== null) {
                    $permission = match ($action) {
                        'create' => ($_POST['kind'] ?? '') === 'income' ? 'income.create' : 'expenses.create',
                        'settle' => 'payments.register', 'update', 'delete' => 'entries.edit',
                        'category','category_delete' => 'categories.manage',
                        'goal','goal_update','goal_add','goal_contribution_delete','goal_delete','goal_reserve','goal_release','goal_transfer','goal_status','goal_schedule','goal_schedule_status' => 'goals.manage',
                        // Estas duas mexem em lançamentos: além de metas, exigem a permissão de lançamentos.
                        'goal_pay' => \Moves\Services\PersonalAccessContext::can('goals.manage') ? 'payments.register' : '',
                        'goal_reverse' => \Moves\Services\PersonalAccessContext::can('goals.manage') ? 'entries.edit' : '',
                        default => '',
                    };
                    if ($permission === '' || !\Moves\Services\PersonalAccessContext::can($permission)) { \Moves\Services\AppError::render(403); return; }
                }
                (new \Moves\App\FinanceActions())->handle($action);
            }, 'app.finance.' . $action);
        }

        foreach (['card_purchase' => 'cartao-compra', 'card_pay' => 'cartao-pagar-fatura', 'card_reverse' => 'cartao-estornar-pagamento', 'cost_center' => 'centro-de-custo', 'reconciliation_import' => 'conciliacao-importar', 'reconciliation_review' => 'conciliacao-revisar'] as $action => $slug) {
            $router->post('/minha-conta/financeiro/' . $slug, static function () use ($requirePreview, $action): void {
                if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) return;
                (new \Moves\App\CommercialFinanceActions())->handle($action);
            }, 'app.commercial_finance.' . $action);
        }

        foreach (['pause'=>'pausar','activate'=>'reativar','cancel'=>'cancelar'] as $action=>$slug) {
            $router->post('/minha-conta/financeiro/recorrencia/'.$slug, static function () use ($requirePreview,$action): void {
                if(!$requirePreview()||!\Moves\Services\AccessPolicy::requireAccess('app'))return;
                if(\Moves\Services\PersonalAccessContext::ownerId()!==null){\Moves\Services\AppError::render(403);return;}
                (new \Moves\App\RecurringActions())->handle($action);
            },'app.recurring.'.$action);
        }

        foreach (['settle' => 'recebimento-baixa', 'cancel' => 'recebimento-cancelar'] as $action => $slug) {
            $router->post('/minha-conta/financeiro/' . $slug, static function () use ($requirePreview, $action): void {
                if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) {
                    return;
                }
                if (\Moves\Services\PersonalAccessContext::ownerId() !== null) {
                    $permission = $action === 'settle' ? 'payments.register' : 'entries.edit';
                    if (!\Moves\Services\PersonalAccessContext::can($permission)) { \Moves\Services\AppError::render(403); return; }
                }
                (new \Moves\App\ReceivableActions())->handle($action);
            }, 'app.receivables.' . $action);
        }

        foreach (['create' => 'salvar', 'update' => 'atualizar', 'status' => 'status'] as $action => $slug) {
            $router->post('/minha-conta/contatos/' . $slug, static function () use ($requirePreview, $action): void {
                if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) return;
                (new \Moves\App\ContactActions())->handle($action);
            }, 'app.contacts.' . $action);
        }


        // Logos de instituições: só autenticado, só arquivos aprovados e com REALIZZE_BANK_LOGOS_ENABLED=1.
        $router->get('/minha-conta/bancos/logo/{logo}', static function (array $params) use ($requirePreview): void {
            if (!$requirePreview() || \Moves\Services\SessionAuth::current() === null) {
                http_response_code(404);
                return;
            }
            \Moves\Services\FinancialInstitutions::serveLogo((string) ($params['logo'] ?? ''));
        }, 'app.institutions.logo');

        foreach (['profile' => 'diagnostico', 'create' => 'salvar', 'update' => 'atualizar', 'archive' => 'remover', 'payment' => 'pagamento', 'payment_reverse' => 'pagamento-estornar', 'negotiation' => 'negociacao', 'negotiation_status' => 'negociacao-situacao'] as $action => $slug) {
            $router->post('/minha-conta/dividas/' . $slug, static function () use ($requirePreview, $action): void {
                if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) return;
                (new \Moves\App\DebtActions())->handle($action);
            }, 'app.debts.' . $action);
        }

        foreach (['open' => 'abrir', 'read_all' => 'lidas', 'preferences' => 'preferencias'] as $action => $slug) {
            $router->post('/minha-conta/notificacoes/' . $slug, static function () use ($requirePreview, $action): void {
                if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) return;
                (new \Moves\App\NotificationActions())->handle($action);
            }, 'app.notifications.' . $action);
        }

        foreach (['update' => 'dados', 'password' => 'senha', '2fa_begin' => '2fa/iniciar', '2fa_confirm' => '2fa/confirmar', '2fa_disable' => '2fa/desativar', 'session_revoke' => 'sessao/encerrar', 'sessions_revoke' => 'sessoes/encerrar-outras', 'invite' => 'convite', 'invite_revoke' => 'convite/revogar', 'invite_accept' => 'convite/aceitar', 'invite_decline' => 'convite/recusar', 'notifications' => 'notificacoes'] as $action => $slug) {
            $router->post('/minha-conta/perfil/' . $slug, static function () use ($requirePreview, $action): void {
                if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) return;
                (new \Moves\App\ProfileActions())->handle($action);
            }, 'app.profile.' . $action);
        }

        $router->get('/p/perfil/convite', static function () use ($renderContextDashboard): void {
            $user = \Moves\Services\SessionAuth::current();
            if ($user === null) { header('Location: /entrar', true, 302); return; }
            $token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
            $_SESSION['profile_pending_invite_token'] = $token;
            header('Location: /p/perfil?aba=permissoes&convite=1', true, 302);
        }, 'app.profile.invite.accept');

        foreach (['invite' => 'convidar', 'resend' => 'reenviar', 'revoke' => 'cancelar-convite', 'role' => 'papel', 'suspend' => 'suspender', 'activate' => 'reativar', 'remove' => 'remover'] as $action => $slug) {
            $router->post('/minha-conta/equipe/' . $slug, static function () use ($requirePreview, $action): void {
                if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) return;
                (new \Moves\App\TeamActions())->handle($action);
            }, 'app.team.' . $action);
        }

        $router->post('/minha-conta/empresa/dados', static function () use ($requirePreview): void {
            if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) return;
            (new \Moves\App\CompanyActions())->handle('update');
        }, 'app.company.update');
        foreach (['logo' => 'logo', 'logo_remove' => 'logo-remover'] as $action => $slug) {
            $router->post('/minha-conta/empresa/' . $slug, static function () use ($requirePreview, $action): void {
                if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) return;
                (new \Moves\App\CompanyActions())->handle($action);
            }, 'app.company.' . $action);
        }
        $router->get('/minha-conta/empresa/logo/{organization}', static function (array $params) use ($requirePreview): void {
            if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) return;
            $user=\Moves\Services\SessionAuth::current();
            \Moves\Services\OrganizationLogos::streamAuthorized((int)($params['organization']??0),(int)($user['id']??0));
        }, 'app.company.logo_file');

        // Convite de empresa: o token sai da URL na primeira abertura e fica só na sessão.
        $router->get('/convite-empresa', static function () use ($requirePreview): void {
            if (!$requirePreview()) return;
            if (is_string($_GET['token'] ?? null)) {
                $_SESSION['team_pending_invite'] = $_GET['token'];
                header('Cache-Control: no-store');
                header('Location: /convite-empresa', true, 302);
                return;
            }
            header('Cache-Control: no-store');
            $invitation = \Moves\Services\OrganizationTeam::invitationByToken((string) ($_SESSION['team_pending_invite'] ?? ''));
            require __DIR__ . '/pages/convite-empresa.php';
        }, 'app.team.invitation');
        foreach (['aceitar', 'recusar'] as $inviteAction) {
            $router->post('/convite-empresa/' . $inviteAction, static function () use ($requirePreview, $renderError, $inviteAction): void {
                if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) return;
                $user = \Moves\Services\SessionAuth::current();
                if ($user === null || !\Moves\Services\Csrf::validate($_POST['csrf_token'] ?? null)) { $renderError(403); return; }
                $token = (string) ($_SESSION['team_pending_invite'] ?? '');
                unset($_SESSION['team_pending_invite']);
                header('Cache-Control: no-store');
                try {
                    if ($inviteAction === 'recusar') {
                        $declined = \Moves\Services\OrganizationTeam::invitationByToken($token);
                        \Moves\Services\OrganizationTeam::decline($token, (string) $user['email']);
                        \Moves\Services\OrganizationAudit::record((int) ($declined['organization_id'] ?? 0), (int) $user['id'], 'team.invite_declined', 'invitation', $declined['id'] ?? null);
                        header('Location: /p', true, 303);
                        return;
                    }
                    $accepted = \Moves\Services\OrganizationTeam::invitationByToken($token);
                    $organizationId = \Moves\Services\OrganizationTeam::accept($token, (int) $user['id'], (string) $user['email']);
                    \Moves\Services\OrganizationAudit::record($organizationId, (int) $user['id'], 'team.invite_accepted', 'invitation', $accepted['id'] ?? null, ['role' => $accepted['role'] ?? null]);
                    $organization = \Moves\Services\OrganizationAccess::authorizedOrganization($organizationId);
                    header('Location: ' . ($organization === null ? '/p' : \Moves\Services\AppUrl::base($organization)), true, 303);
                } catch (\InvalidArgumentException) {
                    header('Location: /convite-empresa', true, 303);
                }
            }, 'app.team.invitation.' . $inviteAction);
        }

        (require __DIR__ . '/components/support-routes.php')($router, $requirePreview);

        // Período na URL. Fica depois das rotas fixas de /p e /e: a primeira que casa vence,
        // e a visão geral, por não ter slug, vem por último.
        $periodRoute = static function (string $context, string $section, array $params) use ($renderContextDashboard, $renderError): void {
            $period = AppUrl::periodFromSegments($section, (string) ($params['a'] ?? ''), isset($params['b']) ? (string) $params['b'] : null);
            if ($period === null) {
                $renderError(404);
                return;
            }
            $renderContextDashboard($context, $section, isset($params['organization']) ? (string) $params['organization'] : null, $period);
        };
        $periodSlugs = ['fluxo-de-caixa' => 'fluxo-de-caixa', 'receber' => 'receber', 'pagar' => 'pagar', 'movimentacoes' => 'movimentacoes', 'relatorios' => 'relatorios', 'planejamento' => 'planejamento', 'categorias' => 'categorias', '' => 'overview'];
        foreach ($periodSlugs as $slug => $section) {
            $path = $slug === '' ? '' : '/' . $slug;
            foreach (['/{a}', '/{a}/{b}'] as $segments) {
                if ($section !== 'fluxo-de-caixa') {
                    $router->get('/p' . $path . $segments, static fn (array $params) => $periodRoute('personal', $section, $params));
                }
                $router->get('/e/{organization}' . $path . $segments, static fn (array $params) => $periodRoute('organization', $section, $params));
            }
        }

        $router->post('/sair', static function () use ($requirePreview, $renderError): void {
            if (!$requirePreview()) {
                return;
            }
            if (!\Moves\Services\Csrf::validate($_POST['csrf_token'] ?? null)) {
                $renderError(403);
                return;
            }
            \Moves\Services\SessionAuth::signOut();
            header('Location: /entrar', true, 302);
        }, 'app.logout');
    },
];
