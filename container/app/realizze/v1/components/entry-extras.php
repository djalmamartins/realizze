<?php
/** Painéis e botões de repetir, observação, anexos e tags. Espera $e, $extrasId e $extrasTags. */
$entryIcon = static fn (string $paths): string => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
$tools = [
    'repeat' => ['Repetir', '<path d="M17 2l4 4-4 4"/><path d="M3 11V9a3 3 0 0 1 3-3h15M7 22l-4-4 4-4"/><path d="M21 13v2a3 3 0 0 1-3 3H3"/>'],
    'notes' => ['Observação', '<path d="M4 5h16M4 10h16M4 15h10M4 20h6"/>'],
    'files' => ['Anexos', '<path d="M21 11.500 12.500 20a5 5 0 0 1-7-7l9-9a3.300 3.300 0 0 1 4.700 4.700l-9 9a1.700 1.700 0 0 1-2.400-2.400l8-8"/>'],
    'tags' => ['Tags', '<path d="M20.600 13.400 13.400 20.600a2 2 0 0 1-2.800 0L3 13V3h10l7.600 7.600a2 2 0 0 1 0 2.800z"/><circle cx="8" cy="8" r="1.500"/>'],
];
?>
    <div class="entry-panel" id="<?= $e($extrasId) ?>-repeat" hidden>
      <fieldset class="entry-repeat">
        <legend>Repetir</legend>
        <div class="entry-segments">
          <label><input type="radio" name="repeat" value="" checked><span>Não repetir</span></label>
          <label><input type="radio" name="repeat" value="fixed"><span>Valor fixo</span></label>
          <label><input type="radio" name="repeat" value="installment"><span>Parcelado</span></label>
        </div>
        <div class="finance-dialog-row" data-entry-repeat-fields hidden>
          <label><span data-entry-count-label>Quantas vezes</span><input name="repeat_count" type="number" min="2" max="120" value="12" inputmode="numeric"></label>
          <label>Frequência<select name="repeat_every"><option value="weekly">Semanal</option><option value="biweekly">Quinzenal</option><option value="monthly" selected>Mensal</option><option value="bimonthly">Bimestral</option><option value="quarterly">Trimestral</option><option value="semiannual">Semestral</option><option value="yearly">Anual</option></select></label>
        </div>
        <p class="entry-hint" data-entry-repeat-hint hidden></p>
        <table class="entry-parcels" data-entry-parcels hidden><thead><tr><th scope="col">Nº</th><th scope="col">Vencimento</th><th scope="col" class="num">Valor</th></tr></thead><tbody></tbody></table>
      </fieldset>
    </div>
    <div class="entry-panel" id="<?= $e($extrasId) ?>-notes" hidden>
      <label>Observação<textarea name="notes" rows="3" maxlength="5000"></textarea></label>
    </div>
    <div class="entry-panel" id="<?= $e($extrasId) ?>-files" hidden>
      <span class="entry-panel-title">Anexos</span>
      <label class="entry-drop"><span>Selecione arquivos ou arraste e solte aqui</span><input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt" data-entry-files></label>
      <p class="entry-hint">Até 5 arquivos de 10 MB: imagem, PDF ou texto.</p>
      <ul class="entry-file-list" data-entry-file-list></ul>
    </div>
    <div class="entry-panel" id="<?= $e($extrasId) ?>-tags" hidden>
      <label>Tags<input data-entry-tag-input maxlength="40" list="<?= $e($extrasId) ?>-tag-list" placeholder="Digite e pressione Enter" autocomplete="off"></label>
      <input type="hidden" name="tags" data-entry-tags>
      <datalist id="<?= $e($extrasId) ?>-tag-list"><?php foreach ($extrasTags as $tag): ?><option value="<?= $e($tag) ?>"></option><?php endforeach; ?></datalist>
      <ul class="entry-tag-list" data-entry-tag-list></ul>
    </div>
    <div class="entry-tools" role="group" aria-label="Mais opções">
      <?php foreach ($tools as $tool => [$label, $paths]): ?>
      <button type="button" data-entry-tool="<?= $e($extrasId . '-' . $tool) ?>" aria-expanded="false" aria-controls="<?= $e($extrasId . '-' . $tool) ?>"><?= $entryIcon($paths) ?><span><?= $e($label) ?></span></button>
      <?php endforeach; ?>
    </div>
