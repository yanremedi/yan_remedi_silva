<?php
require_once 'proteger.php';
require_once 'conexao.php';

$erro = '';
$id = 0;
$pet_id = '';
$data_hora = '';
$motivo = '';
$observacoes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);

    try {
        if ($id === false || $id < 0) {
            throw new RuntimeException('ID inválido.');
        }

        if (($_POST['acao'] ?? '') === 'excluir') {
            $stmt = $conn->prepare('DELETE FROM agendamentos WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            header('Location: agendamentos.php?ok=1');
            exit;
        }

        $pet_id = trim($_POST['pet_id'] ?? '');
        if ($pet_id === '') {
            throw new RuntimeException('Preencha Pet.');
        }

        $data_hora = trim($_POST['data_hora'] ?? '');
        if ($data_hora === '') {
            throw new RuntimeException('Preencha Data e horário.');
        }

        $motivo = trim($_POST['motivo'] ?? '');
        if ($motivo === '') {
            throw new RuntimeException('Preencha Motivo.');
        }
        if (mb_strlen($motivo) > 255) {
            throw new RuntimeException('Campo Motivo muito longo.');
        }

        $observacoes = trim($_POST['observacoes'] ?? '');
        if (mb_strlen($observacoes) > 2000) {
            throw new RuntimeException('Campo Observações muito longo.');
        }

        if (!dataValida($data_hora, 'Y-m-d\TH:i')) {
            throw new RuntimeException('Data e horário inválidos.');
        }

        $data_hora = str_replace('T', ' ', $data_hora) . ':00';
        $pet_id = filter_var($pet_id, FILTER_VALIDATE_INT);
        if (!$pet_id || $pet_id < 1) {
            throw new RuntimeException('Selecione um vínculo válido.');
        }

        $check = $conn->prepare('SELECT id FROM pets WHERE id = ?');
        $check->bind_param('i', $pet_id);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            throw new RuntimeException('Registro relacionado não existe.');
        }

        if ($id > 0) {
            $stmt = $conn->prepare('UPDATE agendamentos SET pet_id = ?, data_hora = ?, motivo = ?, observacoes = ? WHERE id = ?');
            $stmt->bind_param('isssi', $pet_id, $data_hora, $motivo, $observacoes, $id);
        } else {
            $stmt = $conn->prepare('INSERT INTO agendamentos (pet_id, data_hora, motivo, observacoes) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('isss', $pet_id, $data_hora, $motivo, $observacoes);
        }

        $stmt->execute();
        header('Location: agendamentos.php?ok=1');
        exit;
    } catch (mysqli_sql_exception $ex) {
        error_log($ex->getMessage());
        $erro = 'Operação não concluída. Verifique os vínculos: registros com dependências não podem ser excluídos.';
    } catch (RuntimeException $ex) {
        $erro = $ex->getMessage();
    }
}

if ($data_hora !== '') {
    $data_hora = substr(str_replace(' ', 'T', $data_hora), 0, 16);
}

if (isset($_GET['editar']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $id = intval($_GET['editar']);
    $stmt = $conn->prepare('SELECT * FROM agendamentos WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $registro = $stmt->get_result()->fetch_assoc();

    if (!$registro) {
        $id = 0;
        $erro = 'Registro não encontrado.';
    } else {
        $pet_id = $registro['pet_id'];
        $data_hora = substr(str_replace(' ', 'T', $registro['data_hora']), 0, 16);
        $motivo = $registro['motivo'];
        $observacoes = $registro['observacoes'];
    }
}

$opcoes = $conn->query('SELECT p.id, p.nome, t.nome AS tutor FROM pets p INNER JOIN tutores t ON t.id = p.tutor_id ORDER BY p.nome');
$listagem = $conn->query('SELECT a.*, p.nome AS pet, p.especie, p.raca, p.data_nascimento, p.tutor_id, t.nome AS tutor, t.cpf_criptografado, t.telefone, t.email FROM agendamentos a INNER JOIN pets p ON p.id = a.pet_id INNER JOIN tutores t ON t.id = p.tutor_id ORDER BY a.data_hora ASC, a.id ASC');

cabecalho('Agendamentos');
?>
<p class="aviso"><?= e($erro) ?></p>
<?php if (isset($_GET['ok'])): ?><p>Operação concluída.</p><?php endif; ?>

<h2><?= $id ? 'Editar registro' : 'Novo registro' ?></h2>
<form method="post">
    <input type="hidden" name="csrf" value="<?= e(token()) ?>">
    <input type="hidden" name="id" value="<?= e($id) ?>">

    <label>Pet
        <select name="pet_id" required>
            <option value="">Selecione</option>
            <?php while ($o = $opcoes->fetch_assoc()): ?>
                <option value="<?= e($o['id']) ?>" <?= (int) $pet_id === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['nome'] . ' - ' . $o['tutor']) ?></option>
            <?php endwhile; ?>
        </select>
    </label>
    <label>Data e horário
        <input type="datetime-local" name="data_hora" value="<?= e($data_hora) ?>" required>
    </label>
    <label>Motivo
        <input type="text" name="motivo" value="<?= e($motivo) ?>" maxlength="255" required>
    </label>
    <label>Observações
        <input type="text" name="observacoes" value="<?= e($observacoes) ?>" maxlength="2000">
    </label>

    <button name="acao" value="salvar">Salvar</button>
    <a href="agendamentos.php">Cancelar / Novo</a>
</form>

<h2>Registros cadastrados</h2>
<div class="rolagem">
    <table>
        <thead>
            <tr>
                <th>Agendamento ID</th>
                <th>Pet ID</th>
                <th>Pet</th>
                <th>Espécie</th>
                <th>Raça</th>
                <th>Nascimento</th>
                <th>Tutor ID</th>
                <th>Tutor</th>
                <th>CPF</th>
                <th>Telefone</th>
                <th>E-mail</th>
                <th>Horário</th>
                <th>Motivo</th>
                <th>Observações</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($r = $listagem->fetch_assoc()): ?>
                <tr>
                    <td><?= e($r['id']) ?></td>
                    <td><?= e($r['pet_id']) ?></td>
                    <td><?= e($r['pet']) ?></td>
                    <td><?= e($r['especie']) ?></td>
                    <td><?= e($r['raca']) ?></td>
                    <td><?= e($r['data_nascimento']) ?></td>
                    <td><?= e($r['tutor_id']) ?></td>
                    <td><?= e($r['tutor']) ?></td>
                    <td><?= e(descriptografar($r['cpf_criptografado'])) ?></td>
                    <td><?= e($r['telefone']) ?></td>
                    <td><?= e($r['email']) ?></td>
                    <td><?= e($r['data_hora']) ?></td>
                    <td><?= e($r['motivo']) ?></td>
                    <td><?= e($r['observacoes']) ?></td>
                    <td>
                        <a href="agendamentos.php?editar=<?= e($r['id']) ?>">Editar</a>
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