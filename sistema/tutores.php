<?php
require_once 'proteger.php';
require_once 'conexao.php';

$erro = '';
$id = 0;
$nome = '';
$cpf = '';
$telefone = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);

    try {
        if ($id === false || $id < 0) {
            throw new RuntimeException('ID inválido.');
        }

        if (($_POST['acao'] ?? '') === 'excluir') {
            $stmt = $conn->prepare('DELETE FROM tutores WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            header('Location: tutores.php?ok=1');
            exit;
        }

        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') {
            throw new RuntimeException('Preencha Nome.');
        }
        if (mb_strlen($nome) > 120) {
            throw new RuntimeException('Campo Nome muito longo.');
        }

        $cpf = trim($_POST['cpf'] ?? '');
        if ($cpf === '') {
            throw new RuntimeException('Preencha CPF (11 dígitos).');
        }
        if (mb_strlen($cpf) > 11) {
            throw new RuntimeException('Campo CPF (11 dígitos) muito longo.');
        }

        $telefone = trim($_POST['telefone'] ?? '');
        if ($telefone === '') {
            throw new RuntimeException('Preencha Telefone.');
        }
        if (mb_strlen($telefone) > 20) {
            throw new RuntimeException('Campo Telefone muito longo.');
        }

        $email = trim($_POST['email'] ?? '');
        if ($email === '') {
            throw new RuntimeException('Preencha E-mail.');
        }
        if (mb_strlen($email) > 120) {
            throw new RuntimeException('Campo E-mail muito longo.');
        }

        if (!preg_match('/^[0-9]{11}$/', $cpf)) {
            throw new RuntimeException('CPF deve conter 11 dígitos numéricos.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('E-mail inválido.');
        }

        $cpf_criptografado = criptografar($cpf);

        if ($id > 0) {
            $stmt = $conn->prepare('UPDATE tutores SET nome = ?, cpf_criptografado = ?, telefone = ?, email = ? WHERE id = ?');
            $stmt->bind_param('ssssi', $nome, $cpf_criptografado, $telefone, $email, $id);
        } else {
            $stmt = $conn->prepare('INSERT INTO tutores (nome, cpf_criptografado, telefone, email) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('ssss', $nome, $cpf_criptografado, $telefone, $email);
        }

        $stmt->execute();
        header('Location: tutores.php?ok=1');
        exit;
    } catch (mysqli_sql_exception $ex) {
        error_log($ex->getMessage());
        $erro = 'Operação não concluída. Verifique os vínculos: registros com dependências não podem ser excluídos.';
    } catch (RuntimeException $ex) {
        $erro = $ex->getMessage();
    }
}

if (isset($_GET['editar']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $id = intval($_GET['editar']);
    $stmt = $conn->prepare('SELECT * FROM tutores WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $registro = $stmt->get_result()->fetch_assoc();

    if (!$registro) {
        $id = 0;
        $erro = 'Registro não encontrado.';
    } else {
        $nome = $registro['nome'];
        $cpf = descriptografar($registro['cpf_criptografado']);
        $telefone = $registro['telefone'];
        $email = $registro['email'];
    }
}

$busca = trim($_GET['busca'] ?? '');
$termo = '%' . $busca . '%';
$stmt = $conn->prepare('SELECT * FROM tutores WHERE nome LIKE ? OR email LIKE ? OR telefone LIKE ? ORDER BY nome');
$stmt->bind_param('sss', $termo, $termo, $termo);
$stmt->execute();
$listagem = $stmt->get_result();

cabecalho('Tutores');
?>
<p class="aviso"><?= e($erro) ?></p>
<?php if (isset($_GET['ok'])): ?><p>Operação concluída.</p><?php endif; ?>
<form method="get">
    <label>Buscar por nome, e-mail ou telefone
        <input name="busca" value="<?= e($busca) ?>">
    </label>
    <button>Buscar</button>
    <a href="tutores.php">Limpar</a>
</form>

<h2><?= $id ? 'Editar registro' : 'Novo registro' ?></h2>
<form method="post">
    <input type="hidden" name="csrf" value="<?= e(token()) ?>">
    <input type="hidden" name="id" value="<?= e($id) ?>">

    <label>Nome
        <input type="text" name="nome" value="<?= e($nome) ?>" maxlength="120" required>
    </label>
    <label>CPF (11 dígitos)
        <input type="text" name="cpf" value="<?= e($cpf) ?>" maxlength="11" required>
    </label>
    <label>Telefone
        <input type="text" name="telefone" value="<?= e($telefone) ?>" maxlength="20" required>
    </label>
    <label>E-mail
        <input type="email" name="email" value="<?= e($email) ?>" maxlength="120" required>
    </label>

    <button name="acao" value="salvar">Salvar</button>
    <a href="tutores.php">Cancelar / Novo</a>
</form>

<h2>Registros cadastrados</h2>
<div class="rolagem">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>CPF (11 dígitos)</th>
                <th>Telefone</th>
                <th>E-mail</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($r = $listagem->fetch_assoc()): ?>
                <tr>
                    <td><?= e($r['id']) ?></td>
                    <td><?= e($r['nome']) ?></td>
                    <td><?= e(descriptografar($r['cpf_criptografado'])) ?></td>
                    <td><?= e($r['telefone']) ?></td>
                    <td><?= e($r['email']) ?></td>
                    <td>
                        <a href="tutores.php?editar=<?= e($r['id']) ?>">Editar</a>
                        <form method="post" onsubmit="return confirm('Excluir este registro?')">
                            <input type="hidden" name="csrf" value="<?= e(token()) ?>">
                            <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                            <button name="acao" value="excluir">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php rodape(); ?>