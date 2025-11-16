<?php
require_once 'config.php';

if (!verificarLogin() || ($_SESSION['nivel_acesso'] != 'administrador' && $_SESSION['nivel_acesso'] != 'funcionario')) {
    header('Location: login.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';
$filtro_cidade = $_GET['cidade'] ?? '';

// Construir query com filtros
$where_conditions = [];
$params = [];

if ($filtro_status) {
    $where_conditions[] = "l.status = ?";
    $params[] = $filtro_status;
}

if ($filtro_data_inicio) {
    $where_conditions[] = "DATE(l.data_cadastro) >= ?";
    $params[] = $filtro_data_inicio;
}

if ($filtro_data_fim) {
    $where_conditions[] = "DATE(l.data_cadastro) <= ?";
    $params[] = $filtro_data_fim;
}

if ($filtro_cidade) {
    $where_conditions[] = "(l.cidade_origem LIKE ? OR l.cidade_destino LIKE ?)";
    $params[] = "%$filtro_cidade%";
    $params[] = "%$filtro_cidade%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Buscar leads
$stmt = $pdo->prepare("
    SELECT l.*, 
           COUNT(c.id) as total_cotacoes,
           MAX(c.data_envio) as ultima_cotacao
    FROM leads l 
    LEFT JOIN cotacoes c ON l.id = c.lead_id 
    $where_clause
    GROUP BY l.id 
    ORDER BY l.data_cadastro DESC
    LIMIT 100
");
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Processar ações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? '';
    $lead_id = $_POST['lead_id'] ?? '';
    
    try {
        switch ($acao) {
            case 'alterar_status':
                $novo_status = $_POST['novo_status'];
                $stmt = $pdo->prepare("UPDATE leads SET status = ? WHERE id = ?");
                $stmt->execute([$novo_status, $lead_id]);
                $mensagem = 'Status do lead alterado com sucesso!';
                $tipo_mensagem = 'success';
                break;
                
            case 'excluir':
                $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ?");
                $stmt->execute([$lead_id]);
                $mensagem = 'Lead excluído com sucesso!';
                $tipo_mensagem = 'success';
                break;
        }
        
        // Recarregar página
        header("Location: gerenciar_leads.php?" . http_build_query($_GET));
        exit;
        
    } catch (Exception $e) {
        $mensagem = 'Erro: ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// Estatísticas
$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE status = 'novo'");
$stats['leads_novos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE status = 'em_andamento'");
$stats['leads_andamento'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE status = 'cotado'");
$stats['leads_cotados'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE data_cadastro >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['leads_semana'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Leads - Portal Cegonheiro</title>
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
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
            text-decoration: none;
        }

        .logo i {
            color: var(--primary-green);
            font-size: 24px;
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
        }

        .btn-primary {
            background: var(--primary-green);
            border-color: var(--primary-green);
        }

        .btn-primary:hover {
            background: var(--secondary-green);
            border-color: var(--secondary-green);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .filters-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            padding: 20px;
            margin-bottom: 24px;
        }

        .form-control {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(0, 188, 117, 0.25);
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
            <a href="#" class="logo">
                <i class="fas fa-truck"></i>
                <span>Portal Cegonheiro</span>
            </a>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard_admin.php" class="menu-item">
                <i class="fas fa-th-large"></i>
                Dashboard
            </a>
            <a href="gerenciar_leads.php" class="menu-item active">
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
                    <strong>HOME</strong> > <a href="dashboard_admin.php">Dashboard</a> > Gerenciar Leads
                </div>
                <h1 class="page-title">Gerenciar Leads</h1>
            </div>
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Buscar leads..." id="searchInput">
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
                        <div class="stat-number"><?php echo $stats['leads_novos']; ?></div>
                        <div class="stat-label">Leads Novos</div>
                    </div>
                    <div class="stat-change">Novo</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['leads_andamento']; ?></div>
                        <div class="stat-label">Em Andamento</div>
                    </div>
                    <div class="stat-change">Ativo</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['leads_cotados']; ?></div>
                        <div class="stat-label">Leads Cotados</div>
                    </div>
                    <div class="stat-change">Cotado</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['leads_semana']; ?></div>
                        <div class="stat-label">Esta Semana</div>
                    </div>
                    <div class="stat-change">+<?php echo $stats['leads_semana']; ?></div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-card">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="status">
                        <option value="">Todos</option>
                        <option value="novo" <?php echo $filtro_status == 'novo' ? 'selected' : ''; ?>>Novo</option>
                        <option value="em_andamento" <?php echo $filtro_status == 'em_andamento' ? 'selected' : ''; ?>>Em Andamento</option>
                        <option value="cotado" <?php echo $filtro_status == 'cotado' ? 'selected' : ''; ?>>Cotado</option>
                        <option value="finalizado" <?php echo $filtro_status == 'finalizado' ? 'selected' : ''; ?>>Finalizado</option>
                        <option value="cancelado" <?php echo $filtro_status == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data Início</label>
                    <input type="date" class="form-control" name="data_inicio" value="<?php echo $filtro_data_inicio; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data Fim</label>
                    <input type="date" class="form-control" name="data_fim" value="<?php echo $filtro_data_fim; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cidade</label>
                    <input type="text" class="form-control" name="cidade" value="<?php echo $filtro_cidade; ?>" placeholder="Origem ou destino">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                        <a href="gerenciar_leads.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Limpar
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-users"></i> Lista de Leads (<?php echo count($leads); ?>)
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Rota</th>
                                <th>Veículo</th>
                                <th>Data Prevista</th>
                                <th>Status</th>
                                <th>Cotações</th>
                                <th>Cadastro</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leads)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="fas fa-inbox fa-2x text-muted mb-2"></i><br>
                                        <span class="text-muted">Nenhum lead encontrado</span>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leads as $lead): ?>
                                    <tr>
                                        <td><strong>#<?php echo $lead['id']; ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($lead['nome']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($lead['email']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo htmlspecialchars($lead['cidade_origem']); ?></span>
                                            <i class="fas fa-arrow-right mx-1"></i>
                                            <span class="badge bg-danger"><?php echo htmlspecialchars($lead['cidade_destino']); ?></span>
                                        </td>
                                        <td>
                                            <i class="fas fa-car text-primary"></i>
                                            <?php echo htmlspecialchars($lead['tipo_veiculo']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($lead['ano_modelo']); ?></small>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($lead['data_prevista'])); ?></td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'novo' => 'primary',
                                                'em_andamento' => 'warning',
                                                'cotado' => 'info',
                                                'finalizado' => 'success',
                                                'cancelado' => 'danger'
                                            ];
                                            $color = $status_colors[$lead['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $lead['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $lead['total_cotacoes']; ?></span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($lead['data_cadastro'])); ?></td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="alterarStatus(<?php echo $lead['id']; ?>)">
                                                            <i class="fas fa-edit"></i> Alterar Status
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" onclick="excluirLead(<?php echo $lead['id']; ?>)">
                                                            <i class="fas fa-trash"></i> Excluir
                                                        </a>
                                                    </li>
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

        function alterarStatus(leadId) {
            const novoStatus = prompt('Digite o novo status (novo, em_andamento, cotado, finalizado, cancelado):');
            if (novoStatus && ['novo', 'em_andamento', 'cotado', 'finalizado', 'cancelado'].includes(novoStatus)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="alterar_status">
                    <input type="hidden" name="lead_id" value="${leadId}">
                    <input type="hidden" name="novo_status" value="${novoStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            } else if (novoStatus) {
                alert('Status inválido!');
            }
        }

        function excluirLead(leadId) {
            if (confirm('Tem certeza que deseja excluir este lead? Esta ação não pode ser desfeita.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="lead_id" value="${leadId}">
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

        console.log('Página de gerenciar leads carregada com sucesso!');
    </script>
</body>
</html>