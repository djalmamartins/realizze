<?php
/** APP-UI-048: preferências PF em prévia. Sem leitura ou alteração de dados pessoais. */
?>
<section class="card finance-workspace finance-settings" aria-labelledby="configuracoes-title">
  <header class="finance-toolbar">
    <div>
      <h2 id="configuracoes-title">Configurações</h2>
      <p class="muted">Preferências, proteção da conta e privacidade em um só lugar.</p>
    </div>
    <button type="button" class="primary" disabled>Salvar preferências</button>
  </header>

  <div class="finance-settings-grid">
    <section class="finance-settings-panel" aria-labelledby="settings-profile-title">
      <h3 id="settings-profile-title">Perfil e preferências</h3>
      <p class="muted">Os dados da conta não são consultados nesta prévia.</p>
      <div class="finance-settings-fields">
        <div>
          <label for="settings-language">Idioma</label>
          <select id="settings-language" aria-describedby="settings-preview-help" disabled>
            <option>Português (Brasil)</option>
          </select>
        </div>
        <div>
          <label for="settings-currency">Moeda</label>
          <select id="settings-currency" aria-describedby="settings-preview-help" disabled>
            <option>Real brasileiro (BRL)</option>
          </select>
        </div>
        <div>
          <label for="settings-date">Formato de data</label>
          <select id="settings-date" aria-describedby="settings-preview-help" disabled>
            <option>DD/MM/AAAA</option>
          </select>
        </div>
      </div>
      <p class="finance-settings-help" id="settings-preview-help">Campos desabilitados até a integração autenticada de preferências.</p>
    </section>

    <section class="finance-settings-panel" aria-labelledby="settings-security-title">
      <h3 id="settings-security-title">Segurança e acesso</h3>
      <p class="muted">O estado da autenticação e das sessões não é exibido nesta prévia.</p>
      <dl class="finance-settings-status">
        <div><dt>Dispositivos e sessões</dt><dd>Não consultado</dd></div>
        <div><dt>Autenticação adicional</dt><dd>Não consultado</dd></div>
        <div><dt>Atividade de acesso</dt><dd>Não consultado</dd></div>
      </dl>
      <button type="button" disabled>Gerenciar sessões</button>
    </section>

    <section class="finance-settings-panel" aria-labelledby="settings-notifications-title">
      <h3 id="settings-notifications-title">Notificações</h3>
      <p class="muted">Alertas de vencimento, movimentações e avisos da conta serão configurados aqui após autorização.</p>
      <p class="finance-settings-help">Nenhuma preferência de comunicação foi carregada ou alterada.</p>
      <button type="button" disabled>Configurar alertas</button>
    </section>

    <section class="finance-settings-panel" aria-labelledby="settings-privacy-title">
      <h3 id="settings-privacy-title">Dados e privacidade</h3>
      <p class="muted">Exportação de dados, consentimentos e encerramento de conta exigirão confirmação, autenticação e auditoria.</p>
      <p class="finance-settings-help">Nenhum dado pessoal é exportado nesta interface.</p>
      <button type="button" disabled>Gerenciar dados</button>
    </section>
  </div>
  <p class="finance-settings-notice" role="note">Prévia somente visual. Nenhuma configuração, sessão, notificação ou informação financeira foi consultada ou modificada.</p>
</section>
