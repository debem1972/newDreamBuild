<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Validar sessão
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/functions.php';

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = $_SESSION['usuario_nome'];

// Variáveis para controlar mensagens
$mensagem = '';
$tipo_mensagem = '';

// ========== INSERÇÃO DE NOVO LANÇAMENTO ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'inserir') {
    try {
        $data = limparPost($_POST['data']);
        $tipo_servico = limparPost($_POST['tipo_servico']);
        $entrada = limparPost($_POST['entrada']);
        $saida = limparPost($_POST['saida']);

        if (empty($data) || empty($tipo_servico) || empty($entrada) || empty($saida)) {
            $mensagem = 'Por favor, preencha todos os campos.';
            $tipo_mensagem = 'error';
        } else {
            $sql = $pdo->prepare("INSERT INTO lancamentos (usuario_id, data, tipo_servico, entrada, saida) VALUES (?, ?, ?, ?, ?)");
            $sql->execute(array($usuario_id, $data, $tipo_servico, $entrada, $saida));

            $mensagem = '✅ Lançamento inserido com sucesso!';
            $tipo_mensagem = 'success';
        }
    } catch (Exception $e) {
        $mensagem = '❌ Erro ao inserir lançamento: ' . $e->getMessage();
        $tipo_mensagem = 'error';
    }
}

// ========== ATUALIZAÇÃO DE LANÇAMENTO ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'atualizar') {
    try {
        $id = limparPost($_POST['id']);
        $data = limparPost($_POST['data']);
        $tipo_servico = limparPost($_POST['tipo_servico']);
        $entrada = limparPost($_POST['entrada']);
        $saida = limparPost($_POST['saida']);

        if (empty($id) || empty($data) || empty($tipo_servico) || empty($entrada) || empty($saida)) {
            $mensagem = 'Por favor, preencha todos os campos.';
            $tipo_mensagem = 'error';
        } else {
            $sql = $pdo->prepare("UPDATE lancamentos SET data = ?, tipo_servico = ?, entrada = ?, saida = ? WHERE id = ? AND usuario_id = ?");
            $sql->execute(array($data, $tipo_servico, $entrada, $saida, $id, $usuario_id));

            $mensagem = '✅ Lançamento atualizado com sucesso!';
            $tipo_mensagem = 'success';
        }
    } catch (Exception $e) {
        $mensagem = '❌ Erro ao atualizar lançamento: ' . $e->getMessage();
        $tipo_mensagem = 'error';
    }
}

// ========== EXCLUSÃO DE LANÇAMENTO ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'deletar') {
    try {
        $id = limparPost($_POST['id']);

        if (empty($id)) {
            $mensagem = 'ID inválido.';
            $tipo_mensagem = 'error';
        } else {
            $sql = $pdo->prepare("DELETE FROM lancamentos WHERE id = ? AND usuario_id = ?");
            $sql->execute(array($id, $usuario_id));

            $mensagem = '✅ Lançamento deletado com sucesso!';
            $tipo_mensagem = 'success';
        }
    } catch (Exception $e) {
        $mensagem = '❌ Erro ao deletar lançamento: ' . $e->getMessage();
        $tipo_mensagem = 'error';
    }
}

// ========== OBTER LANÇAMENTOS ==========
$filtro_data = isset($_GET['filtro_data']) ? limparPost($_GET['filtro_data']) : '';
$filtro_tipo = isset($_GET['filtro_tipo']) ? limparPost($_GET['filtro_tipo']) : '';
$filtro_periodo = isset($_GET['filtro_periodo']) ? limparPost($_GET['filtro_periodo']) : '30';

// Paginação
$itens_por_pagina = 5;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_atual < 1) $pagina_atual = 1;

$sql = "SELECT * FROM lancamentos WHERE usuario_id = ?";
$params = array($usuario_id);

if (!empty($filtro_data)) {
    $sql .= " AND data = ?";
    $params[] = $filtro_data;
}

if (!empty($filtro_tipo)) {
    $sql .= " AND tipo_servico = ?";
    $params[] = $filtro_tipo;
}

