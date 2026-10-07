<?php
require_once 'proteger.php';
require_once 'conexao.php';

$erro = '';
$id = 0;
$nome = '';
$especie = '';
$raca = '';
$data_nascimento = '';
$tutor_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);

    try {
        if ($id === false || $id < 0) {
            throw new RuntimeException('ID inválido.');
        }

        if (($_POST['acao'] ?? '') === 'excluir') {
            $stmt = $conn->prepare('DELETE FROM pets WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            header('Location: pets.php?ok=1');
            exit;
        }

        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') {
            throw new RuntimeException('Preencha Nome.');
        }
        if (mb_strlen($nome) > 100) {
            throw new RuntimeException('Campo Nome muito longo.');
        }

        $especie = trim($_POST['especie'] ?? '');
        if ($especie === '') {
            throw new RuntimeException('Preencha Espécie.');
        }
        if (mb_strlen($especie) > 50) {
            throw new RuntimeException('Campo Espécie muito longo.');
        }

        $raca = trim($_POST['raca'] ?? '');
        if ($raca === '') {
            throw new RuntimeException('Preencha Raça.');
        }
        if (mb_strlen($raca) > 80) {
            throw new RuntimeException('Campo Raça muito longo.');
        }

        $data_nascimento = trim($_POST['data_nascimento'] ?? '');
        if ($data_nascimento === '') {
            throw new RuntimeException('Preencha Nascimento.');
        }

        $tutor_id = trim($_POST['tutor_id'] ?? '');
        if ($tutor_id === '') {
            throw new RuntimeException('Preencha Tutor.');
        }

        if (!dataValida($data_nascimento, 'Y-m-d') || $data_nascimento > date('Y-m-d')) {
            throw new RuntimeException('Data de nascimento inválida.');
        }

        $tutor_id = filter_var($tutor_id, FILTER_VALIDATE_INT);
        if (!$tutor_id || $tutor_id < 1) {
            throw new RuntimeException('Selecione um vínculo válido.');
        }

        $check = $conn->prepare('SELECT id FROM tutores WHERE id = ?');
        $check->bind_param('i', $tutor_id);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            throw new RuntimeException('Registro relacionado não existe.');
        }

        if ($id > 0) {
            $stmt = $conn->prepare('UPDATE pets SET nome = ?, especie = ?, raca = ?, data_nascimento = ?, tutor_id = ? WHERE id = ?');
            $stmt->bind_param('ssssii', $nome, $especie, $raca, $data_nascimento, $tutor_id, $id);
        } else {
            $stmt = $conn->prepare('INSERT INTO pets (nome, especie, raca, data_nascimento, tutor_id) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssi', $nome, $especie, $raca, $data_nascimento, $tutor_id);
        }

        $stmt->execute();
        header('Location: pets.php?ok=1');
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
    $stmt = $conn->prepare('SELECT * FROM pets WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $registro = $stmt->get_result()->fetch_assoc();

    if (!$registro) {
        $id = 0;
        $erro = 'Registro não encontrado.';
    } else {
        $nome = $registro['nome'];
        $especie = $registro['especie'];
        $raca = $registro['raca'];
        $data_nascimento = $registro['data_nascimento'];
        $tutor_id = $registro['tutor_id'];
    }
}

$opcoes = $conn->query('SELECT id, nome FROM tutores ORDER BY nome');
$listagem = $conn->query('SELECT p.*, t.nome AS tutor FROM pets p INNER JOIN tutores t ON t.id = p.tutor_id ORDER BY p.nome');

cabecalho('Pets');
?>
<p class="aviso"><?= e($erro) ?></p>
<?php if (isset($_GET['ok'])): ?><p>Operação concluída.</p><?php endif; ?>

<h2><?= $id ? 'Editar registro' : 'Novo registro' ?></h2>
<form method="post">
    <input type="hidden" name="csrf" value="<?= e(token()) ?>">
    <input type="hidden" name="id" value="<?= e($id) ?>">

    <label>Nome
        <input type="text" name="nome" value="<?= e($nome) ?>" maxlength="100" required>
    </label>
    <label>Espécie
        <input type="text" name="especie" value="<?= e($especie) ?>" maxlength="50" required>
    </label>
    <label>Raça
        <input type="text" name="raca" value="<?= e($raca) ?>" maxlength="80" required>
    </label>
    <label>Nascimento
        <input type="date" name="data_nascimento" value="<?= e($data_nascimento) ?>" required>
    </label>
    <label>Tutor
        <select name="tutor_id" required>
            <option value="">Selecione</option>
            <?php while ($o = $opcoes->fetch_assoc()): ?>
                <option value="<?= e($o['id']) ?>" <?= (int) $tutor_id === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['nome']) ?></option>
            <?php endwhile; ?>
        </select>
    </label>

    <button name="acao" value="salvar">Salvar</button>
    <a href="pets.php">Cancelar / Novo</a>
</form>

<h2>Registros cadastrados</h2>
<div class="rolagem">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Espécie</th>
                <th>Raça</th>
                <th>Nascimento</th>
                <th>Tutor</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($r = $listagem->fetch_assoc()): ?>
                <tr>
                    <td><?= e($r['id']) ?></td>
                    <td><?= e($r['nome']) ?></td>
                    <td><?= e($r['especie']) ?></td>
                    <td><?= e($r['raca']) ?></td>
                    <td><?= e($r['data_nascimento']) ?></td>
                    <td><?= e($r['tutor']) ?></td>
                    <td>
                        <a href="pets.php?editar=<?= e($r['id']) ?>">Editar</a>
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