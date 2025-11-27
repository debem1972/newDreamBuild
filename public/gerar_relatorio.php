<?php
session_start();

// Validar sessão
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/functions.php';

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$nome_relatorio = limparPost($_POST['nome_relatorio']);
$nome_usuario = limparPost($_POST['nome_usuario']);
$data_inicio = limparPost($_POST['data_inicio']);
$data_fim = limparPost($_POST['data_fim']);

// Obter lançamentos do período
try {
    $sql = $pdo->prepare("
        SELECT * FROM lancamentos 
        WHERE usuario_id = ? AND data >= ? AND data <= ?
        ORDER BY data ASC, entrada ASC
    ");
    $sql->execute(array($usuario_id, $data_inicio, $data_fim));
    $lancamentos = $sql->fetchAll();
} catch (Exception $e) {
    die('Erro ao obter lançamentos: ' . $e->getMessage());
}

// Calcular totais
$total_horas = 0;
$horas_por_dia = array();
$horas_por_tipo = array();

foreach ($lancamentos as $lancamento) {
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

// Gerar HTML para o PDF
$html = '
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
        }
        
        .header h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 12px;
            margin: 5px 0;
        }
        
        .info-section {
            background-color: #f5f7fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }
        
        .info-section p {
            margin: 5px 0;
            font-size: 12px;
        }
        
        .info-label {
            font-weight: bold;
            color: #333;
        }
        
        .stats-section {
            margin-bottom: 30px;
        }
        
        .stats-section h2 {
            color: #667eea;
            font-size: 18px;
            margin-bottom: 15px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .stat-box {
            display: table-cell;
            width: 25%;
            padding: 15px;
            text-align: center;
            background-color: #f5f7fa;
            border: 1px solid #ddd;
            border-right: none;
        }
        
        .stat-box:last-child {
            border-right: 1px solid #ddd;
        }
        
        .stat-box .label {
            font-size: 11px;
            color: #666;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-box .value {
            font-size: 20px;
            color: #667eea;
            font-weight: bold;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        table thead {
            background-color: #667eea;
            color: white;
        }
        
        table th {
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: bold;
        }
        
        table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
        }
        
        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        table tbody tr:hover {
            background-color: #f5f7fa;
        }
        
        .total-row {
            background-color: #e7f1ff;
            font-weight: bold;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #999;
        }
        
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🏗️ DreamBuild</h1>
            <p>Relatório de Horas de Trabalho</p>
        </div>
        
        <!-- Informações do Relatório -->
        <div class="info-section">
            <p><span class="info-label">Relatório:</span> ' . htmlspecialchars($nome_relatorio) . '</p>
            <p><span class="info-label">Usuário:</span> ' . htmlspecialchars($nome_usuario) . '</p>
            <p><span class="info-label">Período:</span> ' . converterDataMySQL($data_inicio) . ' a ' . converterDataMySQL($data_fim) . '</p>
            <p><span class="info-label">Data de Geração:</span> ' . date('d/m/Y H:i:s') . '</p>
        </div>
        
        <!-- Estatísticas Gerais -->
        <div class="stats-section">
            <h2>Resumo Geral</h2>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="label">Total de Horas</div>
                    <div class="value">' . formatarHoras($total_horas) . '</div>
                </div>
                <div class="stat-box">
                    <div class="label">Lançamentos</div>
                    <div class="value">' . count($lancamentos) . '</div>
                </div>
                <div class="stat-box">
                    <div class="label">Dias Trabalhados</div>
                    <div class="value">' . count($horas_por_dia) . '</div>
                </div>
                <div class="stat-box">
                    <div class="label">Tipos de Serviço</div>
                    <div class="value">' . count($horas_por_tipo) . '</div>
                </div>
            </div>
        </div>
        
        <!-- Tabela de Lançamentos -->
        <div class="stats-section">
            <h2>Detalhamento de Lançamentos</h2>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo de Serviço</th>
                        <th>Entrada</th>
                        <th>Saída</th>
                        <th>Horas Trabalhadas</th>
                    </tr>
                </thead>
                <tbody>';

foreach ($lancamentos as $lancamento) {
    $horas = calcularHoras($lancamento['entrada'], $lancamento['saida']);
    $html .= '
                    <tr>
                        <td>' . converterDataMySQL($lancamento['data']) . '</td>
                        <td>' . htmlspecialchars($lancamento['tipo_servico']) . '</td>
                        <td>' . htmlspecialchars($lancamento['entrada']) . '</td>
                        <td>' . htmlspecialchars($lancamento['saida']) . '</td>
                        <td>' . formatarHoras($horas) . '</td>
                    </tr>';
}

$html .= '
                </tbody>
            </table>
        </div>
        
        <!-- Resumo por Dia -->
        <div class="stats-section">
            <h2>Resumo por Dia</h2>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Total de Horas</th>
                    </tr>
                </thead>
                <tbody>';

foreach ($horas_por_dia as $data => $horas) {
    $html .= '
                    <tr>
                        <td>' . converterDataMySQL($data) . '</td>
                        <td>' . formatarHoras($horas) . '</td>
                    </tr>';
}

$html .= '
                    <tr class="total-row">
                        <td>TOTAL</td>
                        <td>' . formatarHoras($total_horas) . '</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Resumo por Tipo de Serviço -->
        <div class="stats-section">
            <h2>Resumo por Tipo de Serviço</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tipo de Serviço</th>
                        <th>Total de Horas</th>
                    </tr>
                </thead>
                <tbody>';

foreach ($horas_por_tipo as $tipo => $horas) {
    $html .= '
                    <tr>
                        <td>' . htmlspecialchars($tipo) . '</td>
                        <td>' . formatarHoras($horas) . '</td>
                    </tr>';
}

$html .= '
                    <tr class="total-row">
                        <td>TOTAL</td>
                        <td>' . formatarHoras($total_horas) . '</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>Este relatório foi gerado automaticamente pelo sistema DreamBuild</p>
            <p>© 2025 DreamBuild - Gerenciamento de Horas de Trabalho</p>
        </div>
    </div>
</body>
</html>';

// Usar a biblioteca TCPDF para gerar o PDF
require_once '../vendor/autoload.php';

try {
    // Criar instância do TCPDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configurar propriedades do documento
    $pdf->SetCreator('DreamBuild');
    $pdf->SetAuthor($nome_usuario);
    $pdf->SetTitle($nome_relatorio);
    $pdf->SetSubject('Relatório de Horas de Trabalho');
    
    // Remover header e footer padrão
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Adicionar página
    $pdf->AddPage();
    
    // Escrever HTML
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Gerar nome do arquivo
    $nome_arquivo = 'relatorio_' . date('Y-m-d_H-i-s') . '.pdf';
    
    // Enviar PDF para download
    $pdf->Output($nome_arquivo, 'D');
    
} catch (Exception $e) {
    die('Erro ao gerar PDF: ' . $e->getMessage());
}
?>
