<?php
/** Ações expansíveis da modal de lançamento: repetir, observação, anexo e tags. Espera $e, $extrasReady, $autoReady e $extrasTags. */
$entryIcon = static fn (string $paths): string => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
$tools = [
    'repeat' => ['Repetir', '<path d="M17 2l4 4-4 4"/><path d="M3 11V9a3 3 0 0 1 3-3h15M7 22l-4-4 4-4"/><path d="M21 13v2a3 3 0 0 1-3 3H3"/>', true],
    'notes' => ['Observação', '<path d="M4 5h16M4 10h16M4 15h10M4 20h6"/>', true],
    'files' => ['Anexo', '<path d="M21 11.500 12.500 20a5 5 0 0 1-7-7l9-9a3.300 3.300 0 0 1 4.700 4.700l-9 9a1.700 1.700 0 0 1-2.400-2.400l8-8"/>', $extrasReady],
    'tags' => ['Tags', '<path d="M20.600 13.400 13.400 20.600a2 2 0 0 1-2.800 0L3 13V3h10l7.600 7.600a2 2 0 0 1 0 2.800z"/><circle cx="8" cy="8" r="1.500"/>', $extrasReady],
];
?>
    <div class="entry-tools" role="group" aria-label="Mais opções">
      <?php foreach ($tools as $tool => [$label, $paths, $ready]): ?>
      <button type="button" data-entry-tool="entry-dialog-<?= $e($tool) ?>" aria-expanded="false" aria-controls="entry-dialog-<?= $e($tool) ?>"<?= $ready ? '' : ' disabled title="Disponível depois da atualização do banco de dados"' ?>><?= $entryIcon($paths) ?><span><?= $e($label) ?></span></button>
      <?php endforeach; ?>
    </div>
    <div class="entry-panel" id="entry-dialog-repeat" hidden>
      <fieldset class="entry-repeat">
        <legend>Repetir</legend>
        <div class="entry-segments">
          <label><input type="radio" name="repeat_mode" value="" checked><span>Não repetir</span></label>
          <label<?= $extrasReady ? '' : ' title="Disponível depois da atualização do banco de dados"' ?>><input type="radio" name="repeat_mode" value="term"<?= $extrasReady ? '' : ' disabled data-unavailable' ?>><span>Com prazo</span></label>
          <label><input type="radio" name="repeat_mode" value="subscription"><span>Assinatura</span></label>
        </div>
        <label data-entry-first-due hidden><span data-entry-first-label>Primeiro vencimento</span><input type="date" data-entry-first-input></label>
        <div class="entry-repeat-group" data-entry-term hidden>
          <div class="finance-dialog-row">
            <label>Quantidade de parcelas<input name="repeat_count" type="number" min="2" max="120" value="6" inputmode="numeric"></label>
            <label>Frequência<select name="repeat_every"><option value="weekly">Semanal</option><option value="biweekly">Quinzenal</option><option value="monthly" selected>Mensal</option><option value="bimonthly">Bimestral</option><option value="quarterly">Trimestral</option><option value="semiannual">Semestral</option><option value="yearly">Anual</option></select></label>
          </div>
          <label>O valor informado é<select name="repeat_value"><option value="fixed">O valor de cada parcela</option><option value="installment">O total, dividido entre as parcelas</option></select></label>
        </div>
        <div class="entry-repeat-group" data-entry-subscription hidden>
          <div class="finance-dialog-row">
            <label>Frequência<select name="frequency"><option value="monthly">Mensal</option><option value="weekly">Semanal</option><option value="quarterly">Trimestral</option><option value="yearly">Anual</option></select></label>
            <label>Tipo<select name="item_type"><option value="subscription">Assinatura</option><option value="recurring">Cobrança recorrente</option></select></label>
          </div>
          <label>Fornecedor ou plano (opcional)<input name="provider" maxlength="120"></label>
          <label class="recurring-check"><input type="checkbox" name="auto_create" value="1"<?= $autoReady ? ' checked' : ' disabled' ?>> Criar os lançamentos automaticamente</label>
          <?php if (!$autoReady): ?><p class="entry-hint">Por enquanto a assinatura fica como prevista: a geração automática depende da atualização do banco de dados.</p><?php endif; ?>
        </div>
        <p class="entry-hint" data-entry-repeat-hint hidden></p>
        <table class="entry-parcels" data-entry-parcels hidden><thead><tr><th scope="col">Nº</th><th scope="col">Vencimento</th><th scope="col" class="num">Valor</th></tr></thead><tbody></tbody></table>
      </fieldset>
    </div>
    <div class="entry-panel" id="entry-dialog-notes" hidden>
      <label>Observação<textarea name="notes" rows="3" maxlength="5000"></textarea></label>
    </div>
    <?php if ($extrasReady): ?>
    <div class="entry-panel" id="entry-dialog-files" hidden>
      <span class="entry-panel-title">Anexo</span>
      <ul class="entry-existing" data-entry-existing></ul>
      <label class="entry-drop"><span>Selecione arquivos ou arraste e solte aqui</span><input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt" data-entry-files></label>
      <p class="entry-hint">Até 5 arquivos de 10 MB: imagem, PDF ou texto.</p>
      <ul class="entry-file-list" data-entry-file-list></ul>
    </div>
    <div class="entry-panel" id="entry-dialog-tags" hidden>
      <label>Tags<input data-entry-tag-input maxlength="40" list="entry-dialog-tag-list" placeholder="Digite e pressione Enter" autocomplete="off"></label>
      <input type="hidden" name="tags" data-entry-tags>
      <datalist id="entry-dialog-tag-list"><?php foreach ($extrasTags as $tag): ?><option value="<?= $e($tag) ?>"></option><?php endforeach; ?></datalist>
      <ul class="entry-tag-list" data-entry-tag-list></ul>
    </div>
    <?php endif; ?>