$data_inicio = date('Y-m-d', strtotime("-{$filtro_periodo} days"));
$sql .= " AND data >= ?";
$params[] = $data_inicio;

$sql .= " ORDER BY data DESC, entrada DESC";

$stmt_count = $pdo->prepare($sql);
$stmt_count->execute($params);
$total_registros = $stmt_count->rowCount();
$total_paginas = ($total_registros > 0) ? ceil($total_registros / $itens_por_pagina) : 1;

if ($pagina_atual > $total_paginas && $total_paginas > 0) {
    $pagina_atual = $total_paginas;
}

$offset = ($pagina_atual - 1) * $itens_por_pagina;
if ($offset < 0) $offset = 0;

$sql .= " LIMIT " . intval($itens_por_pagina) . " OFFSET " . intval($offset);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lancamentos = $stmt->fetchAll();

// Obter todos os lançamentos para cálculos de totais
$sql_totais = "SELECT * FROM lancamentos WHERE usuario_id = ?";
$params_totais = array($usuario_id);

if (!empty($filtro_data)) {
    $sql_totais .= " AND data = ?";
    $params_totais[] = $filtro_data;
}

if (!empty($filtro_tipo)) {
    $sql_totais .= " AND tipo_servico = ?";
    $params_totais[] = $filtro_tipo;
}

$sql_totais .= " AND data >= ?";
$params_totais[] = $data_inicio;

$stmt_totais = $pdo->prepare($sql_totais);
$stmt_totais->execute($params_totais);
$lancamentos_totais = $stmt_totais->fetchAll();

// ========== CALCULAR TOTAIS ==========
$total_horas = 0;
$horas_por_dia = array();
$horas_por_tipo = array();

foreach ($lancamentos_totais as $lancamento) {
    $horas = calcularHoras($lancamento['entrada'], $lancamento['saida']);
    $total_horas += $horas;

    $data = $lancamento['data'];
    if (!isset($horas_por_dia[$data])) {
        $horas_por_dia[$data] = 0;
    }
    $horas_por_dia[$data] += $horas;

    $tipo = $lancamento['tipo_servico'];
    if (!isset($horas_por_tipo[$tipo])) {
        $horas_por_tipo[$tipo] = 0;
    }
    $horas_por_tipo[$tipo] += $horas;
}

