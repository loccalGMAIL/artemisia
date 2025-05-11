<?php
$pageTitle = "Presupuesto";
ob_start();
?>

<div class="bg-white p-6 rounded-lg shadow">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-semibold">Crear Presupuesto</h2>
        <button onclick="openModal()"
            class="bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center hover:bg-blue-700">
            <span class="mr-2">+</span>
            Agregar Elemento
        </button>
    </div>

    <div class="overflow-x-auto">
        <table id="presupuestoTable" class="min-w-full bg-white">
            <thead>
                <tr class="bg-gray-50 text-left">
                    <th class="px-6 py-3 border-b border-gray-200">Elemento</th>
                    <th class="px-6 py-3 border-b border-gray-200">Detalles</th>
                    <th class="px-6 py-3 border-b border-gray-200">Costo</th>
                    <th class="px-6 py-3 border-b border-gray-200">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <!-- Elemento Fijo -->
                <tr id="elementoFijo" class="bg-gray-50">
                    <td class="px-6 py-4 border-b">Gestión de la cuenta, planificación y calendarización</td>
                    <td class="px-6 py-4 border-b">-</td>
                    <td class="px-6 py-4 border-b">$95.000,00</td>
                    <td class="px-6 py-4 border-b">
                        <!-- Sin botón de eliminar -->
                    </td>
                </tr>
                <!-- Los elementos se agregarán aquí dinámicamente -->
            </tbody>
            <tfoot>
                <tr class="font-bold">
                    <td class="px-6 py-3 border-t-2" colspan="2">Total</td>
                    <td id="totalCosto" class="px-6 py-3 border-t-2"></td>
                    <td class="px-6 py-3 border-t-2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Modal para agregar elemento -->
