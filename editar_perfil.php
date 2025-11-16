<?php
require_once 'config.php';

if (!verificarLogin() || $_SESSION['nivel_acesso'] != 'cliente') {
    header('Location: login.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';

// Buscar dados do usuário logado
$stmt = $pdo->prepare("SELECT u.*, c.* FROM usuarios u 
                       LEFT JOIN clientes c ON u.email = c.email 
                       WHERE u.id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    try {
        if ($acao == 'atualizar_dados') {
            $nome = trim($_POST['nome']);
            $telefone = preg_replace('/[^0-9]/', '', $_POST['telefone']);
            $bio = trim($_POST['bio']);
            
            // Validações básicas
            if (empty($nome)) {
                throw new Exception('Nome é obrigatório.');
            }
            
            // Atualizar dados do usuário
            $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, telefone = ?, bio = ? WHERE id = ?");
            $stmt->execute([$nome, $telefone, $bio, $_SESSION['usuario_id']]);
            
            // Se existe cliente relacionado, atualizar também
            if ($usuario['nome']) {
                $stmt = $pdo->prepare("UPDATE clientes SET nome = ?, telefone = ? WHERE email = ?");
                $stmt->execute([$nome, $telefone, $usuario['email']]);
            }
            
            $mensagem = '✅ Dados atualizados com sucesso!';
            $tipo_mensagem = 'success';
            
            // Atualizar sessão
            $_SESSION['nome'] = $nome;
            
            // Recarregar dados
            $stmt = $pdo->prepare("SELECT u.*, c.* FROM usuarios u 
                                   LEFT JOIN clientes c ON u.email = c.email 
                                   WHERE u.id = ?");
            $stmt->execute([$_SESSION['usuario_id']]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } elseif ($acao == 'alterar_senha') {
            $senha_atual = $_POST['senha_atual'];
            $nova_senha = $_POST['nova_senha'];
            $confirmar_senha = $_POST['confirmar_senha'];
            
            // Validações
            if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {
                throw new Exception('Todos os campos de senha são obrigatórios.');
            }
            
            if (!password_verify($senha_atual, $usuario['senha'])) {
                throw new Exception('Senha atual incorreta.');
            }
            
            if ($nova_senha !== $confirmar_senha) {
                throw new Exception('Nova senha e confirmação não coincidem.');
            }
            
            if (strlen($nova_senha) < 6) {
                throw new Exception('Nova senha deve ter pelo menos 6 caracteres.');
            }
            
            // Atualizar senha
            $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
            $stmt->execute([$senha_hash, $_SESSION['usuario_id']]);
            
            $mensagem = '🔒 Senha alterada com sucesso!';
            $tipo_mensagem = 'success';
            
        } elseif ($acao == 'upload_foto') {
            if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == 0) {
                $upload_dir = 'uploads/perfil/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    throw new Exception('Formato de arquivo não permitido. Use JPG, PNG ou GIF.');
                }
                
                if ($_FILES['foto_perfil']['size'] > 5 * 1024 * 1024) { // 5MB
                    throw new Exception('Arquivo muito grande. Máximo 5MB.');
                }
                
                $new_filename = 'perfil_' . $_SESSION['usuario_id'] . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $upload_path)) {
                    // Remover foto anterior se existir
                    if ($usuario['foto_perfil'] && file_exists($usuario['foto_perfil'])) {
                        unlink($usuario['foto_perfil']);
                    }
                    
                    // Atualizar banco
                    $stmt = $pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
                    $stmt->execute([$upload_path, $_SESSION['usuario_id']]);
                    
                    $mensagem = '📸 Foto de perfil atualizada com sucesso!';
                    $tipo_mensagem = 'success';
                    
                    // Recarregar dados
                    $stmt = $pdo->prepare("SELECT u.*, c.* FROM usuarios u 
                                           LEFT JOIN clientes c ON u.email = c.email 
                                           WHERE u.id = ?");
                    $stmt->execute([$_SESSION['usuario_id']]);
                    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    throw new Exception('Erro ao fazer upload da foto.');
                }
            } else {
                throw new Exception('Nenhuma foto selecionada ou erro no upload.');
            }
        }
        
    } catch (Exception $e) {
        $mensagem = '❌ ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

function formatarTelefone($telefone) {
    if (empty($telefone)) return '';
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    if (strlen($telefone) == 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7);
    } elseif (strlen($telefone) == 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6);
    }
    return $telefone;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil - Portal Cegonheiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            --sidebar-width: 280px;
            --border-radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: var(--text-dark);
            font-size: 14px;
            line-height: 1.5;
        }

        /* Sidebar */
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

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 24px;
            min-height: 100vh;
        }

        /* Header */
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

        .breadcrumb-nav strong {
            color: var(--text-dark);
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            background: var(--white);
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

        /* Profile Photo */
        .profile-photo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-green);
            margin-bottom: 20px;
            box-shadow: var(--shadow-lg);
        }

        .profile-photo-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--primary-green);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 3rem;
            font-weight: 600;
            box-shadow: var(--shadow-lg);
        }

        .photo-upload-btn {
            background: var(--primary-green);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .photo-upload-btn:hover {
            background: var(--secondary-green);
            transform: translateY(-2px);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: var(--white);
            color: var(--text-dark);
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(0, 188, 117, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* Buttons */
        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 12px 24px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-green);
            border-color: var(--primary-green);
            color: white;
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
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            border-color: #545b62;
        }

        .btn-danger {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            border-color: #bd2130;
        }

        /* Alert */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success {
            background: linear-gradient(135deg, #e8f5e8, #d4edda);
            color: var(--secondary-green);
            border-left: 4px solid var(--primary-green);
        }

        .alert-danger {
            background: linear-gradient(135deg, #fee, #fdd);
            color: #721c24;
            border-left: 4px solid #dc3545;
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

        /* User Info Display */
        .user-info {
            background: #f8f9fa;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .user-info h6 {
            color: var(--primary-green);
            margin-bottom: 16px;
            font-weight: 600;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .info-item strong {
            color: var(--text-dark);
        }

        .info-item span {
            color: var(--text-light);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .card-body {
                padding: 20px;
            }
        }

        /* Sidebar Toggle */
        .sidebar-toggle {
            display: none;
            background: var(--primary-green);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            margin-right: 16px;
        }

        @media (max-width: 992px) {
            .sidebar-toggle {
                display: inline-block;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="https://i.ibb.co/tpmsDyMm/img-logo-portal-03.png" alt="Portal Cegonheiro">
            </div>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-th-large"></i>
                Dashboard
            </a>
            <a href="leads_disponiveis.php" class="menu-item">
                <i class="fas fa-list-alt"></i>
                Leads Disponíveis
            </a>
            <a href="relatorios.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                Relatórios
            </a>
            <a href="editar_perfil.php" class="menu-item active">
                <i class="fas fa-user"></i>
                Meu Perfil
            </a>
            <a href="configuracoes.php" class="menu-item">
                <i class="fas fa-cog"></i>
                Configurações
            </a>
            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                Sair
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div>
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="breadcrumb-nav">
                    <strong>HOME</strong> > Meu Perfil
                </div>
                <h1 class="page-title">Editar Perfil</h1>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo $mensagem; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Informações da Conta -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle"></i>
                    Informações da Conta
                </h3>
            </div>
            <div class="card-body">
                <div class="user-info">
                    <h6><i class="fas fa-user-circle me-2"></i>Dados da Conta</h6>
                    <div class="info-item">
                        <strong>Email:</strong>
                        <span><?php echo htmlspecialchars($usuario['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Nível de Acesso:</strong>
                        <span><?php echo ucfirst($usuario['nivel_acesso']); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Status:</strong>
                        <span class="badge bg-<?php echo $usuario['status'] == 'ativo' ? 'success' : 'danger'; ?>"><?php echo ucfirst($usuario['status']); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Membro desde:</strong>
                        <span><?php echo date('d/m/Y', strtotime($usuario['data_cadastro'])); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Foto de Perfil -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-camera"></i>
                    Foto de Perfil
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="fotoForm">
                    <input type="hidden" name="acao" value="upload_foto">
                    
                    <div class="profile-photo-container">
                        <?php if ($usuario['foto_perfil'] && file_exists($usuario['foto_perfil'])): ?>
                            <img src="<?php echo htmlspecialchars($usuario['foto_perfil']); ?>" alt="Foto de Perfil" class="profile-photo">
                        <?php else: ?>
                            <div class="profile-photo-placeholder">
                                <?php echo strtoupper(substr($usuario['nome'], 0, 2)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <input type="file" id="foto_perfil" name="foto_perfil" accept="image/*" style="display: none;" onchange="previewPhoto(this)">
                            <button type="button" class="photo-upload-btn" onclick="document.getElementById('foto_perfil').click()">
                                <i class="fas fa-camera me-2"></i>Alterar Foto
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <h6><i class="fas fa-info-circle me-2"></i>Informações sobre a Foto</h6>
                        <p>Formatos aceitos: JPG, PNG, GIF. Tamanho máximo: 5MB. Recomendamos fotos quadradas para melhor visualização.</p>
                    </div>
                </form>
            </div>
        </div>

        <!-- Dados Pessoais -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-edit"></i>
                    Dados Pessoais
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" id="dadosForm">
                    <input type="hidden" name="acao" value="atualizar_dados">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nome" class="form-label">
                                <i class="fas fa-user me-2"></i>Nome Completo *
                            </label>
                            <input type="text" class="form-control" id="nome" name="nome" 
                                   value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="telefone" class="form-label">
                                <i class="fas fa-phone me-2"></i>Telefone
                            </label>
                            <input type="tel" class="form-control" id="telefone" name="telefone" 
                                   value="<?php echo formatarTelefone($usuario['telefone']); ?>" 
                                   placeholder="(11) 99999-9999">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bio" class="form-label">
                            <i class="fas fa-quote-left me-2"></i>Biografia
                        </label>
                        <textarea class="form-control" id="bio" name="bio" rows="4" 
                                  placeholder="Conte um pouco sobre você..."><?php echo htmlspecialchars($usuario['bio']); ?></textarea>
                    </div>

                    <div class="d-flex gap-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Alterar Senha -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-lock"></i>
                    Alterar Senha
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" id="senhaForm">
                    <input type="hidden" name="acao" value="alterar_senha">
                    
                    <div class="form-group">
                        <label for="senha_atual" class="form-label">
                            <i class="fas fa-key me-2"></i>Senha Atual *
                        </label>
                        <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="nova_senha" class="form-label">
                                <i class="fas fa-lock me-2"></i>Nova Senha *
                            </label>
                            <input type="password" class="form-control" id="nova_senha" name="nova_senha" required>
                        </div>

                        <div class="form-group">
                            <label for="confirmar_senha" class="form-label">
                                <i class="fas fa-lock me-2"></i>Confirmar Nova Senha *
                            </label>
                            <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
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

                    <div class="d-flex gap-3 mt-3">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-key me-2"></i>
                            Alterar Senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Phone mask
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) {
                value = value.replace(/^(\d\d)(\d{5})(\d{4}).*/, '($1) $2-$3');
            } else if (value.length > 5) {
                value = value.replace(/^(\d\d)(\d{4})(\d{0,4}).*/, '($1) $2-$3');
            } else if (value.length > 2) {
                value = value.replace(/^(\d\d)(\d{0,5})/, '($1) $2');
            }
            e.target.value = value;
        });

        // Preview photo
        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const container = document.querySelector('.profile-photo-container');
                    const existingImg = container.querySelector('.profile-photo');
                    const placeholder = container.querySelector('.profile-photo-placeholder');
                    
                    if (existingImg) {
                        existingImg.src = e.target.result;
                    } else if (placeholder) {
                        placeholder.outerHTML = `<img src="${e.target.result}" alt="Foto de Perfil" class="profile-photo">`;
                    }
                    
                    // Auto submit form
                    document.getElementById('fotoForm').submit();
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Password validation
        document.getElementById('senhaForm').addEventListener('submit', function(e) {
            const novaSenha = document.getElementById('nova_senha').value;
            const confirmarSenha = document.getElementById('confirmar_senha').value;
            
            if (novaSenha !== confirmarSenha) {
                e.preventDefault();
                alert('Nova senha e confirmação não coincidem!');
                return false;
            }
            
            if (novaSenha.length < 6) {
                e.preventDefault();
                alert('Nova senha deve ter pelo menos 6 caracteres!');
                return false;
            }
        });

        // Password strength indicator
        document.getElementById('nova_senha').addEventListener('input', function() {
            const senha = this.value;
            
            if (senha.length >= 6) {
                this.style.borderColor = '#00bc75';
            } else {
                this.style.borderColor = '#dc3545';
            }
        });

        // Confirm password validation
        document.getElementById('confirmar_senha').addEventListener('input', function() {
            const novaSenha = document.getElementById('nova_senha').value;
            const confirmarSenha = this.value;
            
            if (confirmarSenha === novaSenha && novaSenha.length > 0) {
                this.style.borderColor = '#00bc75';
            } else if (confirmarSenha.length > 0) {
                this.style.borderColor = '#dc3545';
            }
        });

        // Close sidebar when clicking outside (mobile)
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.sidebar-toggle');
            
            if (window.innerWidth <= 992 && 
                !sidebar.contains(e.target) && 
                !toggleBtn.contains(e.target) && 
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });

        console.log('Página de edição de perfil carregada com sucesso!');
    </script>
</body>
</html>