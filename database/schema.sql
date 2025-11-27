-- Criar banco de dados
CREATE DATABASE IF NOT EXISTS dreambuilder_bd;
USE dreambuilder_bd;

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de lançamentos (horas de trabalho)
CREATE TABLE IF NOT EXISTS lancamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    data DATE NOT NULL,
    tipo_servico VARCHAR(50) NOT NULL,
    entrada TIME NOT NULL,
    saida TIME NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_data (usuario_id, data),
    INDEX idx_tipo_servico (tipo_servico)
);

-- Inserir usuário de teste
INSERT INTO usuarios (nome, email, senha) VALUES 
('Usuário Teste', 'teste@dreambuild.com', '$2y$10$YIjlrBxvYj5xQvJ5xQvJ5eK5xQvJ5xQvJ5xQvJ5xQvJ5xQvJ5xQvJ5');
-- Senha: 123456
