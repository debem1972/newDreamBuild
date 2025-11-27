<?php
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
$filtro_periodo = isset($_GET['filtro_periodo']) ? limparPost($_GET['filtro_periodo']) : '30'; // 30 dias por padrão

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

// Filtrar por período
$data_inicio = date('Y-m-d', strtotime("-{$filtro_periodo} days"));
$sql .= " AND data >= ?";
$params[] = $data_inicio;

$sql .= " ORDER BY data DESC, entrada DESC";

// Contar total de registros
$stmt_count = $pdo->prepare($sql);
$stmt_count->execute($params);
$total_registros = $stmt_count->rowCount();
$total_paginas = ceil($total_registros / $itens_por_pagina);

// Validar página
if ($pagina_atual > $total_paginas && $total_paginas > 0) {
    $pagina_atual = $total_paginas;
}

// Calcular offset
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Obter registros da página
$sql .= " LIMIT ? OFFSET ?";
$params[] = $itens_por_pagina;
$params[] = $offset;

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
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }

        .container-main {
            padding: 30px 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-section {
            margin-bottom: 30px;
        }

        .header-section h1 {
            color: #333;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .header-section p {
            color: #666;
            font-size: 0.95rem;
        }

        .alert-custom {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 5px;
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
                transform: translateY(-20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .card-custom {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 25px;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card-custom:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
        }

        .card-custom h2 {
            color: #333;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-custom h2 i {
            color: #667eea;
            font-size: 1.3rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(102, 126, 234, 0.2);
        }

        .stat-card h3 {
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
        }

        .form-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 25px;
        }

        .form-section h3 {
            color: #333;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section h3 i {
            color: #667eea;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            color: #333;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary-custom {
            background: #6c757d;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-secondary-custom:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }

        table {
            background: white;
            border-collapse: collapse;
        }

        table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        table th {
            color: #333;
            font-weight: 600;
            padding: 15px;
            text-align: left;
        }

        table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }

        table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
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

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }

        .filter-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            padding: 20px;
            margin-bottom: 25px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
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
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .modal-close:hover {
            color: #333;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }

        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .no-data i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 10px;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Paginação */
        .pagination-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 25px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            flex-wrap: wrap;
        }

        .pagination-btn {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background-color: white;
            color: #333;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
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
            background-color: #f5f5f5;
        }

        .pagination-info {
            color: #666;
            font-size: 0.95rem;
            font-weight: 500;
        }
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

    <!-- Main Container -->
    <div class="container-main">
        <!-- Header -->
        <div class="header-section">
            <h1>📊 Dashboard</h1>
            <p>Gerencie seus lançamentos de horas de trabalho</p>
        </div>

        <!-- Mensagens -->
        <?php if (!empty($mensagem)): ?>
            <div class="alert-custom <?php echo $tipo_mensagem; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total de Horas</h3>
                <div class="value"><?php echo formatarHoras($total_horas); ?></div>
            </div>
            <div class="stat-card">
                <h3>Lançamentos</h3>
                <div class="value"><?php echo count($lancamentos); ?></div>
            </div>
            <div class="stat-card">
                <h3>Dias Trabalhados</h3>
                <div class="value"><?php echo count($horas_por_dia); ?></div>
            </div>
            <div class="stat-card">
                <h3>Tipos de Serviço</h3>
                <div class="value"><?php echo count($horas_por_tipo); ?></div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-section">
            <form method="GET" class="filter-row">
                <div class="form-group">
                    <label for="filtro_data">Data</label>
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
                <div>
                    <button type="submit" class="btn-primary-custom">🔍 Filtrar</button>
                    <a href="dashboard.php" class="btn-secondary-custom">🔄 Limpar</a>
                </div>
            </form>
        </div>

        <!-- Formulário de Inserção -->
        <div class="form-section">
            <h3><i class="bi bi-plus-circle"></i> Novo Lançamento</h3>
            <form method="POST">
                <input type="hidden" name="acao" value="inserir">
                <div class="form-row">
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
                </div>
                <button type="submit" class="btn-primary-custom"><i class="bi bi-check-circle"></i> Registrar Lançamento</button>
            </form>
        </div>

        <!-- Gráficos -->
        <div class="card-custom">
            <h2><i class="bi bi-bar-chart"></i> Análise de Horas</h2>
            <div class="row">
                <div class="col-md-6">
                    <div class="chart-container">
                        <canvas id="chartHorasPorDia"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-container">
                        <canvas id="chartHorasPorTipo"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela de Lançamentos -->
        <div class="card-custom">
            <h2><i class="bi bi-table"></i> Lançamentos Registrados</h2>
            <?php if (count($lancamentos) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Tipo de Serviço</th>
                                <th>Entrada</th>
                                <th>Saída</th>
                                <th>Horas Trabalhadas</th>
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
                </div>
                <!-- Paginação -->
                <?php if ($total_paginas > 1): ?>
                    <div class="pagination-container">
                        <!-- Botão Anterior -->
                        <?php if ($pagina_atual > 1): ?>
                            <a href="?pagina=<?php echo $pagina_atual - 1; ?>&filtro_data=<?php echo urlencode($filtro_data); ?>&filtro_tipo=<?php echo urlencode($filtro_tipo); ?>&filtro_periodo=<?php echo urlencode($filtro_periodo); ?>" class="pagination-btn">« Anterior</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">« Anterior</span>
                        <?php endif; ?>

                        <!-- Números de Página -->
                        <?php
                        // Mostrar até 5 páginas por vez
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

                        <!-- Botão Próximo -->
                        <?php if ($pagina_atual < $total_paginas): ?>
                            <a href="?pagina=<?php echo $pagina_atual + 1; ?>&filtro_data=<?php echo urlencode($filtro_data); ?>&filtro_tipo=<?php echo urlencode($filtro_tipo); ?>&filtro_periodo=<?php echo urlencode($filtro_periodo); ?>" class="pagination-btn">Próximo »</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">Próximo »</span>
                        <?php endif; ?>

                        <!-- Informações de Página -->
                        <span class="pagination-info">Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?> (<?php echo $total_registros; ?> registros)</span>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="bi bi-inbox"></i>
                    <p>Nenhum lançamento encontrado</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Botão para Gerar Relatório -->
        <div style="margin-top: 25px; text-align: center;">
            <button type="button" class="btn-primary-custom" onclick="abrirModalRelatorio()">
                <i class="bi bi-file-pdf"></i> Gerar Relatório em PDF
            </button>
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
                    <button type="submit" class="btn-primary-custom">Salvar Alterações</button>
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

    <!-- Modal de Confirmação de Deleção -->
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
        // ========== FUNÇÕES DE MODAL ==========
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

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            let modal = event.target;
            if (modal.classList.contains('modal')) {
                modal.classList.remove('show');
            }
        }

        // ========== GRÁFICOS ==========
        <?php
        // Preparar dados para gráficos
        $labels_dias = array_keys($horas_por_dia);
        $dados_dias = array_values($horas_por_dia);

        $labels_tipos = array_keys($horas_por_tipo);
        $dados_tipos = array_values($horas_por_tipo);
        ?>

        // Gráfico de Horas por Dia
        const ctxDias = document.getElementById('chartHorasPorDia').getContext('2d');
        new Chart(ctxDias, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map('converterDataMySQL', $labels_dias)); ?>,
                datasets: [{
                    label: 'Horas Trabalhadas',
                    data: <?php echo json_encode($dados_dias); ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.7)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 2,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Horas'
                        }
                    }
                }
            }
        });

        // Gráfico de Horas por Tipo de Serviço
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
                    ],
                    borderColor: [
                        'rgba(102, 126, 234, 1)',
                        'rgba(118, 75, 162, 1)',
                        'rgba(244, 143, 177, 1)',
                        'rgba(66, 165, 245, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>