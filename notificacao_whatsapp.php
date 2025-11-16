<?php
require_once 'config.php';

class WhatsAppNotificacao {
    private $api_url;
    private $api_token;
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        // Configurações da API do WhatsApp (Evolution API, Baileys, etc.)
        $this->api_url = 'https://api.whatsapp.com/send'; // URL da sua API
        $this->api_token = 'SEU_TOKEN_AQUI'; // Token da API
    }
    
    /**
     * Enviar notificação de novo lead via WhatsApp
     */
    public function notificarNovoLead($lead_id) {
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
            
            // Buscar clientes ativos que devem receber notificações
            $clientes = $this->buscarClientesParaNotificacao();
            
            $total_enviados = 0;
            $total_erros = 0;
            
            foreach ($clientes as $cliente) {
                try {
                    $sucesso = $this->enviarMensagemWhatsApp($cliente, $lead);
                    if ($sucesso) {
                        $total_enviados++;
                        $this->registrarNotificacao($cliente['id'], $lead_id, 'whatsapp', 'enviado');
                    } else {
                        $total_erros++;
                        $this->registrarNotificacao($cliente['id'], $lead_id, 'whatsapp', 'erro');
                    }
                } catch (Exception $e) {
                    $total_erros++;
                    $this->registrarNotificacao($cliente['id'], $lead_id, 'whatsapp', 'erro', $e->getMessage());
                    error_log("Erro ao enviar WhatsApp para cliente {$cliente['id']}: " . $e->getMessage());
                }
                
                // Delay entre envios para evitar spam
                usleep(500000); // 0.5 segundos
            }
            
            return [
                'sucesso' => true,
                'total_enviados' => $total_enviados,
                'total_erros' => $total_erros,
                'mensagem' => "Notificações enviadas: $total_enviados, Erros: $total_erros"
            ];
            
        } catch (Exception $e) {
            error_log("Erro geral na notificação WhatsApp: " . $e->getMessage());
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Buscar clientes que devem receber notificações
     */
    private function buscarClientesParaNotificacao() {
        $stmt = $this->pdo->query("
            SELECT c.id, c.nome, c.telefone, c.email, u.id as user_id
            FROM clientes c
            LEFT JOIN usuarios u ON c.email = u.email
            LEFT JOIN user_configuracoes uc ON u.id = uc.user_id
            WHERE c.status = 'ativo' 
            AND c.telefone IS NOT NULL 
            AND c.telefone != ''
            AND (uc.notificacoes_leads IS NULL OR uc.notificacoes_leads = 1)
            AND u.status = 'ativo'
            ORDER BY c.nome
        ");
        
        return $stmt->fetchAll();
    }
    
    /**
     * Enviar mensagem via WhatsApp
     */
    private function enviarMensagemWhatsApp($cliente, $lead) {
        $telefone = $this->formatarTelefone($cliente['telefone']);
        $mensagem = $this->criarMensagemLead($cliente, $lead);
        
        // Método 1: Usando Evolution API ou similar
        if ($this->api_token && $this->api_url !== 'https://api.whatsapp.com/send') {
            return $this->enviarViaAPI($telefone, $mensagem);
        }
        
        // Método 2: Link direto do WhatsApp (fallback)
        return $this->enviarViaLink($telefone, $mensagem);
    }
    
    /**
     * Enviar via API do WhatsApp
     */
    private function enviarViaAPI($telefone, $mensagem) {
        $data = [
            'number' => $telefone,
            'message' => $mensagem
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url . '/send-message');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $result = json_decode($response, true);
            return isset($result['success']) && $result['success'];
        }
        
        return false;
    }
    
    /**
     * Enviar via link do WhatsApp (método alternativo)
     */
    private function enviarViaLink($telefone, $mensagem) {
        // Este método cria um log para envio manual ou automático via webhook
        $this->criarLogEnvio($telefone, $mensagem);
        return true; // Considera como enviado para o log
    }
    
    /**
     * Criar mensagem personalizada do lead
     */
    private function criarMensagemLead($cliente, $lead) {
        $nome_cliente = explode(' ', $cliente['nome'])[0]; // Primeiro nome
        
        $mensagem = "🚚 *Portal Cegonheiro* 🚚\n\n";
        $mensagem .= "Olá *{$nome_cliente}*! 👋\n\n";
        $mensagem .= "🆕 *NOVO LEAD DISPONÍVEL!*\n\n";
        $mensagem .= "📋 *Detalhes do Transporte:*\n";
        $mensagem .= "🚗 Veículo: *{$lead['tipo_veiculo']}*\n";
        $mensagem .= "📅 Ano/Modelo: *{$lead['ano_modelo']}*\n";
        $mensagem .= "📍 Origem: *{$lead['cidade_origem']}*\n";
        $mensagem .= "🎯 Destino: *{$lead['cidade_destino']}*\n";
        $mensagem .= "📅 Data Prevista: *{$lead['data_prevista_formatada']}*\n";
        
        if ($lead['valor_veiculo'] && $lead['valor_veiculo'] > 0) {
            $valor_formatado = 'R\$ ' . number_format($lead['valor_veiculo'], 2, ',', '.');
            $mensagem .= "💰 Valor do Veículo: *{$valor_formatado}*\n";
        }
        
        $mensagem .= "\n⏰ *Cadastrado em:* {$lead['data_formatada']}\n\n";
        $mensagem .= "🔥 *Não perca esta oportunidade!*\n";
        $mensagem .= "Acesse o portal agora e envie sua cotação:\n\n";
        $mensagem .= "🌐 https://seudominio.com/leads_disponiveis.php\n\n";
        $mensagem .= "💡 *Dica:* Seja rápido! Os melhores leads são disputados.\n\n";
        $mensagem .= "---\n";
        $mensagem .= "Portal Cegonheiro - Conectando transportadoras e clientes\n";
        $mensagem .= "📱 Para parar de receber: /configuracoes";
        
        return $mensagem;
    }
    
    /**
     * Formatar telefone para WhatsApp
     */
    private function formatarTelefone($telefone) {
        // Remove tudo que não é número
        $telefone = preg_replace('/[^0-9]/', '', $telefone);
        
        // Adiciona código do país se não tiver
        if (strlen($telefone) === 11 && substr($telefone, 0, 1) !== '55') {
            $telefone = '55' . $telefone;
        } elseif (strlen($telefone) === 10 && substr($telefone, 0, 1) !== '55') {
            $telefone = '55' . $telefone;
        }
        
        return $telefone;
    }
    
    /**
     * Registrar notificação no banco
     */
    private function registrarNotificacao($cliente_id, $lead_id, $tipo, $status, $erro = null) {
        try {
            // Criar tabela se não existir
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS notificacoes_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    cliente_id INT NOT NULL,
                    lead_id INT NOT NULL,
                    tipo ENUM('email', 'whatsapp', 'push') NOT NULL,
                    status ENUM('enviado', 'erro', 'pendente') NOT NULL,
                    mensagem_erro TEXT NULL,
                    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_cliente_lead (cliente_id, lead_id),
                    INDEX idx_data_envio (data_envio)
                )
            ");
            
            $stmt = $this->pdo->prepare("
                INSERT INTO notificacoes_log (cliente_id, lead_id, tipo, status, mensagem_erro) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$cliente_id, $lead_id, $tipo, $status, $erro]);
            
        } catch (Exception $e) {
            error_log("Erro ao registrar notificação: " . $e->getMessage());
        }
    }
    
    /**
     * Criar log para envio manual
     */
    private function criarLogEnvio($telefone, $mensagem) {
        try {
            // Criar tabela de logs de envio se não existir
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS whatsapp_queue (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    telefone VARCHAR(20) NOT NULL,
                    mensagem TEXT NOT NULL,
                    status ENUM('pendente', 'enviado', 'erro') DEFAULT 'pendente',
                    tentativas INT DEFAULT 0,
                    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    data_envio TIMESTAMP NULL,
                    INDEX idx_status (status),
                    INDEX idx_data_criacao (data_criacao)
                )
            ");
            
            $stmt = $this->pdo->prepare("
                INSERT INTO whatsapp_queue (telefone, mensagem) VALUES (?, ?)
            ");
            $stmt->execute([$telefone, $mensagem]);
            
        } catch (Exception $e) {
            error_log("Erro ao criar log de envio: " . $e->getMessage());
        }
    }
    
    /**
     * Processar fila de WhatsApp (para execução via cron)
     */
    public function processarFilaWhatsApp($limite = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM whatsapp_queue 
                WHERE status = 'pendente' AND tentativas < 3
                ORDER BY data_criacao ASC 
                LIMIT ?
            ");
            $stmt->execute([$limite]);
            $mensagens = $stmt->fetchAll();
            
            $processadas = 0;
            
            foreach ($mensagens as $msg) {
                try {
                    // Tentar enviar via API
                    $sucesso = $this->enviarViaAPI($msg['telefone'], $msg['mensagem']);
                    
                    if ($sucesso) {
                        // Marcar como enviado
                        $stmt = $this->pdo->prepare("
                            UPDATE whatsapp_queue 
                            SET status = 'enviado', data_envio = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$msg['id']]);
                        $processadas++;
                    } else {
                        // Incrementar tentativas
                        $stmt = $this->pdo->prepare("
                            UPDATE whatsapp_queue 
                            SET tentativas = tentativas + 1 
                            WHERE id = ?
                        ");
                        $stmt->execute([$msg['id']]);
                    }
                    
                } catch (Exception $e) {
                    // Marcar como erro após 3 tentativas
                    $stmt = $this->pdo->prepare("
                        UPDATE whatsapp_queue 
                        SET status = 'erro', tentativas = tentativas + 1 
                        WHERE id = ?
                    ");
                    $stmt->execute([$msg['id']]);
                }
                
                // Delay entre envios
                usleep(1000000); // 1 segundo
            }
            
            return [
                'sucesso' => true,
                'processadas' => $processadas,
                'total' => count($mensagens)
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao processar fila WhatsApp: " . $e->getMessage());
            return ['sucesso' => false, 'erro' => $e->getMessage()];
        }
    }
}

// Função auxiliar para usar em outros arquivos
function notificarNovoLeadWhatsApp($lead_id) {
    global $pdo;
    
    try {
        $whatsapp = new WhatsAppNotificacao($pdo);
        return $whatsapp->notificarNovoLead($lead_id);
    } catch (Exception $e) {
        error_log("Erro na notificação WhatsApp: " . $e->getMessage());
        return ['sucesso' => false, 'erro' => $e->getMessage()];
    }
}
?>