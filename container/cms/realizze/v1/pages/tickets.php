<?php $this->layout('default'); use Moves\Services\Helpdesk as H; use Moves\Support\CmsView as V;
$filters = ['q' => V::queryString('q'), 'status' => V::queryString('status'), 'priority' => V::queryString('priority'), 'category' => V::queryString('category'), 'assignee' => V::queryString('assignee'), 'overdue' => V::queryString('overdue')];
$this->insert('components/ticket-list', ['filters' => $filters, 'result' => H::staffTickets($filters, V::queryPage()), 'showFilters' => true]);
