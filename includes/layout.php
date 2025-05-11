<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: index.html");
    exit();
}

function activePage($page) {
    $actual_link = basename($_SERVER['PHP_SELF']);
    return $actual_link == $page ? "bg-blue-100" : "";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>artemisia - <?php echo $pageTitle  ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->        
        <div class="w-64 bg-white shadow-lg">
    <!-- Logo y título -->
    <div class="p-4 border-b">
        <div class="flex items-center space-x-3">
            <img src="assets/images/artemisia_logo.png" alt="artemisia" class="w-12 h-12">
            <div>
                <h1 class="text-xl font-semibold text-gray-800">artemisia</h1>
                <!-- <h2 class="text-sm font-bold text-gray-600">Comercial Compras</h2> -->
            </div>
        </div>
    </div>
            
            <nav class="p-4">
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center p-2 text-gray-700 hover:bg-blue-50 rounded-lg <?php echo activePage('dashboard.php'); ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="presupuesto.php" class="flex items-center p-2 text-gray-700 hover:bg-blue-50 rounded-lg <?php echo activePage('remitos.php'); ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Presupuestos
                        </a>
                    </li>
                    <li>
                        <a href="#" class="flex items-center p-2 text-gray-700 hover:bg-blue-50 rounded-lg <?php echo activePage('proveedores.php'); ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            Clientes
                        </a>
                    </li>
                    <li>
                        <a href="#" class="flex items-center p-2 text-gray-700 hover:bg-blue-50 rounded-lg <?php echo activePage('productos.php'); ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            Productos
                        </a>
                    </li>
                    <li>
                        <a href="#" class="flex items-center p-2 text-gray-700 hover:bg-blue-50 rounded-lg <?php echo activePage('configuracion.php'); ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Configuración
                        </a>
                    </li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Top Bar -->
            <div class="bg-white shadow-sm">
                <div class="flex justify-between items-center px-8 py-4">
                    <h2 class="text-xl font-semibold"><?php echo $pageTitle ?? 'Dashboard'; ?></h2>
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-blue-600 rounded-full text-white flex items-center justify-center">
                            <?php echo substr($_SESSION['usuario'], 0, 1); ?>
                        </div>
                        <span class="ml-2"><?php echo $_SESSION['usuario']; ?></span>
                        <a href="logout.php" class="ml-4 text-sm text-gray-500 hover:text-gray-700">Cerrar Sesión</a>
                    </div>
                </div>
            </div>

            <!-- Page Content -->
            <div class="p-8">
                <?php echo $content ?? ''; ?>
            </div>
        </div>
    </div>

    <!-- Charts.js para las gráficas -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>