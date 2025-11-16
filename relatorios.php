<?php
require_once 'config.php';

if (!verificarLogin() || $_SESSION['nivel_acesso'] != 'cliente') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['usuario_id'];
$nome_usuario = $_SESSION['nome'] ?? 'Usuário';

// Parâmetros de filtro
$periodo = $_GET['periodo'] ?? '30';
$tipo_relatorio = $_GET['tipo'] ?? 'geral';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

// Calcular datas baseadas no período
if (empty($data_inicio) || empty($data_fim)) {
    $data_fim = date('Y-m-d');
    switch ($periodo) {
        case '7':
            $data_inicio = date('Y-m-d', strtotime('-7 days'));
            break;
        case '30':
            $data_inicio = date('Y-m-d', strtotime('-30 days'));
            break;
        case '90':
            $data_inicio = date('Y-m-d', strtotime('-90 days'));
            break;
        case '365':
            $data_inicio = date('Y-m-d', strtotime('-365 days'));
            break;
        default:
            $data_inicio = date('Y-m-d', strtotime('-30 days'));
    }
}

try {
    // Estatísticas gerais
    $stats = [];
    
    // Total de leads visualizados
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT lead_id) as total 
        FROM lead_views 
        WHERE user_id = ? AND data_visualizacao BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $data_inicio . ' 00:00:00', $data_fim . ' 23:59:59']);
    $stats['leads_visualizados'] = $stmt->fetch()['total'] ?? 0;
    
    // Total de cotações enviadas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM cotacoes 
        WHERE user_id = ? AND data_envio BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $data_inicio . ' 00:00:00', $data_fim . ' 23:59:59']);
    $stats['cotacoes_enviadas'] = $stmt->fetch()['total'] ?? 0;
    
    // Cotações aceitas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM cotacoes 
        WHERE user_id = ? AND status = 'aceita' AND data_envio BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $data_inicio . ' 00:00:00', $data_fim . ' 23:59:59']);
    $stats['cotacoes_aceitas'] = $stmt->fetch()['total'] ?? 0;
    
    // Taxa de conversão
    $stats['taxa_conversao'] = $stats['cotacoes_enviadas'] > 0 
        ? round(($stats['cotacoes_aceitas'] / $stats['cotacoes_enviadas']) * 100, 1) 
        : 0;
    
    // Valor total das cotações
    $stmt = $pdo->prepare("
        SELECT SUM(valor_proposto) as total 
        FROM cotacoes 
        WHERE user_id = ? AND data_envio BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $data_inicio . ' 00:00:00', $data_fim . ' 23:59:59']);
    $stats['valor_total_cotacoes'] = $stmt->fetch()['total'] ?? 0;
    
    // Valor das cotações aceitas
    $stmt = $pdo->prepare("
        SELECT SUM(valor_proposto) as total 
        FROM cotacoes 
        WHERE user_id = ? AND status = 'aceita' AND data_envio BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $data_inicio . ' 00:00:00', $data_fim . ' 23:59:59']);
    $stats['valor_cotacoes_aceitas'] = $stmt->fetch()['total'] ?? 0;
    
    // Dados para gráficos
    
    // Gráfico de leads visualizados por dia
    $stmt = $pdo->prepare("
        SELECT DATE(data_visualizacao) as data, COUNT(DISTINCT lead_id) as total
        FROM lead_views 
        WHERE user_id = ? AND data_visualizacao BETWEEN ? AND ?
        GROUP BY DATE(data_visualizacao)
        ORDER BY data ASC
    ");
    $stmt->execute([$user_id, $data_inicio . ' 00:00:00', $data_fim . ' 23:59:59']);
    $chart_leads = $stmt->fetchAll();
    
    // Gráfico de cotações por dia
    $stmt = $pdo->prepare("
        SELECT DATE(data_envio) as data, COUNT(*) as total
        FROM cotacoes 
        WHERE user_id = ? AND data_envio BETWEEN ? AND ?
        GROUP BY DATE(data_envio)
        ORDER BY data ASC
    ");
    $stmt->execute([$user_id, $data_inicio . ' 00:00:00', $data_fim . ' 23:59:59']);
    $chart_cotacoes = $stmt->fetchAll();
    
    // Gráfico de status das cotações
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as total
        FROM cotacoes 
        WHERE user_id = ? AND data_envio BETWEEN ? AND ?
        GROUP BY status
        ORDER BY total DESC
    ");
    $stmt->execute([$user_id, $data_inicio . ' 00:00:00', $data_fim . ' 23:59:59']);
    $chart_status = $stmt->fetchAll();
    
    // Gráfico de tipos de veículos mais cotados
    $stmt = $pdo->prepare("
        SELECT l.tipo_veiculo, COUNT(*) as total
        FROM cotacoes c
        JOIN leads l ON c.lead_id = l.id
        WHERE c.user_id = ? AND c.data_envio BETWEEN ? AND ?
        GROUP BY l.tipo_veiculo
        ORDER BY total DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id, $data_inicio . ' 00:00:00', $data_fim . ' 23:59:59']);
    $chart_veiculos = $stmt->fetchAll();
    
    // Rotas mais cotadas
    $stmt = $pdo->prepare("
        SELECT CONCAT(l.cidade_origem, ' → ', l.cidade_destino) as rota, COUNT(*) as total
        FROM cotacoes c
        JOIN leads l ON c.lead_id = l.id
        WHERE c.user_id = ? AND c.data_envio BETWEEN ? AND ?
        GROUP BY l.cidade_origem, l.cidade_destino
        ORDER BY total DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id, $data_inicio . ' 00:00:00', $data_fim . ' 23:59:59']);
    $rotas_populares = $stmt->fetchAll();
    
    // Histórico recente de atividades
    $stmt = $pdo->prepare("
        SELECT 'cotacao' as tipo, c.id, l.cidade_origem, l.cidade_destino, 
               l.tipo_veiculo, c.valor_proposto, c.status, c.data_envio as data
        FROM cotacoes c
        JOIN leads l ON c.lead_id = l.id
        WHERE c.user_id = ? AND c.data_envio BETWEEN ? AND ?
        
        UNION ALL
        
        SELECT 'visualizacao' as tipo, l.id, l.cidade_origem, l.cidade_destino,
               l.tipo_veiculo, l.valor_veiculo, 'visualizado' as status, lv.data_visualizacao as data
        FROM lead_views lv
        JOIN leads l ON lv.lead_id = l.id
        WHERE lv.user_id = ? AND lv.data_visualizacao BETWEEN ? AND ?
        
        ORDER BY data DESC
        LIMIT 20
    ");
    $stmt->execute([
        $user_id, $data_inicio . ' 00:00:00', $data_fim . ' 23:59:59',
        $user_id, $data_inicio . ' 00:00:00', $data_fim . ' 23:59:59'
    ]);
    $atividades_recentes = $stmt->fetchAll();
    
    // Comparação com período anterior
    $data_inicio_anterior = date('Y-m-d', strtotime($data_inicio . ' -' . $periodo . ' days'));
    $data_fim_anterior = date('Y-m-d', strtotime($data_fim . ' -' . $periodo . ' days'));
    
    // Leads visualizados período anterior
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT lead_id) as total 
        FROM lead_views 
        WHERE user_id = ? AND data_visualizacao BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $data_inicio_anterior . ' 00:00:00', $data_fim_anterior . ' 23:59:59']);
    $stats['leads_anterior'] = $stmt->fetch()['total'] ?? 0;
    
    // Cotações enviadas período anterior
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM cotacoes 
        WHERE user_id = ? AND data_envio BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $data_inicio_anterior . ' 00:00:00', $data_fim_anterior . ' 23:59:59']);
    $stats['cotacoes_anterior'] = $stmt->fetch()['total'] ?? 0;
    
    // Calcular variações percentuais
    $stats['variacao_leads'] = $stats['leads_anterior'] > 0 
        ? round((($stats['leads_visualizados'] - $stats['leads_anterior']) / $stats['leads_anterior']) * 100, 1)
        : ($stats['leads_visualizados'] > 0 ? 100 : 0);
        
    $stats['variacao_cotacoes'] = $stats['cotacoes_anterior'] > 0 
        ? round((($stats['cotacoes_enviadas'] - $stats['cotacoes_anterior']) / $stats['cotacoes_anterior']) * 100, 1)
        : ($stats['cotacoes_enviadas'] > 0 ? 100 : 0);
    
} catch (Exception $e) {
    error_log("Erro ao buscar dados dos relatórios: " . $e->getMessage());
    $stats = [
        'leads_visualizados' => 0,
        'cotacoes_enviadas' => 0,
        'cotacoes_aceitas' => 0,
        'taxa_conversao' => 0,
        'valor_total_cotacoes' => 0,
        'valor_cotacoes_aceitas' => 0,
        'variacao_leads' => 0,
        'variacao_cotacoes' => 0
    ];
    $chart_leads = [];
    $chart_cotacoes = [];
    $chart_status = [];
    $chart_veiculos = [];
    $rotas_populares = [];
    $atividades_recentes = [];
}

