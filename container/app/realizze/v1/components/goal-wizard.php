<?php
/**
 * Criação de meta em cinco passos, com resumo ao vivo e análise pelo histórico.
 * Parâmetros: $institutions, $details (migration 009 aplicada), $baseline (Finance::goalBaseline), $today, $csrf, $e.
 */
use Moves\Services\Finance;

$goalIcon = require __DIR__ . '/goal-icon.php';
$steps = [['Objetivo', 'O que você quer alcançar?'], ['Detalhes', 'Quanto e quando?'], ['Contribuição', 'Quanto você pode guardar?'], ['Análise', 'Vamos calcular seu cenário'], ['Revisão', 'Confirme sua meta']];
$categoryIcons = ['transporte' => 'carro', 'viagem' => 'aviao', 'casa' => 'casa', 'educacao' => 'educacao', 'investimento' => 'guardar', 'outra' => 'alvo'];
$iconLabels = ['carro' => 'Carro', 'aviao' => 'Avião', 'casa' => 'Casa', 'educacao' => 'Educação', 'coracao' => 'Coração', 'maleta' => 'Trabalho', 'guardar' => 'Guardar', 'jogo' => 'Lazer', 'camera' => 'Câmera', 'carteira' => 'Carteira', 'alvo' => 'Alvo', 'mais' => 'Outro'];
?>
<dialog class="finance-dialog goal-wizard" id="goal-dialog" aria-labelledby="goal-dialog-title" data-goal-wizard data-goal-baseline="<?= $e(json_encode($baseline, JSON_THROW_ON_ERROR)) ?>" data-goal-today="<?= $e($today) ?>">
  <form method="post" action="/minha-conta/financeiro/meta" enctype="multipart/form-data" data-finance-form novalidate>
    <header>
      <div><h2 id="goal-dialog-title">Nova meta</h2><p class="muted">Defina seu objetivo financeiro. O Realizze ajuda você a acompanhá-lo.</p></div>
      <button type="button" data-finance-close aria-label="Fechar">×</button>
    </header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="metas">
    <div class="goal-wizard-grid">
      <aside class="goal-steps">
        <ol>
          <?php foreach ($steps as $index => [$title, $hint]): ?>
          <li data-goal-step-item="<?= $index ?>"<?= $index === 0 ? ' class="is-current" aria-current="step"' : '' ?>><button type="button" data-goal-step-go="<?= $index ?>"<?= $index === 0 ? '' : ' disabled' ?>><span class="goal-step-number"><?= $index + 1 ?></span><span><strong><?= $e($title) ?></strong><small><?= $e($hint) ?></small></span></button></li>
          <?php endforeach; ?>
        </ol>
        <div class="goal-tip"><strong>Dica do Realizze</strong><p>Com nome, valor e prazo definidos, fica mais fácil acompanhar a meta mês a mês.</p></div>
      </aside>

      <div class="goal-panels">
        <section data-goal-panel="0">
          <h3>1. Qual é o seu objetivo?</h3>
          <label>Nome da meta<span class="goal-counted"><input name="name" maxlength="60" required data-goal-count placeholder="Ex.: Comprar um carro, Viagem para Europa, Reserva de emergência..."><small data-goal-counter>0/60</small></span></label>
          <?php if ($details): ?>
          <fieldset class="goal-choice-cards"><legend>Categoria</legend>
            <?php foreach (Finance::GOAL_CATEGORIES as $key => $label): ?>
            <label><input type="radio" name="category" value="<?= $e($key) ?>" data-goal-icon-for="<?= $e($categoryIcons[$key]) ?>"<?= $key === 'outra' ? ' checked' : '' ?>><span><?= $goalIcon($categoryIcons[$key]) ?><?= $e($label) ?></span></label>
            <?php endforeach; ?>
          </fieldset>
          <?php endif; ?>
          <label>Descrição (opcional)<span class="goal-counted"><textarea name="purpose" rows="2" maxlength="<?= $details ? 200 : 160 ?>" data-goal-count placeholder="Conte um pouco sobre o seu objetivo"></textarea><small data-goal-counter>0/<?= $details ? 200 : 160 ?></small></span></label>
          <?php if ($details): ?>
          <div class="goal-media-row">
            <div class="goal-drop" data-goal-photo>
              <span class="goal-field-title">Imagem (opcional)</span>
              <div class="goal-drop-area">
                <div class="goal-photo-preview" data-goal-photo-preview></div>
                <span class="goal-drop-text">Clique para enviar uma imagem ou arraste e solte aqui<small>PNG ou JPG, até 5 MB</small></span>
                <input type="file" name="photo" accept="image/jpeg,image/png" aria-label="Imagem da meta" data-goal-photo-input>
              </div>
              <p class="goal-photo-error" data-goal-photo-error hidden>Essa imagem passa de 5 MB. Escolha outra.</p>
            </div>
            <fieldset class="goal-icon-grid"><legend>Ou escolha um ícone</legend>
              <?php foreach (Finance::GOAL_ICONS as $key): ?>
              <label title="<?= $e($iconLabels[$key]) ?>"><input type="radio" name="icon" value="<?= $e($key) ?>" aria-label="<?= $e($iconLabels[$key]) ?>"<?= $key === 'alvo' ? ' checked' : '' ?>><span data-goal-icon-svg="<?= $e($key) ?>"><?= $goalIcon($key) ?></span></label>
              <?php endforeach; ?>
            </fieldset>
          </div>
          <?php endif; ?>
        </section>

        <section data-goal-panel="1" hidden>
          <h3>2. Quanto e quando?</h3>
          <div class="finance-dialog-row">
            <label>Valor da meta (R$)<input name="amount" inputmode="decimal" placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?" required></label>
            <label>Prazo (opcional)<input name="deadline" type="date" min="<?= $e($today) ?>"></label>
          </div>
          <label>Já tenho guardado (R$, opcional)<input name="initial" inputmode="decimal" placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?"></label>
        </section>

        <section data-goal-panel="2" hidden>
          <h3>3. Quanto você pode guardar?</h3>
          <div class="goal-capacity" data-goal-capacity></div>
          <label>Contribuição mensal (R$, opcional)<input name="monthly" inputmode="decimal" placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?"></label>
          <?php if ($details): ?>
          <fieldset class="goal-place-fields">
            <legend>Onde você está juntando (opcional)</legend>
            <label>Nome da caixinha ou conta<input name="savings_place" maxlength="120" placeholder="Ex.: Caixinha Carro"></label>
            <div class="finance-dialog-row">
              <label>Banco<select name="institution_id"><option value="">Não está na lista</option><?php foreach ($institutions as $institution): ?><option value="<?= (int) $institution['id'] ?>"><?= $e($institution['trade_name']) ?></option><?php endforeach; ?></select></label>
              <label>Outro banco<input name="institution" maxlength="120" placeholder="Só se não estiver na lista"></label>
            </div>
          </fieldset>
          <?php endif; ?>
        </section>

        <section data-goal-panel="3" hidden>
          <h3>4. Seu cenário</h3>
          <div data-goal-analysis></div>
        </section>

        <section data-goal-panel="4" hidden>
          <h3>5. Confirme sua meta</h3>
          <dl class="goal-review" data-goal-review></dl>
          <p class="muted">Você pode alterar tudo isso depois, em Editar. O Realizze não movimenta seu dinheiro.</p>
        </section>
      </div>

      <aside class="goal-summary">
        <div class="goal-summary-card">
          <h3>Resumo da sua meta</h3>
          <div class="goal-summary-image" data-goal-summary-image><span data-goal-summary-icon><?= $goalIcon('alvo', 34) ?></span></div>
          <span class="goal-summary-chip" data-goal-summary-category hidden></span>
          <strong class="goal-summary-name" data-goal-summary-name>Sua meta</strong>
          <p class="muted" data-goal-summary-purpose></p>
          <dl>
            <div><dt>Valor alvo</dt><dd data-goal-summary-target>R$ 0,00</dd></div>
            <div><dt>Prazo</dt><dd data-goal-summary-deadline>A definir</dd></div>
            <div><dt>Contribuição mensal</dt><dd data-goal-summary-monthly>R$ 0,00</dd></div>
          </dl>
        </div>
        <div class="goal-calc-card">
          <h4>O que vamos calcular para você</h4>
          <ul><li>Quanto sobra por mês</li><li>Prazo estimado</li><li>Cenários atual, recomendado e acelerado</li><li>Peso das despesas no objetivo</li></ul>
          <p class="muted">Os cálculos usam seus lançamentos pagos e você pode ajustá-los quando quiser.</p>
        </div>
      </aside>
    </div>
    <footer>
      <button type="button" data-finance-close>Cancelar</button>
      <span class="goal-footer-actions">
        <button type="button" data-goal-back hidden>Voltar</button>
        <button type="button" class="primary" data-goal-next>Próximo →</button>
        <button type="submit" class="primary" data-goal-submit hidden>Criar meta</button>
      </span>
    </footer>
  </form>
</dialog>
