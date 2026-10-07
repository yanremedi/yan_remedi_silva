<?php
require_once 'conexao.php';
require_once 'funcoes.php';

$erro = isset($_GET['expirou']) ? 'Sessão expirada. Entre novamente.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();

    $login = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $stmt = $conn->prepare('SELECT * FROM usuarios WHERE login = ?');
    $stmt->bind_param('s', $login);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();

    if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['ultima_atividade'] = time();
        header('Location: principal.php');
        exit;
    }

    $erro = 'Login ou senha incorretos. ❌';
}

cabecalho('Casa do Pet 🐾 - Login');
?>
<p class="aviso"><?= e($erro) ?></p>
<form method="post">
    <input type="hidden" name="csrf" value="<?= e(token()) ?>">
    <label>Login <input name="login" required maxlength="60"></label>
    <label>Senha <input type="password" name="senha" required></label>
    <button>Entrar</button>
</form>
<?php rodape(); ?>