<?php
// Ativar exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once 'config.php';
} catch (Exception $e) {
    die('Erro ao carregar config.php: ' . $e->getMessage());
}

// Verificar se está logado
if (!function_exists('verificarLogin')) {
    die('Função verificarLogin não encontrada. Verifique o arquivo config.php');
}

if (!verificarLogin()) {
    header('Location: login.php');
    exit;
}

$resultado = '';
$user_id = $_SESSION['usuario_id'] ?? null;

if (!$user_id) {
    die('ID do usuário não encontrado na sessão');
}

// Buscar dados do usuário
try {
    $stmt = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        die('Usuário não encontrado no banco de dados');
    }
} catch (Exception $e) {
    die('Erro ao buscar usuário: ' . $e->getMessage());
}

// Buscar um lead real para teste
try {
    $stmt = $pdo->query("SELECT id FROM leads ORDER BY id DESC LIMIT 1");
    $lead_real = $stmt->fetch();
} catch (Exception $e) {
    $lead_real = null;
    error_log('Erro ao buscar leads: ' . $e->getMessage());
}

// Verificar quantos leads existem
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM leads");
    $total_leads = $stmt->fetch()['total'];
} catch (Exception $e) {
    $total_leads = 0;
    error_log('Erro ao contar leads: ' . $e->getMessage());
}

