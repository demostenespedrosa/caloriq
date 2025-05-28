<?php
// php_includes/config.php

// --- DEFINIÇÕES DE CONEXÃO COM O BANCO DE DADOS ---
// Altere estas variáveis conforme a configuração do seu ambiente MySQL.
define('DB_SERVER', 'localhost');       // Endereço do servidor MySQL (geralmente 'localhost')
define('DB_USERNAME', 'root');          // Nome de utilizador do MySQL (padrão do XAMPP/WAMP é 'root')
define('DB_PASSWORD', '');              // Senha do MySQL (padrão do XAMPP/WAMP é vazia)
define('DB_NAME', 'caloriq');        // Nome do banco de dados que criamos para o CalorIQ

// --- TENTATIVA DE CONEXÃO ---
// Tenta estabelecer a conexão com o banco de dados MySQL usando as definições acima.
$conexao = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// --- VERIFICAÇÃO DA CONEXÃO ---
// Verifica se a conexão foi bem-sucedida.
if($conexao === false){
    // Se a conexão falhar, exibe uma mensagem de erro genérica e encerra o script.
    // Em um ambiente de produção, é crucial não exibir erros detalhados do MySQL
    // diretamente ao utilizador por questões de segurança. Em vez disso,
    // registe o erro num ficheiro de log no servidor.
    // Exemplo: error_log("Erro de conexão com o BD: " . mysqli_connect_error());
    die("Oops! Parece que estamos com problemas para nos conectar ao nosso sistema. Tente novamente mais tarde, por favor.");
}

// --- CONFIGURAÇÃO DO CHARSET ---
// Define o conjunto de caracteres para utf8mb4 para garantir o suporte adequado
// a uma vasta gama de caracteres, incluindo emojis e acentos diversos.
if (!mysqli_set_charset($conexao, "utf8mb4")) {
    // Se houver falha ao definir o charset, regista um erro.
    // Em produção, esta falha também deve ser logada.
    error_log("Erro ao definir o charset UTF-8 para a conexão MySQL: " . mysqli_error($conexao));
    // Considerar encerrar o script ou tratar o erro de forma mais robusta.
}

// --- CONFIGURAÇÃO OPCIONAL DE TIMEZONE ---
// Descomente a linha abaixo e ajuste para o fuso horário do seu servidor/aplicação
// se precisar de consistência em funções de data e hora do PHP.
// date_default_timezone_set('America/Sao_Paulo');

/*
    NOTAS IMPORTANTES:
    1.  SEGURANÇA: Em um ambiente de produção real, as credenciais do banco de dados
        (DB_USERNAME, DB_PASSWORD) NUNCA devem ser codificadas diretamente no ficheiro
        desta forma se o ficheiro estiver num diretório acessível pela web.
        Considere usar variáveis de ambiente do servidor ou um ficheiro de configuração
        localizado fora do diretório web raiz.
    2.  ERROS: A mensagem de erro `die()` é simplificada. Em produção, use um sistema
        de tratamento de erros mais robusto que registe os detalhes sem os expor.
    3.  INCLUSÃO: Este ficheiro (`config.php`) deve ser incluído no início de todos
        os scripts PHP que precisam de aceder à base de dados, usando `require_once`.
        Ex: `require_once 'php_includes/config.php';`
*/

?>
