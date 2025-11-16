<?php
require_once 'config.php';

class EmailNotificacao {
    private $pdo;
    private $smtp_host;
    private $smtp_port;
    private $smtp_user;
    private $smtp_pass;
    private $from_email;
    private $from_name;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        
        // CONFIGURAÇÕES DE EMAIL - GMAIL
        $this->smtp_host = 'smtp.gmail.com';
        $this->smtp_port = 587;
        $this->smtp_user = 'cegonheiroportal@gmail.com';
        $this->smtp_pass = 'ocqp mprj qfiw plqx';
        $this->from_email = 'cegonheiroportal@gmail.com';
        $this->from_name = 'Portal Cegonheiro';
    }
    
    /**
     * Enviar notificação de novo lead por email
     */
    public function notificarNovoLeadEmail($lead_id) {
        try {
            // Buscar dados do lead
            $stmt = $this->pdo->prepare("
                SELECT l.*, 
                       DATE_FORMAT(l.data_cadastro, '%d/%m/%Y às %H:%i') as data_formatada,
                       DATE_FORMAT(l.data_prevista, '%d/%m/%Y') as data_prevista_formatada
                FROM leads l 
                WHERE l.id = ?
            ");
            $stmt->execute([$lead_id]);
            $lead = $stmt->fetch();
            
            if (!$lead) {
                throw new Exception("Lead não encontrado: $lead_id");
            }
            
            // Buscar clientes que devem receber notificações
            $clientes = $this->buscarClientesParaNotificacao('email');
            
            $total_enviados = 0;
            $total_erros = 0;
            
            foreach ($clientes as $cliente) {
                try {
                    $sucesso = $this->enviarEmailNovoLead($cliente, $lead);
                    if ($sucesso) {
                        $total_enviados++;
                        $this->registrarNotificacao($cliente['id'], $lead_id, 'email', 'enviado', 'novo_lead');
                    } else {
                        $total_erros++;
                        $this->registrarNotificacao($cliente['id'], $lead_id, 'email', 'erro', 'novo_lead');
                    }
                } catch (Exception $e) {
                    $total_erros++;
                    $this->registrarNotificacao($cliente['id'], $lead_id, 'email', 'erro', 'novo_lead', $e->getMessage());
                    error_log("Erro ao enviar email para cliente {$cliente['id']}: " . $e->getMessage());
                }
                
                // Delay entre envios
                usleep(500000); // 0.5 segundos
            }
            
            return [
                'sucesso' => true,
                'total_enviados' => $total_enviados,
                'total_erros' => $total_erros,
                'mensagem' => "Emails enviados: $total_enviados, Erros: $total_erros"
            ];
            
        } catch (Exception $e) {
            error_log("Erro geral na notificação email: " . $e->getMessage());
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar notificação de atualização de cotação
     */
    public function notificarAtualizacaoCotacao($cotacao_id, $status_anterior, $status_novo) {
        try {
            // Buscar dados da cotação
            $stmt = $this->pdo->prepare("
                SELECT c.*, l.*, u.nome as cliente_nome, u.email as cliente_email,
                       DATE_FORMAT(c.data_cotacao, '%d/%m/%Y às %H:%i') as data_cotacao_formatada
                FROM cotacoes c
                JOIN leads l ON c.lead_id = l.id
                JOIN usuarios u ON c.cliente_id = u.id
                WHERE c.id = ?
            ");
            $stmt->execute([$cotacao_id]);
            $cotacao = $stmt->fetch();
            
            if (!$cotacao) {
                throw new Exception("Cotação não encontrada: $cotacao_id");
            }
            
            // Verificar se cliente quer receber notificações de cotação
            $stmt = $this->pdo->prepare("
                SELECT notificacoes_cotacoes FROM user_configuracoes 
                WHERE user_id = ? AND notificacoes_email = 1
            ");
            $stmt->execute([$cotacao['cliente_id']]);
            $config = $stmt->fetch();
            
            if (!$config || !$config['notificacoes_cotacoes']) {
                return ['sucesso' => false, 'erro' => 'Cliente não quer receber notificações de cotação'];
            }
            
            $sucesso = $this->enviarEmailAtualizacaoCotacao($cotacao, $status_anterior, $status_novo);
            
            if ($sucesso) {
                $this->registrarNotificacao($cotacao['cliente_id'], $cotacao['lead_id'], 'email', 'enviado', 'cotacao');
                return ['sucesso' => true, 'mensagem' => 'Email de cotação enviado'];
            } else {
                $this->registrarNotificacao($cotacao['cliente_id'], $cotacao['lead_id'], 'email', 'erro', 'cotacao');
                return ['sucesso' => false, 'erro' => 'Falha no envio'];
            }
            
        } catch (Exception $e) {
            error_log("Erro na notificação de cotação: " . $e->getMessage());
            return ['sucesso' => false, 'erro' => $e->getMessage()];
        }
    }
    
    /**
     * Enviar newsletter
     */
    public function enviarNewsletter($assunto, $conteudo, $template = 'padrao') {
        try {
            // Buscar clientes que querem receber newsletter
            $stmt = $this->pdo->query("
                SELECT u.id, u.nome, u.email
                FROM usuarios u
                JOIN user_configuracoes uc ON u.id = uc.user_id
                WHERE u.status = 'ativo' 
                AND uc.notificacoes_email = 1 
                AND uc.receber_newsletter = 1
                ORDER BY u.nome
            ");
            $clientes = $stmt->fetchAll();
            
            $total_enviados = 0;
            $total_erros = 0;
            
            foreach ($clientes as $cliente) {
                try {
                    $sucesso = $this->enviarEmailNewsletter($cliente, $assunto, $conteudo, $template);
                    if ($sucesso) {
                        $total_enviados++;
                        $this->registrarNotificacao($cliente['id'], null, 'email', 'enviado', 'newsletter');
                    } else {
                        $total_erros++;
                        $this->registrarNotificacao($cliente['id'], null, 'email', 'erro', 'newsletter');
                    }
                } catch (Exception $e) {
                    $total_erros++;
                    $this->registrarNotificacao($cliente['id'], null, 'email', 'erro', 'newsletter', $e->getMessage());
                }
                
                // Delay entre envios
                usleep(1000000); // 1 segundo
            }
            
            return [
                'sucesso' => true,
                'total_enviados' => $total_enviados,
                'total_erros' => $total_erros,
                'mensagem' => "Newsletter enviada para $total_enviados clientes"
            ];
            
        } catch (Exception $e) {
            error_log("Erro no envio de newsletter: " . $e->getMessage());
            return ['sucesso' => false, 'erro' => $e->getMessage()];
        }
    }
    
    /**
     * Buscar clientes para notificação
     */
    private function buscarClientesParaNotificacao($tipo = 'email') {
        $stmt = $this->pdo->query("
            SELECT u.id, u.nome, u.email
            FROM usuarios u
            JOIN user_configuracoes uc ON u.id = uc.user_id
            WHERE u.status = 'ativo' 
            AND u.email IS NOT NULL 
            AND u.email != ''
            AND uc.notificacoes_email = 1
            AND uc.notificacoes_leads = 1
            ORDER BY u.nome
        ");
        
        return $stmt->fetchAll();
    }
    
    /**
     * Enviar email de novo lead
     */
    private function enviarEmailNovoLead($cliente, $lead) {
        $assunto = "🚚 Novo Lead Disponível - Portal Cegonheiro";
        
        $corpo = $this->criarTemplateNovoLead($cliente, $lead);
        
        return $this->enviarEmail($cliente['email'], $cliente['nome'], $assunto, $corpo);
    }
    
    /**
     * Enviar email de atualização de cotação
     */
    private function enviarEmailAtualizacaoCotacao($cotacao, $status_anterior, $status_novo) {
        $assunto = "📋 Atualização da sua Cotação - Portal Cegonheiro";
        
        $corpo = $this->criarTemplateAtualizacaoCotacao($cotacao, $status_anterior, $status_novo);
        
        return $this->enviarEmail($cotacao['cliente_email'], $cotacao['cliente_nome'], $assunto, $corpo);
    }
    
    /**
     * Enviar newsletter
     */
    private function enviarEmailNewsletter($cliente, $assunto, $conteudo, $template) {
        $corpo = $this->criarTemplateNewsletter($cliente, $conteudo, $template);
        
        return $this->enviarEmail($cliente['email'], $cliente['nome'], $assunto, $corpo);
    }
    
    /**
     * Template para novo lead
     */
    private function criarTemplateNovoLead($cliente, $lead) {
        $nome_cliente = explode(' ', $cliente['nome'])[0];
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Novo Lead - Portal Cegonheiro</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #00bc75, #07a368); color: white; padding: 30px; border-radius: 10px; text-align: center; margin-bottom: 30px;'>
                <h1 style='margin: 0; font-size: 28px;'>🚚 Portal Cegonheiro</h1>
                <p style='margin: 10px 0 0 0; font-size: 16px;'>Novo Lead Disponível!</p>
            </div>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
                <h2 style='color: #00bc75; margin-top: 0;'>Olá, {$nome_cliente}! 👋</h2>
                <p>Temos um <strong>novo lead disponível</strong> que pode ser do seu interesse:</p>
            </div>
            
            <div style='background: white; border: 2px solid #00bc75; border-radius: 10px; padding: 25px; margin-bottom: 25px;'>
                <h3 style='color: #00bc75; margin-top: 0; border-bottom: 2px solid #e9ecef; padding-bottom: 10px;'>📋 Detalhes do Transporte</h3>
                
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold; color: #495057;'>🚗 Veículo:</td>
                        <td style='padding: 8px 0; color: #00bc75; font-weight: bold;'>{$lead['tipo_veiculo']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold; color: #495057;'>📅 Ano/Modelo:</td>
                        <td style='padding: 8px 0;'>{$lead['ano_modelo']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold; color: #495057;'>📍 Origem:</td>
                        <td style='padding: 8px 0;'>{$lead['cidade_origem']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold; color: #495057;'>🎯 Destino:</td>
                        <td style='padding: 8px 0;'>{$lead['cidade_destino']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold; color: #495057;'>📅 Data Prevista:</td>
                        <td style='padding: 8px 0;'>{$lead['data_prevista_formatada']}</td>
                    </tr>";
        
        if ($lead['valor_veiculo'] && $lead['valor_veiculo'] > 0) {
            $valor_formatado = 'R$ ' . number_format($lead['valor_veiculo'], 2, ',', '.');
            $html .= "
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold; color: #495057;'>💰 Valor do Veículo:</td>
                        <td style='padding: 8px 0; color: #00bc75; font-weight: bold;'>{$valor_formatado}</td>
                    </tr>";
        }
        
        $html .= "
                </table>
                
                <p style='margin: 20px 0 10px 0; color: #6c757d; font-size: 14px;'>
                    ⏰ <strong>Cadastrado em:</strong> {$lead['data_formatada']}
                </p>
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='https://portalcegonheiro.com.br/app/leads_disponiveis.php' 
                   style='background: #00bc75; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; display: inline-block;'>
                    🔥 Ver Lead e Enviar Cotação
                </a>
            </div>
            
            <div style='background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px; margin: 20px 0;'>
                <p style='margin: 0; color: #856404;'>
                    <strong>💡 Dica:</strong> Seja rápido! Os melhores leads são disputados por várias transportadoras.
                </p>
            </div>
            
            <div style='border-top: 2px solid #e9ecef; padding-top: 20px; margin-top: 30px; text-align: center; color: #6c757d; font-size: 14px;'>
                <p><strong>Portal Cegonheiro</strong><br>
                Conectando transportadoras e clientes</p>
                
                <p style='margin-top: 15px;'>
                    <a href='https://portalcegonheiro.com.br/app/configuracoes.php' style='color: #6c757d; text-decoration: none;'>
                        ⚙️ Gerenciar notificações
                    </a> | 
                    <a href='https://portalcegonheiro.com.br/app/dashboard.php' style='color: #6c757d; text-decoration: none;'>
                        🏠 Acessar portal
                    </a>
                </p>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Template para atualização de cotação
     */
    private function criarTemplateAtualizacaoCotacao($cotacao, $status_anterior, $status_novo) {
        $nome_cliente = explode(' ', $cotacao['cliente_nome'])[0];
        
        $cores_status = [
            'pendente' => '#ffc107',
            'aprovada' => '#28a745',
            'rejeitada' => '#dc3545',
            'em_analise' => '#17a2b8',
            'finalizada' => '#6f42c1'
        ];
        
        $cor_status = $cores_status[$status_novo] ?? '#6c757d';
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Atualização de Cotação - Portal Cegonheiro</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #00bc75, #07a368); color: white; padding: 30px; border-radius: 10px; text-align: center; margin-bottom: 30px;'>
                <h1 style='margin: 0; font-size: 28px;'>📋 Portal Cegonheiro</h1>
                <p style='margin: 10px 0 0 0; font-size: 16px;'>Atualização da sua Cotação</p>
            </div>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
                <h2 style='color: #00bc75; margin-top: 0;'>Olá, {$nome_cliente}! 👋</h2>
                <p>Sua cotação foi <strong>atualizada</strong>. Confira os detalhes abaixo:</p>
            </div>
            
            <div style='background: white; border: 2px solid {$cor_status}; border-radius: 10px; padding: 25px; margin-bottom: 25px;'>
                <h3 style='color: {$cor_status}; margin-top: 0;'>Status da Cotação</h3>
                
                <div style='text-align: center; margin: 20px 0;'>
                    <span style='background: #f8f9fa; color: #6c757d; padding: 8px 16px; border-radius: 20px; font-size: 14px;'>
                        {$status_anterior}
                    </span>
                    <span style='margin: 0 15px; font-size: 20px;'>→</span>
                    <span style='background: {$cor_status}; color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold;'>
                        " . strtoupper($status_novo) . "
                    </span>
                </div>
                
                <h4 style='color: #495057; margin-top: 25px;'>📋 Detalhes do Lead:</h4>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold; color: #495057;'>🚗 Veículo:</td>
                        <td style='padding: 8px 0;'>{$cotacao['tipo_veiculo']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold; color: #495057;'>📍 Rota:</td>
                        <td style='padding: 8px 0;'>{$cotacao['cidade_origem']} → {$cotacao['cidade_destino']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold; color: #495057;'>💰 Sua Cotação:</td>
                        <td style='padding: 8px 0; color: #00bc75; font-weight: bold;'>R$ " . number_format($cotacao['valor_cotacao'], 2, ',', '.') . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold; color: #495057;'>📅 Data da Cotação:</td>
                        <td style='padding: 8px 0;'>{$cotacao['data_cotacao_formatada']}</td>
                    </tr>
                </table>
            </div>";
        
        // Mensagem específica por status
        if ($status_novo == 'aprovada') {
            $html .= "
            <div style='background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center;'>
                <h3 style='color: #155724; margin-top: 0;'>🎉 Parabéns! Sua cotação foi aprovada!</h3>
                <p style='color: #155724; margin-bottom: 0;'>
                    O cliente escolheu sua proposta. Em breve você receberá mais detalhes sobre o transporte.
                </p>
            </div>";
        } elseif ($status_novo == 'rejeitada') {
            $html .= "
            <div style='background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center;'>
                <h3 style='color: #721c24; margin-top: 0;'>Sua cotação não foi selecionada</h3>
                <p style='color: #721c24; margin-bottom: 0;'>
                    Não desanime! Continue participando dos próximos leads. Sua oportunidade está chegando!
                </p>
            </div>";
        }
        
        $html .= "
            <div style='text-align: center; margin: 30px 0;'>
                <a href='https://portalcegonheiro.com.br/app/relatorios.php' 
                   style='background: #00bc75; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; display: inline-block;'>
                    📊 Ver Todas as Cotações
                </a>
            </div>
            
            <div style='border-top: 2px solid #e9ecef; padding-top: 20px; margin-top: 30px; text-align: center; color: #6c757d; font-size: 14px;'>
                <p><strong>Portal Cegonheiro</strong><br>
                Conectando transportadoras e clientes</p>
                
                <p style='margin-top: 15px;'>
                    <a href='https://portalcegonheiro.com.br/app/configuracoes.php' style='color: #6c757d; text-decoration: none;'>
                        ⚙️ Gerenciar notificações
                    </a> | 
                    <a href='https://portalcegonheiro.com.br/app/dashboard.php' style='color: #6c757d; text-decoration: none;'>
                        🏠 Acessar portal
                    </a>
                </p>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Template para newsletter
     */
    private function criarTemplateNewsletter($cliente, $conteudo, $template) {
        $nome_cliente = explode(' ', $cliente['nome'])[0];
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Newsletter - Portal Cegonheiro</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #00bc75, #07a368); color: white; padding: 30px; border-radius: 10px; text-align: center; margin-bottom: 30px;'>
                <h1 style='margin: 0; font-size: 28px;'>📰 Portal Cegonheiro</h1>
                <p style='margin: 10px 0 0 0; font-size: 16px;'>Newsletter</p>
            </div>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
                <h2 style='color: #00bc75; margin-top: 0;'>Olá, {$nome_cliente}! 👋</h2>
            </div>
            
            <div style='background: white; border: 1px solid #e9ecef; border-radius: 10px; padding: 25px; margin-bottom: 25px;'>
                {$conteudo}
            </div>
            
            <div style='border-top: 2px solid #e9ecef; padding-top: 20px; margin-top: 30px; text-align: center; color: #6c757d; font-size: 14px;'>
                <p><strong>Portal Cegonheiro</strong><br>
                Conectando transportadoras e clientes</p>
                
                <p style='margin-top: 15px;'>
                    <a href='https://portalcegonheiro.com.br/app/configuracoes.php' style='color: #6c757d; text-decoration: none;'>
                        ⚙️ Cancelar newsletter
                    </a> | 
                    <a href='https://portalcegonheiro.com.br/app/dashboard.php' style='color: #6c757d; text-decoration: none;'>
                        🏠 Acessar portal
                    </a>
                </p>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * ✅ MÉTODO ÚNICO enviarEmail - PÚBLICO para permitir testes
     */
    public function enviarEmail($para_email, $para_nome, $assunto, $corpo) {
        // Verificar se PHPMailer está disponível
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return $this->enviarComPHPMailer($para_email, $para_nome, $assunto, $corpo);
        } else {
            return $this->enviarComMailNativo($para_email, $para_nome, $assunto, $corpo);
        }
    }
    
    /**
     * Enviar com PHPMailer (recomendado)
     */
    private function enviarComPHPMailer($para_email, $para_nome, $assunto, $corpo) {
        try {
            require_once 'vendor/autoload.php'; // Se instalado via Composer
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Configurações do servidor
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_user;
            $mail->Password = $this->smtp_pass;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtp_port;
            $mail->CharSet = 'UTF-8';
            
            // Remetente
            $mail->setFrom($this->from_email, $this->from_name);
            
            // Destinatário
            $mail->addAddress($para_email, $para_nome);
            
            // Conteúdo
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body = $corpo;
            
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Erro PHPMailer: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enviar com função mail() nativa (fallback)
     */
    private function enviarComMailNativo($para_email, $para_nome, $assunto, $corpo) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->from_name . ' <' . $this->from_email . '>',
            'Reply-To: ' . $this->from_email,
            'Return-Path: ' . $this->from_email,
            'X-Mailer: PHP/' . phpversion(),
            'X-Priority: 3',
            'X-MSMail-Priority: Normal'
        ];
        
        $para = $para_nome . ' <' . $para_email . '>';
        
        // Configurar parâmetros adicionais
        $parametros = '-f' . $this->from_email;
        
        return mail($para, $assunto, $corpo, implode("\r\n", $headers), $parametros);
    }
    
    /**
     * Registrar notificação no log
     */
    private function registrarNotificacao($cliente_id, $lead_id, $tipo, $status, $subtipo = null, $erro = null) {
        try {
            // Criar tabela se não existir
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS notificacoes_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    cliente_id INT NOT NULL,
                    lead_id INT NULL,
                    tipo ENUM('email', 'whatsapp', 'push') NOT NULL,
                    subtipo ENUM('novo_lead', 'cotacao', 'newsletter') NULL,
                    status ENUM('enviado', 'erro', 'pendente') NOT NULL,
                    mensagem_erro TEXT NULL,
                    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_cliente_lead (cliente_id, lead_id),
                    INDEX idx_data_envio (data_envio),
                    INDEX idx_tipo_status (tipo, status)
                )
            ");
            
            $stmt = $this->pdo->prepare("
                INSERT INTO notificacoes_log (cliente_id, lead_id, tipo, subtipo, status, mensagem_erro) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$cliente_id, $lead_id, $tipo, $subtipo, $status, $erro]);
            
        } catch (Exception $e) {
            error_log("Erro ao registrar notificação: " . $e->getMessage());
        }
    }
    
    // ✅ MÉTODOS DE TESTE ADICIONADOS
    
    /**
     * Método público para enviar email de teste simples
     */
    public function enviarEmailTeste($para_email, $para_nome) {
        $assunto = "🧪 Teste de Email - Portal Cegonheiro";
        
        $corpo = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Teste Email</title>
        </head>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #00bc75, #07a368); color: white; padding: 30px; border-radius: 10px; text-align: center; margin-bottom: 30px;'>
                <h1 style='margin: 0;'>✅ Teste Bem-Sucedido!</h1>
                <p style='margin: 10px 0 0 0;'>Portal Cegonheiro</p>
            </div>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
                <h2 style='color: #00bc75; margin-top: 0;'>Olá, {$para_nome}! 👋</h2>
                <p>Se você recebeu este email, significa que as <strong>configurações SMTP estão funcionando perfeitamente!</strong></p>
            </div>
            
            <div style='background: white; border: 2px solid #00bc75; border-radius: 10px; padding: 25px; margin-bottom: 25px;'>
                <h3 style='color: #00bc75; margin-top: 0;'>📋 Detalhes do Teste</h3>
                <table style='width: 100%;'>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold;'>🕐 Data/Hora:</td>
                        <td style='padding: 8px 0;'>" . date('d/m/Y H:i:s') . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold;'>📧 Remetente:</td>
                        <td style='padding: 8px 0;'>cegonheiroportal@gmail.com</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold;'>🎯 Destinatário:</td>
                        <td style='padding: 8px 0;'>{$para_email}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold;'>🔧 SMTP:</td>
                        <td style='padding: 8px 0;'>Gmail (smtp.gmail.com:587)</td>
                    </tr>
                </table>
            </div>
            
            <div style='background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 20px; text-align: center;'>
                <h3 style='color: #155724; margin-top: 0;'>🎉 Sistema de Email Funcionando!</h3>
                <p style='color: #155724; margin-bottom: 0;'>
                    Agora você pode receber notificações de novos leads, atualizações de cotações e newsletters.
                </p>
            </div>
            
            <div style='text-align: center; margin-top: 30px; color: #6c757d; font-size: 14px;'>
                <p><strong>Portal Cegonheiro</strong><br>
                Sistema de Notificações por Email</p>
            </div>
        </body>
        </html>";
        
                return $this->enviarEmail($para_email, $para_nome, $assunto, $corpo);
    }

    /**
     * Método para enviar email com lead simulado
     */
    public function enviarEmailLeadSimulado($para_email, $para_nome) {
        $lead_simulado = [
            'id' => 999,
            'tipo_veiculo' => 'Sedan',
            'ano_modelo' => '2020/2021',
            'cidade_origem' => 'São Paulo - SP',
            'cidade_destino' => 'Rio de Janeiro - RJ',
            'data_prevista_formatada' => date('d/m/Y', strtotime('+7 days')),
            'valor_veiculo' => 45000,
            'data_formatada' => date('d/m/Y H:i')
        ];
        
        $cliente_simulado = [
            'nome' => $para_nome,
            'email' => $para_email
        ];
        
        return $this->enviarEmailNovoLead($cliente_simulado, $lead_simulado);
    }

} // ← Fim da classe EmailNotificacao

// Funções auxiliares para usar em outros arquivos
function notificarNovoLeadEmail($lead_id) {
    global $pdo;
    
    try {
        $email = new EmailNotificacao($pdo);
        return $email->notificarNovoLeadEmail($lead_id);
    } catch (Exception $e) {
        error_log("Erro na notificação email: " . $e->getMessage());
        return ['sucesso' => false, 'erro' => $e->getMessage()];
    }
}

function notificarAtualizacaoCotacao($cotacao_id, $status_anterior, $status_novo) {
    global $pdo;
    
    try {
        $email = new EmailNotificacao($pdo);
        return $email->notificarAtualizacaoCotacao($cotacao_id, $status_anterior, $status_novo);
    } catch (Exception $e) {
        error_log("Erro na notificação de cotação: " . $e->getMessage());
        return ['sucesso' => false, 'erro' => $e->getMessage()];
    }
}

function enviarNewsletter($assunto, $conteudo, $template = 'padrao') {
    global $pdo;
    
    try {
        $email = new EmailNotificacao($pdo);
        return $email->enviarNewsletter($assunto, $conteudo, $template);
    } catch (Exception $e) {
        error_log("Erro no envio de newsletter: " . $e->getMessage());
        return ['sucesso' => false, 'erro' => $e->getMessage()];
    }
}
?>