<?php
require_once 'funcoes.php';

if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

if (time() - ($_SESSION['ultima_atividade'] ?? 0) >= TEMPO_SESSAO) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php?expirou=1');
    exit;
}

$_SESSION['ultima_atividade'] = time();