<?php
declare(strict_types=1);
// APP-A11Y-055: visual-only assistant preview. No providers, example transactions or writes.
?>
<section class="assistant-preview" aria-labelledby="assistant-title">
    <div class="assistant-notice" role="note">
        <span class="assistant-notice-icon"><?= $icon('message-circle') ?></span>
        <div>
            <strong id="assistant-title">Prévia de interface — sem integração</strong>
            <p>O assistente ainda não está conectado a serviços externos. Nenhuma conversa ou movimentação será registrada.</p>
        </div>
        <span class="assistant-status">Somente leitura</span>
    </div>

    <div class="assistant-grid">
        <article class="card assistant-chat" aria-labelledby="assistant-chat-title">
            <header class="assistant-chat-header">
                <div class="assistant-avatar" aria-hidden="true">R</div>
                <div><h2 id="assistant-chat-title">Assistente Realizze</h2><p>Nenhuma conversa carregada</p></div>
                <span class="assistant-offline">Não conectado</span>
            </header>
            <div class="assistant-messages assistant-messages-empty" aria-label="Estado da conversa">
                <div class="assistant-empty">
                    <span class="assistant-empty-icon" aria-hidden="true"><?= $icon('message-circle') ?></span>
                    <strong>Nenhuma conversa iniciada</strong>
                    <p>Esta é uma prévia visual. Não existem mensagens, valores ou lançamentos consultados. O envio e a interpretação estão desabilitados.</p>
                </div>
            </div>
            <div class="assistant-composer" aria-label="Envio indisponível nesta prévia">
                <label for="assistant-message">Mensagem</label>
                <div><textarea id="assistant-message" rows="2" placeholder="Envio ainda indisponível" disabled></textarea><button type="button" disabled>Enviar mensagem</button></div>
            </div>
        </article>

        <aside class="assistant-side" aria-label="Funcionamento e segurança">
            <section class="card assistant-panel">
                <h2>Como poderá funcionar futuramente</h2>
                <ol>
                    <li><span>1</span><div><strong>Autenticar</strong><p>Validar a sessão e o contexto pessoal ou empresarial autorizado.</p></div></li>
                    <li><span>2</span><div><strong>Interpretar</strong><p>Apresentar uma sugestão para revisão, sem gravar automaticamente.</p></div></li>
                    <li><span>3</span><div><strong>Confirmar</strong><p>Exigir confirmação explícita e proteção CSRF para cada operação.</p></div></li>
                    <li><span>4</span><div><strong>Auditar</strong><p>Registrar somente operações permitidas, com escopo e trilha de auditoria.</p></div></li>
                </ol>
            </section>
            <section class="card assistant-panel assistant-security">
                <h2>Estado da integração</h2>
                <ul>
                    <li><span aria-hidden="true"></span>Integração de mensagens não habilitada</li>
                    <li><span aria-hidden="true"></span>Processamento automatizado não configurado</li>
                    <li><span aria-hidden="true"></span>Gravações financeiras desabilitadas</li>
                </ul>
                <button type="button" disabled>Integração indisponível</button>
            </section>
        </aside>
    </div>
</section>