<div id="elementoModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-semibold mb-4">Agregar Elemento</h3>
            <form id="elementoForm">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipo de Elemento</label>
                        <select id="tipoElemento" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Seleccione un elemento</option>
                            <option value="post">Post Estático</option>
                            <option value="carrusel">Carrusel</option>
                            <option value="historia">Historia</option>
                            <option value="reel">Reel</option>
                        </select>
                    </div>

                    <!-- Opciones específicas para Post Estático -->
                    <div id="opcionesPost" class="hidden">
                        <label class="block text-sm font-medium text-gray-700">Cantidad de Posts</label>
                        <input type="number" id="cantidadPost" min="1" value="1"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>

                    <!-- Opciones específicas para Carrusel -->
                    <div id="opcionesCarrusel" class="hidden">
                        <label class="block text-sm font-medium text-gray-700">Cantidad de Placas</label>
                        <input type="number" id="placasCarrusel" min="2" max="10" value="2"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <p class="text-sm text-gray-500 mt-1">Máximo 10 placas</p>
                    </div>

                    <!-- Opciones específicas para Historia -->
                    <div id="opcionesHistoria" class="hidden">
                        <label class="block text-sm font-medium text-gray-700">Cantidad de Placas</label>
                        <input type="number" id="placasHistoria" min="1" max="12" value="1"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <p class="text-sm text-gray-500 mt-1">Máximo 12 placas</p>
                    </div>

                    <!-- Opciones específicas para Reel -->
                    <div id="opcionesReel" class="hidden">
                        <label class="block text-sm font-medium text-gray-700">Duración (segundos)</label>
                        <input type="number" id="segundosReel" min="15" max="90" value="15"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <p class="text-sm text-gray-500 mt-1">Máximo 90 segundos</p>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-4">
                    <button type="button" onclick="closeModal()"
                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Agregar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Precios base
    const precios = {
        post: 13500,
        carrusel: 7000,
        historia: 2500,
        reel: 7000
    };

    // Elementos adicionales
    const preciosPorPlaca = {
        carrusel: 6500,
        historia: 6500
    };
    const precioPorSegundo = 800;

    // Función para formatear números
    function formatearMoneda(numero) {
        return numero.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ".").replace(/\.(?=[^.]*$)/, ",");
    }

    // Función para eliminar elemento
    function eliminarElemento(boton) {
        const fila = boton.closest('tr');
        if (!fila.id || fila.id !== 'elementoFijo') {
            fila.remove();
            actualizarTotal();
        }
    }

    // Función para actualizar el total
    function actualizarTotal() {
        const filas = document.querySelectorAll('#presupuestoTable tbody tr');
        let total = 0;

        filas.forEach(fila => {
            const costoTexto = fila.querySelector('td:nth-child(3)').textContent;
            const costo = parseFloat(costoTexto.replace('$', '').replace(/\./g, '').replace(',', '.'));
            if (!isNaN(costo)) {
                total += costo;
            }
        });

        document.getElementById('totalCosto').textContent = `$${formatearMoneda(total)}`;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('elementoModal');
        const form = document.getElementById('elementoForm');
        const tipoSelect = document.getElementById('tipoElemento');
        const table = document.getElementById('presupuestoTable').getElementsByTagName('tbody')[0];

        // Inicializar el total al cargar la página
        actualizarTotal();

        // Mostrar/ocultar opciones según el tipo
        tipoSelect.addEventListener('change', function () {
            document.getElementById('opcionesPost').classList.toggle('hidden', this.value !== 'post');
            document.getElementById('opcionesCarrusel').classList.toggle('hidden', this.value !== 'carrusel');
            document.getElementById('opcionesHistoria').classList.toggle('hidden', this.value !== 'historia');
            document.getElementById('opcionesReel').classList.toggle('hidden', this.value !== 'reel');
        });

        // Función para calcular el costo
        function calcularCosto(tipo, cantidad = 1) {
            let costo = precios[tipo];

            if (tipo === 'carrusel' || tipo === 'historia') {
                costo += (cantidad) * preciosPorPlaca[tipo];
            } else if (tipo === 'reel') {
                costo += (cantidad) * precioPorSegundo;
            }

            return costo;
        }

        // Manejar el envío del formulario
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const tipo = tipoSelect.value;
            if (!tipo) {
                alert('Por favor seleccione un tipo de elemento');
                return;
            }

            let detalles = '';
            let cantidad = 1;
            let comentarios = '';

            switch (tipo) {
                case 'post':
                    cantidad = parseInt(document.getElementById('cantidadPost').value);
                    if (cantidad < 1) {
                        alert('La cantidad debe ser al menos 1');
                        return;
                    }
                    detalles = 'Post Estático';
                    const costo = calcularCosto(tipo);
                    
                    // Agregar la cantidad de filas especificada
                    for(let i = 0; i < cantidad; i++) {
                        const newRow = table.insertRow();
                        newRow.innerHTML = `
                            <td class="px-6 py-4 border-b">${detalles}</td>
                            <td class="px-6 py-4 border-b">1</td>
                            <td class="px-6 py-4 border-b">$${formatearMoneda(costo)}</td>
                            <td class="px-6 py-4 border-b">
                                <button onclick="eliminarElemento(this)" 
                                        class="text-red-600 hover:text-red-800">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </td>
                        `;
                    }
                    break;
                    
                case 'carrusel':
                    cantidad = parseInt(document.getElementById('placasCarrusel').value);
                    if (cantidad < 1 || cantidad > 10) {
                        alert('La cantidad de placas debe ser entre 1 y 10');
                        return;
                    }
                    detalles = `Carrusel - ${cantidad} placas`;
                    comentarios = 'Incluye redacción de copys con utilización de (hasta) 5 hashtags y publicación de piezas';
                    break;
                case 'historia':
                    cantidad = parseInt(document.getElementById('placasHistoria').value);
                    if (cantidad < 1 || cantidad > 12) {
                        alert('La cantidad de placas debe ser entre 1 y 12');
                        return;
                    }
                    detalles = `Historia - ${cantidad} placas`;
                    comentarios = 'Incluye publicación y programación de piezas';
                    break;
                case 'reel':
                    cantidad = parseInt(document.getElementById('segundosReel').value);
                    if (cantidad < 15 || cantidad > 90) {
                        alert('La duración debe ser entre 15 y 90 segundos');
                        return;
                    }
                    detalles = `Reel - ${cantidad} segundos`;
                    comentarios = 'Incluye redacción de copys con utilización de (hasta) 5 hashtags y publicación de piezas';
                    break;
            }

            if (tipo !== 'post') {
                const costo = calcularCosto(tipo, cantidad);
                const newRow = table.insertRow();
                newRow.innerHTML = `
                    <td class="px-6 py-4 border-b">${detalles}</td>
                    <td class="px-6 py-4 border-b text-sm">
                        ${cantidad + (tipo === 'reel' ? ' segundos' : ' placas')}
                        <br><span class="text-gray-500">${comentarios}</span>
                    </td>
                    <td class="px-6 py-4 border-b">$${formatearMoneda(costo)}</td>
                    <td class="px-6 py-4 border-b">
                        <button onclick="eliminarElemento(this)" 
                                class="text-red-600 hover:text-red-800">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </td>
                `;
            }

            actualizarTotal();
            closeModal();
            form.reset();
        });
    });

    function openModal() {
        document.getElementById('elementoModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('elementoModal').classList.add('hidden');
        document.getElementById('elementoForm').reset();
        document.getElementById('opcionesPost').classList.add('hidden');
        document.getElementById('opcionesCarrusel').classList.add('hidden');
        document.getElementById('opcionesPost').classList.add('hidden');
        document.getElementById('opcionesCarrusel').classList.add('hidden');
        document.getElementById('opcionesHistoria').classList.add('hidden');
        document.getElementById('opcionesReel').classList.add('hidden');
    }

    // Cerrar modal al hacer clic fuera de él
    window.onclick = function(event) {
        const modal = document.getElementById('elementoModal');
        if (event.target === modal) {
            closeModal();
        }
    }
</script>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>