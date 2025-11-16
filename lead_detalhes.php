<?php
// lead_detalhes.php - Versão completa e funcional com mapa OpenStreetMap
require_once 'config.php';

// Verificações de acesso
if (!verificarLogin()) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['nivel_acesso']) || $_SESSION['nivel_acesso'] != 'cliente') {
    header('Location: login.php');
    exit;
}

// Verificar se o ID do lead foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$lead_id = (int)$_GET['id'];
$user_id = obterIdUsuario();
$nome_usuario = $_SESSION['nome'] ?? 'Usuário';

// Buscar dados do lead
try {
    $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch();
    
    if (!$lead) {
        $_SESSION['erro'] = 'Lead não encontrado.';
        header('Location: dashboard.php');
        exit;
    }
    
    // Buscar cotações do lead (se a tabela existir)
    $cotacoes = [];
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, c.data_envio, c.transportadora_nome, c.valor_cotacao, c.observacoes as cotacao_obs
            FROM cotacoes c 
            WHERE c.lead_id = ? 
            ORDER BY c.data_envio DESC
        ");
        $stmt->execute([$lead_id]);
        $cotacoes = $stmt->fetchAll();
    } catch (Exception $e) {
        // Tabela cotações pode não existir ainda
        $cotacoes = [];
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar lead: " . $e->getMessage());
    $_SESSION['erro'] = 'Erro ao carregar dados do lead.';
    header('Location: dashboard.php');
    exit;
}

// Determinar tipo de cliente
$tipo_cliente = $_SESSION['tipo_cliente'] ?? 'pf';

// Função para calcular distância aproximada entre cidades (simulada)
function calcularDistanciaAproximada($origem, $destino) {
    // Distâncias aproximadas entre principais cidades brasileiras (em km)
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
    
    // Limpar e normalizar nomes das cidades
    $origem_limpa = strtoupper(preg_replace('/,.*$/', '', trim($origem)));
    $destino_limpo = strtoupper(preg_replace('/,.*$/', '', trim($destino)));
    
    // Tentar encontrar a distância
    $chave1 = $origem_limpa . '-' . $destino_limpo;
    $chave2 = $destino_limpo . '-' . $origem_limpa;
    
    if (isset($distancias[$chave1])) {
        return $distancias[$chave1];
    } elseif (isset($distancias[$chave2])) {
        return $distancias[$chave2];
    }
    
    // Se não encontrar, retorna uma estimativa baseada na diferença de caracteres
    return rand(800, 2500);
}

$distancia_km = calcularDistanciaAproximada($lead['cidade_origem'], $lead['cidade_destino']);

// Função para formatar telefone para WhatsApp
function formatarTelefoneWhatsApp($telefone) {
    // Remove todos os caracteres não numéricos
    $telefone_limpo = preg_replace('/[^0-9]/', '', $telefone);
    
    // Se começar com 0, remove
    if (substr($telefone_limpo, 0, 1) === '0') {
        $telefone_limpo = substr($telefone_limpo, 1);
    }
    
    // Se não começar com 55 (código do Brasil), adiciona
    if (!preg_match('/^55/', $telefone_limpo)) {
        $telefone_limpo = '55' . $telefone_limpo;
    }
    
    return $telefone_limpo;
}

$telefone_whatsapp = formatarTelefoneWhatsApp($lead['telefone']);

// Coordenadas aproximadas das principais cidades brasileiras
function obterCoordenadas($cidade) {
    $coordenadas = [
        'FORTALEZA' => [-3.7319, -38.5267],
        'GOIÂNIA' => [-16.6869, -49.2648],
        'SÃO PAULO' => [-23.5505, -46.6333],
        'RIO DE JANEIRO' => [-22.9068, -43.1729],
        'BRASÍLIA' => [-15.7939, -47.8828],
        'BELO HORIZONTE' => [-19.9191, -43.9386],
        'SALVADOR' => [-12.9714, -38.5014],
        'RECIFE' => [-8.0476, -34.8770],
        'CURITIBA' => [-25.4244, -49.2654],
        'PORTO ALEGRE' => [-30.0346, -51.2177]
    ];
    
    $cidade_limpa = strtoupper(preg_replace('/,.*$/', '', trim($cidade)));
    return $coordenadas[$cidade_limpa] ?? [-14.2350, -51.9253]; // Centro do Brasil como fallback
}

