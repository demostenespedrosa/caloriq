<?php
// index.php (Página de Login)

// Inicia a sessão ANTES de qualquer output HTML
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Se o utilizador já estiver logado, redireciona para o dashboard
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: dashboard.php");
    exit;
}

require_once 'php_includes/config.php';

$email_login = ""; // Para repopular o campo email em caso de erro
$erros_login = [];
$mensagem_login_html = '';

// Verifica se há uma mensagem de sucesso do cadastro (via GET)
if (isset($_GET['cadastro']) && $_GET['cadastro'] == 'sucesso') {
    $mensagem_login_html = "<div class='mensagem sucesso anim-fade-in'>Cadastro realizado com sucesso! Faça o login para continuar.</div>";
}
if (isset($_GET['logout']) && $_GET['logout'] == 'sucesso') {
    $mensagem_login_html = "<div class='mensagem sucesso anim-fade-in'>Logout realizado com sucesso!</div>";
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email_input = trim(htmlspecialchars($_POST["email"]));
    $senha_input = trim($_POST["senha"]);

    if (empty($email_input)) {
        $erros_login[] = "Por favor, insira o seu email.";
    } else {
        $email_login = $email_input; // Para repopular
    }
    if (empty($senha_input)) {
        $erros_login[] = "Por favor, insira a sua senha.";
    }

    if (empty($erros_login)) {
        $sql = "SELECT id, nome, email, senha_hash FROM usuarios WHERE email = ?";
        if ($stmt = mysqli_prepare($conexao, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_email);
            $param_email = $email_input;

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    mysqli_stmt_bind_result($stmt, $id_usuario, $nome_usuario, $email_usuario_db, $hashed_password);
                    if (mysqli_stmt_fetch($stmt)) {
                        if (password_verify($senha_input, $hashed_password)) {
                            if (session_status() == PHP_SESSION_NONE) { session_start(); }
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id_usuario"] = $id_usuario;
                            $_SESSION["nome_usuario"] = $nome_usuario;
                            $_SESSION["email_usuario"] = $email_usuario_db; // Guardar o email da DB
                            header("location: dashboard.php");
                            exit;
                        } else {
                            $erros_login[] = "A senha que inseriu não é válida.";
                        }
                    }
                } else {
                    $erros_login[] = "Nenhuma conta encontrada com este email.";
                }
            } else {
                $erros_login[] = "Oops! Algo deu errado. Tente novamente mais tarde.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $erros_login[] = "Erro ao preparar a consulta de login: " . mysqli_error($conexao);
        }
    }

    if (!empty($erros_login)) {
        $mensagem_login_html = "<div class='mensagem erro'><strong>Falha no Login:</strong><ul>";
        foreach ($erros_login as $erro) {
            $mensagem_login_html .= "<li>" . htmlspecialchars($erro) . "</li>";
        }
        $mensagem_login_html .= "</ul></div>";
    }
}
if(isset($conexao) && $conexao instanceof mysqli) { mysqli_close($conexao); }
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login - CalorIQ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="icon" href="assets/icons/favicon.ico" type="image/x-icon">
    <style>
        :root { /* Tema CalorIQ */
            --cor-primaria: #A7F3D0; --cor-primaria-escura: #059669; --cor-primaria-muito-escura: #047857;
            --cor-fundo-app: #F0FDF4; --cor-texto-principal: #1F2937; --cor-texto-secundario: #4B5563;
            --cor-surface-container: #FFFFFF; --cor-outline: #D1D5DB; --cor-erro: #EF4444; --cor-sucesso: #10B981;
            --radius-card: 24px; /* Mais arredondado para o card de login/cadastro */
            --radius-input: 12px; --radius-botao: 20px;
            --sombra-card: 0 10px 15px -3px rgba(0,0,0,0.07), 0 4px 6px -4px rgba(0,0,0,0.07); /* Sombra mais pronunciada */
        }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; }
        body {
            font-family: 'Inter', 'Roboto', sans-serif; background-color: var(--cor-fundo-app);
            color: var(--cor-texto-principal); line-height: 1.6;
            display: flex; align-items: center; justify-content: center;
            padding: 20px env(safe-area-inset-left, 16px) 20px env(safe-area-inset-right, 16px);
            padding-top: env(safe-area-inset-top, 20px);
            padding-bottom: env(safe-area-inset-bottom, 20px);
        }
        .auth-container {
            background-color: var(--cor-surface-container);
            padding: 32px 28px; /* Mais padding */
            border-radius: var(--radius-card);
            box-shadow: var(--sombra-card);
            width: 100%;
            max-width: 400px;
            text-align: center;
            animation: fadeInScale 0.5s ease-out forwards;
        }
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .auth-header h1 {
            font-size: 2.5rem; /* Maior */
            font-weight: 700;
            color: var(--cor-primaria-escura);
            margin-bottom: 8px;
            /* Poderia adicionar uma imagem/logo aqui */
        }
        .auth-header p {
            font-size: 1rem;
            color: var(--cor-texto-secundario);
            margin-bottom: 28px;
        }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label {
            display: block; font-size: 0.9rem; font-weight: 500;
            color: var(--cor-texto-secundario); margin-bottom: 8px;
        }
        .form-group input[type="email"],
        .form-group input[type="password"] {
            width: 100%; padding: 14px 16px; /* Mais padding interno */
            border: 1px solid var(--cor-outline);
            border-radius: var(--radius-input);
            background-color: var(--cor-surface-container); /* Para contraste se o fundo do body mudar */
            font-size: 1rem; color: var(--cor-texto-principal);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus {
            outline: none; border-color: var(--cor-primaria-escura);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.2);
        }
        .btn-auth {
            background-color: var(--cor-primaria-escura); color: white;
            padding: 14px 24px; border: none; border-radius: var(--radius-botao);
            font-size: 1rem; font-weight: 600; text-decoration: none; text-align: center;
            display: block; width: 100%; cursor: pointer;
            transition: background-color 0.2s ease-out, transform 0.1s ease;
            margin-top: 24px;
        }
        .btn-auth:hover { background-color: var(--cor-primaria-muito-escura); }
        .btn-auth:active { transform: scale(0.98); }

        .auth-link { margin-top: 24px; font-size: 0.9rem; }
        .auth-link a {
            color: var(--cor-primaria-escura);
            text-decoration: none;
            font-weight: 600;
        }
        .auth-link a:hover { text-decoration: underline; }

        .mensagem { padding: 12px; border-radius: var(--radius-input); margin-bottom: 20px; font-size: 0.9rem; text-align: left; }
        .mensagem.sucesso { background-color: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0;}
        .mensagem.erro { background-color: #FEE2E2; color: var(--cor-erro); border: 1px solid #FCA5A5;}
        .mensagem ul { list-style-position: inside; padding-left: 5px; margin:0; }
        .anim-fade-in { animation: fadeInSimple 0.5s ease-out; }
        @keyframes fadeInSimple { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>
    <div class="auth-container">
        <header class="auth-header">
            <h1>CalorIQ</h1>
            <p>Bem-vindo(a) de volta! <br>Entre para continuar sua jornada.</p>
        </header>

        <?php if (!empty($mensagem_login_html)) echo $mensagem_login_html; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
            <div class="form-group">
                <label for="email">Seu Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_login); ?>" required placeholder="exemplo@email.com">
            </div>
            <div class="form-group">
                <label for="senha">Sua Senha</label>
                <input type="password" id="senha" name="senha" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn-auth">Entrar no CalorIQ</button>
        </form>

        <p class="auth-link">
            Ainda não tem conta? <a href="cadastro.php">Crie uma agora!</a>
        </p>
    </div>
</body>
</html>