$tipos_servico = obterTiposServico();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DreamBuild - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 12px 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.3rem;
        }

        /* LAYOUT PRINCIPAL - Grid 2x2 + Tabela embaixo */
        .main-wrapper {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 56px);
            padding: 15px;
            gap: 15px;
        }

        .grid-container {
            display: grid;
            /*modificado*/
            grid-template-columns: 1fr 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 15px;
            flex: 0 0 auto;
            /*modificado*/
            height: 50vh;
            min-height: 500px;
        }

        .grid-item {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        /*inserido*/
        /* Novo Lançamento (1) - 1ª coluna, ocupa 2 linhas */
        .grid-item:nth-child(1) {
            grid-column: 1;
            grid-row: 1 / 3;
        }


        /* Filtros (2) - 2ª coluna, ocupa 2 linhas */
        .grid-item:nth-child(3) {
            grid-column: 2;
            grid-row: 1 / 3;
        }

        /* Análise de Horas (3) - 3ª coluna, 1ª linha */
        .grid-item:nth-child(2) {
            grid-column: 3;
            grid-row: 1;
        }

        /* Monitor (4) - 3ª coluna, 2ª linha */
        .grid-item:nth-child(4) {
            grid-column: 3;
            grid-row: 2;
        }

        .grid-item h3 {
            color: #333;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        /* ADICIONAR ESTE NOVO ESTILO: */
        .btn-pdf-inline {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            /*color: #ffff;*/
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            /*font-weight: 600;*/
            cursor: pointer;
            transition: transform 0.2s;
            font-size: 0.75rem;
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .grid-item h3 i {
            color: #ffff;
        }

        .btn-pdf-inline:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }



        /* FORMULÁRIO DE LANÇAMENTO */
        .form-compact {
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
        }

        .form-compact .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-compact label {
            color: #333;
            font-weight: 500;
            margin-bottom: 6px;
            font-size: 0.85rem;
        }

        .form-compact input,
        .form-compact select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .form-compact input:focus,
        .form-compact select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            font-size: 0.9rem;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
        }

        /* FILTROS */
        .filter-compact {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .filter-actions {
            display: flex;
            gap: 8px;
            margin-top: auto;
        }

        .btn-secondary-custom {
            background: #6c757d;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary-custom:hover {
            background: #5a6268;
        }

        /* DASHBOARD COM GRÁFICOS */
        .chart-wrapper {
            flex: 1;
            display: flex;
            gap: 15px;
            min-height: 0;
        }

        .chart-box {
            flex: 1;
            position: relative;
            min-height: 0;
        }

        .chart-box canvas {
            max-height: 100%;
        }

        /* MONITOR DE TOTAIS */
        .stats-grid-compact {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            flex: 1;
            align-content: start;
        }

        .stat-card-compact {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-card-compact h4 {
            font-size: 0.7rem;
            font-weight: 500;
            margin-bottom: 6px;
            opacity: 0.9;
        }

        .stat-card-compact .value {
            font-size: 1.2rem;
            font-weight: 700;
        }

        /* TABELA DE LANÇAMENTOS */
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            padding: 20px;
            flex: 1;
            overflow-y: auto;
            min-height: 0;
            /*inserido*/
            max-height: calc(50vh - 30px);
        }

        .table-container h3 {
            color: #333;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-container h3 i {
            color: #667eea;
        }

        table {
            width: 100%;
            background: white;
            border-collapse: collapse;
        }

        table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            position: sticky;
            top: 0;
        }

        table th {
            color: #333;
            font-weight: 600;
            padding: 10px;
            text-align: left;
            font-size: 0.85rem;
        }

        table td {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
            font-size: 0.85rem;
        }

        table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .btn-action {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.2s;
            margin-right: 5px;
        }

        .btn-edit {
            background-color: #28a745;
            color: white;
        }

        .btn-edit:hover {
            background-color: #218838;
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background-color: #c82333;
        }

        /* ALERTAS */
        .alert-custom {
            position: fixed;
            top: 70px;
            right: 20px;
            max-width: 400px;
            padding: 15px;
            border-radius: 5px;
            z-index: 9999;
            animation: slideIn 0.3s ease;
        }

        .alert-custom.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-custom.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* MODAIS */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 15px;
        }

        .modal-header h2 {
            margin: 0;
            color: #333;
            font-weight: 600;
            font-size: 1.3rem;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .no-data {
            text-align: center;
            padding: 20px;
            color: #666;
        }

        /* Paginação */
        .pagination-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .pagination-btn {
            padding: 6px 10px;
            border: 1px solid #ddd;
            background-color: white;
            color: #333;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
        }

        .pagination-btn:hover {
            background-color: #f0f0f0;
            border-color: #667eea;
        }

        .pagination-btn.active {
            background-color: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination-btn:disabled,
        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-info {
            color: #666;
            font-size: 0.85rem;
        }

        /* CONTINUA NA PARTE 2 - Adicionar os scripts e modais */
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand">🏗️ DreamBuild</span>
            <div class="ms-auto d-flex align-items-center gap-3">
                <span class="text-white">Bem-vindo, <strong><?php echo htmlspecialchars($usuario_nome); ?></strong></span>
                <a href="logout.php" class="logout-btn">Sair</a>
            </div>
        </div>
    </nav>

    <!-- Mensagens -->
    <?php if (!empty($mensagem)): ?>
        <div class="alert-custom <?php echo $tipo_mensagem; ?>">
            <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <!-- Grid 2x2 -->
        <div class="grid-container">
            <!-- 1. FORMULÁRIO DE NOVO LANÇAMENTO (Canto Superior Esquerdo) -->
            <div class="grid-item">
                <h3><i class="bi bi-plus-circle"></i> Novo Lançamento</h3>
                <form method="POST" class="form-compact">
                    <input type="hidden" name="acao" value="inserir">

                    <div class="form-group">
                        <label for="data">Data</label>
                        <input type="date" id="data" name="data" required>
                    </div>

                    <div class="form-group">
                        <label for="tipo_servico">Tipo de Serviço</label>
                        <select id="tipo_servico" name="tipo_servico" required>
                            <option value="">Selecione</option>
                            <?php foreach ($tipos_servico as $key => $label): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>">
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="entrada">Hora de Entrada</label>
                        <input type="time" id="entrada" name="entrada" required>
                    </div>

                    <div class="form-group">
                        <label for="saida">Hora de Saída</label>
                        <input type="time" id="saida" name="saida" required>
                    </div>

                    <button type="submit" class="btn-primary-custom mt-auto">
                        <i class="bi bi-check-circle"></i> Registrar
                    </button>
                </form>
            </div>

            <!-- 2. DASHBOARD COM GRÁFICOS (Canto Superior Direito) -->
            <div class="grid-item">
                <h3><i class="bi bi-bar-chart"></i> Análise de Horas</h3>
                <div class="chart-wrapper">
                    <div class="chart-box">
                        <canvas id="chartHorasPorDia"></canvas>
                    </div>
                    <div class="chart-box">
                        <canvas id="chartHorasPorTipo"></canvas>
                    </div>
                </div>
            </div>

            <!-- 3. FILTROS (Canto Inferior Esquerdo) -->
            <div class="grid-item">
                <h3><i class="bi bi-funnel"></i> Filtros</h3>
                <form method="GET" class="filter-compact">
                    <div class="form-group">
                        <label for="filtro_data">Data Específica</label>
                        <input type="date" id="filtro_data" name="filtro_data" value="<?php echo htmlspecialchars($filtro_data); ?>">
                    </div>

                    <div class="form-group">
                        <label for="filtro_tipo">Tipo de Serviço</label>
                        <select id="filtro_tipo" name="filtro_tipo">
                            <option value="">Todos</option>
                            <?php foreach ($tipos_servico as $key => $label): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($filtro_tipo === $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="filtro_periodo">Período</label>
                        <select id="filtro_periodo" name="filtro_periodo">
                            <option value="7" <?php echo ($filtro_periodo === '7') ? 'selected' : ''; ?>>Últimos 7 dias</option>
                            <option value="15" <?php echo ($filtro_periodo === '15') ? 'selected' : ''; ?>>Últimos 15 dias</option>
                            <option value="30" <?php echo ($filtro_periodo === '30') ? 'selected' : ''; ?>>Últimos 30 dias</option>
                            <option value="90" <?php echo ($filtro_periodo === '90') ? 'selected' : ''; ?>>Últimos 90 dias</option>
                        </select>
                    </div>

                    <div class="filter-actions mt-auto">
                        <button type="submit" class="btn-primary-custom">🔍 Filtrar</button>
                        <a href="dashboard.php" class="btn-secondary-custom">🔄 Limpar</a>
                    </div>
                </form>
            </div>


            <!-- 4. MONITOR DE TOTAIS (Canto Inferior Direito) -->
            <div class="grid-item">
                <h3>
                    <i class="bi bi-speedometer2"></i> Monitor
                    <button type="button" class="btn-pdf-inline" onclick="abrirModalRelatorio()" title="Gerar PDF">
                        <i class="bi bi-file-pdf"></i>
                    </button>
                </h3>
                <div class="stats-grid-compact">
                    <div class="stat-card-compact">
                        <h4>Total de Horas</h4>
                        <div class="value"><?php echo formatarHoras($total_horas); ?></div>
                    </div>
                    <div class="stat-card-compact">
                        <h4>Lançamentos</h4>
                        <div class="value"><?php echo count($lancamentos_totais); ?></div>
                    </div>
                    <div class="stat-card-compact">
                        <h4>Dias Trabalhados</h4>
                        <div class="value"><?php echo count($horas_por_dia); ?></div>
                    </div>
                    <div class="stat-card-compact">
                        <h4>Tipos de Serviço</h4>
                        <div class="value"><?php echo count($horas_por_tipo); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABELA DE LANÇAMENTOS (Abaixo do Grid) -->
        <div class="table-container">
            <h3><i class="bi bi-table"></i> Lançamentos Registrados</h3>
            <?php if (count($lancamentos) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo de Serviço</th>
                            <th>Entrada</th>
                            <th>Saída</th>
                            <th>Horas</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lancamentos as $lancamento): ?>
                            <?php $horas = calcularHoras($lancamento['entrada'], $lancamento['saida']); ?>
                            <tr>
                                <td><?php echo converterDataMySQL($lancamento['data']); ?></td>
                                <td><?php echo htmlspecialchars($lancamento['tipo_servico']); ?></td>
                                <td><?php echo htmlspecialchars($lancamento['entrada']); ?></td>
                                <td><?php echo htmlspecialchars($lancamento['saida']); ?></td>
                                <td><strong><?php echo formatarHoras($horas); ?></strong></td>
                                <td>
                                    <button type="button" class="btn-action btn-edit" onclick="abrirModalEdicao(<?php echo $lancamento['id']; ?>, '<?php echo $lancamento['data']; ?>', '<?php echo htmlspecialchars($lancamento['tipo_servico']); ?>', '<?php echo $lancamento['entrada']; ?>', '<?php echo $lancamento['saida']; ?>')">
                                        ✏️ Editar
                                    </button>
                                    <button type="button" class="btn-action btn-delete" onclick="confirmarDelecao(<?php echo $lancamento['id']; ?>)">
                                        🗑️ Deletar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Paginação -->
                <?php if ($total_paginas > 1): ?>
                    <div class="pagination-container">
                        <?php if ($pagina_atual > 1): ?>
                            <a href="?pagina=<?php echo $pagina_atual - 1; ?>&filtro_data=<?php echo urlencode($filtro_data); ?>&filtro_tipo=<?php echo urlencode($filtro_tipo); ?>&filtro_periodo=<?php echo urlencode($filtro_periodo); ?>" class="pagination-btn">« Anterior</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">« Anterior</span>
                        <?php endif; ?>

                        <?php
                        $inicio = max(1, $pagina_atual - 2);
                        $fim = min($total_paginas, $pagina_atual + 2);

                        if ($inicio > 1) {
                            echo '<a href="?pagina=1&filtro_data=' . urlencode($filtro_data) . '&filtro_tipo=' . urlencode($filtro_tipo) . '&filtro_periodo=' . urlencode($filtro_periodo) . '" class="pagination-btn">1</a>';
                            if ($inicio > 2) {
                                echo '<span class="pagination-btn disabled">...</span>';
                            }
                        }

                        for ($i = $inicio; $i <= $fim; $i++) {
                            if ($i == $pagina_atual) {
                                echo '<span class="pagination-btn active">' . $i . '</span>';
                            } else {
                                echo '<a href="?pagina=' . $i . '&filtro_data=' . urlencode($filtro_data) . '&filtro_tipo=' . urlencode($filtro_tipo) . '&filtro_periodo=' . urlencode($filtro_periodo) . '" class="pagination-btn">' . $i . '</a>';
                            }
                        }

                        if ($fim < $total_paginas) {
                            if ($fim < $total_paginas - 1) {
                                echo '<span class="pagination-btn disabled">...</span>';
                            }
                            echo '<a href="?pagina=' . $total_paginas . '&filtro_data=' . urlencode($filtro_data) . '&filtro_tipo=' . urlencode($filtro_tipo) . '&filtro_periodo=' . urlencode($filtro_periodo) . '" class="pagination-btn">' . $total_paginas . '</a>';
                        }
                        ?>

                        <?php if ($pagina_atual < $total_paginas): ?>
                            <a href="?pagina=<?php echo $pagina_atual + 1; ?>&filtro_data=<?php echo urlencode($filtro_data); ?>&filtro_tipo=<?php echo urlencode($filtro_tipo); ?>&filtro_periodo=<?php echo urlencode($filtro_periodo); ?>" class="pagination-btn">Próximo »</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">Próximo »</span>
                        <?php endif; ?>

                        <span class="pagination-info">Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?></span>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="bi bi-inbox" style="font-size: 2rem; color: #ddd;"></i>
                    <p>Nenhum lançamento encontrado</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Edição -->
    <div id="modalEdicao" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Lançamento</h2>
                <button type="button" class="modal-close" onclick="fecharModalEdicao()">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="acao" value="atualizar">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="edit_data">Data</label>
                    <input type="date" id="edit_data" name="data" required>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="edit_tipo_servico">Tipo de Serviço</label>
                    <select id="edit_tipo_servico" name="tipo_servico" required>
                        <option value="">Selecione</option>
                        <?php foreach ($tipos_servico as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>">
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="edit_entrada">Hora de Entrada</label>
                    <input type="time" id="edit_entrada" name="entrada" required>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="edit_saida">Hora de Saída</label>
                    <input type="time" id="edit_saida" name="saida" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary-custom" onclick="fecharModalEdicao()">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Relatório -->
    <div id="modalRelatorio" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Gerar Relatório em PDF</h2>
                <button type="button" class="modal-close" onclick="fecharModalRelatorio()">×</button>
            </div>
            <form method="POST" action="gerar_relatorio.php">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="rel_nome_relatorio">Nome do Relatório</label>
                    <input type="text" id="rel_nome_relatorio" name="nome_relatorio" placeholder="Ex: Relatório de Horas - Novembro" required>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="rel_nome_usuario">Nome do Usuário</label>
                    <input type="text" id="rel_nome_usuario" name="nome_usuario" value="<?php echo htmlspecialchars($usuario_nome); ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="rel_data_inicio">Data de Início</label>
                    <input type="date" id="rel_data_inicio" name="data_inicio" required>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="rel_data_fim">Data de Fim</label>
                    <input type="date" id="rel_data_fim" name="data_fim" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary-custom" onclick="fecharModalRelatorio()">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Gerar PDF</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Deleção -->
    <div id="modalDelecao" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirmar Exclusão</h2>
                <button type="button" class="modal-close" onclick="fecharModalDelecao()">×</button>
            </div>
            <p style="margin-bottom: 20px; color: #666;">Tem certeza que deseja deletar este lançamento? Esta ação não pode ser desfeita.</p>
            <form method="POST">
                <input type="hidden" name="acao" value="deletar">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-footer">
                    <button type="button" class="btn-secondary-custom" onclick="fecharModalDelecao()">Cancelar</button>
                    <button type="submit" class="btn-delete">Deletar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Funções de Modal
        function abrirModalEdicao(id, data, tipo, entrada, saida) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_data').value = data;
            document.getElementById('edit_tipo_servico').value = tipo;
            document.getElementById('edit_entrada').value = entrada;
            document.getElementById('edit_saida').value = saida;
            document.getElementById('modalEdicao').classList.add('show');
        }

        function fecharModalEdicao() {
            document.getElementById('modalEdicao').classList.remove('show');
        }

        function abrirModalRelatorio() {
            document.getElementById('modalRelatorio').classList.add('show');
        }

        function fecharModalRelatorio() {
            document.getElementById('modalRelatorio').classList.remove('show');
        }

        function confirmarDelecao(id) {
            document.getElementById('delete_id').value = id;
            document.getElementById('modalDelecao').classList.add('show');
        }

        function fecharModalDelecao() {
            document.getElementById('modalDelecao').classList.remove('show');
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }

        // Gráficos
        <?php
        $labels_dias = array_keys($horas_por_dia);
        $dados_dias = array_values($horas_por_dia);
        $labels_tipos = array_keys($horas_por_tipo);
        $dados_tipos = array_values($horas_por_tipo);
        ?>

        const ctxDias = document.getElementById('chartHorasPorDia').getContext('2d');
        new Chart(ctxDias, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map('converterDataMySQL', $labels_dias)); ?>,
                datasets: [{
                    label: 'Horas',
                    data: <?php echo json_encode($dados_dias); ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.7)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 2,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        const ctxTipos = document.getElementById('chartHorasPorTipo').getContext('2d');
        new Chart(ctxTipos, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($labels_tipos); ?>,
                datasets: [{
                    data: <?php echo json_encode($dados_tipos); ?>,
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(118, 75, 162, 0.8)',
                        'rgba(244, 143, 177, 0.8)',
                        'rgba(66, 165, 245, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>