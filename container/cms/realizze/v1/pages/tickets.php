<?php $this->layout('default'); use Moves\Services\Helpdesk as H; use Moves\Support\CmsView as V;
$filters = ['q' => V::queryString('q'), 'status' => V::queryString('status'), 'priority' => V::queryString('priority'), 'category' => V::queryString('category'), 'assignee' => V::queryString('assignee'), 'overdue' => V::queryString('overdue'), 'tag' => V::queryString('tag')];
$counts = H::statusCounts(); $current = V::queryString('status');
$tab = static fn (string $key, string $label, int $total): string => '<a class="cms-tab' . ($key === $current ? ' selected' : '') . '" href="/chamados' . ($key === '' ? '' : '?status=' . V::e($key)) . '"' . ($key === $current ? ' aria-current="true"' : '') . '><span>' . V::e($label) . '</span><b>' . $total . '</b></a>';
if (\Moves\Services\CmsAuth::can($admin, 'tickets.reply')) { echo '<div class="cms-actions"><a class="cms-btn-primary" href="/chamados/novo">Novo chamado</a></div>'; }
echo '<nav class="cms-tabs" aria-label="Situação">' . $tab('', 'Todos', array_sum($counts));
foreach (V::TICKET_STATUS as $key => $label) { echo $tab($key, $label, $counts[$key]); }
echo '</nav>';
$this->insert('components/ticket-list', ['filters' => $filters, 'result' => H::staffTickets($filters, V::queryPage()), 'showFilters' => true]);
