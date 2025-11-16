<?php
// config.php - Configuração completa e corrigida
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'joaocr74_cegonha');
define('DB_USER', 'joaocr74_lima');
define('DB_PASS', 'davi@2025');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    error_log("Erro na conexão: " . $e->getMessage());
    die("Erro de conexão com o banco de dados.");
}

// Configurações do sistema
$configuracoes = [
    'site_nome' => 'Portal Cegonheiro',
    'site_email' => 'contato@portalcegonheiro.com.br',
    'site_telefone' => '(85) 98583-2583',
    'debug_mode' => '1'
];

// Funções básicas
function verificarLogin() {
    return (isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id'])) || 
           (isset($_SESSION['user_id']) && !empty($_SESSION['user_id']));
}

function obterIdUsuario() {
    if (isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id'])) {
        return $_SESSION['usuario_id'];
    } elseif (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }
    return null;
}

function obterDadosCliente($user_id, $pdo) {
    try {
        // Primeiro, buscar dados básicos do usuário
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            return null;
        }
        
        // Se a tabela usuarios tem as colunas tipo_cliente e nivel_acesso, usar elas
        if (isset($usuario['tipo_cliente']) && isset($usuario['nivel_acesso'])) {
            return [
                'tipo' => $usuario['tipo_cliente'],
                'usuario' => $usuario,
                'dados' => $usuario
            ];
        }
        
        // Verificar se é PF ou PJ baseado na existência de registros (fallback)
        $stmt = $pdo->prepare("SELECT * FROM clientes_pf WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        $cliente_pf = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cliente_pf) {
            return [
                'tipo' => 'pf',
                'usuario' => $usuario,
                'dados' => $cliente_pf
            ];
        }
        
        // Verificar se é PJ
        $stmt = $pdo->prepare("SELECT * FROM empresas WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($empresa) {
            return [
                'tipo' => 'pj',
                'usuario' => $usuario,
                'dados' => $empresa
            ];
        }
        
        // Se não encontrou nem PF nem PJ, retorna apenas os dados do usuário
        return [
            'tipo' => 'indefinido',
            'usuario' => $usuario,
            'dados' => null
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao obter dados do cliente: " . $e->getMessage());
        return null;
    }
}

function obterConfiguracoes($chave = null) {
    global $configuracoes;
    
    if ($chave) {
        return $configuracoes[$chave] ?? null;
    }
    
    return $configuracoes;
}

// Funções auxiliares para compatibilidade com o sistema de visualizações
function ehAdmin() {
    $nivel = $_SESSION['nivel_acesso'] ?? null;
    debugLog("Verificando se é admin. Nível: $nivel");
    
    // Aceitar tanto 'admin' quanto 'administrador'
    return $nivel === 'admin' || $nivel === 'administrador';
}

function ehTransportadora() {
    $nivel = $_SESSION['nivel_acesso'] ?? null;
    $tipo = $_SESSION['tipo_cliente'] ?? null;
    debugLog("Verificando se é transportadora. Nível: $nivel, Tipo: $tipo");
    return $nivel === 'cliente' && $tipo === 'pj';
}

function obterNomeUsuario() {
    return $_SESSION['nome'] ?? 'Usuário';
}

function obterNivelAcesso() {
    return $_SESSION['nivel_acesso'] ?? 'cliente';
}

function obterTipoCliente() {
    return $_SESSION['tipo_cliente'] ?? 'pf';
}

// Função para debug
function debugLog($message) {
    if (obterConfiguracoes('debug_mode') === '1') {
        error_log("DEBUG: " . $message);
    }
}

// Função para verificar se usuário pode ver leads
function podeVerLeads() {
    $pode = ehAdmin() || ehTransportadora();
    debugLog("Pode ver leads: " . ($pode ? 'SIM' : 'NÃO'));
    return $pode;
}

// Função para contar visualizações de um lead
function contarVisualizacoesLead($lead_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as total FROM lead_views WHERE lead_id = ?");
        $stmt->execute([$lead_id]);
        $result = $stmt->fetch();
        return (int)$result['total'];
    } catch (Exception $e) {
        error_log("Erro ao contar visualizações: " . $e->getMessage());
        return 0;
    }
}

// Função para verificar se usuário já visualizou um lead
function jaVisualizouLead($lead_id, $user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id FROM lead_views WHERE lead_id = ? AND user_id = ?");
        $stmt->execute([$lead_id, $user_id]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        error_log("Erro ao verificar visualização: " . $e->getMessage());
        return false;
    }
}

// Função para registrar visualização de lead
function registrarVisualizacaoLead($lead_id, $user_id) {
    global $pdo;
    try {
        // Verificar se já visualizou
        if (jaVisualizouLead($lead_id, $user_id)) {
            debugLog("Usuário $user_id já visualizou lead $lead_id");
            return true; // Já visualizou, não precisa registrar novamente
        }
        
        // Verificar se ainda pode visualizar (limite de 7)
        $total_views = contarVisualizacoesLead($lead_id);
        if ($total_views >= 7) {
            debugLog("Lead $lead_id atingiu limite de 7 visualizações");
            return false; // Limite atingido
        }
        
        // Registrar visualização
        $stmt = $pdo->prepare("INSERT INTO lead_views (lead_id, user_id, view_timestamp) VALUES (?, ?, NOW())");
        $resultado = $stmt->execute([$lead_id, $user_id]);
        
        if ($resultado) {
            debugLog("Visualização registrada: Lead $lead_id, Usuário $user_id");
        }
        
        return $resultado;
        
    } catch (Exception $e) {
        error_log("Erro ao registrar visualização: " . $e->getMessage());
        return false;
    }
}

