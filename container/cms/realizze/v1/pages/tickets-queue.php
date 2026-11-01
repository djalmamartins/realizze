<?php $this->layout('default'); use Moves\Services\Helpdesk as H; use Moves\Support\CmsView as V;
$assignee = V::queryString('assignee'); $filters = ['queue' => '1', 'assignee' => $assignee];
echo V::tabs([['/chamados/fila', 'Toda a fila', null, $assignee === ''], ['?assignee=none', 'Sem responsável', null, $assignee === 'none'], ['?assignee=' . (int) $admin['id'], 'Atribuídos a mim', null, $assignee === (string) $admin['id']]], 'Fila');
$this->insert('components/ticket-list', ['filters' => $filters, 'result' => H::staffTickets($filters, V::queryPage()), 'showFilters' => false]);
