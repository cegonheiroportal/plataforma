<?php
require_once 'config.php';
require_once 'notificacao_email.php';

if (!verificarLogin()) {
    die('Faça login primeiro');
}

$resultado = '';

if ($_POST['acao'] === 'testar') {
    $tipo = $_POST['tipo'];
    
    try {
        $email = new EmailNotificacao($pdo);
        
        switch ($tipo) {
            case 'novo_lead':
                $resultado = $email->notificarNovoLeadEmail(1); // Lead ID 1
                break;
            case 'cotacao':
                $resultado = $email->notificarAtualizacaoCotacao(1, 'pendente', 'aprovada');
                break;
            case 'newsletter':
                $resultado = $email->enviarNewsletter(
                    'Teste Newsletter', 
                    '<h3>Este é um teste!</h3><p>Se você recebeu este email, o sistema está funcionando.</p>'
                );
                break;
        }
        
        $resultado = $resultado['sucesso'] ? 
            '<div class="alert alert-success">✅ Email enviado com sucesso!</div>' :
            '<div class="alert alert-danger">❌ Erro: ' . $resultado['erro'] . '</div>';
            
    } catch (Exception $e) {
        $resultado = '<div class="alert alert-danger">❌ Erro: ' . $e->getMessage() . '</div>';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Teste Email</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>🧪 Teste de Notificações Email</h2>
        
        <?php echo $resultado; ?>
        
        <form method="POST">
            <input type="hidden" name="acao" value="testar">
            
            <div class="mb-3">
                <label class="form-label">Tipo de Teste:</label>
                <select name="tipo" class="form-select" required>
                    <option value="">Selecione...</option>
                    <option value="novo_lead">📧 Novo Lead</option>
                    <option value="cotacao">📋 Atualização de Cotação</option>
                    <option value="newsletter">📰 Newsletter</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane me-2"></i>Enviar Teste
            </button>
        </form>
    </div>
</body>
</html>