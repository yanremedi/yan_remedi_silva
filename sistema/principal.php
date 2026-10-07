<?php
require_once 'proteger.php';

cabecalho('Principal');
?>
<p>Olá Casa do Pet, Seja Bem-Vindo, <?= e($_SESSION['usuario_nome']) ?> 👋!</p>
<p>Use o seu menu para cadastrar tutores, pets e agendamentos. 🗓️</p>
<form action="logout.php" method="post">
    <input type="hidden" name="csrf" value="<?= e(token()) ?>">
    <button>Sair do sistema</button>
</form>
<?php rodape(); ?>