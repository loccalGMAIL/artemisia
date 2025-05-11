<?php
$pageTitle = "Dashboard";
ob_start();
?>

<!-- Stats Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Productos -->
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Proyectos Actuales</p>
                <p class="text-2xl font-semibold mt-1">4</p>
            </div>
            <div class="p-3 rounded-full bg-blue-500">
                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Ventas del Mes -->
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Presupuestos Abiertos</p>
                <p class="text-2xl font-semibold mt-1">6</p>
            </div>
            <div class="p-3 rounded-full bg-green-500">
                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Reclamos Pendientes -->
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Clientes Redes</p>
                <p class="text-2xl font-semibold mt-1">3</p>
            </div>
            <div class="p-3 rounded-full bg-red-500">
                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
    </div>


</div>

<!-- Charts Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Ventas Chart -->
    <div class="bg-white p-6 rounded-lg shadow">
        <h3 class="text-lg font-semibold mb-4">Ventas Mensuales</h3>
        <canvas id="ventasChart" class="w-full" height="300"></canvas>
    </div>

    <!-- Actividad Reciente -->
    <div class="bg-white p-6 rounded-lg shadow">
        <h3 class="text-lg font-semibold mb-4">Actividad Reciente</h3>
        <div class="space-y-4">
            <!-- <div class="flex items-center">
                <div class="w-2 h-2 bg-blue-500 rounded-full mr-3"></div>
                <p class="text-sm">Nuevo remito creado - R003</p>
                <span class="ml-auto text-sm text-gray-500">Hace 2h</span>
            </div>
            <div class="flex items-center">
                <div class="w-2 h-2 bg-green-500 rounded-full mr-3"></div>
                <p class="text-sm">Reclamo resuelto - #123</p>
                <span class="ml-auto text-sm text-gray-500">Hace 3h</span>
            </div>
            <div class="flex items-center">
                <div class="w-2 h-2 bg-red-500 rounded-full mr-3"></div>
                <p class="text-sm">Nuevo reclamo - #124</p>
                <span class="ml-auto text-sm text-gray-500">Hace 5h</span>
            </div> -->
        </div>
    </div>
</div>

<script>
    // Inicializar gráfico de ventas
    const ctx = document.getElementById('ventasChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'],
            datasets: [{
                label: 'Ventas ($)',
                data: [400, 300, 600, 800, 500, 700],
                borderColor: '#3b82f6',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
</script>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>
<!-- Resto del contenido -->
</div>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>