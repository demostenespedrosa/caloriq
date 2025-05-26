<?php
// logout.php - Script para encerrar a sessão do utilizador no CalorIQ

// PASSO 1: Iniciar a sessão
// É crucial iniciar a sessão ANTES de qualquer tentativa de manipulá-la ou destruí-la.
// Mesmo que pareça contraintuitivo para um logout, é necessário para aceder às
// informações da sessão atual e destruí-la corretamente.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// PASSO 2: Limpar todas as variáveis de sessão
// A função `session_unset()` pode ser usada, mas atribuir um array vazio
// a `$_SESSION` é uma forma comum e eficaz de limpar todos os dados da sessão.
$_SESSION = array();

// PASSO 3: Destruir o cookie de sessão (Opcional, mas recomendado para segurança)
// Se a sua aplicação usa cookies para gerir sessões (o que é o padrão do PHP),
// é uma boa prática apagar o cookie de sessão do navegador do utilizador.
// Isto ajuda a garantir que a sessão não possa ser facilmente reestabelecida.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params(); // Obtém os parâmetros do cookie de sessão atual
    setcookie(
        session_name(),             // Nome do cookie de sessão (ex: PHPSESSID)
        '',                         // Define o valor do cookie como vazio
        time() - 42000,             // Define um tempo de expiração no passado para apagar o cookie
        $params["path"],            // Caminho do cookie
        $params["domain"],          // Domínio do cookie
        $params["secure"],          // Se o cookie só deve ser enviado por HTTPS
        $params["httponly"]         // Se o cookie não deve ser acessível por JavaScript
    );
}

// PASSO 4: Destruir a sessão no servidor
// Esta função remove todos os dados associados à sessão atual do armazenamento do servidor.
// É o passo mais importante para invalidar a sessão.
if (session_destroy()) {
    // A sessão foi destruída com sucesso.
    // Neste ponto, podemos redirecionar o utilizador.
} else {
    // Se session_destroy() falhar (raro, mas possível dependendo da configuração do servidor),
    // é bom registar um erro para depuração.
    error_log("Logout: Falha ao destruir a sessão para o utilizador.");
    // Mesmo com falha, tentaremos redirecionar.
}

// PASSO 5: Redirecionar o utilizador para a página de login
// Após o logout, o utilizador deve ser levado de volta à página de login.
// Adicionamos um parâmetro GET `?logout=sucesso` para que a página de login
// possa, opcionalmente, exibir uma mensagem de "Logout realizado com sucesso".
header("location: index.php?logout=sucesso");
exit; // Garante que nenhum código adicional seja executado após o redirecionamento.

/*
    NOTAS IMPORTANTES:
    1.  ORDEM DAS OPERAÇÕES: A ordem (iniciar sessão, limpar variáveis,
        destruir cookie, destruir sessão, redirecionar) é importante para um logout eficaz.
    2.  SEGURANÇA: Destruir o cookie de sessão ajuda a prevenir certos tipos de ataques
        de fixação de sessão, embora a destruição da sessão no servidor (`session_destroy()`)
        seja a medida de segurança primária.
    3.  FEEDBACK AO UTILIZADOR: O redirecionamento com `?logout=sucesso` permite que a página
        `index.php` (login) mostre uma mensagem amigável, confirmando que o logout
        foi bem-sucedido.
*/
?>
