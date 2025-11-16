<?php
require_once 'config.php';

// Verificar se está logado e se é administrador
if (!verificarLogin() || $_SESSION['nivel_acesso'] != 'administrador') {
    header('Location: login.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';

// Buscar clientes para o select
try {
    $stmt = $pdo->query("SELECT id, nome, email, telefone FROM clientes WHERE status = 'ativo' ORDER BY nome");
    $clientes = $stmt->fetchAll();
} catch (Exception $e) {
    $clientes = [];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $cliente_id = $_POST['cliente_id'];
        $nivel_acesso = $_POST['nivel_acesso'];
        $tipo_cliente = $_POST['tipo_cliente'];
        $senha = $_POST['senha'];
        $confirmar_senha = $_POST['confirmar_senha'];
        $status = $_POST['status'];
        
        // Validações básicas
        if (empty($cliente_id) || empty($nivel_acesso) || empty($senha) || empty($confirmar_senha) || empty($status)) {
            throw new Exception('Por favor, preencha todos os campos obrigatórios.');
        }
        
        // Validar senhas
        if ($senha !== $confirmar_senha) {
            throw new Exception('As senhas não coincidem.');
        }
        
        if (strlen($senha) < 6) {
            throw new Exception('A senha deve ter pelo menos 6 caracteres.');
        }
        
        // Buscar dados do cliente selecionado
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ? AND status = 'ativo'");
        $stmt->execute([$cliente_id]);
        $cliente = $stmt->fetch();
        
        if (!$cliente) {
            throw new Exception('Cliente não encontrado ou inativo.');
        }
        
        // Verificar se já existe usuário com este email
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND deletado_em IS NULL");
        $stmt->execute([$cliente['email']]);
        $usuario_existente = $stmt->fetch();
        
        if ($usuario_existente) {
            throw new Exception('Já existe um usuário com este email.');
        }
        
        // Criar hash da senha
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        
        // Inserir usuário
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (
                nome, email, telefone, nivel_acesso, tipo_cliente, 
                senha, status, data_cadastro
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $cliente['nome'],
            $cliente['email'], 
            $cliente['telefone'],
            $nivel_acesso,
            $tipo_cliente,
            $senha_hash,
            $status
        ]);
        
        if ($result) {
            $mensagem = 'Usuário cadastrado com sucesso!';
            $tipo_mensagem = 'success';
            
            // Limpar campos
            $_POST = array();
            
        } else {
            throw new Exception('Erro ao criar o usuário. Tente novamente.');
        }
        
    } catch (Exception $e) {
        $mensagem = $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Usuário - Portal Cegonheiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: #00bc75;
            --secondary-green: #07a368;
            --sidebar-bg: #f8f9fa;
            --white: #ffffff;
            --text-dark: #2c2c2c;
            --text-light: #6c757d;
            --border-color: #e9ecef;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            --sidebar-width: 280px;
            --border-radius: 12px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: var(--text-dark);
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--white);
            border-right: 1px solid var(--border-color);
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid var(--border-color);
            text-align: center;
        }

        .logo img {
            height: 40px;
            width: auto;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            display: block;
            padding: 12px 20px;
            color: var(--text-light);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .menu-item:hover,
        .menu-item.active {
            color: var(--primary-green);
            background: rgba(0, 188, 117, 0.05);
            border-left-color: var(--primary-green);
        }

        .menu-item i {
            width: 20px;
            margin-right: 12px;
            font-size: 16px;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 24px;
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .breadcrumb-nav {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .form-container {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .form-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            background: var(--white);
        }

        .form-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-body {
            padding: 24px;
        }

        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(0, 188, 117, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .section-title {
            color: var(--text-dark);
            font-weight: 600;
            margin: 25px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary-green);
        }

        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 10px 20px;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary-green);
            border-color: var(--primary-green);
        }

        .btn-primary:hover {
            background: var(--secondary-green);
            border-color: var(--secondary-green);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .alert {
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Client Info Display */
        .client-info {
            background: #f8f9fa;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            margin-top: 12px;
            display: none;
        }

        .client-info.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .client-info h6 {
            color: var(--primary-green);
            margin-bottom: 12px;
            font-weight: 600;
        }

        .client-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .client-detail strong {
            color: var(--text-dark);
        }

        .client-detail span {
            color: var(--text-light);
        }

        /* Info Box */
        .info-box {
            background: #e8f5ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .info-box h6 {
            color: #0066cc;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .info-box p {
            color: #004499;
            margin: 0;
            font-size: 14px;
        }

        /* Password Requirements */
        .password-requirements {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 16px;
            margin-top: 12px;
        }

        .password-requirements h6 {
            color: #856404;
            margin-bottom: 12px;
            font-weight: 600;
            font-size: 14px;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
            color: #856404;
            font-size: 13px;
        }

        .password-requirements li {
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="https://i.ibb.co/tpmsDyMm/img-logo-portal-03.png" alt="Portal Cegonheiro">
            </div>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard_admin.php" class="menu-item">
                <i class="fas fa-th-large"></i>
                Dashboard
            </a>
            <a href="clientes_admin.php" class="menu-item">
                <i class="fas fa-building"></i>
                Clientes
            </a>
            <a href="usuarios_admin.php" class="menu-item">
                <i class="fas fa-users"></i>
                Usuários
            </a>
            <a href="cadastro_cliente.php" class="menu-item">
                <i class="fas fa-user-plus"></i>
                Novo Cliente
            </a>
            <a href="cadastro_usuario.php" class="menu-item active">
                <i class="fas fa-user-cog"></i>
                Novo Usuário
            </a>
            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                Sair
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <div>
                <div class="breadcrumb-nav">
                    <strong>HOME</strong> > Dashboard > Cadastro de Usuário
                </div>
                <h1 class="page-title">Cadastro de Usuário</h1>
            </div>
        </div>

        <div class="form-container">
            <div class="form-header">
                <h2 class="form-title">
                    <i class="fas fa-user-plus"></i>
                    Cadastro de Novo Usuário
                </h2>
            </div>
            
            <div class="form-body">
                <?php if ($mensagem): ?>
                    <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo $mensagem; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="info-box">
                    <h6><i class="fas fa-info-circle me-2"></i>Informações Importantes</h6>
                    <p>Selecione um cliente da lista para criar um usuário. Os dados do cliente (nome, email, telefone) serão automaticamente utilizados para criar a conta de acesso.</p>
                </div>

                <form method="POST" id="usuarioForm">
                    <h4 class="section-title">
                        <i class="fas fa-user"></i> Seleção do Cliente
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="cliente_id" class="form-label">Cliente *</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required onchange="showClientInfo()">
                                <option value="">Selecione um cliente...</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?php echo $cliente['id']; ?>" 
                                            data-nome="<?php echo htmlspecialchars($cliente['nome']); ?>"
                                            data-email="<?php echo htmlspecialchars($cliente['email']); ?>"
                                            data-telefone="<?php echo htmlspecialchars($cliente['telefone']); ?>"
                                            <?php echo (isset($_POST['cliente_id']) && $_POST['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cliente['nome']) . ' - ' . htmlspecialchars($cliente['email']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <div class="client-info" id="clientInfo">
                                <h6><i class="fas fa-user-circle me-2"></i>Dados do Cliente Selecionado</h6>
                                <div class="client-detail">
                                    <strong>Nome:</strong>
                                    <span id="clientNome">-</span>
                                </div>
                                <div class="client-detail">
                                    <strong>Email:</strong>
                                    <span id="clientEmail">-</span>
                                </div>
                                <div class="client-detail">
                                    <strong>Telefone:</strong>
                                    <span id="clientTelefone">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4 class="section-title">
                        <i class="fas fa-shield-alt"></i> Configurações de Acesso
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nivel_acesso" class="form-label">Nível de Acesso *</label>
                            <select class="form-select" id="nivel_acesso" name="nivel_acesso" required>
                                <option value="">Selecione o nível...</option>
                                <option value="administrador" <?php echo (isset($_POST['nivel_acesso']) && $_POST['nivel_acesso'] == 'administrador') ? 'selected' : ''; ?>>
                                    👑 Administrador
                                </option>
                                <option value="funcionario" <?php echo (isset($_POST['nivel_acesso']) && $_POST['nivel_acesso'] == 'funcionario') ? 'selected' : ''; ?>>
                                    👨‍💼 Funcionário
                                </option>
                                <option value="cliente" <?php echo (isset($_POST['nivel_acesso']) && $_POST['nivel_acesso'] == 'cliente') ? 'selected' : ''; ?>>
                                    👤 Cliente
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tipo_cliente" class="form-label">Tipo de Cliente *</label>
                            <select class="form-select" id="tipo_cliente" name="tipo_cliente" required>
                                <option value="pj" <?php echo (isset($_POST['tipo_cliente']) && $_POST['tipo_cliente'] == 'pj') ? 'selected' : ''; ?>>
                                    🏢 Pessoa Jurídica (PJ)
                                </option>
                                <option value="pf" <?php echo (isset($_POST['tipo_cliente']) && $_POST['tipo_cliente'] == 'pf') ? 'selected' : ''; ?>>
                                    👤 Pessoa Física (PF)
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="ativo" <?php echo (isset($_POST['status']) && $_POST['status'] == 'ativo') ? 'selected' : ''; ?>>
                                    ✅ Ativo
                                </option>
                                <option value="inativo" <?php echo (isset($_POST['status']) && $_POST['status'] == 'inativo') ? 'selected' : ''; ?>>
                                    ⏸️ Inativo
                                </option>
                                <option value="suspenso" <?php echo (isset($_POST['status']) && $_POST['status'] == 'suspenso') ? 'selected' : ''; ?>>
                                    ⏯️ Suspenso
                                </option>
                                <option value="bloqueado" <?php echo (isset($_POST['status']) && $_POST['status'] == 'bloqueado') ? 'selected' : ''; ?>>
                                    🚫 Bloqueado
                                </option>
                            </select>
                        </div>
                    </div>

                    <h4 class="section-title">
                        <i class="fas fa-lock"></i> Senha de Acesso
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="senha" class="form-label">Senha *</label>
                            <input type="password" class="form-control" name="senha" id="senha" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirmar_senha" class="form-label">Confirmar Senha *</label>
                            <input type="password" class="form-control" name="confirmar_senha" id="confirmar_senha" required>
                        </div>
                    </div>

                    <div class="password-requirements">
                        <h6><i class="fas fa-info-circle me-2"></i>Requisitos da Senha</h6>
                        <ul>
                            <li>Mínimo de 6 caracteres</li>
                            <li>Recomendamos usar letras maiúsculas e minúsculas</li>
                            <li>Inclua números e símbolos para maior segurança</li>
                            <li>Evite senhas muito simples ou previsíveis</li>
                        </ul>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-user-plus"></i> Cadastrar Usuário
                        </button>
                        <a href="dashboard_admin.php" class="btn btn-outline-secondary btn-lg ms-3">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showClientInfo() {
            const select = document.getElementById('cliente_id');
            const clientInfo = document.getElementById('clientInfo');
            const selectedOption = select.options[select.selectedIndex];

            if (selectedOption.value) {
                document.getElementById('clientNome').textContent = selectedOption.dataset.nome;
                document.getElementById('clientEmail').textContent = selectedOption.dataset.email;
                document.getElementById('clientTelefone').textContent = selectedOption.dataset.telefone || 'Não informado';
                clientInfo.classList.add('show');
            } else {
                clientInfo.classList.remove('show');
            }
        }

        // Form validation
        document.getElementById('usuarioForm').addEventListener('submit', function(e) {
            const senha = document.getElementById('senha').value;
            const confirmarSenha = document.getElementById('confirmar_senha').value;
            
            if (senha !== confirmarSenha) {
                e.preventDefault();
                alert('As senhas não coincidem!');
                return false;
            }
            
            if (senha.length < 6) {
                e.preventDefault();
                alert('A senha deve ter pelo menos 6 caracteres!');
                return false;
            }
        });

        // Password strength indicator
        document.getElementById('senha').addEventListener('input', function() {
            const senha = this.value;
            
            if (senha.length >= 6) {
                this.style.borderColor = '#00bc75';
            } else {
                this.style.borderColor = '#dc3545';
            }
        });

        // Confirm password validation
        document.getElementById('confirmar_senha').addEventListener('input', function() {
            const senha = document.getElementById('senha').value;
            const confirmarSenha = this.value;
            
            if (confirmarSenha === senha && senha.length > 0) {
                this.style.borderColor = '#00bc75';
            } else if (confirmarSenha.length > 0) {
                this.style.borderColor = '#dc3545';
            }
        });
    </script>
</body>
</html>