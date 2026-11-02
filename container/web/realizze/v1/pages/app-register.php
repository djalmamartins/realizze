<?php $this->layout('default',['title'=>'Criar conta | Realizze','description'=>'Crie sua conta pessoal no Realizze.']);$this->start('optout');$this->stop();
$old=static fn(string $key):string=>htmlspecialchars(is_string($_POST[$key]??null)?$_POST[$key]:'',ENT_QUOTES,'UTF-8');
$site=htmlspecialchars(rtrim((string)getenv('APP_URL'),'/'),ENT_QUOTES,'UTF-8'); ?>
<section class="auth-section"><div class="auth-card"><span class="eyebrow">COMECE POR AQUI</span><h1><?=!empty($registerSuccess)?'Confira seu e-mail':'Crie sua conta pessoal'?></h1>
<?php if(!empty($registerSuccess)):?><p class="form-feedback is-success" role="status">Enviamos as instruções para o e-mail informado. O link de confirmação vale por 24 horas.</p><p class="auth-bottom">Não chegou? Veja o spam ou <a href="/reenviar-confirmacao">peça um novo link</a>.</p><?php else:?>
<p>Informe seus dados reais. O e-mail precisa ser confirmado antes do primeiro acesso.</p>
<?php if(!empty($registerError)):?><p class="form-feedback" role="alert"><?=htmlspecialchars((string)$registerError,ENT_QUOTES,'UTF-8')?></p><?php endif;?>
<form class="demo-form" method="post" action="/cadastrar"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(\Moves\Services\Csrf::token(),ENT_QUOTES,'UTF-8')?>"><div class="sc-trap" aria-hidden="true"><label>Não preencha<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
<label for="register-name">Nome completo</label><input id="register-name" name="name" autocomplete="name" maxlength="160" required value="<?=$old('name')?>">
<label for="register-email">E-mail</label><input id="register-email" name="email" type="email" autocomplete="email" maxlength="254" required value="<?=$old('email')?>">
<label for="register-phone">Celular com DDD</label><input id="register-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="20" placeholder="(31) 99999-9999" required value="<?=$old('phone')?>">
<label for="register-password">Senha</label><input id="register-password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" required><small>Use ao menos 12 caracteres.</small>
<label for="register-confirm">Confirmar senha</label><input id="register-confirm" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="128" required>
<label class="form-check"><input type="checkbox" name="terms" value="1" required><span>Li e aceito os <a href="<?=$site?>/termos" target="_blank" rel="noopener">Termos de uso</a>.</span></label><label class="form-check"><input type="checkbox" name="privacy" value="1" required><span>Li e aceito a <a href="<?=$site?>/privacidade" target="_blank" rel="noopener">Política de privacidade</a>.</span></label>
<button class="btn btn-primary btn-full" type="submit">Criar conta</button></form><?php endif;?><p class="auth-bottom">Já tem cadastro? <a href="/entrar">Entrar</a></p></div></section>
