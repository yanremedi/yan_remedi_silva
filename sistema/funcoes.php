<?php
require_once 'config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    ]);
    session_start();
}

function e($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function validarToken() {
    if (!hash_equals(token(), $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Formulário inválido. Recarregue a página.');
    }
}

function criptografar($cpf) {
    $iv = random_bytes(12);
    $chave = hash('sha256', SEGREDO_CPF, true);
    $texto = openssl_encrypt(
        $cpf,
        'aes-256-gcm',
        $chave,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($texto === false) {
        throw new RuntimeException('Falha na criptografia.');
    }

    return base64_encode($iv . $tag . $texto);
}

function descriptografar($valor) {
    $dados = base64_decode($valor, true);

    if ($dados === false || strlen($dados) < 28) {
        return '';
    }

    $texto = openssl_decrypt(
        substr($dados, 28),
        'aes-256-gcm',
        hash('sha256', SEGREDO_CPF, true),
        OPENSSL_RAW_DATA,
        substr($dados, 0, 12),
        substr($dados, 12, 16)
    );

    return $texto === false ? '' : $texto;
}

function dataValida($valor, $formato) {
    $data = DateTime::createFromFormat('!' . $formato, $valor);
    return $data && $data->format($formato) === $valor;
}

function cabecalho($titulo) {
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($titulo) . '</title><link rel="stylesheet" href="style.css">';
    echo '</head><body><main><h1>' . e($titulo) . '</h1>';

    if (!empty($_SESSION['usuario_id'])) {
        echo '<nav><a href="principal.php">Principal</a> ';
        echo '<a href="tutores.php">Tutores</a> <a href="pets.php">Pets</a> ';
        echo '<a href="agendamentos.php">Agendamentos</a></nav>';
    }
}

function rodape() {
    echo '</main></body></html>';
}