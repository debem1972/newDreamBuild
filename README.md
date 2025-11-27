# 🏗️ DreamBuild - Gerenciamento de Horas de Trabalho

Um sistema web fullstack para gerenciamento de horas de trabalho, desenvolvido com PHP, MySQL, HTML, CSS, Bootstrap 5 e JavaScript.

## 📋 Funcionalidades

- **Autenticação de Usuários**: Login e registro com segurança
- **Dashboard Moderno**: Interface intuitiva e responsiva
- **Cadastro de Lançamentos**: Registre suas horas de trabalho com data, tipo de serviço, hora de entrada e saída
- **Cálculo Automático**: Cálculo automático de horas trabalhadas por dia
- **Filtros Avançados**: Filtre por data, tipo de serviço e período
- **Gráficos Interativos**: Visualize seus dados com gráficos em tempo real
- **Relatórios em PDF**: Gere relatórios personalizados em PDF
- **Análise de Dados**: Totais parciais e gerais de horas trabalhadas

## 🛠️ Stack Tecnológico

- **Backend**: PHP 7.4+
- **Banco de Dados**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, Bootstrap 5
- **JavaScript**: Chart.js para gráficos
- **PDF**: TCPDF para geração de relatórios

## 📦 Instalação

### Pré-requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Apache com mod_rewrite habilitado
- Composer

### Passos de Instalação

1. **Clone ou extraia o projeto**:
   ```bash
   /DreamBuild
   ```

2. **Instale as dependências**:
   ```bash
   composer install
   ```

3. **Configure o banco de dados**:
   - Abra seu cliente MySQL (phpMyAdmin, MySQL Workbench, etc.)
   - Execute o arquivo `database/schema.sql` para criar o banco de dados e tabelas
   - Ou execute via linha de comando:
     ```bash
     mysql -u root -p < database/schema.sql
     ```

4. **Configure o arquivo de conexão** (se necessário):
   - Edite `config/database.php` com suas credenciais MySQL

5. **Inicie o servidor Apache**:
   ```bash
   sudo service apache2 start
   ```

6. **Acesse a aplicação**:
   - Abra seu navegador e acesse: `http://localhost/DreamBuild`

## 🔐 Dados de Teste

Para testar a aplicação, use as seguintes credenciais:

- **Email**: teste@dreambuild.com
- **Senha**: 123456

## 📁 Estrutura do Projeto

```
DreamBuild/
├── public/              # Arquivos públicos (acessíveis via web)
│   ├── login.php       # Página de login
│   ├── signup.php      # Página de registro
│   ├── dashboard.php   # Dashboard principal
│   ├── gerar_relatorio.php  # Geração de PDF
│   └── logout.php      # Logout
├── app/                # Código da aplicação
│   ├── controllers/    # Controladores (para expansão futura)
│   ├── models/         # Modelos (para expansão futura)
│   └── views/          # Views (para expansão futura)
├── config/             # Configurações
│   ├── database.php    # Conexão com banco de dados
│   └── functions.php   # Funções utilitárias
├── database/           # Scripts SQL
│   └── schema.sql      # Schema do banco de dados
├── assets/             # Arquivos estáticos
│   ├── css/           # Estilos CSS
│   ├── js/            # Scripts JavaScript
│   └── images/        # Imagens
├── vendor/             # Dependências do Composer
├── .htaccess          # Configuração do Apache
├── composer.json      # Dependências do Composer
└── README.md          # Este arquivo
```

## 🚀 Como Usar

### 1. Fazer Login
- Acesse a página de login
- Use as credenciais de teste ou crie uma nova conta

### 2. Registrar Horas de Trabalho
- No dashboard, preencha o formulário "Novo Lançamento"
- Selecione a data, tipo de serviço, hora de entrada e saída
- Clique em "Registrar Lançamento"

### 3. Filtrar Dados
- Use os filtros para buscar por data, tipo de serviço ou período
- Os gráficos e tabelas se atualizam automaticamente

### 4. Gerar Relatório
- Clique no botão "Gerar Relatório em PDF"
- Preencha as informações do relatório
- O PDF será baixado automaticamente

## 📊 Tipos de Serviço

Os tipos de serviço disponíveis são:
- Garçom
- Forno
- Massa

Você pode adicionar mais tipos editando o arquivo `config/functions.php` na função `obterTiposServico()`.

## 🔧 Configurações

### Modificar Tipos de Serviço

Edite o arquivo `config/functions.php`:

```php
function obterTiposServico() {
    return array(
        'garçom' => 'Garçom',
        'forno' => 'Forno',
        'massa' => 'Massa',
        // Adicione novos tipos aqui
    );
}
```

### Modificar Credenciais do Banco de Dados

Edite o arquivo `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
define('DB_NAME', 'dreambuilder_bd');
```

## 🐛 Troubleshooting

### Erro de Conexão com o Banco de Dados
- Verifique se o MySQL está rodando
- Confirme as credenciais em `config/database.php`
- Certifique-se de que o banco de dados `dreambuilder_bd` foi criado

### Erro ao Gerar PDF
- Verifique se o Composer foi executado: `composer install`
- Certifique-se de que a biblioteca TCPDF foi instalada

### Erro 404 ao Acessar a Aplicação
- Verifique se o Apache está rodando
- Confirme se o mod_rewrite está habilitado
- Verifique o arquivo `.htaccess`

## 📝 Licença

Este projeto é fornecido como está, sem garantias.

## 👨‍💻 Desenvolvedor

Desenvolvido como um projeto de gerenciamento de horas de trabalho.

## 📧 Suporte

Para dúvidas ou problemas, consulte a documentação ou entre em contato com o desenvolvedor.

---

**Versão**: 1.0.0  
**Última Atualização**: 2025
