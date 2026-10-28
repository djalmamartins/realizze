<?php
declare(strict_types=1);
// APP-AI-046: preview-only conversation. It never calls providers or writes financial data.
?>
<section class="assistant-preview" aria-labelledby="assistant-title">
    <div class="assistant-notice" role="note">
        <span class="assistant-notice-icon"><?= $icon('message-circle') ?></span>
        <div>
            <strong id="assistant-title">Prévia local e demonstrativa</strong>
            <p>Esta conversa não está conectada ao WhatsApp nem a um processador de IA. Nenhuma movimentação será registrada.</p>
        </div>
        <span class="assistant-status">Somente leitura</span>
    </div>

    <div class="assistant-grid">
        <article class="card assistant-chat" aria-labelledby="assistant-chat-title">
            <header class="assistant-chat-header">
                <div class="assistant-avatar" aria-hidden="true">R</div>
                <div><h2 id="assistant-chat-title">Assistente Realizze</h2><p>Exemplo de conversa autorizada</p></div>
                <span class="assistant-offline">Não conectado</span>
            </header>
            <div class="assistant-messages" aria-label="Conversa demonstrativa">
                <div class="assistant-message assistant-message-user">
                    <p>Gastei R$ 85,00 no supermercado hoje.</p>
                    <small>Mensagem de exemplo</small>
                </div>
                <div class="assistant-message assistant-message-bot">
                    <p>Entendi. Confira os dados antes de confirmar:</p>
                    <dl class="assistant-extraction">
                        <div><dt>Tipo</dt><dd>Despesa</dd></div>
                        <div><dt>Valor</dt><dd>R$ 85,00</dd></div>
                        <div><dt>Categoria</dt><dd>Alimentação</dd></div>
                        <div><dt>Data</dt><dd>Hoje</dd></div>
                    </dl>
                    <span class="assistant-pending">Aguardando confirmação</span>
                    <small>Simulação — não representa uma transação real</small>
                </div>
            </div>
            <div class="assistant-composer" aria-label="Envio indisponível nesta prévia">
                <label for="assistant-message">Mensagem</label>
                <div><textarea id="assistant-message" rows="2" placeholder="A integração ainda não foi habilitada" disabled></textarea><button type="button" disabled>Enviar mensagem</button></div>
            </div>
        </article>

        <aside class="assistant-side" aria-label="Funcionamento e segurança">
            <section class="card assistant-panel">
                <h2>Como deverá funcionar</h2>
                <ol>
                    <li><span>1</span><div><strong>Receber</strong><p>Uma mensagem de um canal previamente autorizado.</p></div></li>
                    <li><span>2</span><div><strong>Interpretar</strong><p>Identificar tipo, valor, data e categoria sugerida.</p></div></li>
                    <li><span>3</span><div><strong>Confirmar</strong><p>Solicitar confirmação explícita antes de qualquer gravação.</p></div></li>
                    <li><span>4</span><div><strong>Registrar</strong><p>Somente no futuro, com identidade e permissões validadas.</p></div></li>
                </ol>
            </section>
            <section class="card assistant-panel assistant-security">
                <h2>Estado da integração</h2>
                <ul>
                    <li><span aria-hidden="true"></span>Provedor de mensagens não conectado</li>
                    <li><span aria-hidden="true"></span>Processador de IA não configurado</li>
                    <li><span aria-hidden="true"></span>Gravações financeiras desabilitadas</li>
                </ul>
                <button type="button" disabled>Conectar WhatsApp</button>
            </section>
        </aside>
    </div>
</section>
