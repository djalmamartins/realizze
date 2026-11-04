<?php
// Cliente HTTP para um servidor temporário ligado a um banco de teste (REALIZZE_TEST_HTTP, padrão 127.0.0.1:18199).
// O cookie de sessão é Secure; aqui ele é reenviado à mão, porque o servidor temporário é HTTP.
final class H {
    public array $jar = [];
    public function req(string $method, string $path, array $post = [], array $files = []): array {
        $ch = curl_init((getenv('REALIZZE_TEST_HTTP') ?: 'http://127.0.0.1:18199') . $path);
        $headers = ['Host: app.realizze.lab', 'X-Forwarded-Proto: https'];
        if ($this->jar) $headers[] = 'Cookie: ' . implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($this->jar), $this->jar));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 60]);
        if ($method === 'POST') curl_setopt($ch, CURLOPT_POSTFIELDS, $files ? $post + $files : http_build_query($post));
        $raw = (string) curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $head = substr($raw, 0, $hs); $body = substr($raw, $hs);
        preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]*)/mi', $head, $m, PREG_SET_ORDER);
        foreach ($m as [, $k, $v]) { if ($v === '' || $v === 'deleted') unset($this->jar[$k]); else $this->jar[$k] = $v; }
        preg_match('/^Location:\s*(\S+)/mi', $head, $loc);
        return ['code' => $code, 'body' => $body, 'location' => $loc[1] ?? null];
    }
    public function csrf(string $html): string { preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m); return $m[1] ?? ''; }
    public function login(string $email, string $password): bool {
        $page = $this->req('GET', '/entrar');
        $r = $this->req('POST', '/entrar', ['email' => $email, 'password' => $password, 'csrf_token' => $this->csrf($page['body'])]);
        return $r['code'] === 302 && ($r['location'] ?? '') === '/p';
    }
}
