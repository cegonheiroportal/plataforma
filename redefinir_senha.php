<?php
require_once 'config.php';

$mensagem = '';
$tipo_mensagem = '';
$token_valido = false;
$usuario = null;

// Verificar token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        // Buscar token válido
        $stmt = $pdo->prepare("
            SELECT pr.*, u.nome, u.email 
            FROM password_resets pr
            JOIN usuarios u ON pr.user_id = u.id
            WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used_at IS NULL
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        
        if ($reset) {
            $token_valido = true;
            $usuario = $reset;
        } else {
            $mensagem = 'Link inválido ou expirado. Solicite uma nova recuperação.';
            $tipo_mensagem = 'danger';
        }
        
    } catch (Exception $e) {
        error_log('Erro ao verificar token: ' . $e->getMessage());
        $mensagem = 'Erro interno. Tente novamente.';
        $tipo_mensagem = 'danger';
    }
} else {
    $mensagem = 'Token não fornecido.';
    $tipo_mensagem = 'danger';
}

// Processar nova senha
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $token_valido) {
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    
    if (empty($nova_senha) || empty($confirmar_senha)) {
        $mensagem = 'Preencha todos os campos.';
        $tipo_mensagem = 'danger';
    } elseif (strlen($nova_senha) < 6) {
        $mensagem = 'A senha deve ter pelo menos 6 caracteres.';
        $tipo_mensagem = 'danger';
    } elseif ($nova_senha !== $confirmar_senha) {
        $mensagem = 'As senhas não coincidem.';
        $tipo_mensagem = 'danger';
    } else {
        try {
            // Atualizar senha
            $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
            $stmt->execute([$senha_hash, $usuario['user_id']]);
            
            // Marcar token como usado
            $stmt = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE token = ?");
            $stmt->execute([$token]);
            
            // Log de segurança
            error_log("Senha redefinida para usuário ID: " . $usuario['user_id'] . " - Email: " . $usuario['email']);
            
            $mensagem = 'Senha redefinida com sucesso! Você já pode fazer login.';
            $tipo_mensagem = 'success';
            $token_valido = false; // Impedir nova submissão
            
        } catch (Exception $e) {
            error_log('Erro ao redefinir senha: ' . $e->getMessage());
            $mensagem = 'Erro interno. Tente novamente.';
            $tipo_mensagem = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - Portal Cegonheiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        }
        
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 8px;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #dc3545; }
        .strength-medium { background: #ffc107; }
        .strength-strong { background: #28a745; }
        
        .alert {
            border-radius: 12px;
            border: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="fas fa-shield-alt fa-3x mb-3"></i>
                <h2 class="mb-0">Redefinir Senha</h2>
                <p class="mb-0 mt-2 opacity-75">Crie uma nova senha segura</p>
            </div>
            
            <div class="login-body">
                <?php if ($mensagem): ?>
                    <div class="alert alert-<?php echo $tipo_mensagem; ?> mb-4">
                        <i class="fas fa-<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo $mensagem; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($token_valido): ?>
                    <div class="mb-4 p-3 bg-light rounded">
                        <i class="fas fa-user me-2"></i>
                        <strong>Redefinindo senha para:</strong><br>
                        <span class="text-muted"><?php echo htmlspecialchars($usuario['nome']); ?></span><br>
                        <span class="text-muted"><?php echo htmlspecialchars($usuario['email']); ?></span>
                    </div>
                    
                    <form method="POST" id="resetForm">
                        <div class="mb-3">
                            <label for="nova_senha" class="form-label">
                                <i class="fas fa-lock me-2"></i>Nova Senha
                            </label>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="nova_senha" name="nova_senha" 
                                       placeholder="Digite sua nova senha" required minlength="6">
                                <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y" 
                                        onclick="togglePassword('nova_senha')">
                                    <i class="fas fa-eye" id="nova_senha_icon"></i>
                                </button>
                            </div>
                            <div class="password-strength" id="strength-bar"></div>
                            <div class="form-text" id="strength-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Mínimo 6 caracteres
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirmar_senha" class="form-label">
                                <i class="fas fa-lock me-2"></i>Confirmar Nova Senha
                            </label>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" 
                                       placeholder="Confirme sua nova senha" required>
                                <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y" 
                                        onclick="togglePassword('confirmar_senha')">
                                    <i class="fas fa-eye" id="confirmar_senha_icon"></i>
                                </button>
                            </div>
                            <div class="form-text" id="match-text"></div>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100 mb-4" id="submitBtn" disabled>
                            <i class="fas fa-check me-2"></i>Redefinir Senha
                        </button>
                    </form>
                <?php elseif ($tipo_mensagem == 'success'): ?>
                    <div class="text-center">
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Fazer Login
                        </a>
                    </div>
                <?php endif; ?>
                
                <div class="text-center">
                    <a href="login.php" class="text-muted text-decoration-none">
                        <i class="fas fa-arrow-left me-2"></i>Voltar ao Login
                    </a>
                </div>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <h6 class="text-muted mb-3">Dicas de Segurança</h6>
                    <div class="text-start">
                        <small class="text-muted">
                            <i class="fas fa-check text-success me-2"></i>Use pelo menos 8 caracteres<br>
                            <i class="fas fa-check text-success me-2"></i>Combine letras, números e símbolos<br>
                            <i class="fas fa-check text-success me-2"></i>Não use informações pessoais<br>
                            <i class="fas fa-check text-success me-2"></i>Não compartilhe sua senha
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Função para mostrar/ocultar senha
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '_icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
        
        // Verificar força da senha
        function checkPasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            return strength;
        }
        
        // Atualizar indicador de força
        function updateStrengthIndicator(strength) {
            const bar = document.getElementById('strength-bar');
            const text = document.getElementById('strength-text');
            
            if (strength <= 2) {
                bar.className = 'password-strength strength-weak';
                bar.style.width = '33%';
                text.innerHTML = '<i class="fas fa-exclamation-triangle text-danger me-1"></i>Senha fraca';
            } else if (strength <= 4) {
                bar.className = 'password-strength strength-medium';
                bar.style.width = '66%';
                text.innerHTML = '<i class="fas fa-shield-alt text-warning me-1"></i>Senha média';
            } else {
                bar.className = 'password-strength strength-strong';
                bar.style.width = '100%';
                text.innerHTML = '<i class="fas fa-shield-alt text-success me-1"></i>Senha forte';
            }
        }
        
        // Verificar se senhas coincidem
        function checkPasswordMatch() {
            const password = document.getElementById('nova_senha').value;
            const confirm = document.getElementById('confirmar_senha').value;
            const matchText = document.getElementById('match-text');
            const submitBtn = document.getElementById('submitBtn');
            
            if (confirm.length > 0) {
                if (password === confirm) {
                    matchText.innerHTML = '<i class="fas fa-check text-success me-1"></i>Senhas coincidem';
                    matchText.className = 'form-text text-success';
                } else {
                    matchText.innerHTML = '<i class="fas fa-times text-danger me-1"></i>Senhas não coincidem';
                    matchText.className = 'form-text text-danger';
                }
            } else {
                matchText.innerHTML = '';
            }
            
            // Habilitar botão apenas se senhas coincidem e são válidas
            const isValid = password.length >= 6 && password === confirm && confirm.length > 0;
            submitBtn.disabled = !isValid;
        }
        
        // Event listeners
        document.getElementById('nova_senha').addEventListener('input', function() {
            const strength = checkPasswordStrength(this.value);
            updateStrengthIndicator(strength);
            checkPasswordMatch();
        });
        
        document.getElementById('confirmar_senha').addEventListener('input', checkPasswordMatch);
        
        // Auto-focus
        document.getElementById('nova_senha')?.focus();
    </script>
</body>
</html>