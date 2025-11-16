<?php
require_once 'config.php';
require_once 'notificacao_whatsapp.php';

// Página para testar o sistema de WhatsApp
if (!verificarLogin() || $_SESSION['nivel_acesso'] != 'administrador') {
    die('Acesso negado');
}

if ($_POST['acao'] === 'teste') {
    $lead_id = $_POST['lead_id'];
    $resultado = notificarNovoLeadWhatsApp($lead_id);
    echo json_encode($resultado);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Teste WhatsApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Teste de Notificação WhatsApp</h2>
        
        <form id="testeForm">
            <div class="mb-3">
                <label for="lead_id" class="form-label">ID do Lead</label>
                <input type="number" class="form-control" name="lead_id" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Enviar Teste</button>
        </form>
        
        <div id="resultado" class="mt-3"></div>
    </div>

    <script>
        document.getElementById('testeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('acao', 'teste');
            
            fetch('teste_whatsapp.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('resultado').innerHTML = 
                    '<div class="alert alert-' + (data.sucesso ? 'success' : 'danger') + '">' +
                    JSON.stringify(data, null, 2) + '</div>';
            });
        });
    </script>
</body>
</html>