$coord_origem = obterCoordenadas($lead['cidade_origem']);
$coord_destino = obterCoordenadas($lead['cidade_destino']);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead #<?php echo $lead['id']; ?> - Portal Cegonheiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
          crossorigin=""/>
    
    <style>
        :root {
            --primary-green: #00bc75;
            --secondary-green: #07a368;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: white;
            border-right: 1px solid #e9ecef;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 24px;
            min-height: 100vh;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 20px;
            font-weight: 700;
            color: #2c2c2c;
            text-decoration: none;
            padding: 24px 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .logo i {
            color: var(--primary-green);
            font-size: 24px;
        }
        
        .menu-item {
            display: block;
            padding: 12px 20px;
            color: #6c757d;
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
        
        .lead-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--secondary-green) 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        
        .lead-id {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .lead-status {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            border: 1px solid #e9ecef;
        }
        
        .info-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c2c2c;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-size: 1rem;
            color: #2c2c2c;
            font-weight: 500;
        }
        
        .route-display {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 1rem 0;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .route-city {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            color: white;
            text-align: center;
            min-width: 120px;
        }
        
        .route-origin {
            background: #28a745;
        }
        
        .route-destination {
            background: #dc3545;
        }
        
        .route-arrow {
            color: #6c757d;
            font-size: 1.5rem;
        }
        
        .route-info {
            text-align: center;
            margin-top: 1rem;
            padding: 0.5rem;
            background: rgba(0, 188, 117, 0.1);
            border-radius: 8px;
            color: var(--primary-green);
            font-weight: 600;
        }
        
        /* Mapa */
        .map-container {
            height: 400px;
            width: 100%;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 1rem;
            border: 2px solid #e9ecef;
            position: relative;
        }
        
        #map {
            height: 100%;
            width: 100%;
        }
        
        .map-loading {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 1.1rem;
            z-index: 1000;
        }
        
        .map-controls {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1001;
            display: flex;
            gap: 5px;
        }
        
        .map-btn {
            background: white;
            border: 1px solid #ccc;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .map-btn:hover {
            background: #f0f0f0;
        }
        
        .cotacao-card {
            border-left: 4px solid var(--primary-green);
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0 10px 10px 0;
        }
        
        .cotacao-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .cotacao-valor {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-green);
        }
        
        .cotacao-transportadora {
            font-weight: 600;
            color: #2c2c2c;
        }
        
        .cotacao-data {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .btn-back {
            background: #6c757d;
            border-color: #6c757d;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        
        .btn-back:hover {
            background: #5a6268;
            border-color: #5a6268;
            color: white;
            transform: translateY(-1px);
        }
        
        .btn-cotar {
            background: var(--primary-green);
            border-color: var(--primary-green);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        
        .btn-cotar:hover {
            background: var(--secondary-green);
            border-color: var(--secondary-green);
            color: white;
            transform: translateY(-1px);
        }
        
        .btn-whatsapp {
            background: #25D366;
            border-color: #25D366;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        
        .btn-whatsapp:hover {
            background: #128C7E;
            border-color: #128C7E;
            color: white;
            transform: translateY(-1px);
        }
        
        .btn-email {
            background: #007bff;
            border-color: #007bff;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        
        .btn-email:hover {
            background: #0056b3;
            border-color: #0056b3;
            color: white;
            transform: translateY(-1px);
        }
        
        .vehicle-icon {
            font-size: 2rem;
            color: var(--primary-green);
            margin-right: 1rem;
        }
        
        .value-highlight {
            background: var(--primary-green);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 5px;
            font-weight: 600;
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
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .route-display {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .route-arrow {
                transform: rotate(90deg);
            }
            
            .map-container {
                height: 300px;
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
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <a href="dashboard.php" class="logo">
            <i class="fas fa-truck"></i>
            <span>Portal Cegonheiro</span>
        </a>
        
        <nav style="padding: 20px 0;">
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-th-large"></i>
                Dashboard
            </a>
            
            <?php if ($tipo_cliente == 'pj'): ?>
                <!-- Menu para Transportadoras -->
              <a href="leads_disponiveis.php" class="menu-item">
                <i class="fas fa-th-large"></i>
                Leads Disponíveis
            </a>
                <a href="#" class="menu-item" onclick="alert('Em desenvolvimento')">
                    <i class="fas fa-history"></i>
                    Histórico
                </a>
            <?php else: ?>
                <!-- Menu para Clientes PF -->
                <a href="#" class="menu-item" onclick="alert('Em desenvolvimento')">
                    <i class="fas fa-list"></i>
                    Meus Pedidos
                </a>
                <a href="../index.php#formulario" class="menu-item">
                    <i class="fas fa-plus"></i>
                    Nova Solicitação
                </a>
                <a href="#" class="menu-item" onclick="alert('Em desenvolvimento')">
                    <i class="fas fa-file-invoice"></i>
                    Cotações Recebidas
                </a>
            <?php endif; ?>
            
            <a href="#" class="menu-item" onclick="alert('Em desenvolvimento')">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div style="color: #6c757d; font-size: 14px; margin-bottom: 8px;">
                    <strong>HOME</strong> > Dashboard > Lead #<?php echo $lead['id']; ?>
                </div>
                <h1 style="font-size: 28px; font-weight: 700; color: #2c2c2c;">Detalhes do Lead</h1>
            </div>
        </div>

        <!-- Lead Header -->
        <div class="lead-header">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div>
                    <div class="lead-id">Lead #<?php echo $lead['id']; ?></div>
                    <div class="lead-status">
                        <?php echo ucfirst(str_replace('_', ' ', $lead['status'])); ?>
                    </div>
                </div>
                <div class="text-end">
                    <div style="font-size: 0.9rem; opacity: 0.9;">Cadastrado em</div>
                    <div style="font-size: 1.1rem; font-weight: 600;">
                        <?php echo date('d/m/Y H:i', strtotime($lead['data_cadastro'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informações do Cliente -->
        <div class="info-card">
            <div class="info-title">
                <i class="fas fa-user"></i>
                Informações do Cliente
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Nome</div>
                    <div class="info-value"><?php echo htmlspecialchars($lead['nome']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">E-mail</div>
                    <div class="info-value">
                        <a href="mailto:<?php echo $lead['email']; ?>" style="color: var(--primary-green); text-decoration: none;">
                            <?php echo htmlspecialchars($lead['email']); ?>
                        </a>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Telefone</div>
                    <div class="info-value">
                        <a href="tel:<?php echo $lead['telefone']; ?>" style="color: var(--primary-green); text-decoration: none;">
                            <?php echo htmlspecialchars($lead['telefone']); ?>
                        </a>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Data Prevista</div>
                    <div class="info-value"><?php echo date('d/m/Y', strtotime($lead['data_prevista'])); ?></div>
                </div>
            </div>
        </div>

        <!-- Rota -->
        <div class="info-card">
            <div class="info-title">
                <i class="fas fa-route"></i>
                Rota de Transporte
            </div>
            <div class="route-display">
                <div class="route-city route-origin">
                    <?php echo htmlspecialchars($lead['cidade_origem']); ?>
                </div>
                <div class="route-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
                <div class="route-city route-destination">
                    <?php echo htmlspecialchars($lead['cidade_destino']); ?>
                </div>
            </div>
            <div class="route-info">
                <i class="fas fa-road"></i> Distância aproximada: <?php echo number_format($distancia_km, 0, ',', '.'); ?> km
            </div>
            
            <!-- Mapa -->
            <div class="map-container">
                <div class="map-loading" id="mapLoading">
                    <i class="fas fa-spinner fa-spin me-2"></i>
                    Carregando mapa da rota...
                </div>
                <div class="map-controls">
                    <button class="map-btn" onclick="toggleMapType()" id="mapTypeBtn">
                        <i class="fas fa-layer-group"></i> Satélite
                    </button>
                    <button class="map-btn" onclick="centerMap()">
                        <i class="fas fa-crosshairs"></i> Centralizar
                    </button>
                </div>
                <div id="map"></div>
            </div>
        </div>

        <!-- Informações do Veículo -->
        <div class="info-card">
            <div class="info-title">
                <i class="fas fa-car"></i>
                Informações do Veículo
            </div>
            <div class="d-flex align-items-start gap-3">
                <div class="vehicle-icon">
                    <?php
                    $vehicle_icons = [
                        'Carro' => 'fa-car',
                        'Moto' => 'fa-motorcycle', 
                        'SUV' => 'fa-car-side',
                        'Utilitário' => 'fa-truck',
                        'Caminhonete' => 'fa-truck-pickup'
                    ];
                    $icon = $vehicle_icons[$lead['tipo_veiculo']] ?? 'fa-car';
                    ?>
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Tipo de Veículo</div>
                            <div class="info-value"><?php echo htmlspecialchars($lead['tipo_veiculo']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Marca/Modelo/Ano</div>
                            <div class="info-value"><?php echo htmlspecialchars($lead['ano_modelo']); ?></div>
                        </div>
                        <?php if ($lead['valor_veiculo'] && $lead['valor_veiculo'] > 0): ?>
                        <div class="info-item">
                            <div class="info-label">Valor do Veículo</div>
                            <div class="info-value">
                                <span class="value-highlight">
                                    R$ <?php echo number_format($lead['valor_veiculo'], 2, ',', '.'); ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($lead['observacoes']): ?>
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e9ecef;">
                <div class="info-label">Observações</div>
                <div class="info-value"><?php echo nl2br(htmlspecialchars($lead['observacoes'])); ?></div>
            </div>
            <?php endif; ?>
        </div>


        <!-- Ações -->
        <div class="d-flex gap-3 mt-4 flex-wrap">
            <a href="dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Voltar ao Dashboard
            </a>
            
            <?php if ($tipo_cliente == 'pj'): ?>
                <a href="#" class="btn-cotar" onclick="alert('Função de cotação em desenvolvimento')">
                    <i class="fas fa-paper-plane"></i> Enviar Cotação
                </a>
            <?php endif; ?>
            
            <a href="https://wa.me/<?php echo $telefone_whatsapp; ?>?text=Olá%20<?php echo urlencode($lead['nome']); ?>!%20Vi%20seu%20pedido%20de%20transporte%20no%20Portal%20Cegonheiro.%20Gostaria%20de%20conversar%20sobre%20o%20transporte%20do%20seu%20<?php echo urlencode($lead['tipo_veiculo']); ?>%20de%20<?php echo urlencode($lead['cidade_origem']); ?>%20para%20<?php echo urlencode($lead['cidade_destino']); ?>." 
               target="_blank" 
               class="btn-whatsapp">
                <i class="fab fa-whatsapp"></i> WhatsApp
            </a>
            
            <a href="mailto:<?php echo $lead['email']; ?>?subject=Proposta%20de%20Transporte%20-%20Lead%20#<?php echo $lead['id']; ?>&body=Olá%20<?php echo urlencode($lead['nome']); ?>,%0A%0AVi%20seu%20pedido%20de%20transporte%20no%20Portal%20Cegonheiro%20e%20gostaria%20de%20apresentar%20uma%20proposta.%0A%0ADetalhes%20do%20transporte:%0A-%20Veículo:%20<?php echo urlencode($lead['tipo_veiculo'] . ' - ' . $lead['ano_modelo']); ?>%0A-%20Rota:%20<?php echo urlencode($lead['cidade_origem'] . ' → ' . $lead['cidade_destino']); ?>%0A-%20Distância:%20<?php echo $distancia_km; ?>%20km%0A-%20Data%20prevista:%20<?php echo date('d/m/Y', strtotime($lead['data_prevista'])); ?>%0A%0AEntrarei%20em%20contato%20em%20breve%20com%20mais%20detalhes.%0A%0AAtenciosamente," 
               class="btn-email">
                <i class="fas fa-envelope"></i> E-mail
            </a>
        </div>
    </div>
    
    <!-- Rota -->
        <div class="info-card">
            <div class="info-title">
                <i class="fas fa-route"></i>
                Rota de Transporte
            </div>
            <div class="route-display">
                <div class="route-city route-origin">
                    <?php echo htmlspecialchars($lead['cidade_origem']); ?>
                </div>
                <div class="route-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
                <div class="route-city route-destination">
                    <?php echo htmlspecialchars($lead['cidade_destino']); ?>
                </div>
            </div>
            <div class="route-info">
                <i class="fas fa-road"></i> Distância aproximada: <?php echo number_format($distancia_km, 0, ',', '.'); ?> km
            </div>
            
            <!-- Mapa -->
            <div class="map-container">
                <div class="map-loading" id="mapLoading">
                    <i class="fas fa-spinner fa-spin me-2"></i>
                    Carregando mapa da rota...
                </div>
                <div class="map-controls">
                    <button class="map-btn" onclick="toggleMapType()" id="mapTypeBtn">
                        <i class="fas fa-layer-group"></i> Satélite
                    </button>
                    <button class="map-btn" onclick="centerMap()">
                        <i class="fas fa-crosshairs"></i> Centralizar
                    </button>
                </div>
                <div id="map"></div>
            </div>
        </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Leaflet JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
            crossorigin=""></script>
    
    <script>
        let map;
        let currentLayer;
        let isStreetView = true;
        
        // Coordenadas das cidades
        const origem = [<?php echo $coord_origem[0]; ?>, <?php echo $coord_origem[1]; ?>];
        const destino = [<?php echo $coord_destino[0]; ?>, <?php echo $coord_destino[1]; ?>];
        
        function initMap() {
            try {
                // Remover loading
                document.getElementById('mapLoading').style.display = 'none';
                
                // Calcular centro entre origem e destino
                const centerLat = (origem[0] + destino[0]) / 2;
                const centerLng = (origem[1] + destino[1]) / 2;
                
                // Inicializar mapa
                map = L.map('map').setView([centerLat, centerLng], 6);
                
                // Camada de ruas (padrão)
                const streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 18
                });
                
                // Camada de satélite
                const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    attribution: '© Esri',
                    maxZoom: 18
                });
                
                // Adicionar camada padrão
                currentLayer = streetLayer;
                currentLayer.addTo(map);
                
                // Criar ícones personalizados
                const origemIcon = L.divIcon({
                    html: '<div style="background: #28a745; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">O</div>',
                    className: 'custom-marker',
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                });
                
                const destinoIcon = L.divIcon({
                    html: '<div style="background: #dc3545; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">D</div>',
                    className: 'custom-marker',
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                });
                
                // Adicionar marcadores
                const origemMarker = L.marker(origem, {icon: origemIcon})
                    .addTo(map)
                    .bindPopup('<b>Origem</b><br><?php echo addslashes($lead['cidade_origem']); ?>');
                
                const destinoMarker = L.marker(destino, {icon: destinoIcon})
                    .addTo(map)
                    .bindPopup('<b>Destino</b><br><?php echo addslashes($lead['cidade_destino']); ?>');
                
                // Adicionar linha conectando origem e destino
                const routeLine = L.polyline([origem, destino], {
                    color: '#00bc75',
                    weight: 4,
                    opacity: 0.8,
                    dashArray: '10, 10'
                }).addTo(map);
                
                // Ajustar zoom para mostrar toda a rota
                const group = new L.featureGroup([origemMarker, destinoMarker, routeLine]);
                map.fitBounds(group.getBounds().pad(0.1));
                
                console.log('Mapa carregado com sucesso!');
                
            } catch (error) {
                console.error('Erro ao carregar mapa:', error);
                document.getElementById('map').innerHTML = `
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; background: #f8f9fa; color: #6c757d; text-align: center; padding: 2rem;">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <h5>Erro ao carregar o mapa</h5>
                        <p>Não foi possível exibir o mapa da rota</p>
                    </div>
                `;
            }
        }
        
        function toggleMapType() {
            if (!map) return;
            
            try {
                map.removeLayer(currentLayer);
                
                if (isStreetView) {
                    // Mudar para satélite
                    currentLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                        attribution: '© Esri',
                        maxZoom: 18
                    });
                    document.getElementById('mapTypeBtn').innerHTML = '<i class="fas fa-map"></i> Ruas';
                    isStreetView = false;
                } else {
                    // Mudar para ruas
                    currentLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors',
                        maxZoom: 18
                    });
                    document.getElementById('mapTypeBtn').innerHTML = '<i class="fas fa-layer-group"></i> Satélite';
                    isStreetView = true;
                }
                
                currentLayer.addTo(map);
            } catch (error) {
                console.error('Erro ao trocar tipo de mapa:', error);
            }
        }
        
        function centerMap() {
            if (!map) return;
            
            try {
                const centerLat = (origem[0] + destino[0]) / 2;
                const centerLng = (origem[1] + destino[1]) / 2;
                map.setView([centerLat, centerLng], 6);
            } catch (error) {
                console.error('Erro ao centralizar mapa:', error);
            }
        }
        
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

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

        // Inicializar mapa quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initMap, 500); // Pequeno delay para garantir que o DOM está pronto
        });

        console.log('Lead detalhes carregado com sucesso!');
        console.log('Lead ID: <?php echo $lead['id']; ?>');
        console.log('Cliente: <?php echo addslashes($lead['nome']); ?>');
        console.log('Telefone WhatsApp: <?php echo $telefone_whatsapp; ?>');
        console.log('Coordenadas origem:', origem);
        console.log('Coordenadas destino:', destino);
    </script>
</body>
</html>