// Processar teste
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo_teste = $_POST['tipo_teste'] ?? '';
    
    try {
        // Verificar se o arquivo de notificação existe
        if (!file_exists('notificacao_email.php')) {
            throw new Exception('Arquivo notificacao_email.php não encontrado');
        }
        
        require_once 'notificacao_email.php';
        
        // Verificar se a classe existe
        if (!class_exists('EmailNotificacao')) {
            throw new Exception('Classe EmailNotificacao não encontrada');
        }
        
        $email = new EmailNotificacao($pdo);
        
        if ($tipo_teste == 'simples') {
            // Teste simples direto
            $sucesso = $email->enviarEmailTeste(
                $usuario['email'],
                $usuario['nome']
            );
            
            if ($sucesso) {
                $resultado = '<div class="alert alert-success">✅ Email de teste enviado com sucesso! Verifique sua caixa de entrada.</div>';
            } else {
                $resultado = '<div class="alert alert-danger">❌ Erro ao enviar email de teste. Verifique as configurações SMTP.</div>';
            }
            
        } elseif ($tipo_teste == 'lead' && $lead_real) {
            // Teste com lead real
            $resultado_lead = $email->notificarNovoLeadEmail($lead_real['id']);
            
            if ($resultado_lead['sucesso']) {
                $resultado = '<div class="alert alert-success">✅ Notificação de lead enviada! Total: ' . $resultado_lead['total_enviados'] . '</div>';
            } else {
                $resultado = '<div class="alert alert-danger">❌ Erro: ' . $resultado_lead['erro'] . '</div>';
            }
            
        } elseif ($tipo_teste == 'lead_simulado') {
            // Teste com lead simulado
            $sucesso = $email->enviarEmailLeadSimulado($usuario['email'], $usuario['nome']);
            
            if ($sucesso) {
                $resultado = '<div class="alert alert-success">✅ Email de lead simulado enviado!</div>';
            } else {
                $resultado = '<div class="alert alert-danger">❌ Erro ao enviar email simulado.</div>';
            }
            
        } elseif ($tipo_teste == 'teste_smtp') {
            // Teste básico de SMTP
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: Portal Cegonheiro <cegonheiroportal@gmail.com>',
                'Reply-To: cegonheiroportal@gmail.com'
            ];
            
            $assunto = '🧪 Teste SMTP Básico - Portal Cegonheiro';
            $corpo = '
            <html>
            <body style="font-family: Arial, sans-serif;">
                <h2 style="color: #00bc75;">Teste SMTP Funcionando!</h2>
                <p>Este é um teste básico usando a função mail() do PHP.</p>
                <p><strong>Data:</strong> ' . date('d/m/Y H:i:s') . '</p>
                <p><strong>Usuário:</strong> ' . $usuario['nome'] . '</p>
            </body>
            </html>';
            
            $sucesso = mail($usuario['email'], $assunto, $corpo, implode("\r\n", $headers));
            
            if ($sucesso) {
                $resultado = '<div class="alert alert-success">✅ Teste SMTP básico enviado!</div>';
            } else {
                $resultado = '<div class="alert alert-danger">❌ Erro no teste SMTP básico.</div>';
            }
        }
        
    } catch (Exception $e) {
        $resultado = '<div class="alert alert-danger">❌ Erro: ' . $e->getMessage() . '</div>';
        error_log('Erro no teste de email: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Email - Portal Cegonheiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Inter', sans-serif;
        }
        
        .test-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .test-card-header {
            background: linear-gradient(135deg, #00bc75, #07a368);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <!-- Header -->
                <div class="test-card">
                    <div class="test-card-header">
                        <h2><i class="fas fa-envelope me-2"></i>Teste de Email - Portal Cegonheiro</h2>
                        <p class="mb-0">Diagnóstico do sistema de notificações por email</p>
                    </div>
                </div>

                <!-- Resultado -->
                <?php if ($resultado): ?>
                    <?php echo $resultado; ?>
                <?php endif; ?>
                
                <!-- Status do Sistema -->
                <div class="test-card">
                    <div class="card-header bg-light">
                        <h5><i class="fas fa-info-circle me-2"></i>Status do Sistema</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>📋 Informações do Usuário:</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Nome:</strong></td>
                                        <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>ID:</strong></td>
                                        <td><?php echo $user_id; ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>🔧 Configurações:</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>SMTP:</strong></td>
                                        <td>smtp.gmail.com:587</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Remetente:</strong></td>
                                        <td>cegonheiroportal@gmail.com</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Leads:</strong></td>
                                        <td>
                                            <span class="status-badge <?php echo $total_leads > 0 ? 'status-success' : 'status-warning'; ?>">
                                                <?php echo $total_leads; ?> leads
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Testes Disponíveis -->
                <div class="test-card">
                    <div class="card-header bg-light">
                        <h5><i class="fas fa-flask me-2"></i>Testes Disponíveis</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <!-- Teste SMTP Básico -->
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100 border-primary">
                                        <div class="card-body text-center">
                                            <i class="fas fa-server fa-2x text-primary mb-3"></i>
                                            <h6>Teste SMTP Básico</h6>
                                            <p class="small text-muted">Função mail() nativa do PHP</p>
                                            <button type="submit" name="tipo_teste" value="teste_smtp" class="btn btn-primary btn-sm">
                                                <i class="fas fa-paper-plane me-1"></i>Testar
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Teste Simples -->
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100 border-success">
                                        <div class="card-body text-center">
                                            <i class="fas fa-envelope fa-2x text-success mb-3"></i>
                                            <h6>Teste Classe Email</h6>
                                            <p class="small text-muted">Usando classe EmailNotificacao</p>
                                            <button type="submit" name="tipo_teste" value="simples" class="btn btn-success btn-sm">
                                                <i class="fas fa-paper-plane me-1"></i>Testar
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Lead Simulado -->
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100 border-warning">
                                        <div class="card-body text-center">
                                            <i class="fas fa-truck fa-2x text-warning mb-3"></i>
                                            <h6>Lead Simulado</h6>
                                            <p class="small text-muted">Template completo com dados fictícios</p>
                                            <button type="submit" name="tipo_teste" value="lead_simulado" class="btn btn-warning btn-sm">
                                                <i class="fas fa-truck me-1"></i>Testar
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Lead Real -->
                                <?php if ($lead_real): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100 border-info">
                                        <div class="card-body text-center">
                                            <i class="fas fa-database fa-2x text-info mb-3"></i>
                                            <h6>Lead Real</h6>
                                            <p class="small text-muted">Usar lead ID <?php echo $lead_real['id']; ?></p>
                                            <button type="submit" name="tipo_teste" value="lead" class="btn btn-info btn-sm">
                                                <i class="fas fa-database me-1"></i>Testar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100 bg-light">
                                        <div class="card-body text-center">
                                            <i class="fas fa-exclamation-triangle fa-2x text-muted mb-3"></i>
                                            <h6 class="text-muted">Lead Real</h6>
                                            <p class="small text-muted">Nenhum lead encontrado</p>
                                            <button type="button" class="btn btn-secondary btn-sm" disabled>
                                                Indisponível
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Debug Info -->
                <div class="test-card">
                    <div class="card-header bg-light">
                        <h6><i class="fas fa-bug me-2"></i>Informações de Debug</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <small>
                                    <strong>PHP Version:</strong> <?php echo phpversion(); ?><br>
                                    <strong>Mail Function:</strong> <?php echo function_exists('mail') ? '✅ Disponível' : '❌ Não disponível'; ?><br>
                                    <strong>Config File:</strong> <?php echo file_exists('config.php') ? '✅ Existe' : '❌ Não existe'; ?><br>
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small>
                                    <strong>Email Class:</strong> <?php echo file_exists('notificacao_email.php') ? '✅ Existe' : '❌ Não existe'; ?><br>
                                    <strong>PDO:</strong> <?php echo isset($pdo) ? '✅ Conectado' : '❌ Não conectado'; ?><br>
                                    <strong>Session:</strong> <?php echo session_status() === PHP_SESSION_ACTIVE ? '✅ Ativa' : '❌ Inativa'; ?><br>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Links -->
                <div class="text-center">
                    <a href="configuracoes.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left me-2"></i>Voltar
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-home me-2"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        console.log('🧪 Página de teste de email carregada');
        console.log('👤 Usuário:', '<?php echo $usuario['nome']; ?>');
        console.log('📧 Email:', '<?php echo $usuario['email']; ?>');
        console.log('📊 Total leads:', <?php echo $total_leads; ?>);
    </script>
</body>
</html>