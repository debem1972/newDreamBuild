<?php
session_start();

// Se já está logado, redireciona para o dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/functions.php';

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = limparPost($_POST['nome']);
    $email = limparPost($_POST['email']);
    $senha = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    // Validações
    if (empty($nome) || empty($email) || empty($senha) || empty($confirmar_senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== $confirmar_senha) {
        $erro = 'As senhas não coincidem.';
    } else {
        try {
            // Verificar se o email já existe
            $sql = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $sql->execute(array($email));
            
            if ($sql->rowCount() > 0) {
                $erro = 'Este email já está cadastrado.';
            } else {
                // Inserir novo usuário
                $senha_hash = password_hash($senha, PASSWORD_BCRYPT);
                $sql = $pdo->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
                $sql->execute(array($nome, $email, $senha_hash));
                
                $sucesso = 'Conta criada com sucesso! Você pode fazer login agora.';
            }
        } catch (Exception $e) {
            $erro = 'Erro ao criar conta: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DreamBuild - Criar Conta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .signup-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }

        .signup-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .signup-header h1 {
            color: #667eea;
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .signup-header p {
            color: #666;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-signup {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
        }

        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .alert {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 5px;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 0.95rem;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="signup-header">
            <h1>🏗️ DreamBuild</h1>
            <p>Criar Nova Conta</p>
        </div>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-error"><?php echo $erro; ?></div>
        <?php endif; ?>

        <?php if (!empty($sucesso)): ?>
            <div class="alert alert-success">
                <?php echo $sucesso; ?>
                <br><a href="login.php" style="color: #155724; text-decoration: underline;">Ir para login</a>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="nome">Nome Completo</label>
                <input type="text" id="nome" name="nome" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required>
            </div>

            <div class="form-group">
                <label for="confirmar_senha">Confirmar Senha</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" required>
            </div>

            <button type="submit" class="btn-signup">Criar Conta</button>
        </form>

        <div class="login-link">
            Já tem conta? <a href="login.php">Fazer login</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
