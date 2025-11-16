<?php
// Ativar exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once 'config.php';
} catch (Exception $e) {
    die('Erro ao carregar config.php: ' . $e->getMessage());
}

// Verificar se está logado e é admin
if (!function_exists('verificarLogin')) {
    die('Função verificarLogin não encontrada');
}

if (!verificarLogin() || $_SESSION['nivel_acesso'] != 'administrador') {
    header('Location: login.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';

// Função para formatar telefone
function formatarTelefone($telefone) {
    if (empty($telefone)) return '-';
    $telefone = preg_replace('/\D/', '', $telefone);
    if (strlen($telefone) == 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7);
    } elseif (strlen($telefone) == 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6);
    }
    return $telefone;
}

// Função para formatar CNPJ
function formatarCNPJ($cnpj) {
    if (empty($cnpj)) return '-';
    $cnpj = preg_replace('/\D/', '', $cnpj);
    if (strlen($cnpj) == 14) {
        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12);
    }
    return $cnpj;
}

try {
    // Verificar se tabela clientes existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'clientes'");
    $clientes_table_exists = $stmt->rowCount() > 0;
    
    // Buscar usuários com dados de clientes (se a tabela existir)
    if ($clientes_table_exists) {
        $sql = "
            SELECT 
                u.*,
                c.empresa,
                c.cnpj,
                c.endereco,
                c.cidade,
                c.estado,
                c.cep
            FROM usuarios u
            LEFT JOIN clientes c ON u.id = c.user_id
            WHERE (u.deletado_em IS NULL OR u.deletado_em = '')
            ORDER BY u.data_cadastro DESC
        ";
    } else {
        $sql = "
            SELECT u.*
            FROM usuarios u
            WHERE (u.deletado_em IS NULL OR u.deletado_em = '')
            ORDER BY u.data_cadastro DESC
        ";
    }
    
    $stmt = $pdo->query($sql);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // Se der erro na consulta, tentar consulta mais simples
    try {
        $stmt = $pdo->query("SELECT * FROM usuarios ORDER BY data_cadastro DESC");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $clientes_table_exists = false;
    } catch (Exception $e2) {
        die('Erro ao buscar usuários: ' . $e2->getMessage());
    }
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? '';
    $usuario_id = $_POST['usuario_id'] ?? '';
    
    try {
        switch ($acao) {
            case 'alterar_status':
                $novo_status = $_POST['novo_status'];
                $stmt = $pdo->prepare("UPDATE usuarios SET status = ? WHERE id = ?");
                $stmt->execute([$novo_status, $usuario_id]);
                $mensagem = 'Status do usuário alterado com sucesso!';
                $tipo_mensagem = 'success';
                break;
                
            case 'alterar_nivel':
                $novo_nivel = $_POST['novo_nivel'];
                $stmt = $pdo->prepare("UPDATE usuarios SET nivel_acesso = ? WHERE id = ?");
                $stmt->execute([$novo_nivel, $usuario_id]);
                $mensagem = 'Nível de acesso alterado com sucesso!';
                $tipo_mensagem = 'success';
                break;
                
            case 'resetar_senha':
                $nova_senha = password_hash('123456', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                $stmt->execute([$nova_senha, $usuario_id]);
                $mensagem = 'Senha resetada para: 123456';
                $tipo_mensagem = 'warning';
                break;
                
            case 'excluir_usuario':
                // Verificar se coluna deletado_em existe
                try {
                    $stmt = $pdo->prepare("UPDATE usuarios SET deletado_em = NOW() WHERE id = ?");
                    $stmt->execute([$usuario_id]);
                } catch (Exception $e) {
                    // Se não existir a coluna, fazer delete real (cuidado!)
                    $stmt = $pdo->prepare("UPDATE usuarios SET status = 'excluido' WHERE id = ?");
                    $stmt->execute([$usuario_id]);
                }
                $mensagem = 'Usuário excluído com sucesso!';
                $tipo_mensagem = 'success';
                break;
        }
        
        // Recarregar página para atualizar dados
        header('Location: usuarios_admin.php');
        exit;
        
    } catch (Exception $e) {
        $mensagem = 'Erro: ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// Estatísticas
try {
    $stats = [];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE status = 'ativo'");
    $stats['usuarios_ativos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE nivel_acesso = 'administrador'");
    $stats['administradores'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE nivel_acesso = 'cliente'");
    $stats['clientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE data_cadastro >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['novos_mes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (Exception $e) {
    $stats = [
        'usuarios_ativos' => 0,
        'administradores' => 0,
        'clientes' => 0,
        'novos_mes' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - Portal Cegonheiro</title>
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

        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            width: 280px;
            padding: 10px 16px 10px 40px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--white);
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }

        .breadcrumb-nav {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .breadcrumb-nav strong {
            color: var(--text-dark);
        }

        .breadcrumb-nav a {
            color: var(--text-light);
            text-decoration: none;
        }

        .breadcrumb-nav a:hover {
            color: var(--primary-green);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--white);
            padding: 24px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1;
        }

        .stat-change {
            background: rgba(0, 188, 117, 0.1);
            color: var(--primary-green);
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 14px;
            font-weight: 500;
        }

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
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            padding: 0;
        }

        .table {
            margin: 0;
            font-size: 13px;
        }

        .table th {
            background: #f8f9fa;
            border: none;
            padding: 12px 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .table td {
            padding: 12px 8px;
            border-color: var(--border-color);
            vertical-align: middle;
            font-size: 0.85rem;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 188, 117, 0.05);
        }

        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .bg-primary { background: var(--primary-green) !important; }
        .bg-success { background: #28a745 !important; }
        .bg-warning { background: #ffc107 !important; }
        .bg-danger { background: #dc3545 !important; }
        .bg-info { background: #17a2b8 !important; }

        .btn {
            border-radius: 6px;
            font-weight: 500;
            padding: 6px 12px;
            transition: all 0.3s ease;
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

        .btn-sm {
            padding: 4px 8px;
            font-size: 0.75rem;
        }

        .btn-add-user {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--secondary-green) 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 188, 117, 0.2);
        }

        .btn-add-user:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 188, 117, 0.4);
            color: white;
        }

        .dropdown-menu {
            border-radius: 8px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }

        .alert {
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert i {
            font-size: 18px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary-green);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
        }

        .user-details h6 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .user-details small {
            color: var(--text-light);
            font-size: 0.75rem;
        }

        .company-info {
            font-size: 0.8rem;
        }

        .company-info strong {
            color: var(--text-dark);
        }

        .company-info small {
            color: var(--text-light);
            display: block;
        }

        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 12px;
        }

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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
        }

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
    <div class="sidebar" id="sidebar">
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
            <a href="#" class="menu-item" onclick="alert('Página em desenvolvimento')">
                <i class="fas fa-users"></i>
                Gerenciar Leads
            </a>
            <a href="clientes_admin.php" class="menu-item">
                <i class="fas fa-building"></i>
                Clientes
            </a>
            <a href="cotacoes_admin.php" class="menu-item">
                <i class="fas fa-file-invoice-dollar"></i>
                Cotações
            </a>
            <a href="cadastro_cliente.php" class="menu-item">
                <i class="fas fa-user-plus"></i>
                Novo Cliente
            </a>
            <a href="relatorios_admin.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                Relatórios
            </a>
            <a href="configuracoes_admin.php" class="menu-item">
                <i class="fas fa-cog"></i>
                Configurações
            </a>
            <a href="usuarios_admin.php" class="menu-item active">
                <i class="fas fa-users-cog"></i>
                Usuários
            </a>
            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                Sair
            </a>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <div>
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="breadcrumb-nav">
                    <strong>HOME</strong> > <a href="dashboard_admin.php">Dashboard</a> > Usuários
                </div>
                <h1 class="page-title">Gerenciar Usuários</h1>
            </div>
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Buscar usuários..." id="searchInput">
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['nome']); ?></span>
                    <div style="width: 40px; height: 40px; background: var(--primary-green); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                        <?php echo strtoupper(substr($_SESSION['nome'], 0, 2)); ?>
                    </div>
                </div>
            </div>
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
                Total de usuários encontrados: <?php echo count($usuarios); ?><br>
                Tabela clientes existe: <?php echo $clientes_table_exists ? '✅ Sim' : '❌ Não'; ?><br>
                Usuário logado: <?php echo $_SESSION['nome']; ?> (ID: <?php echo $_SESSION['user_id']; ?>)<br>
                Timestamp: <?php echo date('d/m/Y H:i:s'); ?>
            </small>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['usuarios_ativos']; ?></div>
                        <div class="stat-label">Usuários Ativos</div>
                    </div>
                    <div class="stat-change">+<?php echo $stats['novos_mes']; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['administradores']; ?></div>
                        <div class="stat-label">Administradores</div>
                    </div>
                    <div class="stat-change">Admin</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['clientes']; ?></div>
                        <div class="stat-label">Clientes</div>
                    </div>
                    <div class="stat-change">+<?php echo $stats['novos_mes']; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo count($usuarios); ?></div>
                        <div class="stat-label">Total de Usuários</div>
                    </div>
                    <div class="stat-change">Total</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-users-cog"></i> Lista de Usuários (<?php echo count($usuarios); ?>)
                </h5>
                <a href="cadastro_usuario.php" class="btn-add-user">
                    <i class="fas fa-user-plus"></i>
                    Cadastrar Usuário
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuário</th>
                                <th>Contato</th>
                                <?php if ($clientes_table_exists): ?>
                                <th>Empresa</th>
                                <th>Localização</th>
                                <?php endif; ?>
                                <th>Nível</th>
                                <th>Status</th>
                                <th>Cadastro</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($usuarios)): ?>
                                <tr>
                                    <td colspan="<?php echo $clientes_table_exists ? '9' : '7'; ?>" class="text-center py-4">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i><br>
                                        <strong>Nenhum usuário encontrado</strong><br>
                                        <small class="text-muted">Cadastre o primeiro usuário do sistema</small>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><strong>#<?php echo $usuario['id']; ?></strong></td>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($usuario['nome'], 0, 2)); ?>
                                                </div>
                                                <div class="user-details">
                                                    <h6><?php echo htmlspecialchars($usuario['nome']); ?></h6>
                                                    <small><?php echo htmlspecialchars($usuario['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo formatarTelefone($usuario['telefone'] ?? ''); ?></strong>
                                            <?php if (!empty($usuario['bio'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($usuario['bio'], 0, 30)) . '...'; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($clientes_table_exists): ?>
                                        <td>
                                            <?php if (!empty($usuario['empresa'])): ?>
                                                <div class="company-info">
                                                    <strong><?php echo htmlspecialchars($usuario['empresa']); ?></strong>
                                                    <small><?php echo formatarCNPJ($usuario['cnpj'] ?? ''); ?></small>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($usuario['cidade'])): ?>
                                                <small>
                                                    <?php echo htmlspecialchars($usuario['cidade']); ?> - <?php echo htmlspecialchars($usuario['estado']); ?><br>
                                                    CEP: <?php echo htmlspecialchars($usuario['cep']); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                        <td>
                                            <?php
                                            $nivel_colors = [
                                                'administrador' => 'danger',
                                                'funcionario' => 'warning',
                                                'cliente' => 'primary'
                                            ];
                                            $color = $nivel_colors[$usuario['nivel_acesso']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($usuario['nivel_acesso']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $usuario['status'] == 'ativo' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($usuario['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo date('d/m/Y', strtotime($usuario['data_cadastro'])); ?><br>
                                                <span class="text-muted"><?php echo date('H:i', strtotime($usuario['data_cadastro'])); ?></span>
                                            </small>
                                        </td>
                                       <td>
    <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
        <div class="d-flex gap-1">
            <a href="editar_usuario.php?id=<?php echo $usuario['id']; ?>" 
               class="btn btn-primary btn-sm" title="Editar">
                <i class="fas fa-edit"></i>
            </a>
            <button class="btn btn-warning btn-sm" 
                    onclick="resetarSenha(<?php echo $usuario['id']; ?>)" title="Reset Senha">
                <i class="fas fa-key"></i>
            </button>
            <button class="btn btn-<?php echo $usuario['status'] == 'ativo' ? 'danger' : 'success'; ?> btn-sm" 
                    onclick="alterarStatus(<?php echo $usuario['id']; ?>, '<?php echo $usuario['status'] == 'ativo' ? 'inativo' : 'ativo'; ?>')" 
                    title="<?php echo $usuario['status'] == 'ativo' ? 'Desativar' : 'Ativar'; ?>">
                <i class="fas fa-<?php echo $usuario['status'] == 'ativo' ? 'ban' : 'check'; ?>"></i>
            </button>
            <div class="dropdown">
                <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item" href="#" onclick="alterarNivel(<?php echo $usuario['id']; ?>)">
                            <i class="fas fa-user-tag"></i> Alterar Nível
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="#" onclick="excluirUsuario(<?php echo $usuario['id']; ?>)">
                            <i class="fas fa-trash"></i> Excluir
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    <?php else: ?>
        <span class="badge bg-info">Você</span>
    <?php endif; ?>
</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        function alterarStatus(userId, novoStatus) {
            if (confirm(`Tem certeza que deseja ${novoStatus == 'ativo' ? 'ativar' : 'desativar'} este usuário?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="alterar_status">
                    <input type="hidden" name="usuario_id" value="${userId}">
                    <input type="hidden" name="novo_status" value="${novoStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function alterarNivel(userId) {
            const novoNivel = prompt('Digite o novo nível (administrador, funcionario, cliente):');
            if (novoNivel && ['administrador', 'funcionario', 'cliente'].includes(novoNivel)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="alterar_nivel">
                    <input type="hidden" name="usuario_id" value="${userId}">
                    <input type="hidden" name="novo_nivel" value="${novoNivel}">
                `;
                document.body.appendChild(form);
                form.submit();
            } else if (novoNivel) {
                alert('Nível inválido! Use: administrador, funcionario ou cliente');
            }
        }

        function resetarSenha(userId) {
            if (confirm('Tem certeza que deseja resetar a senha para 123456?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="resetar_senha">
                    <input type="hidden" name="usuario_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Função de busca
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

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

        console.log('Página de usuários carregada com sucesso!');
        console.log('Total de usuários:', <?php echo count($usuarios); ?>);
    </script>
</body>
</html>