<?php $this->layout('default'); use Moves\Services\Helpdesk as H; use Moves\Support\CmsView as V;
$filters = ['queue' => '1', 'assignee' => V::queryString('assignee')]; ?>
<div class="cms-actions"><a class="cms-btn" href="?assignee=none">Sem responsável</a><a class="cms-btn" href="?assignee=<?= (int) $admin['id'] ?>">Atribuídos a mim</a><a class="cms-btn" href="/chamados/fila">Toda a fila</a></div>
<?php $this->insert('components/ticket-list', ['filters' => $filters, 'result' => H::staffTickets($filters, V::queryPage()), 'showFilters' => false]);
