<?php $this->layout('default'); use Moves\Services\CmsAuth; use Moves\Services\Helpdesk as H; use Moves\Support\CmsView as V;
$filters = ['q' => V::queryString('q'), 'status' => V::queryString('status'), 'priority' => V::queryString('priority'), 'category' => V::queryString('category'), 'assignee' => V::queryString('assignee'), 'overdue' => V::queryString('overdue'), 'tag' => V::queryString('tag')];
$counts = H::statusCounts(); $tabs = [['/chamados', 'Todos', array_sum($counts), $filters['status'] === '']];
foreach (V::TICKET_STATUS as $key => $label) { $tabs[] = ['/chamados?status=' . $key, $label, $counts[$key], $filters['status'] === $key]; }
if (CmsAuth::can($admin, 'tickets.reply')) { $this->start('actions'); ?><a class="cms-btn-primary" href="/chamados/novo"><?= V::icon('plus') ?>Novo chamado</a><?php $this->stop(); }
echo V::tabs($tabs, 'Situação');
$this->insert('components/ticket-list', ['filters' => $filters, 'result' => H::staffTickets($filters, V::queryPage()), 'showFilters' => true]);
