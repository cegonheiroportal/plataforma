<?php
// notificacoes.php
require_once 'config.php';

class NotificacaoManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Criar nova notificação
    public function criarNotificacao($usuario_id, $tipo, $titulo, $mensagem, $link = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, link) 
                VALUES (?, ?, ?, ?, ?)
            ");
            return $stmt->execute([$usuario_id, $tipo, $titulo, $mensagem, $link]);
        } catch (Exception $e) {
            error_log("Erro ao criar notificação: " . $e->getMessage());
            return false;
        }
    }
    
    // Buscar notificações não lidas
    public function buscarNaoLidas($usuario_id, $limite = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM notificacoes 
                WHERE usuario_id = ? AND lida = FALSE 
                ORDER BY data_criacao DESC 
                LIMIT ?
            ");
            $stmt->execute([$usuario_id, $limite]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erro ao buscar notificações: " . $e->getMessage());
            return [];
        }
    }
    
    // Contar notificações não lidas
    public function contarNaoLidas($usuario_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total 
                FROM notificacoes 
                WHERE usuario_id = ? AND lida = FALSE
            ");
            $stmt->execute([$usuario_id]);
            return $stmt->fetch()['total'];
        } catch (Exception $e) {
            error_log("Erro ao contar notificações: " . $e->getMessage());
            return 0;
        }
    }
    
    // Marcar como lida
    public function marcarComoLida($id, $usuario_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notificacoes 
                SET lida = TRUE, data_leitura = NOW() 
                WHERE id = ? AND usuario_id = ?
            ");
            return $stmt->execute([$id, $usuario_id]);
        } catch (Exception $e) {
            error_log("Erro ao marcar notificação como lida: " . $e->getMessage());
            return false;
        }
    }
    
    // Marcar todas como lidas
    public function marcarTodasComoLidas($usuario_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notificacoes 
                SET lida = TRUE, data_leitura = NOW() 
                WHERE usuario_id = ? AND lida = FALSE
            ");
            return $stmt->execute([$usuario_id]);
        } catch (Exception $e) {
            error_log("Erro ao marcar todas notificações como lidas: " . $e->getMessage());
            return false;
        }
    }
    
    // Buscar todas as notificações (com paginação)
    public function buscarTodas($usuario_id, $pagina = 1, $por_pagina = 20) {
        try {
            $offset = ($pagina - 1) * $por_pagina;
            $stmt = $this->pdo->prepare("
                SELECT * FROM notificacoes 
                WHERE usuario_id = ? 
                ORDER BY data_criacao DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$usuario_id, $por_pagina, $offset]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erro ao buscar todas notificações: " . $e->getMessage());
            return [];
        }
    }
    
    // Notificações automáticas para novos leads
    public function notificarNovoLead($lead_id) {
        try {
            // Buscar dados do lead
            $stmt = $this->pdo->prepare("SELECT * FROM leads WHERE id = ?");
            $stmt->execute([$lead_id]);
            $lead = $stmt->fetch();
            
            if (!$lead) return false;
            
            // Buscar todos os usuários transportadoras
            $stmt = $this->pdo->prepare("
                SELECT id FROM usuarios 
                WHERE nivel_acesso = 'cliente' AND tipo_cliente = 'pj' AND ativo = 1
            ");
            $stmt->execute();
            $transportadoras = $stmt->fetchAll();
            
            $titulo = "Novo Lead Disponível";
            $mensagem = "Novo lead de {$lead['cidade_origem']} para {$lead['cidade_destino']} - {$lead['tipo_veiculo']} {$lead['ano_modelo']}";
            $link = "lead_detalhes.php?id={$lead_id}";
            
            // Criar notificação para cada transportadora
            foreach ($transportadoras as $transportadora) {
                $this->criarNotificacao(
                    $transportadora['id'], 
                    'novo_lead', 
                    $titulo, 
                    $mensagem, 
                    $link
                );
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Erro ao notificar novo lead: " . $e->getMessage());
            return false;
        }
    }
    
    // Notificação para leads urgentes
    public function notificarLeadsUrgentes() {
        try {
            // Buscar leads que vencem em 24 horas
            $stmt = $this->pdo->prepare("
                SELECT * FROM leads 
                WHERE status = 'novo' 
                AND data_prevista BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
                AND id NOT IN (
                    SELECT DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(link, '=', -1), '&', 1) 
                    FROM notificacoes 
                    WHERE tipo = 'lead_urgente' 
                    AND data_criacao >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                )
            ");
            $stmt->execute();
            $leads_urgentes = $stmt->fetchAll();
            
            // Buscar transportadoras
            $stmt = $this->pdo->prepare("
                SELECT id FROM usuarios 
                WHERE nivel_acesso = 'cliente' AND tipo_cliente = 'pj' AND ativo = 1
            ");
            $stmt->execute();
            $transportadoras = $stmt->fetchAll();
            
            foreach ($leads_urgentes as $lead) {
                $titulo = "Lead Urgente - Vence em 24h";
                $mensagem = "Lead #{$lead['id']} de {$lead['cidade_origem']} para {$lead['cidade_destino']} vence em breve!";
                $link = "lead_detalhes.php?id={$lead['id']}";
                
                foreach ($transportadoras as $transportadora) {
                    $this->criarNotificacao(
                        $transportadora['id'], 
                        'lead_urgente', 
                        $titulo, 
                        $mensagem, 
                        $link
                    );
                }
            }
            
            return count($leads_urgentes);
        } catch (Exception $e) {
            error_log("Erro ao notificar leads urgentes: " . $e->getMessage());
            return 0;
        }
    }
}
?>