function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function formatarVariacao($variacao) {
    $sinal = $variacao >= 0 ? '+' : '';
    $classe = $variacao >= 0 ? 'text-success' : 'text-danger';
    $icone = $variacao >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
    
    return '<span class="' . $classe . '"><i class="fas ' . $icone . '"></i> ' . $sinal . $variacao . '%</span>';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Portal Cegonheiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Filter Section */
        .filter-section {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            padding: 24px;
            margin-bottom: 24px;
        }

        .filter-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .stat-icon.leads {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.cotacoes {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--secondary-green) 100%);
        }

        .stat-icon.aceitas {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-icon.conversao {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-icon.valor {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .stat-content {
            flex: 1;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 14px;
            font-weight: 500;
        }

        .stat-change {
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
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

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 16px;
        }

        .chart-container.small {
            height: 250px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 16px;
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
            padding: 10px 16px;
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

        /* Buttons */
        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 10px 20px;
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

        .btn-outline-primary {
            color: var(--primary-green);
            border-color: var(--primary-green);
        }

        .btn-outline-primary:hover {
            background: var(--primary-green);
            border-color: var(--primary-green);
            color: white;
        }

        /* Activity Timeline */
        .activity-timeline {
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 14px;
            color: white;
        }

        .activity-icon.cotacao {
            background: var(--primary-green);
        }

        .activity-icon.visualizacao {
            background: #6c757d;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
            margin-bottom: 2px;
        }

        .activity-description {
            font-size: 13px;
            color: var(--text-light);
        }

        .activity-time {
            font-size: 12px;
            color: var(--text-light);
            text-align: right;
        }

        /* Popular Routes */
        .route-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .route-item:last-child {
            border-bottom: none;
        }

        .route-name {
            font-weight: 500;
            color: var(--text-dark);
        }

        .route-count {
            background: var(--primary-green);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Export Section */
        .export-section {
            background: #f8f9fa;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            margin-top: 24px;
        }

        .export-title {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 12px;
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .chart-container {
                height: 250px;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .card-body {
                padding: 16px;
            }

            .filter-section {
                padding: 16px;
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

        /* Loading States */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 200px;
            color: var(--text-light);
        }

        .loading i {
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
            <a href="relatorios.php" class="menu-item active">
                <i class="fas fa-chart-bar"></i>
                Relatórios
            </a>
            <a href="editar_perfil.php" class="menu-item">
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
                    <strong>HOME</strong> > Relatórios
                </div>
                <h1 class="page-title">Relatórios e Análises</h1>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-section">
            <h3 class="filter-title">
                <i class="fas fa-filter"></i>
                Filtros do Relatório
            </h3>
            
            <form method="GET" action="relatorios.php" class="row g-3">
                <div class="col-md-3">
                    <label for="periodo" class="form-label">Período</label>
                    <select class="form-select" name="periodo" id="periodo" onchange="toggleCustomDates()">
                        <option value="7" <?php echo $periodo == '7' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                        <option value="30" <?php echo $periodo == '30' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                        <option value="90" <?php echo $periodo == '90' ? 'selected' : ''; ?>>Últimos 90 dias</option>
                        <option value="365" <?php echo $periodo == '365' ? 'selected' : ''; ?>>Último ano</option>
                        <option value="custom" <?php echo (!in_array($periodo, ['7', '30', '90', '365'])) ? 'selected' : ''; ?>>Período personalizado</option>
                    </select>
                </div>
                
                <div class="col-md-3" id="customDates" style="<?php echo (!in_array($periodo, ['7', '30', '90', '365'])) ? '' : 'display: none;'; ?>">
                    <label for="data_inicio" class="form-label">Data Início</label>
                    <input type="date" class="form-control" name="data_inicio" id="data_inicio" 
                           value="<?php echo $data_inicio; ?>">
                </div>
                
                <div class="col-md-3" id="customDatesEnd" style="<?php echo (!in_array($periodo, ['7', '30', '90', '365'])) ? '' : 'display: none;'; ?>">
                    <label for="data_fim" class="form-label">Data Fim</label>
                    <input type="date" class="form-control" name="data_fim" id="data_fim" 
                           value="<?php echo $data_fim; ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="tipo" class="form-label">Tipo de Relatório</label>
                    <select class="form-select" name="tipo" id="tipo">
                        <option value="geral" <?php echo $tipo_relatorio == 'geral' ? 'selected' : ''; ?>>Relatório Geral</option>
                        <option value="leads" <?php echo $tipo_relatorio == 'leads' ? 'selected' : ''; ?>>Apenas Leads</option>
                        <option value="cotacoes" <?php echo $tipo_relatorio == 'cotacoes' ? 'selected' : ''; ?>>Apenas Cotações</option>
                    </select>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>
                        Gerar Relatório
                    </button>
                    <button type="button" class="btn btn-outline-primary ms-2" onclick="exportarRelatorio()">
                        <i class="fas fa-download me-2"></i>
                        Exportar PDF
                    </button>
                </div>
            </form>
        </div>

        <!-- Estatísticas Principais -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon leads">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($stats['leads_visualizados'], 0, ',', '.'); ?></div>
                        <div class="stat-label">Leads Visualizados</div>
                        <div class="stat-change"><?php echo formatarVariacao($stats['variacao_leads']); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon cotacoes">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($stats['cotacoes_enviadas'], 0, ',', '.'); ?></div>
                        <div class="stat-label">Cotações Enviadas</div>
                        <div class="stat-change"><?php echo formatarVariacao($stats['variacao_cotacoes']); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon aceitas">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($stats['cotacoes_aceitas'], 0, ',', '.'); ?></div>
                        <div class="stat-label">Cotações Aceitas</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon conversao">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['taxa_conversao']; ?>%</div>
                        <div class="stat-label">Taxa de Conversão</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon valor">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo formatarMoeda($stats['valor_cotacoes_aceitas']); ?></div>
                        <div class="stat-label">Valor Faturado</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            Atividade ao Longo do Tempo
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-pie"></i>
                            Status das Cotações
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container small">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-car"></i>
                            Tipos de Veículos Mais Cotados
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container small">
                            <canvas id="vehicleChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-route"></i>
                            Rotas Mais Populares
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($rotas_populares)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-route fa-3x mb-3"></i>
                                <p>Nenhuma rota encontrada no período selecionado</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($rotas_populares as $rota): ?>
                                <div class="route-item">
                                    <div class="route-name"><?php echo htmlspecialchars($rota['rota']); ?></div>
                                    <div class="route-count"><?php echo $rota['total']; ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Atividades Recentes -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-history"></i>
                    Atividades Recentes
                </h3>
            </div>
            <div class="card-body">
                <?php if (empty($atividades_recentes)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-history fa-3x mb-3"></i>
                        <p>Nenhuma atividade encontrada no período selecionado</p>
                    </div>
                <?php else: ?>
                    <div class="activity-timeline">
                        <?php foreach ($atividades_recentes as $atividade): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?php echo $atividade['tipo']; ?>">
                                    <i class="fas fa-<?php echo $atividade['tipo'] == 'cotacao' ? 'paper-plane' : 'eye'; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <?php if ($atividade['tipo'] == 'cotacao'): ?>
                                            Cotação enviada - <?php echo htmlspecialchars($atividade['tipo_veiculo']); ?>
                                        <?php else: ?>
                                            Lead visualizado - <?php echo htmlspecialchars($atividade['tipo_veiculo']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-description">
                                        <?php echo htmlspecialchars($atividade['cidade_origem']); ?> → <?php echo htmlspecialchars($atividade['cidade_destino']); ?>
                                        <?php if ($atividade['tipo'] == 'cotacao' && $atividade['valor_proposto']): ?>
                                            - <?php echo formatarMoeda($atividade['valor_proposto']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="activity-time">
                                    <?php echo date('d/m/Y H:i', strtotime($atividade['data'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        function toggleCustomDates() {
            const periodo = document.getElementById('periodo').value;
            const customDates = document.getElementById('customDates');
            const customDatesEnd = document.getElementById('customDatesEnd');
            
            if (periodo === 'custom') {
                customDates.style.display = 'block';
                customDatesEnd.style.display = 'block';
            } else {
                customDates.style.display = 'none';
                customDatesEnd.style.display = 'none';
            }
        }

        function exportarRelatorio() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'pdf');
            window.open('exportar_relatorio.php?' + params.toString(), '_blank');
        }

        // Gráfico de Atividade
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        const activityChart = new Chart(activityCtx, {
            type: 'line',
            data: {
                labels: [<?php 
                    $all_dates = [];
                    foreach ($chart_leads as $item) $all_dates[] = $item['data'];
                    foreach ($chart_cotacoes as $item) $all_dates[] = $item['data'];
                    $all_dates = array_unique($all_dates);
                    sort($all_dates);
                    echo !empty($all_dates) ? "'" . implode("', '", $all_dates) . "'" : "'Hoje'";
                ?>],
                datasets: [{
                    label: 'Leads Visualizados',
                    data: [<?php 
                        $leads_data = [];
                        foreach ($all_dates as $date) {
                            $count = 0;
                                                    foreach ($all_dates as $date) {
                            $count = 0;
                            foreach ($chart_leads as $item) {
                                if ($item['data'] == $date) {
                                    $count = $item['total'];
                                    break;
                                }
                            }
                            $leads_data[] = $count;
                        }
                        echo implode(', ', $leads_data);
                    ?>],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Cotações Enviadas',
                    data: [<?php 
                        $cotacoes_data = [];
                        foreach ($all_dates as $date) {
                            $count = 0;
                            foreach ($chart_cotacoes as $item) {
                                if ($item['data'] == $date) {
                                    $count = $item['total'];
                                    break;
                                }
                            }
                            $cotacoes_data[] = $count;
                        }
                        echo implode(', ', $cotacoes_data);
                    ?>],
                    borderColor: '#00bc75',
                    backgroundColor: 'rgba(0, 188, 117, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f1f5f9',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: {
                                size: 12
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                elements: {
                    point: {
                        hoverRadius: 8,
                        radius: 4
                    }
                }
            }
        });

        // Gráfico de Status das Cotações
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php 
                    $status_labels = [];
                    foreach ($chart_status as $item) {
                        $status_labels[] = "'" . ucfirst($item['status']) . "'";
                    }
                    echo implode(', ', $status_labels);
                ?>],
                datasets: [{
                    data: [<?php 
                        $status_data = [];
                        foreach ($chart_status as $item) {
                            $status_data[] = $item['total'];
                        }
                        echo implode(', ', $status_data);
                    ?>],
                    backgroundColor: [
                        '#00bc75',
                        '#ffc107',
                        '#dc3545',
                        '#6c757d',
                        '#17a2b8'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 15
                        }
                    }
                }
            }
        });

        // Gráfico de Tipos de Veículos
        const vehicleCtx = document.getElementById('vehicleChart').getContext('2d');
        const vehicleChart = new Chart(vehicleCtx, {
            type: 'bar',
            data: {
                labels: [<?php 
                    $vehicle_labels = [];
                    foreach ($chart_veiculos as $item) {
                        $vehicle_labels[] = "'" . $item['tipo_veiculo'] . "'";
                    }
                    echo implode(', ', $vehicle_labels);
                ?>],
                datasets: [{
                    label: 'Cotações',
                    data: [<?php 
                        $vehicle_data = [];
                        foreach ($chart_veiculos as $item) {
                            $vehicle_data[] = $item['total'];
                        }
                        echo implode(', ', $vehicle_data);
                    ?>],
                    backgroundColor: [
                        '#00bc75',
                        '#667eea',
                        '#f093fb',
                        '#4facfe',
                        '#43e97b'
                    ],
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f1f5f9',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: {
                                size: 12
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: {
                                size: 12
                            }
                        }
                    }
                }
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

        // Auto-refresh data every 5 minutes
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 300000);

        // Print functionality
        function printReport() {
            window.print();
        }

        // Add print styles
        const printStyles = `
            @media print {
                .sidebar { display: none !important; }
                .main-content { margin-left: 0 !important; }
                .btn, .sidebar-toggle { display: none !important; }
                .card { break-inside: avoid; }
                .stats-grid { break-inside: avoid; }
                .chart-container { height: 300px !important; }
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = printStyles;
        document.head.appendChild(styleSheet);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + P to print
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printReport();
            }
            
            // Ctrl + E to export
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportarRelatorio();
            }
        });

        // Loading animation for charts
        function showChartLoading(chartId) {
            const container = document.querySelector(`#${chartId}`).parentNode;
            container.innerHTML = `
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    Carregando gráfico...
                </div>
            `;
        }

        // Animate numbers on load
        function animateNumbers() {
            const numbers = document.querySelectorAll('.stat-number');
            numbers.forEach(number => {
                const target = parseInt(number.textContent.replace(/[^0-9]/g, ''));
                let current = 0;
                const increment = target / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    
                    if (number.textContent.includes('%')) {
                        number.textContent = Math.floor(current) + '%';
                    } else if (number.textContent.includes('R$')) {
                        number.textContent = 'R$ ' + Math.floor(current).toLocaleString('pt-BR');
                    } else {
                        number.textContent = Math.floor(current).toLocaleString('pt-BR');
                    }
                }, 20);
            });
        }

        // Initialize animations when page loads
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(animateNumbers, 500);
        });

        // Tooltip for chart points
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(0, 0, 0, 0.8)';
        Chart.defaults.plugins.tooltip.titleColor = '#fff';
        Chart.defaults.plugins.tooltip.bodyColor = '#fff';
        Chart.defaults.plugins.tooltip.cornerRadius = 8;

        // Update charts when window resizes
        window.addEventListener('resize', function() {
            activityChart.resize();
            statusChart.resize();
            vehicleChart.resize();
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const dataInicio = document.getElementById('data_inicio').value;
            const dataFim = document.getElementById('data_fim').value;
            const periodo = document.getElementById('periodo').value;
            
            if (periodo === 'custom') {
                if (!dataInicio || !dataFim) {
                    e.preventDefault();
                    alert('Por favor, selecione as datas de início e fim.');
                    return false;
                }
                
                if (new Date(dataInicio) > new Date(dataFim)) {
                    e.preventDefault();
                    alert('A data de início deve ser anterior à data de fim.');
                    return false;
                }
            }
        });

        // Add loading state to form submission
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Gerando...';
            submitBtn.disabled = true;
        });

        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            console.log('✅ Relatórios carregados em:', Math.round(loadTime), 'ms');
            console.log('📊 Período selecionado:', '<?php echo $periodo; ?> dias');
            console.log('📈 Leads visualizados:', <?php echo $stats['leads_visualizados']; ?>);
            console.log('📋 Cotações enviadas:', <?php echo $stats['cotacoes_enviadas']; ?>);
            console.log('✅ Taxa de conversão:', '<?php echo $stats['taxa_conversao']; ?>%');
        });

        console.log('Página de relatórios carregada com sucesso!');
    </script>
</body>
</html>
                