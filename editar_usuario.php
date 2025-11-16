<?php
require_once 'config.php';

if (!verificarLogin() || $_SESSION['nivel_acesso'] != 'administrador') {
    header('Location: login.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';
$usuario_id = $_GET['id'] ?? $_POST['id'] ?? '';

if (empty($usuario_id)) {
    header('Location: usuarios_admin.php');
    exit;
}

// Buscar dados do usuário
try {
    // Primeiro, verificar se a tabela clientes existe e quais colunas tem
    $stmt = $pdo->query("SHOW TABLES LIKE 'clientes'");
    $clientes_table_exists = $stmt->rowCount() > 0;
    
    if ($clientes_table_exists) {
        // Verificar quais colunas existem na tabela clientes
        $stmt = $pdo->query("SHOW COLUMNS FROM clientes");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $empresa_col = in_array('empresa', $columns) ? 'c.empresa' : 'NULL as empresa';
        $cnpj_col = in_array('cnpj', $columns) ? 'c.cnpj' : 'NULL as cnpj';
        $endereco_col = in_array('endereco', $columns) ? 'c.endereco' : 'NULL as endereco';
        $cidade_col = in_array('cidade', $columns) ? 'c.cidade' : 'NULL as cidade';
        $estado_col = in_array('estado', $columns) ? 'c.estado' : 'NULL as estado';
        $cep_col = in_array('cep', $columns) ? 'c.cep' : 'NULL as cep';
        
        $sql = "
            SELECT 
                u.*,
                {$empresa_col},
                {$cnpj_col},
                {$endereco_col},
                {$cidade_col},
                {$estado_col},
                {$cep_col}
            FROM usuarios u
            LEFT JOIN clientes c ON u.id = c.user_id
            WHERE u.id = ?
        ";
    } else {
        $sql = "
            SELECT 
                u.*,
                NULL as empresa,
                NULL as cnpj,
                NULL as endereco,
                NULL as cidade,
                NULL as estado,
                NULL as cep
            FROM usuarios u
            WHERE u.id = ?
        ";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        header('Location: usuarios_admin.php');
        exit;
    }
} catch (Exception $e) {
    // Fallback: buscar apenas dados do usuário
    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            header('Location: usuarios_admin.php');
            exit;
        }
        
        // Adicionar campos vazios
        $usuario['empresa'] = '';
        $usuario['cnpj'] = '';
        $usuario['endereco'] = '';
        $usuario['cidade'] = '';
        $usuario['estado'] = '';
        $usuario['cep'] = '';
        
    } catch (Exception $e2) {
        die('Erro ao buscar usuário: ' . $e2->getMessage());
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar'])) {
    try {
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $telefone = trim($_POST['telefone']);
        $nivel_acesso = $_POST['nivel_acesso'];
        $status = $_POST['status'];
        $tipo_cliente = $_POST['tipo_cliente'];
        $bio = trim($_POST['bio']);
        
        // Dados da empresa (se for PJ)
        $empresa = trim($_POST['empresa'] ?? '');
        $cnpj = trim($_POST['cnpj'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $estado = trim($_POST['estado'] ?? '');
        $cep = trim($_POST['cep'] ?? '');
        
        // Validações básicas
        if (empty($nome) || empty($email)) {
            throw new Exception('Nome e email são obrigatórios');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email inválido');
        }
        
        // Verificar se email já existe (exceto o próprio usuário)
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $stmt->execute([$email, $usuario_id]);
        if ($stmt->fetch()) {
            throw new Exception('Este email já está sendo usado por outro usuário');
        }
        
        // Atualizar usuário
        $stmt = $pdo->prepare("
            UPDATE usuarios SET 
                nome = ?, 
                email = ?, 
                telefone = ?, 
                nivel_acesso = ?, 
                status = ?, 
                tipo_cliente = ?, 
                bio = ?
            WHERE id = ?
        ");
        $stmt->execute([$nome, $email, $telefone, $nivel_acesso, $status, $tipo_cliente, $bio, $usuario_id]);
        
        // Atualizar/inserir dados da empresa se for PJ e tabela existir
        if ($tipo_cliente == 'pj' && $clientes_table_exists) {
            try {
                // Verificar se já existe registro na tabela clientes
                $stmt = $pdo->prepare("SELECT id FROM clientes WHERE user_id = ?");
                $stmt->execute([$usuario_id]);
                $cliente_existe = $stmt->fetch();
                
                if ($cliente_existe) {
                    // Atualizar
                    $stmt = $pdo->prepare("
                        UPDATE clientes SET 
                            empresa = ?, 
                            cnpj = ?, 
                            endereco = ?, 
                            cidade = ?, 
                            estado = ?, 
                            cep = ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$empresa, $cnpj, $endereco, $cidade, $estado, $cep, $usuario_id]);
                } else {
                    // Inserir
                    $stmt = $pdo->prepare("
                        INSERT INTO clientes (user_id, empresa, cnpj, endereco, cidade, estado, cep, data_cadastro)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$usuario_id, $empresa, $cnpj, $endereco, $cidade, $estado, $cep]);
                }
            } catch (Exception $e) {
                // Se der erro na tabela clientes, continuar sem ela
                error_log('Erro ao atualizar dados da empresa: ' . $e->getMessage());
            }
        }
        
        $mensagem = 'Usuário atualizado com sucesso!';
        $tipo_mensagem = 'success';
        
        // Recarregar dados
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();
        
    } catch (Exception $e) {
        $mensagem = 'Erro: ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// Processar alteração de senha
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['alterar_senha'])) {
    try {
        $nova_senha = $_POST['nova_senha'];
        $confirmar_senha = $_POST['confirmar_senha'];
        
        if (empty($nova_senha) || empty($confirmar_senha)) {
            throw new Exception('Preencha todos os campos de senha');
        }
        
        if (strlen($nova_senha) < 6) {
            throw new Exception('A senha deve ter pelo menos 6 caracteres');
        }
        
        if ($nova_senha !== $confirmar_senha) {
            throw new Exception('As senhas não coincidem');
        }
        
        $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $stmt->execute([$senha_hash, $usuario_id]);
        
        $mensagem = 'Senha alterada com sucesso!';
        $tipo_mensagem = 'success';
        
    } catch (Exception $e) {
        $mensagem = 'Erro: ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuário - Portal Cegonheiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-green: #00bc75;
            --secondary-green: #07a368;
            --white: #ffffff;
            --text-dark: #2c2c2c;
            --text-light: #6c757d;
            --border-color: #e9ecef;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            --border-radius: 12px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            color: var(--text-dark);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }

        .page-header {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .breadcrumb-nav {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .breadcrumb-nav a {
            color: var(--text-light);
            text-decoration: none;
        }

        .breadcrumb-nav a:hover {
            color: var(--primary-green);
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            background: var(--white);
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 24px;
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 12px 16px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(0, 188, 117, 0.25);
        }

        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 12px 24px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary-green);
            border-color: var(--primary-green);
        }

        .btn-primary:hover {
            background: var(--secondary-green);
            border-color: var(--secondary-green);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 188, 117, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            border-color: #6c757d;
        }

        .btn-warning {
            background: #ffc107;
            border-color: #ffc107;
            color: #000;
        }

        .btn-outline-secondary {
            color: #6c757d;
            border-color: #6c757d;
        }

        .btn-outline-secondary:hover {
            background: #6c757d;
            border-color: #6c757d;
        }

        .alert {
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-green);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 24px;
            margin-bottom: 16px;
        }

        .user-info {
            text-align: center;
            margin-bottom: 24px;
        }

        .user-info h4 {
            margin: 8px 0 4px 0;
            color: var(--text-dark);
        }

        .user-info .text-muted {
            font-size: 14px;
        }

        .form-section {
            margin-bottom: 32px;
        }

        .form-section h5 {
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border-color);
        }

        .row {
            margin-bottom: 16px;
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

        .actions-bar {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 20px 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
        }

        .actions-left {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .actions-right {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .actions-left,
            .actions-right {
                justify-content: center;
            }
            
            .btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="breadcrumb-nav">
                <a href="dashboard_admin.php">Dashboard</a> > 
                <a href="usuarios_admin.php">Usuários</a> > 
                <strong>Editar Usuário</strong>
            </div>
            <h1 class="page-title">
                <i class="fas fa-user-edit"></i>
                Editar Usuário #<?php echo $usuario['id']; ?>
            </h1>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo $mensagem; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Debug Info -->
        <div class="debug-info">
            <strong>🔧 Debug Info:</strong><br>
            <small>
                Tabela clientes existe: <?php echo $clientes_table_exists ? '✅ Sim' : '❌ Não'; ?><br>
                Usuário ID: <?php echo $usuario['id']; ?><br>
                Tipo cliente: <?php echo $usuario['tipo_cliente'] ?? 'Não definido'; ?><br>
                Empresa: <?php echo $usuario['empresa'] ?? 'Vazio'; ?>
            </small>
        </div>

        <div class="row">
            <div class="col-lg-4">
                <!-- Informações do Usuário -->
                <div class="card">
                    <div class="card-body">
                        <div class="user-info">
                            <div class="user-avatar mx-auto">
                                <?php echo strtoupper(substr($usuario['nome'], 0, 2)); ?>
                            </div>
                            <h4><?php echo htmlspecialchars($usuario['nome']); ?></h4>
                            <p class="text-muted"><?php echo htmlspecialchars($usuario['email']); ?></p>
                            <div class="d-flex justify-content-center gap-2 mb-3">
                                <span class="badge bg-<?php echo $usuario['status'] == 'ativo' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($usuario['status']); ?>
                                </span>
                                <span class="badge bg-primary">
                                    <?php echo ucfirst($usuario['nivel_acesso']); ?>
                                </span>
                            </div>
                            <small class="text-muted">
                                Cadastrado em <?php echo date('d/m/Y', strtotime($usuario['data_cadastro'])); ?>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Alterar Senha -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-key"></i>
                            Alterar Senha
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                            
                            <div class="mb-3">
                                <label for="nova_senha" class="form-label">Nova Senha</label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" id="nova_senha" name="nova_senha" 
                                           placeholder="Digite a nova senha" required minlength="6">
                                    <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y" 
                                            onclick="togglePassword('nova_senha')">
                                        <i class="fas fa-eye" id="nova_senha_icon"></i>
                                    </button>
                                </div>
                                <div class="password-strength" id="strength-bar"></div>
                                <div class="form-text" id="strength-text">
                                    Mínimo 6 caracteres
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirmar_senha" class="form-label">Confirmar Senha</label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" 
                                           placeholder="Confirme a nova senha" required>
                                    <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y" 
                                            onclick="togglePassword('confirmar_senha')">
                                        <i class="fas fa-eye" id="confirmar_senha_icon"></i>
                                    </button>
                                </div>
                                <div class="form-text" id="match-text"></div>
                            </div>
                            
                            <button type="submit" name="alterar_senha" class="btn btn-warning w-100" id="senhaBtn" disabled>
                                <i class="fas fa-key"></i>
                                Alterar Senha
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <!-- Dados do Usuário -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-user"></i>
                            Dados do Usuário
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="userForm">
                            <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                            
                            <!-- Dados Pessoais -->
                            <div class="form-section">
                                <h5><i class="fas fa-user"></i> Dados Pessoais</h5>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="nome" class="form-label">Nome Completo *</label>
                                        <input type="text" class="form-control" id="nome" name="nome" 
                                               value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="telefone" class="form-label">Telefone</label>
                                        <input type="text" class="form-control" id="telefone" name="telefone" 
                                               value="<?php echo htmlspecialchars($usuario['telefone']); ?>" 
                                               placeholder="(11) 99999-9999">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="tipo_cliente" class="form-label">Tipo de Cliente</label>
                                        <select class="form-select" id="tipo_cliente" name="tipo_cliente" onchange="toggleEmpresaFields()">
                                            <option value="pf" <?php echo ($usuario['tipo_cliente'] ?? 'pf') == 'pf' ? 'selected' : ''; ?>>Pessoa Física</option>
                                            <option value="pj" <?php echo ($usuario['tipo_cliente'] ?? 'pf') == 'pj' ? 'selected' : ''; ?>>Pessoa Jurídica</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="bio" class="form-label">Biografia/Observações</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="3" 
                                              placeholder="Informações adicionais sobre o usuário"><?php echo htmlspecialchars($usuario['bio'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <!-- Dados da Empresa -->
                            <?php if ($clientes_table_exists): ?>
                            <div class="form-section" id="empresaSection" style="display: <?php echo ($usuario['tipo_cliente'] ?? 'pf') == 'pj' ? 'block' : 'none'; ?>">
                                <h5><i class="fas fa-building"></i> Dados da Empresa</h5>
                                
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="empresa" class="form-label">Nome da Empresa</label>
                                        <input type="text" class="form-control" id="empresa" name="empresa" 
                                               value="<?php echo htmlspecialchars($usuario['empresa'] ?? ''); ?>" 
                                               placeholder="Razão social da empresa">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="cnpj" class="form-label">CNPJ</label>
                                        <input type="text" class="form-control" id="cnpj" name="cnpj" 
                                               value="<?php echo htmlspecialchars($usuario['cnpj'] ?? ''); ?>" 
                                               placeholder="00.000.000/0000-00">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="endereco" class="form-label">Endereço</label>
                                    <input type="text" class="form-control" id="endereco" name="endereco" 
                                           value="<?php echo htmlspecialchars($usuario['endereco'] ?? ''); ?>" 
                                           placeholder="Rua, número, bairro">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="cidade" class="form-label">Cidade</label>
                                        <input type="text" class="form-control" id="cidade" name="cidade" 
                                               value="<?php echo htmlspecialchars($usuario['cidade'] ?? ''); ?>" 
                                               placeholder="Nome da cidade">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="estado" class="form-label">Estado</label>
                                        <select class="form-select" id="estado" name="estado">
                                            <option value="">Selecione...</option>
                                            <option value="SP" <?php echo ($usuario['estado'] ?? '') == 'SP' ? 'selected' : ''; ?>>São Paulo</option>
                                            <option value="RJ" <?php echo ($usuario['estado'] ?? '') == 'RJ' ? 'selected' : ''; ?>>Rio de Janeiro</option>
                                            <option value="MG" <?php echo ($usuario['estado'] ?? '') == 'MG' ? 'selected' : ''; ?>>Minas Gerais</option>
                                            <option value="RS" <?php echo ($usuario['estado'] ?? '') == 'RS' ? 'selected' : ''; ?>>Rio Grande do Sul</option>
                                            <option value="PR" <?php echo ($usuario['estado'] ?? '') == 'PR' ? 'selected' : ''; ?>>Paraná</option>
                                            <option value="SC" <?php echo ($usuario['estado'] ?? '') == 'SC' ? 'selected' : ''; ?>>Santa Catarina</option>
                                            <option value="BA" <?php echo ($usuario['estado'] ?? '') == 'BA' ? 'selected' : ''; ?>>Bahia</option>
                                            <option value="GO" <?php echo ($usuario['estado'] ?? '') == 'GO' ? 'selected' : ''; ?>>Goiás</option>
                                            <option value="ES" <?php echo ($usuario['estado'] ?? '') == 'ES' ? 'selected' : ''; ?>>Espírito Santo</option>
                                            <option value="DF" <?php echo ($usuario['estado'] ?? '') == 'DF' ? 'selected' : ''; ?>>Distrito Federal</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="cep" class="form-label">CEP</label>
                                        <input type="text" class="form-control" id="cep" name="cep" 
                                               value="<?php echo htmlspecialchars($usuario['cep'] ?? ''); ?>" 
                                               placeholder="00000-000">
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Aviso:</strong> Tabela de clientes não encontrada. Execute o SQL fornecido para criar a estrutura completa.
                            </div>
                            <?php endif; ?>

                            <!-- Configurações de Acesso -->
                            <div class="form-section">
                                <h5><i class="fas fa-cog"></i> Configurações de Acesso</h5>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="nivel_acesso" class="form-label">Nível de Acesso</label>
                                        <select class="form-select" id="nivel_acesso" name="nivel_acesso" required>
                                            <option value="cliente" <?php echo $usuario['nivel_acesso'] == 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                                            <option value="funcionario" <?php echo $usuario['nivel_acesso'] == 'funcionario' ? 'selected' : ''; ?>>Funcionário</option>
                                            <option value="administrador" <?php echo $usuario['nivel_acesso'] == 'administrador' ? 'selected' : ''; ?>>Administrador</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="ativo" <?php echo $usuario['status'] == 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                            <option value="inativo" <?php echo $usuario['status'] == 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                                            <option value="suspenso" <?php echo $usuario['status'] == 'suspenso' ? 'selected' : ''; ?>>Suspenso</option>
                                            <option value="bloqueado" <?php echo $usuario['status'] == 'bloqueado' ? 'selected' : ''; ?>>Bloqueado</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barra de Ações -->
        <div class="actions-bar">
            <div class="actions-left">
                <button type="submit" form="userForm" name="atualizar" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Salvar Alterações
                </button>
                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                    <i class="fas fa-undo"></i>
                    Desfazer
                </button>
            </div>
            <div class="actions-right">
                <a href="usuarios_admin.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Voltar
                </a>
                <button type="button" class="btn btn-warning" onclick="resetarSenhaRapido()">
                    <i class="fas fa-key"></i>
                    Reset Senha (123456)
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle campos da empresa
        function toggleEmpresaFields() {
            const tipo = document.getElementById('tipo_cliente').value;
            const empresaSection = document.getElementById('empresaSection');
            if (empresaSection) {
                empresaSection.style.display = tipo === 'pj' ? 'block' : 'none';
            }
        }

        // Toggle mostrar/ocultar senha
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
            const senhaBtn = document.getElementById('senhaBtn');
            
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
            senhaBtn.disabled = !isValid;
        }

        // Event listeners
        document.getElementById('nova_senha').addEventListener('input', function() {
            const strength = checkPasswordStrength(this.value);
            updateStrengthIndicator(strength);
            checkPasswordMatch();
        });

        document.getElementById('confirmar_senha').addEventListener('input', checkPasswordMatch);

        // Resetar formulário
        function resetForm() {
            if (confirm('Tem certeza que deseja desfazer todas as alterações?')) {
                location.reload();
            }
        }

        // Reset senha rápido
        function resetarSenhaRapido() {
            if (confirm('Tem certeza que deseja resetar a senha para 123456?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                    <input type="hidden" name="nova_senha" value="123456">
                    <input type="hidden" name="confirmar_senha" value="123456">
                    <input type="hidden" name="alterar_senha" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Máscaras
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{4,5})(\d{4})$/, '$1-$2');
                e.target.value = value;
            }
        });

        const cnpjField = document.getElementById('cnpj');
        if (cnpjField) {
            cnpjField.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 14) {
                    value = value.replace(/(\d{2})(\d)/, '$1.$2');
                    value = value.replace(/(\d{3})(\d)/, '$1.$2');
                    value = value.replace(/(\d{3})(\d)/, '$1/$2');
                    value = value.replace(/(\d{4})(\d)/, '$1-$2');
                    e.target.value = value;
                }
            });
        }

        const cepField = document.getElementById('cep');
        if (cepField) {
            cepField.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 8) {
                    value = value.replace(/(\d{5})(\d)/, '$1-$2');
                    e.target.value = value;
                }
            });
        }

        console.log('Página de edição carregada');
    </script>
</body>
</html>