<?php
require_once 'config.php';

if (!verificarLogin() || ($_SESSION['nivel_acesso'] != 'administrador' && $_SESSION['nivel_acesso'] != 'funcionario')) {
    header('Location: login.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';

// Buscar clientes
$stmt = $pdo->query("SELECT * FROM clientes ORDER BY data_cadastro DESC");
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar se cada cliente tem usuário cadastrado
foreach ($clientes as &$cliente) {
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND deletado_em IS NULL");
    $stmt->execute([$cliente['email']]);
    $cliente['tem_usuario'] = $stmt->fetch() ? true : false;
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? '';
    $cliente_id = $_POST['cliente_id'] ?? '';
    
    try {
        switch ($acao) {
            case 'alterar_status':
                $novo_status = $_POST['novo_status'];
                $stmt = $pdo->prepare("UPDATE clientes SET status = ? WHERE id = ?");
                $stmt->execute([$novo_status, $cliente_id]);
                $mensagem = 'Status do cliente alterado com sucesso!';
                $tipo_mensagem = 'success';
                break;
                
            case 'excluir':
                $stmt = $pdo->prepare("UPDATE clientes SET status = 'inativo' WHERE id = ?");
                $stmt->execute([$cliente_id]);
                $mensagem = 'Cliente desativado com sucesso!';
                $tipo_mensagem = 'success';
                break;
        }
        
        // Recarregar clientes
        $stmt = $pdo->query("SELECT * FROM clientes ORDER BY data_cadastro DESC");
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Verificar usuários novamente
        foreach ($clientes as &$cliente) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND deletado_em IS NULL");
            $stmt->execute([$cliente['email']]);
            $cliente['tem_usuario'] = $stmt->fetch() ? true : false;
        }
        
    } catch (Exception $e) {
        $mensagem = 'Erro: ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// Estatísticas
$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) as total FROM clientes WHERE status = 'ativo'");
$stats['clientes_ativos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM clientes WHERE data_cadastro >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stats['novos_mes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM clientes WHERE status = 'inativo'");
$stats['clientes_inativos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Clientes com usuários
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT c.id) as total 
    FROM clientes c 
    INNER JOIN usuarios u ON c.email = u.email 
    WHERE c.status = 'ativo' AND u.deletado_em IS NULL
");
$stats['com_usuarios'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

function formatarTelefone($telefone) {
    if (empty($telefone)) return 'Não informado';
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
    <title>Clientes - Portal Cegonheiro</title>
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
            padding: 24px;
        }

        .table {
            margin: 0;
        }

        .table th {
            background: #f8f9fa;
            border: none;
            padding: 15px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 15px;
            border-color: var(--border-color);
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 188, 117, 0.05);
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .bg-primary { background: var(--primary-green) !important; }
        .bg-success { background: #28a745 !important; }
        .bg-warning { background: #ffc107 !important; }
        .bg-danger { background: #dc3545 !important; }
        .bg-info { background: #17a2b8 !important; }

        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 8px 16px;
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
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .btn-add-client {
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

        .btn-add-client:hover {
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

        .whatsapp-link {
            color: #25D366;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .whatsapp-link:hover {
            color: #128C7E;
            transform: scale(1.05);
        }

        .user-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
        }

        .user-status.has-user {
            color: #28a745;
        }

        .user-status.no-user {
            color: #dc3545;
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

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .header-actions {
                width: 100%;
                justify-content: space-between;
            }

            .search-box input {
                width: 100%;
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

        .notification-badge {
            position: relative;
        }

        .notification-badge::after {
            content: '2';
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 16px;
            text-align: center;
        }

        .notification-badge.bell::after {
            content: '10';
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
            <a href="clientes_admin.php" class="menu-item active">
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
            <a href="usuarios_admin.php" class="menu-item">
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
                    <strong>HOME</strong> > <a href="dashboard_admin.php">Dashboard</a> > Clientes
                </div>
                <h1 class="page-title">Gerenciar Clientes</h1>
            </div>
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Buscar clientes..." id="searchInput">
                </div>
                <div class="notification-badge">
                    <i class="fas fa-envelope" style="font-size: 20px; color: var(--text-light);"></i>
                </div>
                <div class="notification-badge bell">
                    <i class="fas fa-bell" style="font-size: 20px; color: var(--text-light);"></i>
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

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['clientes_ativos']; ?></div>
                        <div class="stat-label">Clientes Ativos</div>
                    </div>
                    <div class="stat-change">+<?php echo $stats['novos_mes']; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['com_usuarios']; ?></div>
                        <div class="stat-label">Com Usuários</div>
                    </div>
                    <div class="stat-change">Acesso</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['clientes_inativos']; ?></div>
                        <div class="stat-label">Inativos</div>
                    </div>
                    <div class="stat-change" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">Pausados</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo count($clientes); ?></div>
                        <div class="stat-label">Total de Clientes</div>
                    </div>
                    <div class="stat-change">Total</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-building"></i> Lista de Clientes
                </h5>
                <a href="cadastro_cliente.php" class="btn-add-client">
                    <i class="fas fa-building"></i>
                    Cadastrar Cliente
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Telefone</th>
                                <th>Plano</th>
                                <th>Status</th>
                                <th>Usuário</th>
                                <th>Cadastro</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clientes)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="fas fa-inbox text-muted" style="font-size: 3rem;"></i>
                                        <h5 class="text-muted mt-3">Nenhum cliente cadastrado</h5>
                                        <p class="text-muted">Clique em "Cadastrar Cliente" para adicionar o primeiro cliente.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clientes as $cliente): ?>
                                    <tr>
                                        <td><strong>#<?php echo $cliente['id']; ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($cliente['nome']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                                        <td>
                                            <?php if (!empty($cliente['telefone'])): ?>
                                                <a href="https://wa.me/55<?php echo preg_replace('/[^0-9]/', '', $cliente['telefone']); ?>?text=Olá! Entro em contato pelo Portal Cegonheiro" 
                                                   class="whatsapp-link" target="_blank" title="Enviar WhatsApp">
                                                    <i class="fab fa-whatsapp"></i> <?php echo formatarTelefone($cliente['telefone']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Não informado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $plano_colors = [
                                                'basico' => 'info',
                                                'intermediario' => 'warning',
                                                'premium' => 'success'
                                            ];
                                            $color = $plano_colors[$cliente['plano']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($cliente['plano']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $cliente['status'] == 'ativo' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($cliente['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($cliente['tem_usuario']): ?>
                                                <div class="user-status has-user">
                                                    <i class="fas fa-check-circle"></i>
                                                    <span>Cadastrado</span>
                                                </div>
                                            <?php else: ?>
                                                <div class="user-status no-user">
                                                    <i class="fas fa-times-circle"></i>
                                                    <a href="cadastro_usuario.php" class="text-decoration-none" style="color: #dc3545; font-weight: 500;">
                                                        Criar Usuário
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($cliente['data_cadastro'])); ?></td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="editar_cliente.php?id=<?php echo $cliente['id']; ?>">
                                                            <i class="fas fa-edit"></i> Editar
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="alterarStatus(<?php echo $cliente['id']; ?>, '<?php echo $cliente['status'] == 'ativo' ? 'inativo' : 'ativo'; ?>')">
                                                            <i class="fas fa-<?php echo $cliente['status'] == 'ativo' ? 'ban' : 'check'; ?>"></i>
                                                            <?php echo $cliente['status'] == 'ativo' ? 'Desativar' : 'Ativar'; ?>
                                                        </a>
                                                    </li>
                                                    <?php if (!$cliente['tem_usuario']): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="cadastro_usuario.php">
                                                                <i class="fas fa-user-plus"></i> Criar Usuário
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($cliente['telefone'])): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="https://wa.me/55<?php echo preg_replace('/[^0-9]/', '', $cliente['telefone']); ?>?text=Olá! Entro em contato pelo Portal Cegonheiro" target="_blank">
                                                                <i class="fab fa-whatsapp"></i> WhatsApp
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
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

        function alterarStatus(clienteId, novoStatus) {
            if (confirm(`Tem certeza que deseja ${novoStatus == 'ativo' ? 'ativar' : 'desativar'} este cliente?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="alterar_status">
                    <input type="hidden" name="cliente_id" value="${clienteId}">
                    <input type="hidden" name="novo_status" value="${novoStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

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

        console.log('Página de clientes carregada com sucesso!');
    </script>
</body>
</html>