// Função para carregar dados do usuário na sessão (para uso no login)
function carregarDadosUsuarioSessao($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['usuario_id'] = $usuario['id']; // Compatibilidade
            $_SESSION['nome'] = $usuario['nome'];
            $_SESSION['email'] = $usuario['email'];
            $_SESSION['nivel_acesso'] = $usuario['nivel_acesso'] ?? 'cliente';
            $_SESSION['tipo_cliente'] = $usuario['tipo_cliente'] ?? 'pf';
            
            debugLog("Dados do usuário carregados na sessão: ID={$usuario['id']}, Nome={$usuario['nome']}, Nível={$_SESSION['nivel_acesso']}, Tipo={$_SESSION['tipo_cliente']}");
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Erro ao carregar dados do usuário: " . $e->getMessage());
        return false;
    }
}

// Função para verificar sessão e recarregar dados se necessário
function verificarSessaoCompleta() {
    if (verificarLogin()) {
        $user_id = obterIdUsuario();
        
        // Se não tem dados completos na sessão, recarregar
        if (!isset($_SESSION['nivel_acesso']) || !isset($_SESSION['tipo_cliente'])) {
            debugLog("Dados incompletos na sessão, recarregando...");
            return carregarDadosUsuarioSessao($user_id);
        }
        
        return true;
    }
    
    return false;
}

// Função para verificar se tabela existe
function tabelaExiste($tabela) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tabela]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Função para verificar se coluna existe
function colunaExiste($tabela, $coluna) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$tabela` LIKE ?");
        $stmt->execute([$coluna]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Função para obter leads disponíveis com paginação
function obterLeadsDisponiveis($pagina = 1, $por_pagina = 10, $filtros = []) {
    global $pdo;
    
    try {
        $offset = ($pagina - 1) * $por_pagina;
        
        // Construir WHERE baseado nos filtros
        $where_conditions = ["l.status = 'novo'"];
        $params = [];
        
        if (!empty($filtros['cidade_origem'])) {
            $where_conditions[] = "l.cidade_origem LIKE ?";
            $params[] = '%' . $filtros['cidade_origem'] . '%';
        }
        
        if (!empty($filtros['cidade_destino'])) {
            $where_conditions[] = "l.cidade_destino LIKE ?";
            $params[] = '%' . $filtros['cidade_destino'] . '%';
        }
        
        if (!empty($filtros['tipo_veiculo'])) {
            $where_conditions[] = "l.tipo_veiculo = ?";
            $params[] = $filtros['tipo_veiculo'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Query principal com contagem de visualizações
        $sql = "SELECT l.*, 
                       COUNT(DISTINCT lv.user_id) as total_visualizacoes,
                       (COUNT(DISTINCT lv.user_id) >= 7) as limite_atingido
                FROM leads l 
                LEFT JOIN lead_views lv ON l.id = lv.lead_id 
                WHERE $where_clause
                GROUP BY l.id 
                ORDER BY l.data_cadastro DESC 
                LIMIT $por_pagina OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $leads = $stmt->fetchAll();
        
        // Contar total para paginação
        $sql_count = "SELECT COUNT(DISTINCT l.id) as total 
                      FROM leads l 
                      WHERE $where_clause";
        
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute($params);
        $total = $stmt_count->fetch()['total'];
        
        return [
            'leads' => $leads,
            'total' => $total,
            'pagina_atual' => $pagina,
            'total_paginas' => ceil($total / $por_pagina),
            'por_pagina' => $por_pagina
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao obter leads disponíveis: " . $e->getMessage());
        return [
            'leads' => [],
            'total' => 0,
            'pagina_atual' => 1,
            'total_paginas' => 0,
            'por_pagina' => $por_pagina
        ];
    }
}

// Função para formatar valor em reais
function formatarValor($valor) {
    if (is_null($valor) || $valor == 0) {
        return 'Não informado';
    }
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Função para formatar data
function formatarData($data) {
    if (empty($data)) {
        return 'Não informado';
    }
    
    try {
        $dt = new DateTime($data);
        return $dt->format('d/m/Y');
    } catch (Exception $e) {
        return 'Data inválida';
    }
}

// Função para formatar data e hora
function formatarDataHora($data) {
    if (empty($data)) {
        return 'Não informado';
    }
    
    try {
        $dt = new DateTime($data);
        return $dt->format('d/m/Y H:i');
    } catch (Exception $e) {
        return 'Data inválida';
    }
}

// Função para limpar dados de entrada
function limparInput($data) {
    if (is_array($data)) {
        return array_map('limparInput', $data);
    }
    return trim(htmlspecialchars(strip_tags($data)));
}

// Função para validar email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Função para gerar token CSRF
function gerarTokenCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Função para verificar token CSRF
function verificarTokenCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Timezone
date_default_timezone_set('America/Sao_Paulo');

debugLog("Config.php carregado com sucesso");
?>