<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Validar sessão
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

require_once '../config/database.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

$usuario_id = $_SESSION['usuario_id'];

// ========== INSERÇÃO DE NOVO LANÇAMENTO ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'inserir') {
    try {
        $data = limparPost($_POST['data']);
        $tipo_servico = limparPost($_POST['tipo_servico']);
        $entrada = limparPost($_POST['entrada']);
        $saida = limparPost($_POST['saida']);

        if (empty($data) || empty($tipo_servico) || empty($entrada) || empty($saida)) {
            echo json_encode(['success' => false, 'message' => 'Por favor, preencha todos os campos.']);
            exit;
        }

        $sql = $pdo->prepare("INSERT INTO lancamentos (usuario_id, data, tipo_servico, entrada, saida) VALUES (?, ?, ?, ?, ?)");
        $sql->execute(array($usuario_id, $data, $tipo_servico, $entrada, $saida));

        // Retornar dados do novo lançamento
        $novo_id = $pdo->lastInsertId();
        $horas = calcularHoras($entrada, $saida);

        echo json_encode([
            'success' => true,
            'message' => '✅ Lançamento inserido com sucesso!',
            'lancamento' => [
                'id' => $novo_id,
                'data' => converterDataMySQL($data),
                'data_original' => $data,
                'tipo_servico' => htmlspecialchars($tipo_servico),
                'entrada' => htmlspecialchars($entrada),
                'saida' => htmlspecialchars($saida),
                'horas' => formatarHoras($horas)
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '❌ Erro ao inserir lançamento: ' . $e->getMessage()]);
    }
    exit;
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
            echo json_encode(['success' => false, 'message' => 'Por favor, preencha todos os campos.']);
            exit;
        }

        $sql = $pdo->prepare("UPDATE lancamentos SET data = ?, tipo_servico = ?, entrada = ?, saida = ? WHERE id = ? AND usuario_id = ?");
        $sql->execute(array($data, $tipo_servico, $entrada, $saida, $id, $usuario_id));

        $horas = calcularHoras($entrada, $saida);

        echo json_encode([
            'success' => true,
            'message' => '✅ Lançamento atualizado com sucesso!',
            'lancamento' => [
                'id' => $id,
                'data' => converterDataMySQL($data),
                'data_original' => $data,
                'tipo_servico' => htmlspecialchars($tipo_servico),
                'entrada' => htmlspecialchars($entrada),
                'saida' => htmlspecialchars($saida),
                'horas' => formatarHoras($horas)
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '❌ Erro ao atualizar lançamento: ' . $e->getMessage()]);
    }
    exit;
}

// ========== EXCLUSÃO DE LANÇAMENTO ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'deletar') {
    try {
        $id = limparPost($_POST['id']);

        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        $sql = $pdo->prepare("DELETE FROM lancamentos WHERE id = ? AND usuario_id = ?");
        $sql->execute(array($id, $usuario_id));

        echo json_encode([
            'success' => true,
            'message' => '✅ Lançamento deletado com sucesso!',
            'id' => $id
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '❌ Erro ao deletar lançamento: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
