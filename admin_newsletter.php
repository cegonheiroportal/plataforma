<?php
require_once 'config.php';

if (!verificarLogin() || $_SESSION['nivel_acesso'] != 'administrador') {
    header('Location: login.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao == 'enviar_newsletter') {
        $assunto = trim($_POST['assunto']);
        $conteudo = trim($_POST['conteudo']);
        
        if (!empty($assunto) && !empty($conteudo)) {
            require_once 'notificacao_email.php';
            
            $resultado = enviarNewsletter($assunto, $conteudo);
            
            if ($resultado['sucesso']) {
                $mensagem = "✅ Newsletter enviada para {$resultado['total_enviados']} clientes!";
                $tipo_mensagem = 'success';
            } else {
                $mensagem = "❌ Erro: " . $resultado['erro'];
                $tipo_mensagem = 'danger';
            }
        } else {
            $mensagem = "❌ Preencha todos os campos!";
            $tipo_mensagem = 'danger';
        }
    }
}

// Buscar estatísticas
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_usuarios,
        SUM(CASE WHEN uc.receber_newsletter = 1 AND uc.notificacoes_email = 1 THEN 1 ELSE 0 END) as newsletter_ativo
    FROM usuarios u
    LEFT JOIN user_configuracoes uc ON u.id = uc.user_id
    WHERE u.status = 'ativo'
");
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter - Portal Cegonheiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3><i class="fas fa-newspaper me-2"></i>Enviar Newsletter</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($mensagem): ?>
                            <div class="alert alert-<?php echo $tipo_mensagem; ?>"><?php echo $mensagem; ?></div>
                        <?php endif; ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5><?php echo $stats['total_usuarios']; ?></h5>
                                        <small>Total de Usuários</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h5><?php echo $stats['newsletter_ativo']; ?></h5>
                                        <small>Inscritos na Newsletter</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="acao" value="enviar_newsletter">
                            
                            <div class="mb-3">
                                <label for="assunto" class="form-label">Assunto *</label>
                                <input type="text" class="form-control" name="assunto" id="assunto" 
                                       placeholder="Ex: Novidades do Portal Cegonheiro - Março 2024" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="conteudo" class="form-label">Conteúdo *</label>
                                <textarea class="form-control" name="conteudo" id="conteudo" rows="15" required
                                          placeholder="Digite o conteúdo da newsletter em HTML...">
<h3>🚚 Novidades do Portal Cegonheiro</h3>

<p>Olá! Temos novidades importantes para você:</p>

<h4>📱 Novo Sistema de Notificações WhatsApp</h4>
<p>Agora você pode receber notificações de novos leads diretamente no seu WhatsApp! 
   Ative nas suas configurações e seja o primeiro a saber sobre oportunidades.</p>

<h4>📊 Relatórios Melhorados</h4>
<p>Novos gráficos e estatísticas para você acompanhar melhor seu desempenho.</p>

<h4>🎯 Dicas para Aumentar suas Cotações</h4>
<ul>
    <li>Responda rapidamente aos leads</li>
    <li>Seja competitivo nos preços</li>
    <li>Mantenha seu perfil sempre atualizado</li>
    <li>Use fotos de qualidade dos seus veículos</li>
</ul>

<p><strong>Continue aproveitando o Portal Cegonheiro para fazer mais negócios!</strong></p>
                                </textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>
                                    Enviar Newsletter para <?php echo $stats['newsletter_ativo']; ?> clientes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>