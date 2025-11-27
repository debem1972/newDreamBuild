<?php
/**
 * Funções Utilitárias
 * DreamBuild - Gerenciamento de Horas de Trabalho
 */

/**
 * Limpar dados POST para evitar SQL Injection
 */
function limparPost($dados) {
    return htmlspecialchars(trim($dados), ENT_QUOTES, 'UTF-8');
}

/**
 * Validar sessão do usuário
 */
function validarSessao() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: /DreamBuild/public/login.php');
        exit;
    }
}

/**
 * Calcular horas trabalhadas entre dois horários
 */
function calcularHoras($horaInicio, $horaFim) {
    $inicio = new DateTime($horaInicio);
    $fim = new DateTime($horaFim);
    
    if ($fim < $inicio) {
        $fim->add(new DateInterval('P1D'));
    }
    
    $intervalo = $fim->diff($inicio);
    return $intervalo->h + ($intervalo->i / 60);
}

/**
 * Formatar horas em formato HH:MM
 */
function formatarHoras($horas) {
    $h = floor($horas);
    $m = round(($horas - $h) * 60);
    return sprintf('%02d:%02d', $h, $m);
}

/**
 * Converter data de formato brasileiro para MySQL
 */
function converterDataBR($data) {
    $dataConvertida = DateTime::createFromFormat('d/m/Y', $data);
    if ($dataConvertida) {
        return $dataConvertida->format('Y-m-d');
    }
    return null;
}

/**
 * Converter data de formato MySQL para brasileiro
 */
function converterDataMySQL($data) {
    $dataConvertida = DateTime::createFromFormat('Y-m-d', $data);
    if ($dataConvertida) {
        return $dataConvertida->format('d/m/Y');
    }
    return null;
}

/**
 * Obter tipos de serviço disponíveis
 */
function obterTiposServico() {
    return array(
        'garçom' => 'Garçom',
        'forno' => 'Forno',
        'massa' => 'Massa'
    );
}
?>
