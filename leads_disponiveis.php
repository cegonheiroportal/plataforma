<?php
// leads_disponiveis.php - Página de leads disponíveis com layout de colunas organizadas
require_once 'config.php';

// Verificações de acesso
if (!verificarLogin()) {
    header('Location: login.php');
    exit;
}

// Verificar se tem permissão (administrador ou cliente transportadora)
$nivel_acesso = $_SESSION['nivel_acesso'] ?? '';
$tipo_cliente = $_SESSION['tipo_cliente'] ?? 'pf';

$tem_acesso = false;
$eh_admin = false;

if ($nivel_acesso === 'admin') {
    $tem_acesso = true;
    $eh_admin = true;
} elseif ($nivel_acesso === 'cliente' && $tipo_cliente === 'pj') {
    $tem_acesso = true;
    $eh_admin = false;
}

if (!$tem_acesso) {
    $_SESSION['erro'] = 'Acesso negado. Esta página é apenas para transportadoras e administradores.';
    header('Location: dashboard.php');
    exit;
}

$user_id = obterIdUsuario();
$nome_usuario = $_SESSION['nome'] ?? 'Usuário';

// Buscar dados do usuário para foto de perfil
try {
    $stmt = $pdo->prepare("SELECT id, nome, email, nivel_acesso, foto_perfil FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch();
} catch (Exception $e) {
    error_log("Erro ao buscar dados do usuário: " . $e->getMessage());
    $usuario = ['nome' => $nome_usuario, 'foto_perfil' => null];
}

// Parâmetros de filtro simplificados
$filtro_origem = $_GET['origem'] ?? '';
$filtro_destino = $_GET['destino'] ?? '';
$filtro_veiculo = $_GET['veiculo'] ?? '';
$filtro_distancia = $_GET['distancia'] ?? '';
$ordenar_por = $_GET['ordenar'] ?? 'data_cadastro';
$ordem = $_GET['ordem'] ?? 'DESC';
$pagina = (int)($_GET['pagina'] ?? 1);
$por_pagina = (int)($_GET['por_pagina'] ?? 15);

// Construir query com filtros
$where_conditions = ["l.status IN ('novo', 'em_andamento')"];
$params = [];

if (!empty($filtro_origem)) {
    $where_conditions[] = "l.cidade_origem LIKE ?";
    $params[] = '%' . $filtro_origem . '%';
}

if (!empty($filtro_destino)) {
    $where_conditions[] = "l.cidade_destino LIKE ?";
    $params[] = '%' . $filtro_destino . '%';
}

if (!empty($filtro_veiculo)) {
    $where_conditions[] = "l.tipo_veiculo = ?";
    $params[] = $filtro_veiculo;
}

$where_clause = implode(' AND ', $where_conditions);

// Validar ordenação
$campos_ordenacao = ['data_cadastro', 'tipo_veiculo', 'cidade_origem'];
if (!in_array($ordenar_por, $campos_ordenacao)) {
    $ordenar_por = 'data_cadastro';
}
$ordem = ($ordem === 'ASC') ? 'ASC' : 'DESC';

try {
    // Contar total de leads
    $sql_count = "SELECT COUNT(*) as total FROM leads l WHERE $where_clause";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_leads = $stmt_count->fetch()['total'];
    
    // Calcular paginação
    $total_paginas = ceil($total_leads / $por_pagina);
    $offset = ($pagina - 1) * $por_pagina;
    
    // Query corrigida para contar visualizações corretamente
    $sql = "SELECT l.*, 
                   COALESCE(c.total_cotacoes, 0) as total_cotacoes,
                   c.ultima_cotacao,
                   COALESCE(v.total_visualizacoes, 0) as total_visualizacoes,
                   CASE WHEN v.ja_visualizei > 0 THEN 1 ELSE 0 END as ja_visualizei
            FROM leads l 
            LEFT JOIN (
                SELECT lead_id, 
                       COUNT(*) as total_cotacoes,
                       MAX(data_envio) as ultima_cotacao
                FROM cotacoes 
                GROUP BY lead_id
            ) c ON l.id = c.lead_id
            LEFT JOIN (
                SELECT lead_id, 
                       COUNT(DISTINCT user_id) as total_visualizacoes,
                       SUM(CASE WHEN user_id = ? THEN 1 ELSE 0 END) as ja_visualizei
                FROM lead_views 
                GROUP BY lead_id
            ) v ON l.id = v.lead_id
            WHERE $where_clause 
            ORDER BY l.$ordenar_por $ordem 
            LIMIT $por_pagina OFFSET $offset";
    
    $params_with_user = array_merge([$user_id], $params);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_with_user);
    $leads = $stmt->fetchAll();
    
    // Buscar TODAS as cidades para autocomplete
    $stmt_todas_cidades = $pdo->query("
        SELECT DISTINCT cidade_origem as cidade FROM leads 
        UNION 
        SELECT DISTINCT cidade_destino as cidade FROM leads 
        ORDER BY cidade
    ");
    $todas_cidades = $stmt_todas_cidades->fetchAll(PDO::FETCH_COLUMN);
    
    // Filtrar leads por distância se especificado
    if (!empty($filtro_distancia) && !empty($leads)) {
        $leads_filtrados = [];
        foreach ($leads as $lead) {
            $distancia = calcularDistanciaAproximada($lead['cidade_origem'], $lead['cidade_destino']);
            
            switch ($filtro_distancia) {
                case '0-500':
                    if ($distancia <= 500) $leads_filtrados[] = $lead;
                    break;
                case '501-1000':
                    if ($distancia > 500 && $distancia <= 1000) $leads_filtrados[] = $lead;
                    break;
                case '1001-1500':
                    if ($distancia > 1000 && $distancia <= 1500) $leads_filtrados[] = $lead;
                    break;
                case '1501-2000':
                    if ($distancia > 1500 && $distancia <= 2000) $leads_filtrados[] = $lead;
                    break;
                case '2001-2500':
                    if ($distancia > 2000 && $distancia <= 2500) $leads_filtrados[] = $lead;
                    break;
                case '2501-3000':
                    if ($distancia > 2500 && $distancia <= 3000) $leads_filtrados[] = $lead;
                    break;
                case '3000+':
                    if ($distancia > 3000) $leads_filtrados[] = $lead;
                    break;
            }
        }
        $leads = $leads_filtrados;
        $total_leads = count($leads);
        $total_paginas = ceil($total_leads / $por_pagina);
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar leads: " . $e->getMessage());
    $leads = [];
    $total_leads = 0;
    $total_paginas = 0;
    $todas_cidades = [];
}

// Função para calcular distância aproximada
function calcularDistanciaAproximada($origem, $destino) {
    $distancias = [
        'FORTALEZA-GOIÂNIA' => 1180,
        'FORTALEZA-SÃO PAULO' => 2380,
        'FORTALEZA-RIO DE JANEIRO' => 2300,
        'FORTALEZA-BRASÍLIA' => 1150,
        'FORTALEZA-BELO HORIZONTE' => 1950,
        'SÃO PAULO-RIO DE JANEIRO' => 430,
        'SÃO PAULO-BRASÍLIA' => 1010,
        'SÃO PAULO-BELO HORIZONTE' => 580,
        'SÃO PAULO-GOIÂNIA' => 920,
        'RIO DE JANEIRO-BRASÍLIA' => 1150,
        'RIO DE JANEIRO-BELO HORIZONTE' => 440,
        'BRASÍLIA-GOIÂNIA' => 210,
        'BRASÍLIA-BELO HORIZONTE' => 740,
        'BELO HORIZONTE-GOIÂNIA' => 890
    ];
    
    $origem_limpa = strtoupper(preg_replace('/,.*$/', '', trim($origem)));
    $destino_limpo = strtoupper(preg_replace('/,.*$/', '', trim($destino)));
    
    $chave1 = $origem_limpa . '-' . $destino_limpo;
    $chave2 = $destino_limpo . '-' . $origem_limpa;
    
    if (isset($distancias[$chave1])) {
        return $distancias[$chave1];
    } elseif (isset($distancias[$chave2])) {
        return $distancias[$chave2];
    }
    
    return rand(800, 2500);
}

// Função para verificar se pode visualizar o lead
function podeVisualizarLead($lead, $eh_admin) {
    // Admin pode visualizar sempre
    if ($eh_admin) {
        return true;
    }
    
    // Se já visualizou antes, pode visualizar novamente
    if ($lead['ja_visualizei'] == 1) {
        return true;
    }
    
    // Se ainda não atingiu o limite de 7 visualizações
    if ($lead['total_visualizacoes'] < 7) {
        return true;
    }
    
    return false;
}

// Verificar se há filtros ativos
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads Disponíveis - Portal Cegonheiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-green: #00bc75;
            --secondary-green: #07a368;
            --light-green: rgba(0, 188, 117, 0.1);
            --border-color: #e9ecef;
            --text-muted: #6c757d;
            --text-dark: #2c2c2c;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.07);
            --shadow-lg: 0 8px 15px rgba(0, 0, 0, 0.1);
            --bg-light: #f8f9fa;
            --divider-color: #dee2e6;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-light);
            line-height: 1.6;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: white;
            border-right: 1px solid var(--border-color);
            z-index: 1000;
            overflow-y: auto;
            box-shadow: var(--shadow-md);
        }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid var(--border-color);
            text-align: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
            text-decoration: none;
        }

        .logo img {
            height: 120px;
            width: auto;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 24px;
            min-height: 100vh;
        }
        
        .menu-item {
            display: block;
            padding: 12px 20px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        
        .menu-item:hover,
        .menu-item.active {
            color: var(--primary-green);
            background: var(--light-green);
            border-left-color: var(--primary-green);
        }
        
        .menu-item i {
            width: 20px;
            margin-right: 12px;
            font-size: 16px;
        }

        /* Header */
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
            margin-bottom: 0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-green);
        }

        .user-avatar-placeholder {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            border: 2px solid var(--primary-green);
        }

        /* Filtros */
        .filters-section {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .filters-header {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .filters-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .filters-body {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            background: white;
        }
        
        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(0, 188, 117, 0.25);
        }

        .form-select {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            background: white;
        }
        
        .form-select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(0, 188, 117, 0.25);
        }

        /* Autocomplete styles */
        .autocomplete-container {
            position: relative;
        }

        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid var(--border-color);
            border-top: none;
            border-radius: 0 0 10px 10px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .autocomplete-suggestion {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease;
        }

        .autocomplete-suggestion:hover,
        .autocomplete-suggestion.selected {
            background-color: var(--light-green);
            color: var(--primary-green);
        }

        .autocomplete-suggestion:last-child {
            border-bottom: none;
        }

        /* Cabeçalho da tabela */
        .leads-table-header {
            background: white;
            border-radius: 12px 12px 0 0;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            margin-bottom: 0;
            padding: 1rem 0;
        }

        .table-header-row {
            display: grid;
            grid-template-columns: 80px 1fr 150px 150px 120px 100px 100px 150px;
            gap: 1rem;
            padding: 0 2rem;
            align-items: center;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .header-cell {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .header-cell:hover {
            color: var(--primary-green);
        }

        .header-cell i {
            font-size: 0.8rem;
            opacity: 0.7;
        }

        /* Lista de leads em colunas organizadas */
        .leads-container {
            background: white;
            border-radius: 0 0 12px 12px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            border-top: none;
            overflow: hidden;
        }

        .leads-list {
            display: flex;
            flex-direction: column;
        }
        
        .lead-item {
            border-bottom: 1px solid var(--divider-color);
            transition: all 0.3s ease;
            position: relative;
            background: white;
        }

        .lead-item:last-child {
            border-bottom: none;
        }
        
        .lead-item:hover {
            background: #f8f9fa;
            transform: translateX(4px);
            box-shadow: 4px 0 12px rgba(0, 188, 117, 0.1);
        }
        
        .lead-item.blocked {
            opacity: 0.6;
            background: #f8f9fa;
        }
        
        .lead-item.blocked::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(255, 0, 0, 0.05) 10px,
                rgba(255, 0, 0, 0.05) 20px
            );
            pointer-events: none;
        }
        
        .lead-row {
            display: grid;
            grid-template-columns: 80px 1fr 150px 150px 120px 100px 100px 150px;
            gap: 1rem;
            padding: 1.5rem 2rem;
            align-items: center;
            min-height: 80px;
        }

        /* Colunas específicas */
        .col-id {
            text-align: center;
        }

        .lead-id {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-green);
            margin-bottom: 0.25rem;
        }
        
        .lead-status {
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-novo {
            background: #d4edda;
            color: #155724;
        }
        
        .status-em-andamento {
            background: #fff3cd;
            color: #856404;
        }

        .col-route {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .route-display {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .route-city {
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.8rem;
            text-align: center;
            min-width: 100px;
            background: #f8f9fa;
            color: var(--text-dark);
            border: 1px solid var(--border-color);
        }
        
        .route-arrow {
            color: var(--primary-green);
            font-size: 1.2rem;
        }
        
        .distance-info {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.25rem;
        }

        .col-vehicle {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .vehicle-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .vehicle-icon {
            font-size: 1.1rem;
            color: var(--primary-green);
        }
        
        .vehicle-details {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .col-client {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .client-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }
        
        .client-date {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .col-value {
            text-align: center;
        }
        
        .value-highlight {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.85rem;
            display: inline-block;
        }

        .value-na {
            color: var(--text-muted);
            font-size: 0.8rem;
            font-style: italic;
        }

        .col-date {
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .date-value {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }
        
        .date-relative {
            font-size: 0.75rem;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            font-weight: 500;
        }

        .date-today {
            background: #fff3cd;
            color: #856404;
        }

        .date-future {
            background: #d4edda;
            color: #155724;
        }

        .date-past {
            background: #f8d7da;
            color: #721c24;
        }

        .col-views {
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .views-count {
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        .views-count.limited {
            color: #dc3545;
        }
        
        .views-count.available {
            color: var(--primary-green);
        }
        
        .views-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .col-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            min-width: 100px;
            justify-content: center;
        }
        
        .btn-view {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
        }
        
        .btn-view:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
            color: white;
        }
        
        .btn-view:disabled,
        .btn-view.disabled {
            background: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .btn-admin {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }
        
        .btn-admin:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
            color: white;
        }

        .blocked-overlay {
            position: absolute;
            top: 0;
            right: 0;
            background: #dc3545;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0 12px 0 12px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 3;
        }

        /* Badges */
        .admin-badge {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .transportadora-badge {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Paginação */
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 3rem;
        }
        
        .pagination {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            padding: 0.5rem;
        }
        
        .pagination .page-link {
            color: var(--text-muted);
            border: none;
            padding: 0.75rem 1rem;
            margin: 0 0.25rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
            box-shadow: var(--shadow-sm);
        }
        
        .pagination .page-link:hover {
            background: var(--light-green);
            color: var(--primary-green);
            transform: translateY(-1px);
        }

        /* Estado vazio */
        .no-results {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }
        
        .no-results-icon {
            font-size: 4rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
            opacity: 0.5;
        }
        
        .no-results h4 {
            color: var(--text-dark);
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .no-results p {
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }

        /* Responsividade */
        @media (max-width: 1400px) {
            .lead-row {
                grid-template-columns: 70px 1fr 140px 140px 110px 90px 90px 140px;
                gap: 0.75rem;
                padding: 1.25rem 1.5rem;
            }

            .table-header-row {
                grid-template-columns: 70px 1fr 140px 140px 110px 90px 90px 140px;
                gap: 0.75rem;
                padding: 0 1.5rem;
            }
        }

        @media (max-width: 1200px) {
            .lead-row {
                grid-template-columns: 1fr;
                gap: 1rem;
                padding: 1.5rem;
            }

            .table-header-row {
                display: none;
            }

            .leads-table-header {
                display: none;
            }

            .col-id,
            .col-route,
            .col-vehicle,
            .col-client,
            .col-value,
            .col-date,
            .col-views,
            .col-actions {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem;
                border-bottom: 1px solid #f0f0f0;
                background: #fafafa;
                border-radius: 8px;
                margin-bottom: 0.5rem;
            }

            .col-id::before { content: "ID / Status:"; }
            .col-route::before { content: "Rota:"; }
            .col-vehicle::before { content: "Veículo:"; }
            .col-client::before { content: "Cliente:"; }
            .col-value::before { content: "Valor:"; }
            .col-date::before { content: "Data:"; }
            .col-views::before { content: "Visualizações:"; }
            .col-actions::before { content: "Ações:"; }

            .col-id::before,
            .col-route::before,
            .col-vehicle::before,
            .col-client::before,
            .col-value::before,
            .col-date::before,
            .col-views::before,
            .col-actions::before {
                font-weight: 600;
                color: var(--text-dark);
                font-size: 0.85rem;
            }

            .route-display {
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-end;
            }

            .col-actions {
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-end;
            }
        }
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .page-header {
                padding: 1.5rem;
                text-align: center;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .user-info {
                justify-content: center;
                margin-top: 1rem;
            }
            
            .filters-body {
                padding: 1.5rem;
            }
        }

        .sidebar-toggle {
            display: none;
            background: var(--primary-green);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-right: 1rem;
            font-weight: 600;
        }
        
        @media (max-width: 992px) {
            .sidebar-toggle {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
            }
        }

        /* Botões de ação */
        .btn-search {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .btn-search:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
            color: white;
        }
        
        .btn-clear {
            background: white;
            border: 2px solid var(--border-color);
            color: var(--text-muted);
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .btn-clear:hover {
            border-color: var(--text-muted);
            color: var(--text-dark);
            transform: translateY(-1px);
        }

        /* Resultados header */
        .results-header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .results-count {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .results-meta {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="logo">
                <img src="https://i.ibb.co/VcS31tMR/img-logo-portal-01.png" alt="Portal Cegonheiro">
            </a>
        </div>
        
        <nav style="padding: 20px 0;">
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-th-large"></i>
                Dashboard
            </a>
            
            <a href="leads_disponiveis.php" class="menu-item active">
                <i class="fas fa-users"></i>
                Leads Disponíveis
            </a>
            
            <?php if (!$eh_admin): ?>
                <a href="historico_leads.php" class="menu-item">
                    <i class="fas fa-history"></i>
                    Histórico
                </a>
            <?php endif; ?>
            
            <?php if ($eh_admin): ?>
                <a href="#" class="menu-item" onclick="alert('Em desenvolvimento')">
                    <i class="fas fa-chart-bar"></i>
                    Relatórios
                </a>
                <a href="#" class="menu-item" onclick="alert('Em desenvolvimento')">
                    <i class="fas fa-users-cog"></i>
                    Gerenciar Usuários
                </a>
                <a href="#" class="menu-item" onclick="alert('Em desenvolvimento')">
                    <i class="fas fa-building"></i>
                    Transportadoras
                </a>
            <?php endif; ?>
            
            <a href="editar_perfil.php" class="menu-item">
                <i class="fas fa-user-edit"></i>
                Meu Perfil
            </a>
            <a href="#" class="menu-item" onclick="alert('Em desenvolvimento')">
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
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <button class="sidebar-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                        Menu
                    </button>
                    <div class="page-subtitle">
                        <i class="fas fa-home"></i> Home > Leads Disponíveis
                    </div>
                    <h1 class="page-title">
                        <i class="fas fa-users"></i>
                        Leads Disponíveis
                    </h1>
                </div>
                <div class="user-info">
                    <div class="text-end d-none d-md-block">
                        <div style="font-weight: 600; color: var(--text-dark);">
                            <?php echo htmlspecialchars($nome_usuario); ?>
                        </div>
                        <?php if ($eh_admin): ?>
                            <div class="admin-badge">
                                <i class="fas fa-crown"></i>
                                Administrador
                            </div>
                        <?php else: ?>
                            <div class="transportadora-badge">
                                <i class="fas fa-truck"></i>
                                Transportadora
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($usuario['foto_perfil'] && file_exists($usuario['foto_perfil'])): ?>
                        <img src="<?php echo htmlspecialchars($usuario['foto_perfil'], ENT_QUOTES, 'UTF-8'); ?>" 
                             alt="Foto de Perfil" class="user-avatar">
                    <?php else: ?>
                        <div class="user-avatar-placeholder">
                            <?php echo strtoupper(substr($nome_usuario, 0, 2)); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Filtros Simplificados -->
        <div class="filters-section">
            <div class="filters-header">
                <h3 class="filters-title">
                    <i class="fas fa-filter"></i>
                    Filtros de Busca
                </h3>
            </div>
            
            <div class="filters-body">
                <form method="GET" action="leads_disponiveis.php" id="filterForm">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">
                                <i class="fas fa-map-marker-alt text-success"></i>
                                Cidade de Origem
                            </label>
                            <div class="autocomplete-container">
                                <input type="text" 
                                       class="form-control" 
                                       name="origem" 
                                       id="origem"
                                       value="<?php echo htmlspecialchars($filtro_origem, ENT_QUOTES, 'UTF-8'); ?>"
                                       placeholder="Digite a cidade de origem..."
                                       autocomplete="off">
                                <div class="autocomplete-suggestions" id="origem-suggestions"></div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">
                                <i class="fas fa-map-marker-alt text-danger"></i>
                                Cidade de Destino
                            </label>
                            <div class="autocomplete-container">
                                <input type="text" 
                                       class="form-control" 
                                       name="destino" 
                                       id="destino"
                                       value="<?php echo htmlspecialchars($filtro_destino, ENT_QUOTES, 'UTF-8'); ?>"
                                       placeholder="Digite a cidade de destino..."
                                       autocomplete="off">
                                <div class="autocomplete-suggestions" id="destino-suggestions"></div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">
                                <i class="fas fa-car"></i>
                                Tipo de Veículo
                            </label>
                            <select class="form-select" name="veiculo">
                                <option value="">Todos os tipos</option>
                                <option value="Carro" <?php echo ($filtro_veiculo === 'Carro') ? 'selected' : ''; ?>>Carro</option>
                                <option value="Moto" <?php echo ($filtro_veiculo === 'Moto') ? 'selected' : ''; ?>>Moto</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">
                                <i class="fas fa-ruler-horizontal"></i>
                                Distância (km)
                            </label>
                            <select class="form-select" name="distancia">
                                <option value="">Todas as distâncias</option>
                                <option value="0-500" <?php echo ($filtro_distancia === '0-500') ? 'selected' : ''; ?>>0 - 500 km</option>
                                <option value="501-1000" <?php echo ($filtro_distancia === '501-1000') ? 'selected' : ''; ?>>501 - 1000 km</option>
                                <option value="1001-1500" <?php echo ($filtro_distancia === '1001-1500') ? 'selected' : ''; ?>>1001 - 1500 km</option>
                                <option value="1501-2000" <?php echo ($filtro_distancia === '1501-2000') ? 'selected' : ''; ?>>1501 - 2000 km</option>
                                <option value="2001-2500" <?php echo ($filtro_distancia === '2001-2500') ? 'selected' : ''; ?>>2001 - 2500 km</option>
                                <option value="2501-3000" <?php echo ($filtro_distancia === '2501-3000') ? 'selected' : ''; ?>>2501 - 3000 km</option>
                                <option value="3000+" <?php echo ($filtro_distancia === '3000+') ? 'selected' : ''; ?>>Mais de 3000 km</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-3 justify-content-center">
                        <button type="submit" class="btn-search">
                            <i class="fas fa-search"></i>
                            Buscar Leads
                        </button>
                        
                        <a href="leads_disponiveis.php" class="btn-clear">
                            <i class="fas fa-times"></i>
                            Limpar Filtros
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Resultados -->
        <div class="results-header">
            <div class="results-count">
                <i class="fas fa-list"></i>
                <strong><?php echo number_format($total_leads, 0, ',', '.'); ?></strong> leads encontrados
                <?php if ($pagina > 1): ?>
                    <span class="results-meta">(Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?>)</span>
                <?php endif; ?>
            </div>
            <div class="results-meta">
                Mostrando <?php echo count($leads); ?> de <?php echo $total_leads; ?> resultados
                <?php if ($tem_filtros_ativos): ?>
                    <span class="badge bg-success ms-2">Filtrado</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cabeçalho da Tabela -->
        <?php if (!empty($leads)): ?>
            <div class="leads-table-header">
                <div class="table-header-row">
                    <div class="header-cell">
                        <i class="fas fa-hashtag"></i>
                        ID
                    </div>
                    <div class="header-cell">
                        <i class="fas fa-route"></i>
                        Rota
                    </div>
                    <div class="header-cell">
                        <i class="fas fa-car"></i>
                        Veículo
                    </div>
                    <div class="header-cell">
                        <i class="fas fa-user"></i>
                        Cliente
                    </div>
                    <div class="header-cell">
                        <i class="fas fa-dollar-sign"></i>
                        Valor
                    </div>
                    <div class="header-cell">
                        <i class="fas fa-calendar"></i>
                        Data
                    </div>
                    <div class="header-cell">
                        <i class="fas fa-eye"></i>
                        Views
                    </div>
                    <div class="header-cell">
                        <i class="fas fa-cog"></i>
                        Ações
                    </div>
                </div>
            </div>

            <!-- Container dos Leads -->
            <div class="leads-container">
                <div class="leads-list">
                    <?php foreach ($leads as $lead): ?>
                        <?php 
                        $distancia = calcularDistanciaAproximada($lead['cidade_origem'], $lead['cidade_destino']);
                        $vehicle_icons = [
                            'Carro' => 'fa-car',
                            'Moto' => 'fa-motorcycle'
                        ];
                        $icon = $vehicle_icons[$lead['tipo_veiculo']] ?? 'fa-car';
                        
                        $dias_restantes = ceil((strtotime($lead['data_prevista']) - time()) / (60 * 60 * 24));
                        $pode_visualizar = podeVisualizarLead($lead, $eh_admin);
                        $visualizacoes_restantes = max(0, 7 - $lead['total_visualizacoes']);
                        ?>
                        
                        <div class="lead-item <?php echo !$pode_visualizar ? 'blocked' : ''; ?>">
                            <?php if (!$pode_visualizar): ?>
                                <div class="blocked-overlay">
                                    <i class="fas fa-lock"></i>
                                    Bloqueado
                                </div>
                            <?php endif; ?>
                            
                            <div class="lead-row">
                                <!-- Coluna ID -->
                                <div class="col-id">
                                    <div class="lead-id">#<?php echo $lead['id']; ?></div>
                                    <span class="lead-status status-<?php echo str_replace('_', '-', $lead['status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $lead['status'])); ?>
                                    </span>
                                </div>
                                
                                <!-- Coluna Rota -->
                                <div class="col-route">
                                    <div class="route-display">
                                        <div class="route-city">
                                            <?php echo htmlspecialchars($lead['cidade_origem'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                        <div class="route-arrow">
                                            <i class="fas fa-arrow-right"></i>
                                        </div>
                                        <div class="route-city">
                                            <?php echo htmlspecialchars($lead['cidade_destino'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    </div>
                                    <div class="distance-info">
                                        <i class="fas fa-road"></i>
                                        <?php echo number_format($distancia, 0, ',', '.'); ?> km
                                    </div>
                                </div>
                                
                                <!-- Coluna Veículo -->
                                <div class="col-vehicle">
                                    <div class="vehicle-info">
                                        <i class="fas <?php echo $icon; ?> vehicle-icon"></i>
                                        <span><?php echo htmlspecialchars($lead['tipo_veiculo'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="vehicle-details">
                                        <?php echo htmlspecialchars($lead['ano_modelo'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                </div>
                                
                                <!-- Coluna Cliente -->
                                <div class="col-client">
                                    <div class="client-name">
                                        <?php echo htmlspecialchars($lead['nome'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <div class="client-date">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('d/m/Y H:i', strtotime($lead['data_cadastro'])); ?>
                                    </div>
                                </div>
                                
                                <!-- Coluna Valor -->
                                <div class="col-value">
                                    <?php if ($lead['valor_veiculo'] && $lead['valor_veiculo'] > 0): ?>
                                        <div class="value-highlight">
                                            R$ <?php echo number_format($lead['valor_veiculo'], 0, ',', '.'); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="value-na">
                                            Não informado
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Coluna Data -->
                                <div class="col-date">
                                    <div class="date-value">
                                        <?php echo date('d/m/Y', strtotime($lead['data_prevista'])); ?>
                                    </div>
                                    <div class="date-relative 
                                        <?php 
                                        if ($dias_restantes > 0) echo 'date-future';
                                        elseif ($dias_restantes == 0) echo 'date-today';
                                        else echo 'date-past';
                                        ?>">
                                        <?php if ($dias_restantes > 0): ?>
                                            em <?php echo $dias_restantes; ?> dias
                                        <?php elseif ($dias_restantes == 0): ?>
                                            hoje
                                        <?php else: ?>
                                            <?php echo abs($dias_restantes); ?> dias atrás
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Coluna Visualizações -->
                                <div class="col-views">
                                    <div class="views-count <?php echo ($lead['total_visualizacoes'] >= 7) ? 'limited' : 'available'; ?>">
                                        <?php echo $lead['total_visualizacoes']; ?>/7
                                    </div>
                                    <div class="views-label">
                                        <?php if ($lead['total_visualizacoes'] >= 7): ?>
                                            Esgotado
                                        <?php else: ?>
                                            <?php echo $visualizacoes_restantes; ?> restantes
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Coluna Ações -->
                                <div class="col-actions">
                                    <?php if ($pode_visualizar): ?>
                                        <a href="lead_detalhes.php?id=<?php echo $lead['id']; ?>" class="btn-action btn-view">
                                            <i class="fas fa-eye"></i>
                                            Ver Detalhes
                                        </a>
                                    <?php else: ?>
                                        <button class="btn-action btn-view disabled" disabled title="Limite de visualizações atingido">
                                            <i class="fas fa-lock"></i>
                                            Bloqueado
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($eh_admin): ?>
                                        <a href="#" class="btn-action btn-admin" onclick="alert('Gerenciar lead em desenvolvimento')">
                                            <i class="fas fa-cog"></i>
                                            Gerenciar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Paginação -->
            <?php if ($total_paginas > 1): ?>
                <div class="pagination-container">
                    <nav>
                        <ul class="pagination">
                            <?php if ($pagina > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>" title="Primeira página">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>" title="Página anterior">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $inicio = max(1, $pagina - 2);
                            $fim = min($total_paginas, $pagina + 2);
                            
                            if ($inicio > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['pagina' => 1])) . '">1</a></li>';
                                if ($inicio > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $inicio; $i <= $fim; $i++):
                            ?>
                                <li class="page-item <?php echo ($i === $pagina) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php 
                            endfor;
                            
                            if ($fim < $total_paginas) {
                                if ($fim < $total_paginas - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['pagina' => $total_paginas])) . '">' . $total_paginas . '</a></li>';
                            }
                            ?>
                            
                            <?php if ($pagina < $total_paginas): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>" title="Próxima página">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $total_paginas])); ?>" title="Última página">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Estado vazio -->
            <div class="no-results">
                <div class="no-results-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h4>Nenhum lead encontrado</h4>
                <p>Tente ajustar os filtros de busca para encontrar mais resultados.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="leads_disponiveis.php" class="btn-search">
                        <i class="fas fa-refresh"></i>
                        Ver Todos os Leads
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Array de todas as cidades para autocomplete
        const todasCidades = <?php echo json_encode($todas_cidades); ?>;

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Função de autocomplete
        function setupAutocomplete(inputId, suggestionsId) {
            const input = document.getElementById(inputId);
            const suggestions = document.getElementById(suggestionsId);
            let selectedIndex = -1;

            input.addEventListener('input', function() {
                const value = this.value.toLowerCase();
                suggestions.innerHTML = '';
                selectedIndex = -1;

                if (value.length < 2) {
                    suggestions.style.display = 'none';
                    return;
                }

                const filteredCities = todasCidades.filter(city => 
                    city.toLowerCase().includes(value)
                ).slice(0, 10);

                if (filteredCities.length > 0) {
                    filteredCities.forEach((city, index) => {
                        const div = document.createElement('div');
                        div.className = 'autocomplete-suggestion';
                        div.textContent = city;
                        div.addEventListener('click', function() {
                            input.value = city;
                            suggestions.style.display = 'none';
                        });
                        suggestions.appendChild(div);
                    });
                    suggestions.style.display = 'block';
                } else {
                    suggestions.style.display = 'none';
                }
            });

            input.addEventListener('keydown', function(e) {
                const items = suggestions.querySelectorAll('.autocomplete-suggestion');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                    updateSelection(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, -1);
                    updateSelection(items);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (selectedIndex >= 0 && items[selectedIndex]) {
                        input.value = items[selectedIndex].textContent;
                        suggestions.style.display = 'none';
                    }
                } else if (e.key === 'Escape') {
                    suggestions.style.display = 'none';
                    selectedIndex = -1;
                }
            });

            function updateSelection(items) {
                items.forEach((item, index) => {
                    item.classList.toggle('selected', index === selectedIndex);
                });
            }

            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !suggestions.contains(e.target)) {
                    suggestions.style.display = 'none';
                }
            });
        }

        setupAutocomplete('origem', 'origem-suggestions');
        setupAutocomplete('destino', 'destino-suggestions');

        function registrarVisualizacao(leadId) {
            console.log('Registrando visualização para lead:', leadId);
            
            fetch('registrar_visualizacao.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    lead_id: leadId,
                    timestamp: new Date().toISOString()
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Resposta do servidor:', data);
                if (data.success) {
                    console.log('Visualização registrada com sucesso');
                } else {
                    console.error('Erro ao registrar visualização:', data.error);
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
            });
        }

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

        document.querySelectorAll('.pagination .page-link').forEach(link => {
            link.addEventListener('click', function() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });

        document.getElementById('filterForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('.btn-search');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
            submitBtn.disabled = true;
            
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });

        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.lead-item').forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(20px)';
            item.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
            observer.observe(item);
        });

        document.querySelectorAll('.btn-view:not(.disabled)').forEach(button => {
            button.addEventListener('click', function(e) {
                const leadItem = this.closest('.lead-item');
                const leadId = leadItem.querySelector('.lead-id').textContent.replace('#', '');
                
                registrarVisualizacao(leadId);
            });
        });

        document.addEventListener('keydown', function(e) {
                if (!e.ctrlKey && !e.altKey && !e.metaKey) {
                    e.preventDefault();
                    const firstFilter = document.getElementById('origem');
                    if (firstFilter) {
                        firstFilter.focus();
                    }
                }
            }
            
            if (e.key === 'Escape') {
                const hasActiveFilters = <?php echo $tem_filtros_ativos ? 'true' : 'false'; ?>;
                if (hasActiveFilters) {
                    window.location.href = 'leads_disponiveis.php';
                }
            }
        });

        document.querySelectorAll('.btn-action').forEach(button => {
            button.addEventListener('focus', function() {
                this.closest('.lead-item').style.outline = '2px solid var(--primary-green)';
                this.closest('.lead-item').style.outlineOffset = '2px';
            });
            
            button.addEventListener('blur', function() {
                this.closest('.lead-item').style.outline = 'none';
            });
        });

        document.querySelectorAll('.lead-item.blocked').forEach(item => {
            item.addEventListener('click', function(e) {
                if (!e.target.closest('.btn-action')) {
                    e.preventDefault();
                    alert('Este lead atingiu o limite de 7 visualizações por transportadoras diferentes.');
                }
            });
        });

        document.querySelectorAll('.lead-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                if (!this.classList.contains('blocked')) {
                    this.style.borderLeft = '4px solid var(--primary-green)';
                }
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.borderLeft = 'none';
            });
        });

        window.addEventListener('load', function() {
            const loadTime = performance.now();
            console.log('✅ Página carregada em:', Math.round(loadTime), 'ms');
            console.log('📊 Total de leads:', <?php echo $total_leads; ?>);
            console.log('📄 Página atual:', <?php echo $pagina; ?>);
            console.log('👤 Tipo de usuário:', '<?php echo $eh_admin ? "Administrador" : "Transportadora"; ?>');
            console.log('🔍 Filtros ativos:', <?php echo $tem_filtros_ativos ? 'true' : 'false'; ?>);
            console.log('🏙️ Total de cidades:', todasCidades.length);
        });

        console.log('Sistema de visualizações carregado');
        console.log('Total de leads na página:', document.querySelectorAll('.lead-item').length);
        console.log('Layout em colunas ativado');
        console.log('Autocomplete ativado para', todasCidades.length, 'cidades');
    </script>
</body>
</html>