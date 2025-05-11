<?php
// validar.php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];
    
    // Verificar credenciales estáticas
    if ($usuario === "juli" && $password === "123") {
        $_SESSION['usuario'] = $usuario;
        header("Location: dashboard.php");
        exit();
    } else {
        header("Location: index.html?error=1");
        exit();
    }
}
?>